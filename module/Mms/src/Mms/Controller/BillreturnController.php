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

class BillreturnController extends AbstractActionController
{
    public function __construct()
    {
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }
    public function returnWizardAction(){
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
                $CostCentreId= $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $SupplierId= $this->bsf->isNullCheck($postParams['SupplierId'],'number');


//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_Resource'))
//                    ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,0 As Include ") ))
//                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
//                    ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
//                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
//                    ->where("c.ProjectId =".$CostCentreId );
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectResource'))
                    ->columns(array(new Expression("b.ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0) > 0 Then d.ItemCode Else b.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else b.ResourceName End As ResourceName,0 As Include,'Project' As RFrom") ))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array("ResourceGroupName"), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_Brand'),'b.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                    ->join(array('e' => 'WF_OperationalCostCentre'),'a.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                    ->where("b.TypeId IN(2,3) and e.CostCentreId=". $CostCentreId ." ");

                $selRa = $sql -> select();
                $selRa->from(array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId As ResourceId,isnull(c.BrandId,0) ItemId,Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then c.BrandName Else a.ResourceName End As ResourceName,0 As Include,'Library' As RFrom ")))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId',array("ResourceGroupName"),$selRa::JOIN_LEFT)
                    ->join(array('c' => 'MMS_Brand'),'a.ResourceId=c.ResourceId',array(),$select::JOIN_LEFT)
                    ->where("a.TypeId IN(2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource A
                         Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=".$CostCentreId.") ");
                $select->combine($selRa,'Union All');
                $statement = $sql->getSqlStringForSqlObject($select);
                $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('resources' => $requestResources)));
                return $response;

            }
        } else  {
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
    public function returnEntryAction() {

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $PRRegisterId = $this->params()->fromRoute('prId');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentre'), 'number');
                $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('resourceId'), 'number');
                $itemid = $this->bsf->isNullCheck($this->params()->fromPost('itemid'), 'number');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getwbsdetails':
                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,
                            0 As Qty") ))
                            ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=a.ProjectId',array(),$select::JOIN_INNER)
                            ->where(array("a.LastLevel"=>"1","g.costcentreId"=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql ->select();
                        $select->from(array("a" => "MMS_WareHouse"))
                            ->columns(array(
                                "StockId"=>new Expression("e.StockId"),
                                "ResourceId"=>new Expression("e.ResourceId"),
                                "ItemId"=>new Expression("e.ItemId"),
                                "WareHouseId"=>new Expression("c.TransId"),
                                "WareHouseName"=>new Expression("a.WareHouseName"),
                                "Description"=>new Expression("c.Description"),
                                "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                                "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                                "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                            ))
                            ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                            ->where(array('b.CostCentreId=' . $CostCentre . ' And c.LastLevel=1 And d.ClosingStock > 0 and e.ResourceId IN ('.$resourceId.') and e.ItemId IN ('.$itemid.')'));
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_iows, 'warehouse' => $arr_sel_warehouse)));
                        return $response;
                        break;

                    case 'getwhdetails':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $CostCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentre'), 'number');

                        $select = $sql ->select();
                        $select->from(array("a" => "MMS_WareHouse"))
                            ->columns(array(
                                "StockId"=>new Expression("e.StockId"),
                                "ResourceId"=>new Expression("e.ResourceId"),
                                "ItemId"=>new Expression("e.ItemId"),
                                "WareHouseId"=>new Expression("c.WareHouseId"),
                                "WareHouseName"=>new Expression("a.WareHouseName"),
                                "Description"=>new Expression("c.Description"),
                                "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                                "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                                "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                            ))
                            ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                            ->where(array("b.CostCentreId= $CostCentre And c.LastLevel=1 And E.ClosingStock > 0 and
                             e.ResourceId=$ResourceId and e.ItemId=$ItemId"));
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_wh= $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_wh));
                        return $response;
                        break;

                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

                        $selSub2 = $sql -> select();
                        $selSub2->from(array("a" => "MMS_PRQualTrans"))
                            ->columns(array("QualifierId"));
                        $selSub2->where(array('a.PRRegisterId' => $POId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));

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
                        $select->from(array("c" => "MMS_PRQualTrans"))
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

                        $select->where(array('a.QualType' => 'M', 'c.PRRegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
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

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid Issue!');
                        return $response;
                        break;
                }
                if ($postParams['mode'] == 'Questions') {
                    $Select = $sql->select();
                    $Select->from('mms_stock')
                        ->columns(array("ClosingStock"))
                        ->where(array("resourceId" => $postParams['resourceId'],"itemId" => $postParams['itemId'],"costCentreId" => $postParams['CostCentre']));
                    $Statement = $sql->getSqlStringForSqlObject($Select);
                    $result = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
//                echo"<pre>";
//                print_r($postData);
//                echo"</pre>";die;

                $CostCentre=  $this->bsf->isNullCheck($postData['CostCentre'],'number');
                $Supplier=  $this->bsf->isNullCheck($postData['Supplier'],'number');
                $requestTransIds = implode(',',$postData['requestTransIds']);
                $itemTransIds = implode(',',$postData['itemTransIds']);
                $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');
                $this->_view->gridtype=$gridtype;
                $this->_view->costcenterid=$CostCentre;
                $this->_view->supplierid=$Supplier;
                if (!is_null($postData['frm_index'])) {
                    $voNo = CommonHelper::getVoucherNo(309, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo=$voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CompanyId'))
                        ->where("CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$Comp['CompanyId'];

                    //CostCentreId
                    $CCPR = CommonHelper::getVoucherNo(309, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                    $this->_view->CCPR = $CCPR;
                    $CCPRNo=$CCPR['voucherNo'];
                    $this->_view->CCPRNo = $CCPRNo;

                    //CompanyId
                    $CPR = CommonHelper::getVoucherNo(309, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CPR = $CPR;
                    $CPRNo=$CPR['voucherNo'];
                    $this->_view->CPRNo = $CPRNo;

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where("Deactivate=0 AND CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->costcenter=$costcentre['CostCentreName'];
                    // vendor details
                    $select = $sql->select();
                    $select->from(array("a"=>'Vendor_Master'))
                        ->columns(array(new expression('a.VendorId as SupplierId'), new expression('a.VendorName as SupplierName'), 'LogoPath'))
                        ->where("VendorId=$Supplier");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $Suppliers = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->supplier=$Suppliers['SupplierName'];

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_CCWareHouse'))
                        ->columns(array('WareHouseId'))
                        ->where(array("a.CostCentreId=$CostCentre"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->WarehouseCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                    $select = $sql->select();
                    $select->from(array('a' => 'mms_purchasetype'))
                        ->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
                        ->join(array('b' => 'FA_AccountMaster'), 'a.AccountId=b.AccountId', array(), $select::JOIN_INNER)
                        ->where(array('a.sel' => "1"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                        Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                           Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                           Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                           Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                           Case When isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End As Rate ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                        ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT )
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where("g.CostCentreId=".$CostCentre." and a.TypeId IN (2,3) and
                        (a.ResourceId NOT IN ($requestTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and
                                 a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
	                                 Inner Join WF_OperationalCostCentre b On a.projectid=b.projectid
                                     Where b.costcentreid=". $CostCentre .") and
                                  (a.ResourceId NOT IN ($requestTransIds) Or isnull(c.BrandId,0) NOT IN ($itemTransIds)) ");
                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,
                        Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                        Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                        b.ResourceGroupName,b.ResourceGroupId,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                        Case when isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End As Rate,
                         0 As Qty")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where("g.CostCentreId =" . $CostCentre . " and (a.ResourceId IN ($requestTransIds) and
                        isnull(d.BrandId,0) IN ($itemTransIds))");

                    $select1 = $sql->select();
                    $select1->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then C.BrandName  Else  a.ResourceName End As ResourceName,
                                b.ResourceGroupName,b.ResourceGroupId,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate,
                                0 As Qty")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select1:: JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId', array(), $select1:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_Uom'), 'a.UnitId=d.UnitId', array(), $select1::JOIN_LEFT)
                        ->join(array('e' => 'Proj_Uom'), 'c.UnitId=e.UnitId',array(),$select::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and
                        (a.ResourceId IN ($requestTransIds) and isnull(c.BrandId,0) IN ($itemTransIds)) and
                        a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource A
                           Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where CostCentreId=$CostCentre)");
                    $select1 -> combine($select,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $wbsSelect = $sql->select();
                    $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                        ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,
                        0 As Qty")))
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=a.ProjectId',array(),$select::JOIN_INNER)
                        ->where(array("a.LastLevel" => "1", "g.CostCentreId" => $CostCentre));
                    $statement = $sql->getSqlStringForSqlObject($wbsSelect);
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
                    $this->_view->qualList = $qualList;

                    $select = $sql->select();
                    $select->from(array("a" => "FA_AccountMaster"))
                        ->columns(array('AccountId','AccountName','TypeId'));
                    $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(
                            "StockId"=>new Expression("e.StockId"),
                            "ResourceId"=>new Expression("e.ResourceId"),
                            "ItemId"=>new Expression("e.ItemId"),
                            "WareHouseId"=>new Expression("c.TransId"),
                            "WareHouseName"=>new Expression("a.WareHouseName"),
                            "Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                        ))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                        ->where(array('b.CostCentreId=' . $CostCentre . ' And c.LastLevel=1 And d.ClosingStock > 0 and e.ResourceId IN ('.$requestTransIds.') and e.ItemId IN ('.$itemTransIds.')'));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(
                            "StockId"=>new Expression("e.StockId"),
                            "ResourceId"=>new Expression("e.ResourceId"),
                            "ItemId"=>new Expression("e.ItemId"),
                            "WareHouseId"=>new Expression("c.TransId"),
                            "WareHouseName"=>new Expression("a.WareHouseName"),
                            "Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                        ))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                        ->where(array('b.CostCentreId=' . $CostCentre . ' And c.LastLevel=1 And d.ClosingStock > 0 and e.ResourceId IN ('.$requestTransIds.') and e.ItemId IN ('.$itemTransIds.')'));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_wbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //////Start - ClosingStock
                    $stdate = date('Y/m/d');

                    $selM1 = $sql -> select();
                    $selM1->from(array("a" => "MMS_PVTrans"))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty)
                               Else SUM(a.BillQty) End,Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And h.SEZProject=0
                                and (j.StateId=l.StateId)) Then Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then
                                 isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) Else
                                  SUM(A.BillQty * Case When (b.FFactor>0 and b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) else a.GrossRate End) End Else
                                   case when c.ThruDC='Y' then SUM(a.ActualQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End)
                                   else sum(a.BillQty*Case when (b.FFactor>0 and b.TFactor>0) Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,
                                   c.CostCentreId ")))
                        ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM1::JOIN_INNER)
                        ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM1::JOIN_INNER )
                        ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM1::JOIN_INNER)
                        ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM1::JOIN_LEFT)
                        ->join(array("f" => "MMS_DCTrans"),"a.DCTransId=f.DCTransId and a.DCRegisterId=f.DCRegisterId",array(),$selM1::JOIN_INNER)
                        ->join(array("g" => "MMS_DCRegister"),"f.DCRegisterId=g.DCRegisterId",array(),$selM1::JOIN_INNER)
                        ->join(array("h" => "WF_OperationalCostCentre"),"c.CostCentreId=h.CostCentreId",array(),$selM1::JOIN_INNER)
                        ->join(array("i" => "Vendor_Master"),"c.VendorId=i.VendorId",array(),$selM1::JOIN_INNER)
                        ->join(array("j" => "WF_CityMaster"),"i.CityId=j.CityId",array(),$selM1::JOIN_LEFT)
                        ->join(array("k" => "WF_CostCentre"),"h.FACostCentreId=k.CostCentreId",array(),$selM1::JOIN_INNER)
                        ->join(array("m" => "WF_CityMaster"),"k.CityId=m.CityId",array(),$selM1::JOIN_INNER)
                        ->join(array("l" => "WF_StateMaster"),"m.StateId=l.StateId",array(),$selM1::JOIN_INNER)
                        ->where("a.BillQty>0 and g.DCDate <='$stdate' and c.CostCentreId=$CostCentre
                              Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,c.CostCentreId,h.SEZProject,
                               b.FFactor,b.TFactor,j.StateId,l.StateId");

                    $selM2 = $sql -> select();
                    $selM2->from(array("a" => "MMS_PVTrans"))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty)
                               else SUM(a.BillQty) End,Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0
                               and (h.StateId=j.StateId)) Then Case When c.ThruDC='Y' Then SUM(a.ActualQty * Case When (b.FFactor>0 and b.TFactor>0)
                               then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) Else SUM(a.BillQty*
                               Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else
                               a.GrossRate End) End Else Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case when (b.FFactor>0 and b.TFactor>0) then
                               isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 and b.TFactor>0)
                               then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,a.CostCentreId As CostCentreId   ")))
                        ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM2::JOIN_INNER)
                        ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM2::JOIN_INNER)
                        ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM2::JOIN_INNER)
                        ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM2::JOIN_LEFT)
                        ->join(array("f" => "WF_OperationalCostCentre"),"a.CostCentreId=f.CostCentreId",array(),$selM2::JOIN_INNER)
                        ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM2::JOIN_INNER)
                        ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM2::JOIN_LEFT)
                        ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM2::JOIN_INNER)
                        ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM2::JOIN_INNER)
                        ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM2::JOIN_INNER)
                        ->where("a.BillQty>0 and c.PVDate <= '$stdate' and a.CostCentreId=$CostCentre and c.CostCentreId=0
                               Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,a.CostCentreId,
                                f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId");
                    $selM2->combine($selM1,'Union ALL');



                    $selM3 = $sql -> select();
                    $selM3->from(array("a" => "MMS_PVTrans"))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' then SUM(a.ActualQty) else SUM(a.BillQty) End,
                              Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 and (h.StateId=j.StateId)) then
                              Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/
                              nullif(b.FFactor,0) Else a.GrossRate End) Else SUM(a.BillQty*Case when (b.FFactor>0 and b.TFactor>0) then
                              isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) End  Else
                              Case when c.ThruDC='Y' then sum(a.actualqty*case when (b.FFactor>0 and b.TFactor>0)  then
                              isnull((a.qrate * b.TFactor),0)/nullif(b.ffactor,0) else a.qrate end) else sum(a.billqty* case when (b.ffactor>0 and b.tfactor>0)
                              then isnull((a.qrate*b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end  end,c.CostCentreId    ")))
                        ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM3::JOIN_INNER)
                        ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM3::JOIN_INNER)
                        ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM3::JOIN_INNER)
                        ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM3::JOIN_LEFT)
                        ->join(array("f" => "WF_OperationalCostCentre"),"c.CostCentreId=f.CostCentreId",array(),$selM3::JOIN_INNER)
                        ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM3::JOIN_INNER)
                        ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM3::JOIN_LEFT)
                        ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM3::JOIN_INNER)
                        ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM3::JOIN_INNER)
                        ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM3::JOIN_INNER)
                        ->where("a.BillQty>0 and c.PVDate <= '$stdate' and c.CostCentreId=$CostCentre and
                                c.ThruPO='Y' Group BY e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,
                                 c.ThruDC,c.CostCentreId,f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId");
                    $selM3->combine($selM2,'Union All');



                    $selM4 = $sql -> select();
                    $selM4->from(array("a" => "MMS_DCTrans"))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.BalQty),Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5)
                              and f.SEZProject=0 and (h.StateId=j.StateId)) then sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0) then
                              isnull((a.grossrate*b.tfactor),0)/nullif(b.ffactor,0) else a.grossrate end) else
                              sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0) then isnull((a.qrate*b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end,
                              c.CostCentreId As CostCentreId")))
                        ->join(array("b" => "MMS_DCGroupTrans"),"a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId",array(),$selM4::JOIN_INNER)
                        ->join(array("c" => "MMS_DCRegister"),"a.DCRegisterId=c.DCRegisterId",array(),$selM4::JOIN_INNER)
                        ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM4::JOIN_INNER)
                        ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM4::JOIN_LEFT)
                        ->join(array("f" => "WF_OperationalCostCentre"),"c.CostCentreId=f.CostCentreId",array(),$selM4::JOIN_INNER)
                        ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM4::JOIN_INNER)
                        ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM4::JOIN_LEFT)
                        ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM4::JOIN_INNER)
                        ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM4::JOIN_INNER)
                        ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM4::JOIN_INNER)
                        ->where("a.balqty>0 and c.DCDate <= '$stdate' and c.CostCentreId=$CostCentre and
                              c.DcOrCSM=1 Group By e.ResourceGroupID,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.CostCentreId,
                              f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId ");
                    $selM4->combine($selM3,'Union All');



                    $selM5 = $sql -> select();
                    $selM5 -> from(array("a" => "MMS_DCTrans"))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Sum(a.BalQty),Amount=Case when ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5)
                            and f.SEZProject=0 and (h.StateId=j.StateId)) then SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0)
                            then isnull((a.grossrate*b.TFactor),0)/nullif(b.FFactor,0) else a.grossrate end) else sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0)
                            then isnull((a.qrate *b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end,a.CostCentreId As CostCentreId ")))
                        ->join(array("b" => "MMS_DCGroupTrans"),"a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId",array(),$selM5::JOIN_INNER)
                        ->join(array("c" => "MMS_DCRegister"),"a.DCRegisterId=c.DCRegisterId",array(),$selM5::JOIN_INNER)
                        ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM5::JOIN_INNER)
                        ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM5::JOIN_LEFT)
                        ->join(array("f" => "WF_OperationalCostCentre"),"a.CostCentreId=f.CostCentreId",array(),$selM5::JOIN_INNER)
                        ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM5::JOIN_INNER)
                        ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM5::JOIN_LEFT)
                        ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM5::JOIN_INNER)
                        ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM5::JOIN_INNER)
                        ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM5::JOIN_INNER)
                        ->where("a.BalQty>0 and c.DCDate <= '$stdate' and a.CostCentreId=$CostCentre  and c.CostCentreId=0
                               and c.DcOrCSM=1 Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,a.CostCentreId,
                                f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId ");
                    $selM5->combine($selM4,'Union All');



                    $selM6 = $sql -> select();
                    $selM6 -> from(array("a" => "MMS_TransferTrans" ))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=-SUM(a.TransferQty),Amount=-SUM(a.Amount),
                              b.FromCostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
                        ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_INNER)
                        ->where("a.TransferQty>0 and b.TVDate <= '$stdate' and b.FromCostCentreId=$CostCentre
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.FromCostCentreId");
                    $selM6->combine($selM5,'Union All');



                    $selM7 = $sql -> select();
                    $selM7 -> from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.RecdQty),Amount=SUM(a.RecdQty*a.QRate),
                              b.ToCostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
                        ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_LEFT)
                        ->where("a.RecdQty>0 and b.TVDate <= '$stdate' and b.ToCostCentreId=$CostCentre
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.ToCostCentreId");
                    $selM7->combine($selM6,'Union All');


                    $selM8 = $sql -> select();
                    $selM8 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.ReturnQty),Amount=SUM(-Amount),b.CostCentreId As CostCentreId')))
                        ->join(array("b" => "MMS_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array(),$selM8::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM8::JOIN_INNER)
                        ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM8::JOIN_INNER)
                        ->where("a.ReturnQty>0 and b.PRDate <= '$stdate' and b.CostCentreId=$CostCentre
                             Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId");
                    $selM8->combine($selM7,'Union All');


                    $selM9 = $sql -> select();
                    $selM9 -> from(array("a" => "MMS_IssueTrans"))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.IssueQty),Amount=-SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                        ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                        ->where("a.IssueQty>0 and b.IssueDate <= '$stdate' and b.CostCentreId=$CostCentre and b.IssueOrReturn=0 and a.IssueOrReturn='I'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                    $selM9->combine($selM8,'Union All');

                    $selM9a = $sql -> select();
                    $selM9a -> from(array("a" => "MMS_IssueTrans"))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.IssueQty),Amount=SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                        ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                        ->where("a.IssueQty>0 and b.IssueDate <= '$stdate' and b.CostCentreId=$CostCentre and b.IssueOrReturn=1 and a.IssueOrReturn='R'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                    $selM9a->combine($selM9,'Union All');

                    $selM10 = $sql ->select();
                    $selM10 -> from(array("a" => "MMS_PVRateAdjustment" ))
                        ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM10::JOIN_INNER)
                        ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM10::JOIN_INNER)
                        ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM10::JOIN_INNER)
                        ->where("d.PVDate <= '$stdate'  and d.CostCentreId=$CostCentre and a.Type='D'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                    $selM10->combine($selM9a,'Union All');

                    $selM11 = $sql ->select();
                    $selM11 -> from(array("a" => "MMS_PVRateAdjustment" ))
                        ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=-SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                        ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM11::JOIN_INNER)
                        ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM11::JOIN_INNER)
                        ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM11::JOIN_INNER)
                        ->where("d.PVDate <= '$stdate'  and d.CostCentreId=$CostCentre and a.Type='C'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                    $selM11->combine($selM10,'Union All');

                    $selM12 = $sql -> select();
                    $selM12 -> from(array("a" => "MMS_Stock"))
                        ->columns(array(new Expression('a.ResourceId,a.ItemId,a.OpeningStock As Qty,Amount=a.OpeningStock*a.ORate,a.CostCentreId')))
                        ->where(" a.CostCentreId=$CostCentre ");
                    $selM12->combine($selM11,'Union All');

                    $selF1 = $sql -> select();
                    $selF1 -> from (array("g1" => $selM12))
                        ->columns(array(new Expression('g1.ResourceId,g1.ItemId,Qty=Sum(g1.Qty),AvgRate=Case When CAST(isnull(isnull(SUM(g1.Amount),0)/nullif(SUM(g1.Qty),0),0) As Decimal(18,3)) < 0
                              Then 0 Else CAST(isnull(isnull(SUM(g1.Amount),0)/nullif(SUM(g1.Qty),0),0) As Decimal(18,3)) End ,Cost=Case When SUM(g1.Amount) < 0 Then 0
                                 When SUM(g1.Qty) <= 0 Then 0  Else SUM(g1.Amount) End,g1.CostCentreId As CostCentreId')));
                    $selF1->group(new Expression("ResourceId,ItemId,CostCentreId"));


                    $selF2 = $sql -> select();
                    $selF2 -> from (array("g" => $selF1 ))
                        ->columns(array(new Expression("RG.ResourceGroupId,G.ResourceId,G.ItemId,Case When g.ItemId>0 Then BR.ItemCode Else RV.Code End Code,Case When ISNULL(RG.ResourceGroupId,0)>0 Then RG.ResourceGroupName Else 'Others' End As ResourceGroup,
                               RV.ResourceName As Resource,Case When g.ItemId>0 then BR.BrandName Else '' End ItemName,Case When G.ItemId>0 Then U.UnitName Else U1.UnitName End As Unit,
                               g.Qty,g.AvgRate,g.Cost,g.CostCentreId  ")))
                        ->join(array("RV" => "Proj_Resource"),"g.ResourceId=RV.ResourceId",array(),$selF2::JOIN_INNER)
                        ->join(array("RG" => "Proj_ResourceGroup"),"RV.ResourceGroupId=RG.ResourceGroupId",array(),$selF2::JOIN_LEFT)
                        ->join(array("BR" => "MMS_Brand"),"g.ResourceId=BR.ResourceId And g.ItemId=BR.BrandId",array(),$selF2::JOIN_LEFT)
                        ->join(array("U" => "Proj_UOM"),"BR.UnitId=U.UnitId",array(),$selF2::JOIN_LEFT)
                        ->join(array("U1" => "Proj_UOM"),"RV.UnitId=U1.UnitId",array(),$selF2::JOIN_LEFT)
                        ->where('RV.TypeId IN (2) And G.ResourceId IN ('. $requestTransIds .') And G.ItemId IN ('. $itemTransIds .') And g.CostCentreId='.$CostCentre.' ');

                    $statement = $sql->getSqlStringForSqlObject($selF2);
                    $arr_resource_closingstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arr_resource_closingstock=$arr_resource_closingstock;

                    /////// End - ClosingSTock

                }
            }else{
                if (isset($PRRegisterId) && $PRRegisterId != '') {
                    $selReqReg=$sql->select();
                    $selReqReg->from(array('a' => 'mms_PRRegister'))
                        ->columns(array(
                            'PRRegisterId'=> new Expression("a.PRRegisterId"),
                            'PRDate'=> new Expression("a.PRDate"),
                            'BillDate'=> new Expression("a.BillDate"),
                            'PRNo'=> new Expression("a.PRNo"),
                            'CostCentreId'=> new Expression("a.CostCentreId"),
                            'CostCentreName'=> new Expression("b.CostCentreName"),
                            'SupplierId'=> new Expression("a.VendorId"),
                            'SupplierName'=> new Expression("c.VendorName"),
                            'CCPRNo'=> new Expression("a.CCPRNo"),
                            'CPRNo'=> new Expression("a.CPRNo"),
                            'BillNo'=> new Expression("a.BillNo"),
                            'Narration'=> new Expression("a.Narration"),
                            'Approve'=> new Expression("a.Approve"),
                            'BillAmount'=> new Expression("a.BillAmount"),
                            'GridType' => new Expression("a.GridType")
                        ))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $selReqReg:: JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $selReqReg:: JOIN_LEFT)
                        ->where("a.PRRegisterId=$PRRegisterId");
                    $statement = $sql->getSqlStringForSqlObject( $selReqReg );
                    $register= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->PRRegisterId=$register['PRRegisterId'];
                    $this->_view->PRDate= date('Y-m-d', strtotime($register['PRDate']));
                    $this->_view->BillDate= date('Y-m-d', strtotime($register['BillDate']));
                    $this->_view->vNo=$register['PRNo'];
                    $this->_view->costcenterid=$register['CostCentreId'];
                    $this->_view->costcenter=$register['CostCentreName'];
                    $this->_view->supplierid=$register['SupplierId'];
                    $this->_view->supplier=$register['SupplierName'];
                    $this->_view->CCPRNo=$register['CCPRNo'];
                    $this->_view->CPRNo=$register['CPRNo'];
                    $this->_view->BillNo=$register['BillNo'];
                    $this->_view->Narration=$register['Narration'];
                    $this->_view->BillAmount=$register['BillAmount'];
                    $this->_view->Approve=$register['Approve'];
                    $this->_view->gridtype=$register['GridType'];
                    $CostCentreId=$this->_view->costcenterid;
                    $SupplierId=$this->_view->supplierid;
                    $Approve=$this->_view->Approve;


                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_CCWareHouse'))
                        ->columns(array('WareHouseId'))
                        ->where(array("a.CostCentreId=$CostCentreId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->WarehouseCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                    $select = $sql->select();
                    $select->from(array('a' => 'mms_purchasetype'))
                        ->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
                        ->join(array('b' => 'FA_AccountMaster'), 'a.AccountId=b.AccountId', array(), $select::JOIN_INNER)
                        ->where(array('a.sel' => "1"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                            Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                            Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                            Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                            Case When isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End As Rate ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                        ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where("g.CostCentreId=$CostCentreId");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
	                            Inner Join WF_OperationalCostCentre b On a.projectId=b.projectId
	                            Where b.costcentreId =". $CostCentreId .") and (a.ResourceId NOT IN (Select ResourceId From MMS_PRTrans
                         Where PRRegisterId=".$PRRegisterId.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_PRTrans Where PRRegisterId=".$PRRegisterId."))  ");
                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_PRTrans'))
                        ->columns(array(
                            "ResourceId" => new Expression("a.ResourceId"),
                            "PRRegisterId" => new Expression("a.PRRegisterId"),
                            "ItemId" => new Expression("a.ItemId"),
                            "Code" => new Expression("Case When a.ItemId>0 Then d.ItemCode Else b.Code End"),
                            "ResourceName" => new Expression("Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End"),
                            "ResourceGroupName" => new Expression("c.ResourceGroupName"),
                            "ResourceGroupId" => new Expression("c.ResourceGroupId"),
                            "UnitName" => new Expression("f.UnitName"),
                            "UnitId" => new Expression("a.UnitId"),
                            "Rate" => new Expression("a.Rate"),
                            "QRate" => new Expression("a.QRate"),
                            "Qty" => new Expression("a.ReturnQty"),
                            "Amount" => new Expression("a.Amount"),
                            "QAmount" => new Expression("a.QAmount"),
                            "PurchaseTypeId" => new Expression("a.PurchaseTypeId"),
                            "PurchaseTypeName" => new Expression("h.PurchaseTypeName"),
                        ))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId ', array(), $select:: JOIN_INNER)
                        ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId ', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'MMS_PRRegister'), 'a.PRRegisterId=e.PRRegisterId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'), 'a.UnitId=f.UnitId',array(), $select::JOIN_LEFT)
                        ->join(array('h' => 'mms_purchasetype'), 'a.PurchaseTypeId=h.PurchaseTypeId',array(), $select::JOIN_LEFT)
                        ->where("e.CostCentreId=" . $CostCentreId . " and e.PRRegisterId=".$PRRegisterId."");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subWbs1=$sql->select();
                    $subWbs1->from(array('a'=>'Proj_WBSMaster'))
                        ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,0 As Qty")))
                        ->join(array('e' => 'WF_OperationalCostCentre'),'e.projectId=a.ProjectId',array(),$subWbs1::JOIN_INNER);
                    $subWbs1->where ('a.LastLevel=1 And e.costcentreId='.$CostCentreId.' ');
//                        And a.WBSId NOT IN (Select AnalysisId From mms_PRAnalTrans Where PRTransId IN (Select PRTransId From MMS_PRTrans Where PRRegisterId=?))', $PRRegisterId);
//                    $selectAnal = $sql->select();
//                    $selectAnal->from(array("a" => "MMS_PRAnalTrans"))
//                        ->columns(array(
//                            'ResourceId' => new Expression('e.ResourceId'),
//                            'ItemId' => new Expression('e.ItemId'),
//                            'WbsId' => new Expression('c.WBSId'),
//                            'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
//                            'Qty' => new Expression('CAST(A.ReturnQty As Decimal(18,5))')))
//                        ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
//                        ->join(array("e" => "MMS_PRTrans"), " a.PRTransId=e.PRTransId", array(), $selectAnal::JOIN_LEFT);
//                    $selectAnal->where(array("e.PRRegisterId = $PRRegisterId"));
//                    $selectAnal->combine($subWbs1, 'Union ALL');
                    $statement1 = $sql->getSqlStringForSqlObject($subWbs1);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $selectAnal = $sql->select();
                    $selectAnal->from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array(
                            'ResourceId' => new Expression('e.ResourceId'),
                            'ItemId' => new Expression('e.ItemId'),
                            'WBSId' => new Expression('c.WBSId'),
                            'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
                            'Qty' => new Expression('CAST(A.ReturnQty As Decimal(18,5))')))
                        ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
                        ->join(array("e" => "MMS_PRTrans"), " a.PRTransId=e.PRTransId", array(), $selectAnal::JOIN_LEFT);
                    $selectAnal->where(array("e.PRRegisterId = $PRRegisterId"));
                    $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
                    $this->_view->arr_res_selwbs = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer',
                            'Sign','SurCharge','EDCess','HEDCess','NetPer',
                            'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                            'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'M'));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;
                    $this->_view->qualList = $qualList;

                    $arrqual = array();
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PRQualTrans"))
                        ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer',
                            'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER);
                    $select->where(array('a.PRRegisterId'=>$PRRegisterId));
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

                    $selectsub = $sql ->select();
                    $selectsub->from(array("a" => "MMS_PRWareHouseTrans"))
                        ->columns(array(
                            "StockId"=>new Expression("f.StockId"),
                            "ResourceId"=>new Expression("b.ResourceId"),
                            "ItemId"=>new Expression("b.ItemId"),
                            "WareHouseId"=>new Expression("d.TransId"),
                            "WareHouseName"=>new Expression("e.WareHouseName"),
                            "Description"=>new Expression("d.Description"),
                            "ClosingStock"=>new Expression(" CAST(g.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(a.PRQty As Decimal(18,2))")
                        ))
                        ->join(array("b" => "MMS_PRTrans"), " A.PRTransId=B.PRTransId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_PRRegister"), "B.PRRegisterId=C.PRRegisterId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"), "A.WareHouseId=D.TransId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_WareHouse"), "D.WareHouseId=E.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("f" => "MMS_Stock"), "B.ResourceId=F.ResourceId And B.ItemId=F.ItemId and  c.costcentreId= f.costcentreId ", array(), $select::JOIN_INNER)
                        ->join(array("g" => "MMS_StockTrans"), "F.StockId=G.StockId And A.WareHouseId=G.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array("d.LastLevel=1 And C.PRRegisterId=$PRRegisterId"));

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(
                            "StockId"=>new Expression("e.StockId"),
                            "ResourceId"=>new Expression("e.ResourceId"),
                            "ItemId"=>new Expression("e.ItemId"),
                            "WareHouseId"=>new Expression("c.TransId"),
                            "WareHouseName"=>new Expression("a.WareHouseName"),
                            "Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                        ))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                        ->where(array("B.CostCentreId= $CostCentreId And C.LastLevel=1 and D.ClosingStock>0 And (E.ResourceId IN (Select ResourceId From MMS_PRTrans Where PRRegisterId=$PRRegisterId) And
     					E.ItemId IN (Select ItemId From MMS_PRTrans Where PRRegisterId=$PRRegisterId)) And C.TransId NOT IN (Select A.WareHouseId From MMS_PRWareHouseTrans A inner Join MMS_PRTrans B On A.PRTransId=B.PRTransId Where B.PRRegisterId=$PRRegisterId)"));
                    $select -> combine($selectsub,"Union All");
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //wbs-warehouse

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(
                            "StockId"=>new Expression("e.StockId"),
                            "ResourceId"=>new Expression("e.ResourceId"),
                            "ItemId"=>new Expression("e.ItemId"),
                            "WareHouseId"=>new Expression("c.TransId"),
                            "WareHouseName"=>new Expression("a.WareHouseName"),
                            "Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
                        ))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
                        ->where(array("b.CostCentreId= $CostCentreId  And c.LastLevel=1 and(
                        e.ResourceId IN (Select ResourceId From MMS_PRTrans Where PRRegisterId=$PRRegisterId) and
                        e.ItemId IN (Select ItemId From MMS_PRTrans Where PRRegisterId=$PRRegisterId))"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_wbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_PRWareHouseAnalTrans"))
                        ->columns(array(
                            "StockId"=>new Expression("f.StockId"),
                            "ResourceId"=>new Expression("b.ResourceId"),
                            "ItemId"=>new Expression("b.ItemId"),
                            "WareHouseId"=>new Expression("d.TransId"),
                            "WareHouseName"=>new Expression("e.WareHouseName"),
                            "Description"=>new Expression("d.Description"),
                            "ClosingStock"=>new Expression("CAST(g.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
                            "HiddenQty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
                            "AnalysisId" => new Expression("a.AnalysisId")))
                        ->join(array("b" => "MMS_PRTrans"), " A.PRTransId=B.PRTransId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_PRRegister"), "B.PRRegisterId=C.PRRegisterId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"), "A.WareHouseId=D.TransId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_WareHouse"), "D.WareHouseId=E.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("f" => "MMS_Stock"), "B.ResourceId=F.ResourceId And B.ItemId=F.ItemId and  c.costcentreId= f.costcentreId ", array(), $select::JOIN_INNER)
                        ->join(array("g" => "MMS_StockTrans"), "F.StockId=G.StockId And A.WareHouseId=G.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array("d.LastLevel=1 And C.PRRegisterId=$PRRegisterId"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sels_wbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //end of wbs-warehouse


                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.projectId=d.projectId',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.costcentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function returnSaveAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(309, date('Y/m/d'), 0, 0, $dbAdapter, "");
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
//
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";die;

                $voucherno='';
                $PRRegisterId = $postParams['PRRegisterId'];
                $CostCenterId = $this->bsf->isNullCheck($postParams['CostCentre'],'number');
                $SupplierId = $this->bsf->isNullCheck($postParams['Supplier'],'number');
                $PRDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['PRDate'], 'string')));
                $BillDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['BillDate'], 'string')));
                $BillNo = $this->bsf->isNullCheck($postParams['BillNo'],'number');
                $CCPRNo = $this->bsf->isNullCheck($postParams['CCPRNo'],'number');
                $CPRNo = $this->bsf->isNullCheck($postParams['CPRNo'],'number');
                $PRNo = $this->bsf->isNullCheck($postParams['PRNo'],'string');
                $voucherno=$PRNo;
                $Notes = $this->bsf->isNullCheck($postParams['Notes'],'string');
                $totamt= $this->bsf->isNullCheck($postParams['total'],'string');
                $gridtype = $this->bsf->isNullCheck($postParams['gridtype'],'number');

                $Approve="";
                $Role="";
                $PVRegisterId = $postParams['PVRegisterId'];
                if ($this->bsf->isNullCheck($PVRegisterId, 'number') > 0) {
                    $Approve="E";
                    $Role="Bill-Return-Modify";
                }else{
                    $Approve="N";
                    $Role="Bill-Return-Create";
                }
                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where("CostCentreId=$CostCenterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

                //CostCentre
                $CCPR = CommonHelper::getVoucherNo(309, date('Y/m/d'), 0, $CostCenterId, $dbAdapter, "");
                $this->_view->CCPR = $CCPR;

                //CompanyId
                $CPR = CommonHelper::getVoucherNo(309, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                $this->_view->CPR = $CPR;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($this->bsf->isNullCheck($PRRegisterId, 'number') > 0) {
                        $selPrevAnal=$sql->select();
                        $selPrevAnal->from(array("a"=>"MMS_PRTrans"))
                            ->columns(array(new Expression("a.Amount as ReturnAmount,a.PRTransId as PRTransId,a.ReturnQty As Qty,a.ResourceId as ResourceId,a.ItemId as ItemId")))
                            ->join(array("b"=>"mms_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
                            ->where(array("a.PRRegisterId"=>$PRRegisterId));
                        $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                        $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($prevanal as $arrprevanal)
                        {
                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" =>$CostCenterId,
                                    "ResourceId" => $arrprevanal['ResourceId'],
                                    "ItemId" => $arrprevanal['ItemId']
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if(count($stockselId) > 0){

                                $updDecAnal=$sql->update();
                                $updDecAnal->table('mms_stock');
                                $updDecAnal->set(array(
                                    'ReturnQty'=> new Expression('ReturnQty-'.$arrprevanal['Qty'].''),
                                    'ReturnAmount'=> new Expression('ReturnAmount-'.$arrprevanal['ReturnAmount'].''),
                                    'ClosingStock'=>new Expression('ClosingStock+'.$arrprevanal['Qty'].'')
                                ));
                                $updDecAnal->where(array("StockId" => $stockselId['StockId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $sel = $sql->select();
                            $sel->from(array("a" => "MMS_PRTrans"))
                                ->columns(array("ResourceId", "ItemId"))
                                ->join(array("b" => "MMS_PRWareHouseTrans"), "a.PRTransId=b.PRTransId", array("WareHouseId", "PRQty"), $sel::JOIN_INNER)
                                ->where(array("a.PRRegisterId" => $PRRegisterId));
                            $statementPrev = $sql->getSqlStringForSqlObject($sel);
                            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($pre as $prestockTrans){
                                $sUpdate = $sql->update();
                                $sUpdate->table('mms_stockTrans');
                                $sUpdate->set(array(
                                    "ReturnQty" => new Expression('ReturnQty+' . $prestockTrans['PRQty'] . ''),
                                    "ClosingStock" => new Expression('ClosingStock-' . $prestockTrans['PRQty'] . '')
                                ));
                                $sUpdate->where(array("StockId" => $stockselId['StockId'],"WareHouseId"=>$prestockTrans['WareHouseId']));
                                $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        } //overall foreach

                        $subQuery   = $sql->select();
                        $subQuery->from("mms_PRTrans")
                            ->columns(array("PRTransId"));
                        $subQuery->where(array('PRRegisterId'=>$PRRegisterId));

                        $select = $sql->delete();
                        $select->from('mms_PRAnalTrans')
                            ->where->expression('PRTransId IN ?',
                                array($subQuery));
                        $WBSTransStatement = $sql->getSqlStringForSqlObject($select);
                        $register1 = $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select1 = $sql->delete();
                        $select1->from('mms_PRWareHouseAnalTrans')
                            ->where->expression('PRTransId IN ?',
                                array($subQuery));
                        $wareTransStatement = $sql->getSqlStringForSqlObject($select1);
                        $dbAdapter->query($wareTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->delete();
                        $select->from("mms_PRTrans")
                            ->where(array('PRRegisterId'=>$PRRegisterId));
                        $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
                        $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delPVQualTrans = $sql->delete();
                        $delPVQualTrans->from('MMS_PRQualTrans')
                            ->where(array("PRRegisterId" => $PRRegisterId));
                        $PVQualStatement = $sql->getSqlStringForSqlObject($delPVQualTrans);
                        $dbAdapter->query($PVQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delQualTrans = $sql->delete();
                        $delQualTrans->from('MMS_QualTrans')
                            ->where(array("RegisterId" => $PRRegisterId));
                        $QualStatement = $sql->getSqlStringForSqlObject($delQualTrans);
                        $dbAdapter->query($QualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $registerUpdate = $sql->update()
                            ->table("MMS_PRRegister")
                            ->set(array(
                                "PRDate" => $PRDate,
                                "PRNo" => $voucherno,
                                "BillNo" => $BillNo,
                                "BillDate" => $BillDate,
                                "CCPRNo" => $CCPRNo,
                                "CPRNo" => $CPRNo,
                                "Narration" => $Notes,
                                "BillAmount" => $totamt
                            ))
                            ->where(array("PRRegisterId" => $PRRegisterId));
                        $upregStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($upregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $resTotal = $postParams['rowid'];
                        for ($i = 1; $i < $resTotal; $i++) {
                            if (is_null($postParams['resourceid_' . $i]))
                                continue;
                            $PRtransInsert = $sql->insert('mms_PRTrans');
                            $PRtransInsert->values(array(
                                "PRRegisterId" => $PRRegisterId,
                                "UnitId" => $postParams['unitid_' . $i],
                                "ResourceId" => $postParams['resourceid_' . $i],
                                "ItemId" => $postParams['itemid_' . $i],
                                "ReturnQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                "Amount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                "QAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "PurchaseTypeId" => $this->bsf->isNullCheck($postParams['PurchaseAccount_' . $i], 'string'),
                                "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number')));
                            $PRtransStatement = $sql->getSqlStringForSqlObject($PRtransInsert);
                            $dbAdapter->query($PRtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PRTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" =>$CostCenterId,
                                    "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i]
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {
                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "ReturnQty" => new Expression('ReturnQty+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                    'ReturnAmount' => new Expression('ReturnAmount +' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number')),
                                    "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
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
                                        "ReturnQty" => $postParams['qty_' . $i],
                                        "ReturnAmount" => $postParams['amount_' . $i],
                                        "ClosingStock" => $postParams['qty_' . $i]
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } // end of stock

                            //start warehouse-stocktrans-edit
                            $whTotal = $postParams['wh_' . $i . '_rowid'];
                            for ($w = 1; $w <= $whTotal; $w++) {
                                if($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0 ) {
                                    $whInsert = $sql->insert('MMS_PRWareHouseTrans');
                                    $whInsert->values(array("PRTransId" => $PRTransId,
                                        "WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],
                                        "PRQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . '')
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans adding
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],"StockId" => $stockselId['StockId'] ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "ReturnQty" => new Expression('ReturnQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . ''),
                                            "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . '')
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
                                                "StockId" => $stockselId['StockId'],
                                                "ReturnQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                                "ClosingStock" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                            //end of warehouse-stocktrans - edit

                            $qual = $postParams['QualRowId_' . $i];
                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_PRQualTrans');
                                    $qInsert->values(array(
                                        "PRRegisterId" => $PRRegisterId,
                                        "PRTransId" => $PRTransId,
                                        "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q],
                                        "YesNo" => "1",
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "Sign" =>  $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Sign_' . $q], 'string' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number' . ''),
                                        "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number' . ''),
                                        "Expression" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Exp_' . $q], 'string' . ''),
                                        "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number' . '')));
                                    $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                    $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $select = $sql->select();
                            $select->from(array("a" => "MMS_PRTrans"))
                                ->columns(array('Amount' => new Expression ("sum(a.Amount)")))
                                ->where("a.PRRegisterId=$PRRegisterId");
                            $Statement = $sql->getSqlStringForSqlObject($select);
                            $pvregAmount = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $pvregAmount['Amount'];

                            $Update = $sql->update();
                            $Update->table('MMS_PRRegister');
                            $Update->set(array(
                                "BillAmount" => $pvregAmount['Amount']
                            ));
                            $Update->where("PRRegisterId=$PRRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($Update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            $wbsTotal = $postParams['iow_' . $i . '_rowid'];
                            for ($j = 0; $j < $wbsTotal; $j++) {
                                if (($postParams['iow_' . $i . '_qty_' . $j]) > 0) {
                                    $PRAnalTransInsert = $sql->insert('mms_PRAnalTrans');
                                    $PRAnalTransInsert->values(array(
                                        "PRTransId" => $PRTransId,
                                        "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_wbsid_' . $j], 'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                        "ReturnQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number')
                                    ));
                                    $PRAnalTransStatement = $sql->getSqlStringForSqlObject($PRAnalTransInsert);
                                    $dbAdapter->query($PRAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $PRAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                                //warehouse-insert -add
                                $whTotal = $postParams['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                for ($wh = 1; $wh <= $whTotal; $wh++) {
                                    if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                        $whInsert = $sql->insert('MMS_PRWareHouseAnalTrans');
                                        $whInsert->values(array("PRTransId" => $PRTransId, "PRAnalTransId" => $PRAnalTransId,
                                            "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_wbsid_' . $j], 'number'),
                                            "WareHouseId" => $postParams['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                            "PRQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                        ));
                                        $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                        $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }

                            $fselect = $sql->select();
                            $fselect->from(array("a" => "MMS_PRWareHouseAnalTrans"))
                                ->columns(array(new Expression("SUM(a.PRQty) as Qty, A.WareHouseId as WareHouseId,
                                           a.PRTransId as PRTransId")))
                                ->where(array("PRTransId" => $PRTransId));
                            $fselect->group(array("a.WareHouseId","a.PRTransId"));
                            $statement = $sql->getSqlStringForSqlObject($fselect);
                            $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($ware As $wareData){

                                if($wareData['Qty'] > 0){
                                    $whInsert = $sql->insert('MMS_PRWareHouseTrans');
                                    $whInsert->values(array("PRTransId" => $wareData['PRTransId'],
                                        "WareHouseId" => $wareData['WareHouseId'],
                                        "PRQty" => $wareData['Qty']
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans adding
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $wareData['WareHouseId'],
                                            "StockId" => $stockselId['StockId'] ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "ReturnQty" => new Expression('ReturnQty+' . $wareData['Qty'] . ''),
                                            "ClosingStock" => new Expression('ClosingStock-' . $wareData['Qty'] . '')
                                        ));
                                        $sUpdate->where(array("StockId" => $sId['StockId'],
                                            "WareHouseId"=> $wareData['WareHouseId']));
                                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else
                                    {
                                        if($wareData['Qty'] > 0 ) {
                                            $stock1 = $sql->insert('mms_stockTrans');
                                            $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                "StockId" => $stockselId['StockId'],
                                                "ReturnQty" => $wareData['Qty'],
                                                "ClosingStock" => $wareData['Qty']
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                        }
                        $qrow = $postParams['Qrowid'];
                        $type='P';
                        if(count($qrow) > 0) {
                            for ($r = 0; $r < $qrow; $r++) {
                                $qInsert = $sql->insert('mms_QualTrans');
                                $qInsert->values(array(
                                    "RegisterId" => $PRRegisterId,
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
                                $Update->table('mms_PRQualTrans');
                                $Update->set(array(
                                    "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . '')
                                ));
                                $Update->where(array("Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                    "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                    "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . '')
                                ));
                                $statement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                    } else {
                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(309, date('Y/m/d', strtotime($PRDate)), 0, 0, $dbAdapter, "I");
                            $voucherno = $voucher['voucherNo'];
                        } else {
                            $voucherno = $PRNo;
                        }

                        if ($CCPR['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(309, date('Y/m/d', strtotime($PRDate)), 0, $CostCenterId, $dbAdapter, "I");
                            $CCPRNo = $voucher['voucherNo'];
                        } else {
                            $CCPRNo = $CCPRNo;
                        }

                        if ($CPR['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(309, date('Y/m/d', strtotime($PRDate)), $CompanyId, 0, $dbAdapter, "I");
                            $CPRNo = $voucher['voucherNo'];
                        } else {
                            $CPRNo = $CPRNo;
                        }
                        $registerInsert = $sql->insert('mms_PRRegister');
                        $registerInsert->values(array(
                            "VendorId" => $SupplierId,
                            "CostCentreId" => $CostCenterId,
                            "PRNo" => $PRNo,
                            "PRDate" => $PRDate,
                            "BillNo" => $BillNo,
                            "CCPRNo" => $CCPRNo,
                            "CPRNo" => $CPRNo,
                            "Narration" => $Notes,
                            "BillDate" => $BillDate,
                            "BillAmount" => $totamt,
                            "DeleteFlag" => 0,
                            "GridType" => $gridtype
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $PRRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $resTotal = $postParams['rowid'];
                        for ($i = 1; $i < $resTotal; $i++) {
                            if (is_null($postParams['resourceid_' . $i]))
                                continue;
                            $PRtransInsert = $sql->insert('mms_PRTrans');
                            $PRtransInsert->values(array(
                                "PRRegisterId" => $PRRegisterId,
                                "UnitId" => $postParams['unitid_' . $i],
                                "ResourceId" => $postParams['resourceid_' . $i],
                                "ItemId" => $postParams['itemid_' . $i],
                                "ReturnQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                "PurchaseTypeId" => $this->bsf->isNullCheck($postParams['PurchaseAccount_' . $i], 'string'),
                                "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number')));
                            $PRtransStatement = $sql->getSqlStringForSqlObject($PRtransInsert);
                            $dbAdapter->query($PRtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PRTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            // stock details adding
                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" =>$CostCenterId,
                                    "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i]
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {
                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "ReturnQty" => new Expression('ReturnQty+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                    'ReturnAmount' => new Expression('ReturnAmount +' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number')),
                                    "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
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
                                        "ReturnQty" => $postParams['qty_' . $i],
                                        "ReturnAmount" => $postParams['amount_' . $i],
                                        "ClosingStock" => $postParams['qty_' . $i]
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } // end of stock
                            //warehouse-stocktrans changes
                            $wareTotal = $postParams['wh_' . $i . '_rowid'];
                            for ($w = 1; $w <= $wareTotal; $w++) {
                                if($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0 ) {
                                    $whInsert = $sql->insert('MMS_PRWareHouseTrans');
                                    $whInsert->values(array("PRTransId" => $PRTransId,
                                        "WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],
                                        "PRQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . '')
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans adding
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w],"StockId" => $stockselId['StockId'] ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "ReturnQty" => new Expression('ReturnQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . ''),
                                            "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') . '')
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
                                                "StockId" => $stockselId['StockId'],
                                                "ReturnQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                                "ClosingStock" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number'),
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                            // end of warehouse stock trans
                            $qual = $postParams['QualRowId_' . $i];
                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_PRQualTrans');
                                    $qInsert->values(array(
                                        "PRRegisterId" => $PRRegisterId,
                                        "PRTransId" => $PRTransId,
                                        "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q],
                                        "YesNo" => "1",
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "Sign" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Sign_' . $q], 'string' . ''),
                                        "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number' . ''),
                                        "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number' . ''),
                                        "Expression" =>  $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Exp_' . $q], 'string' . ''),
                                        "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number' . '')));
                                    $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                    $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $select = $sql->select();
                            $select->from(array("a" => "MMS_PRTrans"))
                                ->columns(array('Amount' => new Expression ("sum(a.Amount)")))
                                ->where("a.PRRegisterId=$PRRegisterId");
                            $Statement = $sql->getSqlStringForSqlObject($select);
                            $pvregAmount = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $Update = $sql->update();
                            $Update->table('MMS_PRRegister');
                            $Update->set(array(
                                "BillAmount" => $pvregAmount['Amount']
                            ));
                            $Update->where("PRRegisterId=$PRRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($Update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $wbsTotal = $postParams['iow_' . $i . '_rowid'];
                            for ($j = 1; $j <= $wbsTotal; $j++) {
                                if (($postParams['iow_' . $i . '_qty_' . $j]) > 0) {
                                    $PRAnalTransInsert = $sql->insert('mms_PRAnalTrans');
                                    $PRAnalTransInsert->values(array(
                                        "PRTransId" => $PRTransId,
                                        "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_wbsid_' . $j], 'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                        "ReturnQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number')
                                    ));
                                    $PRAnalTransStatement = $sql->getSqlStringForSqlObject($PRAnalTransInsert);
                                    $PRAnalTransResults = $dbAdapter->query($PRAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $PRAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                                //warehouse-insert -add
                                $whTotal = $postParams['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                for ($wh = 1; $wh <= $whTotal; $wh++) {
                                    if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                        $whInsert = $sql->insert('MMS_PRWareHouseAnalTrans');
                                        $whInsert->values(array("PRTransId" => $PRTransId, "PRAnalTransId" => $PRAnalTransId,
                                            "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_wbsid_' . $j], 'number'),
                                            "WareHouseId" => $postParams['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                            "PRQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                        ));
                                        $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                        $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                            $fselect = $sql->select();
                            $fselect->from(array("a" => "MMS_PRWareHouseAnalTrans"))
                                ->columns(array(new Expression("SUM(a.PRQty) as Qty, A.WareHouseId as WareHouseId,
                                           a.PRTransId as PRTransId")))
                                ->where(array("PRTransId" => $PRTransId));
                            $fselect->group(array("a.WareHouseId","a.PRTransId"));
                            $statement = $sql->getSqlStringForSqlObject($fselect);
                            $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($ware As $wareData){

                                if($wareData['Qty'] > 0){
                                    $whInsert = $sql->insert('MMS_PRWareHouseTrans');
                                    $whInsert->values(array("PRTransId" => $wareData['PRTransId'],
                                        "WareHouseId" => $wareData['WareHouseId'],
                                        "PRQty" => $wareData['Qty']
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //stock trans adding
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $wareData['WareHouseId'],
                                            "StockId" => $stockselId['StockId'] ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "ReturnQty" => new Expression('ReturnQty+' . $wareData['Qty'] . ''),
                                            "ClosingStock" => new Expression('ClosingStock-' . $wareData['Qty'] . '')
                                        ));
                                        $sUpdate->where(array("StockId" => $sId['StockId'],
                                            "WareHouseId"=> $wareData['WareHouseId']));
                                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else
                                    {
                                        if($wareData['Qty'] > 0 ) {
                                            $stock1 = $sql->insert('mms_stockTrans');
                                            $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                "StockId" => $stockselId['StockId'],
                                                "ReturnQty" => $wareData['Qty'],
                                                "ClosingStock" => $wareData['Qty']
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                        }
                        //POP UP QUALIIFER DETAILS
                        $qrow = $postParams['Qrowid'];
                        $type='P';
                        if(count($qrow) > 0) {
                            for ($r = 0; $r < $qrow; $r++) {

                                $qInsert = $sql->insert('mms_QualTrans');
                                $qInsert->values(array(
                                    "RegisterId" => $PRRegisterId,
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
                                $Update->table('mms_PRQualTrans');
                                $Update->set(array(
                                    "AccountId" => $this->bsf->isNullCheck($postParams['accountname_' . $r], 'number' . '')
                                ));
                                $Update->where(array("Sign" => $this->bsf->isNullCheck($postParams['sign_' . $r], 'string' . ''),
                                    "ExpPer" => $this->bsf->isNullCheck($postParams['percentage_' . $r], 'number' . ''),
                                    "QualifierId" => $this->bsf->isNullCheck($postParams['QualifierId_' . $r], 'number' . '')
                                ));
                                $statement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                    }
                    $vType = CommonHelper::GetVoucherType(309,$dbAdapter);
                    if ($vType == "  " || $vType == "GE"){
                        $sPRNo= $voucherno;
                    } else if ($vType == "CC"){
                        $sPRNo= $CCPRNo;
                    } else if ($vType == "CO") {
                        $sPRNo= $CPRNo;
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Bill-Return',$PRRegisterId,$CostCenterId,$CompanyId, 'MMS',$sPRNo,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'billreturn', 'action' => 'returnbill-register'));
                }catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

        }
    }
    public function returnbillRegisterAction(){
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
        $selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_PRRegister"));
        $selectVendor->columns(array(
            "PRRegisterId" => new Expression("a.PRRegisterId"),
            "PRNo" => new Expression("a.PRNo"),
            "BillDate" => new Expression("Convert(varchar(10),a.BillDate,105)"),
            "PRDate" => new Expression("Convert(varchar(10),a.PRDate,105)"),
            "PRRegisterId" => new Expression("a.PRRegisterId"),
            "CostCentreName" => new Expression("b.CostCentreName"),
            "SupplierName" => new Expression("c.VendorName"),
            "Approve" => new Expression("Case when a.Approve='Y' Then 'Yes' when a.Approve='P' Then 'Partial' Else 'No' End ")


        ))
            ->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("c"=>"vendor_master"), "a.VendorId=c.VendorId", array(), $selectVendor::JOIN_LEFT)
            ->where(array('a.DeleteFlag'=>0))
            ->order("a.PRRegisterId Desc");
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->gridResult = $gridResult;
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function returnbillDetailedAction(){
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
        $PRRegisterId = $this->params()->fromRoute('prId');
        $request = $this->getRequest();
        $response = $this->getResponse();
        // if($request->isXmlHttpRequest()){
			// $request = $this->getRequest();
            // $resp = array();
			// if ($request->isPost()) { echo 44444; die;
                ////Write your Ajax post code here
				// $postParam = $request->getPost();
				// if($postParam['mode'] == 'getqualdetails'){

					// $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
					// $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
					// $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

					// $selSub2 = $sql -> select();
					// $selSub2->from(array("a" => "MMS_PRQualTrans"))
						// ->columns(array("QualifierId"));
					// $selSub2->where(array('a.PRRegisterId' => $POId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));

					// $selSub1 = $sql -> select();
					// $selSub1->from(array("a" => "Proj_QualifierTrans"))
						// ->columns(array('ResourceId'=>new Expression("'$ResId'"),'ItemId'=>new Expression("'$ItemId'"),'QualifierId'=>new Expression('a.QualifierId'),
							// 'YesNo'=>new Expression('Case When a.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('a.Expression'),
							// 'ExpPer'=>new Expression('a.ExpPer'),'TaxablePer'=>new Expression('a.TaxablePer'),'TaxPer'=>new Expression('a.TaxPer'),
							// 'Sign'=>new Expression('a.Sign'),'SurCharge'=>new Expression('a.SurCharge'),'EDCess'=>new Expression('a.EDCess'),
							// 'HEDCess'=>new Expression('a.HEDCess'),'NetPer'=>new Expression('a.NetPer'),'BaseAmount'=>new Expression("'0'"),
							// 'ExpressionAmt'=>new Expression("'0'"),'TaxableAmt'=>new Expression("'0'"),'TaxAmt'=>new Expression("'0'"),
							// 'SurChargeAmt'=>new Expression("'0'"),'EDCessAmt'=>new Expression("'0'"),'HEDCessAmt'=>new Expression("'0'"),
							// 'NetAmt'=>new Expression("'0'"),'QualifierName'=>new Expression('b.QualifierName'),'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
							// 'RefId'=>new Expression('b.RefNo'),'SortId'=>new Expression('a.SortId') ))
						// ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId",array(),$selSub1::JOIN_INNER)
						// ->where->expression('a.QualType='."'M'".' and a.QualifierId NOT IN ?', array($selSub2));

					// $select = $sql->select();
					// $select->from(array("c" => "MMS_PRQualTrans"))
						// ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
							// 'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
							// 'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
							// 'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
							// 'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
							// 'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
							// 'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
							// 'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3)) '),'QualifierName'=>new Expression('b.QualifierName'),
							// 'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
						// ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
						// ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);

					// $select->where(array('a.QualType' => 'M', 'c.PRRegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
					// $select -> combine($selSub1,"Union All");
					// $selMain = $sql -> select()->from(array('result'=>$select));
					// $selMain->order('SortId ASC');
					// $statement = $sql->getSqlStringForSqlObject($selMain);
					// $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					// $sHtml = Qualifier::getQualifier($qualList);
                // }
            // }

        // }
		
		if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                switch($Type) {
                    case 'getqualdetails':

                    $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
					$ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
					$POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

					$select = $sql->select();
					$select->from(array("c" => "MMS_PRQualTrans"))
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

					$select->where(array('a.QualType' => 'M', 'c.PRRegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
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


                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        }
		
		$selReqReg=$sql->select();
		$selReqReg->from(array('a' => 'mms_PRRegister'))
			->columns(array(
				'PRRegisterId'=> new Expression("a.PRRegisterId"),
				'PRDate'=> new Expression("a.PRDate"),
				'BillDate'=> new Expression("a.BillDate"),
				'PRNo'=> new Expression("a.PRNo"),
				'CostCentreId'=> new Expression("a.CostCentreId"),
				'CostCentreName'=> new Expression("b.CostCentreName"),
				'SupplierId'=> new Expression("a.VendorId"),
				'SupplierName'=> new Expression("c.VendorName"),
				'CCPRNo'=> new Expression("a.CCPRNo"),
				'CPRNo'=> new Expression("a.CPRNo"),
				'BillNo'=> new Expression("a.BillNo"),
				'Narration'=> new Expression("a.Narration"),
				'Approve'=> new Expression("a.Approve"),
				'BillAmount'=> new Expression("a.BillAmount"),
				'GridType' => new Expression("a.GridType")
			))
			->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $selReqReg:: JOIN_LEFT)
			->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $selReqReg:: JOIN_LEFT)
			->where("a.PRRegisterId=$PRRegisterId");
		$statement = $sql->getSqlStringForSqlObject( $selReqReg );
		$register= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$this->_view->PRRegisterId=$register['PRRegisterId'];
		$this->_view->PRDate= date('Y-m-d', strtotime($register['PRDate']));
		$this->_view->BillDate= date('Y-m-d', strtotime($register['BillDate']));
		$this->_view->vNo=$register['PRNo'];
		$this->_view->costcenterid=$register['CostCentreId'];
		$this->_view->costcenter=$register['CostCentreName'];
		$this->_view->supplierid=$register['SupplierId'];
		$this->_view->supplier=$register['SupplierName'];
		$this->_view->CCPRNo=$register['CCPRNo'];
		$this->_view->CPRNo=$register['CPRNo'];
		$this->_view->BillNo=$register['BillNo'];
		$this->_view->Narration=$register['Narration'];
		$this->_view->BillAmount=$register['BillAmount'];
		$this->_view->Approve=$register['Approve'];
		$this->_view->gridtype=$register['GridType'];
		$CostCentreId=$this->_view->costcenterid;
		$SupplierId=$this->_view->supplierid;
		$Approve=$this->_view->Approve;


		$select = $sql->select();
		$select->from(array('a' => 'MMS_CCWareHouse'))
			->columns(array('WareHouseId'))
			->where(array("a.CostCentreId=$CostCentreId"));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->WarehouseCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

		$select = $sql->select();
		$select->from(array('a' => 'mms_purchasetype'))
			->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
			->join(array('b' => 'FA_AccountMaster'), 'a.AccountId=b.AccountId', array(), $select::JOIN_INNER)
			->where(array('a.sel' => "1"));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array('a' => 'Proj_Resource'))
			//->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
			->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
				Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
				Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
				Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
				Case When isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End As Rate ")))
			->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
			->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
			->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
			->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
			->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
			->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=e.ProjectId',array(),$select::JOIN_INNER)
			->where("g.CostCentreId=$CostCentreId");

		$selRa = $sql -> select();
		$selRa->from(array("a" => "Proj_Resource"))
			->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
					Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
					Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
					Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
					Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
					Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate  ")))
			->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
			->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
			->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
			->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
			->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
					Inner Join WF_OperationalCostCentre b On a.projectId=b.projectId
					Where b.costcentreId =". $CostCentreId .") and (a.ResourceId NOT IN (Select ResourceId From MMS_PRTrans
			 Where PRRegisterId=".$PRRegisterId.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_PRTrans Where PRRegisterId=".$PRRegisterId."))  ");
		$select -> combine($selRa,"Union All");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array('a' => 'MMS_PRTrans'))
			->columns(array(
				"ResourceId" => new Expression("a.ResourceId"),
				"PRRegisterId" => new Expression("a.PRRegisterId"),
				"ItemId" => new Expression("a.ItemId"),
				"Code" => new Expression("Case When a.ItemId>0 Then d.ItemCode Else b.Code End"),
				"ResourceName" => new Expression("Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End"),
				"ResourceGroupName" => new Expression("c.ResourceGroupName"),
				"ResourceGroupId" => new Expression("c.ResourceGroupId"),
				"UnitName" => new Expression("f.UnitName"),
				"UnitId" => new Expression("a.UnitId"),
				"Rate" => new Expression("CAST (a.Rate as Decimal(18,2))"),
				"QRate" => new Expression("CAST (a.QRate as Decimal (18,2))"),
				"Qty" => new Expression("CAST (a.ReturnQty as Decimal(18,3))"),
				"Amount" => new Expression("CAST (a.Amount as Decimal (18,2))"),
				"QAmount" => new Expression("CAST (a.QAmount as Decimal(18,2))"),
				"PurchaseTypeId" => new Expression("a.PurchaseTypeId"),
				"PurchaseTypeName" => new Expression("h.PurchaseTypeName"),
			))
			->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId ', array(), $select:: JOIN_INNER)
			->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
			->join(array('d' => 'MMS_Brand'), 'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId ', array(), $select::JOIN_LEFT)
			->join(array('e' => 'MMS_PRRegister'), 'a.PRRegisterId=e.PRRegisterId', array(), $select::JOIN_INNER)
			->join(array('f' => 'Proj_UOM'), 'a.UnitId=f.UnitId',array(), $select::JOIN_LEFT)
			->join(array('h' => 'mms_purchasetype'), 'a.PurchaseTypeId=h.PurchaseTypeId',array(), $select::JOIN_LEFT)
			->where("e.CostCentreId=" . $CostCentreId . " and e.PRRegisterId=".$PRRegisterId."");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$subWbs1=$sql->select();
		$subWbs1->from(array('a'=>'Proj_WBSMaster'))
			->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,0 As Qty")))
			->join(array('e' => 'WF_OperationalCostCentre'),'e.projectId=a.ProjectId',array(),$subWbs1::JOIN_INNER);
		$subWbs1->where ('a.LastLevel=1 And e.costcentreId='.$CostCentreId.' ');
//                        And a.WBSId NOT IN (Select AnalysisId From mms_PRAnalTrans Where PRTransId IN (Select PRTransId From MMS_PRTrans Where PRRegisterId=?))', $PRRegisterId);
//                    $selectAnal = $sql->select();
//                    $selectAnal->from(array("a" => "MMS_PRAnalTrans"))
//                        ->columns(array(
//                            'ResourceId' => new Expression('e.ResourceId'),
//                            'ItemId' => new Expression('e.ItemId'),
//                            'WbsId' => new Expression('c.WBSId'),
//                            'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
//                            'Qty' => new Expression('CAST(A.ReturnQty As Decimal(18,5))')))
//                        ->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
//                        ->join(array("e" => "MMS_PRTrans"), " a.PRTransId=e.PRTransId", array(), $selectAnal::JOIN_LEFT);
//                    $selectAnal->where(array("e.PRRegisterId = $PRRegisterId"));
//                    $selectAnal->combine($subWbs1, 'Union ALL');
		$statement1 = $sql->getSqlStringForSqlObject($subWbs1);
		$this->_view->arr_resource_iows = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectAnal = $sql->select();
		$selectAnal->from(array("a" => "MMS_PRAnalTrans"))
			->columns(array(
				'ResourceId' => new Expression('e.ResourceId'),
				'ItemId' => new Expression('e.ItemId'),
				'WBSId' => new Expression('c.WBSId'),
				'WbsName' => new Expression("(C.ParentText +'->'+C.WBSName)"),
				'Qty' => new Expression('CAST(A.ReturnQty As Decimal(18,5))')))
			->join(array("c" => "Proj_WBSMaster"), "a.AnalysisId=c.WBSId", array(), $selectAnal::JOIN_LEFT)
			->join(array("e" => "MMS_PRTrans"), " a.PRTransId=e.PRTransId", array(), $selectAnal::JOIN_LEFT);
		$selectAnal->where(array("e.PRRegisterId = $PRRegisterId"));
		$statement1 = $sql->getSqlStringForSqlObject($selectAnal);
		$this->_view->arr_res_selwbs = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array("a" => "Proj_QualifierTrans"))
			->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
			->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer',
				'Sign','SurCharge','EDCess','HEDCess','NetPer',
				'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
				'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),
				'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
		$select->where(array('a.QualType' => 'M'));
		$select->order('a.SortId ASC');
		$statement = $sql->getSqlStringForSqlObject($select);
		$qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$sHtml=Qualifier::getQualifier($qualList);
		$this->_view->qualHtml = $sHtml;
		$this->_view->qualList = $qualList;

		$arrqual = array();
		$select = $sql->select();
		$select->from(array("a" => "MMS_PRQualTrans"))
			->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer',
				'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
				'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
				'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
				'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'))
			->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER);
		$select->where(array('a.PRRegisterId'=>$PRRegisterId));
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

		$selectsub = $sql ->select();
		$selectsub->from(array("a" => "MMS_PRWareHouseTrans"))
			->columns(array(
				"StockId"=>new Expression("f.StockId"),
				"ResourceId"=>new Expression("b.ResourceId"),
				"ItemId"=>new Expression("b.ItemId"),
				"WareHouseId"=>new Expression("d.TransId"),
				"WareHouseName"=>new Expression("e.WareHouseName"),
				"Description"=>new Expression("d.Description"),
				"ClosingStock"=>new Expression(" CAST(g.ClosingStock As Decimal(18,2))"),
				"Qty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
				"HiddenQty"=>new Expression("CAST(a.PRQty As Decimal(18,2))")
			))
			->join(array("b" => "MMS_PRTrans"), " A.PRTransId=B.PRTransId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_PRRegister"), "B.PRRegisterId=C.PRRegisterId", array(), $select::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"), "A.WareHouseId=D.TransId", array(), $select::JOIN_INNER)
			->join(array("e" => "MMS_WareHouse"), "D.WareHouseId=E.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("f" => "MMS_Stock"), "B.ResourceId=F.ResourceId And B.ItemId=F.ItemId and  c.costcentreId= f.costcentreId ", array(), $select::JOIN_INNER)
			->join(array("g" => "MMS_StockTrans"), "F.StockId=G.StockId And A.WareHouseId=G.WareHouseId", array(), $select::JOIN_INNER)
			->where(array("d.LastLevel=1 And C.PRRegisterId=$PRRegisterId"));

		$select = $sql ->select();
		$select->from(array("a" => "MMS_WareHouse"))
			->columns(array(
				"StockId"=>new Expression("e.StockId"),
				"ResourceId"=>new Expression("e.ResourceId"),
				"ItemId"=>new Expression("e.ItemId"),
				"WareHouseId"=>new Expression("c.TransId"),
				"WareHouseName"=>new Expression("a.WareHouseName"),
				"Description"=>new Expression("c.Description"),
				"ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
				"Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
				"HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
			))
			->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
			->where(array("B.CostCentreId= $CostCentreId And C.LastLevel=1 and D.ClosingStock>0 And (E.ResourceId IN (Select ResourceId From MMS_PRTrans Where PRRegisterId=$PRRegisterId) And
			E.ItemId IN (Select ItemId From MMS_PRTrans Where PRRegisterId=$PRRegisterId)) And C.TransId NOT IN (Select A.WareHouseId From MMS_PRWareHouseTrans A inner Join MMS_PRTrans B On A.PRTransId=B.PRTransId Where B.PRRegisterId=$PRRegisterId)"));
		$select -> combine($selectsub,"Union All");
		$selectStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//wbs-warehouse

		$select = $sql ->select();
		$select->from(array("a" => "MMS_WareHouse"))
			->columns(array(
				"StockId"=>new Expression("e.StockId"),
				"ResourceId"=>new Expression("e.ResourceId"),
				"ItemId"=>new Expression("e.ItemId"),
				"WareHouseId"=>new Expression("c.TransId"),
				"WareHouseName"=>new Expression("a.WareHouseName"),
				"Description"=>new Expression("c.Description"),
				"ClosingStock"=>new Expression(" CAST(D.ClosingStock As Decimal(18,2))"),
				"Qty"=>new Expression("CAST(0 As Decimal(18,2))"),
				"HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")
			))
			->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("d" => "MMS_StockTrans"), "c.TransId=d.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("e" => "MMS_Stock"), "D.StockId=E.StockId And B.CostCentreId=E.CostCentreId", array(), $select::JOIN_INNER)
			->where(array("b.CostCentreId= $CostCentreId  And c.LastLevel=1 and(
			e.ResourceId IN (Select ResourceId From MMS_PRTrans Where PRRegisterId=$PRRegisterId) and
			e.ItemId IN (Select ItemId From MMS_PRTrans Where PRRegisterId=$PRRegisterId))"));
		$selectStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_sel_wbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$select = $sql ->select();
		$select->from(array("a" => "MMS_PRWareHouseAnalTrans"))
			->columns(array(
				"StockId"=>new Expression("f.StockId"),
				"ResourceId"=>new Expression("b.ResourceId"),
				"ItemId"=>new Expression("b.ItemId"),
				"WareHouseId"=>new Expression("d.TransId"),
				"WareHouseName"=>new Expression("e.WareHouseName"),
				"Description"=>new Expression("d.Description"),
				"ClosingStock"=>new Expression("CAST(g.ClosingStock As Decimal(18,2))"),
				"Qty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
				"HiddenQty"=>new Expression("CAST(a.PRQty As Decimal(18,2))"),
				"AnalysisId" => new Expression("a.AnalysisId")))
			->join(array("b" => "MMS_PRTrans"), " A.PRTransId=B.PRTransId", array(), $select::JOIN_INNER)
			->join(array("c" => "MMS_PRRegister"), "B.PRRegisterId=C.PRRegisterId", array(), $select::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"), "A.WareHouseId=D.TransId", array(), $select::JOIN_INNER)
			->join(array("e" => "MMS_WareHouse"), "D.WareHouseId=E.WareHouseId", array(), $select::JOIN_INNER)
			->join(array("f" => "MMS_Stock"), "B.ResourceId=F.ResourceId And B.ItemId=F.ItemId and  c.costcentreId= f.costcentreId ", array(), $select::JOIN_INNER)
			->join(array("g" => "MMS_StockTrans"), "F.StockId=G.StockId And A.WareHouseId=G.WareHouseId", array(), $select::JOIN_INNER)
			->where(array("d.LastLevel=1 And C.PRRegisterId=$PRRegisterId"));
		$selectStatement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_sels_wbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		//end of wbs-warehouse


		$wbsRes = $sql -> select();
		$wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
			->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
			->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
			->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
			->join(array('d' => 'WF_OperationalCostCentre'),'a.projectId=d.projectId',array(),$wbsRes::JOIN_INNER)
			->where("a.IncludeFlag=1 and d.costcentreId=$CostCentreId");
		$statement = $sql->getSqlStringForSqlObject($wbsRes);
		$this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		
		
		
        // $selectVendor = $sql->select();
        // $selectVendor->from(array("a"=>"mms_PRRegister"));
        // $selectVendor->columns(array(
            // "PRRegisterId" => new Expression("a.PRRegisterId"),
            // "PRNo" => new Expression("a.PRNo"),
            // "BillNo" => new Expression("a.BillNo"),
            // "BillDate" => new Expression("Convert(varchar(10),a.BillDate,105)"),
            // "PRDate" => new Expression("Convert(varchar(10),a.PRDate,105)"),
            // "CostCentreName" => new Expression("b.CostCentreName"),
            // "SupplierName" => new Expression("c.VendorName"),
            // "Approve" => new Expression("Case when a.Approve='Y' then 'Yes' Else 'No' End")
        // ))
            // ->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $selectVendor::JOIN_LEFT)
            // ->join(array("c"=>"vendor_master"), "a.VendorId=c.VendorId", array(), $selectVendor::JOIN_LEFT)
            // ->where(array('a.PRRegisterId'=>$PRRegisterId));
        // $statement = $sql->getSqlStringForSqlObject($selectVendor);
        // $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        // $this->_view->gridResult = $gridResult;
        // $this->_view->PRRegisterId = $PRRegisterId;
        // $Approve = $gridResult["Approve"];
        // $this->_view->Approve = $Approve;

        // $selectVendor = $sql->select();
        // $selectVendor->from(array("a"=>"mms_prtrans"));
        // $selectVendor->columns(array(
            // "ResourceName" => new Expression("g.ResourceName"),
            // "Code" => new Expression("g.Code"),
            // "UnitName" => new Expression("e.UnitName"),
            // "ReturnQty" => new Expression("a.ReturnQty"),
            // "Rate" => new Expression("a.Rate"),
            // "Amount" => new Expression("a.Amount"),
            // "PRRegisterId" => new Expression("a.PRRegisterId"),
            // "PRTransId" => new Expression("a.PRTransId")
        // ))
            // ->join(array("e"=>"Proj_UOM"), "a.UnitId=e.UnitId", array(), $selectVendor::JOIN_LEFT)
            // ->join(array("g"=>"Proj_Resource"), "a.ResourceId=g.ResourceId", array(), $selectVendor::JOIN_LEFT)
            // ->join(array("f"=>"mms_prregister"),"a.PRRegisterId=f.PRRegisterId",array(),$selectVendor::JOIN_INNER);
        // $selectVendor->where(array("a.PRRegisterId"=>$PRRegisterId));
        // $statement = $sql->getSqlStringForSqlObject($selectVendor);
        // $trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // $selectAnal = $sql->select();
        // $selectAnal->from(array("a" => "mms_PRAnalTrans"))
            // ->columns(array(new Expression("a.PRAnalTransId,b.PRTransId,(c.ParentText+'->'+c.WBSName) As WbsName,a.ReturnQty As ReturnQty")))
            // ->join(array("c" => "Proj_WBSMaster"), "c.WBSId=a.AnalysisId", array(), $selectAnal::JOIN_LEFT)
            // ->join(array("b" => "MMS_PRTrans"), "a.PRTransId=b.PRTransId", array(), $selectAnal::JOIN_LEFT);
        // $selectAnal->where(array("b.PRRegisterId" => $PRRegisterId));
        // $statement1 = $sql->getSqlStringForSqlObject($selectAnal);
        // $anal = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        // $this->_view->anal = $anal;
        // $this->_view->trans = $trans;
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function returnbillDeleteAction(){
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
        $PRRegisterId = $this->params()->fromRoute('prId');

        $selPrevAnal = $sql->select();
        $selPrevAnal->from(array("a"=>"MMS_PRTrans"))
            ->columns(array(new Expression("a.Amount as ReturnAmount,a.PRTransId as PRTransId,a.ReturnQty As Qty,a.ResourceId as ResourceId,a.ItemId as ItemId")))
            ->join(array("b"=>"mms_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
            ->where(array("a.PRRegisterId"=>$PRRegisterId));
        $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
        $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($prevanal as $arrprevanal)
        {
            $updDecAnal=$sql->update();
            $updDecAnal->table('mms_stock');
            $updDecAnal->set(array(
                'ReturnQty'=> new Expression('ReturnQty-'.$arrprevanal['Qty'].''),
                'ReturnAmount'=> new Expression('ReturnAmount-'.$arrprevanal['ReturnAmount'].''),
                'ClosingStock'=>new Expression('ClosingStock+'.$arrprevanal['Qty'].'')
            ));
            $updDecAnal->where(array('ItemId'=>$arrprevanal['ItemId']));
            $updDecAnal->where(array('ResourceId'=>$arrprevanal['ResourceId']));
            $updDecAnal->where(array('CostCentreId'=>$arrprevanal['CostCentreId']));
            $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
            $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        $subQuery   = $sql->select();
        $subQuery->from("mms_PRTrans")
            ->columns(array("PRTransId"));
        $subQuery->where(array('PRRegisterId'=>$PRRegisterId));

        $select = $sql->delete();
        $select->from('mms_PRAnalTrans')
            ->where->expression('PRTransId IN ?',
                array($subQuery));
        $WBSTransStatement = $sql->getSqlStringForSqlObject($select);
        $register1 = $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->delete();
        $select->from("mms_PRTrans")
            ->where(array('PRRegisterId'=>$PRRegisterId));
        $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
        $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $UpdReg = $sql->update();
        $UpdReg->table('mms_IssueRegister')
            ->set(array('DeleteFlag' => 1))
            ->where("PRRegisterId = $PRRegisterId");
        $UpdStatement = $sql->getSqlStringForSqlObject($UpdReg);
        $Project = $dbAdapter->query($UpdStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        $this->redirect()->toRoute('mms/default', array('controller' => 'issue','action' => 'issue-register'));
        return $this->_view;
    }
    public function billreturnReportAction(){
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
            $this->redirect()->toRoute("billreturn/returnbill-register", array("controller" => "billreturn","action" => "returnbill-register"));
        }

        $dir = 'public/billreturn/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/billreturn/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $PRRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($PRRegisterId == 0)

            $this->redirect()->toRoute("billreturn/returnbill-register", array("controller" => "billreturn","action" => "returnbill-register"));

        if (!file_exists($filePath)) {
            $filePath = 'public/billreturn/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/billreturn/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;
        //Template
        $selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_PRRegister"));
        $selectVendor->columns(array(
            "PRRegisterId" => new Expression("a.PRRegisterId"),
            "PRNo" => new Expression("a.PRNo"),
            "BillDate" => new Expression("Convert(varchar(10),a.BillDate,105)"),
            "PRDate" => new Expression("Convert(varchar(10),a.PRDate,105)"),
            "CostCentreName" => new Expression("b.CostCentreName"),
            "SupplierName" => new Expression("c.VendorName"),
            "Approve" => new Expression("Case when a.Approve='Y' Then 'Yes' when a.Approve='P' Then 'Partial' Else 'No' End ")
        ))
            ->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("c"=>"vendor_master"), "a.VendorId=c.VendorId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("d"=>"WF_CostCentre"), "b.CostCentreId=d.CostCentreId", array("Address"), $selectVendor::JOIN_LEFT)
            ->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $selectVendor::JOIN_LEFT)
            ->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $selectVendor::JOIN_LEFT)
            ->join(array("g"=>"WF_CountryMaster"), "d.CountryId=g.CountryId", array("CountryName"), $selectVendor::JOIN_LEFT)
            ->where(array('a.DeleteFlag'=>0,'PRRegisterId'=>$PRRegisterId))
            ->order("a.BillDate Desc");
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $this->_view->reqregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        //Grid
        $selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_prtrans"));
        $selectVendor->columns(array(
            "SNo" => new Expression("(ROW_NUMBER() OVER(PARTITION by A.PRRegisterId Order by A.PRRegisterId asc))"),
            "ResourceName" => new Expression("g.ResourceName"),
            "Code" => new Expression("g.Code"),
            "UnitName" => new Expression("e.UnitName"),
            "ReturnQty" => new Expression("a.ReturnQty"),
            "Rate" => new Expression("a.Rate"),
            "QRate" => new Expression("a.QRate"),
            "Amount" => new Expression("a.Amount"),
            "QAmount" => new Expression("a.QAmount"),
            "PRRegisterId" => new Expression("a.PRRegisterId"),
            "PRTransId" => new Expression("a.PRTransId"),
            "QCount" => new Expression("(Select Count(PRTransId) From MMS_PRQUalTrans Where PRTransId = a.PRTransId)"),
        ))
            ->join(array("e"=>"Proj_UOM"), "a.UnitId=e.UnitId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("g"=>"Proj_Resource"), "a.ResourceId=g.ResourceId", array(), $selectVendor::JOIN_LEFT)
            ->join(array("f"=>"mms_prregister"),"a.PRRegisterId=f.PRRegisterId",array(),$selectVendor::JOIN_INNER);
        $selectVendor->where(array("a.PRRegisterId"=>$PRRegisterId));
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("c" => "MMS_PRQualTrans"))
            ->columns(array(
                'PRTransId'=>new Expression('c.PRTransId'),
                'PRRegisterId'=>new Expression('c.PRRegisterId'),
                'Expression'=>new Expression('c.Expression'),
                'ExpPer'=>new Expression('c.ExpPer'),
                'Sign'=>new Expression('c.Sign'),
                'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3))')))
            ->join(array("b" => "Proj_QualifierMaster"), "c.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
            ->where(array('c.PRRegisterId'=>$PRRegisterId));
        $regStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->register1 = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
}

