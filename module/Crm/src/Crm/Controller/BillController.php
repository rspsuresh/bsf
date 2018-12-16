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

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use Application\View\Helper\Qualifier;
use DOMPDF;
class BillController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function progressAction(){
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
        $CompletionId = $this->bsf->isNullCheck($this->params()->fromRoute('stgCId'),'number');
        $sql = new Sql($dbAdapter);
        $this->_view->adp = $dbAdapter;
        $this->_view->arrVNo = CommonHelper::getVoucherNo(802, date('Y-m-d'), 0, 0, $dbAdapter, "");
        $userId = $this->auth->getIdentity()->UserId;
        if($CompletionId !=0){
            $selectStage = $sql->select();
            $selectStage->from(array("a"=>"KF_StageCompletion"));
            $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.CompletionDate,105) as CompletionDate , a.PBRaised,a.FloorId,a.UnitWise,Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName end as StageName,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
								When a.StageType='D' then 'DescriptionName' end as Stage,StageType,a.StageId")),array("ProjectName"))
                ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $selectStage::JOIN_LEFT)
                ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName","BlockId"), $selectStage::JOIN_LEFT)
                ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array("FloorName","FloorId"), $selectStage::JOIN_LEFT)
                ->join(array("e"=>"Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $selectStage::JOIN_LEFT)
                ->join(array("f"=>"Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $selectStage::JOIN_LEFT)
                ->join(array("g"=>"KF_StageMaster"), NEW Expression("a.StageId=g.StageId and a.StageType='S'"), array(), $selectStage::JOIN_LEFT)
                ->where(array('StageCompletionId' => $CompletionId,'a.DeleteFlag'=>0))
                ->order("a.CreatedDate Desc");
            $statement = $sql->getSqlStringForSqlObject($selectStage);
            $stage = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
//            echo '<pre>';
//                print_r($stage);
//                echo '</pre>';
//                die;
            if($stage['PBRaised'] == 1 ){
                $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "progress-register"));
            }
            // echo $stage['UnitWise']; die;



            if($stage['UnitWise'] == 1){
                $selectUnit = $sql->select();
                $selectUnit->from(array("a"=>"KF_StageCompletionTrans"));
                $selectUnit->columns(array(new Expression("a.UnitId,c.UnitNo,b.StageType,Case When b.StageType='S' then g.StageName when b.StageType='O' then f.OtherCostName
										When b.StageType='D' then e.DescriptionName end as Stage,
								d.Amount-d.Discount as Amount,d.QualAmount,case when d.NetAmount > d.PaidAmount then d.NetAmount-d.PaidAmount when d.NetAmount < d.PaidAmount  then 0 end as NetAmount ,d.PaidAmount")),array("ProjectName"))
                    ->join(array("b"=>"KF_StageCompletion"), "a.StageCompletionId=b.StageCompletionId", array(), $selectUnit::JOIN_LEFT)
                    ->join(array("c"=>"KF_UnitMaster"), "a.UnitId=c.UnitId", array(), $selectUnit::JOIN_LEFT)

                    ->join(array("e"=>"Crm_DescriptionMaster"), NEW Expression("b.StageId=e.DescriptionId and b.StageType='D' "), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("f"=>"Crm_OtherCostMaster"), NEW Expression("b.StageId=f.OtherCostId and b.StageType='O'"), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("g"=>"KF_StageMaster"), NEW Expression("b.StageId=g.StageId and b.StageType='S'"), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("h"=>"Crm_UnitBooking"), NEW Expression("h.UnitId=c.UnitId and h.DeleteFlag='0'"), array('BuyerName' => 'BookingName','LeadId'), $selectUnit::JOIN_LEFT)
                    ->join(array("d"=>"Crm_PaymentScheduleUnitTrans"), "b.StageType=d.StageType and b.StageId=d.StageId and h.UnitId=d.UnitId", array(), $selectUnit::JOIN_INNER)
                    ->where(array('a.StageCompletionId' => $CompletionId,'b.DeleteFlag'=>'0'));
                $statement = $sql->getSqlStringForSqlObject($selectUnit);
                $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $strcnt="";
            if($stage['FloorId']!=0) {
                $strcnt = " and a.FloorId=b.FloorId";
            }

            if($stage['UnitWise'] == 0){
                $selectUnit = $sql->select();
                $selectUnit->from(array("a"=>"KF_StageCompletion"));
                $selectUnit->columns(array(new Expression("b.UnitId,b.UnitNo,a.StageType,Case When a.StageType='S' then f.StageName when a.StageType='O' then e.OtherCostName
								When a.StageType='D' then d.DescriptionName end as Stage, c.Amount-c.discount as Amount,c.QualAmount,case when c.NetAmount > c.PaidAmount then c.NetAmount-c.PaidAmount when c.NetAmount < c.PaidAmount  then 0 end as NetAmount ,c.PaidAmount")),array())
                    ->join(array("b"=>"KF_UnitMaster"), "a.ProjectId=b.ProjectId  and a.BlockId=b.BlockId $strcnt", array(), $selectUnit::JOIN_INNER)
                    ->join(array("d"=>"Crm_DescriptionMaster"),NEW Expression("a.StageId=d.DescriptionId and a.StageType='D'"), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("e"=>"Crm_OtherCostMaster"), NEW Expression("a.StageId=e.OtherCostId and a.StageType='O'"), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("f"=>"KF_StageMaster"), NEW Expression("a.StageId=f.StageId and a.StageType='S' "), array(), $selectUnit::JOIN_LEFT)
                    ->join(array("g"=>"Crm_UnitBooking"), NEW Expression("g.UnitId=b.UnitId and g.DeleteFlag='0'"), array('BuyerName' => 'BookingName','LeadId'), $selectUnit::JOIN_LEFT)
                    ->join(array("g"=>"Crm_UnitBooking"), NEW Expression("g.UnitId=b.UnitId and g.DeleteFlag='0'"), array('BuyerName' => 'BookingName','LeadId'), $selectUnit::JOIN_LEFT)
                    ->where(array('a.StageCompletionId' => $CompletionId,'a.DeleteFlag'=>'0'));
                $statement = $sql->getSqlStringForSqlObject($selectUnit);
                $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            }

            $iQualCount = 0;

            foreach($selectUnit as &$unit){
                $SelectReceiptType = $sql->select();
                $SelectReceiptType->from(array("a"=>"Crm_PaymentScheduleUnitReceiptTypeTrans"));
                $SelectReceiptType->columns( array('ReceiptTypeTransId' ,'ReceiptTypeId','ReceiptType','Percentage','Amount'=>new Expression("a.Amount-a.Discount"),'QualAmount','NetAmount' ,'ReceiptTypeName' => new Expression("B.ReceiptTypeName")))
                    ->join(array("b"=>"Crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array("receipt"=>"ReceiptType"), $SelectReceiptType::JOIN_LEFT)
                    ->join(array("c"=>"Crm_PaymentScheduleUnitTrans"), "c.PaymentScheduleUnitTransId=a.PaymentScheduleUnitTransId", array(), $SelectReceiptType::JOIN_INNER)
                    ->where(array('c.UnitId' => $unit['UnitId'],'c.StageId' => $stage['StageId'],'c.StageType' => $stage['StageType']));
                $statement = $sql->getSqlStringForSqlObject($SelectReceiptType);
                $unit['ReceiptTypeTrans'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( array( 'a' => 'Crm_UnitDetails' ) )
                    ->columns(array('BaseAmt'))
                    ->where( array( 'a.UnitId' => $unit['UnitId'] ) );
                $stmt = $sql->getSqlStringForSqlObject( $select );
                $base = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' =>'Crm_ProgressBillTrans'))
                    ->columns(array('LateFee'))
                    ->where( array( 'a.UnitId' => $unit['UnitId'] ) )
                    ->order('ProgressBillTransId desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->latefee= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();






                // Qualifiers added
                foreach($unit['ReceiptTypeTrans'] as &$qual) {
                    $receiptTypeId =  $qual['ReceiptTypeId'];
                    $receiptType =$qual['receipt'];

                    $select = $sql->select();
                    $select->from(array('c' => 'Crm_QualifierSettings'))
                        ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                            array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                    $select->where(array( 'QualSetTypeId' => $receiptTypeId, 'QualSetType' => $receiptType, 'a.QualType' => 'C'))
                        ->order('SortOrder ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    foreach($qualList as &$list){
                        $list['SurCharge'] = 0;
                        $list['EDCess'] =0;
                        $list['HEDCess'] = 0;
                        $list['KKCess'] = 0;
                        $list['SBCess'] = 0;
                        if($list['QualifierTypeId']==1 && $base['BaseAmt'] > 5000000) {
                            $tdstype = 11;
                            $date = date('Y/m/d');
                            $tds = CommonHelper::getTDSSetting($tdstype, $date, $dbAdapter);
                            $list['TaxablePer'] = $tds["TaxablePer"];
                            $list['TaxPer'] = $tds["TaxPer"];
                            $list['SurCharge'] = $tds["SurCharge"];
                            $list['EDCess'] = $tds["EDCess"];
                            $list['HEDCess'] = $tds["HEDCess"];
                            $list['NetPer'] = $tds["NetTax"];
                        }

                        else if($list['QualifierTypeId']==2 ){
                            $select = $sql->select();
                            if ($receiptType == "S") {
                                $select->from('Crm_ReceiptTypeMaster')
                                    ->columns(array('TaxablePer'))
                                    ->where(array('ReceiptTypeId' => $receiptTypeId));

                            } else {
                                $select->from('Crm_OtherCostMaster')
                                    ->columns(array('TaxablePer'))
                                    ->where(array('OtherCostId' => $receiptTypeId));
                            }

                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $Taxable = 0;
                            if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];

                            $tdstype = 'F';
                            $date = date('Y/m/d');
                            $tds = CommonHelper::getSTSetting($tdstype, $date, $dbAdapter);
                            $list['TaxablePer'] = $Taxable;
                            $list['TaxPer'] = $tds["TaxPer"];
                            $list['KKCess'] = $tds["KKCess"];
                            $list['SBCess'] = $tds["SBCess"];
                            $list['NetPer'] = $tds["NetTax"];
                        }
                        else {
                            $list['TaxablePer'] =0;
                            $list['TaxPer'] = 0;
                            $list['NetPer'] = 0;
                        }
                    }
                    $sHtml=Qualifier::getQualifier($qualList);
                    $iQualCount = $iQualCount+1;
                    $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
                    $qual['HtmlTag'] = $sHtml;
                }

            }
            $this->_view->stage = $stage;
            $this->_view->unit = $selectUnit;
        } else {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'completedstage'));
        }

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
                //Print_r($postParams);die;

                $arrVNo = CommonHelper::getVoucherNo(802, date('m-d-Y',strtotime($postParams['BillDate'])), 0, 0, $dbAdapter, "I");
                if($arrVNo['genType']== true){
                    $ProgressNo = $arrVNo['voucherNo'];
                } else {
                    $ProgressNo = $postParams['ProgressNo'];
                }

                $BillDate= date('m-d-Y',strtotime($postParams['BillDate']));
                $ProjectId = $postParams['ProjectId'];
                $BlockId = $postParams['BlockId'];
                $FloorId = $postParams['FloorId'];
                $StageType = $postParams['StageType'];
                $StageId = $postParams['StageId'];
                //$ProgressNo = $postParams['ProgressNo'];
                if($postParams['DemandApproval'] == 1){
                    $DemandApproval=1;
                } else {
                    $DemandApproval=0;
                }
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $insert = $sql->insert('Crm_ProgressBill');
                    $insertData = array(
                        'BillDate'  => $BillDate,
                        'ProjectId' => $ProjectId,
                        'BlockId'=>$BlockId,
                        'FloorId'=>$FloorId,
                        'StageType'=>$StageType,
                        'StageId'=>$StageId,
                        'ProgressNo'=>$ProgressNo,
                        'createdDate'=>date('m-d-Y H:i:s'),
                        'StageCompletionId'=>$CompletionId,
                        'DemandApproval'=>$DemandApproval
                    );
                    $insert->values($insertData);
                   $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $ProgressBillId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    //Trans
                    //voucher no
                    for($i=1; $i<=count($postParams['UnitId']); $i++){
                        $arrTransVNo = CommonHelper::getVoucherNo(803, date('m-d-Y',strtotime($postParams['BillDate'])), 0, 0, $dbAdapter, "I");
                        if($arrVNo['genType']== true){
                            $ProgressTransNo = $arrTransVNo['voucherNo'];
                        } else {
                            $ProgressTransNo = $postParams['PBNo'][$i];
                        }
                        $insert = $sql->insert('Crm_ProgressBillTrans');
                        $insertData = array(
                            'PBNo'=> $ProgressTransNo,
                            'UnitId'  => $postParams['UnitId'][$i],
                            'BuyerId'  => $postParams['LeadId'],
                            'ProgressBillId' => $ProgressBillId,
                            'BillDate'  => $BillDate,
                            'StageId'=>$StageId,
                            'StageType'=>$StageType,
                            'PaidAmount'=> $this->bsf->isNullCheck($postParams['paidamt'][$i],'number'),
                            'Amount'=> $this->bsf->isNullCheck($postParams['Amount'][$i],'number'),
                            'QualAmount'=>$this->bsf->isNullCheck($postParams['QualAmount'][$i],'number'),
                            'NetAmount'=>$this->bsf->isNullCheck($postParams['NetAmount'][$i],'number')
                        );
                        $insert->values($insertData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ProgressTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $unitIdprog=$postParams['UnitId'][$i];

//                        $select = $sql->select();
//                        $select->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
//                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array(), $select::JOIN_INNER)
//                            ->join(array('c' => 'Crm_ReceiptRegister'), 'b.ReceiptId=c.ReceiptId', array(), $select::JOIN_INNER)
//                            ->columns(array('Amount'=> new Expression("Sum(a.NetAmount)")));
//                        $select->where("b.ProgressBillTransId=0 and b.StageId=$StageId and b.StageType='$StageType' and c.UnitId=$unitIdprog and c.DeleteFlag=0 and c.ChequeBounce=0");
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//                        $dPaidAmt=0;
//                        if (!empty($ramt)) $dPaidAmt =  $this->bsf->isNullCheck($ramt->Amount,'number');
//
//
//                        $update = $sql->update();
//                        $update->table('Crm_ProgressBillTrans');
//                        $update->set(array(
//                            'PaidAmount' => $dPaidAmt,
//                        ));
//                        $update->where(array('UnitId' => $postParams['UnitId'][$i], 'StageType' => $StageType, 'StageId' => $StageId));
//                     echo   $statement = $sql->getSqlStringForSqlObject($update); die;
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//

                        if(!isset($postParams['ReceiptTypeId'][$i])){
                            continue;
                        }
                        for($j=1;$j<=count($postParams['ReceiptTypeId'][$i]); $j++){
                            // $postParams['UnitId'][$i];
                            $insert = $sql->insert('Crm_ProgressBillReceiptTypeTrans');
                            $insertData = array(
                                'ProgressBillTransId' => $ProgressTransId,
                                'ReceiptTypeId'=>$postParams['ReceiptTypeId'][$i][$j],
                                'ReceiptType'=>$postParams['ReceiptType'][$i][$j],
                                'Amount'=>$this->bsf->isNullCheck($postParams['ReceiptTypeAmount'][$i][$j],'number'),
                                'QualAmount'=>$this->bsf->isNullCheck($postParams['ReceiptTypeQualAmount'][$i][$j],'number'),
                                'NetAmount'=>$this->bsf->isNullCheck($postParams['ReceiptTypeNetAmount'][$i][$j],'number'),
                                'UnitId'=>$postParams['UnitId'][$i]
                            );
                            $insert->values($insertData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $ReceiptTypeTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            //Qualifier Row Count

                            $qualRefId = $this->bsf->isNullCheck($postParams['BillAbs_'.$i . '_QualRefId_' . $j], 'number');
                            $qualRowId = $this->bsf->isNullCheck($postParams['QualRowId_'.$qualRefId], 'number');

                            for ($k = 1; $k <= $qualRowId; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_Id_' . $k], 'number');
                                $iYesNo = isset($postParams['Qual_' . $qualRefId . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_ExpPer_' . $k], 'number');
                                $iQualTypeId= $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_Sign_' . $k], 'string');

                                $dCessPer=0;
                                $dEDPer=0;
                                $dHEdPer=0;
                                $dCessAmt=0;
                                $dEDAmt=0;
                                $dHEdAmt=0;

                                $dKKCessPer=0;
                                $dSBCessPer=0;
                                $dKKCessAmt=0;
                                $dSBCessAmt=0;

                                if ($iQualTypeId==1) {
                                    $dTaxablePer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId==2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxPer_' . $k], 'number');
                                    $dKKCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_KKCessPer_' . $k], 'number');
                                    $dSBCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_SBCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $qualRefId . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('Crm_ProgressBillQualifierTrans');
                                $insert->Values(array('PBReceiptTypeTransId' =>$ReceiptTypeTransId,'ProgressBillId' => $ProgressBillId,'ProgressBillTransId' => $ProgressTransId,
                                    'ReceiptTypeId' => $postParams['ReceiptTypeId'][$i][$j],
                                    'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                    'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'SBCess'=>$dSBCessPer,'KKCess'=>$dKKCessPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                    'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'SBCessAmt'=>$dSBCessAmt,'KKCessAmt'=>$dKKCessAmt,'NetAmt'=>$dNetAmt));

                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $update = $sql->update();
                        $update->table('Crm_PaymentScheduleUnitTrans');
                        $update->set(array(
                            'BillPassed'=>1,
                        ));
                        $update->where(array('UnitId'=>$postParams['UnitId'][$i],'StageType'=>$StageType,'StageId'=>$StageId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    //Write your Normal form post code here
                    $update = $sql->update();
                    $update->table('KF_StageCompletion');
                    $update->set(array(
                        'PBRaised'=>1,
                    ));
                    $update->where(array('StageCompletionId'=>$CompletionId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);



                    if($DemandApproval == 1) {

                        $select = $sql->select();
                        $select->from(array("a"=>"Crm_ProgressBill"));
                        $select->columns(array(new Expression("b.UnitId,e.UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,a.CreditDays,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType, b.ProgressBillTransId,b.Amount,b.NetAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage, Convert(varchar(10),i.DOB,105) as DOB")))
                            ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("m"=>"Crm_PaymentScheduleUnitTrans"), new Expression("b.StageId=m.StageId and b.StageType = m.StageType and b.UnitId = m.UnitId"), array("PaidAmount"), $select::JOIN_LEFT)
                            ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array(), $select::JOIN_LEFT)
                            ->join(array("f"=>"Crm_UnitBooking"), new Expression("f.UnitId=b.UnitId and f.DeleteFlag = 0"), array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
                            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email','LeadId'), $select::JOIN_LEFT)
                            ->join(array("j"=>"Crm_LeadAddress"), new Expression("j.LeadId=f.LeadId and j.AddressType = 'C'"), array('Address1', 'Address2'), $select::JOIN_LEFT)
                            ->join(array("h"=>"Crm_UnitType"), "h.UnitTypeId=e.UnitTypeId", array('IntPercent'), $select::JOIN_LEFT)
                            ->join(array("i"=>"Crm_LeadPersonalInfo"), "i.LeadId=f.LeadId", array(), $select::JOIN_LEFT)
                            ->where(array('a.ProgressBillId' => $ProgressBillId,'a.DeleteFlag'=>0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selectUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($selectUnits as &$curUnit){
                            $select = $sql->select();
                            $select->from(array("a"=>"Crm_ProgressBillReceiptTypeTrans"));
                            $select->columns( array('PBReceiptTypeTransId','ReceiptTypeId','Percentage','Amount','QualAmount', 'NetAmount' ,'ReceiptTypeName' => new Expression("B.ReceiptTypeName")))
                                ->join(array("b"=>"Crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $select::JOIN_INNER)
                                ->where(array('a.ProgressBillTransId' => $curUnit['ProgressBillTransId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arrTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $curUnit['ReceiptTypeTrans'] = $arrTrans;

                        }
                        $selectStage = $sql->select();
                        $selectStage->from(array("a"=>"Crm_ProgressBill"));
                        $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.BillDate,105) as BillDate,a.ProgressNo, a.DemandApproval,
                        Case When a.StageType='S' then G.StageName when a.StageType='O' then F.OtherCostName When a.StageType='D' then E.DescriptionName end as StageName,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage,a.StageType,b.ProjectName,c.BlockName,c.BlockId,d.FloorName,d.FloorId,a.StageType,a.StageId")),array())
                            ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array('ProjectName'), $selectStage::JOIN_LEFT)
                            ->join(array("j"=>"KF_KickoffRegister"), "j.KickoffId=b.KickoffId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("k"=>"WF_CostCentre"), "k.CostCentreId=j.CostCentreId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("h"=>"WF_CompanyMaster"), "h.CompanyId=k.CompanyId", array('CompanyName', 'Address', 'Mobile', 'Email', 'LogoPath'), $selectStage::JOIN_LEFT)
                            ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("f"=>"Crm_OtherCostMaster"), "a.StageId=f.OtherCostId", array(), $selectStage::JOIN_LEFT)
                            ->join(array("g"=>"KF_StageMaster"), "a.StageId=g.StageId", array(), $selectStage::JOIN_LEFT)
                            ->where(array('a.ProgressBillId' => $ProgressBillId,'a.DeleteFlag'=>0));
                        $statement = $sql->getSqlStringForSqlObject($selectStage);
                        $progressBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

                        require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        foreach($selectUnits as $curUnit) {
                            $select=$sql->select();
                            $select->from('Crm_LeadFollowUp')
                                ->columns (array ('StatusId'))
                                ->where(array('LeadId'=> $curUnit['LeadId']))
                                ->order ('EntryId  desc');
                           $stmt = $sql->getSqlStringForSqlObject( $select );
                            $base = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $pdfhtml = $this->generateProgressBillPdf( array($curUnit), $progressBill );

//                            $ClientPass = substr($curUnit['BuyerName'], 0, 4) . $curUnit['DOB'] ;
//                            $ClientPass = strtoupper(str_replace('-', '', $ClientPass));
//                            echo $pdfhtml; die;

                            // send mail
                            $dompdf = new DOMPDF();
                            $dompdf->load_html($pdfhtml);
                            $dompdf->set_paper("A4");
                            $dompdf->render();

//                            $dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));

                            $canvas = $dompdf->get_canvas();
                            $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                            $output = $dompdf->output();
                            $fileName = "ProgressBill_{$curUnit['PBNo']}_{$curUnit['UnitNo']}.pdf";

                            $dir = 'public/uploads/crm/paymentvoucher/'.$curUnit['UnitId'].'/';
                            if(!is_dir($dir)){
                                mkdir($dir, 0755, true);
                            }
                            file_put_contents($dir.$fileName, $output);
                            $content_encoded = base64_encode($output);



                            //activation mail
                            if($curUnit['Email']!=''||$curUnit['Email']!=null) {
                                $mailData = array(
                                    array(
                                        'name' => 'USERNAME',
                                        'content' => $curUnit['BuyerName']
                                    ),
                                    array(
                                        'name' => 'BUYERADDRESS1',
                                        'content' => $curUnit['Address1']
                                    ),
                                    array(
                                        'name' => 'BUYERADDRESS2',
                                        'content' => $curUnit['Address2']
                                    ),
                                    array(
                                        'name' => 'BUYERMAIL',
                                        'content' => $curUnit['Email']
                                    ),
                                    array(
                                        'name' => 'COMPANYNAME',
                                        'content' => $progressBill['CompanyName']
                                    ),
                                    array(
                                        'name' => 'COMPANYADDRESS',
                                        'content' => $progressBill['Address']
                                    ),
                                    array(
                                        'name' => 'COMPANYMAIL',
                                        'content' => $progressBill['Email']
                                    ),
                                    array(
                                        'name' => 'PROGRESSBILLNO',
                                        'content' => $curUnit['PBNo']
                                    ),
                                    array(
                                        'name' => 'PROGRESSBILLDATE',
                                        'content' => $progressBill['BillDate']
                                    ),
                                    array(
                                        'name' => 'NETAMOUNT',
                                        'content' => $curUnit['NetAmount']
                                    )
                                );
                                $attachment = array(
                                    'name' => $fileName,
                                    'type' => "application/pdf",
                                    'content' => $content_encoded
                                );
                                $viewRenderer->MandrilSendMail()->sendMailWithAttachment($curUnit['Email'], $config['general']['mandrilEmail'], 'Progress Bill', 'crm_progressbill', $attachment, $mailData);
                            }
                            $update = $sql->update();
                            $update->table('Crm_ProgressBillTrans');
                            $update->set(array('DemandLetter' => $fileName, 'DemandDate' => date('m-d-Y H:i:s')))
                                ->where(array("ProgressBillTransId" => $curUnit['ProgressBillTransId']));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);




                            if(isset($curUnit['LeadId'])) {
                                $insert = $sql->insert('Crm_LeadFollowUp');
                                $insertData = array(
                                    'LeadId' => $curUnit['LeadId'],
                                    'FollowUpDate' => date('m-d-Y H:i:s'),
                                    'NatureId' => 3,
                                    'Completed' =>1,
                                    'LeadFlag' =>'B',
                                     'CallTypeId' => 23,
                                    'NextCallDate'=>$BillDate,
                                    'StatusId'=>$base['StatusId'],
                                    'UserId' => $this->auth->getIdentity()->UserId,
                                    'DemandLetterId' => $curUnit['ProgressBillTransId']
                                );
                                $insert->values($insertData);
                               $statement = $sql->getSqlStringForSqlObject($insert);
                                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }

                        }
                    }

                    echo "df";
                    echo ",dsddf";
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Progress-Bill-Add','N','Progress-Bill',$ProgressBillId,$ProjectId, 0, 'CRM', $ProgressNo,$userId, 0 ,0);
                    $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'progress-register'));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function receiptAction(){
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
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );


                $select = $sql->select();
                $select->from( array( 'a' => 'Crm_UnitDetails' ) )
                    ->columns(array('BaseAmt'))
                    ->where( array( 'a.UnitId' => $PostDataStr ) );
                $stmt = $sql->getSqlStringForSqlObject( $select );
                $base = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( array( 'a' => 'Crm_PostSaleDiscountRegister' ) )
                    ->columns(array('PostSaleDiscountId'))
                    ->where( array( 'a.UnitId' => $PostDataStr ) )
                    ->where("DistFlag='0'");
                $stmt = $sql->getSqlStringForSqlObject( $select );
                $posdist = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();





                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                switch($RType) {
                    case 'receiptno':
                        $data = 'N';
                        $select->from('Crm_ReceiptRegister')
                            ->columns(array('ReceiptId'))
                            ->where("ReceiptNo='$PostDataStr'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;

                    case "Bill" :
                        if($posdist['PostSaleDiscountId'] > 0){
                            $postdiscountid=$posdist['PostSaleDiscountId'];
                            $select2 = $sql->select();
                            $select2->from(array("a" => "Crm_PSDPaymentScheduleUnitTrans"))
                                ->columns(array('ProgressBillTransId' => new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(SchDate, 'dd-MM-yyyy')"), 'PBNo' => new Expression("''"), 'BillAmount' => new Expression("a.NetAmount-a.DiscountAmount"), 'StageType', 'StageId', 'ExtraBillRegisterId' => new Expression("CAST(0 As int)"),
                                    'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end"), 'PaidAmount' => new Expression("Sum(a.PaidAmount)")))
                                //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.StageId=b.StageId and a.StageType=b.StageType and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select2::JOIN_LEFT)
                                ->join(array("c" => "KF_StageMaster"), "a.StageId=c.StageId", array(), $select2::JOIN_LEFT)
                                ->join(array("d" => "Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select2::JOIN_LEFT)
                                ->join(array("e" => "Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select2::JOIN_LEFT)
                                ->where("a.UnitId=$PostDataStr and a.StageType<>'A' and a.NetAmount>a.PaidAmounts and a.BillPassed=0 and a.PostSaleDiscountId=$postdiscountid");
                            $select2->group(new Expression('a.SchDate,a.StageId,a.StageType,a.DiscountAmount,c.StageName,d.OtherCostName,e.DescriptionName,a.NetAmount'));
                        }
                        else {
                            $select2 = $sql->select();
                            $select2->from(array("a" => "Crm_PaymentScheduleUnitTrans"))
                                ->columns(array('ProgressBillTransId' => new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(SchDate, 'dd-MM-yyyy')"), 'PBNo' => new Expression("''"), 'BillAmount' => new Expression("a.NetAmount-a.Discount"), 'StageType', 'StageId', 'ExtraBillRegisterId' => new Expression("CAST(0 As int)"),
                                    'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end"), 'PaidAmount' => new Expression("Sum(a.PaidAmount)")))
                                //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.StageId=b.StageId and a.StageType=b.StageType and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select2::JOIN_LEFT)
                                ->join(array("c" => "KF_StageMaster"), "a.StageId=c.StageId", array(), $select2::JOIN_LEFT)
                                ->join(array("d" => "Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select2::JOIN_LEFT)
                                ->join(array("e" => "Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select2::JOIN_LEFT)
                                ->where("a.UnitId=$PostDataStr and a.StageType<>'A' and a.NetAmount>a.PaidAmount and BillPassed=0");
                            $select2->group(new Expression('a.SchDate,a.StageId,a.StageType,a.Discount,c.StageName,d.OtherCostName,e.DescriptionName,a.NetAmount'));
                     }
                        $select1 = $sql->select();
                        $select1->from(array("a"=>"Crm_ExtraBillRegister"))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"),'BillDate' => new Expression("FORMAT(ExtraBillDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("ExtraBillNo"), 'BillAmount' => new Expression("a.NetAmount"),'StageType'=>new Expression("''"),'StageId'=>new Expression("CAST(0 As int)"),'ExtraBillRegisterId',
                                'StageName' => new Expression("'Extra-Bill'"),'PaidAmount'=>new Expression("Sum(a.PaidAmount)")))
                            //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                            ->where("a.UnitId=$PostDataStr and a.NetAmount>a.PaidAmount");
                        $select1->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount'));
                        $select1->combine($select2,'Union ALL');

                        $select->from(array('a' => 'Crm_ProgressBillTrans'))
                            ->columns(array('ProgressBillTransId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo','Amount'=>new Expression("a.Amount + a.QualAmount"),'StageType','StageId','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end"),'PaidAmount'=>new Expression("Sum(a.PaidAmount)")))
                            //  ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=b.ProgressBillTransId and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select::JOIN_LEFT)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
                            ->where("a.UnitId=$PostDataStr and (a.Amount + a.QualAmount) > a.PaidAmount and  a.CancelId=0")
                            ->group(new Expression('a.ProgressBillTransId,a.PBNo,a.BillDate,a.Amount,a.QualAmount,a.StageType,a.StageId,c.StageName,d.OtherCostName,e.DescriptionName'));
                        $select->combine($select1,'Union ALL');

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $billformats = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($billformats as &$bill) {
                            $billId = $bill['ProgressBillTransId'];
                            $sStageType= $bill['StageType'];
                            $iStageId= $bill['StageId'];
                            $iExtraBillId= $bill['ExtraBillRegisterId'];

                            $select = $sql->select();
                            if ($iExtraBillId !=0) {
                                $select->from( array('a' => 'Crm_ExtraBillRegister' ))
                                    ->columns( array('ReceiptTypeId','ReceiptType' =>new Expression("'E'"),'Amount'))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=a1.ExtraBillRegisterId and a.UnitId=a1.UnitId', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('ReceiptTypeName',"receipt"=>"ReceiptType"), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId', array( 'PaidAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                                    ->where("a.ExtraBillRegisterId=$iExtraBillId and a.Amount<>0")
                                    ->group(new Expression('a.ReceiptTypeId,a.Amount, b.ReceiptType,b.ReceiptTypeName'));
                            } else if ($billId !=0) {
                                $select->from( array('a' => 'Crm_ProgressBillReceiptTypeTrans' ))
                                    ->columns( array('ReceiptTypeId','ReceiptType','Amount','ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=a1.ProgressBillTransId', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array("receipt"=>"ReceiptType"), $select::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId', array( 'PaidAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                                    ->where("a.ProgressBillTransId=$billId and a.Amount<>0")
                                    ->group(new Expression('a.ReceiptTypeId,a.ReceiptType, b.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                            } else {
                                if($posdist['PostSaleDiscountId'] > 0){
                                    $select->from( array('a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ))
                                        ->columns( array('ReceiptTypeId','ReceiptType','Amount'=>new Expression("a.Amount-a.DiscountAmount"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                        ->join(array('a1' => 'Crm_PSDPaymentScheduleUnitTrans'), 'a.PSDPaymentScheduleUnitTransId=a1.PSDPaymentScheduleUnitTransId', array(), $select::JOIN_INNER)
                                        ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.StageId=a2.StageId and a1.StageType=a2.StageType and a2.UnitId=a1.UnitId', array(), $select::JOIN_LEFT)
                                        ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array("receipt"=>"ReceiptType"), $select::JOIN_LEFT)
                                        ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select::JOIN_LEFT)
                                        ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a2.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId', array( 'PaidAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                                        ->where("a1.UnitId=$PostDataStr and a.DistFlag=0 and a1.StageType<>'A' and a1.StageId=$iStageId and a1.StageType='$sStageType' and a.Amount<>0")
                                        ->group(new Expression('a.ReceiptTypeId,a.ReceiptType, b.ReceiptType,a.DiscountAmount,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                                }
                                else{
                                $select->from( array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ))
                                    ->columns( array('ReceiptTypeId','ReceiptType','Amount'=>new Expression("a.Amount-a.Discount"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_PaymentScheduleUnitTrans'), 'a.PaymentScheduleUnitTransId=a1.PaymentScheduleUnitTransId', array(), $select::JOIN_INNER)
                                    ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.StageId=a2.StageId and a1.StageType=a2.StageType and a2.UnitId=a1.UnitId', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array("receipt"=>"ReceiptType"), $select::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a2.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId', array( 'PaidAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                                    ->where("a1.UnitId=$PostDataStr and a1.StageType<>'A' and a1.StageId=$iStageId and a1.StageType='$sStageType' and a.Amount<>0")
                                    ->group(new Expression('a.ReceiptTypeId,a.ReceiptType, b.ReceiptType,a.Discount,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                           }
                        }
                          $statement = $sql->getSqlStringForSqlObject($select);
                            $billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            foreach($billabs as &$qual) {
                                $receiptTypeId = $qual['ReceiptTypeId'];
                                $receiptType = $qual['ReceiptType'];

                                if($receiptType=='L' || $receiptType=='C'){
                                    $receiptType ='S';
                                }

                                $select = $sql->select();
                                $select->from(array('c' => 'Crm_QualifierSettings'))
                                    ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                                        array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                            'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                                $select->where(array( 'QualSetTypeId' => $receiptTypeId, 'QualSetType' => $receiptType, 'a.QualType' => 'C'))
                                    ->order('SortOrder ASC');
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                foreach($qualList as &$list){
                                    $list['KKCess'] = 0;
                                    $list['SBCess'] = 0;
                                    $list['SurCharge'] = 0;
                                    $list['EDCess'] = 0;
                                    $list['HEDCess'] = 0;

                                    if($list['QualifierTypeId']==1 && $base['BaseAmt']> 5000000) {
                                        $tdstype = 11;
                                        $date = date('Y/m/d');
                                        $tds = CommonHelper::getTDSSetting($tdstype, $date, $dbAdapter);
                                        $list['TaxablePer'] = $tds["TaxablePer"];
                                        $list['TaxPer'] = $tds["TaxPer"];
                                        $list['SurCharge'] = $tds["SurCharge"];
                                        $list['EDCess'] = $tds["EDCess"];
                                        $list['HEDCess'] = $tds["HEDCess"];
                                        $list['NetPer'] = $tds["NetTax"];
                                    }

                                    else if($list['QualifierTypeId']==2){
                                        $select = $sql->select();
                                        if ($receiptType == "S") {
                                            $select->from('Crm_ReceiptTypeMaster')
                                                ->columns(array('TaxablePer'))
                                                ->where(array('ReceiptTypeId' => $receiptTypeId));

                                        } else {
                                            $select->from('Crm_OtherCostMaster')
                                                ->columns(array('TaxablePer'))
                                                ->where(array('OtherCostId' => $receiptTypeId));
                                        }

                                        $stmt = $sql->getSqlStringForSqlObject($select);
                                        $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                        $Taxable = 0;
                                        if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];


                                        $tdstype = 'F';
                                        $date = date('Y/m/d');
                                        $tds = CommonHelper::getSTSetting($tdstype, $date, $dbAdapter);
                                        $list['TaxablePer'] = $Taxable;
                                        $list['TaxPer'] = $tds["TaxPer"];
                                        $list['KKCess'] = $tds["KKCess"];
                                        $list['SBCess'] = $tds["SBCess"];
                                        $list['NetPer'] = $tds["NetTax"];
                                    }
                                    else {

                                        $list['TaxablePer'] =0;
                                        $list['TaxPer'] = 0;
                                        $list['NetPer'] = 0;
                                    }

                                }

                                $sHtml=Qualifier::getQualifier($qualList);
                                $qual['HtmlTag'] = $sHtml;

                            }

                            $bill['BillAbs'] = $billabs;
                        }


                        $data = json_encode($billformats);

                }

                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                if($mode=='amount'){
                    $unitId = $this->bsf->isNullCheck($postData['id'], 'number');
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitDetails'))
                        ->columns(array('AdvAmount'))
                        ->where(array("a.UnitId"=>$unitId));
                   $statement = $sql->getSqlStringForSqlObject($select);
                    $detamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_PostSaleDiscountRegister'))
                        ->columns(array('PostSaleDiscountId'))
                        ->where(array("a.UnitId"=>$unitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $discount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if($discount['PostSaleDiscountId'] > 0 ){
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('NetAmount'))
                            ->where(array("a.UnitId" => $unitId))
                            ->where(array("a.stageType" => 'A'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $detpay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    else {
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('NetAmount'))
                            ->where(array("a.UnitId" => $unitId))
                            ->where(array("a.stageType" => 'A'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $detpay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    if($detpay['NetAmount']==0 || $detpay['NetAmount']=='' || $detpay['NetAmount']==NULL){
                        $amt=$detamt['AdvAmount'];
                       // Print_r($amt);die;
                    }
                    else{
                        $amt=$detpay['NetAmount'];
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array('Amount'=>new Expression("sum(a.Amount)")))
                        ->where("a.UnitId=$unitId and ReceiptAgainst='A' and a.CancelId=0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $recamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($amt > $recamt['Amount']){
                        $data=$amt - $recamt['Amount'];
                    }
                    else{
                        $data=0;
                    }

                }
                else if($mode=='submit'){


                        $unitId = $this->bsf->isNullCheck($postData['id'], 'number');
                        $receiptId = $this->bsf->isNullCheck($postData['receiptId'], 'number');
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitDetails'))
                            ->columns(array('AdvAmount'))
                            ->where(array("a.UnitId"=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $detamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('NetAmount'))
                            ->where(array("a.UnitId"=>$unitId))
                            ->where(array("a.stageType"=>'A'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $detpay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($detpay['NetAmount']==0 || $detpay['NetAmount']=='' || $detpay['NetAmount']==NULL){
                            $amt=$detamt['AdvAmount'];
                            // Print_r($amt);die;
                        }
                        else{
                            $amt=$detpay['NetAmount'];
                        }

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('Amount'=>new Expression("sum(a.Amount)")))
                            ->where("a.UnitId=$unitId and ReceiptAgainst='A' and a.CancelId=0")
                            ->where->notEqualTo('a.ReceiptId', $receiptId);
                      $statement = $sql->getSqlStringForSqlObject($select);
                        $recamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($amt > $recamt['Amount']){
                            $data=$amt - $recamt['Amount'];
                        }
                        else{
                            $data=0;
                        }


                }else if($mode=='LateInt'){
                    $unitId = $this->bsf->isNullCheck($postData['id'], 'number');
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProgressBillTrans'))
                        ->columns(array('LateAmount'=>new expression('SUM(LateFee)')))
                        ->where(array("a.UnitId"=>$unitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $detamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                     $amt=floatval($detamt['LateAmount']);


                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array('Amount'=>new Expression("sum(a.Amount)")))
                        ->where("a.UnitId=$unitId and ReceiptAgainst='L' and a.CancelId=0");
                   $statement = $sql->getSqlStringForSqlObject($select);
                    $recamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($amt > floatval($recamt['Amount'])){
                        $data= floatval($amt) - floatval($recamt['Amount']);
                    }
                    else{
                        $data=0;
                    }

                }
                else if($mode=="rent") {

                    $unitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');

                    $select = $sql->select();
                    $select->from(array('a' => 'PM_RentBillRegister'))
                        ->columns(array("TotalAmountPayable","PVNo","RegisterId"))
                        ->where("a.UnitId=$unitId and a.DeleteFlag=0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $data['beAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $data['reAmt']=false;
                    if(isset($data['beAmt'])) {
                        $rId = $data['beAmt']['RegisterId'];
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                            ->where(array("a.RentRegisterId"=>$rId ,"a.DeleteFlag"=>0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $data['reAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }
                    $data=json_encode($data);
                }
                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                // Print_r($postData);die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $recpid = $this->bsf->isNullCheck($postData['receiptid'], 'number');
                    $sql = new Sql($dbAdapter);
                    $userId = $this->auth->getIdentity()->UserId;

                    $sMode='Add';
                    if ($recpid == 0) {
                        $UnitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $iLeadId= $this->bsf->isNullCheck($postData['leadid'], 'number');
                        $ReceiptDate = $this->bsf->isNullCheck($postData['ReceiptDate'], 'string');
                        $ReceiptNo = $this->bsf->isNullCheck($postData['ReceiptNo'], 'string');
                        $ReceiptAgainst = $this->bsf->isNullCheck($postData['ReceiptAgainst'], 'string');
                        $PaymentMode = $this->bsf->isNullCheck($postData['PaymentMode'], 'string');
                        $TNo = $this->bsf->isNullCheck($postData['TNo'], 'string');
                        $TDate = $this->bsf->isNullCheck($postData['TDate'], 'string');
                        $Remarks = $this->bsf->isNullCheck($postData['Remarks'], 'string');
                        $Amount = $this->bsf->isNullCheck($postData['Amount'], 'number');
                        $rentRegId = $this->bsf->isNullCheck($postData['rentRegId'], 'number');
                        $rentBillNo = $this->bsf->isNullCheck($postData['rentBillNo'], 'string');
                        $rentBillAmt = $this->bsf->isNullCheck($postData['rentBillAmt'], 'number');
                        $latefee = $this->bsf->isNullCheck($postData['latefee'], 'number');

                        $grossAmt =$this->bsf->isNullCheck($postData['BillCurTotal'], 'number');
                        $excessAmt =$this->bsf->isNullCheck($postData['BillExcessAmt'], 'number');
                        $qualAmt =$this->bsf->isNullCheck($postData['BillTaxTotal'], 'number');
                        $netAmt =$this->bsf->isNullCheck($postData['BillNetTotal'], 'number');

                        $sVno= $ReceiptNo;

                        $aVNo = CommonHelper::getVoucherNo(805, date('Y-m-d', strtotime($postData['ReceiptDate'])), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == true) $sVno = $aVNo["voucherNo"];

                        $insert = $sql->insert();
                        $insert->into('Crm_ReceiptRegister');
                        $insert->Values(array('UnitId' => $UnitId,
                            'LeadId' => $iLeadId,
                            'ReceiptNo' => $sVno,
                            'ReceiptDate' => date('Y-m-d', strtotime($ReceiptDate)),
                            'ReceiptAgainst' => $ReceiptAgainst,
                            'ReceiptMode' => $PaymentMode,
                            'TransNo' => $TNo,
                            'TransDate' => date('Y-m-d', strtotime($TDate)),
                            'Remarks' => $Remarks,
                            'Amount' => $Amount,
                            'GrossAmount' => $grossAmt,
                            'ExcessAmount' => $excessAmt,
                            'LateIntAmount' => $latefee,
                            'QualAmount' => $qualAmt,
                            'NetAmount' => $netAmt,
                            'RentBillNo'=>$rentBillNo,
                            'RentBillAmount'=>$rentBillAmt,
                            'RentRegisterId'=>$rentRegId,
                            'BankName' => $this->bsf->isNullCheck($postData['BankName'],'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);

                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ReceiptId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $billrowid = $this->bsf->isNullCheck($postData['billrowid'],'number');

                        for ($i = 1; $i <= $billrowid; $i++) {
                            $BillId = $this->bsf->isNullCheck($postData['BillId_' . $i],'number');
                            $iStageId = $this->bsf->isNullCheck($postData['StageId_' . $i],'number');
                            $sStageType = $this->bsf->isNullCheck($postData['StageType_' . $i],'string');
                            $iExtraBillId= $this->bsf->isNullCheck($postData['ExtraBillId_' . $i],'number');

                            $BAmount = $this->bsf->isNullCheck($postData['CurAmt_' . $i],'number');
                            $QAmount = $this->bsf->isNullCheck($postData['TaxAmt_' . $i],'number');
                            $NAmount = $this->bsf->isNullCheck($postData['NetAmt_' . $i],'number');
                            $UnitId = $this->bsf->isNullCheck($postData['unitid'], 'number');

                            if ($BAmount == 0) continue;

                            $insert = $sql->insert();
                            $insert->into('Crm_ReceiptAdjustment');
                            $insert->Values(array('ReceiptId' => $ReceiptId,'ProgressBillTransId' => $BillId,'StageId'=>$iStageId,'UnitId'=>$UnitId,'StageType'=>$sStageType,'ExtraBillRegisterId'=>$iExtraBillId, 'Amount'=> $BAmount,'QualAmount' => $QAmount,'NetAmount'=>$NAmount));
                              $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $adjId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $billabsrowid = $this->bsf->isNullCheck($postData['billabsrowid_'.$i],'number');
                            for ($j = 1; $j <= $billabsrowid; $j++) {
                                $iReceiptTypeId = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_ReceiptTypeId_' . $j],'number');
                                $sReceiptType = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_ReceiptType_' . $j],'string');
                                $AbsAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_CurAmt_' . $j],'number');
                                $qTAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_TaxAmt_' . $j],'number');
                                $nTAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_NetAmt_' . $j],'number');

                                if ($AbsAmount == 0 || $iReceiptTypeId==0) continue;

                                $insert = $sql->insert();
                                $insert->into('Crm_ReceiptAdjustmentTrans');
                                $insert->Values(array('ReceiptAdjId' =>$adjId,'ReceiptTypeId'=>$iReceiptTypeId,'ReceiptType'=>$sReceiptType,'Amount'=> $AbsAmount,'QualAmount'=>$qTAmount,'NetAmount'=>$nTAmount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $adjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $qRefId =   $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_QualRefId_' . $j],'number');
                                $qRowId =   $this->bsf->isNullCheck($postData['QualRowId_'.$qRefId],'number');

                                for ($k = 1; $k <= $qRowId; $k++) {
                                    $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Id_' . $k], 'number');
                                    $iYesNo = isset($postData['Qual_' . $qRefId . '_YesNo_' . $k]) ? 1 : 0;
                                    $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Exp_' . $k], 'string');
                                    $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpValue_' . $k], 'number');
                                    $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                    $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TypeId_' . $k], 'number');
                                    $sSign = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Sign_' . $k], 'string');

                                    $dCessPer = 0;
                                    $dEDPer = 0;
                                    $dHEdPer = 0;
                                    $dCessAmt = 0;
                                    $dEDAmt = 0;
                                    $dHEdAmt = 0;
                                    $dKKCessPer=0;
                                    $dSBCessPer=0;
                                    $dKKCessAmt=0;
                                    $dSBCessAmt=0;

                                    if ($iQualTypeId==1 ) {
                                        $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxablePer_' . $k], 'number');
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPer_' . $k], 'number');
                                        $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_CessPer_' . $k], 'number');
                                        $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_EduCessPer_' . $k], 'number');
                                        $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_HEduCessPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetPer_' . $k], 'number');

                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxableAmt_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPerAmt_' . $k], 'number');
                                        $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_CessAmt_' . $k], 'number');
                                        $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_EduCessAmt_' . $k], 'number');
                                        $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_HEduCessAmt_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetAmt_' . $k], 'number');
                                    } else if  ($iQualTypeId==2) {

                                        $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxablePer_' . $k], 'number');
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPer_' . $k], 'number');
                                        $dKKCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_KKCessPer_' . $k], 'number');
                                        $dSBCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_SBCessPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetPer_' . $k], 'number');

                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxableAmt_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPerAmt_' . $k], 'number');
                                        $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_KKCessAmt_' . $k], 'number');
                                        $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_SBCessAmt_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetAmt_' . $k], 'number');

                                    } else {
                                        $dTaxablePer = 100;
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpValue_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Amount_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Amount_' . $k], 'number');
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('Crm_ReceiptQualifierTrans');
                                    $insert->Values(array('ReceiptAdjTransId' =>$adjTransId, 'QualTypeId'=>$iQualTypeId,
                                        'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                        'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'SBCess'=>$dSBCessPer,'KKCess'=>$dKKCessPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                        'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'SBCessAmt'=>$dSBCessAmt,'KKCessAmt'=>$dKKCessAmt, 'NetAmt'=>$dNetAmt));

                                     $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $select = $sql->select();
                                $select->from( array( 'a' => 'Crm_PostSaleDiscountRegister' ) )
                                    ->columns(array('PostSaleDiscountId'))
                                    ->where( array( 'a.UnitId' => $postData['unitid'] ) )
                                    ->where("DistFlag='0'");
                                $stmt = $sql->getSqlStringForSqlObject( $select );
                                $posdist = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
                                    ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array(), $select::JOIN_INNER)
                                    ->join(array('c' => 'Crm_ReceiptRegister'), 'b.ReceiptId=c.ReceiptId', array(), $select::JOIN_INNER)
                                    ->columns(array('Amount'=> new Expression("Sum(a.NetAmount)")));
                                if ($iExtraBillId !=0) {
                                    $select->where("b.ExtraBillRegisterId=$iExtraBillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and b.UnitId='$UnitId' and c.DeleteFlag=0 and c.ChequeBounce=0");
                                } else if ($BillId !=0) {
                                    $select->where("b.ProgressBillTransId=$BillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and b.UnitId='$UnitId' and c.ChequeBounce=0");
                                } else {
                                    $select->where("b.StageId=$iStageId and b.StageType='$sStageType' and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and b.UnitId='$UnitId' and c.DeleteFlag=0 and c.ChequeBounce=0");
                                }

                               $statement = $sql->getSqlStringForSqlObject($select);
                                $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $dPaidAmt=0;
                                if (!empty($ramt)) $dPaidAmt =  $this->bsf->isNullCheck($ramt->Amount,'number');
                              // Print_r($dPaidAmt);die;


                                $update = $sql->update();
                                if ($BillId !=0) {
                                    $update->table('Crm_ProgressBillReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt));
                                    $update->where("ProgressBillTransId=$BillId and ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType'");

                                } else {
                                    if($posdist['PostSaleDiscountId']>0){
                                        $subQuery = $sql->select();
                                        $subQuery->from("Crm_PSDPaymentScheduleUnitTrans")
                                            ->columns(array('PSDPaymentScheduleUnitTransId'))
                                            ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                        $update->table('Crm_PSDPaymentScheduleUnitReceiptTypeTrans');
                                        $update->set(array('PaidAmount' => $dPaidAmt))
                                            ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PSDPaymentScheduleUnitTransId IN ?", array($subQuery));
                                    }
                                    else{
                                    $subQuery = $sql->select();
                                    $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                        ->columns(array('PaymentScheduleUnitTransId'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                    $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt))
                                        ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                }
                          }
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                if ($BillId !=0) {
                                    if($posdist['PostSaleDiscountId']>0){
                                        $subQuery = $sql->select();
                                        $subQuery->from("Crm_PSDPaymentScheduleUnitTrans")
                                            ->columns(array('PSDPaymentScheduleUnitTransId'))
                                            ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                        $update = $sql->update();
                                        $update->table('Crm_PSDPaymentScheduleUnitReceiptTypeTrans');
                                        $update->set(array('PaidAmount' => $dPaidAmt))
                                            ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PSDPaymentScheduleUnitTransId IN ?", array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else{

                                    $subQuery = $sql->select();
                                    $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                        ->columns(array('PaymentScheduleUnitTransId'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                    $update = $sql->update();
                                    $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt))
                                        ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                               }
                            }
                            }

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustment'))
                                ->join(array('b' => 'Crm_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select::JOIN_INNER)
                                ->columns(array('Amount'=> new Expression("(a.NetAmount)")));

                            if ($iExtraBillId !=0) {
                                $select->where("a.ExtraBillRegisterId=$iExtraBillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else if ($BillId !=0) {
                                $select->where("a.ProgressBillTransId=$BillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else {
                                $select->where("a.StageId=$iStageId and a.StageType='$sStageType' and a.UnitId =$UnitId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            }
                            $select->order('ReceiptAdjId desc');
                             $statement = $sql->getSqlStringForSqlObject($select);
                            $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $dPaidAmt=0;
                            if (!empty($ramt)) $dPaidAmt =  $this->bsf->isNullCheck($ramt->Amount,'number');
                           //Print_r($dPaidAmt);

                            if ($iExtraBillId !=0) {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ExtraBillRegister'))
                                    ->columns(array('PaidAmount'))
                                    ->where(array("ExtraBillRegisterId=$iExtraBillId"));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $dt= $progress['PaidAmount']+$dPaidAmt;

                                $update = $sql->update();
                                $update->table('Crm_ExtraBillRegister');
                                $update->set(array('PaidAmount' => $dt));
                                $update->where("ExtraBillRegisterId=$iExtraBillId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            } else if (intval($BillId) !=0) {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ProgressBillTrans'))
                                    ->columns(array('PaidAmount'))
                                    ->where(array("ProgressBillTransId=$BillId"));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $dt= $progress['PaidAmount']+$dPaidAmt;

                                $update = $sql->update();
                                $update->table('Crm_ProgressBillTrans');
                                $update->set(array('PaidAmount' => $dt));
                                $update->where("ProgressBillTransId=$BillId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if($posdist['PostSaleDiscountId']>0){
                                    $select = $sql->select();
                                    $select->from("Crm_PSDPaymentScheduleUnitTrans")
                                        ->columns(array('PSDPaymentScheduleUnitTransId', 'PaidAmounts'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $dt = $progress['PaidAmount'] + $dPaidAmt;

                                    $update = $sql->update();
                                    $update->table('Crm_PSDPaymentScheduleUnitTrans');
                                    $update->set(array('PaidAmounts' => $dt))
                                        ->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $select = $sql->select();
                                    $select->from("Crm_PaymentScheduleUnitTrans")
                                        ->columns(array('PaymentScheduleUnitTransId', 'PaidAmount'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $dt = $progress['PaidAmount'] + $dPaidAmt;

                                    $update = $sql->update();
                                    $update->table('Crm_PaymentScheduleUnitTrans');
                                    $update->set(array('PaidAmount' => $dt))
                                        ->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                              }
                                    $select = $sql->select();
                                    $select->from(array('a' => 'Crm_ProgressBillTrans'))
                                        ->columns(array('PaidAmount', 'ProgressBillTransId'))
                                        ->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if(count($progress['ProgressBillTransId'])>0) {

                                    $dt = $progress['PaidAmount'] + $dPaidAmt;

                                    $update = $sql->update();
                                    $update->table('Crm_ProgressBillTrans');
                                    $update->set(array('PaidAmount' => $dt));
                                    $update->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                              if (intval($BillId) !=0) {
                                  if($posdist['PostSaleDiscountId']>0){
                                      $select = $sql->select();
                                      $select->from("Crm_PSDPaymentScheduleUnitTrans")
                                          ->columns(array('PSDPaymentScheduleUnitTransId', 'PaidAmounts'))
                                          ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                      $stmt = $sql->getSqlStringForSqlObject($select);
                                      $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                      $dt = $progress['PaidAmount'] + $dPaidAmt;

                                      $update = $sql->update();
                                      $update->table('Crm_PSDPaymentScheduleUnitTrans');
                                      $update->set(array('PaidAmounts' => $dt))
                                          ->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                      $statement = $sql->getSqlStringForSqlObject($update);
                                      $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                  }else {
                                      $select = $sql->select();
                                      $select->from("Crm_PaymentScheduleUnitTrans")
                                          ->columns(array('PaymentScheduleUnitTransId', 'PaidAmount'))
                                          ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");
                                      $stmt = $sql->getSqlStringForSqlObject($select);
                                      $progress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                      $dt = $progress['PaidAmount'] + $dPaidAmt;

                                      $update = $sql->update();
                                      $update->table('Crm_PaymentScheduleUnitTrans');
                                      $update->set(array('PaidAmount' => $dt))
                                          ->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                      $statement = $sql->getSqlStringForSqlObject($update);
                                      $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                               }

                            }
                        }


                        $billqualrowid = $this->bsf->isNullCheck($postData['billqualrowid'],'number');
                        for ($n = 1; $n <= $billqualrowid; $n++) {
                            $iQualifierId =  $this->bsf->isNullCheck($postData['QualifierId_' . $n], 'number');
                            $dQualAmt =  $this->bsf->isNullCheck($postData['QualAmt_' . $n], 'number');
                            $sSign = "+";
                            if ($dQualAmt < 0) $sSign = "-";
                            $dQualAmt = abs($dQualAmt);

                            $insert = $sql->insert();
                            $insert->into('Crm_ReceiptQualifierAbstract');
                            $insert->Values(array('ReceiptId' => $ReceiptId,'QualifierId' => $iQualifierId,'Amount' => $dQualAmt,'Sign'=>$sSign));
                            $statement = $sql->getSqlStringForSqlObject($insert);

                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Receipt-Add','N','CRM-Receipt',$ReceiptId,0, 0, 'CRM','',$userId, 0 ,0);

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_LEFT)
                            ->join(array("h"=>"KF_FloorMaster"), "h.FloorId=b.FloorId", array('FloorName'), $select::JOIN_LEFT)
                            ->join(array("i"=>"KF_BlockMaster"), "i.BlockId=b.BlockId", array('BlockName'), $select::JOIN_LEFT)
                            ->join(array("c"=>"Proj_ProjectMaster"), "c.ProjectId=b.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                            ->join(array("d"=>"WF_CompanyMaster"), "d.CompanyId=c.CompanyId", array('CompanyName', 'Address', 'Mobile', 'Email', 'LogoPath'), $select::JOIN_LEFT)
                            ->join(array('f' => 'Crm_UnitBooking'), 'f.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Crm_Leads'), 'e.LeadId=f.LeadId', array('LeadName','Email','Mobile'), $select::JOIN_LEFT)
                            ->join(array('j' => 'Crm_LeadAddress'), new Expression("j.LeadId=e.LeadId and j.AddressType = 'P' "), array('Address1'), $select::JOIN_LEFT)
                            ->where(array('a.ReceiptId' => $ReceiptId, 'a.DeleteFlag' => 0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $receiptInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(empty($receiptInfo)) {
                            throw new \Exception('Invalid Receipt!');
                        }

                        $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";

                        $pdfHtml = $this->generateReceiptPdf($receiptInfo);

                        require_once($path);

                        $dompdf = new DOMPDF();
                        $dompdf->load_html($pdfHtml);


                        $dompdf->set_paper("A4");
                        $dompdf->render();
                        $canvas = $dompdf->get_canvas();
                        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                        $output = $dompdf->output();
                        $fileName = "ReceiptEntry_{$ReceiptId}.pdf";
                        $content_encoded = base64_encode($output);

                        $dir = 'public/uploads/crm/Receipt/'.$ReceiptId.'/';
                        if(!is_dir($dir)){
                            mkdir($dir, 0755, true);
                        }
                        file_put_contents($dir.$fileName, $output);
                        $mailData = array(
                            array(
                                'name' => 'LEADNAME',
                                'content' => $receiptInfo['LeadName']
                            ),
                            array(
                                'name' => 'UNITNO',
                                'content' => $receiptInfo['UnitNo']
                            ),
                            array(
                                'name' => 'FLOORNAME',
                                'content' => $receiptInfo['FloorName']
                            ),
                            array(
                                'name' => 'BLOCKNAME',
                                'content' => $receiptInfo['BlockName']
                            ),
                            array(
                                'name' => 'BUYERMAILID',
                                'content' => $receiptInfo['Email']
                            ),
                            array(
                                'name' => 'BUYERMOBILENO',
                                'content' => $receiptInfo['Mobile']
                            ),
                            array(
                                'name' => 'BUYERADDRESS',
                                'content' => $receiptInfo['Address1']
                            ),
                            array(
                                'name' => 'PAIDAMOUNT',
                                'content' => $receiptInfo['NetAmount']
                            ),


                        );
                        $Tomail = $receiptInfo['Email'];
                      if($receiptInfo['Email']!=''||$receiptInfo['Email']!=null) {
                          $attachment = array(
                              'name' => $fileName,
                              'type' => "application/pdf",
                              'content' => $content_encoded
                          );
                          $sm = $this->getServiceLocator();
                          $config = $sm->get('application')->getConfig();
                          $viewRenderer->MandrilSendMail()->sendMailWithAttachment($receiptInfo['Email'], $config['general']['mandrilEmail'], 'Receipt Conformation', 'Crm_ReceiptConformation', $attachment, $mailData);
                      }

                    } else {

                        $sMode='Edit';

                        $UnitId = $this->bsf->isNullCheck($postData['unitid'], 'number');

                        $subQuery = $sql->select();
                        $subQuery->from("Crm_ReceiptAdjustment")
                            ->columns(array('ReceiptAdjId'))
                            ->where(array("ReceiptId " => $recpid));

                        $delete = $sql->delete();
                        $delete->from('Crm_ReceiptAdjustmentTrans')
                            ->where->expression('ReceiptAdjId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //delete CB_ReceiptAdustment
                        $delete = $sql->delete();
                        $delete->from('Crm_ReceiptAdjustment')
                            ->where(array("ReceiptId" => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery1 = $sql->select();
                        $subQuery1->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array(), $subQuery1::JOIN_INNER)
                            ->columns(array('ReceiptAdjTransId'))
                            ->where("b.ReceiptId=$recpid");

                        $delete = $sql->delete();
                        $delete->from('Crm_ReceiptQualifierTrans')
                            ->where->expression('ReceiptAdjTransId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Crm_ReceiptQualifierAbstract')
                            ->where(array("ReceiptId" => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $UnitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $iLeadId= $this->bsf->isNullCheck($postData['leadid'], 'number');
                        $ReceiptDate = $this->bsf->isNullCheck($postData['ReceiptDate'], 'string');
                        $ReceiptAgainst = $this->bsf->isNullCheck($postData['ReceiptAgainst'], 'string');
                        $PaymentMode = $this->bsf->isNullCheck($postData['PaymentMode'], 'string');
                        $TNo = $this->bsf->isNullCheck($postData['TNo'], 'string');
                        $TDate = $this->bsf->isNullCheck($postData['TDate'], 'string');
                        $Remarks = $this->bsf->isNullCheck($postData['Remarks'], 'string');
                        $Amount = $this->bsf->isNullCheck($postData['Amount'], 'number');
                        $rentRegId = $this->bsf->isNullCheck($postData['rentRegId'], 'number');
                        $rentBillNo = $this->bsf->isNullCheck($postData['rentBillNo'], 'string');
                        $rentBillAmt = $this->bsf->isNullCheck($postData['rentBillAmt'], 'number');
                        $latefee = $this->bsf->isNullCheck($postData['latefee'], 'number');

                        $grossAmt =$this->bsf->isNullCheck($postData['BillCurTotal'], 'number');
                        $excessAmt =$this->bsf->isNullCheck($postData['BillExcessAmt'], 'number');
                        $qualAmt =$this->bsf->isNullCheck($postData['BillTaxTotal'], 'number');
                        $netAmt =$this->bsf->isNullCheck($postData['BillNetTotal'], 'number');

                        $ReceiptNo = $this->bsf->isNullCheck($postData['ReceiptNo'], 'string');
                        $update = $sql->update();
                        $update->table('Crm_ReceiptRegister');
                        $update->set(array('UnitId' => $UnitId,
                            'LeadId' => $iLeadId,
                            'ReceiptDate' => date('Y-m-d', strtotime($ReceiptDate)),
                            'ReceiptAgainst' => $ReceiptAgainst,
                            'ReceiptMode' => $PaymentMode,
                            'TransNo' => $TNo,
                            'TransDate' => date('Y-m-d', strtotime($TDate)),
                            'Remarks' => $Remarks,
                            'Amount' => $Amount,
                            'LateIntAmount' => $latefee,
                            'GrossAmount' => $grossAmt,
                            'ExcessAmount' => $excessAmt,
                            'QualAmount' => $qualAmt,
                            'NetAmount' => $netAmt,
                            'RentBillNo'=>$rentBillNo,
                            'RentBillAmount'=>$rentBillAmt,
                            'RentRegisterId'=>$rentRegId,
                            'BankName' => $this->bsf->isNullCheck($postData['BankName'],'string')));
                        $update->where(array('ReceiptId' => $recpid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $billrowid = $this->bsf->isNullCheck($postData['billrowid'],'number');

                        for ($i = 1; $i <= $billrowid; $i++) {
                            $BillId = $this->bsf->isNullCheck($postData['BillId_' . $i],'number');
                            $iStageId = $this->bsf->isNullCheck($postData['StageId_' . $i],'number');
                            $sStageType = $this->bsf->isNullCheck($postData['StageType_' . $i],'string');
                            $iExtraBillId= $this->bsf->isNullCheck($postData['ExtraBillId_' . $i],'number');

                            $BAmount = $this->bsf->isNullCheck($postData['CurAmt_' . $i],'number');
                            $QAmount = $this->bsf->isNullCheck($postData['TaxAmt_' . $i],'number');
                            $NAmount = $this->bsf->isNullCheck($postData['NetAmt_' . $i],'number');

                            if ($BAmount == 0) continue;

                            $insert = $sql->insert();
                            $insert->into('Crm_ReceiptAdjustment');
                            $insert->Values(array('ReceiptId' => $recpid,'ProgressBillTransId' => $BillId,'StageId'=>$iStageId,'StageType'=>$sStageType,'ExtraBillRegisterId'=>$iExtraBillId, 'Amount'=> $BAmount,'QualAmount' => $QAmount,'NetAmount'=>$NAmount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $adjId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $billabsrowid = $this->bsf->isNullCheck($postData['billabsrowid_'.$i],'number');

                            for ($j = 1; $j <= $billabsrowid; $j++) {
                                $iReceiptTypeId = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_ReceiptTypeId_' . $j],'number');
                                $sReceiptType = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_ReceiptType_' . $j],'string');
                                $AbsAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_CurAmt_' . $j],'number');
                                $qTAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_TaxAmt_' . $j],'number');
                                $nTAmount = $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_NetAmt_' . $j],'number');

                                if ($AbsAmount == 0 || $iReceiptTypeId==0) continue;

                                $insert = $sql->insert();
                                $insert->into('Crm_ReceiptAdjustmentTrans');
                                $insert->Values(array('ReceiptAdjId' =>$adjId,'ReceiptTypeId'=>$iReceiptTypeId,'ReceiptType'=>$sReceiptType,'Amount'=> $AbsAmount,'QualAmount'=>$qTAmount,'NetAmount'=>$nTAmount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $adjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $qRefId =   $this->bsf->isNullCheck($postData['BillAbs_'.$i.'_QualRefId_' . $j],'number');
                                $qRowId =   $this->bsf->isNullCheck($postData['QualRowId_'.$qRefId],'number');
                                for ($k = 1; $k <= $qRowId; $k++) {
                                    $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Id_' . $k], 'number');
                                    $iYesNo = isset($postData['Qual_' . $qRefId . '_YesNo_' . $k]) ? 1 : 0;
                                    $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Exp_' . $k], 'string');
                                    $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpValue_' . $k], 'number');
                                    $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                    $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TypeId_' . $k], 'number');
                                    $sSign = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Sign_' . $k], 'string');

                                    $dCessPer = 0;
                                    $dEDPer = 0;
                                    $dHEdPer = 0;
                                    $dCessAmt = 0;
                                    $dEDAmt = 0;
                                    $dHEdAmt = 0;
                                    $dKKCessPer=0;
                                    $dSBCessPer=0;
                                    $dKKCessAmt=0;
                                    $dSBCessAmt=0;

                                    if ($iQualTypeId==1) {
                                        $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxablePer_' . $k], 'number');
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPer_' . $k], 'number');
                                        $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_CessPer_' . $k], 'number');
                                        $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_EduCessPer_' . $k], 'number');
                                        $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_HEduCessPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetPer_' . $k], 'number');

                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxableAmt_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPerAmt_' . $k], 'number');
                                        $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_CessAmt_' . $k], 'number');
                                        $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_EduCessAmt_' . $k], 'number');
                                        $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_HEduCessAmt_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetAmt_' . $k], 'number');
                                    } else if ($iQualTypeId==2){
                                        $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxablePer_' . $k], 'number');
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPer_' . $k], 'number');
                                        $dSBCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_KKCessPer_' . $k], 'number');
                                        $dKKCessPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_SBCessPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetPer_' . $k], 'number');

                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxableAmt_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_TaxPerAmt_' . $k], 'number');
                                        $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_KKCessAmt_' . $k], 'number');
                                        $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_SBCessAmt_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_NetAmt_' . $k], 'number');
                                    } else {
                                        $dTaxablePer = 100;
                                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpPer_' . $k], 'number');
                                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_ExpValue_' . $k], 'number');
                                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Amount_' . $k], 'number');
                                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $qRefId . '_Amount_' . $k], 'number');
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('Crm_ReceiptQualifierTrans');
                                    $insert->Values(array('ReceiptAdjTransId' =>$adjTransId,'QualTypeId'=>$iQualTypeId,
                                        'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                        'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCessPer,'SBCess'=>$dSBCessPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                        'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                                $select = $sql->select();
                                $select->from( array( 'a' => 'Crm_PostSaleDiscountRegister' ) )
                                    ->columns(array('PostSaleDiscountId'))
                                    ->where( array( 'a.UnitId' => $UnitId))
                                    ->where("DistFlag='0'");
                                $stmt = $sql->getSqlStringForSqlObject( $select );
                                $posdist = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
                                    ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array(), $select::JOIN_INNER)
                                    ->join(array('c' => 'Crm_ReceiptRegister'), 'b.ReceiptId=c.ReceiptId', array(), $select::JOIN_INNER)
                                    ->columns(array('Amount'=> new Expression("Sum(a.NetAmount)")));
                                if ($iExtraBillId !=0) {
                                    $select->where("b.ExtraBillRegisterId=$iExtraBillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                                } else if ($BillId !=0) {
                                    $select->where("b.ProgressBillTransId=$BillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                                } else {
                                    $select->where("b.StageId=$iStageId and b.StageType='$sStageType' and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                                }

                                $statement = $sql->getSqlStringForSqlObject($select);
                                $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $dPaidAmt=0;
                                if (!empty($ramt)) $dPaidAmt =  $this->bsf->isNullCheck($ramt->Amount,'number');

                                $update = $sql->update();

                                if ($iExtraBillId !=0) {
                                    $update->table('Crm_ExtraBillRegister');
                                    $update->set(array('PaidAmount' => $dPaidAmt));
                                    $update->where("ExtraBillRegisterId=$iExtraBillId and ReceiptTypeId=$iReceiptTypeId");
                                } else if ($BillId !=0) {
                                    $update->table('Crm_ProgressBillReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt));
                                    $update->where("ProgressBillTransId=$BillId and ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType'");

                                } else {
                                    if($posdist['PostSaleDiscountId']>0){
                                        $subQuery = $sql->select();
                                        $subQuery->from("Crm_PSDPaymentScheduleUnitTrans")
                                            ->columns(array('PSDPaymentScheduleUnitTransId'))
                                            ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                        $update = $sql->update();
                                        $update->table('Crm_PSDPaymentScheduleUnitReceiptTypeTrans');
                                        $update->set(array('PaidAmount' => $dPaidAmt))
                                            ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PSDPaymentScheduleUnitTransId IN ?", array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    else{
                                    $subQuery = $sql->select();
                                    $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                        ->columns(array('PaymentScheduleUnitTransId'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                    $update = $sql->update();
                                    $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt))
                                        ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                              }
                            }
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                if ($BillId !=0) {
                                    if($posdist['PostSaleDiscountId']>0){ $subQuery = $sql->select();
                                        $subQuery->from("Crm_PSDPaymentScheduleUnitTrans")
                                            ->columns(array('PSDPaymentScheduleUnitTransId'))
                                            ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                        $update = $sql->update();
                                        $update->table('Crm_PSDPaymentScheduleUnitReceiptTypeTrans');
                                        $update->set(array('PaidAmount' => $dPaidAmt))
                                            ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PSDPaymentScheduleUnitTransId IN ?", array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);}else{
                                    $subQuery = $sql->select();
                                    $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                        ->columns(array('PaymentScheduleUnitTransId'))
                                        ->where("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId");

                                    $update = $sql->update();
                                    $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                    $update->set(array('PaidAmount' => $dPaidAmt))
                                        ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                              }
                            }
                            }

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustment'))
                                ->join(array('b' => 'Crm_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select::JOIN_INNER)
                                ->columns(array('Amount'=> new Expression("Sum(a.NetAmount)")));

                            if ($iExtraBillId !=0) {
                                $select->where("a.ExtraBillRegisterId=$iExtraBillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else if ($BillId !=0) {
                                $select->where("a.ProgressBillTransId=$BillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else {
                                $select->where("a.StageId=$iStageId and a.StageType='$sStageType' and b.DeleteFlag=0 and b.ChequeBounce=0");
                            }

                            $statement = $sql->getSqlStringForSqlObject($select);
                            $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $dPaidAmt=0;
                            if (!empty($ramt)) $dPaidAmt =  $this->bsf->isNullCheck($ramt->Amount,'number');

                            $update = $sql->update();

                            if ($iExtraBillId !=0) {
                                $update->table('Crm_ExtraBillRegister');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array('ExtraBillRegisterId' => $iExtraBillId));
                            } else if ($BillId !=0) {
                                $update->table('Crm_ProgressBillTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array('ProgressBillTransId' => $BillId));
                            } else {
                                if($posdist['PostSaleDiscountId']>0){$update->table('Crm_PSDPaymentScheduleUnitTrans');
                                    $update->set(array('PaidAmounts' => $dPaidAmt));
                                    $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));}
                                else{
                                $update->table('Crm_PaymentScheduleUnitTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                          }
                        }

                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($BillId !=0) {
                                if($posdist['PostSaleDiscountId']>0){$update = $sql->update();
                                    $update->table('Crm_PSDPaymentScheduleUnitTrans');
                                    $update->set(array('PaidAmounts' => $dPaidAmt));
                                    $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);}else{
                                $update = $sql->update();
                                $update->table('Crm_PaymentScheduleUnitTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId=$UnitId"));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                           }
                        }
                        }

                        $billqualrowid = $this->bsf->isNullCheck($postData['billqualrowid'],'number');

                        for ($n = 1; $n <= $billqualrowid; $n++) {
                            $iQualifierId =  $this->bsf->isNullCheck($postData['QualifierId_' . $n], 'number');
                            $dQualAmt =  $this->bsf->isNullCheck($postData['QualAmt_' . $n], 'number');
                            $sSign = "+";
                            if ($dQualAmt < 0) $sSign = "-";
                            $dQualAmt = abs($dQualAmt);

                            $insert = $sql->insert();
                            $insert->into('Crm_ReceiptQualifierAbstract');
                            $insert->Values(array('ReceiptId' => $recpid,'QualifierId' => $iQualifierId,'Amount' => $dQualAmt,'Sign'=>$sSign));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Receipt-Modify','E','CRM-Receipt',$recpid,0, 0, 'CRM','',$userId, 0 ,0);

                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                }

                // save and print


                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if(isset($FeedId) && $FeedId!="") {
                    if(!is_null($postData['print']) && $postData['print'] == 'true') {
                        if($recpid==0){
                            $this->redirect()->toRoute('crm/receipt-print', array('controller' => 'bill', 'action' => 'receipt-print', 'receiptId' => $ReceiptId));}
                        else{
                            $this->redirect()->toRoute('crm/receipt-print', array('controller' => 'bill', 'action' => 'receipt-print', 'receiptId' => $recpid));
                        }
                    } else {
                        if ($sMode == 'Add') {
                            $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt'), array('query' => array('AskId' => $AskId, 'FeedId' => $FeedId, 'type' => 'feed')));
                        } else {
                            $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt-register'), array('query' => array('AskId' => $AskId, 'FeedId' => $FeedId, 'type' => 'feed')));
                        }
                    }
                } else {
                    if(!is_null($postData['print']) && $postData['print'] == 'true') {
                        if($recpid==0){
                            $this->redirect()->toRoute('crm/receipt-print', array('controller' => 'bill', 'action' => 'receipt-print', 'receiptId' => $ReceiptId));}
                        else{
                            $this->redirect()->toRoute('crm/receipt-print', array('controller' => 'bill', 'action' => 'receipt-print', 'receiptId' => $recpid));
                        }
                    } else {
                        if ($sMode == 'Add') {
                            $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt'));
                        } else {
                            $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt-register'));
                        }
                    }
                }
//                if ($sMode == 'Add') {
//                    $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt'));
//                } else {
//                    $this->redirect()->toRoute('crm/default', array('controller' => 'bill', 'action' => 'receipt-register'));
//                }
            } else {
                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');
                $sql = new Sql($dbAdapter);
                if($mode=="final") {
                    $aAmount = $this->bsf->isNullCheck($this->params()->fromRoute('aAmount'), 'number');
                    $select1 = $sql->select();
                    $select1->from(array("a" => "Crm_UnitDetails"))
                        ->join(array('u' => 'Crm_UnitBooking'), new expression('a.UnitId=u.UnitId and u.DeleteFlag=0'), array('LeadId'), $select1::JOIN_LEFT)
                        ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array(), $select1::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select1::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select1::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("c.ProjectName + ' : ' + m.UnitNo + ' ('+b.LeadName + ')'")));
                    $select1->where(array('a.UnitId' => $editid));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select1);
                    $receiptName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->receiptName=$receiptName;
                    $this->_view->aAmount=$aAmount;
                } else {
                    if ($editid != 0) {
                        $select = $sql->select();
                        $select->from(array("a" => "Crm_ReceiptRegister"))
                            ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                            ->join(array("u" => "KF_UnitMaster"), "a.UnitId=u.UnitId", array(), $select::JOIN_INNER)
                            ->join(array('l' => 'Crm_UnitBooking'), 'a.UnitId=l.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Crm_Leads'), 'l.LeadId=c.LeadId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectMaster'), 'u.ProjectId=d.ProjectId', array(), $select::JOIN_LEFT)
                            ->columns(array("UnitId", "LeadId", "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')")
                            , "ReceiptAgainst", "ReceiptMode", "TransNo", "TransDate" => new Expression("FORMAT(a.TransDate, 'dd-MM-yyyy')")
                            , "BankName","Remarks",'RentBillNo','RentBillAmount','RentRegisterId', "Amount", 'UnitName' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                        $select->where(array('a.ReceiptId' => $editid));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $receiptregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $runitid=0;
                        $irLeadId=0;

                        if (!empty($receiptregister))  { $runitid =  $receiptregister->UnitId; $irLeadId =  $receiptregister->LeadId; }
                         $this->_view->receiptregister = $receiptregister;


                        //Trans
                        //UnBilled Stages
                        $selects1 = $sql->select();
                        $selects1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(SchDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("''"),'BillAmount'=>new Expression("a.Amount-a.Discount"),'StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.StageId=b.StageId and a.StageType=b.StageType', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $selects1::JOIN_LEFT)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $selects1::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $selects1::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selects1::JOIN_LEFT)
                            ->where("a.BillPassed=0 and a.StageType<>'A' and a.UnitId =$runitid");
                        $selects1->group(new Expression('a.SchDate,a.Amount,a.StageId,a.Discount,a.StageType,c.StageName,d.OtherCostName,e.DescriptionName'));

                        $selects2 = $sql->select();
                        $selects2->from(array("a"=>"Crm_PaymentScheduleUnitTrans"))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(SchDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("''"), 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"), 'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array("r"=>"Crm_ReceiptRegister"), "a.UnitId=r.UnitId", array(), $selects1::JOIN_INNER)
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.StageId=b.StageId and a.StageType=b.StageType and r.ReceiptId=b.ReceiptId', array('PaidAmount' => new Expression("Sum(b.Amount)"),'CurrentAmount'=>new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $selects2::JOIN_INNER)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $selects2::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $selects2::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selects2::JOIN_LEFT);

                        $selects2->where("a.BillPassed=0 and a.StageType<>'A' and b.ReceiptId<>$editid and r.LeadId = $irLeadId and a.UnitId=$runitid");
                        $selects2->combine($selects1,'Union ALL');
                        $selects2->group(new Expression('a.SchDate,a.StageId,a.StageType,a.Discount,c.StageName,d.OtherCostName,e.DescriptionName'));

                        $selects2Edit = $sql->select();
                        $selects2Edit->from(array("a"=>"Crm_PaymentScheduleUnitTrans"))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(SchDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("''"), 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.StageId=b.StageId and a.StageType=b.StageType', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("Sum(b.Amount)"),'QualAmount' => new Expression("Sum(b.QualAmount)"),'NetAmount' => new Expression("Sum(b.NetAmount)")), $selects2Edit::JOIN_INNER)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $selects2Edit::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $selects2Edit::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selects2Edit::JOIN_LEFT);
                        $selects2Edit->where("a.BillPassed=0 and  a.StageType<>'A' and b.ReceiptId=$editid and a.UnitId=$runitid");
                        $selects2Edit->combine($selects2,'Union ALL');
                        $selects2Edit->group(new Expression('a.SchDate,a.StageId,a.StageType,a.Discount,c.StageName,d.OtherCostName,e.DescriptionName'));

                        $selects3 = $sql->select();
                        $selects3->from(array("g"=>$selects2Edit))
                            ->columns(array('ProgressBillTransId', 'BillDate', 'PBNo',"BillAmount"=>new Expression("Sum(g.BillAmount)"),'StageId','StageType','ExtraBillRegisterId'
                            ,"PaidAmount"=>new Expression("Sum(g.PaidAmount)"),"CurrentAmount"=>new Expression("Sum(g.CurrentAmount)"),"QualAmount"=>new Expression("Sum(g.QualAmount)"),"NetAmount"=>new Expression("Sum(g.NetAmount)"),'StageName'));
                        $selects3->group(new Expression('g.ProgressBillTransId,g.PBNo,g.BillDate,g.StageName,g.StageId,g.StageType,g.ExtraBillRegisterId'));


                        //ExtraBill
                        $selectE1 = $sql->select();
                        $selectE1->from(array('a' => 'Crm_ExtraBillRegister'))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(ExtraBillDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("ExtraBillNo"),'BillAmount'=>'Amount','StageId'=>new Expression("CAST(0 As int)"),'StageType'=>new Expression("''"),'ExtraBillRegisterId',
                                'StageName' => new Expression("'Extra-Bill'")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $selectE1::JOIN_LEFT)
                            ->where("a.UnitId =$runitid");
                        $selectE1->group(new Expression('a.ExtraBillDate,a.ExtraBillNo,a.Amount,a.ExtraBillRegisterId'));

                        $selectE2 = $sql->select();
                        $selectE2->from(array("a"=>"Crm_ExtraBillRegister"))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(ExtraBillDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("ExtraBillNo"), 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId'=>new Expression("CAST(0 As int)"),'StageType'=>new Expression("''"),'ExtraBillRegisterId',
                                'StageName' => new Expression("'Extra-Bill'")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId', array('PaidAmount' => new Expression("Sum(b.Amount)"),'CurrentAmount'=>new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $selectE2::JOIN_INNER);
                        $selectE2->where("b.ReceiptId<>$editid and a.LeadId=$irLeadId and a.UnitId=$runitid");
                        $selectE2->combine($selectE1,'Union ALL');
                        $selectE2->group(new Expression('a.ExtraBillDate,a.ExtraBillNo,a.ExtraBillRegisterId'));

                        $selectE2Edit = $sql->select();
                        $selectE2Edit->from(array("a"=>"Crm_ExtraBillRegister"))
                            ->columns( array('ProgressBillTransId'=>new Expression("CAST(0 As int)"), 'BillDate' => new Expression("FORMAT(ExtraBillDate, 'dd-MM-yyyy')"), 'PBNo'=>new Expression("ExtraBillNo"), 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId'=>new Expression("CAST(0 As int)"),'StageType'=>new Expression("''"),'ExtraBillRegisterId',
                                'StageName' => new Expression("'Extra-Bill'")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("Sum(b.Amount)"),'QualAmount' => new Expression("Sum(b.QualAmount)"),'NetAmount' => new Expression("Sum(b.NetAmount)")), $selectE2Edit::JOIN_INNER);
                        $selectE2Edit->where("b.ReceiptId=$editid");
                        $selectE2Edit->combine($selectE2,'Union ALL');
                        $selectE2Edit->group(new Expression('a.ExtraBillDate,a.ExtraBillNo,a.ExtraBillRegisterId'));

                        $selectE3 = $sql->select();
                        $selectE3->from(array("g"=>$selectE2Edit))
                            ->columns(array('ProgressBillTransId', 'BillDate', 'PBNo',"BillAmount"=>new Expression("Sum(g.BillAmount)"),'StageId','StageType','ExtraBillRegisterId'
                            ,"PaidAmount"=>new Expression("Sum(g.PaidAmount)"),"CurrentAmount"=>new Expression("Sum(g.CurrentAmount)"),"QualAmount"=>new Expression("Sum(g.QualAmount)"),"NetAmount"=>new Expression("Sum(g.NetAmount)"),'StageName'));
                        $selectE3->group(new Expression('g.ProgressBillTransId,g.PBNo,g.BillDate,g.StageName,g.StageId,g.StageType,g.ExtraBillRegisterId'));

                        $selectE3->combine($selects3,'Union ALL');

                        //ProgressBill
                        $select1 = $sql->select();
                        $select1->from(array('a' => 'Crm_ProgressBillTrans'))
                            ->columns( array('ProgressBillTransId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo','BillAmount'=>'Amount','StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=b.ProgressBillTransId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select1::JOIN_LEFT)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
                            ->where(array('a.UnitId' => $runitid));
                        $select1->group(new Expression('a.ProgressBillTransId,a.PBNo,a.BillDate,a.Amount,a.StageId,a.StageType,c.StageName,d.OtherCostName,e.DescriptionName'));


                        $select2 = $sql->select();
                        $select2->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->columns( array('ProgressBillTransId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo', 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=b.ProgressBillTransId', array('PaidAmount' => new Expression("Sum(b.Amount)"),'CurrentAmount'=>new Expression("CAST(0 As Decimal(18,2))"),'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"),'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_INNER)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT);

                        $select2->where("b.ReceiptId<>$editid and a.BuyerId =$irLeadId and a.UnitId=$runitid");
                        $select2->combine($select1,'Union ALL');
                        $select2->group(new Expression('a.ProgressBillTransId,a.PBNo,a.BillDate,a.StageId,a.StageType,c.StageName,d.OtherCostName,e.DescriptionName'));

                        $select2Edit = $sql->select();
                        $select2Edit->from(array("a"=>"Crm_ProgressBillTrans"))
                            ->columns( array('ProgressBillTransId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo', 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'StageId','StageType','ExtraBillRegisterId'=>new Expression("CAST(0 As int)"),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=b.ProgressBillTransId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("Sum(b.Amount)"),'QualAmount' => new Expression("Sum(b.QualAmount)"),'NetAmount' => new Expression("Sum(b.NetAmount)")), $select2Edit::JOIN_INNER)
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT);
                        $select2Edit->where("b.ReceiptId=$editid");
                        $select2Edit->combine($select2,'Union ALL');
                        $select2Edit->group(new Expression('a.ProgressBillTransId,a.PBNo,a.BillDate,a.StageId,a.StageType,c.StageName,d.OtherCostName,e.DescriptionName'));

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$select2Edit))
                            ->columns(array('ProgressBillTransId', 'BillDate', 'PBNo',"BillAmount"=>new Expression("Sum(g.BillAmount)"),'StageId','StageType','ExtraBillRegisterId'
                            ,"PaidAmount"=>new Expression("Sum(g.PaidAmount)"),"CurrentAmount"=>new Expression("Sum(g.CurrentAmount)"),"QualAmount"=>new Expression("Sum(g.QualAmount)"),"NetAmount"=>new Expression("Sum(g.NetAmount)"),'StageName'));
                        $select3->group(new Expression('g.ProgressBillTransId,g.PBNo,g.BillDate,g.StageName,g.StageId,g.StageType,g.ExtraBillRegisterId'));
                        $select3->combine($selectE3,'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($select3);
                        $billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $iQualCount=0;
                        foreach($billformats as &$bill) {
                            $billId = $bill['ProgressBillTransId'];
                            $sStageType= $bill['StageType'];
                            $iStageId= $bill['StageId'];
                            $iExtraBillId= $bill['ExtraBillRegisterId'];

                            if ($iExtraBillId !=0)
                            {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ExtraBillRegister'))
                                    ->columns(array('ReceiptTypeId','ReceiptType'=>new Expression("'E'"),'BillAmount' => new Expression("CAST(0 As Decimal(18,2))")),array('ReceiptTypeName'))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=a1.ExtraBillRegisterId', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('ReceiptTypeName'), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("Sum(c.Amount)"), 'QualAmount' => new Expression("Sum(c.QualAmount)"), 'NetAmount' => new Expression("Sum(c.NetAmount)")), $select::JOIN_LEFT);
                                $select->where(array('a.ExtraBillRegisterId' => $iExtraBillId, 'a1.ReceiptId' => $editid));
                                $select->group(new Expression('a.ReceiptTypeId,a.Amount,b.ReceiptTypeName'));

                                $select2 = $sql->select();
                                $select2->from(array('a' => 'Crm_ExtraBillRegister'))
                                    ->columns(array('ReceiptTypeId','ReceiptType'=>new Expression("'E'"),'BillAmount' => new Expression("CAST(0 As Decimal(18,2))")), array('ReceiptTypeName'))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=a1.ExtraBillRegisterId', array(), $select2::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('ReceiptTypeName'), $select2::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("Sum(c.Amount)"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_LEFT);
                                $select2->where("a.ExtraBillRegisterId=$iExtraBillId AND a1.ReceiptId<>$editid");
                                $select2->group(new Expression('a.ReceiptTypeId,a.Amount,b.ReceiptTypeName'));
                                $select2->combine($select, 'Union ALL');

                                $select21 = $sql->select();
                                $select21->from(array('a' => 'Crm_ExtraBillRegister'))
                                    ->columns(array('ReceiptTypeId','ReceiptType'=>new Expression("'E'"),'BillAmount' => 'Amount'), array('ReceiptTypeName'))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=a1.ExtraBillRegisterId', array(), $select21::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('ReceiptTypeName', 'PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT);
                                $select21->where("a.ExtraBillRegisterId=$iExtraBillId AND a.Amount<>0");
                                $select21->group(new Expression('a.ReceiptTypeId,a.Amount,b.ReceiptTypeName'));
                                $select21->combine($select2, 'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g" => $select21))
                                    ->columns(array('ReceiptTypeId','ReceiptType','ReceiptTypeName', "BillAmount" => new Expression("Sum(g.BillAmount)"), "PaidAmount" => new Expression("Sum(g.PaidAmount)")
                                    , "CurrentAmount" => new Expression("Sum(g.CurrentAmount)"), "QualAmount" => new Expression("Sum(g.QualAmount)"), "NetAmount" => new Expression("Sum(g.NetAmount)")));
                                $select3->group(new Expression('g.ReceiptTypeId,g.ReceiptType,g.ReceiptTypeName'));
                            } else if ($billId !=0) {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ProgressBillReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType','BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=a1.ProgressBillTransId', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array(), $select::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("Sum(c.Amount)"), 'QualAmount' => new Expression("Sum(c.QualAmount)"), 'NetAmount' => new Expression("Sum(c.NetAmount)")), $select::JOIN_LEFT);
                                $select->where(array('a.ProgressBillTransId' => $billId, 'a1.ReceiptId' => $editid));
                                $select->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));

                                $select2 = $sql->select();
                                $select2->from(array('a' => 'Crm_ProgressBillReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType','BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=a1.ProgressBillTransId', array(), $select2::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array(), $select2::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select2::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a1.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("Sum(c.Amount)"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_LEFT);
                                $select2->where("a.ProgressBillTransId=$billId AND a1.ReceiptId<>$editid");
                                $select2->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                                $select2->combine($select, 'Union ALL');

                                $select21 = $sql->select();
                                $select21->from(array('a' => 'Crm_ProgressBillReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType', 'BillAmount' => 'Amount','ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_ReceiptAdjustment'), 'a.ProgressBillTransId=a1.ProgressBillTransId', array(), $select21::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select2::JOIN_LEFT);
                                $select21->where("a.ProgressBillTransId=$billId AND a.Amount<>0");
                                $select21->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                                $select21->combine($select2, 'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g" => $select21))
                                    ->columns(array('ReceiptTypeId','ReceiptType','ReceiptTypeName', "BillAmount" => new Expression("Sum(g.BillAmount)"), "PaidAmount" => new Expression("Sum(g.PaidAmount)")
                                    , "CurrentAmount" => new Expression("Sum(g.CurrentAmount)"), "QualAmount" => new Expression("Sum(g.QualAmount)"), "NetAmount" => new Expression("Sum(g.NetAmount)")));
                                $select3->group(new Expression('g.ReceiptTypeId,g.ReceiptType,g.ReceiptTypeName'));
                            } else {

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType', 'BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_PaymentScheduleUnitTrans'), 'a.PaymentScheduleUnitTransId=a1.PaymentScheduleUnitTransId', array(), $select::JOIN_INNER)
                                    ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.StageId=a2.StageId and a1.StageType=a2.StageType', array(), $select::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array(), $select::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a2.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("Sum(c.Amount)"), 'QualAmount' => new Expression("Sum(c.QualAmount)"), 'NetAmount' => new Expression("Sum(c.NetAmount)")), $select::JOIN_LEFT);
                                $select->where("a1.UnitId=$runitid and a1.StageType<>'A' and a1.StageId = $iStageId and a1.StageType = '$sStageType' and a2.ReceiptId = $editid");
                                $select->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));

                                $select2 = $sql->select();
                                $select2->from(array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType','BillAmount' => new Expression("CAST(0 As Decimal(18,2))"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_PaymentScheduleUnitTrans'), 'a.PaymentScheduleUnitTransId=a1.PaymentScheduleUnitTransId', array(), $select2::JOIN_INNER)
                                    ->join(array("r"=>"Crm_ReceiptRegister"), "a1.UnitId=r.UnitId", array(), $selects1::JOIN_INNER)
                                    ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.StageId=a2.StageId and a1.StageType=a2.StageType and r.ReceiptId=a2.ReceiptId', array(), $select2::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array(), $select2::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select2::JOIN_LEFT)
                                    ->join(array('c' => 'Crm_ReceiptAdjustmentTrans'), 'a2.ReceiptAdjId=c.ReceiptAdjId and a.ReceiptTypeId=c.ReceiptTypeId',
                                        array('PaidAmount' => new Expression("Sum(c.Amount)"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_LEFT);
                                $select2->where("a1.UnitId=$runitid and a1.StageType<>'A' and a1.StageId = $iStageId and a1.StageType = '$sStageType' and a2.ReceiptId<>$editid");
                                $select2->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,b.ReceiptTypeName,o.OtherCostName'));
                                $select2->combine($select, 'Union ALL');

                                $select21 = $sql->select();
                                $select21->from(array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'))
                                    ->columns(array('ReceiptTypeId','ReceiptType','BillAmount' =>new Expression("a.Amount-a.Discount"),'ReceiptTypeName'=> new Expression("Case When a.ReceiptType='O' then o.OtherCostName else b.ReceiptTypeName end")))
                                    ->join(array('a1' => 'Crm_PaymentScheduleUnitTrans'), 'a.PaymentScheduleUnitTransId=a1.PaymentScheduleUnitTransId', array(), $select21 ::JOIN_INNER)
                                    ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.StageId=a2.StageId and a1.StageType=a2.StageType', array(), $select21 ::JOIN_LEFT)
                                    ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId=b.ReceiptTypeId', array('PaidAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'QualAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT)
                                    ->join(array('o' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId=o.OtherCostId', array(), $select21::JOIN_LEFT);
                                $select21->where("a1.UnitId=$runitid and a1.StageType<>'A' and a1.StageId = $iStageId and a1.StageType = '$sStageType' and a.Amount<>0");
                                $select21->group(new Expression('a.ReceiptTypeId,a.ReceiptType,a.Amount,a.Discount,b.ReceiptTypeName,o.OtherCostName'));
                                $select21->combine($select2, 'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g" => $select21))
                                    ->columns(array('ReceiptTypeId','ReceiptType','ReceiptTypeName', "BillAmount" => new Expression("Sum(g.BillAmount)"), "PaidAmount" => new Expression("Sum(g.PaidAmount)")
                                    , "CurrentAmount" => new Expression("Sum(g.CurrentAmount)"), "QualAmount" => new Expression("Sum(g.QualAmount)"), "NetAmount" => new Expression("Sum(g.NetAmount)")));
                                $select3->group(new Expression('g.ReceiptTypeId,g.ReceiptType,g.ReceiptTypeName'));
                            }

                            $statement = $sql->getSqlStringForSqlObject($select3);
                            $billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            //   Print_r($billabs);die;
//                        $bill['BillAbs'] = $billabs;
                            foreach($billabs as &$qual) {
                                $curAmt =$qual['CurrentAmount'];
                                $receiptTypeId = $qual['ReceiptTypeId'];
                                $select = $sql->select();
                                if ($curAmt ==0) {
                                    $select->from(array("a" => "Proj_QualifierTrans"))
                                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER)
                                        ->columns(array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(RefId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SBCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'KKCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                                    $select->where(array('a.QualType' => 'C'));
                                } else {
                                    $select->from(array("a" => "Crm_ReceiptQualifierTrans"))
                                        ->join(array('a1' => 'Crm_ReceiptAdjustmentTrans'), 'a.ReceiptAdjTransId=a1.ReceiptAdjTransId', array(), $select::JOIN_LEFT)
                                        ->join(array('a2' => 'Crm_ReceiptAdjustment'), 'a1.ReceiptAdjId=a2.ReceiptAdjId', array(), $select::JOIN_LEFT)
                                        ->join(array("c" => "Proj_QualifierTrans"), "a.QualifierId=c.QualifierId", array(), $select::JOIN_INNER)
                                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER)
                                        ->columns(array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(c.RefId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer','ExpressionAmt','TaxableAmt',
                                            'TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt'));
                                    $select->where("a2.ProgressBillTransId=$billId and a1.ReceiptTypeId=$receiptTypeId and a2.ReceiptId=$editid and c.QualType='C'");
                                }
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                $sHtml=Qualifier::getQualifier($qualList);
                                $iQualCount = $iQualCount+1;
                                $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
                                $qual['HtmlTag'] = $sHtml;
                            }
                            $bill['BillAbs'] = $billabs;
                        }
                        $this->_view->billformats = $billformats;
                    }

                }

                // Project/Unit/Buyer
                $select1 = $sql->select();
                $select1->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array('LeadId'), $select1::JOIN_LEFT)
                    // ->join(array('n' => 'Crm_UnitBlock'), 'a.UnitId=n.UnitId', array('LeadId'), $select::JOIN_LEFT)
                    //  ->join(array('o' => 'Crm_UnitPreBooking'), 'a.UnitId=o.UnitId', array('LeadId'), $select::JOIN_LEFT)
                    ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array(), $select1::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select1::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select1::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where('u.DeleteFlag=0');

                $select2 = $sql->select();
                $select2->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('n' => 'Crm_UnitBlock'), 'a.UnitId=n.UnitId', array('LeadId'), $select2::JOIN_LEFT)
                    ->join(array("b" => "Crm_Leads"), "n.LeadId=b.LeadId", array(), $select2::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select2::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select2::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where('n.DeleteFlag=0');
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('o' => 'Crm_UnitPreBooking'), 'a.UnitId=o.UnitId', array('LeadId'), $select3::JOIN_LEFT)
                    ->join(array("b" => "Crm_Leads"), "o.LeadId=b.LeadId", array(), $select3::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select3::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select3::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where('o.DeleteFlag=0');
                $select3->combine($select2,'Union ALL');

                $select4 = $sql->select();
                $select4->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('k' => 'PM_RentalRegister'), 'a.UnitId=k.UnitId', array('LeadId'), $select4::JOIN_LEFT)
                    ->join(array("b" => "Crm_Leads"), "k.LeadId=b.LeadId", array(), $select4::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select4::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select4::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where('k.DeleteFlag=0');
                $select4->combine($select3,'Union ALL');


                $statement = $sql->getSqlStringForSqlObject($select4);
                $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $rout= $this->params()->fromRoute('id');
                $mode= $this->params()->fromRoute('mode');
if($mode=='save') {

    $select5 = $sql->select();
    $select5->from(array("a" => "Crm_UnitDetails"))
        ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array('LeadId'), $select5::JOIN_LEFT)
        // ->join(array('n' => 'Crm_UnitBlock'), 'a.UnitId=n.UnitId', array('LeadId'), $select::JOIN_LEFT)
        //  ->join(array('o' => 'Crm_UnitPreBooking'), 'a.UnitId=o.UnitId', array('LeadId'), $select::JOIN_LEFT)
        ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array(), $select5::JOIN_INNER)
        ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select5::JOIN_INNER)
        ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select5::JOIN_INNER)
        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
        ->where(array('u.DeleteFlag=0', 'a.UnitId' => $rout));
     $statement = $sql->getSqlStringForSqlObject($select5);
    $unitstype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    $this->_view->unitstype = $unitstype;
}


                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');

                $aVNo = CommonHelper::getVoucherNo(805, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];

                if($mode=="final" ||$mode== "save") {
                    $this->_view->receiptid = 0;
                } else {
                    $this->_view->receiptid = $editid;
                }
                $this->_view->mode = $mode;

                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                return $this->_view;
            }
        }
    }
    public function receiptRegisterAction() {
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
        $fromDate = date('Y-m-01');
        $toDate = date('Y-m-d');

        $fromdat = $this->params()->fromRoute('fromDate');
        $todat = $this->params()->fromRoute('toDate');
        if($fromdat!="" && strtotime($fromdat)!=false){
            $fromDate= date('Y-m-d', strtotime($fromdat));
        }


        if($todat!="" && strtotime($todat)!=false && strtotime($fromdat)){
            if(strtotime($fromdat) > strtotime($todat)) {
                $toDate= date('Y-m-d', strtotime($fromdat));

            } else {
                $toDate= date('Y-m-d', strtotime($todat));
            }

        }
        $todate=$toDate." 23:59:59";

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $result = "";
                //Write your Ajax post code here
                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                if ($mode == "filter") {

                    $choose = $this->bsf->isNullCheck($postData['choose'], 'number');
                    if ($choose == 3) {
                        $fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                        $curMonth = date('Y-M-d', strtotime("+0 month", strtotime($fromDate)));
                        $uptoDate = date('Y-M-d', strtotime("-6 month", strtotime($curMonth)));

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('Mon' => new Expression("month(ReceiptDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),3) + '-' + ltrim(str(Year(ReceiptDate)))"), 'Amount' => new Expression("sum(NetAmount)")))
                            ->where("a.DeleteFlag='0' and a.ReceiptDate>= '$uptoDate' ")
                            ->group(new Expression('month(ReceiptDate), LEFT(DATENAME(MONTH,ReceiptDate),3),Year(ReceiptDate)'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
                    } else if ($choose == 1) {
                        $fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                        $FromDaysAdd = -7;
                        $ToDaysAdd = 1;

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('Mondata' => new Expression("FORMAT(a.ReceiptDate, 'dd/MM')"), 'Amount' => new Expression("sum(NetAmount)")))
                            ->where("a.DeleteFlag='0' and a.ReceiptDate between (Convert(nvarchar(12), DATEADD(day, $FromDaysAdd,'$fromDate'), 113)) and (Convert(nvarchar(12),DATEADD(day, $ToDaysAdd, '$fromDate'), 113))  ")
                            ->group(new Expression('a.ReceiptDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
                    } else if ($choose == 2) {
                        $fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                        $FromDaysAdd = -30;
                        $ToDaysAdd = 1;

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('Mondata' => new Expression("FORMAT(a.ReceiptDate, 'dd/MM')"), 'Amount' => new Expression("sum(NetAmount)")))
                            ->where("a.DeleteFlag='0' and a.ReceiptDate between (Convert(nvarchar(12), DATEADD(day, $FromDaysAdd,'$fromDate'), 113)) and (Convert(nvarchar(12),DATEADD(day, $ToDaysAdd, '$fromDate'), 113))  ")
                            ->group(new Expression('a.ReceiptDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
                    }

                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

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

            } else {
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a" => "Crm_ReceiptRegister"))
                    ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Crm_Leads'), 'a.LeadId=c.LeadId', array(), $select::JOIN_LEFT)
                    ->join(array('p' => 'KF_UnitMaster'), 'a.UnitId=p.UnitId', array('UnitNo'), $select::JOIN_LEFT)
                    ->join(array('k' => 'KF_BlockMaster'), 'p.BlockId=k.BlockId', array('BlockName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectMaster'), 'p.ProjectId=d.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                    ->columns(array("ReceiptId", "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"),
                        "ReceiptAgainst"=> new Expression("Case When ReceiptAgainst ='B' then 'Bill/Schedule' When ReceiptAgainst ='A' then 'Advance' When ReceiptAgainst ='L' then 'LateFeeAmount'  When ReceiptAgainst ='O' then 'Others' When ReceiptAgainst ='P' then 'Pre-Booking' When ReceiptAgainst ='R' then 'Rent' else 'N/A 'end") ,"Amount",'LeadName'=>new Expression("Case When u.BookingName <>'' then u.BookingName else c.LeadName end")))
                 ->where("a.ReceiptDate<= '$todate' and a.ReceiptDate>= '$fromDate' ")
                    ->where('a.DeleteFlag=0')
                    ->order('a.ReceiptId desc');
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->receipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                $this->_view->toDate=$toDate;
                $this->_view->fromDate=$fromDate;

                $select = $sql->select();
                $select->from(array('a' => 'Crm_UnitDetails'))
                    ->join(array('b' => 'Crm_UnitBooking'), 'a.UnitId=b.UnitId', array(), $select::JOIN_INNER)
                    ->columns(array('Amount' =>new Expression("Sum(BaseAmt)")))
                    ->where("b.LeadId<>0");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->salevalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'Crm_ProgressBillTrans'))
                    ->columns(array('Amount' =>new Expression("Sum(Amount)")));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->billvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'Crm_ReceiptRegister'))
                    ->columns(array('Amount' =>new Expression("Sum(Amount)")))
                    ->where("a.DeleteFlag='0'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->receiptvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'Crm_ReceiptRegister'))
                    ->join(array('b' => 'Crm_UnitDetails'), 'a.UnitId=b.UnitId', array(),$select:: JOIN_LEFT)
                    ->join(array('p' => 'KF_UnitMaster'), 'a.UnitId=p.UnitId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectMaster'), 'p.ProjectId=c.ProjectId',array('ProjectName'), $select:: JOIN_LEFT)
                    ->columns(array('Amount' =>new Expression("sum(a.Amount)")),array('ProjectName'))
                    ->where("a.DeleteFlag='0' and a.Amount<>0.00")
                    ->group(array('c.ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectreceipts = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function receiptPrintAction() {
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


                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request
                try {

                    $receiptId = $this->params()->fromRoute('receiptId');

                    if(preg_match('/^[\d]+$/', $receiptId) == FALSE) {
                        throw new \Exception('Invalid receipt-id');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_LEFT)
                        ->join(array("c"=>"Proj_ProjectMaster"), "c.ProjectId=b.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array("d"=>"WF_CompanyMaster"), "d.CompanyId=c.CompanyId", array('CompanyName', 'Address', 'Mobile', 'Email', 'LogoPath'), $select::JOIN_LEFT)
                        ->join(array('f' => 'Crm_UnitBooking'), 'f.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Crm_Leads'), 'e.LeadId=f.LeadId', array('LeadName'), $select::JOIN_LEFT)
                        ->where(array('a.ReceiptId' => $receiptId, 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $receiptInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if(empty($receiptInfo)) {
                        throw new \Exception('Invalid Receipt!');
                    }

                    $pdfHtml = $this->generateReceiptPdf($receiptInfo);
                    //echo $pdfHtml; die;

                    require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
                    $dompdf = new DOMPDF();
                    $dompdf->load_html($pdfHtml);
                    $dompdf->set_paper("A4");
                    $dompdf->render();
                    $canvas = $dompdf->get_canvas();
                    $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $dompdf->stream("Receipt.pdf");

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
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
    private function convertAmountToWords($number) {

        $no = floor($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array('0' => '', '1' => 'One', '2' => 'Two',
            '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
            '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
            '13' => 'Thirteen', '14' => 'Fourteen',
            '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
            '18' => 'Eighteen', '19' =>'Nineteen', '20' => 'Twenty',
            '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
            '60' => 'Sixty', '70' => 'Seventy',
            '80' => 'Eighty', '90' => 'Ninety');
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');

        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ? " and " . $words[((int)($point /10)) . '0'] . " " . $words[$point = $point % 10] . " Paise": '';

        return $result . "Rupees  " . $points . " Only.";
    }


    private function generateReceiptPdf($receiptInfo) {

        $receiptDate = date('d-m-Y', strtotime($receiptInfo['ReceiptDate']));
        $transDate = date('d-M-Y', strtotime($receiptInfo['TransDate']));
        $amtInWords = $this->convertAmountToWords($receiptInfo['Amount']);
        $pdfHtml = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Receipt</title>
<style>
	#logo {
		  text-align: center;
		  margin-bottom: 10px;
		}
		.project {float: left;}
		.company {float: right;}
		.project div,.company div {white-space: nowrap;}
</style>
</head>
<body>
<div style="width:700px; margin:auto; height:600px; padding:20px;">
    <div style="width:650px; height:550px;margin:auto; padding:10px;border:2px solid #787878">
		<div id="logo">
            <img src="{$receiptInfo['LogoPath']}.png"/>
        </div>
        <p style="font-size:21px;text-align:center;padding:0px; font-weight:600;">
        	{$receiptInfo['CompanyName']}<br />
        	<span style="font-size:12px;">{$receiptInfo['Address']}</br>{$receiptInfo['Email']}</span>
        </p>
        <p style="text-align:center; text-decoration:underline; font-size:18px; font-weight:700;">
        	RECEIPT
        </p>
		<div align="center" style="width:100%;">
			<table align="center" style="width:98%;">
				<tbody>
					<tr>
						<td style="text-align:left !important;">
							<div class="project">
								<div style="font-weight:600;">No.{$receiptInfo['ReceiptNo']}</div>
							</div>
						</td>
						<td style="text-align:right !important;">
							<div class="company" style="padding: 0px !important">
								<div><b>DATE :</b> {$receiptDate}</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

        <br>
        <p style="width:100%;font-size:15px; font-style:italic; line-height:26px;">
        	<span style="display:inline-block;font-weight:600; width:180px;">Received with thanks from </span><span style="border-bottom:1px dashed #ccc; width:470px; font-weight:600;display:inline-block;"> Mr./M.s/Mrs. {$receiptInfo['LeadName']}</span>
        </p>
        <p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:80px;">the sum of </span><span style="border-bottom:1px dashed #ccc; width:570px; display:inline-block;"> {$amtInWords}</span>
        </p>
		<p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:405pxpx;">Towards payment for the purchase of unit No.{$receiptInfo['UnitNo']} <span> at </span></span><span style="border-bottom:1px dashed #ccc; width:245px; display:inline-block;">{$receiptInfo['ProjectName']}</span>
        </p>
        <p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:195px;">by &nbsp;&nbsp;&nbsp; {$receiptInfo['ReceiptMode']}
EOT;
        if($receiptInfo['ReceiptMode'] != 'Cash') {
            $pdfHtml .= <<<EOT
        	<span style="margin-left:78px;">NO.</span></span><span style="border-bottom:1px dashed #ccc; width:455px; display:inline-block;"> {$receiptInfo['TransNo']} dated {$transDate}</span>
EOT;
        } else {
            $pdfHtml .= <<<EOT
            </span>
EOT;
        }
        $pdfHtml .= <<<EOT
        </p>
        <p style="width:100%;font-size:15px;line-height:26px;">
        	<span style="display:inline-block; font-size:25px;font-weight:600;width:300px;font-style:italic;">
			<b style="font-size: 15px;">for</b> &nbsp;&nbsp;&nbsp;
			<span>
			<span style="font-size:30px;"> Rs.</span>
		 	<span style="border-bottom:3px  solid #000;">{$receiptInfo['Amount']}</span>
			</span><br />
			<span style="font-size:11px; margin-left:45px; font-weight:normal; font-style:normal;">Cheque/DD-subject to realisation</span>
			</span>
			<span style="font-size:13px;font-weight:600; width:350px; display:inline-block; text-align:right; ">for &nbsp;&nbsp;&nbsp;
			<span>
			<span style="text-transform: uppercase">{$receiptInfo['CompanyName']}</span></span><br /><br />
			<span style="font-weight: normal;">Sales Team</span></span>
        </p>
    </div>
</div>
</body>
</html>

EOT;

        return $pdfHtml;
    }

    public function deletereceiptAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                $userId = $this->auth->getIdentity()->UserId;

                try {
                    $ReceiptId = $this->params()->fromPost('ReceiptId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('Crm_ReceiptRegister')
                        ->columns(array('UnitId','Approve'))
                        ->where(array("ReceiptId" => $ReceiptId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if($bills['Approve'] =='N') {
                        $iUnitId = 0;
                        if (!empty($bills)) {
                            $iUnitId = $bills->UnitId;
                        }


                        $update = $sql->update();
                        $update->table('Crm_ReceiptRegister')
                            ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                            ->where(array('ReceiptId' => $ReceiptId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Update AdjTrans
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
                            ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array('ProgressBillTransId', 'StageId', 'StageType', 'ExtraBillRegisterId'), $select::JOIN_INNER)
                            ->columns(array('ReceiptTypeId', 'ReceiptType'));
                        $select->where("b.ReceiptId = $ReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $adjtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($adjtrans as $trans) {
                            $iReceiptTypeId = $trans['ReceiptTypeId'];
                            $sReceiptType = $trans['ReceiptType'];
                            $iPBillId = $trans['ProgressBillTransId'];
                            $sStageType = $trans['StageType'];
                            $iStageId = $trans['StageId'];
                            $iExtraBillId = $trans['ExtraBillRegisterId'];


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustmentTrans'))
                                ->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ReceiptAdjId=b.ReceiptAdjId', array(), $select::JOIN_INNER)
                                ->join(array('c' => 'Crm_ReceiptRegister'), 'b.ReceiptId=c.ReceiptId', array(), $select::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(a.NetAmount)")));
                            if ($iExtraBillId != 0) {
                                $select->where("b.ExtraBillRegisterId=$iExtraBillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                            } else if ($iPBillId != 0) {
                                $select->where("b.ProgressBillTransId=$iPBillId and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                            } else {
                                $select->where("b.StageId=$iStageId and b.StageType='$sStageType' and a.ReceiptTypeId=$iReceiptTypeId and a.ReceiptType='$sReceiptType' and c.DeleteFlag=0 and c.ChequeBounce=0");
                            }

                            $statement = $sql->getSqlStringForSqlObject($select);
                            $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $dPaidAmt = 0;
                            if (!empty($ramt)) $dPaidAmt = $this->bsf->isNullCheck($ramt->Amount, 'number');

                            $update = $sql->update();

                            if ($iExtraBillId != 0) {
                                $update->table('Crm_ExtraBillRegister');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where("ExtraBillRegisterId=$iExtraBillId and ReceiptTypeId=$iReceiptTypeId");
                            } else if ($iPBillId != 0) {
                                $update->table('Crm_ProgressBillReceiptTypeTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where("ProgressBillTransId=$iPBillId and ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType'");

                            } else {

                                $subQuery = $sql->select();
                                $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                    ->columns(array('PaymentScheduleUnitTransId'))
                                    ->where("StageId=$iStageId and StageType='$sStageType' and UnitId = $iUnitId");

                                $update = $sql->update();
                                $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt))
                                    ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($iPBillId != 0) {
                                $subQuery = $sql->select();
                                $subQuery->from("Crm_PaymentScheduleUnitTrans")
                                    ->columns(array('PaymentScheduleUnitTransId'))
                                    ->where("StageId=$iStageId and StageType='$sStageType' and UnitId = $iUnitId");

                                $update = $sql->update();
                                $update->table('Crm_PaymentScheduleUnitReceiptTypeTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt))
                                    ->where->expression("ReceiptTypeId=$iReceiptTypeId and ReceiptType='$sReceiptType' and PaymentScheduleUnitTransId IN ?", array($subQuery));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //Update Adjustment
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptAdjustment'))
                            ->columns(array('ProgressBillTransId', 'StageId', 'StageType', 'ExtraBillRegisterId'));
                        $select->where("a.ReceiptId = $ReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $adjtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($adjtrans as $trans) {
                            $iPBillId = $trans['ProgressBillTransId'];
                            $sStageType = $trans['StageType'];
                            $iStageId = $trans['StageId'];
                            $iExtraBillId = $trans['ExtraBillRegisterId'];

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustment'))
                                ->join(array('b' => 'Crm_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select::JOIN_INNER)
                                ->columns(array('Amount' => new Expression("Sum(a.NetAmount)")));

                            if ($iExtraBillId != 0) {
                                $select->where("a.ExtraBillRegisterId=$iExtraBillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else if ($iPBillId != 0) {
                                $select->where("a.ProgressBillTransId=$iPBillId and b.DeleteFlag=0 and b.ChequeBounce=0");
                            } else {
                                $select->where("a.StageId=$iStageId and a.StageType='$sStageType' and b.DeleteFlag=0 and b.ChequeBounce=0");
                            }
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $ramt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $dPaidAmt = 0;
                            if (!empty($ramt)) $dPaidAmt = $this->bsf->isNullCheck($ramt->Amount, 'number');

                            $update = $sql->update();

                            if ($iExtraBillId != 0) {
                                $update->table('Crm_ExtraBillRegister');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array('ExtraBillRegisterId' => $iExtraBillId));
                            } else if ($iPBillId != 0) {
                                $update->table('Crm_ProgressBillTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array('ProgressBillTransId' => $iPBillId));
                            } else {
                                $update->table('Crm_PaymentScheduleUnitTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId = $iUnitId"));
                            }

                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($iPBillId != 0) {
                                $update = $sql->update();
                                $update->table('Crm_PaymentScheduleUnitTrans');
                                $update->set(array('PaidAmount' => $dPaidAmt));
                                $update->where(array("StageId=$iStageId and StageType='$sStageType' and UnitId = $iUnitId"));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        $connection->commit();

                        $insertLog = CommonHelper::insertLog(date('Y-m-d H:i:s'), 'CRM-Receipt-Delete', 'N', 'CRM-Receipt', $ReceiptId, 0, 0, 'CRM','', $userId, 0, 0);
                        $status = 'deleted';

                    } else if($bills['Approve']=='P') {
                        $status='partially';
                    } else {
                        $status='approved';
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

                $this->_view->setTerminal(true);
                $response->setContent($status);
                return $response;
            }
        }
    }


    public function checkProgressBillUsedAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $postParams = $request->getPost();
                $iBillId= $postParams['ProgressBillId'];
                $select = $sql->select();
                $select->from(array('a' => 'Crm_ReceiptAdjustment'))
                    ->join(array('b' => 'Crm_ProgressBillTrans'), 'a.ProgressBillTransId=b.ProgressBillTransId',array(), $select::JOIN_INNER)
                    ->columns(array('ProgressBillTransId'))
                    ->where(array("B.ProgressBillId"=>$iBillId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (!empty($results)) $ans ='Y';

                $response = $this->getResponse();
                $response->setContent($ans);
                return $response;
            }
        }
    }


    public function deleteprogressAction(){
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
                    $ProgressBillId = $this->params()->fromPost('ProgressBillId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProgressBill'))
                        ->columns(array('StageCompletionId'))
                        ->where(array('ProgressBillId' => $ProgressBillId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageCompletion = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $update = $sql->update();
                    $update->table('KF_StageCompletion')
                        ->set(array('PBRaised' => 0))
                        ->where(array('StageCompletionId' => $stageCompletion['StageCompletionId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_progressBillTrans'))
                        ->columns(array('StageType','StageId','UnitId'))
                        ->where(array('ProgressBillId' => $ProgressBillId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageCompletionUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($stageCompletionUnits as $units){
                        $update = $sql->update();
                        $update->table('Crm_PaymentScheduleUnitTrans')
                            ->set(array('BillPassed' => 0))
                            ->where(array('StageType' => $units['StageType'],'StageId' => $units['StageId'],'UnitId' => $units['UnitId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Crm_ProgressBill')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('ProgressBillId' => $ProgressBillId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Crm_progressBillTrans')
                        ->where(array('ProgressBillId' => $ProgressBillId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Crm_ProgressBillQualifierTrans')
                        ->where(array('ProgressBillId' => $ProgressBillId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Progress-Bill-Delete','D','Progress-Bill',$ProgressBillId,0, 0, 'CRM','',$userId, 0 ,0);


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

    public function progressRegisterAction(){
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

        $selectStage = $sql->select();
        $selectStage->from(array("a"=>"Crm_ProgressBill"));
        $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.BillDate,105) as BillDate,a.StageId,a.ProgressNo,a.ProgressBillId")))
            ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $selectStage::JOIN_LEFT)
            ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName"), $selectStage::JOIN_LEFT)
            ->where(array('a.DeleteFlag'=>0))
            ->order("a.ProgressNo Desc");
        $statement = $sql->getSqlStringForSqlObject($selectStage);
        $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->gridResult = $gridResult;
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

    public function progressEditAction(){
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

        $this->_view->adb = $dbAdapter;
        $userId = $this->auth->getIdentity()->UserId;
        $PBId = $this->bsf->isNullCheck($this->params()->fromRoute('pbId'),'number');

        $this->_view->arrVNo = CommonHelper::getVoucherNo(802, date('Y-m-d'), 0, 0, $dbAdapter, "");

        if($PBId !=0){
            $sql = new Sql($dbAdapter);
            $selectStage = $sql->select();
            $selectStage->from(array("a"=>"Crm_ProgressBill"));
            $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.BillDate,105) as BillDate,a.ProgressNo,a.CreditDays,
								Case When a.StageType='S' then G.StageName when a.StageType='O' then F.OtherCostName When a.StageType='D' then E.DescriptionName end as StageName,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
								When a.StageType='D' then 'DescriptionName' end as Stage,a.StageType,b.ProjectName,c.BlockName,c.BlockId,d.FloorName,d.FloorId,a.StageType,a.StageId,a.DemandApproval")),array())
                ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array(), $selectStage::JOIN_LEFT)
                ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array(), $selectStage::JOIN_LEFT)
                ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array(), $selectStage::JOIN_LEFT)
                ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selectStage::JOIN_LEFT)
                ->join(array("f"=>"Crm_OtherCostMaster"), "a.StageId=f.OtherCostId", array(), $selectStage::JOIN_LEFT)
                ->join(array("g"=>"KF_StageMaster"), "a.StageId=g.StageId", array(), $selectStage::JOIN_LEFT)
                ->where(array('a.ProgressBillId' => $PBId,'a.DeleteFlag'=>0));
            $statement = $sql->getSqlStringForSqlObject($selectStage);

            $progressBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
            //unitTable

            $selectUnit = $sql->select();
            $selectUnit->from(array("a"=>"Crm_ProgressBill"));
            $selectUnit->columns(array(new Expression("b.UnitId,e.UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType,b.ProgressBillTransId,b.Amount,b.QualAmount,b.NetAmount,b.PaidAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
								When a.StageType='D' then 'DescriptionName' end as Stage")))
                ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array('LateFee'), $selectUnit::JOIN_LEFT)
                ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $selectUnit::JOIN_LEFT)
                ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array(), $selectUnit::JOIN_LEFT)
//						->join(array("f"=>"Crm_UnitDetails"), "f.UnitId=b.UnitId", array(), $selectStage::JOIN_LEFT)
                ->join(array("g"=>"Crm_UnitBooking"), NEW Expression("g.UnitId=b.UnitId and g.DeleteFlag='0'"), array('BuyerName' => 'BookingName','LeadId'), $selectStage::JOIN_LEFT)
                ->where(array('a.ProgressBillId' => $PBId,'a.DeleteFlag'=>0));
            $statement = $sql->getSqlStringForSqlObject($selectUnit);
            $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $iQualCount = 0;
            foreach($selectUnit as &$unit){
                $SelectReceiptType = $sql->select();
                $SelectReceiptType->from(array("a"=>"Crm_ProgressBillReceiptTypeTrans"));
                $SelectReceiptType->columns( array('PBReceiptTypeTransId','ReceiptTypeId','ReceiptType','Percentage','Amount','QualAmount','NetAmount' ,'ReceiptTypeName' => new Expression("B.ReceiptTypeName")))
                    ->join(array("b"=>"Crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $SelectReceiptType::JOIN_INNER)
                    ->where(array('a.ProgressBillTransId' => $unit['ProgressBillTransId']))
                    ->where('a.Amount !=0');
                $statement = $sql->getSqlStringForSqlObject($SelectReceiptType);
                $unit['ReceiptTypeTrans'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach($unit['ReceiptTypeTrans'] as &$qual) {
                    $PBReceiptTypeTransId = $qual['PBReceiptTypeTransId'];
                    $PBReceiptTypeAmount = $qual['Amount'];
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_ProgressBillQualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId'), $select::JOIN_INNER)
                        ->columns(array('TransId','QualifierId','YesNo','RefId' => new Expression("'R'+ rtrim(ltrim(str(b.QualifierId)))"),'Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer',
                            'ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt',
                            'EDCessAmt','HEDCessAmt', 'SBCessAmt','KKCessAmt','NetAmt'));
                    $select->where(array('a.PBReceiptTypeTransId' => $PBReceiptTypeTransId ));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $iQualCount = $iQualCount+1;
                    $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
                    $qual['HtmlTag'] = $sHtml;
                }

            }
            $this->_view->progressBill = $progressBill;
            $this->_view->unit = $selectUnit;
            $this->_view->PBId = $PBId;
        } else {
            $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "progress-register"));
        }
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
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();

                    //Write your Normal form post code here
                    $BillDate= date('m-d-Y',strtotime($postParams['BillDate']));
                    if($postParams['DemandApproval'] == 1){
                        $DemandApproval=1;
                    } else {
                        $DemandApproval=0;
                    }
                    $update = $sql->update('Crm_ProgressBill');
                    $updateData = array(
                        'BillDate'  => $BillDate,
                        'ProgressNo'  => $postParams['ProgressNo'],
                        'CreditDays'  => $postParams['CreditDays'],
                        'DemandApproval'  => $DemandApproval,
                    );
                    $update->set($updateData);
                    $update->where(array('ProgressBillId'=>$PBId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    $amt= str_replace(',', '', $postParams['Amount']);
//					$ntamt= str_replace(',', '', $postParams['NetAmount']);
//
//					for($i=1; $i<=count($postParams['UnitId']); $i++){
//						$update = $sql->update('Crm_ProgressBillTrans');
//						$updateData = array(
//							'ProgressBillId' => $PBId,
//							'PBNo'=> $postParams['PBNo'][$i],
//							'BillDate'  => $BillDate,
//							'BuyerId'  => $postParams['LeadId'],
//							'Amount'=> $amt[$i],
//							'NetAmount'=>$ntamt[$i]
//						);
//						$update->set($updateData);
//						$update->where(array('ProgressBillTransId'=>$postParams['ProgressBillTransId'][$i]));
//					     $statement = $sql->getSqlStringForSqlObject($update);
//						$results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//						$receipt=$postParams['ReceiptTypeNetAmount'];
//						$receipt[1]= str_replace(',', '', $receipt[1]);
//						$receiptamt=$postParams['ReceiptTypeAmount'];
//						$receiptamt[1]= str_replace(',', '', $receiptamt[1]);
//
//						for($j=1; $j<=count($postParams['PBReceiptTypeTransId'][$i]); $j++){
//
//						//Print_r($ReceiptTypeNetAmount); die;
//							$UpdateReceiptType = $sql->update('Crm_ProgressBillReceiptTypeTrans');
//							$updateData = array(
//								//'Percentage'=>$postParams['ReceiptTypePercentage'][$i][$j],
//								'Amount'=>$receiptamt[$i][$j],
//								'NetAmount'=>$receipt[$i][$j],
//							);
//							$UpdateReceiptType->set($updateData);
//							$UpdateReceiptType->where(array('PBReceiptTypeTransId'=>$postParams['PBReceiptTypeTransId'][$i][$j]));
//						    $statement = $sql->getSqlStringForSqlObject($UpdateReceiptType);
//							$results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//							// delete
//							$delete = $sql->delete();
//							$delete->from('Crm_ProgressBillQualifierTrans')
//									->where(array('PBReceiptTypeTransId' => $postParams['PBReceiptTypeTransId'][$i][$j]));
//							$stmt = $sql->getSqlStringForSqlObject($delete);
//							$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
//							$ReceiptTypeTransId = $postParams['PBReceiptTypeTransId'][$i][$j];
//
//							//Qualifier Row Count
//							$qRowCount =   $this->bsf->isNullCheck($postParams['QualRowId_'.$j],'number');
//							for ($k = 1; $k <= $qRowCount; $k++) {
//								$iQualifierId = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_Id_' . $k], 'number');
//								$iYesNo = isset($postParams['Qual_' . $j . '_YesNo_' . $k]) ? 1 : 0;
//								$sExpression = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_Exp_' . $k], 'string');
//								$dExpAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_ExpValue_' . $k], 'number');
//								$dExpPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_ExpPer_' . $k], 'number');
//								$iQualTypeId= $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TypeId_' . $k], 'number');
//								$sSign = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_Sign_' . $k], 'string');
//
//                                $dCessPer = 0;
//                                $dEDPer = 0;
//                                $dHEdPer = 0;
//                                $dCessAmt = 0;
//                                $dEDAmt = 0;
//                                $dHEdAmt = 0;
//
//                                $dKKCessPer=0;
//                                $dSBCessPer=0;
//                                $dKKCessAmt=0;
//                                $dSBCessAmt=0;
//
//                                if ($iQualTypeId==1) {
//									$dTaxablePer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxablePer_' . $k], 'number');
//									$dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxPer_' . $k], 'number');
//									$dCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_CessPer_' . $k], 'number');
//									$dEDPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_EduCessPer_' . $k], 'number');
//									$dHEdPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_HEduCessPer_' . $k], 'number');
//									$dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_NetPer_' . $k], 'number');
//
//									$dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
//									$dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
//									$dCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_CessAmt_' . $k], 'number');
//									$dEDAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_EduCessAmt_' . $k], 'number');
//									$dHEdAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_HEduCessAmt_' . $k], 'number');
//									$dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_NetAmt_' . $k], 'number');
//								} else if  ($iQualTypeId==2) {
//                                    $dTaxablePer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxablePer_' . $k], 'number');
//                                    $dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxPer_' . $k], 'number');
//                                    $dKKCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_KKCessPer_' . $k], 'number');
//                                    $dSBCessPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_SBCessPer_' . $k], 'number');
//                                    $dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_NetPer_' . $k], 'number');
//
//                                    $dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
//                                    $dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
//                                    $dKKCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_KKCessAmt_' . $k], 'number');
//                                    $dSBCessAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_SBCessAmt_' . $k], 'number');
//                                    $dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_NetAmt_' . $k], 'number');
//                                } else {
//									$dTaxablePer = 100;
//									$dTaxPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_ExpPer_' . $k], 'number');
//									$dNetPer = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_ExpPer_' . $k], 'number');
//									$dTaxableAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_ExpValue_' . $k], 'number');
//									$dTaxAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_Amount_' . $k], 'number');
//									$dNetAmt = $this->bsf->isNullCheck($postParams['Qual_' . $j . '_Amount_' . $k], 'number');
//								}
//							    $dnetAmount= $dNetAmt;
//					            $dnetAmount= str_replace(',', '', $dnetAmount);
////								$insert = $sql->insert();
////								$insert->into('Crm_ProgressBillQualifierTrans');
////								$insert->Values(array('PBReceiptTypeTransId' =>$ReceiptTypeTransId,'ProgressBillId' => $PBId,'ProgressBillTransId' => $postParams['PBReceiptTypeTransId'][$i][$j],
////								'ReceiptTypeId' => $postParams['ReceiptTypeId'][$i][$j],
////								'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
////								'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
////								'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'NetAmt'=>$dnetAmount));
////
////							$statement = $sql->getSqlStringForSqlObject($insert);
////								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//							}
//						}

//					}
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Progress-Bill-Modify','E','Progress-Bill',$PBId,0, 0, 'CRM', $postParams['ProgressNo'],$userId, 0 ,0);

                    $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "progress-register"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function progressPrintAction(){
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

        $PBId = $this->bsf->isNullCheck($this->params()->fromRoute('pbId'), 'number');
        if($PBId == 0) {
            $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "progress-register"));
        }
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"Crm_ProgressBill"));
        $select->columns(array(new Expression("b.UnitId,UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,a.CreditDays,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType, b.ProgressBillTransId,b.Amount,b.NetAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage, Convert(varchar(10),i.DOB,105) as DOB")))
            ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array('*'), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $select::JOIN_LEFT)
            ->join(array("m"=>"Crm_PaymentScheduleUnitTrans"), new Expression("b.StageId=m.StageId and b.StageType = m.StageType and b.UnitId = m.UnitId"), array("PaidAmount","Percentage"), $select::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array('UnitNO'), $select::JOIN_LEFT)
            ->join(array("e1"=>"Proj_ProjectMaster"), "e.ProjectId=e1.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
            ->join(array("e2"=>"WF_OperationalCostCentre"), "e2.ProjectId=e1.ProjectId", array('CompanyId'), $select::JOIN_LEFT)
            ->join(array("z"=>"WF_CompanyMaster"), "e2.CompanyId=z.CompanyId", array('CompanyName', 'Address1'=>"Address", 'Mobile', 'Email', 'LogoPath'), $select::JOIN_LEFT)

            ->join(array("f"=>"Crm_UnitBooking"), new Expression("f.UnitId=b.UnitId and f.DeleteFlag = 0"), array('BuyerName' => 'BookingName','BookingDate','BaseAmount','NetAmount','Rate'), $select::JOIN_LEFT)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile','LeadName' ,'LeadDate','Email','LeadId'), $select::JOIN_LEFT)
            ->join(array("j"=>"Crm_LeadAddress"), new Expression("j.LeadId=f.LeadId and j.AddressType = 'C'"), array('Address'=>'Address1', 'LandLine'), $select::JOIN_LEFT)
            ->join(array("h"=>"Crm_UnitType"), "h.UnitTypeId=e.UnitTypeId", array('IntPercent'), $select::JOIN_LEFT)
            ->join(array("i"=>"Crm_LeadPersonalInfo"), "i.LeadId=f.LeadId", array(), $select::JOIN_LEFT)
            ->where(array('a.ProgressBillId' => $PBId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $selectUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($selectUnits as &$curUnit){
            $select = $sql->select();
            $select->from(array("a"=>"Crm_ReceiptRegister"))
                ->columns(array("LateAmt"=>new Expression("SUM(LateIntAmount)")))
                ->where(array('a.UnitId' => $curUnit['UnitId']))
                ->where(array('a.ReceiptAgainst' => 'L'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $curUnit['ReceiptAmount'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $curUnit['logopath'] =  getcwd()."/public/".$curUnit['LogoPath'];



        }
        $selectStage = $sql->select();
        $selectStage->from(array("a"=>"Crm_ProgressBill"));
        $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.BillDate,105) as BillDate,a.ProgressNo, a.DemandApproval,
                        Case When a.StageType='S' then G.StageName when a.StageType='O' then F.OtherCostName When a.StageType='D' then E.DescriptionName end as StageName,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage,a.StageType,b.ProjectName,c.BlockName,c.BlockId,d.FloorName,d.FloorId,a.StageType,a.StageId")),array())
            ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array('ProjectName'), $selectStage::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "h.CompanyId=b.CompanyId", array('CompanyName', 'Address1'=>"Address", 'Mobile', 'Email', 'LogoPath'), $selectStage::JOIN_LEFT)
            ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array(), $selectStage::JOIN_LEFT)
            ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array(), $selectStage::JOIN_LEFT)
            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selectStage::JOIN_LEFT)
            ->join(array("f"=>"Crm_OtherCostMaster"), "a.StageId=f.OtherCostId", array(), $selectStage::JOIN_LEFT)
            ->join(array("g"=>"KF_StageMaster"), "a.StageId=g.StageId", array(), $selectStage::JOIN_LEFT)
            ->where(array('a.ProgressBillId' => $PBId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($selectStage);
        $progressBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

        $this->_view->progressBill = $progressBill;
        $this->_view->unit = $selectUnits;
        $this->_view->PBId = $PBId;


        $pdfhtml = $this->generateProgressBillPdf($selectUnits, $progressBill,$viewRenderer);

       // $pdfhtml = $this->generateprogresspdf($selectUnits, $progressBill);
        $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";



        require_once($path);
        $dompdf = new DOMPDF();

        $dompdf->load_html($pdfhtml);
        $dompdf->set_paper("A4");
        $dompdf->render();
        //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("ProgressBill.pdf");
    }
     Private function generateProgressBillPdf($selectUnits,$progressBill,$viewRenderer){

         $pdfhtml=<<<EOT
         <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
</head>
<style>
h1{font: bold 30px/39px 'Times New Roman'; color:#666;text-align:right;}
table{font: bold 12px 'Times New Roman';border:1px solid #ddd;}
table thead tr th{font: bold 12px/22px 'Times New Roman';border:1px solid #d2d2d8; background:#e0e2e5;color:#000}
table tbody tr td{font: bold 12px/22px 'Times New Roman'; border:1px solid #ddd; background:#fff; color:#545353}
p{font: 500 12px/20px 'Times New Roman'; background:#fff;color:#494949}
.border-none table,.border-none table tbody tr td{border:none; color:#191919}
border-none table tbody tr td.heading{color:#666 !important}
.clearfix:after {content: "";display: table;clear: both;}
</style>
<body>
<!-- CSS Code -->
EOT;
         foreach($selectUnits as $selUnit) {
             $pdfhtml.=<<<EOT
<div class="border-none clearfix" style="width:100%;">
<table align="center" style="width:98%;">
       <tbody>
             <tr>
  <td width="40%" style="border-bottom:5px solid #ff0000;height:60px"><img style="width:100%;height:60px" src="#" /></td>
 <td width="60%"  style="border-bottom:5px solid #666;height:60px" align="right"><h1>Adding life to Living!&nbsp;</h1></td>
 </tr>
 </tbody>
 </table>
</div>
<!-- HTML Code -->
<div class="clearfix" style="width:100%">
  <p style="font-size:16px;color:#666;margin:5px 0 0 0;font-weight:500;padding:0; line-height:25px; font-style:italic">Corporate Office:&nbsp;{$selUnit['Address1']}</p>
  <p style="font-size:16px;color:#666;margin:0;padding:0;font-weight:500; line-height:25px; font-style:italic"><span style="border-right:2px solid #666;margin-right:5px;">Tel:&nbsp;{$selUnit['Mobile']}</span><span style="border-right:2px solid #666;margin-right:5px;">Email: &nbsp;{$selUnit['Email']} </span> <a href="#" style="margin-right:5px;color:#b70101; text-decoration:none">&nbsp;WWW.bbcl.in </a>
</div>
<!-- HTML Code -->

<div style="font-size:20px;margin-bottom:20px; text-align:center"><span style="border-bottom:3px solid #333;color:#333;line-height:45px;font-weight:600; ">TEAM SHEET</span></div>
<!-- HTML Code -->
EOT;


  $billDate = date('d-m-Y', strtotime($selUnit['BillDate']));
   if(is_numeric($selUnit['CreditDays'])) {
       $dueDate = date('d-m-Y', strtotime($selUnit['BillDate'] . '+ '.$selUnit['CreditDays'].' days'));
   }
           $sumAmount=$selUnit['QualAmount'] + $selUnit['Amount'];
           if($selUnit['PaidAmount'] > $selUnit['NetAmount']){
               $balAmount=0;
           }
           else {
               $balAmount = $selUnit['NetAmount'] - $selUnit['PaidAmount'];
           }
           $pdfhtml.=<<<EOT

<div class="border-none clearfix" style="width:100%; border-bottom:2px solid #ddd">
<table align="center" style="width:98%;">
       <tbody>
             <tr>
			 <td width="25%" class="heading">Date </td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">{$billDate}</td>
			  <td width="25%" class="heading">Project Name</td> 
			   <td width="2%">:&nbsp;</td>
			  <td width="23%">{$selUnit['ProjectName']}</td>
			  </tr>
			   <tr>
			 <td width="25%" class="heading">Buyer </td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">{$selUnit['BuyerName']}</td>
			  <td width="25%" class="heading">Unit No</td> 
			   <td width="2%">:&nbsp;</td>
			  <td width="23%">{$selUnit['UnitNo']}</td>
			  </tr>
			  <tr>
			  <td width="25%" rowspan="3" class="heading">Address</td>
            <td width="2%" rowspan="3">:&nbsp;</td>			  
			  <td width="23%" rowspan="3">{$selUnit['Address']}</td>
			 <td width="25%" class="heading">Rate</td>
            <td width="2%">:&nbsp;</td>			  
			  <td width="23%">{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['Rate'],2,true)}</td>
			  </tr>
			  <tr>
			 <td width="25%" class="heading">Basic Amount</td>
            <td width="2%">:&nbsp;</td>			  
			  <td width="23%">{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['BaseAmount'],2,true)}</td>
			  </tr>
			   <tr>
			 <td width="25%" class="heading">Total Amount</td> 
			   <td width="2%">:&nbsp;</td>
			  <td width="23%">{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['NetAmount'],2,true)}</td> 
			  </tr>	
			   <tr>
			  <td width="25%" class="heading">Telephone No</td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">{$selUnit['LandLine']}</td>
			 <td width="25%" class="heading">Total Due</td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">&nbsp;0.00</td>
			  </tr>
			  <tr>
			  <td width="25%" class="heading">Mobile No </td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">{$selUnit['Mobile']}</td>
			  <td width="25%" class="heading">Interest Due</td> 
			   <td width="2%">:&nbsp;</td>
			  <td width="23%">{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['LateFee'],2,true)}</td>
			  </tr>
           
			  <tr>
			  <td width="25%" class="heading">Booking Date</td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">&nbsp;{$selUnit['BookingDate']}</td>
			 <td width="25%" class="heading">Interest Received</td>
			 <td width="2%">:&nbsp;</td>
			 <td width="23%">{$viewRenderer->commonHelper()->sanitizeNumber($selectUnits[0]['ReceiptAmount']['LateAmt'],2,true)}</td>
			  </tr>
			</tbody>
	  </table>
</div>
<!-- HTML Code -->
<div style="width:100%"class="clearfix">
  <p><b>Dear Sir&nbsp;/&nbsp;</b></p>
  <p style="color:#666">We are pleased to inform you that the following amount are due and payable aganist the above mentioned flat.</p>
</div>

<!-- HTML Code -->

<div class="clearfix" style="width:100%;margin-bottom:15px;clear:both">
  <table style="width:100%;">
    <thead>
      <tr>
        <th>Due<br /> Date</th>
        <th>Description</th>
        <th>%</th>
        <th>StageGross<br /> Amt</th>
        <th>Service <br />tax</th>
        <th>Total</th>
        <th>Amt.Reevd</th>
        <th>Bal</th>
        <th>Interest</th>
        <th>Interest<br /> Reed</th>
        <th>Net<br /> Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{$dueDate}</td>
        <td>{$selUnit['StageName']}</td>
        <td>{$selUnit['Percentage']}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['Amount'],2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['QualAmount'],2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($sumAmount ,2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['PaidAmount'],2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($balAmount,2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['LateFee'],2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selectUnits[0]['ReceiptAmount']['LateAmt'],2,true)}</td>
        <td>{$viewRenderer->commonHelper()->sanitizeNumber($selUnit['NetAmount'],2,true)}</td>
      </tr>
    </tbody>

  </table>
</div>
EOT;
       }
         $pdfhtml .= <<<EOT

<div style="width:100%;" class="clearfix">
  <p style="color:#666">Thanking You,</p>
  <p style="color:#666">Your Faithfully,</p>
  <p style="color:#333;font-weight:600">For NAVIN HOUSING AND PROPERTIES PRIV</p>
  <p style="color:#666">Authorised Signatory</p>
</div>

<!-- HTML Code -->
<div style="width:100%;" class="clearfix">
  <table style="width:100%;">
    <thead>
      <tr>
        <th colspan="2">Bank Details</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td width="30%" style="background:#f1f1f1;">1.Name</td>
        <td width="70%">NAVIN HOUSING AND PROPERTIES PRIV</td>
      </tr>
      <tr>
        <td width="30%" style="background:#f1f1f1;">2.Account Number</td>
        <td width="70%">bv</td>
      </tr>
      <tr>
        <td width="30%" style="background:#f1f1f1;">3.Bank Name</td>
        <td width="70%">gfh</td>
      </tr>
      <tr>
        <td width="30%"style="background:#f1f1f1;">4.Branch Name</td>
        <td width="70%">dfg</td>
      </tr>
      <tr>
        <td width="30%" style="background:#f1f1f1;">5.IFSC Code</td>
        <td width="70%" >fdg</td>
      </tr>
    </tbody>
  </table>
</div>
<br /><br /><br /><br /><br /><br />
<!-- HTML Code -->
<div style="width:100%" class="clearfix">
  <p><span style="font-size:16px; color:#333; border-bottom:2px solid #600;margin-bottom:20px;font-weight:700; line-height:25px;">Note</span> </p>
  <p>1. &nbsp;NAVIN HOUSING AND PROPERTIES PRIV</span> </p>
  <p>2. &nbsp;Delayed payments will attract interest</p>
  <p>3. &nbsp;Kindly issue separate cheque for interest</p>
</div>
<div style="width:100%;" class="clearfix border-none">
  <table style="width:100%;">
    <tbody>
    <tr>
	<td width="30%"><b>Company Service Tax No</b></td>
	<td width="4%">:&nbsp;</td>
	<td width="66%">&nbsp;4506985947457</td>
	</tr>
	<tr>
    <td width="30%"><b>PAN</b>
	<td width="4%">:&nbsp;</td>
	<td>&nbsp;45747457547</td> 
	</tr>
	</tbody>
	</table>
	</div>


</body>
</html>
EOT;
         return $pdfhtml;

     }

//    private function generateProgressBillPdf(array $selectUnit, $progressBill) {
//
//        $pdfhtml = <<<EOT
//<!DOCTYPE html>
//<html lang="en">
//<head>
//<meta charset="utf-8">
//<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,400italic,600,600italic,700,700italic,800,300,300italic,800italic' rel='stylesheet' type='text/css'>
//<title>PDF</title>
//<style>
//.clearfix:after {
//content: "";
//display: table;
//clear: both;
//}
//
//a {
//text-decoration: underline;
//}
//
//body {
//position: relative;
//width: 612px;
//margin: 0 auto;
//color: #001028;
//background: #FFFFFF;
//font-size: 12px;
//font-family: "Open Sans",sans-serif;
//}
//
//header {
//padding: 10px 0;
//}
//
//#logo {
//text-align: center;
//margin-bottom: 10px;
//}
//
//#logo img {
//width: 90px;
//}
//
//h1 {
//border-top: 1px solid  #5D6975;
//border-bottom: 1px solid  #5D6975;
//color: #5D6975;
//font-size: 2.4em;
//line-height: 1.4em;
//font-weight: normal;
//text-align: center;
//margin: 0 0 20px 0;
//background: url(dimension.png);
//}
//
//.project {float: left;}
//.project span {color: #5D6975;text-align: right;width: 87px;margin-right: 10px;display: inline-block;font-size: 14px;}
//.company {float: right;text-align: right;font-size: 14px;}
//.project div,.company div {white-space: nowrap;}
//
//2
//.project2 {float:right;}
//.widspn span {width: 164px;}
//.project2 span {
//color: #5D6975;
//text-align: right;
//width: 300px;
//margin-right: 10px;
//display: inline-block;
//font-size: 11px;
//}
//.pd10{ padding:2px;}
//
//table {
//width: 100%;
//border-collapse: collapse;
//border-spacing: 0;
//margin-bottom: 20px;
//
//page-break-inside:auto;
//}
//
//table tr {
//page-break-inside:avoid;
//page-break-after:auto;
//}
//
//table tr:nth-child(2n-1) td {
//background: #F5F5F5;
//}
//
//table th,
//table td {
//text-align: center;
//}
//
//table th {
//padding: 5px 20px;
//color: #5D6975;
//border-bottom: 1px solid #C1CED9;
//white-space: nowrap;
//font-weight: 600;
//}
//
//table .service,
//table .desc {
//text-align: left;
//}
//
//table td {
//padding: 5px;
//text-align: right;
//}
//
//table td.service,
//table td.desc {
//vertical-align: top;
//}
//
//table td.unit,
//table td.qty,
//table td.total {
//font-size: 15px;}
//notices .notice {
//color: #5D6975;
//font-size: 1.2em;
//}
//
//.project.widspn {
//page-break-after: always;
//}
//.project.widspn:last-of-type {
//page-break-after: avoid;
//}
//
//footer {
//color: #5D6975;
//width: 100%;
//height: 30px;
//position: absolute;
//bottom: 0;
//border-top: 1px solid #C1CED9;
//padding: 8px 0;
//text-align: center;
//}
//</style>
//</head>
//<body style="border:1px solid #000;">
//
//EOT;
//        foreach($selectUnit as $selUnit) {
//            $billDate = date('d-m-Y', strtotime($selUnit['BillDate']));
//            $dueDate = date('d-m-Y', strtotime($selUnit['BillDate']));
//            $demandApproval = ($progressBill['DemandApproval'] == 1)? 'Demand Letter': '';
//
//            if(is_numeric($selUnit['CreditDays'])) {
//                $dueDate = date('d-m-Y', strtotime($selUnit['BillDate'] . '+ '.$selUnit['CreditDays'].' days'));
//            }
//            $pdfhtml .= <<<EOT
//	<header class="clearfix" style="padding-left:10px; padding-right:10px;">
//        <div id="logo">
//           <!-- <img src=""/>-->
//        </div>
//        <h1>Bulidsuperfast Invoicebill</h1>
//    </header>
//    <div align="center" style="width:100%;">
//        <table align="center" style="width:98%;">
//            <tbody>
//                <tr>
//                    <td style="text-align:left !important;">
//                        <div class="project">
//                            <div class="pd10"><span>REF No. : </span>{$selUnit['PBNo']}</div>
//                            <div class="pd10"><span>CLIENT : </span>{$selUnit['BuyerName']} </div>
//                            <div class="pd10"><span>Unit No. : </span>{$selUnit['UnitNo']}</div>
//                            <div class="pd10"><span>EMAIL : </span>{$selUnit['Email']}</div>
//                            <div class="pd10"><span>MOBILE No. : </span>{$selUnit['Mobile']}</div>
//                            <div class="pd10"><span>DATE : </span>$billDate</div>
//                            <div class="pd10"><span>REQUEST : </span>$demandApproval</div>
//                            <div class="pd10"><span>Stage Name : </span>{$progressBill['StageName']}</div>
//                        </div>
//                    </td>
//                    <td>
//                        <div class="company" style="padding: 0px !important">
//                            <div >{$progressBill['CompanyName']}</div>
//                            <div>{$progressBill['Address']}</div>
//                            <div>{$progressBill['Mobile']}</div>
//                            <div>{$progressBill['Email']}</div>
//                        </div>
//                    </td>
//                </tr>
//            </tbody>
//        </table>
//    </div>
//    <main>
//        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
//            <p style="padding: 0 15px ; ">This is with reference to the booking of your unit in our Project <span style="color:#000;">{$progressBill['ProjectName']}.</span> We are pleased to inform you that we have completed the below Stage.</p>
//        </div>
//        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
//            <p style="padding: 0 15px ; ">You are therefore requested to make the payment before the due date.</p>
//        </div>
//        <div align="center" style="width:100%;">
//            <table align="center" style="width:98%;">
//                <thead  style="font-size: 16px ! important; ">
//                    <tr>
//                        <th class="desc">DESCRIPTION</th>
//                        <th>Gross</th>
//                        <th>Service Tax</th>
//                        <th>Net Amount</th>
//                    </tr>
//                </thead>
//                <tbody>
//EOT;
//            if(count($selUnit['ReceiptTypeTrans'])>0){
//
//
//            foreach($selUnit['ReceiptTypeTrans'] as $receiptTrans) {
//                $pdfhtml .= <<<EOT
//                    <tr>
//                        <td class="desc">{$receiptTrans['ReceiptTypeName']}</td>
//                        <td class="unit">{$receiptTrans['Amount']}</td>
//                        <td class="qty">{$receiptTrans['QualAmount']}</td>
//                        <td class="total">{$receiptTrans['NetAmount']}</td>
//                    </tr>
//EOT;
//            }
//            }else{
//                $pdfhtml .= <<<EOT
//                <tr>
//                        <td class="desc">{$progressBill['StageName']}</td>
//                        <td class="unit">{$selUnit['NetAmount']}</td>
//                        <td class="qty"></td>
//                        <td class="total">{$selUnit['NetAmount']}</td>
//                    </tr>
//EOT;
//            }
//            if(isset($selUnit['PaidAmount']) && $selUnit['PaidAmount']>0) {
//                $pdfhtml .= <<<EOT
//                    <tr>
//                        <td colspan="3">Paid Amount</td>
//                        <td class="total">{$selUnit['PaidAmount']}</td>
//                    </tr>
//EOT;
//            }
//            $payable=$selUnit['NetAmount'];
//            if(isset($selUnit['PaidAmount'])) {
//                if(floatval($selUnit['PaidAmount'])> floatval($selUnit['NetAmount'])){
//                    $payable=0;
//                }else{
//                    $payable = $selUnit['NetAmount'] - $selUnit['PaidAmount'];
//            }
//            }
//            $pdfhtml .= <<<EOT
//                    <tr>
//                        <td colspan="3" >Net Payable</td>
//                        <td class="total"><b>{$payable}</b></td>
//                    </tr>
//                </tbody>
//            </table>
//        </div>
//        <div align="center" id="notices">
//            <div class="notice">NOTICE : A finance charge of {$selUnit['IntPercent']}% will be made on unpaid balances after {$selUnit['CreditDays']} days. <b>("Due Date: $dueDate")</b></div>
//        </div>
//        <div class="project widspn" style="margin-top:20px; width:100%;"></div>
//        <div class="clearfix"></div>
//    </main>
//EOT;
//        }
//        $pdfhtml .= <<<EOT
//    <div style="font-size: 15px; color:#4E4C4C; padding:10px 15px;">
//        <p>Thanking you and assuring of our best services at all times.</p>
//        <p style="padding: 0 15px ; ">For {$progressBill['CompanyName']}</p>
//        <p style="padding: 0 15px ; "><b>(Authorised Signatory)</b></p>
//    </div>
//</body>
//</html>
//EOT;
//        return $pdfhtml;
//    }

    public function getqualifierAction(){
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
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a" => "Proj_QualifierTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId'), $select::JOIN_INNER)
                    ->columns(array('QualifierId','YesNo','RefId' => new Expression("'R'+ rtrim(ltrim(str(RefId)))"),'Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer'));
                $select->where(array('a.QualType' => 'C'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($sHtml);
                return $response;
            }
        }
    }

    public function extraAction(){
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
        $iQualCount = 0;
        $sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;

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

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();


                    //voucher no
                    $arrTransVNo = CommonHelper::getVoucherNo(803, date('m-d-Y', strtotime($postData['booking_date'])), 0, 0, $dbAdapter, "I");
                    if($arrTransVNo['genType']== true){
                        $extraBillNo = $arrTransVNo['voucherNo'];
                    } else {
                        $extraBillNo = $postData['extraBillNo'];
                    }

                    $insert = $sql->insert('Crm_ExtraBillRegister');
                    $insertData = array(
                        'ExtraBillNo'  => $extraBillNo,
                        'ExtraBillDate' => date('m-d-Y', strtotime($postData['booking_date'])),
                        'UnitId' => $this->bsf->isNullCheck($postData['unitId'],'number'),
                        'Amount' => $this->bsf->isNullCheck($postData['grossAmount'],'number'),
                        'QualAmount' => $this->bsf->isNullCheck($postData['taxAmount'],'number'),
                        'NetAmount' => $this->bsf->isNullCheck($postData['netAmount'],'number'),
                        'ReceiptTypeId' => 3
                    );
                    $insert->values($insertData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $extraBillRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $update = $sql->update();
                    $update->table('Crm_ExtraItemDoneRegister');
                    $update->set(array(
                        'BillDone'=>1,

                    ));
                    $update->where(array('ExtraItemDoneRegId'=>$this->bsf->isNullCheck($postData['billdone'],'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach($postData as $key => $data) {
                        if(preg_match('/^extraItemId_[\d]+$/', $key)) {

                            preg_match_all('/^extraItemId_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $id], 'number');
                            if($extraItemId <= 0) {
                                continue;
                            }

                            $extraBillTrans = array(
                                'ExtraBillRegisterId' => $extraBillRegId,
                                'ExtraItemId' => $extraItemId,
                                'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number'),
                                'Rate' => $this->bsf->isNullCheck($postData['transRate_' . $id], 'number'),
                                'Qty' => $this->bsf->isNullCheck($postData['transQuantity_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_ExtraBillTrans');
                            $insert->values($extraBillTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Qualifier
                    $j=1;
                    $qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$j],'number');
                    for ($k = 1; $k <= $qRowCount; $k++) {
                        $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Id_' . $k], 'number');
                        $iYesNo = isset($postData['Qual_' . $j . '_YesNo_' . $k]) ? 1 : 0;
                        $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Exp_' . $k], 'string');
                        $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                        $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                        $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $j . '_TypeId_' . $k], 'number');
                        $sSign = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Sign_' . $k], 'string');

                        $dCessPer = 0;
                        $dEDPer = 0;
                        $dHEdPer = 0;
                        $dCessAmt = 0;
                        $dEDAmt = 0;
                        $dHEdAmt = 0;
                        $dKKCessPer=0;
                        $dSBCessPer=0;
                        $dKKCessAmt=0;
                        $dSBCessAmt =0;

                        if ($iQualTypeId==1) {
                            $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
                            $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessPer_' . $k], 'number');
                            $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessPer_' . $k], 'number');
                            $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
                            $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessAmt_' . $k], 'number');
                            $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessAmt_' . $k], 'number');
                            $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessAmt_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
                        } else if ($iQualTypeId==2) {
                            $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
                            $dKKCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_KKCessPer_' . $k], 'number');
                            $dSBCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_SBCessPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
                            $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_KKCessAmt_' . $k], 'number');
                            $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_SBCessAmt_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
                        } else {
                            $dTaxablePer = 100;
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                        }

                        $insert = $sql->insert();
                        $insert->into('Crm_ExtraBillQualifierTrans');
                        $insert->Values(array('extraBillRegId' => $extraBillRegId,
                            'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                            'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCessPer,'SBCess'=>$dSBCessPer, 'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                            'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt, 'NetAmt'=>$dNetAmt));

                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Entry-Add','N','Extra-Bill-Entry',$extraBillRegId,0, 0, 'CRM', $extraBillNo,$userId, 0 ,0);
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("crm/default", array("controller" => "bill", "action" => "extra"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("crm/default", array("controller" => "bill", "action" => "extra"));
                    }
//                    $this->redirect()->toRoute("crm/default", array("controller" => "bill", "action" => "extra"));
                }
                else{
                    $this->_view->arrVNo = CommonHelper::getVoucherNo(804, date('Y-m-d'), 0, 0, $dbAdapter, "");

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_ReceiptTypeMaster"))
                         ->columns(array('TaxablePer' ))
                       ->where("ReceiptType = 'E' and ReceiptTypeId='3'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $tax = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a" => "KF_UnitMaster"))
                        ->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('c' => 'Crm_QualifierSettings'))
                        ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                            array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                    $select->where(array( 'QualSetTypeId' => 3, 'QualSetType'=>'S', 'a.QualType' => 'C'))
                        ->order('SortOrder ASC');
                   $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $taxablePer = $tax['TaxablePer'];

                    foreach($qualList as &$list){

                        if ($list['QualifierTypeId'] == 1) {

                            $tdstype = 11;
                            $date = date('Y/m/d');
                            $tds = CommonHelper::getTDSSetting($tdstype, $date, $dbAdapter);
                            $list['TaxablePer'] = $tds["TaxablePer"];
                            $list['TaxPer'] = $tds["TaxPer"];
                            $list['SurCharge'] = $tds["SurCharge"];
                            $list['EDCess'] = $tds["EDCess"];
                            $list['HEDCess'] = $tds["HEDCess"];
                            $list['NetPer'] = $tds["NetTax"];
                        } else if ($list['QualifierTypeId'] == 2) {

                            $tdstype = 'F';
                            $date = date('Y/m/d');
                            $tds = CommonHelper::getSTSetting($tdstype, $date, $dbAdapter);
                            $list['TaxablePer'] = $taxablePer;
                            $list['TaxPer'] = $tds["TaxPer"];
                            $list['KKCess'] = $tds["KKCess"];
                            $list['SBCess'] = $tds["SBCess"];
                            $list['NetPer'] = $tds["NetTax"];
                        }
                        else {
                            $list['TaxablePer'] = 0;
                            $list['TaxPer'] = 0;
                            $list['SurCharge'] = 0;
                            $list['EDCess'] = 0;
                            $list['HEDCess'] = 0;
                            $list['NetPer'] = 0;
                        }

                    }

                    $sHtml=Qualifier::getQualifier($qualList);
                    $iQualCount = $iQualCount+1;
                    $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
                    $qualHtml = $sHtml;


                }


            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            $this->_view->qualHtml = $qualHtml;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function extraEditAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
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

        $this->_view->arrVNo = CommonHelper::getVoucherNo(804, date('Y-m-d'), 0, 0, $dbAdapter, "");

        $extraBillRegId = $this->bsf->isNullCheck($this->params()->fromRoute('extraBillRegId'), 'number');
        $iQualCount = 0;
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array("a" => "Crm_ExtraBillQualifierTrans"))
          ->join(array("c" => "Proj_QualifierTrans"), "a.QualifierId=c.QualifierId", array(), $select::JOIN_INNER)
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER)
            ->columns(array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(c.RefId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer','ExpressionAmt','TaxableAmt',
                'TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt'));
        $select->where("a.ExtraBillRegId=$extraBillRegId and  c.QualType='C'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sHtml=Qualifier::getQualifier($qualList);
        $iQualCount = $iQualCount+1;
        $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
        $this->_view->qualHtml = $sHtml;

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
                    //$extraBillRegId = $this->bsf->isNullCheck($postData['extraBillRegId'], 'number');
                    if($extraBillRegId <= 0) {
                        throw new \Exception('Invalid Extra Bill Register!');
                    }
                    $vNo = $this->bsf->isNullCheck($postData['extraBillNo'], 'string');
                    $arrValues = array(
                        'ExtraBillNo' => $this->bsf->isNullCheck($postData['extraBillNo'], 'string'),
                        'ExtraBillDate' => date('Y-m-d', strtotime($postData['booking_date'])),
                        'Amount' => $this->bsf->isNullCheck($postData['net_total'], 'number'),
                        'NetAmount' => $this->bsf->isNullCheck($postData['net_total'], 'number'),
                    );

                    $update = $sql->update();
                    $update->table('Crm_ExtraBillRegister')
                        ->set($arrValues)
                        ->where(array('ExtraBillRegisterId' => $extraBillRegId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    // delete trans
                    $delete = $sql->delete();
                    $delete->from('Crm_ExtraBillTrans')
                        ->where(array('ExtraBillRegisterId' => $extraBillRegId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach($postData as $key => $data) {
                        if(preg_match('/^extraItemId_[\d]+$/', $key)) {

                            preg_match_all('/^extraItemId_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $id], 'number');
                            if($extraItemId <= 0) {
                                continue;
                            }

                            $extraBillTrans = array(
                                'ExtraBillRegisterId' => $extraBillRegId,
                                'ExtraItemId' => $extraItemId,
                                'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number'),
                                'Rate' => $this->bsf->isNullCheck($postData['transRate_' . $id], 'number'),
                                'Qty' => $this->bsf->isNullCheck($postData['transQuantity_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_ExtraBillTrans');
                            $insert->values($extraBillTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    // delete
                    $delete = $sql->delete();
                    $delete->from('Crm_ExtraBillQualifierTrans')
                        ->where(array('ExtraBillRegId' => $extraBillRegId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $j=1;
                    //Qualifier Row Count
                    $qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$j],'number');
                    for ($k = 1; $k <= $qRowCount; $k++) {
                        $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Id_' . $k], 'number');
                        $iYesNo = isset($postData['Qual_' . $j . '_YesNo_' . $k]) ? 1 : 0;
                        $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Exp_' . $k], 'string');
                        $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                        $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                        $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $j . '_TypeId_' . $k], 'number');
                        $sSign = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Sign_' . $k], 'string');

                        $dCessPer = 0;
                        $dEDPer = 0;
                        $dHEdPer = 0;
                        $dCessAmt = 0;
                        $dEDAmt = 0;
                        $dHEdAmt = 0;
                        $dKKCessPer=0;
                        $dSBCessPer=0;
                        $dKKCessAmt=0;
                        $dSBCessAmt =0;

                        if ($iQualTypeId==1) {
                            $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
                            $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessPer_' . $k], 'number');
                            $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessPer_' . $k], 'number');
                            $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
                            $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessAmt_' . $k], 'number');
                            $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessAmt_' . $k], 'number');
                            $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessAmt_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
                        } else if ($iQualTypeId==2) {
                            $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
                            $dKKCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_KKCessPer_' . $k], 'number');
                            $dSBCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_SBCessPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
                            $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_KKCessAmt_' . $k], 'number');
                            $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_SBCessAmt_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
                        } else {
                            $dTaxablePer = 100;
                            $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                            $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                            $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                            $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                            $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                        }

                        $insert = $sql->insert();
                        $insert->into('Crm_ExtraBillQualifierTrans');
                        $insert->Values(array('extraBillRegId' => $extraBillRegId,
                            'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                            'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCessPer,'SBCess'=>$dSBCessPer, 'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                            'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt, 'NetAmt'=>$dNetAmt));

                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Entry-Modify','E','Extra-Bill-Entry',$extraBillRegId,0, 0, 'CRM', $vNo,$userId, 0 ,0);

                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('crm/extra-edit', array('controller' => 'bill', 'action' => 'extra-edit', 'extraBillRegId' => $extraBillRegId), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/extra-edit', array('controller' => 'bill', 'action' => 'extra-edit', 'extraBillRegId' => $extraBillRegId));
                    }

//                    $this->redirect()->toRoute('crm/extra-edit', array('controller' => 'bill', 'action' => 'extra-edit', 'extraBillRegId' => $extraBillRegId));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {


                    if($extraBillRegId <= 0) {
                        throw new \Exception('Invalid Bill!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ExtraBillRegister'))
                        ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array("c" => "Crm_UnitBooking"), "b.UnitId=c.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "Crm_Leads"), "d.LeadId=c.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "Proj_ProjectMaster"), "e.ProjectId=b.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('*', 'unit_name' => new Expression("e.ProjectName + ' : ' + b.UnitNo + ' ('+d.LeadName + ')'")))
                        ->where(array('a.ExtraBillRegisterId' => $extraBillRegId, 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $extraBill = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if(empty($extraBill)) {
                        throw new \Exception('Bill not found!');
                    }
                    $this->_view->extraBill = $extraBill;

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ExtraBillTrans'))
                        ->join(array('b' => 'Crm_ExtraItemMaster'), 'a.ExtraItemId=b.ExtraItemId', array('ItemDescription'), $select::JOIN_LEFT)
                        ->join(array("c" => "Proj_UOM"), "c.UnitId=b.MUnitid", array('UnitName'=>'UnitName'), $select::JOIN_INNER)
                        ->where(array('ExtraBillRegisterId' => $extraBillRegId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrExtraBillTrans = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitExtraItemTrans"))
                        ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('data' => 'ExtraItemId', 'value' => 'ItemDescription','Code'=>'Code'), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "c.UnitId=b.MUnitid", array('UnitName'=>'UnitName'), $select::JOIN_INNER)
                        ->columns(array('Rate'=>'Rate','Amount'=>'Amount','Quantity'=>'Quantity'),array('data' => 'ExtraItemId', 'value' => 'ItemDescription','Code'=>'Code'),array('UnitName'=>'UnitName'))
                        ->where(array('a.UnitId'=>$extraBill['UnitId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    // AJAX Request
    public function extraitemlistAction() {
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

                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    if($UnitId == 0) {
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
    }
    public function paymentVoucherAction() {
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
            }
            $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
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

                    $unitId = $this->bsf->isNullCheck($postData[ 'unitId' ],'number');

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array('ExcessAmount'))
                        ->where(array("a.UnitId" => $unitId,'ReceiptAgainst'=>'B','a.DeleteFlag'=>0))
                        ->order('a.ReceiptId desc');
                  $stmt = $sql->getSqlStringForSqlObject($select);
                    $Receipt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


//                    $select = $sql->select();
//                    $select->from(array("a" => "Crm_ReceiptRegister"))
//                        ->columns(array("rAmount" => new Expression("isnull(SUM(a.NetAmount),0)")))
//                        ->where(array('a.UnitId'=>$unitId,'a.DeleteFlag'=>0));
//                    $statement = $sql->getSqlStringForSqlObject( $select);
//                    $Receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

//                    $select = $sql->select();
//                    $select->from(array("a" => "Crm_ProgressBillTrans"))
//                        ->join(array('b' => 'Crm_ProgressBill'), 'a.ProgressBillId=b.ProgressBillId', array(), $select::JOIN_LEFT)
//                        ->columns(array("bAmount" => new Expression("isnull(SUM(a.Amount),0)")))
//                        ->where(array('a.UnitId'=>$unitId,'b.DeleteFlag'=>0));
//                    $statement = $sql->getSqlStringForSqlObject( $select);
//                    $BIll= $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_PaymentVoucher"))
                        ->columns(array("vAmount" => new Expression("isnull(SUM(a.ExcessAmount),0)")))
                        ->where(array('a.UnitId'=>$unitId,'a.DeleteFlag'=>0));
                 $statement = $sql->getSqlStringForSqlObject( $select);
                    $voucher = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                    $excessAmt = floatval($Receipt['ExcessAmount']);

                    $excessAmt = floatval($excessAmt) - floatval($voucher['vAmount']);

                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($excessAmt);
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
                    $voucherId = $this->bsf->isNullCheck($postData['voucherId'], 'number');
                    $PaymentMode = $this->bsf->isNullCheck($postData['PaymentMode'],'string');
                    $TNo = $this->bsf->isNullCheck($postData['TNo'], 'string');
                    $BankName = $this->bsf->isNullCheck($postData['BankName'], 'string');
                    $Remarks = $this->bsf->isNullCheck($postData['Remarks'], 'string');
                    $TransDate = date('m-d-Y H:i:s',strtotime($postData['TDate']));
                    if(trim($PaymentMode)=="Cash") {
                        $TNo="";
                        $BankName="";
                        $TransDate=date('m-d-Y H:i:s');
                    }
                    if($voucherId==0) {
                        $arrVNo = CommonHelper::getVoucherNo(822, date('m-d-Y',strtotime($postData['date'])), 0, 0, $dbAdapter, "I");
                        if($arrVNo['genType']== true){
                            $refNo = $arrVNo['voucherNo'];
                        } else {
                            $refNo = $this->bsf->isNullCheck($postData['ref_no'],'string');
                        }
                        $unitId = $this->bsf->isNullCheck($postData['unitId'],'number');
                        $excessAmount = $this->bsf->isNullCheck($postData['excess_amount'],'number');
                        $insert = $sql->insert('Crm_PaymentVoucher');
                        $insertData = array(
                            'PaymentVoucherNo'  => $refNo,
                            'VoucherDate' => date('m-d-Y H:i:s',strtotime($postData['date'])),
                            'UnitId'=>$unitId,
                            'ExcessAmount'=>$excessAmount,
                            'createdDate'=>date('m-d-Y H:i:s'),
                            'TransNo'=>$TNo,
                            'TransDate'=>$TransDate,
                            'PaymentMode'=>$PaymentMode,
                            'BankName'=>$BankName,
                            'TransRemarks'=>$Remarks
                        );
                        $insert->values($insertData);
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $excessAmount = $this->bsf->isNullCheck($postData['excess_amount'],'number');

                        $update = $sql->update();
                        $update->table('Crm_PaymentVoucher');
                        $update->set(array(
                            'ExcessAmount'=>$excessAmount,
                            'ModifiedDate'=>date('m-d-Y H:i:s'),
                            'TransNo'=>$TNo,
                            'TransDate'=>$TransDate,
                            'PaymentMode'=>$PaymentMode,
                            'BankName'=>$BankName,
                            'TransRemarks'=>$Remarks
                        ));
                        $update->where(array('PaymentVoucherId'=>$voucherId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $this->redirect()->toRoute( 'crm/default', array(
                        'controller' => 'bill',
                        'action' => 'payment-voucher',
                    ));

                } catch(\Exception $ex) {
                    $connection->rollback();
                    print "Error!: " . $ex->getMessage() . "</br>";
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {
                    $voucherId = $this->bsf->isNullCheck($this->params()->fromRoute('voucherId'), 'number');
                    if($voucherId!=0) {
                        $select = $sql->select();
                        $select->from(array("a" => "Crm_PaymentVoucher"))
                            ->columns(array('*'))
                            ->where("a.PaymentVoucherId=$voucherId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $payVoucherDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->payVoucherDetails=$payVoucherDetails;

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_UnitBooking"))
                            ->join(array('u' => 'Crm_UnitDetails'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                            ->join(array("b" => "Crm_Leads"), "a.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
                            ->join(array("m" => "KF_UnitMaster"), "u.UnitId=m.UnitId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                            ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                            ->where(array("a.UnitId"=>$payVoucherDetails['UnitId'],'a.DeleteFlag'=>0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->ownerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_ReceiptRegister"))
                            ->columns(array("rAmount" => new Expression("isnull(SUM(a.Amount),0)")))
                            ->where(array('a.UnitId'=>$payVoucherDetails['UnitId'],'a.DeleteFlag'=>0));
                        $statement = $sql->getSqlStringForSqlObject( $select);
                        $Receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_ProgressBillTrans"))
                            ->join(array('b' => 'Crm_ProgressBill'), 'a.ProgressBillId=b.ProgressBillId', array(), $select::JOIN_LEFT)
                            ->columns(array("bAmount" => new Expression("isnull(SUM(a.Amount),0)")))
                            ->where(array('a.UnitId'=>$payVoucherDetails['UnitId'],'b.DeleteFlag'=>0));
                        $statement = $sql->getSqlStringForSqlObject( $select);
                        $BIll= $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_PaymentVoucher"))
                            ->columns(array("vAmount" => new Expression("isnull(SUM(a.ExcessAmount),0)")))
                            ->where(array('a.UnitId'=>$payVoucherDetails['UnitId'],'a.DeleteFlag'=>0));
                        $select->where("a.PaymentVoucherId<>$voucherId");
                        $statement = $sql->getSqlStringForSqlObject( $select);
                        $voucher = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                        $excessAmt = floatval($Receipt['rAmount'])-floatval($BIll['bAmount']);
                        $excessAmt = floatval($excessAmt) - floatval($voucher['vAmount']);
                        if($excessAmt<0){
                            $excessAmt=0;
                        }
                        $this->_view->excessAmt = $excessAmt;
                    }
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitBooking"))
                        ->join(array('u' => 'Crm_UnitDetails'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                        ->join(array("b" => "Crm_Leads"), "a.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "u.UnitId=m.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                        ->where(array('a.DeleteFlag'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $aVNo = CommonHelper::getVoucherNo(822, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->genType = $aVNo["genType"];
                    if ($aVNo["genType"] == false)
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];

                    $this->_view->voucherId = $voucherId;
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

    public function extraRegisterAction(){
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
        $select->from(array("a" => "Crm_ExtraBillRegister"))
            ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_INNER)
            ->join(array('d' => 'Proj_ProjectMaster'), 'b.ProjectId=d.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
            ->columns(array("ExtraBillRegisterId", "ExtraBillNo", "ExtraBillDate" => new Expression("FORMAT(a.ExtraBillDate, 'dd-MM-yyyy')"),"Amount","NetAmount"))
            ->where('a.DeleteFlag=0')
            ->order('a.ExtraBillRegisterId desc');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->extraBills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


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
    public function extraPrintAction(){
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
        $extraBillId = $this->bsf->isNullCheck($this->params()->fromRoute('extraId'), 'number');
        if($extraBillId == 0) {
            $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "extra-register"));
        }

        $select = $sql->select();
        $select->from(array('a' => 'Crm_ExtraBillRegister'))
            ->join(array('b' => 'crm_unitBooking'), 'a.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
            ->join(array("c" => "KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
            ->join(array("d" => "Crm_Leads"), "b.LeadId=d.LeadId", array(), $select::JOIN_LEFT)
            ->join(array("e" => "Proj_ProjectMaster"), "e.ProjectId=c.ProjectId", array(), $select::JOIN_LEFT)
            ->join(array("f" => "WF_CompanyMaster"), "f.CompanyId=e.CompanyId", array(), $select::JOIN_LEFT)
            ->columns(array('ExtraBillNo','ExtraBillDate'=> new Expression("format(a.ExtraBillDate,'dd-MM-yyyy')"),'Amount','NetAmount','UnitNo'=> new Expression("c.UnitNo")
            ,'LeadName'=> new Expression("d.LeadName"),'Email'=> new Expression("d.Email"),'Mobile'=> new Expression("d.Mobile"),'CompanyName'=> new Expression("f.CompanyName")
            ,'Address'=> new Expression("f.Address"),'CompanyMobile'=> new Expression("f.Mobile"),'CompanyEMail'=> new Expression("f.Email"),'Photo'=> new Expression("f.LogoPath"),'ProjectName'=> new Expression("e.ProjectName")))
            ->where(array('a.ExtraBillRegisterId' => $extraBillId, 'a.DeleteFlag' => 0));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $extraBill = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(empty($extraBill)) {
            throw new \Exception('Bill not found!');
        }
        $select = $sql->select();
        $select->from(array('a' => 'Crm_ExtraBillTrans'))
            ->join(array('b' => 'Crm_ExtraItemMaster'), 'a.ExtraItemId=b.ExtraItemId', array('ItemDescription'), $select::JOIN_LEFT)
            ->columns(array('Qty','Rate','Amount'))
            ->where(array('ExtraBillRegisterId' => $extraBillId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $arrExtraBillTrans = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //print

        $pdfhtml = $this->generateExtraBillPdf($extraBill, $arrExtraBillTrans);

        require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
        $dompdf = new DOMPDF();

        $dompdf->load_html($pdfhtml);
        $dompdf->set_paper("A4");
        $dompdf->render();
        //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("ProgressBill.pdf");
    }
    private function generateExtraBillPdf($extraBill, $arrExtraBillTrans) {

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
.project span {color: #5D6975;text-align: right;width: 87px;margin-right: 10px;display: inline-block;font-size: 11px;}
.company {float: right;text-align: right;font-size: 11px;}
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
font-size: 11px;
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
text-align: center;
}

table th {
padding: 5px 20px;
color: #5D6975;
border-bottom: 1px solid #C1CED9;
white-space: nowrap;
font-weight: 600;
}

table .service,
table .desc {
text-align: left;
}

table td {
padding: 5px;
text-align: right;
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
	<header class="clearfix" style="padding-left:10px; padding-right:10px;">
        <div id="logo">
            <img src="{$extraBill['Photo']}"/>
        </div>
        <h1>Bulidsuperfast Invoicebill</h1>
    </header>
    <div align="center" style="width:100%;">
        <table align="center" style="width:98%;">
            <tbody>
                <tr>
                    <td style="text-align:left !important;">
                        <div class="project">
                            <div class="pd10"><span>REF No. : </span>{$extraBill['ExtraBillNo']}</div>
                            <div class="pd10"><span>CLIENT : </span>{$extraBill['LeadName']} </div>
                            <div class="pd10"><span>Unit No. : </span>{$extraBill['UnitNo']}</div>
                            <div class="pd10"><span>EMAIL : </span>{$extraBill['Email']}</div>
                            <div class="pd10"><span>MOBILE No. : </span>{$extraBill['Mobile']}</div>
                            <div class="pd10"><span>DATE : </span>{$extraBill['ExtraBillDate']}</div>
                        </div>
                    </td>
                    <td>
                        <div class="company" style="padding: 0px !important">
                            <div >{$extraBill['CompanyName']}</div>
                            <div>{$extraBill['Address']}</div>
                            <div>{$extraBill['CompanyMobile']}</div>
                            <div>{$extraBill['CompanyEMail']}</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <main>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">This is with reference to the booking of your unit in our Project <span style="color:#000;">{$extraBill['ProjectName']}.</span> We are pleased to inform you that we have completed the below Stage.</p>
        </div>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">You are therefore requested to make the payment before the due date.</p>
        </div>
        <div align="center" style="width:100%;">
            <table align="center" style="width:98%;">
                <thead  style="font-size: 16px ! important; ">
                    <tr>
                        <th class="desc">Extra Item</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
EOT;
        foreach($arrExtraBillTrans as $extraItemTrans) {
            $pdfhtml .= <<<EOT
                    <tr>
                        <td class="desc">{$extraItemTrans['ItemDescription']}</td>
                        <td class="unit">{$extraItemTrans['Qty']}</td>
                        <td class="qty">{$extraItemTrans['Rate']}</td>
                        <td class="total">{$extraItemTrans['Amount']}</td>
                    </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
					<tr>
                        <td colspan="3" >Tax Payable</td>
                        <td class="total"><b>0</b></td>
                    </tr>
                    <tr>
                        <td colspan="3" >Net Payable</td>
                        <td class="total"><b>{$extraBill['NetAmount']}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="project widspn" style="margin-top:20px; width:100%;"></div>
        <div class="clearfix"></div>
    </main>
    <div style="font-size: 15px; color:#4E4C4C; padding:10px 15px;">
        <p>Thanking you and assuring of our best services at all times.</p>
        <p style="padding: 0 15px ; ">For {$extraBill['CompanyName']}</p>
        <p style="padding: 0 15px ; "><b>(Authorised Signatory)</b></p>
    </div>
</body>
</html>
EOT;
        return $pdfhtml;
    }

    public function paymentVoucherRegisterAction(){
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
            $response = $this->getResponse();

            if ($request->isPost()) {
                //Write your Ajax post code here
                try {
                    $postData = $request->getPost();
                    $PaymentVoucherId = $this->bsf->isNullCheck($postData['PaymentVoucherId'],'number');
                    $Remarks = $this->bsf->isNullCheck($postData['Remarks'],'string');

                    $update = $sql->update();
                    $update->table('Crm_PaymentVoucher');
                    $update->set(array(
                        'DeleteFlag'=>1,
                        'Remarks'=>$Remarks,
                        'ModifiedDate'=>date('m-d-Y H:i:s')
                    ));
                    $update->where(array('PaymentVoucherId'=>$PaymentVoucherId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent('success');
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                $select = $sql->select();
                $select->from(array("a" => "Crm_PaymentVoucher"))
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_LEFT)
                    ->join(array("c" => "Crm_UnitBooking"), new expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array('LeadId'), $select::JOIN_LEFT)
                    ->join(array("d" => "Crm_Leads"), "c.LeadId=d.LeadId", array('LeadName'), $select::JOIN_LEFT)
                    ->join(array("e" => "Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->columns(array('*'))
                    ->where(array('a.DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->payVoucherDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    private function generateVoucherPdf($payVoucherDetails,$viewRenderer) {

        $voucherDate = date('d-m-Y', strtotime($payVoucherDetails['VoucherDate']));
        $transDate = date('d-M-Y', strtotime($payVoucherDetails['TransDate']));
        $amtInWords = $this->convertAmountToWords($payVoucherDetails['ExcessAmount']);
        $pdfHtml = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Payment Voucher</title>
<style>
	#logo {
		  text-align: center;
		  margin-bottom: 10px;
		}
		.project {float: left;}
		.company {float: right;}
		.project div,.company div {white-space: nowrap;}
</style>
</head>
<body>
<div style="width:700px; margin:auto; height:600px; padding:20px;">
    <div style="width:650px; height:550px;margin:auto; padding:10px;border:2px solid #787878">
		<div id="logo">
            <img src="{$payVoucherDetails['LogoPath']}.png"/>
        </div>
        <p style="font-size:21px;text-align:center;padding:0px; font-weight:600;">
        	{$payVoucherDetails['CompanyName']}<br />
        	<span style="font-size:12px;">{$payVoucherDetails['Address']}</br>{$payVoucherDetails['Email']}</span>
        </p>
        <p style="text-align:center; text-decoration:underline; font-size:18px; font-weight:700;">
        	RECEIPT
        </p>
		<div align="center" style="width:100%;">
			<table align="center" style="width:98%;">
				<tbody>
					<tr>
						<td style="text-align:left !important;">
							<div class="project">
								<div style="font-weight:600;">No.{$payVoucherDetails['PaymentVoucherNo']}</div>
							</div>
						</td>
						<td style="text-align:right !important;">
							<div class="company" style="padding: 0px !important">
								<div><b>DATE :</b> {$voucherDate}</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

        <br>
        <p style="width:100%;font-size:15px; font-style:italic; line-height:26px;">
        	<span style="display:inline-block;font-weight:600; width:180px;">Received with thanks from </span><span style="border-bottom:1px dashed #ccc; width:470px; font-weight:600;display:inline-block;"> Mr./M.s/Mrs. {$payVoucherDetails['LeadName']}</span>
        </p>
        <p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:80px;">the sum of </span><span style="border-bottom:1px dashed #ccc; width:570px; display:inline-block;"> {$amtInWords}</span>
        </p>
		<p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:405pxpx;">Towards payment for the purchase of unit No.{$payVoucherDetails['UnitNo']} <span> at </span></span><span style="border-bottom:1px dashed #ccc; width:245px; display:inline-block;">{$payVoucherDetails['ProjectName']}</span>
        </p>
        <p style="width:100%;font-size:15px; font-style:italic;line-height:26px;">
        	<span style="display:inline-block; font-weight:600;width:195px;">by &nbsp;&nbsp;&nbsp; {$payVoucherDetails['PaymentMode']}
EOT;
        if($payVoucherDetails['PaymentMode'] != 'Cash') {
            $pdfHtml .= <<<EOT
        	<span style="margin-left:78px;">NO.</span></span><span style="border-bottom:1px dashed #ccc; width:455px; display:inline-block;"> {$payVoucherDetails['TransNo']} dated {$transDate}</span>
EOT;
        } else {
            $pdfHtml .= <<<EOT
            </span>
EOT;
        }
        $pdfHtml .= <<<EOT
        </p>
        <p style="width:100%;font-size:15px;line-height:26px;">
        	<span style="display:inline-block; font-size:25px;font-weight:600;width:300px;font-style:italic;">
			<b style="font-size: 15px;">for</b> &nbsp;&nbsp;&nbsp;
			<span>
			<span style="font-size:30px;"> Rs.</span>
		 	<span style="border-bottom:3px  solid #000;">{$viewRenderer->commonHelper()->sanitizeNumber($payVoucherDetails['ExcessAmount'], 2, TRUE)}</span>
			</span><br />
			<span style="font-size:11px; margin-left:45px; font-weight:normal; font-style:normal;">Cheque/DD-subject to realisation</span>
			</span>
			<span style="font-size:13px;font-weight:600; width:350px; display:inline-block; text-align:right; ">for &nbsp;&nbsp;&nbsp;
			<span>
			<span style="text-transform: uppercase">{$payVoucherDetails['CompanyName']}</span></span><br /><br />
			<span style="font-weight: normal;">Executive Director</span></span>
        </p>
    </div>
</div>
</body>
</html>

EOT;

        return $pdfHtml;
    }
    public function paymentVoucherPrintAction(){
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
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request
                try {

                    $voucherId = $this->params()->fromRoute('voucherId');
                    if(preg_match('/^[\d]+$/', $voucherId) == FALSE) {
                        throw new \Exception('Invalid voucher-id');
                    }
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_PaymentVoucher"))
                        ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_LEFT)
                       ->join(array("c" => "Crm_UnitBooking"),new expression("a.UnitId=c.UnitId and c.DeleteFlag=0"), array('LeadId'), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_Leads"), "c.LeadId=d.LeadId", array('LeadName'), $select::JOIN_LEFT)
                        ->join(array("e" => "Proj_ProjectMaster"), "b.ProjectId=e.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                        ->join(array("f"=>"WF_CompanyMaster"), "f.CompanyId=e.CompanyId", array('CompanyName', 'Address', 'Mobile', 'Email', 'LogoPath'), $select::JOIN_LEFT)
                        ->columns(array('*'))
                        ->where(array('a.PaymentVoucherId'=>$voucherId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $payVoucherDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($payVoucherDetails)) {
                        throw new \Exception('Invalid Payment Voucher!');
                    }

                    $pdfHtml = $this->generateVoucherPdf($payVoucherDetails,$viewRenderer);
                   // echo $pdfHtml;die;

                    require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
                    $dompdf = new DOMPDF();
                    $dompdf->load_html($pdfHtml);
                    $dompdf->set_paper("A4");
                    $dompdf->render();
                    $canvas = $dompdf->get_canvas();
                    $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $dompdf->stream("Payment_voucher.pdf");

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
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
    public function deletextraAction(){
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
                    $Extrabill = $this->bsf->isNullCheck($this->params()->fromPost('Extrabill'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_ExtraBillRegister"))
                        ->columns(array('Approve'))
                        ->where(array('ExtraBillRegisterId'=>$Extrabill));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $extrabill= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $extrabill=$extrabill['Approve'];

                    if($extrabill=='Y'){
                        $status = 'approved';
                    }
                    else {
                        $update = $sql->update();
                        $update->table('Crm_ExtraBillRegister')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                            ->where(array('ExtraBillRegisterId' => $extrabill));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $status = 'deleted';
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Delete','D','Extra',$Extrabill,0, 0, 'CRM', '',$userId, 0 ,0);


                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
}