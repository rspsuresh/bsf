<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 16-08-2016
 * Time: 2:48 PM
 */

namespace MMS\View\Helper;
use Zend\View\Helper\AbstractHelper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Zend\Db\Adapter\Adapter;
use Zend\Authentication\Result;

use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Session\Container;
use Zend\Form\Element;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper;

class MMSHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        $this->strCode="";
    }
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    public function Update_PO_Advance($argLogId, $argLogTime, $argCCId, $arg_PORegId ,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $bAns = false;
        $sCVType="";
        $iVendorId = 0;
        $dPODate = "";
        $sPONo = "";
        $dReqDate = "";
        $sReqNo = "";
        $iCCId = 0;
        $iFACCId = 0;
        $iCompId = 0;
        $iFYearId = 0;
        $dAdvAmount = 0;

        $iFCurrencyId = 0;
        $iTCurrencyId = 0;
        $dForexRate = 0;
        $iBillRefId = 0;
        $sCVType = "";
        $VendorName = "";
        $CompanyName = "";
        $sCVType = CommonHelper::GetVoucherType(301, $dbAdapter);

        $sql = new Sql($dbAdapter);

        $transSelect = $sql->select();
        $transSelect->from('WF_TermsMaster')
            ->columns(array('TermsId'));
        $transSelect->where("Title='Advance' And TermType='S'");

        $select = $sql->select();
        $select->from(array("a"=>"MMS_PORegister"))
            ->columns(array('PODate','PONo','CCPONo','CPONo','ReqNo','ReqDate','VendorId' => new Expression("a.VendorId")
            ,'CostCentreId','AdvAmount' => new Expression("b.Value"),'CurrencyId','VendorName' => new Expression("c.VendorName")))
            ->join(array("b"=>"MMS_POPaymentTerms"), "a.PORegisterId=b.PORegisterId", array(), $select::JOIN_LEFT)
            ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array(), $select::JOIN_INNER)
            ->where("a.PORegisterId=$arg_PORegId");
        $select->where->In('b.TermsId', $transSelect);
        $statement = $sql->getSqlStringForSqlObject($select);
        $poResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($poResult) > 0) {
            $iVendorId = $poResult[0]['VendorId'];
            $iCCId = $poResult[0]['CostCentreId'];
            if ($iCCId == 0) {
                $bAns = true;
                return $bAns;
            }

            $dPODate = date('Y-m-d', strtotime($poResult[0]['PODate']));
            $VendorName = $poResult[0]['VendorName'];
            if($sCVType =="  " || $sCVType == "GE"){
                $sPONo = $poResult[0]['PONo'];
            } else if ($sCVType == "CC") {
                $sPONo = $poResult[0]['CCPONo'];
            } else if ($sCVType == "CO") {
                $sPONo = $poResult[0]['CPONo'];
            }
            $dReqDate = date('Y-m-d', strtotime($poResult[0]['ReqDate']));
            $sReqNo = $poResult[0]['ReqNo'];
            $dAdvAmount = $poResult[0]['AdvAmount'];
            $iFCurrencyId = $poResult[0]['CurrencyId'];
        }

        $bHO = false;
        $select = $sql->select();
        if ($bHO == true){
            $select->from(array("a"=>"WF_OperationalCostCentre"))
                ->columns(array('CompanyId' => new Expression("b.CompanyId"),'FACostCentreId','CurrencyId' => new Expression("c.CurrencyId")
                ,'CompanyName' => new Expression("c.CompanyName")))
                ->join(array("b"=>"WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $select::JOIN_LEFT)
                ->join(array("c"=>"WF_CompanyMaster"), "b.CompanyId=c.CompanyId", array(), $select::JOIN_INNER);
        } else {
            $select->from(array("a"=>"WF_OperationalCostCentre"))
                ->columns(array('CompanyId' => new Expression("b.CompanyId"),'FACostCentreId','CurrencyId' => new Expression("c.CurrencyId")
                ,'CompanyName' => new Expression("c.CompanyName")))
                ->join(array("b"=>"WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $select::JOIN_LEFT)
                ->join(array("c"=>"WF_CompanyMaster"), "b.CompanyId=c.CompanyId", array(), $select::JOIN_INNER);
        }
        $select->where("a.CostCentreId=$iCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ccResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($ccResult) > 0) {
            $iFACCId = $ccResult[0]['FACostCentreId'];
            $iCompId = $ccResult[0]['CompanyId'];
            $iTCurrencyId = $ccResult[0]['CurrencyId'];
            $CompanyName = $ccResult[0]['CompanyName'];
        }

        if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId) {
            $dForexRate=1;
            /*$select->from(array("a"=>"WF_CurrencyConverter"))
                ->columns(array('*'));
            $select->where("(FCurrencyId=$iFCurrencyId  AND TCurrencyId=$iTCurrencyId)  " +
                "OR (TCurrencyId=$iTCurrencyId AND FCurrencyId=$iFCurrencyId)");
            $statement = $sql->getSqlStringForSqlObject($select);
            $currencyResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($currencyResult) > 0) {
                if ($iFCurrencyId == $currencyResult[0]['FCurrencyId']){
                    $dForexRate = $currencyResult[0]['TQty'] / $currencyResult[0]['FQty'];
                } else if ($iFCurrencyId == $currencyResult[0]['TCurrencyId']){
                    $dForexRate = $currencyResult[0]['FQty'] / $currencyResult[0]['TQty'];
                }
            }*/
        }
        if ($dForexRate == 0) { $dForexRate = 1; }

        $iAdvAccountId = CommonHelper::Get_Account_From_Type(9, $dbAdapter);
        if ($dAdvAmount > 0){
            $iFYearId = CommonHelper::GetFAYearId($iCompId, $dPODate, $dbAdapter);
            if ($iFYearId == 0) {
                //BsfGlobal.g_sErrorInfo = "Company Fiscal Year not found";
                echo '<script type="text/javascript">alert("Company Fiscal Year not found");</script>';
                return $bAns;
            }
            $iSubLedgerId = CommonHelper::GetSubLedgerId($iVendorId, 1, $dbAdapter);  // Getting Vendor Subledger Id
            if ($iSubLedgerId == 0){
                // BsfGlobal.g_sErrorInfo = "Company Fiscal Year Database not found";
                return $bAns;
            }
            if ($iAdvAccountId == 0) {
                //BsfGlobal.g_sErrorInfo = "Advance Account not found";
                echo '<script type="text/javascript">alert("Advance Account not found");</script>';
                return $bAns;
            }
            if (CommonHelper::Check_Bill_Exists_FA($arg_PORegId, "PO", $dbAdapter) == true) {
                return $bAns = true;
            }

            if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId) {
                $dForexAmount = $dAdvAmount * $dForexRate;

                $insert = $sql->insert();
                $insert->into('FA_BillRegister');
                $insert->Values(array('BillDate' => $dPODate
                , 'BillNo' => $sPONo
                , 'RefTypeId' => 7
                , 'RefType' => 'PO'
                , 'ReferenceId' => $arg_PORegId
                , 'AccountId' => $iAdvAccountId
                , 'SubLedgerId' => $iSubLedgerId
                , 'BillAmount' => $dForexAmount
                , 'ApproveAmount' => 0
                , 'CostCentreId' => $iFACCId
                , 'CompanyId' => $iCompId
                , 'TransType' => 'P'
                , 'FYearId' => $iFYearId
                , 'BillType' => 'A'
                , 'CurrencyId' => $iFCurrencyId
                , 'RefDate' => $dReqDate
                , 'RefNo' => $sReqNo
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iBillRefId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $insert = $sql->insert();
                $insert->into('FA_ForexBillRegister');
                $insert->Values(array('BillRegisterId' => $iBillRefId
                , 'BillAmount' => $dAdvAmount
                , 'AdvanceAmount' => 0
                , 'DebitAmount' => 0
                , 'BillForexRate' => $dForexRate
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            } else {
                $insert = $sql->insert();
                $insert->into('FA_BillRegister');
                $insert->Values(array('BillDate' => $dPODate
                , 'BillNo' => $sPONo
                , 'RefTypeId' => 7
                , 'RefType' => 'PO'
                , 'ReferenceId' => $arg_PORegId
                , 'AccountId' => $iAdvAccountId
                , 'SubLedgerId' => $iSubLedgerId
                , 'BillAmount' => $dAdvAmount
                , 'ApproveAmount' => 0
                , 'CostCentreId' => $iFACCId
                , 'CompanyId' => $iCompId
                , 'TransType' => 'P'
                , 'FYearId' => $iFYearId
                , 'BillType' => 'A'
                , 'RefDate' => $dReqDate
                , 'RefNo' => $sReqNo
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iBillRefId = $dbAdapter->getDriver()->getLastGeneratedValue();
            }

            $update = $sql->update();
            $update->table('MMS_PORegister')
                ->set(array('RefId' => $iBillRefId))
                ->where(array('PORegisterId' => $arg_PORegId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table( "MMS_POPaymentTerms" )
                ->set( array( 'AppAmount' => new Expression ("Value ")));
            $update->where("TermsId IN (SELECT TermsId From WF_TermsMaster Where Title='Advance' And TermType='S')");
            $statement = $sql->getSqlStringForSqlObject( $update );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            CommonHelper::InsertPaymentAdvice($argLogId, $argLogTime, $argCCId, $dbAdapter);
        }
        $bAns = true;
        return $bAns;
    }

    public function Remove_PO_Advance($arg_PORegId,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $dBillDate = date("Y/m/d");
        $iCompId = 0;
        $iFYearId = 0;

        $selAdv = $sql -> select();
        $selAdv -> from (array("a" => "FA_BillRegister"))
            ->columns(array('BillDate','CompanyId'))
            ->where("RefType='PO' and ReferenceId=$arg_PORegId");
        $statement = $sql->getSqlStringForSqlObject($selAdv);
        $poResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($poResult)>0) {
            $iCompId = $this->bsf->isNullCheck($poResult['CompanyId'], 'number');
            $dBillDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($poResult['BillDate'], 'string')));
        }
        else  {
            $bAns = true;
            return $bAns;
        }
        $iFYearId = CommonHelper::GetFAYearId($iCompId,$dBillDate,$dbAdapter);
        if($iFYearId == 0) {
            echo '<script type="text/javascript">alert("Company Fiscal Year not found");</script>';
            return $bAns;
        }
        $updPo = $sql -> update();
        $updPo->table('MMS_PORegister');
        $updPo->set(array(

            "RefId"=>0
        ));
        $updPo->where(array('PORegisterId'=>$arg_PORegId));
        $statement = $sql->getSqlStringForSqlObject($updPo);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $delForex = $sql -> delete();
        $delForex->from('FA_ForexBillRegister')
            ->where("BillRegisterId IN (Select BillRegisterId From FA_BillRegister Where RefType='PO' and ReferenceId=$arg_PORegId)");
        $statement = $sql->getSqlStringForSqlObject($delForex);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $delBill = $sql -> delete();
        $delBill->from('FA_BillRegister')
            ->where(array("RefType='PO' and ReferenceId=$arg_PORegId"));
        $statement = $sql->getSqlStringForSqlObject($delBill);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $bAns = true;
        return $bAns;

    }

    public function Update_DC_Advance($arg_DCRegId,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);

        $iVendorId = 0;
        $iAdvAccountId = 0;
        $iSubLedgerId = 0;
        $iPORegId = 0;
        $dDCDate = date("Y/m/d");
        $sDCNo = "";
        $dPODate = date("Y/m/d");
        $sPONo = "";
        $iCCId = 0;
        $iFACCId = 0;
        $iCompId = 0;
        $iFYearId = 0;
        $dAdvAmount = 0;
        $iFCurrencyId = 0;
        $iTCurrencyId = 0;
        $dForexRate = 0;
        $iBillRefId = 0;
        $sPOVType = "";
        $sDCVType = "";
        $bHO = false;
        $dForexAmount = 0;
        $sCVTypePO = CommonHelper::GetVoucherType(301, $dbAdapter);
        $sCVTypeMin = CommonHelper::GetVoucherType(303, $dbAdapter);

        $selDc = $sql -> select();
        $selDc->from(array("a" => "MMS_DCRegister"))
            ->columns(array('DCDate','DCNo','CCDCNo','CDCNo','VendorId','CostCentreId',
                'AdvAmount'=>new Expression("b.Amount"),'CurrencyId','PONo'=>new Expression("c.PONo"),
                'CPONo'=>new Expression("c.CPONo"),'CCPONo'=>new Expression("c.CCPONo"),
                'PODate'=>new Expression("c.PODate"),'PORegisterId'=>new Expression("c.PORegisterId")   ))
            ->join(array("b"=>"MMS_DCAdvance"),"a.DCRegisterId=b.DCRegisterId",array(),$selDc::JOIN_INNER)
            ->join(array("c"=>"MMS_PORegister"),"c.PORegisterId=b.PORegisterId",array(),$selDc::JOIN_INNER)
            ->where("b.Amount>0 and a.DCRegisterId=$arg_DCRegId");
        $statement = $sql->getSqlStringForSqlObject($selDc);
        $dcAdv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($dcAdv)>0){
            foreach($dcAdv as $adv){
                $iVendorId = $this->bsf->isNullCheck($adv['VendorId'],'number');
                $iCCId = $this->bsf->isNullCheck($adv['CostCentreId'],'number');
                $dDCDate=date('Y-m-d', strtotime($adv['DCDate']));
                if($iCCId == 0) {
                    $bAns = true;
                    return $bAns;
                }
                if($sCVTypeMin == " " || $sCVTypeMin == "GE") {
                    $sDCNo = $this->bsf->isNullCheck($adv['DCNo'],'string');
                }
                else if($sCVTypeMin == "CC") {
                    $sDCNo = $this->bsf->isNullCheck($adv['CCDCNo'],'string');
                }
                else if($sCVTypeMin == "CO") {
                    $sDCNo = $this->bsf->isNullCheck($adv['CDCNo'],'string');
                }

                if($sCVTypePO == " " || $sCVTypePO == "GE") {
                    $sPONo = $this->bsf->isNullCheck($adv['PONo'],'string');
                }
                else if($sCVTypePO == "CC") {
                    $sPONo = $this->bsf->isNullCheck($adv['CCPONo'],'string');
                }
                else if($sCVTypePO == "CO") {
                    $sPONo = $this->bsf->isNullCheck($adv['CPONo'],'string');
                }
                $iPORegId = $this->bsf->isNullCheck($adv['PORegisterId'],'number');
                $dPODate = date('Y-m-d', strtotime($adv['PODate']));
                $dAdvAmount = $this->bsf->isNullCheck($adv['AdvAmount'],'number');
                $iFCurrencyId = $this->bsf->isNullCheck($adv['CurrencyId'],'number');

                $bHO = CommonHelper::FindHOCC($iCCId,$dbAdapter);
                if($bHO == true) {
                    $selOth = $sql->select();
                    $selOth->from(array("a" => "WF_OperationalCostCentre"))
                        ->columns(array('CompanyId'=>new Expression("a.CompanyId"),'FACostCentreId','MINAccount'=>new Expression("c.MINAccount"),
                            'CurrencyId'=>new Expression("c.CurrencyId")  ))
                        ->join(array("b" => "WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $selOth::JOIN_INNER)
                        ->join(array("c" => "WF_CompanyMaster"), "b.CompanyId=c.CompanyId",array(),$selOth::JOIN_INNER)
                        ->where("a.CostCentreId=$iCCId");
                }
                else {

                    $selOth = $sql->select();
                    $selOth->from(array("a" => "WF_OperationalCostCentre"))
                        ->columns(array('CompanyId'=>new Expression("b.CompanyId"),'FACostCentreId','MINAccount'=>new Expression("c.MINAccount"),
                         'CurrencyId'=>new Expression("c.CurrencyId")  ))
                        ->join(array("b" => "WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $selOth::JOIN_INNER)
                        ->join(array("c" => "WF_CompanyMaster"), "b.CompanyId=c.CompanyId",array(),$selOth::JOIN_INNER)
                        ->where("a.CostCentreId=$iCCId");
                    }
                $statement = $sql->getSqlStringForSqlObject($selOth);
                $others = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if(count($others)>0) {
                    $iFACCId = $this->bsf->isNullCheck($others['FACostCentreId'],'number');
                    $iCompId = $this->bsf->isNullCheck($others['CompanyId'],'number');
                    $iTCurrencyId = $this->bsf->isNullCheck($others['CurrencyId'],'number');
                }
                $iAdvAccountId =CommonHelper::Get_Account_From_Type(9,$dbAdapter);
                if($dAdvAmount > 0) {
                    $iFYearId = CommonHelper::GetFAYearId($iCompId,$dDCDate,$dbAdapter);
                    if($iFYearId == 0) {
                        echo '<script type="text/javascript">alert("Company Fiscal Year not found");</script>';
                        return $bAns;
                    }
                    $iSubLedgerId = CommonHelper::GetSubLedgerId($iVendorId,1,$dbAdapter);
                    if($iSubLedgerId == 0) {
                        echo '<script type="text/javascript">alert("Vendor Sub Ledger not found");</script>';
                        return $bAns;
                    }
                    if($iAdvAccountId == 0) {
                        echo '<script type="text/javascript">alert("Vendor Advance account not found");</script>';
                        return $bAns;
                    }
                    if(CommonHelper::Check_Bill_Exists_FA($arg_DCRegId,"DC",$dbAdapter) == true) {
                        return $bAns=true;
                    }
                    if($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId) {
                        $dForexAmount = $dAdvAmount * $dForexRate;
                        $advInsert = $sql -> insert('FA_BillRegister');
                        $advInsert -> values (array(
                            "BillDate" => $dDCDate,
                            "BillNo" => $sDCNo,
                            "RefTypeId" => 11,
                            "RefType" => 'DC',
                            "ReferenceId" => $arg_DCRegId,
                            "AccountId" =>$iAdvAccountId,
                            "SubLedgerId"=>$iSubLedgerId,
                            "BillAmount" => $dForexAmount,
                            "ApproveAmount"=>0,
                            "CostCentreId"=>$iFACCId,
                            "CompanyId"=>$iCompId,
                            "TransType"=>'P',
                            "FYearId"=>$iFYearId,
                            "BillType"=>'A',
                            "CurrencyId"=>$iFCurrencyId,
                            "RefDate"=>$dPODate,
                            "RefNo"=>$sPONo,
                            "OCCId"=>$iCCId,
                            "Approve"=>'Y'
                        ));
                        $advStatement = $sql->getSqlStringForSqlObject($advInsert);
                        $dbAdapter->query($advStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iBillRefId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $advInsert1 = $sql -> insert('FA_ForexBillRegister');
                        $advInsert1 -> values(array(
                            "BillRegisterId" => $iBillRefId,
                            "BillAmount" => $dAdvAmount,
                            "AdvanceAmount" => 0,
                            "DebitAmount" => 0,
                            "BillForexRate" => $dForexRate

                        ));
                        $advStatement1 = $sql->getSqlStringForSqlObject($advInsert1);
                        $dbAdapter->query($advStatement1, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    else {
                        $advInsert = $sql -> insert('FA_BillRegister');
                        $advInsert -> values (array(
                            "BillDate" => $dDCDate,
                            "BillNo" => $sDCNo,
                            "RefTypeId" => 11,
                            "RefType" => 'DC',
                            "ReferenceId" => $arg_DCRegId,
                            "AccountId" =>$iAdvAccountId,
                            "SubLedgerId"=>$iSubLedgerId,
                            "BillAmount" => $dAdvAmount,
                            "ApproveAmount"=>0,
                            "CostCentreId"=>$iFACCId,
                            "CompanyId"=>$iCompId,
                            "TransType"=>'P',
                            "FYearId"=>$iFYearId,
                            "BillType"=>'A',
                            "CurrencyId"=>$iFCurrencyId,
                            "RefDate"=>$dPODate,
                            "RefNo"=>$sPONo,
                            "OCCId"=>$iCCId,
                            "Approve"=>'Y'
                        ));
                        $advStatement = $sql->getSqlStringForSqlObject($advInsert);
                        $dbAdapter->query($advStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iBillRefId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $updAdv = $sql -> update();
                    $updAdv->table('MMS_DCAdvance');
                    $updAdv->set(array(
                        "RefId"=>$iBillRefId
                    ));
                    $updAdv->where(array("DCRegisterId"=>$arg_DCRegId,"PORegisterId"=>$iPORegId));
                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($updAdv);
                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                }
                $bAns = true;
            }
        }
        else {
            $bAns = true;
        }
        return $bAns;
    }

    public function Remove_DC_Advance($arg_DCRegId,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $dBillDate = date("Y/m/d");
        $iCompId=0;
        $iFYearId=0;
        $selRem = $sql -> select();
        $selRem -> from(array("a" => "FA_BillRegister"))
            ->columns(array('BillDate','CompanyId'))
            ->where("a.RefType='DC' and a.ReferenceId=$arg_DCRegId and a.BillType='A'");
        $statement = $sql->getSqlStringForSqlObject($selRem);
        $dcRem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(count($dcRem)>0) {
            $iCompId = $this->bsf->isNullCheck($dcRem['CompanyId'],'number');
            $dBillDate = date('Y-m-d', strtotime($dcRem['BillDate']));
        }
        else {
            $bAns = true;
            return $bAns;
        }
        $iFYearId = CommonHelper::GetFAYearId($iCompId,$dBillDate,$dbAdapter);
        if($iFYearId == 0) {
            echo '<script type="text/javascript">alert("Company Fiscal Year not found");</script>';
            return $bAns;
        }
        $updAdv = $sql -> update();
        $updAdv->table('MMS_DCAdvance');
        $updAdv->set(array(
            "RefId"=>0
        ));
        $updAdv->where(array("DCRegisterId"=>$arg_DCRegId));
        $advUpdateStatement = $sql->getSqlStringForSqlObject($updAdv);
        $dbAdapter->query($advUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

        $del = $sql->delete();
        $del->from('FA_BillRegister')
            ->where("RefType='DC' and BillType='A' and ReferenceId=$arg_DCRegId ");
        $statement = $sql->getSqlStringForSqlObject($del);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        return $bAns;
    }

    public function Update_DC($arg_DCRegId,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $iCCId = 0;
        $dDCDate = date("Y/m/d");
        $dMaxDate = date("Y/m/d");
        $iPVAccountId = 0;
        $iVendorId = 0;
        $iVendorAccId = 0;
        $iVendorSLId = 0;
        $iFYearId = 0;
        $iCompanyId = 0;
        $iPVSLTypeId = 6;
        $iFACCId = 0;
        $sDBName = "";
        $sDCNo = "";
        $dDCAmount = 0;
        $iFCurrencyId = 0;
        $iTCurrencyId = 0;
        $dForexRate = 0;
        $sNarration = "";
        $sCVType = "";
        $sCVType = CommonHelper::GetVoucherType(303, $dbAdapter);

        $selDc = $sql -> select();
        $selDc->from(array('a' => 'MMS_DCRegister'))
            ->columns(array('DCDate','DCNo','CCDCNo','CDCNo','VendorId','CostCentreId','Amount','CurrencyId','Narration'))
            ->where("a.DCRegisterId=$arg_DCRegId");
        $dcStatement = $sql->getSqlStringForSqlObject($selDc);
        $dcRes = $dbAdapter->query($dcStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(count($dcRes)>0) {
            $iCCId = $this->bsf->isNullCheck($dcRes['CostCentreId'],'number');

            if($iCCId == 0) {
                $bAns = false;
                return $bAns;
            }

            $dDCDate = date('Y-m-d', strtotime($dcRes['DCDate']));
            //if($sCVType == "" )
        }
    }

    public function Update_PurchaseBill($argLogId, $argLogTime, $argCCId, $arg_iRegId ,$dbAdapter,$argRole)
    {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $iVendorId = 0;
        $iCCId = 0;
        $dPVDate = "";
        $iPVAccountId = 0;
        $iVendorSLId = 0;
        $iCompanyId = 0;
        $iVendorSLTypeId = 1;
        $iPVSLTypeId = 6;
        $iBillTypeId = 2;
        $iFACCId = 0;
        $sDBName = "";
        $iVendorAccId = 0;
        $iBranchId = 0;

        $dBillAmount = 0;
        $dDebit = 0;
        $iQualId = 0;
        $iMQualId = 0;
        $iQSLId = 0;
        $iQAccId = 0;
        $iBillRegId = 0;
        $sPvNo = "";
        $dGross = 0;
        $dAdvance = 0;
        $dTotal = 0;
        $iJVId = 0;
        $iRowNo = 0;
        $iFCurrencyId = 0;
        $iTCurrencyId = 0;
        $dForexRate = 0;
        $bDebitNote=false;
        $dQDiff = 0;
        $sPType = "";
        $sMType = "";
        $dFBillAmt = 0;
        $dFAdvance = 0;
        $dFDebit = 0;
        $dAmount = 0;
        $dQAmount = 0;
        $iPVGroupId = 0;
        $iDCAccountId = 0;
        $iVendorProAccId = 0;
        $dDCNAmount = 0;
        $dDCOAmount = 0;
        $dDCTotal = 0;
        $dOthers = 0;
        $iTermsTypeId = 0;
        $iTermsAccountId = 0;
        $iTermsSLId = 0;
        $iPVTypeId = 0;
        $sRefNo = "";
        $iQTypeId = 0;
        $iServiceTypeId = 0;
        $iKeyNo = 0;
        $sNarration = "";
        $dRefDate = "";
        $bMINAccount = false;
        $bIssueAccount = false;
        $iCrPeriod = 0;
        $bCST = 0;
        $bReg = 0;
        $iAdvAccId = 0;
        $sCVType = "";
        if($argRole == 'Bill-Approval') {
            $sCVType = CommonHelper::GetVoucherType(305, $dbAdapter);
        }
        else {
            $sCVType = CommonHelper::GetVoucherType(306, $dbAdapter);
        }
        $bCheck = false;
        $iVATInputType = 0;
        $iCCStateId = 0;
        $iVendorStateId = 0;
        $bHO = false;
        $bSEZ = false;

        $arg_bRefresh = false;

        $select = $sql->select();
        $select->from(array("a"=>"MMS_PVRegister"))
            ->columns(array('VendorId','CostCentreId','PVNo','CCPVNo','CPVNo','PVDate','BillNo' ,'BillDate','PurchaseTypeId','Amount'
            ,'BillAmount','Others','DebitAmount','AdvAmount','CurrencyId','Narration','BranchId','RefId','CreditPeriod','CSTPurchase','STINNo'))
            ->where("a.PVRegisterId=$arg_iRegId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $pvResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($pvResult) > 0) {
            $iVendorId = $pvResult[0]['VendorId'];
            $iBranchId = $pvResult[0]['BranchId'];
            $iCCId = $pvResult[0]['CostCentreId'];
            if ($iCCId == 0) {
                /*
                 * if (Update_PurchaseBill_MultiCC(argLogId, argLogTime, argCCId, arg_iRegId, conn, trans, arg_bRefresh) == true)
                {
                    bAns = true;
                    return bAns;
                }
                else
                {
                    bAns = false;
                    return bAns;
                }
                 */
                $bAns = true;
                return $bAns;
            }

            $dPVDate = date('Y-m-d', strtotime($pvResult[0]['PVDate']));
            if($sCVType =="  " || $sCVType == "GE"){
                $sPvNo = $pvResult[0]['PVNo'];
            } else if ($sCVType == "CC") {
                $sPvNo = $pvResult[0]['CCPVNo'];
            } else if ($sCVType == "CO") {
                $sPvNo = $pvResult[0]['CPVNo'];
            }
            $dRefDate = $pvResult[0]['BillDate'];
            $sRefNo = $pvResult[0]['BillNo'];
            $iPVTypeId = $pvResult[0]['PurchaseTypeId'];
            $dGross = $pvResult[0]['Amount'];
            $dBillAmount = $pvResult[0]['BillAmount'];
            $dOthers = $pvResult[0]['Others'];
            $dDebit = $pvResult[0]['DebitAmount'];
            $dAdvance = $pvResult[0]['AdvAmount'];
            $dFBillAmt = $pvResult[0]['BillAmount'];

            $dFDebit = $pvResult[0]['DebitAmount'];
            $dFAdvance = $pvResult[0]['AdvAmount'];
            $iFCurrencyId = $pvResult[0]['CurrencyId'];
            $sNarration = $pvResult[0]['Narration'];
            $iKeyNo = $pvResult[0]['RefId'];

            $iCrPeriod = $pvResult[0]['CreditPeriod'];
            $bCST = $pvResult[0]['CSTPurchase'];
            $bReg=1;
            if($pvResult[0]['STINNo']=="") {
                $bReg = 0;
            }
        }

        if ($dDebit != 0){
            $bDebitNote = true;
        } else {
            $bDebitNote = false;
        }

        $iVendorSLId = CommonHelper::GetSubLedgerId($iVendorId, 1, $dbAdapter);
        if ($iVendorSLId == 0){
            echo '<script type="text/javascript">alert("Vendor Sub ledger not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Vendor Sub ledger not found";
            return $bAns;
        }
        $dTotal = $dBillAmount + $dDebit;

        $iCMStateId = 0;

        $select = $sql->select();
        $select->from(array("a"=>"WF_OperationalCostCentre"))
            ->columns(array('CompanyId','FACostCentreId','SEZProject','MINAccount' => new Expression("c.MINAccount"),'IssueAccount' => new Expression("c.IssueAccount")
            ,'CurrencyId' => new Expression("c.CurrencyId"),'StateId' => new Expression("b.StateId"),'HO' => new Expression("b.HO"),'CCStateId' => new Expression("b.StateId")
            ,'CompanyName' => new Expression("c.CompanyName")))
            ->join(array("b"=>"WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $select::JOIN_LEFT)
            ->join(array("c"=>"WF_CompanyMaster"), "a.CompanyId=c.CompanyId", array(), $select::JOIN_INNER);
        $select->where("a.CostCentreId=$iCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $compResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($compResult) > 0) {
            $iCompanyId = $compResult[0]['CompanyId'];
            $iFACCId = $compResult[0]['FACostCentreId'];
            $iTCurrencyId = $compResult[0]['CurrencyId'];
            $iCMStateId = $compResult[0]['StateId'];
            $bMINAccount = $compResult[0]['MINAccount'];
            $IssueAccount = $compResult[0]['IssueAccount'];
            $iCCStateId = $compResult[0]['CCStateId'];
            $bHO = $compResult[0]['HO'];
            $bSEZ = $compResult[0]['SEZProject'];
            if ($bHO == 1) {$iCCStateId = $iCMStateId;}
        }
        $iFYearId = CommonHelper::GetFAYearId($iCompanyId, $dPVDate, $dbAdapter);

        if ($iFYearId == 0) {
            echo '<script type="text/javascript">alert("Fiscal Year not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Fiscal Year not found";
            return $bAns;
        }
        /*$sDBName = CommonHelper::GetDBName($iFYearId, $dbAdapter);
        if (BsfGlobal.CheckDBFound($sDBName) == false)
        {
            echo "Fiscal Year DB not found";// BsfGlobal.g_sErrorInfo = "Fiscal Year DB not found";
            return $bAns;
        }*/
        $iVendorAccId = CommonHelper::Get_Account_From_Type(4, $dbAdapter);
        if ($iVendorAccId == 0){
            echo '<script type="text/javascript">alert("Vendor Account not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Vendor Account not found";
            return $bAns;
        }
        if ($bMINAccount == 1) {
            $iVendorProAccId = CommonHelper::Get_Account_From_Type(41, $dbAdapter);
            if ($iVendorProAccId == 0) {
                echo '<script type="text/javascript">alert("Vendor (Provisional) Account not found");</script>';
                //BsfGlobal.g_sErrorInfo = "Vendor (Provisional) Account not found";
                return $bAns;
            }
        }

        if (CommonHelper::Check_Posting_Lock_FA($iCompanyId, $iFYearId, $dPVDate, $dbAdapter) == true){
            //BsfGlobal.g_sErrorInfo = "Posting period / Fiscal Year lock found, can't proceed";
            echo '<script type="text/javascript">alert("Posting period / Fiscal Year lock found, cannot proceed");</script>';
            return $bAns;
        }



        if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId) {
            $dForexRate = 1;
            $fCurrCode="";
            $tCurrCode="";
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'WF_CurrencyMaster'))
                ->columns(array('CurrencyShort'));
            $select->where("CurrencyId=$iFCurrencyId");
            $select_stmt = $sql->getSqlStringForSqlObject($select);
            $curSettingDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if(count($curSettingDet) > 0) {
                $fCurrCode=$curSettingDet[0]['CurrencyShort'];
                // $dForexRate = ($curSettingDet[0]['TQty']/$curSettingDet[0]['FQty']);
            }
            $select = $sql->select();
            $select->from(array('a' => 'WF_CurrencyMaster'))
                ->columns(array('CurrencyShort'));
            $select->where("CurrencyId=$iTCurrencyId");
            $select_stmt1 = $sql->getSqlStringForSqlObject($select);
            $curSettingDet1 = $dbAdapter->query($select_stmt1, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if(count($curSettingDet1) > 0) {
                $tCurrCode=$curSettingDet1[0]['CurrencyShort'];
                // $dForexRate = ($curSettingDet[0]['TQty']/$curSettingDet[0]['FQty']);
            }
            $dForexRate =CommonHelper::calculateCurrency($tCurrCode,$fCurrCode,1);

        }
        if ($dForexRate == 0) {$dForexRate = 1;}
        if ($arg_bRefresh == false){
            if (CommonHelper::Check_Bill_Exists_FA($arg_iRegId, "PV", $dbAdapter) == true) {
                return $bAns = true;
            }

            $insert = $sql->insert();
            $insert->into('FA_BillRegister');
            if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId){
                $dBillAmount = $dBillAmount * $dForexRate;
                $dGross = $dGross * $dForexRate;
                $dAdvance = $dAdvance * $dForexRate;
                $dDebit = $dDebit * $dForexRate;

                $insert->Values(array('BillDate' => $dPVDate
                , 'BillNo' => $sPvNo
                , 'RefTypeId' => $iBillTypeId
                , 'RefType' => 'PV'
                , 'ReferenceId' => $arg_iRegId
                , 'AccountId' => $iVendorAccId
                , 'SubLedgerId' => $iVendorSLId
                , 'BillAmount' => $dBillAmount
                , 'CostCentreId' => $iFACCId
                , 'CompanyId' => $iCompanyId
                , 'TransType' => 'P'
                , 'FYearId' => $iFYearId
                , 'BillType' => 'B'
                , 'Advance' => $dAdvance
                , 'DebitAmount' => $dDebit
                , 'CurrencyId' => $iFCurrencyId
                , 'RefDate' => $dRefDate
                , 'RefNo' => $sRefNo
                , 'BranchId' => $iBranchId
                , 'CreditDays' => $iCrPeriod
                , 'IsCST' => $bCST
                , 'IsReg' => $bReg
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
            } else {
                $insert->Values(array('BillDate' => $dPVDate
                , 'BillNo' => $sPvNo
                , 'RefTypeId' => $iBillTypeId
                , 'RefType' => 'PV'
                , 'ReferenceId' => $arg_iRegId
                , 'AccountId' => $iVendorAccId
                , 'SubLedgerId' => $iVendorSLId
                , 'BillAmount' => $dBillAmount
                , 'CostCentreId' => $iFACCId
                , 'CompanyId' => $iCompanyId
                , 'TransType' => 'P'
                , 'FYearId' => $iFYearId
                , 'BillType' => 'B'
                , 'Advance' => $dAdvance
                , 'DebitAmount' => $dDebit
                , 'RefDate' => $dRefDate
                , 'RefNo' => $sRefNo
                , 'BranchId' => $iBranchId
                , 'CreditDays' => $iCrPeriod
                , 'IsCST' => $bCST
                , 'IsReg' => $bReg
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
            }
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iBillRegId = $dbAdapter->getDriver()->getLastGeneratedValue();
            if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId){
                $insert = $sql->insert();
                $insert->into('FA_ForexBillRegister');
                $insert->Values(array('BillRegisterId' => $iBillRegId
                , 'BillAmount' => $dFBillAmt
                , 'AdvanceAmount' => $dFAdvance
                , 'DebitAmount' => $dFDebit
                , 'BillForexRate' => $dForexRate
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            // Accounts Update for Resource - Debit Posting
            if ($dAdvance != 0){
                $select = $sql->select();
                $select->from(array("a"=>"FA_AdvAdjustment"))
                    ->columns(array( 'BillRegisterId' => new Expression("$iBillRegId")
                    , 'BillRegisterId' => new Expression("b.BillRegisterId"), 'Amount', 'TermsId' ))
                    ->join(array("b"=>"FA_BillRegister"), "a.PORegisterId=b.BillRegisterId", array(), $select::JOIN_INNER)
                    ->where("b.RefType='PO' AND a.BillRegisterId=$arg_iRegId");
                $insert = $sql->insert();
                $insert->into( 'FA_BillRefDet' );
                $insert->columns(array('BillRegisterId', 'ReferenceId', 'RefAmount','TermsId'));
                $insert->Values( $select );
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                $iAdvAccId = CommonHelper::Get_Account_From_Type(9, $dbAdapter);
                if ($iAdvAccId == 0)
                {
                    //BsfGlobal.g_sErrorInfo = "Advance Amount found,but Advance Account not found, can't proceed";
                    echo '<script type="text/javascript">alert("Advance Amount found,but Advance Account not found, cannot proceed");</script>';
                    return $bAns;
                }

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iAdvAccId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAdvance
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'C'
                , 'RefType' => 'PV'
                , 'AccountId' => $iAdvAccId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAdvance
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        } else {
            $iBillRegId = $iKeyNo;
            // Delete Code Added By Bala Due to Account Id 0 Or Amount <0 for all purchase entries ....

            $subQuery = $sql->select();
            $subQuery->from("FA_BillRegister")
                ->columns(array('BillRegisterId'));
            $subQuery->where("RefType='PV'");

            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where->expression("RefType='PV' AND RefId NOT IN ?", array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //DELETE FROM [{0}].dbo.EntryTrans WHERE RefType='PV' AND (AccountId=0 Or Amount<=0)
            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where("RefType='PV' AND (AccountId=0 Or Amount<=0)");
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            // DELETE FROM [{0}].dbo.EntryTrans WHERE RefType='PV' AND AccountId<>0 AND " +
            //"AccountId NOT IN (SELECT AccountId FROM [{0}].dbo.AccountList WHERE LastLevel='Y')
            $subQuery = $sql->select();
            $subQuery->from("FA_AccountList")
                ->columns(array('AccountId'));
            $subQuery->where("LastLevel='Y'");

            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where->expression("RefType='PV' AND AccountId<>0 AND AccountId NOT IN ?", array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //DELETE FROM [{0}].dbo.EntryTrans WHERE RefType='PV' AND RelatedAccountId<>0 AND " +
            //"RelatedAccountId NOT IN (SELECT AccountId FROM [{0}].dbo.AccountList WHERE LastLevel='Y')
            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where->expression("RefType='PV' AND RelatedAccountId<>0 AND RelatedAccountId NOT IN ?", array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            //DELETE FROM [{0}].dbo.EntryTrans WHERE RefId={1} AND RefType='PV'", sDBName, iKeyNo
            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where("RefId=$iKeyNo AND RefType='PV'");
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //DELETE FROM [{0}].dbo.EntryTrans WHERE RefType='DN' AND RefId In
            //(Select JournalEntryId From  [{0}].dbo.JournalEntryMaster where RefBillId={1} and JournalType='D'
            $subQuery = $sql->select();
            $subQuery->from("FA_JournalEntryMaster")
                ->columns(array('JournalEntryId'));
            $subQuery->where("RefBillId=$iKeyNo and JournalType='D'");

            $delete = $sql->delete();
            $delete->from('FA_EntryTrans');
            $delete->where->expression("RefType='DN' AND RefId IN ?", array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //DELETE FROM [{0}].dbo.JournalEntryTrans WHERE JournalEntryId In
            //(Select JournalEntryId From [{0}].dbo.JournalEntryMaster where RefBillId={1} and JournalType='D'
            $delete = $sql->delete();
            $delete->from('FA_JournalEntryTrans');
            $delete->where->expression("JournalEntryId IN ?", array($subQuery));
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            //DELETE FROM [{0}].dbo.JournalEntryMaster where RefBillId={1} and JournalType='D'
            $delete = $sql->delete();
            $delete->from('FA_JournalEntryMaster');
            $delete->where("RefBillId=$iKeyNo and JournalType='D'");
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $iAdvAccId = CommonHelper::Get_Account_From_Type(9, $dbAdapter);
            if ($iAdvAccId == 0) {
                //BsfGlobal.g_sErrorInfo = "Advance Amount found,but Advance Account not found, can't proceed";
                echo '<script type="text/javascript">alert("Advance Amount found,but Advance Account not found, cannot proceed");</script>';
                return $bAns;
            }

            if ($dAdvance != 0) {
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iAdvAccId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAdvance
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'C'
                , 'RefType' => 'PV'
                , 'AccountId' => $iAdvAccId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAdvance
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        if ($iBranchId == 0){
            $iVendorStateId = CommonHelper::Get_Vendor_State($iVendorId, $dbAdapter);
        } else {
            $iVendorStateId = CommonHelper::Get_Vendor_Branch_State($iBranchId, $iVendorId, $dbAdapter);
        }
        //$iVATInputType = CommonHelper::Get_VAT_Input_Type($iCCStateId, $dbAdapter);

        $iResLedId = 0;
        $iSLTypeId = 0;
        $dGTotal = 0;
        $dQTotal = 0;
        $dDNAmount = 0;
        $dIPDAmount = 0;
        $dRoundOff = 0;

        /*
         * SELECT A.PVGroupId,A.ResourceId,A.BillQty,A.Rate,A.QRate,Amount=ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(FFactor,0) ELSE A.GrossRate END,3)," +
         "QAmount=ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END,3),A.PurchaseTypeId," +
         "IPDAmount=ISNULL((SELECT SUM(CASE WHEN B.AddLessFlag='+' THEN B.Amount ELSE -B.Amount END) FROM [{0}].dbo.IPDCompQual B WHERE A.PVRegisterId=B.RegisterId AND A.PVGroupId=B.IPDTransId " +
         "AND B.Type='PV' AND B.QualifierId IN (SELECT QualifierId FROM [{2}].dbo.Qualifier_Temp WHERE QualType='M' AND QualTypeId IN (5,6,7,8,9,14,15,16,17))),0), " +
         "RoundOff=ISNULL((SELECT SUM(CASE WHEN B.AddLessFlag='+' THEN B.Amount ELSE -B.Amount END) FROM [{0}].dbo.IPDCompQual B WHERE A.PVRegisterId=B.RegisterId AND A.PVGroupId=B.IPDTransId " +
         "AND B.Type='PV' AND B.QualifierId IN (SELECT QualifierId FROM [{2}].dbo.Qualifier_Temp WHERE QualType='M' AND QualTypeId=13)),0) " +
         "FROM [{0}].dbo.PVGroupTrans A WHERE A.PVRegisterId={1} ",
         BsfGlobal.g_sMMSDBName,
         arg_iRegId,
         BsfGlobal.g_sRateAnalDBName
         */
        if(@$argRole == 'Bill-Approval') {
            $select = $sql->select();
            $select->from(array("A" => "MMS_PVGroupTrans"))
                ->columns(array('PVGroupId', 'ResourceId', 'BillQty', 'Rate', 'QRate', 'PurchaseTypeId'
                , 'Amount' => new Expression("ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(FFactor,0) ELSE A.GrossRate END,3)")
                , 'QAmount' => new Expression("ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END,3)")
                , 'IPDAmount' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_MCQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                    AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId  WHERE A.QualType='M' AND B.QualifierTypeId IN (5,6,7,8,9,14,15,16,17))),0)")
                , 'RoundOff' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_MCQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                    AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId  WHERE A.QualType='M' AND B.QualifierTypeId=13)),0)")
                ))
                ->where("A.PVRegisterId=$arg_iRegId");
        }
        else {
            $select = $sql->select();
            $select->from(array("A" => "MMS_PVGroupTrans"))
                ->columns(array('PVGroupId', 'ResourceId', 'BillQty', 'Rate', 'QRate', 'PurchaseTypeId'
                , 'Amount' => new Expression("ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(FFactor,0) ELSE A.GrossRate END,3)")
                , 'QAmount' => new Expression("ROUND(A.BillQty*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END,3)")
                , 'IPDAmount' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_PVQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                    AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId  WHERE QualType='M' AND B.QualifierTypeId IN (5,6,7,8,9,14,15,16,17))),0)")
                , 'RoundOff' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_PVQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                    AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId  WHERE A.QualType='M' AND B.QualifierTypeId=13)),0)")
                ))
                ->where("A.PVRegisterId=$arg_iRegId");
        }
        $statement = $sql->getSqlStringForSqlObject($select);
        $pvtransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($pvtransResult as &$pvtransResults) {
            $iRowNo = $iRowNo + 1;
            $iPVGroupId = $pvtransResults["PVGroupId"];
            $iPVAccountId = $pvtransResults["PurchaseTypeId"];
            if ($iPVTypeId == 2) {
                $iPVSLTypeId = 5;
                $iResLedId = CommonHelper::Get_Common_SubLedgerId(5, $dbAdapter);
            } else {
                $iPVSLTypeId = 6;
                $iResLedId = CommonHelper::GetSubLedgerId($pvtransResults["ResourceId"], 6, $dbAdapter);
            }
            $dIPDAmount = 0;

            $dRoundOff = $pvtransResults["RoundOff"] * $dForexRate;
            $dAmount = $pvtransResults["Amount"] * $dForexRate;
            $dAmount += $dIPDAmount; //Clarify with Preethi needed...
            $dQAmount = $pvtransResults["QAmount"] * $dForexRate;
            $dQAmount -= $dRoundOff;
            $dQTotal += $dQAmount;
            $dGTotal += $dAmount;

            // Min Accounting Reversal
            if ($iVendorProAccId != 0){
                /*
                 * SELECT SUM(DCT.DCQty* DCT.Rate) DCAmount ,SUM(PVT.ActualQty*DCT.Rate ) BillAmount,DCT.PurchaseTypeId " +
                     "FROM [{0}]..PVTrans PVT INNER JOIN [{0}]..DCTrans DCT ON PVT.DCTransId=DCT.DCTransId " +
                     "WHERE PVT.DCPurchaseTypeId<>0 AND PVT.PVGroupId={1} GROUP BY DCT.PurchaseTypeId
                 */
                $select = $sql->select();
                $select->from(array("PVT"=>"MMS_PVTrans"))
                    ->columns(array('DCAmount'=> new Expression("SUM(DCT.DCQty* DCT.Rate)"),'BillAmount'=> new Expression("SUM(PVT.ActualQty*DCT.Rate )")
                    ,'PurchaseTypeId'=> new Expression("DCT.PurchaseTypeId")))
                    ->join(array("DCT"=>"MMS_DCTrans"), "PVT.DCTransId=DCT.DCTransId", array(), $select::JOIN_INNER)
                    ->where("PVT.DCPurchaseTypeId<>0 AND PVT.PVGroupId=$iPVGroupId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $pvTransdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $dDCTotal = 0;
                foreach($pvTransdetResult as &$pvTransdetResults) {
                    $iDCAccountId = $pvTransdetResults["PurchaseTypeId"];
                    $dDCOAmount = $pvTransdetResults["DCAmount"];
                    $dDCNAmount = $pvTransdetResults["BillAmount"];
                    if ($iFCurrencyId != 0 && $iFCurrencyId != $iTCurrencyId) {
                        $dDCNAmount = $dDCNAmount * $dForexRate;
                        $dDCOAmount = $dDCOAmount * $dForexRate;
                        if ($dDCOAmount >= $dDCNAmount) { $dDCOAmount = $dDCNAmount;}
                        $dDCTotal += $dDCOAmount;
                    } else {
                        if ($dDCOAmount >= $dDCNAmount) { $dDCOAmount = $dDCNAmount; }
                        $dDCTotal += $dDCOAmount;
                    }
                    /*
                     *  sSql = String.Format("INSERT INTO [{0}].dbo.EntryTrans(VoucherDate, VoucherNo, RefId,TransType,RefType,AccountId,
                     * RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve) " +
                                         "VALUES ('{1:dd-MMM-yyyy}','{2}' ,{3},'C','PV',{4},{5},{6},{7},{8},{9},{10},{11},{12},'{13}', 'Y')", sDBName,
                    dPVDate, sPvNo, iBillRegId, iPVAccountId, iVendorProAccId, iPVSLTypeId, iResLedId, 1, iVendorSLId, iFACCId, dDCOAmount, iCompanyId, BsfGlobal.Insert_SingleQuot(sNarration));
                     */
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $dPVDate
                    , 'VoucherNo' => $sPvNo
                    , 'RefId' => $iBillRegId
                    , 'TransType' => 'C'
                    , 'RefType' => 'PV'
                    , 'AccountId' => $iPVAccountId
                    , 'RelatedAccountId' => $iVendorProAccId
                    , 'SubLedgerTypeId' => $iPVSLTypeId
                    , 'SubLedgerId' => $iResLedId
                    , 'RelatedSLTypeId' => 1
                    , 'RelatedSLId' => $iVendorSLId
                    , 'CostCentreId' => $iFACCId
                    , 'Amount' => $dDCOAmount
                    , 'CompanyId' => $iCompanyId
                    , 'Remarks' => $sNarration
                    , 'Approve' => 'Y'
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }

                if ($dDCTotal != 0){
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $dPVDate
                    , 'VoucherNo' => $sPvNo
                    , 'RefId' => $iBillRegId
                    , 'TransType' => 'D'
                    , 'RefType' => 'PV'
                    , 'AccountId' => $iVendorProAccId
                    , 'RelatedAccountId' => $iPVAccountId
                    , 'SubLedgerTypeId' => $iVendorSLTypeId
                    , 'SubLedgerId' => $iVendorSLId
                    , 'RelatedSLTypeId' => 6
                    , 'RelatedSLId' => $iResLedId
                    , 'CostCentreId' => $iFACCId
                    , 'Amount' => $dDCTotal
                    , 'CompanyId' => $iCompanyId
                    , 'Remarks' => $sNarration
                    , 'Approve' => 'Y'
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }

            if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5) && $bSEZ==false){
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iPVAccountId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iPVSLTypeId
                , 'SubLedgerId' => $iResLedId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            } else {
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iPVAccountId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iPVSLTypeId
                , 'SubLedgerId' => $iResLedId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dQAmount
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPvNo
                , 'VoucherDate' => $dPVDate
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
        // Accounts Update for Vendor - Credit Posting
        if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5) && $bSEZ == false){
            if ($iRowNo > 1){
                if ($iPVSLTypeId == 2){
                    $iResLedId = CommonHelper::Get_Common_SubLedgerId(5, $dbAdapter);
                } else {
                    $iResLedId = CommonHelper::Get_Common_SubLedgerId(6, $dbAdapter);
                }
            }

            $insert = $sql->insert();
            $insert->into('FA_EntryTrans');
            $insert->Values(array('RefId' => $iBillRegId
            , 'TransType' => 'C'
            , 'RefType' => 'PV'
            , 'AccountId' => $iVendorAccId
            , 'RelatedAccountId' => $iPVAccountId
            , 'SubLedgerTypeId' => $iVendorSLTypeId
            , 'SubLedgerId' => $iVendorSLId
            , 'CostCentreId' => $iFACCId
            , 'Amount' => $dGTotal
            , 'CompanyId' => $iCompanyId
            , 'VoucherDate' => $dPVDate
            , 'VoucherNo' => $sPvNo
            , 'BranchId' => $iBranchId
            , 'Remarks' => $sNarration
            , 'RelatedSLTypeId' => $iPVSLTypeId
            , 'RelatedSLId' => $iResLedId
            , 'Approve' => 'Y'
            ));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        } else {
            if ($iRowNo > 1){
                if ($iPVSLTypeId == 2){
                    $iResLedId = CommonHelper::Get_Common_SubLedgerId(5, $dbAdapter);
                } else {
                    $iResLedId = CommonHelper::Get_Common_SubLedgerId(6, $dbAdapter);
                }
            }

            $insert = $sql->insert();
            $insert->into('FA_EntryTrans');
            $insert->Values(array('RefId' => $iBillRegId
            , 'TransType' => 'C'
            , 'RefType' => 'PV'
            , 'AccountId' => $iVendorAccId
            , 'RelatedAccountId' => $iPVAccountId
            , 'SubLedgerTypeId' => $iVendorSLTypeId
            , 'SubLedgerId' => $iVendorSLId
            , 'CostCentreId' => $iFACCId
            , 'Amount' => $dQTotal
            , 'CompanyId' => $iCompanyId
            , 'VoucherDate' => $dPVDate
            , 'VoucherNo' => $sPvNo
            , 'BranchId' => $iBranchId
            , 'Remarks' => $sNarration
            , 'RelatedSLTypeId' => $iPVSLTypeId
            , 'RelatedSLId' => $iResLedId
            , 'Approve' => 'Y'
            ));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        #region Debit Note

        $dRate = 0;
        if ($bDebitNote == true) {
            $select = $sql->select();
            $select->from(array("a"=>"MMS_PVGroupTrans"))
                ->columns(array('DNAmount' => new Expression("Round(SUM((BillQty-ActualQty)*CASE WHEN (FFactor>0 AND TFactor>0) THEN ISNULL((GrossRate*TFactor),0)/NULLIF(FFactor,0) ELSE GrossRate END),3)")
                ,'DNQAmount' => new Expression("Round(SUM((BillQty-ActualQty)*CASE WHEN (FFactor>0 And TFactor>0) THEN ISNULL((QRate*TFactor),0)/NULLIF(FFactor,0) ELSE QRate END),3)")
                ));
            $select->where("a.PVRegisterId=$arg_iRegId AND BillQty-ActualQty<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $pvTransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($pvTransResult) > 0) {
                $dDNAmount = $pvTransResult[0]['DNQAmount'] * $dForexRate;
            }
            /*
             *  sSql = String.Format("SELECT A.PVRegisterId,A.PVGroupId,ResourceId,A.PurchaseTypeId,DNAmount=Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 AND A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.GrossRate END),3)," +
                    "DNQAmount=Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END),3), " +
                    "IPDAmount=ISNULL((SELECT SUM(CASE WHEN B.AddLessFlag='+' THEN B.Amount ELSE -B.Amount END) FROM [{0}].dbo.IPDCompQual B WHERE A.PVRegisterId=B.RegisterId AND A.PVGroupId=B.IPDTransId AND B.Type='PV' AND A.PurchaseTypeId=B.AccountId " +
                    "AND B.QualifierId NOT IN (SELECT QualifierId FROM [{2}].dbo.Qualifier_Temp WHERE QualType='M' AND QualTypeId=13)),0), " +
                    "RoundOff=ISNULL((SELECT SUM(CASE WHEN B.AddLessFlag='+' THEN B.Amount ELSE -B.Amount END) FROM [{0}].dbo.IPDCompQual B WHERE A.PVRegisterId=B.RegisterId AND A.PVGroupId=B.IPDTransId " +
                    "AND B.Type='PV' AND B.QualifierId IN (SELECT QualifierId FROM [{2}].dbo.Qualifier_Temp WHERE QualType='M' AND QualTypeId=13)),0), " +
                    "Others=0 " +
                    "FROM [{0}].dbo.PVGroupTrans A WHERE A.PVRegisterId={1} AND BillQty-ActualQty<>0
            GROUP BY A.PVRegisterId,A.PVGroupId,A.ResourceId,A.PurchaseTypeId ",
                    BsfGlobal.g_sMMSDBName, arg_iRegId, BsfGlobal.g_sRateAnalDBName);
             */
            if($argRole == 'Bill-Approval') {
                $select = $sql->select();
                $select->from(array("A" => "MMS_PVGroupTrans"))
                    ->columns(array('PVRegisterId', 'PVGroupId', 'ResourceId', 'PurchaseTypeId'
                    , 'DNAmount' => new Expression("Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 AND A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.GrossRate END),3)")
                    , 'DNQAmount' => new Expression("Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END),3)")
                    , 'IPDAmount' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_MCQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId AND A.PurchaseTypeId=B.AccountId
                        AND B.QualifierId NOT IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId WHERE A.QualType='M' AND QualifierTypeId=13)),0)")
                    , 'RoundOff' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_MCQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                         AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId WHERE A.QualType='M' AND B.QualifierTypeId=13)),0)")
                    , 'Others' => new Expression("1-1")
                    ));

                $select->where("A.PVRegisterId=$arg_iRegId AND BillQty-ActualQty<>0 ");
                $select->group(new Expression('A.PVRegisterId,A.PVGroupId,A.ResourceId,A.PurchaseTypeId'));
            }
            else {
                $select = $sql->select();
                $select->from(array("A" => "MMS_PVGroupTrans"))
                    ->columns(array('PVRegisterId', 'PVGroupId', 'ResourceId', 'PurchaseTypeId'
                    , 'DNAmount' => new Expression("Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 AND A.TFactor>0) THEN ISNULL((A.GrossRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.GrossRate END),3)")
                    , 'DNQAmount' => new Expression("Round(SUM((A.BillQty-A.ActualQty)*CASE WHEN (A.FFactor>0 And A.TFactor>0) THEN ISNULL((A.QRate*A.TFactor),0)/NULLIF(A.FFactor,0) ELSE A.QRate END),3)")
                    , 'IPDAmount' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_PVQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId AND A.PurchaseTypeId=B.AccountId
                        AND B.QualifierId NOT IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId WHERE A.QualType='M' AND B.QualifierTypeId=13)),0)")
                    , 'RoundOff' => new Expression("ISNULL((SELECT SUM(CASE WHEN B.Sign='+' THEN B.ExpressionAmt ELSE -B.ExpressionAmt END) FROM MMS_MCQualTrans B WHERE A.PVRegisterId=B.PVRegisterId AND A.PVGroupId=B.PVGroupId
                         AND B.QualifierId IN (SELECT A.QualifierId FROM Proj_QualifierTrans A Inner Join Proj_QualifierMaster B On A.QualifierId=B.QualifierId WHERE A.QualType='M' AND B.QualifierTypeId=13)),0)")
                    , 'Others' => new Expression("1-1")
                    ));

                $select->where("A.PVRegisterId=$arg_iRegId AND BillQty-ActualQty<>0 ");
                $select->group(new Expression('A.PVRegisterId,A.PVGroupId,A.ResourceId,A.PurchaseTypeId'));
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $pvTransDetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($pvTransDetResult) > 0) {
                //$dDNAmount = $pvTransResult[0]['DNQAmount'] * $dForexRate;
                /*
                 * sSql = "INSERT INTO [" + sDBName + "].dbo.JournalEntryMaster(JVDate,JVNo,JournalId,JVBookNo,Debit,Credit,JournalType,AccountId,SubLedgerTypeId,SubLedgerId,CostCentreId,RefBillId,CompanyId,Narration,Approve) " +
                       "VALUES ('" + dPVDate.ToString("dd-MMM-yyyy") + "','" + sPvNo + "',4,'" + sPvNo + "'," + dDNAmount + "," + dDNAmount + ",'D',0,1,
                " + iVendorSLId + "," + iFACCId + "," + iBillRegId + "," + iCompanyId + ",'" + BsfGlobal.Insert_SingleQuot(sNarration) + "','Y') SELECT SCOPE_IDENTITY();";
                cmd = new SqlCommand(sSql, conn, trans);
                iJVId = Convert.ToInt32(cmd.ExecuteScalar());
                 */
                $insert = $sql->insert();
                $insert->into('FA_JournalEntryMaster');
                $insert->Values(array('JVDate' => $dPVDate
                , 'JVNo' => $sPvNo
                , 'JournalId' => 4
                , 'JVBookNo' => $sPvNo
                , 'Debit' => $dDNAmount
                , 'Credit' => $dDNAmount
                , 'JournalType' => 'D'
                , 'AccountId' => 0
                , 'SubLedgerTypeId' => 1
                , 'SubLedgerId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'RefBillId' => $iBillRegId
                , 'CompanyId' => $iCompanyId
                , 'Narration' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iJVId = $dbAdapter->getDriver()->getLastGeneratedValue();
            }
            $iRowNo = 0;

            foreach($pvTransDetResult as &$pvTransDetResults) {
                $iPVAccountId = $pvTransDetResults["PurchaseTypeId"];
                $iResLedId = CommonHelper::GetSubLedgerId($pvTransDetResults["ResourceId"], 6, $dbAdapter);
                if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5))
                {
                    $dDNAmount = $pvTransDetResults["DNAmount"] * $dForexRate;
                    $dOthers = $pvTransDetResults["Others"] * $dForexRate;
                    $dDNAmount += $dOthers;
                }
                else
                {
                    $dDNAmount = $pvTransDetResults["DNQAmount"] * $dForexRate;
                    $dOthers = $pvTransDetResults["Others"] * $dForexRate;
                    $dRoundOff = $pvTransDetResults["RoundOff"] * $dForexRate;
                    $dDNAmount -= dRoundOff;
                }

                $iRowNo = $iRowNo + 1;

                $insert = $sql->insert();
                $insert->into('FA_JournalEntryTrans');
                $insert->Values(array('JournalEntryId' => $iJVId
                , 'JVDate' => $dPVDate
                , 'SortRowId' => $iRowNo
                , 'AccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'TransType' => 'D'
                , 'Amount' => $dDNAmount
                , 'CompanyId' => $iCompanyId
                , 'Remarks' => $sNarration
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $iRowNo = $iRowNo + 1;

                $insert = $sql->insert();
                $insert->into('FA_JournalEntryTrans');
                $insert->Values(array('JournalEntryId' => $iJVId
                , 'JVDate' => $dPVDate
                , 'SortRowId' => $iRowNo
                , 'AccountId' => $iPVAccountId
                , 'SubLedgerTypeId' => $iPVSLTypeId
                , 'SubLedgerId' => $iResLedId
                , 'CostCentreId' => $iFACCId
                , 'TransType' => 'C'
                , 'Amount' => $dDNAmount
                , 'CompanyId' => $iCompanyId
                , 'Remarks' => $sNarration
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RefId' => $iJVId
                , 'TransType' => 'D'
                , 'RefType' => 'DN'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iPVAccountId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => 6
                , 'RelatedSLId' => $iResLedId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dDNAmount
                , 'CompanyId' => $iCompanyId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RefId' => $iJVId
                , 'TransType' => 'C'
                , 'RefType' => 'DN'
                , 'AccountId' => $iPVAccountId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iPVSLTypeId
                , 'SubLedgerId' => $iResLedId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dDNAmount
                , 'CompanyId' => $iCompanyId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'OCCId' => $iCCId
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            #region Qualifier Reverse - Debit Note
            if($argRole == 'Bill-Approval') {
                $select = $sql->select();
                $select->from(array("A" => "MMS_MCQualTrans"))
                    ->join(array("B" => "Proj_QualifierTrans"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                    ->columns(array("ResourceId", "QualTypeId" => new Expression("B.QualTypeId"), "QualifierId", "Sign", "NetPer", "ExpressionAmt", "ActAmt", "A.AccountId"))
                    ->where("A.Type='PV' AND A.PVRegisterId=$arg_iRegId  AND A.ExpressionAmt-A.ActAmt>0");
                if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5)) {
                    $select->where("B.QualTypeId IN (1,2,3,4,10,12,13)");
                } else {
                    $select->where("B.QualTypeId IN (13)");
                }
            }
            else {
                $select = $sql->select();
                $select->from(array("A" => "MMS_IPDCompQual"))
                    ->join(array("B" => "Proj_QualifierTrans"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                    ->columns(array("ResourceId", "QualTypeId" => new Expression("B.QualTypeId"), "QualifierId", "Sign", "NetPer", "ExpressionAmt", "ActAmt", "A.AccountId"))
                    ->where("A.Type='PV' AND A.PVRegisterId=$arg_iRegId  AND A.ExpressionAmt-A.ActAmt>0");
                if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5)) {
                    $select->where("B.QualTypeId IN (1,2,3,4,10,12,13)");
                } else {
                    $select->where("B.QualTypeId IN (13)");
                }
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualTransList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($qualTransList as &$qualTransLists) {
                $iQAccId = $qualTransLists['AccountId'];
                $iQualId = $qualTransLists['QualifierId'];
                $iQTypeId = $qualTransLists['QualTypeId'];
                $iQSLId = 0;
                $dQDiff = $qualTransLists['ExpressionAmt'] - $qualTransLists['ActAmt'];
                $dQDiff = dQDiff * dForexRate;
                $dRate = $qualTransLists['NetPer'];
                if ($iQTypeId == 1) {
                    $iQSLId = CommonHelper::Get_Common_SubLedgerId(iQTypeId, $dbAdapter);
                    $iSLTypeId = 8;
                } else if ($iQTypeId == 2 || $iQTypeId == 3 || $iQTypeId == 4 || $iQTypeId == 10 || $iQTypeId == 12) {
                    $iMQualId = $iQualId;//CommonHelper::GetQualId($iQualId, $dbAdapter);
                    $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iCMStateId, $dRate, $iServiceTypeId, $dbAdapter);
                    $iSLTypeId = 8;
                } else if ($iQTypeId == 13) {
                    $iSLTypeId = 9;
                    $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", $dbAdapter);
                    $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
                }
                if ($iQSLId == 0) {
                    //BsfGlobal.g_sErrorInfo = "Qualifier Sub ledger not found";
                    echo '<script type="text/javascript">alert("Qualifier Sub ledger not found");</script>';
                    return $bAns;
                }

                if ($iQAccId == 0) {
                    //BsfGlobal.g_sErrorInfo = "Qualifier Account not found";
                    echo '<script type="text/javascript">alert("Qualifier Account not found");</script>';
                    return $bAns;
                }
                if ($qualTransLists['AddLessFlag'] == "-") {
                    $sPType = "C";
                    $sMType = "D";
                } else {
                    $sPType = "D";
                    $sMType = "C";
                }

                if ($dQDiff != 0){
                    $iRowNo = $iRowNo + 1;
                    $insert = $sql->insert();
                    $insert->into('FA_JournalEntryTrans');
                    $insert->Values(array('JournalEntryId' => $iJVId
                    , 'JVDate' => $dPVDate
                    , 'SortRowId' => $iRowNo
                    , 'AccountId' => $iQAccId
                    , 'SubLedgerTypeId' => $iSLTypeId
                    , 'SubLedgerId' => $iQSLId
                    , 'CostCentreId' => $iFACCId
                    , 'TransType' => $sMType
                    , 'Amount' => $dQDiff
                    , 'CompanyId' => $iCompanyId
                    , 'Remarks' => $sNarration
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $iRowNo = $iRowNo + 1;
                    $insert = $sql->insert();
                    $insert->into('FA_JournalEntryTrans');
                    $insert->Values(array('JournalEntryId' => $iJVId
                    , 'JVDate' => $dPVDate
                    , 'SortRowId' => $iRowNo
                    , 'AccountId' => $iVendorAccId
                    , 'SubLedgerTypeId' => 1
                    , 'SubLedgerId' => $iVendorSLId
                    , 'CostCentreId' => $iFACCId
                    , 'TransType' => $sPType
                    , 'Amount' => $dQDiff
                    , 'CompanyId' => $iCompanyId
                    , 'Remarks' => $sNarration
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('RefId' => $iJVId
                    , 'VoucherDate' => $dPVDate
                    , 'VoucherNo' => $sPvNo
                    , 'TransType' => $sMType
                    , 'RefType' => 'DN'
                    , 'AccountId' => $iQAccId
                    , 'RelatedAccountId' => $iVendorAccId
                    , 'SubLedgerTypeId' => $iSLTypeId
                    , 'SubLedgerId' => $iQSLId
                    , 'RelatedSLTypeId' => 1
                    , 'RelatedSLId' => $iVendorSLId
                    , 'CostCentreId' => $iFACCId
                    , 'Amount' => $dQDiff
                    , 'CompanyId' => $iCompanyId
                    , 'BranchId' => $iBranchId
                    , 'Remarks' => $sNarration
                    , 'OCCId' => $iCCId
                    , 'Approve' => 'Y'
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $dPVDate
                    , 'VoucherNo' => $sPvNo
                    , 'RefId' => $iJVId
                    , 'TransType' => $sPType
                    , 'RefType' => 'DN'
                    , 'AccountId' => $iVendorAccId
                    , 'RelatedAccountId' => $iQAccId
                    , 'SubLedgerTypeId' => 1
                    , 'SubLedgerId' => $iVendorSLId
                    , 'RelatedSLTypeId' => $iSLTypeId
                    , 'RelatedSLId' => $iQSLId
                    , 'CostCentreId' => $iFACCId
                    , 'Amount' => $dQDiff
                    , 'CompanyId' => $iCompanyId
                    , 'BranchId' => $iBranchId
                    , 'Remarks' => $sNarration
                    , 'OCCId' => $iCCId
                    , 'Approve' => 'Y'
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
            #endregion
            /*
             *  sSql = "UPDATE [" + sDBName + "].dbo.JournalEntryMaster SET Debit=DAmount, Credit=CAmount FROM [" + sDBName + "].dbo.JournalEntryMaster A JOIN (" +
                   "SELECT JournalEntryId,DAmount=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),CAmount=SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END) " +
                   "FROM [" + sDBName + "].dbo.JournalEntryTrans WHERE JournalEntryId=" + iJVId + " GROUP BY JournalEntryId) B ON A.JournalEntryId=B.JournalEntryId";

             */
            $update = $sql->update();
            $update->table( "FA_JournalEntryMaster" )
                ->set( array('Debit' => new Expression ("DAmount")
                , 'Credit' => new Expression ("CAmount FROM FA_JournalEntryMaster A JOIN (SELECT JournalEntryId,DAmount=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END) ")
                , 'CAmount' => new Expression ("SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END)
                        FROM FA_JournalEntryTrans WHERE JournalEntryId=$iJVId GROUP BY JournalEntryId) B ON A.JournalEntryId=B.JournalEntryId ")
                ));
            $statement = $sql->getSqlStringForSqlObject( $update );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
        }
        #endregion

        #region Qualifiers
        /*
         * SELECT A.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,Amount=SUM(A.Amount),ActAmt=SUM(A.ActAmt),AccountId FROM (" +
                   "SELECT A.ResourceId, B.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,A.Amount,A.ActAmt,A.AccountId " +
                   "FROM [" + BsfGlobal.g_sMMSDBName + "].dbo.IPDCompQual A INNER JOIN [" + BsfGlobal.g_sRateAnalDBName + "].dbo.Qualifier_Temp B ON A.QualifierId=B.QualifierId " +
                   "WHERE A.Type='PV' AND A.RegisterId=" + arg_iRegId + " AND B.QualTypeId IN (1,2,3,4,10,12,13)) A " +
                   "GROUP BY A.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,A.AccountId
         */
        $select = $sql->select();
        if($argRole == 'Bill-Approval') {
            $select->from(array("A" => "MMS_MCQualTrans"))
                ->join(array("B" => "Proj_QualifierTrans"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                ->join(array("C" => "Proj_QualifierMaster"), "B.QualifierId=C.QualifierId", array(), $select::JOIN_INNER)
                ->columns(array("QualTypeId"=>new Expression("C.QualifierTypeId"), "QualifierId", "Sign", "NetPer", "Amount" => new Expression("SUM(A.ExpressionAmt)")
                , "ActAmt" => new Expression("SUM(A.ExpressionAmt)"), "AccountId"))
                ->where("A.PVRegisterId=$arg_iRegId");
            if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5) && $bSEZ == false) {
                $select->where("C.QualifierTypeId IN (1,2,3,4,10,12,13)");
            } else {
                $select->where("C.QualifierTypeId IN (13)");
            }
            $select->group(new Expression('C.QualifierTypeId,A.QualifierId,A.Sign,A.NetPer,A.AccountId'));
        }
        else {
            $select->from(array("A" => "MMS_PVQualTrans"))
                ->join(array("B" => "Proj_QualifierTrans"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                ->join(array("C" => "Proj_QualifierMaster"), "B.QualifierId=C.QualifierId", array(), $select::JOIN_INNER)
                ->columns(array("QualTypeId"=>new Expression("C.QualifierTypeId"), "QualifierId", "Sign", "NetPer", "Amount" => new Expression("SUM(A.ExpressionAmt)")
                , "ActAmt" => new Expression("SUM(A.ExpressionAmt)"), "AccountId"))
                ->where("A.PVRegisterId=$arg_iRegId");
            if ($iVendorStateId == $iCCStateId && $iVATInputType == 1 && ($iPVTypeId == 0 || $iPVTypeId == 5) && $bSEZ == false) {
                $select->where("C.QualifierTypeId IN (1,2,3,4,10,12,13)");
            } else {
                $select->where("C.QualifierTypeId IN (13)");
            }
            $select->group(new Expression('C.QualifierTypeId,A.QualifierId,A.Sign,A.NetPer,A.AccountId'));
        }
        $statement = $sql->getSqlStringForSqlObject($select);

        $qualTransList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($qualTransList as &$qualTransLists) {
            $iQualId = $qualTransLists['QualifierId'];
            $iQTypeId =  $qualTransLists['QualTypeId'];
            $iQSLId = 0;
            $iQAccId = $qualTransLists['AccountId'];
            $dRate = $qualTransLists['NetPer'];
            $dAmount = $qualTransLists['Amount'] * $dForexRate;
            if ($iQTypeId == 1) {
                $iQSLId = CommonHelper::Get_Common_SubLedgerId($iQTypeId, $dbAdapter);
                $iSLTypeId = 8;
            } else if ($iQTypeId == 2 || $iQTypeId == 3 || $iQTypeId == 4 || $iQTypeId == 10 || $iQTypeId == 12 || $iQTypeId == 19) {
                $iMQualId =$iQualId;// CommonHelper::GetQualId($iQualId, $dbAdapter);
                $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iCCStateId, $dRate, $iServiceTypeId, $dbAdapter);
                $iSLTypeId = 8;
            } else if ($iQTypeId == 13) {
                $iSLTypeId = 9;
                $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", $dbAdapter);
                $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
            }

            if ($iQSLId == 0) {
                //BsfGlobal.g_sErrorInfo = "Qualifier Sub ledger not found";
                echo '<script type="text/javascript">alert("Qualifier Sub ledger not found");</script>';
                return $bAns;
            } else if ($iQAccId == 0) {
                //BsfGlobal.g_sErrorInfo = "Qualifier Account not found";
                echo '<script type="text/javascript">alert("Qualifier Account not found");</script>';
                return $bAns;
            }

            if ($qualTransLists['AddLessFlag'] == "-") {
                $sPType = "C";
                $sMType = "D";
            } else {
                $sPType = "D";
                $sMType = "C";
            }

            if ($dAmount != 0) {
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'TransType' => $sPType
                , 'RefType' => 'PV'
                , 'AccountId' => $iQAccId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iSLTypeId
                , 'SubLedgerId' => $iQSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount
                , 'CompanyId' => $iCompanyId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RefId' => $iBillRegId
                , 'TransType' => $sMType
                , 'RefType' => 'PV'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iQAccId
                , 'SubLedgerTypeId' => 1
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => $iSLTypeId
                , 'RelatedSLId' => $iQSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount
                , 'CompanyId' => $iCompanyId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
        #endregion

        #region Terms
        $select = $sql->select();
        $select->from(array("A" => "MMS_PVPaymentTerms"))
            ->join(array("B" => "WF_TermsMaster"), "A.TermsId=B.TermsId", array(), $select::JOIN_INNER)
            ->columns(array("Value","AccountId","TermsTypeId"=>new Expression("B.TermsTypeId"),"TermsId"=>new Expression("B.TermsId")))
            ->where("A.Value<> 0 AND PVRegisterId=$arg_iRegId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $termTransList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($termTransList as &$termTransLists) {
            $iTermsTypeId = $termTransLists['TermsTypeId'];
            $iTermsAccountId = $termTransLists['AccountId'];
            if ($iTermsAccountId == 0) {
                //BsfGlobal.g_sErrorInfo = "Miscellaneous Account not found";
                echo '<script type="text/javascript">alert("Miscellaneous Account not found");</script>';
                return $bAns;
            }

            $iTermsSLId = CommonHelper::GetSubLedgerId($iTermsTypeId, 9, $dbAdapter);
            if ($iTermsSLId == 0)
            {
                //BsfGlobal.g_sErrorInfo = "Miscellaneous Sub ledger not found";
                echo '<script type="text/javascript">alert("Miscellaneous Sub ledger not found");</script>';
                return $bAns;
            }
            $dAmount = $termTransLists['Value'] * $dForexRate;

            if ($dAmount >= 0){
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'C'
                , 'RefType' => 'PV'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iTermsAccountId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount
                , 'CompanyId' => $iCompanyId
                , 'VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RelatedSLId' => $iTermsSLId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'RelatedSLTypeId' => 9
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iTermsAccountId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => 9
                , 'SubLedgerId' => $iTermsSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount
                , 'CompanyId' => $iCompanyId
                , 'VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RelatedSLId' => $iVendorSLId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'RelatedSLTypeId' => 1
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            } else {
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'D'
                , 'RefType' => 'PV'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iTermsAccountId
                , 'SubLedgerTypeId' => $iVendorSLTypeId
                , 'SubLedgerId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount //Math.Abs(dAmount)
                , 'CompanyId' => $iCompanyId
                , 'VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RelatedSLId' => $iTermsSLId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'RelatedSLTypeId' => 9
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillRegId
                , 'TransType' => 'C'
                , 'RefType' => 'PV'
                , 'AccountId' => $iTermsAccountId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => 9
                , 'SubLedgerId' => $iTermsSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dAmount //Math.Abs(dAmount)
                , 'CompanyId' => $iCompanyId
                , 'VoucherDate' => $dPVDate
                , 'VoucherNo' => $sPvNo
                , 'RelatedSLId' => $iVendorSLId
                , 'BranchId' => $iBranchId
                , 'Remarks' => $sNarration
                , 'RelatedSLTypeId' => 1
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
        #endregion

        #region Issue Note
        if ($bIssueAccount == true) {
            $exists = false;

            //select case when exists((select * from information_schema.tables where table_name = 'PVRateAdjustment')) then 1 else 0 end
            $select = $sql->select();
            $select->from(array('a' =>'information_schema.tables'))
                ->columns(array('*'));
            $select->where("a.table_name = 'MMS_PVRateAdjustment'");

            $selectFinal = $sql->select();
            $selectFinal->from("shemaaa")
                ->columns(array("Found"=>new Expression("case when exists((". $select->getSqlString()." )) then 1 else 0 end") ));
            $statement = preg_replace('/FROM "shemaaa"/','',$selectFinal->getSqlString(),1);
            $statement2 = preg_replace('/"information_schema.tables"/','information_schema.tables',$statement,1);
            $schemaResult = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($schemaResult) > 0) {
                //$exists = $schemaResult[0]['Found'];
                $exists = true;
            }

            if ($exists == true){
                $select = $sql->select();
                $select->from('MMS_PVRateAdjustment')
                    ->columns(array('PVRegisterId'))
                    ->where("Qty>0 AND PVRegisterId=$arg_iRegId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $INValidResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($INValidResult) > 0) {
                    $select = $sql->select();
                    $select->from('MMS_PVRateAdjustment')
                        ->columns(array('Amount'=>new expression("SUM(Amount)")))
                        ->where("PVRegisterId=$arg_iRegId And Type='D'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $INDebitResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    /*
                     * Select B.ResourceId,A.Amount From [" + BsfGlobal.g_sMMSDBName + "].dbo.PVRateAdjustment A " +
                         "Inner Join [" + BsfGlobal.g_sMMSDBName + "].dbo.PVTrans B On A.PVTransId=B.PVTransId  " +
                         "Where A.PVRegisterId=" + arg_iRegId + " And Type='D' ";
                     */
                    $select = $sql->select();
                    $select->from(array("B" =>"MMS_PVRateAdjustment"))
                        ->join(array("B" => "MMS_PVTrans"), "A.PVTransId=B.PVTransId", array(), $select::JOIN_INNER)
                        ->columns(array('Amount','ResourceId'=>new expression("B.ResourceId")))
                        ->where("A.PVRegisterId=$arg_iRegId And Type='D'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $INDDetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(count($INDebitResult) > 0) {
                        $dDNAmount = $INDebitResult[0]['Amount'] * $dForexRate;

                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryMaster');
                        $insert->Values(array('JVDate' => $dPVDate
                        , 'JVNo' => $sPvNo
                        , 'JournalId' => 4
                        , 'JVBookNo' => $sPvNo
                        , 'Debit' => $dDNAmount
                        , 'Credit' => $dDNAmount
                        , 'JournalType' => 'D'
                        , 'AccountId' => 0
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'CostCentreId' => $iFACCId
                        , 'RefBillId' => $iBillRegId
                        , 'CompanyId' => $iCompanyId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iJVId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $iRowNo = 0;
                    foreach($INDDetResult as &$INDDetResults) {
                        if ($iPVSLTypeId == 2){
                            //iResLedId = GetSubLedgerId(Convert.ToInt32($INDDetResults['ResourceId'], 5, conn, trans);
                            $iResLedId = CommonHelper::Get_Common_SubLedgerId(5, $dbAdapter);
                        } else {
                            $iResLedId = CommonHelper::GetSubLedgerId($INDDetResults['ResourceId'], 6, $dbAdapter);
                        }
                        $dDNAmount = $INDDetResults['Amount'] * $dForexRate;

                        $iRowNo = $iRowNo + 1;
                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryTrans');
                        $insert->Values(array('JournalEntryId' => $iJVId
                        , 'JVDate' => $dPVDate
                        , 'SortRowId' => $iRowNo
                        , 'AccountId' => $iVendorAccId
                        , 'SubLedgerTypeId' => $iVendorSLTypeId
                        , 'SubLedgerId' => $iVendorSLId
                        , 'CostCentreId' => $iFACCId
                        , 'TransType' => 'D'
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $iRowNo = $iRowNo + 1;
                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryTrans');
                        $insert->Values(array('JournalEntryId' => $iJVId
                        , 'JVDate' => $dPVDate
                        , 'SortRowId' => $iRowNo
                        , 'AccountId' => $iPVAccountId
                        , 'SubLedgerTypeId' => $iPVSLTypeId
                        , 'SubLedgerId' => $iResLedId
                        , 'CostCentreId' => $iFACCId
                        , 'TransType' => 'C'
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iJVId
                        , 'VoucherDate' => $dPVDate
                        , 'VoucherNo' => $sPvNo
                        , 'TransType' => 'D'
                        , 'RefType' => 'DN'
                        , 'AccountId' => $iVendorAccId
                        , 'RelatedAccountId' => $iPVAccountId
                        , 'SubLedgerTypeId' => $iVendorSLTypeId
                        , 'SubLedgerId' => $iVendorSLId
                        , 'RelatedSLTypeId' => 6
                        , 'RelatedSLId' => $iResLedId
                        , 'CostCentreId' => $iFACCId
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        , 'BranchId' => $iBranchId
                        , 'Remarks' => ''
                        , 'OCCId' => $iCCId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iJVId
                        , 'VoucherDate' => $dPVDate
                        , 'VoucherNo' => $sPvNo
                        , 'TransType' => 'C'
                        , 'RefType' => 'DN'
                        , 'AccountId' => $iPVAccountId
                        , 'RelatedAccountId' => $iVendorAccId
                        , 'SubLedgerTypeId' => $iPVSLTypeId
                        , 'SubLedgerId' => $iResLedId
                        , 'RelatedSLTypeId' => 1
                        , 'RelatedSLId' => $iVendorSLId
                        , 'CostCentreId' => $iFACCId
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        , 'BranchId' => $iBranchId
                        , 'Remarks' => ''
                        , 'OCCId' => $iCCId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    //Credit
                    $select = $sql->select();
                    $select->from('MMS_PVRateAdjustment')
                        ->columns(array('Amount'=>new expression("SUM(Amount)")))
                        ->where("Qty>0 AND PVRegisterId=$arg_iRegId And Type='C'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $INDebitResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("B" =>"MMS_PVRateAdjustment"))
                        ->join(array("B" => "MMS_PVTrans"), "A.PVTransId=B.PVTransId", array(), $select::JOIN_INNER)
                        ->columns(array('Amount','ResourceId'=>new expression("B.ResourceId")))
                        ->where("A.PVRegisterId=$arg_iRegId And Type='C'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $INCDetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(count($INDebitResult) > 0) {
                        $dDNAmount = $INDebitResult[0]['Amount'] * $dForexRate;

                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryMaster');
                        $insert->Values(array('JVDate' => $dPVDate
                        , 'JVNo' => $sPvNo
                        , 'JournalId' => 5
                        , 'JVBookNo' => $sPvNo
                        , 'Debit' => $dDNAmount
                        , 'Credit' => $dDNAmount
                        , 'JournalType' => 'C'
                        , 'AccountId' => 0
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'CostCentreId' => $iFACCId
                        , 'RefBillId' => $iBillRegId
                        , 'CompanyId' => $iCompanyId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iJVId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $iRowNo = 0;
                    foreach($INCDetResult as &$INCDetResults) {
                        if ($iPVSLTypeId == 2){
                            //iResLedId = GetSubLedgerId(Convert.ToInt32($INDDetResults['ResourceId'], 5, conn, trans);
                            $iResLedId = CommonHelper::Get_Common_SubLedgerId(5, $dbAdapter);
                        } else {
                            $iResLedId = CommonHelper::GetSubLedgerId($INCDetResults['ResourceId'], 6, $dbAdapter);
                        }
                        $dDNAmount = $INCDetResults['Amount'] * $dForexRate;

                        $iRowNo = $iRowNo + 1;
                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryTrans');
                        $insert->Values(array('JournalEntryId' => $iJVId
                        , 'JVDate' => $dPVDate
                        , 'SortRowId' => $iRowNo
                        , 'AccountId' => $iVendorAccId
                        , 'SubLedgerTypeId' => $iVendorSLTypeId
                        , 'SubLedgerId' => $iVendorSLId
                        , 'CostCentreId' => $iFACCId
                        , 'TransType' => 'C'
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $iRowNo = $iRowNo + 1;
                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryTrans');
                        $insert->Values(array('JournalEntryId' => $iJVId
                        , 'JVDate' => $dPVDate
                        , 'SortRowId' => $iRowNo
                        , 'AccountId' => $iPVAccountId
                        , 'SubLedgerTypeId' => $iPVSLTypeId
                        , 'SubLedgerId' => $iResLedId
                        , 'CostCentreId' => $iFACCId
                        , 'TransType' => 'D'
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iJVId
                        , 'VoucherDate' => $dPVDate
                        , 'VoucherNo' => $sPvNo
                        , 'TransType' => 'C'
                        , 'RefType' => 'DN'
                        , 'AccountId' => $iVendorAccId
                        , 'RelatedAccountId' => $iPVAccountId
                        , 'SubLedgerTypeId' => $iVendorSLTypeId
                        , 'SubLedgerId' => $iVendorSLId
                        , 'RelatedSLTypeId' => 6
                        , 'RelatedSLId' => $iResLedId
                        , 'CostCentreId' => $iFACCId
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        , 'BranchId' => $iBranchId
                        , 'Remarks' => ''
                        , 'OCCId' => $iCCId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iJVId
                        , 'VoucherDate' => $dPVDate
                        , 'VoucherNo' => $sPvNo
                        , 'TransType' => 'D'
                        , 'RefType' => 'DN'
                        , 'AccountId' => $iPVAccountId
                        , 'RelatedAccountId' => $iVendorAccId
                        , 'SubLedgerTypeId' => $iPVSLTypeId
                        , 'SubLedgerId' => $iResLedId
                        , 'RelatedSLTypeId' => 1
                        , 'RelatedSLId' => $iVendorSLId
                        , 'CostCentreId' => $iFACCId
                        , 'Amount' => $dDNAmount
                        , 'CompanyId' => $iCompanyId
                        , 'BranchId' => $iBranchId
                        , 'Remarks' => ''
                        , 'OCCId' => $iCCId
                        , 'Approve' => 'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        }
        #endregion

        $update = $sql->update();
        $update->table('MMS_PVRegister')
            ->set(array('RefId' => $iBillRegId ))
            ->where("PVRegisterId=$arg_iRegId");
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $update = $sql->update();
        $update->table("FA_EntryTrans")
            ->set(array('OCCId' => new Expression ("B.OCCId,RefDate=B.RefDate,RefNo=B.RefNo, RefAmount=B.BillAmount,Approve='Y' FROM FA_EntryTrans A JOIN (
                SELECT BillRegisterId,OCCId,RefDate,RefNo,RefType,BillAmount FROM FA_BillRegister WHERE BillRegisterId=$iBillRegId) B
                ON A.RefId=B.BillRegisterId AND A.RefType=B.RefType WHERE A.RefId=$iBillRegId")));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        if ($arg_bRefresh == false) {
            CommonHelper::InsertPaymentAdvice($argLogId, $argLogTime, $argCCId, $dbAdapter);
        }
        //if (FYearGlobal.YearEndTransfer.NFYearId != 0) { FYearGlobal.YearEndTransfer.AccList = FYearGlobal.YearEndTransfer.Get_Transfer_Balance_Account("PV", iBillRegId.ToString(), conn, trans, sDBName); }
        //BsfGlobal.g_iEntryId = iBillRegId;
        $bAns = true;
        return $bAns;

    }

    public function Update_Purchase_Return($arg_iRegId ,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $iVendorId = 0;
        $iCCId = 0;
        $dPRDate = "";
        $sRefNo = "";
        $dRefDate = "";
        $iPRAccountId = 0;
        $iVendorSLId = 0;
        $iCompanyId = 0;
        $iVendorSLTypeId = 1;
        $iPRSLTypeId = 6;
        $iFACCId = 0;
        $sDBName = "";
        $iVendorAccId = 0;

        $dBillAmount = 0;

        $iSubLedgerId = 0;
        $iBillId = $arg_iRegId;
        $sPRNo = "";
        $iRowNo = 0;
        $dQAmount = 0;
        $sCVType = "";
        $sNarration = "";
        $iQualId = 0;
        $iQTypeId = 0;
        $iQSLId = 0;
        $iQAccId = 0;
        $dRate = 0;
        $dQualAmt = 0;
        $iMQualId = 0;
        $iStateId = 0;
        $iSLTypeId = 0;
        $iServiceTypeId = 0;
        $sPType = "";
        $sMType = "";
        $sCVType = CommonHelper::GetVoucherType(309,$dbAdapter);
        $arg_bRefresh=false;

        $select = $sql->select();
        $select->from(array("a"=>"MMS_PRRegister"))
            ->columns(array('VendorId','CostCentreId','PRNo','CCPRNo','CPRNo','PRDate','PurchaseTypeId'
            ,'BillAmount','Narration','RefId','BillDate','BillNo'))
            ->where("a.PRRegisterId=$arg_iRegId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $prResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($prResult) > 0) {
            $iVendorId = $prResult[0]['VendorId'];
            $iCCId = $prResult[0]['CostCentreId'];
            $dPRDate = date('Y-m-d', strtotime($prResult[0]['PRDate']));
            if ($sCVType == "  " || $sCVType == "GE"){
                $sPRNo= $prResult[0]['PRNo'];
            } else if ($sCVType == "CC"){
                $sPRNo= $prResult[0]['CCPRNo'];
            } else if ($sCVType == "CO") {
                $sPRNo= $prResult[0]['CPRNo'];
            }
            $dRefDate = $prResult[0]['BillDate'];
            $sRefNo = $prResult[0]['BillNo'];
            $dBillAmount = $prResult[0]['BillAmount'];
            $sNarration = $prResult[0]['Narration'];
            $iBillId = $prResult[0]['RefId'];
        }
        $iVendorSLId = CommonHelper::GetSubLedgerId($iVendorId, 1, $dbAdapter);
        if ($iVendorSLId == 0) {
            //BsfGlobal.g_sErrorInfo = "Vendor Sub ledger not found";
            echo '<script type="text/javascript">alert("Vendor Sub ledger not found");</script>';
            return $bAns;
        }

        $bHO = CommonHelper::FindHOCC($iCCId, $dbAdapter);
        $select = $sql->select();
        if ($bHO == true) {
            $select->from(array("OCC"=>"WF_OperationalCostCentre"))
                ->columns(array('CompanyId','FACostCentreId','MINAccount' => new Expression("CM.MINAccount")
                ,'CurrencyId' => new Expression("CM.CurrencyId"),'StateId' => new Expression("CC.StateId")))
                ->join(array("CC"=>"WF_CostCentre"), "OCC.FACostCentreId=CC.CostCentreId", array(), $select::JOIN_INNER)
                ->join(array("CM"=>"WF_CompanyMaster"), "OCC.CompanyId=CM.CompanyId", array(), $select::JOIN_INNER);
        } else {
            $select->from(array("OCC"=>"WF_OperationalCostCentre"))
                ->columns(array('CompanyId'=> new Expression("CC.CompanyId"),'FACostCentreId','MINAccount' => new Expression("CM.MINAccount")
                ,'CurrencyId' => new Expression("CM.CurrencyId"),'StateId' => new Expression("CC.StateId")))
                ->join(array("CC"=>"WF_CostCentre"), "OCC.FACostCentreId=CC.CostCentreId", array(), $select::JOIN_INNER)
                ->join(array("CM"=>"WF_CompanyMaster"), "CC.CompanyId=CM.CompanyId", array(), $select::JOIN_INNER);
        }
        $select->where("OCC.CostCentreId=$iCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $operationalCCResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($operationalCCResult) > 0) {
            $iCompanyId = $operationalCCResult[0]['CompanyId'];
            $iFACCId = $operationalCCResult[0]['FACostCentreId'];
            $iStateId = $operationalCCResult[0]['StateId'];
        }

        $iFYearId = CommonHelper::GetFAYearId($iCompanyId, $dPRDate, $dbAdapter);
        if ($iFYearId == 0) {
            //BsfGlobal.g_sErrorInfo = "Company Fiscal Year not found";
            echo '<script type="text/javascript">alert("Company Fiscal Year not found");</script>';
            return $bAns;
        }
        $sDBName="";
        /*$sDBName = CommonHelper::GetDBName($iFYearId, $dbAdapter);
        if (BsfGlobal.CheckDBFound(sDBName) == false) {
            //BsfGlobal.g_sErrorInfo = "Company Fiscal Year Database not found";
            return $bAns;
        }*/

        $iVendorAccId = CommonHelper::Get_Account_From_Type(4, $dbAdapter);
        if ($iVendorAccId == 0) {
            echo '<script type="text/javascript">alert("Vendor Account not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Vendor Account not found";
            return $bAns;
        }

        //Account Posting
        $iSubLedgerId = CommonHelper::GetSubLedgerId($iVendorId, 1, $dbAdapter);
        if ($iSubLedgerId == 0) { return $bAns; }

        if (CommonHelper::Check_Posting_Lock_FA($iCompanyId, $iFYearId, $dPRDate, $dbAdapter) == true) {
            echo '<script type="text/javascript">alert("Posting period / Fiscal Year lock found, cannot proceed");</script>';
            //BsfGlobal.g_sErrorInfo = "Posting period / Fiscal Year lock found, can't proceed";
            return $bAns;
        }

        if ($arg_bRefresh == false){
            if (CommonHelper::Check_Entries_Exists_FA($iBillId, "PR", $sDBName, $iCompanyId, $dbAdapter) == true) return $bAns = true;

            $insert = $sql->insert();
            $insert->into('FA_ReturnRegister');
            $insert->Values(array('ReturnDate' => $dPRDate
            , 'ReturnNo' => $sPRNo
            , 'RefId' => $arg_iRegId
            , 'RefNo' => $sPRNo
            , 'RefDate' => $dPRDate
            , 'SubLedgerId' => $iVendorSLId
            , 'CostCentreId' => $iFACCId
            , 'ReturnAmount' => $dBillAmount
            , 'CompanyId' => $iCompanyId
            , 'FYearId' => $iFYearId
            ));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iBillId = $dbAdapter->getDriver()->getLastGeneratedValue();
        } else {
            $delete = $sql->delete();
            $delete->from('FA_EntryTrans')
                ->where("RefType='PR' AND RefId=$iBillId");
            $DelStatement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $select = $sql->select();
        $select->from(array("a"=>"MMS_PRTrans"))
            ->columns(array('ResourceId','ReturnQty','QRate','Amount','QAmount','PurchaseTypeId'))
            ->where("a.PRRegisterId=$arg_iRegId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $prTransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        // Accounts Update for Resource - Credit Posting
        $iResLedId = 0;
        $dBAmount = 0;
        //if(count($prTransResult) > 0) {
        foreach($prTransResult as &$prTransResults) {
            $iRowNo = $iRowNo + 1;
            $iPRAccountId = $prTransResults['PurchaseTypeId'];
            $iResLedId = CommonHelper::GetSubLedgerId($prTransResults['ResourceId'], 6, $dbAdapter);
            $dQAmount = $prTransResults['Amount'];
            $dBAmount += $dQAmount;

            $insert = $sql->insert();
            $insert->into('FA_EntryTrans');
            $insert->Values(array('RefId' => $iBillId
            , 'TransType' => 'C'
            , 'RefType' => 'PR'
            , 'AccountId' => $iPRAccountId
            , 'RelatedAccountId' => $iVendorAccId
            , 'SubLedgerTypeId' => $iPRSLTypeId
            , 'SubLedgerId' => $iResLedId
            , 'RelatedSLTypeId' => 1
            , 'RelatedSLId' => $iVendorSLId
            , 'CostCentreId' => $iFACCId
            , 'Amount' => $dQAmount
            , 'CompanyId' => $iCompanyId
            , 'VoucherDate' => $dPRDate
            , 'VoucherNo' => $sPRNo
            , 'Remarks' => $sNarration
            , 'OCCId' => $iCCId
            , 'Approve' => 'Y'
            ));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        // Accounts Update for Vendor - Debit Posting
        $insert = $sql->insert();
        $insert->into('FA_EntryTrans');
        $insert->Values(array('RefId' => $iBillId
        , 'TransType' => 'D'
        , 'RefType' => 'PR'
        , 'AccountId' => $iVendorAccId
        , 'RelatedAccountId' => $iPRAccountId
        , 'SubLedgerTypeId' => $iVendorSLTypeId
        , 'SubLedgerId' => $iVendorSLId
        , 'CostCentreId' => $iFACCId
        , 'Amount' => $dBAmount
        , 'CompanyId' => $iCompanyId
        , 'VoucherDate' => $dPRDate
        , 'VoucherNo' => $sPRNo
        , 'Remarks' => $sNarration
        , 'OCCId' => $iCCId
        , 'Approve' => 'Y'
        ));
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        // Qualifier Account Posting
        /*
         * sSql = "SELECT A.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,Amount=SUM(A.Amount),ActAmt=SUM(A.ActAmt),AccountId FROM (" +
               "SELECT A.ResourceId, B.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,A.Amount,A.ActAmt,A.AccountId " +
               "FROM [" + BsfGlobal.g_sMMSDBName + "].dbo.IPDCompQual A " +
               "INNER JOIN [" + BsfGlobal.g_sRateAnalDBName + "].dbo.Qualifier_Temp B ON A.QualifierId=B.QualifierId " +
               "WHERE A.Type='R' AND A.RegisterId=" + arg_iRegId + " AND B.QualTypeId IN (1,2,3,4,6,7,10,12,13,17)) A " +
               "GROUP BY A.QualTypeId,A.QualifierId,A.AddLessFlag,A.NetPer,A.AccountId ";
         */

        $selectGroup = $sql->select();
        $selectGroup->from(array("A"=>"MMS_PRQualTrans"))
            ->columns(array('ResourceId' ,'QualTypeId'=> new Expression("C.QualifierTypeId"),'QualifierId',
                'Sign'=>new Expression("A.Sign"),'NetPer'
            ,'Amount'=>new Expression("A.ExpressionAmt"),'ActAmt' ,'AccountId'))
            ->join(array("B" => "Proj_QualifierTrans"), "A.QualifierId=B.QualifierId", array(), $selectGroup::JOIN_INNER)
            ->join(array("C" => "Proj_QualifierMaster"),"B.QualifierId=C.QualifierId",array(),$selectGroup::JOIN_INNER)
            ->where("A.PRRegisterId=$arg_iRegId AND B.QualType='M' And C.QualifierTypeId IN (1,2,3,4,6,7,10,12,13,17)");

        $selectFinal = $sql->select();
        $selectFinal->from(array("A"=>$selectGroup))
            ->columns(array('QualTypeId','QualifierId','Sign','NetPer','Amount'=> new Expression("SUM(A.Amount)")
            ,'ActAmt'=> new Expression("SUM(A.ActAmt)"),'AccountId'));
        $selectFinal->group(new Expression('A.QualTypeId,A.QualifierId,A.Sign,A.NetPer,A.AccountId'));
        $statement = $sql->getSqlStringForSqlObject($selectFinal);
        $prQualTransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($prQualTransResult as &$prQualTransResults) {
            $iQualId = $prQualTransResults['QualifierId'];
            $iQTypeId = $prQualTransResults['QualTypeId'];
            $iQSLId = 0;
            $iQAccId = $prQualTransResults['AccountId'];
            $dRate = $prQualTransResults['NetPer'];
            $dQualAmt = $prQualTransResults['Amount'];
            if ($iQTypeId == 1){
                $iQSLId = CommonHelper::Get_Common_SubLedgerId($iQTypeId, $dbAdapter);
                $iSLTypeId = 8;
            } else if ($iQTypeId == 2 || $iQTypeId == 3 || $iQTypeId == 4 || $iQTypeId == 10 || $iQTypeId == 12) {
                $iMQualId = $iQualId;//CommonHelper::GetQualId($iQualId, $dbAdapter);
                $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iStateId, $dRate, $iServiceTypeId, $dbAdapter);
                $iSLTypeId = 8;
            } else if ($iQTypeId == 6) {
                $iSLTypeId = 9;
                $iMQualId = CommonHelper::Get_TermsTypeId("DISCOUNT", $dbAdapter);
                $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
            } else if ($iQTypeId == 7) {
                $iSLTypeId = 9;
                $iMQualId = CommonHelper::Get_TermsTypeId("TRANSPORT", $dbAdapter);
                $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
            } else if ($iQTypeId == 13) {
                $iSLTypeId = 9;
                $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", $dbAdapter);
                $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
            } else if ($iQTypeId == 17) {
                $iSLTypeId = 9;
                $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, 0, 0, 0, $dbAdapter);
            }

            if ($iQSLId == 0) {
                //BsfGlobal.g_sErrorInfo = "Qualifier Sub ledger not found";
                echo '<script type="text/javascript">alert("Qualifier Sub ledger not found");</script>';
                return $bAns;
            } else if ($iQAccId == 0)  {
                //BsfGlobal.g_sErrorInfo = "Qualifier Account not found";
                echo '<script type="text/javascript">alert("Qualifier Account not found"");</script>';
                return $bAns;
            }

            if($prQualTransResults['Sign'] == "-"){
                $sPType = "D";
                $sMType = "C";
            } else {
                $sPType = "C";
                $sMType = "D";
            }


            if ($dQAmount != 0){
                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillId
                , 'VoucherDate' => $dPRDate
                , 'TransType' => $sPType
                , 'RefType' => 'PR'
                , 'AccountId' => $iQAccId
                , 'RelatedAccountId' => $iVendorAccId
                , 'SubLedgerTypeId' => $iSLTypeId
                , 'SubLedgerId' => $iQSLId
                , 'RelatedSLTypeId' => 1
                , 'RelatedSLId' => $iVendorSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dQualAmt
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPRNo
                , 'BranchId' => 0
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                $insert->into('FA_EntryTrans');
                $insert->Values(array('RefId' => $iBillId
                , 'VoucherDate' => $dPRDate
                , 'TransType' => $sPType
                , 'RefType' => 'PR'
                , 'AccountId' => $iVendorAccId
                , 'RelatedAccountId' => $iQAccId
                , 'SubLedgerTypeId' => 1
                , 'SubLedgerId' => $iVendorSLId
                , 'RelatedSLTypeId' => $iSLTypeId
                , 'RelatedSLId' => $iQSLId
                , 'CostCentreId' => $iFACCId
                , 'Amount' => $dQualAmt
                , 'CompanyId' => $iCompanyId
                , 'VoucherNo' => $sPRNo
                , 'BranchId' => 0
                , 'Remarks' => $sNarration
                , 'Approve' => 'Y'
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
        $update = $sql->update();
        $update->table('MMS_PRRegister')
            ->set(array('RefId' => $iBillId));
        $update->where(array('PRRegisterId' => $arg_iRegId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        /*
         * if (FYearGlobal.YearEndTransfer.NFYearId != 0) { FYearGlobal.YearEndTransfer.AccList = FYearGlobal.YearEndTransfer.Get_Transfer_Balance_Account("PR", iBillId.ToString(), conn, trans, sDBName); }
         */
        $bAns = true;
        return $bAns;
    }

}
?>