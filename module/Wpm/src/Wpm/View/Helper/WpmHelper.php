<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 16-08-2016
 * Time: 2:48 PM
 */

namespace Wpm\View\Helper;
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

class WpmHelper extends AbstractHelper implements ServiceLocatorAwareInterface
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

    /* Approve WPM Labours */
    public function Approve_Labours($argRegId,$dbAdapter) {
		
        $sql = new Sql($dbAdapter);
		$select = $sql->select();
        $select->from('WPM_LabourRegister')
            ->where(array('LabourRegisterId' => $argRegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $labResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$costCentreId = $labResult['CostCentreId'];

        $select = $sql->select();
        $select->from('WPM_LabourTrans')
            ->where(array('LabourRegisterId' => $argRegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $transResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('WPM_LabourCodeSetup')
            ->columns(array('GenType'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $wcode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $bWAutoCode=false;
        if (!empty($wcode)) {if ($wcode['GenType'] ==1) $bWAutoCode=true;}


        foreach($transResult as $transRes) {
            $iLabourId = $transRes['LabourId'];
            if ($iLabourId ==0) {
                $labcode = $transRes['Code'];
                if ($bWAutoCode ==true) $labcode = WpmHelper::_GetLabourCode($dbAdapter);

                $insert = $sql->insert();
                $insert->into('WPM_LabourMaster');
                $insert->Values(array('LabourTransId' => $transRes['LabourTransId']
                , 'Code' => $labcode
                , 'LabourName' => $transRes['LabourName']
                , 'CostCentreId' => $costCentreId
                , 'LabourGroupId' => $transRes['LabourGroupId']
                , 'VendorId' => $transRes['VendorId']
                , 'LabourTypeId' => $transRes['LabourTypeId']
                , 'Code' => $transRes['Code']
                , 'Address' => $transRes['Address']
                , 'CityId' => $transRes['CityId']
                , 'PinCode' => $transRes['PinCode']
                , 'Mobile' => $transRes['Mobile']
                , 'Email' => $transRes['Email']
                , 'PFNo' => $transRes['PFNo']
                , 'LabourProfile' => $transRes['LabourProfile']
                , 'AdharNo' => $transRes['AdharNo']
                , 'ESINo' => $transRes['ESINo']));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            } else {
                $update = $sql->update();
                $update->table('WPM_LabourMaster');
                $update->set(array('LabourTransId' => $transRes['LabourTransId']
                , 'LabourName' => $transRes['LabourName']
                , 'CostCentreId' => $costCentreId
                , 'LabourGroupId' => $transRes['LabourGroupId']
                , 'VendorId' => $transRes['VendorId']
                , 'LabourTypeId' => $transRes['LabourTypeId']
                , 'Code' => $transRes['Code']
                , 'Address' => $transRes['Address']
                , 'CityId' => $transRes['CityId']
                , 'PinCode' => $transRes['PinCode']
                , 'Mobile' => $transRes['Mobile']
                , 'Email' => $transRes['Email']
                , 'PFNo' => $transRes['PFNo']
                , 'LabourProfile' => $transRes['LabourProfile']
                , 'AdharNo' => $transRes['AdharNo']
                , 'ESINo' => $transRes['ESINo']));
                $update->where(array('LabourId' => $iLabourId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $delete = $sql->delete();
            $delete->from('WPM_LabourMasterDocumentTrans')
                  ->where(array('LabourId' => $iLabourId));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from('WPM_LabourDocumentTrans')
                ->columns(array('LabourId'=>new Expression("'$iLabourId'"),'DocumentType', 'DocumentName', 'DocumentURL'))
                ->where(array('LabourTransId' => $transRes['LabourTransId']));

            $insert = $sql->insert();
            $insert->into('WPM_LabourMasterDocumentTrans');
            $insert->columns(array('LabourId','DocumentType', 'DocumentName', 'DocumentURL'));
            $insert->Values($select);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }

    function _GetLabourCode($dbAdapter)
    {
        $labCode = "";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();

        $select->from('WPM_LabourCodeSetup')
            ->columns(array('GenType', 'Prefix', 'Suffix', 'Width', 'Separator', 'MaxNo'));
        $statement = $sql->getSqlStringForSqlObject($select);

        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($code['GenType'] == 1) {
            $sPrefix = $code['Prefix'];
            $sSuffix = $code['Suffix'];
            $iWidth = $code['Width'];
            $sSperator = $code['Separator'];

            $iVNo = $code['MaxNo'] + 1;
            WpmHelper::_UpdateLabourCodeMaxNo($iVNo, $dbAdapter);

            $iLen = $iWidth - strlen($iVNo);
            $sPre = "";
            for ($i = 1; $i < $iLen; $i++) {
                $sPre = $sPre . "0";
            }
            $labCode = $sPrefix . $sSperator. $sPre . $iVNo . $sSuffix;
        }
        return $labCode;
    }
    function  _UpdateLabourCodeMaxNo($maxno, $dbAdapter)
    {
        $sql = new Sql($dbAdapter);

        $update = $sql->update();
        $update->table('WPM_LabourCodeSetup');
        $update->set(array(
            'MaxNo' => $maxno,
        ));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
}
?>