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
use Application\View\Helper\CommonHelper;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\Qualifier;

class MinconversionController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function conversionAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Minconversion","action" => "conversion"));
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

                $postParams = $request->getPost();
                $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');
                $VendorId = $this->bsf->isNullCheck($postParams['VendorId'], 'number');


                $select = $sql->select();
                $select->from(array("a" => "MMS_DCRegister"))
                    ->columns(array(new Expression("Distinct(a.DCRegisterId),Convert(Varchar(10),a.DCDate,103) As MINDate,
                                  a.DCNo as MINNo,a.SiteDCNo as SiteMINNo,Convert(Varchar(10),a.SiteDCDate,103) As SiteMINDate")))
                    ->join(array('b' => 'Vendor_Master'), 'a.VendorId=b.VendorId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'MMS_DCTrans'), 'a.DCRegisterId=c.DCRegisterId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=d.DCRegisterId', array(), $select::JOIN_INNER)
                    ->where("a.DCOrCSM <> 0 AND c.BalQty > 0 and d.BalQty > 0 AND a.Approve = 'Y' AND d.ShortClose=0 And a.CostCentreId= $CostCentreId And a.VendorId=$VendorId
                    GROUP BY a.DCRegisterId,a.DcDate,b.VendorName,a.SiteDcNo,a.SiteDcDate,a.CostCentreId,a.DCNo")
                    ->order("a.DCRegisterId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "MMS_DCRegister"))
                    ->columns(array(new Expression("Distinct(a.DCRegisterId),e.DCGroupId,
                    Case When b.ItemId>0 Then '(' + d.ItemCode +')'+ ' ' + d.BrandName Else '('+c.Code +')'+ ' ' + c.ResourceName End As Description,
                    a.DCNo As MinNo,Convert(Varchar(10),a.DCDate,103) As MinDate,
                     CAST(SUM(ISNULL(b.DCQty,0)) As Decimal(18,3)) As MinQty,
                     CAST(SUM(ISNULL(b.AcceptQty,0)) As Decimal(18,3)) As AcceptQty,
                     CAST(SUM(ISNULL(b.BalQty,0)) As Decimal(18,3)) As BalQty,
                     CONVERT(bit,0,0) As Include")))
                    ->join(array('b' => 'MMS_DCTrans'), 'a.DCRegisterId=b.DCRegisterId', array(), $select::JOIN_INNER)
                    ->join(array('e' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=e.DCRegisterId and b.DCGroupId=e.DCGroupId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_Resource'), 'b.ResourceId=c.ResourceId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_Brand'), 'b.ResourceId=d.ResourceId And b.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                    ->where("b.BalQty>0 And a.CostCentreId= $CostCentreId And a.VendorId=$VendorId  And
                     a.Approve='Y' And e.ShortClose=0 and e.BalQty > 0 GROUP BY a.DCRegisterId,e.DCGroupId,a.DcDate,b.DCQty,b.AcceptQty,
					 b.BalQty, b.resourceid,d.ItemCode,b.ItemId,c.Code,a.DCNo,
					 d.BrandName,c.ResourceName ");
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

            // populate the vendors
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                ->where(array('Supply' => '1'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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

    public function conversionentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Minconversion","action" => "conversion"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);
        $request = $this->getRequest();

        $pvid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');

        if (!$this->getRequest()->isXmlHttpRequest() && $pvid == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('mms/default', array('controller' => 'minconversion', 'action' => 'conversionregister'));

        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentre = $this->bsf->isNullCheck($postData['CostCentreId'], 'number');
                $resourceid = $this->bsf->isNullCheck($postData['resourceid'], 'number');
                $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');
                $itemid = $this->bsf->isNullCheck($postData['itemid'], 'number');
                $WBSId = $this->bsf->isNullCheck($postData['WBSId'], 'number');
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

                    case 'selectPo':

                        //DC-MIN REGISTER
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_DCRegister"))
                            ->columns(array(new Expression("a.DCRegisterId,b.DCGroupId,b.ResourceId,b.ItemId,b.UnitId,
                            a.DCNo as MINNo,CONVERT(varchar(10),a.DCDate,105) As MINDate,
                            a.SiteDCNo as SiteMINNo,CONVERT(varchar(10),a.SiteDCDate,105) As SiteMINDate,
                            CAST(b.DCQty As Decimal(18,3)) As MINQty,CAST(b.AcceptQty As Decimal(18,3)) As AcceptQty,
                            CAST(b.BillQty As Decimal(18,3)) As PrevBillQty,
                            CAST(b.BalQty As Decimal(18,3)) As BalQty")))
                            ->join(array('b' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=b.DCRegisterId ', array(), $select::JOIN_INNER)
                            ->where("a.CostCentreId=$CostCentre and
                            b.ResourceId =$resourceid and
                             b.ItemId =$itemid and
                             a.VendorId =$VendorId and  a.Approve='Y' and b.BalQty > 0");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['arr_resource_iows'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;

                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $pvId=$this->bsf->isNullCheck($this->params()->fromPost('pvId'), 'number');

                        $selSub2 = $sql -> select();
                        $selSub2->from(array("a" => "MMS_MCQualTrans"))
                            ->columns(array("QualifierId"));
                        $selSub2->where(array('a.PVRegisterId' => $pvId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));

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
                        $select->from(array("c" => "MMS_MCQualTrans"))
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
                        $select->where(array('a.QualType' => 'M', 'c.PVRegisterId' => $pvId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
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

                    case 'getAddqualdetails':

                        $resId = $this->bsf->isNullCheck($this->params()->fromPost('resourceId'), 'number');
                        $itemId = $this->bsf->isNullCheck($this->params()->fromPost('itemId'), 'number');
                        $dcgrpId=  $this->params()->fromPost('dcgrpId');

                        //qualifier from min
                        $selSub2 = $sql->select();
                        $selSub2 ->from(array("a" => "MMS_DCQualTrans"))
                            ->columns(array(new Expression("a.QualifierId as QualifierId ")))
                            ->join(array("b" => "MMS_DCTrans"), "a.DCTransId=b.DCTransId", array(),$selSub2::JOIN_INNER);
                        $selSub2->where(array("b.DCGroupId IN ($dcgrpId) and b.ResourceId IN ($resId) AND b.ItemId IN ($itemId)"));

                        $selSub1 = $sql -> select();
                        $selSub1->from(array("a" => "Proj_QualifierTrans"))
                            ->columns(array('ResourceId'=>new Expression("'$resId'"),'ItemId'=>new Expression("'$itemId'"),
                                'QualifierId'=>new Expression('a.QualifierId'),
                                'YesNo'=>new Expression("'0'"),'Expression'=>new Expression('a.Expression'),
                                'ExpPer'=>new Expression('a.ExpPer'),'TaxablePer'=>new Expression('a.TaxablePer'),
                                'TaxPer'=>new Expression('a.TaxPer'), 'Sign'=>new Expression('a.Sign'),
                                'SurCharge'=>new Expression('a.SurCharge'),'EDCess'=>new Expression('a.EDCess'),
                                'HEDCess'=>new Expression('a.HEDCess'),'NetPer'=>new Expression('a.NetPer'),
                                'BaseAmount'=>new Expression("'0'"), 'ExpressionAmt'=>new Expression("'0'"),
                                'TaxableAmt'=>new Expression("'0'"),'TaxAmt'=>new Expression("'0'"),
                                'SurChargeAmt'=>new Expression("'0'"),'EDCessAmt'=>new Expression("'0'"),
                                'HEDCessAmt'=>new Expression("'0'"), 'NetAmt'=>new Expression("'0'"),
                                'QualifierName'=>new Expression('b.QualifierName'),'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
                                'RefId'=>new Expression('b.RefNo'),'SortId'=>new Expression('a.SortId') ))
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId",array(),$selSub1::JOIN_INNER)
                            ->where->expression('a.QualType='."'M'".' and a.QualifierId NOT IN ?', array($selSub2));

                        $select = $sql->select();
                        $select->from(array("c" => "MMS_DCQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),
                                'QualifierId'=>new Expression('c.QualifierId'), 'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),
                                'Expression'=>new Expression('c.Expression'), 'ExpPer'=>new Expression('c.ExpPer'),
                                'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),
                                'EDCess'=>new Expression('c.EDCess'), 'HEDCess'=>new Expression('c.HEDCess'),
                                'NetPer'=>new Expression('c.NetPer'),
                                'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),
                                'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '), 'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(0 As Decimal(18,3)) '),
                                'QualifierName'=>new Expression('b.QualifierName'), 'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
                                'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("f" => "MMS_DCTrans"), "f.DCTransId=c.DCTransId", array(),$select::JOIN_INNER);
                        $select->where(array('a.QualType' => 'M', 'f.DCGroupId IN ('.$dcgrpId.')' ,
                            'c.ResourceId' => $resId, 'c.ItemId' => $itemId));

                        $selM1 = $sql -> select();
                        $selM1 -> from (array("a"=>"MMS_DCQualTrans"))
                            ->columns(array(new Expression("a.QualifierId")))
                            ->join(array("b" => "MMS_DCTrans"), "a.DCTransId=b.DCTransId", array(),$selSub2::JOIN_INNER)
                            ->where(array("b.DCGroupId IN ($dcgrpId)and b.ResourceId IN ($resId) AND b.ItemId IN ($itemId) "));
                        $selM1->group(array(new Expression("a.QualifierId Having count(1)>1")));
                        $select -> where -> expression('(c.QualifierId NOT IN ?)',array($selM1));

                        $select -> combine($selSub1,"Union All");
                        $selMain = $sql -> select()->from(array('result'=>$select));
                        $selMain->order('SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($selMain);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
                        $this->_view->qualHtml = $sHtml;
                        $this->_view->qualList = $qualList;

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

                if (!is_null($postData['frm_index'])) {
                    $CostCentreId = $this->bsf->isNullCheck($postData['costCentre'], 'string');
                    $VendorId = $this->bsf->isNullCheck($postData['vendor'], 'string');
                    $DCGroupIds = implode(',', $postData['DCGroupIds']);
                    $gridtype = $this->bsf->isNullCheck($postData['gridtype'], 'number');
                    $this->_view->gridtype = $gridtype;
                    $this->_view->dcGroupIds = $DCGroupIds;
                    //Get CompanyId
                    $getCompany = $sql -> select();
                    $getCompany->from("WF_OperationalCostCentre")
                        ->columns(array("CompanyId"));
                    $getCompany->where(array('CostCentreId'=>$CostCentreId));
                    $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                    $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');


                    $voNo = CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo = $voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    //CompanyId
                    $CPV = CommonHelper::getVoucherNo(304, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CPV = $CPV;
                    $CPVNo=$CPV['voucherNo'];
                    $this->_view->CPVNo = $CPVNo;

                    //CostCenterId
                    $CCPV = CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, $CostCentreId, $dbAdapter, "");
                    $this->_view->CCPV = $CCPV;
                    $CCPVNo=$CCPV['voucherNo'];
                    $this->_view->CCPVNo = $CCPVNo;

                    $this->_view->CostCentre = $CostCentreId;
                    $this->_view->Vendor = $VendorId;
                    $this->_view->DCGroupIds = $DCGroupIds;


                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where("Deactivate=0 AND CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                        ->where("VendorId=$VendorId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //purchasetype details
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array("Default"),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->order("b.Default Desc")
                        ->where('c.CostCentreId='.$CostCentreId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from('WF_CurrencyMaster')
                        ->columns(array('CurrencyId','CurrencyName'))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;


                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),
                        case when a.ItemId>0 then '('+c.ItemCode+')'+ ' ' +c.BrandName Else '('+b.Code+')'+ b.ResourceName End as [Desc],
                        CAST(SUM(a.BalQty) As Decimal(18,3)) As BillQty,
                        CAST(SUM(a.BalQty) As Decimal(18,3)) As ActualQty,
                        d.UnitId as UnitId, d.UnitName As UnitName,
                        Case When a.ItemId>0 Then CAST(c.Rate As Decimal(18,2)) Else CAST(b.Rate As Decimal(18,2)) End As Rate,
                        Case When a.ItemId>0 Then CAST(c.Rate As Decimal(18,2)) Else CAST(b.Rate As Decimal(18,2)) End As QRate,
                        Case When a.ItemId>0 Then CAST(SUM(a.BalQty)*c.Rate As Decimal(18,2)) Else CAST(SUM(a.BalQty)*b.Rate As Decimal(18,2)) End As BaseAmount,
                        Case When a.ItemId>0 Then CAST(SUM(a.BalQty)*c.Rate As Decimal(18,2)) Else CAST(SUM(a.BalQty)*b.Rate As Decimal(18,2)) End As Amount")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $select::JOIN_LEFT)
                        ->where('a.DCGroupId IN (' . $DCGroupIds . ')');
                    $select->group(array("a.ResourceId", "a.ItemId", "c.ItemCode","c.BrandName","b.Code","b.ResourceName",
                             "d.UnitId","d.UnitName","c.Rate","b.Rate" ));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //DC-MIN REGISTER
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCRegister"))
                        ->columns(array(new Expression("a.DCRegisterId,b.DCGroupId,b.ResourceId,
                        b.ItemId,b.UnitId,a.DCNo as MINNo,CONVERT(varchar(10),a.DCDate,105) As MINDate,
                        a.SiteDCNo as SiteMINNo,CONVERT(varchar(10),a.SiteDCDate,105) As SiteMINDate,
                        CAST(b.DCQty As Decimal(18,3)) As MINQty,CAST(b.AcceptQty As Decimal(18,3)) As AcceptQty,
                        CAST(b.BillQty As Decimal(18,3)) As PrevBillQty, CAST(b.BalQty As Decimal(18,3)) As BalQty")))
                        ->join(array('b' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=b.DCRegisterId ', array(), $select::JOIN_INNER)
                        ->where("a.CostCentreId= $CostCentreId and a.Approve='Y' ");
                    $select->where('b.DCGroupId IN ('.$DCGroupIds.')');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //QUALIFIER
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

                    //Qualifier Account deteails -add
                    $select = $sql->select();
                    $select->from(array("a" => "FA_AccountMaster"))
                        ->columns(array('AccountId','AccountName','TypeId'));
                    $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //AUTOCOMPLETE
                    $select = $sql->select();
                    $select ->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array(new Expression("distinct(a.ResourceId) As data,a.ItemId,a.UnitId,e.UnitName,CAST(a.Rate As Decimal(18,3)) As Rate,CAST(a.QRate As Decimal(18,2)) As QRate,
                                Case When a.ItemId>0 Then '('+d.ItemCode+')'+ ' '+d.BrandName Else '('+c.Code+')'+ ' ' +c.ResourceName End As value")))
                        ->join(array("b"=>'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$select::JOIN_INNER)
                        ->join(array("c"=>'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array("d"=>'MMS_Brand'),'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                        ->join(array("e"=>'Proj_UOM'),'a.UnitId=e.UnitId',array(),$select::JOIN_LEFT)
                        ->where(array("b.CostCentreId= $CostCentreId And b.VendorId= $VendorId And
                                     a.DCGroupId NOT IN (select DCGroupId from MMS_DCGroupTrans where DCGroupId IN ($DCGroupIds))
                                      And a.BalQty>0"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //TERMS AND CONDITION
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

                    //ADVANCE ADJUSTMENT
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
                            'DedutionBalance' => new Expression("CAST(B.AdjustAmount as Decimal(18,2))"),
                            'CurrentAmount' => new Expression("Cast(0 As Decimal(18,2))"),
                            'HiddenAmount' => new Expression('Cast(0 As Decimal(18,2))')
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'WF_TermsMaster'),'B.TermsId=C.TermsId',array(),$select::JOIN_INNER)
                        ->where("C.Title IN ('Advance','Against Delivery','Against Test Certificate') AND  A.VendorId= $VendorId And CostCentreId=$CostCentreId AND (B.Value-B.AdjustAmount)>0
                         And A.PODate <='$today'  GROUP BY A.PORegisterId,B.TermsId, A.PONo, A.PODate,C.Title,B.Value,B.AdjustAmount ");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_advance = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            }
            else {
                $postData = $request->getPost();
                //edit mode of conversionentry
                if (isset($pvid) && $pvid != '') {

                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->join(array('b' => 'MMS_PVRegister'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                        ->where("a.Deactivate=0 AND b.PVRegisterId=$pvid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Master'))
                        ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                        ->join(array('b' => 'MMS_PVRegister'), 'a.VendorId=b.VendorId')
                        ->where("b.PVRegisterId=$pvid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //PVREGSITER
                    $selPOReg=$sql->select();
                    $selPOReg->from(array('a'=>'MMS_PVRegister'))
                        ->columns(array(
                            'CostCentreId' => new Expression('a.CostCentreId'),
                            'SupplierId'=> new Expression('a.VendorId'),
                            'SupplierName'=> new Expression('c.VendorName'),
                            'CurrencyId'=> new Expression('a.CurrencyId'),
                            'CostCentreName'=> new Expression('b.CostCentreName'),
                            'PVDate'=> new Expression('Convert(Varchar(10),a.PVDate,103)'),
                            'BillDate'=> new Expression('Convert(Varchar(10),a.BillDate,103)'),
                            'PVNo'=> new Expression('a.PVNo'),
                            'CCPVNo'=> new Expression('a.CCPVNo'),
                            'CPVNo'=> new Expression('a.CPVNo'),
                            'BillNo'=> new Expression('a.BillNo'),
                            'BillAmount'=> new Expression('a.BillAmount'),
                            'Narration'=> new Expression('a.Narration'),
                            'PurchaseTypeId'=> new Expression('a.PurchaseTypeId'),
                            'Approve'=> new Expression('a.Approve'),
                            'PurchaseAccount'=> new Expression('a.PurchaseAccount'),
                            'GridType' => new Expression('a.GridType') ))
                        ->join(array('b'=>'WF_OperationalCostCentre'),'a.CostCentreId=b.CostCentreId',array(),$selPOReg::JOIN_INNER)
                        ->join(array('c'=>'Vendor_Master'),'a.VendorId=c.VendorId',array(),$selPOReg::JOIN_INNER)
                        ->where(array("a.PVRegisterId"=>$pvid));
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
                    $BillAmount=$this->_view->register ['BillAmount'];
                    $PurchaseTypeId=$this->_view->register ['PurchaseTypeId'];
                    $PurchaseAccount=$this->_view->register ['PurchaseAccount'];
                    $Approve=$this->_view->register ['Approve'];
                    $gridtype=$this->_view->register ['GridType'];
                    $this->_view->purchasetype= $PurchaseTypeId;
                    $this->_view->accounttype=$PurchaseAccount;
                    $this->_view->Narration=$Narration;
                    $this->_view->vNo = $PVNo;
                    $this->_view->currency= $CurrencyId;
                    $this->_view->SupplierName= $SupplierName;
                    $this->_view->PODate= $PVDate;
                    $this->_view->CCPVNo= $CCPVNo;
                    $this->_view->CPVNo= $CPVNo;
                    $this->_view->BillNo= $BillNo;
                    $this->_view->BillDate= $BillDate;
                    $this->_view->BillAmount= $BillAmount;
                    $this->_view->pvId =$pvid;
                    $this->_view->Approve =$Approve;
                    $this->_view->gridtype =$gridtype;

                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from('WF_CurrencyMaster')
                        ->columns(array('CurrencyId','CurrencyName'))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;

                    //PURCHASE TYPE
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->where(array("c.CostCentreId=$CostCentreId and b.Sel=1"));
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    //ACCOUNT TYPE
                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$PurchaseTypeId));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    //AUTOCOMPLETE
                    $select = $sql->select();
                    $select ->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array(new expression("distinct(a.ResourceId) As data,a.ItemId, a.UnitId,e.UnitName,CAST(a.Rate As Decimal(18,2)) As Rate,CAST(a.QRate As Decimal(18,2)) As QRate,
                                 Case When a.ItemId>0 Then d.ItemCode+' - '+d.BrandName Else c.Code+' - '+c.ResourceName End As value")))
                        ->join(array("b"=>'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId ',array(),$select::JOIN_INNER)
                        ->join(array("c"=>'Proj_Resource'),'a.ResourceId=c.ResourceId  ',array(),$select::JOIN_INNER)
                        ->join(array("d"=>'MMS_Brand'),'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array(), $select:: JOIN_LEFT)
                        ->where(array("b.CostCentreId= $CostCentreId And b.VendorId= $SupplierId And
                                    (a.ResourceId NOT IN (select ResourceId from MMS_PVGroupTrans where PVRegisterId= $pvid)
                                     OR a.ItemId NOT IN(Select ItemId From MMS_PVGroupTrans Where PVRegisterId= $pvid))
                                     And a.BalQty>0"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // edit mode- resource row
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PVGroupTrans"))
                        ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),a.UnitId,
                            case when a.ItemId>0 then '('+c.ItemCode+')'+ ' ' +c.BrandName Else '('+b.Code+')'+ ' '+ b.ResourceName End as [Desc],
                            CAST(a.BillQty As Decimal(18,3)) As BillQty,
                            CAST(a.ActualQty As Decimal(18,3)) As ActualQty,
                            CAST(a.Rate As Decimal(18,2)) As Rate,CAST(a.QRate As Decimal(18,2)) As QRate,
                            CAST(a.Amount As Decimal(18,2)) As Amount,CAST(a.QAmount As Decimal(18,2)) As QAmount,
                            CAST(e.BillAmount As Decimal(18,2)) As BillAmount")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'MMS_PVRegister'), 'a.PVRegisterId=e.PVRegisterId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId')
                        ->where('a.PVRegisterId=' . $pvid . '');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $dcgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arr_requestResources = $dcgroup;

                    //edit-mode- resourceitem - min row
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PVRegister"))
                        ->columns(array(new Expression("e.DCRegisterId,f.DCGroupId,b.ResourceId,b.ItemId,g.DCNo as MINNo,
                                        CONVERT(varchar(10),g.DCDate,105) As MINDate, g.SiteDCNo as SiteMINNo,
                                        CONVERT(varchar(10),g.SiteDCDate,105) As SiteMINDate,
                                        CAST(SUM(ISNULL(f.DCQty,0)) As Decimal(18,3)) As MINQty,
                                        CAST(SUM(ISNULL(f.AcceptQty,0)) As Decimal(18,3)) As AcceptQty,
                                        CAST(SUM(ISNULL(f.BillQty,0)) As Decimal(18,3)) As PrevBillQty,
                                        CAST(SUM(ISNULL(f.BalQty,0)) As Decimal(18,3)) As BalQty,
                                        CAST(SUM(ISNULL(d.Qty,0)) As Decimal(18,3)) As HiddenQty"
                        )))
                        ->join(array('b' => 'MMS_PVGroupTrans'), 'a.PVRegisterId=b.PVRegisterId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVTrans'), 'b.PVGroupId=c.PVGroupId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'MMS_IPDTrans'), 'c.PVTransId=d.PVTransId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'MMS_DCTrans'), 'd.DCTransId=e.DCTransId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'MMS_DCGroupTrans'), 'e.DCGroupId=f.DCGroupId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'MMS_DCRegister'), 'f.DCRegisterId=g.DCRegisterId', array(), $select::JOIN_INNER)
                        ->where("a.PVRegisterId=$pvid");
                    $select->group(array("e.DCRegisterId","F.DCGroupId",
                        "b.ResourceId","b.ItemId","g.DCNo","g.DCDate","g.SiteDCNo","g.SiteDCDate"));

                    $select1 = $sql->select();
                    $select1 ->from(array("a" => "MMS_DCRegister"))
                        ->columns(array(new Expression("a.DCRegisterId,b.DCGroupId,b.ResourceId,
                                    b.ItemId,a.DCNo as MINNo,CONVERT(varchar(10),a.DCDate,105) As MINDate,
                                    a.SiteDCNo as SiteMINNo,CONVERT(varchar(10),a.SiteDCDate,105) As SiteMINDate,
                                    CAST(ISNULL(SUM(b.DCQty),0) As Decimal(18,3)) As MINQty,CAST(ISNULL(SUM(b.AcceptQty),0) As Decimal(18,3)) As AcceptQty,
                                    CAST(ISNULL(SUM(b.BillQty),0) As Decimal(18,3)) As PrevBillQty,
                                    CAST(ISNULL(SUM(b.BalQty),0) As Decimal(18,3)) As BalQty,
                                    CAST(0 As Decimal(18,3)) As HiddenQty"
                        )))
                        ->join(array('b' => 'MMS_DCTrans'), 'a.DcregisterId=b.dcregisterId', array(), $select1::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=c.DCRegisterId and b.DCGroupId=c.DCGroupId', array(), $select1:: JOIN_INNER)
                        ->where("a.CostCentreId=$CostCentreId and a.Approve='Y' And CAST(b.BalQty As Decimal(18,5))>0 And
                                        (b.ResourceId IN (Select ResourceId From MMS_PVGroupTrans Where PVRegisterId= $pvid) And
                                         b.ItemId IN (Select ItemId From MMS_PVGroupTrans Where PVRegisterId=$pvid)) And
                                         b.dctransid not in (select b.dctransid from mms_ipdtrans a
                                         inner join mms_pvtrans b on a.pvtransid=b.pvtransid where b.pvregisterid= $pvid)");
                    $select1->group(array("a.DCRegisterId","b.DCGroupId",
                        "b.ResourceId","b.ItemId","a.DCNo","a.DCDate","a.SiteDCNo","a.SiteDCDate"));
                    $select->combine($select1, 'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                        where PVRegisterId=$pvid)"));
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
                        ->where("PVRegisterId=$pvid");
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
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign',
                            'SurCharge','EDCess','HEDCess','NetPer',
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
                    $sHtml= Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;
                    $this->_view->qualList = $qualList;

                    $arrqual = array();
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_MCQualTrans"))
                        ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression',
                            'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge',
                            'EDCess', 'HEDCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'));
                    $select->where(array('a.PVRegisterId'=>$pvid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->qualAccList = $qualAccList;

                    //Qualifier Account deteails - edit
                    $select = $sql->select();
                    $select->from(array("a" => "FA_AccountMaster"))
                        ->columns(array('AccountId','AccountName','TypeId'));
                    $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //advance adjustment
                    $today=date('Y/m/d');
                    $advsub = $sql->select();
                    $advsub->from(array("A" => "MMS_PORegister"))
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
                            'CurrentAmount' => new Expression("Cast(0 As Decimal(18,2))"),
                            'HiddenAmount' => new Expression("Cast(0 As Decimal(18,2))")
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'WF_TermsMaster'),'B.TermsId=C.TermsId',array(),$select::JOIN_INNER)
                        ->where("C.Title IN ('Advance','Against Delivery','Against Test Certificate') AND  A.VendorId=$SupplierId And CostCentreId=$CostCentreId AND (B.Value-B.AdjustAmount)>0
                         And A.PODate <='$today' and
                            ((a.PORegisterId NOT IN(select PORegisterId from mms_advAdjustment where BillRegisterId=$pvid)) OR
                            (b.termsid NOT IN(select termsid from mms_advadjustment where billregisterid= $pvid)))
                             GROUP BY A.PORegisterId,B.TermsId, A.PONo, A.PODate,C.Title,B.Value,B.AdjustAmount ");

                    $select = $sql->select();
                    $select->from(array("A" => "MMS_PORegister"))
                        ->columns(array(
                            'PORegisterId' => new Expression('A.PORegisterId'),
                            'PONo' => new Expression('A.PONo'),
                            'TermsId' => new Expression('B.TermsId'),
                            'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                            'Title' => new Expression('TM.Title'),
                            'Amount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                            'PaidAmount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                            'Balance' => new Expression("CAST(B.Value - ISNULL((Select SUM(Amount) From MMS_AdvAdjustment
                                  Where PORegisterId=A.PORegisterId And TermsId=B.TermsId),0) As Decimal(18,2))"),
                            'DeductionBalance' => new Expression("Cast(B.AdjustAmount As Decimal(18,2))"),
                            'CurrentAmount' => new Expression("CAST(C.Amount As Decimal(18,2))"),
                            'HiddenAmount' => new Expression("CAST(C.Amount As Decimal(18,2))")
                        ))
                        ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array("TM"=>'WF_TermsMaster'),'B.TermsId=TM.TermsId',array(),$select::JOIN_INNER)
                        ->join(array("C"=>'MMS_AdvAdjustment'),'A.PORegisterId=C.PORegisterId and TM.TermsId=c.TermsId',array(),$select::JOIN_INNER)
                        ->join(array("D"=>'MMS_PVRegister'),'C.BillRegisterId=D.PVRegisterId',array(),$select::JOIN_INNER)
                        ->where("TM.Title IN ('Advance','Against Delivery','Against Test Certificate') AND TM.TermType='S' AND  D.PVRegisterId=$pvid ");
                    $select->combine($advsub, 'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_advance = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            }


            //Common function
            $aVNo = CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];

            if (!$aVNo["genType"])
                $this->_view->woNo = "";
            else
                $this->_view->woNo = $aVNo["voucherNo"];
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function conversionsaveAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Minconversion","action" => "conversionentry"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);
        //$argLogId, $argLogTime, $argCCId, $arg_iRegId ,$dbAdapter,$argRole)
        //CommonHelper::Update_PurchaseBill(0,date('Y-m-d H:i:s'),0,2623,$dbAdapter,"Bill-Approval");die;
        $vNo = CommonHelper::getVoucherNo(304, date('Y/m/d'), 0, 0, $dbAdapter, "");
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
                $postParams = $request->getPost();

//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;

                $Approve="";
                $Role="";
                $pvId = $this->bsf->isNullCheck($postParams['pvId'], 'number');
                if ($this->bsf->isNullCheck($pvId, 'number') > 0) {
                    $Approve="E";
                    $Role="Bill-Modify";
                }else{
                    $Approve="N";
                    $Role="Bill-Create";
                }

                //getting the value from postparams
                $voucherno='';

                $VendorId = $this->bsf->isNullCheck($postParams['vendorId'], 'number');
                $CostCentreId = $this->bsf->isNullCheck($postParams['costcentreId'], 'number');
                $PVDate =date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['pvdate'], 'string')) ) ;
                $BillNo = $this->bsf->isNullCheck($postParams['billno'], 'string');
                $BillDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['billdate'], 'string')));
                $PVNo = $this->bsf->isNullCheck($postParams['pvno'], 'string');
                $voucherno=$PVNo;
                $CCPVNo = $this->bsf->isNullCheck($postParams['ccpvno'], 'string');
                $CPVNo = $this->bsf->isNullCheck($postParams['cpvno'], 'string');
                $Narration = $this->bsf->isNullCheck($postParams['narration'], 'string');
                $Amount = $this->bsf->isNullCheck($postParams['total'], 'number');
                $netAmount = $this->bsf->isNullCheck($postParams['nettotal'], 'number');
                $CurrencyId = $this->bsf->isNullCheck($postParams['currency'], 'number');
                $PurTypeId = $this->bsf->isNullCheck($postParams['purchase_type'], 'number');
                $AccountId = $this->bsf->isNullCheck($postParams['account_type'], 'number');
                $gridtype = $this->bsf->isNullCheck($postParams['gridtype'], 'number');
                if($gridtype == 0){
                    $Amount = $this->bsf->isNullCheck($postParams['basetotal1'], 'number');
                    $netAmount = $this->bsf->isNullCheck($postParams['nettotal1'], 'number');
                } else {
                    $Amount = $this->bsf->isNullCheck($postParams['basetotal'], 'number');
                    $netAmount = $this->bsf->isNullCheck($postParams['nettotal'], 'number');
                }


                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where("CostCentreId=$CostCentreId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

                //CompanyId
                $CPV = CommonHelper::getVoucherNo(305, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                $this->_view->CPV = $CPV;
                //CostCenterId
                $CCPV = CommonHelper::getVoucherNo(305, date('Y/m/d'), 0, $CostCentreId, $dbAdapter, "");
                $this->_view->CCPV = $CCPV;


                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    // starting the edit mode
                    if($pvId > 0){

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PVTrans"))
                            ->columns(array("DCTransId", "ActualQty"))
                            ->where(array("a.PVRegisterId" => $pvId));
                        $statementPrev = $sql->getSqlStringForSqlObject($select);
                        $poData = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($poData as $arrpoData) { // min QTY

                            $updDcTrans = $sql->update();
                            $updDcTrans->table('MMS_DCTrans');
                            $updDcTrans->set(array(

                                'BillQty' => new Expression('BillQty-' . $arrpoData['ActualQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrpoData['ActualQty'] . '')

                            ));
                            $updDcTrans->where(array('DCTransId' => $arrpoData['DCTransId']));
                            $updDcTransStatement = $sql->getSqlStringForSqlObject($updDcTrans);
                            $dbAdapter->query($updDcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PVTrans"))
                            ->columns(array(new Expression("SUM(a.ActualQty) as ActualQty")))
                            ->join(array("b"=>"MMS_DCTrans"), "a.DCTransId=b.DCTransId", array('DCGroupId'), $select::JOIN_INNER)
                            ->where(array("a.PVRegisterId" => $pvId));
                        $select->group(new Expression("b.DCGroupId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $prevGroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($prevGroup as $arrprevGroup) {

                            $updDcgTrans = $sql->update();
                            $updDcgTrans->table('MMS_DCGroupTrans');
                            $updDcgTrans->set(array(

                                'BillQty' => new Expression('BillQty-' . $arrprevGroup['ActualQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevGroup['ActualQty'] . '')

                            ));
                            $updDcgTrans->where(array('DCGroupId' => $arrprevGroup['DCGroupId']));
                            $updDcgTransStatement = $sql->getSqlStringForSqlObject($updDcgTrans);
                            $dbAdapter->query($updDcgTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PVATrans"))
                            ->columns(array("DCAnalTransId","BillQty"))
                            ->join(array("b"=>"MMS_DCAnalTrans"), "a.DCAnalTransId=b.DCAnalTransId", array(), $select::JOIN_INNER)
                            ->join(array("c"=>"MMS_PVTrans"), "a.PVTransId=c.PVTransId", array(), $select::JOIN_INNER)
                            ->where(array("PVRegisterId" => $pvId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $prevAnal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($prevAnal as $arrprevAnal) {

                            $updcaTrans = $sql->update();
                            $updcaTrans->table('MMS_DCAnalTrans');
                            $updcaTrans->set(array(

                                'BillQty' => new Expression('BillQty-' . $arrprevAnal['BillQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevAnal['BillQty'] . '')

                            ));
                            $updcaTrans->where(array('DCAnalTransId' => $arrprevAnal['DCAnalTransId']));
                            $updcaTransStatement = $sql->getSqlStringForSqlObject($updcaTrans);
                            $dbAdapter->query($updcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $seladv = $sql->select();
                        $seladv->from(array("a" => "mms_advadjustment"))
                            ->columns(array(
                                "PORegisterId" => new Expression("a.PORegisterId"),
                                "TermsId" => new Expression("a.TermsId"),
                                "Amount" => new Expression("a.Amount")
                            ))
                            ->where(array("a.BillRegisterId = $pvId"));
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


                        //delete the data for edit save
                        //MCQualTrans
                        $delPVQualTrans = $sql->delete();
                        $delPVQualTrans->from('MMS_MCQualTrans')
                            ->where(array("PVRegisterId" => $pvId));
                        $PVQualStatement = $sql->getSqlStringForSqlObject($delPVQualTrans);
                        $dbAdapter->query($PVQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delQualTrans = $sql->delete();
                        $delQualTrans->from('MMS_QualTrans')
                            ->where(array("RegisterId" => $pvId));
                        $QualStatement = $sql->getSqlStringForSqlObject($delQualTrans);
                        $dbAdapter->query($QualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //SUBQUERY-1
                        $subPVA = $sql->select();
                        $subPVA->from("MMS_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where(array("PVRegisterId" => $pvId));

                        $del = $sql->delete();
                        $del->from('MMS_PVATrans')
                            ->where->expression('PVTransId IN ?', array($subPVA));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //SUBQUERY-2
                        $subIPDT = $sql->select();
                        $subIPDT->from("MMS_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where(array("PVRegisterId" => $pvId));

                        $del = $sql->delete();
                        $del->from("MMS_IPDProjTrans")
                            ->where(array("Status"=>'U'));
                        $del->where->expression('PVTransId IN ?',array($subIPDT));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //SUBQUERY-3
                        $subIPD = $sql->select();
                        $subIPD->from("MMS_PVTrans")
                            ->columns(array("PVTransId"))
                            ->where(array("PVRegisterId" => $pvId));

                        $del = $sql->delete();
                        $del->from('MMS_IPDTrans')
                            ->where(array("Status"=> 'U'));
                        $del->where->expression('PVTransId IN ?', array($subIPD));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DELETE QUERY
                        $del = $sql->delete();
                        $del->from('MMS_PVGroupTrans')
                            ->where(array("PVRegisterId" => $pvId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_PVTrans')
                            ->where(array("PVRegisterId" => $pvId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delPVPayTrans = $sql -> delete();
                        $delPVPayTrans -> from ('MMS_PVPaymentTerms')
                            -> where (array("PVRegisterId" => $pvId));
                        $PVPayStatement = $sql->getSqlStringForSqlObject($delPVPayTrans);
                        $dbAdapter->query($PVPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $deladvadjustment = $sql -> delete();
                        $deladvadjustment -> from ('mms_advadjustment')
                            -> where (array("BillRegisterId" => $pvId));
                        $advadjustmentStatement = $sql->getSqlStringForSqlObject($deladvadjustment);
                        $dbAdapter->query($advadjustmentStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //UPDATE PVREGISTER
                        $registerUpdate = $sql->update()
                            ->table("MMS_PVRegister")
                            ->set(array(
                                "VendorId" => $VendorId,
                                "CostCentreId" => $CostCentreId,
                                "PVNo" => $voucherno,
                                "PVDate" => $PVDate,
                                "BillNo" => $BillNo,
                                "BillDate" => $BillDate,
                                "CCPVNo" => $CCPVNo,
                                "CPVNo" => $CPVNo,
                                "Narration" => $Narration,
                                "PurchaseTypeId" => $PurTypeId,
                                "PurchaseAccount" => $AccountId,
                                "CurrencyId" => $CurrencyId,
                                "Narration" => $Narration,
                                "Amount" => $Amount,
                                "BillAmount" => $netAmount
                            ))
                            ->where(array("PVRegisterId" => $pvId));
                        $upregStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($upregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // advance adjustment- editmode
                        $advTotal = $postParams['Arowid'];
                        for ($a = 1; $a <= $advTotal; $a++) {
                            if ($this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') > 0) {
                                $advanceInsert = $sql->insert('mms_advadjustment');
                                $advanceInsert->values(array(
                                    "BillRegisterId" => $pvId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number' . ''),
                                    "PORegisterId" => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number' . ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number' . '')
                                ));
                                $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updadj = $sql->update();
                                $updadj->table('mms_popaymentterms');
                                $updadj->set(array(
                                    "AdjustAmount" => new Expression('AdjustAmount+' . $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') . '')
                                ));
                                $updadj->where(array('PORegisterId' => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number' . ''), 'TermsId' => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number' . '')));
                                $updAdjStatement = $sql->getSqlStringForSqlObject($updadj);
                                $dbAdapter->query($updAdjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //terms and condition
                        $termsTotal = $postParams['trowid'];
                        for ($t = 1; $t <= $termsTotal; $t++) {
                            if ($this->bsf->isNullCheck($postParams['value_' . $t], 'number' . '') > 0 && $this->bsf->isNullCheck($postParams['account_' . $t], 'number' . '') > 0) {
                                $termsInsert = $sql->insert('MMS_PVPaymentTerms');
                                $termsInsert->values(array(
                                    "PVRegisterId" => $pvId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t], 'number' . ''),
                                    "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number' . ''),
                                    "AccountId" => $this->bsf->isNullCheck($postParams['account_' . $t], 'number' . '')
                                ));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $pvregId = $postParams['rowid'];
                        $dCDcQty = 0.0;
                        for ($i = 1; $i < $pvregId; $i++) {
                            if ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number') > 0) {

                                $dnq = ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) - ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . ''));
                                $PVGroupInsert = $sql->insert('MMS_PVGroupTrans');
                                $PVGroupInsert->values(array("PVRegisterId" => $pvId, "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                    "ActualQty" => $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . ''),
                                    "BillQty" => $this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . ''),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                    "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number' . ''),
                                    "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number' . ''),
                                    "DNQty" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) - ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')),
                                    "DNAmt" => new Expression("$dnq*" . $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                ));
                                $PVGroupStatement = $sql->getSqlStringForSqlObject($PVGroupInsert);
                                $dbAdapter->query($PVGroupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $PVGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                            $pvtransTotal = $postParams['iow_' . $i . '_rowid'];
                            $dDCGQty = $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '');
                            $dCDcQty = $dDCGQty;
                            if ($pvtransTotal > 0) {
                                for ($j = 1; $j <= $pvtransTotal; $j++) {
                                  if($dCDcQty > 0) {
                                    $DCGroupid = $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcgroupid_' . $j], 'number');
                                    $select = $sql->select();
                                    $select->from(array("a" => "MMS_DCTrans"))
                                        ->columns(array("DCTransId", "BalQty"))
                                        ->where('a.DCGroupId=' . $DCGroupid . ' and a.BalQty>0');
                                    $Statement = $sql->getSqlStringForSqlObject($select);
                                    $dctran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                    $dccount = count($dctran);

                                    if ($dccount > 0) {
                                        $iDcTransId = 0;
                                        $dDcQty = 0.0;


                                        foreach ($dctran as $dc) {

                                            $iDcTransId = $this->bsf->isNullCheck($dc['DCTransId'], 'number');
                                            $dDcQty = $this->bsf->isNullCheck($dc['BalQty'], 'number' . '');
                                            if ($dDcQty > 0) {

                                                if ($dCDcQty >= $dDcQty) {

                                                    //insert pvtrans using $dDcQty
                                                    $pvTransInsert = $sql->insert('MMS_PVTrans');
                                                    $pvTransInsert->values(array("PVRegisterId" => $pvId, "PVGroupId" => $PVGroupId,
                                                        "DCRegisterId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcregisterid_' . $j], 'number'),
                                                        "DCTransId" => $iDcTransId,
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "ActualQty" => $dDcQty,
                                                        "BillQty" => $dDcQty,
                                                        "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                                        "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                                        "Amount" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                        "QAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                                        "GrossRate" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                        "GrossAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''))

                                                    ));
                                                    $pvTransStatement = $sql->getSqlStringForSqlObject($pvTransInsert);
                                                    $dbAdapter->query($pvTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    //insert ipdtrans using $dDcQty
                                                    $status = "U";
                                                    $ipdTransInsert = $sql->insert('MMS_IPDTrans');
                                                    $ipdTransInsert->values(array(
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "Qty" => $dDcQty,
                                                        "Status" => $status,
                                                        "DCTransId" => $iDcTransId,
                                                        "PVTransId" => $PVTransId));
                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    //insert ipdprojtrans using $dDcQty
                                                    $status = "U";
                                                    $ipdPInsert = $sql->insert('MMS_IPDProjTrans');
                                                    $ipdPInsert->values(array(
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "Qty" => $dDcQty,
                                                        "Status" => $status,
                                                        "DCProjTransId" => $iDcTransId,
                                                        "PVTransId" => $PVTransId,
                                                        "IPDTransId" => $IPDTransId
                                                    ));
                                                    $ipdPInsertStatement = $sql->getSqlStringForSqlObject($ipdPInsert);
                                                    $dbAdapter->query($ipdPInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    //update dcgrouptrans billqty & balqty
                                                    $dcGTransUpdate = $sql->update();
                                                    $dcGTransUpdate->table('MMS_DCGroupTrans');
                                                    $dcGTransUpdate->set(array(
                                                        "BillQty" => new Expression('BillQty+' . $dDcQty),
                                                        "BalQty" => new Expression('BalQty-' . $dDcQty),
                                                    ));
                                                    $dcGTransUpdate->where(array("DCGroupId" => $DCGroupid));
                                                    $dcGTransStatement = $sql->getSqlStringForSqlObject($dcGTransUpdate);
                                                    $dbAdapter->query($dcGTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                    //update dctrans billqty & balqty
                                                    $dcTransUpdate = $sql->update();
                                                    $dcTransUpdate->table('MMS_DCTrans');
                                                    $dcTransUpdate->set(array(
                                                        "BillQty" => new Expression('BillQty+' . $dDcQty),
                                                        "BalQty" => new Expression('BalQty-' . $dDcQty),
                                                    ));
                                                    $dcTransUpdate->where(array("DCTransId" => $iDcTransId));
                                                    $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                    $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $dCDcQty = $dCDcQty - $dDcQty;

                                                    // dcanaltrans
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCAnalTrans"))
                                                        ->columns(array("DCAnalTransId", "DCTransId", "AnalysisId", "ResourceId", "ItemId", "BalQty"))
                                                        ->where('a.DCTransId=' . $iDcTransId . ' and a.BalQty>0');
                                                    $Statement = $sql->getSqlStringForSqlObject($select);
                                                    $dcanaltran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDAId = 0;
                                                    $iAnaId = 0;
                                                    $iResId = 0;
                                                    $iItemId = 0;
                                                    $dBillQty = 0.0;
                                                    $dCBillQty = 0.0;
                                                    $dCBillQty = $dDcQty;
                                                    foreach ($dcanaltran as $dca) {

                                                        $iDAId = $this->bsf->isNullCheck($dca['DCAnalTransId'], 'number');
                                                        $iAnaId = $this->bsf->isNullCheck($dca['AnalysisId'], 'number');
                                                        $iResId = $this->bsf->isNullCheck($dca['ResourceId'], 'number');
                                                        $iItemId = $this->bsf->isNullCheck($dca['ItemId'], 'number');
                                                        $dBillQty = $this->bsf->isNullCheck($dca['BalQty'], 'number');

                                                        if ($dCBillQty > 0) {

                                                            if ($dCBillQty >= $dBillQty) {

                                                                //Insert PVATrans use $dBillQty
                                                                $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                $ipdTransInsert->values(array(
                                                                    "PVTransId" => $PVTransId,
                                                                    "PVGroupId" => $PVGroupId,
                                                                    "DCAnalTransId" => $iDAId,
                                                                    "AnalysisId" => $iAnaId,
                                                                    "ResourceId" => $iResId,
                                                                    "ItemId" => $iItemId,
                                                                    "BillQty" => $dBillQty));
                                                                $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                //Update DCAnalTrans billqty & balqty
                                                                $dcaTransUpdate = $sql->update();
                                                                $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                $dcaTransUpdate->set(array(
                                                                    "BillQty" => new Expression('BillQty+' . $dBillQty),
                                                                    "BalQty" => new Expression('BalQty-' . $dBillQty),
                                                                ));
                                                                $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $dCBillQty = $dCBillQty - $dBillQty;

                                                            } else {
                                                                //Insert PVATrans use $dCBillQty
                                                                $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                $ipdTransInsert->values(array(
                                                                    "PVTransId" => $PVTransId,
                                                                    "PVGroupId" => $PVGroupId,
                                                                    "DCAnalTransId" => $iDAId,
                                                                    "AnalysisId" => $iAnaId,
                                                                    "ResourceId" => $iResId,
                                                                    "ItemId" => $iItemId,
                                                                    "BillQty" => $dCBillQty));
                                                                $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                //Update DCAnalTrans billqty & balqty
                                                                $dcaTransUpdate = $sql->update();
                                                                $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                $dcaTransUpdate->set(array(
                                                                    "BillQty" => new Expression('BillQty+' . $dCBillQty),
                                                                    "BalQty" => new Expression('BalQty-' . $dCBillQty),
                                                                ));
                                                                $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $dCBillQty = 0;
                                                            }
                                                        }
                                                        if ($dCBillQty == 0) break;
                                                    }
                                                } else {

                                                    //insert pvtrans using $dCDcQty
                                                    $pvTransInsert = $sql->insert('MMS_PVTrans');
                                                    $pvTransInsert->values(array("PVRegisterId" => $pvId, "PVGroupId" => $PVGroupId,
                                                        "DCRegisterId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcregisterid_' . $j], 'number'),
                                                        "DCTransId" => $iDcTransId,
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "ActualQty" => $dCDcQty,
                                                        "BillQty" => $dCDcQty,
                                                        "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                                        "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                                        "Amount" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                        "QAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                                        "GrossRate" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                        "GrossAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''))

                                                    ));
                                                    $pvTransStatement = $sql->getSqlStringForSqlObject($pvTransInsert);
                                                    $dbAdapter->query($pvTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    //insert ipdtrans using $dCDcQty

                                                    $status = "U";
                                                    $ipdTransInsert = $sql->insert('MMS_IPDTrans');
                                                    $ipdTransInsert->values(array(
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "Qty" => $dCDcQty,
                                                        "Status" => $status,
                                                        "DCTransId" => $iDcTransId,
                                                        "PVTransId" => $PVTransId));
                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                                    //insert ipdtrans using $dCDcQty

                                                    $ipdPTransInsert = $sql->insert('MMS_IPDProjTrans');
                                                    $ipdPTransInsert->values(array(
                                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                        "Qty" => $dCDcQty,
                                                        "Status" => $status,
                                                        "DCProjTransId" => $iDcTransId,
                                                        "PVTransId" => $PVTransId,
                                                        "IPDTransId" => $IPDTransId));
                                                    $ipdPTransStatement = $sql->getSqlStringForSqlObject($ipdPTransInsert);
                                                    $dbAdapter->query($ipdPTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    //update dcgrouptrans billqty & balqty
                                                    $dcGTransUpdate = $sql->update();
                                                    $dcGTransUpdate->table('MMS_DCGroupTrans');
                                                    $dcGTransUpdate->set(array(
                                                        "BillQty" => new Expression('BillQty+' . $dCDcQty),
                                                        "BalQty" => new Expression('BalQty-' . $dCDcQty),
                                                    ));
                                                    $dcGTransUpdate->where(array("DCGroupId" => $DCGroupid));
                                                    $dcGTransStatement = $sql->getSqlStringForSqlObject($dcGTransUpdate);
                                                    $dbAdapter->query($dcGTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                    //update dctrans billqty & balqty
                                                    $dcTransUpdate = $sql->update();
                                                    $dcTransUpdate->table('MMS_DCTrans');
                                                    $dcTransUpdate->set(array(
                                                        "BillQty" => new Expression('BillQty+' . $dCDcQty),
                                                        "BalQty" => new Expression('BalQty-' . $dCDcQty),
                                                    ));
                                                    $dcTransUpdate->where(array("DCTransId" => $iDcTransId));
                                                    $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                    $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                    //DCANALATRANS

                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCAnalTrans"))
                                                        ->columns(array("DCAnalTransId", "DCTransId", "AnalysisId", "ResourceId", "ItemId", "BalQty"))
                                                        ->where('a.DCTransId=' . $iDcTransId . ' and a.BalQty>0');
                                                    $Statement = $sql->getSqlStringForSqlObject($select);
                                                    $dcanaltran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDAId = 0;
                                                    $iAnaId = 0;
                                                    $iResId = 0;
                                                    $iItemId = 0;
                                                    $dBillQty = 0.0;
                                                    $dCBillQty = 0.0;
                                                    $dCBillQty = $dCDcQty;
                                                    foreach ($dcanaltran as $dca) {

                                                        $iDAId = $this->bsf->isNullCheck($dca['DCAnalTransId'], 'number');
                                                        $iAnaId = $this->bsf->isNullCheck($dca['AnalysisId'], 'number');
                                                        $iResId = $this->bsf->isNullCheck($dca['ResourceId'], 'number');
                                                        $iItemId = $this->bsf->isNullCheck($dca['ItemId'], 'number');
                                                        $dBillQty = $this->bsf->isNullCheck($dca['BalQty'], 'number');

                                                        if ($dCBillQty > 0) {

                                                            if ($dCBillQty >= $dBillQty) {
                                                                //Insert PVATrans use $dBillQty
                                                                $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                $ipdTransInsert->values(array(
                                                                    "PVTransId" => $PVTransId,
                                                                    "PVGroupId" => $PVGroupId,
                                                                    "DCAnalTransId" => $iDAId,
                                                                    "AnalysisId" => $iAnaId,
                                                                    "ResourceId" => $iResId,
                                                                    "ItemId" => $iItemId,
                                                                    "BillQty" => $dBillQty));
                                                                $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                //Update DCAnalTrans billqty & balqty
                                                                $dcaTransUpdate = $sql->update();
                                                                $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                $dcaTransUpdate->set(array(
                                                                    "BillQty" => new Expression('BillQty+' . $dBillQty),
                                                                    "BalQty" => new Expression('BalQty-' . $dBillQty),
                                                                ));
                                                                $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
//
                                                                $dCBillQty = $dCBillQty - $dBillQty;

                                                            } else {

                                                                //Insert PVATrans use $dCBillQty
                                                                $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                $ipdTransInsert->values(array(
                                                                    "PVTransId" => $PVTransId,
                                                                    "PVGroupId" => $PVGroupId,
                                                                    "DCAnalTransId" => $iDAId,
                                                                    "AnalysisId" => $iAnaId,
                                                                    "ResourceId" => $iResId,
                                                                    "ItemId" => $iItemId,
                                                                    "BillQty" => $dCBillQty));
                                                                $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                //Update DCAnalTrans billqty & balqty
                                                                $dcaTransUpdate = $sql->update();
                                                                $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                $dcaTransUpdate->set(array(
                                                                    "BillQty" => new Expression('BillQty+' . $dCBillQty),
                                                                    "BalQty" => new Expression('BalQty-' . $dCBillQty),
                                                                ));
                                                                $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                $dCBillQty = 0;

                                                            }
                                                        }
                                                        if ($dCBillQty == 0) break;
                                                    } // end of last foreach
                                                    $dCDcQty = 0;
                                                }
                                            }
                                        }
                                    } // end of code

                                }
                                    if($dCDcQty == 0) break;
                                } // end of j
                            }//end of j count

                            //Qualifier Insert
                            $qual = $postParams['QualRowId_' . $i];
                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_MCQualTrans');
                                    $qInsert->values(array("PVRegisterId" => $pvId, "PVGroupId" => $PVGroupId,
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
                            } //end of qualifier


                        } // end of mugesh

                        //update the amount in pvregister
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PVGroupTrans"))
                            ->columns(array('Amount' => new Expression ("sum(a.Amount)")))
                            ->where("a.PVRegisterId=$pvId");
                        $Statement = $sql->getSqlStringForSqlObject($select);
                        $pvregAmount = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $Update = $sql->update();
                        $Update->table('MMS_PVRegister');
                        $Update->set(array(
                            "Amount" => $pvregAmount['Amount']
                        ));
                        $Update->where("PVRegisterId=$pvId");
                        $statement = $sql->getSqlStringForSqlObject($Update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //POP UP QUALIIFER DETAILS
                        $qrow = $postParams['Qrowid'];
                        $type='P';
                        if(count($qrow) > 0) {
                            for ($r = 0; $r < $qrow; $r++) {
                                if($this->bsf->isNullCheck($postParams['qualamount_' . $r], 'number' . '') > 0 ||$this->bsf->isNullCheck($postParams['qualamount_' . $r], 'number' . '') < 0) {

                                    $qInsert = $sql->insert('mms_QualTrans');
                                    $qInsert->values(array(
                                        "RegisterId" => $pvId,
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
                                    $Update->table('mms_MCQualTrans');
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
                        } // END OF POP QUALIFIER


                    } // end of updating the edit details

                    // starting the add mode
                    else {
                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(305, date('Y/m/d', strtotime($PVDate)), 0, 0, $dbAdapter, "I");
                            $voucherno = $voucher['voucherNo'];
                        } else {
                            $voucherno = $PVNo;
                        }

                        if ($CCPV['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(305, date('Y/m/d', strtotime($PVDate)), 0, $CostCentreId, $dbAdapter, "I");
                            $CCPVNo = $voucher['voucherNo'];
                        } else {
                            $CCPVNo = $CCPVNo;
                        }

                        if ($CPV['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(305, date('Y/m/d', strtotime($PVDate)), $CompanyId, 0, $dbAdapter, "I");
                            $CPVNo = $voucher['voucherNo'];
                        } else {
                            $CPVNo = $CPVNo;
                        }

                        //insert pvreg ->conversionentry
                        $registerInsert = $sql->insert('MMS_PVRegister');
                        $registerInsert->values(array("VendorId" => $VendorId, "CostCentreId" => $CostCentreId,
                            "PVNo" => $voucherno, "PVDate" => $PVDate, "BillNo" => $BillNo, "BillDate" => $BillDate,
                            "CCPVNo" => $CCPVNo, "CPVNo" => $CPVNo, "PurchaseTypeId" => $PurTypeId,
                            "PurchaseAccount" => $AccountId, "CurrencyId" => $CurrencyId, "Narration" => $Narration,
                            "Amount" => $Amount, "BillAmount" => $netAmount, "ThruDC" => 'Y'
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $PVRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $pvId = $PVRegisterId;

                        // advance adjustment- addmode
                        $advTotal = $postParams['Arowid'];
                        for ($a = 1; $a <= $advTotal; $a++) {
                            if ($this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') > 0) {
                                $advanceInsert = $sql->insert('mms_advadjustment');
                                $advanceInsert->values(array(
                                    "BillRegisterId" => $PVRegisterId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number' . ''),
                                    "PORegisterId" => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number' . ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number' . '')
                                ));
                                $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updadj = $sql->update();
                                $updadj->table('mms_popaymentterms');
                                $updadj->set(array(
                                    "AdjustAmount" => new Expression('AdjustAmount+' . $this->bsf->isNullCheck($postParams['currentamount_' . $a], 'number') . '')
                                ));
                                $updadj->where(array('PORegisterId' => $this->bsf->isNullCheck($postParams['poregisterid_' . $a], 'number' . ''), 'TermsId' => $this->bsf->isNullCheck($postParams['advtermsid_' . $a], 'number' . '')));
                                $updAdjStatement = $sql->getSqlStringForSqlObject($updadj);
                                $dbAdapter->query($updAdjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //terms and condition
                        $termsTotal = $postParams['trowid'];
                        for ($t = 1; $t <= $termsTotal; $t++) {
                            if ($this->bsf->isNullCheck($postParams['value_' . $t], 'number' . '') > 0 && $this->bsf->isNullCheck($postParams['account_' . $t], 'number' . '') > 0) {
                                $termsInsert = $sql->insert('MMS_PVPaymentTerms');
                                $termsInsert->values(array(
                                    "PVRegisterId" => $PVRegisterId,
                                    "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t], 'number' . ''),
                                    "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number' . ''),
                                    "AccountId" => $this->bsf->isNullCheck($postParams['account_' . $t], 'number' . '')
                                ));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $pvregId = $postParams['rowid'];
                        $dCDcQty = 0.0;
                        for ($i = 1; $i < $pvregId; $i++) {
                            if ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number') > 0 ) {
                                $dnq = ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) - ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . ''));
                                $PVGroupInsert = $sql->insert('MMS_PVGroupTrans');
                                $PVGroupInsert->values(array("PVRegisterId" => $PVRegisterId, "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'), "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                    "ActualQty" => $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . ''),
                                    "BillQty" => $this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . ''),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                    "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . ''),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number' . ''),
                                    "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number' . ''),
                                    "DNQty" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) - ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')),
                                    "DNAmt" => new Expression("$dnq*" . $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                ));
                                $PVGroupStatement = $sql->getSqlStringForSqlObject($PVGroupInsert);
                                $dbAdapter->query($PVGroupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $PVGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                            $pvtransTotal = $postParams['iow_' . $i . '_rowid'];
                            $dDCGQty = $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '');
                            $dCDcQty = $dDCGQty;

                            if ($pvtransTotal > 0) {
                                for ($j = 1; $j <= $pvtransTotal; $j++) {
                                    if($dCDcQty > 0) {

                                        $DCGroupid = $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcgroupid_' . $j], 'number');
                                        $select = $sql->select();
                                        $select->from(array("a" => "MMS_DCTrans"))
                                            ->columns(array("DCTransId", "BalQty"))
                                            ->where('a.DCGroupId=' . $DCGroupid . ' and a.BalQty>0');
                                        $Statement = $sql->getSqlStringForSqlObject($select);
                                        $dctran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                        $dccount = count($dctran);

                                        if ($dccount > 0) {
                                            $iDcTransId = 0;
                                            $dDcQty = 0.0;

                                            foreach ($dctran as $dc) {

                                                $iDcTransId = $this->bsf->isNullCheck($dc['DCTransId'], 'number');
                                                $dDcQty = $this->bsf->isNullCheck($dc['BalQty'], 'number' . '');
                                                if ($dDcQty > 0) {

                                                    if ($dCDcQty >= $dDcQty) {

                                                        //insert pvtrans using $dDcQty
                                                        $pvTransInsert = $sql->insert('MMS_PVTrans');
                                                        $pvTransInsert->values(array("PVRegisterId" => $PVRegisterId, "PVGroupId" => $PVGroupId,
                                                            "DCRegisterId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcregisterid_' . $j], 'number'),
                                                            "DCTransId" => $iDcTransId,
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "ActualQty" => $dDcQty,
                                                            "BillQty" => $dDcQty,
                                                            "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                                            "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                                            "Amount" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                            "QAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                                            "GrossRate" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                            "GrossAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''))

                                                        ));
                                                        $pvTransStatement = $sql->getSqlStringForSqlObject($pvTransInsert);
                                                        $dbAdapter->query($pvTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //insert ipdtrans using $dDcQty
                                                        $status = "U";
                                                        $ipdTransInsert = $sql->insert('MMS_IPDTrans');
                                                        $ipdTransInsert->values(array(
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "Qty" => $dDcQty,
                                                            "Status" => $status,
                                                            "DCTransId" => $iDcTransId,
                                                            "PVTransId" => $PVTransId));
                                                        $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                        $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //insert ipdprojtrans using $dDcQty
                                                        $status = "U";
                                                        $ipdPInsert = $sql->insert('MMS_IPDProjTrans');
                                                        $ipdPInsert->values(array(
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "Qty" => $dDcQty,
                                                            "Status" => $status,
                                                            "DCProjTransId" => $iDcTransId,
                                                            "PVTransId" => $PVTransId,
                                                            "IPDTransId" => $IPDTransId
                                                        ));
                                                        $ipdPInsertStatement = $sql->getSqlStringForSqlObject($ipdPInsert);
                                                        $dbAdapter->query($ipdPInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //update dcgrouptrans billqty & balqty
                                                        $dcGTransUpdate = $sql->update();
                                                        $dcGTransUpdate->table('MMS_DCGroupTrans');
                                                        $dcGTransUpdate->set(array(
                                                            "BillQty" => new Expression('BillQty+' . $dDcQty),
                                                            "BalQty" => new Expression('BalQty-' . $dDcQty),
                                                        ));
                                                        $dcGTransUpdate->where(array("DCGroupId" => $DCGroupid));
                                                        $dcGTransStatement = $sql->getSqlStringForSqlObject($dcGTransUpdate);
                                                        $dbAdapter->query($dcGTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        //update dctrans billqty & balqty
                                                        $dcTransUpdate = $sql->update();
                                                        $dcTransUpdate->table('MMS_DCTrans');
                                                        $dcTransUpdate->set(array(
                                                            "BillQty" => new Expression('BillQty+' . $dDcQty),
                                                            "BalQty" => new Expression('BalQty-' . $dDcQty),
                                                        ));
                                                        $dcTransUpdate->where(array("DCTransId" => $iDcTransId));
                                                        $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                        $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $dCDcQty = $dCDcQty - $dDcQty;

                                                        // dcanaltrans
                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCAnalTrans"))
                                                            ->columns(array("DCAnalTransId", "DCTransId", "AnalysisId", "ResourceId", "ItemId", "BalQty"))
                                                            ->where('a.DCTransId=' . $iDcTransId . ' and a.BalQty>0');
                                                        $Statement = $sql->getSqlStringForSqlObject($select);
                                                        $dcanaltran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        $iDAId = 0;
                                                        $iAnaId = 0;
                                                        $iResId = 0;
                                                        $iItemId = 0;
                                                        $dBillQty = 0.0;
                                                        $dCBillQty = 0.0;
                                                        $dCBillQty = $dDcQty;
                                                        foreach ($dcanaltran as $dca) {

                                                            $iDAId = $this->bsf->isNullCheck($dca['DCAnalTransId'], 'number');
                                                            $iAnaId = $this->bsf->isNullCheck($dca['AnalysisId'], 'number');
                                                            $iResId = $this->bsf->isNullCheck($dca['ResourceId'], 'number');
                                                            $iItemId = $this->bsf->isNullCheck($dca['ItemId'], 'number');
                                                            $dBillQty = $this->bsf->isNullCheck($dca['BalQty'], 'number');

                                                            if ($dCBillQty > 0) {

                                                                if ($dCBillQty >= $dBillQty) {

                                                                    //Insert PVATrans use $dBillQty
                                                                    $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                    $ipdTransInsert->values(array(
                                                                        "PVTransId" => $PVTransId,
                                                                        "PVGroupId" => $PVGroupId,
                                                                        "DCAnalTransId" => $iDAId,
                                                                        "AnalysisId" => $iAnaId,
                                                                        "ResourceId" => $iResId,
                                                                        "ItemId" => $iItemId,
                                                                        "BillQty" => $dBillQty));
                                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                    //Update DCAnalTrans billqty & balqty
                                                                    $dcaTransUpdate = $sql->update();
                                                                    $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                    $dcaTransUpdate->set(array(
                                                                        "BillQty" => new Expression('BillQty+' . $dBillQty),
                                                                        "BalQty" => new Expression('BalQty-' . $dBillQty),
                                                                    ));
                                                                    $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                    $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                    $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $dCBillQty = $dCBillQty - $dBillQty;

                                                                } else {
                                                                    //Insert PVATrans use $dCBillQty
                                                                    $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                    $ipdTransInsert->values(array(
                                                                        "PVTransId" => $PVTransId,
                                                                        "PVGroupId" => $PVGroupId,
                                                                        "DCAnalTransId" => $iDAId,
                                                                        "AnalysisId" => $iAnaId,
                                                                        "ResourceId" => $iResId,
                                                                        "ItemId" => $iItemId,
                                                                        "BillQty" => $dCBillQty));
                                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                    //Update DCAnalTrans billqty & balqty
                                                                    $dcaTransUpdate = $sql->update();
                                                                    $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                    $dcaTransUpdate->set(array(
                                                                        "BillQty" => new Expression('BillQty+' . $dCBillQty),
                                                                        "BalQty" => new Expression('BalQty-' . $dCBillQty),
                                                                    ));
                                                                    $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                    $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                    $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $dCBillQty = 0;
                                                                }
                                                            }
                                                            if ($dCBillQty == 0) break;
                                                        }

                                                    } else {

                                                        //insert pvtrans using $dCDcQty
                                                        $pvTransInsert = $sql->insert('MMS_PVTrans');
                                                        $pvTransInsert->values(array("PVRegisterId" => $PVRegisterId, "PVGroupId" => $PVGroupId,
                                                            "DCRegisterId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_dcregisterid_' . $j], 'number'),
                                                            "DCTransId" => $iDcTransId,
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "ActualQty" => $dCDcQty,
                                                            "BillQty" => $dCDcQty,
                                                            "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . ''),
                                                            "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''),
                                                            "Amount" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                            "QAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . '')),
                                                            "GrossRate" => ($this->bsf->isNullCheck($postParams['bqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['rate_' . $i], 'number' . '')),
                                                            "GrossAmount" => ($this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')) * ($this->bsf->isNullCheck($postParams['qrate_' . $i], 'number' . ''))

                                                        ));
                                                        $pvTransStatement = $sql->getSqlStringForSqlObject($pvTransInsert);
                                                        $dbAdapter->query($pvTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $PVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //insert ipdtrans using $dCDcQty

                                                        $status = "U";
                                                        $ipdTransInsert = $sql->insert('MMS_IPDTrans');
                                                        $ipdTransInsert->values(array(
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "Qty" => $dCDcQty,
                                                            "Status" => $status,
                                                            "DCTransId" => $iDcTransId,
                                                            "PVTransId" => $PVTransId));
                                                        $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                        $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                                        //insert ipdtrans using $dCDcQty

                                                        $ipdPTransInsert = $sql->insert('MMS_IPDProjTrans');
                                                        $ipdPTransInsert->values(array(
                                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                                            "Qty" => $dCDcQty,
                                                            "Status" => $status,
                                                            "DCProjTransId" => $iDcTransId,
                                                            "PVTransId" => $PVTransId,
                                                            "IPDTransId" => $IPDTransId));
                                                        $ipdPTransStatement = $sql->getSqlStringForSqlObject($ipdPTransInsert);
                                                        $dbAdapter->query($ipdPTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //update dcgrouptrans billqty & balqty
                                                        $dcGTransUpdate = $sql->update();
                                                        $dcGTransUpdate->table('MMS_DCGroupTrans');
                                                        $dcGTransUpdate->set(array(
                                                            "BillQty" => new Expression('BillQty+' . $dCDcQty),
                                                            "BalQty" => new Expression('BalQty-' . $dCDcQty),
                                                        ));
                                                        $dcGTransUpdate->where(array("DCGroupId" => $DCGroupid));
                                                        $dcGTransStatement = $sql->getSqlStringForSqlObject($dcGTransUpdate);
                                                        $dbAdapter->query($dcGTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        //update dctrans billqty & balqty
                                                        $dcTransUpdate = $sql->update();
                                                        $dcTransUpdate->table('MMS_DCTrans');
                                                        $dcTransUpdate->set(array(
                                                            "BillQty" => new Expression('BillQty+' . $dCDcQty),
                                                            "BalQty" => new Expression('BalQty-' . $dCDcQty),
                                                        ));
                                                        $dcTransUpdate->where(array("DCTransId" => $iDcTransId));
                                                        $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                        $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        //DCANALATRANS

                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCAnalTrans"))
                                                            ->columns(array("DCAnalTransId", "DCTransId", "AnalysisId", "ResourceId", "ItemId", "BalQty"))
                                                            ->where('a.DCTransId=' . $iDcTransId . ' and a.BalQty>0');
                                                        $Statement = $sql->getSqlStringForSqlObject($select);
                                                        $dcanaltran = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        $iDAId = 0;
                                                        $iAnaId = 0;
                                                        $iResId = 0;
                                                        $iItemId = 0;
                                                        $dBillQty = 0.0;
                                                        $dCBillQty = 0.0;
                                                        $dCBillQty = $dCDcQty;
                                                        foreach ($dcanaltran as $dca) {

                                                            $iDAId = $this->bsf->isNullCheck($dca['DCAnalTransId'], 'number');
                                                            $iAnaId = $this->bsf->isNullCheck($dca['AnalysisId'], 'number');
                                                            $iResId = $this->bsf->isNullCheck($dca['ResourceId'], 'number');
                                                            $iItemId = $this->bsf->isNullCheck($dca['ItemId'], 'number');
                                                            $dBillQty = $this->bsf->isNullCheck($dca['BalQty'], 'number');

                                                            if ($dCBillQty > 0) {

                                                                if ($dCBillQty >= $dBillQty) {
                                                                    //Insert PVATrans use $dBillQty
                                                                    $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                    $ipdTransInsert->values(array(
                                                                        "PVTransId" => $PVTransId,
                                                                        "PVGroupId" => $PVGroupId,
                                                                        "DCAnalTransId" => $iDAId,
                                                                        "AnalysisId" => $iAnaId,
                                                                        "ResourceId" => $iResId,
                                                                        "ItemId" => $iItemId,
                                                                        "BillQty" => $dBillQty));
                                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                    //Update DCAnalTrans billqty & balqty
                                                                    $dcaTransUpdate = $sql->update();
                                                                    $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                    $dcaTransUpdate->set(array(
                                                                        "BillQty" => new Expression('BillQty+' . $dBillQty),
                                                                        "BalQty" => new Expression('BalQty-' . $dBillQty),
                                                                    ));
                                                                    $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                    $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                    $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
//
                                                                    $dCBillQty = $dCBillQty - $dBillQty;

                                                                } else {

                                                                    //Insert PVATrans use $dCBillQty
                                                                    $ipdTransInsert = $sql->insert('MMS_PVATrans');
                                                                    $ipdTransInsert->values(array(
                                                                        "PVTransId" => $PVTransId,
                                                                        "PVGroupId" => $PVGroupId,
                                                                        "DCAnalTransId" => $iDAId,
                                                                        "AnalysisId" => $iAnaId,
                                                                        "ResourceId" => $iResId,
                                                                        "ItemId" => $iItemId,
                                                                        "BillQty" => $dCBillQty));
                                                                    $ipdTransStatement = $sql->getSqlStringForSqlObject($ipdTransInsert);
                                                                    $dbAdapter->query($ipdTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $PVATransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                                    //Update DCAnalTrans billqty & balqty
                                                                    $dcaTransUpdate = $sql->update();
                                                                    $dcaTransUpdate->table('MMS_DCAnalTrans');
                                                                    $dcaTransUpdate->set(array(
                                                                        "BillQty" => new Expression('BillQty+' . $dCBillQty),
                                                                        "BalQty" => new Expression('BalQty-' . $dCBillQty),
                                                                    ));
                                                                    $dcaTransUpdate->where(array("DCAnalTransId" => $iDAId));
                                                                    $dcaTransStatement = $sql->getSqlStringForSqlObject($dcaTransUpdate);
                                                                    $dbAdapter->query($dcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    $dCBillQty = 0;

                                                                }
                                                            }
                                                            if ($dCBillQty == 0) break;
                                                        } // end of last foreach
                                                        $dCDcQty = 0;
                                                    }
                                                }
                                            }
                                        } // end of code
                                        if ($dCDcQty == 0) break;
                                    }
                                } // end of j
                            }//end of j count

                            //Qualifier Insert
                            $qual = $postParams['QualRowId_' . $i];
                            for ($q = 1; $q <= $qual; $q++) {
                                if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                    $qInsert = $sql->insert('mms_MCQualTrans');
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
                            } //end of qualifier

                        } //end of mugesh

                        //update the amount in pvregister
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PVGroupTrans"))
                            ->columns(array('Amount' => new Expression ("sum(a.Amount)")))
                            ->where("a.PVRegisterId=$PVRegisterId");
                        $Statement = $sql->getSqlStringForSqlObject($select);
                        $pvregAmount = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $Update = $sql->update();
                        $Update->table('MMS_PVRegister');
                        $Update->set(array(
                            "Amount" => $pvregAmount['Amount']
                        ));
                        $Update->where("PVRegisterId=$PVRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($Update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //POP UP QUALIIFER DETAILS
                        $qrow = $postParams['Qrowid'];
                        $type='P';
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
                                    $Update->table('mms_MCQualTrans');
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
                        }  // END OF POP QUALIFIER
                    } //end of insert details

                    $vType = CommonHelper::GetVoucherType(305,$dbAdapter);
                    if ($vType == "  " || $vType == "GE"){
                        $sPVNo= $voucherno;
                    } else if ($vType == "CC"){
                        $sPVNo= $CCPVNo;
                    } else if ($vType == "CO") {
                        $sPVNo= $CPVNo;
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Bill',$pvId,$CostCentreId,$CompanyId, 'MMS',$sPVNo,$this->auth->getIdentity()->UserId,$Amount,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'minconversion', 'action' => 'conversionregister'));

                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends

                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

                return $this->_view;
            }
            //end of the conversionsave
        }
    }

    public function conversionregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Minconversion","action" => "conversion"));
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
            $resp = array();
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['mode'] == 'first') {

                    $regSelect = $sql->select();
                    $regSelect->from(array("a" => "mms_PVRegister"))
                        ->columns(array(new Expression("a.PVRegisterId,a.PVNo,CAST(a.BillAmount As Decimal(18,2)) As BillAmount,
                         c.CostCentreName as CostCentre,a.BillNo as BillNo,Convert(Varchar(10),a.BillDate,103) As BillDate,
                         CCPVNo As CCPVNo,CPVNo As CPVNo,Convert(Varchar(10),a.PVDate,103) As PVDate,
                         Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b" => "Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
                        ->join(array("c" => "WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array(), $regSelect::JOIN_INNER)
                        ->order("a.CreatedDate Desc")
                        ->where(array("a.DeleteFlag = 0 AND a.ThruDC='Y'"));
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

    public function detailedconversionAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "minconversion","action" => "detailedconversion"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        //getting row id from conversion register
        $id = $this->bsf->isNullCheck($this->params()->fromRoute('rid'),'number');

        $request = $this->getRequest();
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $id = $this->bsf->isNullCheck($postParams['PVRegisterId'], 'number');
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');

//                if ($postParams['mode'] == 'final') {
//                    $select = $sql->select();
//                    $select->from(array("a"=>"mms_pvRegister"));
//                    $select->columns(array(new expression("a.PVRegisterId,Convert(Varchar(10),a.PVDate,103) As PVDate,
//                    CAST(c.BillQty As Decimal(18,6)) As BillQty,
//                    CAST(c.ActualQty As Decimal(18,6)) As ActualQty,c.QRate,c.QAmount")))
//                        ->join(array("c"=>'mms_pvGroupTrans'), "c.PVRegisterId=a.PVRegisterId", array(), $select::JOIN_LEFT)
//                        ->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitName"), $select::JOIN_LEFT)
//                        ->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array("ResourceName","Code"), $select::JOIN_LEFT)
//                        ->join(array("f"=>"mms_pvtrans"),"c.PVRegisterId=f.PVRegisterId And c.PVGroupId=f.PVGroupId",array("ResourceId","ItemId","UnitId","PVTransId"),$select::JOIN_INNER);
//                    $select->where(array("a.PVRegisterId"=>$id));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resp['resource'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                    $select = $sql->select();
//                    $select->from(array("a"=>"MMS_IPDTrans"));
//                    $select -> columns(array(
//                        'PVTransId' => new Expression('a.PVTransId'),
//                        'IPDTransId' => new Expression('a.IPDTransId'),
//                        'ResourceId' => new Expression('b.ResourceId'),
//                        'ItemId' => new Expression('b.ItemId'),
//                        'MinNo' => new Expression('d.DCNo'),
//                        'MinDate' => new Expression('Convert(Varchar(10),D.DCDate,103)'),
//                        'SiteMINNo' => new Expression('d.SiteDCNo'),
//                        'SiteMINDate' => new Expression('Convert(Varchar(10),D.SiteDCDate,103)'),
//                        'BalQty' => new Expression('CAST(B.BalQty As Decimal(18,6))'),
//                        'Qty' => new Expression('CAST(A.Qty As Decimal(18,6))')))
//                        ->join(array("b"=>'mms_DCTrans'), "a.DCTransId=b.DCTransId", array(), $select::JOIN_INNER)
//                        ->join(array("c"=>'MMS_PVTrans'), "c.PVTransId=a.PVTransId", array(), $select::JOIN_INNER)
//                        ->join(array("d"=>'MMS_DCRegister'), "b.DCRegisterId=d.DCRegisterId", array(), $select::JOIN_INNER);
//                    $select->where(array("c.PVRegisterId = $id and a.Status= 'U'"));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resp['request']  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                }

                switch($Type) {
                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $pvId = $this->bsf->isNullCheck($this->params()->fromPost('pvId'), 'number');

                        $select = $sql->select();
                        $select->from(array("c" => "MMS_MCQualTrans"))
                            ->columns(array('ResourceId' => new Expression('c.ResourceId'), 'ItemId' => new Expression('c.ItemId'), 'QualifierId' => new Expression('c.QualifierId'),
                                'YesNo' => new Expression('Case When c.YesNo=1 Then 1 Else 0 End'), 'Expression' => new Expression('c.Expression'),
                                'ExpPer' => new Expression('c.ExpPer'), 'TaxablePer' => new Expression('c.TaxablePer'), 'TaxPer' => new Expression('c.TaxPer'),
                                'Sign' => new Expression('c.Sign'), 'SurCharge' => new Expression('c.SurCharge'), 'EDCess' => new Expression('c.EDCess'),
                                'HEDCess' => new Expression('c.HEDCess'), 'NetPer' => new Expression('c.NetPer'), 'BaseAmount' => new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '),
                                'ExpressionAmt' => new Expression('CAST(c.ExpressionAmt As Decimal(18,3)) '), 'TaxableAmt' => new Expression('CAST(c.TaxableAmt As Decimal(18,3)) '),
                                'TaxAmt' => new Expression('CAST(c.TaxAmt As Decimal(18,3)) '),
                                'SurChargeAmt' => new Expression('CAST(c.SurChargeAmt As Decimal(18,3)) '), 'EDCessAmt' => new Expression('c.EDCessAmt'),
                                'HEDCessAmt' => new Expression('c.HEDCessAmt'), 'NetAmt' => new Expression('CAST(c.NetAmt As Decimal(18,3)) '),
                                'QualifierName' => new Expression('b.QualifierName'),
                                'QualifierTypeId' => new Expression('b.QualifierTypeId'),
                                'RefId' => new Expression('b.RefNo'), 'SortId' => new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);
                        $select->where(array('a.QualType' => 'M', 'c.PVRegisterId' => $pvId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
                        $selMain = $sql->select()->from(array('result' => $select));
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
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            // edit mode- resource row
            $select = $sql->select();
            $select->from(array("a" => "MMS_PVGroupTrans"))
                ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),a.UnitId,
					case when a.ItemId>0 then c.ItemCode+ '' +c.BrandName Else b.Code+' - '+b.ResourceName End as [Desc],
					CAST(a.BillQty As Decimal(18,3)) As BillQty,
					CAST(a.ActualQty As Decimal(18,3)) As ActualQty,
					CAST(a.Rate As Decimal(18,2)) As Rate,CAST(a.QRate As Decimal(18,2)) As QRate,CAST(a.Amount As Decimal(18,2)) As Amount,CAST(a.QAmount As Decimal(18,2)) As QAmount,CAST(e.BillAmount As Decimal(18,2)) As BillAmount")))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                ->join(array('e' => 'MMS_PVRegister'), 'a.PVRegisterId=e.PVRegisterId', array(), $select::JOIN_INNER)
                ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId')
                ->where('a.PVRegisterId=' . $id . '');
            $statement = $sql->getSqlStringForSqlObject($select);
            $dcgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arr_requestResources = $dcgroup;

            //edit-mode- resourceitem - min row
            $select = $sql->select();
            $select->from(array("a" => "MMS_PVRegister"))
                ->columns(array(new Expression("e.DCRegisterId,f.DCGroupId,b.ResourceId,b.ItemId,g.DCNo as MINNo,
								CONVERT(varchar(10),g.DCDate,105) As MINDate, g.SiteDCNo as SiteMINNo,
								CONVERT(varchar(10),g.SiteDCDate,105) As SiteMINDate,
								CAST(SUM(ISNULL(f.BalQty,0)) As Decimal(18,3)) As BalQty,
								CAST(SUM(ISNULL(d.Qty,0)) As Decimal(18,3)) As Qty,
								CAST(SUM(ISNULL(d.Qty,0)) As Decimal(18,3)) As HiddenQty"
                )))
                ->join(array('b' => 'MMS_PVGroupTrans'), 'a.PVRegisterId=b.PVRegisterId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'MMS_PVTrans'), 'b.PVGroupId=c.PVGroupId', array(), $select::JOIN_LEFT)
                ->join(array('d' => 'MMS_IPDTrans'), 'c.PVTransId=d.PVTransId', array(), $select::JOIN_INNER)
                ->join(array('e' => 'MMS_DCTrans'), 'd.DCTransId=e.DCTransId', array(), $select::JOIN_INNER)
                ->join(array('f' => 'MMS_DCGroupTrans'), 'e.DCGroupId=f.DCGroupId', array(), $select::JOIN_INNER)
                ->join(array('g' => 'MMS_DCRegister'), 'f.DCRegisterId=g.DCRegisterId', array(), $select::JOIN_INNER)
                ->where("a.PVRegisterId=$id");
            $select->group(array("e.DCRegisterId","F.DCGroupId",
                "b.ResourceId","b.ItemId","g.DCNo","g.DCDate","g.SiteDCNo","g.SiteDCDate"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                ->where("PVRegisterId=$id");
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
            $select->from(array("a" => "Proj_QualifierTrans"))
                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign',
                    'SurCharge','EDCess','HEDCess','NetPer',
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
            $select->from(array("a" => "MMS_MCQualTrans"))
                ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression',
                    'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge',
                    'EDCess', 'HEDCess', 'NetPer',
                    'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                    'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                    'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'));
            $select->where(array('a.PVRegisterId'=>$id));
            $select->order('a.SortId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->qualAccList = $qualAccList;

            //Qualifier Account deteails - edit
            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array('AccountId','AccountName','TypeId'));
            $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //advance adjustment

            $select = $sql->select();
            $select->from(array("A" => "MMS_PORegister"))
                ->columns(array(
                    'PORegisterId' => new Expression('A.PORegisterId'),
                    'PONo' => new Expression('A.PONo'),
                    'TermsId' => new Expression('B.TermsId'),
                    'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                    'Title' => new Expression('TM.Title'),
                    'Amount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                    'PaidAmount' => new Expression("CAST(B.Value As Decimal(18,2))"),
                    'Balance' => new Expression("CAST(B.Value - ISNULL((Select SUM(Amount) From MMS_AdvAdjustment
						  Where PORegisterId=A.PORegisterId And TermsId=B.TermsId),0) As Decimal(18,2))"),
                    'DeductionBalance' => new Expression("Cast(B.AdjustAmount As Decimal(18,2))"),
                    'CurrentAmount' => new Expression("CAST(C.Amount As Decimal(18,2))"),
                    'HiddenAmount' => new Expression("CAST(C.Amount As Decimal(18,2))")
                ))
                ->join(array("B"=>'MMS_POPaymentTerms'),'A.PORegisterId=B.PORegisterId',array(),$select::JOIN_INNER)
                ->join(array("TM"=>'WF_TermsMaster'),'B.TermsId=TM.TermsId',array(),$select::JOIN_INNER)
                ->join(array("C"=>'MMS_AdvAdjustment'),'A.PORegisterId=C.PORegisterId and TM.TermsId=c.TermsId',array(),$select::JOIN_INNER)
                ->join(array("D"=>'MMS_PVRegister'),'C.BillRegisterId=D.PVRegisterId',array(),$select::JOIN_INNER)
                ->where("TM.Title IN ('Advance','Against Delivery','Against Test Certificate') AND TM.TermType='S' AND  D.PVRegisterId=$id ");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_advance = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            ///
            $regDetails = $sql->select();
            $regDetails->from(array("a" => "MMS_PVRegister"))
                ->columns(array(
                    'PVNo' => new Expression('a.PVNo'),
                    'PVDate' => new Expression('Convert(Varchar(10),a.PVDate,103)'),
                    'BillDate' => new Expression('Convert(Varchar(10),a.BillDate,103)'),
                    'CostCentreName' => new Expression('b.CostCentreName'),
                    'SupplierName' => new Expression('c.VendorName'),
                    'BillNo' => new Expression('a.BillNo'),
                    'CCPVNo' => new Expression('a.CCPVNo'),
                    'CPVNo' => new Expression('a.CPVNo'),
                    'PurchaseTypeName' => new Expression('e.PurchaseTypeName'),
                    'PurchaseTypeId' => new Expression('a.PurchaseTypeId'),
                    'CurrencyName' => new Expression('d.CurrencyName'),
                    'Approve' => new Expression("Case when a.Approve='Y' then 'Yes' Else 'No' End"),
                    'GridType' => new Expression('a.GridType'),
                    'Narration' => new Expression('a.Narration')
                ))
                ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $regDetails::JOIN_INNER)
                ->join(array("c" => "Vendor_Master"), "a.VendorId=c.VendorId", array(), $regDetails::JOIN_INNER)
                ->join(array("d" => "WF_CurrencyMaster"), "a.CurrencyId=d.CurrencyId", array(), $regDetails::JOIN_INNER)
                ->join(array("e" => "MMS_PurchaseType"), "a.PurchaseTypeId=e.PurchaseTypeId", array(), $regDetails::JOIN_INNER)
                ->where(array('a.PVRegisterId' => $id));
            $regStatement = $sql->getSqlStringForSqlObject($regDetails);
            $regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $this->_view->PVNo = $regResult['PVNo'];
            $this->_view->PVDate = $regResult['PVDate'];
            $this->_view->BillDate = $regResult['BillDate'];
            $this->_view->CostCentreName = $regResult['CostCentreName'];
            $this->_view->SupplierName = $regResult['SupplierName'];
            $this->_view->BillNo = $regResult['BillNo'];
            $this->_view->CCPVNo = $regResult['CCPVNo'];
            $this->_view->CPVNo = $regResult['CPVNo'];
            $this->_view->PurchaseTypeName = $regResult['PurchaseTypeName'];
            $PurchaseTypeId = $regResult['PurchaseTypeId'];
            $this->_view->CurrencyName = $regResult['CurrencyName'];
            $this->_view->gridtype = $regResult['GridType'];
            $this->_view->Approve = $regResult['Approve'];
            $this->_view->Narration = $regResult['Narration'];
            $this->_view->PVRegisterId = $id;
            $this->_view->PVRegisterId = $this->params()->fromRoute('rid');


            $select = $sql->select();
            $select->from(array("a" => "MMS_PurchaseType"))
                ->columns(array(new Expression("b.AccountId as AccountId ,b.AccountName as AccountName")))
                ->join(array('b' => 'FA_AccountMaster'), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                ->where(array('a.PurchaseTypeId' => $PurchaseTypeId));
            $selectStatement = $sql->getSqlStringForSqlObject($select);
            $regResult1 = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->PurchaseAccount = $regResult1['AccountName'];


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function conversionDeleteAction(){
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
        $pvId = $this->params()->fromRoute('rid');

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
            // over all delete

            $select = $sql->select();
            $select->from(array("a" => "MMS_PVTrans"))
                ->columns(array("DCTransId", "ActualQty"))
                ->where(array("a.PVRegisterId" => $pvId));
            $statementPrev = $sql->getSqlStringForSqlObject($select);
            $poData = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($poData as $arrpoData) { // min QTY

                $updDcTrans = $sql->update();
                $updDcTrans->table('MMS_DCTrans');
                $updDcTrans->set(array(

                    'BillQty' => new Expression('BillQty-' . $arrpoData['ActualQty'] . ''),
                    'BalQty' => new Expression('BalQty+' . $arrpoData['ActualQty'] . '')

                ));
                $updDcTrans->where(array('DCTransId' => $arrpoData['DCTransId']));
                $updDcTransStatement = $sql->getSqlStringForSqlObject($updDcTrans);
                $dbAdapter->query($updDcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $select = $sql->select();
            $select->from(array("a" => "MMS_PVTrans"))
                ->columns(array(new Expression("SUM(a.ActualQty) as ActualQty")))
                ->join(array("b"=>"MMS_DCTrans"), "a.DCTransId=b.DCTransId", array('DCGroupId'), $select::JOIN_INNER)
                ->where(array("a.PVRegisterId" => $pvId));
            $select->group(new Expression("b.DCGroupId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $prevGroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($prevGroup as $arrprevGroup) {

                $updDcgTrans = $sql->update();
                $updDcgTrans->table('MMS_DCGroupTrans');
                $updDcgTrans->set(array(

                    'BillQty' => new Expression('BillQty-' . $arrprevGroup['ActualQty'] . ''),
                    'BalQty' => new Expression('BalQty+' . $arrprevGroup['ActualQty'] . '')

                ));
                $updDcgTrans->where(array('DCGroupId' => $arrprevGroup['DCGroupId']));
                $updDcgTransStatement = $sql->getSqlStringForSqlObject($updDcgTrans);
                $dbAdapter->query($updDcgTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $select = $sql->select();
            $select->from(array("a" => "MMS_PVATrans"))
                ->columns(array("DCAnalTransId","BillQty"))
                ->join(array("b"=>"MMS_DCAnalTrans"), "a.DCAnalTransId=b.DCAnalTransId", array(), $select::JOIN_INNER)
                ->join(array("c"=>"MMS_PVTrans"), "a.PVTransId=c.PVTransId", array(), $select::JOIN_INNER)
                ->where(array("PVRegisterId" => $pvId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $prevAnal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($prevAnal as $arrprevAnal) {

                $updcaTrans = $sql->update();
                $updcaTrans->table('MMS_DCAnalTrans');
                $updcaTrans->set(array(

                    'BillQty' => new Expression('BillQty-' . $arrprevAnal['BillQty'] . ''),
                    'BalQty' => new Expression('BalQty+' . $arrprevAnal['BillQty'] . '')

                ));
                $updcaTrans->where(array('DCAnalTransId' => $arrprevAnal['DCAnalTransId']));
                $updcaTransStatement = $sql->getSqlStringForSqlObject($updcaTrans);
                $dbAdapter->query($updcaTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $seladv = $sql->select();
            $seladv->from(array("a" => "mms_advadjustment"))
                ->columns(array(
                    "PORegisterId" => new Expression("a.PORegisterId"),
                    "TermsId" => new Expression("a.TermsId"),
                    "Amount" => new Expression("a.Amount")
                ))
                ->where(array("a.BillRegisterId" => $pvId ));
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


            //delete the data for edit save
            //MCQualTrans
            $delPVQualTrans = $sql->delete();
            $delPVQualTrans->from('MMS_MCQualTrans')
                ->where(array("PVRegisterId" => $pvId));
            $PVQualStatement = $sql->getSqlStringForSqlObject($delPVQualTrans);
            $dbAdapter->query($PVQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delQualTrans = $sql->delete();
            $delQualTrans->from('MMS_QualTrans')
                ->where(array("RegisterId" => $pvId));
            $QualStatement = $sql->getSqlStringForSqlObject($delQualTrans);
            $dbAdapter->query($QualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //SUBQUERY-1
            $subPVA = $sql->select();
            $subPVA->from("MMS_PVTrans")
                ->columns(array("PVTransId"))
                ->where(array("PVRegisterId" => $pvId));

            $del = $sql->delete();
            $del->from('MMS_PVATrans')
                ->where->expression('PVTransId IN ?', array($subPVA));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //SUBQUERY-2
            $subIPDT = $sql->select();
            $subIPDT->from("MMS_PVTrans")
                ->columns(array("PVTransId"))
                ->where(array("PVRegisterId" => $pvId));

            $del = $sql->delete();
            $del->from("MMS_IPDProjTrans")
                ->where(array("Status"=>'U'));
            $del->where->expression('PVTransId IN ?',array($subIPDT));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //SUBQUERY-3
            $subIPD = $sql->select();
            $subIPD->from("MMS_PVTrans")
                ->columns(array("PVTransId"))
                ->where(array("PVRegisterId" => $pvId));

            $del = $sql->delete();
            $del->from('MMS_IPDTrans')
                ->where(array("Status"=> 'U'));
            $del->where->expression('PVTransId IN ?', array($subIPD));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //DELETE QUERY
            $del = $sql->delete();
            $del->from('MMS_PVGroupTrans')
                ->where(array("PVRegisterId" => $pvId));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $del = $sql->delete();
            $del->from('MMS_PVTrans')
                ->where(array("PVRegisterId" => $pvId));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delPVPayTrans = $sql -> delete();
            $delPVPayTrans -> from ('MMS_PVPaymentTerms')
                -> where (array("PVRegisterId" => $pvId));
            $PVPayStatement = $sql->getSqlStringForSqlObject($delPVPayTrans);
            $dbAdapter->query($PVPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $deladvadjustment = $sql -> delete();
            $deladvadjustment -> from ('mms_advadjustment')
                -> where (array("BillRegisterId" => $pvId));
            $advadjustmentStatement = $sql->getSqlStringForSqlObject($deladvadjustment);
            $dbAdapter->query($advadjustmentStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //update the deleted row
            $del= $sql ->update();
            $del -> table('MMS_PVRegister')
                -> set(array('DeleteFlag'=>1))
                -> where(array("PVRegisterId" => $pvId));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->redirect()->toRoute('mms/default', array('controller' => 'minconversion','action' => 'conversionregister'));
            return $this->_view;
        }
    }
    public function minconversionReportAction(){
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
            $this->redirect()->toRoute("minconversion/conversionregister", array("controller" => "minconversion","action" => "conversionregister"));
        }

        $dir = 'public/minconversion/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/minconversion/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $RegId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($RegId == 0)

            $this->redirect()->toRoute("minconversion/conversionregister", array("controller" => "minconversion","action" => "conversionregister"));

        if (!file_exists($filePath)) {
            $filePath = 'public/minconversion/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/minconversion/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;

        //Template
        $regSelect = $sql->select();
        $regSelect->from(array("a" => "mms_PVRegister"))
            ->columns(array(new Expression("a.PVRegisterId,a.PVNo,a.BillAmount,
			 c.CostCentreName as CostCentre,a.BillNo as BillNo,Convert(Varchar(10),a.BillDate,103) As BillDate,
			 CCPVNo As CCPVNo,CPVNo As CPVNo,Convert(Varchar(10),a.PVDate,103) As PVDate,a.Narration as Purpose,
			 Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
            ->join(array("b" => "Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
            ->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $regSelect::JOIN_INNER)
            ->join(array("d"=>"WF_CostCentre"), "c.CostCentreId=d.CostCentreId", array("Address"), $regSelect::JOIN_LEFT)
            ->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $regSelect::JOIN_LEFT)
            ->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $regSelect::JOIN_LEFT)
            ->join(array("g"=>"WF_CountryMaster"), "d.CountryId=g.CountryId", array("CountryName"), $regSelect::JOIN_LEFT)
            ->order("a.CreatedDate Desc")
            ->where(array("a.DeleteFlag = 0 AND a.ThruDC='Y' and a.PVRegisterId=$RegId"));
        $regStatement = $sql->getSqlStringForSqlObject($regSelect);
        $this->_view->reqregister = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //GRID
        $select = $sql->select();
        $select->from(array("a"=>"mms_pvRegister"));
        $select->columns(array(new expression("(ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId asc)) as SNo,
        a.BillAmount,a.PVRegisterId,Convert(Varchar(10),a.PVDate,103) As PVDate,a.Amount,
        case when f.ItemId>0 then s.ItemCode  Else g.Code  End as Code,
        case when f.ItemId>0 then s.BrandName Else g.ResourceName End as ResourceName,
		CAST(c.BillQty As Decimal(18,6)) As BillQty,
		CAST(c.ActualQty As Decimal(18,6)) As ActualQty,c.QRate,c.Rate,c.Amount,c.QAmount,
		(Select Count(PVGroupId) From MMS_MCQualTrans Where pvregisterId = a.pvregisterId) as QCount")))
            ->join(array("c"=>'mms_pvGroupTrans'), "c.PVRegisterId=a.PVRegisterId", array(), $select::JOIN_LEFT)
            ->join(array("e"=>"Proj_UOM"), "e.UnitId=c.UnitId", array("UnitName"), $select::JOIN_LEFT)
            ->join(array("g"=>"Proj_Resource"), "g.ResourceId=c.ResourceId", array(), $select::JOIN_LEFT)
            ->join(array("f"=>"mms_pvtrans"),"c.PVRegisterId=f.PVRegisterId And c.PVGroupId=f.PVGroupId",array("ResourceId","ItemId","UnitId","PVTransId","PVGroupId"),$select::JOIN_INNER)
            ->join(array("s" => "MMS_Brand"), "f.ResourceId=s.ResourceId and f.ItemId=s.BrandId", array(), $select::JOIN_LEFT);
        $select->where(array("a.PVRegisterId"=>$RegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from(array("c" => "MMS_MCQualTrans"))
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

        $select = $sql->select();
        $select->from(array("a" => "Proj_QualifierTrans"))
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
            ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign',
                'SurCharge','EDCess','HEDCess','NetPer',
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

        $select = $sql->select();
        $select->from(array("a" => "MMS_MCQualTrans"))
            ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression',
                'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge',
                'EDCess', 'HEDCess', 'NetPer',
                'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                'EDCessAmt', 'HEDCessAmt', 'NetAmt','AccountId'));
        $select->where(array('a.PVRegisterId'=>$RegId));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $qualAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->qualAccList = $qualAccList;

        //Qualifier Account deteails - edit
        $select = $sql->select();
        $select->from(array("a" => "FA_AccountMaster"))
            ->columns(array('AccountId','AccountName','TypeId'));
        $select->where(array("LastLevel='Y' AND TypeId IN (12,14,22,30,32)"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->Qual_Acc= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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