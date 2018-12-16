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

class IssueController extends AbstractActionController
{
    public function __construct()	{
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function entryAction(){
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
                $postData=$request->getPost();

                $resourceId = array_filter(explode(",", $postData['rid']));

                if($postData['mode']=='pickList'){

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array("Code", "ResourceId", "ResourceName"), array("UnitName", "UnitId"))
                        //->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName", "ResourceGroupId"), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT);
                    $select->where(array("a.ResourceId"=>$resourceId));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result['results']= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $wbsSelect = $sql->select();
                    $wbsSelect->from(array('a'=>'Proj_WBSMaster'))
                        ->columns(array("WbsId"=>"WBSId", "WbsName"=>"WBSName"))
                        ->join(array('b' => 'Proj_WBSMaster'), 'a.ParentID=b.WBSId', array("PLevel1"=>"WBSName"), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_WBSMaster'), 'b.ParentID=c.WBSId', array("PLevel2"=>"WBSName"), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSMaster'), 'c.ParentID=d.WBSId', array("PLevel3"=>"WBSName"), $select:: JOIN_LEFT)
                        ->where(array("a.LastLevel"=>"1"));

                    $wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
                    $result['wbsResults'] = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                if($postData['mode']=='resourcewbs'){
                    $select = $sql->select();
                    $select->from(array("a" => "Proj_WBSMaster"))
                        ->columns(array('WBSName','WBSId','ParentText'))
                        ->join(array('c' => "Proj_ProjectResource"), 'a.ProjectId=c.ProjectId',array(), $select::JOIN_LEFT)
                        ->where(array('c.ResourceId'=>$postData['proj']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                if($postData['mode']=='firstStep'){
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,0 As Include ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->where( "c.ProjectId =".$postData['proj_name'] );
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result =  $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                //$result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams=$request->getPost();
                //print_r($postParams); die;
                $issue_date = date('Y-M-d',strtotime($postParams['issue_date']));
                $insert  = $sql->insert('MMS_IssueRegister');
                $newData = array(
                    'CostCentreId'  =>$this->bsf->isNullCheck($postParams['proj_name'],'number'),
                    'ContractorId'   => $this->bsf->isNullCheck($postParams['contractor'],'number'),
                    'IssueNo'  => $this->bsf->isNullCheck($postParams['issue_no'],'string'),
                    'IssueDate' =>$issue_date,
                    'IssueType'  => $this->bsf->isNullCheck($postParams['issue_type'],'number'),
                    'CreatedDate'=>date('m-d-Y H:i:s')
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $resId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $resource_id = explode(",", $postParams['resourceId']);
                foreach($resource_id as $i){
                    $insert  = $sql->insert('MMS_IssueTrans');
                    $newData = array(
                        'IssueRegisterId'=>$resId,
                        'ResourceId'=>$i,
                        'IssueQty'=>$this->bsf->isNullCheck($postParams['current_quantity_'.$i],'number'),
                        'IssueRate'=>$this->bsf->isNullCheck($postParams['rate_'.$i],'number'),
                        'UnitId'=>$postParams['unitId_'.$i]
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $connection->commit();
                $this->redirect()->toRoute('mms/register', array('controller' => 'issue', 'action' => 'register'));

            }
            else{
                //to select project
                $projSelect = $sql->select();
                $projSelect->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreId", "CostCentreName"));
                $projStatement = $sql->getSqlStringForSqlObject($projSelect);
                $this->_view->resourceproj = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_Resource"))
                    ->columns(array('*'));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcename = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $wbsSelect = $sql->select();
                $wbsSelect->from("Proj_WBSMaster")
                    ->columns(array("WbsId"=>"WBSId", "WbsName"=>"WBSName"))
                    ->where(array("LastLevel"=>"1"));

                $wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
                $this->_view->wbsResult = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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

    public function registerAction(){
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
            else{
                $select = $sql->select();
                $select->from(array("a" => "MMS_IssueRegister"))
                    ->columns(array('*',
                        'contractor'=>new Expression("CASE
						When (a.ContractorId=1)  Then 'Contractor1'
						When (a.ContractorId=2) Then 'Contractor2' Else 'None' End "),
                        'Type'=>new Expression("CASE
						When (a.IssueType=1) Then 'Internal'
						When (a.IssueType=2 ) Then 'Contractor' Else 'None' End ")))
                    ->join(array('b' => "Proj_ProjectMaster"), 'a.CostCentreId=b.ProjectId',array("CostCentre"=>"ProjectName"), $select::JOIN_LEFT)
                    ->where(array('a.DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->issuereg= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    public function entryEditAction(){
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
        $id = $this->params()->fromRoute('IssueRegisterId');

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
            else{
                $select = $sql->select();
                $select->from(array("a" => "MMS_IssueRegister"))
                    ->columns(array('*',
                        'contractor'=>new Expression("CASE
						When (a.ContractorId=1)  Then 'Contractor1'
						When (a.ContractorId=2) Then 'Contractor2' Else 'None' End "),
                        'Type'=>new Expression("CASE
						When (a.IssueType=1) Then 'Internal'
						When (a.IssueType=2 ) Then 'Contractor' Else 'None' End ")))
                    ->join(array('b' => "Proj_ProjectMaster"), 'a.CostCentreId=b.ProjectId',array("CostCentre"=>"ProjectName"), $select::JOIN_LEFT)
                    ->where(array('a.IssueRegisterId' => $id));
                $statement = $sql->getSqlStringForSqlObject($select);
                $issue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->issuereg =$issue;
                $projname=$issue['CostCentreId'];

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array("ResourceId", "Code", "ResourceName"))
                    ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
                    ->join(array('b' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array("ProjectName"), $select::JOIN_LEFT)
                    ->join(array('d' =>"MMS_IssueTrans"), new Expression('a.ResourceId=d.ResourceId and d.IssueRegisterId='.$id),array("sel"=>new Expression("case When d.ResourceId <>0 Then 1 Else 0 END")), $select::JOIN_LEFT)
                    ->where( "c.ProjectId =".$projname);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->allRes =  $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
    public function deleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('MMS_IssueRegister')
                        ->set(array('DeleteFlag' => '1','DeleteOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('IssueRegisterId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    //CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Delete','D','Payment-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);

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
    public function saveAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $CostCentreId= $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string');
                $whereCond = array("a.CostCentreId"=>$CostCentreId);
                if($RequestType == 'Material' && $RequestType != '') {
                    $RequestType=2;
                }
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectResource'))
                    ->columns(array(new Expression("b.ResourceId,isnull(d.BrandId,0) ItemId,
	                            Case When isnull(d.BrandId,0) > 0 Then d.ItemCode Else b.Code End As Code,
	                            Case When isnull(d.BrandId,0) >0 Then d.BrandName Else b.ResourceName End As ResourceName,
	                            0 As Include,'Project' As RFrom ") ))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_Brand'),'b.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT)
                    ->join(array('e' => 'WF_OperationalCostCentre'),'a.ProjectId=e.ProjectId',array(),$select::JOIN_INNER)
                    ->where("b.TypeId = $RequestType and e.CostCentreId =".$CostCentreId );
                $statement = $sql->getSqlStringForSqlObject($select);
                $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('resources' => $requestResources)));
                return $response;
            }
        } else {
            // get cost centres
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $projSelect = $sql->select();
            $projSelect->from('Vendor_Master')
                ->columns(array("VendorId", "VendorName"))
                ->where("Contract = 1" );
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_contractor = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function issueeditAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $this->_view->IssueRegisterId = 0;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentre'), 'number','');
                $itemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number','');
                $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number','');
                $contractor = $this->bsf->isNullCheck($this->params()->fromPost('contractor'), 'number','');
                $issue_date = $this->bsf->isNullCheck($this->params()->fromPost('issuedate'), 'string','');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getwbsdetails':
                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,ParentText+'=>'+WbsName As WbsName,
                            CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('e' => 'WF_OperationalCostCentre'),'e.projectId=a.ProjectId',array(),$select::JOIN_INNER)
                            ->where(array("a.LastLevel"=>"1","e.costcentreId"=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                            ->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0');
                        $statement = $sql->getSqlStringForSqlObject($selWh);
                        $arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                        //Closing Stock
//                        $stdate = date('Y-m-d',strtotime($issue_date));
//
//                        $selM1 = $sql -> select();
//                        $selM1->from(array("a" => "MMS_PVTrans"))
//                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty)
//                               Else SUM(a.BillQty) End,Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And h.SEZProject=0
//                                and (j.StateId=l.StateId)) Then Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then
//                                 isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) Else
//                                  SUM(A.BillQty * Case When (b.FFactor>0 and b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) else a.GrossRate End) End Else
//                                   case when c.ThruDC='Y' then SUM(a.ActualQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End)
//                                   else sum(a.BillQty*Case when (b.FFactor>0 and b.TFactor>0) Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,
//                                   c.CostCentreId ")))
//                            ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM1::JOIN_INNER)
//                            ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM1::JOIN_INNER )
//                            ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM1::JOIN_INNER)
//                            ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM1::JOIN_LEFT)
//                            ->join(array("f" => "MMS_DCTrans"),"a.DCTransId=f.DCTransId and a.DCRegisterId=f.DCRegisterId",array(),$selM1::JOIN_INNER)
//                            ->join(array("g" => "MMS_DCRegister"),"f.DCRegisterId=g.DCRegisterId",array(),$selM1::JOIN_INNER)
//                            ->join(array("h" => "WF_OperationalCostCentre"),"c.CostCentreId=h.CostCentreId",array(),$selM1::JOIN_INNER)
//                            ->join(array("i" => "Vendor_Master"),"c.VendorId=i.VendorId",array(),$selM1::JOIN_INNER)
//                            ->join(array("j" => "WF_CityMaster"),"i.CityId=j.CityId",array(),$selM1::JOIN_LEFT)
//                            ->join(array("k" => "WF_CostCentre"),"h.FACostCentreId=k.CostCentreId",array(),$selM1::JOIN_INNER)
//                            ->join(array("m" => "WF_CityMaster"),"k.CityId=m.CityId",array(),$selM1::JOIN_INNER)
//                            ->join(array("l" => "WF_StateMaster"),"m.StateId=l.StateId",array(),$selM1::JOIN_INNER)
//                            ->where("a.BillQty>0 and g.DCDate <='$stdate' and c.CostCentreId=$CostCentre
//                              Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,c.CostCentreId,h.SEZProject,
//                               b.FFactor,b.TFactor,j.StateId,l.StateId");
//
//
//
//
//                        $selM2 = $sql -> select();
//                        $selM2->from(array("a" => "MMS_PVTrans"))
//                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty)
//                               else SUM(a.BillQty) End,Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0
//                               and (h.StateId=j.StateId)) Then Case When c.ThruDC='Y' Then SUM(a.ActualQty * Case When (b.FFactor>0 and b.TFactor>0)
//                               then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) Else SUM(a.BillQty*
//                               Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else
//                               a.GrossRate End) End Else Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case when (b.FFactor>0 and b.TFactor>0) then
//                               isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 and b.TFactor>0)
//                               then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,a.CostCentreId As CostCentreId   ")))
//                            ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM2::JOIN_INNER)
//                            ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM2::JOIN_INNER)
//                            ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM2::JOIN_INNER)
//                            ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM2::JOIN_LEFT)
//                            ->join(array("f" => "WF_OperationalCostCentre"),"a.CostCentreId=f.CostCentreId",array(),$selM2::JOIN_INNER)
//                            ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM2::JOIN_INNER)
//                            ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM2::JOIN_LEFT)
//                            ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM2::JOIN_INNER)
//                            ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM2::JOIN_INNER)
//                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM2::JOIN_INNER)
//                            ->where("a.BillQty>0 and c.PVDate <= '$stdate' and a.CostCentreId=$CostCentre and c.CostCentreId=0
//                               Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,a.CostCentreId,
//                                f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId");
//                        $selM2->combine($selM1,'Union ALL');
//
//
//
//                        $selM3 = $sql -> select();
//                        $selM3->from(array("a" => "MMS_PVTrans"))
//                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case When c.ThruDC='Y' then SUM(a.ActualQty) else SUM(a.BillQty) End,
//                              Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 and (h.StateId=j.StateId)) then
//                              Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/
//                              nullif(b.FFactor,0) Else a.GrossRate End) Else SUM(a.BillQty*Case when (b.FFactor>0 and b.TFactor>0) then
//                              isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) End  Else
//                              Case when c.ThruDC='Y' then sum(a.actualqty*case when (b.FFactor>0 and b.TFactor>0)  then
//                              isnull((a.qrate * b.TFactor),0)/nullif(b.ffactor,0) else a.qrate end) else sum(a.billqty* case when (b.ffactor>0 and b.tfactor>0)
//                              then isnull((a.qrate*b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end  end,c.CostCentreId    ")))
//                            ->join(array("b" => "MMS_PVGroupTrans"),"a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId",array(),$selM3::JOIN_INNER)
//                            ->join(array("c" => "MMS_PVRegister"),"a.PVRegisterId=c.PVRegisterId",array(),$selM3::JOIN_INNER)
//                            ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM3::JOIN_INNER)
//                            ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM3::JOIN_LEFT)
//                            ->join(array("f" => "WF_OperationalCostCentre"),"c.CostCentreId=f.CostCentreId",array(),$selM3::JOIN_INNER)
//                            ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM3::JOIN_INNER)
//                            ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM3::JOIN_LEFT)
//                            ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM3::JOIN_INNER)
//                            ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM3::JOIN_INNER)
//                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM3::JOIN_INNER)
//                            ->where("a.BillQty>0 and c.PVDate <= '$stdate' and c.CostCentreId=$CostCentre and
//                                c.ThruPO='Y' Group BY e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,
//                                 c.ThruDC,c.CostCentreId,f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId");
//                        $selM3->combine($selM2,'Union All');
//
//
//
//                        $selM4 = $sql -> select();
//                        $selM4->from(array("a" => "MMS_DCTrans"))
//                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.BalQty),Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5)
//                              and f.SEZProject=0 and (h.StateId=j.StateId)) then sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0) then
//                              isnull((a.grossrate*b.tfactor),0)/nullif(b.ffactor,0) else a.grossrate end) else
//                              sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0) then isnull((a.qrate*b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end,
//                              c.CostCentreId As CostCentreId")))
//                            ->join(array("b" => "MMS_DCGroupTrans"),"a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId",array(),$selM4::JOIN_INNER)
//                            ->join(array("c" => "MMS_DCRegister"),"a.DCRegisterId=c.DCRegisterId",array(),$selM4::JOIN_INNER)
//                            ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM4::JOIN_INNER)
//                            ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM4::JOIN_LEFT)
//                            ->join(array("f" => "WF_OperationalCostCentre"),"c.CostCentreId=f.CostCentreId",array(),$selM4::JOIN_INNER)
//                            ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM4::JOIN_INNER)
//                            ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM4::JOIN_LEFT)
//                            ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM4::JOIN_INNER)
//                            ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM4::JOIN_INNER)
//                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM4::JOIN_INNER)
//                            ->where("a.balqty>0 and c.DCDate <= '$stdate' and c.CostCentreId=$CostCentre and
//                              c.DcOrCSM=1 Group By e.ResourceGroupID,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.CostCentreId,
//                              f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId ");
//                        $selM4->combine($selM3,'Union All');
//
//
//
//                        $selM5 = $sql -> select();
//                        $selM5 -> from(array("a" => "MMS_DCTrans"))
//                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Sum(a.BalQty),Amount=Case when ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5)
//                            and f.SEZProject=0 and (h.StateId=j.StateId)) then SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0)
//                            then isnull((a.grossrate*b.TFactor),0)/nullif(b.FFactor,0) else a.grossrate end) else sum(a.balqty*case when (b.ffactor>0 and b.tfactor>0)
//                            then isnull((a.qrate *b.tfactor),0)/nullif(b.ffactor,0) else a.qrate end) end,a.CostCentreId As CostCentreId ")))
//                            ->join(array("b" => "MMS_DCGroupTrans"),"a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId",array(),$selM5::JOIN_INNER)
//                            ->join(array("c" => "MMS_DCRegister"),"a.DCRegisterId=c.DCRegisterId",array(),$selM5::JOIN_INNER)
//                            ->join(array("d" => "Proj_Resource"),"a.ResourceId=d.ResourceId",array(),$selM5::JOIN_INNER)
//                            ->join(array("e" => "Proj_ResourceGroup"),"d.ResourceGroupId=e.ResourceGroupId",array(),$selM5::JOIN_LEFT)
//                            ->join(array("f" => "WF_OperationalCostCentre"),"a.CostCentreId=f.CostCentreId",array(),$selM5::JOIN_INNER)
//                            ->join(array("g" => "Vendor_Master"),"c.VendorId=g.VendorId",array(),$selM5::JOIN_INNER)
//                            ->join(array("h" => "WF_CityMaster"),"g.CityId=h.CityId",array(),$selM5::JOIN_LEFT)
//                            ->join(array("i" => "WF_CostCentre"),"f.FACostCentreId=i.CostCentreId",array(),$selM5::JOIN_INNER)
//                            ->join(array("k" => "WF_CityMaster"),"k.CityId=i.CityId",array(),$selM5::JOIN_INNER)
//                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM5::JOIN_INNER)
//                            ->where("a.BalQty>0 and c.DCDate <= '$stdate' and a.CostCentreId=$CostCentre  and c.CostCentreId=0
//                               and c.DcOrCSM=1 Group By e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,a.CostCentreId,
//                                f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId ");
//                        $selM5->combine($selM4,'Union All');
//
//
//
//                        $selM6 = $sql -> select();
//                        $selM6 -> from(array("a" => "MMS_TransferTrans" ))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=-SUM(a.TransferQty),Amount=-SUM(a.Amount),
//                              b.FromCostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
//                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
//                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_INNER)
//                            ->where("a.TransferQty>0 and b.TVDate <= '$stdate' and b.FromCostCentreId=$CostCentre
//                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.FromCostCentreId");
//                        $selM6->combine($selM5,'Union All');
//
//
//
//                        $selM7 = $sql -> select();
//                        $selM7 -> from(array("a" => "MMS_TransferTrans"))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.RecdQty),Amount=SUM(a.RecdQty*a.QRate),
//                              b.ToCostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
//                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
//                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_LEFT)
//                            ->where("a.RecdQty>0 and b.TVDate <= '$stdate' and b.ToCostCentreId=$CostCentre
//                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.ToCostCentreId");
//                        $selM7->combine($selM6,'Union All');
//
//
//
//                        $selM8 = $sql -> select();
//                        $selM8 -> from(array("a" => "MMS_PRTrans"))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.ReturnQty),Amount=SUM(-Amount),b.CostCentreId As CostCentreId')))
//                            ->join(array("b" => "MMS_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array(),$selM8::JOIN_INNER)
//                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM8::JOIN_INNER)
//                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM8::JOIN_INNER)
//                            ->where("a.ReturnQty>0 and b.PRDate <= '$stdate' and b.CostCentreId=$CostCentre
//                             Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId");
//                        $selM8->combine($selM7,'Union All');
//
//
//
//                        $selM9 = $sql -> select();
//                        $selM9 -> from(array("a" => "MMS_IssueTrans"))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.IssueQty),Amount=-SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
//                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
//                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
//                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
//                            ->where("a.IssueQty>0 and b.IssueDate <= '$stdate' and b.CostCentreId=$CostCentre and b.IssueOrReturn=0 and a.IssueOrReturn='I'
//                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
//                        $selM9->combine($selM8,'Union All');
//
//                        $selM9a = $sql -> select();
//                        $selM9a -> from(array("a" => "MMS_IssueTrans"))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.IssueQty),Amount=SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
//                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
//                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
//                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
//                            ->where("a.IssueQty>0 and b.IssueDate <= '$stdate' and b.CostCentreId=$CostCentre and b.IssueOrReturn=1 and a.IssueOrReturn='R'
//                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
//                        $selM9a->combine($selM9,'Union All');
//
//
//
//                        $selM10 = $sql ->select();
//                        $selM10 -> from(array("a" => "MMS_PVRateAdjustment" ))
//                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) then
//                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM10::JOIN_INNER)
//                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM10::JOIN_INNER)
//                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM10::JOIN_INNER)
//                            ->where("d.PVDate <= '$stdate'  and d.CostCentreId=$CostCentre and a.Type='D'
//                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
//                        $selM10->combine($selM9a,'Union All');
//
//
//                        $selM11 = $sql ->select();
//                        $selM11 -> from(array("a" => "MMS_PVRateAdjustment" ))
//                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=-SUM(Case When (c.FFactor>0 and c.TFactor>0) then
//                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
//                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM11::JOIN_INNER)
//                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM11::JOIN_INNER)
//                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM11::JOIN_INNER)
//                            ->where("d.PVDate <= '$issue_date'  and d.CostCentreId=$CostCentre and a.Type='C'
//                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
//                        $selM11->combine($selM10,'Union All');
//
//                        $selM12 = $sql -> select();
//                        $selM12 -> from(array("a" => "MMS_Stock"))
//                            ->columns(array(new Expression('a.ResourceId,a.ItemId,a.OpeningStock As Qty,Amount=a.OpeningStock*a.ORate,a.CostCentreId')))
//                            ->where(" a.CostCentreId=$CostCentre ");
//                        $selM12->combine($selM11,'Union All');
//
//                        $selF1 = $sql -> select();
//                        $selF1 -> from (array("g1" => $selM12))
//                            ->columns(array(new Expression('g1.ResourceId,g1.ItemId,Qty=Sum(g1.Qty),AvgRate=Case When CAST(isnull(isnull(SUM(g1.Amount),0)/nullif(SUM(g1.Qty),0),0) As Decimal(18,3)) < 0
//                              Then 0 Else CAST(isnull(isnull(SUM(g1.Amount),0)/nullif(SUM(g1.Qty),0),0) As Decimal(18,3)) End ,Cost=Case When SUM(g1.Amount) < 0 Then 0
//                                 When SUM(g1.Qty) <= 0 Then 0  Else SUM(g1.Amount) End,g1.CostCentreId As CostCentreId')));
//                        $selF1->group(new Expression("ResourceId,ItemId,CostCentreId"));
//
//
//
//                        $selF2 = $sql -> select();
//                        $selF2 -> from (array("g" => $selF1 ))
//                            ->columns(array(new Expression("RG.ResourceGroupId,G.ResourceId,G.ItemId,
//                            Case When g.ItemId>0 Then BR.ItemCode Else RV.Code End Code,Case When ISNULL(RG.ResourceGroupId,0)>0 Then RG.ResourceGroupName Else 'Others' End As ResourceGroup,
//                               RV.ResourceName As Resource,Case When g.ItemId>0 then BR.BrandName Else '' End ItemName,Case When G.ItemId>0 Then U.UnitName Else U1.UnitName End As Unit,
//                               g.Qty,g.AvgRate,g.Cost,g.CostCentreId  ")))
//                            ->join(array("RV" => "Proj_Resource"),"g.ResourceId=RV.ResourceId",array(),$selF2::JOIN_INNER)
//                            ->join(array("RG" => "Proj_ResourceGroup"),"RV.ResourceGroupId=RG.ResourceGroupId",array(),$selF2::JOIN_LEFT)
//                            ->join(array("BR" => "MMS_Brand"),"g.ResourceId=BR.ResourceId And g.ItemId=BR.BrandId",array(),$selF2::JOIN_LEFT)
//                            ->join(array("U" => "Proj_UOM"),"BR.UnitId=U.UnitId",array(),$selF2::JOIN_LEFT)
//                            ->join(array("U1" => "Proj_UOM"),"RV.UnitId=U1.UnitId",array(),$selF2::JOIN_LEFT)
//                            ->where('RV.TypeId IN (2) And G.ResourceId='.$resourceId.' And G.ItemId='.$itemId.' And g.CostCentreId='.$CostCentre.' ');
//
//                        $statement = $sql->getSqlStringForSqlObject($selF2);
//                        $arr_auto_closingstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $this->_view->arr_auto_closingstock=$arr_auto_closingstock;
//                        //

                        //////Start - ClosingStock
                        $stdate = date('Y-m-d',strtotime($issue_date));

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
                            ->where('RV.TypeId IN (2) And G.ResourceId IN ('.$resourceId.') And G.ItemId IN ('.$itemId.') And g.CostCentreId='.$CostCentre.' ');

                        $statement = $sql->getSqlStringForSqlObject($selF2);
                        $arr_auto_closingstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_auto_closingstock=$arr_auto_closingstock;
                        $arr_auto_closingstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        /////// End - ClosingSTock
                        $response->setStatusCode('200');
                        $response->setContent(json_encode(array('arr_resource_iows'=> $arr_resource_iows,'arr_sel_warehouse' => $arr_sel_warehouse,'arr_auto_closingstock' => $arr_auto_closingstock)));
                        return $response;
                        break;
                    case 'getIssueDetails':
                        $select = $sql->select();
                        $select->from(array('a' => 'mms_IssueRegister'))
                            ->columns(array(new Expression("0 IssueRegisterId, 0 IssueTransId,0 IRetTransId, A.ContractorId VendorId,A.IssueRegisterId ARegisterId,B.IssueTransId AIssueTransId,A.CostCentreId,
                                                            B.ResourceId,B.ItemId, A.IssueNo,Convert(Varchar(10),A.IssueDate,103) [IssueDate],
                                                            Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
                                                            Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End As HiddenQty,
                                                            ( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,
                                                            CAST(B.IssueRate As Decimal(18,2)) As Rate, CAST(0 As Decimal(18,3)) CurrentQty,CAST(0 As Decimal(18,3)) AdjustmentQty,
                                                            CAST(0 As Decimal(18,3)) HiddenQty,CAST(0 As Decimal(18,3)) HAdjustmentQty,B.TUnitId UnitId ")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId=b.IssueRegisterId  ', array(), $select:: JOIN_INNER)
                            ->where(" A.CostCentreId=$CostCentre And A.DeleteFlag=0 And A.Approve='Y' And A.IssueOrReturn=0 And B.IssueOrReturn='I'
                                    And (Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) > 0 And A.OWNOrCSM=0 and b.ResourceId in ($resourceId) and b.ItemId in ($itemId) and A.ContractorId=$contractor");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selWh = $sql -> select();
                        $selWh->from(array("a" => "MMS_IssueWareHouseTrans"))
                            ->columns(array(new Expression("a.IssueTransId as IssueTransId,
                            CAST(a.IssueQty As Decimal(18,3)) AS [IssueQty],b.transid AS [WareHouseId],
                            c.WareHouseName AS [WareHouseName],b.Description AS [Description],
                            f.ResourceId As ResourceId, f.ItemId As ItemId,f.StockId,
                            CAST(0 As Decimal(18,3)) AS [Qty],CAST(0 As Decimal(18,3)) AS [HiddenQty],
                            CAST(0 As Decimal(18,3)) AS [AdjustmentQty],CAST(0 As Decimal(18,3)) AS [AHiddenQty]")))
                            ->join(array("b" => "MMS_WareHouseDetails"),'a.WareHouseId=b.TransId',array(),$selWh::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->join(array("d" => "MMS_IssueTrans"),"d.IssueTransId=a.IssueTransId",array(),$selWh::JOIN_INNER)
                            ->join(array("e" => "MMS_IssueRegister"),"d.IssueRegisterId=e.IssueRegisterId",array(),$selWh::JOIN_INNER)
                            ->join(array("f" => "MMS_Stock"),"f.Resourceid=d.ResourceId and f.ItemId=d.ItemId and f.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                            ->join(array("g" => "MMS_StockTrans"),"f.StockId=g.StockId and g.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->where('e.CostCentreId='. $CostCentre .' and b.LastLevel=1 and a.IssueQty>0');
                        $statement = $sql->getSqlStringForSqlObject($selWh);
                        $arr_resel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode(array('arr_resource_issue'=> $arr_resource_iows,'arr_warehouse' => $arr_resel_warehouse)));
                        return $response;
                        break;
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid Issue!');
                        return $response;
                        break;
                }
                if ($postParams['mode'] == 'Questions') {
                    $VehicleSelect = $sql->select();
                    $VehicleSelect->from('mms_stock')
                        ->columns(array("ClosingStock"))
                        ->where(array("resourceId" => $postParams['resourceId'],"itemId" => $postParams['itemId'],"costCentreId" => $postParams['CostCentre']));
                    $VehicleStatement = $sql->getSqlStringForSqlObject($VehicleSelect);
                    $result = $dbAdapter->query($VehicleStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
//                  echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                   return;
                $CostCentre=  $this->bsf->isNullCheck($postData['CostCentre'],'number');
                $VendorId=  $this->bsf->isNullCheck($postData['VendorId'],'number');
                $issue_date =  $this->bsf->isNullCheck($postData['issue_date'],'date');
                //$IssueNo = $this->bsf->isNullCheck($this->params()->fromPost('IssueNo'), 'string');
                $issue_type= $this->bsf->isNullCheck($postData['issue_type'],'string');
                $contractor =  $this->bsf->isNullCheck($postData['contractor'],'number');
                $issue =  $this->bsf->isNullCheck($postData['issue'],'number');
                $OwnOrCsm=$this->bsf->isNullCheck($postData['OwnOrCsm'],'string');
                $requestTransIds = implode(',',$postData['requestTransIds']);
                $itemTransIds = implode(',',$postData['itemTransIds']);
                $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');
                $this->_view->CostCentre = $CostCentre;
                $this->_view->issue_date = $issue_date;
               // $this->_view->IssueNo=$IssueNo;
                $this->_view->issue_type=$issue_type;
                if($issue_type==1){
                    $this->_view->issue_typename="Internal" ;
                }else{
                    $this->_view->issue_typename="Contractor" ;
                }if($issue==0){
                    $this->_view->issue="issue";
                }else{
                    $this->_view->issue="return";
                }
                $Narration="";
                $this->_view->VendorId=$VendorId;
                $this->_view->OwnOrCsm=$OwnOrCsm;
                $this->_view->gridtype=$gridtype;
                $this->_view->Narration=$Narration;

                if (!is_null($postData['frm_index'])) {

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CompanyId'))
                        ->where("CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$Comp['CompanyId'];

                    //Issue No
                    $vNo = CommonHelper::getVoucherNo(307,date('Y/m/d') ,0,0, $dbAdapter,"");
                    $voNo = $vNo['voucherNo'];
                    $this->_view->vNo = $voNo;
                    $this->_view->genType = $vNo["genType"];

                    //CostCentreId
                    $CCIssue = CommonHelper::getVoucherNo(307, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                    $this->_view->CCIssue = $CCIssue;
                    $CCINo=$CCIssue['voucherNo'];
                    $this->_view->CCINo = $CCINo;

                    //CompanyId
                    $CIssue = CommonHelper::getVoucherNo(307, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CIssue = $CIssue;
                    $CINo=$CIssue['voucherNo'];
                    $this->_view->CINo = $CINo;

                    $selCC = $sql->select();
                    $selCC->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreName'))
                        ->where("a.CostCentreId=" . $CostCentre);
                    $statement = $sql->getSqlStringForSqlObject($selCC);
                    $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->ProjectName = $ccname['CostCentreName'];
                    if ($issue_type == 2) {
                        $selCC = $sql->select();
                        $selCC->from(array('a' => 'Vendor_Master'))
                            ->columns(array('VendorName'))
                            ->where("a.VendorId=$contractor");
                        $statement = $sql->getSqlStringForSqlObject($selCC);
                        $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->ContractorName = $ccname['VendorName'];
                    }
                    $this->_view->contractor = $contractor;
                    // get resource lists
                    if ($issue == 0){
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,
                                Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                                Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                                b.ResourceGroupName,b.ResourceGroupId,
                                Case when isnull(d.BrandId,0)>0 then f.UnitName Else c.UnitName End As UnitName,
                                CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty,
                                Case when isnull(d.BrandId,0)>0 then f.UnitId Else c.UnitId End As UnitId,
                                Case when isnull(d.BrandId,0)>0 then CAST(d.Rate As Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End As Rate ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName", "ResourceGroupId"), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=e.ProjectId',array(),$select::JOIN_INNER)
                            ->where("g.CostCentreId=" . $CostCentre . " and
                            (a.ResourceId IN ($requestTransIds) and isnull(d.BrandId,0) IN ($itemTransIds))");

                        $selLib = $sql -> select();
                        $selLib -> from (array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                        b.ResourceGroupName,b.ResourceGroupId,
                        Case when isnull(d.BrandId,0)>0 then e.UnitName Else c.UnitName End As UnitName,
                        CAST(0 As Decimal(18,6)) As Qty,CAST(0 As Decimal(18,6)) As HiddenQty,
                        Case when isnull(d.BrandId,0)>0 then e.UnitId Else c.UnitId End As UnitId,
                        Case when isnull(d.BrandId,0)>0 then CAST(d.Rate As Decimal(18,2)) Else CAST(a.Rate As Decimal(18,2)) End As Rate ")))
                            ->join(array('b' => 'Proj_ResourceGroup'),'a.ResourceGroupId=b.ResourceGroupId',array("ResourceGroupName","ResourceGroupId"),$selLib::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'),'a.UnitId=c.UnitId',array(),$selLib::JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $selLib::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'),'d.UnitId=e.UnitId',array(),$selLib::JOIN_LEFT)
                            ->join(array('f' => 'Proj_ProjectResource'),'a.ResourceId=f.ResourceId',array(),$selLib::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectId=f.ProjectId',array(),$selLib::JOIN_LEFT)
                            ->where("a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource Where ProjectId=". $CostCentre .")
                              and (a.ResourceId  IN ($requestTransIds) and isnull(d.BrandId,0)  IN ($itemTransIds)) and
                              (a.ResourceId  NOT IN ($requestTransIds) and isnull(d.BrandId,0) NOT IN ($itemTransIds))");
                        $select -> combine($selLib,'Union All');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'mms_purchasetype'))
                            ->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
                            ->join(array('b' => 'FA_AccountMaster'), 'a.AccountId=b.AccountId', array(), $select::JOIN_INNER)
                            ->where(array('a.sel' => "1"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $subQuery = $sql->select();
                        $subQuery->from("VM_RequestTrans")
                            ->columns(array('ResourceId'))
                            ->where('IssueRegisterId IN (' . $requestTransIds . ')')
                            ->group(new Expression('ResourceId'));

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,
                            ParentText+'=>'+WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('e' => 'WF_OperationalCostCentre'),'e.projectId=a.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("a.LastLevel" => "1", "e.costcentreId" => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                        $select = $sql->select();
//                        $select->from(array('a' => 'Proj_Resource'))
//                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,c.UnitName,c.UnitId")))
//                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
//                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array("UnitName", "UnitId"), $select:: JOIN_LEFT)
//                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
//                            ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_LEFT)
//                            ->where("e.ProjectId=" . $CostCentre . " and (a.ResourceId NOT IN ($requestTransIds) and isnull(d.BrandId,0) NOT IN ($itemTransIds))");
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //IsWareHouse
                        $select = $sql -> select();
                        $select->from(array("a" => "MMS_CCWareHouse"))
                            ->columns(array("WareHouseId"))
                            ->where(array("a.CostCentreId= $CostCentre"));
                        $whStatement = $sql->getSqlStringForSqlObject($select);
                        $isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->isWh = $isWareHouse;

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
                            ->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0');
                        $statement = $sql->getSqlStringForSqlObject($selWh);
                        $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsRes = $sql -> select();
                        $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                            ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                            ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                            ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_LEFT)
                            ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($wbsRes);
                        $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //////Start - ClosingStock
                        $stdate = date('Y-m-d',strtotime($issue_date));

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

                    }else{
                        $select = $sql->select();
                        $select->from(array('a' => 'mms_IssueRegister'))
                            ->columns(array(new Expression("0 IssueRegisterId, 0 IssueTransId,0 IRetTransId, A.ContractorId VendorId,A.IssueRegisterId ARegisterId,B.IssueTransId AIssueTransId,A.CostCentreId,
                                                            B.ResourceId,B.ItemId, A.IssueNo,Convert(Varchar(10),A.IssueDate,103) [IssueDate],
                                                            Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
                                                            Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End As HiddenQty,
                                                            ( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,
                                                            CAST(B.IssueRate As Decimal(18,2)) Rate, CAST(0 As Decimal(18,3)) CurrentQty,CAST(0 As Decimal(18,3)) AdjustmentQty,
                                                            CAST(0 As Decimal(18,3)) HiddenQty,CAST(0 As Decimal(18,3)) HAdjustmentQty,B.TUnitId UnitId ")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId=b.IssueRegisterId  ', array(), $select:: JOIN_INNER)
                            ->where(" A.CostCentreId=$CostCentre And A.DeleteFlag=0 And A.Approve='Y' And A.IssueOrReturn=0 And B.IssueOrReturn='I'
                                    And (Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) > 0 And A.OWNOrCSM=0 and b.ResourceId in ($requestTransIds) and b.ItemId in ($itemTransIds) and A.ContractorId=$contractor ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows=$arr_resource_iows;

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                              Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                              b.ResourceGroupName,b.ResourceGroupId,Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                              Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=e.ProjectId',array(),$select::JOIN_INNER)
                            ->where("g.CostCentreId=" . $CostCentre . " and
                            (a.ResourceId IN ($requestTransIds) and isnull(d.BrandId,0) IN ($itemTransIds))");

                        $sellib = $sql -> select();
                        $sellib->from(array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,b.ResourceGroupName,b.ResourceGroupId,c.UnitName,c.UnitId")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'),'d.UnitId=e.UnitId',array(),$sellib::JOIN_LEFT)
                            ->join(array('f' => 'Proj_ProjectResource'),'a.ResourceId=f.ResourceId',array(),$sellib::JOIN_LEFT)
                            ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=f.ProjectId',array(),$sellib::JOIN_LEFT)
                            ->where("(a.ResourceId IN ($requestTransIds) and isnull(d.BrandId,0) IN ($itemTransIds))
                                 and (a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource Where ProjectId=". $CostCentre .")) and
                                 (a.ResourceId  NOT IN ($requestTransIds) and
                                 isnull(d.BrandId,0) NOT IN ($itemTransIds))");
                        $select -> combine($sellib,'Union All');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'mms_purchasetype'))
                            ->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
                            ->join(array('b' => 'FA_AccountMaster'), 'a.AccountId=b.AccountId', array(), $select::JOIN_INNER)
                            ->where(array('a.sel' => "1"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //IsWareHouse
                        $select = $sql -> select();
                        $select->from(array("a" => "MMS_CCWareHouse"))
                            ->columns(array("WareHouseId"))
                            ->where(array("a.CostCentreId= $CostCentre"));
                        $whStatement = $sql->getSqlStringForSqlObject($select);
                        $isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->isWh = $isWareHouse;

                        //return-warehouse-add
                        $selWh = $sql -> select();
                        $selWh->from(array("a" => "MMS_IssueWareHouseTrans"))
                            ->columns(array(new Expression("a.IssueTransId as IssueTransId,
                            CAST(a.IssueQty As Decimal(18,3)) AS [IssueQty],
                            CAST((a.IssueQty - (a.ReturnQty+a.AdjustmentQty)) As Decimal(18,3)) AS [BalIssueQty],
                            b.transid AS [WareHouseId],
                            c.WareHouseName AS [WareHouseName],b.Description AS [Description],
                            f.ResourceId As ResourceId, f.ItemId As ItemId,f.StockId,
                            CAST(0 As Decimal(18,3)) AS [bIssueQty],
                            CAST(0 As Decimal(18,3)) AS [Qty],CAST(0 As Decimal(18,3)) AS [HiddenQty],
                            CAST(0 As Decimal(18,3)) AS [AdjustmentQty],CAST(0 As Decimal(18,3)) AS [HAdjustmentQty]")))
                            ->join(array("b" => "MMS_WareHouseDetails"),'a.WareHouseId=b.TransId',array(),$selWh::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->join(array("d" => "MMS_IssueTrans"),"d.IssueTransId=a.IssueTransId",array(),$selWh::JOIN_INNER)
                            ->join(array("e" => "MMS_IssueRegister"),"d.IssueRegisterId=e.IssueRegisterId",array(),$selWh::JOIN_INNER)
                            ->join(array("f" => "MMS_Stock"),"f.Resourceid=d.ResourceId and f.ItemId=d.ItemId and f.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                            ->join(array("g" => "MMS_StockTrans"),"f.StockId=g.StockId and g.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
                           // ->join(array("h" => "MMS_returnWareHouseTrans"),"h.IssueTransId=a.IssueTransId and h.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->where('e.CostCentreId='. $CostCentre .' and b.LastLevel=1 and a.IssueQty>0');
                         $statement = $sql->getSqlStringForSqlObject($selWh);
                        $this->_view->arr_resel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                        Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                        Case when isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(e.Rate As Decimal(18,2)) End As Rate ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=e.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.TypeId IN (2,3) and g.costcentreId= $CostCentre and
                        (a.ResourceId NOT IN ($requestTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) else CAST(a.Rate As Decimal(18,2)) End As Rate  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
	                            Inner Join WF_OperationalCostCentre b On a.projectid=b.projectid
	                            Where b.costcentreid=". $CostCentre .") and (a.ResourceId NOT IN ($requestTransIds)
                                Or isnull(c.BrandId,0) NOT IN ($itemTransIds))");
                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            else {
                $IssueRegisterId =$this->params()->fromRoute('rid');
                $this->_view->IssueRegisterId=$IssueRegisterId;
                if (isset($IssueRegisterId) && $IssueRegisterId != '') {
                    // get request
                    $selReqReg=$sql->select();
                    $selReqReg->from(array('a' => 'mms_issueRegister'))
                        ->where("a.IssueRegisterId=$IssueRegisterId");
                    $statement = $sql->getSqlStringForSqlObject( $selReqReg );
                    $this->_view->reqregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$Approve = $this->_view->reqregister['Approve'];
					$Narration = $this->_view->reqregister['Narration'];
					$gridtype = $this->_view->reqregister['GridType'];
					$this->_view->Narration = $Narration;
					$this->_view->Approve = $Approve;
					$this->_view->gridtype = $gridtype;

                    $selReqReg=$sql->select();
                    $selReqReg->from(array('a' => 'mms_issueTrans'))
                        ->where("a.IssueRegisterId=$IssueRegisterId");
                    $statement = $sql->getSqlStringForSqlObject( $selReqReg );
                    $this->_view->reqregist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $CostCentre = $this->_view->reqregister['CostCentreId'];
                    $ccino = $this->_view->reqregister['CCIssueNo'];
                    $cino = $this->_view->reqregister['CIssueNo'];
                    $ResourceId = $this->_view->reqregist['ResourceId'];
                    $ItemId = $this->_view->reqregist['ItemId'];
                    $vNo = $this->_view->reqregister['IssueNo'];
                    $issue_date =$this->_view->reqregister['IssueDate'];
                    $issue_dates=strtotime($issue_date);
                    $issue_datess=date('d-m-Y', $issue_dates);
                    $this->_view->issue_datess=$issue_datess;

                    $issue_type =$this->_view->reqregister['IssueType'];
                    $contractor =$this->_view->reqregister['ContractorId'];
//                    $requestTransIds = implode(',', $postData['requestTransIds']);
//                    $itemTransIds = implode(',', $postData['itemTransIds']);
                    $this->_view->CostCentre = $CostCentre;
                    $this->_view->CCINo = $ccino;
                    $this->_view->CINo = $cino;
                    $this->_view->issue_date = $issue_date;
                    $this->_view->vNo=$vNo;
                    $this->_view->contractor=$contractor;
                    $selCC = $sql->select();
                    $selCC->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreName'))
                        ->where("a.CostCentreId=".$CostCentre);
                    $statement = $sql->getSqlStringForSqlObject($selCC);
                    $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->ProjectName=$ccname['CostCentreName'];
                    if($issue_type==2) {
                        $selCC = $sql->select();
                        $selCC->from(array('a' => 'Vendor_Master'))
                            ->columns(array('VendorName'))
                            ->where("a.VendorId=$contractor");
                        $statement = $sql->getSqlStringForSqlObject($selCC);
                        $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->ContractorName = $ccname['VendorName'];
                    }
                    $selectCurRequest = $sql->select();
                    $selectCurRequest->from(array("a"=>"mms_issueRegister"))
                        ->columns(array(new Expression("Case When a.IssueType=1 Then 'Internal' Else' Contractor' End as IssueType,Case When a.IssueOrReturn=0 Then 'issue' Else 'return' End as Type")))
                        ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($selectCurRequest);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->issue_typename=$results['IssueType'];
                    $issue=$results['Type'];
                    $this->_view->issue=$issue;


                    $select = $sql->select();
                    $select-> from(array('a' => 'mms_purchasetype'))
                        ->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
                        ->join(array('b' => 'FA_AccountMaster'),'a.AccountId=b.AccountId',array(),$select::JOIN_INNER)
                        ->where(array('a.sel'=>"1"));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_IssueTrans'))
                        ->columns(array(new Expression("a.ResourceId,a.ItemId,Case When a.ItemId>0
                         Then d.ItemCode Else b.Code End As Code,Case When a.ItemId>0 Then
                         d.BrandName Else b.ResourceName End As ResourceName,c.ResourceGroupName,
                         c.ResourceGroupId,f.UnitName,a.UnitId,CAST(a.IssueQty As Decimal(18,3)) As Qty,CAST(a.IssueQty As Decimal(18,3)) As HiddenQty,CAST(a.IssueRate As Decimal(18,2)) as rate,CAST(a.IssueAmount As Decimal(18,2)) as Amount,a.Remarks as Remarks,a.PurchaseTypeId as PurchaseAccount,a.IssueTypeId as issueAccount,a.FreeOrCharge as FreeOrCharge")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId ', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId ', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'MMS_IssueRegister'), 'a.IssueRegisterId=e.IssueRegisterId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_UOM'), 'a.UnitId=f.UnitId ' )
                        ->where("e.CostCentreId=" . $CostCentre . " and e.IssueRegisterId=".$IssueRegisterId."");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subWbs1=$sql->select();
                    $subWbs1->from(array('a'=>'Proj_WBSMaster'))
                        ->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$subWbs1::JOIN_INNER)
                        ->where -> expression('a.LastLevel=1 And b.CostCentreId='.$CostCentre.' And a.WBSId NOT IN (Select AnalysisId From mms_IssueAnalTrans Where IssueTransId IN (Select IssueTransId From MMS_IssueTrans Where IssueRegisterId=?))',$IssueRegisterId);
                    if($issue=="issue") {
                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("c.ResourceId,C.ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,CAST(b.IssueQty As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
                            ->join(array('b' => 'mms_IssueAnalTrans'), 'a.WbsId=b.AnalysisId', array(), $wbsSelect::JOIN_INNER)
                            ->join(array('c' => 'mms_issueTrans'), 'b.IssueTransId=c.IssueTransId', array(), $wbsSelect::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("a.LastLevel" => "1", "d.CostCentreId" => $CostCentre, "c.IssueRegisterId" => $IssueRegisterId));
                        $wbsSelect->combine($subWbs1, 'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    else {
                        $select = $sql->select();
                        $select->from(array('A' => 'MMS_IssueRegister'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("E.IssueRegisterId,D.IssueTransId,C.IRetTransId,A.ContractorId VendorId,A.IssueRegisterId ARegisterId,
                                             B.IssueTransId AIssueTransId,A.CostCentreId,B.ResourceId,B.ItemId,A.IssueNo,CONVERT(Varchar(10),A.IssueDate,103) IssueDate,
                                             Case When (B.TFactor>0 And B.FFactor>0) Then CAST((ISNULL((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
                                             ( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                             Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                             Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,CAST(B.IssueRate As Decimal(18,2)) Rate,
                                             Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.Qty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.Qty As Decimal(18,3)) End CurrentQty,
                                             Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.AdjustmentQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.AdjustmentQty As Decimal(18,3)) End AdjustmentQty,
                                             Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.Qty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.Qty As Decimal(18,3)) End HiddenQty,
                                             Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.AdjustmentQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.AdjustmentQty As Decimal(18,3)) End HAdjustmentQty,
                                             B.UnitId  ")))
                            ->join(array('B' => 'MMS_IssueTrans'), ' A.IssueRegisterId=B.IssueRegisterId', array(), $select:: JOIN_INNER)
                            ->join(array('C' => 'MMS_IssueReturnTrans'), 'B.IssueTransId=C.RIssueTransId ', array(), $select:: JOIN_INNER)
                            ->join(array('D' => 'MMS_IssueTrans'), 'C.IssueTransId=D.IssueTransId ', array(), $select:: JOIN_INNER)
                            ->join(array('E' => 'MMS_IssueRegister'), 'D.IssueRegisterId=E.IssueRegisterId ', array(), $select:: JOIN_INNER)
                            ->where(" A.OWNOrCSM=0 And E.OWNOrCSM=0 And A.IssueOrReturn=0 And E.IssueOrReturn=1 And A.Approve='Y'
                                     And A.CostCentreId= $CostCentre   And E.IssueRegisterId= $IssueRegisterId And A.ContractorId=$contractor Union All
                                     Select 0 IssueRegisterId, 0 IssueTransId,0 IRetTransId, A.ContractorId VendorId,A.IssueRegisterId ARegisterId,B.IssueTransId AIssueTransId,A.CostCentreId,
                                     B.ResourceId,B.ItemId, A.IssueNo,Convert(Varchar(10),A.IssueDate,103) [IssueDate],
                                     Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
                                    ( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else B.IssueQty End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,
                                    CAST(B.IssueRate As Decimal(18,2)) Rate, CAST(0 As Decimal(18,3)) CurrentQty,CAST(0 As Decimal(18,3)) AdjustmentQty,CAST(0 As Decimal(18,3)) HiddenQty,CAST(0 As Decimal(18,3)) HAdjustmentQty,B.TUnitId UnitId  from MMS_IssueRegister A
                                    Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId
                                    Where  A.CostCentreId=$CostCentre   And A.Approve='Y' And A.IssueOrReturn=0 And B.IssueOrReturn='I'
                                    And (Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
                                    Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) > 0 And B.IssueTransId
                                    Not In (Select RIssueTransId From MMS_IssueReturnTrans Where IssueTransId IN (Select IssueTransId
                                    From MMS_IssueTrans Where IssueRegisterId=$IssueRegisterId  )) And A.OWNOrCSM=0 and  A.ContractorId=$contractor ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arr_resource_iows=$arr_resource_iows;
                    }
                    $selR=$sql->select();
                    $selR->from(array('a'=>'MMS_IssueTrans'))
                        ->columns(array(new Expression("a.ResourceId")))
                        ->where (array("a.IssueRegisterId"=>$IssueRegisterId));
                    $selI=$sql->select();
                    $selI->from(array('a'=>'MMS_IssueTrans'))
                        ->columns(array(new Expression("a.ItemId")))
                        ->where (array("a.IssueRegisterId"=>$IssueRegisterId));

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                         Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                         Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                         case when isnull(d.BrandId,0)>0 Then e.UnitName
                         Else C.UnitName End As UnitName,Case When isnull(d.BrandId,0)>0
                         Then E.UnitId Else C.UnitId End As UnitId,
                         Case when isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(f.Rate As Decimal(18,2)) End As Rate ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), ' a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'd.UnitID=e.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_ProjectResource'), 'a.ResourceId=f.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=f.ProjectId',array(),$select::JOIN_INNER)
                        ->where("g.CostCentreId=" . $CostCentre );

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) else CAST(a.Rate As Decimal(18,2)) End As Rate  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
	                                 Inner Join WF_OperationalCostCentre b On a.projectid=b.projectid
                                     Where b.costcentreid=". $CostCentre .")");
                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select=$sql->select();
                    $select->from(array('a'=>'mms_IssueRegister'))
                        ->columns(array(new Expression("a.Narration as Notes")))
                        ->where (array("a.IssueRegisterId"=>$IssueRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->Narrat = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //IsWareHouse
                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_CCWareHouse"))
                        ->columns(array("WareHouseId"))
                        ->where(array("a.CostCentreId"=> $CostCentre));
                    $whStatement = $sql->getSqlStringForSqlObject($select);
                    $isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->isWh = $isWareHouse;

                        //warehouse-issue-edit
                    $selWhm = $sql -> select();
                    $selWhm->from(array("a" => "MMS_IssueWareHouseTrans"))
                        ->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
                            "ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
                            "WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
                            "ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,3))"),
                            "Qty" => new Expression("CAST(a.IssueQty As Decimal(18,3))"),"HiddenQty"=>new expression("CAST(a.IssueQty As Decimal(18,3))") ))
                        ->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$selWhm::JOIN_INNER)
                        ->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$selWhm::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$selWhm::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$selWhm::JOIN_INNER)
                        ->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$selWhm::JOIN_INNER)
                        ->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$selWhm::JOIN_INNER)
                        ->where ("c.IssueRegisterId=".$IssueRegisterId."");

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
                        ->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0 and
                            (e.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
                            e.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' )) and
                            c.TransId NOT IN (Select warehouseid from MMS_IssueWareHouseTrans A Inner Join MMS_IssueTrans B On A.IssueTransId=B.IssueTransId
                            where b.IssueRegisterId='.$IssueRegisterId.')    ');
                    $selWhm->combine($selWh,'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($selWhm);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
                            "ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
                            "WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,3))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,3))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$select::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$select::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$select::JOIN_INNER)
                        ->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0 and
                            (e.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
                            e.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' ))');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_wbs_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select1 = $sql -> select();
                    $select1->from(array("a" => "MMS_IssueWareHouseWbsTrans"))
                        ->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
                            "AnalysisId" => new Expression("a.AnalysisId"),
                            "ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
                            "WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
                            "ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,3))"),
                            "Mode" => new Expression("CONVERT(bit,0,0)"),
                            "Qty" => new Expression("CAST(a.IssueQty As Decimal(18,3))"),"HiddenQty"=>new expression("CAST(a.IssueQty As Decimal(18,3))")
                             ))
                        ->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$select1::JOIN_INNER)
                        ->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$select1::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$select1::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$select1::JOIN_INNER)
                        ->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$select1::JOIN_INNER)
                        ->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$select1::JOIN_INNER)
                        ->where ("c.IssueRegisterId=".$IssueRegisterId."");
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->arr_selwbs_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.projectid=d.projectid',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //return-warehouse-edit
                    $selWh = $sql -> select();
                    $selWh->from(array("a" => "MMS_IssueWareHouseTrans"))
                        ->columns(array(new Expression("a.IssueTransId as IssueTransId,
                            CAST(a.IssueQty As Decimal(18,3)) As IssueQty,
                            CAST((a.IssueQty - (a.ReturnQty+a.AdjustmentQty)) As Decimal(18,3)) AS [BalIssueQty],
                            b.transid AS [WareHouseId],
                            c.WareHouseName AS [WareHouseName],b.Description AS [Description],
                            f.ResourceId As ResourceId, f.ItemId As ItemId,f.StockId,
                            CAST(0 As Decimal(18,3)) AS [Qty],CAST(0 As Decimal(18,3)) AS [HiddenQty],
                            CAST(0 As Decimal(18,3)) AS [AdjustmentQty],CAST(0 As Decimal(18,3)) AS [AHiddenQty]")))
                        ->join(array("b" => "MMS_WareHouseDetails"),'a.WareHouseId=b.TransId',array(),$selWh::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("d" => "MMS_IssueTrans"),"d.IssueTransId=a.IssueTransId",array(),$selWh::JOIN_INNER)
                        ->join(array("e" => "MMS_IssueRegister"),"d.IssueRegisterId=e.IssueRegisterId",array(),$selWh::JOIN_INNER)
                        ->join(array("f" => "MMS_Stock"),"f.Resourceid=d.ResourceId and f.ItemId=d.ItemId and f.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                        ->join(array("g" => "MMS_StockTrans"),"f.StockId=g.StockId and g.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("h" => "MMS_returnWareHouseTrans"),"h.IssueTransId=a.IssueTransId and h.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->where('e.CostCentreId='. $CostCentre .' and b.LastLevel=1 and a.IssueQty>0 and
                             (f.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
                              f.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' )) ');
                    $statement = $sql->getSqlStringForSqlObject($selWh);
                    $this->_view->arr_resel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select1 = $sql -> select();
                    $select1->from(array("a" => "MMS_IssueWareHouseReturnTrans"))
                        ->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
                            "RIssueTransId" => new Expression("a.RIssueTransId"),
                            "ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
                            "WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
                            "ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,2))"),
                            "Mode" => new Expression("CONVERT(bit,0,0)"),
                            "Qty" => new Expression("CAST(a.ReturnQty As Decimal(18,3))"),
                            "HiddenQty"=>new expression("CAST(a.ReturnQty As Decimal(18,3))"),
                            "AdjustmentQty" => new expression("CAST(a.AdjustmentQty As Decimal(18,3))"),
                            "AHiddenQty" => new expression("CAST(a.AdjustmentQty As Decimal(18,3))")
                        ))
                        ->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$select1::JOIN_INNER)
                        ->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$select1::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$select1::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$select1::JOIN_INNER)
                        ->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$select1::JOIN_INNER)
                        ->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$select1::JOIN_INNER)
                        ->where ("c.IssueRegisterId=".$IssueRegisterId."");
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->arr_resel_iswarehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            }

        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function issueEntAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(307,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

                $postData = $request->getPost();
//                echo"<pre>";
//                print_r($postData);
//                echo"</pre>";die;


                $IssueRegisterId =$this->bsf->isNullCheck($postData['IssueRegisterId'], 'number');
                $CostCentre=$this->bsf->isNullCheck($postData['CostCentre'], 'number');
                $CCINo=$this->bsf->isNullCheck($postData['CCINo'],'string');
                $CINo=$this->bsf->isNullCheck($postData['CINo'],'string');
                $this->_view->IssueRegisterId=$IssueRegisterId;
                $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');
                $this->_view->gridtype=$gridtype;
                $voucherno='';
                $Approve="";
                $Role="";
                if ($this->bsf->isNullCheck($IssueRegisterId, 'number') > 0) {
                    $Approve="E";
                    $Role="Issue-Modify";
                }else{
                    $Approve="N";
                    $Role="Issue-Create";
                }
                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where("CostCentreId=$CostCentre");
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];
                //CostCentre
                $CCIssue = CommonHelper::getVoucherNo(307, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                $this->_view->CCIssue = $CCIssue;

                //CompanyId
                $CIssue = CommonHelper::getVoucherNo(307, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                $this->_view->CIssue = $CIssue;

                if (isset($postData['IssueRegisterId']) && $postData['IssueRegisterId'] != 0) {
                    $IssueNo = $this->bsf->isNullCheck($this->params()->fromPost('IssueNo'), 'string');
                    $voucherNo = $postData['IssueNo'];
                    $voucherno=$voucherNo;
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
                    try{
                        $selPrevAnal=$sql->select();
                        $selPrevAnal->from(array("a"=>"MMS_IssueRegister"))
                            ->columns(array(new Expression("a.IssueOrReturn as Issue")))
                            ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
                        $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                        $Iss = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($Iss['Issue'] == '0'){
                            $issueName="I";
                        }else{
                            $issueName="R";
                        }
                        if($issueName=="I") {
                            $selPrevAnal=$sql->select();
                            $selPrevAnal->from(array("a"=>"MMS_IssueTrans"))
                                ->columns(array(new Expression("a.IssueAmount as IssueAmount,a.IssueTransId as IssueTransId,
                                a.IssueOrReturn as Issue,a.IssueQty As Qty,a.ResourceId as ResourceId,a.ItemId as ItemId")))
                                ->join(array("b"=>"mms_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
                                ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
                            $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                            $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($prevanal as $arrprevanal)
                            {
                                $updDecAnal=$sql->update();
                                $updDecAnal->table('mms_stock');
                                $updDecAnal->set(array(
                                    'IssueQty'=> new Expression('IssueQty+'.$arrprevanal['Qty'].''),
                                    'IssueAmount'=> new Expression('IssueAmount+'.$arrprevanal['IssueAmount'].''),
                                    'ClosingStock'=>new Expression('ClosingStock+'.$arrprevanal['Qty'].'')
                                ));
                                $updDecAnal->where(array('ItemId'=>$arrprevanal['ItemId']));
                                $updDecAnal->where(array('ResourceId'=>$arrprevanal['ResourceId']));
                                $updDecAnal->where(array('CostCentreId'=>$arrprevanal['CostCentreId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }

                            //Stock Tran Update
                            $selSTrans=$sql->select();
                            $selSTrans->from(array("a"=>"MMS_IssueWareHouseTrans"))
                                ->columns(array(new Expression("c.StockId,a.WareHouseId As WareHouseId,a.IssueQty as IssueQty")))
                                ->join(array("b"=>"MMS_IssueTrans"),"a.IssueTransId=b.IssueTransId",array(),$selSTrans::JOIN_INNER)
                                ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId",array(),$selSTrans::JOIN_INNER)
                                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
                            $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);

                            $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($tranwhtrans as $awh)
                            {
                                $updatewh = $sql -> update();
                                $updatewh->table('MMS_StockTrans');
                                $updatewh->set(array(
                                    'IssueQty'=>new Expression('IssueQty+'.$this->bsf->isNullCheck($awh['IssueQty'],'number') .''),
                                    'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($awh['IssueQty'],'number') .'')
                                ));
                                $updatewh->where(array('WareHouseId'=>$awh['WareHouseId']));
                                $updatewh->where(array('StockId'=>$awh['StockId']));
                                $updstocktransStatement = $sql->getSqlStringForSqlObject($updatewh);
                                $dbAdapter->query($updstocktransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $subQuery   = $sql->select();
                            $subQuery->from("mms_IssueTrans")
                                ->columns(array("IssueTransId"));
                            $subQuery->where(array('IssueRegisterId'=>$IssueRegisterId));

                            $delTVWh = $sql -> delete();
                            $delTVWh->from('MMS_IssueWareHouseTrans')
                                ->where->expression('IssueTransId IN ?',array($subQuery));
                            $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
                            $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql -> delete();
                            $delete->from('MMS_IssueWareHouseWbsTrans')
                                ->where->expression('IssueTransId IN ?',array($subQuery));
                            $deleteStatement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($deleteStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->delete();
                            $select->from('mms_IssueAnalTrans')
                                ->where->expression('IssueTransId IN ?',
                                    array($subQuery));
                            $WBSTransStatement = $sql->getSqlStringForSqlObject($select);
                            $register1 = $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //delete RequestTrans
                            $select = $sql->delete();
                            $select->from("mms_IssueTrans")
                                ->where(array('IssueRegisterId'=>$IssueRegisterId));
                            $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
                            $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                            //update RequestRegister
                            $Mod_date= date ("F d Y H:i:s");
                            $update = $sql->update();
                            $update->table('mms_IssueRegister');
                            $update->set(array(
                                'IssueDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['issueDate'],"date"))),
                                'IssueNo' => $voucherno,
                                'Narration' => $this->bsf->isNullCheck($postData['Notes'],'string'),
                                'ModifiedDate' => $Mod_date,
                                'IssueOrReturn' => $Iss['Issue']
                            ));
                            $update->where(array('IssueRegisterId'=>$IssueRegisterId));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $resTotal = $postData['rowid'];
                            for ($i = 1; $i < $resTotal; $i++) {
                                if (isset($postData['unitid_' . $i])) {
                                    $Qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');

                                    $requestInsert = $sql->insert('mms_issueTrans');
                                    $requestInsert->values(array(
                                        "IssueRegisterId" => $IssueRegisterId,
                                        "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                        "IssueQty" => $Qty,
                                        "IssueOrReturn" => $issueName,
                                        "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                        "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                        "IssueRate" => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'),
                                        "IssueAmount" => $this->bsf->isNullCheck($postData['amount_' . $i], 'number'),
                                        "FreeOrCharge" => $this->bsf->isNullCheck($postData['FC_' . $i], 'string'),
                                        "IssueTypeId" => $this->bsf->isNullCheck($postData['issueAccount_' . $i], 'number'),
                                        "PurchaseTypeId" => $this->bsf->isNullCheck($postData['PurchaseAccount_' . $i], 'number')
                                    ));
                                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                    $requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IssueTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $wbsTotal = $postData['iow_' . $i . '_rowid'];
                                    for ($j = 1; $j <= $wbsTotal; $j++) {
                                        if (($postData['iow_' . $i . '_qty_' . $j]) > 0) {
                                            $requestTransInsert = $sql->insert('mms_IssueAnalTrans');
                                            $requestTransInsert->values(array(
                                                "IssueTransId" => $IssueTransId,
                                                "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                "IssueQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number')
                                            ));

                                            $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                            $results = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $issueAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        }
                                        //warehouse-insert -edit
                                        $whTotal = $postData['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                        for ($wh = 1; $wh <= $whTotal; $wh++) {
                                            if ($this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                $whInsert = $sql->insert('MMS_IssueWareHouseWbsTrans');
                                                $whInsert->values(array("IssueTransId" => $IssueTransId, "IssueAnalTransId" => $issueAnalTransId,
                                                    "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "WareHouseId" => $postData['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                    "IssueQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                                ));
                                                $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                    $update = $sql->update();
                                    $update->table('mms_stock');
                                    $update->set(array(
                                        'IssueQty' => new Expression('IssueQty -' . $Qty),
                                        'IssueAmount' => new Expression('IssueAmount -' . $postData['amount_' . $i]),
                                        'ClosingStock' => new Expression('ClosingStock -' . $Qty),
                                    ));
                                    $update->where(array('ItemId' => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number')));
                                    $update->where(array('ResourceId' => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number')));
                                    $update->where(array('CostCentreId' => $CostCentre));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $stockId = 0;
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stock"))
                                        ->columns(array("StockId"))
                                        ->where(array("CostCentreId" => $CostCentre,
                                            "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number')
                                        ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $stockId = $this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                    //warehouse - issue stocktrans - add
                                    $fselect = $sql->select();
                                    $fselect->from(array("G" => "MMS_IssueWareHouseWbsTrans"))
                                        ->columns(array(new Expression("SUM(G.IssueQty) as IssueQty, G.WareHouseId as WareHouseId, G.IssueTransId as IssueTransId")))
                                        ->where(array("IssueTransId" => $IssueTransId));
                                    $fselect->group(array("G.WareHouseId", "G.IssueTransId"));
                                    $statement = $sql->getSqlStringForSqlObject($fselect);
                                    $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if (count($ware) > 0) {
                                        foreach($ware as $wareData) {

                                            if($wareData['IssueQty'] > 0) {
                                                $wInsert = $sql -> insert('MMS_IssueWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" =>$wareData['IssueTransId'],
                                                    "WareHouseId"=> $wareData['WareHouseId'],
                                                    "IssueQty" => $wareData['IssueQty']));
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
                                                        "IssueQty" => new Expression('IssueQty-' . $wareData['IssueQty']. ''),
                                                        "ClosingStock" => new Expression('ClosingStock-' . $wareData['IssueQty'] . '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId, "WareHouseId" => $wareData['WareHouseId']));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else {
                                                    if ($wareData['WareHouseId'] > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck(-$wareData['IssueQty'], 'number'),
                                                            "ClosingStock" => $this->bsf->isNullCheck(-$wareData['IssueQty'], 'number'),
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    } else{

                                        $waTotal = $this->bsf->isNullCheck($postData['wh_'.$i.'_rowid'],'number','');
                                        for($wa = 1; $wa <= $waTotal; $wa++){
                                            if($this->bsf->isNullCheck($postData['wh_'.$i.'_qty_'.$wa],'number','') > 0){

                                                $wInsert = $sql -> insert('MMS_IssueWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" => $IssueTransId,
                                                    "WareHouseId"=>$this->bsf->isNullCheck($postData['wh_'.$i.'_warehouseid_'.$wa],'number',''),
                                                    "IssueQty" => $this->bsf->isNullCheck($postData['wh_'.$i.'_qty_'.$wa],'number','')));
                                                $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                                $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                //stock trans update
                                                $stockSelect = $sql->select();
                                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                                    ->columns(array("StockId"))
                                                    ->where(array("WareHouseId" => $postData['wh_' . $i . '_warehouseid_' . $wa],
                                                        "StockId" => $stockId ));
                                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                                $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                if (count($sId) > 0) {
                                                    $sUpdate = $sql->update();
                                                    $sUpdate->table('mms_stockTrans');
                                                    $sUpdate->set(array(
                                                        "IssueQty" => new Expression('IssueQty-' . $this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number') . ''),
                                                        "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number') . '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId,"WareHouseId"=>$postData['wh_' . $i . '_warehouseid_' . $wa]));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else {
                                                    if ($this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number','') > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $postData['wh_' . $i . '_warehouseid_' . $wa],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck(-$postData['wh_' . $i . '_qty_' . $wa], 'number',''),
                                                            "ClosingStock" => $this->bsf->isNullCheck(-$postData['wh_' . $i . '_qty_' . $wa], 'number',''),
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    }// end of warehouse
                                }
                            }
                        }else{
                            $selects=$sql->select();
                            $selects->from(array("a"=>"mms_IssueReturnTrans"))
                                ->columns(array(new Expression("a.RIssueTransId as RIssueTransId,a.Qty as Qty,a.AdjustmentQty as AdjustmentQty")))
                                ->join(array("b"=>"mms_IssueTrans"),"a.IssueTransId=b.IssueTransId",array("IssueTransId"),$selects::JOIN_INNER)
                                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
                            $state = $sql->getSqlStringForSqlObject($selects);
                            $preview = $dbAdapter->query($state, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($preview as $arrive)
                            {
                                $updDecAnal=$sql->update();
                                $updDecAnal->table('mms_IssueTrans');
                                $updDecAnal->set(array(
                                    'ReturnQty'=> new Expression('ReturnQty-'.$arrive['Qty'].''),
                                    'AdjustmentQty'=>new Expression('AdjustmentQty-'.$arrive['AdjustmentQty'].'')
                                ));
                                $updDecAnal->where(array('IssueTransId'=>$arrive['RIssueTransId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $selPrevAnal=$sql->select();
                            $selPrevAnal->from(array("a"=>"MMS_IssueTrans"))
                                ->columns(array(new Expression("a.IssueTransId as IssueTransId,a.IssueQty As Qty,a.IssueAmount as Amount,a.ResourceId as ResourceId,a.ItemId as ItemId")))
                                ->join(array("b"=>"mms_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
                                ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
                            $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                            $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($prevanal as $arrprevanal)
                            {
                                $updDecAnal=$sql->update();
                                $updDecAnal->table('mms_stock');
                                $updDecAnal->set(array(
                                    'IssueQty'=> new Expression('IssueQty-'.$arrprevanal['Qty'].''),
                                    'ClosingStock'=>new Expression('ClosingStock-'.$arrprevanal['Qty'].''),
                                    'IssueAmount'=>new Expression('IssueAmount-'.$arrprevanal['Amount'].'')
                                ));
                                $updDecAnal->where(array('ItemId'=>$arrprevanal['ItemId']));
                                $updDecAnal->where(array('ResourceId'=>$arrprevanal['ResourceId']));
                                $updDecAnal->where(array('CostCentreId'=>$arrprevanal['CostCentreId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
//
//                                $selPrevAnal=$sql->select();
//                                $selPrevAnal->from(array("a"=>"MMS_IssueTrans"))
//                                    ->columns(array(new Expression("a.IssueTransId as IssueTransId,a.IssueAmount as IssueAmount,a.IssueQty As Qty,a.ResourceId as ResourceId,a.ItemId as ItemId")))
//                                    ->join(array("b"=>"mms_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
//                                    ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
//                                $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
//                                $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                                foreach($prevanal as $arrprevanal)
//                                {
//                                    $updDecAnal=$sql->update();
//                                    $updDecAnal->table('mms_stock');
//                                    $updDecAnal->set(array(
//                                        'IssueQty'=> new Expression('IssueQty-'.$arrprevanal['Qty'].''),
//                                        'IssueAmount'=> new Expression('IssueAmount-'.$arrprevanal['IssueAmount'].''),
//                                        'ClosingStock'=>new Expression('ClosingStock+'.$arrprevanal['Qty'].'')
//                                    ));
//                                    $updDecAnal->where(array('ItemId'=>$arrprevanal['ItemId']));
//                                    $updDecAnal->where(array('ResourceId'=>$arrprevanal['ResourceId']));
//                                    $updDecAnal->where(array('CostCentreId'=>$arrprevanal['CostCentreId']));
//                                    $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
//                                    $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                                }


                            $selects=$sql->select();
                            $selects->from(array("a"=>"mms_IssueReturnTrans"))
                                ->columns(array(new Expression("a.RIssueTransId as RIssueTransId,a.Qty as Qty,
                                a.AdjustmentQty as AdjustmentQty")))
                                ->join(array("b"=>"mms_IssueTrans"),"a.IssueTransId=b.IssueTransId",array("IssueTransId"),$selects::JOIN_INNER)
                                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
                            $state = $sql->getSqlStringForSqlObject($selects);
                            $preview = $dbAdapter->query($state, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($preview as $arrive)
                            {
                                $updDecAnal=$sql->update();
                                $updDecAnal->table('MMS_IssueWareHouseTrans');
                                $updDecAnal->set(array(
                                    'ReturnQty'=> new Expression('ReturnQty-'.$arrive['Qty'].''),
                                    'AdjustmentQty'=>new Expression('AdjustmentQty-'.$arrive['AdjustmentQty'].'')
                                ));
                                $updDecAnal->where(array('IssueTransId'=>$arrive['RIssueTransId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }


                            //Stock Tran Update
                            $selSTrans=$sql->select();
                            $selSTrans->from(array("a"=>"MMS_IssueWareHouseTrans"))
                                ->columns(array(new Expression("c.StockId,a.WareHouseId As WareHouseId,a.IssueQty as IssueQty")))
                                ->join(array("b"=>"MMS_IssueTrans"),"a.IssueTransId=b.IssueTransId",array(),$selSTrans::JOIN_INNER)
                                ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId",array(),$selSTrans::JOIN_INNER)
                                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
                            $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);
                            $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            foreach($tranwhtrans as $awh)
                            {
                                $updatewh = $sql -> update();
                                $updatewh->table('MMS_StockTrans');
                                $updatewh->set(array(
                                    'IssueQty'=>new Expression('IssueQty-'.$this->bsf->isNullCheck($awh['IssueQty'],'number') .''),
                                    'ClosingStock'=>new Expression('ClosingStock-'. $this->bsf->isNullCheck($awh['IssueQty'],'number') .'')
                                ));
                                $updatewh->where(array('StockId'=>$awh['StockId']));
                                $updatewh->where(array('WareHouseId'=>$awh['WareHouseId']));
                                $updstocktransStatement = $sql->getSqlStringForSqlObject($updatewh);
                                $dbAdapter->query($updstocktransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }


                            $subQuery   = $sql->select();
                            $subQuery->from("mms_IssueTrans")
                                ->columns(array("IssueTransId"));
                            $subQuery->where(array('IssueRegisterId'=>$IssueRegisterId));

                            $subQuery1  = $sql->select();
                            $subQuery1->from("mms_IssueReturnTrans")
                                ->columns(array("RIssueTransId"));
                            $subQuery1->where(array('IssueTransId'=>$subQuery));

                            $delTVWh = $sql -> delete();
                            $delTVWh->from('MMS_IssueWareHouseTrans')
                                ->where->expression('IssueTransId IN ?',array($subQuery));
                            $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
                            $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $deleteW = $sql -> delete();
                            $deleteW->from('MMS_ReturnWareHouseTrans')
                                ->where->expression('IssueTransId IN ?',array($subQuery1));
                            $deleteWStatement = $sql->getSqlStringForSqlObject($deleteW);
                            $dbAdapter->query($deleteWStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $deleteW = $sql -> delete();
                            $deleteW->from('MMS_IssueWareHouseReturnTrans')
                                ->where->expression('IssueTransId IN ?',array($subQuery));
                            $deleteWStatement = $sql->getSqlStringForSqlObject($deleteW);
                            $dbAdapter->query($deleteWStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->delete();
                            $select->from('mms_IssueReturnTrans')
                                ->where->expression('IssueTransId IN ?',
                                    array($subQuery));
                            $WBSTransStatement = $sql->getSqlStringForSqlObject($select);
                            $register1 = $dbAdapter->query($WBSTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->delete();
                            $select->from("mms_IssueTrans")
                                ->where(array('IssueRegisterId'=>$IssueRegisterId));
                            $ReqTransStatement = $sql->getSqlStringForSqlObject($select);
                            $register2 = $dbAdapter->query($ReqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //update RequestRegister


                            $Mod_date= date ("F d Y H:i:s");
                            $update = $sql->update();
                            $update->table('mms_IssueRegister');
                            $update->set(array(
                                'IssueDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['issueDate'],"date"))),
                                'IssueNo' => $voucherNo,
                                'Narration' => $this->bsf->isNullCheck($postData['Notes'],'string'),
                                'ModifiedDate' => $Mod_date,
                                'IssueOrReturn' => $Iss['Issue']
                            ));
                            $update->where(array('IssueRegisterId'=>$IssueRegisterId));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $resTotal = $postData['rowid'];
                            for ($i = 1; $i < $resTotal; $i++) {
                                if (isset($postData['unitid_' . $i])) {
                                    $ResourceId = $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number');
                                    $ItemId = $this->bsf->isNullCheck($postData['itemid_' . $i], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                    $requestInsert = $sql->insert('mms_issueTrans');
                                    $requestInsert->values(array(
                                        "IssueRegisterId" => $IssueRegisterId,
                                        "ResourceId" => $ResourceId,
                                        "ItemId" => $ItemId,
                                        "IssueQty" => $Qty,
                                        "IssueOrReturn" => $issueName,
                                        "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                        "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                        "IssueRate" => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'),
                                        "IssueAmount" => $this->bsf->isNullCheck($postData['amount_' . $i], 'number'),
                                        "FreeOrCharge" => $this->bsf->isNullCheck($postData['FC_' . $i], 'string'),
                                        "IssueTypeId" => $this->bsf->isNullCheck($postData['issueAccount_' . $i], 'number'),
                                        "PurchaseTypeId" => $this->bsf->isNullCheck($postData['PurchaseAccount_' . $i], 'number'),
                                    ));

                                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                    $requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IssueTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                    $wbsTotal = $postData['iow_' . $i . '_rowid'];
                                    for ($j = 1; $j <= $wbsTotal; $j++) {
                                        if (($postData['iow_' . $i . '_ReturnQty_' . $j]) > 0) {
                                            $TransId=$postData['iow_' . $i . '_TransId_' . $j];
                                            $requestTransInsert = $sql->insert('mms_IssueReturnTrans');
                                            $requestTransInsert->values(array(
                                                "IssueTransId" => $IssueTransId,
                                                "RIssueTransId" => $postData['iow_' . $i . '_TransId_' . $j],
                                                "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j], 'number'),
                                                "AdjustmentQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_AdjustmentQty_' . $j], 'number')
                                            ));
                                            $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                            $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $issueReturnTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $update = $sql->update();
                                            $update->table('mms_issueTrans');
                                            $update->set(array(
                                                "ReturnQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j], 'number'),
                                                "AdjustmentQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_AdjustmentQty_' . $j], 'number'),
                                            ));
                                            $update->where(array('IssueTransId' => $TransId));
                                            $requestTransStatement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            //warehouse-insert -add
                                            $iwhTotal = $postData['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                            for ($wh = 1; $wh <= $iwhTotal; $wh++) {
                                                if ($this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                    $whInsert = $sql->insert('MMS_IssueWareHouseReturnTrans');
                                                    $whInsert->values(array("IssueTransId" => $IssueTransId,
                                                        "RIssueTransId" => $postData['iow_' . $i . '_TransId_' . $j],
                                                        "IssueReturnTransId" => $issueReturnTransId,
                                                        "WareHouseId" => $postData['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                        "ReturnQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . ''),
                                                        "AdjustmentQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_adqty_' . $wh], 'number' . '')
                                                    ));
                                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }

                                        }
                                        $update = $sql->update();
                                        $update->table('mms_stock');
                                        $update->set(array(
                                            'IssueQty' => new Expression('IssueQty +' . $postData['iow_' . $i . '_ReturnQty_' . $j]),
                                            'IssueAmount' => new Expression('IssueAmount +' . $postData['amount_' . $i]),
                                            'ClosingStock' => new Expression('ClosingStock +' . $postData['iow_' . $i . '_ReturnQty_' . $j])
                                        ));
                                        $update->where(array('ItemId' => $ItemId));
                                        $update->where(array('ResourceId' => $ResourceId));
                                        $update->where(array('CostCentreId' => $CostCentre));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                    $stockId=0;

                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stock"))
                                        ->columns(array("StockId"))
                                        ->where(array("CostCentreId" => $CostCentre,
                                            "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number')
                                        ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $stockId=$this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                    //warehouse
                                    $fselect = $sql->select();
                                    $fselect->from(array("G" => "MMS_IssueWareHouseReturnTrans"))
                                        ->columns(array(new Expression("SUM(G.ReturnQty) as ReturnQty,
                                        SUM(G.AdjustmentQty) as  AdjustmentQty, G.WareHouseId as WareHouseId,
                                        G.IssueTransId as IssueTransId,
                                        G.RIssueTransId as RIssueTransId")))
                                        ->where(array("IssueTransId" => $IssueTransId));
                                    $fselect->group(array("G.WareHouseId","G.IssueTransId","G.RIssueTransId"));
                                    $statement = $sql->getSqlStringForSqlObject($fselect);
                                    $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($ware) > 0) {
                                        foreach($ware as $wareData) {
                                            if ($wareData['ReturnQty'] > 0) {

                                                $wInsert = $sql->insert('MMS_ReturnWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" => $wareData['RIssueTransId'],
                                                    "WareHouseId" => $wareData['WareHouseId'],
                                                    "ReturnQty" => $wareData['ReturnQty']));
                                                $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                                $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $update = $sql->update();
                                                $update->table('MMS_IssueWareHouseTrans');
                                                $update->set(array(
                                                    "ReturnQty" => $wareData['ReturnQty'],
                                                    "AdjustmentQty" => $wareData['AdjustmentQty']
                                                ));
                                                $update->where(array('IssueTransId' => $wareData['RIssueTransId']));
                                                $update->where(array('WareHouseId' => $wareData['WareHouseId']));
                                                $updateStatement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

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
                                                        "IssueQty" => new Expression('IssueQty+' . $wareData['ReturnQty'] . ''),
                                                        "ClosingStock" => new Expression('ClosingStock+' . $wareData['ReturnQty']. '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId, "WareHouseId" => $wareData['WareHouseId']));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else {
                                                    if ($wareData['ReturnQty'] > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck($wareData['ReturnQty'], 'number'),
                                                            "ClosingStock" => $this->bsf->isNullCheck(+$wareData['ReturnQty'], 'number'),
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        } // end of warehouse
                                    }
                                }
                            }

                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Issue',$IssueRegisterId,$CostCentre,$CompanyId, 'MMS',$voucherno,$this->auth->getIdentity()->UserId,0,0);
                        // $this->redirect()->toRoute('mms/issue-register', array('controller' => 'issue', 'action' => 'issue-register',));
                        $this->redirect()->toRoute('mms/default', array('controller' => 'issue', 'action' => 'issue-register',));
                        //  $this->redirect()->toRoute('mms/default', array('controller' => 'transfer', 'action' => 'display-register', 'rid' => $TVRegisterId));
                    }
                    catch (PDOException $e) {
                        $connection->rollback();
                        print "Error!: " . $e->getMessage() . "</br>";
                    }
                }
                else{
                    if ($vNo['genType']) {
                        $voucher = CommonHelper::getVoucherNo(307, date('Y/m/d', strtotime($postData['issue_date'])), 0, 0, $dbAdapter, "I");
                        $voucherno = $voucher['voucherNo'];
                    } else {
                        $voucherno = $postData['voucherNo'];
                    }

                    if ($CCIssue['genType']==1) {
                        $voucher = CommonHelper::getVoucherNo(307, date('Y/m/d', strtotime($postData['issue_date'])), 0, $CostCentre, $dbAdapter, "I");
                        $CCINo = $voucher['voucherNo'];
                    } else {
                        $CCINo = $CCINo;
                    }

                    if ($CIssue['genType']==1) {
                        $voucher = CommonHelper::getVoucherNo(307, date('Y/m/d', strtotime($postData['issue_date'])), $CompanyId, 0, $dbAdapter, "I");
                        $CINo = $voucher['voucherNo'];
                    } else {
                        $CINo = $CINo;
                    }

                    $CostCentre= $postData['CostCentre'];
                    $IssueNo = $this->bsf->isNullCheck($this->params()->fromPost('IssueNo'), 'string');
                    $contractor = $postData['contractor'];
                    $issue_type = $postData['issue_type'];
                    $issue = $postData['issue'];
                    $OwnOrCsm = $postData['OwnOrCsm'];
                    $Notes = $postData['Notes'];
                    $IssueRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
                    if($issue=="return"){
                        $issueNum=1;
                    }else{
                        $issueNum=0;
                    }

                    try {
                        $issue_date=date('Y-m-d',strtotime($postData['issueDate']));
                        $registerInsert = $sql->insert('mms_issueregister');
                        $registerInsert->values(array(
                            "CostCentreId" => $CostCentre,
                            "CCIssueNo" => $CCINo,
                            "CIssueNo" => $CINo,
                            "Approve" => 'N',
                            "IssueNo" => $IssueNo,
                            "ContractorId" => $contractor,
                            "OwnOrCsm" => $OwnOrCsm,
                            "IssueDate" => $issue_date,
                            "IssueType" => $issue_type,
                            "IssueOrReturn" => $issueNum,
                            "Narration"=>$Notes,
                            "GridType" => $gridtype
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $IssueRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if($issue=="return"){
                            $issueName="R";
                        }else{
                            $issueName="I";
                        }

                        $resTotal = $postData['rowid'];
                        if($issue=="issue") {
                            for ($i = 1; $i < $resTotal; $i++) {
                                if (isset($postData['unitid_' . $i])) {
//                                    echo"<pre>";
//                                    print_r($postData);
//                                    echo"</pre>";
                                    //die;
                                    $ResourceId = $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number');
                                    $ItemId = $this->bsf->isNullCheck($postData['itemid_' . $i], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                    $requestInsert = $sql->insert('mms_issueTrans');
                                    $requestInsert->values(array(
                                        "IssueRegisterId" => $IssueRegisterId,
                                        "ResourceId" => $ResourceId,
                                        "ItemId" => $ItemId,
                                        "IssueQty" => $Qty,
                                        "IssueOrReturn" => $issueName,
                                        "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number'),
                                        "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string'),
                                        "IssueRate" => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'),
                                        "IssueAmount" => $this->bsf->isNullCheck($postData['amount_' . $i], 'number'),
                                        "FreeOrCharge" => $this->bsf->isNullCheck($postData['FC_' . $i], 'string'),
                                        "IssueTypeId" => $this->bsf->isNullCheck($postData['issueAccount_' . $i], 'number'),
                                        "PurchaseTypeId" => $this->bsf->isNullCheck($postData['PurchaseAccount_' . $i], 'number'),
                                    ));

                                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                    $requestResults = $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $issueTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $wbsTotal = $postData['iow_' . $i . '_rowid'];
                                    for ($j = 1; $j <= $wbsTotal; $j++) {
                                        if (($postData['iow_' . $i . '_qty_' . $j]) > 0) {
                                            $requestTransInsert = $sql->insert('mms_IssueAnalTrans');
                                            $requestTransInsert->values(array(
                                                "IssueTransId" => $issueTransId,
                                                "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                "IssueQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number')
                                            ));
                                            $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                            $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $issueAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        }
                                        //warehouse-insert -add
                                        $whTotal = $postData['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                        for ($wh = 1; $wh <= $whTotal; $wh++) {
                                            if ($this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                $whInsert = $sql->insert('MMS_IssueWareHouseWbsTrans');
                                                $whInsert->values(array("IssueTransId" => $issueTransId, "IssueAnalTransId" => $issueAnalTransId,
                                                    "AnalysisId" => $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number'),
                                                    "WareHouseId" => $postData['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                    "IssueQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                                ));
                                                $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                    $update = $sql->update();
                                    $update->table('mms_stock');
                                    $update->set(array(
                                        'IssueQty' => new Expression('IssueQty -' . $Qty),
                                        'IssueAmount' => new Expression('IssueAmount -' . $this->bsf->isNullCheck($postData['amount_' . $i], 'number')),
                                        'ClosingStock' => new Expression('ClosingStock -' . $Qty),
                                    ));
                                    $update->where(array('ItemId' => $ItemId));
                                    $update->where(array('ResourceId' => $ResourceId));
                                    $update->where(array('CostCentreId' => $CostCentre));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $stockId=0;
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stock"))
                                        ->columns(array("StockId"))
                                        ->where(array("CostCentreId" => $CostCentre,
                                            "ResourceId" => $ResourceId,
                                            "ItemId" => $ItemId
                                        ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $stockId=$this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                    //warehouse - issue stocktrans - add

                                    $fselect = $sql->select();
                                    $fselect->from(array("G" => "MMS_IssueWareHouseWbsTrans"))
                                        ->columns(array(new Expression("SUM(G.IssueQty) as IssueQty, G.WareHouseId as WareHouseId, G.IssueTransId as IssueTransId")))
                                        ->where(array("IssueTransId" => $issueTransId));
                                    $fselect->group(array("G.WareHouseId","G.IssueTransId"));
                                    $statement = $sql->getSqlStringForSqlObject($fselect);
                                    $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($ware) > 0){
                                        foreach($ware as $wareData){
                                            if($wareData['IssueQty'] > 0) {

                                                $wInsert = $sql -> insert('MMS_IssueWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" =>$wareData['IssueTransId'],
                                                    "WareHouseId"=> $wareData['WareHouseId'],
                                                    "IssueQty" => $wareData['IssueQty']));
                                                $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                                $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                //stock trans update
                                                $stockSelect = $sql->select();
                                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                                    ->columns(array("StockId"))
                                                    ->where(array("WareHouseId" => $wareData['WareHouseId'],"StockId" => $stockId ));
                                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                                $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                if (count($sId) > 0) {
                                                    $sUpdate = $sql->update();
                                                    $sUpdate->table('mms_stockTrans');
                                                    $sUpdate->set(array(
                                                        "IssueQty" => new Expression('IssueQty-' . $wareData['IssueQty'] . ''),
                                                        "ClosingStock" => new Expression('ClosingStock-' . $wareData['IssueQty'] . '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId,"WareHouseId"=>$wareData['WareHouseId']));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                                else {
                                                    if ($wareData['IssueQty'] > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck(-$wareData['IssueQty'], 'number'),
                                                            "ClosingStock" => $this->bsf->isNullCheck(-$wareData['IssueQty'], 'number')
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        $waTotal = $this->bsf->isNullCheck($postData['wh_'.$i.'_rowid'],'number');
                                        for($wa = 1; $wa <= $waTotal; $wa++){
                                            if($this->bsf->isNullCheck($postData['wh_'.$i.'_qty_'.$wa],'number','') > 0){

                                                $wInsert = $sql -> insert('MMS_IssueWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" => $issueTransId,
                                                    "WareHouseId"=>$this->bsf->isNullCheck($postData['wh_'.$i.'_warehouseid_'.$wa],'number',''),
                                                    "IssueQty" => $this->bsf->isNullCheck($postData['wh_'.$i.'_qty_'.$wa],'number','')));
                                                $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                                $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                //stock trans update
                                                $stockSelect = $sql->select();
                                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                                    ->columns(array("StockId"))
                                                    ->where(array("WareHouseId" => $postData['wh_' . $i . '_warehouseid_' . $wa],
                                                        "StockId" => $stockId ));
                                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                                $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                if (count($sId) > 0) {
                                                    $sUpdate = $sql->update();
                                                    $sUpdate->table('mms_stockTrans');
                                                    $sUpdate->set(array(
                                                        "IssueQty" => new Expression('IssueQty-' . $this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number') . ''),
                                                        "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number') . '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId,"WareHouseId"=>$postData['wh_' . $i . '_warehouseid_' . $wa]));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else {
                                                    if ($this->bsf->isNullCheck($postData['wh_' . $i . '_qty_' . $wa], 'number','') > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $postData['wh_' . $i . '_warehouseid_' . $wa],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck(-$postData['wh_' . $i . '_qty_' . $wa], 'number',''),
                                                            "ClosingStock" => $this->bsf->isNullCheck(-$postData['wh_' . $i . '_qty_' . $wa], 'number',''),
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    } // end of the warehouse
                                }
                            }
                        }else{
                            for ($i = 1; $i < $resTotal; $i++) {
                                if (isset($postData['unitid_' . $i])) {
//                                    echo"<pre>";
//                                    print_r($postData);
//                                    echo"</pre>";die;
                                    $ResourceId = $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number');
                                    $ItemId = $this->bsf->isNullCheck($postData['itemid_' . $i], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                    $requestInsert = $sql->insert('mms_issueTrans');
                                    $requestInsert->values(array(
                                        "IssueRegisterId" => $IssueRegisterId,
                                        "ResourceId" => $ResourceId,
                                        "ItemId" => $ItemId,
                                        "IssueQty" => $Qty,
                                        "IssueOrReturn" => $issueName,
                                        "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number',''),
                                        "Remarks" => $this->bsf->isNullCheck($postData['remarks_' . $i], 'string',''),
                                        "IssueRate" => $this->bsf->isNullCheck($postData['rate_' . $i], 'number',''),
                                        "IssueAmount" => $this->bsf->isNullCheck($postData['amount_' . $i], 'number',''),
                                        "FreeOrCharge" => $this->bsf->isNullCheck($postData['FC_' . $i], 'string',''),
                                        "IssueTypeId" => $this->bsf->isNullCheck($postData['issueAccount_' . $i], 'number',''),
                                        "PurchaseTypeId" => $this->bsf->isNullCheck($postData['PurchaseAccount_' . $i], 'number',''),
                                    ));

                                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                    $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IssueTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $wbsTotal = $postData['iow_' . $i . '_rowid'];
                                    for ($j = 1; $j <= $wbsTotal; $j++) {
                                        if (($postData['iow_' . $i . '_ReturnQty_' . $j]) > 0) {
                                            $TransId=$postData['iow_' . $i . '_TransId_' . $j];
                                            $requestTransInsert = $sql->insert('mms_IssueReturnTrans');
                                            $requestTransInsert->values(array(
                                                "IssueTransId" => $IssueTransId,
                                                "RIssueTransId" => $TransId,
                                                "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number'),
                                                "Qty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j], 'number'),
                                                "AdjustmentQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_AdjustmentQty_' . $j], 'number')
                                            ));
                                            $requestTransStatement = $sql->getSqlStringForSqlObject($requestTransInsert);
                                            $results = $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $issueReturnTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $update = $sql->update();
                                            $update->table('mms_issueTrans');
                                            $update->set(array(
                                                "ReturnQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j], 'number'),
                                                "AdjustmentQty" => $this->bsf->isNullCheck($postData['iow_' . $i . '_AdjustmentQty_' . $j], 'number'),
                                            ));
                                            $update->where(array('IssueTransId' => $TransId));
                                            $requestTransStatement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($requestTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            //warehouse-insert -add
                                            $iwhTotal = $postData['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                            for ($wh = 1; $wh <= $iwhTotal; $wh++) {
                                                if ($this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                    $whInsert = $sql->insert('MMS_IssueWareHouseReturnTrans');
                                                    $whInsert->values(array("IssueTransId" => $IssueTransId,
                                                        "RIssueTransId" => $postData['iow_' . $i . '_TransId_' . $j],
                                                        "IssueReturnTransId" => $issueReturnTransId,
                                                        "WareHouseId" => $postData['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                        "ReturnQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . ''),
                                                        "AdjustmentQty" => $this->bsf->isNullCheck($postData['wh_' . $i . '_wbs_' . $j . '_adqty_' . $wh], 'number' . '')
                                                    ));
                                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }

                                        }
                                        $update = $sql->update();
                                        $update->table('mms_stock');
                                        $update->set(array(
                                            'IssueQty' => new Expression('IssueQty +' . $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j],'number','')),
                                            'IssueAmount' => new Expression('IssueAmount +' . $this->bsf->isNullCheck($postData['amount_' . $i],'number')),
                                            'ClosingStock' => new Expression('ClosingStock +' . $this->bsf->isNullCheck($postData['iow_' . $i . '_ReturnQty_' . $j],'number',''))
                                        ));
                                        $update->where(array('ItemId' => $ItemId));
                                        $update->where(array('ResourceId' => $ResourceId));
                                        $update->where(array('CostCentreId' => $CostCentre));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                    $stockId=0;

                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stock"))
                                        ->columns(array("StockId"))
                                        ->where(array("CostCentreId" => $CostCentre,
                                            "ResourceId" => $ResourceId,
                                            "ItemId" => $ItemId
                                        ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $stockId=$this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                    //warehouse
                                    $fselect = $sql->select();
                                    $fselect->from(array("G" => "MMS_IssueWareHouseReturnTrans"))
                                        ->columns(array(new Expression("SUM(G.ReturnQty) as ReturnQty, SUM(G.AdjustmentQty) as  AdjustmentQty,
                                        G.WareHouseId as WareHouseId,
                                        G.IssueTransId as IssueTransId,
                                        G.RIssueTransId as RIssueTransId")))
                                        ->where(array("IssueTransId" => $IssueTransId));
                                    $fselect->group(array("G.WareHouseId","G.RIssueTransId","G.IssueTransId"));
                                    $statement = $sql->getSqlStringForSqlObject($fselect);
                                    $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($ware) > 0) {
                                        foreach($ware as $wareData) {

                                            if ($wareData['ReturnQty'] > 0) {
                                                $wInsert = $sql->insert('MMS_ReturnWareHouseTrans');
                                                $wInsert->values(array("IssueTransId" => $wareData['RIssueTransId'],
                                                    "WareHouseId" => $wareData['WareHouseId'],
                                                    "ReturnQty" => $wareData['ReturnQty']));
                                                $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                                $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $update = $sql->update();
                                                $update->table('MMS_IssueWareHouseTrans');
                                                $update->set(array(
                                                    "ReturnQty" => $wareData['ReturnQty'],
                                                    "AdjustmentQty" => $wareData['AdjustmentQty']
                                                ));
                                                $update->where(array('IssueTransId' => $wareData['RIssueTransId']));
                                                $update->where(array('WareHouseId' => $wareData['WareHouseId']));
                                                $updateStatement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

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
                                                        "IssueQty" => new Expression('IssueQty+' . $wareData['ReturnQty'] . ''),
                                                        "ClosingStock" => new Expression('ClosingStock+' . $wareData['ReturnQty']. '')
                                                    ));
                                                    $sUpdate->where(array("StockId" => $stockId, "WareHouseId" => $wareData['WareHouseId']));
                                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else {
                                                    if ($wareData['ReturnQty'] > 0) {
                                                        $stock1 = $sql->insert('mms_stockTrans');
                                                        $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                            "StockId" => $stockId,
                                                            "IssueQty" => $this->bsf->isNullCheck($wareData['ReturnQty'], 'number',''),
                                                            "ClosingStock" => $this->bsf->isNullCheck(+$wareData['ReturnQty'], 'number',''),
                                                        ));
                                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    }  // end of the warehouse
                                }
                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Issue',$IssueRegisterId,$CostCentre,$CompanyId, 'MMS',$voucherno,$this->auth->getIdentity()->UserId,0,0);
                        //$this->redirect()->toRoute('ats/default', array('controller' => 'index','action' => 'display-register'));
                        $this->redirect()->toRoute('mms/default', array('controller' => 'issue', 'action' => 'issue-register'));
                        // $this->redirect()->toRoute('mms/resource-item', array('controller' => 'master', 'action' => 'resource-item'));
                    } catch (PDOException $e) {
                        $connection->rollback();
                        print "Error!: " . $e->getMessage() . "</br>";
                    }
                }            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->_view->IssueRegisterId=$IssueRegisterId;
            return $this->_view;
        }
    }
    public function issueDetailedAction(){
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
        $IssueRegisterId = $this->params()->fromRoute('rid');
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if ($request->isPost()){

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
		// get request
		$selReqReg=$sql->select();
		$selReqReg->from(array('a' => 'mms_issueRegister'))
			->where("a.IssueRegisterId=$IssueRegisterId");
		$statement = $sql->getSqlStringForSqlObject( $selReqReg );
		$this->_view->reqregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$Approve = $this->_view->reqregister['Approve'];
		$Narration = $this->_view->reqregister['Narration'];
		$gridtype = $this->_view->reqregister['GridType'];
        $contractor =$this->_view->reqregister['ContractorId'];
		$this->_view->Narration = $Narration;
		$this->_view->Approve = $Approve;
		$this->_view->gridtype = $gridtype;


        $selCC = $sql->select();
        $selCC->from(array('a' => 'Vendor_Master'))
                ->columns(array('VendorName'))
                ->where("a.VendorId=$contractor");
        $statement = $sql->getSqlStringForSqlObject($selCC);
        $ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(count($ccname) > 0){
            $this->_view->contractorName = $ccname['VendorName'];
        } else {
            $this->_view->contractorName = '';
        }

		$selReqReg=$sql->select();
		$selReqReg->from(array('a' => 'mms_issueTrans'))
			->where("a.IssueRegisterId=$IssueRegisterId");
		$statement = $sql->getSqlStringForSqlObject( $selReqReg );
		$this->_view->reqregist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$CostCentre = $this->_view->reqregister['CostCentreId'];
		$ccino = $this->_view->reqregister['CCIssueNo'];
		$cino = $this->_view->reqregister['CIssueNo'];
		$ResourceId = $this->_view->reqregist['ResourceId'];
		$ItemId = $this->_view->reqregist['ItemId'];
		$IssueNo = $this->_view->reqregister['IssueNo'];
		$issue_date =$this->_view->reqregister['IssueDate'];
		$issue_dates=strtotime($issue_date);
		$issue_datess=date('d-m-Y', $issue_dates);
		$this->_view->issue_datess=$issue_datess;

		$issue_type =$this->_view->reqregister['IssueType'];
		$this->_view->CostCentre = $CostCentre;
		$this->_view->CCINo = $ccino;
		$this->_view->CINo = $cino;
		$this->_view->issue_date = $issue_date;
		$this->_view->IssueNo=$IssueNo;
		$this->_view->contractor=$contractor;
		$selCC = $sql->select();
		$selCC->from(array('a' => 'WF_OperationalCostCentre'))
			->columns(array('CostCentreName'))
			->where("a.CostCentreId=".$CostCentre);
		$statement = $sql->getSqlStringForSqlObject($selCC);
		$ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->ProjectName=$ccname['CostCentreName'];
		if($issue_type==2) {
			$selCC = $sql->select();
			$selCC->from(array('a' => 'Vendor_Master'))
				->columns(array('VendorName'))
				->where("a.VendorId=$contractor");
			$statement = $sql->getSqlStringForSqlObject($selCC);
			$ccname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->ContractorName = $ccname['VendorName'];
		}
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"mms_issueRegister"))
			->columns(array(new Expression("Case When a.IssueType=1 Then 'Internal' Else' Contractor' End as IssueType,Case When a.IssueOrReturn=0 Then 'issue' Else 'return' End as Type")))
			->where(array("a.IssueRegisterId"=>$IssueRegisterId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest);
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->issue_typename=$results['IssueType'];
		$issue=$results['Type'];
		$this->_view->issue=$issue;


		$select = $sql->select();
		$select-> from(array('a' => 'mms_purchasetype'))
			->columns(array(new Expression("a.AccountId as TypeId,b.AccountName as Typename")))
			->join(array('b' => 'FA_AccountMaster'),'a.AccountId=b.AccountId',array(),$select::JOIN_INNER)
			->where(array('a.sel'=>"1"));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->arr_accountType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array('a' => 'MMS_IssueTrans'))
			->columns(array(new Expression("a.ResourceId,a.ItemId,Case When a.ItemId>0
			 Then d.ItemCode Else b.Code End As Code,Case When a.ItemId>0 Then
			 d.BrandName Else b.ResourceName End As ResourceName,c.ResourceGroupName,
			 c.ResourceGroupId,f.UnitName,a.UnitId,CAST(a.IssueQty As Decimal(18,3)) As Qty,CAST(a.IssueQty As Decimal(18,3)) As HiddenQty,CAST(a.IssueRate As Decimal(18,2)) as rate,CAST(a.IssueAmount As Decimal(18,2)) as Amount,a.Remarks as Remarks,a.PurchaseTypeId as PurchaseAccount,a.IssueTypeId as issueAccount,a.FreeOrCharge as FreeOrCharge")))
			->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId ', array(), $select:: JOIN_LEFT)
			->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
			->join(array('d' => 'MMS_Brand'), 'a.ItemId=d.BrandId And a.ResourceId=d.ResourceId ', array(), $select::JOIN_LEFT)
			->join(array('e' => 'MMS_IssueRegister'), 'a.IssueRegisterId=e.IssueRegisterId', array(), $select::JOIN_LEFT)
			->join(array('f' => 'Proj_UOM'), 'a.UnitId=f.UnitId ' )
			->where("e.CostCentreId=" . $CostCentre . " and e.IssueRegisterId=".$IssueRegisterId."");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$subWbs1=$sql->select();
		$subWbs1->from(array('a'=>'Proj_WBSMaster'))
			->columns(array(new Expression("0 As ResourceId,0 As ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
			->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$subWbs1::JOIN_INNER)
			->where -> expression('a.LastLevel=1 And b.CostCentreId='.$CostCentre.' And a.WBSId NOT IN (Select AnalysisId From mms_IssueAnalTrans Where IssueTransId IN (Select IssueTransId From MMS_IssueTrans Where IssueRegisterId=?))',$IssueRegisterId);
		if($issue=="issue") {
			$wbsSelect = $sql->select();
			$wbsSelect->from(array('a' => 'Proj_WBSMaster'))
				->columns(array(new Expression("c.ResourceId,C.ItemId,a.WBSId,a.ParentText+'=>'+a.WbsName As WbsName,CAST(b.IssueQty As Decimal(18,3)) As Qty,CAST(0 As Decimal(18,3)) As HiddenQty")))
				->join(array('b' => 'mms_IssueAnalTrans'), 'a.WbsId=b.AnalysisId', array(), $wbsSelect::JOIN_INNER)
				->join(array('c' => 'mms_issueTrans'), 'b.IssueTransId=c.IssueTransId', array(), $wbsSelect::JOIN_INNER)
				->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsSelect::JOIN_INNER)
				->where(array("a.LastLevel" => "1", "d.CostCentreId" => $CostCentre, "c.IssueRegisterId" => $IssueRegisterId));
			$wbsSelect->combine($subWbs1, 'Union ALL');
			$statement = $sql->getSqlStringForSqlObject($wbsSelect);
			$this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		else {
			$select = $sql->select();
			$select->from(array('A' => 'MMS_IssueRegister'))
				//->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
				->columns(array(new Expression("E.IssueRegisterId,D.IssueTransId,C.IRetTransId,A.ContractorId VendorId,A.IssueRegisterId ARegisterId,
								 B.IssueTransId AIssueTransId,A.CostCentreId,B.ResourceId,B.ItemId,A.IssueNo,CONVERT(Varchar(10),A.IssueDate,103) IssueDate,
								 Case When (B.TFactor>0 And B.FFactor>0) Then CAST((ISNULL((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
								 ( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
								 Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
								 Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,CAST(B.IssueRate As Decimal(18,2)) Rate,
								 Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.Qty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.Qty As Decimal(18,3)) End CurrentQty,
								 Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.AdjustmentQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.AdjustmentQty As Decimal(18,3)) End AdjustmentQty,
								 Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.Qty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.Qty As Decimal(18,3)) End HiddenQty,
								 Case When (B.TFactor>0 And B.FFactor>0) Then CAST( (isnull((C.AdjustmentQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(C.AdjustmentQty As Decimal(18,3)) End HAdjustmentQty,
								 B.UnitId  ")))
				->join(array('B' => 'MMS_IssueTrans'), ' A.IssueRegisterId=B.IssueRegisterId', array(), $select:: JOIN_INNER)
				->join(array('C' => 'MMS_IssueReturnTrans'), 'B.IssueTransId=C.RIssueTransId ', array(), $select:: JOIN_INNER)
				->join(array('D' => 'MMS_IssueTrans'), 'C.IssueTransId=D.IssueTransId ', array(), $select:: JOIN_INNER)
				->join(array('E' => 'MMS_IssueRegister'), 'D.IssueRegisterId=E.IssueRegisterId ', array(), $select:: JOIN_INNER)
				->where(" A.OWNOrCSM=0 And E.OWNOrCSM=0 And A.IssueOrReturn=0 And E.IssueOrReturn=1 And A.Approve='Y'
						 And A.CostCentreId= $CostCentre   And E.IssueRegisterId= $IssueRegisterId And A.ContractorId=$contractor Union All
						 Select 0 IssueRegisterId, 0 IssueTransId,0 IRetTransId, A.ContractorId VendorId,A.IssueRegisterId ARegisterId,B.IssueTransId AIssueTransId,A.CostCentreId,
						 B.ResourceId,B.ItemId, A.IssueNo,Convert(Varchar(10),A.IssueDate,103) [IssueDate],
						 Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty * B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST(B.IssueQty As Decimal(18,3)) End Qty,
						( Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else B.IssueQty End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3)) Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) BalQty,
						CAST(B.IssueRate As Decimal(18,2)) Rate, CAST(0 As Decimal(18,3)) CurrentQty,CAST(0 As Decimal(18,3)) AdjustmentQty,CAST(0 As Decimal(18,3)) HiddenQty,CAST(0 As Decimal(18,3)) HAdjustmentQty,B.TUnitId UnitId  from MMS_IssueRegister A
						Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId
						Where  A.CostCentreId=$CostCentre   And A.Approve='Y' And A.IssueOrReturn=0 And B.IssueOrReturn='I'
						And (Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
						Else CAST(B.IssueQty As Decimal(18,3)) End - Case When (B.TFactor>0 And B.FFactor>0) Then CAST((isnull(((B.ReturnQty+B.AdjustmentQty)*B.TFactor),0)/nullif(B.FFactor,0)) As Decimal(18,3))
						Else CAST((B.ReturnQty+B.AdjustmentQty) As Decimal(18,3)) End) > 0 And B.IssueTransId
						Not In (Select RIssueTransId From MMS_IssueReturnTrans Where IssueTransId IN (Select IssueTransId
						From MMS_IssueTrans Where IssueRegisterId=$IssueRegisterId  )) And A.OWNOrCSM=0 and  A.ContractorId=$contractor ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$this->_view->arr_resource_iows=$arr_resource_iows;
		}
		$selR=$sql->select();
		$selR->from(array('a'=>'MMS_IssueTrans'))
			->columns(array(new Expression("a.ResourceId")))
			->where (array("a.IssueRegisterId"=>$IssueRegisterId));
		$selI=$sql->select();
		$selI->from(array('a'=>'MMS_IssueTrans'))
			->columns(array(new Expression("a.ItemId")))
			->where (array("a.IssueRegisterId"=>$IssueRegisterId));

		$select = $sql->select();
		$select->from(array('a' => 'Proj_Resource'))
			->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,
			 Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
			 Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
			 case when isnull(d.BrandId,0)>0 Then e.UnitName
			 Else C.UnitName End As UnitName,Case When isnull(d.BrandId,0)>0
			 Then E.UnitId Else C.UnitId End As UnitId,
			 Case when isnull(d.BrandId,0)>0 Then CAST(d.Rate As Decimal(18,2)) Else CAST(f.Rate As Decimal(18,2)) End As Rate ")))
			->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
			->join(array('c' => 'Proj_UOM'), ' a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
			->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
			->join(array('e' => 'Proj_UOM'), 'd.UnitID=e.UnitId', array(), $select::JOIN_LEFT)
			->join(array('f' => 'Proj_ProjectResource'), 'a.ResourceId=f.ResourceId', array(), $select::JOIN_INNER)
			->join(array('g' => 'WF_OperationalCostCentre'),'g.projectid=f.ProjectId',array(),$select::JOIN_INNER)
			->where("g.CostCentreId=" . $CostCentre );

		$selRa = $sql -> select();
		$selRa->from(array("a" => "Proj_Resource"))
			->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
					Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
					Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
					Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
					Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
					Case when isnull(c.BrandId,0)>0 Then CAST(c.Rate As Decimal(18,2)) else CAST(a.Rate As Decimal(18,2)) End As Rate  ")))
			->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
			->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
			->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
			->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
			->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource a
						 Inner Join WF_OperationalCostCentre b On a.projectid=b.projectid
						 Where b.costcentreid=". $CostCentre .")");
		$select -> combine($selRa,"Union All");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$select=$sql->select();
		$select->from(array('a'=>'mms_IssueRegister'))
			->columns(array(new Expression("a.Narration as Notes")))
			->where (array("a.IssueRegisterId"=>$IssueRegisterId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->Narrat = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//IsWareHouse
		$select = $sql -> select();
		$select->from(array("a" => "MMS_CCWareHouse"))
			->columns(array("WareHouseId"))
			->where(array("a.CostCentreId"=> $CostCentre));
		$whStatement = $sql->getSqlStringForSqlObject($select);
		$isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->isWh = $isWareHouse;

			//warehouse-issue-edit
		$selWhm = $sql -> select();
		$selWhm->from(array("a" => "MMS_IssueWareHouseTrans"))
			->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
				"ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
				"WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
				"ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,3))"),
				"Qty" => new Expression("CAST(a.IssueQty As Decimal(18,3))"),"HiddenQty"=>new expression("CAST(a.IssueQty As Decimal(18,3))") ))
			->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$selWhm::JOIN_INNER)
			->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$selWhm::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$selWhm::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$selWhm::JOIN_INNER)
			->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$selWhm::JOIN_INNER)
			->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$selWhm::JOIN_INNER)
			->where ("c.IssueRegisterId=".$IssueRegisterId."");

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
			->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0 and
				(e.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
				e.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' )) and
				c.TransId NOT IN (Select warehouseid from MMS_IssueWareHouseTrans A Inner Join MMS_IssueTrans B On A.IssueTransId=B.IssueTransId
				where b.IssueRegisterId='.$IssueRegisterId.')    ');
		$selWhm->combine($selWh,'Union ALL');
		$statement = $sql->getSqlStringForSqlObject($selWhm);
		$this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$select = $sql -> select();
		$select->from(array("a" => "MMS_WareHouse"))
			->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
				"ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
				"WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
				"ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,3))"),
				"Qty"=>new Expression("CAST(0 As Decimal(18,3))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,3))")))
			->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$select::JOIN_INNER)
			->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$select::JOIN_INNER)
			->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$select::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$select::JOIN_INNER)
			->where('b.CostCentreId='. $CostCentre .' and c.LastLevel=1 and d.ClosingStock>0 and
				(e.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
				e.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' ))');
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_wbs_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$select1 = $sql -> select();
		$select1->from(array("a" => "MMS_IssueWareHouseWbsTrans"))
			->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
				"AnalysisId" => new Expression("a.AnalysisId"),
				"ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
				"WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
				"ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,3))"),
				"Mode" => new Expression("CONVERT(bit,0,0)"),
				"Qty" => new Expression("CAST(a.IssueQty As Decimal(18,3))"),"HiddenQty"=>new expression("CAST(a.IssueQty As Decimal(18,3))")
				 ))
			->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$select1::JOIN_INNER)
			->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$select1::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$select1::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$select1::JOIN_INNER)
			->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$select1::JOIN_INNER)
			->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$select1::JOIN_INNER)
			->where ("c.IssueRegisterId=".$IssueRegisterId."");
		$statement = $sql->getSqlStringForSqlObject($select1);
		$this->_view->arr_selwbs_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$wbsRes = $sql -> select();
		$wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
			->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
			->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
			->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId',array(),$wbsRes::JOIN_INNER)
			->join(array('d' => 'WF_OperationalCostCentre'),'a.projectid=d.projectid',array(),$wbsRes::JOIN_INNER)
			->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
		$statement = $sql->getSqlStringForSqlObject($wbsRes);
		$this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//return-warehouse-edit
		$selWh = $sql -> select();
		$selWh->from(array("a" => "MMS_IssueWareHouseTrans"))
			->columns(array(new Expression("a.IssueTransId as IssueTransId,
				CAST(a.IssueQty As Decimal(18,3)) As IssueQty,
				CAST((a.IssueQty - (a.ReturnQty+a.AdjustmentQty)) As Decimal(18,3)) AS [BalIssueQty],
				b.transid AS [WareHouseId],
				c.WareHouseName AS [WareHouseName],b.Description AS [Description],
				f.ResourceId As ResourceId, f.ItemId As ItemId,f.StockId,
				CAST(0 As Decimal(18,3)) AS [Qty],CAST(0 As Decimal(18,3)) AS [HiddenQty],
				CAST(0 As Decimal(18,3)) AS [AdjustmentQty],CAST(0 As Decimal(18,3)) AS [AHiddenQty]")))
			->join(array("b" => "MMS_WareHouseDetails"),'a.WareHouseId=b.TransId',array(),$selWh::JOIN_INNER)
			->join(array("c" => "MMS_WareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
			->join(array("d" => "MMS_IssueTrans"),"d.IssueTransId=a.IssueTransId",array(),$selWh::JOIN_INNER)
			->join(array("e" => "MMS_IssueRegister"),"d.IssueRegisterId=e.IssueRegisterId",array(),$selWh::JOIN_INNER)
			->join(array("f" => "MMS_Stock"),"f.Resourceid=d.ResourceId and f.ItemId=d.ItemId and f.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
			->join(array("g" => "MMS_StockTrans"),"f.StockId=g.StockId and g.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
			->join(array("h" => "MMS_returnWareHouseTrans"),"h.IssueTransId=a.IssueTransId and h.WareHouseId=a.WareHouseId",array(),$selWh::JOIN_INNER)
			->where('e.CostCentreId='. $CostCentre .' and b.LastLevel=1 and a.IssueQty>0 and
				 (f.ResourceId IN (Select ResourceId From MMS_IssueTrans  Where IssueRegisterId='.$IssueRegisterId.') and
				  f.ItemId IN (Select ItemId From MMS_IssueTrans Where IssueRegisterId='.$IssueRegisterId.' )) ');
		$statement = $sql->getSqlStringForSqlObject($selWh);
		$this->_view->arr_resel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select1 = $sql -> select();
		$select1->from(array("a" => "MMS_IssueWareHouseReturnTrans"))
			->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
				"RIssueTransId" => new Expression("a.RIssueTransId"),
				"ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
				"WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
				"ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,2))"),
				"Mode" => new Expression("CONVERT(bit,0,0)"),
				"Qty" => new Expression("CAST(a.ReturnQty As Decimal(18,3))"),
				"HiddenQty"=>new expression("CAST(a.ReturnQty As Decimal(18,3))"),
				"AdjustmentQty" => new expression("CAST(a.AdjustmentQty As Decimal(18,3))"),
				"AHiddenQty" => new expression("CAST(a.AdjustmentQty As Decimal(18,3))")
			))
			->join(array("b" => "MMS_IssueTrans"),'a.IssueTransId=b.IssueTransId',array(),$select1::JOIN_INNER)
			->join(array("c" => "MMS_IssueRegister"),'b.IssueRegisterId=c.IssueRegisterId',array(),$select1::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$select1::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.CostCentreId=e.CostCentreId',array(),$select1::JOIN_INNER)
			->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$select1::JOIN_INNER)
			->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$select1::JOIN_INNER)
			->where ("c.IssueRegisterId=".$IssueRegisterId."");
		$statement = $sql->getSqlStringForSqlObject($select1);
		$this->_view->arr_resel_iswarehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->IssueRegisterId = $IssueRegisterId;
		

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function issueregisterAction(){
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
        //Pageload
        $selectActRequest = $sql->select();
        $selectActRequest->from(array("a"=>"mms_IssueRegister"));
        $selectActRequest->columns(array(new Expression("top 1 a.IssueNo,a.IssueRegisterId,Convert(varchar(10),a.IssueDate,105) as IssueDate,
        CASE WHEN a.IssueType='1' THEN 'Internal' Else 'Contractor' END as IssueType,
        CASE WHEN a.IssueOrReturn=0 THEN 'Issue' Else 'Return' End As [Issue/Return],
        CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as ApproveReg")))
            ->where(array('a.DeleteFlag'=>0))
            ->order("a.CreatedDate Desc");
        $statement = $sql->getSqlStringForSqlObject($selectActRequest);
        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_IssueRegister"));
        $selectVendor->columns(array(new Expression("a.IssueRegisterId,a.IssueNo,a.CCIssueNo,a.CIssueNo,Convert(varchar(10),a.IssueDate,105) as IssueDate,
            CASE WHEN a.IssueOrReturn=0 THEN 'Issue' Else 'Return' End As [Issue/Return]"),
            new Expression("CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as ApproveReg,
            CASE WHEN a.IssueType='1' THEN 'Internal' Else 'Contractor' END as IssueType")),array("CostCentreName"))
            ->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $selectVendor::JOIN_LEFT)
            ->where(array('a.DeleteFlag'=>0))
            ->order("a.IssueRegisterId Desc");
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->IssueNo = $results['IssueNo'];
        $this->_view->IssueDate = $results['IssueDate'];
        $this->_view->IssueType = $results['IssueType'];
        $this->_view->IssueRegisterId = $results['IssueRegisterId'];
        $this->_view->Approve = $results['ApproveReg'];
        $this->_view->gridResult = $gridResult;

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function issueDeleteAction(){
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
        $IssueRegisterId = $this->params()->fromRoute('id');

        $select =$sql->select();
        $select->from('MMs_IssueRegister')
            ->columns(array('IssueOrReturn'))
            ->where("IssueRegisterId=$IssueRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $Type = $results['IssueOrReturn'];
        if($Type==1){

            $selects=$sql->select();
            $selects->from(array("a"=>"mms_IssueReturnTrans"))
                ->columns(array(new Expression("a.RIssueTransId as RIssueTransId,a.Qty as Qty,a.AdjustmentQty as AdjustmentQty")))
                ->join(array("b"=>"mms_IssueTrans"),"a.IssueTransId=b.IssueTransId",array("IssueTransId"),$selects::JOIN_INNER)
                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
            $state = $sql->getSqlStringForSqlObject($selects);
            $preview = $dbAdapter->query($state, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($preview as $arrive)
            {
                $updDecAnal=$sql->update();
                $updDecAnal->table('mms_IssueTrans');
                $updDecAnal->set(array(
                    'ReturnQty'=> new Expression('ReturnQty-'.$arrive['Qty'].''),
                    'AdjustmentQty'=>new Expression('AdjustmentQty-'.$arrive['AdjustmentQty'].'')
                ));
                $updDecAnal->where(array('IssueTransId'=>$arrive['RIssueTransId']));
                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $selPrevAnal=$sql->select();
            $selPrevAnal->from(array("a"=>"MMS_IssueTrans"))
                ->columns(array(new Expression("a.IssueTransId as IssueTransId,a.IssueQty As Qty,a.IssueAmount as Amount,a.ResourceId as ResourceId,a.ItemId as ItemId")))
                ->join(array("b"=>"mms_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array("CostCentreId"),$selPrevAnal::JOIN_INNER)
                ->where(array("a.IssueRegisterId"=>$IssueRegisterId));
            $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
            $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($prevanal as $arrprevanal)
            {
                $updDecAnal=$sql->update();
                $updDecAnal->table('mms_stock');
                $updDecAnal->set(array(
                    'IssueQty'=> new Expression('IssueQty+'.$arrprevanal['Qty'].''),
                    'ClosingStock'=>new Expression('ClosingStock-'.$arrprevanal['Qty'].''),
                    'IssueAmount'=>new Expression('IssueAmount+'.$arrprevanal['Amount'].'')
                ));
                $updDecAnal->where(array('ItemId'=>$arrprevanal['ItemId']));
                $updDecAnal->where(array('ResourceId'=>$arrprevanal['ResourceId']));
                $updDecAnal->where(array('CostCentreId'=>$arrprevanal['CostCentreId']));
                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $selects=$sql->select();
            $selects->from(array("a"=>"mms_IssueReturnTrans"))
                ->columns(array(new Expression("a.RIssueTransId as RIssueTransId,a.Qty as Qty,
                                a.AdjustmentQty as AdjustmentQty")))
                ->join(array("b"=>"mms_IssueTrans"),"a.IssueTransId=b.IssueTransId",array("IssueTransId"),$selects::JOIN_INNER)
                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
            $state = $sql->getSqlStringForSqlObject($selects);
            $preview = $dbAdapter->query($state, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($preview as $arrive)
            {
                $updDecAnal=$sql->update();
                $updDecAnal->table('MMS_IssueWareHouseTrans');
                $updDecAnal->set(array(
                    'ReturnQty'=> new Expression('ReturnQty-'.$arrive['Qty'].''),
                    'AdjustmentQty'=>new Expression('AdjustmentQty-'.$arrive['AdjustmentQty'].'')
                ));
                $updDecAnal->where(array('IssueTransId'=>$arrive['RIssueTransId']));
                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            //Stock Tran Update
            $selSTrans=$sql->select();
            $selSTrans->from(array("a"=>"MMS_IssueWareHouseTrans"))
                ->columns(array(new Expression("c.StockId,a.WareHouseId As WareHouseId,a.IssueQty as IssueQty")))
                ->join(array("b"=>"MMS_IssueTrans"),"a.IssueTransId=b.IssueTransId",array(),$selSTrans::JOIN_INNER)
                ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId",array(),$selSTrans::JOIN_INNER)
                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
            $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);
            $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            foreach($tranwhtrans as $awh)
            {
                $updatewh = $sql -> update();
                $updatewh->table('MMS_StockTrans');
                $updatewh->set(array(
                    'IssueQty'=>new Expression('IssueQty-'.$this->bsf->isNullCheck($awh['IssueQty'],'number') .''),
                    'ClosingStock'=>new Expression('ClosingStock-'. $this->bsf->isNullCheck($awh['IssueQty'],'number') .'')
                ));
                $updatewh->where(array('StockId'=>$awh['StockId']));
                $updatewh->where(array('WareHouseId'=>$awh['WareHouseId']));
                $updstocktransStatement = $sql->getSqlStringForSqlObject($updatewh);
                $dbAdapter->query($updstocktransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $subQuery=$sql->select();
            $subQuery->from('MMS_IssueTrans')
                ->columns(array('IssueTransId'))
                ->where("IssueRegisterId=$IssueRegisterId");

            $subQuery1  = $sql->select();
            $subQuery1->from("mms_IssueReturnTrans")
                ->columns(array("RIssueTransId"));
            $subQuery1->where(array('IssueTransId'=>$subQuery));

            $deleteR = $sql->delete();
            $deleteR->from('MMS_IssueWareHouseReturnTrans')
                ->where->expression('IssueTransId IN ?', array($subQuery));
            $deleteRState= $sql->getSqlStringForSqlObject($deleteR);
            $dbAdapter->query($deleteRState, $dbAdapter::QUERY_MODE_EXECUTE);

            $deleteW = $sql->delete();
            $deleteW->from('MMS_IssueWareHouseTrans')
                ->where->expression('IssueTransId IN ?', array($subQuery));
            $deleteRState= $sql->getSqlStringForSqlObject($deleteW);
            $dbAdapter->query($deleteRState, $dbAdapter::QUERY_MODE_EXECUTE);

            $deleteW = $sql -> delete();
            $deleteW->from('MMS_ReturnWareHouseTrans')
                ->where->expression('IssueTransId IN ?',array($subQuery1));
            $deleteWStatement = $sql->getSqlStringForSqlObject($deleteW);
            $dbAdapter->query($deleteWStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //RequestTrans
            $deleteT = $sql->delete();
            $deleteT->from('MMS_IssueReturnTrans')
                ->where->expression('IssueTransId IN ?', array($subQuery));
            $DelState= $sql->getSqlStringForSqlObject($deleteT);
            $dbAdapter->query($DelState, $dbAdapter::QUERY_MODE_EXECUTE);

            $deleteTrans = $sql->delete();
            $deleteTrans->from('MMS_IssueTrans')
                ->where("IssueRegisterId=$IssueRegisterId");
            $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //Request
            $deleteReq = $sql ->update();
            $deleteReq -> table('mms_IssueRegister')
                -> set(array('DeleteFlag'=>1))
                -> where("IssueRegisterId=$IssueRegisterId");
            $DelStatement = $sql->getSqlStringForSqlObject($deleteReq);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);


        }else {
            $selPrevAnal = $sql->select();
            $selPrevAnal->from(array("a" => "MMS_IssueTrans"))
                ->columns(array(new Expression("a.IssueTransId as IssueTransId,a.IssueAmount as IssueAmount,a.IssueQty As Qty,a.ResourceId as ResourceId,a.ItemId as ItemId")))
                ->join(array("b" => "mms_IssueRegister"), "a.IssueRegisterId=b.IssueRegisterId", array("CostCentreId"), $selPrevAnal::JOIN_INNER)
                ->where(array("a.IssueRegisterId" => $IssueRegisterId));
            $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
            $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($prevanal as $arrprevanal) {
                $updDecAnal = $sql->update();
                $updDecAnal->table('mms_stock');
                $updDecAnal->set(array(
                    'IssueQty' => new Expression('IssueQty-' . $arrprevanal['Qty'] . ''),
                    'IssueAmount' => new Expression('IssueAmount-' . $arrprevanal['IssueAmount'] . ''),
                    'ClosingStock' => new Expression('ClosingStock+' . $arrprevanal['Qty'] . '')
                ));
                $updDecAnal->where(array('ItemId' => $arrprevanal['ItemId']));
                $updDecAnal->where(array('ResourceId' => $arrprevanal['ResourceId']));
                $updDecAnal->where(array('CostCentreId' => $arrprevanal['CostCentreId']));
                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            //Stock Tran Update
            $selSTrans=$sql->select();
            $selSTrans->from(array("a"=>"MMS_IssueWareHouseTrans"))
                ->columns(array(new Expression("a.WareHouseId As WareHouseId,a.IssueQty as IssueQty")))
                ->join(array("b"=>"MMS_IssueTrans"),"a.IssueTransId=b.IssueTransId",array(),$selSTrans::JOIN_INNER)
                ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId",array(),$selSTrans::JOIN_INNER)
                ->where(array("b.IssueRegisterId"=>$IssueRegisterId));
            $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);

            $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($tranwhtrans as $awh)
            {
                $updatewh = $sql -> update();
                $updatewh->table('MMS_StockTrans');
                $updatewh->set(array(
                    'IssueQty'=>new Expression('IssueQty-'.$this->bsf->isNullCheck($awh['IssueQty'],'number') .''),
                    'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($awh['IssueQty'],'number') .'')
                ));
            }

            //RequestAnalTrans
            $subQuery = $sql->select();
            $subQuery->from('MMS_IssueTrans')
                ->columns(array('IssueTransId'))
                ->where("IssueRegisterId=$IssueRegisterId");

            $delete = $sql -> delete();
            $delete->from('MMS_IssueWareHouseWbsTrans')
                ->where->expression('IssueTransId IN ?',array($subQuery));
            $delStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($delStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delTVWh = $sql -> delete();
            $delTVWh->from('MMS_IssueWareHouseTrans')
                ->where->expression('IssueTransId IN ?',array($subQuery));
            $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
            $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delTVWh = $sql -> delete();
            $delTVWh->from('MMS_IssueReturnTrans')
                ->where->expression('IssueTransId IN ?',array($subQuery));
            $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
            $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('mms_IssueAnalTrans')
                ->where->expression('IssueTransId IN ?', array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //RequestTrans

            $deleteTrans = $sql->delete();
            $deleteTrans->from('MMS_IssueTrans')
                ->where("IssueRegisterId=$IssueRegisterId");
            $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
            $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //Request
            $deleteReq = $sql->update();
            $deleteReq->table('mms_IssueRegister')
                ->set(array('DeleteFlag' => 1))
                ->where("IssueRegisterId=$IssueRegisterId");
            $DelStatement = $sql->getSqlStringForSqlObject($deleteReq);
            $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        $this->redirect()->toRoute('mms/default', array('controller' => 'issue','action' => 'issue-register'));
        return $this->_view;
    }
    public function issueReportAction(){
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
            $this->redirect()->toRoute("issue/issue-register", array("controller" => "issue","action" => "issue-register"));
        }

        $dir = 'public/issue/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/issue/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $issueRegId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($issueRegId == 0)

            $this->redirect()->toRoute("issue/issue-register", array("controller" => "issue","action" => "conversionregister"));

        if (!file_exists($filePath)) {
            $filePath = 'public/issue/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/issue/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;

        //Template
        $selectVendor = $sql->select();
        $selectVendor->from(array("a"=>"mms_IssueRegister"));
        $selectVendor->columns(array(new Expression("a.IssueRegisterId,a.IssueNo,Convert(varchar(10),a.IssueDate,105) as IssueDate"),new Expression("CASE WHEN a.Approve='Y' THEN 'Yes'
																WHEN a.Approve='P' THEN 'Partial'
																Else 'No'
														END as ApproveReg, CASE WHEN a.IssueType='1' THEN 'Internal'
        Else 'Contractor'
        END as IssueType")),array("CostCentreName"))
            ->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $selectVendor::JOIN_LEFT)
            ->join(array("d"=>"WF_CostCentre"), "c.CostCentreId=d.CostCentreId", array("Address"), $selectVendor::JOIN_LEFT)
            ->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $selectVendor::JOIN_LEFT)
            ->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $selectVendor::JOIN_LEFT)
            ->join(array("g"=>"WF_CountryMaster"), "d.CountryId=g.CountryId", array("CountryName"), $selectVendor::JOIN_LEFT)
            ->where(array("a.DeleteFlag = 0 and a.IssueRegisterId=$issueRegId"))
            ->order("a.IssueDate Desc");
        $statement = $sql->getSqlStringForSqlObject($selectVendor);
        $this->_view->reqregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        //GRID
        $select = $sql->select();
        $select->from(array("A" => "MMS_IssueTrans"))
            ->columns(array(
                'SNo'=>new Expression("(ROW_NUMBER() OVER(PARTITION by A.IssueRegisterId Order by A.IssueRegisterId asc))"),
                'Code'=>new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End"),
                'Resource'=>new Expression("Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End"),
                'IssueTransId'=>new Expression("A.IssueTransId"),
                'IssueQty'=>new Expression("A.IssueQty"),
                'IssueRate'=>new Expression("A.IssueRate"),
                'IssueAmount'=>new Expression("A.IssueAmount"),
                'Remarks'=>new Expression("A.Remarks")
            ))
            ->join(array('B' => 'mms_issueRegister'), ' A.IssueRegisterId =B.IssueRegisterId', array("IssueRegisterId"), $select::JOIN_INNER)
            ->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceId', array(), $select::JOIN_INNER)
            ->join(array('E' => 'Proj_UOM'), 'e.UnitId=a.UnitId', array("UnitName"), $select::JOIN_INNER)
            ->join(array('D' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
            ->where(array("A.IssueRegisterId"=>$issueRegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
}