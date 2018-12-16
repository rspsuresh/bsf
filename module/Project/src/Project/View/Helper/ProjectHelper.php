<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 16-08-2016
 * Time: 2:48 PM
 */

namespace Project\View\Helper;
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

class ProjectHelper extends AbstractHelper implements ServiceLocatorAwareInterface
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

    function approveFromRFC($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype="";
        if (!empty($rfcregister)) $rfctype = $rfcregister['RFCType'];

        if ($rfctype == 'Resource-Add') ProjectHelper::_resourceAdd($rfcid, $dbAdapter);
        else if ($rfctype == 'Resource-Edit') ProjectHelper::_resourceEdit($rfcid, $dbAdapter);
        else if ($rfctype == 'Resource-Delete') ProjectHelper::_resourceDelete($rfcid, $dbAdapter);
        else if ($rfctype == 'IOW-Add') ProjectHelper::_iowAdd($rfcid, $dbAdapter);
        else if ($rfctype == 'IOW-Edit') ProjectHelper::_iowEdit($rfcid, $dbAdapter);
        else if ($rfctype == 'IOW-Delete') ProjectHelper::_iowDelete($rfcid, $dbAdapter);
        else if ($rfctype == 'Resource-Group-Add') ProjectHelper::_resourceGroupAdd($rfcid, $dbAdapter);
        else if ($rfctype == 'Resource-Group-Edit') ProjectHelper::_resourceGroupEdit($rfcid, $dbAdapter);
        else if ($rfctype == 'Resource-Group-Delete') ProjectHelper::_resourceGroupDelete($rfcid, $dbAdapter);
        else if ($rfctype == 'WorkGroup-Add') ProjectHelper::_workgroupAdd($rfcid, $dbAdapter);
        else if ($rfctype == 'WorkGroup-Edit') ProjectHelper::_workgroupEdit($rfcid, $dbAdapter);
        else if ($rfctype == 'WorkGroup-Delete') ProjectHelper::_workgroupDelete($rfcid, $dbAdapter);
        else if ($rfctype == 'WorkType-Edit') ProjectHelper::_worktypeEdit($rfcid, $dbAdapter);
        else if ($rfctype == 'ProjectIOW-Add') ProjectHelper::_updateProjectIOW($rfcid, $dbAdapter);
        else if ($rfctype == 'ProjectIOW-Edit') ProjectHelper::_updateProjectIOW($rfcid, $dbAdapter);
        else if ($rfctype == 'ProjectIOW-Qty') ProjectHelper::_updateProjectIOW($rfcid, $dbAdapter);
        else if ($rfctype == 'Project-IOW-Delete') ProjectHelper::_updateProjectIOW($rfcid, $dbAdapter);
        else if ($rfctype == 'IOWPlan-Add') ProjectHelper::_updateProjectIOWPlan($rfcid, $dbAdapter);
        else if ($rfctype == 'IOWPlan-Qty') ProjectHelper::_updateProjectIOWPlan($rfcid, $dbAdapter);
        else if ($rfctype == 'IOWPlan-Edit') ProjectHelper::_updateProjectIOWPlan($rfcid, $dbAdapter);
        else if ($rfctype == 'Project-Resource') ProjectHelper::_updateProjectResource($rfcid, $dbAdapter);
//        else if ($rfctype == 'WBS-Add') ProjectHelper::_updateWBS($rfcid, $dbAdapter);
        else if ($rfctype == 'WBS-Edit') ProjectHelper::_updateWBSEdit($rfcid, $dbAdapter);
//        else if ($rfctype == 'WBS-Delete') ProjectHelper::_updateWBS($rfcid, $dbAdapter);
        else if ($rfctype == 'OtherCost-Add') ProjectHelper::_updateOtherCost($rfcid,$dbAdapter);
        else if ($rfctype == 'OtherCost-Edit') ProjectHelper::_updateOtherCost($rfcid,$dbAdapter);
        else if ($rfctype == 'Schedule-Add') ProjectHelper::_updateSchedule($rfcid,$dbAdapter);
        else if ($rfctype == 'Schedule-Edit') ProjectHelper::_updateSchedule($rfcid,$dbAdapter);
        else if ($rfctype == 'WBS-Schedule-Add') ProjectHelper::_updateSchedule($rfcid,$dbAdapter);
        else if ($rfctype == 'WBS-Schedule-Edit') ProjectHelper::_updateSchedule($rfcid,$dbAdapter);
    }


    function _newresourceAdd($rfcid,$dbAdapter) {
        $bAutoCode=false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bAutoCode=false;

        if (!empty($code)) {if ($code['GenType'] ==1) $bAutoCode=true;}

//        ProjectHelper::_resourceGroupAdd($rfcid, $dbAdapter);
//
//        $select = $sql->select();
//        $select->from('Proj_RFCResourceGroupTrans')
//            ->columns(array('ResourceGroupId','ResourceGroupName'))
//            ->where(array("RFCRegisterId"=>$rfcid));
//        $statement = $sql->getSqlStringForSqlObject($select);
//        $rfcresgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        foreach ($rfcresgroup as $trans) {
//            $resgroupid = $trans['ResourceGroupId'];
//            $resgroupName = $trans['ResourceGroupName'];
//
//            $update = $sql->update();
//            $update->table('Proj_RFCResourceTrans');
//            $update->set(array(
//                'ResourceGroupId' => $resgroupid,
//            ));
//            $update->where(array('ResourceGroupName' => $resgroupName));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//        }


        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType','ResourceGroupName'))
            ->where(array("RFCRegisterId"=>$rfcid,'ResourceId'=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $resArr =  array();
        $i=0;
        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode ==true) $rescode = ProjectHelper::_GetResCode($iTypeId,$iGroupid,$dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'],'Rate'=>$trans['Rate'],'RateType'=>$trans['RateType'],'LRate'=>$trans['LRate'],'MRate'=>$trans['MRate'],
                'ARate'=>$trans['ARate'],'WorkUnitId'=>$trans['WorkUnitId'],'WorkRate'=>$trans['WorkRate'],'MaterialType'=>$trans['MaterialType'],'RFCRegisterId'=>$rfcid,'ResourceGroupName'=>$trans['ResourceGroupName']));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($iGroupid !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $iGroupid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];


            $resArr[$i]['ResId'] =$resid;
            $resArr[$i]['ResName'] =$trans['ResourceName'];
            $i= $i+1;


            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCActivityTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_RFCActivityTrans')
                ->columns(array('ActivityType', 'ResourceId', 'Qty', 'Rate', 'Amount','ResourceName','NewId'))
                ->where(array("RFCTransId=$rfctransid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcactivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            foreach ($rfcactivity as $atrans) {
                $sql = new Sql($dbAdapter);
                $insert = $sql->insert();
                $insert->into('Proj_ResourceActivityTrans');
                $insert->Values(array('MResourceId' => $resid, 'ActivityType' => $atrans['ActivityType'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],'RFCTransId'=>$rfctransid,'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId']));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $select = $sql->select();
            $select->from('Proj_RFCSteelTrans')
                ->columns(array('SteelDescription', 'SteelDia', 'Factor', 'Wastage'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcsteel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcsteel as $strans) {
                $insert = $sql->insert();
                $insert->into('Proj_ResourceSteelTrans');
                $insert->Values(array('ResourceId' => $resid, 'SteelDescription' => $strans['SteelDescription'], 'SteelDia' => $strans['SteelDia'], 'Factor' => $strans['Factor'], 'Wastage' => $strans['Wastage'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        if ($i >0) {
            for ($j = 0; $j < $i; $j++) {
                $iResId = $resArr[$j]['ResId'];
                $sResName = $resArr[$j]['ResName'];

                $update = $sql->update();
                $update->table('Proj_ResourceActivityTrans');
                $update->set(array(
                    'ResourceId' => $iResId,
                ));
                $update->where(array('ResourceName' => $sResName,'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
    }

    function _resourceAdd($rfcid,$dbAdapter) {
        $bAutoCode=false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bAutoCode=false;

        if (!empty($code)) {if ($code['GenType'] ==1) $bAutoCode=true;}

        ProjectHelper::_resourceGroupAdd($rfcid, $dbAdapter);

        $select = $sql->select();
        $select->from('Proj_RFCResourceGroupTrans')
            ->columns(array('ResourceGroupId','ResourceGroupName'))
            ->where(array("RFCRegisterId"=>$rfcid));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcresgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfcresgroup as $trans) {
            $resgroupid = $trans['ResourceGroupId'];
            $resgroupName = $trans['ResourceGroupName'];

            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceGroupId' => $resgroupid,
            ));
            $update->where(array('ResourceGroupName' => $resgroupName));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }


        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType','ResourceGroupName'))
            ->where(array("RFCRegisterId"=>$rfcid,'ResourceId'=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $resArr =  array();
        $i=0;
        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode ==true) $rescode = ProjectHelper::_GetResCode($iTypeId,$iGroupid,$dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'],'Rate'=>$trans['Rate'],'RateType'=>$trans['RateType'],'LRate'=>$trans['LRate'],'MRate'=>$trans['MRate'],
                'ARate'=>$trans['ARate'],'WorkUnitId'=>$trans['WorkUnitId'],'WorkRate'=>$trans['WorkRate'],'MaterialType'=>$trans['MaterialType'],'RFCRegisterId'=>$rfcid,'ResourceGroupName'=>$trans['ResourceGroupName']));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($iGroupid !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $iGroupid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];


            $resArr[$i]['ResId'] =$resid;
            $resArr[$i]['ResName'] =$trans['ResourceName'];
            $i= $i+1;


            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCActivityTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_RFCActivityTrans')
                ->columns(array('ActivityType', 'ResourceId', 'Qty', 'Rate', 'Amount','ResourceName','NewId'))
                ->where(array("RFCTransId=$rfctransid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcactivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            foreach ($rfcactivity as $atrans) {
                $sql = new Sql($dbAdapter);
                $insert = $sql->insert();
                $insert->into('Proj_ResourceActivityTrans');
                $insert->Values(array('MResourceId' => $resid, 'ActivityType' => $atrans['ActivityType'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],'RFCTransId'=>$rfctransid,'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId']));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $select = $sql->select();
            $select->from('Proj_RFCSteelTrans')
                ->columns(array('SteelDescription', 'SteelDia', 'Factor', 'Wastage'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcsteel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcsteel as $strans) {
                $insert = $sql->insert();
                $insert->into('Proj_ResourceSteelTrans');
                $insert->Values(array('ResourceId' => $resid, 'SteelDescription' => $strans['SteelDescription'], 'SteelDia' => $strans['SteelDia'], 'Factor' => $strans['Factor'], 'Wastage' => $strans['Wastage'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        if ($i >0) {
            for ($j = 0; $j < $i; $j++) {
                $iResId = $resArr[$j]['ResId'];
                $sResName = $resArr[$j]['ResName'];

                $update = $sql->update();
                $update->table('Proj_ResourceActivityTrans');
                $update->set(array(
                    'ResourceId' => $iResId,
                ));
                $update->where(array('ResourceName' => $sResName,'NewId'=>1,'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
    }

    function _resourceEdit($rfcid,$dbAdapter) {
        ProjectHelper::_newresourceAdd($rfcid, $dbAdapter);

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $iresid = $trans['ResourceId'];

            $update = $sql->update();
            $update->table('Proj_Resource');
            $update->set(array(
                'Code' => $trans['Code'], 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'],'Rate'=>$trans['Rate'],'RateType'=>$trans['RateType'],'LRate'=>$trans['LRate'],'MRate'=>$trans['MRate'],
                'ARate'=>$trans['ARate'],'WorkUnitId'=>$trans['WorkUnitId'],'WorkRate'=>$trans['WorkRate'],'MaterialType'=>$trans['MaterialType'],'RFCRegisterId'=>$rfcid));
            $update->where(array('ResourceId' => $iresid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $delete = $sql->delete();
            $delete->from('Proj_ResourceActivityTrans')
                ->where(array("MResourceId" => $iresid));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCActivityTrans')
                ->columns(array('ActivityType', 'ResourceId', 'Qty', 'Rate', 'Amount','ResourceName','NewId'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcactivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcactivity as $atrans) {
                $insert = $sql->insert();
                $insert->into('Proj_ResourceActivityTrans');
                $insert->Values(array('MResourceId' => $iresid, 'ActivityType' => $atrans['ActivityType'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],'RFCTransId'=>$rfctransid,'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId']));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $delete = $sql->delete();
            $delete->from('Proj_ResourceSteelTrans')
                ->where(array("ResourceId" => $iresid));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCSteelTrans')
                ->columns(array('SteelDescription', 'SteelDia', 'Factor', 'Wastage'))
                ->where(array("RFCTransId='$rfctransid'"));

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcsteel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcsteel as $strans) {
                $insert = $sql->insert();
                $insert->into('Proj_ResourceSteelTrans');
                $insert->Values(array('ResourceId' => $iresid, 'SteelDescription' => $strans['SteelDescription'], 'SteelDia' => $strans['SteelDia'], 'Factor' => $strans['Factor'], 'Wastage' => $strans['Wastage'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
    }

    function _resourceDelete($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCResourceDeleteTrans')
            ->columns(array('ResourceId'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $resid = $trans['ResourceId'];

//            $delete = $sql->delete();
//            $delete->from('Proj_ResourceActivityTrans')
//                ->where(array("MResourceId" => $resid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_ResourceSteelTrans')
//                ->where(array("ResourceId" => $resid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $delete = $sql->delete();
//            $delete->from('Proj_Resource')
//                ->where(array("ResourceId" => $resid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $iRGId= 0;
            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array('ResourceGroupId'))
                ->where(array('ResourceId' =>$resid));
            $statement = $sql->getSqlStringForSqlObject($select);
            $parent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($parent)) $iRGId = $parent['ResourceGroupId'];


            $update = $sql->update();
            $update->table('Proj_Resource');
            $update->set(array(
                'DeleteFlag' => 1,
            ));
            $update->where(array('ResourceId' => $resid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($iRGId !=0) {
                $select = $sql->select();
                $select->from('Proj_Resource')
                    ->columns(array('ResourceGroupId'))
                    ->where(array('ResourceGroupId'=>$iRGId,'DeleteFlag'=>0));

                $select1 = $sql->select();
                $select1->from(array('a' => 'Proj_RFCResourceTrans'))
                    ->join(array('b' => 'Proj_RFCRegister'), 'a.RFCRegisterId=b.RFCRegisterId', array(), $select1::JOIN_INNER)
                    ->columns(array('ResourceGroupId'))
                    ->where("b.Approve<>'Y' and a.ResourceGroupId=$iRGId");
                $select->combine($select1, 'UNION ALL');
                $statement = $sql->getSqlStringForSqlObject($select);
                $parent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (empty($parent)) {
                    $update = $sql->update();
                    $update->table('Proj_ResourceGroup');
                    $update->set(array(
                        'GroupUsed' => 0,
                    ));
                    $update->where(array('ResourceGroupId' => $iRGId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
        }
    }

    function _iowAdd($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        $bWAutoCode=false;

        $select = $sql->select();
        $select->from('Proj_WorkGroupCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $wcode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bWAutoCode=false;

        if (!empty($wcode)) {if ($wcode['GenType'] ==1) $bWAutoCode=true;}

        $select = $sql->select();
        $select->from(array('a' => 'Proj_RFCWorkGroupTrans'))
            ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness'), $select:: JOIN_INNER)
            ->columns(array('RFCTransId','SerialNo','WorkTypeId','WorkGroupName','AutoRateAnalysis','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty'))
            ->where(array("a.RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $wgcode = $trans['SerialNo'];
            if ($bWAutoCode ==true) $wgcode = ProjectHelper::_GetWorkGroupCode($trans['WorkTypeId'],$dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupMaster');
            $insert->Values(array('SerialNo'=>$wgcode,'WorkTypeId' => $trans['WorkTypeId'], 'WorkGroupName' => $trans['WorkGroupName'],
                'AutoRateAnalysis' => $trans['AutoRateAnalysis'],
                'ConcreteMix' => $trans['ConcreteMix'], 'Cement' => $trans['Cement'], 'Sand' => $trans['Sand'], 'Metal' => $trans['Metal'],
                'Thickness' => $trans['Thickness'], 'WorkingQty' => $trans['WorkingQty'], 'RWorkingQty' => $trans['RWorkingQty'],
                'CementRatio' => $trans['CementRatio'], 'SandRatio' => $trans['SandRatio'], 'MetalRatio' => $trans['MetalRatio'], 'ThickQty' => $trans['ThickQty'], 'RFCRegisterId' => $rfcid));

            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $workGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

            $update = $sql->update();
            $update->table('Proj_RFCIOWTrans');
            $update->set(array(
                'WorkGroupId' => $workGroupId,'WorkTypeId'=>$trans['WorkTypeId']
            ));
            $update->where(array('WorkGroupName'=>$trans['WorkGroupName'],'WorkGroupId' => 0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCWorkGroupTrans');
            $update->set(array(
                'WorkGroupId' => $workGroupId,
            ));
            $update->where(array('RFCTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $update = $sql->update();
//            $update->table('Proj_IOWMaster');
//            $update->set(array(
//                'WorkGroupId' => $workGroupId,'WorkTypeId'=>$trans['WorkTypeId']
//            ));
//            $update->where(array('WorkGroupName'=>$trans['WorkGroupName'],'WorkGroupId' => 0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $select = $sql->select();
//            $select->from('Proj_RFCWorkGroupAnalysis')
//                ->columns(array('IncludeFlag', 'ReferenceId', 'ResourceId', 'Qty', 'CFormula', 'Type'))
//                ->where(array("RFCTransId='$rfctransid'"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $rfcanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $select = $sql->select();
        $select->from('Proj_RFCIOWTrans')
            ->columns(array('RFCTransId', 'WorkGroupId','WorkTypeId', 'ParentId','ParentName','SerialNo','RefSerialNo','Header','Specification','ShortSpec', 'UnitId', 'Rate','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','MixType','SRate','RRate','Rate','ParentText','WorkGroupName'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $iWidth=5;
        $resArr =  array();
        $k=0;

        $resIOWId =  array();
        $n=0;


        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];

            $iparentid = $trans['ParentId'];
            $iworkgroupid = $trans['WorkGroupId'];
            $anewSlNo = ProjectHelper::_GetIOWSlNo(0,$iparentid, $iworkgroupid, $dbAdapter);

            $newSlNo = $anewSlNo[0];
            $PSlNo = $anewSlNo[1];

            $slNo ="";
            $sPre="";
            $iVNo="";
            $arrSlNo = explode('.', $newSlNo);
            for ($i = 0; $i < count($arrSlNo) ; $i++) {
                $iVNo = $arrSlNo[$i];
                $iLen = $iWidth - strlen($iVNo);
                $sPre = "";
                for ($j = 1; $j <= $iLen; $j++)
                {
                    $sPre = $sPre . "0";
                }
                $slNo = $slNo . $sPre . $iVNo . '.';
            }
            $slNo = rtrim($slNo,'.');

            $iIOWs=0;
            $iUnitId=$this->bsf->isNullCheck($trans['UnitId'],'number');
            if ($iUnitId !=0) $iIOWs=1;

            $insert = $sql->insert();
            $insert->into('Proj_IOWMaster');
            $insert->Values(array('WorkGroupId' => $trans['WorkGroupId'],'WorkTypeId' => $trans['WorkTypeId'],'ParentId' => $trans['ParentId'],'ParentName'=>$trans['ParentName'],
                'SerialNo' => $newSlNo, 'RefSerialNo'=> $trans['RefSerialNo'],'Header'=> $trans['Header'],
                'Specification' => $trans['Specification'],'ShortSpec' => $trans['ShortSpec'], 'UnitId' => $iUnitId,'IOWs' => $iIOWs,
                'WorkingQty'=> $trans['WorkingQty'],'RWorkingQty'=> $trans['RWorkingQty'],'CementRatio'=> $trans['CementRatio'],
                'SandRatio'=> $trans['SandRatio'],'MetalRatio'=> $trans['MetalRatio'],'ThickQty'=> $trans['ThickQty'],'MixType'=> $trans['MixType'],'SRate'=> $trans['SRate'],'RRate'=> $trans['RRate'],'Rate'=> $trans['Rate'],'ParentText'=> $trans['ParentText'],'SlNo'=>$slNo,'PSlNo'=> $PSlNo,'WorkGroupName'=>$trans['WorkGroupName'], 'RFCRegisterId'=>$rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iowid = $dbAdapter->getDriver()->getLastGeneratedValue();

            if (($trans['ParentName'] !="" &&  $iparentid ==0) || ($trans['WorkGroupName'] !=0 && $iworkgroupid==0)) {
                $resIOWId[$n]['IOWId'] = $iowid;
                $n = $n + 1;
            }

            $update = $sql->update();
            $update->table('Proj_RFCIOWTrans');
            $update->set(array(
                'IOWId' => $iowid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $sSpec = $trans['Specification'];

            $update = $sql->update();
            $update->table('Proj_RFCIOWTrans');
            $update->set(array(
                'ParentId' => $iowid,'WorkGroupId'=>$trans['WorkGroupId'],'WorkTypeId'=>$trans['WorkTypeId']
            ));
            $update->where("convert(varchar,ParentName) = '$sSpec' and RFCRegisterId = $rfcid and ParentId =0");
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $resArr[$k]['IOWId'] =$iowid;
            $resArr[$k]['ParentName'] =$sSpec;
            $resArr[$k]['WorkGroupId'] =$trans['WorkGroupId'];
            $resArr[$k]['WorkTypeId'] =$trans['WorkTypeId'];
            $k= $k+1;


//            $update = $sql->update();
//            $update->table('Proj_IOWMaster');
//            $update->set(array(
//                'ParentId' => $iowid,'WorkGroupId'=>$trans['WorkGroupId'],'WorkTypeId'=>$trans['WorkTypeId']
//            ));
//            $update->where("convert(varchar,ParentName) = '$sSpec' and ParentId =0");
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCIOWRate')
                ->columns(array('IOWId'=> new Expression("'$iowid'"),'WastageAmt', 'BaseRate', 'QualifierValue','TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue','RTotalRate', 'RNetRate'))
                ->where(array("RFCTransId='$rfctransid'"));

            $insert = $sql->insert();
            $insert->into('Proj_IOWRate');
            $insert->columns(array('IOWId','WastageAmt', 'BaseRate', 'QualifierValue','TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue','RTotalRate', 'RNetRate'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCIOWQualTrans')
                ->columns(array('IOWId'=> new Expression("'$iowid'"), 'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                    'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'))
                ->where(array("RFCTransId='$rfctransid'"));

            $insert = $sql->insert();
            $insert->into('Proj_IOWQualTrans');
            $insert->columns(array('IOWId', 'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $select = $sql->select();
            $select->from('Proj_RFCRateAnalysis')
                ->columns(array('IOWId'=> new Expression("'$iowid'"),'IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'))
                ->where(array("RFCTransId='$rfctransid'"));
            $select->order('SortId ASC');

            $insert = $sql->insert();
            $insert->into('Proj_IOWRateAnalysis');
            $insert->columns(array('IOWId', 'IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        if ($k >0) {
            for ($j = 0; $j < $k; $j++) {
                $iIOWId = $resArr[$j]['IOWId'];
                $sParentName = $resArr[$j]['ParentName'];
                $iWorkGroupId = $resArr[$j]['WorkGroupId'];
                $iWorkTypeId = $resArr[$j]['WorkTypeId'];

                $update = $sql->update();
                $update->table('Proj_IOWMaster');
                $update->set(array(
                    'ParentId' => $iIOWId,'WorkGroupId'=>$iWorkGroupId,'WorkTypeId'=>$iWorkTypeId
                ));
                $update->where("convert(varchar,ParentName) = '$sParentName' and ParentId =0");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
        if ($n >0) {
            for ($x = 0; $x < $n; $x++) {
                $iIOWId = $resIOWId[$x]['IOWId'];
                $iWGId=0;
                $iPId=0;

                $select = $sql->select();
                $select->from('Proj_IOWMaster')
                    ->columns(array('WorkGroupId','ParentId'))
                    ->where(array('IOWId'=>$iIOWId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $iowmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($iowmaster)) {
                    $iWGId = $iowmaster['WorkGroupId'];
                    $iPId = $iowmaster['ParentId'];
                }

                $anewSlNo = ProjectHelper::_GetIOWSlNo($iIOWId,$iPId, $iWGId, $dbAdapter);

                $newSlNo = $anewSlNo[0];
                $PSlNo = $anewSlNo[1];

                $slNo ="";
                $sPre="";
                $iVNo="";
                $arrSlNo = explode('.', $newSlNo);
                for ($i = 0; $i < count($arrSlNo) ; $i++) {
                    $iVNo = $arrSlNo[$i];
                    $iLen = $iWidth - strlen($iVNo);
                    $sPre = "";
                    for ($j = 1; $j <= $iLen; $j++)
                    {
                        $sPre = $sPre . "0";
                    }
                    $slNo = $slNo . $sPre . $iVNo . '.';
                }
                $slNo = rtrim($slNo,'.');

                $update = $sql->update();
                $update->table('Proj_IOWMaster');
                $update->set(array(
                    'SerialNo' => $newSlNo,'SlNo'=>$slNo,'PSlNo'=> $PSlNo
                ));
                $update->where(array('IOWId'=>$iIOWId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] ==1) $bAutoCode=true;

        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $update = $sql->update();
            $update->table('Proj_RFCRateAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_IOWRateAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

//        $select = $sql->select();
//        $select->from(array('a' => 'Proj_RFCWorkGroupTrans'))
//            ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness'), $select:: JOIN_INNER)
//            ->columns(array('RFCTransId','WorkTypeId','WorkGroupName','AutoRateAnalysis','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty'))
//            ->where(array("a.RFCRegisterId='$rfcid'"));
//        $statement = $sql->getSqlStringForSqlObject($select);
//        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        foreach ($rfctrans as $trans) {
//            $rfctransid = $trans['RFCTransId'];
//
//            $insert = $sql->insert();
//            $insert->into('Proj_WorkGroupMaster');
//            $insert->Values(array('WorkTypeId' => $trans['WorkTypeId'], 'WorkGroupName' => $trans['WorkGroupName'],
//                'AutoRateAnalysis' => $trans['AutoRateAnalysis'],
//                'ConcreteMix' => $trans['ConcreteMix'], 'Cement' => $trans['Cement'], 'Sand' => $trans['Sand'], 'Metal' => $trans['Metal'],
//                'Thickness' => $trans['Thickness'], 'WorkingQty' => $trans['WorkingQty'], 'RWorkingQty' => $trans['RWorkingQty'],
//                'CementRatio' => $trans['CementRatio'], 'SandRatio' => $trans['SandRatio'], 'MetalRatio' => $trans['MetalRatio'], 'ThickQty' => $trans['ThickQty'], 'RFCRegisterId' => $rfcid));
//
//            $statement = $sql->getSqlStringForSqlObject($insert);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $workGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//            $update = $sql->update();
//            $update->table('Proj_RFCIOWTrans');
//            $update->set(array(
//                'WorkGroupId' => $workGroupId,'WorkTypeId'=>$trans['WorkTypeId']
//            ));
//            $update->where(array('WorkGroupName'=>$trans['WorkGroupName'],'WorkGroupId' => 0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $update = $sql->update();
//            $update->table('Proj_RFCWorkGroupTrans');
//            $update->set(array(
//                'WorkGroupId' => $workGroupId,
//            ));
//            $update->where(array('RFCTransId' => $rfctransid));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $update = $sql->update();
//            $update->table('Proj_IOWMaster');
//            $update->set(array(
//                'WorkGroupId' => $workGroupId,'WorkTypeId'=>$trans['WorkTypeId']
//            ));
//            $update->where(array('WorkGroupName'=>$trans['WorkGroupName'],'WorkGroupId' => 0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
////            $select = $sql->select();
////            $select->from('Proj_RFCWorkGroupAnalysis')
////                ->columns(array('IncludeFlag', 'ReferenceId', 'ResourceId', 'Qty', 'CFormula', 'Type'))
////                ->where(array("RFCTransId='$rfctransid'"));
////            $statement = $sql->getSqlStringForSqlObject($select);
////            $rfcanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//        }
    }

    function _iowEdit($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCIOWTrans')
            ->columns(array('RFCTransId','IOWId','WorkGroupId','WorkTypeId', 'ParentId','ParentName', 'SerialNo','RefSerialNo','Header','Specification','ShortSpec', 'UnitId', 'Rate','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','MixType','SRate','RRate','Rate','ParentText'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//        $resArr =  array();
//        $k=0;

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $iowid = $trans['IOWId'];

            $iIOWs=0;
            $iUnitId=$this->bsf->isNullCheck($trans['UnitId'],'number');
            if ($iUnitId !=0) $iIOWs=1;

            $update = $sql->update();
            $update->table('Proj_IOWMaster');
            $update->set(array(
                'WorkGroupId' => $trans['WorkGroupId'], 'WorkTypeId' => $trans['WorkTypeId'],'ParentId' => $trans['ParentId'],'ParentName'=>$trans['ParentName'],
                'SerialNo' => $trans['SerialNo'], 'RefSerialNo'=> $trans['RefSerialNo'],'Header'=> $trans['Header'],
                'Specification' => $trans['Specification'],'ShortSpec' => $trans['ShortSpec'], 'UnitId' => $iUnitId,'IOWs' => $iIOWs, 'Rate' => $trans['Rate'],
                'WorkingQty'=> $trans['WorkingQty'],'RWorkingQty'=> $trans['RWorkingQty'],'CementRatio'=> $trans['CementRatio'],
                'SandRatio'=> $trans['SandRatio'],'MetalRatio'=> $trans['MetalRatio'],'ThickQty'=> $trans['ThickQty'],'MixType'=> $trans['MixType'],'SRate'=> $trans['SRate'],'RRate'=> $trans['RRate'],'Rate'=>$trans['Rate'],'ParentText'=>$trans['ParentText'],'RFCRegisterId'=>$rfcid));
            $update->where(array('IOWId' => $iowid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $update = $sql->update();
//            $update->table('Proj_RFCIOWTrans');
//            $update->set(array(
//                'ParentId' => $iowid,$trans['WorkGroupId'],$trans['WorkTypeId']
//            ));
//            $update->where(array('ParentName' => $trans['Specification'],'RFCRegisterId'=>$rfcid,'ParentId' => 0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//            $resArr[$k]['IOWId'] =$iowid;
//            $resArr[$k]['ParentName'] =$trans['Specification'];
//            $resArr[$k]['WorkGroupId'] =$trans['WorkGroupId'];
//            $resArr[$k]['WorkTypeId'] =$trans['WorkTypeId'];
//            $k= $k+1;

//            $update = $sql->update();
//            $update->table('Proj_IOWMaster');
//            $update->set(array(
//                'ParentId' => $iowid,
//            ));
//            $update->where(array('ParentName' => $trans['Specification'],'ParentId' => 0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_IOWRate')
                ->where(array("IOWId" => $iowid));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_IOWQualTrans')
                ->where(array("IOWId" => $iowid));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_IOWRateAnalysis')
                ->where(array("IOWId" => $iowid));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_RFCIOWRate')
                ->columns(array('IOWId'=> new Expression("'$iowid'"),'WastageAmt', 'BaseRate', 'QualifierValue','TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue','RTotalRate', 'RNetRate'))
                ->where(array("RFCTransId='$rfctransid'"));

            $insert = $sql->insert();
            $insert->into('Proj_IOWRate');
            $insert->columns(array('IOWId','WastageAmt', 'BaseRate', 'QualifierValue','TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue','RTotalRate', 'RNetRate'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCIOWQualTrans')
                ->columns(array('IOWId'=> new Expression("'$iowid'"), 'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                    'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'))
                ->where(array("RFCTransId='$rfctransid'"));

            $insert = $sql->insert();
            $insert->into('Proj_IOWQualTrans');
            $insert->columns(array('IOWId', 'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCRateAnalysis')
                ->columns(array('IOWId'=> new Expression("'$iowid'"),'IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'))
                ->where(array("RFCTransId='$rfctransid'"));
            $select->order('SortId ASC');

            $insert = $sql->insert();
            $insert->into('Proj_IOWRateAnalysis');
            $insert->columns(array('IOWId', 'IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        }

//        if ($k >0) {
//            for ($j = 0; $j < $k; $j++) {
//                $iIOWId = $resArr[$j]['IOWId'];
//                $sParentName = $resArr[$j]['ParentName'];
//                $iWorkGroupId = $resArr[$j]['WorkGroupId'];
//                $iWorkTypeId = $resArr[$j]['WorkTypeId'];
//
//                $update = $sql->update();
//                $update->table('Proj_IOWMaster');
//                $update->set(array(
//                    'ParentId' => $iIOWId,'WorkGroupId'=>$iWorkGroupId,'WorkTypeId'=>$iWorkTypeId
//                ));
//                $update->where("convert(varchar,ParentName) = '$sParentName' and ParentId =0");
//                $statement = $sql->getSqlStringForSqlObject($update);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//            }
//        }

        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] ==1) $bAutoCode=true;

        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $update = $sql->update();
            $update->table('Proj_RFCRateAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_IOWRateAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

    }

    function _iowDelete($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCIOWDeleteTrans')
            ->columns(array('IOWId'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $iowid = $trans['IOWId'];

//            $delete = $sql->delete();
//            $delete->from('Proj_IOWRateAnalysis')
//                ->where(array("IOWId" => $iowid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_IOWQualTrans')
//                ->where(array("IOWId" => $iowid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_IOWRate')
//                ->where(array("IOWId" => $iowid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//            $delete = $sql->delete();
//            $delete->from('Proj_IOWMaster')
//                ->where(array("IOWId" => $iowid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $update = $sql->update();
            $update->table('Proj_IOWMaster');
            $update->set(array(
                'DeleteFlag' => 1,
            ));
            $update->where(array('IOWId' => $iowid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _resourceGroupAdd($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        $select->from('Proj_RGCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bAutoCode=false;

        if (!empty($code)) {if ($code['GenType'] ==1) $bAutoCode=true;}

        $select = $sql->select();
        $select->from('Proj_RFCResourceGroupTrans')
            ->columns(array('RFCTransId','TypeId','ParentId','ParentName','Code','ResourceGroupName','NewGroup'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        foreach ($rfctrans as $trans) {
            $iNew=$trans['NewGroup'];
            $iParentId=$this->bsf->isNullCheck($trans['ParentId'],'number');
            $sGroupName = $trans['ResourceGroupName'];
            $sParentName = $trans['ParentName'];
            if ($iNew==1) {
                $select = $sql->select();
                $select->from('Proj_ResourceGroup')
                    ->columns(array('ResourceGroupId'))
                    ->where(array("ResourceGroupName='$sParentName'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resgroupmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($resgroupmaster))  $iParentId =$resgroupmaster['ResourceGroupId'];
            }

            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $iParentId;
            if ($bAutoCode ==true) $rescode = ProjectHelper::_GetResourceGroupCode($iTypeId,$iGroupid,$dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_ResourceGroup');
            $insert->Values(array('TypeId'=>$trans['TypeId'],'ParentId'=>$iParentId,'Code'=>$rescode,
                'ResourceGroupName'=>$sGroupName,'LastLevel'=>1,'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $resGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

            if ($iParentId !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'LastLevel' => 0,
                ));
                $update->where(array('ResourceGroupId' => $iParentId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $update = $sql->update();
            $update->table('Proj_RFCResourceGroupTrans');
            $update->set(array(
                'ResourceGroupId' => $resGroupId,
            ));
            $update->where(array('RFCTransId' => $trans['RFCTransId']));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        }
    }

    function _resourceGroupEdit($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCResourceGroupTrans')
            ->columns(array('ResourceGroupId','TypeId','ParentId','Code','ResourceGroupName'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $resgroupid = $trans['ResourceGroupId'];

            $update = $sql->update();
            $update->table('Proj_ResourceGroup');
            $update->set(array(
                'TypeId'=>$trans['TypeId'],'ParentId'=>$trans['ParentId'],'Code'=>$trans['Code'],
                'ResourceGroupName'=>$trans['ResourceGroupName'],'RFCRegisterId' => $rfcid));
            $update->where(array('ResourceGroupId' => $resgroupid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($trans['ParentId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'LastLevel' => 0,
                ));
                $update->where(array('ResourceGroupId' => $trans['ParentId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

        }
    }

    function _resourceGroupDelete($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCResGroupDeleteTrans')
            ->columns(array('ResourceGroupId'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $resgroupid = $trans['ResourceGroupId'];

            $iParentId= 0;
            $select = $sql->select();
            $select->from('Proj_ResourceGroup')
                ->columns(array('ParentId'))
                ->where(array('ResourceGroupId' =>$resgroupid));
            $statement = $sql->getSqlStringForSqlObject($select);
            $parent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($parent)) $iParentId = $parent['ParentId'];

            $update = $sql->update();
            $update->table('Proj_ResourceGroup');
            $update->set(array(
                'DeleteFlag' => 1,
            ));
            $update->where(array('ResourceGroupId' => $resgroupid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if ($iParentId !=0) {
                $select = $sql->select();
                $select->from('Proj_ResourceGroup')
                    ->columns(array('ResourceGroupId'))
                    ->where(array('ParentId' =>$iParentId,'DeleteFlag'=>0));

                $select1 = $sql->select();
                $select1->from(array('a' => 'Proj_RFCResourceGroupTrans'))
                    ->join(array('b' => 'Proj_RFCRegister'), 'a.RFCRegisterId=b.RFCRegisterId', array(), $select1::JOIN_INNER)
                    ->columns(array('ResourceGroupId'))
                    ->where("b.Approve<>'Y' and a.ParentId=$iParentId");
                $select->combine($select1, 'UNION ALL');
                $statement = $sql->getSqlStringForSqlObject($select);
                $parent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (empty($parent)) {
                    $update = $sql->update();
                    $update->table('Proj_ResourceGroup');
                    $update->set(array(
                        'LastLevel' => 0,
                    ));
                    $update->where(array('ResourceGroupId' => $iParentId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
//            $delete = $sql->delete();
//            $delete->from('Proj_ResourceGroup')
//                ->where(array("ResourceGroupId" => $resgroupid));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _workgroupAdd($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bAutoCode=false;

        if (!empty($code)) {if ($code['GenType'] ==1) $bAutoCode=true;}

        $select->from('Proj_WorkGroupCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $wcode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bWAutoCode=false;

        if (!empty($wcode)) {if ($wcode['GenType'] ==1) $bWAutoCode=true;}

        $select = $sql->select();
        $select->from(array('a' => 'Proj_RFCWorkGroupTrans'))
            ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness'), $select:: JOIN_INNER)
            ->columns(array('RFCTransId','WorkTypeId','SerialNo','WorkGroupName','AutoRateAnalysis','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','UnitId'))
            ->where(array("a.RFCRegisterId='$rfcid'"));

//        $select = $sql->select();
//        $select->from('Proj_RFCWorkGroupTrans')
//            ->columns(array('RFCTransId','WorkTypeId','SerialNo','WorkGroupName','AutoRateAnalysis','ConcreteMix','Cement','Sand','Metal','Thickness','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','UnitId'))
//            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $wgcode = $trans['SerialNo'];
            $iTypeId = $trans['WorkTypeId'];
            if ($bWAutoCode ==true) $wgcode = ProjectHelper::_GetWorkGroupCode($iTypeId,$dbAdapter);


            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupMaster');
            $insert->Values(array('WorkTypeId'=>$trans['WorkTypeId'],'WorkGroupName'=>$trans['WorkGroupName'],'SerialNo'=>$wgcode,
                'AutoRateAnalysis'=>$trans['AutoRateAnalysis'],
                'ConcreteMix'=>$trans['ConcreteMix'],'Cement'=>$trans['Cement'],'Sand'=>$trans['Sand'],'Metal'=>$trans['Metal'],
                'Thickness'=>$trans['Thickness'],'WorkingQty'=>$trans['WorkingQty'],'RWorkingQty'=>$trans['RWorkingQty'],
                'CementRatio'=>$trans['CementRatio'],'SandRatio'=>$trans['SandRatio'],'MetalRatio'=>$trans['MetalRatio'],'ThickQty'=>$trans['ThickQty'],'UnitId'=>$trans['UnitId'],'RFCRegisterId'=>$rfcid));

            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $workGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

            $update = $sql->update();
            $update->table('Proj_RFCWorkGroupTrans');
            $update->set(array(
                'WorkGroupId' => $workGroupId,
            ));
            $update->where(array('RFCTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $delete = $sql->delete();
//            $delete->from('Proj_WorkGroupWorkChecklistTrans')
//                ->where(array("WorkGroupId" => $workGroupId));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_WorkGroupQualityChecklistTrans')
//                ->where(array("WorkGroupId" => $workGroupId));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_WorkGroupSafetyChecklistTrans')
//                ->where(array("WorkGroupId" => $workGroupId));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupWorkChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupWorkChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupQualityChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupQualityChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupSafetyChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupSafetyChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//            if ($trans['ActivityResGroup']==1) {
//                $insert = $sql->insert();
//                $insert->into('Proj_ResourceGroup');
//                $insert->Values(array('TypeId' => 4, 'ParentId' => 0, 'Code' => $trans['SerialNo'],
//                    'ResourceGroupName' => $trans['WorkGroupName'], 'WorkGroupId' => $workGroupId,
//                    'RFCRegisterId' => $rfcid));
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                $resGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();
//            }
//
//            if ($trans['ActivityResource']==1) {
//                $rescode = $trans['SerialNo'];
//                if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode(4, $resGroupId, $dbAdapter);
//                $insert = $sql->insert();
//                $insert->into('Proj_Resource');
//                $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['WorkGroupName'], 'ResourceGroupId' => $resGroupId, 'TypeId' => 4,
//                    'UnitId' => $trans['UnitId'], 'AnalysisAQty' => 1,
//                    'AnalysisMQty' => 1, 'Rate' => 0, 'RateType' => 'L', 'LRate' => 0, 'MRate' => 0,
//                    'ARate' => 0, 'WorkUnitId' => 0, 'WorkRate' => 0, 'RFCRegisterId' => $rfcid,'WorkGroupId' => $workGroupId));
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//            }

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupAnalysis')
                ->columns(array('IncludeFlag', 'ReferenceId', 'ResourceId', 'Qty', 'CFormula','Type','SortId','TransType','Description','ResourceName','NewId'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcanal as $atrans) {

                $insert = $sql->insert();
                $insert->into('Proj_WorkGroupAnalysis');
                $insert->Values(array('WorkGroupId' => $workGroupId, 'IncludeFlag' => $atrans['IncludeFlag'], 'ReferenceId' => $atrans['ReferenceId'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'],
                    'CFormula' => $atrans['CFormula'],'Type'=>$atrans['Type'],'SortId'=>$atrans['SortId'],'TransType'=>$atrans['TransType'],'Description'=>$atrans['Description'],'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);
            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCWorkGroupAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_WorkGroupAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _workgroupEdit($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCWorkGroupTrans')
            ->columns(array('RFCTransId','WorkGroupId','SerialNo','WorkTypeId','WorkGroupName','AutoRateAnalysis','ConcreteMix','Cement','Sand','Metal','Thickness','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $workGroupId= $trans['WorkGroupId'];
            $wgcode = $trans['SerialNo'];

            $update = $sql->update();
            $update->table('Proj_WorkGroupMaster');
            $update->set(array(
                'WorkTypeId'=>$trans['WorkTypeId'],'WorkGroupName'=>$trans['WorkGroupName'],
                'AutoRateAnalysis'=>$trans['AutoRateAnalysis'],'SerialNo'=>$wgcode,
                'ConcreteMix'=>$trans['ConcreteMix'],'Cement'=>$trans['Cement'],'Sand'=>$trans['Sand'],'Metal'=>$trans['Metal'],
                'Thickness'=>$trans['Thickness'],'WorkingQty'=>$trans['WorkingQty'],'RWorkingQty'=>$trans['RWorkingQty'],
                'CementRatio'=>$trans['CementRatio'],'SandRatio'=>$trans['SandRatio'],'MetalRatio'=>$trans['MetalRatio'],'ThickQty'=>$trans['ThickQty'],'RFCRegisterId'=>$rfcid));
            $update->where(array('WorkGroupId' => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_IOWMaster');
            $update->set(array(
                'WorkTypeId'=>$trans['WorkTypeId']));
            $update->where(array('WorkGroupId' => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkGroupAnalysis')
                ->where(array("WorkGroupId" => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupAnalysis')
                ->columns(array('IncludeFlag', 'ReferenceId', 'ResourceId', 'Qty', 'CFormula','Type','SortId','TransType','Description','ResourceName','NewId'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcanal as $atrans) {
                $insert = $sql->insert();
                $insert->into('Proj_WorkGroupAnalysis');
                $insert->Values(array('WorkGroupId' => $workGroupId, 'IncludeFlag' => $atrans['IncludeFlag'], 'ReferenceId' => $atrans['ReferenceId'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'],
                    'CFormula' => $atrans['CFormula'],'Type'=>$atrans['Type'],'SortId'=>$atrans['SortId'],'TransType'=>$atrans['TransType'],'Description'=>$atrans['Description'],'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $delete = $sql->delete();
            $delete->from('Proj_WorkGroupWorkChecklistTrans')
                ->where(array("WorkGroupId" => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkGroupQualityChecklistTrans')
                ->where(array("WorkGroupId" => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkGroupSafetyChecklistTrans')
                ->where(array("WorkGroupId" => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupWorkChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupWorkChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupQualityChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupQualityChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupSafetyChecklistTrans')
                ->columns(array('RFCRegisterId','WorkGroupId'=>new Expression("$workGroupId"),'CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCTransId'=>$rfctransid));

            $insert = $sql->insert();
            $insert->into('Proj_WorkGroupSafetyChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkGroupId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] ==1) $bAutoCode=true;

        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);
            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
            //$resid = $trans['ResourceId'];

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCWorkGroupAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_WorkGroupAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _workgroupDelete($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCWorkGroupDeleteTrans')
            ->columns(array('WorkGroupId'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $workGroupId = $trans['WorkGroupId'];

            $update = $sql->update();
            $update->table('Proj_WorkGroupMaster');
            $update->set(array(
                'DeleteFlag' => 1,
            ));
            $update->where(array('WorkGroupId' => $workGroupId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $delete = $sql->delete();
//            $delete->from('Proj_WorkGroupAnalysis')
//                ->where(array("WorkGroupId" => $workGroupId));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//            $delete = $sql->delete();
//            $delete->from('Proj_WorkGroupMaster')
//                ->where(array("WorkGroupId" => $workGroupId));
//            $statement = $sql->getSqlStringForSqlObject($delete);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _worktypeEdit($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCWorkTypeTrans')
            ->columns(array('RFCTransId','WorkTypeId','ConcreteMix','Cement','Sand','Metal','Thickness','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);

        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $workTypeId= $trans['WorkTypeId'];
            $update = $sql->update();
            $update->table('Proj_WorkTypeMaster');
            $update->set(array(
                'ConcreteMix'=>$trans['ConcreteMix'],'Cement'=>$trans['Cement'],'Sand'=>$trans['Sand'],'Metal'=>$trans['Metal'],
                'Thickness'=>$trans['Thickness'],'WorkingQty'=>$trans['WorkingQty'],'RWorkingQty'=>$trans['RWorkingQty'],
                'CementRatio'=>$trans['CementRatio'],'SandRatio'=>$trans['SandRatio'],'MetalRatio'=>$trans['MetalRatio'],'ThickQty'=>$trans['ThickQty'],'RFCRegisterId'=>$rfcid));
            $update->where(array('WorkTypeId' => $workTypeId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkTypeAnalysis')
                ->where(array("WorkTypeId" => $workTypeId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkTypeAnalysis')
                ->columns(array('IncludeFlag', 'ReferenceId', 'ResourceId', 'Qty','CFormula','Type','SortId','TransType','Description','ResourceName','NewId'))
                ->where(array("RFCTransId='$rfctransid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfcanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfcanal as $atrans) {
                $insert = $sql->insert();
                $insert->into('Proj_WorkTypeAnalysis');
                $insert->Values(array('WorkTypeId' => $workTypeId, 'IncludeFlag' => $atrans['IncludeFlag'], 'ReferenceId' => $atrans['ReferenceId'], 'ResourceId' => $atrans['ResourceId'], 'Qty' => $atrans['Qty'],
                    'CFormula' => $atrans['CFormula'],'Type'=>$atrans['Type'],'SortId'=>$atrans['SortId'],'TransType'=>$atrans['TransType'],'Description'=>$atrans['Description'],'ResourceName'=>$atrans['ResourceName'],'NewId'=>$atrans['NewId'],'RFCTransId'=>$rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $delete = $sql->delete();
            $delete->from('Proj_WorkTypeWorkChecklistTrans')
                ->where(array("WorkTypeId" => $workTypeId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkTypeQualityChecklistTrans')
                ->where(array("WorkTypeId" => $workTypeId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            $delete->from('Proj_WorkTypeSafetyChecklistTrans')
                ->where(array("WorkTypeId" => $workTypeId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkTypeWorkChecklistTrans')
                ->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCRegisterId'=>$rfcid,'WorkTypeId'=>$workTypeId));

            $insert = $sql->insert();
            $insert->into('Proj_WorkTypeWorkChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkTypeQualityChecklistTrans')
                ->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCRegisterId'=>$rfcid,'WorkTypeId'=>$workTypeId));

            $insert = $sql->insert();
            $insert->into('Proj_WorkTypeQualityChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkTypeSafetyChecklistTrans')
                ->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'))
                ->where(array('RFCRegisterId'=>$rfcid,'WorkTypeId'=>$workTypeId));

            $insert = $sql->insert();
            $insert->into('Proj_WorkTypeSafetyChecklistTrans');
            $insert->columns(array('RFCRegisterId','WorkTypeId','CheckListId','Priority','WhenType','WhenPeriod','WhenPeriodType','FrequencyPeriod','FrequencyPeriodType'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if (!empty($code)) { if ($code['GenType'] ==1) $bAutoCode=true; }

        $select = $sql->select();
        $select->from('Proj_RFCResourceTrans')
            ->columns(array('RFCTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("RFCRegisterId='$rfcid'"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['RFCTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);
            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            //$resid = $trans['ResourceId'];

            $update = $sql->update();
            $update->table('Proj_RFCResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('RFCTransId' => $rfctransid));

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_RFCWorkTypeAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('Proj_WorkTypeAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _GetIOWSlNo($iowid, $parentid, $workgroupId, $dbAdapter)
    {
        $sCode = "";
        $sSerialNo="";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        if ($parentid==0) {
            $select->from('Proj_WorkGroupMaster')
                ->columns(array('SerialNo'))
                ->where(array('WorkGroupId' => $workgroupId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $wg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($wg)) $sCode = $wg['SerialNo'];
        } else {
            $select->from('Proj_IOWMaster')
                ->columns(array('SerialNo'))
                ->where(array('IOWId' => $parentid));
            $statement = $sql->getSqlStringForSqlObject($select);
            $wg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($wg)) $sCode = $wg['SerialNo'];
        }

        $sPSlNo=0;
        $select = $sql->select();
        $select->from(array('a' => 'Proj_IOWMaster'))
            ->columns(array('PSLNo'=>new Expression("Max(PSLNo)")))
            ->where(array("a.WorkGroupId=$workgroupId and a.ParentId=$parentid and a.IOWId<>$iowid"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $wgm = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($wgm)) $sPSlNo = intval($this->bsf->isNullCheck($wgm['PSLNo'],'number'));
        $sPSlNo = $sPSlNo+1;
        $iWidth=5;
        $iLen = $iWidth - strlen($sPSlNo);
        $sPre = "";
        for ($i = 1; $i < $iLen; $i++) {
            $sPre = $sPre . "0";
        }
        $sSerialNo = $sCode .'.'.$sPre.$sPSlNo;

        return array($sSerialNo,$sPSlNo);
    }


    function _GetResCode($typeId, $groupId, $dbAdapter)
    {
        $resCode = "";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType', 'Prefix', 'PType', 'PGroup', 'Suffix', 'Width', 'GroupLevel', 'CountLevel','Separator', 'MaxNo'));
        $statement = $sql->getSqlStringForSqlObject($select);

        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] == 1) {
            $sPrefix = $code['Prefix'];
            $sSuffix = $code['Suffix'];
            $iWidth = $code['Width'];
            $sSperator = $code['Separator'];
            $iTMaxNo = 0;
            $sTPrefix = "";
            $sGPrefix = "";
            $iGMaxNo = 0;

            if ($code['PType'] == 1) {
                $sTPrefix = ProjectHelper::_getTypeCode($typeId, $dbAdapter);
            }
            if ($code['PGroup'] == 1) {
                if ($code['GroupLevel'] == 'F') {
                    $iParentGroupId = ProjectHelper::_getFirstLevelGroup($groupId, $dbAdapter);
                    $sGPrefix = ProjectHelper::_getResGroupCode($iParentGroupId, $dbAdapter);
                } else if ($code['GroupLevel'] == 'L') {
                    $sGPrefix = ProjectHelper::_getResGroupCode($groupId, $dbAdapter);
                } else {
                    $this->strCode="";
                    ProjectHelper::_getallResGroupCode($groupId, $dbAdapter);
                    $sGPrefix = $this->strCode;
                }
            }

            if ($code['CountLevel'] == 'G') {
                $iGMaxNo = ProjectHelper::_getGroupMaxNo($groupId, $dbAdapter);
                $iVNo = $iGMaxNo;
                ProjectHelper::_UpdateGroupMaxNo($iVNo, $groupId, $dbAdapter);

            } else if ($code['CountLevel'] == 'T') {
                $iTMaxNo = ProjectHelper::_getTypeMaxNo($typeId, $dbAdapter);
                $iVNo = $iTMaxNo;
                ProjectHelper::_UpdateTypeMaxNo($iVNo, $typeId, $dbAdapter);
            } else {
                $iVNo = $code['MaxNo'] + 1;
                ProjectHelper::_UpdateResourceMaxNo($iVNo, $dbAdapter);
            }

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for ($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre . "0";
            }
            $resCode = $sPrefix . $sTPrefix . $sGPrefix . $sSperator. $sPre . $iVNo . $sSuffix;
        }
        return $resCode;
    }

    function _getFirstLevelGroup($groupId, $dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ResourceGroup')
            ->columns(array('ParentId'))
            ->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iParentId = 0;
        if (!empty($resgroup)) $iParentId = $resgroup['ParentId'];
        if ($iParentId ==0) {
            return $groupId;
        } else {
            ProjectHelper::_getFirstLevelGroup($groupId, $dbAdapter);
        }
    }

    function  _getTypeCode($typeid, $dbAdapter)
    {
        $typecode = "";

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ResourceType')
            ->columns(array('TypeCode'))
            ->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $restype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $typecode = $restype['TypeCode'];

        return $typecode;
    }

    function  _getWorkTypeCode($typeid, $dbAdapter)
    {
        $typecode = "";

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_WorkTypeMaster')
            ->columns(array('Code'))
            ->where(array('WorkTypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $restype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $typecode = $restype['Code'];

        return $typecode;
    }

    function  _getTypeMaxNo($typeid, $dbAdapter)
    {
        $iMaxno = 0;

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ResourceType')
            ->columns(array('MaxNo'))
            ->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $restype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iMaxno = $restype['MaxNo'];
        $iMaxno = $iMaxno + 1;
        return $iMaxno;
    }

    function  _getRGTypeMaxNo($typeid, $dbAdapter)
    {
        $iMaxno = 0;

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ResourceType')
            ->columns(array('RGMaxNo'))
            ->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $restype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iMaxno = $restype['RGMaxNo'];
        $iMaxno = $iMaxno + 1;
        return $iMaxno;
    }

    function  _getWorkTypeMaxNo($typeid, $dbAdapter)
    {
        $iMaxno = 0;

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_WorkTypeMaster')
            ->columns(array('MaxNo'))
            ->where(array('WorkTypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $restype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iMaxno = $restype['MaxNo'];
        $iMaxno = $iMaxno + 1;
        return $iMaxno;
    }

    function  _getallResGroupCode($groupid, $dbAdapter)
    {
        $groupcode = "";
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ResourceGroup')
            ->columns(array('Code','ParentId'))
            ->where(array('ResourceGroupId' => $groupid));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iParentId = 0;
        if (!empty($resgroup)) {
            $iParentId  = $resgroup['ParentId'];
            $this->strCode = $this->strCode . $resgroup['Code'];
        }
        if ($iParentId !=0)  ProjectHelper::_getallResGroupCode($groupId, $dbAdapter);

        return $groupcode;
    }

    function  _getResGroupCode($groupid, $dbAdapter)
    {
        $groupcode = "";
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ResourceGroup')
            ->columns(array('Code'))
            ->where(array('ResourceGroupId' => $groupid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $groupcode = $resgroup['Code'];

        return $groupcode;
    }

    function  _getGroupMaxNo($groupid, $dbAdapter)
    {
        $iMaxno = 0;

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ResourceGroup')
            ->columns(array('MaxNo'))
            ->where(array('ResourceGroupId' => $groupid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $iMaxno = $resgroup['MaxNo'];
        $iMaxno = $iMaxno + 1;

        return $iMaxno;
    }

    function  _getRGGroupMaxNo($groupid, $dbAdapter)
    {
        $iMaxno = 0;

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ResourceGroup')
            ->columns(array('RGMaxNo'))
            ->where(array('ResourceGroupId' => $groupid));

        $statement = $sql->getSqlStringForSqlObject($select);
        $resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $iMaxno = $resgroup['RGMaxNo'];
        $iMaxno = $iMaxno + 1;

        return $iMaxno;
    }

    function  _UpdateResourceMaxNo($maxno, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_ResourceCodeSetup');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
    function  _UpdateResourceGroupMaxNo($maxno, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_RGCodeSetup');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
    function  _UpdateWorkGroupMaxNo($maxno, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_WorkGroupCodeSetup');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }

    function  _UpdateGroupMaxNo($maxno, $groupid, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_ResourceGroup');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $update->where(array('ResourceGroupId' => $groupid));

        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }

    function  _UpdateRGGroupMaxNo($maxno, $groupid, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_ResourceGroup');
        $update->set(array(
            'RGMaxNo' => $maxno,
        ));
        $update->where(array('ResourceGroupId' => $groupid));

        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }

    function  _UpdateTypeMaxNo($maxno, $typeid, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_ResourceType');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $update->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
    function  _UpdateRGTypeMaxNo($maxno, $typeid, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_ResourceType');
        $update->set(array(
            'RGMaxNo' => $maxno,
        ));
        $update->where(array('TypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }

    function  _UpdateWorkTypeMaxNo($maxno, $typeid, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('Proj_WorkTypeMaster');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $update->where(array('WorkTypeId' => $typeid));

        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }


    function _GetWorkGroupCode($typeId, $dbAdapter)
    {
        $resCode = "";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        $select->from('Proj_WorkGroupCodeSetup')
            ->columns(array('GenType', 'Prefix', 'PType', 'Suffix', 'Width', 'Separator', 'MaxNo'));
        $statement = $sql->getSqlStringForSqlObject($select);

        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] == 1) {
            $sPrefix = $code['Prefix'];
            $sSuffix = $code['Suffix'];
            $iWidth = $code['Width'];
            $sSperator = $code['Separator'];
            $sTPrefix = "";
            $sGPrefix = "";

            if ($code['PType'] == 1) {
                $sTPrefix = ProjectHelper::_getWorkTypeCode($typeId, $dbAdapter);
                $iTMaxNo = ProjectHelper::_getWorkTypeMaxNo($typeId, $dbAdapter);
                $iVNo = $iTMaxNo;
                ProjectHelper::_UpdateWorkTypeMaxNo($iVNo, $typeId, $dbAdapter);
            } else {
                $iVNo = $code['MaxNo'] + 1;
                ProjectHelper::_UpdateWorkGroupMaxNo($iVNo, $dbAdapter);
            }

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for ($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre . "0";
            }
            $resCode = $sPrefix . $sTPrefix . $sGPrefix . $sSperator. $sPre . $iVNo . $sSuffix;
        }
        return $resCode;
    }


    function _GetResourceGroupCode($typeId, $groupId, $dbAdapter)
    {
        $resCode = "";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        $select->from('Proj_RGCodeSetup')
            ->columns(array('GenType', 'Prefix', 'PType', 'PGroup', 'Suffix', 'Width', 'GroupLevel', 'CountLevel','Separator', 'MaxNo'));
        $statement = $sql->getSqlStringForSqlObject($select);

        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] == 1) {
            $sPrefix = $code['Prefix'];
            $sSuffix = $code['Suffix'];
            $iWidth = $code['Width'];
            $sSperator = $code['Separator'];
            $iTMaxNo = 0;
            $sTPrefix = "";
            $sGPrefix = "";
            $iGMaxNo = 0;

            if ($code['PType'] == 1) {
                $sTPrefix = ProjectHelper::_getTypeCode($typeId, $dbAdapter);
            }
            if ($code['PGroup'] == 1) {
                if ($code['GroupLevel'] == 'F') {
                    $iParentGroupId = ProjectHelper::_getFirstLevelGroup($groupId, $dbAdapter);
                    $sGPrefix = ProjectHelper::_getResGroupCode($iParentGroupId, $dbAdapter);
                } else if ($code['GroupLevel'] == 'L') {
                    $sGPrefix = ProjectHelper::_getResGroupCode($groupId, $dbAdapter);
                } else {
                    $this->strCode="";
                    ProjectHelper::_getallResGroupCode($groupId, $dbAdapter);
                    $sGPrefix = $this->strCode;
                }
            }

            if ($code['CountLevel'] == 'G') {
                $iGMaxNo = ProjectHelper::_getRGGroupMaxNo($groupId, $dbAdapter);
                $iVNo = $iGMaxNo;
                ProjectHelper::_UpdateRGGroupMaxNo($iVNo, $groupId, $dbAdapter);

            } else if ($code['CountLevel'] == 'T') {
                $iTMaxNo = ProjectHelper::_getRGTypeMaxNo($typeId, $dbAdapter);
                $iVNo = $iTMaxNo;
                ProjectHelper::_UpdateRGTypeMaxNo($iVNo, $typeId, $dbAdapter);
            } else {
                $iVNo = $code['MaxNo'] + 1;
                ProjectHelper::_UpdateResourceGroupMaxNo($iVNo, $dbAdapter);
            }

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for ($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre . "0";
            }
            $resCode = $sPrefix . $sTPrefix . $sGPrefix . $sSperator. $sPre . $iVNo . $sSuffix;
        }
        return $resCode;
    }

    function _updateOtherCost($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType','RevRequired'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        $iRevRequired=0;
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
            $iRevRequired = $this->bsf->isNullCheck($rfcregister['RevRequired'], 'number');
        }

        if ($rfctype == "OtherCost-Add") {

            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_RFCOHTrans'))
                ->join(array('b' => 'Proj_OHMaster'), 'a.OHId=b.OHId', array('OHTypeId'), $select:: JOIN_INNER)
                ->columns(array('RFCTransId','OHId', 'Amount'))
                ->where(array("RFCRegisterId='$rfcid'"));

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iOHTypeId= $trans['OHTypeId'];
                $OHId= $trans['OHId'];
                $Amount= $trans['Amount'];

                $insert = $sql->insert();
                if ($sProjectType=="P") $insert->into('Proj_OHAbstractPlan');
                else $insert->into('Proj_OHAbstract');
                $insert->Values(array('ProjectId' =>$iProjectId,'OHId' => $OHId, 'Amount' => $Amount,
                    'RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iOHAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();


                if ($iOHTypeId==1) {
                    //Item
                    $select = $sql->select();
                    $select->from('Proj_RFCOHItemTrans')
                        ->columns(array('RFCItemTransId', 'ProjectIOWId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHItemPlan');
                        else $insert->into('Proj_OHItem');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ProjectIOWId' => $atrans['ProjectIOWId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCItemTransId' => $atrans['RFCItemTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==2) {
                    //Material
                    $select = $sql->select();
                    $select->from('Proj_RFCOHMaterialTrans')
                        ->columns(array('RFCMaterialTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHMaterialPlan');
                        else $insert->into('Proj_OHMaterial');

                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCMaterialTransId' => $atrans['RFCMaterialTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==3) {
                    //Labour
                    $select = $sql->select();
                    $select->from('Proj_RFCOHLabourTrans')
                        ->columns(array('RFCLabourTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHLabourPlan');
                        else $insert->into('Proj_OHLabour');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCLabourTransId' => $atrans['RFCLabourTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==4) {
                    //Service
                    $select = $sql->select();
                    $select->from('Proj_RFCOHServiceTrans')
                        ->columns(array('RFCServiceTransId', 'ServiceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHServicePlan');
                        else $insert->into('Proj_OHService');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ServiceId' => $atrans['ServiceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCServiceTransId' => $atrans['RFCServiceTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==5) {
                    //Machinery
                    $select = $sql->select();
                    $select->from('Proj_RFCOHMachineryTrans')
                        ->columns(array('RFCMachineryTransId', 'MResourceId', 'Nos', 'WorkingQty', 'TotalQty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $rfcMtransId = $atrans['RFCMachineryTransId'];
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHMachineryPlan');
                        else $insert->into('Proj_OHMachinery');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'Nos' => $atrans['Nos'],
                            'WorkingQty' => $atrans['WorkingQty'], 'TotalQty' => $atrans['TotalQty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCMachineryTransId' => $atrans['RFCMachineryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from('Proj_RFCOHMachineryDetails')
                            ->columns(array('RFCMachineryDetailId', 'ProjectIOWId', 'Percentage', 'Amount'))
                            ->where(array("RFCMachineryTransId='$rfcMtransId'"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rfcmachinery = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($rfcmachinery as $mtrans) {
                            $insert = $sql->insert();
                            if ($sProjectType == "P") $insert->into('Proj_OHMachineryDetailsPlan');
                            else $insert->into('Proj_OHMachineryDetails');
                            $insert->Values(array('ProjectId' => $iProjectId, 'MachineryTransId' => $iMTransId, 'ProjectIOWId' => $mtrans['ProjectIOWId'], 'Percentage' => $mtrans['Percentage'],
                                'Amount' => $mtrans['Amount'],
                                'RFCMachineryDetailId' => $mtrans['RFCMachineryDetailId']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                }
                else if ($iOHTypeId==6) {
                    //AdminExpense
                    $select = $sql->select();
                    $select->from('Proj_RFCOHAdminExpenseTrans')
                        ->columns(array('RFCExpenseTransId', 'ExpenseId', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHAdminExpensePlan');
                        else $insert->into('Proj_OHAdminExpense');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ExpenseId' => $atrans['ServiceId'],
                            'Amount' => $atrans['Amount'], 'RFCExpenseTransId' => $atrans['RFCExpenseTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==7) {
                    //Salary
                    $select = $sql->select();
                    $select->from('Proj_RFCOHSalaryTrans')
                        ->columns(array('RFCSalaryTransId', 'PositionId', 'Nos', 'cMonths', 'Salary', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHSalaryPlan');
                        else $insert->into('Proj_OHSalary');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'PositionId' => $atrans['PositionId'],
                            'Nos' => $atrans['Nos'], 'cMonths' => $atrans['cMonths'], 'Salary' => $atrans['Salary'], 'Amount' => $atrans['Amount'], 'RFCSalaryTransId' => $atrans['RFCSalaryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==8) {
                    //Fuel
                    $select = $sql->select();
                    $select->from('Proj_RFCOHFuelTrans')
                        ->columns(array('RFCFuelTransId', 'MResourceId', 'FResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHFuelPlan');
                        else $insert->into('Proj_OHFuelTrans');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'FResourceId' => $atrans['FResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCFuelTransId' => $atrans['RFCFuelTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        } else  if ($rfctype == "OtherCost-Edit") {

            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_RFCOHTrans'))
                ->join(array('b' => 'Proj_OHMaster'), 'a.OHId=b.OHId', array('OHTypeId'), $select:: JOIN_INNER)
                ->columns(array('RFCTransId','OHId', 'Amount'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iOHTypeId= $trans['OHTypeId'];
                $OHId= $trans['OHId'];
                $Amount= $trans['Amount'];

                $select = $sql->select();
                if ($sProjectType=="P") $select->from('Proj_OHAbstractPlan');
                else $select->from('Proj_OHAbstract');
                $select->columns(array('OHAbsId'))
                    ->where(array("OHId" => $OHId,'ProjectId'=>$iProjectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $ohabs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $ioldOhAbsId = 0;
                if (!empty($ohabs)) {
                    $ioldOhAbsId = $this->bsf->isNullCheck($ohabs['OHAbsId'],'number');
                }

                $delete = $sql->delete();
                if ($sProjectType=="P") $delete->from('Proj_OHAbstractPlan');
                else $delete->from('Proj_OHAbstract');
                $delete->where(array("OHAbsId" => $ioldOhAbsId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $insert = $sql->insert();
                if ($sProjectType=="P") $insert->into('Proj_OHAbstractPlan');
                else $insert->into('Proj_OHAbstract');
                $insert->Values(array('ProjectId' =>$iProjectId,'OHId' => $OHId, 'Amount' => $Amount,
                    'RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iOHAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();

                if ($iOHTypeId==1) {
                    //Item

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHItemPlan');
                    else $delete->from('Proj_OHItem');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $select = $sql->select();
                    $select->from('Proj_RFCOHItemTrans')
                        ->columns(array('RFCItemTransId', 'ProjectIOWId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHItemPlan');
                        else $insert->into('Proj_OHItem');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ProjectIOWId' => $atrans['ProjectIOWId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCItemTransId' => $atrans['RFCItemTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==2) {
                    //Material

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHMaterialPlan');
                    else $delete->from('Proj_OHMaterial');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHMaterialTrans')
                        ->columns(array('RFCMaterialTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHMaterialPlan');
                        else $insert->into('Proj_OHMaterial');

                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCMaterialTransId' => $atrans['RFCMaterialTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==3) {
                    //Labour

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHLabourPlan');
                    else $delete->from('Proj_OHLabour');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHLabourTrans')
                        ->columns(array('RFCLabourTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHLabourPlan');
                        else $insert->into('Proj_OHLabour');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCLabourTransId' => $atrans['RFCLabourTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==4) {
                    //Service

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHServicePlan');
                    else $delete->from('Proj_OHService');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHServiceTrans')
                        ->columns(array('RFCServiceTransId', 'ServiceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHServicePlan');
                        else $insert->into('Proj_OHService');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ServiceId' => $atrans['ServiceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCServiceTransId' => $atrans['RFCServiceTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==5) {
                    //Machinery

                    $subQuery = $sql->select();
                    if ($sProjectType=="P") $subQuery->from('Proj_OHMachineryPlan');
                    else $subQuery->from('Proj_OHMachinery');
                    $subQuery->columns(array("MachineryTransId"));
                    $subQuery->where(array('OHAbsId' => $ioldOhAbsId));

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHMachineryDetailsPlan');
                    else $delete->from('Proj_OHMachineryDetails');
                    $delete->where->expression('MachineryTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHMachineryPlan');
                    else $delete->from('Proj_OHMachinery');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $select = $sql->select();
                    $select->from('Proj_RFCOHMachineryTrans')
                        ->columns(array('RFCMachineryTransId', 'MResourceId', 'Nos', 'WorkingQty', 'TotalQty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $rfcMtransId = $atrans['RFCMachineryTransId'];
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHMachineryTransPlan');
                        else $insert->into('Proj_OHMachineryTrans');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'Nos' => $atrans['Nos'],
                            'WorkingQty' => $atrans['WorkingQty'], 'TotalQty' => $atrans['TotalQty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCMachineryTransId' => $atrans['RFCMachineryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from('Proj_RFCOHMachineryDetails')
                            ->columns(array('RFCMachineryDetailId', 'ProjectIOWId', 'Percentage', 'Amount'))
                            ->where(array("RFCMachineryTransId='$rfcMtransId'"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rfcmachinery = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($rfcmachinery as $mtrans) {
                            $insert = $sql->insert();
                            if ($sProjectType == "P") $insert->into('Proj_OHMachineryDetailsPlan');
                            else $insert->into('Proj_OHMachineryDetails');
                            $insert->Values(array('ProjectId' => $iProjectId, 'MachineryTransId' => $iMTransId, 'ProjectIOWId' => $mtrans['ProjectIOWId'], 'Percentage' => $mtrans['Percentage'],
                                'Amount' => $mtrans['Amount'],
                                'RFCMachineryDetailId' => $atrans['RFCMachineryDetailId']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                }
                else if ($iOHTypeId==6) {
                    //AdminExpense

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHAdminExpensePlan');
                    else $delete->from('Proj_OHAdminExpense');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHAdminExpenseTrans')
                        ->columns(array('RFCExpenseTransId', 'ExpenseId', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHAdminExpensePlan');
                        else $insert->into('Proj_OHAdminExpense');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ExpenseId' => $atrans['ServiceId'],
                            'Amount' => $atrans['Amount'], 'RFCExpenseTransId' => $atrans['RFCExpenseTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==7) {
                    //Salary

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHSalaryPlan');
                    else $delete->from('Proj_OHSalary');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHSalaryTrans')
                        ->columns(array('RFCSalaryTransId', 'PositionId', 'Nos', 'cMonths', 'Salary', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHSalaryPlan');
                        else $insert->into('Proj_OHSalary');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'PositionId' => $atrans['PositionId'],
                            'Nos' => $atrans['Nos'], 'cMonths' => $atrans['cMonths'], 'Salary' => $atrans['Salary'], 'Amount' => $atrans['Amount'], 'RFCSalaryTransId' => $atrans['RFCSalaryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                else if ($iOHTypeId==8) {
                    //Fuel

                    $delete = $sql->delete();
                    if ($sProjectType=="P") $delete->from('Proj_OHFuelPlan');
                    else $delete->from('Proj_OHFuelTrans');
                    $delete->where(array("OHAbsId" => $ioldOhAbsId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCOHFuelTrans')
                        ->columns(array('RFCFuelTransId', 'MResourceId', 'FResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        if ($sProjectType == "P") $insert->into('Proj_OHFuelPlan');
                        else $insert->into('Proj_OHFuelTrans');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'FResourceId' => $atrans['FResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'RFCFuelTransId' => $atrans['RFCFuelTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        }
    }

    function _getRevisionName($argProjectId,$argType,$argRFCId,$dbAdapter)
    {
        $sql     = new Sql($dbAdapter);
        $iRevId=0;

        try {
            $iWidth = 0;
            $iMaxNo = 0;
            $iVNo = 0;
            $iLen = 0;
            $sPre = "";
            $sPrefix = "";
            $sSuffix = "";
            $sSeperator = "";

            $sStage = "";
            if ($argType =="P") {
                $sStage = "Plan";
            } else if ($argType =="B") {
                $sStage = "Budget";
            }

            $iMaxRevId=0;
            $select = $sql->select();
            $select->from('Proj_RevisionMaster')
                ->columns(array('RevisionId'=>new Expression("Max(RevisionId)")))
                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>$argType));
            $statement = $sql->getSqlStringForSqlObject($select);
            $maxrevmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($maxrevmaster)) {
                $iMaxRevId = $this->bsf->isNullCheck($maxrevmaster['RevisionId'],'number');
            }
            $iRevId = $iMaxRevId;

            $select = $sql->select();
            $select->from('Proj_RevisionNameSetup')
                ->columns(array('Prefix','Width','Suffix','Separator'))
                ->where(array("StageName"=>$sStage));
            $statement = $sql->getSqlStringForSqlObject($select);
            $namesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($namesetup)) {
                $iWidth = $namesetup['Width'];
                $sPrefix = $namesetup['Prefix'];
                $sSuffix = $namesetup['Suffix'];
                $sSeperator = $namesetup['Separator'];
            }

            $select = $sql->select();
            $select->from('Proj_RevisionMaster')
                ->columns(array('OrderId'=>new Expression("Max(OrderId)")))
                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>$argType));
            $statement = $sql->getSqlStringForSqlObject($select);
            $revmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($revmaster)) {
                $iMaxNo = $this->bsf->isNullCheck($revmaster['OrderId'],'number');
            }

            $iVNo = $iMaxNo + 1;

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre."0";
            }

            $revname = $sPrefix.$sSeperator.$sPre.trim($iVNo);
            if ($sSuffix != "") {
                $revname =  $revname.$sSeperator.$sSuffix;
            }

            $insert = $sql->insert();
            $insert->into('Proj_RevisionMaster');
            $insert->Values(array('OrderId' => $iVNo, 'ProjectId' => $argProjectId,
                'RevisionName' => $revname, 'RevisionType'=> $argType,
                'RFCRegisterId' => $argRFCId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        } catch (Zend_Exception $e) {
            echo "Error: " . $e->getMessage() . "</br>";
        }
        return $iRevId;
    }

    function _updateProjectResource($rfcid,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType','RevRequired'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        $iRevRequired=0;
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
            $iRevRequired = $this->bsf->isNullCheck($rfcregister['RevRequired'], 'number');
        }

        if ($rfctype == "Project-Resource") {

            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select = $sql->select();
            $select->from('Proj_RFCProjectResourceRate')
                ->columns(array('ResourceId', 'Rate', 'IncludeFlag','RateType'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $iResId = $trans['ResourceId'];
                $sRateType = $trans['RateType'];
                $dRate = $trans['Rate'];
                $iInclFlag = $trans['IncludeFlag'];

                $select = $sql->select();
                $select->from('Proj_ProjectDetails')
                    ->columns(array('ProjectIOWId'))
                    ->where(array('ResourceId'=>$iResId,'ProjectId'=>$iProjectId,'RateType'=>$sRateType));
                $statement = $sql->getSqlStringForSqlObject($select);
                $pdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach ($pdetails as $ptrans) {
                    $iPIOWid =  $ptrans["ProjectIOWId"];

                    $update = $sql->update();
                    $update->table('Proj_ProjectRateAnalysis');
                    $update->set(array(
                        'Rate' => $dRate, 'Amount' => new Expression("Qty*$dRate"), 'WastageAmount' => new Expression("WastageQty*$dRate")
                    ));
                    $update->where(array('ResourceId' => $iResId,'ProjectIOWId'=>$iPIOWid,'RateType'=>$sRateType));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateIOWRate($iProjectId, $iPIOWid, $dbAdapter);
                    ProjectHelper::_updateProjectDetails($iProjectId, $iPIOWid, $sProjectType, $dbAdapter);
                }
            }
            ProjectHelper::_updateResourceRate($iProjectId, $sProjectType, $dbAdapter);
        }
    }


    function _updateIOWRate($iProjectId, $iPIOWid, $dbAdapter) {
        $sql = new Sql($dbAdapter);

        $dWQty=0;
        $dRWQty=0;
        $sMixRatio=100;
        $rMixRatio=0;

        $select = $sql->select();
        $select->from('Proj_ProjectIOWMaster')
            ->columns(array('WorkingQty','RWorkingQty'))
            ->where(array('ProjectIOWId' => $iPIOWid));
        $statement = $sql->getSqlStringForSqlObject($select);
        $pdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($pdetails)) {
            $dWQty = floatval($pdetails['WorkingQty']);
            $dRWQty = floatval($pdetails['RWorkingQty']);
        }

        $select = $sql->select();
        $select->from('Proj_ProjectIOW')
            ->columns(array('ReadyMixRatio'))
            ->where(array('ProjectIOWId' => $iPIOWid));
        $statement = $sql->getSqlStringForSqlObject($select);
        $pdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($pdetails)) {
            $rMixRatio= floatval($pdetails['ReadyMixRatio']);
        }


        if ($dWQty ==0) $dWQty =1;
        if ($dRWQty ==0) $dRWQty =1;
        $sMixRatio = 100 - $rMixRatio;

        $select = $sql->select();
        $select->from('Proj_ProjectRateAnalysis')
            ->columns(array('Amount'=>new Expression("sum(Amount)"),'WAmount'=>new Expression("Sum(WastageAmount)")))
            ->where(array('ProjectIOWId' => $iPIOWid))
            ->where("ProjectIOWId=$iPIOWid and MixType<>'R'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $pamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $dAmt =0;
        $dWAmt=0;
        if (!empty($pamt)) {
            $dAmt =   floatval($pamt['Amount']);
            $dWAmt =  floatval($pamt['WAmount']);
        }

        $dRate = ($dAmt+$dWAmt) / $dWQty;

        $select = $sql->select();
        $select->from('Proj_ProjectRateAnalysis')
            ->columns(array('Amount'=>new Expression("sum(Amount)"),'WAmount'=>new Expression("Sum(WastageAmount)")))
            ->where(array('ProjectIOWId' => $iPIOWid))
            ->where("ProjectIOWId=$iPIOWid and MixType='R'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $pamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $dAmt =0;
        $dWAmt=0;
        if (!empty($pamt)) {
            $dAmt =   floatval($pamt['Amount']);
            $dWAmt =  floatval($pamt['WAmount']);
        }

        $dRRate = ($dAmt+$dWAmt) / $dRWQty;

        $iRate = ($dRate * ($sMixRatio/100)) + ($dRRate * ($rMixRatio/100));


        $update = $sql->update();
        $update->table('Proj_ProjectIOW');
        $update->set(array(
            'Rate' => $iRate, 'Amount' => new Expression("Qty*$iRate"),  'QualRate' => $iRate, 'QualAmount' => new Expression("Qty*$iRate")
        ));
        $update->where(array('ProjectIOWId'=>$iPIOWid));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }


    function _updateTenderQuotation($iQuotationId,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        $bAutoCode=false;
        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] ==1) $bAutoCode=true;

        $select = $sql->select();
        $select->from('Proj_TenderQuotationResourceTrans')
            ->columns(array('QuotationTransId', 'ResourceId','Code','ResourceName','ResourceGroupId','TypeId','UnitId','LeadDays','AnalysisMQty','AnalysisAQty','Rate','RateType','LRate','MRate','ARate','WorkUnitId','WorkRate','MaterialType'))
            ->where(array("QuotationId"=>$iQuotationId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($rfctrans as $trans) {
            $rfctransid = $trans['QuotationTransId'];
            $rescode = $trans['Code'];
            $iTypeId = $trans['TypeId'];
            $iGroupid = $trans['ResourceGroupId'];

            if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);

            $insert = $sql->insert();
            $insert->into('Proj_Resource');
            $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'QuotationId' => $iQuotationId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $resid = $dbAdapter->getDriver()->getLastGeneratedValue();

            if ($trans['ResourceGroupId'] !=0) {
                $update = $sql->update();
                $update->table('Proj_ResourceGroup');
                $update->set(array(
                    'GroupUsed' => 1,
                ));
                $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $update = $sql->update();
            $update->table('Proj_TenderQuotationResourceTrans');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('QuotationTransId' => $rfctransid));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $update = $sql->update();
            $update->table('Proj_TenderQuotationRateAnalysis');
            $update->set(array(
                'ResourceId' => $resid,
            ));
            $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _updateWorkOrderToProjects($iWORegId,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        $iProjectId=0;
        /*$select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId'))
            ->where(array("WORegisterId"=>$iWORegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $project = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($project)) $iProjectId = $project['ProjectId'];
        if ($iProjectId ==0)  return;*/

        $iAmendment = 0;
        $select = $sql->select();
        $select->from('Proj_WORegister')
            ->columns(array('Amendment'))
            ->where(array("WORegisterId"=>$iWORegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $woreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($woreg)) $iAmendment = $woreg['Amendment'];

        if ( $iAmendment ==0) {
            $select = $sql->select();
            $select->from('Proj_TenderWOSetup')
                ->where(array("WORegisterId" => $iWORegId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $projWoSetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $insert = $sql->insert();
            $insert->into('Proj_ProjectMaster');
            $insert->Values(array('ProjectName' => $projWoSetup['ProjectDescription'], 'ProjectTypeId' => $projWoSetup['ProjectTypeId'], 'WORegisterId' => $iWORegId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iProjectId = $dbAdapter->getDriver()->getLastGeneratedValue();

            $insert = $sql->insert();
            $insert->into('WF_OperationalCostCentre');
            $insert->Values(array('CostCentreName' => $projWoSetup['ProjectDescription']
            , 'FACostCentreId' => $projWoSetup['ProjectDivision']
            , 'ProjectId' => $iProjectId
            , 'BusinessTypeId' => $this->bsf->isNullCheck($projWoSetup['BusinessTypeId'], 'number')
            , 'ProjectTypeId' => $this->bsf->isNullCheck($projWoSetup['ProjectTypeId'], 'number')
            , 'SEZProject' => $this->bsf->isNullCheck($projWoSetup['IsSEZ'], 'number')
            , 'WBSReqMMS' => $this->bsf->isNullCheck($projWoSetup['MaterialStock'], 'number')
            , 'WBSReqWPM' => $this->bsf->isNullCheck($projWoSetup['WorkProgress'], 'number')
            , 'WBSReqClientBill' => $this->bsf->isNullCheck($projWoSetup['ClientBill'], 'number')
            , 'WBSReqLS' => $this->bsf->isNullCheck($projWoSetup['LabourStrength'], 'number')
            , 'WBSReqMMSStockOut' => $this->bsf->isNullCheck($projWoSetup['MaterialConsumption'], 'number')
            , 'WBSReqAsset' => $this->bsf->isNullCheck($projWoSetup['PlantMachinery'], 'number')
            , 'MaterialConsumptionBased' => $this->bsf->isNullCheck($projWoSetup['MaterialConsumptionBased'], 'string')
            , 'ItemWiseIssue' => $this->bsf->isNullCheck($projWoSetup['IssueRequire'], 'number')
            , 'IssueRate' => $this->bsf->isNullCheck($projWoSetup['IssueRateBased'], 'string')
            , 'IssueBasedOn' => $this->bsf->isNullCheck($projWoSetup['IssueBased'], 'string')
            , 'TransferBasedOn' => $this->bsf->isNullCheck($projWoSetup['TransferBased'], 'string')
            , 'CostControlBased' => $this->bsf->isNullCheck($projWoSetup['CostControlBased'], 'string')
            , 'WORegisterId' => $iWORegId
            , 'OHBudget' => $this->bsf->isNullCheck($projWoSetup['OHBudgetFrom'], 'string')));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iCCId = $dbAdapter->getDriver()->getLastGeneratedValue();

            $update = $sql->update();
            $update->table('Proj_WORegister');
            $update->set(array(
                'CostCentreId' => $iCCId,
            ));
            $update->where(array('WORegisterId' => $iWORegId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $iEnquiryId = 0;
            $select = $sql->select();
            $select->from('Proj_WORegister')
                ->columns(array('TenderEnquiryId'))
                ->where(array("WORegisterId" => $iWORegId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $woreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($woreg)) $iEnquiryId = $woreg['TenderEnquiryId'];
            if ($iEnquiryId == 0) return;

            $iQuotationId = 0;
            $select = $sql->select();
            $select->from('Proj_TenderQuotationRegister')
                ->columns(array('QuotationId'))
                ->where(array("TenderEnquiryId" => $iEnquiryId, "LiveQuotation" => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $qreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($qreg)) $iQuotationId = $qreg['QuotationId'];
            if ($iQuotationId == 0) return;

            $select = $sql->select();
            $select->from('Proj_TenderWorkGroup')
                ->columns(array('PWorkGroupId', 'WorkGroupId', 'WorkGroupName', 'SerialNo', 'WorkTypeId'))
                ->where(array("TenderEnquiryId" => $iEnquiryId, 'MWorkGroupId' => 0));

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $iTWorkGroupId = $trans['PWorkGroupId'];
                $insert = $sql->insert();
                $insert->into('Proj_ProjectWorkGroup');
                $insert->Values(array('WorkGroupId' => $trans['WorkGroupId'], 'SerialNo' => $trans['SerialNo'], 'WorkGroupName' => $trans['WorkGroupName'], 'WorkTypeId' => $trans['WorkTypeId'], 'ProjectId' => $iProjectId));

                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $workGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $update = $sql->update();
                $update->table('Proj_TenderWorkGroup');
                $update->set(array(
                    'MWorkGroupId' => $workGroupId,
                ));
                $update->where(array('PWorkGroupId' => $iTWorkGroupId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $update = $sql->update();
//            $update->table('Proj_ProjectIOWMaster');
//            $update->set(array(
//                'PWorkGroupId' => $workGroupId,
//            ));
//            $update->where(array('PWorkGroupName' => $trans['WorkGroupName'],'ProjectId'=>$iProjectId, 'PWorkGroupId'=>0));
//            $statement = $sql->getSqlStringForSqlObject($update);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $select = $sql->select();
            $select->from('Proj_TenderWBSMaster')
                ->columns(array('WBSId', 'ParentId', 'WBSName', 'LastLevel', 'SortOrder', 'MWBSId'))
                ->where(array("TenderEnquiryId" => $iEnquiryId))
                ->order('WBSId ASC');

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $newwbsArray = [];
            foreach ($rfctrans as $trans) {
                $iTWBSId = $trans['WBSId'];
                $iMWBSId = $trans['MWBSId'];
                $iTParentId = $trans['ParentId'];
                $iMParentId = 0;

                if ($iTParentId != 0) {
                    $iMParentId = $newwbsArray[$iTParentId];
                }

                if ($iMWBSId == 0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_WBSMaster');
                    $insert->Values(array('ParentId' => $iMParentId, 'WBSName' => $trans['WBSName'], 'LastLevel' => $trans['LastLevel'], 'SortOrder' => $trans['SortOrder'], 'ProjectId' => $iProjectId));

                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $wbsId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $update = $sql->update();
                    $update->table('Proj_TenderWBSMaster');
                    $update->set(array(
                        'MWBSId' => $wbsId,
                    ));
                    $update->where(array('WBSId' => $iTWBSId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $newwbsArray[$iTWBSId] = $wbsId;
                } else {
                    $newwbsArray[$iTWBSId] = $iMWBSId;
                }
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderQuotationTrans'))
                ->join(array('b' => 'Proj_TenderWorkGroup'), 'a.ProjectWorkGroupId=b.PWorkGroupId', array(), $select:: JOIN_LEFT)
                ->columns(array('QuotationTransId', 'WorkGroupId', 'ParentId', 'IOWs', 'SerialNo', 'RefSerialNo', 'Specification',
                    'ShortSpec', 'Header', 'UnitId', 'Rate', 'Qty', 'Amount', 'IOWId', 'WorkingQty', 'RWorkingQty', 'CementRatio', 'SandRatio', 'MetalRatio', 'ThickQty', 'SRate', 'RRate', 'MixType', 'ParentText', 'PWorkGroupId' => new Expression("b.MWorkGroupId"), 'ProjectWorkGroupName', 'SiteMixRatio', 'ReadyMixRatio', 'QuotedRate', 'QuotedAmount',
                    'ProjectIOWId', 'WastageAmt', 'BaseRate', 'QualifierValue', 'TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue', 'RTotalRate', 'RNetRate', 'ParentName', 'WorkTypeId'))
                ->where(array("QuotationId" => $iQuotationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['QuotationTransId'];
                $slNo = "";

                $iHeader = $this->bsf->isNullCheck($trans['Header'], 'number');

                $insert = $sql->insert();
                $insert->into('Proj_ProjectIOWMaster');
                $insert->Values(array('ProjectId' => $iProjectId, 'WorkGroupId' => $trans['WorkGroupId'], 'PWorkGroupId' => $trans['PWorkGroupId'], 'ParentId' => $trans['ParentId'],
                    'SerialNo' => $trans['SerialNo'], 'RefSerialNo' => $trans['RefSerialNo'], 'Header' => $trans['Header'],
                    'Specification' => $trans['Specification'], 'ShortSpec' => $trans['ShortSpec'], 'UnitId' => $trans['UnitId'], 'IOWId' => $trans['IOWId'],
                    'WorkingQty' => $trans['WorkingQty'], 'RWorkingQty' => $trans['RWorkingQty'], 'CementRatio' => $trans['CementRatio'],
                    'SandRatio' => $trans['SandRatio'], 'MetalRatio' => $trans['MetalRatio'], 'ThickQty' => $trans['ThickQty'], 'MixType' => $trans['MixType'], 'SRate' => $trans['SRate'], 'RRate' => $trans['RRate'], 'Rate' => $trans['Rate'], 'ParentText' => $trans['ParentText'], 'PWorkGroupName' => $trans['ProjectWorkGroupName'], 'WorkTypeId' => $trans['WorkTypeId'], 'ParentName' => $trans['ParentName'], 'QuotationTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iowid = $dbAdapter->getDriver()->getLastGeneratedValue();

                $update = $sql->update();
                $update->table('Proj_TenderQuotationTrans');
                $update->set(array(
                    'ProjectIOWId' => $iowid,
                ));
                $update->where(array('QuotationTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                if ($iHeader == 0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOW');
                    $insert->Values(array('ProjectIOWId' => $iowid, 'Qty' => $trans['Qty'],
                        'Rate' => $trans['Rate'], 'Amount' => $trans['Amount'],
                        'QualRate' => $trans['Rate'], 'QualAmount' => $trans['Amount'],
                        'ProjectId' => $iProjectId, 'RFCTransId' => $rfctransid,
                        'WastageAmt' => $trans['WastageAmt'], 'BaseRate' => $trans['BaseRate'],
                        'QualifierValue' => $trans['QualifierValue'], 'TotalRate' => $trans['TotalRate'],
                        'NetRate' => $trans['NetRate'], 'RWastageAmt' => $trans['RWastageAmt'],
                        'RBaseRate' => $trans['RBaseRate'], 'RQualifierValue' => $trans['RQualifierValue'],
                        'SiteMixRatio' => $trans['SiteMixRatio'], 'ReadyMixRatio' => $trans['ReadyMixRatio'],
                        'RTotalRate' => $trans['RTotalRate'], 'RNetRate' => $trans['RNetRate']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationQualTrans')
                        ->columns(array('ProjectIOWId' => new Expression("'$iowid'"), 'ProjectId' => new Expression("'$iProjectId'"), 'QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'ExpressionAmt',
                            'TaxableAmt', 'TaxAmt', 'SurChargeAmt', 'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt', 'SortId', 'MixType'))
                        ->where(array("QuotationTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWQualTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'ExpressionAmt',
                        'TaxableAmt', 'TaxAmt', 'SurChargeAmt', 'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt', 'SortId', 'MixType'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationRateAnalysis')
                        ->columns(array('IOWId' => new Expression("'$iowid'"), 'ProjectId' => new Expression("'$iProjectId'"), 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'SortId', 'RateType', 'QuotationTransId', 'Wastage', 'WastageQty', 'WastageAmount', 'Weightage'))
                        ->where(array("QuotationTransId='$rfctransid'"));
                    $select->order('SortId ASC');

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectRateAnalysis');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'SortId', 'RateType', 'QuotationTransId', 'Wastage', 'WastageQty', 'WastageAmount', 'Weightage'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                $select = $sql->select();
//                $select->from('Proj_ProjectIOWMeasurement');
//                $select->columns(array('ProjectId'=>new Expression("$iProjectId"), 'ProjectIOWId'=>new Expression("$iowid"), 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'))
//                    ->where(array("ProjectId=$iProjectId"));
//
//                $insert = $sql->insert();
//                $insert->into('Proj_ProjectIOWMeasurement');
//                $insert->columns(array('ProjectId', 'ProjectIOWId', 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'));
//                $insert->Values($select);
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderWBSTrans'))
                        ->join(array('b' => 'Proj_TenderWBSMaster'), 'a.WBSId=b.WBSId', array(), $select:: JOIN_INNER)
                        ->columns(array('IOWId' => new Expression("'$iowid'"), 'ProjectId' => new Expression("'$iProjectId'"), 'WBSId' => new Expression("b.MWBSId"), 'Qty'))
                        ->where(array("QuotationTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_WBSTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'WBSId', 'Qty'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateProjectDetails($iProjectId, $iowid, "B", $dbAdapter);
                }
            }

            ProjectHelper::_updateResourceRate($iProjectId, "B", $dbAdapter);

            //OtherCost
            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderOHTrans'))
                ->join(array('b' => 'Proj_OHMaster'), 'a.OHId=b.OHId', array('OHTypeId'), $select:: JOIN_INNER)
                ->columns(array('TenderTransId', 'OHId', 'Amount'))
                ->where(array("QuotationId" => $iQuotationId));

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['TenderTransId'];
                $iOHTypeId = $trans['OHTypeId'];
                $OHId = $trans['OHId'];
                $Amount = $trans['Amount'];

                $insert = $sql->insert();
                $insert->into('Proj_OHAbstract');
                $insert->Values(array('ProjectId' => $iProjectId, 'OHId' => $OHId, 'Amount' => $Amount,
                    'TenderTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iOHAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();


                if ($iOHTypeId == 1) {
                    //Item
                    $select = $sql->select();
                    $select->from('Proj_TenderOHItemTrans')
                        ->columns(array('TenderItemTransId', 'ProjectIOWId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHItem');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ProjectIOWId' => $atrans['ProjectIOWId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderItemTransId' => $atrans['TenderItemTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 2) {
                    //Material
                    $select = $sql->select();
                    $select->from('Proj_TenderOHMaterialTrans')
                        ->columns(array('TenderMaterialTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHMaterial');

                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderMaterialTransId' => $atrans['TenderMaterialTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 3) {
                    //Labour
                    $select = $sql->select();
                    $select->from('Proj_TenderOHLabourTrans')
                        ->columns(array('TenderLabourTransId', 'ResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHLabour');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ResourceId' => $atrans['ResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderLabourTransId' => $atrans['TenderLabourTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 4) {
                    //Service
                    $select = $sql->select();
                    $select->from('Proj_TenderOHServiceTrans')
                        ->columns(array('TenderServiceTransId', 'ServiceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHService');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ServiceId' => $atrans['ServiceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderServiceTransId' => $atrans['TenderServiceTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 5) {
                    //Machinery
                    $select = $sql->select();
                    $select->from('Proj_TenderOHMachineryTrans')
                        ->columns(array('TenderMachineryTransId', 'MResourceId', 'Nos', 'WorkingQty', 'TotalQty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $rfcMtransId = $atrans['TenderMachineryTransId'];
                        $insert = $sql->insert();
                        $insert->into('Proj_OHMachinery');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'Nos' => $atrans['Nos'],
                            'WorkingQty' => $atrans['WorkingQty'], 'TotalQty' => $atrans['TotalQty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderMachineryTransId' => $atrans['TenderMachineryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from('Proj_TenderOHMachineryDetails')
                            ->columns(array('TenderMachineryDetailId', 'ProjectIOWId', 'Percentage', 'Amount'))
                            ->where(array("TenderMachineryTransId='$rfcMtransId'"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rfcmachinery = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($rfcmachinery as $mtrans) {
                            $insert = $sql->insert();
                            $insert->into('Proj_OHMachineryDetails');
                            $insert->Values(array('ProjectId' => $iProjectId, 'MachineryTransId' => $iMTransId, 'ProjectIOWId' => $mtrans['ProjectIOWId'], 'Percentage' => $mtrans['Percentage'],
                                'Amount' => $mtrans['Amount'],
                                'TenderMachineryDetailId' => $mtrans['TenderMachineryDetailId']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                } else if ($iOHTypeId == 6) {
                    //AdminExpense
                    $select = $sql->select();
                    $select->from('Proj_TenderOHAdminExpenseTrans')
                        ->columns(array('TenderExpenseTransId', 'ExpenseId', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHAdminExpense');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'ExpenseId' => $atrans['ServiceId'],
                            'Amount' => $atrans['Amount'], 'TenderExpenseTransId' => $atrans['TenderExpenseTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 7) {
                    //Salary
                    $select = $sql->select();
                    $select->from('Proj_TenderOHSalaryTrans')
                        ->columns(array('TenderSalaryTransId', 'PositionId', 'Nos', 'cMonths', 'Salary', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHSalary');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'PositionId' => $atrans['PositionId'],
                            'Nos' => $atrans['Nos'], 'cMonths' => $atrans['cMonths'], 'Salary' => $atrans['Salary'], 'Amount' => $atrans['Amount'], 'TenderSalaryTransId' => $atrans['TenderSalaryTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else if ($iOHTypeId == 8) {
                    //Fuel
                    $select = $sql->select();
                    $select->from('Proj_TenderOHFuelTrans')
                        ->columns(array('TenderFuelTransId', 'MResourceId', 'FResourceId', 'Qty', 'Rate', 'Amount'))
                        ->where(array("TenderTransId='$rfctransid'"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfcitem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ($rfcitem as $atrans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_OHFuelTrans');
                        $insert->Values(array('ProjectId' => $iProjectId, 'OHAbsId' => $iOHAbsId, 'MResourceId' => $atrans['MResourceId'], 'FResourceId' => $atrans['FResourceId'],
                            'Qty' => $atrans['Qty'], 'Rate' => $atrans['Rate'], 'Amount' => $atrans['Amount'],
                            'TenderFuelTransId' => $atrans['TenderFuelTransId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        }
    }

    function _updateProjectIOW($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType','RevRequired'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $iRevRequired = 0;

        $sProjectType = "";
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
            $iRevRequired = $this->bsf->isNullCheck($rfcregister['RevRequired'], 'number');
        }

        if ($rfctype == "ProjectIOW-Add") {
            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->join(array('b' => 'Proj_RFCIOWRate'), 'a.RFCTransId=b.RFCTransId', array('WastageAmt', 'BaseRate', 'QualifierValue','TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue','RTotalRate', 'RNetRate'), $select:: JOIN_LEFT)
                ->columns(array('RFCTransId', 'WorkGroupId','PWorkGroupId','WorkTypeId', 'ParentId','IOWId','SerialNo', 'RefSerialNo','Header','Specification', 'ShortSpec', 'UnitId', 'Qty', 'Rate', 'Amount', 'WorkingQty', 'RWorkingQty', 'CementRatio', 'SandRatio', 'MetalRatio', 'ThickQty', 'MixType', 'SRate', 'RRate', 'Rate', 'ParentText','ParentName','PWorkGroupName','SiteMixRatio','ReadyMixRatio'))
                ->where(array("a.RFCRegisterId=$rfcid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $resArr =  array();
            $k=0;

            $resIOWId =  array();
            $n=0;

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iparentid = $trans['ParentId'];
                $iworkgroupid = $trans['WorkGroupId'];

                $slNo = "";

                $iUnitId = $this->bsf->isNullCheck($trans['UnitId'], 'number');

                $insert = $sql->insert();
                $insert->into('Proj_ProjectIOWMaster');
                $insert->Values(array('WorkGroupId' => $trans['WorkGroupId'],'PWorkGroupId' => $trans['PWorkGroupId'], 'ParentId' => $trans['ParentId'],'IOWId' => $trans['IOWId'],
                    'SerialNo' => $trans['SerialNo'], 'RefSerialNo' => $trans['RefSerialNo'],'Header' => $trans['Header'],
                    'Specification' => $trans['Specification'],'ShortSpec' => $trans['ShortSpec'], 'UnitId' => $trans['UnitId'],
                    'WorkingQty' => $trans['WorkingQty'], 'RWorkingQty' => $trans['RWorkingQty'], 'CementRatio' => $trans['CementRatio'],
                    'SandRatio' => $trans['SandRatio'], 'MetalRatio' => $trans['MetalRatio'], 'ThickQty' => $trans['ThickQty'], 'MixType' => $trans['MixType'], 'SRate' => $trans['SRate'], 'RRate' => $trans['RRate'], 'Rate' => $trans['Rate'], 'ParentText' => $trans['ParentText'],'ParentName' => $trans['ParentName'],'SlNo' => $slNo, 'ProjectId' => $iProjectId,'PWorkGroupName'=>$trans['PWorkGroupName'],'WorkTypeId'=>$trans['WorkTypeId'], 'RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iowid = $dbAdapter->getDriver()->getLastGeneratedValue();

                if (($trans['ParentName'] !="" &&  $iparentid ==0) || ($trans['PWorkGroupName'] !=0 && $iworkgroupid==0)) {
                    $resIOWId[$n]['IOWId'] = $iowid;
                    $n = $n + 1;
                }


                $update = $sql->update();
                $update->table('Proj_RFCIOWTrans');
                $update->set(array(
                    'ProjectIOWId' => $iowid,
                ));
                $update->where(array('RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $sSpec = $trans['Specification'];

                $update = $sql->update();
                $update->table('Proj_RFCIOWTrans');
                $update->set(array(
                    'ParentId' => $iowid,'WorkGroupId'=>$trans['WorkGroupId'],'WorkTypeId'=>$trans['WorkTypeId']
                ));
                $update->where("convert(varchar,ParentName) = '$sSpec' and RFCRegisterId = $rfcid and ParentId =0");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $resArr[$k]['IOWId'] =$iowid;
                $resArr[$k]['ParentName'] =$sSpec;
                $resArr[$k]['WorkGroupId'] =$trans['WorkGroupId'];
                $resArr[$k]['WorkTypeId'] =$trans['WorkTypeId'];
                $k= $k+1;


                if ($iUnitId != 0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOW');
                    $insert->Values(array('ProjectIOWId' => $iowid, 'Qty' => $trans['Qty'],
                        'Rate' => $trans['Rate'], 'Amount' => $trans['Amount'],
                        'QualRate' => $trans['Rate'], 'QualAmount' => $trans['Amount'],
                        'ProjectId' => $iProjectId, 'RFCTransId' => $rfctransid,
                        'WastageAmt'=> $trans['WastageAmt'], 'BaseRate'=> $trans['BaseRate'],
                        'QualifierValue'=> $trans['QualifierValue'],'TotalRate'=> $trans['TotalRate'],
                        'NetRate'=> $trans['NetRate'], 'RWastageAmt'=> $trans['RWastageAmt'],
                        'RBaseRate'=> $trans['RBaseRate'], 'RQualifierValue'=> $trans['RQualifierValue'],
                        'SiteMixRatio' => $trans['SiteMixRatio'], 'ReadyMixRatio' => $trans['ReadyMixRatio'],
                        'RTotalRate'=> $trans['RTotalRate'], 'RNetRate'=> $trans['RNetRate']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCIOWQualTrans')
                        ->columns(array('IOWId'=> new Expression("'$iowid'"), 'ProjectId'=> new Expression("'$iProjectId'"),'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                            'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'))
                        ->where(array("RFCTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWQualTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId','QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                        'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCRateAnalysis')
                        ->columns(array('IOWId'=> new Expression("'$iowid'"), 'ProjectId'=> new Expression("'$iProjectId'"), 'IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $select->order('SortId ASC');

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectRateAnalysis');
                    $insert->columns(array('ProjectIOWId', 'ProjectId','IncludeFlag', 'ReferenceId', 'ResourceId','SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula','MixType','TransType','SortId','RateType','RFCTransId','Wastage','WastageQty','WastageAmount','Weightage','ResourceName'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_ProjectIOWMeasurement');
                    $select->columns(array('ProjectId'=>new Expression("$iProjectId"), 'ProjectIOWId'=>new Expression("$iowid"), 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'))
                        ->where(array("ProjectId=$iProjectId"));

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWMeasurement');
                    $insert->columns(array('ProjectId', 'ProjectIOWId', 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $select = $sql->select();
                    $select->from('Proj_RFCIOWWbsTrans')
                        ->columns(array('IOWId'=> new Expression("'$iowid'"), 'ProjectId'=> new Expression("'$iProjectId'"), 'WBSId', 'Qty','Measurement','CellName','SelectedColumns'))
                        ->where(array("RFCTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_WBSTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId','WBSId', 'Qty','Measurement','CellName','SelectedColumns'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateProjectDetails($iProjectId, $iowid, $sProjectType, $dbAdapter);
                }
            }

            if ($k >0) {
                for ($j = 0; $j < $k; $j++) {
                    $iIOWId = $resArr[$j]['IOWId'];
                    $sParentName = $resArr[$j]['ParentName'];
                    $iWorkGroupId = $resArr[$j]['WorkGroupId'];
                    $iWorkTypeId = $resArr[$j]['WorkTypeId'];

                    $update = $sql->update();
                    $update->table('Proj_ProjectIOWMaster');
                    $update->set(array(
                        'ParentId' => $iIOWId,'WorkGroupId'=>$iWorkGroupId,'WorkTypeId'=>$iWorkTypeId
                    ));
                    $update->where("convert(varchar,ParentName) = '$sParentName' and ParentId =0");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }


            $bAutoCode = false;
            $select = $sql->select();
            $select->from('Proj_ResourceCodeSetup')
                ->columns(array('GenType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if ($code['GenType'] == 1) $bAutoCode = true;

            $select = $sql->select();
            $select->from('Proj_RFCResourceTrans')
                ->columns(array('RFCTransId', 'ResourceId', 'Code', 'ResourceName', 'ResourceGroupId', 'TypeId', 'UnitId', 'LeadDays', 'AnalysisMQty', 'AnalysisAQty', 'Rate', 'RateType', 'LRate', 'MRate', 'ARate', 'WorkUnitId', 'WorkRate', 'MaterialType'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $rescode = $trans['Code'];
                $iTypeId = $trans['TypeId'];
                $iGroupid = $trans['ResourceGroupId'];

                if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);

                $insert = $sql->insert();
                $insert->into('Proj_Resource');
                $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                    'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                    'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                    'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
                //$resid = $trans['ResourceId'];

                if ($trans['ResourceGroupId'] !=0) {
                    $update = $sql->update();
                    $update->table('Proj_ResourceGroup');
                    $update->set(array(
                        'GroupUsed' => 1,
                    ));
                    $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $update = $sql->update();
                $update->table('Proj_RFCResourceTrans');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RFCRateAnalysis');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RFCActivityTrans');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ProjectRateAnalysis');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCActivityTrans');
            $subQuery->columns(array("MResourceId" => new Expression('DISTINCT(MResourceId)')));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $delete = $sql->delete();
            $delete->from('Proj_ProjectResourceActivityTrans')
                ->where->expression('MResourceId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCActivityTrans');
            $select->columns(array('ProjectId'=>new Expression("$iProjectId"), 'MResourceId','ActivityType', 'ResourceId', 'Qty','Rate','Amount','RFCRegisterId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectResourceActivityTrans');
            $insert->columns(array('ProjectId', 'MResourceId', 'ActivityType', 'ResourceId', 'Qty','Rate','Amount','RFCRegisterId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCWorkGroupTrans')
                ->columns(array('RFCTransId', 'WorkGroupId', 'WorkGroupName','SerialNo','WorkTypeId'))
                ->where(array("RFCRegisterId='$rfcid'"));

            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            $wgArr =  array();
//            $iwg=0;
            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];

                $insert = $sql->insert();
                $insert->into('Proj_ProjectWorkGroup');
                $insert->Values(array('WorkGroupId' => $trans['WorkGroupId'],'SerialNo'=>$trans['SerialNo'], 'WorkGroupName' => $trans['WorkGroupName'],'WorkTypeId'=>$trans['WorkTypeId'], 'ProjectId' => $iProjectId,'RFCTransId' => $rfctransid));

                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $workGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

//                $wgArr[$iwg]['PWorkGroupId'] =$workGroupId;
//                $wgArr[$iwg]['PWorkGroupName'] =$trans['WorkGroupName'];
//                $iwg= $iwg+1;

                $update = $sql->update();
                $update->table('Proj_RFCWorkGroupTrans');
                $update->set(array(
                    'PWorkGroupId' => $workGroupId,
                ));
                $update->where(array('RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RFCIOWTrans');
                $update->set(array(
                    'PWorkGroupId' => $workGroupId,
                ));
                $update->where(array('PWorkGroupName' => $trans['WorkGroupName'],'RFCRegisterId'=> $rfcid,'PWorkGroupId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ProjectIOWMaster');
                $update->set(array(
                    'PWorkGroupId' => $workGroupId,
                ));
                $update->where(array('PWorkGroupName' => $trans['WorkGroupName'],'PWorkGroupId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $select = $sql->select();
            $select->from('Proj_RFCProjectResourceRate')
                ->columns(array('ResourceId', 'Rate', 'IncludeFlag','RateType'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $iResId = $trans['ResourceId'];
                $dRate = $trans['Rate'];
                $sRateType = $trans['RateType'];
                $iInclFlag = $trans['IncludeFlag'];

                $select = $sql->select();
                $select->from('Proj_ProjectDetails')
                    ->columns(array('ProjectIOWId'))
                    ->where(array('ResourceId'=>$iResId,'ProjectId'=>$iProjectId,'RateType'=>$sRateType));
                $statement = $sql->getSqlStringForSqlObject($select);
                $pdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach ($pdetails as $ptrans) {
                    $iPIOWid =  $ptrans["ProjectIOWId"];

                    $update = $sql->update();
                    $update->table('Proj_ProjectRateAnalysis');
                    $update->set(array(
                        'Rate' => $dRate, 'Amount' => new Expression("Qty*$dRate"), 'WastageAmount' => new Expression("WastageQty*$dRate")
                    ));
                    $update->where(array('ResourceId' => $iResId,'ProjectIOWId'=>$iPIOWid,'RateType'=>$sRateType));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateIOWRate($iProjectId, $iPIOWid, $dbAdapter);
                    ProjectHelper::_updateProjectDetails($iProjectId, $iPIOWid, $sProjectType, $dbAdapter);
                }
            }

//            if ($iwg >0) {
//                for ($j = 0; $j < $iwg; $j++) {
//                    $iWGId = $wgArr[$j]['PWorkGroupId'];
//                    $sWGName = $wgArr[$j]['PWorkGroupName'];
//
//                    $update = $sql->update();
//                    $update->table('Proj_ProjectIOWMaster');
//                    $update->set(array(
//                        'PWorkGroupId' => $iWGId,
//                    ));
//                    $update->where(array('PWorkGroupName' => $sWGName,'PWorkGroupId'=>0));
//                    $statement = $sql->getSqlStringForSqlObject($update);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                }
//            }

            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);
        } else if  ($rfctype == "ProjectIOW-Edit") {

            if ($iRevRequired) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->join(array('b' => 'Proj_RFCIOWRate'), 'a.RFCTransId=b.RFCTransId', array('WastageAmt', 'BaseRate', 'QualifierValue', 'TotalRate', 'NetRate', 'RWastageAmt', 'RBaseRate', 'RQualifierValue', 'RTotalRate', 'RNetRate'), $select:: JOIN_LEFT)
                ->columns(array('RFCTransId', 'ProjectIOWId', 'WorkGroupId', 'PWorkGroupId', 'WorkTypeId', 'ParentId','IOWId','SerialNo', 'RefSerialNo', 'Header', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'Rate', 'Amount', 'WorkingQty', 'RWorkingQty', 'CementRatio', 'SandRatio', 'MetalRatio', 'ThickQty', 'MixType', 'SRate', 'RRate', 'ParentText','ParentName', 'PWorkGroupName','SiteMixRatio','ReadyMixRatio'))
                ->where(array("a.RFCRegisterId=$rfcid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iPIOWId = $trans['ProjectIOWId'];
                $slNo = "";

                $iUnitId = $this->bsf->isNullCheck($trans['UnitId'], 'number');

                $update = $sql->update();
                $update->table('Proj_ProjectIOWMaster');
                $update->set(array('WorkGroupId' => $trans['WorkGroupId'], 'ParentId' => $trans['ParentId'],'IOWId' => $trans['IOWId'],
                    'SerialNo' => $trans['SerialNo'], 'RefSerialNo' => $trans['RefSerialNo'], 'Header' => $trans['Header'],
                    'Specification' => $trans['Specification'], 'ShortSpec' => $trans['ShortSpec'], 'UnitId' => $trans['UnitId'],
                    'WorkingQty' => $trans['WorkingQty'], 'RWorkingQty' => $trans['RWorkingQty'], 'CementRatio' => $trans['CementRatio'],
                    'SandRatio' => $trans['SandRatio'], 'MetalRatio' => $trans['MetalRatio'], 'ThickQty' => $trans['ThickQty'], 'MixType' => $trans['MixType'], 'SRate' => $trans['SRate'], 'RRate' => $trans['RRate'], 'Rate' => $trans['Rate'], 'ParentText' => $trans['ParentText'],'ParentName' => $trans['ParentName'], 'SlNo' => $slNo, 'ProjectId' => $iProjectId, 'WorkTypeId' => $trans['WorkTypeId'], 'RFCTransId' => $rfctransid));
                $update->where(array('ProjectIOWId' => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                if ($iUnitId != 0) {
                    $update = $sql->update();
                    $update->table('Proj_ProjectIOW');
                    $update->set(array('Qty' => $trans['Qty'],
                        'Rate' => $trans['Rate'], 'Amount' => $trans['Amount'],
                        'QualRate' => $trans['Rate'], 'QualAmount' => $trans['Amount'],
                        'ProjectId' => $iProjectId, 'RFCTransId' => $rfctransid,
                        'WastageAmt' => $trans['WastageAmt'], 'BaseRate' => $trans['BaseRate'],
                        'QualifierValue' => $trans['QualifierValue'], 'TotalRate' => $trans['TotalRate'],
                        'NetRate' => $trans['NetRate'], 'RWastageAmt' => $trans['RWastageAmt'],
                        'RBaseRate' => $trans['RBaseRate'], 'RQualifierValue' => $trans['RQualifierValue'],
                        'SiteMixRatio' => $trans['SiteMixRatio'], 'ReadyMixRatio' => $trans['ReadyMixRatio'],
                        'RTotalRate' => $trans['RTotalRate'], 'RNetRate' => $trans['RNetRate']));
                    $update->where(array('ProjectIOWId' => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectIOWQualTrans')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectRateAnalysis')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_WBSTrans')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectIOWMeasurement')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCIOWQualTrans')
                        ->columns(array('ProjectIOWId' => new Expression("'$iPIOWId'"), 'ProjectId' => new Expression("'$iProjectId'"), 'QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'ExpressionAmt',
                            'TaxableAmt', 'TaxAmt', 'SurChargeAmt', 'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt', 'SortId', 'MixType'))
                        ->where(array("RFCTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWQualTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'ExpressionAmt',
                        'TaxableAmt', 'TaxAmt', 'SurChargeAmt', 'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt', 'SortId', 'MixType'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCRateAnalysis')
                        ->columns(array('IOWId' => new Expression("'$iPIOWId'"), 'ProjectId' => new Expression("'$iProjectId'"), 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'SortId', 'RateType', 'RFCTransId', 'Wastage', 'WastageQty', 'WastageAmount', 'Weightage', 'ResourceName'))
                        ->where(array("RFCTransId='$rfctransid'"));
                    $select->order('SortId ASC');

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectRateAnalysis');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'SortId', 'RateType', 'RFCTransId', 'Wastage', 'WastageQty', 'WastageAmount', 'Weightage', 'ResourceName'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_ProjectIOWMeasurement');
                    $select->columns(array('ProjectId' => new Expression("$iProjectId"), 'ProjectIOWId' => new Expression("'$iPIOWId'"), 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'))
                        ->where(array("ProjectId=$iProjectId"));

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWMeasurement');
                    $insert->columns(array('ProjectId', 'ProjectIOWId', 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('Proj_RFCIOWWbsTrans')
                        ->columns(array('IOWId' => new Expression("'$iPIOWId'"), 'ProjectId' => new Expression("'$iProjectId'"), 'WBSId', 'Qty','Measurement','CellName','SelectedColumns'))
                        ->where(array("RFCTransId='$rfctransid'"));

                    $insert = $sql->insert();
                    $insert->into('Proj_WBSTrans');
                    $insert->columns(array('ProjectIOWId', 'ProjectId', 'WBSId', 'Qty','Measurement','CellName','SelectedColumns'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateProjectDetails($iProjectId, $iPIOWId, $sProjectType, $dbAdapter);
                }
            }


            $bAutoCode = false;
            $select = $sql->select();
            $select->from('Proj_ResourceCodeSetup')
                ->columns(array('GenType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if ($code['GenType'] == 1) $bAutoCode = true;

            $select = $sql->select();
            $select->from('Proj_RFCResourceTrans')
                ->columns(array('RFCTransId', 'ResourceId', 'Code', 'ResourceName', 'ResourceGroupId', 'TypeId', 'UnitId', 'LeadDays', 'AnalysisMQty', 'AnalysisAQty', 'Rate', 'RateType', 'LRate', 'MRate', 'ARate', 'WorkUnitId', 'WorkRate', 'MaterialType'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $rescode = $trans['Code'];
                $iTypeId = $trans['TypeId'];
                $iGroupid = $trans['ResourceGroupId'];

                if ($bAutoCode == true) $rescode = ProjectHelper::_GetResCode($iTypeId, $iGroupid, $dbAdapter);

                $insert = $sql->insert();
                $insert->into('Proj_Resource');
                $insert->Values(array('Code' => $rescode, 'ResourceName' => $trans['ResourceName'], 'ResourceGroupId' => $trans['ResourceGroupId'], 'TypeId' => $trans['TypeId'],
                    'UnitId' => $trans['UnitId'], 'LeadDays' => $trans['LeadDays'], 'AnalysisAQty' => $trans['AnalysisAQty'],
                    'AnalysisMQty' => $trans['AnalysisMQty'], 'Rate' => $trans['Rate'], 'RateType' => $trans['RateType'], 'LRate' => $trans['LRate'], 'MRate' => $trans['MRate'],
                    'ARate' => $trans['ARate'], 'WorkUnitId' => $trans['WorkUnitId'], 'WorkRate' => $trans['WorkRate'], 'MaterialType' => $trans['MaterialType'], 'RFCRegisterId' => $rfcid));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $resid = $dbAdapter->getDriver()->getLastGeneratedValue();
                //$resid = $trans['ResourceId'];

                if ($trans['ResourceGroupId'] !=0) {
                    $update = $sql->update();
                    $update->table('Proj_ResourceGroup');
                    $update->set(array(
                        'GroupUsed' => 1,
                    ));
                    $update->where(array('ResourceGroupId' => $trans['ResourceGroupId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $update = $sql->update();
                $update->table('Proj_RFCResourceTrans');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('RFCTransId' => $rfctransid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RFCRateAnalysis');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RFCActivityTrans');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $update = $sql->update();
                $update->table('Proj_ProjectRateAnalysis');
                $update->set(array(
                    'ResourceId' => $resid,
                ));
                $update->where(array('ResourceName' => $trans['ResourceName'],'ResourceId'=>0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCActivityTrans');
            $subQuery->columns(array("MResourceId" => new Expression('DISTINCT(MResourceId)')));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $delete = $sql->delete();
            $delete->from('Proj_ProjectResourceActivityTrans')
                ->where->expression('MResourceId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCActivityTrans');
            $select->columns(array('ProjectId'=>new Expression("$iProjectId"), 'MResourceId','ActivityType', 'ResourceId', 'Qty','Rate','Amount','RFCRegisterId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectResourceActivityTrans');
            $insert->columns(array('ProjectId', 'MResourceId', 'ActivityType', 'ResourceId', 'Qty','Rate','Amount','RFCRegisterId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_RFCProjectResourceRate')
                ->columns(array('ResourceId', 'Rate', 'IncludeFlag','RateType'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $iResId = $trans['ResourceId'];
                $dRate = $trans['Rate'];
                $sRateType = $trans['RateType'];
                $iInclFlag = $trans['IncludeFlag'];

                $select = $sql->select();
                $select->from('Proj_ProjectDetails')
                    ->columns(array('ProjectIOWId'))
                    ->where(array('ResourceId' => $iResId, 'ProjectId' => $iProjectId,'RateType'=>$sRateType));
                $statement = $sql->getSqlStringForSqlObject($select);
                $pdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach ($pdetails as $ptrans) {
                    $iPIOWid = $ptrans["ProjectIOWId"];

                    $update = $sql->update();
                    $update->table('Proj_ProjectRateAnalysis');
                    $update->set(array(
                        'Rate' => $dRate, 'Amount' => new Expression("Qty*$dRate"), 'WastageAmount' => new Expression("WastageQty*$dRate")
                    ));
                    $update->where(array('ResourceId' => $iResId, 'ProjectIOWId' => $iPIOWid,'RateType'=>$sRateType));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    ProjectHelper::_updateIOWRate($iProjectId, $iPIOWid, $dbAdapter);
                    ProjectHelper::_updateProjectDetails($iProjectId, $iPIOWid, $sProjectType, $dbAdapter);
                }
            }

            ProjectHelper::_updateResourceRate($iProjectId, $sProjectType, $dbAdapter);

        } else if ($rfctype=="ProjectIOW-Qty") {

            if ($iRevRequired) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }


            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->columns(array('RFCTransId', 'ProjectIOWId','Qty'))
                ->where("a.RFCRegisterId=$rfcid  and Qty<>PrevQty");
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iPIOWId =  $trans['ProjectIOWId'];
                $dQty  = $trans['Qty'];

                $update = $sql->update();
                $update->table('Proj_ProjectIOW');
                $update->set(array('Qty' => $dQty,
                    'Amount' => new Expression("Rate*$dQty"),
                    'QualAmount' => new Expression("QualRate*$dQty"),
                    'RFCTransId' => $rfctransid));
                $update->where(array('ProjectIOWId' => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $select = $sql->select();
                $select->from('Proj_RFCIOWWbsTrans')
                    ->columns(array('WBSId','Qty'))
                    ->where("RFCTransId=$rfctransid and Qty<>PrevQty");
                $statement = $sql->getSqlStringForSqlObject($select);
                $wbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach ($wbstrans as $wtrans) {
                    $iwbsid =  $trans['WBSId'];
                    $dwQty  = $trans['Qty'];

                    $update = $sql->update();
                    $update->table('Proj_WBSTras');
                    $update->set(array('Qty' => $dwQty,
                        'Amount' => new Expression("Rate*$dwQty")));
                    $update->where(array('ProjectIOWId' => $iPIOWId,'WBSId'=>$iwbsid));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                ProjectHelper::_updateProjectDetails($iProjectId, $iPIOWId, $sProjectType, $dbAdapter);
            }

            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);

        } else if  ($rfctype == "Project-IOW-Delete") {

//            $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
//            if ($iRevId !=0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);

            $select = $sql->select();
            $select->from('Proj_RFCProjectIOWDeleteTrans')
                ->columns(array('ProjectIOWId'))
                ->where(array("RFCRegisterId='$rfcid'"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            foreach ($rfctrans as $trans) {
                $iPIOWId = $trans['ProjectIOWId'];

                $delete = $sql->delete();
                $delete->from('Proj_ProjectIOWQualTrans')
                    ->where(array("ProjectIOWId" => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delete = $sql->delete();
                $delete->from('Proj_ProjectRateAnalysis')
                    ->where(array("ProjectIOWId" => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delete = $sql->delete();
                $delete->from('Proj_ProjectIOWMeasurement')
                    ->where(array("ProjectIOWId" => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                if ($sProjectType != "P") {
                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectDetailsPlan')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectIOW')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectDetails')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_ProjectIOWPlan')
                        ->where(array("ProjectIOWId" => $iPIOWId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }

                $delete = $sql->delete();
                $delete->from('Proj_ProjectIOWMaster')
                    ->where(array("ProjectIOWId" => $iPIOWId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                $update = $sql->update();
//                $update->table('Proj_ProjectIOWMaster');
//                $update->set(array(
//                    'DeleteFlag' => 1,
//                ));
//                $update->where(array("ProjectIOWId" => $iPIOWId));
//                $statement = $sql->getSqlStringForSqlObject($update);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);
        }
    }

    function _updateSchedule($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
        }


        if ($rfctype == "Schedule-Add") {

            $iShId = ProjectHelper::_getScheduleName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
            if ($iShId !=0) ProjectHelper::_scheduleCopy($iProjectId, $sProjectType, $iShId, $dbAdapter);


            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_SchedulePlan');
            else $delete->from('Proj_Schedule');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_SchPredecessorsPlan');
            else $delete->from('Proj_SchPredecessors');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_ScheduleDetailsPlan');
            else $delete->from('Proj_ScheduleDetails');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_RFCSchedule');
            $select->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'Qty', 'RFCTransId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_SchedulePlan');
            $insert->into('Proj_Schedule');

            $insert->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'Qty', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCSchedule');
            $subQuery->columns(array("RFCTransId"));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $select = $sql->select();
            $select->from('Proj_RFCSchPredecessors');
            $select->columns(array('ProjectId' => new Expression("$iProjectId"), 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'))
                ->where->expression('RFCTransId IN ?', array($subQuery));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_SchPredecessorsPlan');
            else $insert->into('Proj_SchPredecessors');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCScheduleDetails');
            $select->columns(array('ProjectId','ProjectIOWId', 'WBSId', 'SDate', 'SQty', 'CQty', 'Holiday'))
                ->where(array('RFCRegisterId' => $rfcid));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_ScheduleDetailsPlan');
            else $insert->into('Proj_ScheduleDetails');
            $insert->columns(array('ProjectId','ProjectIOWId', 'WBSId', 'SDate', 'SQty', 'CQty', 'Holiday'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            ProjectHelper::_assignTask($iProjectId, $dbAdapter);

//            $select = $sql->select();
//            $select->from('Proj_WeekHoliday')
//                ->columns(array('WeekDay'))
//                ->where(array("ProjectId='$iProjectId'"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $weekHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//            $select = $sql->select();
//            $select->from('Proj_Holiday')
//                ->columns(array('HDate'))
//                ->where(array("ProjectId='$iProjectId'"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $tHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            $select = $sql->select();
//            if ($sProjectType == "P") $select->from('Proj_SchedulePlan');
//            else $select->from('Proj_Schedule');
//            $select->columns(array('ProjectId', 'StartDate', 'EndDate', 'Duration', 'Qty','Id','ProjectIOWId','WBSId'))
//                ->where(array("ProjectId=$iProjectId"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $shtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


//            foreach ($shtrans as $trans) {
//                $iPIOWId = $trans['ProjectIOWId'];
//                $iWBSId = $trans['WBSId'];
//                $dStartDate = $trans['StartDate'];
//                $dEndDate = $trans['EndDate'];
//                $iDuration = intval($trans['Duration']);
//                $dQty = floatval($trans['Qty']);
//                $dTQty = 0;
//                $dSplitQty = 0;
//                if ($iDuration != 0) $dSplitQty = $dQty / $iDuration;
//                $sDate = date('d-m-Y', strtotime($dStartDate));
//                while (strtotime($sDate) <= strtotime($dEndDate)) {
//                    $iHoliday=0;
//                    $bHoliday = ProjectHelper::_checkHoliDay($tHoliday, $weekHoliday, $sDate);
//                    if ($bHoliday ==true) $iHoliday=1;
//
//                    $insert = $sql->insert();
//                    if ($sProjectType == "P") $insert->into('Proj_ScheduleDetailsPlan');
//                    else $insert->into('Proj_ScheduleDetails');
//                    $insert->Values(array('ProjectId' => $iProjectId, 'ProjectIOWId' => $iPIOWId,'WBSId'=>$iWBSId,'Holiday'=>$iHoliday, 'sDate' => date('Y-m-d', strtotime($sDate)),
//                        'SQty' => $dSplitQty));
//                    $statement = $sql->getSqlStringForSqlObject($insert);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    $iTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                    if ($bHoliday == false) $dTQty = $dTQty + $dSplitQty;
//
//                    $sDate = date('d-m-Y', strtotime($sDate . "+1 days"));
//                    if (strtotime($sDate) > strtotime($dEndDate)) {
//                        if ($dTQty != $dQty) {
//                            $dFinalQty = $dSplitQty + ($dQty - $dTQty);
//                            $update = $sql->update();
//                            if ($sProjectType == "P") $update->table('Proj_ScheduleDetailsPlan');
//                            else $update->table('Proj_ScheduleDetails');
//                            $update->set(array(
//                                'SQty' => $dFinalQty,
//                            ));
//                            $update->where(array('TransId' => $iTransId));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }
//                }
//            }
        } else if ($rfctype == "Schedule-Edit") {
            $iShId = ProjectHelper::_getScheduleName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
            if ($iShId !=0) ProjectHelper::_scheduleCopy($iProjectId, $sProjectType, $iShId, $dbAdapter);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_SchedulePlan');
            else $delete->from('Proj_Schedule');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_SchPredecessorsPlan');
            else $delete->from('Proj_SchPredecessors');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_ScheduleDetailsPlan');
            else $delete->from('Proj_ScheduleDetails');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCSchedule');
            $select->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'Qty', 'RFCTransId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_SchedulePlan');
            else $insert->into('Proj_Schedule');

            $insert->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'Qty', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCSchedule');
            $subQuery->columns(array("RFCTransId"));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $select = $sql->select();
            $select->from('Proj_RFCSchPredecessors');
            $select->columns(array('ProjectId' => new Expression("$iProjectId"), 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'))
                ->where->expression('RFCTransId IN ?', array($subQuery));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_SchPredecessorsPlan');
            else $insert->into('Proj_SchPredecessors');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCScheduleDetails');
            $select->columns(array('ProjectId','ProjectIOWId', 'WBSId', 'SDate', 'SQty', 'CQty', 'Holiday'))
                ->where(array('RFCRegisterId' => $rfcid));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_ScheduleDetailsPlan');
            else $insert->into('Proj_ScheduleDetails');
            $insert->columns(array('ProjectId','ProjectIOWId', 'WBSId', 'SDate', 'SQty', 'CQty', 'Holiday'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            ProjectHelper::_assignTask($iProjectId, $dbAdapter);

//            $select = $sql->select();
//            if ($sProjectType == "P") $select->from('Proj_SchedulePlan');
//            else $select->from('Proj_Schedule');
//            $select->columns(array('ProjectId', 'StartDate', 'EndDate', 'Duration', 'Qty','ProjectIOWId','WBSId'))
//                ->where(array("ProjectId=$iProjectId"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $shtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//            foreach ($shtrans as $trans) {
//                $iPIOWId = $trans['ProjectIOWId'];
//                $iWBSId = $trans['WBSId'];
//                $dStartDate = $trans['StartDate'];
//                $dEndDate = $trans['EndDate'];
//                $iDuration = intval($trans['Duration']);
//                $dQty = floatval($trans['Qty']);
//                $dTQty = 0;
//                $dSplitQty = 0;
//                if ($iDuration != 0) $dSplitQty = $dQty / $iDuration;
//                $sDate = date('d-m-Y', strtotime($dStartDate));
//                while (strtotime($sDate) <= strtotime($dEndDate)) {
//                    $iHoliday=0;
//                    $bHoliday = $this->_checkHoliDay($tHoliday, $weekHoliday, $sDate);
//                    if ($bHoliday ==true) $iHoliday=1;
//                    $insert = $sql->insert();
//                    if ($sProjectType == "P") $insert->into('Proj_ScheduleDetailsPlan');
//                    else $insert->into('Proj_ScheduleDetails');
//                    $insert->Values(array('ProjectId' => $iProjectId, 'ProjectIOWId' => $iPIOWId,'WBSId'=>$iWBSId,'Holiday'=>$iHoliday, 'sDate' => date('Y-m-d', strtotime($sDate)),
//                        'SQty' => $dSplitQty));
//                    $statement = $sql->getSqlStringForSqlObject($insert);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    $iTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
//                    if ($bHoliday == false) $dTQty = $dTQty + $dSplitQty;
//                    $sDate = date('d-m-Y', strtotime($dStartDate . "+1 days"));
//                    if (strtotime($sDate) > strtotime($dEndDate)) {
//                        if ($dTQty != $dQty) {
//                            $dFinalQty = $dSplitQty + ($dQty - $dTQty);
//                            $update = $sql->update();
//                            if ($sProjectType == "P") $update->table('Proj_ScheduleDetailsPlan');
//                            else $update->table('Proj_ScheduleDetails');
//                            $update->set(array(
//                                'SQty' => $dFinalQty,
//                            ));
//                            $update->where(array('TransId' => $iTransId));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }
//                }
//            }
        } else if ($rfctype == "WBS-Schedule-Add") {

            $iShId = ProjectHelper::_getScheduleName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
            if ($iShId !=0) ProjectHelper::_wbsscheduleCopy($iProjectId,$sProjectType,$iShId,$dbAdapter);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_WBSSchedulePlan');
            else $delete->from('Proj_WBSSchedule');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_WBSSchPredecessorsPlan');
            else $delete->from('Proj_WBSSchPredecessors');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCSchedule');
            $select->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'RFCTransId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_WBSSchedulePlan');
            else $insert->into('Proj_WBSSchedule');
            $insert->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCSchedule');
            $subQuery->columns(array("RFCTransId"));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $select = $sql->select();
            $select->from('Proj_RFCSchPredecessors');
            $select->columns(array('ProjectId' => new Expression("$iProjectId"), 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'))
                ->where->expression('RFCTransId IN ?', array($subQuery));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_WBSSchPredecessorsPlan');
            else $insert->into('Proj_WBSSchPredecessors');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        } else if ($rfctype == "WBS-Schedule-Edit") {

            $iShId = ProjectHelper::_getScheduleName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
            if ($iShId !=0) ProjectHelper::_wbsscheduleCopy($iProjectId,$sProjectType,$iShId,$dbAdapter);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_WBSSchedulePlan');
            else $delete->from('Proj_WBSSchedule');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $delete = $sql->delete();
            if ($sProjectType=="P") $delete->from('Proj_WBSSchPredecessorsPlan');
            else $delete->from('Proj_WBSSchPredecessors');
            $delete->where(array('ProjectId'=>$iProjectId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('Proj_RFCSchedule');
            $select->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'RFCTransId'))
                ->where(array("RFCRegisterId=$rfcid"));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_WBSSchedulePlan');
            else $insert->into('Proj_WBSSchedule');

            $insert->columns(array('ProjectId', 'Id', 'Specification', 'StartDate', 'EndDate', 'Duration', 'Progress', 'Predecessor', 'Parent', 'ProjectIOWId', 'WBSId', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $subQuery = $sql->select();
            $subQuery->from('Proj_RFCSchedule');
            $subQuery->columns(array("RFCTransId"));
            $subQuery->where(array('RFCRegisterId' => $rfcid));

            $select = $sql->select();
            $select->from('Proj_RFCSchPredecessors');
            $select->columns(array('ProjectId' => new Expression("$iProjectId"), 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'))
                ->where->expression('RFCTransIdIN ?', array($subQuery));

            $insert = $sql->insert();
            if ($sProjectType == "P") $insert->into('Proj_WBSSchPredecessorsPlan');
            else $insert->into('Proj_WBSSchPredecessors');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'WBSId', 'PProjectIOWId', 'PWBSId', 'TaskType', 'Lag', 'FDate', 'PType', 'PPType', 'RFCTransId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        }
    }

    function _checkHoliDay($nHoliday,$weekHoliday,$argDate) {
        $bFound = false;
        if (in_array($argDate, $nHoliday)) {
            $bFound = true;
        }
        if ($bFound == false) {
            $sWeekDay = date('l', strtotime($argDate));
            if (in_array($sWeekDay, $weekHoliday)) {
                $bFound = true;
            }
        }
        return $bFound;
    }

    function _scheduleCopy($iProjectId,$sProjectType,$iShId, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        //Proj_Schedule
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_SchedulePlan');
        else $select->from('Proj_Schedule');
        $select->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId','ScheduleId' => new Expression("$iShId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ScheduleTrans');
        $insert->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId','ScheduleId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Proj_ScheduleDetails
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ScheduleDetailsPlan');
        else $select->from('Proj_ScheduleDetails');
        $select->columns(array('ProjectId','ProjectIOWId','WBSId','sDate','SQty', 'ScheduleId' => new Expression("$iShId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ScheduleDetailsTrans');
        $insert->columns(array('ProjectId','ProjectIOWId','WBSId','sDate','SQty', 'ScheduleId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Proj_Predecessors
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_SchPredecessorsPlan');
        else $select->from('Proj_SchPredecessors');
        $select->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId', 'ScheduleId' => new Expression("$iShId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_SchPredecessorsTrans');
        $insert->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId','ScheduleId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

    }

    function _wbsscheduleCopy($iProjectId,$sProjectType,$iShId, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        //Proj_Schedule
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_WBSSchedulePlan');
        else $select->from('Proj_WBSSchedule');
        $select->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','RFCTransId','ScheduleId' => new Expression("$iShId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_WBSScheduleTrans');
        $insert->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','RFCTransId','ScheduleId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Proj_Predecessors
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_WBSSchPredecessorsPlan');
        else $select->from('Proj_WBSSchPredecessors');
        $select->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId', 'ScheduleId' => new Expression("$iShId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_WBSSchPredecessorsTrans');
        $insert->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId','ScheduleId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

    }

    function _getScheduleName($argProjectId,$argType,$argRFCId,$dbAdapter)
    {
        $iShId=0;
        try {
            $iWidth = 0;
            $iMaxNo = 0;
            $iVNo = 0;
            $iLen = 0;
            $sPre = "";
            $sPrefix = "";
            $sSuffix = "";
            $sSeperator = "";

            $sStage = "";
            if ($argType =="P") {
                $sStage = "Plan";
            } else if ($argType =="B") {
                $sStage = "Budget";
            }

            $iMaxShId=0;
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ScheduleMaster')
                ->columns(array('ScheduleId'=>new Expression("Max(ScheduleId)")))
                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>$argType));
            $statement = $sql->getSqlStringForSqlObject($select);
            $maxrevmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($maxrevmaster)) {
                $iMaxShId = intval($this->bsf->isNullCheck($maxrevmaster['ScheduleId'],'number'));
            }
            $iShId=$iMaxShId;

            $sql     = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ScheduleNameSetup')
                ->columns(array('Prefix','Width','Suffix','Separator'))
                ->where(array("StageName"=>$sStage));
            $statement = $sql->getSqlStringForSqlObject($select);
            $namesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($namesetup)) {
                $iWidth = $this->bsf->isNullCheck($namesetup['Width'],'number');
                $sPrefix = $this->bsf->isNullCheck($namesetup['Prefix'],'string');
                $sSuffix = $this->bsf->isNullCheck($namesetup['Suffix'],'string');
                $sSeperator = $this->bsf->isNullCheck($namesetup['Separator'],'string');
            }

            $select = $sql->select();
            $select->from('Proj_ScheduleMaster')
                ->columns(array('OrderId'=>new Expression("Max(OrderId)")))
                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>$argType));
            $statement = $sql->getSqlStringForSqlObject($select);
            $revmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($revmaster)) {
                $iMaxNo = $this->bsf->isNullCheck($revmaster['OrderId'],'number');
            }

            $iVNo = $iMaxNo + 1;

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre."0";
            }

            $shname = $sPrefix.$sSeperator.$sPre.trim($iVNo);
            if ($sSuffix != "") {
                $shname =  $shname.$sSeperator.$sSuffix;
            }

            $insert = $sql->insert();
            $insert->into('Proj_ScheduleMaster');
            $insert->Values(array('OrderId' => $iVNo, 'ProjectId' => $argProjectId,
                'ScheduleName' => $shname, 'RevisionType'=> $argType,
                'RFCRegisterId' => $argRFCId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


        } catch (Zend_Exception $e) {
            echo "Error: " . $e->getMessage() . "</br>";
        }

        return $iShId;
    }

    function _updateProjectIOWPlan($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType','RevRequired'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        $iRevRequired = 0;
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
            $iRevRequired = $this->bsf->isNullCheck($rfcregister['RevRequired'], 'number');
        }

        if ($rfctype == "IOWPlan-Add") {
            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array('Rate','QualRate'), $select:: JOIN_INNER)
                ->columns(array('ProjectIOWId', 'Qty','PrevQty','RFCTransId'))
                ->where(array("a.RFCRegisterId=$rfcid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iowid= $trans['ProjectIOWId'];
                $dCumQty = floatval($trans['Qty']) + floatval($trans['PrevQty']);
                $dAmt =  $dCumQty * $trans['Rate'];
                $dQAmt =  $dCumQty * $trans['QualRate'];

                $select = $sql->select();
                $select->from('Proj_ProjectIOWPlan')
                    ->columns(array('ProjectIOWId'))
                    ->where(array("ProjectIOWId"=>$iowid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $plan = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($plan)) {
                    $update = $sql->update();
                    $update->table('Proj_ProjectIOWPlan');
                    $update->set(array('CurQty' => $trans['Qty'],'PrevQty' => $trans['PrevQty'],
                        'Rate' => $trans['Rate'], 'Amount' => $dAmt, 'Qty' => $dCumQty,
                        'QualRate' => $trans['QualRate'], 'QualAmount' => $dQAmt,
                        'RFCTransId' => $rfctransid));
                    $update->where(array('ProjectIOWId' => $iowid));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectIOWPlan');
                    $insert->Values(array('ProjectIOWId' => $iowid, 'CurQty' => $trans['Qty'],'PrevQty' => $trans['PrevQty'],
                        'Rate' => $trans['Rate'], 'Amount' => $dAmt, 'Qty' => $dCumQty,
                        'QualRate' => $trans['QualRate'], 'QualAmount' => $dQAmt,
                        'ProjectId' => $iProjectId, 'RFCTransId' => $rfctransid));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $select = $sql->select();
                $select->from('Proj_ProjectIOWPlan')
                    ->columns(array('ProjectIOWId'))
                    ->where(array("ProjectIOWId"=>$iowid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $plan = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($plan)) {

                }

                $select = $sql->select();
                $select->from('Proj_RFCIOWWbsTrans')
                    ->columns(array('IOWId'=> new Expression("'$iowid'"), 'ProjectId'=> new Expression("'$iProjectId'"), 'WBSId', 'CumQty'=>new Expression("Qty+PrevQty") ,'PrevQty','Qty'))
                    ->where(array("RFCTransId='$rfctransid'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcwbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach ($rfcwbstrans as $wtrans) {
                    $select = $sql->select();
                    $select->from('Proj_WBSTransPlan')
                        ->columns(array('ProjectIOWId'))
                        ->where(array("ProjectIOWId"=>$iowid,'WBSId'=>$wtrans['WBSId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $plan = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($plan)) {
                        $update = $sql->update();
                        $update->table('Proj_WBSTransPlan');
                        $update->set(array('Qty' => $wtrans['CumQty'], 'PrevQty' => $wtrans['PrevQty'],
                            'CurQty' => $wtrans['Qty'],
                            'RFCTransId' => $rfctransid));
                        $update->where(array('ProjectIOWId' => $iowid,'WBSId'=>$wtrans['WBSId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_WBSTransPlan');
                        $insert->Values(array('ProjectIOWId' => $iowid, 'ProjectId' => $iProjectId,
                            'WBSId' => $wtrans['WBSId'],
                            'Qty' => $wtrans['CumQty'], 'PrevQty' => $wtrans['PrevQty'],
                            'CurQty' => $wtrans['Qty'],
                            'RFCTransId' => $rfctransid));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                ProjectHelper::_updateProjectDetails($iProjectId, $iowid, $sProjectType, $dbAdapter);
            }
            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);
        } else if  ($rfctype == "IOWPlan-Qty") {

            if ($iRevRequired==1) {
                $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
                if ($iRevId != 0) ProjectHelper::_revisonCopy($iProjectId, $sProjectType, $iRevId, $dbAdapter);
            }

            $select = $sql->select();
            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->columns(array('RFCTransId','ProjectIOWId', 'Qty'))
                ->where(array("a.RFCRegisterId=$rfcid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iowid= $trans['ProjectIOWId'];
                $dQty = $trans['Qty'];

                $update = $sql->update();
                $update->table('Proj_ProjectIOWPlan');
                $update->set(array('ProjectIOWId' => $iowid, 'Qty' => new Expression("PrevQty+$dQty"),'CurQty' => $dQty,
                    'Amount' => new Expression("(PrevQty+$dQty)*Rate"),
                    'QualAmount' => new Expression("(PrevQty+$dQty)*QualRate"),
                    'RFCTransId' => $rfctransid));
                $update->where(array('ProjectIOWId' => $iowid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $select = $sql->select();
                $select->from('Proj_RFCIOWWbsTrans')
                    ->columns(array('WBSId','Qty'))
                    ->where(array("RFCTransId='$rfctransid'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcwbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach ($rfcwbstrans as $wtrans) {
                    $dwQty = $wtrans['Qty'];

                    $select = $sql->select();
                    $select->from('Proj_WBSTransPlan')
                        ->columns(array('ProjectIOWId'))
                        ->where(array("ProjectIOWId"=>$iowid,'WBSId'=>$wtrans['WBSId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $plan = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($plan)) {
                        $update = $sql->update();
                        $update->table('Proj_WBSTransPlan');
                        $update->set(array('Qty' => new Expression("PrevQty+$dwQty"),
                            'CurQty' => $dwQty,
                            'RFCTransId' => $rfctransid));
                        $update->where(array('ProjectIOWId' => $iowid,'WBSId'=>$wtrans['WBSId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_WBSTransPlan');
                        $insert->Values(array('ProjectIOWId' => $iowid, 'ProjectId' => $iProjectId,
                            'WBSId' => $wtrans['WBSId'],
                            'Qty' => new Expression("PrevQty+$dwQty"),
                            'CurQty' => $dwQty,
                            'RFCTransId' => $rfctransid));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                ProjectHelper::_updateProjectDetails($iProjectId, $iowid, $sProjectType, $dbAdapter);
            }
            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);
        } else if  ($rfctype == "IOWPlan-Edit"){
            $select = $sql->select();
            $select->from(array('a' => 'Proj_RFCIOWTrans'))
                ->columns(array('ProjectIOWId', 'Qty','PrevQty','RFCTransId'))
                ->where(array("a.RFCRegisterId=$rfcid"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach ($rfctrans as $trans) {
                $rfctransid = $trans['RFCTransId'];
                $iowid= $trans['ProjectIOWId'];
                $dCumQty = floatval($trans['Qty']) + floatval($trans['PrevQty']);
                $dAmt =  $dCumQty * $trans['Rate'];
                $dQAmt =  $dCumQty * $trans['QualRate'];

                $update = $sql->update();
                $update->table('Proj_ProjectIOWPlan');
                $update->set(array('ProjectIOWId' => $iowid, 'Qty' => $dCumQty,'PrevQty' => $trans['PrevQty'],'CurQty' => $trans['Qty'],
                    'Rate' => $trans['Rate'], 'Amount' => $dAmt,
                    'QualRate' => $trans['QualRate'], 'QualAmount' => $dQAmt,
                    'ProjectId' => $iProjectId, 'RFCTransId' => $rfctransid));
                $update->where(array('ProjectIOWId' => $iowid));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from('Proj_RFCIOWWbsTrans')
                    ->columns(array('IOWId'=> new Expression("'$iowid'"), 'ProjectId'=> new Expression("'$iProjectId'"), 'WBSId', 'CumQty'=>new Expression("Qty+PrevQty") ,'PrevQty','Qty'))
                    ->where(array("RFCTransId='$rfctransid'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcwbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach ($rfcwbstrans as $wtrans) {
                    $select = $sql->select();
                    $select->from('Proj_WBSTransPlan')
                        ->columns(array('ProjectIOWId'))
                        ->where(array("ProjectIOWId"=>$iowid,'WBSId'=>$wtrans['WBSId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $plan = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($plan)) {
                        $update = $sql->update();
                        $update->table('Proj_WBSTransPlan');
                        $update->set(array('Qty' => $wtrans['CumQty'], 'PrevQty' => $wtrans['PrevQty'],
                            'CurQty' => $wtrans['Qty'],
                            'RFCTransId' => $rfctransid));
                        $update->where(array('ProjectIOWId' => $iowid,'WBSId'=>$wtrans['WBSId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_WBSTransPlan');
                        $insert->Values(array('ProjectIOWId' => $iowid, 'ProjectId' => $iProjectId,
                            'WBSId' => $wtrans['WBSId'],
                            'Qty' => $wtrans['CumQty'], 'PrevQty' => $wtrans['PrevQty'],
                            'CurQty' => $wtrans['Qty'],
                            'RFCTransId' => $rfctransid));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                ProjectHelper::_updateProjectDetails($iProjectId, $iowid, $sProjectType, $dbAdapter);
            }
            ProjectHelper::_updateResourceRate($iProjectId,$sProjectType,$dbAdapter);
        }
    }

    function _updateWBSEdit($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
        }

        if ($rfctype == "WBS-Edit") {
            $select = $sql->select();
            $select->from('Proj_RFCWBSIOWTrans')
                ->columns(array('WBSId','ProjectIOWId','Qty'))
                ->where(array('RFCRegisterId'=>$rfcid));
            $statement = $sql->getSqlStringForSqlObject($select);
            $iowtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($iowtrans as $itrans) {
                $iWBSId = $itrans['WBSId'];
                $iIOWId = $itrans['ProjectIOWId'];

                $update = $sql->update();
                if ($sProjectType=="P") $update->table('Proj_WBSTransPlan');
                else $update->table('Proj_WBSTrans');

                $update->set(array('Qty' => $itrans['Qty']));
                $update->where(array('WBSId' => $iWBSId,'ProjectIOWId'=>$iIOWId,'ProjectId'=>$iProjectId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $dQty =0 ;
                $select = $sql->select();
                if ($sProjectType=="P") $select->from('Proj_WBSTransPlan');
                else $select->from('Proj_WBSTrans');
                $select->columns(array('Qty'=>new Expression("Sum(Qty)")))
                       ->where(array('ProjectIOWId'=>$iIOWId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $iowtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($iowtotal)) $dQty= $iowtotal['Qty'];

                $update = $sql->update();
                if ($sProjectType=="P") $update->table('Proj_ProjectIOW');
                else $update->table('Proj_ProjectIOW');

                $update->set(array('Qty' => $dQty,'Amount'=>new Expression("Rate*$dQty"),'QualAmount'=>new Expression("QualRate*$dQty")));
                $update->where(array('ProjectIOWId'=>$iIOWId,'ProjectId'=>$iProjectId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                ProjectHelper::_updateProjectDetails($iProjectId, $iIOWId, $sProjectType, $dbAdapter);
            }
        }
    }


    function _updateWBS($rfcid,$dbAdapter)
    {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_RFCRegister')
            ->columns(array('RFCType', 'ProjectId', 'ProjectType'))
            ->where(array("RFCRegisterId='$rfcid'"));

        $statement = $sql->getSqlStringForSqlObject($select);
        $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $rfctype = "";
        $iProjectId = 0;
        $sProjectType = "";
        if (!empty($rfcregister)) {
            $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
            $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
            $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
        }

        if ($rfctype == "WBS-Add") {

            $iRevId = ProjectHelper::_getRevisionName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
            if ($iRevId !=0) ProjectHelper::_revisonCopy($iProjectId,$sProjectType,$iRevId,$dbAdapter);

            $select = $sql->select();
            $select->from('Proj_RFCWBSTrans')
                ->columns(array('WBSTransId','WBSId','TempParentId','ParentId','WBSName'))
                ->where(array("RFCRegisterId='$rfcid'"));

            $statement = $sql->getSqlStringForSqlObject($select);
            $wbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $wbsArray = array();
            foreach ($wbstrans as $trans) {
                $iwbstransid = $trans['WBSTransId'];
                $iwbsId =$trans['WBSId'];
                $iTempParentId =$trans['TempParentId'];
                $iParentId =$trans['ParentId'];
                $sWBSName =$trans['WBSName'];

                if ($iwbsId==0)
                {
                    if ($iParentId !=0) $iWBSParentId = $iParentId;
                    else $iWBSParentId = $wbsArray[$iTempParentId];

                    $insert = $sql->insert();
                    $insert->into('Proj_WBSMaster');
                    $insert->Values(array('ProjectId' =>$iProjectId,'ParentId' => $iWBSParentId, 'WBSName' => $sWBSName));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $iNewWBSId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $wbsArray[$iwbstransid] = $iNewWBSId;

                } else {
                    $wbsArray[$iwbstransid] = $iwbsId;
                }

                $select = $sql->select();
                $select->from('Proj_RFCWBSIOWTrans')
                    ->columns(array('RFCTransId','ProjectIOWId','Qty'))
                    ->where(array("WBSTransId='$iwbstransid'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $iowtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach ($iowtrans as $itrans) {
                    $iIOWWBSId= $wbsArray[$iwbstransid];

                    $insert = $sql->insert();
                    if ($sProjectType=="P") $insert->into('Proj_WBSTransPlan');
                    else $insert->into('Proj_WBSTrans');

                    $insert->Values(array('ProjectId' =>$iProjectId,'WBSId' => $iIOWWBSId, 'ProjectIOWId' => $itrans['ProjectIOWId'],'Qty'=> $itrans['Qty']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
        }
        ProjectHelper::_updateWBSParent($iProjectId,$dbAdapter);
    }

    public function _updateWBSParent($iProjectId,$dbAdapter) {

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_WBSMaster')
            ->columns(array('WBSId'))
            ->where("ProjectId=$iProjectId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach ($wbslist as $trans) {
            $iWBSId = $trans['WBSId'];

            $statement = "exec Get_WBS_Hierarchy_Parent @Id= " .$iWBSId;
            $parent= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $sParentText ="";
            foreach ($parent as $ptrans) {
                if ($ptrans['WBSId'] != $iWBSId) {
                    $sParentText = $sParentText . $ptrans['WBSName'] . "->";
                }
            }
            $sParentText  = rtrim($sParentText , '->');

            $update = $sql->update();
            $update->table('Proj_WBSMaster');
            $update->set(array('ParentText' => $sParentText));
            $update->where(array('WBSId' => $iWBSId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _updateProjectDetails($iProjectId,$iProjIOWId, $sProjectType,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        if ($sProjectType == "P") {

            $delete = $sql->delete();
            $delete->from('Proj_ProjectDetailsPlan')
                ->where(array("ProjectIOWId" => $iProjIOWId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select->from(array('a' => 'Proj_ProjectRateAnalysis'))
                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOWPlan'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'IncludeFlag','RateType','MixType','Rate', 'Qty' => new Expression("(a.Qty/b.WorkingQty)*c.Qty"), 'Amount' => new Expression("((a.Qty/b.WorkingQty)*c.Qty)*a.Rate")))
                ->where(array("a.ProjectIOWId=$iProjIOWId"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectDetailsPlan');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'IncludeFlag','RateType','MixType','Rate', 'Qty', 'Amount'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        } else  {

            $delete = $sql->delete();
            $delete->from('Proj_ProjectDetails')
                ->where(array("ProjectIOWId" => $iProjIOWId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectRateAnalysis'))
                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'IncludeFlag', 'Rate','RateType','MixType',
                    'Qty' => new Expression("((a.Qty/b.WorkingQty)*c.Qty)*((100-c.ReadyMixRatio)/100)"), 'Amount' => new Expression("(((a.Qty/b.WorkingQty)*c.Qty)*a.Rate)*((100-c.ReadyMixRatio)/100)")))
                ->where(array("a.ProjectIOWId=$iProjIOWId and a.MixType<>'R'"));

            $select1 = $sql->select();
            $select1->from(array('a' => 'Proj_ProjectRateAnalysis'))
                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select1:: JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select1:: JOIN_INNER)
                ->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'IncludeFlag', 'Rate','RateType','MixType',
                    'Qty' => new Expression("((a.Qty/b.RWorkingQty)*c.Qty)*(c.ReadyMixRatio/100)"), 'Amount' => new Expression("(((a.Qty/b.RWorkingQty)*c.Qty)*a.Rate)*(c.ReadyMixRatio/100)")))
                ->where(array("a.ProjectIOWId=$iProjIOWId and a.MixType='R'"));

            $select->combine($select1, 'UNION ALL');

            $insert = $sql->insert();
            $insert->into('Proj_ProjectDetails');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'IncludeFlag', 'Rate','RateType','MixType', 'Qty', 'Amount'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _updateResourceRate($iProjectId,$sProjectType,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $delete = $sql->delete();
        if ($sProjectType == "P") $delete->from('Proj_ProjectResourcePlan');
        else $delete->from('Proj_ProjectResource');
        $delete->where(array("ProjectId" => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ProjectDetailsPlan');
        else $select->from('Proj_ProjectDetails');
        $select->columns(array('ProjectId', 'ResourceId', 'IncludeFlag', 'Rate','RateType', 'Qty'=> new Expression("sum(Qty)") , 'Amount' => new Expression("sum(Amount)")))
            ->where(array("ProjectId=$iProjectId"))
            ->group(new Expression('ProjectId, ResourceId, IncludeFlag, Rate,RateType'));

        $insert = $sql->insert();
        if ($sProjectType == "P") $insert->into('Proj_ProjectResourcePlan');
        else $insert->into('Proj_ProjectResource');
        $insert->columns(array('ProjectId', 'ResourceId', 'IncludeFlag', 'Rate','RateType', 'Qty', 'Amount'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $sql = new Sql($dbAdapter);
        $delete = $sql->delete();
        if ($sProjectType == "P") $delete->from('Proj_ProjectWBSResourcePlan');
        else $delete->from('Proj_ProjectWBSResource');
        $delete->where(array("ProjectId" => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        if ($sProjectType == "P") {
            $select->from(array('a' => 'Proj_ProjectDetailsPlan'))
                ->join(array('b' => 'Proj_ProjectIOWPlan'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_WBSTransPlan'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->columns(array('ProjectId','WBSId'=>new Expression('c.WBSId'), 'ResourceId','RateType','IncludeFlag', 'Rate',
                    'Qty' => new Expression("sum(((a.Qty/b.Qty)*c.Qty))"), 'Amount' => new Expression("sum((((a.Qty/b.Qty)*c.Qty)*a.Rate))")))
                ->where(array('a.ProjectId'=>$iProjectId))
                ->group(new Expression('a.ProjectId,c.WBSId,a.ResourceId, a.IncludeFlag, a.Rate,a.RateType'));
        } else {
            $select->from(array('a' => 'Proj_ProjectDetails'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_WBSTrans'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select:: JOIN_INNER)
                ->columns(array('ProjectId','WBSId'=>new Expression('c.WBSId'), 'ResourceId','RateType','IncludeFlag', 'Rate',
                    'Qty' => new Expression("sum(((a.Qty/b.Qty)*c.Qty))"), 'Amount' => new Expression("sum((((a.Qty/b.Qty)*c.Qty)*a.Rate))")))
                ->where(array('a.ProjectId'=>$iProjectId))
                ->group(new Expression('a.ProjectId,c.WBSId,a.ResourceId, a.IncludeFlag, a.Rate,a.RateType'));

        }

        $insert = $sql->insert();
        if ($sProjectType == "P") $insert->into('Proj_ProjectWBSResourcePlan');
        else $insert->into('Proj_ProjectWBSResource');
        $insert->columns(array('ProjectId','WBSId','ResourceId','RateType','IncludeFlag', 'Rate','Qty', 'Amount'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }

    function _revisonCopy($iProjectId,$sProjectType,$iRevId,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        //ProjectBOQ
        //ProjectIOW
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ProjectIOWPlan');
        else $select->from('Proj_ProjectIOW');
        $select->columns(array('ProjectId', 'ProjectIOWId', 'Qty', 'Rate', 'Amount', 'QualRate', 'QualAmount','RFCTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ProjectIOWTrans');
        $insert->columns(array('ProjectId', 'ProjectIOWId', 'Qty', 'Rate', 'Amount', 'QualRate', 'QualAmount','RFCTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //RateAnalysis
        if ($sProjectType == "B") {
            $select = $sql->select();
            $select->from('Proj_ProjectRateAnalysis');
            $select->columns(array('ProjectId', 'ProjectIOWId', 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'RFCTransId','TransType','SortId','RateType','Wastage','WastageQty','WastageAmount','Weightage', 'RevisionId' => new Expression("$iRevId")))
                ->where(array("ProjectId=$iProjectId"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectRateAnalysisTrans');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'IncludeFlag', 'ReferenceId', 'ResourceId', 'SubIOWId', 'Description', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'RFCTransId','TransType','SortId','RateType','Wastage','WastageQty','WastageAmount','Weightage','RevisionId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_ProjectIOWQualTrans')
                ->columns(array('ProjectId','ProjectIOWId','QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                    'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType','RevisionId' => new Expression("$iRevId")))
                ->where(array("ProjectId=$iProjectId"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectIOWQualRevTrans');
            $insert->columns(array('ProjectId','ProjectIOWId','QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt',
                'TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType','RevisionId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $select = $sql->select();
            $select->from('Proj_ProjectIOWMeasurement');
            $select->columns(array('ProjectId', 'ProjectIOWId', 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId','RevisionId' => new Expression("$iRevId")))
                ->where(array("ProjectId=$iProjectId"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectIOWMeasurementTrans');
            $insert->columns(array('ProjectId', 'ProjectIOWId', 'Measurement', 'CellName', 'SelectedColumns', 'RFCTransId','RevisionId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        //ProjectResource
        $delete = $sql->delete();
        $delete->from('Proj_ProjectResourceTrans')
            ->where(array("RevisionId" => $iRevId));
        $statement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ProjectResourcePlan');
        else $select->from('Proj_ProjectResource');
        $select->columns(array('ProjectId', 'ResourceId', 'IncludeFlag', 'NT','RateType','Rate', 'Qty', 'Amount', 'RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ProjectResourceTrans');
        $insert->columns(array('ProjectId', 'ResourceId', 'IncludeFlag', 'NT','RateType','Rate', 'Qty', 'Amount', 'RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //ActivityResourceDetails
        if ($sProjectType == "B") {
            $select = $sql->select();
            $select->from('Proj_ProjectResourceActivityTrans');
            $select->columns(array('ProjectId', 'MResourceId', 'ActivityType', 'ResourceId', 'Qty', 'Rate', 'Amount', 'RFCTransId', 'RevisionId' => new Expression("$iRevId")))
                ->where(array("ProjectId=$iProjectId"));

            $insert = $sql->insert();
            $insert->into('Proj_ProjectResourceActivityRevTrans');
            $insert->columns(array('ProjectId', 'MResourceId', 'ActivityType', 'ResourceId', 'Qty', 'Rate', 'Amount', 'RFCTransId', 'RevisionId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        //ProjectDetails
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ProjectDetailsPlan');
        else $select->from('Proj_ProjectDetails');
        $select->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'IncludeFlag', 'QualifierId', 'NT','Weightage','RateType','MixType','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ProjectDetailsTrans');
        $insert->columns(array('ProjectId', 'ProjectIOWId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'IncludeFlag', 'QualifierId', 'NT','Weightage','RateType','MixType','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //WBS
        //WBSTrans
        //Select ProjectId,WBSID,ProjectIOWId,SerialNo,Qty,Rate,Amount from
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_WBSTransPlan');
        else $select->from('Proj_WBSTrans');
        $select->columns(array('ProjectId','WBSID','ProjectIOWId','SerialNo','Qty','Rate','Amount','Measurement','CellName','SelectedColumns','RFCTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_WBSTransRevTrans');
        $insert->columns(array('ProjectId','WBSID','ProjectIOWId','SerialNo','Qty','Rate','Amount','Measurement','CellName','SelectedColumns','RFCTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Select  From
        //WBSResourceRate
        if ($sProjectType == "B") {
            $select = $sql->select();
            $select->from('Proj_WBSResourceRate');
            $select->columns(array('ProjectId','ResourceId','WBSId','LiftingCharges','FloorRiseRate','RateType', 'RevisionId' => new Expression("$iRevId")))
                ->where(array("ProjectId=$iProjectId"));

            $insert = $sql->insert();
            $insert->into('Proj_WBSResourceRateTrans');
            $insert->columns(array('ProjectId','ResourceId','WBSId','LiftingCharges','FloorRiseRate','RateType', 'RevisionId'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_ProjectWBSResourcePlan');
        else $select->from('Proj_ProjectWBSResource');
        $select->columns(array('ProjectId', 'ResourceId','WBSId', 'IncludeFlag', 'NT','RateType','Rate', 'Qty', 'Amount', 'RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_ProjectWBSResourceTrans');
        $insert->columns(array('ProjectId', 'ResourceId','WBSId','IncludeFlag', 'NT','RateType','Rate', 'Qty', 'Amount', 'RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //OtherCost
        //OHAbstract
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHAbstractPlan');
        else $select->from('Proj_OHAbstract');
        $select->columns(array('ProjectId','OHAbsId','OHId','Amount','RFCTransId', 'RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHAbstractTrans');
        $insert->columns(array('ProjectId','OHAbsId','OHId','Amount','RFCTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Select   from
        //Item
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHItemPlan');
        else $select->from('Proj_OHItem');
        $select->columns(array('ProjectId','OHAbsId','ProjectIOWId','Qty','Rate','Amount','RFCItemTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHItemTrans');
        $insert->columns(array('ProjectId','OHAbsId','ProjectIOWId','Qty','Rate','Amount','RFCItemTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Material
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHMaterialPlan');
        else $select->from('Proj_OHMaterial');
        $select->columns(array('ProjectId','OHAbsId','ResourceId','Qty','Rate','Amount','RFCMaterialTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHMaterialTrans');
        $insert->columns(array('ProjectId','OHAbsId','ResourceId','Qty','Rate','Amount','RFCMaterialTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Labour
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHLabourPlan');
        else $select->from('Proj_OHLabour');
        $select->columns(array('ProjectId','OHAbsId','ResourceId','Qty','Rate','Amount','RFCLabourTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHLabourTrans');
        $insert->columns(array('ProjectId','OHAbsId','ResourceId','Qty','Rate','Amount','RFCLabourTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Service
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHServicePlan');
        else $select->from('Proj_OHService');
        $select->columns(array('ProjectId','OHAbsId','ServiceId','Qty','Rate','Amount','RFCServiceTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHServiceTrans');
        $insert->columns(array('ProjectId','OHAbsId','ServiceId','Qty','Rate','Amount','RFCServiceTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Machinery
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHMachineryPlan');
        else $select->from('Proj_OHMachinery');
        $select->columns(array('ProjectId','OHAbsId','MachineryTransId','MResourceId','Nos','WUnitId','WorkingQty','TotalQty','Rate','Amount','RFCMachineryTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHMachineryTrans');
        $insert->columns(array('ProjectId','MachineryTransId','OHAbsId','MResourceId','Nos','WUnitId','WorkingQty','TotalQty','Rate','Amount','RFCMachineryTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Select  from
        //MachineryDetails
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHMachineryDetailsPlan');
        else $select->from('Proj_OHMachineryDetails');
        $select->columns(array('ProjectId','MachineryTransId','ProjectIOWId','Percentage','Amount','RFCMachineryDetailId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHMachineryDetailsTrans');
        $insert->columns(array('ProjectId','MachineryTransId','ProjectIOWId','Percentage','Amount','RFCMachineryDetailId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Admin-Expense
        //Select  from
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHAdminExpensePlan');
        else $select->from('Proj_OHAdminExpense');
        $select->columns(array('ProjectId','OHAbsId','ExpenseId','Amount','RFCExpenseTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHAdminExpenseTrans');
        $insert->columns(array('ProjectId','OHAbsId','ExpenseId','Amount','RFCExpenseTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Salary
        //Select  from
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHSalaryPlan');
        else $select->from('Proj_OHSalary');
        $select->columns(array('ProjectId','OHAbsId','PositionId','Nos','cMonths','Salary','Amount','RFCSalaryTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHSalaryTrans');
        $insert->columns(array('ProjectId','OHAbsId','PositionId','Nos','cMonths','Salary','Amount','RFCSalaryTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //Fuel
        //Select  from
        $select = $sql->select();
        if ($sProjectType == "P") $select->from('Proj_OHFuelPlan');
        else $select->from('Proj_OHFuel');
        $select->columns(array('ProjectId','OHAbsId','MResourceId','FResourceId','Qty','Rate','Amount','RFCFuelTransId','RevisionId' => new Expression("$iRevId")))
            ->where(array("ProjectId=$iProjectId"));

        $insert = $sql->insert();
        $insert->into('Proj_OHFuelTrans');
        $insert->columns(array('ProjectId','OHAbsId','MResourceId','FResourceId','Qty','Rate','Amount','RFCFuelTransId','RevisionId'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }


    function _assignTask($iProjectId,$dbAdapter) {
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('PC_AssignedChecklist');
        $select->columns(array('WBSId', 'ProjectIOWId', 'CheckListId', 'UserId'))
            ->where(array("ProjectId=$iProjectId"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $checklisttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_ScheduleDetails')
            ->columns(array('ProjectIOWId','WBSId','SDate','SQty'))
            ->where(array("ProjectId=$iProjectId"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $delete = $sql->delete();
        $delete->from('PC_UserTask')
            ->where(array("ProjectId" => $iProjectId,"Status"=>''));
        $statement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($checklisttrans as $trans) {
            $iIOWId = $trans['ProjectIOWId'];
            $iWBSId = $trans['WBSId'];
            $iCheckListId = $trans['CheckListId'];
            $iUserId = $trans['UserId'];

            $arr = array();
            $arr = array_filter($arrSchedule, function ($v) use ($iWBSId,$iIOWId) {
                return $v['WBSId'] == $iWBSId && $v['ProjectIOWId'] == $iIOWId;
            });

            foreach ($arr as $atrans) {
                $update = $sql->update();
                $update->table('PC_UserTask');
                $update->set(array('Qty' => $atrans['SQty']));
                $update->where(array('WBSId' => $iWBSId,'ProjectIOWId'=>$iIOWId,'CheckListId'=>$iCheckListId,'CDate' => date('Y-m-d', strtotime($atrans['SDate']))));
                $statement = $sql->getSqlStringForSqlObject($update);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                if ($result->getAffectedRows() <=0) {
                    $insert = $sql->insert();
                    $insert->into('PC_UserTask');
                    $insert->Values(array('CheckListId' => $iCheckListId, 'WBSId' => $iWBSId, 'ProjectIOWId' => $iIOWId, 'ProjectId' => $iProjectId,
                        'Qty' => $atrans['SQty'], 'UserId' => $iUserId, 'CDate' => date('Y-m-d', strtotime($atrans['SDate']))));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
        }
    }

    function _allProjectCostUpdate($dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','WORegisterId','KickoffId'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrProj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach ($arrProj as $trans) {
            $iProjectId = $trans['ProjectId'];
            $sType = "B";
            if ($trans['WORegisterId'] !=0) $sType = "C";
            ProjectHelper::_projectCostUpdate($iProjectId,$sType,$dbAdapter);
        }
    }

    function _projectCostUpdate($iProjectId,$sType,$dbAdapter) {
        //ProjectCost
        $sql = new Sql($dbAdapter);
        $update = $sql->update();
        $update->table('Proj_ProjectInfo');
        $update->set(array('BudgetCost'=>0,'SDate'=>new Expression("NULL"),'EDate'=>new Expression("NULL"),'Duration'=>0,'CompletionPer'=>0,'WorkDone'=>0,'CTC'=>0,'TCTC'=>0,'RDays'=>0,'DayProgress'=>0,
            'RProgress'=>0,'Billed'=>0,'Received'=>0,'Receivable'=>0));
        $update->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        if ($result->getAffectedRows() <=0) {
            $insert = $sql->insert();
            $insert->into('Proj_ProjectInfo');
            $insert->Values(array('ProjectId' => $iProjectId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

        $select = $sql->select();
        $select->from('Proj_ProjectIOW')
            ->columns(array('Amount'=>new Expression("sum(Amount)")))
            ->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $dBudgetCost = 0;
        if (!empty($arrCost)) $dBudgetCost=floatval($this->bsf->isNullCheck($arrCost['Amount'],'number'));
        $update = $sql->update();
        $update->table('Proj_ProjectMaster');
        $update->set(array(
            'ProjectCost' => $dBudgetCost,
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $update = $sql->update();
        $update->table('Proj_ProjectInfo');
        $update->set(array(
            'BudgetCost' => $dBudgetCost,
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        //WorkDoneCost
        $select = $sql->select();
        $select->from(array('a' => 'Proj_SchCompletion'))
            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
            ->columns(array('Amount'=>new Expression("sum(a.Qty*b.Rate)")))
            ->where(array('a.ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $dWorkDone = 0;
        if (!empty($arrCost)) $dWorkDone=floatval($this->bsf->isNullCheck($arrCost['Amount'],'number'));
        $update = $sql->update();
        $update->table('Proj_ProjectMaster');
        $update->set(array(
            'ProjectCompleted' => $dWorkDone,
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        $dCompPer = 0;
        if ($dBudgetCost !=0) $dCompPer = ($dWorkDone/$dBudgetCost) *100;
        $dCTC = $dBudgetCost -$dWorkDone;
        $dTCTC = $dBudgetCost;
        if ($dCTC <0) $dCTC=0;

        $update = $sql->update();
        $update->table('Proj_ProjectInfo');
        $update->set(array(
            'WorkDone' => $dWorkDone,'CompletionPer'=>$dCompPer,'CTC'=>$dCTC ,'TCTC'=>$dTCTC
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));

        $select = $sql->select();
        $select->from(array('a' => 'Proj_ScheduleDetails'))
            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
            ->columns(array('Amount'=>new Expression("sum(a.SQty*b.Rate)")))
            ->where("a.ProjectId='$iProjectId' and a.SDate<= '$asonDate'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $dCost = 0;

        if (!empty($arrCost)) $dCost=floatval($this->bsf->isNullCheck($arrCost['Amount'],'number'));
        $update = $sql->update();
        $update->table('Proj_ProjectMaster');
        $update->set(array(
            'ScheduleValue' => $dCost,
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $delete = $sql->delete();
        $delete->from('Proj_ProjectStatus')
            ->where(array("ProjectId" => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($delete);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        $select->from(array('a' => 'Proj_ScheduleDetails'))
            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
            ->columns(array('ProjectId','Month'=>new Expression("Month(a.SDate)"),'Year'=>new Expression("Year(a.SDate)"),'BudgetAmount'=>new Expression("sum(a.SQty*b.Rate)")))
            ->where("a.ProjectId='$iProjectId' and a.SDate<= '$asonDate'")
            ->group(new Expression('a.ProjectId,Month(a.SDate),Year(a.SDate)'));

        $insert = $sql->insert();
        $insert->into('Proj_ProjectStatus');
        $insert->columns(array('ProjectId','PMonth','PYear','BudgetAmount'));
        $insert->Values($select);
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        $select->from(array('a' => 'Proj_SchCompletion'))
            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
            ->columns(array('Month'=>new Expression("Month(a.sDate)"),'Year'=>new Expression("Year(a.sDate)"),'Amount'=>new Expression("sum(a.Qty*b.Rate)")))
            ->where("a.ProjectId='$iProjectId' and a.SDate<= '$asonDate'")
            ->group(new Expression('Month(a.sDate),Year(a.sDate)'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach ($arrComp as $ctrans) {
            $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
            $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
            $dAmt = floatval($this->bsf->isNullCheck($ctrans['Amount'],'number'));

            $update = $sql->update();
            $update->table('Proj_ProjectStatus');
            $update->set(array('WorkDone' => $dAmt));
            $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
            $statement = $sql->getSqlStringForSqlObject($update);
            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            if ($result->getAffectedRows() <=0) {
                $insert = $sql->insert();
                $insert->into('Proj_ProjectStatus');
                $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'WorkDone' => $dAmt));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        $select = $sql->select();
        $select->from(array('a' => 'Proj_SchCompletion'))
            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
            ->columns(array('Month'=>new Expression("Month(a.sDate)"),'Year'=>new Expression("Year(a.sDate)"),'Amount'=>new Expression("sum(a.Qty*b.Rate)")))
            ->where("a.ProjectId='$iProjectId' and a.sDate<= '$asonDate'")
            ->group(new Expression('Month(a.sDate),Year(a.sDate)'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach ($arrComp as $ctrans) {
            $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
            $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
            $dAmt = floatval($this->bsf->isNullCheck($ctrans['Amount'],'number'));

            $update = $sql->update();
            $update->table('Proj_ProjectStatus');
            $update->set(array('WorkDone' => $dAmt));
            $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
            $statement = $sql->getSqlStringForSqlObject($update);
            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            if ($result->getAffectedRows() <=0) {
                $insert = $sql->insert();
                $insert->into('Proj_ProjectStatus');
                $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'WorkDone' => $dAmt));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        $dBilled=0;
        $dReceipt=0;
        $dReceivable=0;
        if ($sType=="B") {
            $select = $sql->select();
            $select->from(array('a' => 'Crm_ProgressBillTrans'))
                ->join(array('b' => 'Crm_ProgressBill'), 'a.ProgressBillId=b.ProgressBillId', array(), $select::JOIN_INNER)
                ->columns(array('Month'=>new Expression("Month(a.BillDate)"),'Year'=>new Expression("Year(a.BillDate)"),'Amount'=>new Expression("sum(a.Amount)")))
                ->where("b.ProjectId='$iProjectId'")
                ->group(new Expression('Month(a.BillDate),Year(a.BillDate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($arrComp as $ctrans) {
                $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
                $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
                $dAmt = floatval($this->bsf->isNullCheck($ctrans['Amount'],'number'));
                $dBilled = $dBilled + $dAmt;

                $update = $sql->update();
                $update->table('Proj_ProjectStatus');
                $update->set(array('BillAmount' => $dAmt));
                $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
                $statement = $sql->getSqlStringForSqlObject($update);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                if ($result->getAffectedRows() <=0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectStatus');
                    $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'BillAmount' => $dAmt));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }

            $select = $sql->select();
            $select->from(array('a' => 'Crm_ReceiptRegister'))
                ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array(), $select::JOIN_INNER)
                ->columns(array('Month'=>new Expression("Month(a.ReceiptDate)"),'Year'=>new Expression("Year(a.ReceiptDate)"),'Amount'=>new Expression("sum(a.Amount)")))
                ->where("b.ProjectId='$iProjectId'")
                ->group(new Expression('Month(a.ReceiptDate),Year(a.ReceiptDate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($arrComp as $ctrans) {
                $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
                $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
                $dAmt = floatval($this->bsf->isNullCheck($ctrans['Amount'],'number'));
                $dReceipt = $dReceipt + $dAmt;

                $update = $sql->update();
                $update->table('Proj_ProjectStatus');
                $update->set(array('ReceiptAmount' => $dAmt));
                $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
                $statement = $sql->getSqlStringForSqlObject($update);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                if ($result->getAffectedRows() <=0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectStatus');
                    $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'ReceiptAmount' => $dAmt));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
            $dReceivable = $dBilled+$dReceipt;

        } else {
            $select = $sql->select();
            $select->from(array('a' => 'CB_BillMaster'))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                ->columns(array('Month'=>new Expression("Month(a.SubmittedDate)"),'Year'=>new Expression("Year(a.SubmittedDate)"),'Amount'=>new Expression("sum(a.SubmitAmount)")))
                ->where("b.ProjectId='$iProjectId'")
                ->group(new Expression('Month(a.SubmittedDate),Year(a.SubmittedDate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($arrComp as $ctrans) {
                $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
                $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
                $dAmt = floatval($this->bsf->isNullCheck($ctrans['Amount'],'number'));
                $dBilled = $dBilled + $dAmt;

                $update = $sql->update();
                $update->table('Proj_ProjectStatus');
                $update->set(array('BillAmount' => $dAmt));
                $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
                $statement = $sql->getSqlStringForSqlObject($update);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                if ($result->getAffectedRows() <=0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectStatus');
                    $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'BillAmount' => $dAmt));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }

            $select = $sql->select();
            $select->from(array('a' => 'CB_ReceiptRegister'))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                ->columns(array('Month'=>new Expression("Month(a.ReceiptDate)"),'Year'=>new Expression("Year(a.ReceiptDate)"),'Amount'=>new Expression("sum(a.Amount)")))
                ->where("b.ProjectId='$iProjectId'")
                ->group(new Expression('Month(a.ReceiptDate),Year(a.ReceiptDate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arrComp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($arrComp as $ctrans) {
                $iMonth = intval($this->bsf->isNullCheck($ctrans['Month'],'number'));
                $iYear = intval($this->bsf->isNullCheck($ctrans['Year'],'number'));
                $dAmt = $ctrans['Amount'];
                $dReceipt = $dReceipt + $dAmt;

                $update = $sql->update();
                $update->table('Proj_ProjectStatus');
                $update->set(array('ReceiptAmount' => $dAmt));
                $update->where(array('ProjectId' => $iProjectId,'PMonth'=>$iMonth,'PYear'=>$iYear));
                $statement = $sql->getSqlStringForSqlObject($update);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                if ($result->getAffectedRows() <=0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectStatus');
                    $insert->Values(array('ProjectId' => $iProjectId, 'PMonth' => $iMonth, 'PYear' => $iYear, 'ReceiptAmount' => $dAmt));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
            $dReceivable = $dBilled+$dReceipt;
        }

        $update = $sql->update();
        $update->table('Proj_ProjectInfo');
        $update->set(array(
            'Billed' => $dBilled,'Received'=>$dReceipt,'Receivable'=>$dReceivable
        ));
        $update->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $select = $sql->select();
        $select->from('Proj_Schedule')
            ->columns(array('EndDate'=>new Expression("Max(EndDate)")))
            ->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrEDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Proj_Schedule')
            ->columns(array('StartDate'=>new Expression("Min(StartDate)")))
            ->where(array('ProjectId'=>$iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrSDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if (!empty($arrEDate) && !empty($arrSDate)) {
            if (!is_null($arrEDate['EndDate']) && !is_null($arrSDate['StartDate'])) {

                $currentDate = strtotime(date('Y-m-d'));
                $sDate =  strtotime($arrSDate['StartDate']);
                $eDate = strtotime($arrEDate['EndDate']);

                $iDuration = round(abs($eDate-$sDate)/86400);
                $iRDays =  round(abs($eDate-$currentDate)/86400);

                $select = $sql->select();
                $select->from('Proj_WeekHoliday')
                    ->columns(array('WeekDay'))
                    ->where(array("ProjectId='$iProjectId'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $weekHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $arrWeek = array();
                $i=0;
                foreach($weekHoliday as $wrow) {
                    $arrWeek[$i] = $wrow['WeekDay'];
                    $i = $i+1;
                }

                $select = $sql->select();
                $select->from('Proj_Holiday')
                    ->columns(array('HDate'))
                    ->where(array("ProjectId='$iProjectId'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $tHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $arrHoliday = array();
                $i=0;
                foreach($tHoliday as $wrow) {
                    $arrHoliday[$i] = date( 'Y-m-d',strtotime($wrow['HDate']));
                    $i = $i+1;
                }
                if (!empty($tHoliday) || !empty($weekHoliday)) {
                    $dStartDate = $sDate;
                    $dEndDate = $eDate;
                    $iHDays = 0;
                    for ( $i = $dStartDate; $i <= $dEndDate; $i = $i + 86400 ) {
                        $thisDate = date( 'Y-m-d', $i );
                        $bHoliday = ProjectHelper::_checkHoliDay($arrHoliday, $arrWeek, $thisDate);
                        if ($bHoliday == true) $iHDays= $iHDays+1;
                    }
                    $iDuration = $iDuration - $iHDays;

                    $dStartDate = $currentDate;
                    $dEndDate = $eDate;
                    $iHDays = 0;
                    for ( $i = $dStartDate; $i <= $dEndDate; $i = $i + 86400 ) {
                        $thisDate = date( 'Y-m-d', $i );
                        $bHoliday = ProjectHelper::_checkHoliDay($arrHoliday, $arrWeek, $thisDate);
                        if ($bHoliday == true) $iHDays= $iHDays+1;
                    }
                    $iRDays = $iRDays - $iHDays;
                }
                if ($iRDays <0) $iRDays=0;
                $dProgress = 0;
                $dRProgress = 0;
                if ($iDuration !=0) $dProgress  = $dBudgetCost/$iDuration;
                if ($iRDays !=0) $dRProgress = $dCTC/$iRDays;

                $update = $sql->update();
                $update->table('Proj_ProjectInfo');
                $update->set(array(
                    'SDate' => date('Y-m-d', $sDate),'EDate' => date('Y-m-d', $eDate),'Duration'=>$iDuration,'RDays'=>$iRDays,'DayProgress'=>$dProgress,'RProgress'=>$dRProgress
                ));
                $update->where(array('ProjectId'=>$iProjectId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }

        $update = $sql->update();
        $update->table('Proj_ProjectStatus');
        $update->set(array('PDate' => new Expression("convert(datetime,'01/'+ ltrim(str(PMonth)) + '/' + ltrim(str(PYear)),103)")))
            ->where("PMonth <>0 and PYear <>0 and ProjectId='$iProjectId'");
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
}
?>