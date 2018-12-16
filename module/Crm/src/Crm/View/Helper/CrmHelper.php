<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 16-08-2016
 * Time: 2:48 PM
 */

namespace Crm\View\Helper;
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

class CrmHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
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

    public function Update_BuyerReceipt($arg_iReceiptId ,$dbAdapter) {
        $arg_bRefresh = false;
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $dRDate = "";
        $sRecNo = "";
        $iCCId = 0;
        $iFACCId = 0;
        $iCompId = 0;
        $iLeadId = 0;
        $iFlatId = 0;
        $iFYearId = 0;
        $iSubLedgerId = 0;
        $iReceiptId = 0;
        $sBlockName = "";
        $sFlatName = "";
        $sFloorName = "";
        $sBillType = "";
        $bBillFAUpdate = false;
        $sDBName = "";
        $dRate = 0;
        $iQSLTypeId=0;
        $iQTypeId = 0;
        $iQualId = 0;
        $iMQualId = 0;
        $iQSLId = 0;
        $iQAccId = 0;
        $iBuyerAccId = 0;
        $sTransType = "";
        $dQAmt = 0;
        $dGross = 0;
        $iEntryId = 0;
        $iEFYId = 0;
        $bHO = false;
        $iStateId = 0;
        $dRAmount= 0;
        $dTotal = 0;
        $dTDSAmount = 0;
        $dTDSRate = 0;
        $bPreBook = false;
        $iRCompId = 0;
        $iRCCId = 0;
        $iLandId = 0;
        $bCompoundTax = false;
        $bPartialFA = false;
        $bLOPartialFA = false;
        $sType = "";
        $bExists = false;
        $dNonFAAmt = 0;
        $dCompTax = 0;
        $dInterest = 0;
        $sPayType = "";
        $sRefType = "";
        $sHCType = "";
        $sCType = "";
        $sRemarks= "";
        $iSLTypeId = 0;
        $bMultiCompany = false;
        $bPreBookAdjust= false;
        $iKeyNo = 0;

        /*
         * SELECT [a].[ReceiptDate] AS [ReceiptDate], [a].[ReceiptNo] AS [ReceiptNo]
, [d].[CostCentreId] AS [CostCentreId], [d].[CompanyId] AS [CompanyId]
, [a].[ReceiptAgainst] AS [BillType], [a].[Narration] AS [Narration]
, [a].[ReceiptAgainst] AS [PaymentAgainst], [a].[UnitId] AS [FlatId]
, [a].[KeyNo] AS [KeyNo], [a].[PreBookAdjust] AS [PreBookAdjust] FROM [CRM_ReceiptRegister] AS [a]
INNER JOIN KF_UnitMaster b on a.UnitId=b.UnitId
INNER JOIN Proj_ProjectMaster c on b.ProjectId=c.ProjectId
INNER JOIN WF_OperationalCostCentre d on c.ProjectId=d.ProjectId
WHERE a.ReceiptId=1180
         */
        $select = $sql->select();
        $select->from(array("a"=>"CRM_ReceiptRegister"))
            ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
            ->join(array("d" => "WF_OperationalCostCentre"), "c.ProjectId=d.ProjectId", array(), $select::JOIN_INNER)
            ->columns(array('ReceiptDate','ReceiptNo','CostCentreId'=> new Expression("d.CostCentreId")
            ,'CompanyId'=> new Expression("d.CompanyId"),'BillType'=> new Expression("a.ReceiptAgainst"),'Narration'
            ,'PaymentAgainst'=> new Expression("a.ReceiptAgainst"),'FlatId'=> new Expression("a.UnitId"),'KeyNo','PreBookAdjust'))
            ->where("a.ReceiptId=$arg_iReceiptId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $receiptResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($receiptResult) > 0) {
            $dRDate = $receiptResult[0]['ReceiptDate'];
            $iRCCId = $receiptResult[0]['CostCentreId'];
            $iCCId = $receiptResult[0]['CostCentreId'];
            $iFlatId = $receiptResult[0]['FlatId'];
            $iRCompId = $receiptResult[0]['CompanyId'];
            $sBillType = $receiptResult[0]['BillType'];
            $sRecNo = $receiptResult[0]['BillType'];
            $sRemarks = $receiptResult[0]['Narration'];
            $sPayType = $receiptResult[0]['PaymentAgainst'];
            if($receiptResult[0]['PreBookAdjust']==1){
                $bPreBookAdjust = true;
            }
            if($sPayType=="T"){
                $bPreBook = true;
            } else {
                $bPreBook = false;
            }
            $iKeyNo = $receiptResult[0]['KeyNo'];
        }

        if ($sPayType == "R"){
            $sRefType = "LRR";
        } else if ($sPayType == "M") {
            $sRefType = "HCR";
        } else {
            $sRefType = "BR";
        }

        /*
         * SELECT CostCentreId FROM [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CostCentre WHERE MultiCompany=1 AND CostCentreId IN (" +
               "SELECT FACostCentreId FROM  [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.OperationalCostCentre WHERE CostCentreId=" + iCCId + ")
         */

        $subQuery = $sql->select();
        $subQuery->from("WF_OperationalCostCentre")
            ->columns(array('FACostCentreId'))
            ->where("CostCentreId=$iCCId");

        $select = $sql->select();
        $select->from(array("a"=>"WF_CostCentre"))
            ->columns(array('CostCentreId' ));
        $select->where->expression('a.CostCentreId IN ?', array($subQuery));
        $select->where("MultiCompany=1");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ccResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($ccResult) > 0) {
            $bMultiCompany=true;
        }

        if ($bMultiCompany == true) {
            if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R" && $sPayType != "M") {
                #region Find Compounding Or Regular Tax
                $subQuery = $sql->select();
                $subQuery->from("CRM_FlatDetails")
                    ->columns(array('PayTypeId'))
                    ->where("FlatId=$iFlatId");

                $select = $sql->select();
                $select->from(array("a"=>"CRM_PaySchType"))
                    ->columns(array('TypeId' ));
                $select->where->expression('a.TypeId IN ?', array($subQuery));
                $select->where("TypeWise=0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $compountResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($compountResult) > 0) {
                    $bCompoundTax=true;
                }
                #endregion

                #region Find Partial FA Update / Partial Land Owner FA Update ...
                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("Type='L' AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompResult) > 0) {
                    $bLOPartialFA=true;
                }

                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("Type='L' AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompResult) > 0) {
                    $bPartialFA=true;
                }

                if ($bPartialFA == true && $bLOPartialFA == true){
                    //BsfGlobal.g_sErrorInfo = "Multi Company Receipt Type FA Update conflict(Land Owner & Others)";
                    echo '<script type="text/javascript">alert("Multi Company Receipt Type FA Update conflict(Land Owner & Others)");</script>';
                    return $bAns;
                }
                #endregion

                #region Find Whether the Flat is Land Owner
                if ($bLOPartialFA == true) {
                    $bLOPartialFA = false;
                    $select = $sql->select();
                    $select->from(array("a"=>"CRM_UnitReserve"))//crm_UnitReserve
                    ->columns(array('UnitId'))
                        ->where("ReservedBy='LandOwner' AND UnitId=$iFlatId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($multiCompResult) > 0) {
                        $bLOPartialFA=true;
                    }
                }
                #endregion

                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompListResult) > 0) {
                    $iCCId=$multiCompListResult[0]['MultiCostCentreId'];
                    if ($iCCId == 0) { $iCCId = $iRCCId; }
                } else {
                    //BsfGlobal.g_sErrorInfo = "Multi Company Setup not found";
                    echo '<script type="text/javascript">alert("Multi Company Setup not found");</script>';
                    return $bAns;
                }
            }
        }

        $select = $sql->select();
        $select->from(array("a" => "WF_OperationalCostCentre"))
            ->join(array("b" => "WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $select::JOIN_INNER)
            ->join(array("c" => "WF_CompanyMaster"), "c.CompanyId=a.CompanyId", array(), $select::JOIN_INNER)
            ->columns(array('FACostCentreId','CompanyId'=>new Expression('b.CompanyId'),'HO'=>new Expression("b.HO")
            ,'HOCompId'=>new Expression("a.CompanyId")
            ,'ProgressBillFAUpdate'=>new Expression("1")//c.ProgressBillFAUpdate for setup
            ,'StateId'=>new Expression("b.StateId")));
        $select->where("a.CostCentreId=$iCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $HOCompListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($HOCompListResult) > 0) {
            $iFACCId = $HOCompListResult[0]['FACostCentreId'];
            $iStateId = $HOCompListResult[0]['StateId'];
            if($HOCompListResult[0]['HO']==1){
                $bHO =true;
            }
            if ($bHO == true) {
                $iCompId =$HOCompListResult[0]['HOCompId'];
            } else {
                $iCompId =$HOCompListResult[0]['CompanyId'];
            }
            if($HOCompListResult[0]['ProgressBillFAUpdate']==1){
                $bBillFAUpdate=true;
            }
        }

        if ($iCompId == 0) {
            echo '<script type="text/javascript">alert("Company not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Company not found";
            return $bAns;
        }

        $iFYearId = CommonHelper::GetFAYearId($iCompId, $dRDate, $dbAdapter);
        //$sDBName = CommonHelper::GetDBName($iFYearId, $dbAdapter);

        if ($arg_bRefresh == false)
        {
            if (CommonHelper::Check_Receipt_Exists_FA($arg_iReceiptId, $sRefType, "", $dbAdapter) == true) return $bAns = true;
        }

        //Sai
        #region if Partial FA Update / Partial LO FA Update
        if ($bPartialFA == true || $bLOPartialFA == true) {
            $dNonFAAmt = 0;
            $bExists = false;

            $select = $sql->select();
            $select->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
            ->columns(array('Count'=>new Expression('Count(ReceiptTypeId)')));
            $select->where("a.FlatId=$iFlatId AND ReceiptId=$iReceiptId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ReceiptShTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if($ReceiptShTrans['Count'] != 0){ //check
                $bExists = true;
            }
            if($bExists == true){
                if ($bPartialFA == true) $sType = "A";
                if ($bLOPartialFA == true) $sType = "L";

                $subQuery1 = $sql->select();
                $subQuery1->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('ReceiptTypeId'));
                $subQuery1->where("a.ReceiptTypeId<>0 AND a.MultiCostCentreId=$iCCId AND a.FAUpdate='R' AND a.Type='$sType'");

                $select1=$sql->select();
                $select1->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select1->where->expression("a.ReceiptId=$arg_iReceiptId AND ReceiptTypeId<>0 AND ReceiptTypeId NOT IN ? ", array($subQuery1));

                $subQuery2 = $sql->select();
                $subQuery2->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('QualifierId'));
                $subQuery2->where("QualifierId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R' AND Type='$sType'");

                $select2=$sql->select();
                $select2->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select2->where->expression("ReceiptId=$arg_iReceiptId AND QualifierId<>0 AND QualifierId NOT IN ? ", array($subQuery2));
                $select2->combine($select1,'Union ALL');

                $subQuery3 = $sql->select();
                $subQuery3->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('OtherCostId'));
                $subQuery3->where("OtherCostId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R' AND Type='$sType'");

                $select3=$sql->select();
                $select3->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select3->where->expression("ReceiptId=$arg_iReceiptId AND OtherCostId<>0 AND OtherCostId NOT IN ? ", array($subQuery2));
                $select3->combine($select2,'Union ALL');

                $select=$sql->select();
                $select->from(array("a" => $select3))
                    ->columns(array('NonFAAmt'=>new Expression('SUM(ISNULL(NonFAAmt,0))')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $NonFAAmt= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($NonFAAmt) > 0) {
                    $dNonFAAmt += $NonFAAmt[0]["NonFAAmt"];
                }
            }
            $bExists = false;

            $select=$sql->select();
            $select->from(array("a" => 'CRM_MultiCompany'))
                ->columns(array('MultiCostCentreId'))
                ->where("ReceiptTypeId=1 AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $MultiCompany= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($MultiCompany) > 0) {
                $bExists = true;
            }
            if ($bExists == true){
                $select=$sql->select();
                $select->from(array("a" => 'CRM_ReceiptTrans'))//Crm_ReceiptAdjustment
                ->columns(array('NonFAAmt'=>new Expression("ISNULL(SUM(Amount),0)")))
                    ->where("ReceiptType='Advance' AND ReceiptId=$arg_iReceiptId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $FA_ReceiptTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($FA_ReceiptTrans)>0){
                    $dNonFAAmt += $FA_ReceiptTrans[0]["NonFAAmt"];
                }
            }
            if ($bPartialFA == true) $sType = "A";
            if ($bLOPartialFA == true) $sType = "L";
        }
        #endregion

        $select=$sql->select();
        $select->from(array("a" => 'CRM_ReceiptRegister'))
            ->columns(array('LeadId','Amount'))
            ->where("ReceiptId=$arg_iReceiptId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ReceiptRegister= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($ReceiptRegister)>0){
            $iLeadId = $ReceiptRegister[0]["LeadId"];
            $dRAmount= $ReceiptRegister[0]["Amount"];
            //$dTDSAmount = $ReceiptRegister[0]["TDS"];
            //$dTDSRate = $ReceiptRegister[0]["TDSPercentage"];
            //$dCompTax = $ReceiptRegister[0]["CompoundTax"];
            //$dInterest = $ReceiptRegister[0]["PaidInterest"];
            $dTotal = $dRAmount;//+ $dCompTax + $dInterest - $dTDSAmount;

            if ($dNonFAAmt != 0) {
                $dTotal = $dTotal - $dNonFAAmt;
            }

            if ($bPreBookAdjust == true){
                $dRAmount = 0;
                $dTDSAmount = 0;
                $dTDSRate = 0;
                $dCompTax = 0;
                $dInterest = 0;
                $dTotal = 0;
            }
            if ($dTotal <= 0 && $bPreBookAdjust == false){
                //BsfGlobal.g_sErrorInfo = "Receipt Amount should be valid ( " + sRecNo + " /" + dNonFAAmt.ToString() + ")";
                echo '<script type="text/javascript">alert("Receipt Amount should be valid ( '. $sRecNo .' / '. $dNonFAAmt .')");</script>';
                return $bAns;
            }
            // iSubLedgerId = GetSubLedgerId(iLeadId, 3, conn, trans);
            $iSubLedgerId = CommonHelper::GetSubLedgerId($iLeadId, 3, $dbAdapter);

            if ($iSubLedgerId == 0 && $bPreBook == true){
                $select=$sql->select();
                $select->from(array("a" => 'CRM_Leads'))
                    ->columns(array('LeadName','SubLedgerTypeId'=> new Expression("3"),'LeadId'))
                    ->where("LeadId NOT IN (SELECT RefId FROM FA_SubLedgerMaster WHERE SubLedgerTypeId=3) AND LeadId=$iLeadId");

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iSubLedgerId = CommonHelper::GetSubLedgerId($iLeadId, 3, $dbAdapter);
            }

            if ($iSubLedgerId == 0){
                /*BsfGlobal.g_sErrorInfo = "Buyer Sub Ledger not found";*/
                echo '<script type="text/javascript">alert("Buyer Sub Ledger not found");</script>';
                return $bAns;
            }

            if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                /*sSql = String.Format("SELECT LandId=0, BM.BlockName,FD.FlatNo,LM.LevelName FROM [{0}].dbo.FlatDetails FD " +
                                        "INNER JOIN  [{0}].dbo.LevelMaster LM ON LM.LevelId=FD.LevelId " +
                                        "INNER JOIN  [{0}].dbo.BlockMaster BM ON BM.BlockId=FD.BlockId WHERE FD.FlatId IN ({2})",
                                        BsfGlobal.g_sCRMDBName,
                                        arg_iReceiptId,
                                        iFlatId

                 * SELECT 1-1 AS [LandId], BM.BlockName AS [BlockName], FD.UnitNo AS [FlatNo]
, LM.FloorName AS [LevelName] FROM [KF_unitMaster] AS [FD]
INNER JOIN [KF_FloorMaster] AS [LM] ON [LM].[FloorId]=[FD].[FloorId]
INNER JOIN [KF_BlockMaster] AS [BM] ON [BM].[BlockId]=[FD].[BlockId]
WHERE FD.UnitId IN (6)

                                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'KF_unitMaster'))//Crm_Unit
                ->join(array("LM" => "KF_FloorMaster"), "LM.FloorId=FD.FloorId", array(), $select::JOIN_INNER)
                    ->join(array("BM" => "KF_BlockMaster"), "BM.BlockId=FD.BlockId", array(), $select::JOIN_INNER)
                    ->columns(array('LandId'=>new Expression("1-1"),'BlockName'=>new Expression("BM.BlockName")
                    ,'FlatNo'=>new Expression("FD.UnitNo"),'LevelName'=>new Expression("LM.FloorName")))
                    ->where("FD.UnitId IN ($iFlatId)");
            } else if ($sPayType == "R"){
                /*sSql = String.Format("SELECT LandId=0, BM.BlockName,FlatNo=FD.LeaseFlatNo,LM.LevelName FROM [{0}].dbo.LeaseFlatDetails FD " +
                        "INNER JOIN  [{0}].dbo.LevelMaster LM ON LM.LevelId=FD.LevelId " +
                        "INNER JOIN  [{0}].dbo.BlockMaster BM ON BM.BlockId=FD.BlockId WHERE FD.LeaseFlatId IN ({2})",
                        BsfGlobal.g_sCRMDBName,
                        arg_iReceiptId,
                        iFlatId
                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'KF_unitMaster'))//Crm_Unit
                ->join(array("LM" => "KF_FloorMaster"), "LM.FloorId=FD.FloorId", array(), $select::JOIN_INNER)
                    ->join(array("BM" => "KF_BlockMaster"), "BM.BlockId=FD.BlockId", array(), $select::JOIN_INNER)
                    ->columns(array('LandId'=>new Expression("1-1"),'BlockName'=>new Expression("BM.BlockName")
                    ,'FlatNo'=>new Expression("FD.UnitNo"),'LevelName'=>new Expression("LM.FloorName")))
                    ->where("FD.UnitId IN ($iFlatId)");
            } else{
                /*sSql = String.Format("SELECT LandId=LandRegisterId, BlockName='',FlatNo=PlotNo,LevelName='' FROM [{0}].dbo.LandPlotDetails FD WHERE FD.PlotDetailsId IN ({2})",
                        BsfGlobal.g_sRateAnalDBName,
                        arg_iReceiptId,
                        iFlatId
                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'Proj_LandPlotDetails'))
                    ->columns(array('LandId'=>new Expression("FD.LandRegisterId"),'BlockName'=>new Expression("''")
                    ,'FlatNo'=>new Expression("FD.PlotNo"),'LevelName'=>new Expression("''")))
                    ->where("FD.PlotDetailsId IN ($iFlatId)");
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $LandDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(count($LandDetails) > 0) {
                $sBlockName = $LandDetails[0]["BlockName"];
                $sFlatName = $LandDetails[0]["FlatNo"];
                $sFloorName = $LandDetails[0]["LevelName"];
                $iLandId = $LandDetails[0]["LandId"];
            }

            if ($sRefType == "BR" || $sRefType == "LRR"){
                //iBuyerAccId = Get_Account_From_Type(1, conn, trans);
                $iBuyerAccId = CommonHelper::Get_Account_From_Type(1, $dbAdapter);
            } else {
                $iBuyerAccId = 0;
                $select=$sql->select();
                $select->from(array("RT" => 'FA_ReceiptTrans'))
                    ->join(array("HBT" => "FA_HCBillTrans"), "HBT.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->join(array("HBR" => "FA_HCBillRegister"), "HBR.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->columns(array('Type'=>new Expression("DISTINCT HBT.AccountId,HBR.Type"))) //check
                    ->where("RT.ReceiptId=0 AND RT.Amount<>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $BuyerAccDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($BuyerAccDet) == 1) {
                    $iBuyerAccId = $BuyerAccDet[0]["AccountId"];
                    $sHCType = $BuyerAccDet[0]["Type"];
                }
                if (strtoupper($sHCType) == "DONE") {
                    $iBuyerAccId = CommonHelper::Get_Account_From_Type(1, $dbAdapter);
                }
            }

            if ($iBuyerAccId == 0 && ($sRefType == "BR" || $sRefType == "LRR")) {
                /*BsfGlobal.g_sErrorInfo = "Buyer/Advance Account not found";*/
                echo '<script type="text/javascript">alert("Buyer/Advance Account not found");</script>';
                return $bAns;
            }

            if ($iSubLedgerId != 0){
                $sFlatInfo = "";
                #region Over All

                $delete = $sql->delete();
                $delete->from('FA_SLDet')
                    ->where("SLId=$iSubLedgerId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //check
                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + FlatNo FROM [{0}].dbo.FlatDetails " +
                            "WHERE LeadId={1} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iLeadId);*/
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->join(array("b" => "KF_unitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo"))) //check
                        ->where("a.LeadId=$iLeadId");
                    $select->group(array("b.UnitNo"));

                } else if ($sPayType == "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + LeaseFlatNo FROM [{0}].dbo.LeaseFlatDetails " +
                            "WHERE LeadId={1} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iLeadId);*/
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->join(array("b" => "KF_unitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo"))) //check
                        ->where("a.LeadId=$iLeadId");
                    $select->group(array("b.UnitNo"));
                } else{
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE BuyerId={1} SELECT @Name FlatNo ", BsfGlobal.g_sRateAnalDBName, iLeadId);*/
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=>new Expression("a.PlotNo"))) //check
                        ->where("a.BuyerId=$iLeadId");
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $FlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($FlatLandDetail as &$FlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $FlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                    $sFlatInfo=substr($sFlatInfo, 0, 200);
                }
                /*sSql = String.Format("INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.SLDet(SLId,SLTypeId,Remarks) " +
                        "SELECT {0},3,'{1}'", iSubLedgerId, iFACCId, sFlatInfo);
                cmd = new SqlCommand(sSql, conn, trans);
                cmd.ExecuteNonQuery();
                cmd.Dispose();*/

                $insert = $sql->insert();
                $insert->into('FA_SLDet');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion

                #region Company
                $sFlatInfo = "";

                $delete = $sql->delete();
                $delete->from('FA_CMSLDet')
                    ->where("SLId=$iSubLedgerId AND CompanyId=$iCompId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //check
                $subQuery = $sql->select();
                $subQuery->from(array("a" => "WF_OperationalCostCentre"))
                    ->columns(array("CostCentreId"));
                $subQuery->where("a.CompanyId=$iCompId");

                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R") {

                    /*select b.UnitNo from Crm_UnitBooking a
INNER JOIN KF_UnitMaster b on a.UnitId=b.UnitId
INNER JOIN WF_OperationalCostCentre c on b.ProjectId=c.ProjectId
Where a.LeadId=2 and c.CompanyId=1 */
                    $select->from(array("a" => 'Crm_UnitBooking'))//Crm_Unit
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_OperationalCostCentre"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo")))
                        ->where("a.LeadId=$iLeadId and c.CompanyId=$iCompId");
                    $select->group(array("b.UnitNo"));
                } else if ($sPayType == "R"){
                    $select->from(array("a" => 'Crm_UnitBooking'))//Crm_Unit
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_OperationalCostCentre"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo")))
                        ->where("a.LeadId=$iLeadId and c.CompanyId=$iCompId");
                    $select->group(array("b.UnitNo"));
                }
                else{
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.PlotNo")));
                    $select->where->expression("a.BuyerId=$iLeadId and a.CostCentreId IN ?", array($subQuery));
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE CostCentreId IN (SELECT CostCentreId FROM [{3}].dbo.OperationalCostCentre " +
                            "WHERE CompanyId={1}) AND BuyerId={2} SELECT @Name FlatNo ",
                            BsfGlobal.g_sRateAnalDBName, iCompId, iLeadId, BsfGlobal.g_sWorkFlowDBName);*/
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $CompanyFlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($CompanyFlatLandDetail as &$CompanyFlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $CompanyFlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                    $sFlatInfo=substr($sFlatInfo, 0, 200);
                }


                $insert = $sql->insert();//check
                $insert->into('FA_CMSLDet');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'CompanyId' => $iCompId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion

                #region CC Wise
                $sFlatInfo = "";

                $delete = $sql->delete();
                $delete->from('FA_CCSLDet')
                    ->where("SLId=$iSubLedgerId AND CCId=$iFACCId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + FlatNo FROM [{0}].dbo.FlatDetails " +
                            "WHERE CostCentreId= {1} AND LeadId={2} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iCCId, iLeadId);*/
                    $select->from(array("a" => 'Crm_UnitBooking'))//Crm_Unit
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_OperationalCostCentre"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo")))
                        ->where("a.LeadId=$iLeadId and c.CostCentreId=$iCCId");
                    $select->group(array("b.UnitNo"));
                } else if ($sPayType == "R"){
                    $select->from(array("a" => 'Crm_UnitBooking'))//Crm_Unit
                    ->join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_OperationalCostCentre"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('FlatNo'=>new Expression("b.UnitNo")))
                        ->where("a.LeadId=$iLeadId and c.CostCentreId=$iCCId");
                    $select->group(array("b.UnitNo"));
                } else{
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE CostCentreId= {1} AND BuyerId={2} SELECT @Name FlatNo ", BsfGlobal.g_sRateAnalDBName, iCCId, iLeadId);*/
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.PlotNo")));
                    $select->where("a.CostCentreId=$iCCId and a.BuyerId=$iLeadId");
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $CCFlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($CCFlatLandDetail as &$CCFlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $CCFlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                    $sFlatInfo=substr($sFlatInfo, 0, 200);
                }

                $insert = $sql->insert();//check
                $insert->into('FA_CCSLDet');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'CCId' => $iFACCId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion
            }

            if ($arg_bRefresh == false){

                /*sSql = String.Format("INSERT INTO [{0}].dbo.ReceiptRegister(ReceiptDate,ReceiptNo,RefType,RefTypeId,ReferenceId,AccountId,SubLedgerId,CreditDays,ReceiptAmount,ChequeNo,ChequeDate,BankName,TransType,BillType,CostCentreId,CompanyId,FYearId,Remarks,FlatInfo,BlockInfo,FloorInfo) " +
                        "SELECT ReceiptDate,ReceiptNo,'"+sRefType+"',15,ReceiptId,{10},{1},0 ," + dTotal + ",ChequeNo,ChequeDate,BankName,'R',BillType,{2},{3},{4}, Narration,'{5}','{8}','{9}' FROM [{6}].dbo.ReceiptRegister WHERE ReceiptId={7} SELECT SCOPE_IDENTITY();",
                        BsfGlobal.g_sFaDBName,
                        iSubLedgerId,
                        iFACCId,
                        iCompId,
                        0,
                        sFlatName,
                        BsfGlobal.g_sCRMDBName,
                        arg_iReceiptId,
                        sBlockName,
                        sFloorName,
                        iBuyerAccId
                    );*/
                $select=$sql->select();
                $select->from(array("a" => 'CRM_ReceiptRegister'))
                    ->columns(array('ReceiptDate','ReceiptNo','RefType'=> new Expression("'$sRefType'"),'RefTypeId'=> new Expression("15"),'ReceiptId'
                    ,'AccountId'=> new Expression("$iBuyerAccId"),'SubLedgerId'=> new Expression("$iSubLedgerId"),'CreditDays'=> new Expression("1-1") ,'ReceiptAmount'=> new Expression("$dTotal")
                    ,'TransNo','TransDate','BankName','TransType'=> new Expression("'R'")
                    ,'ReceiptMode','CostCentreId'=> new Expression("$iFACCId"),'CompanyId'=> new Expression("$iCompId"),'FYearId'=> new Expression("1-1")
                    , 'Narration','FlatInfo'=> new Expression("'$sFlatName'"),'BlockInfo'=> new Expression("'$sBlockName'"),'FloorInfo'=> new Expression("'$sFloorName'")))
                    ->where("ReceiptId=$arg_iReceiptId");

                $insert = $sql->insert();//check
                $insert->into('FA_ReceiptRegister');
                $insert->columns(array('ReceiptDate', 'ReceiptNo', 'RefType','RefTypeId','ReferenceId','AccountId','SubLedgerId','CreditDays','ReceiptAmount','ChequeNo'
                ,'ChequeDate','BankName','TransType','BillType','CostCentreId','CompanyId','FYearId','Remarks','FlatInfo','BlockInfo','FloorInfo'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iReceiptId = $dbAdapter->getDriver()->getLastGeneratedValue();
                $iKeyNo = $iReceiptId;
            } else {
                //sSql = String.Format("SELECT ReceiptId,EntryId,FYearId FROM [{0}].dbo.ReceiptRegister WHERE ReferenceId={1}", BsfGlobal.g_sFaDBName, arg_iReceiptId);
                $select=$sql->select();
                $select->from(array("a" => 'FA_ReceiptRegister'))
                    ->columns(array('ReceiptId','EntryId','FYearId'))
                    ->where("ReferenceId=$arg_iReceiptId");
                echo $statement = $sql->getSqlStringForSqlObject($select);die;
                $ReceiptDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (count($ReceiptDetails)> 0){
                    $iReceiptId = $ReceiptDetails[0]["ReceiptId"];
                    $iEntryId = $ReceiptDetails[0]["EntryId"];
                    $iEFYId = $ReceiptDetails[0]["FYearId"];
                }

                if ($iEntryId != 0){
                    $bAns = true;
                    return $bAns;
                }
            }

            #region Tax / Receipt Against Schedule ....

            if ($sBillType == "S" || $sBillType == "B" || $sBillType == "A"){
                /* $select=$sql->select();
                 $select->from(array("a" => 'CRM_QualifierAccount'))
                     ->columns(array('QualifierId','Cnt'=>new Expression("Count(QualifierId)")))
                     ->group(array("QualifierId"))
                     ->having("Count(QualifierId)>1");//check
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $QualifierAccount= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 if (count($QualifierAccount)> 0){
                     //BsfGlobal.g_sErrorInfo = "Multiple Account found for Same Qualifier,Check Qualifier Account Setup";
                     echo '<script type="text/javascript">alert("Multiple Account found for Same Qualifier,Check Qualifier Account Setup");</script>';
                     return $bAns;
                 }
 */
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" ){
                    if ($bCompoundTax == false) {
                        if ($bPartialFA == true || $bLOPartialFA == true) {
                            /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "AND (A.ReceiptTypeId IN (SELECT ReceiptTypeId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE ReceiptTypeId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R') " +
                                "OR A.OtherCostId IN (SELECT OtherCostId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE OtherCostId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R')) " +
                                "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                            $subQuery1 = $sql->select();
                            $subQuery1->from("CRM_MultiCompany")
                                ->columns(array('ReceiptTypeId'))
                                ->where("ReceiptTypeId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $subQuery2 = $sql->select();
                            $subQuery2->from("CRM_MultiCompany")
                                ->columns(array('OtherCostId'))
                                ->where("OtherCostId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptQualifier'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("B.AccountId"),'NetPer','Add_Less_Flag'
                                , 'GrossValue'=>new Expression("SUM(A.ExpValue)"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where->expression("A.ReceiptId=$arg_iReceiptId AND (A.ReceiptTypeId IN ?",array($subQuery1)," OR A.OtherCostId IN ?",array($subQuery2),")");
                            $select->group(array("A.QualTypeId","A.QualifierId","B.AccountId","A.Add_Less_Flag", "NetPer"))
                                ->having("SUM(Amount)<>0");
                        } else {
                            /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                            /*
                             * select a.QualifierId,a.NetPer,a.Sign,GrossValue=SUM(a.ExpressionAmt), Amount=SUM(a.NetAmt)
from Crm_ReceiptQualifierTrans a
INNER JOIN Crm_ReceiptAdjustmentTrans b on a.ReceiptAdjTransId=b.ReceiptAdjTransId
INNER JOIN Crm_ReceiptAdjustment c on b.ReceiptAdjId=c.ReceiptAdjId
Where c.ReceiptId=5
group by a.QualifierId,a.NetPer,a.Sign
                             */
                            $select = $sql->select();
                            $select->from(array('a' =>'Crm_ReceiptQualifierTrans'))
                                ->join(array("b" => "Crm_ReceiptAdjustmentTrans"), "a.ReceiptAdjTransId=b.ReceiptAdjTransId", array(), $select::JOIN_INNER)
                                ->join(array("c" => "Crm_ReceiptAdjustment"), "b.ReceiptAdjId=c.ReceiptAdjId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("1-1"),'NetPer','Add_Less_Flag'=>new Expression("a.Sign")
                                , 'GrossValue'=>new Expression("SUM(a.ExpressionAmt)"), 'Amount'=>new Expression("SUM(a.NetAmt)")))
                                ->where("c.ReceiptId=$arg_iReceiptId");
                            $select->group(array("a.QualTypeId","a.QualifierId", "NetPer","A.Sign"))
                                ->having("SUM(a.NetAmt)<>0");
                        }
                    } else { // CompoundingTax
                        if ($bPartialFA == true || $bLOPartialFA == true) {
                            /*sSql = "SELECT Q.QualTypeId,A.QualifierId,B.AccountId, NetPer=Percentage,Add_Less_Flag='+', GrossValue=0, Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTax A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId " +
                                "INNER JOIN [" + BsfGlobal.g_sRateAnalDBName+ "].dbo.Qualifier_Temp Q ON A.QualifierId=Q.QualifierId " +
                                "WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "AND A.QualifierId IN (SELECT QualifierId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE QualifierId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R') " +
                                "GROUP BY Q.QualTypeId,A.QualifierId,B.AccountId, Percentage HAVING SUM(Amount)<>0 ";*/

                            $subQuery1 = $sql->select();
                            $subQuery1->from("CRM_MultiCompany")
                                ->columns(array('QualifierId'))
                                ->where("QualifierId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptTax'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->join(array("Q" => "Proj_Qualifier_Temp"), "A.QualifierId=Q.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId'=>new Expression("Q.QualTypeId"),'QualifierId','AccountId'=>new Expression("B.AccountId")
                                ,'NetPer'=>new Expression("A.Percentage"),'Add_Less_Flag'=>new Expression("'+'")
                                , 'GrossValue'=>new Expression("1-1"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where->expression("A.ReceiptId=$arg_iReceiptId AND A.QualifierId IN ? ",array($subQuery1));
                            $select->group(array("Q.QualTypeId","A.QualifierId","B.AccountId","A.Percentage"))
                                ->having("SUM(A.Amount)<>0");

                        } else {
                            /*sSql = "SELECT Q.QualTypeId,A.QualifierId,B.AccountId, NetPer=Percentage,Add_Less_Flag='+', GrossValue=0, Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTax A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId " +
                                "INNER JOIN [" + BsfGlobal.g_sRateAnalDBName + "].dbo.Qualifier_Temp Q ON A.QualifierId=Q.QualifierId " +
                                "WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "GROUP BY Q.QualTypeId,A.QualifierId,B.AccountId, Percentage HAVING SUM(Amount)<>0 ";*/

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptTax'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->join(array("Q" => "Proj_Qualifier_Temp"), "A.QualifierId=Q.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId'=>new Expression("Q.QualTypeId"),'QualifierId','AccountId'=>new Expression("B.AccountId")
                                ,'NetPer'=>new Expression("A.Percentage"),'Add_Less_Flag'=>new Expression("'+'")
                                , 'GrossValue'=>new Expression("1-1"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where("A.ReceiptId=$arg_iReceiptId");
                            $select->group(array("Q.QualTypeId","A.QualifierId","B.AccountId","A.Percentage"))
                                ->having("SUM(A.Amount)<>0");
                        }
                    }
                } else { // Plot Qualifier ...
                    /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                        "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                        "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                    $select = $sql->select();
                    $select->from(array('A' =>'CRM_ReceiptQualifier'))
                        ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                        ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("B.AccountId"),'NetPer','Add_Less_Flag'
                        , 'GrossValue'=>new Expression("SUM(A.ExpValue)"), 'Amount'=>new Expression("SUM(A.Amount)")))
                        ->where("A.ReceiptId=$arg_iReceiptId");
                    $select->group(array("A.QualTypeId","A.QualifierId","A.Add_Less_Flag","NetPer"))
                        ->having("SUM(A.Amount)<>0");


                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $QualifierDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                for ($k = 0; $k < count($QualifierDet); $k++) {
                    $iQTypeId = $QualifierDet[$k]['QualTypeId'];
                    $iQualId = $QualifierDet[$k]['QualifierId'];
                    //iMQualId = GetQualId(iQualId, conn, trans);
                    $iMQualId =$iQualId;// CommonHelper::GetQualId($iQualId, $dbAdapter);
                    $dRate = $QualifierDet[$k]['NetPer'];
                    $dGross = $QualifierDet[$k]['GrossValue'];
                    $dQAmt = $QualifierDet[$k]['Amount'];
                    $sTransType =$QualifierDet[$k]['Add_Less_Flag'];

                    if ($sTransType == "+"){
                        $sTransType = "D";
                    } else{
                        $sTransType = "C";
                    }

                    if ($iQTypeId!= 13) {
                        $iQSLTypeId = 8;
                        //$iQSLId = CommonHelper::GetTaxSubLedger(iMQualId, iStateId, dRate, 0, conn, trans);
                        $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iStateId, $dRate, 0, $dbAdapter);
                    } else {
                        $iQSLTypeId = 9;
//                        $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", conn, trans);
//                        $iQSLId = CommonHelper::GetSubLedgerId(iMQualId, 9, conn, trans);
                        $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", $dbAdapter);
                        $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
                    }

                    if ($iQSLId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Sub ledger(Tax) not found";*/
                        echo '<script type="text/javascript">alert("Sub ledger(Tax) not found");</script>';
                        return $bAns;
                    }
                    //Need to Modified based on CRMQualifer AccountTagging
                    //For Receipt
                    $iQAccId=0;
                    $iQAccTypeId=0;
                    if($iQTypeId==1){
                        $iQAccTypeId=13;
                    } else if($iQTypeId==2){
                        $iQAccTypeId=15;
                    } else if($iQTypeId==3){
                        $iQAccTypeId=31;
                    } else if($iQTypeId==4){
                        $iQAccTypeId=33;
                    }
                    /*//For Payment
                    if($iQTypeId==1){
                        $iQAccTypeId=12;
                    } else if($iQTypeId==2){
                        $iQAccTypeId=14;
                    } else if($iQTypeId==3){
                        $iQAccTypeId=30;
                    } else if($iQTypeId==4){
                        $iQAccTypeId=32;
                    } else if($iQTypeId==SBC){
                        $iQAccTypeId=54;
                    } else if($iQTypeId==KKC){
                        $iQAccTypeId=57;
                    }
                    */
                    if($iQAccTypeId!=0){
                        $iQAccId= CommonHelper::Get_Account_From_Type($iQAccTypeId, $dbAdapter);
                    }
                    if ($iQAccId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Tax Account not found";*/
                        echo '<script type="text/javascript">alert("Tax Account not found");</script>';
                        return $bAns;
                    }

                    if ($arg_bRefresh == false){//true
                        $delete= $sql->delete();
                        $delete->from('FA_ReceiptTaxDet')
                            ->where("RefId=$arg_iReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if ($bBillFAUpdate == true){//false
                        /*sSql = "INSERT INTO FA_ReceiptTaxDet(ReceiptId,QualTypeId,QualifierId,AccountId,SubLedgerId,RefId,GrossAmount,Amount,TransType,TaxRate) " +
                            "SELECT " + iReceiptId + "," + iQSLTypeId + "," + iQualId + "," + iQAccId + "," + iQSLId + ", " + arg_iReceiptId + "," + dGross + "," + dQAmt + ",'" + sTransType + "'," + dRate + "";
                        cmd = new SqlCommand(sSql, conn, trans);
                        cmd.ExecuteNonQuery(); cmd.Dispose();*/

                        $insert = $sql->insert();//check
                        $insert->into('FA_ReceiptTaxDet');
                        $insert->Values(array('ReceiptId' => $iReceiptId
                        , 'QualTypeId' => $iQSLTypeId
                        , 'QualifierId' => $iQualId
                        , 'AccountId' => $iQAccId
                        , 'SubLedgerId' =>$iQSLId
                        , 'RefId' => $arg_iReceiptId
                        , 'GrossAmount' =>$dGross
                        , 'Amount' => $dQAmt
                        , 'TransType' =>$sTransType
                        , 'TaxRate' => $dRate
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $bPreBookAdjust=true;//for Manually cheque

                        if ($bPreBookAdjust == true){
                            if ($iFYearId == 0){
                                /*BsfGlobal.g_sErrorInfo = "Fiscal year not found";*/
                                echo '<script type="text/javascript">alert("Fiscal year not found");</script>';
                                return $bAns;
                            }
                            /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                                "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','D','BR'," + iBuyerAccId + "," + iQAccId + ",3," + iSubLedgerId + ",8," + iQSLId + "," +
                                "" + iFACCId + "," + dQAmt + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iReceiptId
                            , 'VoucherNo' => $sRecNo
                            , 'VoucherDate' => $dRDate //check
                            , 'TransType' => 'D'
                            , 'RefType' =>'BR'
                            , 'AccountId' =>$iBuyerAccId
                            , 'RelatedAccountId' =>$iQAccId
                            , 'SubLedgerTypeId' =>3
                            , 'SubLedgerId' =>$iSubLedgerId
                            , 'RelatedSLTypeId' =>8
                            , 'RelatedSLId' =>$iQSLId
                            , 'CostCentreId' =>$iFACCId
                            , 'Amount' =>$dQAmt
                            , 'CompanyId' =>$iCompId
                            , 'Remarks' =>$sRemarks
                            , 'Approve' =>'Y'
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                                "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','C','BR'," + iQAccId + "," + iBuyerAccId + ", 8," + iQSLId + ",3," + iSubLedgerId + "," +
                                "" + iFACCId + "," + dQAmt + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iReceiptId
                            , 'VoucherNo' => $sRecNo
                            , 'VoucherDate' => $dRDate //check
                            , 'TransType' => 'C'
                            , 'RefType' =>'BR'
                            , 'AccountId' =>$iQAccId
                            , 'RelatedAccountId' =>$iBuyerAccId
                            , 'SubLedgerTypeId' =>8
                            , 'SubLedgerId' =>$iQSLId
                            , 'RelatedSLTypeId' =>3
                            , 'RelatedSLId' =>$iSubLedgerId
                            , 'CostCentreId' =>$iFACCId
                            , 'Amount' =>$dQAmt
                            , 'CompanyId' =>$iCompId
                            , 'Remarks' =>$sRemarks
                            , 'Approve' =>'Y'
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptRegister SET FYearId=" + iFYearId + " WHERE ReceiptId=" + iReceiptId;*/
                            $update = $sql->update();
                            $update->table('FA_ReceiptRegister')
                                ->set(array('FYearId' => $iFYearId))
                                ->where("ReceiptId=$iReceiptId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                if ($dTDSAmount != 0){
                    /*sSql = String.Format("SELECT A.QualifierId,B.QualMId,A.AccountId FROM [{0}].dbo.QualifierAccount A " +
                            "INNER JOIN [{1}].dbo.Qualifier_Temp B ON A.QualifierId=B.QualifierId WHERE B.QualTypeId=1 AND A.AccountId<>0 ",
                            BsfGlobal.g_sCRMDBName, BsfGlobal.g_sRateAnalDBName);*/
                    $select = $sql->select();
                    $select->from(array("A" => "CRM_QualifierAccount"))
                        ->join(array("B" => "Proj_Qualifier_Temp"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_LEFT)
                        ->columns(array("QualifierId",'AccountId','QualMId'=>new Expression("B.QualMId")));
                    $select->where("B.QualTypeId=1 AND A.AccountId<>0");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $QualAcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if (count($QualAcc)== 1) {
                        $iQualId = $QualAcc[0]["QualifierId"];
                        $iMQualId = $QualAcc[0]["QualMId"];
                        $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iStateId, $dTDSRate, 11, $dbAdapter);
                        $iQSLTypeId = 8;
                        $iQAccId = $QualAcc[0]["AccountId"];
                        $dGross = $dTotal+$dTDSAmount;
                        $sTransType = "C";

                    }
                    if ($iQSLId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Sub ledger(Tax) not found";*/
                        echo '<script type="text/javascript">alert("Sub ledger(Tax) not found");</script>';
                        return $bAns;
                    }
                    if ($iQAccId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Tax Account not found";*/
                        echo '<script type="text/javascript">alert("Tax Account not found");</script>';
                        return $bAns;
                    }

                    /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptTaxDet(ReceiptId,QualTypeId,QualifierId,AccountId,SubLedgerId,RefId,GrossAmount,Amount,TransType,TaxRate) " +
                        "SELECT " + iReceiptId + ", " + iQSLTypeId + "," + iQualId + "," + iQAccId + "," + iQSLId + ", " + arg_iReceiptId + "," + dGross + "," + Math.Abs(dTDSAmount) + ",'" + sTransType + "'," + dTDSRate + "";*/

                    $insert = $sql->insert();//check
                    $insert->into('FA_ReceiptTaxDet');
                    $insert->Values(array('ReceiptId' => $iReceiptId
                    , 'QualTypeId' => $iQSLTypeId
                    , 'QualifierId' => $iQualId
                    , 'AccountId' => $iQAccId
                    , 'SubLedgerId' =>$iQSLId
                    , 'RefId' => $arg_iReceiptId
                    , 'GrossAmount' =>$dGross
                    , 'Amount' => $dTDSAmount
                    , 'TransType' => $sTransType
                    , 'TaxRate' => $dTDSRate
                    ));

                    if ($dTotal == 0 && $dTDSAmount != 0){
                        if ($iFYearId== 0){
                            /*BsfGlobal.g_sErrorInfo = "Fiscal year not found";*/
                            echo '<script type="text/javascript">alert("Fiscal year not found");</script>';
                            return $bAns;
                        }

                        /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                            "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','C','BR'," + iBuyerAccId + "," + iQAccId + ",3," + iSubLedgerId + ",8," + iQSLId + "," +
                            "" + iFACCId + "," + dTDSAmount + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iReceiptId
                        , 'VoucherNo' => $sRecNo
                        , 'VoucherDate' => $dRDate //check
                        , 'TransType' => 'C'
                        , 'RefType' =>'BR'
                        , 'AccountId' =>$iBuyerAccId
                        , 'RelatedAccountId' =>$iQAccId
                        , 'SubLedgerTypeId' =>3
                        , 'SubLedgerId' =>$iSubLedgerId
                        , 'RelatedSLTypeId' =>8
                        , 'RelatedSLId' =>$iQSLId
                        , 'CostCentreId' =>$iFACCId
                        , 'Amount' =>$dTDSAmount
                        , 'CompanyId' =>$iCompId
                        , 'Remarks' =>$sRemarks
                        , 'Approve' =>'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                            "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','D','BR'," + iQAccId+ "," + iBuyerAccId + ", 8," + iQSLId + ",3," + iSubLedgerId + "," +
                            "" + iFACCId + "," + dTDSAmount + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iReceiptId
                        , 'VoucherNo' => $sRecNo
                        , 'VoucherDate' => $dRDate //check
                        , 'TransType' => 'D'
                        , 'RefType' =>'BR'
                        , 'AccountId' =>$iQAccId
                        , 'RelatedAccountId' =>$iBuyerAccId
                        , 'SubLedgerTypeId' =>8
                        , 'SubLedgerId' =>$iQSLId
                        , 'RelatedSLTypeId' =>3
                        , 'RelatedSLId' =>$iSubLedgerId
                        , 'CostCentreId' =>$iFACCId
                        , 'Amount' =>$dTDSAmount
                        , 'CompanyId' =>$iCompId
                        , 'Remarks' =>$sRemarks
                        , 'Approve' =>'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptRegister SET FYearId=" + iFYearId + " WHERE ReceiptId=" + iReceiptId;*/
                        $update = $sql->update();
                        $update->table('FA_ReceiptRegister')
                            ->set(array('FYearId' => $iFYearId))
                            ->where("ReceiptId=$iReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            } else if ($sBillType == "M") {
                /*sSql = "SELECT RT.ReceiptId,RT.Amount,HBT.AccountId,TransType='C',SType=HCC.Type FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTrans RT " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCBillTrans HBT ON HBT.ChargeId=RT.PaySchId AND RT.BillRegId=HBT.BillId  " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCBillRegister HBR ON HBR.BillId=RT.BillRegId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCCharges HCC ON HCC.ChargeId=HBT.ChargeId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCServiceMaster HCS ON HCS.ServiceId=HBT.ServiceId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.FeatureListMaster HCF ON HCF.FeatureId=HBT.FeatureId " +
                    "WHERE RT.ReceiptId=" + arg_iReceiptId;*/

                $select = $sql->select();
                $select->from(array("RT" => "CRM_ReceiptTrans"))
                    ->join(array("HBT" => "CRM_HCBillTrans"), "HBT.ChargeId=RT.PaySchId AND RT.BillRegId=HBT.BillId", array(), $select::JOIN_INNER)
                    ->join(array("HBR" => "CRM_HCBillRegister"), "HBR.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->join(array("HCC" => "CRM_HCCharges"), "HCC.ChargeId=HBT.ChargeId", array(), $select::JOIN_LEFT)
                    ->join(array("HCS" => "CRM_HCServiceMaster"), "HCS.ServiceId=HBT.ServiceId", array(), $select::JOIN_LEFT)
                    ->join(array("HCF" => "CRM_FeatureListMaster"), "HCF.FeatureId=HBT.FeatureId", array(), $select::JOIN_LEFT)
                    ->columns(array("ReceiptId",'Amount','AccountId'=>new Expression("HBT.AccountId")
                    ,'TransType'=>new Expression("'C'"),'SType'=>new Expression("HCC.Type")
                    ));
                $select->where("RT.ReceiptId=$arg_iReceiptId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ReceiptDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                for ($i = 0; $i < count($ReceiptDet); $i++){
                    $sCType = $ReceiptDet[$i]["SType"];
                    $dTotal=$ReceiptDet[$i]["Amount"];
                    if (strtoupper($sHCType)== "DONE") {
                        $sTransType = $ReceiptDet[$i]["TransType"];
                        $iQAccId = $iBuyerAccId;
                        $iSLTypeId = 3;
                        $iQSLId = $iSubLedgerId;
                    } else {
                        $iQAccId = $ReceiptDet[$i]["AccountId"];
                        $sTransType = $ReceiptDet[$i]["TransType"];
                        $iSLTypeId = 0;
                        $iQSLId = 0;
                        if ($sCType == "D") {
                            $iSLTypeId = 3;
                            $iQSLId = $iSubLedgerId;
                        }
                    }

                    /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptTransDet(ReceiptId,RefId,SLTypeId,SubLedgerId,AccountId,Amount,TransType)" +
                        "SELECT " + iReceiptId + "," + arg_iReceiptId + ","+iSLTypeId+"," + iQSLId + "," + iQAccId + "," + dTotal + ",'" + sTransType + "'";*/
                    $insert = $sql->insert();//check
                    $insert->into('FA_ReceiptTransDet');
                    $insert->Values(array('ReceiptId' => $iReceiptId
                    , 'RefId' => $arg_iReceiptId
                    , 'SLTypeId' => $iSLTypeId
                    , 'SubLedgerId' => $iQSLId
                    , 'AccountId' => $iQAccId
                    , 'Amount' => $dTotal
                    , 'TransType' => $sTransType
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
            #endregion

            #region Progress Bill Update
            // No Need to Post ... KG
            if ($bBillFAUpdate == false && 1 == 0){
                if ($arg_bRefresh == true){
                    /*sSql = String.Format("DELETE FROM [{0}].dbo.ReceiptPBTrans WHERE Type='PB' AND ReferenceId={1}", BsfGlobal.g_sFaDBName, arg_iReceiptId);*/
                    $delete= $sql->delete();
                    $delete->from('FA_ReceiptPBTrans')
                        ->where("Type='PB' AND ReferenceId=$arg_iReceiptId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptPBTrans(ReferenceId,PBillId,Amount,PBillAmount,Type) " +
                    "SELECT RT.ReceiptId,RT.BillRegId,RT.Amount,RT.NetAmount, 'PB' FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTrans RT " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptRegister RR ON RR.ReceiptId=RT.ReceiptId " +
                    "WHERE BillRegId<>0 AND RR.ReceiptId=" + arg_iReceiptId;*/

                $select = $sql->select();
                $select->from(array("RT" => "FA_ReceiptTrans"))
                    ->join(array("RR" => "CRM_ReceiptRegister"), "RR.ReceiptId=RT.ReceiptId", array(), $select::JOIN_INNER)
                    ->columns(array("ReceiptId",'BillRegId','Amount','NetAmount'
                    ,'Type'=>new Expression("'PB'")
                    ));
                $select->where("BillRegId<>0 AND RR.ReceiptId=$arg_iReceiptId");

                $insert = $sql->insert();
                $insert->into('FA_ReceiptPBTrans');
                $insert->columns(array('ReferenceId','PBillId','Amount','PBillAmount','Type'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );


                /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "]..ReceiptPBTrans SET ReceiptId=B.ReceiptId FROM [" + BsfGlobal.g_sFaDBName + "]..ReceiptPBTrans A " +
                    "JOIN (SELECT ReceiptId,ReferenceId FROM [" + BsfGlobal.g_sFaDBName + "]..ReceiptRegister) B ON A.ReferenceId=B.ReferenceId";*/

                $update = $sql->update();
                $update->table( "FA_ReceiptPBTrans" )
                    ->set( array( 'ReceiptId' => new Expression ("B.ReceiptId FROM ReceiptPBTrans A
                    JOIN (SELECT ReceiptId,ReferenceId FROM FA_ReceiptRegister) B ON A.ReferenceId=B.ReferenceId")));
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                /*sSql = String.Format("SELECT * FROM [{0}].dbo.ReceiptTrans WHERE BillRegId<>0 AND ReceiptId={1}", BsfGlobal.g_sCRMDBName, arg_iReceiptId);*/
                /*SELECT * FROM FA_ReceiptTrans WHERE BillRegId<>0 AND ReceiptId=$arg_iReceiptId*/

                $select = $sql->select();
                $select->from(array("a" => "CRM_ReceiptTrans"));
                $select->where("BillRegId<>0 AND ReceiptId=$arg_iReceiptId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ReceiptTransDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $i = 0;
                $iPBillId = 0;
                //DataTable dtT = null;
                while ($i < count($ReceiptTransDet)) {
                    $iPBillId = $ReceiptTransDet[$i]["BillRegId"];

                    /*sSql = String.Format("SELECT ProgRegId FROM [{0}]..ProgressBillRegister WHERE PBillId={1} AND RefId=0", BsfGlobal.g_sCRMDBName, iPBillId);*/
                    $select = $sql->select();
                    $select->from(array("a" => "FA_ProgressBillRegister"));
                    $select->columns(array("ProgRegId"));
                    $select->where("PBillId=$iPBillId AND RefId=0");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $ProgressBill= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $bUpdate = false;
                    $iProgRegId = 0;
                    if (count($ProgressBill) > 0) {
                        $iProgRegId = $ProgressBill[0]["ProgRegId"];

                        $bUpdate = CommonHelper::Update_ProgressBill_Flat($iProgRegId, $dbAdapter, $iPBillId, true, $arg_bRefresh);

                        if ($bUpdate == true) {
                            /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister SET PaidAmount=B.PaidAmount FROM [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister A " +
                                "JOIN ( SELECT PBillId,PaidAmount=SUM(Amount) FROM [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptPBTrans WHERE PBillId=" + iPBillId + " GROUP BY PBillId) B " +
                                "ON A.ReferenceId=B.PBillId WHERE A.RefType='PB'";*/

                            $update = $sql->update();
                            $update->table( "FA_BillRegister" )
                                ->set( array( 'PaidAmount' => new Expression ("B.PaidAmount FROM FA_BillRegister A
                                    JOIN ( SELECT PBillId,PaidAmount=SUM(Amount) FROM FA_ReceiptPBTrans WHERE PBillId=$iPBillId GROUP BY PBillId) B
                                    ON A.ReferenceId=B.PBillId WHERE A.RefType='PB'")));
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                    }
                    $i++;
                }
            }
            #endregion

            /*sSql = "UPDATE [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptRegister SET KeyNo=" + iKeyNo + " WHERE ReceiptId=" + arg_iReceiptId;*/
            $update = $sql->update();
            $update->table('CRM_ReceiptRegister')
                ->set(array('KeyNo' => $iKeyNo ))
                ->where("ReceiptId=$arg_iReceiptId");
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            /* $sText = "Dear Customer, Thank you for making payment of Rs." . $dTotal;
             $sMsg = "";
             using (Report.ReceiptInfo frm = new Report.ReceiptInfo())
             {
                 $sMsg = frm.Execute(arg_iReceiptId, conn, trans);
             }
             BsfGlobal.InsertBuyerAlert_Html("Buyer-Payment-Received", sText, sMsg, iLeadId, BsfGlobal.g_sCRMDBName, conn, trans);*/

        }
        $bAns = true;
        return $bAns;
    }

    public function Update_BuyerReceiptOld($arg_iReceiptId ,$dbAdapter) {
        $arg_bRefresh = false;
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $dRDate = "";
        $sRecNo = "";
        $iCCId = 0;
        $iFACCId = 0;
        $iCompId = 0;
        $iLeadId = 0;
        $iFlatId = 0;
        $iFYearId = 0;
        $iSubLedgerId = 0;
        $iReceiptId = 0;
        $sBlockName = "";
        $sFlatName = "";
        $sFloorName = "";
        $sBillType = "";
        $bBillFAUpdate = false;
        $sDBName = "";
        $dRate = 0;
        $iQSLTypeId=0;
        $iQTypeId = 0;
        $iQualId = 0;
        $iMQualId = 0;
        $iQSLId = 0;
        $iQAccId = 0;
        $iBuyerAccId = 0;
        $sTransType = "";
        $dQAmt = 0;
        $dGross = 0;
        $iEntryId = 0;
        $iEFYId = 0;
        $bHO = false;
        $iStateId = 0;
        $dRAmount= 0;
        $dTotal = 0;
        $dTDSAmount = 0;
        $dTDSRate = 0;
        $bPreBook = false;
        $iRCompId = 0;
        $iRCCId = 0;
        $iLandId = 0;
        $bCompoundTax = false;
        $bPartialFA = false;
        $bLOPartialFA = false;
        $sType = "";
        $bExists = false;
        $dNonFAAmt = 0;
        $dCompTax = 0;
        $dInterest = 0;
        $sPayType = "";
        $sRefType = "";
        $sHCType = "";
        $sCType = "";
        $sRemarks= "";
        $iSLTypeId = 0;
        $bMultiCompany = false;
        $bPreBookAdjust= false;
        $iKeyNo = 0;

        $select = $sql->select();
        $select->from(array("a"=>"CRM_ReceiptRegister"))
            ->columns(array('ReceiptDate','ReceiptNo','CostCentreId','CompanyId','BillType','Narration'
            ,'PaymentAgainst','FlatId','KeyNo','PreBookAdjust'))
            ->where("a.ReceiptId=$arg_iReceiptId");
        echo $statement = $sql->getSqlStringForSqlObject($select);die;
        $receiptResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($receiptResult) > 0) {
            $dRDate = $receiptResult[0]['ReceiptDate'];
            $iRCCId = $receiptResult[0]['CostCentreId'];
            $iCCId = $receiptResult[0]['CostCentreId'];
            $iFlatId = $receiptResult[0]['FlatId'];
            $iRCompId = $receiptResult[0]['CompanyId'];
            $sBillType = $receiptResult[0]['BillType'];
            $sRecNo = $receiptResult[0]['BillType'];
            $sRemarks = $receiptResult[0]['Narration'];
            $sPayType = $receiptResult[0]['PaymentAgainst'];
            if($receiptResult[0]['PreBookAdjust']==1){
                $bPreBookAdjust = true;
            }
            if($sPayType=="T"){
                $bPreBook = true;
            } else {
                $bPreBook = false;
            }
            $iKeyNo = $receiptResult[0]['KeyNo'];
        }

        if ($sPayType == "R"){
            $sRefType = "LRR";
        } else if ($sPayType == "M") {
            $sRefType = "HCR";
        } else {
            $sRefType = "BR";
        }

        /*
         * SELECT CostCentreId FROM [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CostCentre WHERE MultiCompany=1 AND CostCentreId IN (" +
               "SELECT FACostCentreId FROM  [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.OperationalCostCentre WHERE CostCentreId=" + iCCId + ")
         */

        $subQuery = $sql->select();
        $subQuery->from("WF_OperationalCostCentre")
            ->columns(array('FACostCentreId'))
            ->where("CostCentreId=$iCCId");

        $select = $sql->select();
        $select->from(array("a"=>"WF_CostCentre"))
            ->columns(array('CostCentreId' ));
        $select->where->expression('a.CostCentreId IN ?', array($subQuery));
        $select->where("MultiCompany=1");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ccResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($ccResult) > 0) {
            $bMultiCompany=true;
        }

        if ($bMultiCompany == true) {
            if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R" && $sPayType != "M") {
                #region Find Compounding Or Regular Tax
                $subQuery = $sql->select();
                $subQuery->from("CRM_FlatDetails")
                    ->columns(array('PayTypeId'))
                    ->where("FlatId=$iFlatId");

                $select = $sql->select();
                $select->from(array("a"=>"CRM_PaySchType"))
                    ->columns(array('TypeId' ));
                $select->where->expression('a.TypeId IN ?', array($subQuery));
                $select->where("TypeWise=0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $compountResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($compountResult) > 0) {
                    $bCompoundTax=true;
                }
                #endregion

                #region Find Partial FA Update / Partial Land Owner FA Update ...
                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("Type='L' AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompResult) > 0) {
                    $bLOPartialFA=true;
                }

                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("Type='L' AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompResult) > 0) {
                    $bPartialFA=true;
                }

                if ($bPartialFA == true && $bLOPartialFA == true){
                    //BsfGlobal.g_sErrorInfo = "Multi Company Receipt Type FA Update conflict(Land Owner & Others)";
                    echo '<script type="text/javascript">alert("Multi Company Receipt Type FA Update conflict(Land Owner & Others)");</script>';
                    return $bAns;
                }
                #endregion

                #region Find Whether the Flat is Land Owner
                if ($bLOPartialFA == true) {
                    $bLOPartialFA = false;
                    $select = $sql->select();
                    $select->from(array("a"=>"CRM_ReserveFlats"))//crm_UnitReserve
                    ->columns(array('FlatId'))
                        ->where("Type='RS' AND FlatId=$iFlatId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $multiCompResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($multiCompResult) > 0) {
                        $bLOPartialFA=true;
                    }
                }
                #endregion

                $select = $sql->select();
                $select->from(array("a"=>"CRM_MultiCompany"))
                    ->columns(array('MultiCostCentreId'=> new Expression("DISTINCT MultiCostCentreId")))
                    ->where("CostCentreId=$iRCCId AND CompanyId=$iRCompId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $multiCompListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($multiCompListResult) > 0) {
                    $iCCId=$multiCompListResult[0]['MultiCostCentreId'];
                    if ($iCCId == 0) { $iCCId = $iRCCId; }
                } else {
                    //BsfGlobal.g_sErrorInfo = "Multi Company Setup not found";
                    echo '<script type="text/javascript">alert("Multi Company Setup not found");</script>';
                    return $bAns;
                }
            }
        }

        $select = $sql->select();
        $select->from(array("a" => "WF_OperationalCostCentre"))
            ->join(array("b" => "WF_CostCentre"), "a.FACostCentreId=b.CostCentreId", array(), $select::JOIN_INNER)
            ->join(array("c" => "WF_CompanyMaster"), "c.CompanyId=a.CompanyId", array(), $select::JOIN_INNER)
            ->columns(array('FACostCentreId','CompanyId'=>new Expression('b.CompanyId'),'HO'=>new Expression("b.HO")
            ,'HOCompId'=>new Expression("a.CompanyId"),'ProgressBillFAUpdate'=>new Expression("c.ProgressBillFAUpdate"),'StateId'=>new Expression("b.StateId")));
        $select->where("a.CostCentreId=$iCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $HOCompListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($HOCompListResult) > 0) {
            $iFACCId = $HOCompListResult[0]['FACostCentreId'];
            $iStateId = $HOCompListResult[0]['StateId'];
            if($HOCompListResult[0]['HO']==1){
                $bHO =true;
            }
            if ($bHO == true) {
                $iCompId =$HOCompListResult[0]['HOCompId'];
            } else {
                $iCompId =$HOCompListResult[0]['CompanyId'];
            }
            if($HOCompListResult[0]['ProgressBillFAUpdate']==1){
                $bBillFAUpdate=true;
            }
        }

        if ($iCompId == 0) {
            echo '<script type="text/javascript">alert("Company not found");</script>';
            //BsfGlobal.g_sErrorInfo = "Company not found";
            return $bAns;
        }

        $iFYearId = CommonHelper::GetFAYearId($iCompId, $dRDate, $dbAdapter);
        //$sDBName = CommonHelper::GetDBName($iFYearId, $dbAdapter);

        if ($arg_bRefresh == false)
        {
            if (CommonHelper::Check_Receipt_Exists_FA($arg_iReceiptId, $sRefType, "", $dbAdapter) == true) return $bAns = true;
        }

        //Sai
        #region if Partial FA Update / Partial LO FA Update

        if ($bPartialFA == true || $bLOPartialFA == true) {
            $dNonFAAmt = 0;
            $bExists = false;

            $select = $sql->select();
            $select->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
            ->columns(array('Count'=>new Expression('Count(ReceiptTypeId)')));
            $select->where("a.FlatId=$iFlatId AND ReceiptId=$iReceiptId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ReceiptShTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if($ReceiptShTrans['Count'] != 0){ //check
                $bExists = true;
            }
            if($bExists == true){
                if ($bPartialFA == true) $sType = "A";
                if ($bLOPartialFA == true) $sType = "L";

                $subQuery1 = $sql->select();
                $subQuery1->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('ReceiptTypeId'));
                $subQuery1->where("a.ReceiptTypeId<>0 AND a.MultiCostCentreId=$iCCId AND a.FAUpdate='R' AND a.Type='$sType'");

                $select1=$sql->select();
                $select1->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select1->where->expression("a.ReceiptId=$arg_iReceiptId AND ReceiptTypeId<>0 AND ReceiptTypeId NOT IN ? ", array($subQuery1));

                $subQuery2 = $sql->select();
                $subQuery2->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('QualifierId'));
                $subQuery2->where("QualifierId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R' AND Type='$sType'");

                $select2=$sql->select();
                $select2->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select2->where->expression("ReceiptId=$arg_iReceiptId AND QualifierId<>0 AND QualifierId NOT IN ? ", array($subQuery2));
                $select2->combine($select1,'Union ALL');

                $subQuery3 = $sql->select();
                $subQuery3->from(array("a" => "CRM_MultiCompany"))
                    ->columns(array('OtherCostId'));
                $subQuery3->where("OtherCostId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R' AND Type='$sType'");

                $select3=$sql->select();
                $select3->from(array("a" => "CRM_ReceiptShTrans"))//Crm_ReceiptAdjustmentTrans
                ->columns(array('NonFAAmt'=>new Expression('Round(SUM(ISNULL(PaidNetAmount,0)),2)')));
                $select3->where->expression("ReceiptId=$arg_iReceiptId AND OtherCostId<>0 AND OtherCostId NOT IN ? ", array($subQuery2));
                $select3->combine($select2,'Union ALL');

                $select=$sql->select();
                $select->from(array("a" => $select3))
                    ->columns(array('NonFAAmt'=>new Expression('SUM(ISNULL(NonFAAmt,0))')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $NonFAAmt= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($NonFAAmt) > 0) {
                    $dNonFAAmt += $NonFAAmt[0]["NonFAAmt"];
                }
            }
            $bExists = false;

            $select=$sql->select();
            $select->from(array("a" => 'CRM_MultiCompany'))
                ->columns(array('MultiCostCentreId'))
                ->where("ReceiptTypeId=1 AND FAUpdate<>'R' AND CostCentreId=$iRCCId AND CompanyId=$iRCompId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $MultiCompany= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($MultiCompany) > 0) {
                $bExists = true;
            }
            if ($bExists == true){
                $select=$sql->select();
                $select->from(array("a" => 'CRM_ReceiptTrans'))//Crm_ReceiptAdjustment
                ->columns(array('NonFAAmt'=>new Expression("ISNULL(SUM(Amount),0)")))
                    ->where("ReceiptType='Advance' AND ReceiptId=$arg_iReceiptId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $FA_ReceiptTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($FA_ReceiptTrans)>0){
                    $dNonFAAmt += $FA_ReceiptTrans[0]["NonFAAmt"];
                }
            }
            if ($bPartialFA == true) $sType = "A";
            if ($bLOPartialFA == true) $sType = "L";
        }
        #endregion

        $select=$sql->select();
        $select->from(array("a" => 'CRM_ReceiptRegister'))
            ->columns(array('LeadId','Amount','TDSPercentage','TDS','CompoundTax','PaidInterest'))
            ->where("ReceiptId=$arg_iReceiptId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $ReceiptRegister= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($ReceiptRegister)>0){

            $iLeadId = $ReceiptRegister[0]["LeadId"];
            $dRAmount= $ReceiptRegister[0]["Amount"];
            $dTDSAmount = $ReceiptRegister[0]["TDS"];
            $dTDSRate = $ReceiptRegister[0]["TDSPercentage"];
            $dCompTax = $ReceiptRegister[0]["CompoundTax"];
            $dInterest = $ReceiptRegister[0]["PaidInterest"];
            $dTotal = $dRAmount+ $dCompTax + $dInterest - $dTDSAmount;
            if ($dNonFAAmt != 0) {
                $dTotal = $dTotal - $dNonFAAmt;
            }
            if ($bPreBookAdjust == true){
                $dRAmount = 0;
                $dTDSAmount = 0;
                $dTDSRate = 0;
                $dCompTax = 0;
                $dInterest = 0;
                $dTotal = 0;
            }
            if ($dTotal <= 0 && $dCompTax == 0 && $dTDSAmount == 0 && $bPreBookAdjust == false){
                //BsfGlobal.g_sErrorInfo = "Receipt Amount should be valid ( " + sRecNo + " /" + dNonFAAmt.ToString() + ")";
                echo '<script type="text/javascript">alert("Receipt Amount should be valid ( '. $sRecNo .' / '. $dNonFAAmt .')");</script>';
                return $bAns;
            }
            // iSubLedgerId = GetSubLedgerId(iLeadId, 3, conn, trans);
            $iSubLedgerId = CommonHelper::GetSubLedgerId($iLeadId, 3, $dbAdapter);

            if ($iSubLedgerId == 0 && $bPreBook == true){
                $select=$sql->select();
                $select->from(array("a" => 'CRM_LeadRegister'))
                    ->columns(array('LeadName','SubLedgerTypeId'=> new Expression("3"),'LeadId'))
                    ->where("LeadId NOT IN (SELECT RefId FROM FA_SubLedgerMaster WHERE SubLedgerTypeId=3) AND LeadId=$iLeadId");

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iSubLedgerId = CommonHelper::GetSubLedgerId($iLeadId, 3, $dbAdapter);
            }

            if ($iSubLedgerId == 0){
                /*BsfGlobal.g_sErrorInfo = "Buyer Sub Ledger not found";*/
                echo '<script type="text/javascript">alert("Buyer Sub Ledger not found");</script>';
                return $bAns;
            }

            if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                /*sSql = String.Format("SELECT LandId=0, BM.BlockName,FD.FlatNo,LM.LevelName FROM [{0}].dbo.FlatDetails FD " +
                                        "INNER JOIN  [{0}].dbo.LevelMaster LM ON LM.LevelId=FD.LevelId " +
                                        "INNER JOIN  [{0}].dbo.BlockMaster BM ON BM.BlockId=FD.BlockId WHERE FD.FlatId IN ({2})",
                                        BsfGlobal.g_sCRMDBName,
                                        arg_iReceiptId,
                                        iFlatId
                                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'CRM_FlatDetails'))//Crm_Unit
                ->join(array("LM" => "WF_LevelMaster"), "LM.LevelId=FD.LevelId", array(), $select::JOIN_INNER)
                    ->join(array("BM" => "KF_BlockMaster"), "BM.BlockId=FD.BlockId", array(), $select::JOIN_INNER)
                    ->columns(array('LandId'=>new Expression("1-1"),'BlockName'=>new Expression("BM.BlockName")
                    ,'FlatNo'=>new Expression("FD.FlatNo"),'LevelName'=>new Expression("LM.LevelName")))
                    ->where("FD.FlatId IN ($iFlatId)");
            } else if ($sPayType == "R"){
                /*sSql = String.Format("SELECT LandId=0, BM.BlockName,FlatNo=FD.LeaseFlatNo,LM.LevelName FROM [{0}].dbo.LeaseFlatDetails FD " +
                        "INNER JOIN  [{0}].dbo.LevelMaster LM ON LM.LevelId=FD.LevelId " +
                        "INNER JOIN  [{0}].dbo.BlockMaster BM ON BM.BlockId=FD.BlockId WHERE FD.LeaseFlatId IN ({2})",
                        BsfGlobal.g_sCRMDBName,
                        arg_iReceiptId,
                        iFlatId
                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'CRM_LeaseFlatDetails'))
                    ->join(array("LM" => "WF_LevelMaster"), "LM.LevelId=FD.LevelId", array(), $select::JOIN_INNER)
                    ->join(array("BM" => "KF_BlockMaster"), "BM.BlockId=FD.BlockId", array(), $select::JOIN_INNER)
                    ->columns(array('LandId'=>new Expression("1-1"),'BlockName'=>new Expression("BM.BlockName")
                    ,'FlatNo'=>new Expression("FD.FlatNo"),'LevelName'=>new Expression("LM.LevelName")))
                    ->where("FD.LeaseFlatId IN ($iFlatId)");
            } else{
                /*sSql = String.Format("SELECT LandId=LandRegisterId, BlockName='',FlatNo=PlotNo,LevelName='' FROM [{0}].dbo.LandPlotDetails FD WHERE FD.PlotDetailsId IN ({2})",
                        BsfGlobal.g_sRateAnalDBName,
                        arg_iReceiptId,
                        iFlatId
                    );*/
                $select=$sql->select();
                $select->from(array("FD" => 'Proj_LandPlotDetails'))
                    ->columns(array('LandId'=>new Expression("FD.LandRegisterId"),'BlockName'=>new Expression("''")
                    ,'FlatNo'=>new Expression("FD.PlotNo"),'LevelName'=>new Expression("''")))
                    ->where("FD.PlotDetailsId IN ($iFlatId)");
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $LandDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(count($LandDetails) > 0) {
                $sBlockName = $LandDetails[0]["BlockName"];
                $sFlatName = $LandDetails[0]["FlatNo"];
                $sFloorName = $LandDetails[0]["LevelName"];
                $iLandId = $LandDetails[0]["LandId"];
            }
            if ($sRefType == "BR" || $sRefType == "LRR"){
                //iBuyerAccId = Get_Account_From_Type(1, conn, trans);
                $iBuyerAccId = CommonHelper::Get_Account_From_Type(1, $dbAdapter);
            } else {
                $iBuyerAccId = 0;
                $select=$sql->select();
                $select->from(array("RT" => 'FA_ReceiptTrans'))
                    ->join(array("HBT" => "FA_HCBillTrans"), "HBT.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->join(array("HBR" => "FA_HCBillRegister"), "HBR.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->columns(array('Type'=>new Expression("DISTINCT HBT.AccountId,HBR.Type"))) //check
                    ->where("RT.ReceiptId=0 AND RT.Amount<>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $BuyerAccDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($BuyerAccDet) == 1) {
                    $iBuyerAccId = $BuyerAccDet[0]["AccountId"];
                    $sHCType = $BuyerAccDet[0]["Type"];
                }
                if (strtoupper($sHCType) == "DONE") {
                    $iBuyerAccId = CommonHelper::Get_Account_From_Type(1, $dbAdapter);
                }
            }
            if ($iBuyerAccId == 0 && ($sRefType == "BR" || $sRefType == "LRR")) {
                /*BsfGlobal.g_sErrorInfo = "Buyer/Advance Account not found";*/
                echo '<script type="text/javascript">alert("Buyer/Advance Account not found");</script>';
                return $bAns;
            }

            if ($iSubLedgerId != 0){
                $sFlatInfo = "";
                #region Over All

                $delete = $sql->delete();
                $delete->from('FA_SLDet')
                    ->where("SLId=$iSubLedgerId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //check
                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + FlatNo FROM [{0}].dbo.FlatDetails " +
                            "WHERE LeadId={1} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iLeadId);*/
                    $select->from(array("a" => 'CRM_FlatDetails'))
                        ->columns(array('FlatNo')) //check
                        ->where("a.LeadId=$iLeadId");

                } else if ($sPayType == "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + LeaseFlatNo FROM [{0}].dbo.LeaseFlatDetails " +
                            "WHERE LeadId={1} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iLeadId);*/
                    $select->from(array("a" => 'CRM_LeaseFlatDetails'))
                        ->columns(array('FlatNo'=>new Expression("a.LeaseFlatNo"))) //check
                        ->where("a.LeadId=$iLeadId");
                } else{
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE BuyerId={1} SELECT @Name FlatNo ", BsfGlobal.g_sRateAnalDBName, iLeadId);*/
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=>new Expression("a.PlotNo"))) //check
                        ->where("a.BuyerId=$iLeadId");
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $FlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($FlatLandDetail as &$FlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $FlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                }
                /*sSql = String.Format("INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.SLDet(SLId,SLTypeId,Remarks) " +
                        "SELECT {0},3,'{1}'", iSubLedgerId, iFACCId, sFlatInfo);
                cmd = new SqlCommand(sSql, conn, trans);
                cmd.ExecuteNonQuery();
                cmd.Dispose();*/

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion

                #region Company
                $sFlatInfo = "";

                $delete = $sql->delete();
                $delete->from('FA_CMSLDet')
                    ->where("SLId=$iSubLedgerId AND CompanyId=$iCompId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //check
                $subQuery = $sql->select();
                $subQuery->from(array("a" => "WF_OperationalCostCentre"))
                    ->columns(array("CostCentreId"));
                $subQuery->where("a.CompanyId=$iCompId");

                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R") {
                    $select->from(array("a" => 'CRM_FlatDetails'))
                        ->columns(array('FlatNo'));
                    $select->where->expression("a.LeadId=$iLeadId and a.CostCentreId IN ?", array($subQuery));
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + FlatNo FROM [{0}].dbo.FlatDetails " +
                            "WHERE CostCentreId IN (SELECT CostCentreId FROM [{3}].dbo.OperationalCostCentre " +
                            "WHERE CompanyId={1}) AND LeadId={2} SELECT @Name FlatNo ",
                            BsfGlobal.g_sCRMDBName, iCompId, iLeadId, BsfGlobal.g_sWorkFlowDBName);*/
                } else if ($sPayType == "R"){
                    $select->from(array("a" => 'CRM_LeaseFlatDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.LeaseFlatNo")));
                    $select->where->expression("a.LeadId=$iLeadId and a.CostCentreId IN ?", array($subQuery));
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + LeaseFlatNo FROM [{0}].dbo.LeaseFlatDetails " +
                            "WHERE CostCentreId IN (SELECT CostCentreId FROM [{3}].dbo.OperationalCostCentre " +
                            "WHERE CompanyId={1}) AND LeadId={2} SELECT @Name FlatNo ",
                            BsfGlobal.g_sCRMDBName, iCompId, iLeadId, BsfGlobal.g_sWorkFlowDBName);*/
                }
                else{
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.PlotNo")));
                    $select->where->expression("a.BuyerId=$iLeadId and a.CostCentreId IN ?", array($subQuery));
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE CostCentreId IN (SELECT CostCentreId FROM [{3}].dbo.OperationalCostCentre " +
                            "WHERE CompanyId={1}) AND BuyerId={2} SELECT @Name FlatNo ",
                            BsfGlobal.g_sRateAnalDBName, iCompId, iLeadId, BsfGlobal.g_sWorkFlowDBName);*/
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $CompanyFlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($CompanyFlatLandDetail as &$CompanyFlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $CompanyFlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                }


                $insert = $sql->insert();//check
                $insert->into('FA_CMSLDet');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'CompanyId' => $iCompId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion

                #region CC Wise
                $sFlatInfo = "";

                $delete = $sql->delete();
                $delete->from('FA_CCSLDet')
                    ->where("SLId=$iSubLedgerId AND CCId=$iFACCId");
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $select=$sql->select();
                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" && $sPayType != "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + FlatNo FROM [{0}].dbo.FlatDetails " +
                            "WHERE CostCentreId= {1} AND LeadId={2} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iCCId, iLeadId);*/
                    $select->from(array("a" => 'CRM_FlatDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.FlatNo")));
                    $select->where("a.CostCentreId=$iCCId and a.LeadId=$iLeadId");
                } else if ($sPayType == "R"){
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + LeaseFlatNo FROM [{0}].dbo.LeaseFlatDetails " +
                            "WHERE CostCentreId= {1} AND LeadId={2} SELECT @Name FlatNo ", BsfGlobal.g_sCRMDBName, iCCId, iLeadId);*/
                    $select->from(array("a" => 'CRM_LeaseFlatDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.LeaseFlatNo")));
                    $select->where("a.CostCentreId=$iCCId and a.LeadId=$iLeadId");
                } else{
                    /*sSql = String.Format("DECLARE @Name VARCHAR(200) SELECT @Name = COALESCE(@Name + ', ', '') + PlotNo FROM [{0}].dbo.LandPlotDetails " +
                            "WHERE CostCentreId= {1} AND BuyerId={2} SELECT @Name FlatNo ", BsfGlobal.g_sRateAnalDBName, iCCId, iLeadId);*/
                    $select->from(array("a" => 'Proj_LandPlotDetails'))
                        ->columns(array('FlatNo'=> new Expression("a.PlotNo")));
                    $select->where("a.CostCentreId=$iCCId and a.BuyerId=$iLeadId");
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $CCFlatLandDetail= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($CCFlatLandDetail as &$CCFlatLandDetails) {
                    $sFlatInfo = $sFlatInfo . $CCFlatLandDetails['FlatNo'] . ",";
                }
                if($sFlatInfo!=""){
                    $sFlatInfo = rtrim($sFlatInfo,',');
                }

                $insert = $sql->insert();//check
                $insert->into('FA_CCSLDet');
                $insert->Values(array(
                    'SLId' => $iSubLedgerId
                , 'CCId' => $iFACCId
                , 'SLTypeId' => 3
                , 'Remarks' => $sFlatInfo
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                #endregion
            }

            if ($arg_bRefresh == false){

                /*sSql = String.Format("INSERT INTO [{0}].dbo.ReceiptRegister(ReceiptDate,ReceiptNo,RefType,RefTypeId,ReferenceId,AccountId,SubLedgerId,CreditDays,ReceiptAmount,ChequeNo,ChequeDate,BankName,TransType,BillType,CostCentreId,CompanyId,FYearId,Remarks,FlatInfo,BlockInfo,FloorInfo) " +
                        "SELECT ReceiptDate,ReceiptNo,'"+sRefType+"',15,ReceiptId,{10},{1},0 ," + dTotal + ",ChequeNo,ChequeDate,BankName,'R',BillType,{2},{3},{4}, Narration,'{5}','{8}','{9}' FROM [{6}].dbo.ReceiptRegister WHERE ReceiptId={7} SELECT SCOPE_IDENTITY();",
                        BsfGlobal.g_sFaDBName,
                        iSubLedgerId,
                        iFACCId,
                        iCompId,
                        0,
                        sFlatName,
                        BsfGlobal.g_sCRMDBName,
                        arg_iReceiptId,
                        sBlockName,
                        sFloorName,
                        iBuyerAccId
                    );*/
                $select=$sql->select();
                $select->from(array("a" => 'CRM_ReceiptRegister'))
                    ->columns(array('ReceiptDate','ReceiptNo','RefType'=> new Expression("'$sRefType'"),'RefTypeId'=> new Expression("15"),'ReceiptId'
                    ,'AccountId'=> new Expression("$iBuyerAccId"),'SubLedgerId'=> new Expression("$iSubLedgerId"),'CreditDays'=> new Expression("1-1") ,'ReceiptAmount'=> new Expression("$dTotal")
                    ,'ChequeNo','ChequeDate','BankName','TransType'=> new Expression("'R'")
                    ,'BillType','CostCentreId'=> new Expression("$iFACCId"),'CompanyId'=> new Expression("$iCompId"),'FYearId'=> new Expression("1-1")
                    , 'Narration','FlatInfo'=> new Expression("'$sFlatName'"),'BlockInfo'=> new Expression("'$sBlockName'"),'FloorInfo'=> new Expression("'$sFloorName'")))
                    ->where("ReceiptId=$arg_iReceiptId");

                $insert = $sql->insert();//check
                $insert->into('FA_ReceiptRegister');
                $insert->columns(array('ReceiptDate', 'ReceiptNo', 'RefType','RefTypeId','ReferenceId','AccountId','SubLedgerId','CreditDays','ReceiptAmount','ChequeNo'
                ,'ChequeDate','BankName','TransType','BillType','CostCentreId','CompanyId','FYearId','Remarks','FlatInfo','BlockInfo','FloorInfo'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iReceiptId = $dbAdapter->getDriver()->getLastGeneratedValue();
                $iKeyNo = $iReceiptId;
            } else {
                //sSql = String.Format("SELECT ReceiptId,EntryId,FYearId FROM [{0}].dbo.ReceiptRegister WHERE ReferenceId={1}", BsfGlobal.g_sFaDBName, arg_iReceiptId);
                $select=$sql->select();
                $select->from(array("a" => 'FA_ReceiptRegister'))
                    ->columns(array('ReceiptId','EntryId','FYearId'))
                    ->where("ReferenceId=$arg_iReceiptId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $ReceiptDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (count($ReceiptDetails)> 0){
                    $iReceiptId = $ReceiptDetails[0]["ReceiptId"];
                    $iEntryId = $ReceiptDetails[0]["EntryId"];
                    $iEFYId = $ReceiptDetails[0]["FYearId"];
                }
                if ($iEntryId != 0){
                    $bAns = true;
                    return $bAns;
                }
            }

            #region Tax / Receipt Against Schedule ....
            if ($sBillType == "S" || $sBillType == "B" || $sBillType == "A"){

                $select=$sql->select();
                $select->from(array("a" => 'CRM_QualifierAccount'))
                    ->columns(array('QualifierId','Cnt'=>new Expression("Count(QualifierId)")))
                    ->group(array("QualifierId"))
                    ->having("Count(QualifierId)>1");//check
                $statement = $sql->getSqlStringForSqlObject($select);
                $QualifierAccount= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (count($QualifierAccount)> 0){
                    /*BsfGlobal.g_sErrorInfo = "Multiple Account found for Same Qualifier,Check Qualifier Account Setup";*/
                    echo '<script type="text/javascript">alert("Multiple Account found for Same Qualifier,Check Qualifier Account Setup");</script>';
                    return $bAns;
                }


                if ($sPayType != "PA" && $sPayType != "PB" && $sPayType != "PO" && $sPayType != "O" && $sPayType != "T" ){
                    if ($bCompoundTax == false) {
                        if ($bPartialFA == true || $bLOPartialFA == true) {
                            /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "AND (A.ReceiptTypeId IN (SELECT ReceiptTypeId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE ReceiptTypeId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R') " +
                                "OR A.OtherCostId IN (SELECT OtherCostId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE OtherCostId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R')) " +
                                "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                            $subQuery1 = $sql->select();
                            $subQuery1->from("CRM_MultiCompany")
                                ->columns(array('ReceiptTypeId'))
                                ->where("ReceiptTypeId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $subQuery2 = $sql->select();
                            $subQuery2->from("CRM_MultiCompany")
                                ->columns(array('OtherCostId'))
                                ->where("OtherCostId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptQualifier'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("B.AccountId"),'NetPer','Add_Less_Flag'
                                , 'GrossValue'=>new Expression("SUM(A.ExpValue)"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where->expression("A.ReceiptId=$arg_iReceiptId AND (A.ReceiptTypeId IN ?",array($subQuery1)," OR A.OtherCostId IN ?",array($subQuery2),")");
                            $select->group(array("A.QualTypeId","A.QualifierId","B.AccountId","A.Add_Less_Flag", "NetPer"))
                                ->having("SUM(Amount)<>0");
                        } else {
                            /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptQualifier'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("B.AccountId"),'NetPer','Add_Less_Flag'
                                , 'GrossValue'=>new Expression("SUM(A.ExpValue)"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where("A.ReceiptId=$arg_iReceiptId");
                            $select->group(array("A.QualTypeId","A.QualifierId","B.AccountId","A.Add_Less_Flag", "NetPer"))
                                ->having("SUM(A.Amount)<>0");
                        }
                    } else { // CompoundingTax
                        if ($bPartialFA == true || $bLOPartialFA == true) {
                            /*sSql = "SELECT Q.QualTypeId,A.QualifierId,B.AccountId, NetPer=Percentage,Add_Less_Flag='+', GrossValue=0, Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTax A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId " +
                                "INNER JOIN [" + BsfGlobal.g_sRateAnalDBName+ "].dbo.Qualifier_Temp Q ON A.QualifierId=Q.QualifierId " +
                                "WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "AND A.QualifierId IN (SELECT QualifierId FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.MultiCompany WHERE QualifierId<>0 AND MultiCostCentreId=" + iCCId + " AND FAUpdate='R') " +
                                "GROUP BY Q.QualTypeId,A.QualifierId,B.AccountId, Percentage HAVING SUM(Amount)<>0 ";*/

                            $subQuery1 = $sql->select();
                            $subQuery1->from("CRM_MultiCompany")
                                ->columns(array('QualifierId'))
                                ->where("QualifierId<>0 AND MultiCostCentreId=$iCCId AND FAUpdate='R'");

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptTax'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->join(array("Q" => "Proj_Qualifier_Temp"), "A.QualifierId=Q.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId'=>new Expression("Q.QualTypeId"),'QualifierId','AccountId'=>new Expression("B.AccountId")
                                ,'NetPer'=>new Expression("A.Percentage"),'Add_Less_Flag'=>new Expression("'+'")
                                , 'GrossValue'=>new Expression("1-1"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where->expression("A.ReceiptId=$arg_iReceiptId AND A.QualifierId IN ? ",array($subQuery1));
                            $select->group(array("Q.QualTypeId","A.QualifierId","B.AccountId","A.Percentage"))
                                ->having("SUM(A.Amount)<>0");

                        } else {
                            /*sSql = "SELECT Q.QualTypeId,A.QualifierId,B.AccountId, NetPer=Percentage,Add_Less_Flag='+', GrossValue=0, Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTax A " +
                                "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId " +
                                "INNER JOIN [" + BsfGlobal.g_sRateAnalDBName + "].dbo.Qualifier_Temp Q ON A.QualifierId=Q.QualifierId " +
                                "WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                                "GROUP BY Q.QualTypeId,A.QualifierId,B.AccountId, Percentage HAVING SUM(Amount)<>0 ";*/

                            $select = $sql->select();
                            $select->from(array('A' =>'CRM_ReceiptTax'))
                                ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                                ->join(array("Q" => "Proj_Qualifier_Temp"), "A.QualifierId=Q.QualifierId", array(), $select::JOIN_INNER)
                                ->columns(array('QualTypeId'=>new Expression("Q.QualTypeId"),'QualifierId','AccountId'=>new Expression("B.AccountId")
                                ,'NetPer'=>new Expression("A.Percentage"),'Add_Less_Flag'=>new Expression("'+'")
                                , 'GrossValue'=>new Expression("1-1"), 'Amount'=>new Expression("SUM(A.Amount)")))
                                ->where("A.ReceiptId=$arg_iReceiptId");
                            $select->group(array("Q.QualTypeId","A.QualifierId","B.AccountId","A.Percentage"))
                                ->having("SUM(A.Amount)<>0");
                        }
                    }
                } else { // Plot Qualifier ...
                    /*sSql = "SELECT A.QualTypeId,A.QualifierId,B.AccountId, NetPer,A.Add_Less_Flag, GrossValue=SUM(ExpValue), Amount=SUM(Amount) FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptQualifier A " +
                        "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.QualifierAccount B ON A.QualifierId=B.QualifierId WHERE A.ReceiptId=" + arg_iReceiptId + " " +
                        "GROUP BY A.QualTypeId,A.QualifierId,B.AccountId,A.Add_Less_Flag, NetPer HAVING SUM(Amount)<>0 ";*/

                    $select = $sql->select();
                    $select->from(array('A' =>'CRM_ReceiptQualifier'))
                        ->join(array("B" => "CRM_QualifierAccount"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_INNER)
                        ->columns(array('QualTypeId','QualifierId','AccountId'=>new Expression("B.AccountId"),'NetPer','Add_Less_Flag'
                        , 'GrossValue'=>new Expression("SUM(A.ExpValue)"), 'Amount'=>new Expression("SUM(A.Amount)")))
                        ->where("A.ReceiptId=$arg_iReceiptId");
                    $select->group(array("A.QualTypeId","A.QualifierId","A.Add_Less_Flag","NetPer"))
                        ->having("SUM(A.Amount)<>0");


                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $QualifierDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                for ($k = 0; $k < count($QualifierDet); $k++) {
                    $iQTypeId = $QualifierDet[$k]['QualTypeId'];
                    $iQualId = $QualifierDet[$k]['QualifierId'];
                    //iMQualId = GetQualId(iQualId, conn, trans);
                    $iMQualId = CommonHelper::GetQualId($iQualId, $dbAdapter);
                    $dRate = $QualifierDet[$k]['NetPer'];
                    $dGross = $QualifierDet[$k]['GrossValue'];
                    $dQAmt = $QualifierDet[$k]['Amount'];
                    $sTransType =$QualifierDet[$k]['Add_Less_Flag'];

                    if ($sTransType == "+"){
                        $sTransType = "D";
                    } else{
                        $sTransType = "C";
                    }

                    if ($iQTypeId!= 13) {
                        $iQSLTypeId = 8;
                        //$iQSLId = CommonHelper::GetTaxSubLedger(iMQualId, iStateId, dRate, 0, conn, trans);
                        $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iStateId, $dRate, 0, $dbAdapter);
                    } else {
                        $iQSLTypeId = 9;
//                        $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", conn, trans);
//                        $iQSLId = CommonHelper::GetSubLedgerId(iMQualId, 9, conn, trans);
                        $iMQualId = CommonHelper::Get_TermsTypeId("ROUNDING OFF", $dbAdapter);
                        $iQSLId = CommonHelper::GetSubLedgerId($iMQualId, 9, $dbAdapter);
                    }

                    $iQAccId = $QualifierDet[$k]['AccountId'];

                    if ($iQSLId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Sub ledger(Tax) not found";*/
                        echo '<script type="text/javascript">alert("Sub ledger(Tax) not found");</script>';
                        return $bAns;
                    }
                    if ($iQAccId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Tax Account not found";*/
                        echo '<script type="text/javascript">alert("Tax Account not found");</script>';
                        return $bAns;
                    }

                    if ($arg_bRefresh == true){
                        $delete= $sql->delete();
                        $delete->from('FA_ReceiptTaxDet')
                            ->where("RefId=$arg_iReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if ($bBillFAUpdate == false){
                        /*sSql = "INSERT INTO FA_ReceiptTaxDet(ReceiptId,QualTypeId,QualifierId,AccountId,SubLedgerId,RefId,GrossAmount,Amount,TransType,TaxRate) " +
                            "SELECT " + iReceiptId + "," + iQSLTypeId + "," + iQualId + "," + iQAccId + "," + iQSLId + ", " + arg_iReceiptId + "," + dGross + "," + dQAmt + ",'" + sTransType + "'," + dRate + "";
                        cmd = new SqlCommand(sSql, conn, trans);
                        cmd.ExecuteNonQuery(); cmd.Dispose();*/

                        $insert = $sql->insert();//check
                        $insert->into('FA_ReceiptTaxDet');
                        $insert->Values(array('ReceiptId' => $iReceiptId
                        , 'QualTypeId' => $iQSLTypeId
                        , 'QualifierId' => $iQualId
                        , 'AccountId' => $iQAccId
                        , 'SubLedgerId' =>$iQSLId
                        , 'RefId' => $arg_iReceiptId
                        , 'GrossAmount' =>$dGross
                        , 'Amount' => $dQAmt
                        , 'TransType' =>$sTransType
                        , 'TaxRate' => $dRate
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        if ($bPreBookAdjust == true){
                            if ($iFYearId == 0){
                                /*BsfGlobal.g_sErrorInfo = "Fiscal year not found";*/
                                echo '<script type="text/javascript">alert("Fiscal year not found");</script>';
                                return $bAns;
                            }
                            /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                                "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','D','BR'," + iBuyerAccId + "," + iQAccId + ",3," + iSubLedgerId + ",8," + iQSLId + "," +
                                "" + iFACCId + "," + dQAmt + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iReceiptId
                            , 'VoucherNo' => $sRecNo
                            , 'VoucherDate' => $dRDate //check
                            , 'TransType' => 'D'
                            , 'RefType' =>'BR'
                            , 'AccountId' =>$iBuyerAccId
                            , 'RelatedAccountId' =>$iQAccId
                            , 'SubLedgerTypeId' =>3
                            , 'SubLedgerId' =>$iSubLedgerId
                            , 'RelatedSLTypeId' =>8
                            , 'RelatedSLId' =>$iQSLId
                            , 'CostCentreId' =>$iFACCId
                            , 'Amount' =>$dQAmt
                            , 'CompanyId' =>$iCompId
                            , 'Remarks' =>$sRemarks
                            , 'Approve' =>'Y'
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                                "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','C','BR'," + iQAccId + "," + iBuyerAccId + ", 8," + iQSLId + ",3," + iSubLedgerId + "," +
                                "" + iFACCId + "," + dQAmt + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iReceiptId
                            , 'VoucherNo' => $sRecNo
                            , 'VoucherDate' => $dRDate //check
                            , 'TransType' => 'C'
                            , 'RefType' =>'BR'
                            , 'AccountId' =>$iQAccId
                            , 'RelatedAccountId' =>$iBuyerAccId
                            , 'SubLedgerTypeId' =>8
                            , 'SubLedgerId' =>$iQSLId
                            , 'RelatedSLTypeId' =>3
                            , 'RelatedSLId' =>$iSubLedgerId
                            , 'CostCentreId' =>$iFACCId
                            , 'Amount' =>$dQAmt
                            , 'CompanyId' =>$iCompId
                            , 'Remarks' =>$sRemarks
                            , 'Approve' =>'Y'
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptRegister SET FYearId=" + iFYearId + " WHERE ReceiptId=" + iReceiptId;*/
                            $update = $sql->update();
                            $update->table('CRM_ReceiptRegister')
                                ->set(array('FYearId' => $iFYearId))
                                ->where("ReceiptId=$iReceiptId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                if ($dTDSAmount != 0){
                    /*sSql = String.Format("SELECT A.QualifierId,B.QualMId,A.AccountId FROM [{0}].dbo.QualifierAccount A " +
                            "INNER JOIN [{1}].dbo.Qualifier_Temp B ON A.QualifierId=B.QualifierId WHERE B.QualTypeId=1 AND A.AccountId<>0 ",
                            BsfGlobal.g_sCRMDBName, BsfGlobal.g_sRateAnalDBName);*/
                    $select = $sql->select();
                    $select->from(array("A" => "CRM_QualifierAccount"))
                        ->join(array("B" => "Proj_Qualifier_Temp"), "A.QualifierId=B.QualifierId", array(), $select::JOIN_LEFT)
                        ->columns(array("QualifierId",'AccountId','QualMId'=>new Expression("B.QualMId")));
                    $select->where("B.QualTypeId=1 AND A.AccountId<>0");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $QualAcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if (count($QualAcc)== 1) {
                        $iQualId = $QualAcc[0]["QualifierId"];
                        $iMQualId = $QualAcc[0]["QualMId"];
                        $iQSLId = CommonHelper::GetTaxSubLedger($iMQualId, $iStateId, $dTDSRate, 11, $dbAdapter);
                        $iQSLTypeId = 8;
                        $iQAccId = $QualAcc[0]["AccountId"];
                        $dGross = $dTotal+$dTDSAmount;
                        $sTransType = "C";

                    }
                    if ($iQSLId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Sub ledger(Tax) not found";*/
                        echo '<script type="text/javascript">alert("Sub ledger(Tax) not found");</script>';
                        return $bAns;
                    }
                    if ($iQAccId == 0){
                        /*BsfGlobal.g_sErrorInfo = "Tax Account not found";*/
                        echo '<script type="text/javascript">alert("Tax Account not found");</script>';
                        return $bAns;
                    }

                    /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptTaxDet(ReceiptId,QualTypeId,QualifierId,AccountId,SubLedgerId,RefId,GrossAmount,Amount,TransType,TaxRate) " +
                        "SELECT " + iReceiptId + ", " + iQSLTypeId + "," + iQualId + "," + iQAccId + "," + iQSLId + ", " + arg_iReceiptId + "," + dGross + "," + Math.Abs(dTDSAmount) + ",'" + sTransType + "'," + dTDSRate + "";*/

                    $insert = $sql->insert();//check
                    $insert->into('FA_ReceiptTaxDet');
                    $insert->Values(array('ReceiptId' => $iReceiptId
                    , 'QualTypeId' => $iQSLTypeId
                    , 'QualifierId' => $iQualId
                    , 'AccountId' => $iQAccId
                    , 'SubLedgerId' =>$iQSLId
                    , 'RefId' => $arg_iReceiptId
                    , 'GrossAmount' =>$dGross
                    , 'Amount' => $dTDSAmount
                    , 'TransType' => $sTransType
                    , 'TaxRate' => $dTDSRate
                    ));

                    if ($dTotal == 0 && $dTDSAmount != 0){
                        if ($iFYearId== 0){
                            /*BsfGlobal.g_sErrorInfo = "Fiscal year not found";*/
                            echo '<script type="text/javascript">alert("Fiscal year not found");</script>';
                            return $bAns;
                        }

                        /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                            "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','C','BR'," + iBuyerAccId + "," + iQAccId + ",3," + iSubLedgerId + ",8," + iQSLId + "," +
                            "" + iFACCId + "," + dTDSAmount + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iReceiptId
                        , 'VoucherNo' => $sRecNo
                        , 'VoucherDate' => $dRDate //check
                        , 'TransType' => 'C'
                        , 'RefType' =>'BR'
                        , 'AccountId' =>$iBuyerAccId
                        , 'RelatedAccountId' =>$iQAccId
                        , 'SubLedgerTypeId' =>3
                        , 'SubLedgerId' =>$iSubLedgerId
                        , 'RelatedSLTypeId' =>8
                        , 'RelatedSLId' =>$iQSLId
                        , 'CostCentreId' =>$iFACCId
                        , 'Amount' =>$dTDSAmount
                        , 'CompanyId' =>$iCompId
                        , 'Remarks' =>$sRemarks
                        , 'Approve' =>'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*sSql = "INSERT INTO [" + sDBName + "].dbo.EntryTrans(RefId,VoucherNo,VoucherDate,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,Remarks,Approve)  " +
                            "VALUES (" + iReceiptId + ",'" + sRecNo + "','" + dRDate.ToString("dd-MMM-yyyy") + "','D','BR'," + iQAccId+ "," + iBuyerAccId + ", 8," + iQSLId + ",3," + iSubLedgerId + "," +
                            "" + iFACCId + "," + dTDSAmount + "," + iCompId + ",'" + BsfGlobal.Insert_SingleQuot(sRemarks) + "','Y')";*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iReceiptId
                        , 'VoucherNo' => $sRecNo
                        , 'VoucherDate' => $dRDate //check
                        , 'TransType' => 'D'
                        , 'RefType' =>'BR'
                        , 'AccountId' =>$iQAccId
                        , 'RelatedAccountId' =>$iBuyerAccId
                        , 'SubLedgerTypeId' =>8
                        , 'SubLedgerId' =>$iQSLId
                        , 'RelatedSLTypeId' =>3
                        , 'RelatedSLId' =>$iSubLedgerId
                        , 'CostCentreId' =>$iFACCId
                        , 'Amount' =>$dTDSAmount
                        , 'CompanyId' =>$iCompId
                        , 'Remarks' =>$sRemarks
                        , 'Approve' =>'Y'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptRegister SET FYearId=" + iFYearId + " WHERE ReceiptId=" + iReceiptId;*/
                        $update = $sql->update();
                        $update->table('FA_ReceiptRegister')
                            ->set(array('FYearId' => $iFYearId))
                            ->where("ReceiptId=$iReceiptId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            } else if ($sBillType == "M") {
                /*sSql = "SELECT RT.ReceiptId,RT.Amount,HBT.AccountId,TransType='C',SType=HCC.Type FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTrans RT " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCBillTrans HBT ON HBT.ChargeId=RT.PaySchId AND RT.BillRegId=HBT.BillId  " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCBillRegister HBR ON HBR.BillId=RT.BillRegId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCCharges HCC ON HCC.ChargeId=HBT.ChargeId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.HCServiceMaster HCS ON HCS.ServiceId=HBT.ServiceId " +
                    "LEFT JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.FeatureListMaster HCF ON HCF.FeatureId=HBT.FeatureId " +
                    "WHERE RT.ReceiptId=" + arg_iReceiptId;*/

                $select = $sql->select();
                $select->from(array("RT" => "CRM_ReceiptTrans"))
                    ->join(array("HBT" => "CRM_HCBillTrans"), "HBT.ChargeId=RT.PaySchId AND RT.BillRegId=HBT.BillId", array(), $select::JOIN_INNER)
                    ->join(array("HBR" => "CRM_HCBillRegister"), "HBR.BillId=RT.BillRegId", array(), $select::JOIN_INNER)
                    ->join(array("HCC" => "CRM_HCCharges"), "HCC.ChargeId=HBT.ChargeId", array(), $select::JOIN_LEFT)
                    ->join(array("HCS" => "CRM_HCServiceMaster"), "HCS.ServiceId=HBT.ServiceId", array(), $select::JOIN_LEFT)
                    ->join(array("HCF" => "CRM_FeatureListMaster"), "HCF.FeatureId=HBT.FeatureId", array(), $select::JOIN_LEFT)
                    ->columns(array("ReceiptId",'Amount','AccountId'=>new Expression("HBT.AccountId")
                    ,'TransType'=>new Expression("'C'"),'SType'=>new Expression("HCC.Type")
                    ));
                $select->where("RT.ReceiptId=$arg_iReceiptId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ReceiptDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                for ($i = 0; $i < count($ReceiptDet); $i++){
                    $sCType = $ReceiptDet[$i]["SType"];
                    $dTotal=$ReceiptDet[$i]["Amount"];
                    if (strtoupper($sHCType)== "DONE") {
                        $sTransType = $ReceiptDet[$i]["TransType"];
                        $iQAccId = $iBuyerAccId;
                        $iSLTypeId = 3;
                        $iQSLId = $iSubLedgerId;
                    } else {
                        $iQAccId = $ReceiptDet[$i]["AccountId"];
                        $sTransType = $ReceiptDet[$i]["TransType"];
                        $iSLTypeId = 0;
                        $iQSLId = 0;
                        if ($sCType == "D") {
                            $iSLTypeId = 3;
                            $iQSLId = $iSubLedgerId;
                        }
                    }

                    /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptTransDet(ReceiptId,RefId,SLTypeId,SubLedgerId,AccountId,Amount,TransType)" +
                        "SELECT " + iReceiptId + "," + arg_iReceiptId + ","+iSLTypeId+"," + iQSLId + "," + iQAccId + "," + dTotal + ",'" + sTransType + "'";*/
                    $insert = $sql->insert();//check
                    $insert->into('FA_ReceiptTransDet');
                    $insert->Values(array('ReceiptId' => $iReceiptId
                    , 'RefId' => $arg_iReceiptId
                    , 'SLTypeId' => $iSLTypeId
                    , 'SubLedgerId' => $iQSLId
                    , 'AccountId' => $iQAccId
                    , 'Amount' => $dTotal
                    , 'TransType' => $sTransType
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
            #endregion

            #region Progress Bill Update
            // No Need to Post ... KG
            if ($bBillFAUpdate == false && 1 == 0){
                if ($arg_bRefresh == true){
                    /*sSql = String.Format("DELETE FROM [{0}].dbo.ReceiptPBTrans WHERE Type='PB' AND ReferenceId={1}", BsfGlobal.g_sFaDBName, arg_iReceiptId);*/
                    $delete= $sql->delete();
                    $delete->from('FA_ReceiptPBTrans')
                        ->where("Type='PB' AND ReferenceId=$arg_iReceiptId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                /*sSql = "INSERT INTO [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptPBTrans(ReferenceId,PBillId,Amount,PBillAmount,Type) " +
                    "SELECT RT.ReceiptId,RT.BillRegId,RT.Amount,RT.NetAmount, 'PB' FROM [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptTrans RT " +
                    "INNER JOIN [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptRegister RR ON RR.ReceiptId=RT.ReceiptId " +
                    "WHERE BillRegId<>0 AND RR.ReceiptId=" + arg_iReceiptId;*/

                $select = $sql->select();
                $select->from(array("RT" => "FA_ReceiptTrans"))
                    ->join(array("RR" => "CRM_ReceiptRegister"), "RR.ReceiptId=RT.ReceiptId", array(), $select::JOIN_INNER)
                    ->columns(array("ReceiptId",'BillRegId','Amount','NetAmount'
                    ,'Type'=>new Expression("'PB'")
                    ));
                $select->where("BillRegId<>0 AND RR.ReceiptId=$arg_iReceiptId");

                $insert = $sql->insert();
                $insert->into('FA_ReceiptPBTrans');
                $insert->columns(array('ReferenceId','PBillId','Amount','PBillAmount','Type'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );


                /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "]..ReceiptPBTrans SET ReceiptId=B.ReceiptId FROM [" + BsfGlobal.g_sFaDBName + "]..ReceiptPBTrans A " +
                    "JOIN (SELECT ReceiptId,ReferenceId FROM [" + BsfGlobal.g_sFaDBName + "]..ReceiptRegister) B ON A.ReferenceId=B.ReferenceId";*/

                $update = $sql->update();
                $update->table( "FA_ReceiptPBTrans" )
                    ->set( array( 'ReceiptId' => new Expression ("B.ReceiptId FROM ReceiptPBTrans A
                    JOIN (SELECT ReceiptId,ReferenceId FROM FA_ReceiptRegister) B ON A.ReferenceId=B.ReferenceId")));
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                /*sSql = String.Format("SELECT * FROM [{0}].dbo.ReceiptTrans WHERE BillRegId<>0 AND ReceiptId={1}", BsfGlobal.g_sCRMDBName, arg_iReceiptId);*/
                /*SELECT * FROM FA_ReceiptTrans WHERE BillRegId<>0 AND ReceiptId=$arg_iReceiptId*/

                $select = $sql->select();
                $select->from(array("a" => "CRM_ReceiptTrans"));
                $select->where("BillRegId<>0 AND ReceiptId=$arg_iReceiptId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ReceiptTransDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $i = 0;
                $iPBillId = 0;
                //DataTable dtT = null;
                while ($i < count($ReceiptTransDet)) {
                    $iPBillId = $ReceiptTransDet[$i]["BillRegId"];

                    /*sSql = String.Format("SELECT ProgRegId FROM [{0}]..ProgressBillRegister WHERE PBillId={1} AND RefId=0", BsfGlobal.g_sCRMDBName, iPBillId);*/
                    $select = $sql->select();
                    $select->from(array("a" => "FA_ProgressBillRegister"));
                    $select->columns(array("ProgRegId"));
                    $select->where("PBillId=$iPBillId AND RefId=0");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $ProgressBill= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $bUpdate = false;
                    $iProgRegId = 0;
                    if (count($ProgressBill) > 0) {
                        $iProgRegId = $ProgressBill[0]["ProgRegId"];

                        $bUpdate = CommonHelper::Update_ProgressBill_Flat($iProgRegId, $dbAdapter, $iPBillId, true, $arg_bRefresh);

                        if ($bUpdate == true) {
                            /*sSql = "UPDATE [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister SET PaidAmount=B.PaidAmount FROM [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister A " +
                                "JOIN ( SELECT PBillId,PaidAmount=SUM(Amount) FROM [" + BsfGlobal.g_sFaDBName + "].dbo.ReceiptPBTrans WHERE PBillId=" + iPBillId + " GROUP BY PBillId) B " +
                                "ON A.ReferenceId=B.PBillId WHERE A.RefType='PB'";*/

                            $update = $sql->update();
                            $update->table( "FA_BillRegister" )
                                ->set( array( 'PaidAmount' => new Expression ("B.PaidAmount FROM FA_BillRegister A
                                    JOIN ( SELECT PBillId,PaidAmount=SUM(Amount) FROM FA_ReceiptPBTrans WHERE PBillId=$iPBillId GROUP BY PBillId) B
                                    ON A.ReferenceId=B.PBillId WHERE A.RefType='PB'")));
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                    }
                    $i++;
                }
            }
            #endregion

            /*sSql = "UPDATE [" + BsfGlobal.g_sCRMDBName + "].dbo.ReceiptRegister SET KeyNo=" + iKeyNo + " WHERE ReceiptId=" + arg_iReceiptId;*/
            $update = $sql->update();
            $update->table('CRM_ReceiptRegister')
                ->set(array('KeyNo' => $iKeyNo ))
                ->where("ReceiptId=$arg_iReceiptId");
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            /* $sText = "Dear Customer, Thank you for making payment of Rs." . $dTotal;
             $sMsg = "";
             using (Report.ReceiptInfo frm = new Report.ReceiptInfo())
             {
                 $sMsg = frm.Execute(arg_iReceiptId, conn, trans);
             }
             BsfGlobal.InsertBuyerAlert_Html("Buyer-Payment-Received", sText, sMsg, iLeadId, BsfGlobal.g_sCRMDBName, conn, trans);*/

        }
        $bAns = true;
        return $bAns;
    }
}
?>