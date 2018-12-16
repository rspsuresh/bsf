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

class StockController extends AbstractActionController
{
	public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
	}

	public function stockstatAction(){
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
			if ($request->isPost()) {
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentreId =$this->params()->fromPost('CostCentreId');
                $ason= $this->bsf->isNullCheck($postParams['ason'],'string');
                switch($Type) {
                    case 'statement':
                        if($ason == ''){
                            $ason =  0;
                        }
                        if($ason == 0) {
                            $ason = date('Y-m-d', strtotime(Date('d-m-Y')));
                        }
                        else
                        {
                            $ason=date('Y-m-d',strtotime($ason));
                        }


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
                            ->where("a.BillQty>0 and g.DCDate <='$ason' and c.CostCentreId=$CostCentreId
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
                            ->where("a.BillQty>0 and c.PVDate <= '$ason' and a.CostCentreId=$CostCentreId and c.CostCentreId=0
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
                            ->where("a.BillQty>0 and c.PVDate <= '$ason' and c.CostCentreId=$CostCentreId and
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
                            ->where("a.balqty>0 and c.DCDate <= '$ason' and c.CostCentreId=$CostCentreId and
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
                            ->where("a.BalQty>0 and c.DCDate <= '$ason' and a.CostCentreId=$CostCentreId  and c.CostCentreId=0
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
                            ->where("a.TransferQty>0 and b.TVDate <= '$ason' and b.FromCostCentreId=$CostCentreId
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.FromCostCentreId");
                        $selM6->combine($selM5,'Union All');



                        $selM7 = $sql -> select();
                        $selM7 -> from(array("a" => "MMS_TransferTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.RecdQty),Amount=SUM(a.RecdQty*a.QRate),
                              b.ToCostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_LEFT)
                            ->where("a.RecdQty>0 and b.TVDate <= '$ason' and b.ToCostCentreId=$CostCentreId
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.ToCostCentreId");
                        $selM7->combine($selM6,'Union All');



                        $selM8 = $sql -> select();
                        $selM8 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.ReturnQty),Amount=SUM(-Amount),b.CostCentreId As CostCentreId')))
                            ->join(array("b" => "MMS_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array(),$selM8::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM8::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM8::JOIN_INNER)
                            ->where("a.ReturnQty>0 and b.PRDate <= '$ason' and b.CostCentreId=$CostCentreId
                             Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId");
                        $selM8->combine($selM7,'Union All');



                        $selM9 = $sql -> select();
                        $selM9 -> from(array("a" => "MMS_IssueTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.IssueQty),Amount=-SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$ason' and b.CostCentreId=$CostCentreId and b.IssueOrReturn=0 and a.IssueOrReturn='I'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                        $selM9->combine($selM8,'Union All');

                        $selM9a = $sql -> select();
                        $selM9a -> from(array("a" => "MMS_IssueTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.IssueQty),Amount=SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$ason' and b.CostCentreId=$CostCentreId and b.IssueOrReturn=1 and a.IssueOrReturn='R'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                        $selM9a->combine($selM9,'Union All');



                        $selM10 = $sql ->select();
                        $selM10 -> from(array("a" => "MMS_PVRateAdjustment" ))
                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->where("d.PVDate <= '$ason'  and d.CostCentreId=$CostCentreId and a.Type='D'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                        $selM10->combine($selM9a,'Union All');


                        $selM11 = $sql ->select();
                        $selM11 -> from(array("a" => "MMS_PVRateAdjustment" ))
                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=-SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->where("d.PVDate <= '$ason'  and d.CostCentreId=$CostCentreId and a.Type='C'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                        $selM11->combine($selM10,'Union All');



                        $selM12 = $sql -> select();
                        $selM12 -> from(array("a" => "MMS_Stock"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,a.OpeningStock As Qty,Amount=a.OpeningStock*a.ORate,a.CostCentreId')))
                            ->where(" a.CostCentreId=$CostCentreId ");
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
                               Cast(g.Qty As Decimal(18,3)) As Qty,Cast(g.AvgRate As Decimal(18,2)) As AvgRate,Cast(g.Cost As Decimal(18,2)) As Cost,g.CostCentreId  ")))
                            ->join(array("RV" => "Proj_Resource"),"g.ResourceId=RV.ResourceId",array(),$selF2::JOIN_INNER)
                            ->join(array("RG" => "Proj_ResourceGroup"),"RV.ResourceGroupId=RG.ResourceGroupId",array(),$selF2::JOIN_LEFT)
                            ->join(array("BR" => "MMS_Brand"),"g.ResourceId=BR.ResourceId And g.ItemId=BR.BrandId",array(),$selF2::JOIN_LEFT)
                            ->join(array("U" => "Proj_UOM"),"BR.UnitId=U.UnitId",array(),$selF2::JOIN_LEFT)
                            ->join(array("U1" => "Proj_UOM"),"RV.UnitId=U1.UnitId",array(),$selF2::JOIN_LEFT)
                            ->where('RV.TypeId IN (2)');

                        $statement = $statement = $sql->getSqlStringForSqlObject($selF2);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($register));
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
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

    public function stockdetailsAction(){
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

    if($this->getRequest()->isXmlHttpRequest())	{
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $ccId = $postParams['costcentreid'];
            $resId = $postParams['resId'];
            $itemId = $postParams['itemId'];
            $ason= $this->bsf->isNullCheck($postParams['ason'],'string');

            if($ason == ''){
                $ason =  0;
            }
            if($ason == 0) {
                $ason = date('Y-m-d', strtotime(Date('d-m-Y')));
            }
            else
            {
                $ason=date('Y-m-d',strtotime($ason));
            }
            $this->_view->ason =$ason;
            $sql = new Sql($dbAdapter);

            $selQ1 = $sql -> select();
            $selQ1->from(array('a' => 'MMS_PVRateAdjustment'))
                ->columns(array(new Expression("d.PVNo As TransNo,d.BillNo As RefNo,b.ResourceId,b.ItemId,d.CostCentreId,CONVERT(Varchar(10),d.PVDate,103) As TransDate,
                       e.VendorName As Vendor,'Rate Adjustment - Credit' As Type,'-' As Sign,CAST(0 As Decimal(18,6)) As Debit,CAST(0 As Decimal(18,6)) As Credit,CAST(0 As Decimal(18,6)) As Qty,
                       CAST(0 As Decimal(18,2)) As Rate,Amount=-SUM(Case When (c.FFactor>0 and c.TFactor>0) Then isnull((a.Amount*c.TFactor),0)/
                       nullif(c.FFactor,0) Else a.Amount End)  ")))
                ->join(array('b' => 'MMS_PVTrans'), 'a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId',array(),$selQ1::JOIN_INNER)
                ->join(array('c' => 'MMS_PVGroupTrans'),'b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId',array(),$selQ1::JOIN_INNER)
                ->join(array('d' => 'MMS_PVRegister'),'b.PVRegisterId=d.PVRegisterId',array(),$selQ1::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'d.VendorId=e.VendorId',array(),$selQ1::JOIN_LEFT)
                ->where("d.PVDate <= '$ason' and d.CostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.Type='C'
                       group by d.PVNo,d.BillNo,d.PVDate,e.VendorName,b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");

            $selQ2 = $sql -> select();
            $selQ2->from(array('a' => 'MMS_PVRateAdjustment'))
                ->columns(array(new Expression("d.PVNo As TransNo,d.BillNo As RefNo,b.ResourceId,b.ItemId,d.CostCentreId,CONVERT(Varchar(10),d.PVDate,103) As TransDate,
                       e.VendorName As Vendor,'Rate Adjustment - Debit' As Type,'+' As Sign,CAST(0 As Decimal(18,6)) As Debit,CAST(0 As Decimal(18,6)) As Credit,CAST(0 As Decimal(18,6)) As Qty,
                       CAST(0 As Decimal(18,2)) As Rate,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) Then isnull((a.Amount*c.TFactor),0)/
                       nullif(c.FFactor,0) Else a.Amount End)  ")))
                ->join(array('b' => 'MMS_PVTrans'), 'a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId',array(),$selQ2::JOIN_INNER)
                ->join(array('c' => 'MMS_PVGroupTrans'),'b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId',array(),$selQ2::JOIN_INNER)
                ->join(array('d' => 'MMS_PVRegister'),'b.PVRegisterId=d.PVRegisterId',array(),$selQ2::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'d.VendorId=e.VendorId',array(),$selQ2::JOIN_LEFT)
                ->where("d.PVDate <= '$ason' and d.CostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.Type='D'
                       group by d.PVNo,d.BillNo,d.PVDate,e.VendorName,b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
            $selQ2->combine($selQ1,'Union All');

            $selQ3 = $sql -> select();
            $selQ3 -> from(array('a' => 'MMS_PRTrans' ))
                ->columns(array(new Expression("b.PRNo As TransNo,b.BillNo As RefNo,a.ResourceId,a.ItemId,b.CostCentreId,Convert(Varchar(10),b.PRDate,103) As TransDate,
                      '' Vendor,'Bill-Return' As Type,'-' As Sign,CAST(0 As Decimal(18,6)) As Debit,Credit=SUM(a.ReturnQty),Qty=-SUM(a.ReturnQty),Rate=a.Rate,Amount=-SUM(Amount)")))
                ->join(array('b' => 'MMS_PRRegister'),'b.PRRegisterId=a.PRRegisterId',array(),$selQ3::JOIN_INNER)
                ->where("a.ReturnQty>0 and b.PRDate <= '$ason' and b.CostCentreId=$ccId and a.ResourceId=$resId and a.ItemId=$itemId
                       group by b.PRNo,b.BillNo,b.PRDate,a.Rate,a.ResourceId,a.ItemId,b.CostCentreId");
            $selQ3->combine($selQ2,'Union All');


            $selQ4 = $sql -> select();
            $selQ4 -> from(array('a' => 'MMS_TransferTrans'))
                ->columns(array(new Expression("b.TVNo As TransNo,'' RefNo,a.ResourceId,a.ItemId,b.ToCostCentreId As CostCentreId,
                       Convert(Varchar(103),b.TVDate,103) As TransDate,'' As Vendor,'Transfered From '+c.CostCentreName As Type,
                       '+' As Sign,Debit=SUM(a.RecdQty),CAST(0 As Decimal(18,6)) As Credit, Qty=SUM(a.RecdQty),Rate=a.QRate,Amount=Sum(a.RecdQty*A.QRate) ")))
                ->join(array('b' => 'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$selQ4::JOIN_INNER)
                ->join(array('c' => 'WF_OperationalCostCentre'),'b.ToCostCentreId=c.CostCentreId',array(),$selQ4::JOIN_INNER)
                ->where("a.RecdQty>0 and b.TVDate<='$ason' and b.ToCostCentreId=$ccId and a.ResourceId=$resId and a.ItemId=$itemId
                       group by b.TVNo,b.TVDate,c.CostCentreName,a.QRate,a.ResourceId,a.ItemId,b.ToCostCentreId ");

            $selQ4->combine($selQ3,'Union All');



            $selQ5 = $sql -> select();
            $selQ5 -> from(array('a' => 'MMS_TransferTrans' ))
                ->columns(array(new Expression("b.TVNo As TransNo,'' RefNo,a.ResourceId,a.ItemId,b.FromCostCentreId As CostCentreId,
                       Convert(Varchar(10),b.TVDate,103) As TransDate,'' As Vendor,'Transfered To '+c.CostCentreName As Type,
                       '-' As Sign,CAST(0 As Decimal(18,6)) As Debit,Credit=SUM(a.TransferQty),Qty=-SUM(a.TransferQty),Rate=a.QRate,Amount=-SUM(a.QAmount)  ")))
                ->join(array('b'  => 'MMS_TransferRegister') , 'a.TransferRegisterId=b.TVRegisterId',array(),$selQ5::JOIN_INNER )
                ->join(array('c' => 'WF_OperationalCostCentre'),'b.FromCostCentreId=c.CostCentreId',array(),$selQ5::JOIN_INNER)
                ->where("a.TransferQty>0 and b.TVDate<='$ason' and b.FromCostCentreId=$ccId and a.ResourceId=$resId and a.ItemId=$itemId
                      group by b.TVNo,b.TVDate,c.CostCentreName,a.QRate,a.ResourceId,a.ItemId,b.FromCostCentreId ");
            $selQ5->combine($selQ4,'Union All');



            $selQ6 = $sql -> select();
            $selQ6 -> from(array('a' => 'MMS_IssueTrans' ))
                ->columns(array(new Expression("b.IssueNo,b.OtherNo As RefNo,a.ResourceId,a.ItemId,b.CostCentreId,
                     Convert(Varchar(10),b.IssueDate,103) As TransDate,Case When c.VendorName <> '' Then c.VendorName Else '' End As Vendor,
                      'Issue-Return' As Type,'+' As Sign,Debit=SUM(a.IssueQty),CAST(0 As Decimal(18,6)) As Credit, Qty=SUM(a.IssueQty),Rate=a.IssueRate,
                       Amount=SUM(Case When (a.FFactor>0 and a.TFactor>0) Then (a.IssueQty*isnull((a.IssueRate*a.TFactor),0)/nullif(a.FFactor,0)) Else
                       a.IssueAmount End)")))
                ->join(array('b' => 'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$selQ6::JOIN_INNER)
                ->join(array('c' => 'Vendor_Master'),'b.ContractorId=c.VendorId',array(),$selQ6::JOIN_LEFT )
                ->where("a.IssueQty>0 and b.IssueDate<='$ason' and b.CostCentreId=$ccId and b.IssueOrReturn=1 and a.IssueOrReturn='R'
                      and a.ResourceId=$resId  and a.ItemId=$itemId group by b.IssueNo,b.OtherNo,b.IssueDate,c.VendorName,
                          a.IssueRate,a.ResourceId,a.ItemId,b.CostCentreId");
            $selQ6->combine($selQ5,'Union All');

            $selQ7 = $sql -> select();
            $selQ7 -> from(array('a' => 'MMS_IssueTrans' ))
                ->columns(array(new Expression("b.IssueNo As TransNo,b.OtherNo As RefNo,a.ResourceId,a.ItemId,b.CostCentreId,
                     Convert(Varchar(10),b.IssueDate,103) As TransDate,Case When c.VendorName <> '' Then c.VendorName Else '' End As Vendor,
                      'Issue' As Type,'-' As Sign,CAST(0 As Decimal(18,6)) As Debit,Credit=SUM(a.IssueQty),Qty=-SUM(a.IssueQty),Rate=a.IssueRate,
                       Amount=-SUM(Case When (a.FFactor>0 and a.TFactor>0) Then (a.IssueQty*isnull((a.IssueRate*a.TFactor),0)/nullif(a.FFactor,0)) Else
                       a.IssueAmount End)")))
                ->join(array('b' => 'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$selQ6::JOIN_INNER)
                ->join(array('c' => 'Vendor_Master'),'b.ContractorId=c.VendorId',array(),$selQ6::JOIN_LEFT )
                ->where("a.IssueQty>0 and b.IssueDate<='$ason' and b.CostCentreId=$ccId and b.IssueOrReturn=0 and a.IssueOrReturn='I'
                      and a.ResourceId=$resId and a.ItemId=$itemId  group by b.IssueNo,b.OtherNo,b.IssueDate,c.VendorName,
                          a.IssueRate,a.ResourceId,a.ItemId,b.CostCentreId");
            $selQ7->combine($selQ6,'Union All');



            $selQ8 = $sql -> select();
            $selQ8 -> from(array('a' => 'MMS_DCTrans'))
                ->columns(array(new Expression("c.DCNo As TransNo,c.SiteDCNo As RefNo,a.ResourceId,a.ItemId,a.CostCentreId,Convert(Varchar(10),c.DCDate,103) As TransDate,
                      e.VendorName As Vendor,'MIN' As Type,'+' As Sign,Debit=SUM(a.BalQty),CAST(0 As Decimal(18,6)) As Credit,Qty=SUM(a.BalQty),Rate=Case When ((b.PurchaseTypeId=0 Or b.PurchaseTypeId=5) And d.SEZProject=0
                      And (f.StateId=h.StateId)) Then Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End Else
                      Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End End,
                      Amount=Case when ((b.PurchaseTypeId=0 or b.PurchaseTypeId=5) and d.SEZProject=0 and (f.StateId=h.StateId)) then sum(a.BalQty*
                      case when (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) else a.GrossRate End) Else
                      SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) else a.QRate End) End ")))
                ->join(array('b' => 'MMS_DCGroupTrans'),'a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId',array(),$selQ8::JOIN_INNER)
                ->join(array('c' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$selQ8::JOIN_INNER)
                ->join(array('d' => 'WF_OperationalCostCentre'),'a.CostCentreId=d.CostCentreId',array(),$selQ8::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'c.VendorId=e.VendorId',array(),$selQ8::JOIN_INNER)
                ->join(array('f' => 'WF_CityMaster'),'e.CityId=f.CityId',array(),$selQ8::JOIN_LEFT)
                ->join(array('g' => 'WF_CostCentre'),'d.FACostCentreId=g.CostCentreId',array(),$selQ8::JOIN_INNER)
                ->join(array('i' => 'WF_CityMaster'),'i.CityId=g.CityId',array(),$selQ8::JOIN_INNER)
                ->join(array('h'  => 'WF_StateMaster'),'i.StateId=h.StateId',array(),$selQ8::JOIN_INNER)
                ->where("a.BalQty>0  and c.DCDate <= '$ason' and a.CostCentreId=$ccId and
                        c.CostCentreId=0 and c.DcOrCsm=1 and a.ResourceId=$resId and a.ItemId=$itemId
                        group by c.DCNo,c.SiteDCNo,c.DCDate,e.VendorName,a.GrossRate,a.QRate,a.ResourceId,a.ItemId,
                          b.PurchaseTypeId,a.CostCentreId,d.SEZProject,b.FFactor,b.TFactor,f.StateId,h.StateId");
            $selQ8->combine($selQ7,'Union All');



            $selQ9 = $sql -> select();
            $selQ9 -> from(array('a' => 'MMS_DCTrans'))
                ->columns(array(new Expression("c.DCNo As TransNo,c.SiteDCNo As RefNo,a.ResourceId,a.ItemId,c.CostCentreId,Convert(Varchar(10),c.DCDate,103) As TransDate,
                      e.VendorName As Vendor,'MIN' As Type,'+' As Sign,Debit=SUM(a.BalQty),CAST(0 As Decimal(18,6)) As Credit,Qty=SUM(a.BalQty),Rate=Case When ((b.PurchaseTypeId=0 Or b.PurchaseTypeId=5) And d.SEZProject=0
                      And (f.StateId=h.StateId)) Then Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End Else
                      Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End End,
                      Amount=Case when ((b.PurchaseTypeId=0 or b.PurchaseTypeId=5) and d.SEZProject=0 and (f.StateId=h.StateId)) then sum(a.BalQty*
                      case when (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) else a.GrossRate End) Else
                      SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) else a.QRate End) End ")))
                ->join(array('b' => 'MMS_DCGroupTrans'),'a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId',array(),$selQ9::JOIN_INNER)
                ->join(array('c' => 'MMS_DCRegister'),'a.DCRegisterId=c.DCRegisterId',array(),$selQ9::JOIN_INNER)
                ->join(array('d' => 'WF_OperationalCostCentre'),'c.CostCentreId=d.CostCentreId',array(),$selQ9::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'c.VendorId=e.VendorId',array(),$selQ9::JOIN_INNER)
                ->join(array('f' => 'WF_CityMaster'),'e.CityId=f.CityId',array(),$selQ9::JOIN_LEFT)
                ->join(array('g' => 'WF_CostCentre'),'d.FACostCentreId=g.CostCentreId',array(),$selQ9::JOIN_INNER)
                ->join(array('i' => 'WF_CityMaster'),'i.CityId=g.CityId',array(),$selQ9::JOIN_INNER)
                ->join(array('h'  => 'WF_StateMaster'),'i.StateId=h.StateId',array(),$selQ9::JOIN_INNER)
                ->where("a.BalQty>0  and c.DCDate <= '$ason' and c.CostCentreId=$ccId and
                         c.DcOrCsm=1 and a.ResourceId=$resId and a.ItemId=$itemId
                        group by c.DCNo,c.SiteDCNo,c.DCDate,e.VendorName,a.GrossRate,a.QRate,a.ResourceId,a.ItemId,
                          b.PurchaseTypeId,c.CostCentreId,d.SEZProject,b.FFactor,b.TFactor,f.StateId,h.StateId");
            $selQ9->combine($selQ8,'Union All');

            $selQ10 = $sql -> select();
            $selQ10 -> from(array('a' => 'MMS_PVTrans'))
                ->columns(array(new Expression("c.PVNo As TransNo,c.BillNo As RefNo,a.ResourceId,a.ItemId,c.CostCentreId,
                      Convert(Varchar(10),c.PVDate,103) As TransDate,e.VendorName As Vendor,'Purchase' As Type,'+' As Sign,
                      Debit=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,CAST(0 As Decimal(18,6)) As Credit,
                      Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,
                      Rate = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And d.SEZProject=0 And (f.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End  Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End End  Else
                       Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End End End,
                      Amount = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And d.SEZProject=0 And (f.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End)  Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) End  Else
                       Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End ")))
                ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ10::JOIN_INNER)
                ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=c.PVRegisterId',array(),$selQ10::JOIN_INNER )
                ->join(array('d' => 'WF_OperationalCostCentre'),'c.CostCentreId=d.CostCentreId',array(),$selQ10::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'c.VendorId=e.VendorId',array(),$selQ10::JOIN_INNER)
                ->join(array('f' => 'WF_CityMaster'),'e.CityId=f.CityId',array(),$selQ10::JOIN_LEFT)
                ->join(array('g' => 'WF_CostCentre'),'d.FACostCentreId=g.CostCentreId',array(),$selQ10::JOIN_INNER)
                ->join(array('i' => 'WF_CityMaster'),'i.CityId=g.CityId',array(),$selQ10::JOIN_INNER)
                ->join(array('h' => 'WF_StateMaster'),'i.StateId=h.StateId',array(),$selQ10::JOIN_INNER)
                ->where("a.BillQty>0 and c.PVDate <= '$ason' and c.CostCentreId=$ccId and c.ThruPO='Y' and
                       a.ResourceId=$resId and a.ItemId=$itemId group by c.PVNo,c.BillNo,c.PVDate,e.VendorName,
                       a.GrossRate,a.QRate,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,c.CostCentreId,
                       d.SEZProject,b.FFactor,b.TFactor,f.StateId,h.StateId   ");
            $selQ10->combine($selQ9,'Union All');


            $selQ11 = $sql -> select();
            $selQ11 -> from(array('a' => 'MMS_PVTrans'))
                ->columns(array(new Expression("c.PVNo As TransNo,c.BillNo As RefNo,a.ResourceId,a.ItemId,a.CostCentreId,
                      Convert(Varchar(10),c.PVDate,103) As TransDate,e.VendorName As Vendor,'Purchase' As Type,'+' As Sign,
                      Debit=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,CAST(0 As Decimal(18,6)) As Credit,
                      Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,
                      Rate = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And d.SEZProject=0 And (f.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End  Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End End  Else
                       Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End End End,
                      Amount = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And d.SEZProject=0 And (f.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End)  Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) End  Else
                       Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End ")))
                ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ11::JOIN_INNER)
                ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=c.PVRegisterId',array(),$selQ11::JOIN_INNER )
                ->join(array('d' => 'WF_OperationalCostCentre'),'a.CostCentreId=d.CostCentreId',array(),$selQ11::JOIN_INNER)
                ->join(array('e' => 'Vendor_Master'),'c.VendorId=e.VendorId',array(),$selQ11::JOIN_INNER)
                ->join(array('f' => 'WF_CityMaster'),'e.CityId=f.CityId',array(),$selQ11::JOIN_LEFT)
                ->join(array('g' => 'WF_CostCentre'),'d.FACostCentreId=g.CostCentreId',array(),$selQ11::JOIN_INNER)
                ->join(array('i' => 'WF_CityMaster'),'i.CityId=g.CityId',array(),$selQ11::JOIN_INNER)
                ->join(array('h' => 'WF_StateMaster'),'g.StateId=i.StateId',array(),$selQ11::JOIN_INNER)
                ->where("a.BillQty>0 and c.PVDate <= '$ason' and a.CostCentreId=$ccId and c.CostCentreId=0 and
                       a.ResourceId=$resId and a.ItemId=$itemId group by c.PVNo,c.BillNo,c.PVDate,e.VendorName,
                       a.GrossRate,a.QRate,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,a.CostCentreId,
                       d.SEZProject,b.FFactor,b.TFactor,f.StateId,h.StateId   ");
            $selQ11->combine($selQ10,'Union All');



            $selQ12 = $sql -> select();
            $selQ12 -> from(array('a' => 'MMS_PVTrans'))
                ->columns(array(new Expression("c.PVNo As TransNo,c.BillNo As RefNo,a.ResourceId,a.ItemId,a.CostCentreId,
                      Convert(Varchar(10),c.PVDate,103) As TransDate,g.VendorName As Vendor,'Purchase' As Type,'+' As Sign,
                      Debit=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,CAST(0 As Decimal(18,6)) As Credit,
                      Qty=Case When c.ThruDC='Y' Then SUM(a.ActualQty) Else SUM(A.BillQty) End,
                      Rate = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 And (j.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End  Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End End  Else
                       Case When c.ThruDC='Y' Then Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End Else Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End End End,
                      Amount = Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 And (j.StateId=h.StateId))
                       Then  Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then
                       isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End)  Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.GrossRate End) End  Else
                       Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                       Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End ")))
                ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ12::JOIN_INNER)
                ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=c.PVRegisterId',array(),$selQ12::JOIN_INNER )
                ->join(array('d' => 'MMS_DCTrans'),'a.DCTransId=d.DCTransId And a.DCRegisterId=d.DCRegisterId',array(),$selQ12::JOIN_INNER)
                ->join(array('e' => 'MMS_DCRegister'),'d.DCRegisterId=e.DCRegisterId',array(),$selQ12::JOIN_INNER)
                ->join(array('f' => 'WF_OperationalCostCentre'),'c.CostCentreId=f.CostCentreId',array(),$selQ12::JOIN_INNER)
                ->join(array('g' => 'Vendor_Master'),'c.VendorId=g.VendorId',array(),$selQ12::JOIN_INNER)
                ->join(array('h' => 'WF_CityMaster'),'g.CityId=h.CityId',array(),$selQ12::JOIN_LEFT)
                ->join(array('i' => 'WF_CostCentre'),'f.FACostCentreId=i.CostCentreId',array(),$selQ12::JOIN_INNER)
                ->join(array('k' => 'WF_CityMaster'),'i.CityId=k.CityId',array(),$selQ12::JOIN_INNER)
                ->join(array('j' => 'WF_StateMaster'),'k.StateId=j.StateId',array(),$selQ12::JOIN_INNER)
                ->where("a.BillQty>0 and e.DCDate <= '$ason' and c.CostCentreId=$ccId and
                       a.ResourceId=$resId and a.ItemId=$itemId group by c.PVNo,c.BillNo,c.PVDate,g.VendorName,
                       a.GrossRate,a.QRate,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,a.CostCentreId,
                       f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId   ");
            $selQ12->combine($selQ11,'Union All');


            $selQ13 = $sql -> select();
            $selQ13 -> from (array('a' => 'MMS_Stock'))
                ->columns(array(new Expression("'' TransNo,'' RefNo,a.ResourceId,a.ItemId,a.CostCentreId,null Date,'' Vendor,
                     'OpeningStock' As Type,'+' As Sign,Debit=a.OpeningStock,CAST(0 As Decimal(18,6)) As Credit,a.OpeningStock As Qty,a.ORate As Rate,(a.OpeningStock*a.ORate) As Amount ")))
                ->where("OpeningStock>0 and a.CostCentreId=$ccId and a.ResourceId=$resId and a.ItemId=$itemId ");
            $selQ13->combine($selQ12,'Union All');

            $selQ14 = $sql -> select();
            $selQ14 -> from(array('g'=>$selQ13))
                ->columns(array(new Expression("g.TransNo,g.RefNo,g.ResourceId,g.ItemId,g.CostCentreId,g.Date,g.Vendor,g.Type,g.Sign,Cast(g.Debit As Decimal(18,3)) As Debit,
                                Cast(g.Credit As Decimal(18,3)) As Credit,Cast(g.Qty As Decimal(18,3)) As Qty,Cast(g.Rate As Decimal(18,2)) As Rate,Cast(g.Amount As Decimal(18,2)) As Amount")));
            $selQ14->order(new Expression("g.Date Asc"));

            $statement = $sql->getSqlStringForSqlObject($selQ14);
            $this->_view->stockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


//                $selD1 = $sql -> select();
//                $selD1 -> from (array('a' => 'MMS_TransferRegister' ))
//                    ->columns(array(new Expression("b.ResourceId,b.ItemId,a.FromCostCentreId As CostCentreId,SUBSTRING(CONVERT(VARCHAR(11),a.TVDate,113),4,8) As Date,'Transfer' As Type,
//                      CAST(-b.TransferQty As Decimal(18,6)) As Qty,a.TVDate As CDate ")))
//                    ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$selD1::JOIN_INNER)
//                    ->where('b.ResourceId='. $resId .' and b.ItemId='. $itemId .' ');
//
//                $selD2 = $sql -> select();
//                $selD2 -> from (array('a' => 'MMS_IssueRegister' ))
//                    ->columns(array(new Expression("b.ResourceId,b.ItemId,a.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11),a.IssueDate,113),4,8) As Date,'Issue' As Type,
//                      CAST(-b.IssueQty As Decimal(18,6)) As Qty,a.CreatedDate As CDate  ")))
//                    ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$selD2::JOIN_INNER )
//                    ->where("b.IssueOrReturn='I' and a.OwnOrCSM=0 and b.ResourceId=$resId and b.ItemId=$itemId ");
//
//                $selD2->combine($selD1,'Union All');
//
//                $selD3 = $sql -> select();
//                $selD3 -> from(array('a'=> 'MMS_PRRegister'  ))
//                    ->columns(array(new Expression("b.ResourceId,b.ItemId,a.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.PRDate,113),4,8) AS Date, 'BillReturn' As [Type], CAST(-B.ReturnQty As Decimal(18,6)) Qty, A.PRDate As CDate ")))
//                    ->join(array('b' => 'MMS_PRTrans'),'a.PRRegisterId=b.PRRegisterId',array(),$selD3::JOIN_INNER)
//                    ->where("b.ResourceId=$resId and b.ItemId=$itemId ");
//
//                $selD3->combine($selD2,'Union All');
//
//                $selD4 = $sql -> select();
//                $selD4 -> from(array('a' => 'MMS_TransferRegister' ))
//                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.ToCostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.TVDate,113),4,8) AS Date, 'Transfer' [Type],ABS(CAST(B.RecdQty As Decimal(18,6))) Qty,  A.TVDate As CDate")))
//                    ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$selD4::JOIN_INNER)
//                    ->where("b.ResourceId=$resId and b.ItemId=$itemId ");
//                $selD4->combine($selD3,'Union All');
//
//                $selD5 = $sql -> select();
//                $selD5 -> from(array('a' => 'MMS_IssueRegister' ))
//                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.IssueDate, 113), 4, 8) AS Date, 'Return' As [Type],CAST(B.IssueQty As Decimal(18,6)) Qty,A.IssueDate As CDate ")))
//                    ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$selD5::JOIN_INNER)
//                    ->where("b.IssueOrReturn='R' and a.OwnOrCSM=0 and b.ResourceId=$resId and b.ItemId=$itemId ");
//                $selD5->combine($selD4,'Union All');
//
//                $selD6 = $sql -> select();
//                $selD6 -> from(array('a' => 'MMS_DCRegister' ))
//                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.DCDate, 113), 4, 8) AS Month, 'DC' [Type],CAST(B.AcceptQty As Decimal(18,6)) Qty,A.CreatedDate As CDate")))
//                    ->join(array('b' => 'MMS_DCTrans'),'a.DCRegisterId=b.DCRegisterId',array(),$selD6::JOIN_INNER)
//                    ->where("a.DcOrCSM=1 and b.ResourceId=$resId and b.ItemId=$itemId");
//                $selD6->combine($selD5,'Union All');
//
//                $selD7 = $sql -> select();
//                $selD7 -> from(array('a' => 'MMS_PVRegister' ))
//                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.PVDate, 113), 4, 8) AS Month, 'Purchase' [Type],Case When A.ThruDC='Y' Then CAST(B.ActualQty As Decimal(18,6)) Else
//                        CAST(B.BillQty As Decimal(18,6)) End Qty,A.PVDate As CDate")))
//                    ->join(array('b' => 'MMS_PVTrans'),'a.PVRegisterId=b.PVRegisterId',array(),$selD7::JOIN_INNER)
//                    ->where("b.DCRegisterId=0 and b.ResourceId=$resId and b.ItemId=$itemId");
//                $selD7->combine($selD6,'Union All');
//
//                $selD8 = $sql -> select();
//                $selD8 -> from(array('a' => 'MMS_Stock' ))
//                    ->columns(array(new Expression("a.ResourceId,a.ItemId, a.CostCentreId,null Month,'OpeningStock' [Type],CAST(a.OpeningStock As Decimal(18,6)) Qty,null As CDate")))
//                    ->where ("a.OpeningStock > 0 and a.ResourceId=$resId and a.ItemId=$itemId");
//                $selD8->combine($selD7,'Union All');
//
//                $selD9 = $sql ->select();
//                $selD9 -> from(array('g' => $selD8 ))
//                     ->columns(array(new Expression("g.ResourceId,g.ItemId,g.CostCentreId,g.Month,g.Type,g.Qty,g.CDate As CDate")))
//                     ->order("CDate");
//                 $statement = $sql->getSqlStringForSqlObject($selD9);

            //WareHouse Stock
            $selW1 = $sql -> select();
            $selW1 -> from(array('a' => 'MMS_StockTrans' ))
                ->columns(array(new Expression("d.WareHouseId,d.WareHouseName,c.Description,Cast(SUM(a.ClosingStock) As Decimal(18,3)) As ClosingStock")))
                ->join(array('b' => 'MMS_Stock'),'a.StockId=b.StockId',array(),$selW1::JOIN_INNER)
                ->join(array('c' => 'MMS_WareHouseDetails'),'a.WareHouseId=c.TransId',array(),$selW1::JOIN_INNER)
                ->join(array('d' => 'MMS_WareHouse'),'c.WareHouseId=d.WareHouseId',array(),$selW1::JOIN_INNER)
                ->join(array('e' => 'MMS_CCWareHouse'),'b.CostCentreId=e.CostCentreId and d.WareHouseId=e.WareHouseId',array(),$selW1::JOIN_INNER)
                ->where ("e.CostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.ClosingStock>0  ");
            $selW1 ->group(new Expression("d.WareHouseId,d.WareHouseName,c.Description"));
            $statement = $sql->getSqlStringForSqlObject($selW1);
            $this->_view->whstock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //

            //Month-Wise BreakUp
            $sel1 = $sql -> select();
            $sel1 -> from (array('a' => 'MMS_TransferRegister'))
                ->columns(array(new Expression("b.ResourceId,b.ItemId,a.FromCostCentreId As CostCentreId,
                      SUBSTRING(CONVERT(Varchar(11),a.TVDate,113),4,8) As Date,'Transfer' As [Type],
                      CAST(-B.TransferQty As Decimal(18,3)) As Qty,A.TVDate As CDate ")))
                ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$sel1::JOIN_INNER)
                ->where("a.FromCostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.TVDate <= '$ason' ");
            $sel2 = $sql -> select();
            $sel2 -> from (array('a' => 'MMS_IssueRegister'))
                ->columns(array(new Expression("b.ResourceId,b.ItemId,a.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.IssueDate, 113), 4, 8) AS Date,
                        'Issue/Return' [Type], CAST(-B.IssueQty As Decimal(18,5)) Qty,A.CreatedDate CDate")))
                ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel2::JOIN_INNER)
                ->where("b.IssueOrReturn='I' and a.IssueOrReturn=0 and a.OwnOrCSM=0 and a.CostCentreId=$ccId and b.ResourceId=$resId
                       and b.ItemId=$itemId and a.IssueDate <= '$ason'");
            $sel2->combine($sel1,'Union All');

            $sel3 = $sql -> select();
            $sel3 -> from(array('G1' => $sel2))
                ->columns(array(new Expression("G1.ResourceId,G1.ItemId,G1.CostCentreId,G1.Date,G1.Type,G1.Qty,G1.CDate As CDate ")));


            $sel4 = $sql -> select();
            $sel4 -> from(array('a' => 'MMS_PRRegister'))
                ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.PRDate,113),4,8) AS Date,
                    'BillReturn' [Type], CAST(-B.ReturnQty As Decimal(18,3)) Qty,A.PRDate As CDate")))
                ->join(array('b' => 'MMS_PRTrans'),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                ->where("a.CostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.PRDate <= '$ason'");

            $sel5 = $sql -> select();
            $sel5 -> from(array('a' => 'MMS_TransferRegister'))
                ->columns(array(new Expression("B.ResourceId,B.ItemId, A.ToCostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.TVDate,113),4,8) AS Date,
                    'Transfer' [Type],ABS(CAST(B.RecdQty As Decimal(18,3))) Qty,A.TVDate As CDate")))
                ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$sel5::JOIN_INNER)
                ->where("a.ToCostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.TVDate <= '$ason'");
            $sel5->combine($sel4,'Union All');

            $sel6 = $sql -> select();
            $sel6 -> from(array('a' => 'MMS_IssueRegister'))
                ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.IssueDate, 113), 4, 8) AS Date,
                    'Issue/Return' [Type],CAST(B.IssueQty As Decimal(18,3)) Qty,A.IssueDate As CDate")))
                ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel6::JOIN_INNER)
                ->where("b.IssueOrReturn='R' and a.IssueOrReturn=1 And a.OwnOrCSM=0 And a.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.IssueDate <= '$ason'");
            $sel6->combine($sel5,'Union All');

            $sel7 = $sql -> select();
            $sel7 -> from(array('a' => 'MMS_DCRegister'))
                ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.DCDate, 113), 4, 8) AS Month,
                        'Receipt' [Type],CAST(B.AcceptQty As Decimal(18,3)) Qty,A.CreatedDate As CDate")))
                ->join(array('b' => 'MMS_DCTrans'),'a.DCRegisterId=b.DCRegisterId',array(),$sel7::JOIN_INNER)
                ->where("A.DcOrCSM=1 And A.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.DCDate <= '$ason'");
            $sel7->combine($sel6,'Union All');

            $sel9 = $sql -> select();
            $sel9 -> from(array('a' => 'MMS_PVRegister'))
                ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.PVDate, 113), 4, 8) AS Month,
                        'Receipt' [Type],Case When A.ThruDC='Y' Then CAST(B.ActualQty As Decimal(18,5)) Else  CAST(B.BillQty As Decimal(18,5)) End Qty, A.PVDate CDate")))
                ->join(array('b' => 'MMS_PVTrans'),'a.PVRegisterId=b.PVRegisterId',array(),$sel9::JOIN_INNER)
                ->where("B.DCRegisterId=0  and A.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.PVDate <= '$ason'");
            $sel9->combine($sel7,'Union All');

            $sel11 = $sql -> select();
            $sel11 -> from (array("G" => $sel9))
                ->columns(array(new Expression("G.ResourceId,G.ItemId,G.CostCentreId,G.Month,G.Type,G.Qty,G.CDate As CDate")));
            $sel11->combine($sel3,'Union All');

            $sel12 = $sql -> select();
            $sel12 ->from(array('G2' => $sel11))
                ->columns(array(new Expression("G2.ResourceId,G2.ItemId,G2.CostCentreId,G2.Month,G2.Type,SUM(G2.Qty) As Qty,
                    DENSE_RANK() OVER ( ORDER BY DATEPART(YYYY, G2.CDate), DATEPART(m, G2.CDate)) As MRank")));
            $sel12 -> group(new Expression("G2.ResourceId,G2.ItemId,G2.CostCentreId,G2.Month,G2.Type,G2.CDate"));
            $statement = $sql->getSqlStringForSqlObject($sel12);
            $selSec = "select ResourceId,ItemId,CostCentreId,Month,MRank,Cast([OpBalance] As Decimal(18,3)) As [OpBalance],
                          Cast(Receipt As Decimal(18,3)) As Receipt,Cast(BillReturn As Decimal(18,3)) As BillReturn,
                          Cast(Transfer As Decimal(18,3)) As Transfer,Cast([Issue/Return] As Decimal(18,3)) As [Issue/Return],
                          Cast([Cl.Balance] As Decimal(18,3)) As [Cl.Balance] from(".$statement.") as P PIVOT (Sum(Qty) For Type IN ([OpBalance],[Receipt],[BillReturn],[Transfer],[Issue/Return],[Cl.Balance]) ) As PVt Order By pvt.MRank";


            $mbreak = $dbAdapter->query($selSec, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



            $selOpbal = $sql -> select();
            $selOpbal -> from (array('s' => 'MMS_Stock'))
                ->columns(array(new Expression("CAST(S.OpeningStock As Decimal(18,3)) As Qty ")))
                ->where("s.OpeningStock>0 and s.CostCentreId=$ccId and s.ResourceId=$resId and s.ItemId= $itemId");
            $obalstatement = $sql->getSqlStringForSqlObject($selOpbal);
            $obal= $dbAdapter->query($obalstatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if(count($mbreak) > 0) {
                for ($i = 0; $i < count($mbreak); $i++) {
                    if ($i == 0) {
                        $mbreak[$i]["OpBalance"] = $this->bsf->isNullCheck($obal['Qty'], 'number');
                    } else {
                        $mbreak[$i]["OpBalance"] = $this->bsf->isNullCheck($mbreak[$i - 1]["Cl.Balance"], 'number');
                    }
                    $mbreak[$i]["Cl.Balance"] = $this->bsf->isNullCheck($mbreak[$i]["OpBalance"], 'number') + $this->bsf->isNullCheck($mbreak[$i]["Receipt"], 'number') +
                        $this->bsf->isNullCheck($mbreak[$i]["BillReturn"], 'number') + $this->bsf->isNullCheck($mbreak[$i]["Transfer"], 'number') +
                        $this->bsf->isNullCheck($mbreak[$i]["Issue/Return"], 'number');

                }
            }
            else {
                $mbreak[0]["ResourceId"] = $resId;
                $mbreak[0]["ItemId"] = $itemId;
                $mbreak[0]["CostCentreId"] = $ccId;
                $mbreak[0]["OpBalance"] = $this->bsf->isNullCheck($obal['Qty'], 'number');
                $mbreak[0]["Cl.Balance"] =$this->bsf->isNullCheck($obal['Qty'], 'number');
            }

//

            $this->_view->mbreak =$mbreak;

            //$cusMBreak = array(array("Month"=>1,"Op.Balance"=>1),array("Month"=>2,"Op.Balance"=>2));

            //

//                $select = $sql->select();
//                $select->from('Proj_WorkTypeMaster')
//                    ->where(array("WorkTypeId=$resId"));
//
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_WorkTypeAnalysis'))
//                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName'), $select:: JOIN_LEFT)
//                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
//                    ->columns(array('IncludeFlag','ReferenceId','ResourceId','Qty','CFormula','Type','TransType', 'Description'))
//                    ->where(array("a.WorkTypeId=$resId"));
//                $select->order('a.SortId ASC');
//


            $this->_view->setTerminal(true);
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
}

    public function stockdaybreakAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $ccId = $postParams['costcentreid'];
                $resId = $postParams['resId'];
                $itemId = $postParams['itemId'];
                $curmonth = $postParams['curmonth'];
                $opbalance = $postParams['opbalance'];
                $ason = date('Y-m-d',strtotime($postParams['ason']));

                $sql = new Sql($dbAdapter);

                $sel1 = $sql -> select();
                $sel1 -> from (array('a' => 'MMS_TransferRegister'))
                    ->columns(array(new Expression("b.ResourceId,b.ItemId,a.FromCostCentreId As CostCentreId,
                      SUBSTRING(CONVERT(Varchar(11),a.TVDate,113),4,8) As Date,'Transfer' As [Type],
                      CAST(-B.TransferQty As Decimal(18,3)) As Qty,A.TVDate As CDate ")))
                    ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$sel1::JOIN_INNER)
                    ->where("a.FromCostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.TVDate <= '$ason' ");
                $sel2 = $sql -> select();
                $sel2 -> from (array('a' => 'MMS_IssueRegister'))
                    ->columns(array(new Expression("b.ResourceId,b.ItemId,a.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.IssueDate, 113), 4, 8) AS Date,
                        'Issue/Return' [Type], CAST(-B.IssueQty As Decimal(18,5)) Qty,Cast(A.CreatedDate As Date) As CDate")))
                    ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel2::JOIN_INNER)
                    ->where("b.IssueOrReturn='I' and a.IssueOrReturn=0 and a.OwnOrCSM=0 and a.CostCentreId=$ccId and b.ResourceId=$resId
                       and b.ItemId=$itemId and a.IssueDate <= '$ason'");
                $sel2->combine($sel1,'Union All');

                $sel3 = $sql -> select();
                $sel3 -> from(array('G1' => $sel2))
                    ->columns(array(new Expression("G1.ResourceId,G1.ItemId,G1.CostCentreId,G1.Date,G1.Type,G1.Qty,G1.CDate As CDate ")));


                $sel4 = $sql -> select();
                $sel4 -> from(array('a' => 'MMS_PRRegister'))
                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.PRDate,113),4,8) AS Date,
                    'BillReturn' [Type], CAST(-B.ReturnQty As Decimal(18,3)) Qty,A.PRDate As CDate")))
                    ->join(array('b' => 'MMS_PRTrans'),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                    ->where("a.CostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.PRDate <= '$ason'");

                $sel5 = $sql -> select();
                $sel5 -> from(array('a' => 'MMS_TransferRegister'))
                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.ToCostCentreId,SUBSTRING(CONVERT(VARCHAR(11),A.TVDate,113),4,8) AS Date,
                    'Transfer' [Type],ABS(CAST(B.RecdQty As Decimal(18,3))) Qty,A.TVDate As CDate")))
                    ->join(array('b' => 'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$sel5::JOIN_INNER)
                    ->where("a.ToCostCentreId=$ccId and b.ResourceId=$resId and b.ItemId=$itemId and a.TVDate <= '$ason'");
                $sel5->combine($sel4,'Union All');

                $sel6 = $sql -> select();
                $sel6 -> from(array('a' => 'MMS_IssueRegister'))
                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.IssueDate, 113), 4, 8) AS Date,
                    'Issue/Return' [Type],CAST(B.IssueQty As Decimal(18,3)) Qty,A.IssueDate As CDate")))
                    ->join(array('b' => 'MMS_IssueTrans'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel6::JOIN_INNER)
                    ->where("b.IssueOrReturn='R' and a.IssueOrReturn=1 And a.OwnOrCSM=0 And
                    a.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.IssueDate <= '$ason'");
                $sel6->combine($sel5,'Union All');

                $sel7 = $sql -> select();
                $sel7 -> from(array('a' => 'MMS_DCRegister'))
                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.DCDate, 113), 4, 8) AS Month,
                        'Receipt' [Type],CAST(B.AcceptQty As Decimal(18,3)) Qty,Cast(A.CreatedDate As Date) As CDate")))
                    ->join(array('b' => 'MMS_DCTrans'),'a.DCRegisterId=b.DCRegisterId',array(),$sel7::JOIN_INNER)
                    ->where("A.DcOrCSM=1 And A.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.DCDate <= '$ason'");
                $sel7->combine($sel6,'Union All');

                $sel9 = $sql -> select();
                $sel9 -> from(array('a' => 'MMS_PVRegister'))
                    ->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,SUBSTRING(CONVERT(VARCHAR(11), A.PVDate, 113), 4, 8) AS Month,
                        'Receipt' [Type],Case When A.ThruDC='Y' Then CAST(B.ActualQty As Decimal(18,5)) Else  CAST(B.BillQty As Decimal(18,5)) End Qty, A.PVDate CDate")))
                    ->join(array('b' => 'MMS_PVTrans'),'a.PVRegisterId=b.PVRegisterId',array(),$sel9::JOIN_INNER)
                    ->where("B.DCRegisterId=0  and A.CostCentreId=$ccId and b.ResourceId=$resId and b.itemid=$itemId and a.PVDate <= '$ason'");
                $sel9->combine($sel7,'Union All');

                $sel11 = $sql -> select();
                $sel11 -> from (array("G" => $sel9))
                    ->columns(array(new Expression("G.ResourceId,G.ItemId,G.CostCentreId,G.Month,G.Type,G.Qty,G.CDate As CDate")));
                $sel11->combine($sel3,'Union All');

                $sel12 = $sql -> select();
                $sel12 ->from(array('G2' => $sel11))
                    ->columns(array(new Expression("G2.ResourceId,G2.ItemId,G2.CostCentreId,G2.Month,G2.Type,SUM(G2.Qty) As Qty,
                    DENSE_RANK() OVER ( ORDER BY DATEPART(YYYY, G2.CDate), DATEPART(m, G2.CDate)) As MRank,G2.CDate As CDate")));
                $sel12 -> group(new Expression("G2.ResourceId,G2.ItemId,G2.CostCentreId,G2.Month,G2.Type,G2.CDate"));
                $statement = $sql->getSqlStringForSqlObject($sel12);
                $selSec = "select ResourceId,ItemId,CostCentreId,Month,CDate,datepart(dd,CDate) As CDay,MRank,CDate,Cast([Op.Balance] As Decimal(18,3)) As [Op.Balance],
                          Cast(Receipt As Decimal(18,3)) As Receipt,Cast(BillReturn As Decimal(18,3)) As BillReturn,
                          Cast(Transfer As Decimal(18,3)) As Transfer,Cast([Issue/Return] As Decimal(18,3)) As [Issue/Return],
                          Cast([Cl.Balance] As Decimal(18,3)) As [Cl.Balance] from(".$statement.") as P PIVOT (Sum(Qty) For Type IN ([Op.Balance],[Receipt],[BillReturn],[Transfer],[Issue/Return],[Cl.Balance]) ) As PVt
                          Where Month='$curmonth' Order By pvt.MRank";


                $dbreak = $dbAdapter->query($selSec, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($dbreak) > 0) {
                    for ($i = 0; $i < count($dbreak); $i++) {
                        if ($i == 0) {
                            $dbreak[$i]["Op.Balance"] = $this->bsf->isNullCheck($opbalance, 'number');
                        } else {
                            $dbreak[$i]["Op.Balance"] = $this->bsf->isNullCheck($dbreak[$i - 1]["Cl.Balance"], 'number');
                        }
                        $dbreak[$i]["Cl.Balance"] = $this->bsf->isNullCheck($dbreak[$i]["Op.Balance"], 'number') + $this->bsf->isNullCheck($dbreak[$i]["Receipt"], 'number') +
                            $this->bsf->isNullCheck($dbreak[$i]["BillReturn"], 'number') + $this->bsf->isNullCheck($dbreak[$i]["Transfer"], 'number') +
                            $this->bsf->isNullCheck($dbreak[$i]["Issue/Return"], 'number');

                    }
                }
                else {
                    $dbreak[0]["ResourceId"] = $resId;
                    $dbreak[0]["ItemId"] = $itemId;
                    $dbreak[0]["CostCentreId"] = $ccId;
                    $dbreak[0]["Op.Balance"] = $this->bsf->isNullCheck($opbalance, 'number');
                    $dbreak[0]["Cl.Balance"] = $this->bsf->isNullCheck($opbalance, 'number');
                }
                $this->_view->dbreak =$dbreak;


                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function inventorystatAction(){
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
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentreId =$this->params()->fromPost('CostCentreId');
                $ason= $this->bsf->isNullCheck($postParams['ason'],'string');
                switch($Type) {
                    case 'inventory':
                        if($ason == ''){
                            $ason =  0;
                        }
                        if($ason == 0) {
                            $ason = date('Y-m-d', strtotime(Date('d-m-Y')));
                        }
                        else
                        {
                            $ason=date('Y-m-d',strtotime($ason));
                        }


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
                            ->join(array('m' => "WF_CityMaster"),"k.CityId=m.CityId",array(),$selM1::JOIN_INNER)
                            ->join(array("l" => "WF_StateMaster"),"m.StateId=l.StateId",array(),$selM1::JOIN_INNER)
                            ->where("a.BillQty>0 and g.DCDate <='$ason' and c.CostCentreId=$CostCentreId
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
                            ->join(array('k' => "WF_CityMaster"),"i.CityId=k.CityId",array(),$selM2::JOIN_INNER)
                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM2::JOIN_INNER)
                            ->where("a.BillQty>0 and c.PVDate <= '$ason' and a.CostCentreId=$CostCentreId and c.CostCentreId=0
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
                            ->join(array('k' => "WF_CityMaster"),"i.CityId=k.CityId",array(),$selM3::JOIN_INNER)
                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM3::JOIN_INNER)
                            ->where("a.BillQty>0 and c.PVDate <= '$ason' and c.CostCentreId=$CostCentreId and
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
                            ->join(array('k' => "WF_CityMaster"),"i.CityId=k.CityId",array(),$selM4::JOIN_INNER)
                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM4::JOIN_INNER)
                            ->where("a.balqty>0 and c.DCDate <= '$ason' and c.CostCentreId=$CostCentreId and
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
                            ->join(array('k' => "WF_CityMaster"),"i.CityId=k.CityId",array(),$selM5::JOIN_INNER)
                            ->join(array("j" => "WF_StateMaster"),"k.StateId=j.StateId",array(),$selM5::JOIN_INNER)
                            ->where("a.BalQty>0 and c.DCDate <= '$ason' and a.CostCentreId=$CostCentreId  and c.CostCentreId=0
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
                            ->where("a.TransferQty>0 and b.TVDate <= '$ason' and b.FromCostCentreId=$CostCentreId
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.FromCostCentreId");
                        $selM6->combine($selM5,'Union All');



                        $selM7 = $sql -> select();
                        $selM7 -> from(array("a" => "MMS_TransferTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.RecdQty),Amount=SUM(a.RecdQty*a.QRate),
                              b.ToCostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$selM6::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM6::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM6::JOIN_LEFT)
                            ->where("a.RecdQty>0 and b.TVDate <= '$ason' and b.ToCostCentreId=$CostCentreId
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.ToCostCentreId");
                        $selM7->combine($selM6,'Union All');



                        $selM8 = $sql -> select();
                        $selM8 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.ReturnQty),Amount=SUM(-Amount),b.CostCentreId As CostCentreId')))
                            ->join(array("b" => "MMS_PRRegister"),"a.PRRegisterId=b.PRRegisterId",array(),$selM8::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM8::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM8::JOIN_INNER)
                            ->where("a.ReturnQty>0 and b.PRDate <= '$ason' and b.CostCentreId=$CostCentreId
                             Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId");
                        $selM8->combine($selM7,'Union All');



                        $selM9 = $sql -> select();
                        $selM9 -> from(array("a" => "MMS_IssueTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(-a.IssueQty),Amount=-SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$ason' and b.CostCentreId=$CostCentreId and b.IssueOrReturn=0 and a.IssueOrReturn='I'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                        $selM9->combine($selM8,'Union All');

                        $selM9a = $sql -> select();
                        $selM9a -> from(array("a" => "MMS_IssueTrans"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,Qty=SUM(a.IssueQty),Amount=SUM(Case When (a.FFactor>0 and a.TFactor>0) Then
                              (a.IssueQty*isnull((a.IssueRate*a.tfactor),0)/nullif(a.ffactor,0)) else IssueAmount End ),b.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_IssueRegister"),"a.IssueRegisterId=b.IssueRegisterId",array(),$selM9::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$selM9::JOIN_INNER)
                            ->join(array("d" => "Proj_ResourceGroup"),"c.ResourceGroupId=d.ResourceGroupId",array(),$selM9::JOIN_LEFT)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$ason' and b.CostCentreId=$CostCentreId and b.IssueOrReturn=1 and a.IssueOrReturn='R'
                              Group By d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId ");
                        $selM9a->combine($selM9,'Union All');



                        $selM10 = $sql ->select();
                        $selM10 -> from(array("a" => "MMS_PVRateAdjustment" ))
                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM10::JOIN_INNER)
                            ->where("d.PVDate <= '$ason'  and d.CostCentreId=$CostCentreId and a.Type='D'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                        $selM10->combine($selM9a,'Union All');


                        $selM11 = $sql ->select();
                        $selM11 -> from(array("a" => "MMS_PVRateAdjustment" ))
                            ->columns(array(new Expression('b.ResourceId,b.ItemId,0 Qty,Amount=-SUM(Case When (c.FFactor>0 and c.TFactor>0) then
                               isnull((a.Amount*c.TFactor),0)/nullif(c.ffactor,0) else a.Amount end),d.CostCentreId As CostCentreId ')))
                            ->join(array("b" => "MMS_PVTrans"),"a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->join(array("c" => "MMS_PVGroupTrans"),"b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->join(array("d" => "MMS_PVRegister"),"b.PVRegisterId=d.PVRegisterId",array(),$selM11::JOIN_INNER)
                            ->where("d.PVDate <= '$ason'  and d.CostCentreId=$CostCentreId and a.Type='C'
                               Group By b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor ");
                        $selM11->combine($selM10,'Union All');



                        $selM12 = $sql -> select();
                        $selM12 -> from(array("a" => "MMS_Stock"))
                            ->columns(array(new Expression('a.ResourceId,a.ItemId,a.OpeningStock As Qty,Amount=a.OpeningStock*a.ORate,a.CostCentreId')))
                            ->where(" a.CostCentreId=$CostCentreId ");
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
                            ->where('RV.TypeId IN (2,3)');

                        $statement = $statement = $sql->getSqlStringForSqlObject($selF2);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($register));
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
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }


}