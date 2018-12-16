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
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use Application\View\Helper\Qualifier;

class PurchasebillController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }
    public function pbillAction(){
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
                $postParams = $request->getPost();
                $CostCentreId= $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $SupplierId= $this->bsf->isNullCheck($postParams['SupplierId'],'number');
                $select = $sql->select();
                $select->from(array("PR" => "MMS_PORegister"))
                    ->columns(array('PORegisterId'=>new Expression('PR.PORegisterId'),
                        'PODate'=>new Expression('CONVERT(VARCHAR(10),PR.PoDate,103)'),
                        'PONo'=>new Expression('PR.PONo'),
                        'CostCentreName'=>new Expression('CC.CostCentreName')))
                    ->join(array('PT' => 'MMS_POTrans'), 'PT.PORegisterID=PR.PoRegisterID', array(), $select::JOIN_INNER)
                    ->join(array('PPT' => 'MMS_POProjTrans'),'PPT.PoTransId=PT.PoTransId',array(),$select::JOIN_INNER)
                    ->join(array('CC' => 'WF_OperationalCostCentre'),'CC.CostCentreId=PPT.CostCentreId',array(),$select::JOIN_INNER)
                    ->where("PT.BalQty > 0 AND PT.LivePO=1 AND PR.Approve='Y' And PT.DCQty=0 And
                    PR.ShortClose =0  And PR.VendorId=$SupplierId And PR.CostCentreId=$CostCentreId
                    GROUP BY PR.PONo,PPT.CostCentreID,CC.CostCentreName,PR.VendorId,PR.PoDate,PR.PoRegisterId,PR.CurrencyId")
                     ->order("PR.PORegisterId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("PR" => "MMS_PORegister"))
                    ->columns(array(new Expression("PT.PoTransId,PT.PORegisterId,0 As Include,CAST(PT.POQty As Decimal(18,3)) As POQty,CAST(PT.BalQty As Decimal(18,3)) As Quantity,
                        Convert(Varchar(10),PR.PODate,103) As PODate,PR.PONo,Case When PT.ItemId>0 Then B.ItemCode + ' - ' + B.BrandName Else
                        R.Code +' - ' + R.ResourceName End As [Desc]")))
                    ->join(array("PT"=>'MMS_POTrans'),'PT.PORegisterID=PR.PoRegisterID',array(),$select::JOIN_INNER)
                    ->join(array("PPT"=>'MMS_POProjTrans'),'PPT.PoTransId=PT.PoTransId',array(),$select::JOIN_INNER)
                    ->join(array('CC' => 'WF_OperationalCostCentre'), 'CC.CostCentreId=PPT.CostCentreId', array(), $select::JOIN_INNER)
                    ->join(array('R'=>'Proj_Resource'),'PT.ResourceId=R.ResourceId',array(),$select::JOIN_INNER)
                    ->join(array('B'=>'MMS_Brand'),'PT.ResourceId=B.ResourceId And PT.ItemId=B.BrandId',array(),$select::JOIN_LEFT)
                    ->where("PT.BalQty > 0 AND PT.LivePO=1 AND PR.Approve='Y' And PT.DCQty=0 And PR.ShortClose =0 And PR.VendorId=$SupplierId And PR.CostCentreId=$CostCentreId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $requestResources)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            }
            $select = $sql->select();
            $select->from(array("a"=>"Vendor_Master"))
                ->columns(array('SupplierId'=>new Expression('a.VendorId'),'SupplierName'=>new Expression('a.VendorName'),'LogoPath'))
                ->where(array('Supply' => '1') );
            $SupplierStatement = $sql->getSqlStringForSqlObject($select);
            $SupplierResults = $dbAdapter->query($SupplierStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->Supplier = $SupplierResults;
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
    public function billentryAction(){
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
        $PVRegisterId=$this->params()->fromRoute('id');
        $flag = $this->params()->fromRoute('flag');

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postData = $request->getPost();
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

                    case 'branchdetails':
                        $BranchId = $this->bsf->isNullCheck($this->params()->fromPost('branchid'), 'number');
                        $select = $sql->select();
                        $select->from(array("A" => "Vendor_BranchContactDetail"))
                            ->columns(array(
                                'BranchTransId'=>new Expression('A.BranchTransId'),
                                'ContactNo'=>new Expression('b.Phone'),
                                'ContactPerson'=>new Expression('A.ContactPerson')
                            ))
                            ->join(array("b" => "Vendor_Branch"), "a.BranchId=b.BranchId",array(),$select::JOIN_LEFT)
                            ->where(array("b.BranchId =$BranchId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $contact= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($contact));
                        return $response;
                        break;

                    case 'contactDetails':
                        $BranchTransId = $this->bsf->isNullCheck($this->params()->fromPost('branchtransid'), 'number');
                        $select = $sql->select();
                        $select->from(array("A" => "Vendor_BranchContactDetail"))
                            ->columns(array(
                                'ContactNo'=>new Expression('A.ContactNo')
                            ))
                            ->where("BranchTransId = $BranchTransId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $contactno= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($contactno));
                        return $response;
                        break;

                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

                        $selSub2 = $sql -> select();
                        $selSub2->from(array("a" => "MMS_PVQualTrans"))
                            ->columns(array("QualifierId"));
                        $selSub2->where(array('a.PvRegisterId' => $POId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));


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
                        $select->from(array("c" => "MMS_PVQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
                                'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                                'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                                'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3)) '),'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);
                        $select->where(array('a.QualType' => 'M', 'c.PvRegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
                        $select -> combine($selSub1,"Union All");
                        $selMain = $sql -> select()->from(array('result'=>$select));
                        $selMain->order('SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($selMain);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($sHtml));
                        return $response;
                        break;

                    case 'getAddqualdetails':

                        $resId = $this->bsf->isNullCheck($this->params()->fromPost('resourceId'), 'number');
                        $itemId = $this->bsf->isNullCheck($this->params()->fromPost('itemId'), 'number');
                        $poTId = $this->params()->fromPost('poId');


                        $selSub2 = $sql->select();
                        $selSub2 ->from(array("a" => "MMS_POQualTrans"))
                            ->columns(array(new Expression("a.QualifierId as QualifierId")))
                            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.PoTransId", array(),$selSub2::JOIN_INNER);
                        $selSub2->where(array("b.PoTransId IN ($poTId) and b.ResourceId IN ($resId) AND b.ItemId IN ($itemId)"));

                        $selSub1 = $sql -> select();
                        $selSub1->from(array("a" => "Proj_QualifierTrans"))
                            ->columns(array('ResourceId'=>new Expression("'$resId'"),'ItemId'=>new Expression("'$itemId'"),
                                'QualifierId'=>new Expression('a.QualifierId'),'YesNo'=>new Expression("'0'"),
                                'Expression'=>new Expression('a.Expression'),'ExpPer'=>new Expression('a.ExpPer'),
                                'TaxablePer'=>new Expression('a.TaxablePer'),'TaxPer'=>new Expression('a.TaxPer'),
                                'Sign'=>new Expression('a.Sign'),'SurCharge'=>new Expression('a.SurCharge'),'EDCess'=>new Expression('a.EDCess'),
                                'HEDCess'=>new Expression('a.HEDCess'),'NetPer'=>new Expression('a.NetPer'),'BaseAmount'=>new Expression("'0'"),
                                'ExpressionAmt'=>new Expression("'0'"),'TaxableAmt'=>new Expression("'0'"),'TaxAmt'=>new Expression("'0'"),
                                'SurChargeAmt'=>new Expression("'0'"),'EDCessAmt'=>new Expression("'0'"),'HEDCessAmt'=>new Expression("'0'"),
                                'NetAmt'=>new Expression("'0'"),'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
                                'RefId'=>new Expression('b.RefNo'),'SortId'=>new Expression('a.SortId') ))
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId",array(),$selSub1::JOIN_INNER)
                            ->where->expression('a.QualType='."'M'".' and a.QualifierId NOT IN ?', array($selSub2));


                        $select = $sql->select();
                        $select->from(array("c" => "MMS_POQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),
                                'QualifierId'=>new Expression('c.QualifierId'),
                                'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                                'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                                'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),
                                'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),
                                'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(0 As Decimal(18,3)) '),
                                'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
                                'RefId'=>new Expression('b.RefNo'),'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)



                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("f" => "MMS_POTrans"), "c.POTransId=f.PoTransId", array(), $select::JOIN_INNER);
                        $select->where(array('a.QualType' => 'M', 'f.PoTransId IN('.$poTId.')', 'c.ResourceId IN('.$resId.')', 'c.ItemId IN('.$itemId.')'));

                        $selM1 = $sql -> select();
                        $selM1 -> from (array("a"=>"MMS_POQualTrans"))
                            ->columns(array(new Expression("a.QualifierId")))
                            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.PoTransId", array(),$selSub2::JOIN_INNER)
                            ->where(array("b.PoTransId IN ($poTId)and b.ResourceId IN ($resId) AND b.ItemId IN ($itemId) "));
                        $selM1->group(array(new Expression("a.QualifierId Having count(1)>1")));
                        $select -> where -> expression('(c.QualifierId NOT IN ?)',array($selM1));

                        $select -> combine($selSub1,"Union All");

                        $selMain = $sql -> select()->from(array('result'=>$select));
                        $selMain->order('SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($selMain);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($sHtml));
                        return $response;
                        break;
                    case 'wbs':
                        $CostCentre = $this->bsf->isNullCheck($postData['CostCenterId'], 'number');
                        $resourceid = $this->bsf->isNullCheck($postData['resourceid'], 'number');
                        $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');
                        $itemid = $this->bsf->isNullCheck($postData['itemid'], 'number');

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,
                            ParentText+'=>'+WbsName As WbsName,
                            CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
						   ->where(array("a.LastLevel" => "1", "b.CostCentreId" => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array('PORegisterId'=>new Expression('c.PORegisterId'),'POTransId'=>new Expression('a.POTransId'),
                                'POProjTransId'=>new Expression('b.POProjTransId'),'PONo'=>new Expression('c.PONo'),
                                'PODate'=>new Expression('Convert(Varchar(10),c.POdate,103)'),
                                'ResourceId'=>new Expression('a.ResourceId'),'ItemId'=>new Expression('a.ItemId'),
                                'POQty'=>new Expression('CAST(a.POQty As Decimal(18,3))'),
                                'BalQty'=>new Expression('CAST(a.BalQty As Decimal(18,3))'),
                                'Qty'=>new Expression('CAST(0 As Decimal(18,3))')))
                            ->join(array('b' => 'MMS_POProjTrans'), 'a.PoTransId=b.PoTransId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'), 'a.PORegisterId=c.PORegisterId', array(), $select::JOIN_INNER)
                            ->where('c.CostCentreId =' . $CostCentre .' and
                             a.ResourceId =' .$resourceid. 'and
                             a.ItemId =' .$itemid. ' and
                             c.VendorId =' .$VendorId. ' and a.BalQty>0 and c.Approve='."'Y'".' ');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('PORegisterId'=>new Expression('c.PORegisterId'),'POTransId'=>new Expression('c.POTransId'),
                                'POAnalTransId'=>new Expression('a.POAnalTransId'),'WBSName'=>new Expression("D.ParentText+'->'+D.WBSName"),
                                'POProjTransId'=>new Expression('b.POProjTransId'),
                                'WBSId'=>new Expression('d.WBSId'),
                                'ResourceId'=>new Expression('a.ResourceId'),'ItemId'=>new Expression('a.ItemId'),
                                'POQty'=>new Expression('CAST(A.POQty As Decimal(18,3))'),
                                'BalQty'=>new Expression('CAST(A.BalQty As Decimal(18,3))'),
                                'Qty'=>new Expression('CAST(0 As Decimal(18,3))')))
                            ->join(array('b' => 'MMS_POProjTrans'), 'a.POProjTransId=b.POProjTransId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'), 'b.POTransId=c.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'Proj_WBSMaster'), 'a.AnalysisId=d.WBSId', array(), $select::JOIN_INNER)
                            ->join(array('e' => 'mms_PORegister'), 'c.PORegisterId=e.PORegisterId', array(), $select::JOIN_LEFT)
							->join(array('f' => 'WF_OperationalCostCentre'),'d.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                            ->where('f.CostCentreId =' . $CostCentre .' and
                             a.ResourceId =' .$resourceid. 'and
                             a.ItemId =' .$itemid. ' and
                             e.VendorId =' .$VendorId. ' ');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //GET ESTIMATE QTY,ESTIMATE RATE <-> WBS
                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.WbsId')
                            ))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' .$CostCentre.' And ResourceId='.$resourceid.' And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$resourceid.') ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId='.$resourceid.' And
                        b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$resourceid.') GROUP BY a.ResourceId,a.AnalysisId');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId' => new Expression(''),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('a.ResourceId='.$resourceid.'  And c.CostCentreId='.$CostCentre.'
                        And c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$resourceid.')GROUP BY a.ResourceId,a.AnalysisId');
                        $sel2->combine($sel1,"Union ALL");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId='.$resourceid.' and c.CostCentreId='.$CostCentre.'
                         and c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$resourceid.')GROUP BY a.ResourceId,a.AnalysisId');
                        $sel3->combine($sel2,"Union ALL");


                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$resourceid.' And c.CostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$resourceid.')GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel4->combine($sel3,"Union ALL");


                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId='.$resourceid.' and c.ToCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel6::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId ='.$resourceid.' and c.FromCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where A.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and A.ResourceId ='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('A.ResourceId'),
                                'WbsId' => new Expression('A.WbsId') ))
                            ->group(new Expression("A.ResourceId,A.WbsId"));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$resourceid.' and c.CostCentreId='.$CostCentre.' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and
                                 a.ResourceId='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and
                        b.CostCentreId='.$CostCentre.' and a.ResourceId='.$resourceid.'    and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId='.$resourceid.'
                        and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and
                        a.ResourceId='.$resourceid.' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$resourceid.')
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,2))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,2))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'ResourceId' => new Expression('CAST(G.ResourceId As Int)'),'WbsId' => new Expression('CAST(G.WbsId As Int)')));
                        $sel12->group(new Expression("G.ResourceId,G.WbsId"));
                        $statement1 = $sql->getSqlStringForSqlObject($sel12);
                        $arr_west = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('po' => $resource_iows,'wbs' => $iow_requests,'wbss' => $arr_resource_wbs,'estwbs' => $arr_west)));
                        return $response;
                        break;

                    case 'getstockdetails':
                        $CCId = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $ResId = $this -> bsf ->isNullCheck($this->params()->fromPost('resourceid'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And c.General=0');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And B.CostCentreId='.$CCId .' And B.General=0 ');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('b.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.General=0 ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
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
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>"MMS_IssueRegister"),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel13::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' ');

                        $sel14 = $sql -> select();
                        $sel14 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("-1 * ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.'');
                        $sel14->combine($sel13,"Union ALL");

                        $sel15 = $sql -> select();
                        $sel15 -> from(array("A"=>$sel14))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty'=>new Expression("CAST(SUM(IssueQty) As Decimal(18,3))") ));
                        $sel15 -> combine($sel7,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'BalReqQty'=>new Expression("ISNULL(SUM(A.BalQty),0)"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.'');
                        $sel8->combine($sel15,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('b.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array(
                                'ResourceId' => new Expression("$ResId"),
                                'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                                'TotMinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0) As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBillQty),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRetQty),0) As Decimal(18,3))"),
                                'TransferQty'=>new Expression("CAST(ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(ISNULL(SUM(G.IssueQty),0) As Decimal(18,3))")
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
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' And WbsId='.$WBSId.' ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And d.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And c.CostCentreId='.$CCId .' And c.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and c.General=0 And a.AnalysisId='.$WBSId.' ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
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
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'BalReqQty'=>new Expression("ISNULL(SUM(A.BalQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and a.AnalysisId='.$WBSId.'');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
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
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
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
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRetQty),0) As Decimal(18,3))") ));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_stock_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock_wbs));
                        return $response;
                        break;

//                        $subWbs1=$sql->select();
//                        $subWbs1->from(array('a'=>'Proj_WBSMaster'))
//                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,0 As Qty")))
//                            ->where -> expression('a.LastLevel=1 And a.ProjectId='.$CostCentreId.'
//                        And a.WBSId NOT IN (Select AnalysisId From mms_PVAnalTrans Where PVTransId IN (Select PVTransId From MMS_PVTrans Where PVRegisterId=?))', $PVRegisterId);
//                        $selectAnal = $sql->select();
//                        $selectAnal->from(array("a" => "MMS_PVAnalTrans"))
//                            ->columns(array(
//                                'ResourceId' => new Expression('e.ResourceId'),
//                                'ItemId' => new Expression('e.ItemId'),
//                                'WbsId' => new Expression('c.WBSId'),
//                                'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
//                                'Qty' => new Expression('CAST(A.BillQty As Decimal(18,6))')))
//                            ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
//                            ->join(array("e" => "MMS_PVTrans"), " a.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_LEFT);
//                        $selectAnal->where(array("e.PVRegisterId = $PVRegisterId"));
//                        $selectAnal->combine($subWbs1, 'Union ALL');

                    case 'wbsEdit':
                        $CostCentre = $this -> bsf ->isNullCheck($this->params()->fromPost('CCId'),'number');
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $PVRegId = $this->bsf->isNullCheck($this->params()->fromPost('PVRegId'), 'number');

                        $select = $sql -> select();
                        $select -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('WbsId' => new Expression('a.AnalysisId'),'WbsName' =>new Expression("c.ParentText+'=>'+c.WBSName"),'Qty'=>new Expression("CAST(a.BillQty As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_PVTrans'), 'a.PVTransId=b.PVTransId',array(),$select::JOIN_INNER)
                            ->join(array('c' => 'Proj_WbsMaster'), 'a.AnalysisId=c.WbsId',array(),$select::JOIN_INNER)
                            ->where('b.PVRegisterId=' .$PVRegId. ' and a.ResourceId=' . $ResId . ' and a.ItemId=' .$ItemId . '');
                        $selR = $sql -> select();
                        $selR -> from (array('a' => 'MMS_PVAnalTrans'))
                            -> columns(array('AnalysisId' => new Expression("a.AnalysisId")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$selR::JOIN_INNER)
                            ->where('b.PVRegisterId='.$PVRegId. 'and a.ResourceId=' .$ResId. 'and a.ItemId='.$ItemId. '');
                        $select1 = $sql -> select();
                        $select1 -> from (array("a" => "Proj_WBSMaster"))
                            ->columns(array('WbsId' => new Expression('a.WbsId'),'WbsName' => new Expression("a.ParentText+'=>'+a.WBSName"),'Qty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$select1::JOIN_INNER)
							->where('a.LastLevel=1 and b.CostCentreId='.$CostCentre.' ');
                        $select1 -> where -> expression('(a.WbsId NOT IN ?)',array($selR));
                        $select->combine($select1,'Union ALL');

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.WbsId')
                            ))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' .$CostCentre.' And ResourceId=' .$ResId. ' And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId =' .$ResId. ') ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId =' .$ResId. ' And
                        b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ') GROUP BY a.ResourceId,a.AnalysisId');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('a.ResourceId=' .$ResId. '  And c.CostCentreId='.$CostCentre.'
                        And c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId =' .$ResId. ')GROUP BY a.ResourceId,a.AnalysisId');
                        $sel2->combine($sel1,"Union ALL");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId=' .$ResId. ' and c.CostCentreId='.$CostCentre.'
                                 and c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')GROUP BY a.ResourceId,a.AnalysisId');
                        $sel3->combine($sel2,"Union ALL");


                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId=' .$ResId. ' And c.CostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel4->combine($sel3,"Union ALL");


                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId=' .$ResId. ' and c.ToCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                                'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel6::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId=' .$ResId. ' and c.FromCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where A.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and A.ResourceId=' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('A.ResourceId'),
                                'WbsId' => new Expression('A.WbsId') ))
                            ->group(new Expression("A.ResourceId,A.WbsId"));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId=' .$ResId. ' and c.CostCentreId='.$CostCentre.' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and
                                 a.ResourceId =' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and
                        b.CostCentreId='.$CostCentre.' and a.ResourceId=' .$ResId. '    and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('a.ResourceId'),
                                'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId=' .$ResId. '
                        and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                                'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and
                        a.ResourceId=' .$ResId. ' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId=' .$ResId. ')
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'ResourceId' => new Expression('G.ResourceId'),'WbsId' => new Expression('G.WbsId')));
                        $sel12->group(new Expression("G.ResourceId,G.WbsId"));
                        $statement1 = $sql->getSqlStringForSqlObject($sel12);
                        $arr_wbsestimate = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_wbs,'estwbs' => $arr_wbsestimate)));
                        return $response;
                        break;

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }

//                $select1 -> from (array("a" => "Proj_WBSMaster"))
//                    ->columns(array('WBSId' => new Expression('a.WbsId'),'WbsName' => new Expression("a.ParentText+'=>'+a.WBSName"),'Qty'=>new Expression("CAST(0 As Decimal(18,2))")))
//                    ->where('a.LastLevel=1 and a.ProjectId='.$CostCentre.' ');
//                $select1 -> where -> expression('(a.WbsId NOT IN ?)',array($selR));
//                $select->combine($select1,'Union ALL');
//
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                $this->_view->setTerminal(true);
//                $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_wbs)));
//                return $response;
//                break;
            }

        } else  {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $OrderType = $this->bsf->isNullCheck($postData['OrderType'], 'string');
                $PurTypeId = $this->bsf->isNullCheck($this->params()->fromPost('ptypeId'), 'number');
                if (!is_null($postData['frm_index'])) {
                    $Supplier = $this->bsf->isNullCheck($postData['Supplier'], 'number');
                    $CostCentre = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                    $gridtype = $this->bsf->isNullCheck($postData['gridtype'], 'number');
                    $PVTransIds =$this->params()->fromPost('poTransIds');
                    if($PVTransIds == ""){
                        $PVTransIds = 0;
                    }else{
                        $PVTransIds = trim(implode(',',$this->params()->fromPost('poTransIds')));
                    }
//                    $PVTransIds = implode(',', $postData['POTransIds']);
                    $this->_view->OrderType = $OrderType;
                    $this->_view->gridtype = $gridtype;
                    $this->_view->poTransIds = $PVTransIds;
					//General
                    $voNo = CommonHelper::getVoucherNo(306, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->vNo = $voNo;
                    $vNo=$voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    if($flag == 1){
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("b.CostCentreId as CostCentreId,b.VendorId as VendorId")))
                            ->join(array("b" => "MMS_PORegister"), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                            ->where('a.PoTransId IN(' .$PVTransIds. ')');
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $cvName = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CostCentre = $this->bsf->isNullCheck($cvName['CostCentreId'],'number');
                        $Supplier = $this->bsf->isNullCheck($cvName['VendorId'],'number');
                    }

					$select = $sql->select();
					$select->from(array('a' => 'WF_OperationalCostCentre'))
						->columns(array('CompanyId'))
						->where("CostCentreId=$CostCentre");
					$statement = $sql->getSqlStringForSqlObject($select);
					$Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$CompanyId=$Comp['CompanyId'];

					//CostCentreId
					$CCPurchase = CommonHelper::getVoucherNo(306, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                    $this->_view->CCPurchase = $CCPurchase;
                    $CCPVNo=$CCPurchase['voucherNo'];
                    $this->_view->CCPVNo = $CCPVNo;

					//CompanyId
					$CPurchase = CommonHelper::getVoucherNo(306, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CPurchase = $CPurchase;
                    $CPVNo=$CPurchase['voucherNo'];
                    $this->_view->CPVNo = $CPVNo;

                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where("Deactivate=0 AND CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from(array("a"=>"Vendor_Master"))
                        ->columns(array(new expression('a.VendorId as SupplierId'), new expression('a.VendorName as SupplierName'), 'LogoPath'))
                        ->where("VendorId=$Supplier");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Suppliers = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_GatePass "))
                        ->columns(array("GateRegId","GatePassNo"))
                        ->where(array("a.CostCentreId= $CostCentre And a.SupplierId= $Supplier "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_gatepass = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from("WF_CurrencyMaster")
                        ->columns(array('CurrencyId','CurrencyName'))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;

                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array("Default"),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->order("b.Default Desc")
                        ->where('c.CostCentreId='.$CostCentre.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array('data'=>new Expression('A.AccountId'),'value'=>new Expression('A.AccountName')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$PurTypeId));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    // get resource lists
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POProjTrans"))
                        ->columns(array(
                            'ItemId'=>new Expression(' b.ItemId'),
                            'ResourceId'=>new Expression('b.ResourceId'),
                            'Desc'=>new Expression("Case When b.ItemId>0 Then '('+d.ItemCode+')'+ ' ' +d.BrandName Else '('+c.Code+')'+ ' '+c.ResourceName End"),
                            'Qty'=>new Expression('CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))'),
                            'Rate'=>new Expression('Case When b.ItemId>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(c.Rate As Decimal(18,2)) End'),
                            'QRate'=>new Expression('Case When b.ItemId>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(c.Rate As Decimal(18,2)) End'),
                            'BaseAmount'=>new Expression('CAST(0 As Decimal(18,2))'),
                            'Amount'=>new Expression('CAST(0 As Decimal(18,2))'),
                            'UnitName'=>new Expression('Case When b.ItemId>0 Then f.UnitName Else e.UnitName End'),
                            'UnitId'=>new Expression('Case When b.ItemId>0 Then f.UnitId Else e.UnitId End'),
                            'RFrom'=>new Expression('Case When b.ResourceId IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId='.$CostCentre.') Then '."'Project'".' Else '."'Library'".' End ')))
                        ->join(array('b' => 'MMS_POTrans'), 'b.POTransId=a.POTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_Resource'), 'b.ResourceId=c.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_Brand'), 'b.ItemId=d.BrandId and b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=E.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_UOM'), 'd.UnitId=F.UnitId', array(), $select::JOIN_LEFT)
                        ->where("b.PoTransId IN ($PVTransIds) Group By b.ResourceId,b.ItemId,d.ItemCode,d.BrandName,c.Code,
                        c.ResourceName,d.Rate,c.Rate,f.UnitName,f.UnitId,e.UnitName,e.UnitId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POTrans"))
                        ->columns(array('PORegisterId'=>new Expression('c.PORegisterId'),
                            'POTransId'=>new Expression('a.POTransId'),
                            'POProjTransId'=>new Expression('b.POProjTransId'),
                            'PONo'=>new Expression('c.PONo'),
                            'PODate'=>new Expression('Convert(Varchar(10),C.POdate,103)'),
                            'ResourceId'=>new Expression('a.ResourceId'),
                            'ItemId'=>new Expression('a.ItemId'),
                            'POQty'=>new Expression('CAST(A.POQty As Decimal(18,3))'),
                            'BalQty'=>new Expression('CAST(A.BalQty As Decimal(18,3))'),
                            'Qty'=>new Expression('CAST(A.BalQty As Decimal(18,3))')))
                        ->join(array('b' => 'MMS_POProjTrans'), 'a.PoTransId=b.PoTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'), 'a.PORegisterId=c.PORegisterId', array(), $select::JOIN_INNER)
                        ->where('b.PoTransId IN (' . $PVTransIds . ')');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
						->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('PORegisterId'=>new Expression('c.PORegisterId'),
                            'POTransId'=>new Expression('c.POTransId'),
                            'POAnalTransId'=>new Expression('a.POAnalTransId'),
                            'WBSName'=>new Expression("D.ParentText+'->'+D.WBSName"),
                            'POProjTransId'=>new Expression('b.POProjTransId'),
                            'WBSId'=>new Expression('d.WBSId'),
                            'ResourceId'=>new Expression('a.ResourceId'),
                            'ItemId'=>new Expression('a.ItemId'),
                            'POQty'=>new Expression('CAST(A.POQty As Decimal(18,3))'),
                            'BalQty'=>new Expression('CAST(A.BalQty As Decimal(18,3))'),
                            'Qty'=>new Expression('CAST(A.BalQty As Decimal(18,3))'),
                            'wHideQty'=>new Expression('CAST(0 As Decimal(18,3))')))
                        ->join(array('b' => 'MMS_POProjTrans'), 'a.POProjTransId=b.POProjTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'), 'b.POTransId=c.POTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'Proj_WBSMaster'), 'a.AnalysisId=d.WBSId', array(), $select::JOIN_INNER)
                        ->where('c.PoTransId IN (' . $PVTransIds . ')');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                    $this->_view->qualList = $qualList;

                        $select = $sql->select();
                        $select->from(array("A" => "WF_TermsMaster"))
                            ->columns(array(
                                'TermsId'=>new Expression('A.TermsId'),
                                'Terms'=>new Expression('A.Title'),
                                'Value'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'Account'=>new Expression("ISNULL(A.AccountId,0)"),
                            ))
                            ->where("TermType='S' And A.AccountUpdate=1");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->terms_ent= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("A" => "FA_AccountMaster"))
                            ->columns(array(
                                'AccountId'=>new Expression('A.AccountId'),
                                'AccountName'=>new Expression('A.AccountName')
                            ))
                            ->where("A.LastLevel='Y' And A.TypeId=22");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->Account= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("A" => "Vendor_Branch"))
                        ->columns(array(
                            'BranchId'=>new Expression('A.BranchId'),
                            'BranchName'=>new Expression('A.BranchName')
                        ))
                        ->where("VendorId = $Supplier");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $branch= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->branch=$branch;

                    //auto complete process
                    $sQuery = $sql->select();
                    $sQuery->from("MMS_POTrans")
                        ->columns(array('ResourceId','ItemId'))
                        ->where('POTransId IN (' . $PVTransIds . ')');
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

                    if($resIDS == ""){
                        $resIDS = 0;
                    }
                    if($itmIDS == ""){
                        $itmIDS = 0;
                    }

                    if($resIDS == 0) {

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_Resource"))
                            ->columns(array('data' => new Expression('a.ResourceId'), "AutoFlag" => new Expression("1-1"), 'ItemId' => new Expression('isnull(d.BrandId,0)'),
                                'Code' => new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
                                'value' => new Expression("Case When isnull(d.BrandId,0)>0 Then (d.ItemCode + ' ' + d.BrandName) Else (a.Code + ' ' +a.ResourceName) End"),
                                'UnitName' => new Expression('c.UnitName'), 'UnitId' => new Expression('c.UnitId'),
                                'Rate' => new Expression("Case when isnull(d.BrandId,0)>0 Then CAST(d.Rate as Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End"),
                                'RFrom' => new Expression("'Project'")))
                            ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $select::JOIN_LEFT)
                            ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
                            ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
                            ->join(array("e" => "Proj_ProjectResource"), "a.ResourceId=e.ResourceId", array(), $select::JOIN_INNER)
							->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                            ->where("f.CostCentreId=" . $CostCentre . "  ");

                        $selRa = $sql->select();
                        $selRa->from(array("a" => "Proj_Resource"))
                            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                            Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                            Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                            Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                            Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                            Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) Else CAST(a.Rate As Decimal(18,2)) End As Rate,
                            'Library' As RFrom ")))
                            ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $selRa::JOIN_LEFT)
                            ->join(array("c" => "MMS_Brand"), "a.ResourceId=c.ResourceId", array(), $selRa::JOIN_LEFT)
                            ->join(array("d" => "Proj_Uom"), "a.UnitId=d.UnitId", array(), $selRa::JOIN_LEFT)
                            ->join(array("e" => "Proj_Uom"), "c.UnitId=e.UnitId", array(), $selRa::JOIN_LEFT)
                            ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                            Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentre . ") ");

                        $select->combine($selRa, "Union All");
                    } else {
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_Resource"))
                            ->columns(array('data' => new Expression('a.ResourceId'), "AutoFlag" => new Expression("1-1"), 'ItemId' => new Expression('isnull(d.BrandId,0)'),
                                'Code' => new Expression("Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End"),
                                'value' => new Expression("Case When isnull(d.BrandId,0)>0 Then '('+d.ItemCode+')'+ ' ' + d.BrandName Else '('+a.Code+')'+ ' ' +a.ResourceName End"),
                                'UnitName' => new Expression('c.UnitName'), 'UnitId' => new Expression('c.UnitId'),
                                'Rate' => new Expression("Case when isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End"),
                                'RFrom' => new Expression("'Project'")))
                            ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $select::JOIN_LEFT)
                            ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
                            ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
                            ->join(array("e" => "Proj_ProjectResource"), "a.ResourceId=e.ResourceId", array(), $select::JOIN_INNER)
							->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                            ->where("f.CostCentreId=" . $CostCentre . " and (a.ResourceId NOT IN ($resIDS) Or isnull(d.BrandId,0) NOT IN ($itmIDS)) ");

                        $selRa = $sql->select();
                        $selRa->from(array("a" => "Proj_Resource"))
                            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                            Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                            Case when isnull(c.BrandId,0)>0 Then '('+c.ItemCode+')'+ ' ' + c.BrandName Else '('+a.Code+')'+' ' + a.ResourceName End As value,
                            Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                            Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                            Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) Else CAST(a.Rate As Decimal(18,2)) End As Rate,
                            'Library' As RFrom ")))
                            ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $selRa::JOIN_LEFT)
                            ->join(array("c" => "MMS_Brand"), "a.ResourceId=c.ResourceId", array(), $selRa::JOIN_LEFT)
                            ->join(array("d" => "Proj_Uom"), "a.UnitId=d.UnitId", array(), $selRa::JOIN_LEFT)
                            ->join(array("e" => "Proj_Uom"), "c.UnitId=e.UnitId", array(), $selRa::JOIN_LEFT)
                            ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                            Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $CostCentre . ") and
                            (a.ResourceId NOT IN ($resIDS) Or isnull(c.BrandId,0) NOT IN ($itmIDS))  ");

                        $select->combine($selRa, "Union All");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array("PR" => "MMS_PORegister"))
                        ->columns(array('ResourceId'=>new Expression("R.ResourceId")))
                        ->join(array("PT"=>'MMS_POTrans'),'PT.PORegisterID=PR.PoRegisterID',array(),$select::JOIN_INNER)
                        ->join(array("PPT"=>'MMS_POProjTrans'),'PPT.PoTransId=PT.PoTransId',array("PoTransId"),$select::JOIN_INNER)
                        ->join(array('CC' => 'WF_OperationalCostCentre'), 'CC.CostCentreId=PPT.CostCentreId', array(), $select::JOIN_INNER)
                        ->join(array('R'=>'Proj_Resource'),'PT.ResourceId=R.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('B'=>'MMS_Brand'),'PT.ResourceId=B.ResourceId And PT.ItemId=B.BrandId',array(),$select::JOIN_LEFT)
                        ->where("PT.BalQty<>0 AND PT.LivePO=1 AND PR.Approve='Y' And PT.DCQty=0 And PR.ShortClose =0 And PR.VendorId=$Supplier And PR.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,3)) Qty,CAST(0 As Decimal(18,3)) HiddenQty")))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array('b.CostCentreId=' . $CostCentre . ' And c.LastLevel=1'));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $today=date('Y/m/d');
                    $select = $sql->select();
                    $select->from(array("A" => "MMS_PORegister"))
                        ->columns(array(
                            'PORegisterId' => new Expression('A.PORegisterId'),
                            'PONo' => new Expression('A.PONo'),
                            'TermsId' => new Expression('B.TermsId'),
                            'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                            'Title' => new Expression('C.Title'),
                            'Amount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                            'PaidAmount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                            'Balance' => new Expression("CAST(B.Value-B.AdjustAmount As Decimal(18,2))"),
                            'DeductionBalance' => new Expression("Cast(B.AdjustAmount As Decimal(18,2))"),
                            'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'HiddenAmount' => new Expression("Cast(0 As Decimal (18,2))")
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'WF_TermsMaster'),'B.TermsId=C.TermsId',array(),$select::JOIN_INNER)
                        ->where(array("C.Title IN ('Advance','Against Delivery','Against Test Certificate') AND  A.VendorId=$Supplier And CostCentreId=$CostCentre AND CAST(B.Value-B.AdjustAmount As Decimal(18,3))>0
                         And A.PODate <='$today'  GROUP BY A.PORegisterId,B.TermsId, A.PONo, A.PODate,C.Title,B.Value,B.AdjustAmount "));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_advance = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "FA_AccountMaster"))
                        ->columns(array('AccountId','AccountName','TypeId'));
                    $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Get EstimateQty,EstimateRate,AvailableQty

                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('EstimateQty' => new Expression('CAST(a.Qty As Decimal(18,3))'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"), 'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
						->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentre .' And a.ResourceId IN (select a.ResourceId from mms_potrans a
                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . '))');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId IN (select a.ResourceId from mms_potrans a
                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . '))
                         And b.CostCentreId='.$CostCentre.' And c.General=0 GROUP BY a.ResourceId ');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN (select a.ResourceId from mms_potrans a
                                inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . ')) And
                                B.CostCentreId='.$CostCentre .' And B.General=0 GROUP BY a.ResourceId ');
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('b.ThruPO='."'Y'".' And a.ResourceId IN (select a.ResourceId from mms_potrans a
                                inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . ')) and
                                 b.CostCentreId='.$CostCentre.' and b.General=0 GROUP BY a.ResourceId ');
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (select a.ResourceId from mms_potrans a
                                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . ')) And
                                        b.CostCentreId='.$CostCentre.' GROUP BY a.ResourceId');
                    $sel4->combine($sel3,"Union ALL");
                   // echo $statement = $sql->getSqlStringForSqlObject($sel4); die;

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (select a.ResourceId from mms_potrans a
                                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId
                                        where a.PoTransId IN (' . $PVTransIds . ')) and
                                        b.ToCostCentreId='.$CostCentre.' GROUP BY a.ResourceId');
                    //$sel5->combine($sel4,"Union ALL");

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferTrans"))
                        ->columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (select a.ResourceId from mms_potrans a
                                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId where a.PoTransId IN (' . $PVTransIds . ')) and
                                         b.FromCostCentreId='.$CostCentre.' GROUP BY a.ResourceId');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("A.ResourceId")));
                    $sel7->group(new Expression("a.ResourceId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (select a.ResourceId from mms_potrans a
                                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId
                                        where a.PoTransId IN (' . $PVTransIds . ')) and
                                         b.CostCentreId='.$CostCentre.' GROUP BY a.ResourceId');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentre.' and
                                        a.ResourceId IN (select a.ResourceId from mms_potrans a
                                        inner join MMS_POProjTrans b on a.PoTransId=b.POTransId
                                        where a.PoTransId IN (' . $PVTransIds . ')) GROUP BY a.ResourceId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('b.General=0 and b.CostCentreId='.$CostCentre.' and
                                           a.ResourceId IN (select a.ResourceId from mms_potrans a
                                           inner join MMS_POProjTrans b on a.PoTransId=b.POTransId
                                           where a.PoTransId IN (' . $PVTransIds . '))GROUP BY a.ResourceId');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentre.' and
                                a.ResourceId IN (select a.ResourceId from mms_potrans a
                                 inner join MMS_POProjTrans b on a.PoTransId=b.POTransId
                                 where a.PoTransId IN (' . $PVTransIds . ')) GROUP BY a.ResourceId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array(
                            'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                            'TotMinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0) As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBillQty),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression("G.ResourceId"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))")
                        ));
                    $sel12->group(new Expression("G.ResourceId"));
                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //GET ESTIMATE QTY,ESTIMATE RATE <-> WBS
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.WbsId')
                             ))
							->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' .$CostCentre.' And ResourceId IN(' .$resIDS. ') And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.')) ');


                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId IN(' .$resIDS. ') And
                        b.CostCentreId='.$CostCentre.' And d.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.')) GROUP BY a.ResourceId,a.AnalysisId');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$resIDS.')  And c.CostCentreId='.$CostCentre.'
                        And c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))GROUP BY a.ResourceId,a.AnalysisId');
                    $sel2->combine($sel1,"Union ALL");


                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN ('.$resIDS.') and c.CostCentreId='.$CostCentre.'
                         and c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))GROUP BY a.ResourceId,a.AnalysisId');
                    $sel3->combine($sel2,"Union ALL");


                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$resIDS.') And c.CostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel4->combine($sel3,"Union ALL");


                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$resIDS.') and c.ToCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId ');

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                         'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel6::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$resIDS.') and c.FromCostCentreId='.$CostCentre.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where A.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and A.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('A.ResourceId'),
                            'WbsId' => new Expression('A.WbsId') ))
                         ->group(new Expression("A.ResourceId,A.WbsId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN ('.$resIDS.') and c.CostCentreId='.$CostCentre.' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and
                                 a.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and
                        b.CostCentreId='.$CostCentre.' and a.ResourceId IN ('.$resIDS.')    and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId IN ('.$resIDS.')
                        and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and
                        a.ResourceId IN ('.$resIDS.') and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN ('.$resIDS.'))
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression('G.ResourceId'),'WbsId' => new Expression('G.WbsId')));
                    $sel12->group(new Expression("G.ResourceId,G.WbsId"));
                    $statement1 = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            else {
                if($PVRegisterId != 0){
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->join(array('b'=>'MMS_PVRegister'),'a.CostCentreId=b.CostCentreId',array(),$select::JOIN_INNER)
                        ->where("a.Deactivate=0 AND b.PVRegisterId=$PVRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costCenterIds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->costcenter=$costCenterIds;
                    $costCentreId=$costCenterIds['CostCentreId'];
                    // vendor details
                    $select = $sql->select();
                    $select->from(array('a'=>'Vendor_Master'))
                        ->columns(array('SupplierId'=> new Expression('a.VendorId') ,'SupplierName'=> new Expression('a.VendorName'), 'LogoPath'))
                        ->join(array('b'=>'MMS_PVRegister'),'a.VendorId=b.VendorId')
                        ->where("b.PVRegisterId=$PVRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $supplierIds= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->Suppliers =$supplierIds;
                    $supplierId=$supplierIds['SupplierId'];
                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from('WF_CurrencyMaster')
                        ->columns(array('CurrencyId','CurrencyName'))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_GatePass "))
                        ->columns(array("GateRegId","GatePassNo"))
                        ->where(array("a.CostCentreId= $costCentreId And a.SupplierId= $supplierId "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_gatepass = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $selPOReg=$sql->select();
                    $selPOReg->from(array('a'=>'MMS_PVRegister'))
                        ->columns(array('CostCentreId' => new Expression('a.CostCentreId'),
                            'SupplierId'=> new Expression('a.VendorId'),
                            'SupplierName'=> new Expression('c.VendorName'),
                            'CurrencyId'=> new Expression('a.CurrencyId'),
                            'CostCentreName'=> new Expression('b.CostCentreName'),
                            'SupplierName'=> new Expression('c.VendorName'),
                            'PVDate'=> new Expression('Convert(Varchar(10),a.PVDate,103)'),
                            'BillDate'=> new Expression('Convert(Varchar(10),a.BillDate,103)'),
                            'PVNo'=> new Expression('a.PVNo'),
                            'CCPVNo'=> new Expression('a.CCPVNo'),
                            'CPVNo'=> new Expression('a.CPVNo'),
                            'BillNo'=> new Expression('a.BillNo'),
                            'Narration'=> new Expression('a.Narration'),
                            'BillAmount'=> new Expression('a.BillAmount'),
                            'PurchaseTypeId'=> new Expression('a.PurchaseTypeId'),
                            'GateRegId'=> new Expression('a.GateRegId'),
                            'Approve'=> new Expression('a.Approve'),
                            'PurchaseAccount'=> new Expression('a.PurchaseAccount'),
                            'GridType'=> new Expression('a.GridType')))
                        ->join(array('b'=>'WF_OperationalCostCentre'),'a.CostCentreId=b.CostCentreId',array(),$selPOReg::JOIN_INNER)
                        ->join(array('c'=>'Vendor_Master'),'a.VendorId=c.VendorId',array(),$selPOReg::JOIN_INNER)
                        ->where(array("a.PVRegisterId"=>$PVRegisterId));
                    $statement = $sql->getSqlStringForSqlObject( $selPOReg );
                    $this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $CostCentreId=$this->_view->register ['CostCentreId'];
                    $SupplierId=$this->_view->register ['SupplierId'];
                    $SupplierName=$this->_view->register ['SupplierName'];
                    $Narration=$this->_view->register ['Narration'];
                    $CurrencyId=$this->_view->register ['CurrencyId'];
                    $PVDate=$this->_view->register ['PVDate'];
                    $PVNo=$this->_view->register ['PVNo'];
                    $CCPVNo=$this->_view->register ['CCPVNo'];
                    $CPVNo=$this->_view->register ['CPVNo'];
                    $BillNo=$this->_view->register ['BillNo'];
                    $BillDate=$this->_view->register ['BillDate'];
                    $PurchaseTypeId=$this->_view->register ['PurchaseTypeId'];
                    $PurchaseAccount=$this->_view->register ['PurchaseAccount'];
                    $BillAmount=$this->_view->register ['BillAmount'];
                    $GateRegId=$this->_view->register ['GateRegId'];
                    $Approve=$this->_view->register ['Approve'];
                    $gridtype=$this->_view->register ['GridType'];
                    $this->_view->purchasetype= $PurchaseTypeId;
                    $this->_view->GateRegId= $GateRegId;
                    $this->_view->BillAmount= $BillAmount;
                    $this->_view->accounttype=$PurchaseAccount;
                    $this->_view->Narration=$Narration;
                    $this->_view->vNo = $PVNo;
                    $this->_view->currency= $CurrencyId;
                    $this->_view->CostCentreId= $CostCentreId;
                    $this->_view->SupplierId= $SupplierId;
                    $this->_view->SupplierName= $SupplierName;
                    $this->_view->PODate= $PVDate;
                    $this->_view->CCPVNo= $CCPVNo;
                    $this->_view->CPVNo= $CPVNo;
                    $this->_view->BillNo= $BillNo;
                    $this->_view->BillDate= $BillDate;
                    $this->_view->PVRegisterId =$PVRegisterId;
                    $this->_view->Approve =$Approve;
                    $this->_view->gridtype =$gridtype;

                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->where('c.CostCentreId='.$CostCentreId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$PurchaseTypeId));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    $select = $sql->select();
                    $select->from(array("a"=>"mms_pvTrans"))
                        ->columns(array(
                            'PVGroupId' => new Expression('a.PVGroupId'),
                            'PVTransId' => new Expression('a.PVTransId'),
                            'POTransId' => new Expression('a.POTransId'),
                            'PORegisterId' => new Expression('a.PORegisterId'),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'ItemId' => new Expression('a.ItemId'),
                            'UnitId' => new Expression('a.UnitId'),
                            'Desc' => new Expression("Case When a.ItemId>0 Then c.ItemCode+''+c.BrandName Else b.Code+''+b.ResourceName End"),
                            'UnitName' => new Expression('d.UnitName'),
                            'Qty' => new Expression("CAST(a.BillQty As Decimal(18,3))"),
                            'Rate' => new Expression('a.Rate'),
                            'ResSpec' => new Expression('e.Description'),
                            'QRate' => new Expression('a.QRate'),
                            'BaseAmount' => new Expression('a.Amount'),
                            'Amount' => new Expression('a.QAmount'),
                            'RFrom'=>new Expression('Case When a.ResourceId IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId='.$CostCentreId.') Then '."'Project'".' Else '."'Library'".' End ')  ))
                        ->join(array('b'=>'Proj_Resource'),'a.ResourceId=b.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('c'=>'MMS_Brand'),'a.ResourceId=b.ResourceId and a.ItemId=c.BrandId',array(),$select::JOIN_LEFT)
                        ->join(array('d'=>'Proj_UOM'),'a.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('e'=>'mms_pvGroupTrans'),'a.PVGroupId=e.PVGroupId',array(),$select::JOIN_LEFT)
                        ->where('a.PVRegisterId='.$PVRegisterId.'');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $poTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arr_requestResources = $poTrans;

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POTrans"))
                        ->columns(array(
                            'PORegisterId'=>new Expression('c.PORegisterId'),
                            'POTransId'=>new Expression('a.POTransId'),
                            'POProjTransId'=>new Expression('b.POProjTransId'),
                            'PONo'=>new Expression('c.PONo'),
                            'PODate'=>new Expression('Convert(Varchar(10),c.POdate,103)'),
                            'ResourceId'=>new Expression('a.ResourceId'),
                            'ItemId'=>new Expression('a.ItemId'),
                            'POQty'=>new Expression('CAST(a.POQty As Decimal(18,3))'),
                            'BalQty'=>new Expression('CAST(a.BalQty As Decimal(18,3))'),
                            'Qty'=>new Expression('CAST(d.BillQty As Decimal(18,3))')))
                        ->join(array('b' => 'MMS_POProjTrans'), 'a.PoTransId=b.PoTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'), 'a.PORegisterId=c.PORegisterId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'mms_PVTrans'), 'a.POTransId=d.POTransId', array(), $select::JOIN_INNER)
                        ->where('d.PVRegisterId='.$PVRegisterId.'');
                    $select->group(array("c.PORegisterId","a.POTransId",
                        "b.POProjTransId","c.PONo","c.POdate","a.ResourceId",
                        "a.ItemId","a.POQty","a.BalQty","d.BillQty"));
                   // echo $statement = $sql->getSqlStringForSqlObject($select); die;

//                    $select = $sql->select();
//                    $select->from(array("a" => "MMS_POTrans"))
//                        ->columns(array(
//                            'PORegisterId'=>new Expression('c.PORegisterId'),
//                            'POTransId'=>new Expression('a.POTransId'),
//                            'POProjTransId'=>new Expression('b.POProjTransId'),
//                            'PONo'=>new Expression('c.PONo'),
//                            'PODate'=>new Expression('Convert(Varchar(10),C.POdate,103)'),
//                            'ResourceId'=>new Expression('a.ResourceId'),
//                            'ItemId'=>new Expression('a.ItemId'),
//                            'POQty'=>new Expression('CAST(SUM(ISNULL(a.POQty,0)) As Decimal(18,6))'),
//                            'BalQty'=>new Expression('CAST(SUM(ISNULL(a.BalQty,0)) As Decimal(18,6))'),
//                            'Qty'=>new Expression('CAST(0 As Decimal(18,6))')))
//                        ->join(array('b' => 'MMS_POProjTrans'), 'a.PoTransId=b.PoTransId', array(), $select::JOIN_INNER)
//                        ->join(array('c' => 'MMS_PORegister'), 'a.PORegisterId=c.PORegisterId', array(), $select::JOIN_INNER)
//                        ->where("c.CostCentreId=4 and c.Approve='Y' And CAST(a.BalQty As Decimal(18,5))>0 And
//                                        (b.ResourceId IN (Select ResourceId From MMS_POTrans Where PVRegisterId= $PVRegisterId) And
//                                         b.ItemId IN (Select ItemId From MMS_POProjTrans Where PVRegisterId= $PVRegisterId))
//                                         b.PoTransId not in ()
//                                         Group By c.PORegisterId,a.POTransId,b.POProjTransId,c.PONo,
//                                         c.POdate,a.ItemId,a.POQty,a.BalQty,d.BillQty");
//                     $statement = $sql->getSqlStringForSqlObject($select);

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
						->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $selectAnal = $sql->select();
                    $selectAnal->from(array("a" => "MMS_IPDAnalTrans"))
                        ->columns(array(
                            'IPDAHTransId' => new Expression('a.IPDAHTransId'),
                            'IPDTransId' => new Expression('f.IPDTransId'),
                            'IPDProjTransId' => new Expression('f.IPDProjTransId'),
                            'POProjTransId' => new Expression('f.POProjTransId'),
                            'POTransId' => new Expression('e.POTransId'),
                            'PORegisterId' => new Expression('e.PORegisterId'),
                            'PVAnalTransId' => new Expression('a.PVAnalTransId'),
                            'WBSId' => new Expression('c.WBSId'),
                            'POAnalTransId' => new Expression('a.POAHTransId'),
                            'PVTransId' => new Expression('e.PVTransId'),
                            'ResourceId' => new Expression('b.ResourceId'),
                            'ItemId' => new Expression('b.ItemId'),
                            'WBSName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
                            'POQty' => new Expression('CAST(B.POQty As Decimal(18,3))'),
                            'BalQty' => new Expression('CAST(B.BalQty As Decimal(18,3))'),
                            'Qty' => new Expression('CAST(A.Qty As Decimal(18,3))'),
                            'wHideQty' => new Expression('CAST(A.Qty As Decimal(18,3))')))
                        ->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selectAnal::JOIN_INNER)
                        ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId And b.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_INNER)
                        ->join(array("d" => "MMS_PVAnalTrans"), " a.PVAnalTransId=d.PVAnalTransId ", array(), $selectAnal::JOIN_INNER)
                        ->join(array("e" => "MMS_PVTrans"), " d.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_INNER)
                        ->join(array("f" => "mms_IPDprojTrans"), " a.IPDProjTransId=f.IPDProjTransId", array(), $selectAnal::JOIN_INNER);
                    $selectAnal->where(array("e.PVRegisterId = $PVRegisterId and a.Status='U'"));
                    $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                    $this->_view->qualList = $qualList;

                    $arrqual = array();
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PVQualTrans"))
                        ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER);
                    $select->where(array('a.PVRegisterId'=>$PVRegisterId));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->qualAccList = $qualAccList;

                    $select = $sql->select();
                    $select->from(array("a" => "FA_AccountMaster"))
                        ->columns(array('AccountId','AccountName','TypeId'));
                    $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subWbs1=$sql->select();
                    $subWbs1->from(array('a'=>'Proj_WBSMaster'))
                        ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,0 As Qty")))
						->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$subWbs1::JOIN_INNER)
                        ->where -> expression('a.LastLevel=1 And b.CostCentreId='.$CostCentreId.'
                        And a.WBSId NOT IN (Select AnalysisId From mms_PVAnalTrans Where PVTransId IN (Select PVTransId From MMS_PVTrans Where PVRegisterId=?))', $PVRegisterId);
                    $selectAnal = $sql->select();
                    $selectAnal->from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array(
                            'ResourceId' => new Expression('e.ResourceId'),
                            'ItemId' => new Expression('e.ItemId'),
                            'WbsId' => new Expression('c.WBSId'),
                            'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
                            'Qty' => new Expression('CAST(A.BillQty As Decimal(18,3))')))
                        ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
                        ->join(array("e" => "MMS_PVTrans"), " a.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_LEFT);
                    $selectAnal->where(array("e.PVRegisterId = $PVRegisterId"));
                    $selectAnal->combine($subWbs1, 'Union ALL');
                    $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
                    $this->_view->arr_wb = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //AUTO COMPLETE
//                    $select = $sql->select();
//                    $select->from(array("PR" => "MMS_PORegister"))
//                        ->columns(array(
//                            'data' => new Expression('R.ResourceId'),
//                            'PoTransId' => new Expression('PT.PoTransId'),
//                            'PORegisterId' => new Expression('PT.PORegisterId'),
//                            'ItemId' => new Expression('isnull(B.BrandId,0)'),
//                            //                                  'Include' => new Expression('0'),
//                            'PODate' => new Expression('Convert(Varchar(10),PR.PODate,103)'),
//                            'Qty' => new Expression('CAST(0 As Decimal(18,5))'),
//                            'Rate' => new Expression('Case When PT.ItemId>0 Then B.Rate Else R.Rate End'),
//                            'QRate' => new Expression('Case When PT.ItemId>0 Then B.Rate Else R.Rate End'),
//                            'BaseAmount' => new Expression('CAST(0 As Decimal(18,3))'),
//                            'Amount' => new Expression('CAST(0 As Decimal(18,3))'),
//                            'PONo' => new Expression('PR.PONo'),
//                            'value' => new Expression('Case When PT.ItemId>0 Then B.BrandName Else R.ResourceName End'),
//                            'UnitName' => new Expression('c.UnitName'),
//                            'UnitId' => new Expression('c.UnitId'),
//                            'Rate' => new Expression('PT.Rate')))
//                        ->join(array("PT" => 'MMS_POTrans'), 'PT.PORegisterID=PR.PoRegisterID', array(), $select::JOIN_INNER)
//                        ->join(array("PPT" => 'MMS_POProjTrans'), 'PPT.PoTransId=PT.PoTransId', array(), $select::JOIN_INNER)
//                        ->join(array('CC' => 'WF_OperationalCostCentre'), 'CC.CostCentreId=PPT.CostCentreId', array(), $select::JOIN_INNER)
//                        ->join(array('R' => 'Proj_Resource'), 'PT.ResourceId=R.ResourceId', array(), $select::JOIN_INNER)
//                        ->join(array('B' => 'MMS_Brand'), 'PT.ResourceId=B.ResourceId And PT.ItemId=B.BrandId', array(), $select::JOIN_LEFT)
//                        ->join(array('c' => 'Proj_UOM'), 'R.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
//                        ->where('PR.CostCentreId = ' . $CostCentreId . ' and (R.ResourceId NOT IN( select resourceid from MMS_PVTrans where PVRegisterId = ' .$PVRegisterId. ' ) OR isnull(B.BrandId,0) NOT IN ( select ItemId from MMS_PVTrans where PVRegisterId = ' .$PVRegisterId. '))');

                    $select = $sql -> select();
                    $select -> from (array("a" => "Proj_Resource"))
                        ->columns(array('data'=>new Expression('a.ResourceId'),"AutoFlag"=>new Expression("1-1"),'ItemId'=>new Expression('isnull(d.BrandId,0)'),
                            'Code'=>new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
                            'value'=>new Expression('Case When isnull(d.BrandId,0)>0 Then (d.ItemCode + '."' - '".' +d.BrandName) Else (a.Code + '."' - '".' +a.ResourceName) End'),
                            'UnitName'=>new Expression('c.UnitName'),'UnitId'=>new Expression('c.UnitId'),
                            'Rate'=>new Expression('Case When isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End'),
                            'RFrom'=>new Expression("'Project'")    ))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$select::JOIN_LEFT)
                        ->join(array("c" => "Proj_UOM"),"a.UnitId=c.UnitId",array(),$select::JOIN_LEFT)
                        ->join(array("d" => "MMS_Brand"),"a.ResourceId=d.ResourceId",array(),$select::JOIN_LEFT)
                        ->join(array("e" => "Proj_ProjectResource"),"a.ResourceId=e.ResourceId",array(),$select::JOIN_INNER)
						->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                        ->where("f.CostCentreId=".$CostCentreId." and (a.ResourceId NOT IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId=".$PVRegisterId.") Or isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_PVTrans Where PVRegisterId=".$PVRegisterId."))");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) else CAST(a.Rate As Decimal(18,2)) End As Rate,'Library' As RFrom  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentreId .") and (a.ResourceId NOT IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId=".$PVRegisterId.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_PVTrans Where PVRegisterId=".$PVRegisterId."))  ");
                    $select -> combine($selRa,"Union All");

                     $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,3)) Qty,CAST(0 As Decimal(18,2)) HiddenQty")))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array("b.CostCentreId=  $CostCentreId And c.LastLevel=1"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "mms_pvwhTrans"))
                        ->columns(array(new Expression("e.ResourceId,e.ItemId,c.CostCentreId,b.TransId as WareHouseId,d.WareHouseName,b.Description,CAST(a.BillQty As Decimal(18,2)) Qty")))
                        ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_PVGroupTrans"), "a.pvGroupId=e.pvGroupId", array(), $select::JOIN_INNER)
                        ->where(array('c.CostCentreId=' . $CostCentreId . ' And b.LastLevel=1 And e.PVRegisterId = ' . $PVRegisterId . ' '));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_warehouseQty = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TERMS AND CONDITION
                    $select = $sql->select();
                    $select->from(array("A" => "WF_TermsMaster"))
                        ->columns(array(
                            'TermsId'=>new Expression("A.TermsId"),
                            'Terms'=>new Expression("A.Title"),
                            'Value'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'AccountId'=>new Expression("ISNULL(A.AccountId,0)"),
                            'Account'=>new Expression("''")
                        ))
                        ->join(array('c' => 'fa_AccountMaster'), 'A.AccountId=c.AccountId', array(), $select:: JOIN_LEFT)
                        ->where(array("TermType='S' And A.AccountUpdate=1 and
                         A.TermsId NOT IN(select termsid from mms_pvpaymentterms
                        where PVRegisterId=$PVRegisterId)"));
                    $select1 = $sql->select();
                    $select1->from(array("A" => "mms_PVPaymentTerms"))
                        ->columns(array(
                            'TermsId'=>new Expression('A.TermsId'),
                            'Terms'=>new Expression('b.Title'),
                            'Value'=>new Expression("A.Value"),
                            'AccountId'=>new Expression("A.AccountId"),
                            'Account'=>new Expression("c.AccountName")
                        ))
                        ->join(array('b' => 'wf_TermsMaster'), 'a.TermsId=b.TermsId', array(), $select1:: JOIN_LEFT)
                        ->join(array('c' => 'fa_AccountMaster'), 'a.AccountId=c.AccountId', array(), $select1:: JOIN_LEFT)
                        ->where("PVRegisterId=$PVRegisterId");
                    $select1->combine($select, 'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->terms_ent= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("A" => "FA_AccountMaster"))
                        ->columns(array(
                            'AccountId'=>new Expression('A.AccountId'),
                            'AccountName'=>new Expression('A.AccountName')
                        ))
                        ->where("A.LastLevel='Y' And A.TypeId=22");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Account= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("A" => "Vendor_Branch"))
                        ->columns(array(
                            'BranchId'=>new Expression('A.BranchId'),
                            'BranchName'=>new Expression('A.BranchName')
                        ))
                        ->where("VendorId = $SupplierId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $branch= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->branch=$branch;


                    $select = $sql->select();
                    $select->from(array("A" => "mms_pvregister"))
                        ->columns(array(
                            'BranchName'=>new Expression('b.BranchName'),
                            'BranchId'=>new Expression('b.BranchId'),
                            'BranchTransId'=>new Expression('c.BranchTransId'),
                            'Phone'=>new Expression('b.Phone'),
                            'ContactPerson'=>new Expression("c.ContactPerson"),
                            'ContactNo'=>new Expression("c.ContactNo"),
                        ))
                        ->join(array('b' => 'Vendor_Branch'), 'a.BranchId=b.BranchId', array(), $select:: JOIN_INNER)
                        ->join(array('c' => 'Vendor_BranchContactDetail'), 'a.BranchTransId=c.BranchTransId', array(), $select:: JOIN_INNER)
                        ->where("PVRegisterId=$PVRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->branches= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $BranchName=$this->_view->branches ['BranchName'];
                    $BranchId=$this->_view->branches ['BranchId'];
                    $BranchTransId=$this->_view->branches ['BranchTransId'];
                    $Phone=$this->_view->branches ['Phone'];
                    $ContactPerson=$this->_view->branches ['ContactPerson'];
                    $ContactNo=$this->_view->branches ['ContactNo'];
                    $this->_view->BranchName= $BranchName;
                    $this->_view->BranchId= $BranchId;
                    $this->_view->BranchTransId= $BranchTransId;
                    $this->_view->Phone= $Phone;
                    $this->_view->ContactPerson= $ContactPerson;
                    $this->_view->ContactNo= $ContactNo;



                    $today=date('Y/m/d');
                    $advsub = $sql->select();
                    $advsub->from(array("A" => "MMS_PORegister"))
                        ->columns(array(
                            'PORegisterId' => new Expression('A.PORegisterId'),
                            'PONo' => new Expression('A.PONo'),
                            'TermsId' => new Expression('B.TermsId'),
                            'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                            'Title' => new Expression('C.Title'),
                            'Amount' => new Expression("CAST(B.Value As decimal(18,2))"),
                            'PaidAmount' => new Expression("CAST(B.Value As decimal(18,2))"),
                            'Balance' => new Expression("CAST(B.Value-B.AdjustAmount As decimal(18,2))"),
                            'DeductionBalance' => new Expression("Cast(B.AdjustAmount As Decimal(18,2))"),
                            'CurrentAmount' => new Expression("Cast(0 As Decimal(18,2))"),
                            'HiddenAmount' => new Expression("Cast(0 As Decimal(18,2))")
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'WF_TermsMaster'),'B.TermsId=C.TermsId',array(),$select::JOIN_INNER)
                        ->where(array("C.Title IN ('Advance','Against Delivery','Against Test Certificate') AND  A.VendorId=$SupplierId And CostCentreId=$CostCentreId AND CAST(B.Value-B.AdjustAmount As Decimal(18,3))>0
                         And A.PODate <='$today' and a.PORegisterId NOT IN(select PORegisterId from mms_advAdjustment where BillRegisterId=$PVRegisterId ) GROUP BY A.PORegisterId,B.TermsId, A.PONo, A.PODate,C.Title,B.Value,B.AdjustAmount "));

                    $select = $sql->select();
                    $select->from(array("A" => "MMS_PORegister"))
                        ->columns(array(
                            'PORegisterId' => new Expression('A.PORegisterId'),
                            'PONo' => new Expression('A.PONo'),
                            'TermsId' => new Expression('B.TermsId'),
                            'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                            'Title' => new Expression('TM.Title'),
                            'Amount' => new Expression("CAST(B.Value As decimal(18,2))"),
                            'PaidAmount' => new Expression("CAST(B.Value As decimal(18,2))"),
                            'Balance' => new Expression("CAST(B.Value - ISNULL((Select SUM(Amount) From MMS_AdvAdjustment
                                              Where PORegisterId=A.PORegisterId And TermsId=B.TermsId),0) as decimal(18,2))"),
                            'DeductionBalance' => new Expression("Cast(B.AdjustAmount As Decimal(18,2))"),
                            'CurrentAmount' => new Expression("Cast(C.Amount As Decimal(18,2))"),
                            'HiddenAmount' => new Expression("Cast(C.Amount As Decimal(18,2))")
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("TM"=>'WF_TermsMaster'),'B.TermsId=TM.TermsId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'MMS_AdvAdjustment'),'A.PORegisterId=C.PORegisterId and TM.TermsId=c.TermsId',array(),$select::JOIN_INNER)
                        ->join(array("D"=>'MMS_PVRegister'),'C.BillRegisterId=D.PVRegisterId',array(),$select::JOIN_INNER)
                        ->where("TM.Title IN ('Advance','Against Delivery','Against Test Certificate') AND TM.TermType='S' AND  D.PVRegisterId=$PVRegisterId ");
                    $select->combine($advsub, 'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_advance = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('EstimateQty' => new Expression('CAST(a.Qty As Decimal(18,3))'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"), 'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
						->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentreId .' And a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .')');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .')
                         And b.CostCentreId='.$CostCentreId.' And c.General=0 GROUP BY a.ResourceId ');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') And
                                B.CostCentreId='.$CostCentreId .' And B.General=0 GROUP BY a.ResourceId ');
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('b.ThruPO='."'Y'".' And a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') and
                                 b.CostCentreId='.$CostCentreId.' and b.General=0 GROUP BY a.ResourceId ');
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') And
                                        b.CostCentreId='.$CostCentreId.' GROUP BY a.ResourceId');
                    $sel4->combine($sel3,"Union ALL");
                    // echo $statement = $sql->getSqlStringForSqlObject($sel4); die;

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') and
                                        b.ToCostCentreId='.$CostCentreId.' GROUP BY a.ResourceId');
                    //$sel5->combine($sel4,"Union ALL");

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferTrans"))
                        ->columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') and
                                         b.FromCostCentreId='.$CostCentreId.' GROUP BY a.ResourceId');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("A.ResourceId")));
                    $sel7->group(new Expression("a.ResourceId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') and
                                         b.CostCentreId='.$CostCentreId.' GROUP BY a.ResourceId');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentreId.' and
                                        a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') GROUP BY a.ResourceId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('b.General=0 and b.CostCentreId='.$CostCentreId.' and
                                           a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .')GROUP BY a.ResourceId');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression("a.ResourceId")))
                        ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentreId.' and
                                a.ResourceId IN (select ResourceId From MMS_PVGroupTrans Where PVRegisterId='. $PVRegisterId .') GROUP BY a.ResourceId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array(
                            'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)+ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)+ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                            'TotMinQty'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0) As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(G.TotBillQty),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression("G.ResourceId"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))")
                        ));
                    $sel12->group(new Expression("G.ResourceId"));
                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //GET ESTIMATE QTY,ESTIMATE RATE <-> WBS
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('EstimateQty' => new Expression('CAST(a.Qty As Decimal(18,3))'),'EstimateRate' => new Expression("CAST(a.Rate As Decimal(18,2))"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.WbsId')
                        ))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' .$CostCentreId. ' And ResourceId IN( Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') And WbsId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='.$CostCentreId.' and
                                 A.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ')) ');
//                    echo $statement1 = $sql->getSqlStringForSqlObject($sel); die;


                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId IN(Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') And
                        b.CostCentreId='.$CostCentreId.' And d.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId=' .$CostCentreId. ' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ')) GROUP BY a.ResourceId,a.AnalysisId');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ')  And c.CostCentreId='.$CostCentreId.'
                        And c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))GROUP BY a.ResourceId,a.AnalysisId');
                    $sel2->combine($sel1,"Union ALL");


                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') and c.CostCentreId='.$CostCentreId.'
                         and c.General=0 And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))GROUP BY a.ResourceId,a.AnalysisId');
                    $sel3->combine($sel2,"Union ALL");


                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') And c.CostCentreId='.$CostCentreId.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel4->combine($sel3,"Union ALL");


                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') and c.ToCostCentreId='.$CostCentreId.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId ');

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)"),
                            'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel6::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') and c.FromCostCentreId='.$CostCentreId.' And a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On B.ProjectIOWId=C.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where A.IncludeFlag=1 and D.CostCentreId='. $CostCentreId.' and A.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('A.ResourceId'),
                            'WbsId' => new Expression('A.WbsId') ))
                        ->group(new Expression("A.ResourceId,A.WbsId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') and c.CostCentreId='.$CostCentreId.' and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='.$CostCentreId.' and
                                 a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and
                        b.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ')    and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"), 'ResourceId' => new Expression('a.ResourceId'),
                            'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ')
                        and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression('a.ResourceId'),'WbsId' => new Expression('a.AnalysisId')))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentreId.' and
                        a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. ') and a.AnalysisId IN(
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
								 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_PVTrans
                         Where PVRegisterId= ' .$PVRegisterId. '))
                                 GROUP BY a.ResourceId,a.AnalysisId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                            'ResourceId' => new Expression('G.ResourceId'),'WbsId' => new Expression('G.WbsId')));
                    $sel12->group(new Expression("G.ResourceId,G.WbsId"));
                    $statement1 = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            }

            // $aVNo = CommonHelper::getVoucherNo(306, date('Y/m/d'), 0, 0, $dbAdapter, "");
            // $this->_view->genType = $aVNo["genType"];
            // if (!$aVNo["genType"])
                // $this->_view->vNo = "";
            // else
                // $this->_view->vNo = $aVNo["voucherNo"];
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

    }

    public function billsaveAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(306, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->vNo = $vNo;
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                 echo"</pre>";die;
            }
        }
        else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//               print_r($postParams);
//                echo"</pre>";
//                die;
                $Approve="";
                $Role="";
                $PVRegisterId = $postParams['PVRegisterId'];
                if ($this->bsf->isNullCheck($PVRegisterId, 'number') > 0) {
                    $Approve="E";
                    $Role="Bill-Direct-Modify";
                }else{
                    $Approve="N";
                    $Role="Bill-Direct-Create";
                }
                $voucherno='';

                $BranchId = $this->bsf->isNullCheck($postParams['branchname'],'number');
                $BranchTransId = $this->bsf->isNullCheck($postParams['contactperson'],'number');
                $CostCenterId = $this->bsf->isNullCheck($postParams['CostCenterId'], 'number');
                $SupplierId = $this->bsf->isNullCheck($postParams['SupplierId'], 'number');
                $PVDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['PODate'], 'string')));
                $BillDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['BillDate'], 'string')));
                $CCPVNo = $this->bsf->isNullCheck($postParams['CCPVNo'], 'number');
                $CPVNo = $this->bsf->isNullCheck($postParams['CPVNo'], 'number');
                $Narration = $this->bsf->isNullCheck($postParams['Narration'], 'string');
                $PVNo = $this->bsf->isNullCheck($postParams['PVNo'], 'string');
                $voucherno=$PVNo;
                $CurrId = $this->bsf->isNullCheck($postParams['currency'], 'number');
                $BillNo = $this->bsf->isNullCheck($postParams['BillNo'], 'number');
                $GatePassNo = $this->bsf->isNullCheck($postParams['GatePassNo'], 'number');
                $PurTypeId = $this->bsf->isNullCheck($postParams['purchase_type'], 'number');
                $AccountId = $this->bsf->isNullCheck($postParams['account_type'], 'number');
                $gridtype = $this->bsf->isNullCheck($postParams['gridtype'], 'number');
                if($gridtype == 0){
                    $totalamt = $this->bsf->isNullCheck($postParams['basetotal1'], 'number');
                    $netAmount = $this->bsf->isNullCheck($postParams['nettotal1'], 'number');
                } else {
                    $totalamt = $this->bsf->isNullCheck($postParams['basetotal'], 'number');
                    $netAmount = $this->bsf->isNullCheck($postParams['nettotal'], 'number');
                }

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where("CostCentreId=$CostCenterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

				//CostCentre
				$CCPurchase = CommonHelper::getVoucherNo(306, date('Y/m/d'), 0, $CostCenterId, $dbAdapter, "");
				$this->_view->CCPurchase = $CCPurchase;

				//CompanyId
				$CPurchase = CommonHelper::getVoucherNo(306, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
				$this->_view->CPurchase = $CPurchase;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($this->bsf->isNullCheck($PVRegisterId, 'number') > 0) {
                        $selAnal = $sql->select();
                        $selAnal->from(array("a" => "MMS_IPDAnalTrans"))
                            ->columns(array(new Expression("D.POAnalTransId as POAnalTransId,A.Qty as Qty")))
                            ->join(array("b" => "MMS_PVAnalTrans"), "a.PVAnalTransId=b.PVAnalTransId", array(), $selAnal::JOIN_INNER)
                            ->join(array("c" => "MMS_PVTrans"), "b.PVTransId=c.PVTransId", array(), $selAnal::JOIN_INNER)
                            ->join(array("d" => "MMS_POAnalTrans"), "a.POAHTransId=d.POAnalTransId", array(), $selAnal::JOIN_INNER)
                            ->where(array("c.PVRegisterId = $PVRegisterId and  A.Status='U'"));
                        $statementPrev = $sql->getSqlStringForSqlObject($selAnal);
                        $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($prevanal as $arrprevanal) {
                            $updAnal = $sql->update();
                            $updAnal->table('MMS_POAnalTrans');
                            $updAnal->set(array(
                                'BillQty' => new Expression('BillQty-' . $arrprevanal['Qty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevanal['Qty'] . '')
                            ));
                            $updAnal->where(array('POAnalTransId' => $arrprevanal['POAnalTransId']));
                            $updAnalStatement = $sql->getSqlStringForSqlObject($updAnal);
                            $dbAdapter->query($updAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $selTrans = $sql->select();
                        $selTrans->from(array("a" => "MMS_IPDTrans"))
                            ->columns(array(new Expression("C.POTransId as POTransId,A.Qty as Qty")))
                            ->join(array("b" => "MMS_PVTrans"), "a.PVTransId=b.PVTransId", array(), $selTrans::JOIN_INNER)
                            ->join(array("c" => "MMS_POTrans"), "a.POTransId=c.POTransId", array(), $selTrans::JOIN_INNER)
                            ->where(array("b.PVRegisterId = $PVRegisterId and  A.Status='U'"));
                        $statementTrans = $sql->getSqlStringForSqlObject($selTrans);
                        $prevTrans = $dbAdapter->query($statementTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($prevTrans as $arrpreTrans) {
                            $updTrans = $sql->update();
                            $updTrans->table('mms_POTrans');
                            $updTrans->set(array(
                                'BillQty' => new Expression('BillQty-' . $arrpreTrans['Qty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrpreTrans['Qty'] . '')
                            ));
                            $updTrans->where(array('POTransId' => $arrpreTrans['POTransId']));
                            $updTranStatement = $sql->getSqlStringForSqlObject($updTrans);
                            $dbAdapter->query($updTranStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $selProj = $sql->select();
                        $selProj->from(array("a" => "MMS_IPDProjTrans"))
                            ->columns(array(new Expression("C.POProjTransId as POProjTransId,A.Qty as Qty")))
                            ->join(array("b" => "MMS_PVTrans"), "a.PVTransId=b.PVTransId", array(), $selProj::JOIN_INNER)
                            ->join(array("c" => "MMS_POProjTrans"), "a.POProjTransId=c.POProjTransId", array(), $selProj::JOIN_INNER)
                            ->where(array("b.PVRegisterId = $PVRegisterId and  A.Status='U'"));
                        $statementProj = $sql->getSqlStringForSqlObject($selProj);
                        $prevProj = $dbAdapter->query($statementProj, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($prevProj as $arrpreProj) {
                            $updProj = $sql->update();
                            $updProj->table('mms_poprojTrans');
                            $updProj->set(array(
                                'BillQty' => new Expression('BillQty-' . $arrpreProj['Qty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrpreProj['Qty'] . '')
                            ));
                            $updProj->where(array('POProjTransId' => $arrpreProj['POProjTransId']));
                            $updProjStatement = $sql->getSqlStringForSqlObject($updProj);
                            $dbAdapter->query($updProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $seladv = $sql->select();
                        $seladv->from(array("a" => "mms_advadjustment"))
                            ->columns(array(
                                "PORegisterId" => new Expression("a.PORegisterId"),
                                "TermsId" => new Expression("a.TermsId"),
                                "Amount" => new Expression("a.Amount")
                                ))
                            ->where(array("a.BillRegisterId = $PVRegisterId"));
                        $statementAdv = $sql->getSqlStringForSqlObject($seladv);
                        $prevAdv = $dbAdapter->query($statementAdv, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($prevAdv as $arrprevAdv) {
                            $updProj = $sql->update();
                            $updProj->table('mms_popaymentterms');
                            $updProj->set(array(
                                'AdjustAmount' => new Expression('AdjustAmount-' . $arrprevAdv['Amount'] . ''),
                            ));
                            $updProj->where(array('PORegisterId' => $arrprevAdv['PORegisterId'],
                                'TermsId' => $arrprevAdv['TermsId']));
                            $updProjStatement = $sql->getSqlStringForSqlObject($updProj);
                            $dbAdapter->query($updProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        //POQualTrans
                        $delPVQualTrans = $sql->delete();
                        $delPVQualTrans->from('MMS_PVQualTrans')
                            ->where(array("PVRegisterId" => $PVRegisterId));
                        $PVQualStatement = $sql->getSqlStringForSqlObject($delPVQualTrans);
                        $dbAdapter->query($PVQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delQualTrans = $sql->delete();
                        $delQualTrans->from('MMS_QualTrans')
                            ->where(array("RegisterId" => $PVRegisterId));
                        $QualStatement = $sql->getSqlStringForSqlObject($delQualTrans);
                        $dbAdapter->query($QualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //IPDAnalTrans
                        $delIPDAnalSQ3 = $sql->select();
                        $delIPDAnalSQ3->from("mms_pvGroupTrans")
                            ->columns(array("PVGroupId"))
                            ->where(array("PVRegisterId" => $PVRegisterId));

                        $delIPDAnalSQ2 = $sql->select();
                        $delIPDAnalSQ2->from("mms_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where->expression('PVGroupId IN ?', array($delIPDAnalSQ3));

                        $delIPDAnalSQ1 = $sql->select();
                        $delIPDAnalSQ1->from("MMS_PVAnalTrans")
                            ->columns(array("PVAnalTransId"))
                            ->where->expression('PVTransId IN ?', array($delIPDAnalSQ2));

                        $delIPDAnal = $sql->delete();
                        $delIPDAnal->from('MMS_IPDAnalTrans')
                            ->where->expression('PVAnalTransId IN ?', array($delIPDAnalSQ1));
                        $IPDAnalStatement = $sql->getSqlStringForSqlObject($delIPDAnal);
                        $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //IPDProjTrans
                        $delIPDProjSQ2 = $sql->select();
                        $delIPDProjSQ2->from("mms_pvGroupTrans")
                            ->columns(array("PVGroupId"))
                            ->where(array("PVRegisterId" => $PVRegisterId));

                        $delIPDProjSQ1 = $sql->select();
                        $delIPDProjSQ1->from("mms_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where->expression('PVGroupId IN ?', array($delIPDProjSQ2));

                        $delIPDProj = $sql->delete();
                        $delIPDProj->from('MMS_IPDProjTrans')
                            ->where->expression('PVTransId IN ?', array($delIPDProjSQ1));
                        $IPDTransStatement = $sql->getSqlStringForSqlObject($delIPDProj);
                        $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //IPDTrans
                        $delIPDTransSQ1 = $sql->select();
                        $delIPDTransSQ1->from("MMS_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where(array("PVRegisterId" => $PVRegisterId));

                        $delIPDTrans = $sql->delete();
                        $delIPDTrans->from('MMS_IPDTrans')
                            ->where->expression('PVTransId IN ?', array($delIPDTransSQ1));
                        $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTrans);
                        $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //PVAnalTrans
                        $delPVAnalSQ2 = $sql->select();
                        $delPVAnalSQ2->from("mms_pvGroupTrans")
                            ->columns(array("PVGroupId"))
                            ->where(array("PVRegisterId" => $PVRegisterId));
                        $delPVAnalSQ1 = $sql->select();
                        $delPVAnalSQ1->from("MMS_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where->expression('PVGroupId IN ?', array($delPVAnalSQ2));

                        $delPVAnal = $sql->delete();
                        $delPVAnal->from('MMS_PVAnalTrans')
                            ->where->expression('PVTransId IN ?', array($delPVAnalSQ1));
                        $delpvanalStatement = $sql->getSqlStringForSqlObject($delPVAnal);
                        $dbAdapter->query($delpvanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //PVProjTrans
                        $delPVProjSQ1 = $sql->select();
                        $delPVProjSQ1->from("mms_pvGroupTrans")
                            ->columns(array("PVGroupId"))
                            ->where(array("PVRegisterId" => $PVRegisterId));

                        $delPVProj = $sql->delete();
                        $delPVProj->from('MMS_PVTrans');
                        $delPVProj->where->expression('PVGroupId IN ?', array($delPVProjSQ1));
                        $delpvprojStatement = $sql->getSqlStringForSqlObject($delPVProj);
                        $dbAdapter->query($delpvprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //PVTrans
                        $delPVTrans = $sql->delete();
                        $delPVTrans->from('mms_pvGroupTrans')
                            ->where(array("PVRegisterId" => $PVRegisterId));
                        $delpvtransStatement = $sql->getSqlStringForSqlObject($delPVTrans);
                        $dbAdapter->query($delpvtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delPVPayTrans = $sql -> delete();
                        $delPVPayTrans -> from ('MMS_PVPaymentTerms')
                            -> where (array("PVRegisterId" => $PVRegisterId));
                        $PVPayStatement = $sql->getSqlStringForSqlObject($delPVPayTrans);
                        $dbAdapter->query($PVPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $deladvadjustment = $sql -> delete();
                        $deladvadjustment -> from ('mms_advadjustment')
                            -> where (array("BillRegisterId" => $PVRegisterId));
                        $advadjustmentStatement = $sql->getSqlStringForSqlObject($deladvadjustment);
                        $dbAdapter->query($advadjustmentStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //UPDATE PVREGISTER
                        $registerUpdate = $sql->update()
                            ->table("MMS_PVRegister")
                            ->set(array(
                                "PVDate" => $PVDate,
                                "PVNo" => $voucherno,
                                "BillNo" => $BillNo,
                                "BillDate" => $BillDate,
                                "CCPVNo" => $CCPVNo,
                                "CPVNo" => $CPVNo,
                                "PurchaseTypeId" => $PurTypeId,
                                "PurchaseAccount" => $AccountId,
                                "CurrencyId" => $CurrId,
                                "BranchId" => $BranchId,
                                "BranchTransId" => $BranchTransId,
                                "Narration" => $Narration,
                                "GateRegId" => $GatePassNo,
                                "Amount" => $totalamt,
                                "BillAmount" => $netAmount,
                                "GridType" => $gridtype
                            ))
                            ->where(array("PVRegisterId" => $PVRegisterId));
                        $upregStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($upregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $advTotal = $postParams['Arowid'];
                        for ($a = 1; $a <= $advTotal; $a++) {
                            if($this->bsf->isNullCheck($postParams['currentamount_' . $a],'number') > 0) {
                                $advanceInsert = $sql->insert('mms_advadjustment');
                                $advanceInsert->values(array(
                                    "BillRegisterId" => $PVRegisterId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number'. ''),
                                    "PORegisterId" => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number'. ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number' . '')
                                ));
                                $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updadj = $sql->update();
                                $updadj->table('mms_popaymentterms');
                                $updadj->set(array(
                                    "AdjustAmount" => new Expression('AdjustAmount+' . $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') . '')
                                ));
                                $updadj->where(array('PORegisterId' => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number' . ''),
                                    'TermsId' => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number' . '') ));
                                $updAdjStatement = $sql->getSqlStringForSqlObject($updadj);
                                $dbAdapter->query($updAdjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        $termsTotal = $postParams['trowid'];
                        for ($t = 1; $t <= $termsTotal; $t++) {
                            $termsInsert = $sql->insert('MMS_PVPaymentTerms');
                            $termsInsert->values(array(
                                "PVRegisterId" => $PVRegisterId,
                                "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t],'number'),
                                "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number'),
                                "AccountId" => $this->bsf->isNullCheck($postParams['account_' . $t],'number')
                            ));
                            $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                            $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $selectIPDAh = $sql->select();
                        $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER);

                        $resTotal = $postParams['rowid'];
                        for ($i = 1; $i < $resTotal; $i++) {
                            $potransInsert = $sql->insert('mms_pvGroupTrans');
                            $potransInsert->values(array(
                                "PVRegisterId" => $PVRegisterId,
                                "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number'),
                                "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                "ActualQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                "Description" => $this->bsf->isNullCheck($postParams['resspec_' . $i], 'string'),
                                "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number')));
                            $potransStatement = $sql->getSqlStringForSqlObject($potransInsert);
                            $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PVGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            // stock details updating

                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'],'number'),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "BillQty" => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                    "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                $stock = $sql->insert('mms_stock');
                                $stock->values(array("CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'],'number'),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                    "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number'),
                                    "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i],'number'),
                                    "ClosingStock" => $this->bsf->isNullCheck($postParams['qty_' . $i],'number')
                                ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } // end of stock update

                            $whTotal = $postParams['wh_' . $i . '_rowid'];
                            for ($w = 1; $w <= $whTotal; $w++) {
                                if($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0 ) {

                                    $whInsert = $sql->insert('mms_pvWhTrans');
                                    $whInsert->values(array("PVGroupId" => $PVGroupId,
                                        "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w],'number'),
                                        "BillQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w],'number')
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans update

                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w],'number'),
                                            "StockId" => $stockselId['StockId'] ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "BillQty" => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . ''),
                                            "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . '')
                                        ));
                                        $sUpdate->where(array("StockId" => $sId['StockId'],"WareHouseId"=>$postParams['wh_' . $i . '_warehouseid_' . $w]));
                                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else {
                                        if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0) {
                                            $stock1 = $sql->insert('mms_stockTrans');
                                            $stock1->values(array("WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],
                                                "StockId" => $stockselId['StockId'],
                                                "BillQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                                "ClosingStock" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }

                            }

                            if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_rowids'], 'number') > 0) {
                                $ipdtransInsert = $sql->insert('mms_PVTrans');
                                $ipdtransInsert->values(array(
                                    "PVRegisterId" => $PVRegisterId,
                                    "PVGroupId" => $PVGroupId,
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                    "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "ActualQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number')));
                                $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $dal = $postParams['wbs_' . $i . '_rowids'];
                                for ($m = 1; $m <= $dal; $m++) {
                                    if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $m], 'number') > 0) {
                                        $ipdanalInsert = $sql->insert('mms_PVAnalTrans');
                                        $ipdanalInsert->values(array(
                                            "PVGroupId" => $PVGroupId,
                                            "PVTransId" => $PVTransId,
                                            "AnalysisId" =>$this->bsf->isNullCheck($postParams['wbs_' . $i . '_wbsid_' . $m],'number'),
                                            "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number'),
                                            "BillQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $m], 'number'),
                                        ));
                                        $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                        $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $PVAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }

                            }else if($postParams['iow_' . $i . '_rowid'] > 0) {
                                $decTotal = $postParams['iow_' . $i . '_rowid'];
                                for ($j = 1; $j <= $decTotal; $j++) {
                                    $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                    //IPDTrans
                                    $ipdtransInsert = $sql->insert('mms_PVTrans');
                                    $ipdtransInsert->values(array(
                                        "PVRegisterId" => $PVRegisterId,
                                        "PVGroupId" => $PVGroupId,
                                        "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_POTransId_' . $j],'number'),
                                        "PORegisterId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PORegisterId_' . $j],'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                        "BillQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "ActualQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                        "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                        "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                        "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                        "UnitId" => $postParams['unitid_' . $i]));
                                    $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                    $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                    $ipdtransInsert->values(array(
                                        "PVTransId" => $PVTransId,
                                        "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_POTransId_' . $j],'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                        "Status" => "U",
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                        "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number')));
                                    $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                    $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                    $ipdprojInsert->values(array(
                                        "PVTransId" => $PVTransId,
                                        "IPDTransId" => $IPDTransId,
                                        "Status" => "U",
                                        "CostCentreId" => $CostCenterId,
                                        "POProjTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_POProjTransId_' . $j],'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                        "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number')));
                                    $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                    $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $transUpdate = $sql->update();
                                    $transUpdate->table('mms_POTrans');
                                    $transUpdate->set(array(
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                        'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                    $transUpdate->where(array('POTransId' => $postParams['iow_' . $i . '_POTransId_' . $j]));
                                    $transStatement = $sql->getSqlStringForSqlObject($transUpdate);
                                    $dbAdapter->query($transStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $ProjtransUpdate = $sql->update();
                                    $ProjtransUpdate->table('mms_poprojTrans');
                                    $ProjtransUpdate->set(array(
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                        'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                    $ProjtransUpdate->where(array('POProjTransId' => $postParams['iow_' . $i . '_POProjTransId_' . $j]));
                                    $ProjtransStatement = $sql->getSqlStringForSqlObject($ProjtransUpdate);
                                    $dbAdapter->query($ProjtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    for ($k = 1; $k <= $wbsTotal; $k++) {
                                        if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') > 0) {
                                            //IPDAnalTrans
                                            $ipdanalInsert = $sql->insert('mms_PVAnalTrans');
                                            $ipdanalInsert->values(array(
                                                "PVGroupId" => $PVGroupId,
                                                "PVTransId" => $PVTransId,
                                                "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_wbsid_' . $k . ''],'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                                "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number'),
                                                "BillQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number'),
                                            ));
                                            $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                            $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $PVAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $ipdanalInsert = $sql->insert('MMS_IPDAnalTrans');
                                            $ipdanalInsert->values(array(
                                                "IPDProjTransId" => $IPDProjTransId,
                                                "PVAnalTransId" => $PVAnalTransId,
                                                "Status" => "U",
                                                "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_wbsid_' . $k . ''],'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                                "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i],'number'),
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number'),
                                                "POAHTransId" => $postParams['iow_' . $i . '_PO_' . $j . '_POAnalTransId_' . $k . '']));
                                            $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                            $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $AnalUpdate = $sql->update();
                                            $AnalUpdate->table('MMS_POAnalTrans');
                                            $AnalUpdate->set(array(
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') . ''),
                                                'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') . '')
                                            ));
                                            $AnalUpdate->where(array('POAnalTransId' => $postParams['iow_' . $i . '_PO_' . $j . '_POAnalTransId_' . $k . '']));
                                            $AnalStatement = $sql->getSqlStringForSqlObject($AnalUpdate);
                                            $dbAdapter->query($AnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                }
                            }
                            //Qualifier Insert
                            $qual = $postParams['QualRowId_' . $i];

                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_pvQualTrans');
                                    $qInsert->values(array("PVRegisterId" => $PVRegisterId, "PVGroupId" => $PVGroupId,
                                        "QualifierId" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Id_' . $q], 'number' . ''), "YesNo" => "1",
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number' . ''),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number' . ''),
                                        "Sign" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Sign_' . $q], 'string' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number' . ''),
                                        "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number' . ''),
                                        "Expression" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Exp_' . $q], 'string' . ''),
                                        "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number')));
                                    $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                    $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            //Gross Rate Update
                            $selGross = $sql -> select();
                            $selGross->from(array("a" => "MMS_PVTrans"))
                                ->columns(array(new Expression("a.PVTransId,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.PVTransId Order by A.PVTransId asc))=1 Then A.QAmount Else 0 End QAmt,
                                    Case When C.QualifierTypeId=3 Then ISNULL(B.NetAmt,0) Else 0 End VatAmt,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.PVTransId Order by A.PVTransId asc))=1 Then ISNULL(A.BillQty,0) Else 0 End As BillQty")))
                                ->join(array('b' => 'MMS_PVQualTrans'),'a.PVGroupId=b.PVGroupId',array(),$selGross::JOIN_LEFT)
                                ->join(array('c' => 'Proj_QualifierMaster'),'b.QualifierId=c.QualifierId',array(),$selGross::JOIN_LEFT)
                                ->where("a.PVRegisterId=$PVRegisterId");

                            $selGross1 = $sql -> select();
                            $selGross1->from(array("g" => $selGross))
                                ->columns(array(new Expression("g.PVTransId,(SUM(G.QAmt)-SUM(G.VatAmt))/SUM(G.PVQty) As GrossRate")));
                            $selGross1->group(new Expression("g.PVTransId"));
                            $statement = $sql->getSqlStringForSqlObject($selGross1);
                            $arr_gross = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($arr_gross as $gross) {
                                $grossUpdate = $sql->update();
                                $grossUpdate->table('MMS_PVTrans');
                                $grossUpdate->set(array(
                                        "GrossRate" => new Expression($this->bsf->isNullCheck($gross["GrossRate"], 'number')),
                                        "GrossAmount" => new Expression('CAST(BillQty*' . $this->bsf->isNullCheck($gross["GrossRate"], 'number') . ' As Decimal(18,3)) ')
                                    )
                                );
                                $grossUpdate->where(array("PVTransId" => $gross['PVTransId']));
                                $grossUpdateStatement = $sql->getSqlStringForSqlObject($grossUpdate);
                                $dbAdapter->query($grossUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //Gross Amount Calculation
                            $selGTotal = $sql -> select();
                            $selGTotal -> from(array("a" => "MMS_PVTrans"))
                                ->columns(array(new Expression("SUM(GrossAmount) As GrossAmount")))
                                ->where("PVRegisterId=$PVRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($selGTotal);
                            $arr_gtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if(count($arr_gtotal) > 0)
                            {
                                $gtotalUpdate = $sql -> update();
                                $gtotalUpdate -> table('MMS_PVRegister');
                                $gtotalUpdate->set(array(
                                        "GrossAmount" =>new Expression($this->bsf->isNullCheck($arr_gtotal["GrossAmount"], 'number')))
                                );
                                $gtotalUpdate->where(array("PVRegisterId" => $PVRegisterId));
                                $gtotalStatement = $sql->getSqlStringForSqlObject($gtotalUpdate);
                                $dbAdapter->query($gtotalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            // end of gross rate update
                        }
                        $qrow = $postParams['Qrowid'];
                        $type='p';
                        if(count($qrow) > 0) {
                            for ($r = 0; $r < $qrow; $r++) {
                                $qInsert = $sql->insert('mms_QualTrans');
                                $qInsert->values(array(
                                    "RegisterId" => $PVRegisterId,
                                    "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . ''),
                                    "Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                    "Rate" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['qutotal_' . $r], 'number' . ''),
                                    "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . ''),
                                    "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                    "Type" => $type));
                                $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $Update = $sql->update();
                                $Update->table('mms_PVQualTrans');
                                $Update->set(array(
                                    "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . '')
                                ));
                                $Update->where(array("Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                    "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                    "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . '')));
                                $statement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                    } else {
                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(306, date('Y/m/d', strtotime($PVDate)), 0, 0, $dbAdapter, "I");
                            $voucherno = $voucher['voucherNo'];
                        } else {
                            $voucherno = $PVNo;
                        }

						if ($CCPurchase['genType']==1) {
						$voucher = CommonHelper::getVoucherNo(306, date('Y/m/d', strtotime($PVDate)), 0, $CostCenterId, $dbAdapter, "I");
							$CCPVNo = $voucher['voucherNo'];
						} else {
							$CCPVNo = $CCPVNo;
						}

						if ($CPurchase['genType']==1) {
							$voucher = CommonHelper::getVoucherNo(306, date('Y/m/d', strtotime($PVDate)), $CompanyId, 0, $dbAdapter, "I");
							$CPVNo = $voucher['voucherNo'];
						} else {
							$CPVNo = $CPVNo;
						}

                            $registerInsert = $sql->insert('mms_pvRegister');
                            $registerInsert->values(array(
                                "VendorId" => $SupplierId,
                                "CostCentreId" => $CostCenterId,
                                "BranchId" => 0,
                                "PurchaseTypeId" => $PurTypeId,
                                "PurchaseAccount" => $AccountId,
                                "BranchTransId" => 0,
                                "PVNo" => $voucherno,
                                "CCPVNo" => $CCPVNo,
                                "CPVNo" => $CPVNo,
                                "Narration" => $Narration,
                                "BillDate" => $BillDate,
                                "DeleteFlag" => 0,
                                "PVDate" => $PVDate,
                                "BillNo" => $BillNo,
                                "BranchId" => $BranchId,
                                "BranchTransId" => $BranchTransId,
                                "GateRegId" => $GatePassNo,
                                "Amount" => $totalamt,
                                "BillAmount" => $netAmount,
                                "GridType" => $gridtype,
                                "ThruPO" => 'Y',
                                "CurrencyId" => $CurrId));
                            $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                            $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PVRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                      $advTotal = $postParams['Arowid'];

                        for ($a = 1; $a <= $advTotal; $a++) {
                            if($this->bsf->isNullCheck($postParams['currentamount_' . $a],'number') > 0) {
                                $advanceInsert = $sql->insert('mms_advadjustment');
                                $advanceInsert->values(array(
                                    "BillRegisterId" => $PVRegisterId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number'),
                                    "PORegisterId" => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number')
                                ));
                                $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updadj = $sql->update();
                                $updadj->table('mms_popaymentterms');
                                $updadj->set(array(
                                    "AdjustAmount" => new Expression('AdjustAmount+' . $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') . '')
                                ));
                                $updadj->where(array('PORegisterId' => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number'),'TermsId' => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number') ));
                                $updAdjStatement = $sql->getSqlStringForSqlObject($updadj);
                                $dbAdapter->query($updAdjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $termsTotal = $postParams['trowid'];
                        for ($t = 1; $t <= $termsTotal; $t++) {

                            if ($this->bsf->isNullCheck($postParams['account_' . $t], 'number') > 0 &&
                                ($this->bsf->isNullCheck($postParams['value_' . $t], 'number') > 0 || $this->bsf->isNullCheck($postParams['value_' . $t], 'number') < 0)
                            ) {
                                $termsInsert = $sql->insert('MMS_PVPaymentTerms');
                                $termsInsert->values(array(
                                    "PVRegisterId" => $PVRegisterId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t], 'number'),
                                    "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number'),
                                    "AccountId" => $this->bsf->isNullCheck($postParams['account_' . $t], 'number')
                                ));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        }
                        $selectIPDAh = $sql->select();
                        $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER);
                        $resTotal = $postParams['rowid'];

                        for ($i = 1; $i < $resTotal; $i++) {

                            if (is_null($postParams['resourceid_' . $i]))
                                continue;

                            $potransInsert = $sql->insert('mms_pvGroupTrans');
                            $potransInsert->values(array(
                                "PVRegisterId" => $PVRegisterId,
                                "UnitId" => $postParams['unitid_' . $i],
                                "ResourceId" => $postParams['resourceid_' . $i],
                                "ItemId" => $postParams['itemid_' . $i],
                                "ActualQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "Description" => $this->bsf->isNullCheck($postParams['resspec_' . $i], 'string'),
                                "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number')));
                            $potransStatement = $sql->getSqlStringForSqlObject($potransInsert);
                            $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PVGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();



                            // stock details adding
                            $stockid=0;
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
                                $stockid=$stockselId['StockId'];
                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "BillQty" => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                    "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockid));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            else
                            {
                                if($postParams['qty_' . $i] != '' ||$postParams['qty_' . $i] > 0 ) {

                                    $stock = $sql->insert('mms_stock');
                                    $stock->values(array("CostCentreId" => $postParams['CostCenterId'],
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "UnitId" => $postParams['unitid_' . $i],
                                        "BillQty" => $postParams['qty_' . $i],
                                        "ClosingStock" => $postParams['qty_' . $i]
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $stockid = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                            } // end of stock

                            $whTotal = $postParams['wh_' . $i . '_rowid'];

                            for ($w = 1; $w <= $whTotal; $w++) {
                                if($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0 ) {
                                    $whInsert = $sql->insert('MMS_pvWHTrans');
                                    $whInsert->values(array("PVGroupId" => $PVGroupId,
                                        "WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],
                                        "BillQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . '')
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans adding
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],"StockId" => $stockid ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "BillQty" => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . ''),
                                            "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . '')
                                        ));
                                        $sUpdate->where(array("StockId" => $sId['StockId'],"WareHouseId"=>$postParams['wh_' . $i . '_warehouseid_' . $w]));
                                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else
                                    {
                                        if($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0 ) {
                                            $stock1 = $sql->insert('mms_stockTrans');
                                            $stock1->values(array("WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],
                                                "StockId" => $stockid,
                                                "BillQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                                "ClosingStock" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }

                            if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_rowids'], 'number') > 0) {
                                $ipdtransInsert = $sql->insert('mms_PVTrans');
                                $ipdtransInsert->values(array(
                                    "PVRegisterId" => $PVRegisterId,
                                    "PVGroupId" => $PVGroupId,
                                    "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i],
                                    "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "ActualQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "UnitId" => $postParams['unitid_' . $i]));
                                $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $dal = $postParams['wbs_' . $i . '_rowids'];
                                for ($m = 1; $m <= $dal; $m++) {
                                    if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $m], 'number') > 0) {
                                        $ipdanalInsert = $sql->insert('mms_PVAnalTrans');
                                        $ipdanalInsert->values(array(
                                            "PVGroupId" => $PVGroupId,
                                            "PVTransId" => $PVTransId,
                                            "AnalysisId" => $postParams['wbs_' . $i . '_wbsid_' . $m],
                                            "ResourceId" => $postParams['resourceid_' . $i],
                                            "ItemId" => $postParams['itemid_' . $i],
                                            "UnitId" => $postParams['unitid_' . $i],
                                            "BillQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $m], 'number'),
                                        ));
                                        $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                        $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $PVAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }

                            }
                            else if($postParams['iow_' . $i . '_rowid'] > 0) {
                                $decTotal = $postParams['iow_' . $i . '_rowid'];

                                for ($j = 1; $j <= $decTotal; $j++) {
                                    $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                    //IPDTrans
                                    $ipdtransInsert = $sql->insert('mms_PVTrans');
                                    $ipdtransInsert->values(array(
                                        "PVRegisterId" => $PVRegisterId,
                                        "PVGroupId" => $PVGroupId,
                                        "POTransId" => $postParams['iow_' . $i . '_POTransId_' . $j],
                                        "PORegisterId" => $postParams['iow_' . $i . '_PORegisterId_' . $j],
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "BillQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "ActualQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                        "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                        "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                        "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                        "UnitId" => $postParams['unitid_' . $i]));
                                    $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                    $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                    $ipdtransInsert->values(array(
                                        "PVTransId" => $PVTransId,
                                        "POTransId" => $postParams['iow_' . $i . '_POTransId_' . $j],
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "Status" => "U",
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $postParams['unitid_' . $i]));
                                    $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                    $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                    $ipdprojInsert->values(array(
                                        "PVTransId" => $PVTransId,
                                        "IPDTransId" => $IPDTransId,
                                        "Status" => "U",
                                        "CostCentreId" => $CostCenterId,
                                        "POProjTransId" => $postParams['iow_' . $i . '_POProjTransId_' . $j],
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $postParams['unitid_' . $i]));
                                    $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                    $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $transUpdate = $sql->update();
                                    $transUpdate->table('mms_POTrans');
                                    $transUpdate->set(array(
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                        'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                    $transUpdate->where(array('POTransId' => $postParams['iow_' . $i . '_POTransId_' . $j]));
                                    $transStatement = $sql->getSqlStringForSqlObject($transUpdate);
                                    $dbAdapter->query($transStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $ProjtransUpdate = $sql->update();
                                    $ProjtransUpdate->table('mms_poprojTrans');
                                    $ProjtransUpdate->set(array(
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                        'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                    $ProjtransUpdate->where(array('POProjTransId' => $postParams['iow_' . $i . '_POProjTransId_' . $j]));
                                    $ProjtransStatement = $sql->getSqlStringForSqlObject($ProjtransUpdate);
                                    $dbAdapter->query($ProjtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    for ($k = 1; $k <= $wbsTotal; $k++) {
                                        if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') > 0) {
                                            //IPDAnalTrans
                                            $ipdanalInsert = $sql->insert('mms_PVAnalTrans');
                                            $ipdanalInsert->values(array(
                                                "PVGroupId" => $PVGroupId,
                                                "PVTransId" => $PVTransId,
                                                "AnalysisId" => $postParams['iow_' . $i . '_PO_' . $j . '_wbsid_' . $k . ''],
                                                "ResourceId" => $postParams['resourceid_' . $i],
                                                "ItemId" => $postParams['itemid_' . $i],
                                                "UnitId" => $postParams['unitid_' . $i],
                                                "BillQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number'),
                                            ));
                                            $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                            $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $PVAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $ipdanalInsert = $sql->insert('MMS_IPDAnalTrans');
                                            $ipdanalInsert->values(array(
                                                "IPDProjTransId" => $IPDProjTransId,
                                                "PVAnalTransId" => $PVAnalTransId,
                                                "Status" => "U",
                                                "AnalysisId" => $postParams['iow_' . $i . '_PO_' . $j . '_wbsid_' . $k . ''],
                                                "ResourceId" => $postParams['resourceid_' . $i],
                                                "ItemId" => $postParams['itemid_' . $i],
                                                "UnitId" => $postParams['unitid_' . $i],
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number'),
                                                "POAHTransId" => $postParams['iow_' . $i . '_PO_' . $j . '_POAnalTransId_' . $k . '']));
                                            $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                            $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $AnalUpdate = $sql->update();
                                            $AnalUpdate->table('MMS_POAnalTrans');
                                            $AnalUpdate->set(array(
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') . ''),
                                                'BillQty' => new Expression('BillQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_PO_' . $j . '_qty_' . $k . ''], 'number') . '')
                                            ));
                                            $AnalUpdate->where(array('POAnalTransId' => $postParams['iow_' . $i . '_PO_' . $j . '_POAnalTransId_' . $k . '']));
                                            $AnalStatement = $sql->getSqlStringForSqlObject($AnalUpdate);
                                            $dbAdapter->query($AnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                }
                            } else {
                                $pvtraninsert = $sql->insert('mms_PVTrans');
                                $pvtraninsert->values(array(
                                    "PVRegisterId" => $PVRegisterId,
                                    "PVGroupId" => $PVGroupId,
                                    "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i],
                                    "BillQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "ActualQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "UnitId" => $postParams['unitid_' . $i]));
                                $pvtranStatement = $sql->getSqlStringForSqlObject($pvtraninsert);
                                $dbAdapter->query($pvtranStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                            //Qualifier Insert
                            $qual = $postParams['QualRowId_' . $i];
                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_pvQualTrans');
                                    $qInsert->values(array("PVRegisterId" => $PVRegisterId, "PVGroupId" => $PVGroupId,
                                        "QualifierId" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Id_' . $q], 'number' . ''), "YesNo" => "1",
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number' . ''),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number' . ''),
                                        "Sign" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Sign_' . $q], 'string' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number' . ''),
                                        "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number' . ''),
                                        "Expression" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Exp_' . $q], 'string' . ''),
                                        "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number')));
                                    $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                    $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            //Gross Rate Update
                            $selGross = $sql -> select();
                            $selGross->from(array("a" => "MMS_PVTrans"))
                                ->columns(array(new Expression("a.PVTransId,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.PVTransId Order by A.PVTransId asc))=1 Then A.QAmount Else 0 End QAmt,
                                    Case When c.QualifierTypeId=3 Then ISNULL(b.NetAmt,0) Else 0 End VatAmt,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.PVTransId Order by A.PVTransId asc))=1 Then ISNULL(A.BillQty,0) Else 0 End As BillQty")))
                                ->join(array('b' => 'MMS_PVQualTrans'),'a.PVGroupId=b.PVGroupId',array(),$selGross::JOIN_LEFT)
                                ->join(array('c' => 'Proj_QualifierMaster'),'b.QualifierId=c.QualifierId',array(),$selGross::JOIN_LEFT)
                                ->where("a.PVRegisterId=$PVRegisterId");

                            $selGross1 = $sql -> select();
                            $selGross1->from(array("g" => $selGross))
                                ->columns(array(new Expression("g.PVTransId,(SUM(G.QAmt)-SUM(G.VatAmt))/SUM(G.BillQty) As GrossRate")));
                            $selGross1->group(new Expression("g.PVTransId"));
                            $statement = $sql->getSqlStringForSqlObject($selGross1);
                            $arr_gross = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($arr_gross as $gross) {
                                $grossUpdate = $sql->update();
                                $grossUpdate->table('MMS_PVTrans');
                                $grossUpdate->set(array(
                                        "GrossRate" => new Expression($this->bsf->isNullCheck($gross["GrossRate"], 'number')),
                                        "GrossAmount" => new Expression('CAST(BillQty*' . $this->bsf->isNullCheck($gross["GrossRate"], 'number') . ' As Decimal(18,3)) ')
                                    )
                                );
                                $grossUpdate->where(array("PVTransId" => $gross['PVTransId']));
                                $grossUpdateStatement = $sql->getSqlStringForSqlObject($grossUpdate);
                                $dbAdapter->query($grossUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //Gross Amount Calculation
                            $selGTotal = $sql -> select();
                            $selGTotal -> from(array("a" => "MMS_PVTrans"))
                                ->columns(array(new Expression("SUM(GrossAmount) As GrossAmount")))
                                ->where("PVRegisterId=$PVRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($selGTotal);
                            $arr_gtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if(count($arr_gtotal) > 0) {
                                $gtotalUpdate = $sql -> update();
                                $gtotalUpdate -> table('MMS_PVRegister');
                                $gtotalUpdate->set(array(
                                        "GrossAmount" =>new Expression($this->bsf->isNullCheck($arr_gtotal["GrossAmount"], 'number')))
                                );
                                $gtotalUpdate->where(array("PVRegisterId" => $PVRegisterId));
                                $gtotalStatement = $sql->getSqlStringForSqlObject($gtotalUpdate);
                                $dbAdapter->query($gtotalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            // END OF

                        } //end of i
                        $qrow = $postParams['Qrowid'];
                        $type='p';
                        if(count($qrow) > 0) {
                            for ($r = 0; $r < $qrow; $r++) {
                                if($this->bsf->isNullCheck($postParams['qualamount_' . $r], 'number' . '') > 0 ||$this->bsf->isNullCheck($postParams['qualamount_' . $r], 'number' . '') < 0) {
                                    $qInsert = $sql->insert('mms_QualTrans');
                                    $qInsert->values(array(
                                        "RegisterId" => $PVRegisterId,
                                        "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . ''),
                                        "Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                        "Rate" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                        "Amount" => $this->bsf->isNullCheck($postParams['qualamount_' . $r], 'number' . ''),
                                        "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                        "Type" => $type));
                                    $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                    $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $Update = $sql->update();
                                    $Update->table('mms_PVQualTrans');
                                    $Update->set(array(
                                        "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . '')
                                    ));
                                    $Update->where(array("Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                        "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . '')));
                                    $statement = $sql->getSqlStringForSqlObject($Update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        } //end of qualtrans
                    }
                    $vType = CommonHelper::GetVoucherType(306,$dbAdapter);
                    if ($vType == "  " || $vType == "GE"){
                        $sPVNo= $voucherno;
                    } else if ($vType == "CC"){
                        $sPVNo= $CCPVNo;
                    } else if ($vType == "CO") {
                        $sPVNo= $CPVNo;
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Bill',$PVRegisterId,$CostCenterId,$CompanyId, 'MMS',$sPVNo,$this->auth->getIdentity()->UserId,$netAmount,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'purchasebill', 'action' => 'purchasebill-register'));
                }catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }
    }
    public function displaypurchasebillAction()	{
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
        $PVRegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('rid'),'number');

        $request = $this->getRequest();
        $response = $this->getResponse();

        /*Ajax Request*/
        if($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {

                $postParam = $request->getPost();
				
				if($postParam['Type'] == 'getqualdetails'){

                    $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                    $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                    $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

					
					
					$select = $sql->select();
					$select->from(array("c" => "MMS_PVQualTrans"))
						->columns(array('ResourceIbindDetailsActiond'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
							'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
							'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
							'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
							'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
							'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
							'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
							'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3)) '),'QualifierName'=>new Expression('b.QualifierName'),
							'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
						->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
						->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);
					$select->where(array('a.QualType' => 'M', 'c.PvRegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
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
		
		$select = $sql->select();
		$select->from(array('a' => 'WF_OperationalCostCentre'))
			->columns(array('CostCentreId', 'CostCentreName'))
			->join(array('b'=>'MMS_PVRegister'),'a.CostCentreId=b.CostCentreId',array(),$select::JOIN_INNER)
			->where("a.Deactivate=0 AND b.PVRegisterId=$PVRegisterId");
		$statement = $sql->getSqlStringForSqlObject($select);
		$costCenterIds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->costcenter=$costCenterIds;
		$costCentreId=$costCenterIds['CostCentreId'];
		// vendor details
		$select = $sql->select();
		$select->from(array('a'=>'Vendor_Master'))
			->columns(array('SupplierId'=> new Expression('a.VendorId') ,'SupplierName'=> new Expression('a.VendorName'), 'LogoPath'))
			->join(array('b'=>'MMS_PVRegister'),'a.VendorId=b.VendorId')
			->where("b.PVRegisterId=$PVRegisterId");
		$statement = $sql->getSqlStringForSqlObject($select);
		$supplierIds= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->Suppliers =$supplierIds;
		$supplierId=$supplierIds['SupplierId'];


		$selPOReg=$sql->select();
		$selPOReg->from(array('a'=>'MMS_PVRegister'))
			->columns(array('CostCentreId' => new Expression('a.CostCentreId'),
				'SupplierId'=> new Expression('a.VendorId'),
				'SupplierName'=> new Expression('c.VendorName'),
				'CurrencyId'=> new Expression('a.CurrencyId'),
				'CostCentreName'=> new Expression('b.CostCentreName'),
				'SupplierName'=> new Expression('c.VendorName'),
				'PVDate'=> new Expression('Convert(Varchar(10),a.PVDate,103)'),
				'BillDate'=> new Expression('Convert(Varchar(10),a.BillDate,103)'),
				'PVNo'=> new Expression('a.PVNo'),
				'CCPVNo'=> new Expression('a.CCPVNo'),
				'CPVNo'=> new Expression('a.CPVNo'),
				'BillNo'=> new Expression('a.BillNo'),
				'Narration'=> new Expression('a.Narration'),
				'BillAmount'=> new Expression('a.BillAmount'),
				'PurchaseTypeId'=> new Expression('a.PurchaseTypeId'),
				'GateRegId'=> new Expression('a.GateRegId'),
				'Approve'=> new Expression('a.Approve'),
				'PurchaseAccount'=> new Expression('a.PurchaseAccount'),
				'Amount'=> new Expression('a.Amount'),
				'GridType'=> new Expression('a.GridType')))
			->join(array('b'=>'WF_OperationalCostCentre'),'a.CostCentreId=b.CostCentreId',array(),$selPOReg::JOIN_INNER)
			->join(array('c'=>'Vendor_Master'),'a.VendorId=c.VendorId',array(),$selPOReg::JOIN_INNER)
			->where(array("a.PVRegisterId"=>$PVRegisterId));
		$statement = $sql->getSqlStringForSqlObject( $selPOReg );
		$this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$CostCentreId=$this->_view->register ['CostCentreId'];
		$SupplierId=$this->_view->register ['SupplierId'];
		$SupplierName=$this->_view->register ['SupplierName'];
		$Narration=$this->_view->register ['Narration'];
		$CurrencyId=$this->_view->register ['CurrencyId'];
		$PVDate=$this->_view->register ['PVDate'];
		$PVNo=$this->_view->register ['PVNo'];
		$CCPVNo=$this->_view->register ['CCPVNo'];
		$CPVNo=$this->_view->register ['CPVNo'];
		$BillNo=$this->_view->register ['BillNo'];
		$BillDate=$this->_view->register ['BillDate'];
		$PurchaseTypeId=$this->_view->register ['PurchaseTypeId'];
		$PurchaseAccount=$this->_view->register ['PurchaseAccount'];
		$BillAmount=$this->_view->register ['BillAmount'];
		$GateRegId=$this->_view->register ['GateRegId'];
		$Approve=$this->_view->register ['Approve'];
		$Amount=$this->_view->register ['Amount'];
		$gridtype=$this->_view->register ['GridType'];

		$this->_view->purchasetype= $PurchaseTypeId;
		$this->_view->GateRegId= $GateRegId;
		$this->_view->BillAmount= $BillAmount;
		$this->_view->accounttype=$PurchaseAccount;
		$this->_view->Narration=$Narration;
		$this->_view->vNo = $PVNo;
		$this->_view->currency= $CurrencyId;
		$this->_view->CostCentreId= $CostCentreId;
		$this->_view->SupplierId= $SupplierId;
		$this->_view->SupplierName= $SupplierName;
		$this->_view->PODate= $PVDate;
		$this->_view->CCPVNo= $CCPVNo;
		$this->_view->CPVNo= $CPVNo;
		$this->_view->BillNo= $BillNo;
		$this->_view->BillDate= $BillDate;
		$this->_view->PVRegisterId =$PVRegisterId;
		$this->_view->Approve =$Approve;
		$this->_view->Amount =$Amount;
		$this->_view->gridtype =$gridtype;


        $select = $sql->select();
        $select->from(array("a" => "MMS_PurchaseType"))
            ->columns(array(new Expression("b.AccountId as AccountId ,b.AccountName as AccountName")))
            ->join(array('b' => 'FA_AccountMaster'), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
            ->where(array('a.PurchaseTypeId' => $PurchaseTypeId));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $regResult1 = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->PurchaseAccount = $regResult1['AccountName'];

		$select = $sql->select();
		$select->from(array("a"=>"mms_pvTrans"))
			->columns(array(
				'PVGroupId' => new Expression('a.PVGroupId'),
				'PVTransId' => new Expression('a.PVTransId'),
				'POTransId' => new Expression('a.POTransId'),
				'PORegisterId' => new Expression('a.PORegisterId'),
				'ResourceId' => new Expression('a.ResourceId'),
				'ItemId' => new Expression('a.ItemId'),
				'UnitId' => new Expression('a.UnitId'),
				'Desc' => new Expression("Case When a.ItemId>0 Then c.ItemCode+''+c.BrandName Else b.Code+''+b.ResourceName End"),
				'UnitName' => new Expression('d.UnitName'),
				'Qty' => new Expression("CAST(a.BillQty As Decimal(18,3))"),
				'Rate' => new Expression('a.Rate'),
				'ResSpec' => new Expression('e.Description'),
				'QRate' => new Expression('a.QRate'),
				'BaseAmount' => new Expression('a.Amount'),
				'Amount' => new Expression('a.QAmount'),
				'RFrom'=>new Expression('Case When a.ResourceId IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId='.$CostCentreId.') Then '."'Project'".' Else '."'Library'".' End ')  ))
			->join(array('b'=>'Proj_Resource'),'a.ResourceId=b.ResourceId',array(),$select::JOIN_INNER)
			->join(array('c'=>'MMS_Brand'),'a.ResourceId=b.ResourceId and a.ItemId=c.BrandId',array(),$select::JOIN_LEFT)
			->join(array('d'=>'Proj_UOM'),'a.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
			->join(array('e'=>'mms_pvGroupTrans'),'a.PVGroupId=e.PVGroupId',array(),$select::JOIN_LEFT)
			->where('a.PVRegisterId='.$PVRegisterId.'');
		$statement = $sql->getSqlStringForSqlObject($select);
		$poTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->arr_requestResources = $poTrans;

		$select = $sql->select();
		$select->from(array("a" => "MMS_POTrans"))
			->columns(array(
				'PORegisterId'=>new Expression('c.PORegisterId'),
				'POTransId'=>new Expression('a.POTransId'),
				'POProjTransId'=>new Expression('b.POProjTransId'),
				'PONo'=>new Expression('c.PONo'),
				'PODate'=>new Expression('Convert(Varchar(10),c.POdate,103)'),
				'ResourceId'=>new Expression('a.ResourceId'),
				'ItemId'=>new Expression('a.ItemId'),
				'POQty'=>new Expression('CAST(a.POQty As Decimal(18,3))'),
				'BalQty'=>new Expression('CAST(a.BalQty As Decimal(18,3))'),
				'Qty'=>new Expression('CAST(d.BillQty As Decimal(18,3))')))
			->join(array('b' => 'MMS_POProjTrans'), 'a.PoTransId=b.PoTransId', array(), $select::JOIN_INNER)
			->join(array('c' => 'MMS_PORegister'), 'a.PORegisterId=c.PORegisterId', array(), $select::JOIN_INNER)
			->join(array('d' => 'mms_PVTrans'), 'a.POTransId=d.POTransId', array(), $select::JOIN_INNER)
			->where('d.PVRegisterId='.$PVRegisterId.'');
		$select->group(array("c.PORegisterId","a.POTransId",
			"b.POProjTransId","c.PONo","c.POdate","a.ResourceId",
			"a.ItemId","a.POQty","a.BalQty","d.BillQty"));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$wbsRes = $sql -> select();
		$wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
			->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
			->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
			->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
			->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$select::JOIN_INNER)
			->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentreId");
		$statement = $sql->getSqlStringForSqlObject($wbsRes);
		$this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$selectAnal = $sql->select();
		$selectAnal->from(array("a" => "MMS_IPDAnalTrans"))
			->columns(array(
				'IPDAHTransId' => new Expression('a.IPDAHTransId'),
				'IPDTransId' => new Expression('f.IPDTransId'),
				'IPDProjTransId' => new Expression('f.IPDProjTransId'),
				'POProjTransId' => new Expression('f.POProjTransId'),
				'POTransId' => new Expression('e.POTransId'),
				'PORegisterId' => new Expression('e.PORegisterId'),
				'PVAnalTransId' => new Expression('a.PVAnalTransId'),
				'WBSId' => new Expression('c.WBSId'),
				'POAnalTransId' => new Expression('a.POAHTransId'),
				'PVTransId' => new Expression('e.PVTransId'),
				'ResourceId' => new Expression('b.ResourceId'),
				'ItemId' => new Expression('b.ItemId'),
				'WBSName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
				'POQty' => new Expression('CAST(B.POQty As Decimal(18,3))'),
				'BalQty' => new Expression('CAST(B.BalQty As Decimal(18,3))'),
				'Qty' => new Expression('CAST(A.Qty As Decimal(18,3))')))
			->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selectAnal::JOIN_INNER)
			->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId And b.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_INNER)
			->join(array("d" => "MMS_PVAnalTrans"), " a.PVAnalTransId=d.PVAnalTransId ", array(), $selectAnal::JOIN_INNER)
			->join(array("e" => "MMS_PVTrans"), " d.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_INNER)
			->join(array("f" => "mms_IPDprojTrans"), " a.IPDProjTransId=f.IPDProjTransId", array(), $selectAnal::JOIN_INNER);
		$selectAnal->where(array("e.PVRegisterId = $PVRegisterId and a.Status='U'"));
		$statement1 = $sql->getSqlStringForSqlObject($selectAnal);
		$this->_view->arr_resource_iow_requests = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
		$this->_view->qualList = $qualList;

		$arrqual = array();
		$select = $sql->select();
		$select->from(array("a" => "MMS_PVQualTrans"))
			->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
				'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
				'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
				'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'))
		->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER);
		$select->where(array('a.PVRegisterId'=>$PVRegisterId));
		$select->order('a.SortId ASC');
		$statement = $sql->getSqlStringForSqlObject($select);
		$qualAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->qualAccList = $qualAccList;

		$select = $sql->select();
		$select->from(array("a" => "FA_AccountMaster"))
			->columns(array('AccountId','AccountName','TypeId'));
		$select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$subWbs1=$sql->select();
		$subWbs1->from(array('a'=>'Proj_WBSMaster'))
			->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,0 As Qty")))
			->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$subWbs1::JOIN_INNER)
			->where -> expression('a.LastLevel=1 And b.CostCentreId='.$CostCentreId.'
			And a.WBSId NOT IN (Select AnalysisId From mms_PVAnalTrans Where PVTransId IN (Select PVTransId From MMS_PVTrans Where PVRegisterId=?))', $PVRegisterId);
		$selectAnal = $sql->select();
		$selectAnal->from(array("a" => "MMS_PVAnalTrans"))
			->columns(array(
				'ResourceId' => new Expression('e.ResourceId'),
				'ItemId' => new Expression('e.ItemId'),
				'WbsId' => new Expression('c.WBSId'),
				'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
				'Qty' => new Expression('CAST(A.BillQty As Decimal(18,3))')))
			->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
			->join(array("e" => "MMS_PVTrans"), " a.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_LEFT);
		$selectAnal->where(array("e.PVRegisterId = $PVRegisterId"));
		$selectAnal->combine($subWbs1, 'Union ALL');
		$statement1 = $sql->getSqlStringForSqlObject($selectAnal);
		$this->_view->arr_wb = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql -> select();
		$select -> from (array("a" => "Proj_Resource"))
			->columns(array('data'=>new Expression('a.ResourceId'),"AutoFlag"=>new Expression("1-1"),'ItemId'=>new Expression('isnull(d.BrandId,0)'),
				'Code'=>new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
				'value'=>new Expression("Case When isnull(d.BrandId,0)>0 Then '('+d.ItemCode+')'+ ' ' +d.BrandName Else '('+a.Code+')'+ ' ' +a.ResourceName End"),
				'UnitName'=>new Expression('c.UnitName'),'UnitId'=>new Expression('c.UnitId'),
				'Rate'=>new Expression('Case When isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End'),
				'RFrom'=>new Expression("'Project'")    ))
			->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$select::JOIN_LEFT)
			->join(array("c" => "Proj_UOM"),"a.UnitId=c.UnitId",array(),$select::JOIN_LEFT)
			->join(array("d" => "MMS_Brand"),"a.ResourceId=d.ResourceId",array(),$select::JOIN_LEFT)
			->join(array("e" => "Proj_ProjectResource"),"a.ResourceId=e.ResourceId",array(),$select::JOIN_INNER)
			->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
			->where("f.CostCentreId=".$CostCentreId." and (a.ResourceId NOT IN (Select ResourceId From MMS_PVTrans
			 Where PVRegisterId=".$PVRegisterId.") Or isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_PVTrans Where PVRegisterId=".$PVRegisterId."))");

		$selRa = $sql -> select();
		$selRa->from(array("a" => "Proj_Resource"))
			->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
					Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
					Case when isnull(c.BrandId,0)>0 Then '('+c.ItemCode+')'+ ' ' +c.BrandName Else '('+a.Code+')'+ ' ' +a.ResourceName End As value,
					Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
					Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
					Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) else CAST(a.Rate As Decimal(18,2)) End As Rate,'Library' As RFrom  ")))
			->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
			->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
			->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
			->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
			->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
					Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentreId .") and (a.ResourceId NOT IN (Select ResourceId From MMS_PVTrans
			 Where PVRegisterId=".$PVRegisterId.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_PVTrans Where PVRegisterId=".$PVRegisterId."))  ");
		$select -> combine($selRa,"Union All");

		 $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql ->select();
		$select->from(array("a" => "MMS_WareHouse"))
			->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,3)) Qty,CAST(0 As Decimal(18,2)) HiddenQty")))
			->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
			->where(array("b.CostCentreId=  $CostCentreId And c.LastLevel=1"));
		$selectStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array("a" => "mms_pvwhTrans"))
			->columns(array(new Expression("e.ResourceId,e.ItemId,c.CostCentreId,b.TransId as WareHouseId,d.WareHouseName,b.Description,CAST(a.BillQty As Decimal(18,2)) Qty")))
			->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("e" => "MMS_PVGroupTrans"), "a.pvGroupId=e.pvGroupId", array(), $select::JOIN_INNER)
			->where(array('c.CostCentreId=' . $CostCentreId . ' And b.LastLevel=1 And e.PVRegisterId = ' . $PVRegisterId . ' '));
		$selectStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_warehouseQty = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//TERMS AND CONDITION

		$select1 = $sql->select();
		$select1->from(array("A" => "mms_PVPaymentTerms"))
			->columns(array(
				'TermsId'=>new Expression('A.TermsId'),
				'Terms'=>new Expression('b.Title'),
				'Value'=>new Expression("A.Value"),
				'AccountId'=>new Expression("A.AccountId"),
				'Account'=>new Expression("c.AccountName")
			))
			->join(array('b' => 'wf_TermsMaster'), 'a.TermsId=b.TermsId', array(), $select1:: JOIN_LEFT)
			->join(array('c' => 'fa_AccountMaster'), 'a.AccountId=c.AccountId', array(), $select1:: JOIN_LEFT)
			->where("PVRegisterId=$PVRegisterId");
		$statement = $sql->getSqlStringForSqlObject($select1);
		$this->_view->terms_ent= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array("A" => "FA_AccountMaster"))
			->columns(array(
				'AccountId'=>new Expression('A.AccountId'),
				'AccountName'=>new Expression('A.AccountName')
			))
			->where("A.LastLevel='Y' And A.TypeId=22");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->Account= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array("A" => "Vendor_Branch"))
			->columns(array(
				'BranchId'=>new Expression('A.BranchId'),
				'BranchName'=>new Expression('A.BranchName')
			))
			->where("VendorId = $SupplierId");
		$statement = $sql->getSqlStringForSqlObject($select);
		$branch= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->branch=$branch;


		$select = $sql->select();
		$select->from(array("A" => "mms_pvregister"))
			->columns(array(
				'BranchName'=>new Expression('b.BranchName'),
				'BranchId'=>new Expression('b.BranchId'),
				'BranchTransId'=>new Expression('c.BranchTransId'),
				'Phone'=>new Expression('b.Phone'),
				'ContactPerson'=>new Expression("c.ContactPerson"),
				'ContactNo'=>new Expression("c.ContactNo"),
			))
			->join(array('b' => 'Vendor_Branch'), 'a.BranchId=b.BranchId', array(), $select:: JOIN_INNER)
			->join(array('c' => 'Vendor_BranchContactDetail'), 'a.BranchTransId=c.BranchTransId', array(), $select:: JOIN_INNER)
			->where("PVRegisterId=$PVRegisterId");
		$statement = $sql->getSqlStringForSqlObject($select);
		$branches= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->bcount = count($branches['BranchId']);
		$this->_view->branches=$branches;
		$BranchName=$this->_view->branches ['BranchName'];
		$BranchId=$this->_view->branches ['BranchId'];
		$BranchTransId=$this->_view->branches ['BranchTransId'];
		$Phone=$this->_view->branches ['Phone'];
		$ContactPerson=$this->_view->branches ['ContactPerson'];
		$ContactNo=$this->_view->branches ['ContactNo'];
		$this->_view->BranchName= $BranchName;
		$this->_view->BranchId= $BranchId;
		$this->_view->BranchTransId= $BranchTransId;
		$this->_view->Phone= $Phone;
		$this->_view->ContactPerson= $ContactPerson;
		$this->_view->ContactNo= $ContactNo;
		
        $regDetails = $sql->select();
        $regDetails->from(array("a" => "MMS_PVRegister"))
            ->columns(array(
                'PVNo' => new Expression('a.PVNo'),
                'PVDate' => new Expression('Convert(Varchar(10),a.PVDate,103)'),
                'CostCentreName' => new Expression('b.CostCentreName'),
                'SupplierName' => new Expression('c.VendorName'),
                'BillNo' => new Expression('a.BillNo'),
                'PurchaseTypeName' => new Expression('e.PurchaseTypeName'),
                'CurrencyName' => new Expression('d.CurrencyName'),
                'Approve' => new Expression("Case when a.Approve='Y' then 'Yes' Else 'No' End "),
            ))
            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $regDetails::JOIN_INNER)
            ->join(array("c" => "Vendor_Master"), "a.VendorId=c.VendorId", array(), $regDetails::JOIN_INNER)
            ->join(array("d" => "WF_CurrencyMaster"), "a.CurrencyId=d.CurrencyId", array(), $regDetails::JOIN_INNER)
            ->join(array("e" => "MMS_PurchaseType"), "a.PurchaseTypeId=e.PurchaseTypeId", array(), $regDetails::JOIN_INNER)
            ->where(array('a.PVRegisterId' => $PVRegisterId));
        $regStatement = $sql->getSqlStringForSqlObject($regDetails);
        $regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->PVNo = $regResult['PVNo'];
        $this->_view->PVDate = $regResult['PVDate'];
        $this->_view->CostCentreName = $regResult['CostCentreName'];
        $this->_view->SupplierName = $regResult['SupplierName'];
        $this->_view->BillNo = $regResult['BillNo'];
        $this->_view->PurchaseTypeName = $regResult['PurchaseTypeName'];
        $this->_view->CurrencyName = $regResult['CurrencyName'];
        $this->_view->Approve = $regResult['Approve'];
        $this->_view->PVRegisterId = $PVRegisterId;
        //Common function
        // $selectVendor = $sql->select();
        // $selectVendor->from(array("a"=>"mms_pvRegister"));
        // $selectVendor->columns(array(new Expression("a.PVRegisterId,e.PVGroupId,
                    // f.PVTransId,f.POTransId,f.PORegisterId,e.ResourceId,e.ItemId,c.UnitName,
                    // e.ItemId,Case When e.ItemId>0 Then d.ItemCode Else b.Code
                    // End As Code,Case When e.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,
                    // CAST(e.BillQty As Decimal(18,5)) As BillQty,Convert(Varchar(10),a.PVDate,103) As PVDate,
                    // e.Rate,e.Amount")))
            // ->join(array("e"=>'mms_pvGroupTrans'), "e.PVRegisterId=a.PVRegisterId", array(), $selectVendor::JOIN_LEFT)
            // ->join(array("b"=>"Proj_Resource"), "e.ResourceId=b.ResourceId", array(), $selectVendor::JOIN_INNER)
            // ->join(array("c"=>"Proj_UOM"), "e.UnitId=c.UnitId", array(), $selectVendor::JOIN_LEFT)
            // ->join(array("d"=>"MMS_Brand"),"e.ResourceId=d.ResourceId And e.ItemId=d.BrandId",array(),$selectVendor::JOIN_LEFT)
            // ->join(array("f"=>"MMS_pvtrans"),"e.PVRegisterId=f.PVRegisterId And e.PVGroupId=f.PVGroupId",array(),$selectVendor::JOIN_INNER);
        // $selectVendor->where(array("a.PVRegisterId"=>$PVRegisterId));
        // $statement = $sql->getSqlStringForSqlObject($selectVendor);
        // $trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // $select = $sql->select();
        // $select->from(array("a"=>"MMS_IPDTrans"));
        // $select -> columns(array(
            // 'PVTransId' => new Expression('c.PVTransId'),
            // 'IPDTransId' => new Expression('a.IPDTransId'),
            // 'IPDProjTransId' => new Expression('e.IPDProjTransId'),
            // 'PVTransId' => new Expression('a.PVTransId'),
            // 'ResourceId' => new Expression('b.ResourceId'),
            // 'ItemId' => new Expression('b.ItemId'),
            // 'PONo' => new Expression('d.PONo'),
            // 'PODate' => new Expression('Convert(Varchar(10),D.PODate,103)'),
            // 'POQty' => new Expression('CAST(B.POQty As Decimal(18,6))'),
            // 'BalQty' => new Expression('CAST(B.BalQty As Decimal(18,6))'),
            // 'Qty' => new Expression('CAST(A.Qty As Decimal(18,6))')))
            // ->join(array("b"=>'mms_POTrans'), "a.POTransId=b.PoTransId", array(), $select::JOIN_INNER)
            // ->join(array("c"=>'MMS_PVTrans'), "c.PVTransId=a.PVTransId", array(), $select::JOIN_INNER)
            // ->join(array("d"=>'MMS_PORegister'), "b.PORegisterId=d.PORegisterId", array(), $select::JOIN_INNER)
            // ->join(array("e"=>'MMS_IPDProjTrans'),"a.IPDTransId=e.IPDTransId",array(),$select::JOIN_INNER);
        // $select->where(array("c.PVRegisterId = $PVRegisterId and a.Status= 'U'"));
        // $statement = $sql->getSqlStringForSqlObject($select);
        // $trans1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // $selectAnal = $sql->select();
        // $selectAnal->from(array("a" => "MMS_IPDAnalTrans"))
            // ->columns(array(
                // 'IPDAHTransId' => new Expression('a.IPDAHTransId'),
                // 'IPDTransId' => new Expression('f.IPDTransId'),
                // 'IPDProjTransId' => new Expression('f.IPDProjTransId'),
                // 'PVAnalTransId' => new Expression('a.PVAnalTransId'),
                // 'PVTransId' => new Expression('e.PVTransId'),
                // 'ResourceId' => new Expression('b.ResourceId'),
                // 'ItemId' => new Expression('b.ItemId'),
                // 'WBSName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
                // 'POQty' => new Expression('CAST(B.POQty As Decimal(18,6))'),
                // 'BalQty' => new Expression('CAST(B.BalQty As Decimal(18,6))'),
                // 'Qty' => new Expression('CAST(A.Qty As Decimal(18,6))')))
            // ->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selectAnal::JOIN_INNER)
            // ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId And b.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_INNER)
            // ->join(array("d" => "MMS_PVAnalTrans"), " a.PVAnalTransId=d.PVAnalTransId ", array(), $selectAnal::JOIN_INNER)
            // ->join(array("e" => "MMS_PVTrans"), " d.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_INNER)
            // ->join(array("f" => "mms_IPDprojTrans"), " a.IPDProjTransId=f.IPDProjTransId", array(), $selectAnal::JOIN_INNER);
        // $selectAnal->where(array("e.PVRegisterId = $PVRegisterId and a.Status='U'"));
        // $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
        // $anal = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // $selectAnal = $sql->select();
        // $selectAnal->from(array("a" => "MMS_PVAnalTrans"))
            // ->columns(array(
                // 'PVAnalTransId' => new Expression('a.PVAnalTransId'),
                // 'PVTransId' => new Expression('e.PVTransId'),
                // 'ResourceId' => new Expression('e.ResourceId'),
                // 'ItemId' => new Expression('e.ItemId'),
                // 'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
                // 'Qty' => new Expression('CAST(A.BillQty As Decimal(18,6))')))
            // ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_INNER)
            // ->join(array("e" => "MMS_PVTrans"), " a.PVTransId=e.PVTransId", array(), $selectAnal::JOIN_INNER);
        // $selectAnal->where(array("e.PVRegisterId = $PVRegisterId  and e.POTransId = 0"));
        // $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
        // $anal1 = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // $this->_view->anal = $anal;
        // $this->_view->anal1 = $anal1;
        // $this->_view->trans1 = $trans1;
        // $this->_view->trans = $trans;
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }
    public function purchasebillregisterAction()	{
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
//        $id = $this->params()->fromRoute('rid');
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postParam = $request->getPost();
                if($postParam['mode'] == 'first'){
                    $regSelect = $sql->select();
                    $regSelect->from(array("a"=>"mms_pvregister"))
                        ->columns(array(new Expression("a.PVRegisterId,a.PVDate,a.PVNo,CAST(a.Amount As Decimal(18,2)) As BillAmount,Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"a.CostCentreId=c.CostCentreId",array("CostCentreName"),$regSelect::JOIN_INNER)
                        ->where(array("a.DeleteFlag = 0 and a.ThruPO='Y'"))
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
    public function pbilldeleteAction(){
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
        $PVRegisterId=$this->params()->fromRoute('regId');
        if($request->isXmlHttpRequest())
        {
            if ($request->isPost())
            {
                $resp = array();
                //Write your Ajax post code here

                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        }
        $selAnal=$sql->select();
        $selAnal->from(array("a"=>"MMS_IPDAnalTrans"))
            ->columns(array(new Expression("D.POAnalTransId as POAnalTransId,A.Qty as Qty")))
            ->join(array("b"=>"MMS_PVAnalTrans"),"a.PVAnalTransId=b.PVAnalTransId",array(),$selAnal::JOIN_INNER)
            ->join(array("c"=>"MMS_PVTrans"),"b.PVTransId=c.PVTransId",array(),$selAnal::JOIN_INNER)
            ->join(array("d"=>"MMS_POAnalTrans"),"a.POAHTransId=d.POAnalTransId",array(),$selAnal::JOIN_INNER)
            ->where(array("c.PVRegisterId = $PVRegisterId and  A.Status='U'"));
        $statementPrev = $sql->getSqlStringForSqlObject($selAnal);
        $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($prevanal as $arrprevanal)
        {
            $updAnal=$sql->update();
            $updAnal->table('MMS_POAnalTrans');
            $updAnal->set(array(
                'BillQty'=> new Expression('BillQty-'.$arrprevanal['Qty'].''),
                'BalQty'=> new Expression('BalQty+'.$arrprevanal['Qty'].'')
            ));
            $updAnal->where(array('POAnalTransId'=>$arrprevanal['POAnalTransId']));
            $updAnalStatement = $sql->getSqlStringForSqlObject($updAnal);
            $dbAdapter->query($updAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $selTrans=$sql->select();
        $selTrans->from(array("a"=>"MMS_IPDTrans"))
            ->columns(array(new Expression("C.POTransId as POTransId,A.Qty as Qty")))
            ->join(array("b"=>"MMS_PVTrans"),"a.PVTransId=b.PVTransId",array(),$selTrans::JOIN_INNER)
            ->join(array("c"=>"MMS_POTrans"),"a.POTransId=c.POTransId",array(),$selTrans::JOIN_INNER)
            ->where(array("b.PVRegisterId = $PVRegisterId and  A.Status='U'"));
        $statementTrans = $sql->getSqlStringForSqlObject($selTrans);
        $prevTrans = $dbAdapter->query($statementTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($prevTrans as $arrpreTrans)
        {
            $updTrans=$sql->update();
            $updTrans->table('mms_POTrans');
            $updTrans->set(array(
                'BillQty'=> new Expression('BillQty-'.$arrpreTrans['Qty'].''),
                'BalQty'=> new Expression('BalQty+'.$arrpreTrans['Qty'].'')
            ));
            $updTrans->where(array('POTransId'=>$arrpreTrans['POTransId']));
            $updTranStatement = $sql->getSqlStringForSqlObject($updTrans);
            $dbAdapter->query($updTranStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $sel = $sql->select();
        $sel->from(array("a" => "MMS_PVTrans"))
            ->columns(array("ResourceId","ItemId","BillQty"))
            ->join(array("b" => "MMS_PVRegister"), "a.PVRegisterId=b.PVRegisterId", array("CostCentreId"), $sel::JOIN_INNER)
            ->where(array("a.PVRegisterId" => $PVRegisterId));
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
                    "BillQty" => new Expression('BillQty-' . $preStock['BillQty'] . ''),
                    "ClosingStock" => new Expression('ClosingStock-' . $preStock['BillQty'] . '')
                ));
                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            //stocktrans edit mode -update
            $sel = $sql->select();
            $sel->from(array("a" => "MMS_PVGroupTrans"))
                ->columns(array("CostCentreId", "ResourceId", "ItemId"))
                ->join(array("b" => "MMS_PVWHTrans"), "a.PVGroupId=b.PVGroupId", array("WareHouseId", "BillQty"), $sel::JOIN_INNER)
                ->where(array("a.PVRegisterId" => $PVRegisterId));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($pre as $preStockTrans) {

                if (count($stockselId['StockId']) > 0) {

                    $stockUpdate = $sql->update();
                    $stockUpdate->table('mms_stockTrans');
                    $stockUpdate->set(array(
                        "BillQty" => new Expression('BillQty-' . $preStockTrans['BillQty'] . ''),
                        "ClosingStock" => new Expression('ClosingStock-' . $preStockTrans['BillQty'] . '')
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
        $whQuery3->from('MMS_PVGroupTrans')
            ->columns(array("PVGroupId"))
            ->where(array("PVRegisterId" => $PVRegisterId));

        $del = $sql->delete();
        $del->from('MMS_PVWHTrans')
            ->where->expression('PVGroupId IN ?', array($whQuery3));
        $statement = $sql->getSqlStringForSqlObject($del);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


        $selProj=$sql->select();
        $selProj->from(array("a"=>"MMS_IPDProjTrans"))
            ->columns(array(new Expression("C.POProjTransId as POProjTransId,A.Qty as Qty")))
            ->join(array("b"=>"MMS_PVTrans"),"a.PVTransId=b.PVTransId",array(),$selProj::JOIN_INNER)
            ->join(array("c"=>"MMS_POProjTrans"),"a.POProjTransId=c.POProjTransId",array(),$selProj::JOIN_INNER)
            ->where(array("b.PVRegisterId = $PVRegisterId and  A.Status='U'"));
        $statementProj = $sql->getSqlStringForSqlObject($selProj);
        $prevProj = $dbAdapter->query($statementProj, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($prevProj as $arrpreProj)
        {
            $updProj=$sql->update();
            $updProj->table('mms_poprojTrans');
            $updProj->set(array(
                'BillQty'=> new Expression('BillQty-'.$arrpreProj['Qty'].''),
                'BalQty'=> new Expression('BalQty+'.$arrpreProj['Qty'].'')
            ));
            $updProj->where(array('POProjTransId'=>$arrpreProj['POProjTransId']));
            $updProjStatement = $sql->getSqlStringForSqlObject($updProj);
            $dbAdapter->query($updProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $seladv = $sql->select();
        $seladv->from(array("a" => "mms_advadjustment"))
            ->columns(array(
                "PORegisterId" => new Expression("a.PORegisterId"),
                "TermsId" => new Expression("a.TermsId"),
                "Amount" => new Expression("a.Amount")
            ))
            ->where(array("a.BillRegisterId = $PVRegisterId"));
        $statementAdv = $sql->getSqlStringForSqlObject($seladv);
        $prevAdv = $dbAdapter->query($statementAdv, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach ($prevAdv as $arrprevAdv) {
            $updProj = $sql->update();
            $updProj->table('mms_popaymentterms');
            $updProj->set(array(
                'AdjustAmount' => new Expression('AdjustAmount-' . $arrprevAdv['Amount'] . ''),
            ));
            $updProj->where(array('PORegisterId' => $arrprevAdv['PORegisterId'],
                'TermsId' => $arrprevAdv['TermsId']));
            $updProjStatement = $sql->getSqlStringForSqlObject($updProj);
            $dbAdapter->query($updProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        //POQualTrans
        $delPOQualTrans = $sql -> delete();
        $delPOQualTrans -> from ('MMS_PVQualTrans')
            ->where (array("PVRegisterId" => $PVRegisterId));
        $POQualStatement = $sql->getSqlStringForSqlObject($delPOQualTrans);
        $dbAdapter->query($POQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        //IPDAnalTrans
        $delIPDAnalSQ3=$sql->select();
        $delIPDAnalSQ3->from("mms_pvGroupTrans")
            ->columns(array("PVGroupId"))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delIPDAnalSQ2=$sql->select();
        $delIPDAnalSQ2->from("mms_PVTrans")
            ->columns(array("PVTransId"))
            ->where->expression('PVGroupId IN ?',array($delIPDAnalSQ3));
        $delIPDAnalSQ1=$sql->select();
        $delIPDAnalSQ1->from("MMS_PVAnalTrans")
            ->columns(array("PVAnalTransId"))
            ->where->expression('PVTransId IN ?',array($delIPDAnalSQ2));
        $delIPDAnal=$sql->delete();
        $delIPDAnal->from('MMS_IPDAnalTrans')
            ->where->expression('PVAnalTransId IN ?',array($delIPDAnalSQ1));
        $IPDAnalStatement = $sql->getSqlStringForSqlObject($delIPDAnal);
        $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //IPDProjTrans
        $delIPDProjSQ2=$sql->select();
        $delIPDProjSQ2->from("mms_pvGroupTrans")
            ->columns(array("PVGroupId"))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delIPDProjSQ1=$sql->select();
        $delIPDProjSQ1->from("mms_PVTrans")
            ->columns(array("PVTransId"))
            ->where->expression('PVGroupId IN ?',array($delIPDProjSQ2));
        $delIPDProj=$sql->delete();
        $delIPDProj->from('MMS_IPDProjTrans')
            ->where->expression('PVTransId IN ?',array($delIPDProjSQ1));
        $IPDTransStatement = $sql->getSqlStringForSqlObject($delIPDProj);
        $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //IPDTrans
        $delIPDTransSQ1=$sql->select();
        $delIPDTransSQ1->from("MMS_PVTrans")
            ->columns(array("PVTransId"))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delIPDTrans=$sql->delete();
        $delIPDTrans->from('MMS_IPDTrans')
            ->where->expression('PVTransId IN ?',array($delIPDTransSQ1));
        $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTrans);
        $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //POAnalTrans
        $delPOAnalSQ2=$sql->select();
        $delPOAnalSQ2->from("mms_pvGroupTrans")
            ->columns(array("PVGroupId"))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delPOAnalSQ1=$sql->select();
        $delPOAnalSQ1->from("MMS_PVTrans")
            ->columns(array("PVTransId"))
            ->where->expression('PVGroupId IN ?',array($delPOAnalSQ2));
        $delPOAnal=$sql->delete();
        $delPOAnal->from('MMS_PVAnalTrans')
            ->where->expression('PVTransId IN ?',array($delPOAnalSQ1));
        $delpoanalStatement = $sql->getSqlStringForSqlObject($delPOAnal);
        $dbAdapter->query($delpoanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //POProjTrans
        $delPOProjSQ1=$sql->select();
        $delPOProjSQ1 ->from("mms_pvGroupTrans")
            ->columns(array("PVGroupId"))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delPOProj=$sql->delete();
        $delPOProj->from('MMS_PVTrans');
        $delPOProj->where->expression('PVGroupId IN ?',array($delPOProjSQ1));
        $delpoprojStatement = $sql->getSqlStringForSqlObject($delPOProj);
        $dbAdapter->query($delpoprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //POTrans
        $delPOTrans=$sql->delete();
        $delPOTrans->from('mms_pvGroupTrans')
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delpotransStatement = $sql->getSqlStringForSqlObject($delPOTrans);
        $dbAdapter->query($delpotransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        //UPDATE PVREGISTER
        $registerUpdate=$sql->update()
            ->table("MMS_PVRegister")
            ->set(array(
                "DeleteFlag" => 1))
            ->where(array("PVRegisterId"=>$PVRegisterId));
        $delporegStatement = $sql->getSqlStringForSqlObject($registerUpdate);
        $dbAdapter->query($delporegStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $delPVPayTrans = $sql -> delete();
        $delPVPayTrans -> from ('MMS_PVPaymentTerms')
            -> where (array("PVRegisterId" => $PVRegisterId));
        $PVPayStatement = $sql->getSqlStringForSqlObject($delPVPayTrans);
        $dbAdapter->query($PVPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $deladvadjustment = $sql -> delete();
        $deladvadjustment -> from ('mms_advadjustment')
            -> where (array("BillRegisterId" => $PVRegisterId));
        $advadjustmentStatement = $sql->getSqlStringForSqlObject($deladvadjustment);
        $dbAdapter->query($advadjustmentStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        $this->redirect()->toRoute('mms/default', array('controller' => 'purchasebill','action' => 'purchasebill-register'));
        return $this->_view;
    }
	public function purchasebillReportAction(){
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
			$this->redirect()->toRoute("purchasebill/purchasebill-register", array("controller" => "purchasebill","action" => "purchasebill-register"));
		}

		$dir = 'public/purchasebill/header/'. $subscriberId;
		$filePath = $dir.'/v1_template.phtml';

		$dirfooter = 'public/purchasebill/footer/'. $subscriberId;
		$filePath1 = $dirfooter.'/v1_template.phtml';

		$RegId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
		if($RegId == 0)

			$this->redirect()->toRoute("purchasebill/purchasebill-register", array("controller" => "purchasebill","action" => "conversionregister"));

		if (!file_exists($filePath)) {
			$filePath = 'public/purchasebill/header/template.phtml';
		}
		if (!file_exists($filePath1)) {
			$filePath1 = 'public/purchasebill/footer/footertemplate.phtml';
		}

		$template = file_get_contents($filePath);
		$this->_view->template = $template;

		$footertemplate = file_get_contents($filePath1);
		$this->_view->footertemplate = $footertemplate;

		//Template
		$regSelect = $sql->select();
		$regSelect->from(array("a"=>"mms_pvregister"))
			->columns(array(new Expression("a.PVRegisterId,Convert(Varchar(10),a.PVDate,103) As PVDate,a.PVNo,a.Amount As BillAmount,Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
			->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
			->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $regSelect::JOIN_INNER)
			->join(array("d"=>"WF_CostCentre"), "c.CostCentreId=d.CostCentreId", array("Address"), $regSelect::JOIN_LEFT)
			->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $regSelect::JOIN_LEFT)
			->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $regSelect::JOIN_LEFT)
			->join(array("g"=>"WF_CountryMaster"), "d.CountryId=g.CountryId", array("CountryName"), $regSelect::JOIN_LEFT)
			->where(array("a.DeleteFlag = 0 and a.ThruPO='Y'"))
		->order("a.CreatedDate Desc")
		->where(array("a.PVRegisterId=$RegId"));
		$regStatement = $sql->getSqlStringForSqlObject($regSelect);
		$this->_view->reqregister = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		//GRID
		$selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_pvRegister"));
        $selectVendor->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId asc)) as SNo,a.BillAmount,a.PVRegisterId,e.PVGroupId,
                    f.PVTransId,f.POTransId,f.PORegisterId,e.ResourceId,e.ItemId,c.UnitName,
                    e.ItemId,Case When e.ItemId>0 Then d.ItemCode Else b.Code
                    End As Code,Case When e.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,
                    CAST(e.BillQty As Decimal(18,5)) As BillQty,Convert(Varchar(10),a.PVDate,103) As PVDate,
                    e.Rate,e.Amount,(Select Count(PVGroupId) From MMS_PVQualTrans Where PVGroupId = f.PVGroupId) as QCount")))
            ->join(array("e"=>'mms_pvGroupTrans'), "e.PVRegisterId=a.PVRegisterId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("b"=>"Proj_Resource"), "e.ResourceId=b.ResourceId", array(), $selectVendor::JOIN_INNER)
            ->join(array("c"=>"Proj_UOM"), "e.UnitId=c.UnitId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("d"=>"MMS_Brand"),"e.ResourceId=d.ResourceId And e.ItemId=d.BrandId",array(),$selectVendor::JOIN_LEFT)
            ->join(array("f"=>"MMS_pvtrans"),"e.PVRegisterId=f.PVRegisterId And e.PVGroupId=f.PVGroupId",array(),$selectVendor::JOIN_INNER);
        $selectVendor->where(array("a.PVRegisterId"=>$RegId));
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $this->_view->register  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array("c" => "MMS_PVQualTrans"))
			->columns(array(
			'PVGroupId'=>new Expression('c.PVGroupId'),
			'PVRegisterId'=>new Expression('c.PVRegisterId'),
			'Expression'=>new Expression('c.Expression'),
			'ExpPer'=>new Expression('c.ExpPer'),
			'Sign'=>new Expression('c.Sign'),
			'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3))')))
				->join(array("b" => "Proj_QualifierMaster"), "c.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
			->where(array('c.PVRegisterId'=>$RegId));
		$regStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->register1 = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		 //TERMS AND CONDITIONS
		$resourceSelect = $sql->select();
		$resourceSelect->from(array("a"=>"MMS_PVPaymentTerms"))
			->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId asc)) as SNo,a.TermsId,a.Value,a.AccountId")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("Title"), $resourceSelect::JOIN_INNER)
			->join(array("c"=>"FA_AccountMaster"), "a.AccountId=c.AccountId", array("AccountName"), $resourceSelect::JOIN_INNER)
			->where(array('a.PVRegisterId'=>$RegId));
		$resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
		$this->_view->register2 = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//ADVANCE ADJUSTMENT
		$select = $sql->select();
		$select->from(array("A" => "MMS_PORegister"))
			->columns(array(
				'SNo' => new Expression('ROW_NUMBER() OVER(PARTITION by D.PVRegisterId Order by D.PVRegisterId asc)'),
				'PORegisterId' => new Expression('A.PORegisterId'),
				'PONo' => new Expression('A.PONo'),
				'TermsId' => new Expression('B.TermsId'),
				'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
				'Title' => new Expression('TM.Title'),
				'Amount' => new Expression('CAST(B.Value As Decimal(18,3))'),
				'PaidAmount' => new Expression('CAST(B.Value As Decimal(18,3))'),
				'Balance' => new Expression('CAST(B.Value As Decimal(18,3)) - ISNULL((Select SUM(Amount) From MMS_AdvAdjustment
					  Where PORegisterId=A.PORegisterId And TermsId=B.TermsId),0)'),
				'CurrentAmount' => new Expression("C.Amount"),
				'HiddenAmount' => new Expression('C.Amount')
			))
			->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
			->join(array("TM"=>'WF_TermsMaster'),'B.TermsId=TM.TermsId',array(),$select::JOIN_INNER)
			->join(array("C"=>'MMS_AdvAdjustment'),'A.PORegisterId=C.PORegisterId and TM.TermsId=c.TermsId',array(),$select::JOIN_INNER)
			->join(array("D"=>'MMS_PVRegister'),'C.BillRegisterId=D.PVRegisterId',array(),$select::JOIN_INNER)
			->where("TM.Title IN ('Advance','Against Delivery','Against Test Certificate') AND TM.TermType='S' AND  D.PVRegisterId=$RegId ");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->register3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
}

