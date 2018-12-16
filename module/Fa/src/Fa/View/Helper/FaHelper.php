<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 16-08-2016
 * Time: 2:48 PM
 */

namespace Fa\View\Helper;
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

class FaHelper extends AbstractHelper implements ServiceLocatorAwareInterface
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

    public function GetsuperiorAccList($curAccId,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('FA_AccountMaster')
            ->columns(array('ParentAccountId'))
            ->where(array('AccountId' => $curAccId));
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($result)>0) {
            foreach($result as $result1) {
                /*if(!in_array($result1['ParentAccountId'],$this->AccountList)) {
                    $this->AccountList[] = $result1['ParentAccountId'];
                }*/
                if($result1['ParentAccountId']!="" && $result1['ParentAccountId']!=0){
                    $this->strAccListId=$this->strAccListId. $result1['ParentAccountId'] .",";
                }
                FaHelper::GetsuperiorAccList($result1["ParentAccountId"], $dbAdapter);
            }
        }
    }

    public function GetLowLevelAccList($curAccId , $companyId,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('FA_AccountList')
            ->columns(array('AccountId'))
            ->where("ParentAccountId=$curAccId AND CompanyId=$companyId");
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($result)>0) {
            foreach($result as $result1) {
                /*if(!in_array($result1['ParentAccountId'],$this->AccountList)) {
                    $this->AccountList[] = $result1['ParentAccountId'];
                }*/
                if($result1['AccountId']!="" && $result1['AccountId']!=0){
                    $this->strAccListId=$this->strAccListId. $result1['AccountId'] .",";
                }
                FaHelper::GetLowLevelAccList($result1["AccountId"], $companyId, $dbAdapter);
            }
        }
    }

    public function GetChildsuperiorAccList($curAccId,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('FA_AccountMaster')
            ->columns(array('AccountId'))
            ->where(array('ParentAccountId' => $curAccId));
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($result)>0) {
            foreach($result as $result1) {
                /*if(!in_array($result1['ParentAccountId'],$this->AccountList)) {
                    $this->AccountList[] = $result1['ParentAccountId'];
                }*/
                if($result1['AccountId']!="" && $result1['AccountId']!=0){
                    $this->strAccListId=$this->strAccListId. $result1['AccountId'] .",";
                }
                FaHelper::GetChildsuperiorAccList($result1["AccountId"], $dbAdapter);
            }
        }
    }



}
?>