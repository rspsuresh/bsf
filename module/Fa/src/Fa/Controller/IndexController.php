<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Fa\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use Zend\Session\Container;
use Fa\View\Helper\FaHelper;
use Application\View\Helper\Qualifier;

class IndexController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction()	{
        if(!$this->auth->hasIdentity()) {
            $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
        }

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        return $this->_view;
    }

    public function accountdirectoryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();

            $select = $sql->select();
            $select->from(array("AM" => "FA_AccountMaster"))
                ->join(array("AT" => "FA_AccountType"), "AM.TypeId=AT.TypeId", array(), $select::JOIN_LEFT)
                ->columns(array("AccountId", "AccountName", "TypeName" => new Expression("AT.TypeName"), "ParentAccountId", "TypeId", "AccountType", "IsFixed", "LevelNo", "LastLevel"
                , "IFRSBSId", "IFRSPLId", "IFRSCFId", "SectionId", "Move" => new Expression("''"), "expanded" => new Expression("1-1")));
            $select->where("AM.AccountType NOT IN ('BA','CA') ");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $result=$cashbankdetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $response->setContent(json_encode($result));
            return $response;
        }else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("AM" => "FA_AccountMaster"))
                ->join(array("AT" => "FA_AccountType"), "AM.TypeId=AT.TypeId", array(), $select::JOIN_LEFT)
                ->columns(array("AccountId", "AccountName", "TypeName" => new Expression("AT.TypeName"), "ParentAccountId", "TypeId", "AccountType", "IsFixed", "LevelNo", "LastLevel"
                , "IFRSBSId", "IFRSPLId", "IFRSCFId", "SectionId", "Move" => new Expression("''"), "expanded" => new Expression("1-1")));
            $select->where("AM.AccountType NOT IN ('BA','CA') ");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accDirectoryList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->accDirectoryList = $accDirectoryList;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function cashbankdetailentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $cashbankId = $this->bsf->isNullCheck($this->params()->fromRoute('cashbankId'),'number');
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type=='getLoadDetails'){

                $cashbankId = $this->bsf->isNullCheck($postData['CashBankId'], 'number');

                $select = $sql->select();
                $select->from(array("AM" => "FA_AccountMaster"))
                    ->columns(array("data" => new Expression("AM.AccountId"), "value" => new Expression("AM.AccountName")))
                    ->where("AM.AccountType IN ('BA') and AM.LastLevel='N' ");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accBankMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->accBankMasterList = $accBankMasterList;

                $select = $sql->select();
                $select->from(array("AM" => "FA_AccountMaster"))
                    ->columns(array("data" => new Expression("AM.AccountId"), "value" => new Expression("AM.AccountName")))
                    ->where("AM.AccountType IN ('CA') and AM.LastLevel='N' ");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accCashMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->accCashMasterList = $accCashMasterList;

                $select = $sql->select();
                $select->from(array("a" => "WF_CompanyMaster"))
                    ->columns(array("data" => new Expression("a.CompanyId"), "value" => new Expression("a.CompanyName")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $companyMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->companyMasterList = $companyMasterList;
                if ($cashbankId != 0) {
                    $select = $sql->select();
                    $select->from(array("a" => "FA_CashBankDet"))
                        ->join(array("b" => "WF_CompanyMaster"), "a.CompanyId=b.CompanyId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "FA_AccountMaster"), "a.AccountId=c.AccountId", array(), $select::JOIN_INNER)
                        ->columns(array('CashBankId', 'AccountId', 'CashBankName', 'CashOrBank', 'AccountType', 'AccountNo'
                        , 'Branch', 'IFSCCode', 'Address1', 'ContactPerson', 'Mobile', 'CALimit', 'ODLimit', 'BGLimit', 'LCLimit'
                        , 'CompanyId', 'CompanyName' => new Expression("b.CompanyName"), 'AccountName' => new Expression("c.AccountName")))
                        ->where("a.CashBankId = $cashbankId");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $cashBankdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->cashBankdet = $cashBankdet;
                }

            }else if($type=='addEditDetails'){

                $postData = $request->getPost();
                try {

                    $connection->beginTransaction();
                    $mode="A";
                    $cashBankIdPost = $this->bsf->isNullCheck($postData['cashBankId'], 'number');

                    $type = $this->bsf->isNullCheck($postData['bankBookType'], 'string');
                    $cashBankName = '';
                    if ($type == 'B') {//Bank
                        $accType = 'BA';//
                        $cashBankName = $this->bsf->isNullCheck($postData['bankName'], 'string');

                        $accId = $this->bsf->isNullCheck($postData['bankunderGroupId'], 'number');
                        $accGroupName = $this->bsf->isNullCheck($postData['bankunderGroup'], 'string');
                        if ($cashBankIdPost == 0) {
                            $select = $sql->select();
                            $select->from(array("a" => "FA_AccountMaster"))
                                ->columns(array("AccountId", "AccountName", "LevelNo", "AccountType"))
                                ->where("AccountType IN ('BA') AND LastLevel='N'");
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $loadMaxlevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $iLevelNo = $loadMaxlevelList['LevelNo'];
                            if ($accId == 0) {
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $accGroupName
                                , 'ParentAccountId' => 20
                                , 'LastLevel' => 'N'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 1));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $ParentaccId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iLevelNo = $iLevelNo + 1;
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $cashBankName
                                , 'ParentAccountId' => $ParentaccId
                                , 'LastLevel' => 'Y'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 0));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $accId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            } else {
                                $iLevelNo = $iLevelNo + 1;
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $cashBankName
                                , 'ParentAccountId' => $accId
                                , 'LastLevel' => 'Y'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 0));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $accId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                        }
                    } else if ($type == 'C') {//Cash
                        $accType = 'CA';
                        $cashBankName = $this->bsf->isNullCheck($postData['cashName'], 'string');
                        $accId = $this->bsf->isNullCheck($postData['cashunderGroupId'], 'number');
                        $accGroupName = $this->bsf->isNullCheck($postData['cashunderGroup'], 'string');
                        if ($cashBankIdPost == 0) {
                            $select = $sql->select();
                            $select->from(array("a" => "FA_AccountMaster"))
                                ->columns(array("AccountId", "AccountName", "LevelNo", "AccountType"))
                                ->where("AccountType IN ('CA') AND LastLevel='N'");
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $loadMaxlevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $iLevelNo = $loadMaxlevelList['LevelNo'];

                            if ($accId == 0) {
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $accGroupName
                                , 'ParentAccountId' => 21
                                , 'LastLevel' => 'N'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 1));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $ParentaccId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iLevelNo = $iLevelNo + 1;
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $cashBankName
                                , 'ParentAccountId' => $ParentaccId
                                , 'LastLevel' => 'Y'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 0));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $accId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            } else {
                                $iLevelNo = $iLevelNo + 1;
                                $insert = $sql->insert();
                                $insert->into('FA_AccountMaster');
                                $insert->Values(array('AccountName' => $cashBankName
                                , 'ParentAccountId' => $accId
                                , 'LastLevel' => 'Y'
                                , 'LevelNo' => $iLevelNo // 4
                                , 'AccountType' => $accType
                                , 'IsFixed' => 0
                                , 'TypeId' => 0));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $accId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }
                        }
                    }

                    if ($cashBankIdPost != 0) {
                        $mode="E";
                        $update = $sql->update();
                        $update->table('FA_CashBankDet')
                            ->set(array('AccountId' => $accId
                            , 'CashBankName' => $cashBankName
                            , 'CashOrBank' => $type
                            , 'AccountType' => $accType
                            , 'AccountNo' => $this->bsf->isNullCheck($postData['bankAccNo'], 'string')
                            , 'Branch' => $this->bsf->isNullCheck($postData['bankBranchName'], 'string')
                            , 'IFSCCode' => $this->bsf->isNullCheck($postData['bankIFSCCode'], 'string')
                            , 'Address1' => $this->bsf->isNullCheck($postData['bankAddr'], 'string')
//                  , 'Address2' => $this->bsf->isNullCheck($postData['Address2'],'string')
//                  , 'City' => $this->bsf->isNullCheck($postData['City'],'string')
//                   , 'State' => $this->bsf->isNullCheck($postData['State'],'string')
//                   , 'Country' => $this->bsf->isNullCheck($postData['Country'],'string')
                            , 'ContactPerson' => $this->bsf->isNullCheck($postData['bankContactPerson'], 'string')
                            , 'Mobile' => $this->bsf->isNullCheck($postData['bankContactNo'], 'string')
//                   , 'Phone' => $this->bsf->isNullCheck($postData['Phone'],'string')
//                    , 'Fax' => $this->bsf->isNullCheck($postData['Fax'],'string')
                            , 'CALimit' => $this->bsf->isNullCheck($postData['bankCALimit'], 'number')
//                     , 'ODLimit' => $this->bsf->isNullCheck($postData['ODLimit'],'number')
                            , 'BGLimit' => $this->bsf->isNullCheck($postData['bankBGLimit'], 'number')
                            , 'LCLimit' => $this->bsf->isNullCheck($postData['bankLCLimit'], 'number')
//                  , 'LCDuration' => $this->bsf->isNullCheck($postData['LCDuration'],'number')
//                  , 'Validity' => $this->bsf->isNullCheck($postData['Validity'],'number')
                            , 'CompanyId' => $this->bsf->isNullCheck($postData['bankCompanyNameId'], 'number')
//                  , 'ReportName' => $this->bsf->isNullCheck($postData['ReportName'],'string')
                            ))
                            ->where(array('CashBankId' => $cashBankIdPost));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_CashBankDet');
                        $insert->Values(array('AccountId' => $accId
                        , 'CashBankName' => $cashBankName
                        , 'CashOrBank' => $type
                        , 'AccountType' => $accType
                        , 'AccountNo' => $this->bsf->isNullCheck($postData['bankAccNo'], 'string')
                        , 'Branch' => $this->bsf->isNullCheck($postData['bankBranchName'], 'string')
                        , 'IFSCCode' => $this->bsf->isNullCheck($postData['bankIFSCCode'], 'string')
                        , 'Address1' => $this->bsf->isNullCheck($postData['bankAddr'], 'string')
//                , 'Address2' => $this->bsf->isNullCheck($postData['Address2'],'string')
//                , 'City' => $this->bsf->isNullCheck($postData['City'],'string')
//                , 'State' => $this->bsf->isNullCheck($postData['State'],'string')
//                , 'Country' => $this->bsf->isNullCheck($postData['Country'],'string')
                        , 'ContactPerson' => $this->bsf->isNullCheck($postData['bankContactPerson'], 'string')
                        , 'Mobile' => $this->bsf->isNullCheck($postData['bankContactNo'], 'string')
//                , 'Phone' => $this->bsf->isNullCheck($postData['Phone'],'string')
//                , 'Fax' => $this->bsf->isNullCheck($postData['Fax'],'string')
                        , 'CALimit' => $this->bsf->isNullCheck($postData['bankCALimit'], 'number')
//                , 'ODLimit' => $this->bsf->isNullCheck($postData['ODLimit'],'number')
                        , 'BGLimit' => $this->bsf->isNullCheck($postData['bankBGLimit'], 'number')
                        , 'LCLimit' => $this->bsf->isNullCheck($postData['bankLCLimit'], 'number')
//                , 'LCDuration' => $this->bsf->isNullCheck($postData['LCDuration'],'number')
//                , 'Validity' => $this->bsf->isNullCheck($postData['Validity'],'number')
                        , 'CompanyId' => $this->bsf->isNullCheck($postData['bankCompanyNameId'], 'number')
//                , 'ReportName' => $this->bsf->isNullCheck($postData['ReportName'],'string')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $cashBankIdPost = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $compId=$this->bsf->isNullCheck($postData['bankCompanyNameId'], 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "FA_FiscalYearTrans"))
                            ->columns(array("FYearId"=> new Expression ("distinct FYearId")))
                            ->where("CompanyId =$compId");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $FyearList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($FyearList as &$FyearLists) {
                            $iFYearId = $FyearLists['FYearId'];

                            $select = $sql->select();
                            $select->from(array("a" => "FA_Account"))
                                ->columns(array("AccountId"))
                                ->where("AccountId=$accId AND CompanyId=$compId AND FYearId=$iFYearId");
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $foundAcctList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $iFoundAccId = $foundAcctList['AccountId'];

                            if($iFoundAccId!=0){

                            } else {
                                $insert = $sql->insert();
                                $insert->into('FA_Account');
                                $insert->Values(array('AccountId' => $accId
                                , 'CompanyId' => $compId
                                , 'FYearId' => $iFYearId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    $connection->commit();

                    if ($type == 'B') {//Bank
                        if ($mode == "E") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Bank-Edit', 'E', 'FA-Bank Details', $cashBankIdPost, 0, 0, 'FA', '', $userId, 0, 0);
                        } else {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Bank-Add', 'N', 'FA-Bank Details', $cashBankIdPost, 0, 0, 'FA', '', $userId, 0, 0);
                        }
                    } else if ($type == 'C') { //cash
                        if ($mode == "E") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Cash-Edit', 'E', 'FA-Cash Details', $cashBankIdPost, 0, 0, 'FA', '', $userId, 0, 0);
                        } else {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Cash-Add', 'N', 'FA-Cash Details', $cashBankIdPost, 0, 0, 'FA', '', $userId, 0, 0);
                        }
                    }
//                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'cashbankregister'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->setTerminal(true);
            return $this->_view;
        } else {
            if ($request->isPost()) {

            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function cashbankregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->join(array("b" => "WF_CompanyMaster"), "a.CompanyId=b.CompanyId", array(), $select::JOIN_LEFT)
                ->columns(array("CashBankId","AccountId", "CashBankName", "AccountType" => new Expression("case when a.AccountType='BA' then 'Bank'  else 'Cash' end")
                , "CompanyId", "CompanyName" => new Expression("b.CompanyName")));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $result=$cashbankdetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $response->setContent(json_encode($result));
            return $response;
        }else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->join(array("b" => "WF_CompanyMaster"), "a.CompanyId=b.CompanyId", array(), $select::JOIN_LEFT)
                ->columns(array("CashBankId","AccountId", "CashBankName", "AccountType" => new Expression("case when a.AccountType='BA' then 'Bank'  else 'Cash' end")
                , "CompanyId", "CompanyName" => new Expression("b.CompanyName")));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $cashbankdetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->cashbankdetList = $cashbankdetList;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function fiscalyearentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Fiscal Year");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $fiscalYearId= $this->bsf->isNullCheck($this->params()->fromRoute('FYearId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type == 'getLoadDetails'){
                $fiscalYearId= $this->bsf->isNullCheck($postData['FYearId'], 'number');

                $select = $sql->select();
                if ($fiscalYearId != 0) {
                    $selectFill = $sql->select();
                    $selectFill->from(array("a" => "FA_FiscalyearTrans"))
                        ->join(array("b" => "WF_CompanyMaster"), "a.CompanyId=b.CompanyId", array(), $selectFill::JOIN_INNER)
                        ->columns(array("CompanyId", "CompanyName" => new Expression("b.CompanyName"), "Sel" => new Expression("1"), "Type" => new Expression("a.PostingPeriod")));
                    $selectFill->where("a.FYearId = $fiscalYearId");

                    $transFisSelect = $sql->select();
                    $transFisSelect->from('FA_FiscalyearTrans')
                        ->columns(array('CompanyId'));
                    $transFisSelect->where("FYearId = $fiscalYearId");

                    $selectNotFill = $sql->select();
                    $selectNotFill->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array("CompanyId", "CompanyName", "Sel" => new Expression("1-1"), "Type" => new Expression("1-1")));
                    $selectNotFill->where->notIn('a.CompanyId', $transFisSelect);
                    $selectNotFill->combine($selectFill, 'Union ALL');

                    $select->from(array("g" => $selectNotFill))
                        ->columns(array("*"));
                    $select->order("g.CompanyName");
                } else {
                    $select->from(array("a" => "WF_CompanyMaster"))
                        ->columns(array("CompanyId", "CompanyName", "Sel" => new Expression("1-1"), "Type" => new Expression("1-1")));
                    $select->order("a.CompanyName");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $companyMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->companyMasterList = $companyMasterList;

                $select = $sql->select();
                $select->from(array("a" => "FA_Fiscalyear"))
                    ->columns(array("FName", "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                $select->where("a.FYearId = $fiscalYearId");
                $select->where("a.DeleteFlag=0");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $fiscalList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->fiscalList = $fiscalList;

                $this->_view->FYearId = $fiscalYearId;

            } else if($type == 'addEditDetails'){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $fYearId = $this->bsf->isNullCheck($postData['FYearId'], 'number');
                    $fromDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['startDate'], 'date')));
                    $pstingLock = $curMonth = date('Y-m-d', strtotime("-1 days", strtotime($fromDate)));
                    $mode="A";
                    if ($fYearId != 0) {
                        $mode="E";
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_FiscalyearTrans')
                            ->where("FYearId=$fYearId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_Fiscalyear')
                            ->set(array('FName' => $this->bsf->isNullCheck($postData['fiscalYearName'], 'string')
                            , 'StartDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['startDate'], 'date')))
                            , 'EndDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['endDate'], 'date')))
                            ))
                            ->where(array('FYearId' => $fYearId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $companyCount = $this->bsf->isNullCheck($postData['companyCount'], 'number');
                        for ($i = 1; $i <= $companyCount; $i++) {
                            $selectedRow = $this->bsf->isNullCheck($postData['sel_' . $i], 'number');
                            $postingLock = $this->bsf->isNullCheck($postData['postingLock_' . $i], 'number');

                            if ($selectedRow != 1 || $postingLock == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('FA_FiscalyearTrans');
                            $insert->Values(array('FYearId' => $fYearId
                            , 'CompanyId' => $this->bsf->isNullCheck($postData['companyId_' . $i], 'string')
                            , 'FromDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['startDate'], 'date')))
                            , 'Todate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['endDate'], 'date')))
                            , 'PostingPeriod' => $postingLock
                            , 'PostingDate' => $pstingLock
                            , 'LockDate' => $pstingLock
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_Fiscalyear');
                        $insert->Values(array('FName' => $this->bsf->isNullCheck($postData['fiscalYearName'], 'string')
                        , 'StartDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['startDate'], 'date')))
                        , 'EndDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['endDate'], 'date')))
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $fYearId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $companyCount = $this->bsf->isNullCheck($postData['companyCount'], 'number');
                        for ($i = 1; $i <= $companyCount; $i++) {
                            $selectedRow = $this->bsf->isNullCheck($postData['sel_' . $i], 'number');
                            $postingLock = $this->bsf->isNullCheck($postData['postingLock_' . $i], 'number');

                            if ($selectedRow != 1 || $postingLock == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('FA_FiscalyearTrans');
                            $insert->Values(array('FYearId' => $fYearId
                            , 'CompanyId' => $this->bsf->isNullCheck($postData['companyId_' . $i], 'string')
                            , 'FromDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['startDate'], 'date')))
                            , 'Todate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['endDate'], 'date')))
                            , 'PostingPeriod' => $postingLock
                            , 'PostingDate' => $pstingLock
                            , 'LockDate' => $pstingLock
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Edit','E','FA-Fiscal Year Details',$fYearId,0, 0, 'FA','',$userId, 0 ,0);
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Add','N','FA-Fiscal Year Details',$fYearId,0, 0, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'fiscalyearregister'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->setTerminal(true);
            return $this->_view;
        } else {
            if ($request->isPost()) {

            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function fiscalyearregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest()) {
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['mode'], 'string');
            if($type=="delete")
            {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $FYearId = $this->bsf->isNullCheck($postData['FYearId'], 'number');
                    $sql = new Sql($dbAdapter);

                    // check for already exists
                    $select = $sql->select();
                    $select->from('FA_Account')
                        ->columns(array('FYearId'))
                        ->where(array('FYearId' => $FYearId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $fiscalFound = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(count($fiscalFound) > 0) {
                        $response->setStatusCode(201)->setContent($status);
                    } else {
                        $connection->beginTransaction();

                        $update = $sql->update();
                        $update->table('FA_FiscalYear')
                            ->set(array('DeleteFlag' => '1','ModifiedDate' => date('Y/m/d H:i:s')))
                            ->where(array('FYearId' => $FYearId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Delete','D','FA-Fiscal Year Details',$FYearId,0, 0, 'FA','',$userId, 0 ,0);
                        $status = 'deleted';
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
            } else {
                $select = $sql->select();
                $select->from(array("a" => "FA_FiscalYear"))
                    ->columns(array("FYearId", "FName", "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')")
                    , "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                $select->where("a.DeleteFlag=0");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $result = $fiscalyeardetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            $this->_view->fiscalyeardetList = $fiscalyeardetList;

                $response->setContent(json_encode($result));
            }
            return $response;
        } else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_FiscalYear"))
                ->columns(array("FYearId", "FName", "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')")
                , "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
            $select->where("a.DeleteFlag=0");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $fiscalyeardetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->fiscalyeardetList = $fiscalyeardetList;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function accountentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type=='getLoadDetails'){

                $type_Id = 0;
                $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "FA_AccountMaster"))
                    ->columns(array("TypeId"));
                $select->where("a.AccountId=$accountId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if(isset($accType['TypeId'])){
                    $type_Id = $accType['TypeId'];
                }

                $select = $sql->select();
                $select->from(array("a" => "FA_AccountType"))
                    ->columns(array("TypeId", "TypeName"));

                if($type_Id != 0){
                    $select->where("a.TypeId IN (6,7,23,24,$type_Id)");
                }else{
                    $select->where("a.TypeId IN (6,7,23,24)");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accTypedetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->accTypedetList = $accTypedetList;

                $fixedAccount = 1;
                if($type_Id == 0 || $type_Id == 6|| $type_Id == 7 || $type_Id == 23 || $type_Id == 24 ){
                    $fixedAccount = 0;
                }
                $this->_view->fixedAccount = $fixedAccount;
                $arrAccNameLists = array();
                $select = $sql->select();
                $select->from("FA_AccountMaster")
                    ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                    ->where("ParentAccountId=0 and LastLevel='N' AND AccountType NOT IN ('BA','CA')");
                if($accountId!=0){
                    $select->where("AccountId NOT IN ($accountId)");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accFirstLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach ($accFirstLevelList as &$accFirstLevelLists) {
                    $iLevelno = 1;
                    $iAccId = $accFirstLevelLists['AccountId'];
                    $sAccDesc =$accFirstLevelLists['AccountName'];

                    $dumArr = array();
                    $dumArr = array(
                        'Id' => $iAccId,
                        'ParentId' => $accFirstLevelLists['ParentAccountId'],
                        //'LevelNo' => $iLevelno,
                        'LevelNo' => $accFirstLevelLists['LevelNo'],
                        'LastLevel' => $accFirstLevelLists['LastLevel'],
                        'data' => $iAccId,
                        'value' => $sAccDesc
                    );
                    $arrAccNameLists[] = $dumArr;

                    $select = $sql->select();
                    $select->from("FA_AccountMaster")
                        ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                        ->where("ParentAccountId=$iAccId and LastLevel='N' AND AccountType NOT IN ('BA','CA')");
                    if($accountId!=0){
                        $select->where("AccountId NOT IN ($accountId)");
                    }
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $accSecondLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach ($accSecondLevelList as &$accSecondLevelLists) {
                        $iLevelno = 2;
                        $iAccId = $accSecondLevelLists['AccountId'];
                        $sAccDesc=$sAccDesc . "-->" . $accSecondLevelLists['AccountName'];
                        $dumArr = array();
                        $dumArr = array(
                            'Id' => $iAccId,
                            'ParentId' => $accSecondLevelLists['ParentAccountId'],
                            //'LevelNo' => $iLevelno,
                            'LevelNo' => $accSecondLevelLists['LevelNo'],
                            'LastLevel' => $accSecondLevelLists['LastLevel'],
                            'data' => $iAccId,
                            'value' => $sAccDesc
                        );
                        $arrAccNameLists[] = $dumArr;

                        $select = $sql->select();
                        $select->from("FA_AccountMaster")
                            ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                            ->where("ParentAccountId=$iAccId and LastLevel='N' AND AccountType NOT IN ('BA','CA')");
                        if($accountId!=0){
                            $select->where("AccountId NOT IN ($accountId)");
                        }
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $accThirdLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($accThirdLevelList as &$accThirdLevelLists) {
                            $iLevelno = 3;
                            $iAccId = $accThirdLevelLists['AccountId'];
                            $sAccDesc=$sAccDesc . "-->" . $accThirdLevelLists['AccountName'];

                            $dumArr = array();
                            $dumArr = array(
                                'Id' => $iAccId,
                                'ParentId' => $accThirdLevelLists['ParentAccountId'],
                                //'LevelNo' => $iLevelno,
                                'LevelNo' => $accThirdLevelLists['LevelNo'],
                                'LastLevel' => $accThirdLevelLists['LastLevel'],
                                'data' => $iAccId,
                                'value' => $sAccDesc
                            );
                            $arrAccNameLists[] = $dumArr;

                            $select = $sql->select();
                            $select->from("FA_AccountMaster")
                                ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                                ->where("ParentAccountId=$iAccId and LastLevel='N' AND AccountType NOT IN ('BA','CA')");
                            if($accountId!=0){
                                $select->where("AccountId NOT IN ($accountId)");
                            }
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $accFourthLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($accFourthLevelList as &$accFourthLevelLists) {
                                $iLevelno = 4;
                                $iAccId = $accFourthLevelLists['AccountId'];
                                $sAccDesc=$sAccDesc . "-->" . $accFourthLevelLists['AccountName'];

                                $dumArr = array();
                                $dumArr = array(
                                    'Id' => $iAccId,
                                    'ParentId' => $accFourthLevelLists['ParentAccountId'],
                                    //'LevelNo' => $iLevelno,
                                    'LevelNo' => $accFourthLevelLists['LevelNo'],
                                    'LastLevel' => $accFourthLevelLists['LastLevel'],
                                    'data' => $iAccId,
                                    'value' => $sAccDesc
                                );
                                $arrAccNameLists[] = $dumArr;

                                $select = $sql->select();
                                $select->from("FA_AccountMaster")
                                    ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                                    ->where("ParentAccountId=$iAccId and LastLevel='N' AND AccountType NOT IN ('BA','CA')");
                                if($accountId!=0){
                                    $select->where("AccountId NOT IN ($accountId)");
                                }
                                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                $accFifthLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach ($accFifthLevelList as &$accFifthLevelLists) {
                                    $iLevelno = 5;
                                    $iAccId = $accFifthLevelLists['AccountId'];
                                    $sAccDesc=$sAccDesc . "-->" . $accFifthLevelLists['AccountName'];

                                    $dumArr = array();
                                    $dumArr = array(
                                        'Id' => $iAccId,
                                        'ParentId' => $accFifthLevelLists['ParentAccountId'],
                                        //'LevelNo' => $iLevelno,
                                        'LevelNo' => $accFifthLevelLists['LevelNo'],
                                        'LastLevel' => $accFifthLevelLists['LastLevel'],
                                        'data' => $iAccId,
                                        'value' => $sAccDesc
                                    );
                                    $arrAccNameLists[] = $dumArr;

                                }
                            }

                        }

                    }

                }
                $this->_view->arrAccNameLists = $arrAccNameLists;
                //var_dump($arrAccNameLists);

                $select = $sql->select();
                $select->from(array("a" => "FA_AccountMaster"))
                    ->join(array("b" => "FA_AccountMaster"), "a.ParentAccountId=b.AccountId", array(), $select::JOIN_LEFT)
                    ->columns(array("AccountId", "AccountName", "ParentAccountId", "ParentAccountName" => new Expression("b.AccountName")
                    , 'LastLevel', 'LevelNo' => new Expression("b.LevelNo"), 'TypeId', 'IFRSBSId', 'IFRSCFId', 'IFRSPLId', 'IFRSType' => new Expression("case when a.IFRSCFId <> 0 then 'C' else 'B' end")));
                $select->where("a.AccountId=$accountId AND a.AccountType NOT IN ('BA','CA')");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accdetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->accdetList = $accdetList;

                $this->strAccListId="";
                if($accountId!=0){
                    FaHelper::GetsuperiorAccList($accountId, $dbAdapter);
                }

                if($this->strAccListId != ""){
                    $this->strAccListId = rtrim($this->strAccListId,',');
                }
                //var_dump($this->AccountList);
                $accIds=$this->strAccListId;
                if($accIds==""){ $accIds="0";}

                $strParentAccName="";
                $select = $sql->select();
                $select->from("FA_AccountMaster")
                    ->columns(array('AccountId', 'AccountName', 'ParentAccountId'));
                $select->where("AccountId IN ($accIds)");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accdescFirstLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $icnt=0;
                foreach ($accdescFirstLevelList as &$accdescFirstLevelLists) {
                    $icnt=$icnt+1;
                    if($icnt==1){
                        $strParentAccName=$accdescFirstLevelLists['AccountName'];
                    } else {
                        $strParentAccName = $strParentAccName . "-->" . $accdescFirstLevelLists['AccountName'];
                    }
                }

                $select = $sql->select();
                $select->from("FA_IFRSBS")
                    ->columns(array('BSId', 'Description' => new Expression("BSName")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ifrsBSList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->ifrsBSList = $ifrsBSList;

                $select = $sql->select();
                $select->from("FA_IFRSCF")
                    ->columns(array('CFId', 'Description' => new Expression("Replace(CFName, '\', '')")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ifrsCFList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->ifrsCFList = $ifrsCFList;

                $select = $sql->select();
                $select->from("FA_IFRSPL")
                    ->columns(array('PLId', 'Description' => new Expression("PLName")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $ifrsPLList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->ifrsPLList = $ifrsPLList;
                $this->_view->strParentAccName = $strParentAccName;

                $this->_view->accountId = $accountId;
            }else if($type=='CheckAccountNameValid'){
                $resp = array();
                $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                $ParentaccountId = $this->bsf->isNullCheck($postData['parentAccNameId'], 'number');
                $accDescp = $this->bsf->isNullCheck($postData['accountName'], 'string');

                $select = $sql->select();
                $select->from(array("a" => "FA_AccountMaster"))
                    ->columns(array("AccountId"));
                $select->where("a.AccountName='$accDescp'");
                if($accountId!=0){
                    $select->where("a.AccountId Not IN ($accountId)");
                }
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response->setContent(json_encode($resp));
                return $response;
            }else if($type=='addEditDetails'){

                $postData = $request->getPost();
                try {

                    $connection->beginTransaction();
                    $mode="A";
                    $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                    if($this->bsf->isNullCheck($postData['accountName'], 'string')!="") {
                        $IFRSType = $this->bsf->isNullCheck($postData['IFRSType'], 'string');
                        $IFRSBSVal = $this->bsf->isNullCheck($postData['BalanceSheetId'], 'number');
                        $IFRSCFVal = $this->bsf->isNullCheck($postData['cashFlowId'], 'number');
                        $profitLoss = $this->bsf->isNullCheck($postData['profitLossId'], 'number');

                        $LevelNumber = $this->bsf->isNullCheck($postData['LevelNo'], 'number') + 1;
                        $desc=$this->bsf->isNullCheck($postData['accountName'], 'string');
                        if ($accountId != 0) {
                            $mode="E";
                            $update = $sql->update();
                            $update->table('FA_AccountMaster')
                                ->set(array('ParentAccountId' => $this->bsf->isNullCheck($postData['parentAccNameId'], 'number')
                                , 'AccountName' => $this->bsf->isNullCheck($postData['accountName'], 'string')
                                , 'LastLevel' => $this->bsf->isNullCheck($postData['levelGroup'], 'string')
                                , 'LevelNo' => $LevelNumber
                                , 'TypeId' => $this->bsf->isNullCheck($postData['accountType'], 'number')
                                , 'IFRSBSId' => $IFRSBSVal
                                , 'IFRSCFId' => $IFRSCFVal
                                , 'IFRSPLId' => $profitLoss
                                ))
                                ->where(array('AccountId' => $accountId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $insert = $sql->insert();
                            $insert->into('FA_AccountMaster');
                            $insert->Values(array('ParentAccountId' => $this->bsf->isNullCheck($postData['parentAccNameId'], 'number')
                            , 'AccountName' => $this->bsf->isNullCheck($postData['accountName'], 'string')
                            , 'LastLevel' => $this->bsf->isNullCheck($postData['levelGroup'], 'string')
                            , 'LevelNo' => $LevelNumber
                            , 'TypeId' => $this->bsf->isNullCheck($postData['accountType'], 'number')
                            , 'IFRSBSId' => $IFRSBSVal
                            , 'IFRSCFId' => $IFRSCFVal
                            , 'IFRSPLId' => $profitLoss
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $accountId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }
                        $connection->commit();
                        if ($mode == "E") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Account-Edit','E','FA-Account Details - '.$desc,$accountId,0, 0, 'FA','',$userId, 0 ,0);
                        } else {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Account-Add','N','FA-Account Details - '.$desc,$accountId,0, 0, 'FA','',$userId, 0 ,0);
                        }
                    }
//                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'cashbankregister'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->setTerminal(true);
            return $this->_view;
        } else {

            if ($request->isPost()) {
                /*$postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $IFRSType = $this->bsf->isNullCheck($postData['IFRSType'], 'string');
                    $IFRSBSVal = $this->bsf->isNullCheck($postData['BalanceSheetId'], 'number');
                    $IFRSCFVal = $this->bsf->isNullCheck($postData['cashFlowId'], 'number');
                    $profitLoss = $this->bsf->isNullCheck($postData['profitLossId'], 'number');

                    $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                    $LevelNumber = $this->bsf->isNullCheck($postData['LevelNo'], 'number') + 1;
                    if ($accountId != 0) {
                        $update = $sql->update();
                        $update->table('FA_AccountMaster')
                            ->set(array('ParentAccountId' => $this->bsf->isNullCheck($postData['parentAccNameId'], 'number')
                            , 'AccountName' => $this->bsf->isNullCheck($postData['accountName'], 'string')
                            , 'LastLevel' => $this->bsf->isNullCheck($postData['levelGroup'], 'string')
                            , 'LevelNo' => $LevelNumber
                            , 'TypeId' => $this->bsf->isNullCheck($postData['accountType'], 'number')
                            , 'IFRSBSId' => $IFRSBSVal
                            , 'IFRSCFId' => $IFRSCFVal
                            , 'IFRSPLId' => $profitLoss
                            ))
                            ->where(array('AccountId' => $accountId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_AccountMaster');
                        $insert->Values(array('ParentAccountId' => $this->bsf->isNullCheck($postData['parentAccNameId'], 'number')
                        , 'AccountName' => $this->bsf->isNullCheck($postData['accountName'], 'string')
                        , 'LastLevel' => $this->bsf->isNullCheck($postData['levelGroup'], 'string')
                        , 'LevelNo' => $LevelNumber
                        , 'TypeId' => $this->bsf->isNullCheck($postData['accountType'], 'number')
                        , 'IFRSBSId' => $IFRSBSVal
                        , 'IFRSCFId' => $IFRSCFVal
                        , 'IFRSPLId' => $profitLoss
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //$PaymentId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'accountdirectory'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }*/
            }


            /*echo '<pre>';
           print_r($arrAccNameLists);
           echo '</pre>';
           die;*/
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function companyaccountdetAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        //$fYearId = $this->bsf->isNullCheck($this->params()->fromRoute('fYearId'),'number');
        //$companyId = $this->bsf->isNullCheck($this->params()->fromRoute('companyId'),'number');
        $response = $this->getResponse();

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                $accIds=$postData['accIds'];
                $companyListId= $this->bsf->isNullCheck($postData['companyListId'], 'number');
                $fYearListId= $this->bsf->isNullCheck($postData['fYearListId'], 'number');
                try {

                    $connection->beginTransaction();
                    foreach($accIds as $accList) {
                        $insert = $sql->insert();
                        $insert->into('FA_Account');
                        $insert->Values(array('AccountId' => $accList
                        , 'CompanyId' => $companyListId
                        , 'FYearId' => $fYearListId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    //                    $accId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $connection->commit();
                    $result='Success';
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Company-Account-Add','N','FA-Company Account Details',$fYearListId,0, $companyListId, 'FA','',$userId, 0 ,0);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $this->strAccListId="";
            //$this->AccountList=array();

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->columns(array("AccountId"));
            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accfilledList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($accfilledList as $accfilledLists) {
                $curAccId=$accfilledLists['AccountId'];
                //$this->AccountList[]=$curAccId;
                FaHelper::GetsuperiorAccList($curAccId, $dbAdapter);
                $this->strAccListId=$this->strAccListId. $curAccId .",";
            }

            if($this->strAccListId != ""){
                $this->strAccListId = rtrim($this->strAccListId,',');
            }
            //var_dump($this->AccountList);
            $accIds=$this->strAccListId;
            if($accIds==""){ $accIds="0";}

            /*$select = $sql->select();
            $select->from(array("AM" => "FA_AccountMaster"))
                ->join(array("AT" => "FA_AccountType"), "AM.TypeId=AT.TypeId", array(), $select::JOIN_LEFT)
                ->columns(array("AccountId", "AccountName", "TypeName" => new Expression("AT.TypeName"), "ParentAccountId", "TypeId", "AccountType", "IsFixed", "LevelNo", "LastLevel"
                , "IFRSBSId", "IFRSPLId", "IFRSCFId", "SectionId", "Move" => new Expression("''"), "expanded" => new Expression("1-1")));
            $select->where("AM.AccountType NOT IN ('BA','CA') ");
            $select->where("AM.AccountId IN ($accIds) ");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);*/



             $select = $sql->select();
             $select->from(array("a" => "FA_FiscalYear"))
                 ->columns(array("FYearId", "FName"));
             $select->where("a.DeleteFlag=0");
             $statement = $statement = $sql->getSqlStringForSqlObject($select);
             $accFiscalList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             $this->_view->accFiscalList = $accFiscalList;

             $transFisSelect = $sql->select();
             $transFisSelect->from('FA_FiscalyearTrans')
                 ->columns(array('CompanyId'));
             $transFisSelect->where("FYearId = $fYearId");

             $select = $sql->select();
             $select->from(array("a" => "WF_CompanyMaster"))
                 ->columns(array("CompanyId", "CompanyName"));
             $select->where("DeleteFlag=0");
             $select->where->In('a.CompanyId', $transFisSelect);

             $statement = $statement = $sql->getSqlStringForSqlObject($select);
             $accCompMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             $this->_view->accCompMasterList = $accCompMasterList;

             $statement = "exec FA_GetCompanyAccDetailsPick @CompanyId= " .$companyId . ",@FYearId= " .$fYearId;
             $accCheckedList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             $this->_view->accCheckedList = $accCheckedList;

            // try {
                 //$connection->beginTransaction();
            $g_lYearTransId = 0;
            $g_lCompanyId = 0;
            $g_lYearId = 0;
            $g_lNYearId = 0;
            $g_lPYearId = 0;
            $g_lCPYearId = 0;
            $g_lCNYearId = 0;
            //BsfGlobal.g_dStartDate = Convert.ToDateTime(dt.Rows[0]["StartDate"].ToString());
            //BsfGlobal.g_dEndDate = Convert.ToDateTime(dt.Rows[0]["EndDate"].ToString());
            //BsfGlobal.g_sCompanyDBName = dt.Rows[0]["DBName"].ToString();
            $g_sCompanyName ="";

             if($fYearId!=0 && $companyId!=0) {
                 $select = $sql->select();
                 $select->columns(array(new expression("IF NOT EXISTS (SELECT * FROM dbo.sysobjects WHERE id = OBJECT_ID(N'[dbo].[FA_AccountList]') AND OBJECTPROPERTY(id, N'IsView') = 1)
                                     EXEC dbo.sp_executesql @statement = N'CREATE VIEW [dbo].[FA_AccountList] AS WITH DirectReports(AccountID, AccountName, ParentAccountID, LastLevel, LevelNo, CompanyId, TypeId, AccountType,SortId) AS
                                     (SELECT A.AccountId, A.AccountName, A.ParentAccountId,A.LastLevel, A.LevelNo, B.CompanyId, A.TypeId, A.AccountType,A.SortId FROM FA_AccountMaster AS A
                                     INNER JOIN FA_Account AS B ON A.AccountId = B.AccountID
                                     UNION ALL
                                     SELECT E.AccountId, E.AccountName, E.ParentAccountId, E.LastLevel, E.LevelNo, D.CompanyId, E.TypeId, E.AccountType,E.SortId FROM FA_AccountMaster E
                                     INNER JOIN DirectReports D ON E.AccountId = D.ParentAccountId)
                                     SELECT DISTINCT AccountID, AccountName, ParentAccountID, LastLevel, LevelNo, CompanyId, TypeId,AccountType,SortId FROM DirectReports DR'
                                 ELSE
                                     EXEC dbo.sp_executesql @statement = N'ALTER VIEW [dbo].[FA_AccountList] AS WITH DirectReports(AccountID, AccountName, ParentAccountID, LastLevel, LevelNo, CompanyId, TypeId,AccountType,SortId) AS
                                     (SELECT A.AccountId, A.AccountName, A.ParentAccountId,A.LastLevel, A.LevelNo, B.CompanyId, A.TypeId, A.AccountType,A.SortId FROM FA_AccountMaster AS A
                                     INNER JOIN FA_Account AS B ON A.AccountId = B.AccountID
                                     UNION ALL
                                     SELECT E.AccountId, E.AccountName, E.ParentAccountId, E.LastLevel, E.LevelNo, D.CompanyId, E.TypeId, E.AccountType,E.SortId FROM FA_AccountMaster E
                                     INNER JOIN DirectReports D ON E.AccountId = D.ParentAccountId)
                                     SELECT DISTINCT AccountID, AccountName, ParentAccountID, LastLevel, LevelNo, CompanyId, TypeId,AccountType,SortId FROM DirectReports DR'")));
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
                 $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE);

                 $select = $sql->select();
                 $select->columns(array(new expression("IF NOT EXISTS (SELECT * FROM dbo.sysobjects WHERE id = OBJECT_ID(N'[dbo].[FA_AccountListOP]') AND OBJECTPROPERTY(id, N'IsView') = 1)
                                     EXEC dbo.sp_executesql @statement = N'CREATE VIEW [dbo].[FA_AccountListOP] AS SELECT AL.AccountID, AL.AccountName, AL.ParentAccountID, AL.LastLevel, AL.LevelNo, AL.CompanyId, ISNULL(A.OpeningBalance, 0) AS OP, A.FromCC, A.FromSL FROM dbo.FA_AccountList AS AL LEFT OUTER JOIN dbo.FA_Account AS A ON AL.AccountID = A.AccountID AND AL.CompanyId= A.CompanyId'
                                 ELSE
                                     EXEC dbo.sp_executesql @statement = N'ALTER VIEW [dbo].[FA_AccountListOP] AS SELECT AL.AccountID, AL.AccountName, AL.ParentAccountID, AL.LastLevel, AL.LevelNo, AL.CompanyId, ISNULL(A.OpeningBalance, 0) AS OP,A.FromCC, A.FromSL FROM dbo.FA_AccountList AS AL LEFT OUTER JOIN dbo.FA_Account AS A ON AL.AccountID = A.AccountID AND AL.CompanyId= A.CompanyId'")));
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
                 $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE);

                 $select = $sql->select();
                 $select->columns(array(new expression("IF NOT EXISTS (SELECT * FROM dbo.sysobjects WHERE id = OBJECT_ID(N'[dbo].[FA_CCAccountListOP]') AND OBJECTPROPERTY(id, N'IsView') = 1)
                                     EXEC dbo.sp_executesql @statement = N'CREATE VIEW [dbo].[FA_CCAccountListOP] AS SELECT AL.AccountID, AL.AccountName, AL.ParentAccountID, C.CostCentreId, C.CostCentreName, AL.LastLevel, AL.LevelNo, AL.CompanyId,ISNULL(CCA.OpeningBalance, 0) OP
                                         FROM dbo.FA_AccountList AS AL LEFT OUTER JOIN (SELECT CompanyId,ParentAccountId,CostCentreId,SUM(OpeningBalance) OpeningBalance
                                         FROM dbo.FA_CCAccount A   GROUP BY CompanyId,ParentAccountId,CostCentreId) AS CCA ON AL.AccountID = CCA.ParentAccountId AND AL.CompanyId = CCA.CompanyId
                                         INNER JOIN dbo.WF_CostCentre AS C ON C.CostCentreId = CCA.CostCentreId'
                                 ELSE
                                     EXEC dbo.sp_executesql @statement = N'ALTER VIEW [dbo].[FA_CCAccountListOP] AS SELECT AL.AccountID, AL.AccountName, AL.ParentAccountID, C.CostCentreId, C.CostCentreName, AL.LastLevel, AL.LevelNo, AL.CompanyId,ISNULL(CCA.OpeningBalance, 0) OP
                                         FROM dbo.FA_AccountList AS AL LEFT OUTER JOIN (SELECT CompanyId,ParentAccountId,CostCentreId,SUM(OpeningBalance) OpeningBalance
                                         FROM dbo.FA_CCAccount GROUP BY CompanyId,ParentAccountId,CostCentreId) AS CCA ON AL.AccountID = CCA.ParentAccountId AND AL.CompanyId = CCA.CompanyId
                                         INNER JOIN dbo.WF_CostCentre AS C ON C.CostCentreId = CCA.CostCentreId'")));
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
                 $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE);

                 //delete
                 $subQuery1 = $sql->select();
                 $subQuery1->from("FA_AccountMaster")
                     ->columns(array('AccountId'))
                     ->where("AccountType IN ('BA','CA')");

                 $deleteTrans = $sql->delete();
                 $deleteTrans->from('FA_CCAccount')
                     ->where("SubLedgerId<>0");
                 $deleteTrans->where->expression('ParentAccountId IN ?', array($subQuery1));
                 $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                 $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                 //Update
                 $subQuery1 = $sql->select();
                 $subQuery1->from("FA_SLAccountType")
                     ->columns(array('TypeId'));

                 $subQuery = $sql->select();
                 $subQuery->from("FA_AccountMaster")
                     ->columns(array('AccountId'));
                 $subQuery->where->expression('TypeId IN ?', array($subQuery1));

                 $update = $sql->update();
                 $update->table('FA_Account')
                     ->set(array('FromSL' => 1
                     , 'FromCC' => 0))
                     ->where("(FromSL=0 OR FromCC=1)");
                 $update->where->expression('AccountId IN ?', array($subQuery));
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                 //update
                 $subQuery1 = $sql->select();
                 $subQuery1->from("FA_SLAccountType")
                     ->columns(array('TypeId'));

                 $subQuery = $sql->select();
                 $subQuery->from("FA_AccountMaster")
                     ->columns(array('AccountId'));
                 $subQuery->where->expression('TypeId NOT IN ?', array($subQuery1));

                 $update = $sql->update();
                 $update->table('FA_Account')
                     ->set(array('FromSL' => 0
                     , 'FromCC' => 1))
                     ->where("(FromSL=1 OR FromCC=0)");
                 $update->where->expression('AccountId IN ?', array($subQuery));
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                 //Delete
                 $subQuery1 = $sql->select();
                 $subQuery1->from("FA_Account")
                     ->columns(array('AccountId'))
                     ->where("FromSL=1 AND CompanyId=$companyId");

                 $deleteTrans = $sql->delete();
                 $deleteTrans->from('FA_CCAccount')
                     ->where("SubLedgerId=0 AND CompanyId=$companyId");
                 $deleteTrans->where->expression('ParentAccountId IN ?', array($subQuery1));
                 $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                 $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                 //update
                 $update = $sql->update();
                 $update->table("FA_Account")
                     ->set(array('OpeningBalance' => new Expression ("Amount from FA_Account A JOIN (SELECT CompanyId,ParentAccountId,Amount=SUM(OpeningBalance) FROM FA_CCAccount GROUP BY ParentAccountId,CompanyId) B ON
                                 A.AccountId=B.ParentAccountId WHERE OpeningBalance<>Amount AND A.CompanyId=B.CompanyId")));
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Load Global value
                 $select = $sql->select();
                 $select->from('FA_FiscalYearTrans')
                     ->columns(array('FYearTransId'))
                     ->where("FYearId=$fYearId and CompanyId=$companyId");
                 $stmt = $sql->getSqlStringForSqlObject($select);
                 $refFiscalList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                 if ($refFiscalList) {
                     $g_lYearTransId = $refFiscalList['FYearTransId'];
                 }
                //UPDATE FiscalYearTrans SET PostingDate=DateAdd(Day,-1,FromDate) WHERE PostingDate IS NULL
                 $update = $sql->update();
                 $update->table("FA_FiscalYearTrans")
                     ->set(array('PostingDate' => new Expression ("DateAdd(Day,-1,FromDate)")));
                 $update->where("PostingDate IS NULL");
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                 //UPDATE FiscalYearTrans SET LockDate=DateAdd(Day,-1,FromDate) WHERE LockDate IS NULL
                 $update = $sql->update();
                 $update->table("FA_FiscalYearTrans")
                     ->set(array('LockDate' => new Expression ("DateAdd(Day,-1,FromDate)")));
                 $update->where("LockDate IS NULL");
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                 //UPDATE FiscalYear SET PFYearId=B.FYearId FROM FiscalYear A JOIN (SELECT FYearId,NFYearId FROM FiscalYear WHERE NFYearId<>0) B ON A.FYearId=B.NFYearId WHERE B.NFYearId<>0 AND A.PFYearId=0
                 $update = $sql->update();
                 $update->table("FA_FiscalYear")
                     ->set(array('PFYearId' => new Expression ("B.FYearId FROM FA_FiscalYear A JOIN (SELECT FYearId,NFYearId FROM FA_FiscalYear WHERE NFYearId<>0) B ON A.FYearId=B.NFYearId WHERE B.NFYearId<>0 AND A.PFYearId=0")));
                 $statement = $sql->getSqlStringForSqlObject($update);
                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                /*SELECT A.CompanyId,A.FYearId,B.NFYearId,B.PFYearId,A.CPFYearId,A.CNFYearId, B.FName,B.StartDate,B.EndDate,B.DBName
                 ,C.CompanyName,B.FName,0 Period ,C.CurrencyId,CM2.DecimalLength, B.CYCaption,B.LYCaption, A.PostingPeriod, A.PostingDate,
                 A.LockDate,A.Freeze,C.Address1,C.Address2,C.Pincode,CM.CityName,SM.StateName,CM1.CountryName,
                 C.Phone,C.Fax,C.Mobile,C.ContactPerson,C.Email,C.Website,C.STNo,C.CSTNo,C.GIRNo,C.PANNo,C.TANNo,C.TNGSTNo,
                 C.TIN,C.CIN,C.ShortName FROM dbo.FiscalYearTrans A
                 INNER JOIN dbo.FiscalYear B ON A.FYearId=B.FYearId
                 INNER JOIN [dnhwF].dbo.CompanyMaster C ON A.CompanyId = C.CompanyId
                 LEFT JOIN  [dnhwF].dbo.CityMaster CM ON C.CityId= CM.CityId
                 LEFT JOIN  [dnhwF].dbo.StateMaster SM ON SM.StateId= CM.StateId
                 LEFT JOIN  [dnhwF].dbo.CountryMaster CM1 ON CM1.CountryId= CM.CountryId
                 INNER JOIN [dnhwF].dbo.CurrencyMaster CM2 ON CM2.CurrencyId=C.CurrencyId
                 WHERE A.FYearTransId=11*/
                 $select = $sql->select();
                 $select->from(array("a" => "FA_FiscalYearTrans"))
                     ->columns(array('CompanyId','FYearId','NFYearId'=> new Expression ("b.NFYearId"),'PFYearId'=> new Expression ("b.PFYearId")
                     ,'CPFYearId','CNFYearId','StartDate'=> new Expression ("b.StartDate"),'EndDate'=> new Expression ("b.EndDate"),'CompanyName'=> new Expression ("c.CompanyName")))
                     ->join(array("b" => "FA_FiscalYear"), "a.FYearId=b.FYearId", array(), $select::JOIN_INNER)
                     ->join(array("c" => "WF_CompanyMaster"), "a.CompanyId=c.CompanyId", array(), $select::JOIN_INNER)
                     ->where("a.FYearTransId=$g_lYearTransId");
                 $stmt = $sql->getSqlStringForSqlObject($select);
                 $refFiscalDetList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                 if ($refFiscalDetList) {
                     $g_lCompanyId = $refFiscalDetList['CompanyId'];
                     $g_lYearId = $refFiscalDetList['FYearId'];
                     $g_lNYearId = $refFiscalDetList['NFYearId'];
                     $g_lPYearId = $refFiscalDetList['PFYearId'];
                     $g_lCPYearId = $refFiscalDetList['CPFYearId'];
                     $g_lCNYearId = $refFiscalDetList['CNFYearId'];
                 }

             }
                //$connection->commit();
            // } catch (PDOException $e) {
            //    $connection->rollback();
           // }

            //if(arg_iCCId==0)
            $select = $sql->select();
            $select->columns(array(new expression("SELECT A.AccountId,A.AccountName,A.ParentAccountId,A.LastLevel,
                    CASE WHEN B.OP >0 THEN B.OP ELSE 0 END Debit,
                    CASE WHEN B.OP <0 THEN B.OP*(-1) ELSE 0 END Credit,FromCC=CAST(ISNULL(B.FromCC,0) As bit), FromSL=CAST (ISNULL(B.FromSL,0) AS bit),A.TypeId,(1-1) as expanded
                    FROM dbo.FA_AccountList A LEFT JOIN (SELECT B.AccountId,B.CompanyId,FromCC=CAST(ISNULL(A.FromCC,0) As bit), FromSL=CAST (ISNULL(A.FromSL,0) AS bit),OP=SUM(B.OP)
                    FROM dbo.FA_Account A INNER JOIN dbo.FA_CCAccountListOP B ON A.AccountId=B.AccountID AND A.CompanyId=B.CompanyId
                    WHERE B.CompanyId= $companyId and A.FYearId=$fYearId
                    GROUP BY B.AccountId,B.CompanyId,CAST(ISNULL(A.FromCC,0) As bit), CAST (ISNULL(A.FromSL,0) AS bit)) B ON A.AccountId = B.AccountId AND A.CompanyId=B.CompanyId
                    WHERE A.CompanyId = $companyId
                    ORDER BY A.LevelNo,A.SortId, A.AccountId")));

            // else
            /*SELECT A.AccountID,A.AccountName,A.ParentAccountID,A.LastLevel, " +
                                     "CASE WHEN B.OP >0 THEN B.OP ELSE 0 END Debit,CASE WHEN B.OP <0 THEN B.OP*(-1) ELSE 0 END Credit, FromCC=CAST(ISNULL(A1.FromCC,0) As bit), FromSL=CAST (ISNULL(A1.FromSL,0) AS bit),A.TypeId " +
                                     "FROM AccountList A LEFT JOIN Account A1 ON A.AccountId=A1.AccountId AND A.CompanyId=A1.CompanyId " +
                                     "LEFT JOIN CCAccountListOP B ON A.AccountId=B.AccountId and A.CompanyId=B.CompanyId " +
                                     "AND B.CostCentreId={1} WHERE  A.CompanyId =
             */
           $statement = $sql->getSqlStringForSqlObject($select);
             $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
            $accDirectoryList = $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists1= array();
            $irowACCId=0;
            foreach($accDirectoryList as $accDirectoryLists) {
                $irowACCId=$accDirectoryLists['AccountId'];
                $this->strAccListId="";
                if($irowACCId!=0){
                    FaHelper::GetLowLevelAccList($irowACCId, $companyId, $dbAdapter);
                }

                if($this->strAccListId != ""){
                    $this->strAccListId = rtrim($this->strAccListId,',');
                }
                //var_dump($this->AccountList);
                $accIds=$this->strAccListId;
                if($accIds==""){ $accIds="0";}

                $select = $sql->select();
                $select->from(array("B" => "FA_CCAccountListOP"))
                    ->columns(array("Debit"=> new Expression("CASE WHEN SUM(B.OP) >0 THEN SUM(B.OP) ELSE 0 END"), "Credit"=> new Expression("CASE WHEN SUM(B.OP) <0 THEN SUM(B.OP)*(-1) ELSE 0 END")))
                    ->join(array("A" => "FA_Account"), new Expression("B.AccountId=A.AccountId And A.CompanyId=B.CompanyId"), array(), $select::JOIN_INNER);
                $select->where("B.CompanyId=$companyId and b.AccountId in ($accIds) and a.FYearId=$fYearId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $obAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $debit=$obAccList['Debit'];
                $credit=$obAccList['Credit'];

                $dumArr1 = array();
                if($accDirectoryLists['LastLevel']=="Y"){
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'Debit' => $accDirectoryLists['Debit'],
                        'Credit' => $accDirectoryLists['Credit'],
                        'FromCC' => $accDirectoryLists['FromCC'],
                        'FromSL' => $accDirectoryLists['FromSL'],
                        'TypeId' => $accDirectoryLists['TypeId'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                } else {
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'Debit' => $debit,
                        'Credit' => $credit,
                        'FromCC' => $accDirectoryLists['FromCC'],
                        'FromSL' => $accDirectoryLists['FromSL'],
                        'TypeId' => $accDirectoryLists['TypeId'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                }
                $arrUnitLists1[] = $dumArr1;
            }
            $this->_view->accDirectoryList = $arrUnitLists1;

            $this->_view->companyId = $companyId;
            $this->_view->fYearId = $fYearId;
            $this->_view->g_lCNYearId = $g_lCNYearId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function ifrsdetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $typeId = $this->bsf->isNullCheck($this->params()->fromRoute('typeId'),'number');
        $response = $this->getResponse();

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
            //$response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            if($typeId==0){
                $select->from(array("a" => "FA_IFRSBS"))
                    ->columns(array("Id"=> new Expression("a.BSId"), "Description"=> new Expression("a.BSName"), "ParentId" , "LevelNo", "LastLevel", "expanded" => new Expression("1-1")));
            } else if ($typeId==1) {
                $select->from(array("a" => "FA_IFRSCF"))
                    ->columns(array("Id"=> new Expression("a.CFId"), "Description"=> new Expression("a.CFName"), "ParentId" , "LevelNo", "LastLevel", "expanded" => new Expression("1-1")));
                //$select->where("a.CFId in (1,2, 3, 4,5, 6, 7, 8,9,10,12,27,28)");
                //5,27,14,11,13,24
            } else if ($typeId==2) {
                $select->from(array("a" => "FA_IFRSPL"))
                    ->columns(array("Id"=> new Expression("a.PLId"), "Description"=> new Expression("a.PLName"), "ParentId" , "LevelNo", "LastLevel", "expanded" => new Expression("1-1")));
            }
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accDirectoryList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //var_dump(json_encode($accDirectoryList, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT| JSON_HEX_TAG | JSON_UNESCAPED_UNICODE));die;
            $this->_view->accDirectoryList = $accDirectoryList;
            $this->_view->typeId = $typeId;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function paymentadviceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $payAdvId= $this->bsf->isNullCheck($this->params()->fromRoute('paymentAdvRegId'),'number');
        $billDate= date('d-M-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $type= $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type){
                        case 'getSubLedgerName':
                            $slTypeId= $this->bsf->isNullCheck($postData['slTypeId'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_SubLedgerMaster"))
                                ->columns(array('SubLedgerId','SubLedgerName'));
                            $select->where("SubLedgerTypeId=$slTypeId");
                            $select->order(array("SubLedgerId"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result=$subLedgerNameList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            break;

                        case 'filterBill':
                            $slTypeId= $this->bsf->isNullCheck($postData['slTypeId'], 'number');
                            $billType= $this->bsf->isNullCheck($postData['billType'], 'string');
                            $slId= $this->bsf->isNullCheck($postData['slId'], 'number');

                            if($billType == 'O'){
                                $billType="'B','A'";
                            }else if($billType == 'B'){
                                $billType="'B'";
                            }else if($billType == 'A'){
                                $billType="'A'";
                            }
                            $subQuery = $sql->select();
                            $subQuery->from(array("a" => "FA_AccountMaster"))
                                ->columns(array('AccountId'));
                            $subQuery->where("TypeId IN (10,37)");

                            $selectBill = $sql->select();
                            $selectBill->from(array("A" => "FA_BillRegister"))
                                ->columns(array(
                                    'SubLedgerId'=>new Expression("SLM.SubLedgerId"),'SubLedgerName'=>new Expression("SLM.SubLedgerName")
                                ,'BillRegisterId',"BillDate" => new Expression("FORMAT(A.BillDate, 'dd-MM-yyyy')"),'BillNo','CreditDays'
                                , 'DueDate' => new Expression("FORMAT(DATEADD(DAY,CreditDays,RefDate),'dd-MM-yyyy')")
                                ,'RefTypeId','AccountId','RefNo','RefDate'=>new Expression("A.RefDate"),'BillAmount'
                                ,'Advance'=>new Expression("A.Advance+WriteOff")
                                ,'O/B Adv'=>new Expression("1-1"),'DebitAmount'
                                ,'RefAmount'=>new Expression("(BillAmount-Advance-DebitAmount-WriteOff)")
                                ,'PayAdvAmount','ApproveAmount'
                                ,'CurrentAmount'=>new Expression("CAST (1-1 AS Decimal(18,3))")
                                ,'PaidAmount','FromOB','CostCentreId','SLName'=>new Expression("SLM.SubLedgerName")
                                ,'CostCentreName'=>new Expression("B.CostCentreName"),'TypeName'=>new Expression("C.TypeName")
                                ,'BillForexRate'=>new Expression("CAST(1-1 As decimal(18,6))"),'Sel'=>new Expression("CAST(1-1 As Bit)")
                                ,'HAdvance'=>new Expression("CAST(1-1 AS Decimal(18,3))"),'HCurrent'=>new Expression("CAST(1-1 AS Decimal(18,3))")
                                ,'BillType'=>new Expression("case when A.BillType='B' THEN 'Bill' else 'Advance' end")))
                                ->join(array("B" => "WF_CostCentre"), "A.CostCentreId=B.CostCentreId", array(), $selectBill::JOIN_LEFT)
                                ->join(array("c" => "FA_InvoiceType"), "C.TypeId=A.RefTypeId", array(), $selectBill::JOIN_LEFT)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=A.SubLedgerId", array(), $selectBill::JOIN_INNER);
                            $selectBill->where->expression("((BillAmount-Advance-DebitAmount-WriteOff)>PayAdvAmount) AND A.CompanyId=$companyId
                            AND A.BillDate<='$billDate' AND A.BillType IN ($billType) AND SLM.SubLedgerTypeId=$slTypeId AND A.CurrencyId=0
                            AND A.Approve='Y' AND A.TransType='P' AND (A.CostCentreId<>0) AND A.AccountId NOT IN ?",array($subQuery));
                            if($slId != 0) //for subledger name filter
                                $selectBill->where("A.SubLedgerId=$slId");
                            $selectBill->order(array("SLM.SubLedgerName"))
                                ->limit(10)
                                ->offset(0);
                            $statement = $sql->getSqlStringForSqlObject($selectBill);
                            $result=$billRegister= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            break;

                        case 'advanceReturnBill':

                            $subLedgerId= $this->bsf->isNullCheck($postData['subLedgerId'], 'string');
                            $payAdvId= $this->bsf->isNullCheck($postData['payAdvId'], 'number');

                            $subQuery = $sql->select();
                            $subQuery->from(array("a" => "FA_OBBillRefDet"))
                                ->columns(array('AdvRegId'));
                            $subQuery->where("PayAdviceId=$payAdvId");

                            $select = $sql->select();
                            $select->from(array("BR" => "FA_BillRegister"))
                                ->join(array("CC" => "WF_CostCentre"), "BR.CostCentreId=CC.CostCentreId", array(), $select::JOIN_LEFT)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=BR.SubLedgerId", array(), $select::JOIN_INNER)
                                ->columns(array('BillRegisterId','SubLedgerId','BillType','BillNo','BillDate' => new Expression("FORMAT(BR.BillDate, 'dd-MM-yyyy')"), 'RefNo'
                                ,'SubLedgerName' => new Expression("SLM.SubLedgerName")
                                ,'RefDate' => new Expression("FORMAT(BR.RefDate, 'dd-MM-yyyy')"), 'RefTypeId', 'RefType'
                                ,'CostCentreName' => new Expression("CC.CostCentreName"),'BillAmount','Adjusted' => new Expression("BR.PayAdvAmount")
                                ,'Balance' => new Expression("BR.BillAmount-BR.PayAdvAmount"),'Current' => new Expression("CAST (0 As decimal(18,3))")
                                ,'CostCentreId', 'Sel' => new Expression("Cast(0 AS bit)"), 'HAdjust' => new Expression("BR.PayAdvAmount"), 'HCurrent' => new Expression("CAST (0 As decimal(18,3))")
                                ));
                            $select->where("PaidAmount<>BillAmount AND BillType='A' AND FromOB=1 AND BR.SubLedgerId in ($subLedgerId) AND BR.BillDate<='$billDate'");
                            $select->where->expression("BR.BillRegisterId NOT IN ?", array($subQuery));
                            if($payAdvId != 0) {
                                $selectGroupEdit = $sql->select();
                                $selectGroupEdit->from(array("BR" => "FA_BillRegister"))
                                    ->join(array("CC" => "WF_CostCentre"), "BR.CostCentreId=CC.CostCentreId", array(), $selectGroupEdit::JOIN_LEFT)
                                    ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=BR.SubLedgerId", array(), $selectGroupEdit::JOIN_INNER)
                                    ->columns(array('BillRegisterId','SubLedgerId','BillType','BillNo','BillDate' => new Expression("FORMAT(BR.BillDate, 'dd-MM-yyyy')"), 'RefNo'
                                    ,'SubLedgerName' => new Expression("SLM.SubLedgerName")
                                    ,'RefDate' => new Expression("FORMAT(BR.RefDate, 'dd-MM-yyyy')"), 'RefTypeId', 'RefType'
                                    ,'CostCentreName' => new Expression("CC.CostCentreName"),'BillAmount','Adjusted' => new Expression("BR.PayAdvAmount")
                                    ,'Balance' => new Expression("BR.BillAmount-BR.PayAdvAmount"),'Current' => new Expression("CAST (0 As decimal(18,3))")
                                    ,'CostCentreId', 'Sel' => new Expression("CAST(ISNULL((SELECT TOP 1 BillRegId FROM FA_OBBillRefDet A WHERE PayAdviceId=$payAdvId),0) AS bit)")
                                    , 'HAdjust' => new Expression("BR.PayAdvAmount"), 'HCurrent' => new Expression("CAST (0 As decimal(18,3))")
                                    ));
                                $selectGroupEdit->where->expression("BR.BillRegisterId IN ?", array($subQuery));
                                $select->combine($selectGroupEdit,'Union ALL');
                            }
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['advanceList']=$advanceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array("A" => "FA_OBBillRefDet"))
                                ->join(array("B" => "FA_BillRegister"), "A.AdvRegId=B.BillRegisterId", array(), $select::JOIN_INNER)
                                ->columns(array('PayAdviceId','BillRegId','AdvRegId','RefTypeId' => new Expression("B.RefTypeId") , 'Amount'
                                ,'HAmount' => new Expression("Amount"), 'CostCentreId' => new Expression("B.CostCentreId")
                                ));
                            $select->where("PayAdviceId<>0 AND PayAdviceId=$payAdvId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $advAdjList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $subQuery = $sql->select();
                            $subQuery->from(array('RA' => 'FA_ReturnAdjustment'))
                                ->columns(array('AdjustAmount'=> new Expression("SUM(AdjustAmount)")));
                            $subQuery->where("RA.AdviceId=$payAdvId AND RA.ReturnRegId=RR.ReturnRegId");

                            $select = $sql->select();
                            $select->from(array("RR" => "FA_ReturnRegister"))
                                ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select::JOIN_INNER)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=RR.SubLedgerId", array(), $select::JOIN_INNER)
                                ->columns(array('ReturnRegId','ReturnDate' => new Expression("FORMAT(RR.ReturnDate, 'dd-MM-yyyy')"),'ReturnNo'
                                ,'CostCentreName' => new Expression("CC.CostCentreName") , 'ReturnAmount','Adjusted' => new Expression("RR.AdjustAmount")
                                ,'SubLedgerName' => new Expression("SLM.SubLedgerName"),'SubLedgerId'
                                ,'Balance' => new Expression("RR.ReturnAmount-RR.AdjustAmount"),'CostCentreId','HAdjust' => new Expression("RR.AdjustAmount")
                                ,'Current' => new Expression("ISNULL((" . $subQuery->getSqlString() . "),0)")
                                ,'HCurrent' => new Expression("ISNULL((" . $subQuery->getSqlString() . "),0)")
                                ));
                            $select->where("RR.SubLedgerId in ($subLedgerId) AND ReturnDate<='$billDate'");
                            if($payAdvId == 0) {
                                $select->where("ReturnAmount>AdjustAmount");
                            } else {
                                $select->where("((ReturnAmount>AdjustAmount) OR ReturnRegId IN (SELECT ReturnRegId FROM FA_ReturnAdjustment WHERE AdviceId=$payAdvId))");
                            }
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['returnList']=$returnList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            break;

                        case 'loadMoreFeed':
                            $PageNo=$postData['PageNo']-1;
                            $offset = 10 * $PageNo;

                            $slTypeId= $this->bsf->isNullCheck($postData['slTypeId'], 'number');
                            $billType= $this->bsf->isNullCheck($postData['billType'], 'string');
                            $slId= $this->bsf->isNullCheck($postData['slId'], 'number');

                            $subQuery = $sql->select();
                            $subQuery->from(array("a" => "FA_AccountMaster"))
                                ->columns(array('AccountId'));
                            $subQuery->where("TypeId IN (10,37)");

                            $selectBill = $sql->select();
                            $selectBill->from(array("A" => "FA_BillRegister"))
                                ->columns(array(
                                    'SubLedgerId' => new Expression("SLM.SubLedgerId"),'SubLedgerName' => new Expression("SLM.SubLedgerName")
                                , 'BillRegisterId', "BillDate" => new Expression("FORMAT(A.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'CreditDays'
                                , 'DueDate' => new Expression("FORMAT(DATEADD(DAY,CreditDays,RefDate),'dd-MM-yyyy')")
                                , 'RefTypeId', 'AccountId', 'RefNo', 'RefDate' => new Expression("A.RefDate"), 'BillAmount'
                                , 'Advance' => new Expression("A.Advance+WriteOff")
                                , 'O/B Adv' => new Expression("1-1"), 'DebitAmount'
                                , 'RefAmount' => new Expression("(BillAmount-Advance-DebitAmount-WriteOff)")
                                , 'PayAdvAmount', 'ApproveAmount'
                                , 'CurrentAmount' => new Expression("CAST (1-1 AS Decimal(18,3))")
                                , 'PaidAmount', 'FromOB', 'CostCentreId', 'SLName' => new Expression("SLM.SubLedgerName")
                                , 'CostCentreName' => new Expression("B.CostCentreName"), 'TypeName' => new Expression("C.TypeName")
                                , 'BillForexRate' => new Expression("CAST(1-1 As decimal(18,6))"), 'Sel' => new Expression("CAST(1-1 As Bit)")
                                , 'HAdvance' => new Expression("CAST(1-1 AS Decimal(18,3))"), 'HCurrent' => new Expression("CAST(1-1 AS Decimal(18,3))")
                                , 'BillType' => new Expression("case when A.BillType='B' THEN 'Bill' else 'Advance' end")))
                                ->join(array("B" => "WF_CostCentre"), "A.CostCentreId=B.CostCentreId", array(), $selectBill::JOIN_LEFT)
                                ->join(array("c" => "FA_InvoiceType"), "C.TypeId=A.RefTypeId", array(), $selectBill::JOIN_LEFT)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=A.SubLedgerId", array(), $selectBill::JOIN_INNER);
                             $selectBill->where->expression("((BillAmount-Advance-DebitAmount-WriteOff)>PayAdvAmount) AND A.CompanyId=$companyId AND
                             A.BillDate<='$billDate' AND A.BillType IN ($billType) AND SLM.SubLedgerTypeId=$slTypeId AND A.CurrencyId=0 AND A.Approve='Y' AND A.TransType='P' AND
                              (A.CostCentreId<>0) AND A.AccountId NOT IN ?", array($subQuery));
                            if($slId != 0) //for subledger name filter
                                $selectBill->where("A.SubLedgerId=$slId");
                            $selectBill->order(array("SLM.SubLedgerName"))
                                ->limit(10)
                                ->offset($offset);
                            $statement = $sql->getSqlStringForSqlObject($selectBill);
                            $result=$billRegister= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            break;

                        default:
                            $result='';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection->beginTransaction();
                $payAdvId= $this->bsf->isNullCheck($postData['payAdvId'], 'number');
                $subRowId= $this->bsf->isNullCheck($postData['currentBillRowId'], 'number');
                $billNo = $this->bsf->isNullCheck($postData['adviceNo'], 'string');
                $billDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['adviceDate'], 'date')));
                $sLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId'], 'number');
                $sLedgerId = 0;//$this->bsf->isNullCheck($postData['subLedgerId'], 'number');
                $SLedgerName= '';//$this->bsf->isNullCheck($postData['subLedgerName'], 'string');
                $adviceTotal = $this->bsf->isNullCheck($postData['currentTot'], 'number');
                $returnAmount = $this->bsf->isNullCheck($postData['deductionTot'], 'number');
                $netTotal = $adviceTotal - $returnAmount;
                $billType = $this->bsf->isNullCheck($postData['billType'], 'string');
                $iCurrencyId=0;
                $mode="A";
                $adviceNo = $this->bsf->isNullCheck($postData['adviceNo'], 'string');

                try {
                    $dTotalAdv=0;
                    $iSuppId=0;
                    $iContId=0;
                    $iServId=0;
                    $iSuppAdvId=0;
                    $iContAdvId=0;
                    $iSerAdvId=0;
                    $select = $sql->select();
                    $select->from(array('a' =>'FA_AccountMaster'))
                        ->columns(array('AccountId','IsPurchase','IsWork','IsService'))
                        ->where("(IsPurchase=1 OR IsWork=1 OR IsService=1)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $loadResultSers = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $isPurcgase = $loadResultSers['IsPurchase'];
                    $isWork = $loadResultSers['IsWork'];
                    $isService = $loadResultSers['IsService'];
                    if($isPurcgase == 1){
                        $iSuppId = $loadResultSers['AccountId'];
                    }
                    if($isWork == 1){
                        $iContId = $loadResultSers['AccountId'];
                    }
                    if($isService == 1){
                        $iServId = $loadResultSers['AccountId'];
                    }

                    $select = $sql->select();
                    $select->from(array('a' =>'FA_AccountMaster'))
                        ->columns(array('AccountId','IsPoAdvance','IsWoAdvance','IsSoAdvance'))
                        ->where("(IsPoAdvance=1 OR IsWoAdvance=1 OR IsSoAdvance=1)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $loadResultAdv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $isPoAdvance = $loadResultAdv['IsPoAdvance'];
                    $isWoAdvance = $loadResultAdv['IsWoAdvance'];
                    $isSoAdvance = $loadResultAdv['IsSoAdvance'];
                    if($isPoAdvance == 1){
                        $iSuppAdvId = $loadResultAdv['AccountId'];
                    }
                    if($isWoAdvance == 1){
                        $iContAdvId = $loadResultAdv['AccountId'];
                    }
                    if($isSoAdvance == 1){
                        $iSerAdvId = $loadResultAdv['AccountId'];
                    }

                    if($payAdvId!=0){
                        if ($iCurrencyId != 0){
                            /*
                             * UPDATE [{0}].dbo.ForexBillRegister SET AdviceAmount= [{0}].dbo.ForexBillRegister.AdviceAmount-P.ApproveAmount FROM PayAdviceTrans P " +
                             "WHERE [{0}].dbo.ForexBillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId={1}
                             */
                            $update = $sql->update();
                            $update->table("FA_ForexBillRegister")
                                ->set(array('AdviceAmount' => new Expression ("FA_ForexBillRegister.AdviceAmount-P.ApproveAmount FROM FA_PayAdviceTrans P
                                WHERE FA_ForexBillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId=$payAdvId")));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            /*
                             * UPDATE [{0}].dbo.BillRegister SET PayAdvAmount= [{0}].dbo.BillRegister.PayAdvAmount-(P.ApproveAmount*P.BillForexRate) FROM PayAdviceTrans P " +
                             "WHERE [{0}].dbo.BillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId=
                             */
                            $update = $sql->update();
                            $update->table("FA_BillRegister")
                                ->set(array('PayAdvAmount' => new Expression ("FA_BillRegister.PayAdvAmount-(P.ApproveAmount*P.BillForexRate) FROM FA_PayAdviceTrans P
                                WHERE FA_BillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId=$payAdvId")));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            /*UPDATE [{0}].dbo.BillRegister SET PayAdvAmount= [{0}].dbo.BillRegister.PayAdvAmount-P.ApproveAmount FROM PayAdviceTrans P " +
                             "WHERE [{0}].dbo.BillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId={1} */
                            $update = $sql->update();
                            $update->table("FA_BillRegister")
                                ->set(array('PayAdvAmount' => new Expression ("FA_BillRegister.PayAdvAmount-P.ApproveAmount FROM FA_PayAdviceTrans P
                                WHERE FA_BillRegister.BillRegisterId=P.BillRegisterId AND P.PayAdviceId=$payAdvId")));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //DELETE FROM PayAdviceTrans WHERE PayAdviceId=
                        $delete = $sql->delete();
                        $delete->from('FA_PayAdviceTrans')
                            ->where("PayAdviceId=$payAdvId");
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DELETE FROM [{0}].dbo.EntryTrans WHERE Remarks='Advance Adjustment(Opening)' AND RefType IN ('PV','WB','SB') " +
                        //"AND RefId IN (SELECT BillRegId FROM OBBillRefDet WHERE PayAdviceId=
                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_OBBillRefDet")
                            ->columns(array('BillRegId'))
                            ->where("PayAdviceId=$payAdvId");

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTrans')
                            ->where("Remarks='Advance Adjustment(Opening)' AND RefType IN ('PV','WB','SB')");
                        $deleteTrans->where->expression('RefId IN ?', array($subQuery1));
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //UPDATE BillRegister SET PayAdvAmount=0,ApproveAmount=0, PaidAmount=0 WHERE BillRegisterId IN (SELECT AdvRegId FROM OBBillRefDet WHERE PayAdviceId=
                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_OBBillRefDet")
                            ->columns(array('AdvRegId'))
                            ->where("PayAdviceId=$payAdvId");
                        $update = $sql->update();
                        $update->table('FA_BillRegister')
                            ->set(array('PayAdvAmount' => 0
                            , 'ApproveAmount' => 0
                            , 'PaidAmount' => 0 ));
                        $update->where->expression('BillRegisterId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //UPDATE BillRegister SET Advance=0, PayAdvAmount=PayAdvAmount-B.Advance FROM BillRegister A JOIN (SELECT BillRegId,Advance=Amount FROM OBBillRefDet WHERE PayAdviceId={0} ) B ON A.BillRegisterId=B.BillRegId
                        $update = $sql->update();
                        $update->table("FA_BillRegister")
                            ->set(array('Advance' => 0,'PayAdvAmount' => new Expression ("PayAdvAmount-B.Advance FROM FA_BillRegister A JOIN
                             (SELECT BillRegId,Advance=Amount FROM FA_OBBillRefDet WHERE PayAdviceId=$payAdvId ) B ON A.BillRegisterId=B.BillRegId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DELETE FROM BillRefDet WHERE ReferenceId IN (SELECT AdvRegId FROM OBBillRefDet WHERE PayAdviceId=
                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_OBBillRefDet")
                            ->columns(array('AdvRegId'))
                            ->where("PayAdviceId=$payAdvId");

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_BillRefDet');
                        $deleteTrans->where->expression('ReferenceId IN ?', array($subQuery1));
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DELETE FROM OBBillRefDet WHERE PayAdviceId=
                        $delete = $sql->delete();
                        $delete->from('FA_OBBillRefDet')
                            ->where("PayAdviceId=$payAdvId");
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    if($payAdvId==0) {
                        /*
                         * INSERT INTO dbo.PayAdviceDet(PayAdviceId,PayAdviceDate,PayAdviceNo,SLTypeId,SLedgerId,BillAmount,ReturnAmount,TotalAmount,BillType
                         * ,Remarks, CurrencyId, CompanyId,FYearId)
	                    VALUES(@PayId,@PayAdvDate,@PayAdvNo,@SLTypeId,@SLedgerId,@BillAmount,@ReturnAmount, @TotalAmount,@BillType,@Remarks, @CurrencyId, @CompanyId,@FYearId)
                         */
                        $insert = $sql->insert();
                        $insert->into('FA_PayAdviceDet');
                        $insert->Values(array('PayAdviceDate' => $billDate
                        , 'PayAdviceNo' => $billNo
                        , 'SLTypeId' => $sLTypeId
                        , 'SLedgerId' => $sLedgerId
                        , 'BillAmount' => $adviceTotal
                        , 'ReturnAmount' => $returnAmount
                        , 'TotalAmount' => $netTotal
                        , 'BillType' => $billType
                        , 'Remarks' => ''
                        , 'CurrencyId' => 0
                        , 'CompanyId' => $companyId
                        , 'FYearId' => $FYearId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $payAdvId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $mode="E";
                        /*
                         * INSERT INTO [{0}]..PastPayAdviceDet (PayAdviceId,PayAdviceDate,PayAdviceNo,SLTypeId,SLedgerId,TotalAmount,CurrencyId,PayForexRate,BillType, CompanyId,FYearId,Remarks,Approve,IsLock,IsCancel,UserId)
                         * SELECT PayAdviceId,PayAdviceDate,PayAdviceNo,SLTypeId,SLedgerId,TotalAmount,CurrencyId,PayForexRate,BillType, CompanyId,FYearId,Remarks,Approve,IsLock,IsCancel,{1} FROM PayAdviceDet WHERE PayAdviceId={2} SELECT SCOPE_IDENTITY () ", BsfGlobal.g_sCompanyDBName, BsfGlobal.g_lUserId, obj.PayAdvId
                         *
                         * INSERT INTO [{0}]..PastPayAdviceTrans (HistoryId,PayTransId,PayAdviceId,BillRegisterId,ApproveAmount,CurrencyId, BillForexRate,CompanyId,FYearId,EntryId,UserId)
                         * SELECT {1},PayTransId,PayAdviceId,BillRegisterId,ApproveAmount,CurrencyId, BillForexRate,CompanyId,FYearId,EntryId,{2}
                         * FROM PayAdviceTrans WHERE PayAdviceId={3}", BsfGlobal.g_sCompanyDBName, iHistoryId, BsfGlobal.g_lUserId, obj.PayAdvId
                         */
                        $iHistoryId = 0;
                        $select = $sql->select();
                        $select->from(array('a' => 'FA_PayAdviceDet' ))
                            ->columns(array( 'PayAdviceId', 'PayAdviceDate', 'PayAdviceNo', 'SLTypeId', 'SLedgerId', 'TotalAmount', 'CurrencyId'
                            , 'PayForexRate', 'BillType', 'CompanyId', 'FYearId', 'Remarks', 'Approve', 'IsLock'
                            , 'IsCancel','UserId'=>new Expression("$userId") ));
                        $select->where("a.PayAdviceId=$payAdvId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastPayAdviceDet' );
                        $insert->columns(array('PayAdviceId', 'PayAdviceDate', 'PayAdviceNo', 'SLTypeId', 'SLedgerId', 'TotalAmount', 'CurrencyId', 'PayForexRate', 'BillType', 'CompanyId', 'FYearId', 'Remarks', 'Approve', 'IsLock', 'IsCancel', 'UserId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        $iHistoryId= $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_PayAdviceTrans' ))
                            ->columns(array('HistoryId'=>new Expression("$iHistoryId"), 'PayTransId','PayAdviceId','BillRegisterId','ApproveAmount'
                            ,'CurrencyId','BillForexRate','CompanyId','FYearId','EntryId', 'UserId'=>new Expression("$userId") ));
                        $select->where("a.PayAdviceId=$payAdvId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastPayAdviceTrans' );
                        $insert->columns(array('HistoryId','PayTransId','PayAdviceId','BillRegisterId','ApproveAmount','CurrencyId','BillForexRate','CompanyId','FYearId','EntryId','UserId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        $update = $sql->update();
                        $update->table('FA_PayAdviceDet')
                            ->set(array('PayAdviceDate' => $billDate
                            , 'PayAdviceNo' => $billNo
                            , 'SLTypeId' => $sLTypeId
                            , 'SLedgerId' => $sLedgerId
                            , 'BillAmount' => $adviceTotal
                            , 'ReturnAmount' => $returnAmount
                            , 'TotalAmount' => $netTotal
                            , 'BillType' => $billType
                            , 'Remarks' => ''
                            , 'CurrencyId' => $iCurrencyId
                            , 'CompanyId' => $companyId
                            , 'FYearId' => $FYearId ))
                            ->where("PayAdviceId=$payAdvId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    for($i=1;$i<=$subRowId;$i++) {
                        $billRegisterId = $this->bsf->isNullCheck($postData['curBillRegisterId_' . $i], 'number');
                        $billnoTrans = $this->bsf->isNullCheck($postData['curBillNo_' . $i], 'number');
                        $currentAmt = $this->bsf->isNullCheck($postData['curAmount_' . $i], 'number');
                        $ForexRate = $this->bsf->isNullCheck($postData['curBillForexRate_' . $i], 'number');
                        $billTypeRow = $this->bsf->isNullCheck($postData['curBillType_' . $i], 'number');
                        $costcentreId = $this->bsf->isNullCheck($postData['curCostCentreId_' . $i], 'number');
                        $dAdvAmount= $this->bsf->isNullCheck($postData['curAdvance_' . $i], 'number');

                        $ForexAmt = $currentAmt * $ForexRate;
                        if ($billRegisterId == 0 || $currentAmt == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('FA_PayAdviceTrans');
                        $insert->Values(array('PayAdviceId' => $payAdvId
                        , 'BillRegisterId' => $billRegisterId
                        , 'ApproveAmount' => $currentAmt
                        , 'BillForexRate' => $ForexRate
                        , 'CurrencyId' => $iCurrencyId
                        , 'CompanyId' => $companyId
                        , 'FYearId' => 0));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        if($iCurrencyId==0){
                            $tot=$dAdvAmount + $currentAmt;
                            $update = $sql->update();
                            $update->table('FA_BillRegister')
                                ->set(array('PayAdvAmount' => new Expression("PayAdvAmount + ".$currentAmt)
//                                    ,'ApproveAmount' => new Expression("PayAdvAmount + ".$tot)
//                                    ,'PaidAmount' => new Expression("PayAdvAmount + ".$tot)
                                ))
                                ->where("BillRegisterId=$billRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            //UPDATE [{0}].dbo.ForexBillRegister SET AdviceAmount=AdviceAmount+{1} WHERE BillRegisterId={2}", BsfGlobal.g_sFaDBName, objTrans.ApproveAmount, objTrans.BillRegId
                            $update = $sql->update();
                            $update->table('FA_ForexBillRegister')
                                ->set(array('AdviceAmount' => new Expression("AdviceAmount + ".$currentAmt)))
                                ->where("BillRegisterId=$billRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //UPDATE [{0}].dbo.BillRegister SET PayAdvAmount=PayAdvAmount+{1} WHERE BillRegisterId={2}", BsfGlobal.g_sFaDBName, objTrans.ForexAmount, objTrans.BillRegId
                            $update = $sql->update();
                            $update->table('FA_BillRegister')
                                ->set(array('PayAdvAmount' => new Expression("PayAdvAmount + ".$ForexAmt)))
                                ->where("BillRegisterId=$billRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        if($billTypeRow=="B"){
                            //advance loop and filter by BillRegId=$billRegisterId
                            $dAdvance=0;
                            $advRegId=0;
                            $iCCId=0;
                            $advanceRowId=0;
                            $dAdvance= $this->bsf->isNullCheck($postData['curAdvance_' . $i], 'number');
                            if ($dAdvance!=0){
                                $dTotalAdv=$dTotalAdv+$dAdvance;
                                $iCCId= $this->bsf->isNullCheck($postData['costcentreId_' . $i], 'number');
                                //$advRegId= $this->bsf->isNullCheck($postData['AdvRegId_' . $j], 'number');
                                $advRegId = $this->bsf->isNullCheck($postData['advCurBillRegisterId_' . $i], 'number');

                                //UPDATE BillRegister SET PayAdvAmount=PayAdvAmount+{0},ApproveAmount=PayAdvAmount+{0},PaidAmount=PayAdvAmount+{0}
                                //WHERE BillRegisterId={1}", dAdvance, dtAdv.Rows[i]["AdvRegId"]
                                if($advRegId !=0) {
                                    $update = $sql->update();
                                    $update->table('FA_BillRegister')
                                        ->set(array('PayAdvAmount' => new Expression("PayAdvAmount + " . $dAdvance)
                                        , 'ApproveAmount' => new Expression("PayAdvAmount + " . $dAdvance)
                                        , 'PaidAmount' => new Expression("PayAdvAmount + " . $dAdvance)))
                                        ->where("BillRegisterId=$advRegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //UPDATE BillRegister SET Advance=Advance+{0} WHERE BillRegisterId={1}", dAdvance, objTrans.BillRegId
                                    $update = $sql->update();
                                    $update->table('FA_BillRegister')
                                        ->set(array('Advance' => new Expression("Advance + " . $dAdvance)
//                                        , 'ApproveAmount' => new Expression("PayAdvAmount + " . $dAdvance)
//                                        , 'PaidAmount' => new Expression("PayAdvAmount + " . $dAdvance)
                                        ))
                                        ->where("BillRegisterId=$billRegisterId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //INSERT INTO [{0}].dbo.BillRefDet(BillRegisterId,ReferenceId,RefAmount)
                                    //SELECT {1}, {2},{3} ", BsfGlobal.g_sFaDBName, objTrans.BillRegId, dtAdv.Rows[i]["AdvRegId"], dAdvance
                                    $insert = $sql->insert();
                                    $insert->into('FA_BillRefDet');
                                    $insert->Values(array('BillRegisterId' => $billRegisterId
                                    , 'ReferenceId' => $advRegId
                                    , 'RefAmount' => $dAdvance));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    //INSERT INTO OBBillRefDet (PayAdviceId,BillRegId,AdvRegId,Amount)
                                    //VALUES ({0},{1},{2},{3} )", iPayAdvId, objTrans.BillRegId, dtAdv.Rows[i]["AdvRegId"], dAdvance
                                    $insert = $sql->insert();
                                    $insert->into('FA_OBBillRefDet');
                                    $insert->Values(array('PayAdviceId' => $payAdvId
                                    , 'BillRegId' => $billRegisterId
                                    , 'AdvRegId' => $advRegId
                                    , 'Amount' => $dAdvance));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $RefTypeId = $this->bsf->isNullCheck($postData['advCurRefTypeId_' . $i], 'number');

                                    $iVendorAccId = $iSuppId;
                                    $iAdvAccId = $iSuppAdvId;
                                    $sRefType = "PV";

                                    if ($RefTypeId == 7) {
                                        $iVendorAccId = $iSuppId;
                                        $iAdvAccId = $iSuppAdvId;
                                        $sRefType = "PV";
                                    } else if ($RefTypeId == 8 || $RefTypeId == 23) {
                                        $iVendorAccId = $iContId;
                                        $iAdvAccId = $iContAdvId;
                                        $sRefType = "WB";
                                    } else if ($RefTypeId == 12) {
                                        $iVendorAccId = $iServId;
                                        $iAdvAccId = $iSerAdvId;
                                        $sRefType = "SB";
                                    } else if ($RefTypeId == 25) {
                                        $iVendorAccId = $iServId;
                                        $iAdvAccId = $iSerAdvId;
                                        $sRefType = "HB";
                                    }

                                    /*
                                     * sSql = "INSERT INTO [" + BsfGlobal.g_sCompanyDBName + "].dbo.EntryTrans(RefId,TransType,RefType,AccountId,
                                     * RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,Amount,CompanyId,VoucherNo, VoucherDate,BranchId,Remarks,Approve)  " +
                                               "VALUES (" + objTrans.BillRegId + ",'D','"+ sRefType +"'," + iVendorAccId + "," + iAdvAccId + "," + obj.SLTypeId + "," + obj.SLedgerId + ",1," + obj.SLedgerId + "," + objTrans.CCId + "," + dAdvance + "," + BsfGlobal.g_lCompanyId + ",'" + objTrans.BillNo + "','" + String.Format("{0:dd-MMM-yyyy}", obj.PayAdvDate) + "',0,'Advance Adjustment(Opening)','Y')";

                                     */
                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $billRegisterId
                                    , 'TransType' => 'D'
                                    , 'RefType' => $sRefType
                                    , 'AccountId' => $iVendorAccId
                                    , 'RelatedAccountId' => $iAdvAccId
                                    , 'SubLedgerTypeId' => $sLTypeId
                                    , 'SubLedgerId' => $sLedgerId
                                    , 'RelatedSLTypeId' => 1
                                    , 'RelatedSLId' => $sLedgerId
                                    , 'CostCentreId' => $costcentreId
                                    , 'Amount' => $dAdvance
                                    , 'CompanyId' => $companyId
                                    , 'VoucherNo' => $billnoTrans
                                    , 'VoucherDate' => $billDate
                                    , 'BranchId' => 0
                                    , 'Remarks' => 'Advance Adjustment(Opening)'
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $billRegisterId
                                    , 'TransType' => 'C'
                                    , 'RefType' => $sRefType
                                    , 'AccountId' => $iAdvAccId
                                    , 'RelatedAccountId' => $iVendorAccId
                                    , 'SubLedgerTypeId' => $sLTypeId
                                    , 'SubLedgerId' => $sLedgerId
                                    , 'RelatedSLTypeId' => 1
                                    , 'RelatedSLId' => $sLedgerId
                                    , 'CostCentreId' => $iCCId
                                    , 'Amount' => $dAdvance
                                    , 'CompanyId' => $companyId
                                    , 'VoucherNo' => $billnoTrans
                                    , 'VoucherDate' => $billDate
                                    , 'BranchId' => 0
                                    , 'Remarks' => 'Advance Adjustment(Opening)'
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }
                    $sSLName = $SLedgerName;
                    //Code Added By Bala - Checking PayAdvAmount - Bill Amount as per .Net version

                    for($i=1;$i<=$subRowId;$i++) {
                        $billRegisterId = $this->bsf->isNullCheck($postData['curBillRegisterId_' . $i], 'number');
                        if ($iCurrencyId == 0) {
                                /*
                                 * UPDATE BillRegister SET PayAdvAmount= Total FROM BillRegister A JOIN (SELECT BillRegisterId, Total=SUM(ApproveAmount) " +
                                 "FROM dbo.PayAdviceTrans A INNER JOIN dbo.PayAdviceDet B ON A.PayAdviceId=B.PayAdviceId WHERE IsCancel=0 AND BillRegisterId={0} GROUP BY BillRegisterId ) B ON A.BillRegisterId=B.BillRegisterId " +
                                 "AND A.PayAdvAmount<>B.Total ",
                                 objTrans.BillRegId
                                 */
                            $update = $sql->update();
                            $update->table("FA_BillRegister")
                                ->set(array('PayAdvAmount' => new Expression ("Total FROM FA_BillRegister A JOIN (SELECT BillRegisterId, Total=SUM(ApproveAmount)
                                    FROM FA_PayAdviceTrans A INNER JOIN FA_PayAdviceDet B ON A.PayAdviceId=B.PayAdviceId WHERE IsCancel=0 AND BillRegisterId=$billRegisterId GROUP BY BillRegisterId ) B ON A.BillRegisterId=B.BillRegisterId
                                    AND A.PayAdvAmount<>B.Total")));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        } else {
                            /*
                             *  sSql = String.Format("UPDATE BillRegister SET PayAdvAmount= Total FROM BillRegister A JOIN (SELECT BillRegisterId, Total=SUM(ApproveAmount*BillForexRate) " +
                                                 "FROM dbo.PayAdviceTrans A INNER JOIN dbo.PayAdviceDet B ON A.PayAdviceId=B.PayAdviceId  WHERE IsCancel=0 AND BillRegisterId={0}  GROUP BY BillRegisterId ) B ON A.BillRegisterId=B.BillRegisterId " +
                                                 "AND A.PayAdvAmount<>B.Total
                             */
                            $update = $sql->update();
                            $update->table("FA_BillRegister")
                                ->set(array('PayAdvAmount' => new Expression ("Total FROM FA_BillRegister A JOIN (SELECT BillRegisterId, Total=SUM(ApproveAmount*BillForexRate)
                                    FROM FA_PayAdviceTrans A INNER JOIN FA_PayAdviceDet B ON A.PayAdviceId=B.PayAdviceId  WHERE IsCancel=0 AND BillRegisterId=$billRegisterId GROUP BY BillRegisterId ) B ON A.BillRegisterId=B.BillRegisterId
                                    AND A.PayAdvAmount<>B.Total")));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if($payAdvId!=0){
                        /*
                         * UPDATE dbo.ReturnRegister SET AdjustAmount=AdjustAmount-SummedQty FROM dbo.ReturnRegister A JOIN (" +
                               "SELECT ReturnRegId,SummedQty=SUM(AdjustAmount) FROM dbo.ReturnAdjustment WHERE AdviceId=" + arg_iPayAdvId + " GROUP BY ReturnRegId)B " +
                               "ON A.ReturnRegId=B.ReturnRegId
                        "DELETE FROM dbo.ReturnAdjustment WHERE AdviceId=" + arg_iPayAdvId
                         */
                        $update = $sql->update();
                        $update->table("FA_ReturnRegister")
                            ->set(array('AdjustAmount' => new Expression ("AdjustAmount-SummedQty FROM FA_ReturnRegister A JOIN (
                                SELECT ReturnRegId,SummedQty=SUM(AdjustAmount) FROM FA_ReturnAdjustment WHERE AdviceId=$payAdvId GROUP BY ReturnRegId)B
                                    ON A.ReturnRegId=B.ReturnRegId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('FA_ReturnAdjustment')
                            ->where("AdviceId=$payAdvId");
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    //Return Row count loop
                    $returnRowId=0;
                    $returnRowId= $this->bsf->isNullCheck($postData['deductionRowId'], 'number');
                    for($k=1;$k<=$returnRowId;$k++) {
                        $returnRegId = $this->bsf->isNullCheck($postData['deductionReturnRegId_' . $k], 'number');
                        $RetAdjAmount = $this->bsf->isNullCheck($postData['deductionCurrentAmount_' . $k], 'number');
                        /*
                         * INSERT INTO dbo.ReturnAdjustment(ReturnRegId,AdjustAmount,AdjustAmount) " +
                               "SELECT " + objReturn.ReturnRegId + "," + objReturn.RetAdjAmount + "," +iPayAdvId +
                         */
                        $insert = $sql->insert();
                        $insert->into('FA_ReturnAdjustment');
                        $insert->Values(array('ReturnRegId' => $returnRegId
                        , 'AdjustAmount' => $RetAdjAmount
                        , 'AdviceId' => $payAdvId ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    if($returnRowId>0){
                        /*
                         * UPDATE dbo.ReturnRegister SET AdjustAmount=AdjustAmount+SummedQty FROM dbo.ReturnRegister A JOIN (" +
                               "SELECT ReturnRegId,SummedQty=SUM(AdjustAmount) FROM dbo.ReturnAdjustment WHERE AdviceId=" + arg_iPayAdvId + " GROUP BY ReturnRegId)B " +
                               "ON A.ReturnRegId=B.ReturnRegId
                         */

                        $update = $sql->update();
                        $update->table("FA_ReturnRegister")
                            ->set(array('AdjustAmount' => new Expression ("AdjustAmount+SummedQty FROM FA_ReturnRegister A JOIN (
                                SELECT ReturnRegId,SummedQty=SUM(AdjustAmount) FROM FA_ReturnAdjustment WHERE AdviceId=$payAdvId GROUP BY ReturnRegId)B
                                    ON A.ReturnRegId=B.ReturnRegId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*
                         * SELECT ReturnRegId,ReturnAmount,AdjustAmount FROM [{0}]..ReturnRegister WHERE ReturnAmount<AdjustAmount AND ReturnRegId IN (" +
                                             "SELECT ReturnRegId FROM [{0}]..ReturnAdjustment WHERE AdviceId={1}
                         */

                        $subQuery = $sql->select();
                        $subQuery->from(array("a" => "FA_ReturnAdjustment"))
                            ->columns(array('ReturnRegId'));
                        $subQuery->where("AdviceId=$payAdvId");

                        $selectReturn = $sql->select();
                        $selectReturn->from(array("A" => "FA_ReturnRegister"))
                            ->columns(array('ReturnRegId','ReturnAmount','AdjustAmount'))
                            ->where("ReturnAmount < AdjustAmount");
                        $selectReturn->where->expression("A.ReturnRegId IN ?",array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($selectReturn);
                        $returnRegisterdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($returnRegisterdet)>0) {
                            $connection->rollback();
                            echo '<script type="text/javascript">alert("Return Adjustment greater than Return Value,Cannot proceed..");</script>';
                            //alert to Return Adjustment greater than Return Value,Cannot proceed..
                            return;
                        }
                    }
                    /*
                     * SELECT BillRegisterId,BillAmount,PayAdvAmount FROM [{0}]..BillRegister WHERE BillAmount<PayAdvAmount AND BillRegisterId IN (" +
                             "SELECT BillRegisterId FROM [{0}]..PayAdviceTrans WHERE PayAdviceId={1}
                     */
                    $subQuery = $sql->select();
                    $subQuery->from(array("a" => "FA_PayAdviceTrans"))
                        ->columns(array('BillRegisterId'));
                    $subQuery->where("PayAdviceId=$payAdvId");

                    $selectBillReg = $sql->select();
                    $selectBillReg->from(array("A" => "FA_BillRegister"))
                        ->columns(array('BillRegisterId','BillAmount','PayAdvAmount'))
                        ->where("BillAmount < PayAdvAmount");
                    $selectBillReg->where->expression("A.BillRegisterId IN ?",array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($selectBillReg);
                    $billRegisterdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //echo '<pre>'; print_r($billRegisterdet);die;
                    //Insert PayAdviceEmplyee Breakup Det
                    $payAdviceEmplyeeRowId=0;
                    if($payAdviceEmplyeeRowId>0) {
                        /*
                     * UPDATE dbo.BillTransDet SET AdviceAmount= AdviceAmount-SummedQty FROM  dbo.BillTransDet A JOIN (" +
                               "SELECT BillTransId,SummedQty=SUM(Amount) FROM dbo.PayAdviceEmployeeDet " +
                               "WHERE PayAdviceId=" + iPayAdvId + " GROUP BY BillTransId) B ON A.BillTransId=B.BillTransId

                        DELETE FROM PayAdviceEmployeeDet WHERE PayAdviceId=
                     */
                        $update = $sql->update();
                        $update->table("FA_BillTransDet")
                            ->set(array('AdjustAmount' => new Expression ("AdviceAmount-SummedQty FROM  FA_BillTransDet A JOIN (
                                SELECT BillTransId,SummedQty=SUM(Amount) FROM FA_PayAdviceEmployeeDet WHERE PayAdviceId=$payAdvId GROUP BY BillTransId) B ON A.BillTransId=B.BillTransId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('FA_PayAdviceEmployeeDet')
                            ->where("PayAdviceId=$payAdvId");
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        for ($l = 1; $l <= $payAdviceEmplyeeRowId; $l++) {
                            $payAdviceEmplyeeBillRegisterId = $this->bsf->isNullCheck($postData['BillRegisterId_' . $l], 'number');
                            $payAdviceEmplyeeBillTransId = $this->bsf->isNullCheck($postData['BillTransId_' . $l], 'number');
                            $payAdviceEmplyeeAmount = $this->bsf->isNullCheck($postData['Current_' . $l], 'number');
                            if($payAdviceEmplyeeAmount!=0){
                                /*
                             * INSERT INTO dbo.PayAdviceEmployeeDet (PayAdviceId,BillRegId,BillTransId,Amount) " +
                               "SELECT " + iPayAdvId + "," + oPayAdvEmpDet.BillRegisterId + "," + oPayAdvEmpDet.BillTransId + "," + oPayAdvEmpDet.Current +
                             */
                                $insert = $sql->insert();
                                $insert->into('FA_PayAdviceEmployeeDet');
                                $insert->Values(array('PayAdviceId' => $payAdvId
                                , 'BillRegId' => $payAdviceEmplyeeBillTransId
                                , 'BillTransId' => $payAdviceEmplyeeBillTransId
                                , 'Amount' => $payAdviceEmplyeeAmount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        /*
                         * UPDATE dbo.BillTransDet SET AdviceAmount= SummedQty FROM  dbo.BillTransDet A JOIN (" +
                               "SELECT BillTransId,SummedQty=SUM(Amount) FROM dbo.PayAdviceEmployeeDet " +
                               "WHERE BillRegId IN (SELECT BillRegId FROM dbo.PayAdviceEmployeeDet WHERE PayAdviceId="+ iPayAdvId + ")" +
                               "GROUP BY BillTransId) B ON A.BillTransId=B.BillTransId
                         */
                        $update = $sql->update();
                        $update->table("FA_BillTransDet")
                            ->set(array('AdviceAmount' => new Expression ("SummedQty FROM  FA_BillTransDet A JOIN (
                                SELECT BillTransId,SummedQty=SUM(Amount) FROM FA_PayAdviceEmployeeDet
                                WHERE BillRegId IN (SELECT BillRegId FROM FA_PayAdviceEmployeeDet WHERE PayAdviceId=$payAdvId
                                GROUP BY BillTransId) B ON A.BillTransId=B.BillTransId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    /* if(count($billRegisterdet)>0) {
                         $connection->rollback();
                         echo '<script type="text/javascript">alert("Advice Value greater than Bill Value ,Cannot proceed..");</script>';
                         //alert to Advice Value greater than Bill Value ,Cannot proceed..
                         return;
                     }*/

                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Payment-Advice-Edit','E','FA-Payment Advice Details',$payAdvId,0, $companyId, 'FA','',$userId, 0 ,0);//pass refno after FA as voucher $adviceNo
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Payment-Advice-Add','N','FA-Payment Advice Details',$payAdvId,0, $companyId, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "payment-advice-register"));
                 } catch (PDOException $e) {
                     $connection->rollback();
                 }
             }

             $SubLedgerTypeId=1;

             $select = $sql->select();
             $select->from(array("a" => "FA_SubLedgerType"))
                     ->columns(array('SLTypeId'=>new Expression("SubLedgerTypeId"),'SLTypeName'=>new Expression("SubLedgerTypeName")));
             $select->where("SubLedgerTypeId IN (1,2,3,4,12,13,14)");//for bill
 //            $select->where("SubLedgerTypeId IN (1,3,4)"); // for advance
             $select->order(array("SLTypeId"));
             $statement = $sql->getSqlStringForSqlObject($select);
             $subLedgerTypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             $this->_view->subLedgerTypeList= $subLedgerTypeList;

            $slTypeId= $subLedgerTypeList[0]['SLTypeId'];

            $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerMaster"))
                ->columns(array('SubLedgerId','SubLedgerName'));
            $select->where("SubLedgerTypeId=$slTypeId");
            $select->order(array("SubLedgerId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $subLedgerNameList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->subLedgerNameList= $subLedgerNameList;

             if($payAdvId != 0){
                 //Edit Page load data
                 $select = $sql->select();
                 $select->from(array("A" => "FA_PayAdviceDet"))
                     ->join(array("C" => "WF_CurrencyMaster"), "C.CurrencyId=A.CurrencyId", array(), $select::JOIN_LEFT)
                     ->columns(array('PayAdviceDate'=>new Expression("FORMAT(A.PayAdviceDate, 'dd-MM-yyyy')"),'PayAdviceNo'
                     ,'Amount'=>new Expression("TotalAmount"),'ReturnAmount','BillAmount','SLTypeId'
                     ,'SLType'=>new Expression("CASE WHEN A.SLTypeId=1 THEN 'Vendor' ELSE CASE WHEN A.SLTypeId=4 THEN 'Employee' ELSE CASE WHEN A.SLTypeId=12 THEN 'Wages' ELSE 'FI' END END END")
                     ,'Approve','BillType','AganistType'=>new Expression("CASE WHEN A.BillType='B' THEN 'Bill' ELSE 'Advance' END"),'Remarks','CurrencyId'
                     ,'CurrencyName'=>new Expression("CASE WHEN A.CurrencyId=0 THEN 'Rupees' ELSE C.CurrencyName END")));
                 $select->where("A.PayAdviceId = $payAdvId");
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $payBillList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->payBillList=$payBillList;
                 if(count($payBillList) > 0) {
                     $editbillType = $payBillList[0]['BillType'];
                     $editSLTypeId = $payBillList[0]['SLTypeId'];
                 }else{
                     $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "payment-advice-register"));
                 }

                 if ($editbillType == "O") {
                     $editbillType = "A,B";
                 }

                 $selectBill = $sql->select();
                 $selectBill->from(array("A" => "FA_BillRegister"))
                     ->columns(array('BillRegisterId', 'BillNo', "BillDate" => new Expression("FORMAT(A.BillDate, 'dd-MM-yyyy')")
                     , 'SubLedgerName' => new Expression("SLM.SubLedgerName")
                     , 'RefTypeId', 'AccountId', 'SubLedgerId', 'RefNo', "RefDate" => new Expression("FORMAT(A.RefDate, 'dd-MM-yyyy')")
                     , 'CreditDays', 'DueDate' => new Expression("FORMAT(DATEADD(DAY,CreditDays,RefDate),'dd-MM-yyyy')")
                     , 'BillAmount', 'Advance', 'O/B Adv' => new Expression("(SELECT SUM(Amount) FROM FA_OBBillRefDet WHERE BillRegId=A.BillRegisterId)")
                     , 'DebitAmount', 'RefAmount' => new Expression("(A.BillAmount-A.Advance-A.DebitAmount)")
                     , 'PayAdvAmount', 'ApproveAmount' => new Expression("(A.PayAdvAmount-ISNULL(B.ApproveAmount,0))")
                     , 'CurrentAmount' => new Expression("ISNULL(B.ApproveAmount,0)"), 'PaidAmount', 'FromOB', 'CostCentreId'
                     , 'CostCentreName' => new Expression("C.CostCentreName"), 'TypeName' => new Expression("I.TypeName"), 'BillForexRate' => new Expression("CAST(0 As decimal(18,6))")
                     , 'SLName' => new Expression("''"), 'Sel' => new Expression("CAST((CASE WHEN ISNULL(B.BillRegisterId,0)>0 THEN 1 ELSE 0 END) AS Bit)")
                     , 'HCurrent' => new Expression("ISNULL(B.ApproveAmount,0)"), 'HAdvance' => new Expression("Advance")
                     , 'BillType' => new Expression("case when A.BillType='B' THEN 'Bill' else 'Advance' end")))
                     ->join(array("I" => "FA_InvoiceType"), "I.TypeId=A.RefTypeId", array(), $selectBill::JOIN_LEFT)
                     ->join(array("B" => "FA_PayAdviceTrans"), "A.BillRegisterId=B.BillRegisterId", array(), $selectBill::JOIN_LEFT)
                     ->join(array("C" => "WF_CostCentre"), "A.CostCentreId=C.CostCentreId", array(), $selectBill::JOIN_LEFT)
                     ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=A.SubLedgerId", array(), $selectBill::JOIN_INNER)
                     ->where("((A.Approve='Y' AND A.TransType='P' AND A.BillType IN ('$editbillType') AND
                     A.SubLedgerId IN (select SubLedgerId from FA_SubLedgerMaster WHere SubLedgerTypeId=$editSLTypeId) AND
                     (BillAmount-Advance-DebitAmount-WriteOff)<>A.PayAdvAmount AND A.CompanyId=$companyId
                     AND A.BillDate<='$billDate' AND ISNULL(B.PayAdviceId,0)=0) OR B.PayAdviceId=$payAdvId)");
                 $selectBill->order(array("SLM.SubLedgerName"));
                 $statement = $sql->getSqlStringForSqlObject($selectBill);
                 $billRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->billRegister = $billRegister;


                 $subLedgerId = '';
                 foreach ($billRegister as $bills) {
                     $subLedgerId .= $bills['SubLedgerId'] . ',';
                 }
                 $subLedgerId = trim($subLedgerId, ',');
//                 $subLedgerId= $this->bsf->isNullCheck($postData['subLedgerId'], 'string');

                 $subQuery = $sql->select();
                 $subQuery->from(array("a" => "FA_OBBillRefDet"))
                     ->columns(array('AdvRegId'));
                 $subQuery->where("PayAdviceId=$payAdvId");

                 $select = $sql->select();
                 $select->from(array("BR" => "FA_BillRegister"))
                     ->join(array("CC" => "WF_CostCentre"), "BR.CostCentreId=CC.CostCentreId", array(), $select::JOIN_LEFT)
                     ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=BR.SubLedgerId", array(), $select::JOIN_INNER)
                     ->columns(array('BillRegisterId', 'SubLedgerId', 'BillType', 'BillNo', 'BillDate' => new Expression("FORMAT(BR.BillDate, 'dd-MM-yyyy')"), 'RefNo'
                     , 'SubLedgerName' => new Expression("SLM.SubLedgerName")
                     , 'RefDate' => new Expression("FORMAT(BR.RefDate, 'dd-MM-yyyy')"), 'RefTypeId', 'RefType'
                     , 'CostCentreName' => new Expression("CC.CostCentreName"), 'BillAmount', 'Adjusted' => new Expression("BR.PayAdvAmount")
                     , 'Balance' => new Expression("BR.BillAmount-BR.PayAdvAmount"), 'Current' => new Expression("CAST (0 As decimal(18,3))")
                     , 'CostCentreId', 'Sel' => new Expression("Cast(0 AS bit)"), 'HAdjust' => new Expression("BR.PayAdvAmount"), 'HCurrent' => new Expression("CAST (0 As decimal(18,3))")
                     ));
                 $select->where("PaidAmount<>BillAmount AND BillType='A' AND FromOB=1 AND BR.SubLedgerId in ($subLedgerId) AND BR.BillDate<='$billDate'");
                 $select->where->expression("BR.BillRegisterId NOT IN ?", array($subQuery));
                 if ($payAdvId != 0) {
                     $selectGroupEdit = $sql->select();
                     $selectGroupEdit->from(array("BR" => "FA_BillRegister"))
                         ->join(array("CC" => "WF_CostCentre"), "BR.CostCentreId=CC.CostCentreId", array(), $selectGroupEdit::JOIN_LEFT)
                         ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=BR.SubLedgerId", array(), $selectGroupEdit::JOIN_INNER)
                         ->columns(array('BillRegisterId', 'SubLedgerId', 'BillType', 'BillNo', 'BillDate' => new Expression("FORMAT(BR.BillDate, 'dd-MM-yyyy')"), 'RefNo'
                         , 'SubLedgerName' => new Expression("SLM.SubLedgerName")
                         , 'RefDate' => new Expression("FORMAT(BR.RefDate, 'dd-MM-yyyy')"), 'RefTypeId', 'RefType'
                         , 'CostCentreName' => new Expression("CC.CostCentreName"), 'BillAmount', 'Adjusted' => new Expression("BR.PayAdvAmount")
                         , 'Balance' => new Expression("BR.BillAmount-BR.PayAdvAmount"), 'Current' => new Expression("CAST (0 As decimal(18,3))")
                         , 'CostCentreId', 'Sel' => new Expression("CAST(ISNULL((SELECT TOP 1 BillRegId FROM FA_OBBillRefDet A WHERE PayAdviceId=$payAdvId),0) AS bit)")
                         , 'HAdjust' => new Expression("BR.PayAdvAmount"), 'HCurrent' => new Expression("CAST (0 As decimal(18,3))")
                         ));
                     $selectGroupEdit->where->expression("BR.BillRegisterId IN ?", array($subQuery));
                     $select->combine($selectGroupEdit, 'Union ALL');
                 }
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $advanceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->advanceList = $advanceList;

                 $select = $sql->select();
                 $select->from(array("A" => "FA_OBBillRefDet"))
                     ->join(array("B" => "FA_BillRegister"), "A.AdvRegId=B.BillRegisterId", array(), $select::JOIN_INNER)
                     ->columns(array('PayAdviceId', 'AdvRegId', 'RefTypeId' => new Expression("B.RefTypeId")
                     , 'Amount' => new Expression("sum(A.Amount)")
                     , 'HAmount' => new Expression("sum(A.Amount)"), 'CostCentreId' => new Expression("B.CostCentreId")
                     ));
                 $select->where("PayAdviceId<>0 AND PayAdviceId=$payAdvId");
                 $select->group(array("A.PayAdviceId", 'A.AdvRegId', 'B.RefTypeId', 'B.CostCentreId'));
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $advAdjList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->advAdjList = $advAdjList;

                 $subQuery = $sql->select();
                 $subQuery->from(array('RA' => 'FA_ReturnAdjustment'))
                     ->columns(array('AdjustAmount' => new Expression("SUM(AdjustAmount)")));
                 $subQuery->where("RA.AdviceId=$payAdvId AND RA.ReturnRegId=RR.ReturnRegId");

                 $select = $sql->select();
                 $select->from(array("RR" => "FA_ReturnRegister"))
                     ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select::JOIN_INNER)
                     ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=RR.SubLedgerId", array(), $select::JOIN_INNER)
                     ->columns(array('ReturnRegId', 'ReturnDate' => new Expression("FORMAT(RR.ReturnDate, 'dd-MM-yyyy')"), 'ReturnNo'
                     , 'CostCentreName' => new Expression("CC.CostCentreName"), 'ReturnAmount', 'Adjusted' => new Expression("RR.AdjustAmount")
                     , 'SubLedgerName' => new Expression("SLM.SubLedgerName"), 'SubLedgerId'
                     , 'Balance' => new Expression("RR.ReturnAmount-RR.AdjustAmount"), 'CostCentreId', 'HAdjust' => new Expression("RR.AdjustAmount")
                     , 'Current' => new Expression("ISNULL((" . $subQuery->getSqlString() . "),0)")
                     , 'HCurrent' => new Expression("ISNULL((" . $subQuery->getSqlString() . "),0)")
                     ));
                 $select->where("RR.SubLedgerId in ($subLedgerId) AND ReturnDate<='$billDate'");
                 if ($payAdvId == 0) {
                     $select->where("ReturnAmount>AdjustAmount");
                 } else {
                     $select->where("((ReturnAmount>AdjustAmount) OR ReturnRegId IN (SELECT ReturnRegId FROM FA_ReturnAdjustment WHERE AdviceId=$payAdvId))");
                 }
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $returnList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->returnList = $returnList;

             } else {
                 $subQuery = $sql->select();
                 $subQuery->from(array("a" => "FA_AccountMaster"))
                     ->columns(array('AccountId'));
                 $subQuery->where("TypeId IN (10,37)");

                 $selectBill = $sql->select();
                 $selectBill->from(array("A" => "FA_BillRegister"))
                     ->columns(array(
                         'SubLedgerId' => new Expression("SLM.SubLedgerId"),'SubLedgerName' => new Expression("SLM.SubLedgerName")
                     , 'BillRegisterId', "BillDate" => new Expression("FORMAT(A.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'CreditDays'
                     , 'DueDate' => new Expression("FORMAT(DATEADD(DAY,CreditDays,RefDate),'dd-MM-yyyy')")
                     , 'RefTypeId', 'AccountId', 'RefNo', 'RefDate' => new Expression("A.RefDate"), 'BillAmount'
                     , 'Advance' => new Expression("A.Advance+WriteOff")
                     , 'O/B Adv' => new Expression("1-1"), 'DebitAmount'
                     , 'RefAmount' => new Expression("(BillAmount-Advance-DebitAmount-WriteOff)")
                     , 'PayAdvAmount', 'ApproveAmount'
                     , 'CurrentAmount' => new Expression("CAST (1-1 AS Decimal(18,3))")
                     , 'PaidAmount', 'FromOB', 'CostCentreId', 'SLName' => new Expression("SLM.SubLedgerName")
                     , 'CostCentreName' => new Expression("B.CostCentreName"), 'TypeName' => new Expression("C.TypeName")
                     , 'BillForexRate' => new Expression("CAST(1-1 As decimal(18,6))"), 'Sel' => new Expression("CAST(1-1 As Bit)")
                     , 'HAdvance' => new Expression("CAST(1-1 AS Decimal(18,3))"), 'HCurrent' => new Expression("CAST(1-1 AS Decimal(18,3))")
                     , 'BillType' => new Expression("case when A.BillType='B' THEN 'Bill' else 'Advance' end")))
                     ->join(array("B" => "WF_CostCentre"), "A.CostCentreId=B.CostCentreId", array(), $selectBill::JOIN_LEFT)
                     ->join(array("c" => "FA_InvoiceType"), "C.TypeId=A.RefTypeId", array(), $selectBill::JOIN_LEFT)
                     ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=A.SubLedgerId", array(), $selectBill::JOIN_INNER);
                 $selectBill->where->expression("((BillAmount-Advance-DebitAmount-WriteOff)>PayAdvAmount) AND A.CompanyId=$companyId AND
                 A.BillDate<='$billDate' AND A.BillType IN ('B','A') AND SLM.SubLedgerTypeId=$SubLedgerTypeId AND A.CurrencyId=0 AND A.Approve='Y' AND A.TransType='P' AND
                  (A.CostCentreId<>0) AND A.AccountId NOT IN ?", array($subQuery));
                 $selectBill->order(array("SLM.SubLedgerName"))
                     ->limit(10)
                     ->offset(0);
                 $statement = $sql->getSqlStringForSqlObject($selectBill);
                 $billRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 $this->_view->billRegister = $billRegister;

                 $subQuery = $sql->select();
                 $subQuery->from(array("a" => "FA_AccountMaster"))
                     ->columns(array('AccountId'));
                 $subQuery->where("TypeId IN (10,37)");

                 $select= $sql->select();
                 $select->from(array("A" => "FA_BillRegister"))
                     ->columns(array('TotalFeeds'=>new Expression("count(*)")))
                     ->join(array("B" => "WF_CostCentre"), "A.CostCentreId=B.CostCentreId", array(), $select::JOIN_LEFT)
                     ->join(array("c" => "FA_InvoiceType"), "C.TypeId=A.RefTypeId", array(), $select::JOIN_LEFT)
                     ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=A.SubLedgerId", array(), $select::JOIN_INNER);
                 $select->where->expression("((BillAmount-Advance-DebitAmount-WriteOff)>PayAdvAmount) AND A.CompanyId=$companyId AND
                 A.BillDate<='$billDate' AND A.BillType IN ('B','A') AND SLM.SubLedgerTypeId=$SubLedgerTypeId AND A.CurrencyId=0 AND A.Approve='Y' AND A.TransType='P' AND
                  (A.CostCentreId<>0) AND A.AccountId NOT IN ?", array($subQuery));
                 $statement = $sql->getSqlStringForSqlObject($select);
                 $totalFeeds= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                 $this->_view->totalFeeds = $totalFeeds;
             }

             /*for edit Mode Trans Load

             SELECT PayAdviceDate,PayAdviceNo
 , TotalAmount Amount,ReturnAmount,BillAmount, A.SLTypeId,
 CASE WHEN A.SLTypeId=1 THEN 'Vendor' ELSE CASE WHEN A.SLTypeId=4 THEN 'Employee' ELSE CASE WHEN A.SLTypeId=12 THEN 'Wages' ELSE 'FI' END END END SLType ,  A.Approve,A.BillType,CASE WHEN A.BillType='B' THEN 'Bill' ELSE 'Advance' END AganistType, A.Remarks, A.CurrencyId,CASE WHEN A.CurrencyId=0 THEN 'Rupees' ELSE C.CurrencyName END CurrencyName
 FROM FA_PayAdviceDet A
     LEFT JOIN WF_CurrencyMaster C ON C.CurrencyId=A.CurrencyId
 WHERE A.PayAdviceId=8

         SELECT A.BillRegisterId,A.BillDate,A.BillNo,A.RefTypeId,A.AccountId,A.SubLedgerId
         ,A.RefNo,A.RefDate,CreditDays,DueDate=DATEADD(DAY,CreditDays,RefDate),A.BillAmount,
         A.Advance,[O/B Adv]=(SELECT SUM(Amount) FROM FA_OBBillRefDet
         WHERE BillRegId=A.BillRegisterId), A.DebitAmount,(A.BillAmount-A.Advance-A.DebitAmount) RefAmount
         , A.PayAdvAmount, (A.PayAdvAmount-ISNULL(B.ApproveAmount,0)) ApproveAmount
         ,ISNULL(B.ApproveAmount,0) CurrentAmount,A.PaidAmount,A.FromOB,A.CostCentreId
         ,C.CostCentreName, I.TypeName, CAST(0 As decimal(18,6)) BillForexRate
         ,'' SLName, CAST((CASE WHEN ISNULL(B.BillRegisterId,0)>0 THEN 1 ELSE 0 END) AS Bit) Sel
         ,HCurrent=ISNULL(B.ApproveAmount,0), HAdvance=Advance FROM FA_BillRegister A
         LEFT JOIN FA_InvoiceType I ON I.TypeId=A.RefTypeId
         LEFT JOIN FA_PayAdviceTrans B ON A.BillRegisterId=B.BillRegisterId
         LEFT JOIN WF_CostCentre C ON A.CostCentreId=C.CostCentreId
         WHERE ((A.Approve='Y' AND A.TransType='P' AND A.BillType='O' AND A.SubLedgerId=1 AND
         (BillAmount-Advance-DebitAmount-WriteOff)<>A.PayAdvAmount AND A.CompanyId=1
         AND A.BillDate<='31-Mar-2018' AND ISNULL(B.PayAdviceId,0)=0) OR B.PayAdviceId=8)
             */

            /*
             *
            SELECT 0 CurrencyId, CurrencyName=(SELECT CurrencyName FROM WF_CurrencyMaster WHERE CurrencyId=2)
            UNION ALL
            SELECT CurrencyId,CurrencyName FROM WF_CurrencyMaster WHERE CurrencyId IN (SELECT CurrencyId
            FROM FA_BillRegister WHERE CurrencyId<>0)

                        SELECT A.AccountId,AM.AccountName,Balance =(OpeningBalance+(SELECT ISNULL(SUM(CASE WHEN TransType='D'
            THEN Amount ELSE Amount*(-1) END),0) Amount FROM FA_EntryTrans ET WHERE [PDC/Cancel]=0
            AND CompanyId=3 AND ET.AccountId=A.AccountId ))FROM FA_Account A
            INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId
            WHERE AccountType IN ('BA','CA') AND A.CompanyId=3 ORDER BY AccountName
             */



            $aVNo = CommonHelper::getVoucherNo(601, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->payAdvId=$payAdvId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function groupcompanytransferAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $userId = $this->auth->getIdentity()->UserId;
        $iEntryId= $this->bsf->isNullCheck($this->params()->fromRoute('entryId'),'number');

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $type= $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type){
                        case 'fromCompany':
                            $fromCompanyId= $this->bsf->isNullCheck($postData['fromCompanyId'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_CashBankDet"))
                                ->columns(array("AccountId","CashBankName","CashOrBank","CompanyId"))
                                ->where("a.CompanyId=$fromCompanyId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['fromBook']=$fromBookName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;
                        case 'toCompany':
                            $toCompanyId= $this->bsf->isNullCheck($postData['toCompanyId'], 'number');
                            $select = $sql->select();
                            $select->from(array("a" => "FA_CashBankDet"))
                                ->columns(array("AccountId","CashBankName","CashOrBank","CompanyId"))
                                ->where("a.CompanyId=$toCompanyId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['toBook']=$fromBookName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;
                        case 'fromBookDet':
                            $fromBookAccId= $this->bsf->isNullCheck($postData['fromBookAccId'], 'number');
                            $payMode= $this->bsf->isNullCheck($postData['payMode'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_CashBankDet"))
                                ->columns(array('AccountType' => new Expression("CashOrBank")))
                                ->where("a.AccountId=$fromBookAccId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['AccTypeDet']=$fromBookName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $result['chequeDet']=array();

                            if($payMode != 3) {
                                $select = $sql->select();
                                $select->from(array("a" => "FA_ChequeTrans"))
                                    ->columns(array('data' => new Expression('ChequeTransId'), 'value' => new Expression('ChequeNo')))
                                    ->where("a.AccountId=$fromBookAccId AND Used=0 AND Cancel=0");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result['chequeDet'] = $fromBookName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }

                            break;
                        default:
                            $result='';
                            break;
                    }
                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $EntryId= $this->bsf->isNullCheck($postData['iEntryId'], 'number');
                    $mode="A";
                    $fromCompany= $this->bsf->isNullCheck($postData['fromCompany'], 'number');
                    $toCompany= $this->bsf->isNullCheck($postData['toCompany'], 'number');

                    $m_iAccountId=$this->getAccountId($dbAdapter);
                    $fromSubLedgerId=$this->getSubLedgerId($fromCompany,$dbAdapter);
                    $toSubLedgerId=$this->getSubLedgerId($toCompany,$dbAdapter);
                    $m_iFCCId=$this->Get_HO_CostCentre($dbAdapter);

                    $voucherDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')));
                    $voucherNo= $this->bsf->isNullCheck($postData['voucherNo'], 'string');

                    $RelatedVNo='';
                    $RefNo='';
                    $RelatedRefNo='';
                    $BookVoucherNo='';
                    $BankCashVoucherNo='';
                    $JournalType='G';
                    $EntryType='D';//Debit
                    $sTransType="P";
                    if($EntryType=="D"){
                        $sTransType="R";
                    }
                    $fromBook= $this->bsf->isNullCheck($postData['fromBook'], 'number');
                    $toBook= $this->bsf->isNullCheck($postData['toBook'], 'number');
                    $SubLedgerTypeId=11;
                    $narration= $this->bsf->isNullCheck($postData['narration'], 'string');
                    $Amount= $this->bsf->isNullCheck($postData['amount'], 'number');
                    $otherAmount= $this->bsf->isNullCheck($postData['otherAmount'], 'number');
                    $fromPayType= $this->bsf->isNullCheck($postData['paymentMode'], 'number');
                    $ExpAccountId= $this->bsf->isNullCheck($postData['ExpAccountId'], 'number');
                    $toPayType='';
                    $ChequeTransId= $this->bsf->isNullCheck($postData['chequeTransId'], 'number');
                    $previousChequeTransId= $this->bsf->isNullCheck($postData['previousChequeTransId'], 'number');
                    $ChequeNo= $this->bsf->isNullCheck($postData['transactionNo'], 'string');
                    $ChequeDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['transactionDate'], 'date')));
                    $ChequeDescription='';
                    $Approve='N';
                    $IsAppReady=1;

                    $DebitTotAmount=$Amount+$otherAmount;
                    $CreditTotAmount=$Amount;
                    if($iEntryId!=0){
                        $mode="E";
                        //delete FA_EntryTrans
                        // FA_EntryTrans WHERE RefType='G' AND RefId in ( SELECT EntryId FROM FA_EntryMaster WHERE RefEntryId IN (SELECT RefEntryId FROM FA_EntryMaster WHERE EntryId=41)

                        $subQuery = $sql->select();
                        $subQuery->from("FA_EntryMaster")
                            ->columns(array('RefEntryId'))
                            ->where("EntryId=$EntryId");

                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_EntryMaster")
                            ->columns(array('EntryId'));
                        $subQuery1->where->expression('RefEntryId IN ?', array($subQuery));

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTrans')
                            ->where("RefType='G'");
                        $deleteTrans->where->expression('RefId IN ?', array($subQuery1));
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //First Add Row
                        $update = $sql->update();
                        $update->table('FA_EntryMaster')
                            ->set(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RelatedVNo' => $RelatedVNo
                            , 'RefNo' => $RefNo
                            , 'RelatedRefNo' => $RelatedRefNo
                            , 'BookVoucherNo' => $BookVoucherNo
                            , 'BankCashVoucherNo' => $BankCashVoucherNo
                            , 'JournalType' => $JournalType
                            , 'EntryType' => $sTransType
                            , 'BookId' => $fromBook
                            , 'AccountId' => $m_iAccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId
                            , 'SubLedgerId' => $toSubLedgerId//RSLedgeTypeId = $fromSubLedgerId
                            , 'Narration' => $narration
                            , 'Amount' => $DebitTotAmount
                            , 'CostCentreId' => $m_iFCCId
                            , 'PayType' => $fromPayType
                            , 'ChequeTransId' => $ChequeTransId
                            , 'ChequeNo' => $ChequeNo
                            , 'ChequeDate' => $ChequeDate
                            , 'ChequeDescription' => $ChequeDescription
                            , 'CompanyId' => $fromCompany
                            , 'Approve' => $Approve
                            , 'IsAppReady' => $IsAppReady
                            ))
                            ->where(array('EntryId' => $EntryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        /*Debit Insert start*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryMaster');
                        $insert->Values(array('VoucherDate' => $voucherDate
                        , 'VoucherNo' => $voucherNo
                        , 'RelatedVNo' => $RelatedVNo
                        , 'RefNo' => $RefNo
                        , 'RelatedRefNo' => $RelatedRefNo
                        , 'BookVoucherNo' => $BookVoucherNo
                        , 'BankCashVoucherNo' => $BankCashVoucherNo
                        , 'JournalType' => $JournalType
                        , 'EntryType' => $sTransType
                        , 'BookId' => $fromBook
                        , 'AccountId' => $m_iAccountId
                        , 'SubLedgerTypeId' => $SubLedgerTypeId
                        , 'SubLedgerId' => $toSubLedgerId//RSLedgeTypeId = $fromSubLedgerId
                        , 'Narration' => $narration
                        , 'Amount' => $DebitTotAmount
                        , 'CostCentreId' => $m_iFCCId
                        , 'PayType' => $fromPayType
                        , 'ChequeTransId' => $ChequeTransId
                        , 'ChequeNo' => $ChequeNo
                        , 'ChequeDate' => $ChequeDate
                        , 'ChequeDescription' => $ChequeDescription
                        , 'CompanyId' => $fromCompany
                        , 'Approve' => $Approve
                        , 'IsAppReady' => $IsAppReady
                        , 'PDC' => ''
                        , 'FYearId' => $FYearId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $EntryId= $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    //update RefEntryId
                    //UPDATE dbo.EntryMaster SET TallyUpdate=0,RefEntryId={0},RefFYearId={1},OtherCharges={2},OtherAccountId={3} WHERE EntryId={4}", iRefEntryId, BsfGlobal.g_lYearId,obj.OtherCharges,obj.OtherAccId,iEntryId)
                    $update = $sql->update();
                    $update->table('FA_EntryMaster')
                        ->set(array('RefEntryId' => $EntryId
                        , 'OtherCharges' => $otherAmount
                        , 'OtherAccountId' => $ExpAccountId
                        , 'RefFYearId' => $FYearId
                        ))
                        ->where(array('EntryId' => $EntryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //Update chequeTrans
                    //UPDATE [{0}].dbo.ChequeTrans SET Used=0,Cancel=0,CancelDate=NULL,CancelRemarks='', PDC=0,PDCDate=NULL, PDCClear=0, PDCClearDate=NULL, IssueDate=NULL,ReconDate=NULL, FYearId=0 WHERE ChequeTransId={1}", BsfGlobal.g_sFaDBName, obj.OChequeId);
                    $update = $sql->update();
                    $update->table('FA_ChequeTrans')
                        ->set(array('Used' => 0
                        , 'Cancel' => 0
                        , 'CancelRemarks' => ''
                        , 'PDCDate' => null
                        , 'PDCClear' => 0
                        , 'IssueDate' => null
                        , 'FYearId' => 0
                        ))
                        ->where(array('ChequeTransId' => $previousChequeTransId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*Debit Insert start*/
                    $TransType='D';
                    $RelatedSLId=11;
                    $Remarks='';
                    $IRemarks='';
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $voucherDate
                    , 'VoucherNo' => $voucherNo
                    , 'RefId' => $EntryId
                    , 'TransType' => $TransType
                    , 'RefType' => $JournalType
                    , 'AccountId' => $m_iAccountId
                    , 'RelatedAccountId' => $fromBook
                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                    , 'SubLedgerId' => $toSubLedgerId
                    , 'RelatedSLTypeId' => 0
                    , 'RelatedSLId' => 0
                    , 'CostCentreId' => $m_iFCCId
                    , 'Amount' => $Amount
                    , 'Remarks' => $Remarks
                    , 'IRemarks' => $IRemarks
                    , 'CompanyId' => $fromCompany
                    , 'RefNo'=> ''
                    , 'Approve' => $Approve));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*Debit Insert end*/

                    /*Credit Insert Start*/
                    if($TransType=="D"){
                        $TransType='C';
                    }
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $voucherDate
                    , 'VoucherNo' => $voucherNo
                    , 'RefId' => $EntryId
                    , 'TransType' => $TransType
                    , 'RefType' => $JournalType
                    , 'AccountId' => $fromBook
                    , 'RelatedAccountId' => $m_iAccountId
                    , 'SubLedgerTypeId' => 0
                    , 'SubLedgerId' => 0
                    , 'RelatedSLTypeId' => $SubLedgerTypeId
                    , 'RelatedSLId' => $toSubLedgerId
                    , 'CostCentreId' => $m_iFCCId
                    , 'Amount' => $Amount
                    , 'Remarks' => $Remarks
                    , 'IRemarks' => $IRemarks
                    , 'CompanyId' => $fromCompany
                    , 'RefNo'=> ''
                    , 'Approve' => $Approve));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*Credit Insert end*/

                    if($otherAmount!=0){
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('VoucherDate' => $voucherDate
                        , 'VoucherNo' => $voucherNo
                        , 'RefId' => $EntryId
                        , 'TransType' => 'C'
                        , 'RefType' => $JournalType
                        , 'AccountId' => $fromBook
                        , 'RelatedAccountId' => $ExpAccountId
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $m_iFCCId
                        , 'Amount' => $otherAmount
                        , 'Remarks' => $Remarks
                        , 'IRemarks' => $IRemarks
                        , 'CompanyId' => $fromCompany
                        , 'RefNo'=> ''
                        , 'Approve' => $Approve));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('VoucherDate' => $voucherDate
                        , 'VoucherNo' => $voucherNo
                        , 'RefId' => $EntryId
                        , 'TransType' => 'D'
                        , 'RefType' => $JournalType
                        , 'AccountId' => $ExpAccountId
                        , 'RelatedAccountId' => $fromBook
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $m_iFCCId
                        , 'Amount' => $otherAmount
                        , 'Remarks' => $Remarks
                        , 'IRemarks' => $IRemarks
                        , 'CompanyId' => $fromCompany
                        , 'RefNo'=> ''
                        , 'Approve' => $Approve));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $EntryType='C';//Debit
                    $sTransType="P";
                    if($EntryType=="D"){
                        $sTransType="R";
                    }
                    //Second Add Row
                    if($mode=="E"){
                        //SELECT EntryId FROM FA_EntryMaster WHERE RefEntryId IN (SELECT RefEntryId FROM FA_EntryMaster WHERE EntryId=40) and EntryId<>40 and RefEntryId<>0
                        $subQuery = $sql->select();
                        $subQuery->from("FA_EntryMaster")
                            ->columns(array('RefEntryId'))
                            ->where("EntryId=$EntryId");

                        $select = $sql->select();
                        $select->from('FA_EntryMaster')
                            ->columns(array('EntryId'))
                            ->where("EntryId<>$EntryId and RefEntryId<>0");
                        $select->where->expression('RefEntryId IN ?', array($subQuery));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $refEntryList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $iRefEntryId = 0;
                        if ($refEntryList) {
                            $iRefEntryId = $refEntryList['EntryId'];
                        }

                        if($iRefEntryId!=0){
                            $update = $sql->update();
                            $update->table('FA_EntryMaster')
                                ->set(array('VoucherDate' => $voucherDate
                                , 'VoucherNo' => $voucherNo
                                , 'RelatedVNo' => $RelatedVNo
                                , 'RefNo' => $RefNo
                                , 'RelatedRefNo' => $RelatedRefNo
                                , 'BookVoucherNo' => $BookVoucherNo
                                , 'BankCashVoucherNo' => $BankCashVoucherNo
                                , 'JournalType' => $JournalType
                                , 'EntryType' => $sTransType
                                , 'BookId' => $toBook
                                , 'AccountId' => $m_iAccountId
                                , 'SubLedgerTypeId' => $SubLedgerTypeId
                                , 'SubLedgerId' => $fromSubLedgerId//RSLedgeTypeId = $fromSubLedgerId
                                , 'Narration' => $narration
                                , 'Amount' => $Amount
                                , 'CostCentreId' => $m_iFCCId
                                , 'PayType' => $fromPayType
                                , 'ChequeTransId' => 0
                                , 'ChequeNo' => ''
                                , 'ChequeDate' => $ChequeDate
                                , 'ChequeDescription' => $ChequeDescription
                                , 'CompanyId' => $toCompany
                                , 'Approve' => $Approve
                                , 'IsAppReady' => $IsAppReady
                                , 'PDC' => ''))
                                ->where(array('EntryId' => $iRefEntryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    else{
                        /*Debit Insert start*/
                        $insert = $sql->insert();
                        $insert->into('FA_EntryMaster');
                        $insert->Values(array('VoucherDate' => $voucherDate
                        , 'VoucherNo' => $voucherNo
                        , 'RelatedVNo' => $RelatedVNo
                        , 'RefNo' => $RefNo
                        , 'RelatedRefNo' => $RelatedRefNo
                        , 'BookVoucherNo' => $BookVoucherNo
                        , 'BankCashVoucherNo' => $BankCashVoucherNo
                        , 'JournalType' => $JournalType
                        , 'EntryType' => $sTransType
                        , 'BookId' => $toBook
                        , 'AccountId' => $m_iAccountId
                        , 'SubLedgerTypeId' => $SubLedgerTypeId
                        , 'SubLedgerId' => $fromSubLedgerId//RSLedgeTypeId = $fromSubLedgerId
                        , 'Narration' => $narration
                        , 'Amount' => $Amount
                        , 'CostCentreId' => $m_iFCCId
                        , 'PayType' => $fromPayType
                        , 'ChequeTransId' => 0
                        , 'ChequeNo' => ''
                        , 'ChequeDate' => $ChequeDate
                        , 'ChequeDescription' => $ChequeDescription
                        , 'CompanyId' => $toCompany
                        , 'Approve' => $Approve
                        , 'IsAppReady' => $IsAppReady
                        , 'PDC' => ''
                        , 'FYearId' => $FYearId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iRefEntryId= $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $update = $sql->update();
                    $update->table('FA_EntryMaster')
                        ->set(array('RefEntryId' => $EntryId
                        , 'OtherCharges' => $otherAmount
                        , 'OtherAccountId' => $ExpAccountId
                        , 'RefFYearId' => $FYearId
                        ))
                        ->where(array('EntryId' => $iRefEntryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $TransType='C';
                    /*Debit Insert start*/
                    $RelatedSLId=11;
                    $Remarks='';
                    $IRemarks='';
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $voucherDate
                    , 'VoucherNo' => $voucherNo
                    , 'RefId' => $iRefEntryId
                    , 'TransType' => $TransType
                    , 'RefType' => $JournalType
                    , 'AccountId' => $m_iAccountId
                    , 'RelatedAccountId' => $toBook
                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                    , 'SubLedgerId' => $fromSubLedgerId
                    , 'RelatedSLTypeId' => 0
                    , 'RelatedSLId' => 0
                    , 'CostCentreId' => $m_iFCCId
                    , 'Amount' => $Amount
                    , 'Remarks' => $Remarks
                    , 'IRemarks' => $IRemarks
                    , 'CompanyId' => $toCompany
                    , 'RefNo'=> ''
                    , 'Approve' => $Approve));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*Debit Insert end*/

                    /*Credit Insert Start*/
                    if($TransType=="C"){
                        $TransType='D';
                    }
                    $insert = $sql->insert();
                    $insert->into('FA_EntryTrans');
                    $insert->Values(array('VoucherDate' => $voucherDate
                    , 'VoucherNo' => $voucherNo
                    , 'RefId' => $iRefEntryId
                    , 'TransType' => $TransType
                    , 'RefType' => $JournalType
                    , 'AccountId' => $toBook
                    , 'RelatedAccountId' => $m_iAccountId
                    , 'SubLedgerTypeId' => 0
                    , 'SubLedgerId' => 0
                    , 'RelatedSLTypeId' => $SubLedgerTypeId
                    , 'RelatedSLId' => $fromSubLedgerId
                    , 'CostCentreId' => $m_iFCCId
                    , 'Amount' => $Amount
                    , 'Remarks' => $Remarks
                    , 'IRemarks' => $IRemarks
                    , 'CompanyId' => $toCompany
                    , 'RefNo'=> ''
                    , 'Approve' => $Approve));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*Credit Insert end*/

                    //update chequeTrans
                    if($ChequeTransId!=0 && $fromPayType != 3){
                        if($voucherDate>=$ChequeDate){
                            $update = $sql->update();
                            $update->table('FA_ChequeTrans')
                                ->set(array('Amount' => $Amount
                                , 'Used' => 1
                                , 'IssueDate' => $voucherDate
                                , 'FYearId' => $FYearId
                                ))
                                ->where(array('ChequeTransId' => $ChequeTransId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $update = $sql->update();
                            $update->table('FA_ChequeTrans')
                                ->set(array('Amount' => $Amount
                                , 'Used' => 1
                                , 'PDC' => 1
                                , 'PDCDate' => $ChequeDate
                                , 'IssueDate' => $voucherDate
                                , 'FYearId' => $FYearId
                                ))
                                ->where(array('ChequeTransId' => $ChequeTransId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Group-Company-Transfer-Edit','E','FA-Group Company Transfer Details',$EntryId,0, $companyId, 'FA','',$userId, 0 ,0);//pass refno after FA as voucher $adviceNo
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Group-Company-Transfer-Add','N','FA-Group Company Transfer Details',$EntryId,0, $companyId, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "journalbook"));
                    //$this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $subQuery = $sql->select();
            $subQuery->from("FA_FiscalYearTrans")
                ->columns(array('CompanyId'))
                ->where("FYearId=$FYearId");

            $select = $sql->select();
            $select->from(array("a" => "WF_CompanyMaster"))
                ->columns(array("CompanyId","CompanyName"));
            $select->where->expression('a.CompanyId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $fromCompanyList=$companyList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->fromCompanyList=$fromCompanyList;

            /*$subQuery = $sql->select();
            $subQuery->from("FA_FiscalYearTrans")
                ->columns(array('CompanyId'))
                ->where("FYearId=$FYearId");*/

            /*$select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("AccountId","AccountName"=> new Expression("CashBankName"),"AccountType"=> new Expression("CashOrBank"),"CompanyId"));
            $select->where->expression('a.CompanyId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $bookList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                    ->columns(array("AccountId","AccountName"))
                    ->where("TypeId=7 AND LastLevel='Y'")
                    ->order(array("AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $accountList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->accountList=$accountList;

            $allowEdit=1;
            if($iEntryId !=0){
                $subQuery = $sql->select();
                $subQuery->from(array("a" => "FA_EntryMaster"))
                    ->columns(array("RefEntryId"))
                    ->where("EntryId=$iEntryId");

                $select = $sql->select();
                $select->from(array("a" => "FA_EntryMaster"))
                    ->join(array("b" => "FA_AccountMaster"), "a.AccountId=b.AccountId", array('AccountName'), $select::JOIN_LEFT)
                    ->join(array("c" => "FA_SubLedgerType"), "a.SubLedgerTypeId=c.SubLedgerTypeId", array('SubLedgerTypeName'), $select::JOIN_LEFT)
                    ->join(array("d" => "FA_SubLedgerMaster"), "a.SubLedgerId=d.SubLedgerId", array('SubLedgerName'), $select::JOIN_LEFT)
                    ->columns(array("EntryId",'RefEntryId',"VoucherDate" => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"),'VoucherNo','EntryType','JournalType','BookId','AccountId'
                    ,'SubLedgerTypeId','SubLedgerId','PayType','ChequeTransId','ChequeNo','ChequeDate'=> new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'Narration'
                    ,'Amount','OtherCharges','OtherAccountId','CompanyId','Approve'))
                    ->where->expression("RefEntryId IN ?",array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $groupList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $groupData=array();

                $groupData['VoucherDate']=date('d-m-Y');
                $groupData['VoucherNo']='';
                $groupData['EntryType']='';
                $groupData['JournalType']='';
                $groupData['fromBookId']=0;
                $groupData['AccountId']=0;
                $groupData['SubLedgerTypeId']=0;
                $groupData['SubLedgerId']=0;
                $groupData['PayType']='';
                $groupData['ChequeTransId']=0;
                $groupData['ChequeNo']='';
                $groupData['ChequeDate']=date('d-m-Y');
                $groupData['Narration']='';
                $groupData['Amount']=0;
                $groupData['OtherCharges']=0;
                $groupData['OtherAccountId']=0;
                $groupData['fromCompanyId']=0;
                $groupData['toBookId']=0;
                $groupData['toCompanyId']=0;

                foreach($groupList as $list){
                    if($list['EntryId'] == $list['RefEntryId']){
                        $groupData['VoucherDate']=$list['VoucherDate'];
                        $groupData['VoucherNo']=$list['VoucherNo'];
                        $groupData['EntryType']=$list['EntryType'];
                        $groupData['JournalType']=$list['JournalType'];
                        $groupData['fromBookId']=$list['BookId'];
                        $groupData['AccountId']=$list['AccountId'];
                        $groupData['SubLedgerTypeId']=$list['SubLedgerTypeId'];
                        $groupData['SubLedgerId']=$list['SubLedgerId'];
                        $groupData['PayType']=$list['PayType'];
                        $groupData['ChequeTransId']=$list['ChequeTransId'];
                        $groupData['ChequeNo']=$list['ChequeNo'];
                        $groupData['ChequeDate']=$list['ChequeDate'];
                        $groupData['Narration']=$list['Narration'];
                        $groupData['Amount']=$list['Amount']-$list['OtherCharges'];
                        $groupData['OtherCharges']=$list['OtherCharges'];
                        $groupData['OtherAccountId']=$list['OtherAccountId'];
                        $groupData['fromCompanyId']=$list['CompanyId'];
                    } else{
                        $groupData['toBookId']=$list['BookId'];
                        $groupData['toCompanyId']=$list['CompanyId'];
                    }

                    if($list['Approve']=="Y" && $allowEdit==1){
                        $allowEdit=0;
                    }
                }
                $grpFromCompany=$groupData['fromCompanyId'];
                $grpFromBook=$groupData['fromBookId'];
                $grpToBook=$groupData['toBookId'];
                $grpPayType=$groupData['PayType'];
                $grpChequeTransId=$groupData['ChequeTransId'];

                $select = $sql->select();
                $select->from(array("a" => "FA_CashBankDet"))
                    ->columns(array("AccountId","CashBankName","CashOrBank","CompanyId"))
                    ->where("a.CompanyId=$grpFromCompany");
                $statement = $sql->getSqlStringForSqlObject($select);
                $grpFromBookList=$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subQuery = $sql->select();
                $subQuery->from("FA_FiscalYearTrans")
                    ->columns(array('CompanyId'))
                    ->where("FYearId=$FYearId");

                $select = $sql->select();
                $select->from(array("a" => "WF_CompanyMaster"))
                    ->columns(array("CompanyId","CompanyName"));
                $select->where->expression("a.CompanyId NOT IN ($grpFromCompany) and a.CompanyId IN ?", array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $toCompanyList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->toCompanyList=$toCompanyList;

                $grpToCompany= $groupData['toCompanyId'];
                $select = $sql->select();
                $select->from(array("a" => "FA_CashBankDet"))
                    ->columns(array("AccountId","CashBankName","CashOrBank","CompanyId"))
                    ->where("a.CompanyId=$grpToCompany");
                $statement = $sql->getSqlStringForSqlObject($select);
                $grpToBookList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "FA_CashBankDet"))
                    ->columns(array('AccountType' => new Expression("CashOrBank")))
                    ->where("a.AccountId=$grpFromBook");
                $statement = $sql->getSqlStringForSqlObject($select);
                $grpAccType=$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $grpChequeDet=array();

                if($grpPayType != 3) {
                    $select = $sql->select();
                    $select->from(array("a" => "FA_ChequeTrans"))
                        ->columns(array('data' => new Expression('ChequeTransId'), 'value' => new Expression('ChequeNo')))
                        ->where("(a.AccountId=$grpFromBook AND Used=0 AND Cancel=0) or a.ChequeTransId=$grpChequeTransId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $grpChequeDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

//                echo '<pre>';print_r($groupData);die;
                $this->_view->groupData=$groupData;
                $this->_view->grpFromBookList=$grpFromBookList;
                $this->_view->grpToBookList=$grpToBookList;
                $this->_view->grpAccType=$grpAccType;
                $this->_view->grpChequeDet=$grpChequeDet;
            }
            $this->_view->allowEdit=$allowEdit;
            $this->_view->iEntryId=$iEntryId;

            $aVNo = CommonHelper::getVoucherNo(610, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->aVNo= $aVNo;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function getAccountId($dbAdapter){

        $sql = new Sql( $dbAdapter );

        $accId=0;
        $select = $sql->select();
        $select->from(array("a" => "FA_AccountMaster"))
            ->columns(array("AccountId"))
            ->where("LastLevel='Y' AND TypeId=25");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accList) != 0)
            $accId=$accList[0]['AccountId'];
        return $accId;
    }
    public function getSubLedgerId($companyId,$dbAdapter){
        $sql = new Sql( $dbAdapter );

        $subLedgerId=0;
        $select = $sql->select();
        $select->from(array("a" => "FA_SubLedgerMaster"))
            ->columns(array("SubLedgerId"))
            ->where("SubLedgerTypeId=11 AND RefId=$companyId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $subLedgerList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($subLedgerList) != 0)
            $subLedgerId=$subLedgerList[0]['SubLedgerId'];
        return $subLedgerId;
    }
    public function Get_HO_CostCentre($dbAdapter){

        $sql = new Sql( $dbAdapter );

        $costCenterId=0;
        $select = $sql->select();
        $select->from(array("a" => "WF_CostCentre"))
            ->columns(array("CostCentreId"))
            ->where("HO=1");
        $statement = $sql->getSqlStringForSqlObject($select);
        $costCenterList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($costCenterList) != 0)
            $costCenterId=$costCenterList[0]['CostCentreId'];
        return $costCenterId;
    }
    public function multicompanytransferAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $CompanyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $CompanyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $CompanyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $entryId= $this->bsf->isNullCheck($this->params()->fromRoute('mtransferId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $fromBookId = $this->bsf->isNullCheck($postData['fromName'], 'number');
                    $type= $this->bsf->isNullCheck($postData['mode'], 'string');

                    if($type == 'pickList'){
                        $select = $sql->select();
                        $select->from(array('a' =>'FA_CashBankDet'))
                            ->columns(array('AccountId','CompanyId','AccountType' => new Expression("CashOrBank")))
                            ->where(array("AccountId = $fromBookId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultProjCamp['accType']=$resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $accId = $resultLeads['AccountId'];
                        $compId = $resultLeads['CompanyId'];
                        if($accId != ''){
                            $accId = $accId;
                        }else{
                            $accId = 0;
                        }
                        if($compId != ''){
                            $compId = $compId;
                        }else{
                            $compId = 0;
                        }
                        $subQuery = $sql->select();
                        $subQuery->from("FA_FiscalYearTrans")
                            ->columns(array('CompanyId'))
                            ->where("FYearId=".$FYearId);

                        $select = $sql->select();
                        $select->from(array("a" => "FA_CashBankDet"))
                            ->columns(array("AccountId","AccountName" => new Expression("CashBankName"),"AccountType" => new Expression("CashOrBank"), 'CompanyId'));
                        $select->where->expression('a.CompanyId IN ?', array($subQuery));
                        $select->where("a.CompanyId = $CompanyId and CashOrBank in ('B','C') and AccountId Not IN($accId)");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultProjCamp['tabData']  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "FA_Account"))
                            ->columns(array("OpeningBalance","Amount" => new Expression("(SELECT ISNULL(SUM(CASE WHEN TransType='D' THEN Amount ELSE Amount*(-1) END),0) Amount
                                        FROM FA_EntryTrans WHERE Approve='Y' AND AccountId=$accId AND [PDC/Cancel]=0 AND CompanyId=$compId)")))
                            ->where("AccountId=$accId AND CompanyId=".$compId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultProjCamp['balAmount'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array("a" => "WF_Costcentre"))
                            ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName"),"CompanyId"))
                            ->where("CompanyId=".$compId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultProjCamp['costCent']= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "FA_chequetrans"))
                            ->columns(array("data"=>new Expression("a.ChequeTransId"),"value"=>new Expression("a.ChequeNo")))
                            ->where("AccountId=$accId and Cancel=0 and used=0");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultProjCamp['chequeList']= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode($resultProjCamp));
                    return $response;

                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();

            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $mode = "A";
                    $idenEntryId = $this->bsf->isNullCheck($postData['iEntryId'], 'number');
                    $rowSize = $this->bsf->isNullCheck($postData['rowSize'], 'number');
                    $isDeleteEntryId = $this->bsf->isNullCheck($postData['rowIsDelete'], 'string');

                    $voucherDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')));
                    $voucherNo= $this->bsf->isNullCheck($postData['voucherNo'], 'string');
                    $RelatedVNo='';
                    $RefNo='';
                    $RelatedRefNo='';
                    $BookVoucherNo='';
                    $BankCashVoucherNo='';
                    $JournalType='T';
                    $EntryType='C';//Debit
                    $m_iFBookId = $this->bsf->isNullCheck($postData['fromBookName'], 'number');
                    $costCenterId=0;
                    $costCenterId = $this->bsf->isNullCheck($postData['costCenter'], 'number');
                    $narration= $this->bsf->isNullCheck($postData['narration'], 'string');
                    //$Amount= $this->bsf->isNullCheck($postData['totAmount'], 'number');
                    //$otherAmount= $this->bsf->isNullCheck($postData['chrgAmount'], 'number');
                    $fromPayType = $this->bsf->isNullCheck($postData['paymentMode'], 'number');
                    $ChequeTransId = $this->bsf->isNullCheck($postData['chequeTransId'], 'number');
                    $previousChequeTransId = $this->bsf->isNullCheck($postData['previousChequeTransId'], 'number');
                    $ChequeNo= $this->bsf->isNullCheck($postData['transactionNo'], 'string');
                    $ChequeDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['transactionDate'], 'date')));
                    $ChequeDescription='';
                    $OtherAccId = $this->bsf->isNullCheck($postData['ExpAccountId'], 'number');
                    if($idenEntryId!=0){
                        $mode = "E";
                        $subQuery = $sql->select();
                        $subQuery->from("FA_EntryMaster")
                            ->columns(array('RefEntryId'))
                            ->where("EntryId=$idenEntryId");

                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_EntryMaster")
                            ->columns(array('EntryId'));
                        $subQuery1->where->expression('RefEntryId IN ?', array($subQuery));

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTrans')
                            ->where("RefType='O'");
                        $deleteTrans->where->expression('RefId IN ?', array($subQuery1));
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        if($isDeleteEntryId!="0" && $isDeleteEntryId != ""){
                            $deleteMaster = $sql->delete();
                            $deleteMaster->from('FA_EntryMaster')
                                ->where("JournalType='O'");
                            $deleteMaster->where("EntryId in ($isDeleteEntryId)");
                            $DelStatement = $sql->getSqlStringForSqlObject($deleteMaster);
                            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    /*Insert start*/
                    $iRefEntryId=0;
                    $icnt=0;
                    for($i=1; $i <=$rowSize; $i++ ){
                        $checkVal = $this->bsf->isNullCheck($postData['check_'.$i], 'number');
                        $rowEntryId = $this->bsf->isNullCheck($postData['rowEntryId_'.$i], 'number');
                        $rowRefEntryId = $this->bsf->isNullCheck($postData['rowRefEntryId_'.$i], 'number');
                        $rowVoucherNo = $this->bsf->isNullCheck($postData['voucherNo_'.$i], 'string');
                        $rowRefNo = $this->bsf->isNullCheck($postData['refVoucherNo_'.$i], 'string');
                        $accountId = $this->bsf->isNullCheck($postData['toBankId_'.$i], 'number');
                        $ToCCId = $this->bsf->isNullCheck($postData['costCenterId_'.$i], 'number');
                        $dAmt=0;
                        $toAmount = $this->bsf->isNullCheck($postData['toAmount_'.$i], 'number');
                        $othercharges = $this->bsf->isNullCheck($postData['bankcharges_'.$i], 'number');
                        $dAmt = $toAmount + $othercharges;
                        if($checkVal == 1){
                            $EntryType='T';

                            if($rowEntryId!=0){
                                $EntryId=$rowEntryId;

                                $update = $sql->update();
                                $update->table('FA_EntryMaster')
                                    ->set(array('VoucherDate' => $voucherDate
                                    , 'VoucherNo' => $voucherNo
                                    , 'RelatedVNo' => $RelatedVNo
                                    , 'RefNo' => $RefNo
                                    , 'RelatedRefNo' => $RelatedRefNo
                                    , 'BookVoucherNo' => $BookVoucherNo
                                    , 'BankCashVoucherNo' => $BankCashVoucherNo
                                    , 'JournalType' => $JournalType
                                    , 'EntryType' => $EntryType
                                    , 'BookId' => $m_iFBookId
                                    , 'AccountId' => $accountId
                                    , 'SubLedgerTypeId' => 0
                                    , 'SubLedgerId' => 0
                                    , 'Narration' => $narration
                                    , 'Amount' => $dAmt
                                    , 'FromCostCentreId' => $costCenterId
                                    , 'CostCentreId' => $ToCCId
                                    , 'PayType' => $fromPayType
                                    , 'ChequeTransId' => $ChequeTransId
                                    , 'ChequeNo' => $ChequeNo
                                    , 'ChequeDate' => $ChequeDate
                                    , 'ChequeDescription' => $ChequeDescription
                                    , 'OtherCharges' => $othercharges
                                    , 'OtherAccountId' => $OtherAccId
                                    , 'CompanyId' => $CompanyId
                                    , 'Approve' => "Y"
                                    , 'IsAppReady' => 1
                                    ))
                                    ->where(array('EntryId' => $EntryId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                $insert = $sql->insert();
                                $insert->into('FA_EntryMaster');
                                $insert->Values(array('VoucherDate' => $voucherDate
                                , 'VoucherNo' => $voucherNo
                                , 'RelatedVNo' => $RelatedVNo
                                , 'RefNo' => $RefNo
                                , 'RelatedRefNo' => $RelatedRefNo
                                , 'BookVoucherNo' => $BookVoucherNo
                                , 'BankCashVoucherNo' => $BankCashVoucherNo
                                , 'JournalType' => $JournalType
                                , 'EntryType' => $EntryType
                                , 'BookId' => $m_iFBookId
                                , 'AccountId' => $accountId
                                , 'SubLedgerTypeId' => 0
                                , 'SubLedgerId' => 0
                                , 'Narration' => $narration
                                , 'Amount' => $dAmt
                                , 'FromCostCentreId' => $costCenterId
                                , 'CostCentreId' => $ToCCId
                                , 'PayType' => $fromPayType
                                , 'ChequeTransId' => $ChequeTransId
                                , 'ChequeNo' => $ChequeNo
                                , 'ChequeDate' => $ChequeDate
                                , 'ChequeDescription' => $ChequeDescription
                                , 'OtherCharges' => $othercharges
                                , 'OtherAccountId' => $OtherAccId
                                , 'CompanyId' => $CompanyId
                                , 'Approve' => "Y"
                                , 'IsAppReady' => 1
                                , 'FYearId' => $FYearId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $EntryId= $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            $icnt=$icnt+1;
                            if($icnt==1){
                                $iRefEntryId=$EntryId;
                            }
                            //update RefEntryId
                            //UPDATE dbo.EntryMaster SET TallyUpdate=0,RefEntryId={0},RefFYearId={1},OtherCharges={2},OtherAccountId={3} WHERE EntryId={4}", iRefEntryId, BsfGlobal.g_lYearId,obj.OtherCharges,obj.OtherAccId,iEntryId)
                            $update = $sql->update();
                            $update->table('FA_EntryMaster')
                                ->set(array('RefEntryId' => $iRefEntryId
                                , 'RefFYearId' => $FYearId
                                ))
                                ->where(array('EntryId' => $EntryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Update chequeTrans
                            //UPDATE [{0}].dbo.ChequeTrans SET Used=0,Cancel=0,CancelDate=NULL,CancelRemarks='', PDC=0,PDCDate=NULL, PDCClear=0, PDCClearDate=NULL, IssueDate=NULL,ReconDate=NULL, FYearId=0 WHERE ChequeTransId={1}", BsfGlobal.g_sFaDBName, obj.OChequeId);
                            $update = $sql->update();
                            $update->table('FA_ChequeTrans')
                                ->set(array('Used' => 0
                                , 'Cancel' => 0
                                , 'CancelRemarks' => ''
                                , 'PDCDate' => null
                                , 'PDCClear' => 0
                                , 'IssueDate' => null
                                , 'FYearId' => 0
                                ))
                                ->where(array('ChequeTransId' => $previousChequeTransId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Insert EntryTrans for Credit
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RefNo' => $RefNo
                            , 'RefId' => $EntryId
                            , 'TransType' => 'C'
                            , 'RefType' => $JournalType
                            , 'AccountId' => $m_iFBookId
                            , 'RelatedAccountId' => $accountId
                            , 'SubLedgerTypeId' => 0
                            , 'SubLedgerId' => 0
                            , 'RelatedSLTypeId' => 0
                            , 'RelatedSLId' => 0
                            , 'CostCentreId' => $costCenterId
                            , 'Amount' => $toAmount
                            , 'Remarks' => $narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $CompanyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Insert EntryTrans for Debit
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $rowVoucherNo
                            , 'RefNo' => $rowRefNo
                            , 'RefId' => $EntryId
                            , 'TransType' => 'D'
                            , 'RefType' => $JournalType
                            , 'AccountId' => $accountId
                            , 'RelatedAccountId' => $m_iFBookId
                            , 'SubLedgerTypeId' => 0
                            , 'SubLedgerId' => 0
                            , 'RelatedSLTypeId' => 0
                            , 'RelatedSLId' => 0
                            , 'CostCentreId' => $ToCCId
                            , 'Amount' => $toAmount
                            , 'Remarks' => $narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $CompanyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Insert OtherCharges
                            if($othercharges!=0){
                                //Insert OtherCharges EntryTrans for Credit
                                $insert = $sql->insert();
                                $insert->into('FA_EntryTrans');
                                $insert->Values(array('VoucherDate' => $voucherDate
                                , 'VoucherNo' => $voucherNo
                                , 'RefNo' => $RefNo
                                , 'RefId' => $EntryId
                                , 'TransType' => 'C'
                                , 'RefType' => $JournalType
                                , 'AccountId' => $m_iFBookId
                                , 'RelatedAccountId' => $OtherAccId
                                , 'SubLedgerTypeId' => 0
                                , 'SubLedgerId' => 0
                                , 'RelatedSLTypeId' => 0
                                , 'RelatedSLId' => 0
                                , 'CostCentreId' => $costCenterId
                                , 'Amount' => $othercharges
                                , 'Remarks' => $narration
                                , 'IRemarks' => ''
                                , 'CompanyId' => $CompanyId
                                , 'Approve' => 'Y'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //Insert OtherCharges EntryTrans for Debit
                                $insert = $sql->insert();
                                $insert->into('FA_EntryTrans');
                                $insert->Values(array('VoucherDate' => $voucherDate
                                , 'VoucherNo' => $voucherNo
                                , 'RefNo' => $RefNo
                                , 'RefId' => $EntryId
                                , 'TransType' => 'D'
                                , 'RefType' => $JournalType
                                , 'AccountId' => $OtherAccId
                                , 'RelatedAccountId' => $m_iFBookId
                                , 'SubLedgerTypeId' => 0
                                , 'SubLedgerId' => 0
                                , 'RelatedSLTypeId' => 0
                                , 'RelatedSLId' => 0
                                , 'CostCentreId' => $costCenterId
                                , 'Amount' => $othercharges
                                , 'Remarks' => $narration
                                , 'IRemarks' => ''
                                , 'CompanyId' => $CompanyId
                                , 'Approve' => 'Y'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //update chequeTrans
                            if($ChequeTransId !=0 && $fromPayType != 3){
                                if($voucherDate>=$ChequeDate){
                                    $update = $sql->update();
                                    $update->table('FA_ChequeTrans')
                                        ->set(array('Amount' => $toAmount
                                        , 'Used' => 1
                                        , 'IssueDate' => $voucherDate
                                        , 'FYearId' => $FYearId
                                        ))
                                        ->where(array('ChequeTransId' => $ChequeTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $update = $sql->update();
                                    $update->table('FA_ChequeTrans')
                                        ->set(array('Amount' => $toAmount
                                        , 'Used' => 1
                                        , 'PDC' => 1
                                        , 'PDCDate' => $ChequeDate
                                        , 'IssueDate' => $voucherDate
                                        , 'FYearId' => $FYearId
                                        ))
                                        ->where(array('ChequeTransId' => $ChequeTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }

                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Multi-Transfer-Edit','E','FA-Multi Transfer Details',$iRefEntryId,0, $CompanyId, 'FA','',$userId, 0 ,0);//pass refno after FA as voucher $adviceNo
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Multi-Transfer-Add','N','FA-Multi Transfer Details',$iRefEntryId,0, $CompanyId, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "journalbook"));
                    //$this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'multicompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }


            // Load Costcentre
            $select = $sql->select();
            $select->from(array("a" => "WF_Costcentre"))
                ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName"),"CompanyId"))
                ->where("CompanyId=".$CompanyId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->costCentreList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "WF_Costcentre"))
                ->columns(array("CostCentreId","CostCentreName","CompanyId"))
                ->where("CompanyId=".$CompanyId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->costCentreLists= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //expense Account
            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array("AccountId","AccountName"))
                ->where("TypeId=7 AND LastLevel='Y'")
                ->order(array("AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->accountList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->columns(array("OpeningBalance","Amount" => new Expression("(SELECT ISNULL(SUM(CASE WHEN TransType='D' THEN Amount ELSE Amount*(-1) END),0) Amount
                                        FROM FA_EntryTrans WHERE Approve='Y' AND AccountId=42 AND [PDC/Cancel]=0 AND CompanyId=1)")))
                ->where("AccountId=42 AND CompanyId=".$CompanyId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $BalAmtList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $subQuery = $sql->select();
            $subQuery->from("FA_FiscalYearTrans")
                ->columns(array('CompanyId'))
                ->where("FYearId=".$FYearId);

            $select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("AccountId","AccountName" => new Expression("CashBankName"),"AccountType" => new Expression("CashOrBank"), 'CompanyId'));
            $select->where->expression('a.CompanyId IN ?', array($subQuery));
            $select->where("a.CompanyId = $CompanyId and CashOrBank in ('B','C')");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->bookNameSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //onload details
            $allowEdit=1;
            $select = $sql->select();
            $select->from(array("a" => "FA_EntryMaster"))
                ->columns(array("VoucherDate"=>new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"),"VoucherNo","BookId","FromCostCentreId"
                                ,"PayType","ChequeTransId","ChequeNo","ChequeDate"=>new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')")
                                ,"OtherAccountId","Narration","Approve"))
                ->where("EntryId=$entryId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->editDetails=$editDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $bookId = $editDetails['BookId'];
            if($editDetails){
                if($editDetails['Approve']=="Y"){
                    $allowEdit=0;
                }
            }
            if($bookId !=0){
                $bookId = $bookId;
            }else{
                $bookId =0;
            }

            $select = $sql->select();
            $select->from(array('a' =>'FA_CashBankDet'))
                ->columns(array('AccountId','CompanyId','AccountType' => new Expression("CashOrBank")))
                ->where(array("AccountId = $bookId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $loadResultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $accountID = $loadResultLeads['AccountId'];
            if($accountID != 0){
                $accountID =$accountID;
            }else{
                $accountID = 0;
            }
            $subQuery = $sql->select();
            $subQuery->from("FA_FiscalYearTrans")
                ->columns(array('CompanyId'))
                ->where("FYearId=".$FYearId);

       /*     $select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("AccountId","AccountName" => new Expression("CashBankName"),"AccountType" => new Expression("CashOrBank"), 'CompanyId'));
            $select->where->expression('a.CompanyId IN ?', array($subQuery));
            $select->where("a.CompanyId = $CompanyId and CashOrBank in ('B','C') and AccountId Not IN($accountID)");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->loadTabData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

                // trans Load

             $subQuery1 = $sql->select();
            $subQuery1->from("FA_EntryMaster")
                ->columns(array('RefEntryId'))
                ->where("EntryId=$entryId");

            $subQueryEntry = $sql->select();
            $subQueryEntry->from('FA_EntryMaster')
                ->columns(array('AccountId'));
            $subQueryEntry->where->expression('RefEntryId IN ?', array($subQuery1));

            $selectGroup = $sql->select();
            $selectGroup->from(array("a"=>"FA_CashBankDet"))
                ->columns(array('EntryId' => new Expression("1-1"),'RefEntryId' => new Expression("1-1"),'AccountId'
                ,'AccountName'=> new Expression("a.CashBankName"),'AccountType'=> new Expression("a.CashOrBank")
                ,'CompanyId','Amount' => new Expression("1-1"),'OtherCharges' => new Expression("1-1")
                ,'CostCentreId' => new Expression("1-1"),'VoucherNo'=> new Expression("''"),'RefNo'=> new Expression("''")
                ,'CostCentreName' => new Expression("''"),'sel' => new Expression("1-1")));
            $selectGroup->where->expression('a.CompanyId IN ?', array($subQuery));
            $selectGroup->where("a.CompanyId = $CompanyId and CashOrBank in ('B','C') and AccountId <>$accountID");
            $selectGroup->where->expression('a.accountId not IN ?', array($subQueryEntry));

            $subQuery1 = $sql->select();
            $subQuery1->from("FA_EntryMaster")
                ->columns(array('RefEntryId'))
                ->where("EntryId=$entryId");

            $subQueryEntry = $sql->select();
            $subQueryEntry->from('FA_EntryMaster')
                ->columns(array('EntryId'));
            $subQueryEntry->where->expression('RefEntryId IN ?', array($subQuery1));

            $selectGroupEdit = $sql->select();
            $selectGroupEdit->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId','RefEntryId','AccountId'
                ,'AccountName'=> new Expression("b.CashBankName"),'AccountType'=> new Expression("b.CashOrBank")
                ,'CompanyId','Amount' => new Expression("(a.Amount-a.OtherCharges)"),'OtherCharges'
                ,'CostCentreId' => new Expression("c.CostCentreId"),'VoucherNo' => new Expression("d.VoucherNo"),'RefNo' => new Expression("d.RefNo")
                ,'CostCentreName' => new Expression("c.CostCentreName"),'sel' => new Expression("1") ))
                ->join(array("b" => "FA_CashBankDet"), "a.AccountId=b.AccountId and a.CompanyId = b.companyId", array(), $selectGroupEdit::JOIN_INNER)
                ->join(array("c" => "WF_CostCentre"), "a.CostCentreId=c.CostCentreId", array(), $selectGroupEdit::JOIN_LEFT)
                ->join(array("d" => "FA_EntryTrans"),  new Expression("a.EntryId=d.RefId and a.AccountId=d.AccountId and TransType='D'"), array(), $selectGroupEdit::JOIN_INNER);
            $selectGroupEdit->where->expression('a.EntryId IN ?', array($subQueryEntry));
            $selectGroupEdit->combine($selectGroup,'Union ALL');

            $selectFinal = $sql->select();
            $selectFinal->from(array("g"=>$selectGroupEdit))
                ->columns(array("*"));

            $statement = $sql->getSqlStringForSqlObject($selectFinal);
            $this->_view->loadEditTabData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "WF_Costcentre"))
                ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName"),"CompanyId"))
                ->where("CompanyId=".$CompanyId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->costCenterdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "FA_chequetrans"))
                ->columns(array("data"=>new Expression("a.ChequeTransId"),"value"=>new Expression("a.ChequeNo")))
                ->where("AccountId=$accountID");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->chequeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->entryId = $entryId;
            $this->_view->allowEdit = $allowEdit;

            $aVNo = CommonHelper::getVoucherNo(609, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->aVNo= $aVNo;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function chequeentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Fiscal Year");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $accountId= $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $chequeId= $this->bsf->isNullCheck($this->params()->fromRoute('chequeId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type == 'getLoadDetails'){
                $ChequeId= $this->bsf->isNullCheck($postData['ChequeId'], 'number');
                $accountId= $this->bsf->isNullCheck($postData['accountId'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "FA_ChequeMaster"))
                    ->columns(array("StartNo", "ChequeRDate" => new Expression("FORMAT(a.ChequeRDate, 'dd-MM-yyyy')")
                    , "NoofLeaves" ,"EndNo", "Remarks", "ChequeNoWidth"));
                $select->where("a.ChequeId = $ChequeId and a.DeleteFlag=0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $chequeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->chequeList = $chequeList;

                $subQuery = $sql->select();
                $subQuery->from("FA_ChequeMaster")
                    ->columns(array('ChequeId'))
                    ->where("ChequeId=$ChequeId and DeleteFlag=0");

                $select = $sql->select();
                $select->from(array("a" => "FA_ChequeTrans"))
                    ->columns(array("ChequeTransId", "ChequeNo"));
                $select->where->expression('a.ChequeId IN ?', array($subQuery));
                //$select->where("a.ChequeId = $ChequeId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $chequeTransList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->chequeTransList = $chequeTransList;
                $this->_view->accountId = $accountId;
                $this->_view->ChequeId = $ChequeId;

            } else if($type == 'addEditDetails'){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $ChequeId = $this->bsf->isNullCheck($postData['ChequeId'], 'number');
                    $accountId= $this->bsf->isNullCheck($postData['accountId'], 'number');
                    $mode="A";

                    $companyId=0;
                    $select = $sql->select();
                    $select->from(array("a" => "FA_CashBankDet"))
                        ->columns(array("CompanyId"))
                        ->where("AccountId=$accountId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $compList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($compList) != 0)
                        $companyId=$compList[0]['CompanyId'];

                    if ($ChequeId != 0) {
                        $mode="E";
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_ChequeTrans')
                            ->where("ChequeId=$ChequeId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_ChequeMaster')
                            ->set(array('ChequeRDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['requestedDate'], 'date')))
                            , 'StartNo' => $this->bsf->isNullCheck($postData['startNo'], 'number')
                            , 'NoofLeaves' => $this->bsf->isNullCheck($postData['noOfLeaves'], 'number')
                            , 'EndNo' => $this->bsf->isNullCheck($postData['endNo'], 'number')
                            , 'Remarks' => $this->bsf->isNullCheck($postData['remarks'], 'string')
                            , 'CompanyId' => $companyId
                            , 'ChequeNoWidth' => $this->bsf->isNullCheck($postData['width'], 'number')
                            ))
                            ->where(array('ChequeId' => $ChequeId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $chequeCount = $this->bsf->isNullCheck($postData['chequeCount'], 'number');
                        for ($i = 1; $i <= $chequeCount; $i++) {

                            $insert = $sql->insert();
                            $insert->into('FA_ChequeTrans');
                            $insert->Values(array('ChequeId' => $ChequeId
                            ,'AccountId' => $accountId
                            , 'ChequeNo' => $this->bsf->isNullCheck($postData['chequeNumber_' . $i], 'string')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_ChequeMaster');
                        $insert->Values(array('AccountId' => $accountId
                        , 'ChequeRDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['requestedDate'], 'date')))
                        , 'StartNo' => $this->bsf->isNullCheck($postData['startNo'], 'number')
                        , 'NoofLeaves' => $this->bsf->isNullCheck($postData['noOfLeaves'], 'number')
                        , 'EndNo' => $this->bsf->isNullCheck($postData['endNo'], 'number')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['remarks'], 'string')
                        , 'CompanyId' => $companyId
                        , 'ChequeNoWidth' => $this->bsf->isNullCheck($postData['width'], 'number')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ChequeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $chequeCount = $this->bsf->isNullCheck($postData['chequeCount'], 'number');
                        for ($i = 1; $i <= $chequeCount; $i++) {

                            $insert = $sql->insert();
                            $insert->into('FA_ChequeTrans');
                            $insert->Values(array('ChequeId' => $ChequeId
                                ,'AccountId' => $accountId
                            , 'ChequeNo' => $this->bsf->isNullCheck($postData['chequeNumber_' . $i], 'string')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    if ($mode == "E") {
                        //CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Edit','E','FA-Fiscal Year Details',$fYearId,0, 0, 'FA','',$userId, 0 ,0);
                    } else {
                        //CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Add','N','FA-Fiscal Year Details',$fYearId,0, 0, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'chequedetail','accountId'=>$accountId));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->setTerminal(true);
            return $this->_view;
        } else {
            if ($request->isPost()) {

            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function chequedetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest()) {
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['mode'], 'string');
            if($type=="delete")
            {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $ChequeId = $this->bsf->isNullCheck($postData['ChequeId'], 'number');
                    $sql = new Sql($dbAdapter);

                    // check for already exists
                    $select = $sql->select();
                    $select->from('FA_ChequeTrans')
                        ->columns(array('ChequeId'))
                        ->where("ChequeId=$ChequeId and (Used=1 or Cancel=1)");
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $fiscalFound = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(count($fiscalFound) > 0) {
                        $response->setStatusCode(201)->setContent($status);
                    } else {
                        $connection->beginTransaction();

                        $update = $sql->update();
                        $update->table('FA_ChequeMaster')
                            ->set(array('DeleteFlag' => '1'))
                            ->where(array('ChequeId' => $ChequeId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        //CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Fiscal-Year-Delete','D','FA-Fiscal Year Details',$FYearId,0, 0, 'FA','',$userId, 0 ,0);
                        $status = 'deleted';
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
            } else {
                $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                $select = $sql->select();
                $select->from(array("a" => "FA_ChequeMaster"))
                    ->join(array("b" => "FA_ChequeTrans"), "a.ChequeID=b.ChequeID", array(), $select::JOIN_INNER)
                    ->columns(array("ChequeId", "ChequeRDate" => new Expression("FORMAT(a.ChequeRDate, 'dd-MM-yyyy')"),"AccountId"
                    ,"StartNo","ChequeNoWidth","NoofLeaves","EndNo","CompanyId", "Used" => new Expression("SUM(CASE WHEN Used=1 THEN 1 ELSE 0 END)")
                    , "Cancel" => new Expression("SUM(CASE WHEN Cancel=1 THEN 1 ELSE 0 END)"), "Balance" => new Expression("Count(ChequeNo)-SUM(CASE WHEN Used=1 THEN 1 ELSE 0 END)-SUM(CASE WHEN Cancel=1 THEN 1 ELSE 0 END)")
                    ));
                $select->where("a.AccountId=$accountId and a.DeleteFlag=0");
                $select->group(new Expression('a.ChequeId,a.ChequeRDate,a.AccountId,a.StartNo,a.ChequeNoWidth,a.NoofLeaves,a.EndNo,a.CompanyId'));
                //$select->where("a.DeleteFlag=0");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $result = $fiscalyeardetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            $this->_view->fiscalyeardetList = $fiscalyeardetList;

                $response->setContent(json_encode($result));
            }
            return $response;
        } else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_ChequeMaster"))
                ->join(array("b" => "FA_ChequeTrans"), "a.ChequeID=b.ChequeID", array(), $select::JOIN_INNER)
            ->columns(array("ChequeId", "ChequeRDate" => new Expression("FORMAT(a.ChequeRDate, 'dd-MM-yyyy')"),"AccountId"
                ,"StartNo","ChequeNoWidth","NoofLeaves","EndNo","CompanyId", "Used" => new Expression("SUM(CASE WHEN Used=1 THEN 1 ELSE 0 END)")
            , "Cancel" => new Expression("SUM(CASE WHEN Cancel=1 THEN 1 ELSE 0 END)"), "Balance" => new Expression("Count(ChequeNo)-SUM(CASE WHEN Used=1 THEN 1 ELSE 0 END)-SUM(CASE WHEN Cancel=1 THEN 1 ELSE 0 END)")
            ));
            $select->where("a.AccountId=$accountId and a.DeleteFlag=0");
            $select->group(new Expression('a.ChequeId,a.ChequeRDate,a.AccountId,a.StartNo,a.ChequeNoWidth,a.NoofLeaves,a.EndNo,a.CompanyId'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $fiscalyeardetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->fiscalyeardetList = $fiscalyeardetList;
            $this->_view->accountId = $accountId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function cashmanagementAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql( $dbAdapter );
        $EntryId= $this->bsf->isNullCheck($this->params()->fromRoute('EntryId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $type= $this->bsf->isNullCheck($postData['type'], 'string');
                    if($type == 'getAccountDetails'){ //Bal Load
                        $accId= $this->bsf->isNullCheck($postData['accId'], 'number');
                        $accCompanyId= $this->bsf->isNullCheck($postData['companyId'], 'number');

                        $subQuery = $sql->select();
                        $subQuery->from(array("a" => "FA_EntryTrans"))
                            ->columns(array("Amount"=>new Expression("ISNULL(SUM(CASE WHEN TransType='D' THEN Amount ELSE Amount*(-1) END),0)")))
                            ->where("Approve='Y' AND AccountId=$accId AND [PDC/Cancel]=0 AND CompanyId=$accCompanyId");

                        $select = $sql->select();
                        $select->from(array("a" => "FA_Account"))
                            ->columns(array("OpeningBalance",'Amount'=>new Expression("(SELECT ISNULL(SUM(CASE WHEN TransType='D' THEN Amount
                            ELSE Amount*(-1) END),0) Amount FROM FA_EntryTrans WHERE Approve='Y' AND AccountId=$accId AND [PDC/Cancel]=0 AND CompanyId=2)")))
                            ->where(" AccountId=$accId AND CompanyId= $accCompanyId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result['balance']=$balanceAmt= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }else if($type == 'getSubLedgerType'){//subledgerType
                        $AccountTypeId= $this->bsf->isNullCheck($postData['AccountTypeId'], 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "FA_SubLedgerType"))
                            ->join(array("b" => "FA_SLAccountType "), "a.SubLedgerTypeId=b.SLTypeId", array(), $select::JOIN_INNER)
                            ->columns(array("SubLedgerTypeId","SubLedgerTypeName","SubLedgerType"
                            ,"data"=>new Expression("a.SubLedgerTypeId"),"value"=>new Expression("a.SubLedgerTypeName")))
                            ->where("b.TypeId=$AccountTypeId")
                            ->order(array("a.SubLedgerTypeName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result['subLedgerType']=$subledgerType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }else if($type == 'getSubLedgerName'){//subLedgerName
                        $SubLedgerTypeId= $this->bsf->isNullCheck($postData['SubLedgerTypeId'], 'number');

                        $select = $sql->select();
                        $select->from(array("SLM" => "FA_SubLedgerMaster"))
                            ->columns(array("SubLedgerId","SubLedgerName","SubLedgerTypeId","RefId","ServiceTypeId"
                            ,"data"=>new Expression("SLM.SubLedgerId"),"value"=>new Expression("SLM.SubLedgerName")))
                            ->where("SLM.SubLedgerTypeId=$SubLedgerTypeId")
                            ->order(array("SLM.SubLedgerName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result['subLedgerName']=$subLedgerName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
//                    $connection->beginTransaction();
//                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $EntryId = $this->bsf->isNullCheck($postData['EntryId'], 'number');

                    $mode="A";
                    $debitAmt = $this->bsf->isNullCheck($postData['DebitSumAMount'], 'number');
                    $creditAmt = $this->bsf->isNullCheck($postData['CreditSumAMount'], 'number');
                    $otherCharges=$this->bsf->isNullCheck($postData['bookName'], 'number');
                    $paymentMode=$this->bsf->isNullCheck($postData['paymentMode'], 'string');
                    $dTotal =$debitAmt -$creditAmt;

                    if($dTotal < 0){
                        $dTotal =$dTotal*(-1);
                    }

                    $sPayOrRec="R";
                    if($debitAmt>=$creditAmt){
                        $sPayOrRec="P";
                    }
                    $transCount = $this->bsf->isNullCheck($postData['rowid'], 'number');

                    if ($EntryId != 0) {
                        $mode="E";
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_EntryTrans')
                            ->where("RefType='M' AND RefId=$EntryId ");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        if($transCount>1) {////For Multi Trans Row
                            $update->table('FA_EntryMaster')
                                ->set(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                , 'RelatedVNo' => ''
                                , 'RefNo' => ''
                                , 'RelatedRefNo' => ''
                                , 'BookVoucherNo' => ''
                                , 'BankCashVoucherNo' => ''
                                , 'JournalType' => 'M'
                                , 'EntryType' => $sPayOrRec
                                , 'BookId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                                , 'AccountId' => 0
                                , 'SubLedgerTypeId' => 0
                                , 'SubLedgerId' => 0
                                , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                                , 'Amount' => $dTotal
                                , 'OtherCharges' => $otherCharges
                                , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                                , 'PayType' => $paymentMode
                                , 'ChequeTransId' => 0
                                , 'ChequeNo' => $this->bsf->isNullCheck($postData['transactionNo'], 'string')
                                , 'ChequeDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['transactionDate'], 'date')))
                                , 'ChequeDescription' => ''
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'N'
                                , 'IsAppReady' => 1
                                , 'PDC' => 0));
                        } else {
                            $update->table('FA_EntryMaster')
                                ->set(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                , 'RelatedVNo' => ''
                                , 'RefNo' => ''
                                , 'RelatedRefNo' => ''
                                , 'BookVoucherNo' => ''
                                , 'BankCashVoucherNo' => ''
                                , 'JournalType' => 'M'
                                , 'EntryType' => $sPayOrRec
                                , 'BookId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                                , 'AccountId' => $this->bsf->isNullCheck($postData['accountHeadId_'.$transCount], 'number')
                                , 'SubLedgerTypeId' => $this->bsf->isNullCheck($postData['subLedgerTypeId_'.$transCount], 'number')
                                , 'SubLedgerId' => $this->bsf->isNullCheck($postData['subLedgerId_'.$transCount], 'number')
                                , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                                , 'Amount' => $dTotal
                                , 'OtherCharges' => $otherCharges
                                , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                                , 'PayType' => $paymentMode
                                , 'ChequeTransId' => 0
                                , 'ChequeNo' => $this->bsf->isNullCheck($postData['transactionNo'], 'string')
                                , 'ChequeDate' => date('Y-m-d',strtotime( $this->bsf->isNullCheck($postData['transactionDate'], 'date')))
                                , 'ChequeDescription' => ''
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'N'
                                , 'IsAppReady' => 1
                                , 'PDC' => 0));
                        }
                        $update->where(array('EntryID' => $EntryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_EntryMaster');
                        if($transCount>1){//For Multi Trans Row
                            $insert->Values(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                , 'RelatedVNo' => ''
                                , 'RefNo' => ''
                                , 'RelatedRefNo' => ''
                                , 'BookVoucherNo' => ''
                                , 'BankCashVoucherNo' => ''
                                , 'JournalType' => 'M'
                                , 'EntryType' => $sPayOrRec
                                , 'BookId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                                , 'AccountId' => 0
                                , 'SubLedgerTypeId' => 0
                                , 'SubLedgerId' => 0
                                , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                                , 'Amount' => $dTotal
                                , 'OtherCharges' => $otherCharges
                                , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                                , 'PayType' => $paymentMode
                                , 'ChequeTransId' => 0
                                , 'ChequeNo' => $this->bsf->isNullCheck($postData['transactionNo'], 'string')
                                , 'ChequeDate' => date('Y-m-d',strtotime( $this->bsf->isNullCheck($postData['transactionDate'], 'date')))
                                , 'ChequeDescription' => ''
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'N'
                                , 'IsAppReady' => 1
                                , 'PDC' => 0
                                , 'FYearId' => $FYearId
                            ));
                        } else { //For Single Trans Row
                            $insert->Values(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                            , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                            , 'RelatedVNo' => ''
                            , 'RefNo' => ''
                            , 'RelatedRefNo' => ''
                            , 'BookVoucherNo' => ''
                            , 'BankCashVoucherNo' => ''
                            , 'JournalType' => 'M'
                            , 'EntryType' => $sPayOrRec
                            , 'BookId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                            , 'AccountId' => $this->bsf->isNullCheck($postData['accountHeadId_'.$transCount], 'number')
                            , 'SubLedgerTypeId' => $this->bsf->isNullCheck($postData['subLedgerTypeId_'.$transCount], 'number')
                            , 'SubLedgerId' => $this->bsf->isNullCheck($postData['subLedgerId_'.$transCount], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'Amount' => $dTotal
                            , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                            , 'PayType' => ''
                            , 'ChequeTransId' => 0
                            , 'ChequeNo' => $this->bsf->isNullCheck($postData['transactionNo'], 'string')
                            , 'ChequeDate' => date('Y-m-d',strtotime( $this->bsf->isNullCheck($postData['transactionDate'], 'date')))
                            , 'ChequeDescription' => ''
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'N'
                            , 'IsAppReady' => 1
                            , 'PDC' => 0
                            , 'FYearId' => $FYearId
                            ));
                        }
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $EntryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    for ($i = 1; $i <= $transCount; $i++) {

                        if($this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number') == 0)
                          continue;

                        $dAmountDe=$this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                        $dAmount=0;
                        if($dAmountDe> 0){
                            $sTransType = "D";
                            $dAmount = $dAmountDe;
                        } else {
                            $sTransType = "C";
                            $dAmount = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                        }
                        //For Credit
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                        ,'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                        , 'RefId' => $EntryId
                        , 'TransType' => $sTransType
                        , 'RefType' => 'M'
                        , 'AccountId' => $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number')
                        , 'RelatedAccountId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                        , 'SubLedgerTypeId' => $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number')
                        , 'SubLedgerId' => $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number')
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                        , 'Amount' => $dAmount
                        , 'CompanyId' => $companyId
                        , 'RefNo' => ''
                        , 'Approve' => 'N'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //For Debit
                        if($sTransType=="C"){
                            $sTransType="D";
                        } else {
                            $sTransType="C";
                        }
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                        ,'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                        , 'RefId' => $EntryId
                        , 'TransType' => $sTransType
                        , 'RefType' => 'M'
                        , 'AccountId' => $this->bsf->isNullCheck($postData['bookName'], 'number')
                        , 'RelatedAccountId' => $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number')
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number')
                        , 'RelatedSLId' => $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostcentreId'], 'number')
                        , 'Amount' => $dAmount
                        , 'CompanyId' => $companyId
                        , 'RefNo' => ''
                        , 'Approve' => 'N'
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Cash-Management-Edit','E','FA-Cash Management Details',$EntryId,0, 0, 'FA','',$userId, 0 ,0);
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Cash-Management-Add','N','FA-Cash Management Details',$EntryId,0, 0, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'journalbook'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->FYearId=$FYearId;
            $this->_view->companyId=$companyId;
            $this->_view->EntryId=$EntryId;

            /*$select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("CashBankId","CashBankName","CashOrBank","CompanyId"))
                ->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $cashBankList=$fromBookName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->cashBankList=$cashBankList;*/

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("AccountID","AccountName"=>new Expression("b.AccountName"),"AccountType"=>new Expression("b.AccountType")))
                ->where("b.AccountType IN ('BA','CA','FI') and a.CompanyId=$companyId and a.FYearId=$FYearId")
                ->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $accountList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->accountList=$accountList;//Book Load



            $select = $sql->select();
            $select->from(array("a" => "WF_CostCentre"))
                ->columns(array("CostCentreId","CostCentreName","StateId"))
                ->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ccList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ccList=$ccList;//Cost Center Load

            $ccLoadId=1;
            $select = $sql->select();
            $select->from(array("a" => "WF_OperationalCostCentre"))
                ->columns(array("CostCentreId","CostCentreName","FACostCentreId"))
                ->where("a.CompanyId=$companyId and FACostCentreId=$ccLoadId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $OPCCList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->OPCCList=$OPCCList;//OPerational CostCenter Load

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("AccountId","AccountName"=>new Expression("b.AccountName"),
                    "AccountType"=>new Expression("b.AccountType"),"TypeId"=>new Expression("b.TypeId")
                    ,"data"=>new Expression("b.AccountId"),"value"=>new Expression("b.AccountName")))
                ->where("a.CompanyId=$companyId and b.AccountType NOT IN ('BA','CA') and b.LastLevel='Y'")
                ->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $AccountHead= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->AccountHead=$AccountHead;//AccountHaed

            $allowEdit=1;
            if($EntryId !=0) {
                $select = $sql->select();
                $select->from(array("a" => "FA_EntryMaster"))
                    ->columns(array("EntryId", "VoucherNo", "VoucherDate" => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')")
                    , "Approve", 'BookId', 'PayType', 'ChequeNo', "ChequeDate" => new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')")
                    , 'Narration', 'Amount', 'OtherCharges', 'CostCentreId' ,'Approve'));
                $select->where("a.EntryId=$EntryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $entryList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($entryList){
                    if($entryList['Approve']=="Y"){
                        $allowEdit=0;
                    }
                }
                $this->_view->entryList = $entryList;

                $subQuery = $sql->select();
                $subQuery->from(array("a" => "FA_EntryMaster"))
                    ->columns(array("BookId"));
                $subQuery->where("a.EntryId=$EntryId");

                $select = $sql->select();
                $select->from(array("a" => "FA_EntryTrans"))
                    ->join(array("b" => "FA_AccountMaster "), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "FA_SubLedgerType "), "a.SubLedgerTypeId=c.SubLedgerTypeId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "FA_SubLedgerMaster "), "a.SubLedgerId=d.SubLedgerId", array(), $select::JOIN_INNER)
                    ->columns(array("AccountId", 'SubLedgerTypeId', 'SubLedgerId', 'Amount', 'TransType', 'AccountName' => new Expression("b.AccountName")
                    , 'SubLedgerTypeName' => new Expression("c.SubLedgerTypeName"), 'SubLedgerName' => new Expression("d.SubLedgerName")));
                $select->where->expression("a.RefType='M' and a.RefId=$EntryId and a.AccountId NOT IN ?", array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $entryTransList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->entryTransList = $entryTransList;
                //SELECT * FROM dbo.EntryMaster WHERE EntryId=
                //SELECT * FROM dbo.EntryTrans WHERE AccountId NOT IN (SELECT BookId FROM dbo.EntryMaster WHERE EntryId={0} ) AND RefType='M' AND RefId=EntryId
            }

            $aVNo = CommonHelper::getVoucherNo(602, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];
            $this->_view->aVNo =$aVNo;
            $this->_view->allowEdit = $allowEdit;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function transferentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function cashmanagementregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_EntryMaster"))
                ->join(array("b" => "FA_AccountMaster "), "a.BookId=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("EntryId","VoucherNo","VoucherDate"=>new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"),"Approve","BookName"=>new Expression("b.AccountName")));
            $select->where("a.CompanyId=$companyId and JournalType='M'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $entryList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->entryList=$entryList;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function specialjournalAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql( $dbAdapter );
        $JournalEntryId= $this->bsf->isNullCheck($this->params()->fromRoute('JournalEntryId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $type= $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type){

                        case 'getVoucher':
                            $journalTypeChar= $this->bsf->isNullCheck($postData['journalTypeChar'], 'string');
                            $specJournalType= $this->bsf->isNullCheck($postData['specJournalType'], 'number');
                            $voucherTypeId=0;
                            switch($journalTypeChar){
                                case 'G':
                                    $voucherTypeId=603;
                                    break;
                                case 'P':
                                    $voucherTypeId=604;
                                    break;
                                case 'I':
                                    $voucherTypeId=605;
                                    break;
                                case 'D':
                                    $voucherTypeId=606;
                                    break;
                                case 'C':
                                    $voucherTypeId=607;
                                    break;
                            }
                            $aVNo = CommonHelper::getVoucherNo($voucherTypeId, date('Y/m/d'), 0, 0, $dbAdapter, "");
                            $genType = $aVNo["genType"];
                            if (!$aVNo["genType"])
                                $svNo = "";
                            else
                                $svNo = $aVNo["voucherNo"];
                            $result['voucherNumber']=$svNo;
                            $result['genType']=$genType;

                            $result['recurringTypes']='';
                            if($specJournalType ==1 || $specJournalType==3){
                                $select = $sql->select();
                                $select->from(array("a" => "FA_RecurringMaster"))
                                    ->columns(array("RecurringId","RecurringTypeName"))
                                    ->where("a.CompanyId=$companyId and BookId=$specJournalType");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result['recurringTypes']=$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }

                            break;

                        case 'getSubLedgerType':
                            $AccountTypeId= $this->bsf->isNullCheck($postData['AccountTypeId'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_SLAccountType"))
                                ->join(array("b" => "FA_AccountType "), "a.TypeId=b.TypeId", array(), $select::JOIN_INNER)
                                ->join(array("c" => "FA_SubLedgerType "), "c.SubLedgerTypeId=a.SLTypeId ", array(), $select::JOIN_INNER)
                                ->columns(array("TypeId","SLTypeId","SubLedgerType"=>new Expression("c.SubLedgerType"),"TypeName"=>new Expression("b.TypeName")
                                ,"data"=>new Expression("a.SLTypeId"),"value"=>new Expression("c.SubLedgerTypeName")))
                                ->where("a.TypeId=$AccountTypeId")
                                ->order(array("b.TypeName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['subLedgerType']=$subledgerType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;

                        case 'getSubLedgerName':
                            $SubLedgerTypeId= $this->bsf->isNullCheck($postData['SubLedgerTypeId'], 'number');
                            $mode= $this->bsf->isNullCheck($postData['mode'], 'string');
                            $result['subLedgerName']='';
                            $result['invoiceSubLedgerName']='';

                            if($mode == 'invoice'){
                                $select = $sql->select();
                                $select->from(array("SLM" => "FA_SubLedgerMaster"))
                                    ->columns(array("SubLedgerId","SubLedgerName","SubLedgerTypeId","RefId","ServiceTypeId"
                                    ,"data"=>new Expression("SLM.SubLedgerId"),"value"=>new Expression("SLM.SubLedgerName")))
                                    ->where("SLM.SubLedgerTypeId=$SubLedgerTypeId and SLM.RefId<>0")
                                    ->order(array("SLM.SubLedgerName"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result['invoiceSubLedgerName']=$subLedgerName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }else {
                                $select = $sql->select();
                                $select->from(array("SLM" => "FA_SubLedgerMaster"))
                                    ->columns(array("SubLedgerId", "SubLedgerName", "SubLedgerTypeId", "RefId", "ServiceTypeId"
                                    , "data" => new Expression("SLM.SubLedgerId"), "value" => new Expression("SLM.SubLedgerName")))
                                    ->where("SLM.SubLedgerTypeId=$SubLedgerTypeId")
                                    ->order(array("SLM.SubLedgerName"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result['subLedgerName'] = $subLedgerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }
                            break;

                        case 'getInvoiceOPCC':
                            $CCId= $this->bsf->isNullCheck($postData['CCId'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "WF_OperationalCostCentre"))
                                ->columns(array("CostCentreId", "CostCentreName", "FACostCentreId"
                                , "data" => new Expression("a.CostCentreId"), "value" => new Expression("a.CostCentreName")))
                                ->where("a.FACostCentreId=$CCId")
                                ->order(array("a.CostCentreName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['invoiceOPCC'] = $subLedgerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;

                        case 'getInvoiceType':
                            $invoiceAccountTypeId= $this->bsf->isNullCheck($postData['invoiceAccountTypeId'], 'number');
                            $invoiceSubLedgerId= $this->bsf->isNullCheck($postData['invoiceSubLedgerId'], 'number');
                            $result['getInvoiceType']='';
                            if($invoiceAccountTypeId == 4){

                                $select = $sql->select();
                                $select->from(array("a" => "FA_SubledgerMaster"))
                                    ->join(array("b" => "Vendor_Master "), "b.VendorId=a.RefId", array(), $select::JOIN_INNER)
                                    ->columns(array("VendorId"=>new Expression("b.VendorId"),'SubLedgerId','SubLedgerName'
                                    ,'Contract'=>new Expression("b.Contract"),'Supply'=>new Expression("b.Supply"),'Service'=>new Expression("b.Service")))
                                    ->where("a.SubLedgerId=$invoiceSubLedgerId");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $invoiceType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $invoiceList = array();
                                if ($invoiceType['Supply'] == 1) {
                                    $invoiceList[0]['InvoiceId'] = 1;
                                    $invoiceList[0]['InvoiceType'] = 'Supply';
                                }
                                if ($invoiceType['Contract'] == 1) {
                                    $invoiceList[1]['InvoiceId'] = 2;
                                    $invoiceList[1]['InvoiceType'] = 'Contract';
                                }
                                if ($invoiceType['Service'] == 1) {
                                    $invoiceList[2]['InvoiceId'] = 3;
                                    $invoiceList[2]['InvoiceType'] = 'Service';
                                }
                                $result['getInvoiceType'] = $invoiceList;
                            }
                            break;

                        case 'getServiceGroup':
                            $select = $sql->select();
                            $select->from(array("a" => "Vendor_ServiceGroup"))
                                ->columns(array('ServiceGroupId','ServiceGroupName','ServiceTypeId'))
                                ->where("a.ServiceTypeId<>0")
                                ->order(array("a.ServiceGroupName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['serviceGroup'] = $subLedgerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;

                        case 'getRecurringTrans':
                            $recurringId=$this->bsf->isNullCheck($postData['recurringType'], 'number');

                            $select = $sql->select();
                            $select -> from(array("a" => "FA_RecurringTrans"))
                                ->columns(array("RecurringTransId","AccountId","SubLedgerId","CostCentreId","SubLedgerTypeId","TransType","Debit" => new Expression("Case when a.TransType ='D' THEN a.Amount END")
                                ,'AccountName'=>new Expression("b.AccountName"),'CostCentreName'=>new Expression("e.CostCentreName"),'SubLedgerTypeName'=>new Expression("c.SubLedgerTypeName"),'SubLedgerName'=>new Expression("d.SubLedgerName"),"Credit" => new Expression("Case when a.TransType ='C' THEN a.Amount END")))
                                ->join(array("b" => "FA_AccountMaster "), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                                ->join(array("c" => "FA_SubLedgerType "), "a.SubLedgerTypeId=c.SubLedgerTypeId", array(), $select::JOIN_INNER)
                                ->join(array("d" => "FA_SubLedgerMaster "), "a.SubLedgerId=d.SubLedgerId", array(), $select::JOIN_INNER)
                                ->join(array("e" => "WF_CostCentre"), "a.CostCentreId=e.CostCentreId", array(), $select::JOIN_LEFT)
                                ->where(array("a.RecurringId=$recurringId"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $recurringList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $result['getRecurringTrans'] =$recurringList;

                            break;
                        default:
                            $result = '';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $result='';
                }

            }
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $JournalEntryId = $this->bsf->isNullCheck($postData['EntryId'], 'number');

                    $mode = "A";
                    $debitAmt = $this->bsf->isNullCheck($postData['DebitSumAMount'], 'number');
                    $creditAmt = $this->bsf->isNullCheck($postData['CreditSumAMount'], 'number');

                    $transCount = $this->bsf->isNullCheck($postData['rowid'], 'number');
                    $EntryType = $this->bsf->isNullCheck($postData['specJournalTypeChar'], 'string');
                    $RecurringId = $this->bsf->isNullCheck($postData['recurringType'], 'number');
                    $iAccIdInvoice = 0;
                    $iSubLedgerTypeIdInvoice = 0;
                    $iSubLedgerIdInvoice = 0;
                    $iCCInvoice = 0;
                    $iOPCCInvoice=0;
                    $iVendorTypeInvoice = 0;
                    $sVendorTypeInvoice = "";
                    $iServiceTypeIdInvoice = 0;
                    if ($EntryType == "I") {
                        $sTransType="P";
                        if(($debitAmt-$creditAmt)<0){
                            $sTransType="R";
                        }
                        $iAccIdInvoice = $this->bsf->isNullCheck($postData['invoiceAccountHeadId'], 'number');
                        $iSubLedgerTypeIdInvoice = $this->bsf->isNullCheck($postData['invoiceSubLedgerTypeId'], 'number');
                        $iSubLedgerIdInvoice = $this->bsf->isNullCheck($postData['invoiceSubLedgerId'], 'number');
                        $iCCInvoice = $this->bsf->isNullCheck($postData['invoiceCostCenterId'], 'number');
                        $iOPCCInvoice = $this->bsf->isNullCheck($postData['invoiceOPCCId'], 'number');
                        $iVendorTypeInvoice = $this->bsf->isNullCheck($postData['invoiceType'], 'number');
                        if ($iVendorTypeInvoice == 1) {
                            $sVendorTypeInvoice = "Supply";
                        } else if ($iVendorTypeInvoice == 2) {
                            $sVendorTypeInvoice = "Contract";
                        } else if ($iVendorTypeInvoice == 3) {
                            $sVendorTypeInvoice = "Service";
                        }
                        $iServiceTypeIdInvoice = $this->bsf->isNullCheck($postData['serviceGroup'], 'number');
                    }
                    if ($JournalEntryId != 0) {
                        $mode = "E";
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_EntryTrans')
                            ->where("RefType IN ('GJ','IJ','PJ','DN','CN','S') and RefId=$JournalEntryId ");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_JournalEntryTrans')
                            ->where("JournalEntryId=$JournalEntryId ");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_JournalEntryMaster')
                            ->set(array('JVDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                            , 'JVNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                            , 'JVBookNo' => ''
                            , 'JournalID' => $this->bsf->isNullCheck($postData['specJournalType'], 'number')
                            , 'Debit' => $debitAmt
                            , 'Credit' => $creditAmt
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'CompanyId' => $companyId
                            , 'JournalType' => $this->bsf->isNullCheck($postData['specJournalTypeChar'], 'string')
                            , 'AccountId' => $iAccIdInvoice
                            , 'SubLedgerTypeId' => $iSubLedgerTypeIdInvoice
                            , 'SubLedgerId' => $iSubLedgerIdInvoice
                            , 'CostCentreId' => $iCCInvoice
                            , 'VendorType' => $sVendorTypeInvoice
                            , 'ServiceTypeId' => $iServiceTypeIdInvoice
                            , 'Approve' => 'N'
                            , 'RecurringId' => $RecurringId
                            , 'IsAppReady' => 1));
                        $update->where(array('JournalEntryId' => $JournalEntryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryMaster');
                        $insert->Values(array('JVDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                        , 'JVNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                        , 'JVBookNo' => ''
                        , 'JournalID' => $this->bsf->isNullCheck($postData['specJournalType'], 'number')
                        , 'Debit' => $debitAmt
                        , 'Credit' => $creditAmt
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        , 'CompanyId' => $companyId
                        , 'JournalType' => $this->bsf->isNullCheck($postData['specJournalTypeChar'], 'string')
                        , 'AccountId' => $iAccIdInvoice
                        , 'SubLedgerTypeId' => $iSubLedgerTypeIdInvoice
                        , 'SubLedgerId' => $iSubLedgerIdInvoice
                        , 'CostCentreId' => $iCCInvoice
                        , 'VendorType' => $sVendorTypeInvoice
                        , 'ServiceTypeId' => $iServiceTypeIdInvoice
                        , 'Approve' => 'N'
                        , 'RecurringId' => $RecurringId
                        , 'IsAppReady' => 1));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $JournalEntryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    for ($i = 1; $i <= $transCount; $i++) {
                        $dAmt = 0;
                        $sType = "";
                        if ($this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number') == 0)
                            continue;

                        $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                        $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                        $dAmount = 0;
                        if ($dAmountDe != 0) {
                            $sType = "D";
                            $dAmt = $dAmountDe;
                        } else if ($dAmountCr != 0) {
                            $sType = "C";
                            $dAmt = $dAmountCr;
                        }

                        $insert = $sql->insert();
                        $insert->into('FA_JournalEntryTrans');
                        $insert->Values(array('JournalEntryId' => $JournalEntryId
                        , 'JVDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                        , 'SortRowID' => $i
                        , 'AccountId' => $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number')
                        , 'SubLedgerTypeId' => $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number')
                        , 'SubLedgerId' => $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number')
                        , 'TransType' => $sType
                        , 'Amount' => $dAmt
                        , 'Remarks' => ''
                        , 'CompanyId' => $companyId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $iDrCount = 0;
                    $iCrCount = 0;
                    $iFinalCount = 0;
                    $iAccountId = 0;
                    $iSLTypeId = 0;
                    $iSLId = 0;
                    $iCCId = 0;
                    $RefType = $this->bsf->isNullCheck($postData['specJournalTypeShort'], 'string');
                    for ($i = 1; $i <= $transCount; $i++) {
                        if ($this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number') == 0)
                            continue;

                        $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                        $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                        $dAmount = 0;
                        if ($dAmountDe > 0) {
                            $iDrCount = $iDrCount + 1;
                        } else if ($dAmountCr > 0) {
                            $iCrCount = $iCrCount + 1;
                        }
                        $iFinalCount = $iFinalCount + 1;
                    }
                    if($EntryType=="I"){
                        for ($i = 1; $i <= $iFinalCount; $i++) {
                            $TransType = "";
                            $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                            $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                            $Amount = 0;
                            if ($dAmountDe > 0) {
                                $TransType = "D";
                                $Amount = $dAmountDe;
                            } else if ($dAmountCr > 0) {
                                $TransType = "C";
                                $Amount = $dAmountCr;
                            }
                            //$RefType = "";
                            $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                            $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                            $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                            $RelatedAccountId = $iAccIdInvoice;
                            $RelatedSLTypeId = $iSubLedgerTypeIdInvoice;
                            $RelatedSLId = $iSubLedgerIdInvoice;
                            $CostCentreId = $iCCInvoice;

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $JournalEntryId
                                , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                , 'TransType' => $TransType
                                , 'RefType' => $RefType
                                , 'AccountId' => $AccountId
                                , 'RelatedAccountId' => $RelatedAccountId
                                , 'SubLedgerTypeId' => $SubLedgerTypeId
                                , 'SubLedgerId' => $SubLedgerId
                                , 'RelatedSLTypeId' => $RelatedSLTypeId
                                , 'RelatedSLId' => $RelatedSLId
                                , 'CostCentreId' => $CostCentreId
                                , 'Amount' => $Amount
                                , 'Remarks' => ''
                                , 'IRemarks' => ''
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'N'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($TransType == "C") {
                                $TransType = "D";
                            } else if ($dAmountCr > 0) {
                                $TransType = "C";
                            }
                            //$RefType = "";
                            $AccountId = $iAccIdInvoice;
                            $SubLedgerTypeId = $iSubLedgerTypeIdInvoice;
                            $SubLedgerId = $iSubLedgerIdInvoice;
                            $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                            $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' .$i], 'number');
                            $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                            $CostCentreId = $iCCInvoice;

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $JournalEntryId
                                , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                , 'TransType' => $TransType
                                , 'RefType' => $RefType
                                , 'AccountId' => $AccountId
                                , 'RelatedAccountId' => $RelatedAccountId
                                , 'SubLedgerTypeId' => $SubLedgerTypeId
                                , 'SubLedgerId' => $SubLedgerId
                                , 'RelatedSLTypeId' => $RelatedSLTypeId
                                , 'RelatedSLId' => $RelatedSLId
                                , 'CostCentreId' => $CostCentreId
                                , 'Amount' => $Amount
                                , 'Remarks' => ''
                                , 'IRemarks' => ''
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'N'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    } else {
                        // One Debit & One Credit
                        if ($iCrCount == $iDrCount && $iDrCount == 1) {
                            $TransType = "";
                            $dAmountDe = $this->bsf->isNullCheck($postData['debit_1'], 'number');
                            $dAmountCr = $this->bsf->isNullCheck($postData['credit_1'], 'number');
                            $Amount = 0;
                            if ($dAmountDe > 0) {
                                $TransType = "D";
                                $Amount = $dAmountDe;
                            } else if ($dAmountCr > 0) {
                                $TransType = "C";
                                $Amount = $dAmountCr;
                            }
                            //$RefType = "";
                            $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_1'], 'number');
                            $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_1'], 'number');
                            $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_1'], 'number');
                            $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_2'], 'number');
                            $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_2'], 'number');
                            $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_2'], 'number');
                            $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_1'], 'number');

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $JournalEntryId
                            , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                            , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                            , 'TransType' => $TransType
                            , 'RefType' => $RefType
                            , 'AccountId' => $AccountId
                            , 'RelatedAccountId' => $RelatedAccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId
                            , 'SubLedgerId' => $SubLedgerId
                            , 'RelatedSLTypeId' => $RelatedSLTypeId
                            , 'RelatedSLId' => $RelatedSLId
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => ''
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $dAmountDe = $this->bsf->isNullCheck($postData['debit_2'], 'number');
                            $dAmountCr = $this->bsf->isNullCheck($postData['credit_2'], 'number');
                            $TransType = "";
                            $Amount = 0;
                            if ($dAmountDe > 0) {
                                $TransType = "D";
                                $Amount = $dAmountDe;
                            } else if ($dAmountCr > 0) {
                                $TransType = "C";
                                $Amount = $dAmountCr;
                            }
                            $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_2'], 'number');
                            $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_2'], 'number');
                            $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_2'], 'number');
                            $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_1'], 'number');
                            $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_1'], 'number');
                            $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_1'], 'number');
                            $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_2'], 'number');

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $JournalEntryId
                            , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                            , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                            , 'TransType' => $TransType
                            , 'RefType' => $RefType
                            , 'AccountId' => $AccountId
                            , 'RelatedAccountId' => $RelatedAccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId
                            , 'SubLedgerId' => $SubLedgerId
                            , 'RelatedSLTypeId' => $RelatedSLTypeId
                            , 'RelatedSLId' => $RelatedSLId
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => ''
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        } else if ($iCrCount == $iDrCount && $iDrCount > 1) { // Equal Debit & Equal Credit (Must Equal Debit & Credit Amount row by row Dr Vs Cr)
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                if ($i % 2 != 0) {
                                    $TransType = "";
                                    $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                                    $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                                    $Amount = 0;
                                    if ($dAmountDe > 0) {
                                        $TransType = "D";
                                        $Amount = $dAmountDe;
                                    } else if ($dAmountCr > 0) {
                                        $TransType = "C";
                                        $Amount = $dAmountCr;
                                    }
                                    //$RefType = "";
                                    $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . ($i + 1)], 'number');
                                    $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . ($i + 1)], 'number');
                                    $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . ($i + 1)], 'number');
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $Amount
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $TransType = "";
                                    $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                                    $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                                    $Amount = 0;
                                    if ($dAmountDe > 0) {
                                        $TransType = "D";
                                        $Amount = $dAmountDe;
                                    } else if ($dAmountCr > 0) {
                                        $TransType = "C";
                                        $Amount = $dAmountCr;
                                    }
                                    //$RefType = "";
                                    $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . ($i - 1)], 'number');
                                    $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . ($i - 1)], 'number');
                                    $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . ($i - 1)], 'number');
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $Amount
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        } else if ($iCrCount > $iDrCount && $iDrCount == 1) {// One Debit  && Multi Credit
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                                if ($dAmountDe > 0) {
                                    $iCCId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');
                                    $iAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $iSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $iSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                }
                            }
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                                if ($dAmountCr > 0) {
                                    $TransType = "C";
                                    //$RefType = "";
                                    $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $RelatedAccountId = $iAccountId;
                                    $RelatedSLTypeId = $iSLTypeId;
                                    $RelatedSLId = $iSLId;
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dAmountCr
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                                if ($dAmountCr > 0) {
                                    $TransType = "D";
                                    //$RefType = "";
                                    $AccountId = $iAccountId;
                                    $SubLedgerTypeId = $iSLTypeId;
                                    $SubLedgerId = $iSLId;
                                    $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $iCCId
                                    , 'Amount' => $dAmountCr
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        } else if ($iCrCount < $iDrCount && $iCrCount == 1) {// One Credit  && Multi Debit
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountCr = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                                if ($dAmountCr > 0) {
                                    $iCCId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');
                                    $iAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $iSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $iSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                }
                            }
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                                if ($dAmountDe > 0) {
                                    $TransType = "D";
                                    //$RefType = "";
                                    $AccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $RelatedAccountId = $iAccountId;
                                    $RelatedSLTypeId = $iSLTypeId;
                                    $RelatedSLId = $iSLId;
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dAmountDe
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            for ($i = 1; $i <= $iFinalCount; $i++) {
                                $dAmountDe = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                                if ($dAmountDe > 0) {
                                    $TransType = "C";
                                    //$RefType = "";
                                    $AccountId = $iAccountId;
                                    $SubLedgerTypeId = $iSLTypeId;
                                    $SubLedgerId = $iSLId;
                                    $RelatedAccountId = $this->bsf->isNullCheck($postData['accountHeadId_' . $i], 'number');
                                    $RelatedSLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                                    $RelatedSLId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                                    $CostCentreId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $JournalEntryId
                                    , 'VoucherDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                                    , 'VoucherNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                                    , 'TransType' => $TransType
                                    , 'RefType' => $RefType
                                    , 'AccountId' => $AccountId
                                    , 'RelatedAccountId' => $RelatedAccountId
                                    , 'SubLedgerTypeId' => $SubLedgerTypeId
                                    , 'SubLedgerId' => $SubLedgerId
                                    , 'RelatedSLTypeId' => $RelatedSLTypeId
                                    , 'RelatedSLId' => $RelatedSLId
                                    , 'CostCentreId' => $iCCId
                                    , 'Amount' => $dAmountDe
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }
                    // Multi Debit  && Multi Credit Pending
                    if ($EntryType == "I") {
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_BillRegister')
                            ->where("ReferenceId=$JournalEntryId and RefType='IJ'");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $RefTypeId=1;
                        $debitAmt = $this->bsf->isNullCheck($postData['DebitSumAMount'], 'number');
                        $creditAmt = $this->bsf->isNullCheck($postData['CreditSumAMount'], 'number');
                        $dAmt=$debitAmt-$creditAmt;

                        $insert = $sql->insert();
                        $insert->into('FA_BillRegister');
                        $insert->Values(array( 'BillDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')))
                        , 'BillNo' => $this->bsf->isNullCheck($postData['voucherNo'], 'string')
                        , 'RefTypeId' => $RefTypeId
                        , 'ReferenceID' => $JournalEntryId
                        , 'AccountId' => $iAccIdInvoice
                        , 'SubLedgerId' => $iSubLedgerIdInvoice
                        , 'BillAmount' => $dAmt
                        , 'CostCentreId' => $iCCInvoice
                        , 'CompanyId' => $companyId
                        , 'FYearId' => $FYearId
                        , 'TransType' => $sTransType
                        , 'BillType' => 'B'
                        , 'RefNo' => $this->bsf->isNullCheck($postData['invoiceBillNo'], 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['invoiceBillDate'], 'date')))
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iBillId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if($sTransType=="R"){
                            $update = $sql->update();
                            $update->table('FA_BillRegister')
                                ->set(array('TransType' => 'R'
                                , 'Approve' => 'Y'));
                            $update->where(array('BillRegisterId' => $iBillId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $update = $sql->update();
                        $update->table('FA_BillRegister')
                            ->set(array('RefType' => 'IJ'
                            , 'OCCId' => $iOPCCInvoice));
                        $update->where(array('BillRegisterId' => $iBillId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_EntryTrans')
                            ->set(array('RefAmount' => $dAmt
                            , 'OCCId' => $iOPCCInvoice
                            , 'RefNo' => $this->bsf->isNullCheck($postData['invoiceBillNo'], 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['invoiceBillDate'], 'date')))
                            ));
                        $update->where("RefId=$JournalEntryId AND RefType='IJ'");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    if ($mode == "E") {
                        if($EntryType=="G") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-General-Journal-Edit', 'E', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="I") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Invoice-Journal-Edit', 'E', 'FA-Invoice Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="P") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Provisional-Journal-Edit', 'E', 'FA-Provisional Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="D") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Debit-Note-Edit', 'E', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="C") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Credit-Note-Edit', 'E', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        }
                    } else {
                        if($EntryType=="G") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-General-Journal-Add', 'N', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="I") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Invoice-Journal-Add', 'N', 'FA-Invoice Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="P") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Provisional-Journal-Add', 'N', 'FA-Provisional Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="D") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Debit-Note-Add', 'N', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        } else if($EntryType=="C") {
                            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'FA-Credit-Note-Add', 'N', 'FA-Special Journal Details', $JournalEntryId, 0, 0, 'FA', '', $userId, 0, 0);
                        }
                    }
                    if($mode != "E") {
                        if ($EntryType == "G") {
                            $aVNo = CommonHelper::getVoucherNo(603, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        } else if ($EntryType == "I") {
                            $aVNo = CommonHelper::getVoucherNo(605, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        } else if ($EntryType == "P") {
                            $aVNo = CommonHelper::getVoucherNo(604, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        } else if ($EntryType == "D") {
                            $aVNo = CommonHelper::getVoucherNo(606, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        } else if ($EntryType == "C") {
                            $aVNo = CommonHelper::getVoucherNo(607, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        }
                    }

                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'specialjournalregister'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("AccountId","AccountName"=>new Expression("b.AccountName"),
                    "AccountType"=>new Expression("b.AccountType"),"TypeId"=>new Expression("b.TypeId")
                ,"data"=>new Expression("b.AccountId"),"value"=>new Expression("b.AccountName")))
                ->where("a.CompanyId=$companyId and b.AccountType NOT IN ('BA','CA') and b.LastLevel='Y'")
                ->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $AccountHead= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->AccountHead=$AccountHead;//AccountHaed

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("data"=>new Expression("a.AccountID"),"value"=>new Expression("b.AccountName")
                ,"AccountType"=>new Expression("b.AccountType"),"TypeId"=>new Expression("b.TypeId")))
                ->where("b.TypeId IN (1,2,4,17,18,36,40,42,43,51) and a.CompanyId=$companyId and a.FYearId=$FYearId")
                ->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $invoiceAccountHeadList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->invoiceAccountHeadList=$invoiceAccountHeadList;  //invoice AccountHead -- single select

            $select = $sql->select();
            $select->from(array("a" => "WF_CostCentre"))
                ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName"),"StateId"))
                ->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ccList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ccList=$ccList;//Cost Center Load

            $select = $sql->select();
            $select->from(array("a" => "FA_JournalType"))
                ->columns(array("JournalId","JournalName","JournalType","ShortName"))
                ->where("a.IsDefault=1");
            $select->order(array("a.JournalId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $journalTypeList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->journalTypeList=$journalTypeList;

            $select = $sql->select();
            $select -> from(array("a" => "FA_JournalEntryMaster"))
                ->columns(array("JournalEntryId","JVNo","JVBookNo","JVDate"=>new Expression("FORMAT(a.JVDate, 'dd-MM-yyyy')")
                ,"JournalID","Narration","AccountId","SubLedgerTypeId","SubLedgerId","CostCentreId","ValidUpto","RefBillId"
                ,"VendorType","ServiceTypeId","IsAppReady","Approve","JournalType","ShortName"=>new Expression("c.ShortName")
                ,"OPCCCostCentreName"=>new Expression("i.CostCentreName"),"OPCCCostCentreId"=>new Expression("i.CostCentreId"),"RecurringId"))
                ->join(array("b" => "FA_BillRegister"), new Expression("a.JournalEntryId=b.ReferenceId AND a.JVDate=b.BillDate") , array("RefNo","BillNo","CreditDays","RefDate","OCCId"), $select::JOIN_LEFT)
                ->join(array("c" => "FA_JournalType"), new Expression("a.JournalId=c.JournalId") , array(), $select::JOIN_INNER)
                ->join(array("d" => "FA_AccountMaster "), "a.AccountId=d.AccountId", array("AccountName","TypeId"), $select::JOIN_LEFT)
                ->join(array("e" => "FA_SubLedgerType "), "a.SubLedgerTypeId=e.SubLedgerTypeId", array("SubLedgerTypeName"), $select::JOIN_LEFT)
                ->join(array("f" => "FA_SubLedgerMaster "), "a.SubLedgerId=f.SubLedgerId", array("SubLedgerName"), $select::JOIN_LEFT)
                ->join(array("h" => "WF_CostCentre "), "a.CostCentreId=h.CostCentreId", array("CostCentreName"), $select::JOIN_LEFT)
                ->join(array("i" => "WF_OperationalCostCentre"), "h.CostCentreId=i.FACostCentreId", array(), $select::JOIN_LEFT)
                ->join(array("j" => "FA_RecurringMaster"), "a.RecurringId=j.RecurringId", array('RecurringTypeName'), $select::JOIN_LEFT)
                ->where(array("JournalEntryId=$JournalEntryId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $journalDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $journalAccId=$journalDetail['AccountId'];
            $journalSLId=$journalDetail['SubLedgerId'];

            $journalInvoiceType='';
            if($journalAccId == 4) {

                $select = $sql->select();
                $select->from(array("a" => "FA_SubledgerMaster"))
                    ->join(array("b" => "Vendor_Master "), "b.VendorId=a.RefId", array(), $select::JOIN_INNER)
                    ->columns(array("VendorId" => new Expression("b.VendorId"), 'SubLedgerId', 'SubLedgerName'
                    , 'Contract' => new Expression("b.Contract"), 'Supply' => new Expression("b.Supply"), 'Service' => new Expression("b.Service")))
                    ->where("a.SubLedgerId=$journalSLId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $invoiceType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $invoiceList = array();
                if ($invoiceType['Supply'] == 1) {
                    $invoiceList[0]['InvoiceId'] = 1;
                    $invoiceList[0]['InvoiceType'] = 'Supply';
                }
                if ($invoiceType['Contract'] == 1) {
                    $invoiceList[1]['InvoiceId'] = 2;
                    $invoiceList[1]['InvoiceType'] = 'Contract';
                }
                if ($invoiceType['Service'] == 1) {
                    $invoiceList[2]['InvoiceId'] = 3;
                    $invoiceList[2]['InvoiceType'] = 'Service';

                    $select = $sql->select();
                    $select->from(array("a" => "Vendor_ServiceGroup"))
                        ->columns(array('ServiceGroupId','ServiceGroupName','ServiceTypeId'))
                        ->where("a.ServiceTypeId<>0")
                        ->order(array("a.ServiceGroupName"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $journalServiceGrp = $subLedgerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->journalServiceGrp =$journalServiceGrp;
                }
                $journalInvoiceType = $invoiceList;
            }

            $select = $sql->select();
            $select -> from(array("a" => "FA_JournalEntryTrans"))
                ->columns(array("JournalEntryId","AccountId","SubLedgerId","CostCentreId","SubLedgerTypeId","TransType","Debit" => new Expression("Case when a.TransType ='D' THEN a.Amount END")
                ,'AccountName'=>new Expression("b.AccountName"),'CostCentreName'=>new Expression("e.CostCentreName"),'SubLedgerTypeName'=>new Expression("c.SubLedgerTypeName"),'SubLedgerName'=>new Expression("d.SubLedgerName"),"Credit" => new Expression("Case when a.TransType ='C' THEN a.Amount END")))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                ->join(array("c" => "FA_SubLedgerType "), "a.SubLedgerTypeId=c.SubLedgerTypeId", array(), $select::JOIN_INNER)
                ->join(array("d" => "FA_SubLedgerMaster "), "a.SubLedgerId=d.SubLedgerId", array(), $select::JOIN_INNER)
                ->join(array("e" => "WF_CostCentre"), "a.CostCentreId=e.CostCentreId", array(), $select::JOIN_LEFT)
                ->where(array("a.JournalEntryId =$JournalEntryId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $journalList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $debitSum=0;
            $creditSum=0;
            foreach($journalList as $list){
                $debitSum=$debitSum+$list['Debit'];
                $creditSum=$creditSum+$list['Credit'];
            }

            $this->_view->journalList = $journalList;
            $this->_view->journalDetail = $journalDetail;
            $this->_view->JournalEntryId =$JournalEntryId;
            $this->_view->debitSum =$debitSum;
            $this->_view->creditSum =$creditSum;
            $this->_view->journalInvoiceType =$journalInvoiceType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function specialjournalregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    //$connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_JournalEntryMaster"))
                ->join(array("b" => "FA_JournalType "), "a.JournalId=b.JournalId", array(), $select::JOIN_INNER)
                ->columns(array("JournalEntryId","JVNo","JVDate"=>new Expression("FORMAT(a.JVDate, 'dd-MM-yyyy')")
                ,"JVType"=>new Expression("b.JournalName"),"RefBillId","Debit","Credit"));
            $select->where("a.CompanyId=$companyId ");//Not Invoice and b.JournalId Not in (2)
            $select->order(array(new Expression("a.JVDate,a.JVNo")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $entryList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->entryList=$entryList;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function transferregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');
        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
//                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            /*$select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("AccountId","CashBankName","CashOrBank","CompanyId"))
                ->where("a.CompanyId=$companyId");*/
            $select = $sql->select();
            $select->from(array("a" => "FA_CashBankDet"))
                ->columns(array("AccountId","AccountName" => new Expression("CashBankName"),"AccountType" => new Expression("CashOrBank"), 'CompanyId'));
            //$select->where->expression('a.CompanyId IN ?', array($subQuery));
            $select->where("a.CompanyId=$companyId and a.CashOrBank in ('B','C')");
            $statement = $sql->getSqlStringForSqlObject($select);
            $fromBookList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $selectGroup = $sql->select();
            $selectGroup->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                    ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                    ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                    ,'Debit' => new Expression("CASE WHEN a.EntryType IN ('R') THEN a.Amount ELSE 0 END")
                    ,'Credit' => new Expression("CASE WHEN a.EntryType IN ('P','T') THEN a.Amount ELSE 0 END")
                    ,'PDCDebit' => new Expression("CASE WHEN a.EntryType IN ('R') THEN CASE WHEN PDC=0 THEN a.Amount ELSE 0 END ELSE 0 END")
                    ,'PDCCredit' => new Expression("CASE WHEN a.EntryType IN ('P','T') THEN CASE WHEN PDC=0 THEN a.Amount ELSE 0 END ELSE 0 END")
                    ,'Narration','ChequeNo','ChequeDate','ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                    ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                    ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ))
                ->join(array('b' => 'FA_AccountMaster'), 'a.AccountID=b.AccountID', array(), $selectGroup::JOIN_LEFT)
                ->join(array('sl' => 'FA_SubLedgerMaster'), 'sl.SubLedgerId=a.SubLedgerId', array(), $selectGroup::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'a.CostCentreId=c.CostCentreId', array(), $selectGroup::JOIN_LEFT)
                ->where("a.BookID=$accountId AND a.CompanyId=$companyId AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectGroup->where("(((a.PDC=1 AND a.RefEntryId=0) OR (a.PDC=1 AND a.GPEntryId=0 )) OR a.PDC=0) ");

            $selectMulti = $sql->select();
            $selectMulti->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                    ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                    ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                    ,'Debit' => new Expression("a.Amount")
                    ,'Credit' => new Expression("1-1")
                    ,'PDCDebit' => new Expression("CASE WHEN PDC=0 THEN a.Amount ELSE 0 END")
                    ,'PDCCredit' => new Expression("1-1")
                    ,'Narration','ChequeNo','ChequeDate','ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                    ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                    ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ))
                ->join(array('b' => 'FA_AccountMaster'), 'a.BookId=b.AccountId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('sl' => 'FA_SubLedgerMaster'), 'sl.SubLedgerId=a.SubLedgerId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'a.CostCentreId=c.CostCentreId', array(), $selectMulti::JOIN_LEFT)
                ->where("a.AccountId=$accountId AND a.CompanyId=$companyId and a.JournalType<>'M' AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectMulti->where("(((a.PDC=1 AND a.RefEntryId=0) OR (a.PDC=1 AND a.GPEntryId=0 )) OR a.PDC=0) ");
            $selectMulti->combine($selectGroup,'Union ALL');

            $selectFinal = $sql->select();
            $selectFinal->from(array("g"=>$selectMulti))
                ->columns(array("EntryId","VoucherDate", "VoucherNo", "RefNo", "RelatedRefNo","AccountName","SubLedgerName"
                    ,"EntryType","JournalType"=> new Expression("RTRIM(LTRIM(g.JournalType))"),"Debit"=> new Expression("g.Debit-g.Others"),"Credit"=> new Expression("g.Credit")
                    ,"PDCDebit"=> new Expression("g.PDCDebit-g.Others"),"PDCCredit"=> new Expression("g.PDCCredit")
                    ,"Narration","ChequeNo","ChequeDate","ChequeDescription","CostCentreName","IsLock","PDC"
                    ,"IsAppReady","Approve","TypeId","SubLedgerTypeId"
                ));
            $selectFinal->order(new Expression("g.VoucherDate,g.VoucherNo"));
            $statement = $sql->getSqlStringForSqlObject($selectFinal);
            $regList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->FYearId=$FYearId;
            $this->_view->accountId=$accountId;
            $this->_view->companyId=$companyId;
            $this->_view->regList=$regList;
            $this->_view->fromBookList=$fromBookList;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function subledgerdetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

         /*   $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerMaster"))
                ->columns(array("SubLedgerTypeId","SubLedgerTypeName"=> new Expression("b.SubLedgerTypeName"),"SubLedgerId","SubLedgerName"
                ,"ParentAccountId"=> new Expression("b.SubLedgerTypeId")))
                ->join(array("b" => "FA_SubLedgerType"), "b.SubLedgerTypeId=a.SubLedgerTypeId", array(), $select::JOIN_INNER);
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $subledgerDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

            $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerType"))
                ->columns(array("SubLedgerTypeId","SubLedgerTypeName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $subLedgerTypeName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $i = 0;
            $arrUnitLists= array();
            $dumArr=array();

            foreach($subLedgerTypeName as &$subLedgerTypeNames) {
                $identID=$subLedgerTypeNames['SubLedgerTypeId'];
                $ParId=0;
                $i=$i+1;
                $j=$i;
                $dumArr = array(
                    'Id' => $j,
                    'ParentId' => $ParId,
                    'IdentityId' => $identID,
                    'Description' => $subLedgerTypeNames['SubLedgerTypeName']
                );
                $select = $sql->select();
                $select->from(array("a" => "FA_SubLedgerMaster"))
                    ->columns(array("SubLedgerId","SubLedgerName"))
                ->where("SubLedgerTypeId=$identID");
                $statement = $sql->getSqlStringForSqlObject($select);
                $subLedgerMatserName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ParId=$i;
                foreach($subLedgerMatserName as &$subLedgerMatserNames) {

                    $i=$i+1;
                    $k=$i;
                    $dumArr1=array();
                    $dumArr1 = array(
                        'Id' => $k,
                        'ParentId' => $ParId,
                        'IdentityId' => $subLedgerMatserNames['SubLedgerId'],
                        'Description' => $subLedgerMatserNames['SubLedgerName']
                    );
                    $arrUnitLists[] =$dumArr1;
                }
                $arrUnitLists[] =$dumArr;
            }
            $this->_view->arrUnitLists=$arrUnitLists;
            /*echo '<pre>';
            print_r($arrUnitLists);
            echo '</pre>';
            die;*/
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function recurringentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $recurringId = $this->bsf->isNullCheck($this->params()->fromRoute('recurringId'),'number');

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $type= $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type){
                        case 'getSubLedgerType':
                            $AccountTypeId= $this->bsf->isNullCheck($postData['AccountTypeId'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_SLAccountType"))
                                ->join(array("b" => "FA_AccountType "), "a.TypeId=b.TypeId", array(), $select::JOIN_INNER)
                                ->join(array("c" => "FA_SubLedgerType "), "c.SubLedgerTypeId=a.SLTypeId ", array(), $select::JOIN_INNER)
                                ->columns(array("TypeId","SLTypeId","SubLedgerType"=>new Expression("c.SubLedgerType"),"TypeName"=>new Expression("b.TypeName")
                                ,"data"=>new Expression("a.SLTypeId"),"value"=>new Expression("c.SubLedgerTypeName")))
                                ->where("a.TypeId=$AccountTypeId")//SubLedgerTypeId NOT IN (6,7)
                                ->order(array("b.TypeName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['subLedgerType']=$subledgerType= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;
                        case 'getSubLedgerName':
                            $SubLedgerTypeId= $this->bsf->isNullCheck($postData['SubLedgerTypeId'], 'number');

                            $select = $sql->select();
                            $select->from(array("SLM" => "FA_SubLedgerMaster"))
                                ->columns(array("SubLedgerId", "SubLedgerName", "SubLedgerTypeId", "RefId", "ServiceTypeId"
                                , "data" => new Expression("SLM.SubLedgerId"), "value" => new Expression("SLM.SubLedgerName")))
                                ->where("SLM.SubLedgerTypeId=$SubLedgerTypeId")
                                ->order(array("SLM.SubLedgerName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result['subLedgerName'] = $subLedgerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;
                        default:
                            $result = '';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $recurringId = $this->bsf->isNullCheck($postData['recurringId'], 'number');

                    $recTypeName=$this->bsf->isNullCheck($postData['recurringTypeName'], 'string');
                    $recType=$this->bsf->isNullCheck($postData['recurringType'], 'string');
                    $recCompanyId=$this->bsf->isNullCheck($postData['companyId'], 'string');
                    $firstDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['firstRun'], 'date')));
                    $lastDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['lastRun'], 'date')));
                    $IntervalType=$this->bsf->isNullCheck($postData['intervalTime'], 'string');
                    $IntervalNos=$this->bsf->isNullCheck($postData['intervalNo'], 'string');
                    $MonthDay=$this->bsf->isNullCheck($postData['monthDay'], 'number');
                    $WeekDay=$this->bsf->isNullCheck($postData['weekDay'], 'string');
                    $RunningType=$this->bsf->isNullCheck($postData['runningType'], 'string');
                    $BookId=$this->bsf->isNullCheck($postData['bookId'], 'number');
                    $narration=$this->bsf->isNullCheck($postData['narration'], 'string');
                    if($recurringId!=0){
                        //delete Trans
                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_RecurringTrans')
                            ->where("RecurringId=$recurringId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_RecurringMaster')
                            ->set(array('RecurringTypeName' => $recTypeName
                            , 'RecurringType' => $recType
                            , 'CompanyId' => $recCompanyId
                            , 'FirstDate' => $firstDate
                            , 'LastDate' => $lastDate
                            , 'IntervalType' => $IntervalType
                            , 'IntervalNos' => $IntervalNos
                            , 'MonthDay' => $MonthDay
                            , 'WeekDay' => $WeekDay
                            , 'RuningType' => $RunningType
                            , 'BookId' => $BookId
                            , 'Narration' => $narration
                            ))
                            ->where(array('RecurringId' => $recurringId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iRecurringId=$recurringId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_RecurringMaster');
                        $insert->Values(array('RecurringTypeName' => $recTypeName
                        , 'RecurringType' => $recType
                        , 'CompanyId' => $recCompanyId
                        , 'FirstDate' => $firstDate
                        , 'LastDate' => $lastDate
                        , 'IntervalType' => $IntervalType
                        , 'IntervalNos' => $IntervalNos
                        , 'MonthDay' => $MonthDay
                        , 'WeekDay' => $WeekDay
                        , 'RuningType' => $RunningType
                        , 'BookId' => $BookId
                        , 'Narration' => $narration));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iRecurringId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $rowId=$this->bsf->isNullCheck($postData['rowid'], 'number');

                    for($i=1;$i<=$rowId;$i++) {
                        $Amount=0;
                        $transType="";
                        $accountHeadId=$this->bsf->isNullCheck($postData['accountHeadId_'.$i], 'number');
                        $subLedgerTypeId=$this->bsf->isNullCheck($postData['subLedgerTypeId_'.$i], 'number');
                        $subLedgerId=$this->bsf->isNullCheck($postData['subLedgerId_'.$i], 'number');
                        $costCenterId=$this->bsf->isNullCheck($postData['costCenterId_'.$i], 'number');
                        $debit=$this->bsf->isNullCheck($postData['debit_'.$i], 'number');
                        $credit=$this->bsf->isNullCheck($postData['credit_'.$i], 'number');
                        if($debit!=0){
                            $Amount=$debit;
                            $transType="D";
                        } else if($credit!=0) {
                            $Amount = $credit;
                            $transType = "C";
                        }
                        if($accountHeadId == 0 || $Amount==0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('FA_RecurringTrans');
                        $insert->Values(array('RecurringId' => $iRecurringId
                        , 'AccountId' => $accountHeadId
                        , 'SubLedgerTypeId' => $subLedgerTypeId
                        , 'SubLedgerId' => $subLedgerId
                        , 'CostCentreId' => $costCenterId
                        , 'TransType' => $transType
                        , 'Amount' => $Amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'recurringmaster'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster "), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array("AccountId","AccountName"=>new Expression("b.AccountName"),
                    "AccountType"=>new Expression("b.AccountType"),"TypeId"=>new Expression("b.TypeId")
                ,"data"=>new Expression("b.AccountId"),"value"=>new Expression("b.AccountName")))
                ->where("a.CompanyId=$companyId and b.AccountType NOT IN ('BA','CA') and b.LastLevel='Y'")
                ->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $AccountHead= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->AccountHead=$AccountHead;//AccountHead

            $select = $sql->select();
            $select->from(array("a" => "WF_CostCentre"))
                ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName"),"StateId"))
                ->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ccList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ccList=$ccList;//Cost Center Load

            $select = $sql->select();
            $select->from(array("a" => "FA_JournalType"))
                ->columns(array("JournalId","JournalName","JournalType","ShortName"))
                ->where('JournalId IN (1,3)')
                ->order(array("a.JournalId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $bookNames= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->bookNames=$bookNames;

            if($recurringId !=0){
                $select = $sql->select();
                $select->from(array("a" => "FA_RecurringMaster"))
                    ->join(array("b" => "FA_JournalType"), "a.BookId=b.JournalId", array(), $select::JOIN_INNER)
                    ->columns(array('RecurringId',"RecurringTypeName","RecurringType",'CompanyId','IntervalType','IntervalNos','MonthDay','WeekDay'
                    ,"FirstDate" => new Expression("FORMAT(a.FirstDate, 'dd-MM-yyyy')"),"LastDate" => new Expression("FORMAT(a.LastDate, 'dd-MM-yyyy')")
                    ,'RuningType','BookId','Narration','JournalName'=>new Expression("b.JournalName")))
                    ->where("a.RecurringId=$recurringId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $recurringList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->recurringList=$recurringList;

                $select = $sql->select();
                $select -> from(array("a" => "FA_RecurringTrans"))
                    ->columns(array("RecurringTransId","AccountId","SubLedgerId","CostCentreId","SubLedgerTypeId","TransType","Debit" => new Expression("Case when a.TransType ='D' THEN a.Amount END")
                    ,'AccountName'=>new Expression("b.AccountName"),'CostCentreName'=>new Expression("e.CostCentreName"),'SubLedgerTypeName'=>new Expression("c.SubLedgerTypeName"),'SubLedgerName'=>new Expression("d.SubLedgerName"),"Credit" => new Expression("Case when a.TransType ='C' THEN a.Amount END")))
                    ->join(array("b" => "FA_AccountMaster "), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "FA_SubLedgerType "), "a.SubLedgerTypeId=c.SubLedgerTypeId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "FA_SubLedgerMaster "), "a.SubLedgerId=d.SubLedgerId", array(), $select::JOIN_INNER)
                    ->join(array("e" => "WF_CostCentre"), "a.CostCentreId=e.CostCentreId", array(), $select::JOIN_LEFT)
                    ->where(array("a.RecurringId=$recurringId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $accountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->accountList=$accountList;
            }

            $this->_view->recurringId=$recurringId;
            $this->_view->companyId=$companyId;
            $this->_view->FYearId=$FYearId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function recurringmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_RecurringMaster"))
                ->join(array("b" => "FA_JournalType"), "a.BookId=b.JournalId", array(), $select::JOIN_INNER)
                ->columns(array('RecurringId',"RecurringTypeName","RecurringType",'CompanyId','IntervalType' => new Expression("case when a.IntervalType='W' then 'Week'  else 'Month' end")
                ,'IntervalNos','MonthDay','WeekDay'
                ,"FirstDate" => new Expression("FORMAT(a.FirstDate, 'dd-MM-yyyy')"),"LastDate" => new Expression("FORMAT(a.LastDate, 'dd-MM-yyyy')")
                  ,'RuningType' => new Expression("case when a.RuningType='M' then 'Manual'  else 'Auto' end")
                ,'BookId','Narration','JournalName'=>new Expression("b.JournalName")))
                ->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $recurringList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->recurringList=$recurringList;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function chequenodetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $chequeId = $this->bsf->isNullCheck($this->params()->fromRoute('chequeId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest()) {
            $postData = $request->getPost();

            return $response;
        } else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $chequeId = $this->bsf->isNullCheck($postData['ChequeId'], 'number');
                    $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                    //echo $accountId;die;
                    $transCount = $this->bsf->isNullCheck($postData['rowSize'], 'number');
                    for ($i = 1; $i <= $transCount; $i++) {
                        $ChequeTransId = 0;
                        $checkUsedVal=0;
                        $checkUsedVal=0;
                        $remarks="";
                        $cancelDate=null;
                        $ChequeTransId=$this->bsf->isNullCheck($postData['ChequeTransId_' . $i], 'number');
                        $checkCancelVal = $this->bsf->isNullCheck($postData['checkCancel_'.$i], 'number');
                        $checkUsedVal = $this->bsf->isNullCheck($postData['checkUsed_'.$i], 'number');
                        if($checkCancelVal==1){
                            $remarks = $this->bsf->isNullCheck($postData['Remarks_'.$i], 'string');
                            $cancelDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_'.$i], 'date')));
                        }

                        $update = $sql->update();
                        $update->table('FA_ChequeTrans')
                            ->set(array('Used' => $checkUsedVal
                            , 'Cancel' => $checkCancelVal
                            , 'CancelRemarks' => $remarks
                            , 'CancelDate' => $cancelDate
                            ))
                            ->where(array('ChequeTransId' => $ChequeTransId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'chequedetail', 'accountId' => $accountId));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_ChequeMaster"))
                ->join(array("b" => "FA_ChequeTrans"), "a.ChequeID=b.ChequeID", array(), $select::JOIN_INNER)
                ->columns(array("ChequeId","ChequeTransId" => new Expression("b.ChequeTransId"),"ChequeNo" => new Expression("b.ChequeNo")
                ,"Used" => new Expression("b.Used"),"Cancel" => new Expression("b.Cancel")
                , "CancelDate" => new Expression("FORMAT(b.CancelDate, 'dd-MM-yyyy')"), "Remarks" => new Expression("b.CancelRemarks")
               ));
            $select->where("a.ChequeId=$chequeId and a.DeleteFlag=0");
            $select->group(new Expression('a.NoOfLeaves,b.Used,b.cancel,a.ChequeID,b.ChequeTransId,b.ChequeNo,b.CancelDate,b.CancelRemarks'));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $cheqTransList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->cheqTransList = $cheqTransList;
            $this->_view->chequeId = $chequeId;
            $this->_view->accountId = $accountId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function journaltypemasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $select = $sql->select();
            $select->from(array("a" => "FA_JournalType"))
                ->columns(array("JournalId","JournalName", "JournalType", "IsDefault" , "ShortName" ));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $result=$journaldetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $response->setContent(json_encode($result));
            return $response;
        }else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_JournalType"))
                ->columns(array("JournalId","JournalName", "JournalType", "IsDefault" , "ShortName" ));
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $journaldetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->journaldetList = $journaldetList;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function journaltypeentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type=='getLoadDetails'){

                $journalId = $this->bsf->isNullCheck($postData['journalId'], 'number');
                $select = $sql->select();
                $select->from(array("a" => "FA_JournalType"))
                    ->columns(array("JournalId","JournalName", "JournalType", "IsDefault" , "ShortName" ));
                $select->where("a.IsDefault=1");
                $statement = $sql->getSqlStringForSqlObject($select);
                $journaltypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->journaltypeList = $journaltypeList;
                $journaltype="G";
                $sysUpdateYes=1;
                if ($journalId != 0) {
                    $select = $sql->select();
                    $select->from(array("a" => "FA_JournalType"))
                        ->columns(array("JournalId","JournalName", "JournalType", "IsDefault" , "ShortName" ));
                    $select->where("a.JournalId=$journalId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $journalentryList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $journaltype=$journalentryList['JournalType'];
                    if($journalentryList['IsDefault']==1){
                        $sysUpdateYes=0;
                    }
                    $this->_view->journalentryList = $journalentryList;
                }
                $this->_view->journaltype=$journaltype;
                $this->_view->sysUpdateYes=$sysUpdateYes;
                $this->_view->journalId = $journalId;
            }else if($type=='addEditDetails'){

                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $mode="A";
                    $journalId = $this->bsf->isNullCheck($postData['journalId'], 'number');
                    if ($journalId != 0) {
                        $mode="E";
                        $update = $sql->update();
                        $update->table('FA_JournalType')
                            ->set(array('JournalName' => $this->bsf->isNullCheck($postData['journalName'], 'string')
                            , 'JournalType' => $this->bsf->isNullCheck($postData['journalType'], 'string')
                            , 'ShortName' => $this->bsf->isNullCheck($postData['shortName'], 'string')
                            ))
                            ->where(array('JournalId' => $journalId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_JournalType');
                        $insert->Values(array('JournalName' => $this->bsf->isNullCheck($postData['journalName'], 'string')
                        , 'JournalType' => $this->bsf->isNullCheck($postData['journalType'], 'string')
                        , 'ShortName' => $this->bsf->isNullCheck($postData['shortName'], 'string')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //$journalId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $this->_view->setTerminal(true);
            return $this->_view;
        } else {
            if ($request->isPost()) {

            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function ccbalanceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $accId = $this->bsf->isNullCheck($this->params()->fromRoute('accId'),'number');
        $g_lCNYearId = $this->bsf->isNullCheck($this->params()->fromRoute('g_lCNYearId'),'number');

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode());
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                    $g_lCNYearId = $this->bsf->isNullCheck($postData['g_lCNYearId'], 'number');
                    $bSuccess=false;
                    $select = $sql->select();
                    $select->from(array("a" => "FA_CCAccount"))
                        ->columns(array("rowCount"=> new Expression("count(a.ParentAccountId)")));
                    $select->where("a.CompanyId=$companyId AND SubledgerId<>0 AND ParentAccountId=$accountId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $CCAccountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $rowCount=$CCAccountList['rowCount'];
                    if($rowCount!=0){
                        //Sub-Ledger detail found..
                        return  $bSuccess;
                    }

                    $deleteFiscalTrans = $sql->delete();
                    $deleteFiscalTrans->from('FA_CCAccount')
                        ->where("OpeningBalance=0 AND SubledgerId=0 AND ParentAccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $dTotAmt=0;
                    $rowSize =$this->bsf->isNullCheck($postData['rowid'], 'number');
                    for($i=1; $i <=$rowSize; $i++ ) {
                        $debitAmt=0;
                        $creditAmt = 0;
                        $ccId = $this->bsf->isNullCheck($postData['costCenterId_' . $i], 'number');
                        $debitAmt = $this->bsf->isNullCheck($postData['debit_' . $i], 'number');
                        $creditAmt = $this->bsf->isNullCheck($postData['credit_' . $i], 'number');
                        if($ccId ==0 && ($debitAmt == 0 || $creditAmt == 0)){
                            continue;
                        }
                        $dBalAmt= $debitAmt - $creditAmt;
                        $dTotAmt = $dTotAmt + $debitAmt - $creditAmt;
                        //Delete
                        $deleteFiscalTrans = $sql->delete();
                        $deleteFiscalTrans->from('FA_CCAccount')
                            ->where("ParentAccountId=$accountId AND CostCentreId=$ccId AND CompanyId=$companyId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //Insert
                        $select = $sql->select();
                        $select->columns(array( 'ParentAccountId' => new Expression("$accountId") ,'CostCentreId' => new Expression("$ccId")
                        ,'OpeningBalance' => new Expression("'$dBalAmt'") ,'CompanyId' => new Expression("$companyId") ));

                        $insert = $sql->insert();
                        $insert->into('FA_CCAccount');
                        $insert->columns(array('ParentAccountId', 'CostCentreId', 'OpeningBalance', 'CompanyId'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $deleteFiscalTrans = $sql->delete();
                    $deleteFiscalTrans->from('FA_CCAccount')
                        ->where("OpeningBalance=0 AND SubledgerId=0 AND ParentAccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteFiscalTrans);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*
                     * UPDATE Account SET OpeningBalance=SummedBal, FromCC=1, FromSL=0 FROM dbo.Account A JOIN " +
                           "(SELECT ParentAccountId,SUM(OpeningBalance) SummedBal FROM dbo.CCAccount " +
                           "WHERE CompanyId=" + BsfGlobal.g_lCompanyId + "  AND ParentAccountId=" + CompanyAccountBL.AccountID + " GROUP BY ParentAccountId) B " +
                           "ON A.AccountId=B.ParentAccountId WHERE A.AccountId=" + CompanyAccountBL.AccountID + " AND A.CompanyId=" + BsfGlobal.g_lCompanyId
                     */

                    $update = $sql->update();
                    $update->table("FA_Account")
                        ->set(array('OpeningBalance' => new Expression ("SummedBal, FromCC=1, FromSL=0 FROM dbo.FA_Account A JOIN
                            (SELECT ParentAccountId,SUM(OpeningBalance) SummedBal FROM dbo.FA_CCAccount
                            WHERE CompanyId=$companyId  AND ParentAccountId=$accountId GROUP BY ParentAccountId) B
                            ON A.AccountId=B.ParentAccountId WHERE A.AccountId=$accountId AND A.CompanyId=$companyId")));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //Update Account Set OpeningBalance = {0} Where AccountId = {1} and CompanyId = {2}
                    $update = $sql->update();
                    $update->table("FA_Account")
                        ->set(array('OpeningBalance' => $dTotAmt ));
                    $update->where("AccountId=$accountId and FYearId=$fYearId and CompanyId=$companyId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    $bSuccess = true;
                    if($g_lCNYearId!=0){
                        $this->Update_Transfer_Balance($companyId,$fYearId,$accountId,$dbAdapter);
                    }
                    $this->redirect()->toRoute('fa/default' , array('controller' => 'index', 'action' => 'companyaccountdet'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }


            $selectCCT = $sql->select();
            $selectCCT->columns(array(new Expression("SELECT CCT.CostCentreId,CCT.CostCentreName,Balance=(
                        SELECT CAST(ISNULL(SUM(OpeningBalance),0) AS decimal(18,3)) FROM FA_CCAccount CCA
                        WHERE CCA.CostCentreId=CCT.CostCentreId AND CCA.ParentAccountId=$accId
                        AND CCA.SubledgerId=0 AND CCA.CompanyId=$companyId) FROM WF_CostCentre CCT
                        WHERE CCT.CompanyId=$companyId ")));

           /* $selectCCA = $sql->select();
            $selectCCA->from(array("CCA" => "FA_CCAccount"))
                ->columns(array("CostCentreId"=>new Expression("1-1"),'CostCentreName'=>new Expression("'(None)'"),"Balance"=>new Expression("CAST(ISNULL(SUM(OpeningBalance),0) as decimal(18,3))")))
                ->where("CCA.CostCentreId=0 AND CCA.ParentAccountId=$accId AND CCA.SubledgerId=0 AND CCA.CompanyId=$companyId");
            $selectCCA->combine($selectCCT,'Union ALL');*/


            $selectFinal = $sql->select();
            $selectFinal->from(array("g" => $selectCCT))
                ->columns(array("CostCentreId","CostCentreName","Debit"=>new Expression("case when g.Balance >= 0 then Balance else 0 end")
                ,"Credit"=>new Expression("case when g.Balance < 0  then -1*Balance else 0 end") ))
                ->order(array("CostCentreName"));
            $statement  = $sql->getSqlStringForSqlObject($selectFinal);
            $statementFin = preg_replace('/SELECT SELECT/','SELECT',$statement,1);

            $costCenterList = $dbAdapter->query($statementFin, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->costCenterList = $costCenterList;
            $this->_view->accountId = $accId;
            $this->_view->g_lCNYearId = $g_lCNYearId;
            $this->_view->curCompanyId = $companyId;
            $this->_view->curFYearId = $fYearId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function Update_Transfer_Balance($companyId,$fYearId,$accountId,$dbAdapter) {
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "FA_FiscalYear"))
            ->join(array("b" => "FA_FiscalYear"), "a.NFYearId=b.FYearId", array(), $select::JOIN_INNER)
            ->join(array("c" => "FA_FiscalYearTrans"), "c.FYearId=b.FYearId", array(), $select::JOIN_INNER)
            ->columns(array("FYearId" => new Expression("b.FYearId"),"PFYearId"));
        $select->where("c.CompanyId=$companyId AND A.FYearId>= $fYearId");
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        /*
         SELECT B.FYearId,B.DBName,OldDBName=A.DBName,A.PFYearId FROM [" + BsfGlobal.g_sFaDBName + "].dbo.FiscalYear A
        INNER JOIN [" + BsfGlobal.g_sFaDBName + "].dbo.FiscalYear B ON A.NFYearId=B.FYearId " +
           "INNER JOIN [" + BsfGlobal.g_sFaDBName + "].dbo.FiscalYearTrans C ON C.FYearId=B.FYearId " +
           "WHERE C.CompanyId=" + arg_iCompId + " AND A.FYearId>=" + arg_iFYId
         */
        if(count($result)>0) {
            foreach($result as $result1) {
                $iNFYearId = $result1['FYearId'];
                $iPFYearId = $result1['PFYearId'];

                //SELECT * FROM [" + BsfGlobal.g_sFaDBName + "].dbo.FiscalYearTrans WHERE Freeze=1 AND CompanyId=" + arg_iCompId + " AND FYearId=" + iNFYearId
                $bLock = false;
                $select = $sql->select();
                $select->from(array("a" => "FA_FiscalYearTrans"))
                    ->columns(array("rowCount"=> new Expression("count(a.FYearTransId)")));
                $select->where("Freeze=1 AND a.CompanyId=$companyId AND FYearId=$iNFYearId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $FYList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $rowCount=$FYList['rowCount'];
                if($rowCount!=0){
                    $bLock = true;
                }

                if ($bLock == false){
                    //DELETE FROM [{0}].dbo.Account WHERE CompanyId={1} AND AccountId={2}
                    $deleteAcc = $sql->delete();
                    $deleteAcc->from('FA_Account')
                        ->where("CompanyId=$companyId AND AccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteAcc);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //DELETE FROM [{0}].dbo.CCAccount WHERE CompanyId={1} AND ParentAccountId={2}
                    $deleteCCAcc = $sql->delete();
                    $deleteCCAcc->from('FA_CCAccount')
                        ->where("CompanyId=$companyId AND ParentAccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteCCAcc);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //DELETE FROM [{0}].dbo.CCBalance WHERE CompanyId={1} AND AccountId={2}
                    $deleteCCBal = $sql->delete();
                    $deleteCCBal->from('FA_CCBalance')
                        ->where("CompanyId=$companyId AND AccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteCCBal);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //DELETE FROM [{0}].dbo.CCSLBalance WHERE CompanyId={1} AND AccountId={2}
                    $deleteCCSLBal = $sql->delete();
                    $deleteCCSLBal->from('FA_CCSLBalance')
                        ->where("CompanyId=$companyId AND AccountId=$accountId");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteCCSLBal);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Account - Closing Balance --> Company
                    /*
                     * INSERT INTO [{0}]..Account(AccountId,CompanyId,OpeningBalance) " +
                      "SELECT AccountId,{1}, SUM(Amount)[O/B] FROM (" +

                      "SELECT ET.AccountId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                      "FROM [{2}].dbo.EntryTrans ET
                    INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                      "WHERE ET.Approve='Y' AND AM.GroupId NOT IN (3,4) AND ET.CompanyId= {1} AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.AccountId={4}
                    GROUP BY ET.AccountId " +
                      "UNION ALL " +
                      "SELECT A.ParentAccountID, SUM(OpeningBalance) Amount FROM [{2}]..CCAccount A " +
                      "INNER JOIN [{3}]..AccountMaster AM ON A.ParentAccountID=AM.AccountId " +
                      "WHERE AM.GroupId NOT IN (3,4) AND A.CompanyId={1} AND A.ParentAccountID={4} " +
                      "GROUP BY A.ParentAccountID,A.CompanyId) A GROUP BY AccountId ORDER BY AccountId
                     */
                    $selectFill = $sql->select();
                    $selectFill->from(array("ET" => "FA_EntryTrans"))
                        ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                        ->columns(array("AccountId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                    $selectFill->where("ET.Approve='Y' AND AM.GroupId NOT IN (3,4) AND ET.CompanyId=$companyId AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.AccountId=$accountId");
                    $selectFill->group(new Expression('ET.AccountId'));

                    $select1 = $sql->select();
                    $select1->from(array('A' => 'FA_CCAccount'))
                        ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountID=AM.AccountId", array(), $select1::JOIN_INNER)
                        ->columns(array("ParentAccountID", "Amount" => new Expression("SUM(OpeningBalance)")));
                    $select1->where("AM.GroupId NOT IN (3,4) AND A.CompanyId=$companyId AND A.ParentAccountID=$accountId");
                    $select1->group(new Expression('A.ParentAccountID,A.CompanyId'));
                    $select1->combine($selectFill, 'Union ALL');

                    $select->from(array("g" => $select1))
                        ->columns(array("AccountId" => new Expression("g.AccountId"),"CompanyId" => new Expression("$companyId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                    $select->group(new Expression('g.AccountId'));
                    $select->order("g.AccountId");

                    $insert = $sql->insert();
                    $insert->into('FA_Account');
                    $insert->columns(array('AccountId', 'CompanyId', 'OpeningBalance'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //CC Account - Cost Centre wise Sub Ledger Closing Balance --> Company
                    /*
                     * INSERT INTO [{0}]..CCAccount(ParentAccountId,SubledgerId,CompanyId,CostCentreId,OpeningBalance) " +
                     *
                     "SELECT AccountId,SubLedgerId,{1},CostCentreId, SUM(Amount)[O/B] FROM (" +
                     "SELECT ET.AccountId,ET.SubLedgerId,ET.CostCentreId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                     "FROM [{2}]..EntryTrans ET INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                     "WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND AM.AccountType NOT IN ('BA','CA') AND AM.GroupId NOT IN (3,4) AND ET.CompanyId= {1} AND ET.CostCentreId IN (" +
                     "SELECT CostCentreId FROM [{0}]..CostCentreTrans WHERE CompanyId={1}) AND ET.AccountId={4} " +
                     "GROUP BY ET.AccountId,ET.SubLedgerId,CostCentreId " +
                     "UNION ALL " +
                     "SELECT A.ParentAccountId,A.SubledgerId,A.CostCentreId, SUM(OpeningBalance) Amount FROM [{2}]..CCAccount A " +
                     "INNER JOIN [{3}]..AccountMaster AM ON A.ParentAccountId=AM.AccountId " +
                     "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId NOT IN (3,4) AND A.CompanyId= {1} AND A.CostCentreId IN (" +
                     "SELECT CostCentreId FROM [{0}]..CostCentreTrans WHERE CompanyId={1}) AND A.ParentAccountId={4} " +
                     "GROUP BY A.ParentAccountId,A.SubledgerId,CostCentreId " +
                     ") A GROUP BY AccountId,SubLedgerId,CostCentreId ORDER BY AccountId,SubLedgerId,CostCentreId
                     */
                    $selectFill = $sql->select();
                    $selectFill->from(array("ET" => "FA_EntryTrans"))
                        ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                        ->columns(array("AccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                    $selectFill->where("ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND AM.AccountType NOT IN ('BA','CA') AND AM.GroupId NOT IN (3,4) AND ET.CompanyId=$companyId AND ET.AccountId=$accountId");
                    $selectFill->group(new Expression('ET.AccountId,ET.SubLedgerId,CostCentreId'));

                    $select1 = $sql->select();
                    $select1->from(array('A' => 'FA_CCAccount'))
                        ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountId=AM.AccountId", array(), $select1::JOIN_INNER)
                        ->columns(array("ParentAccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(OpeningBalance)")));
                    $select1->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId NOT IN (3,4) AND A.CompanyId=$companyId AND A.ParentAccountID=$accountId");
                    $select1->group(new Expression('A.ParentAccountId,A.SubledgerId,CostCentreId'));
                    $select1->combine($selectFill, 'Union ALL');

                    $select->from(array("g" => $select1))
                        ->columns(array("AccountId" => new Expression("g.AccountId"),"SubLedgerId" => new Expression("g.SubLedgerId")
                        ,"CompanyId" => new Expression("$companyId"),"CostCentreId" => new Expression("g.CostCentreId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                    $select->group(new Expression('g.AccountId,g.SubLedgerId,g.CostCentreId'));
                    $select->order("g.AccountId,g.SubLedgerId,g.CostCentreId");

                    $insert = $sql->insert();
                    $insert->into('FA_CCAccount');
                    $insert->columns(array('ParentAccountId','SubledgerId','CompanyId','CostCentreId','OpeningBalance'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    if ($iPFYearId == 0){
                    /*
                     * INSERT INTO [{0}]..CCBalance(AccountId,CompanyId,CostCentreId,CurrentBalance) " +
                        "SELECT AccountId,{1},CostCentreId, SUM(Amount)[O/B] FROM (" +
                        "SELECT ET.AccountId,ET.CostCentreId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                        "FROM [{2}]..EntryTrans ET INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                        "WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId= {1} AND ET.CostCentreId IN (" +
                        "SELECT CostCentreId FROM [{0}]..CostCentreTrans WHERE CompanyId={1}) AND ET.AccountId={4} " +
                        "GROUP BY ET.AccountId,CostCentreId " +
                        "UNION ALL " +
                        "SELECT A.ParentAccountId,A.CostCentreId, SUM(OpeningBalance) Amount FROM [{2}]..CCAccount A " +
                        "INNER JOIN [{3}]..AccountMaster AM ON A.ParentAccountId=AM.AccountId " +
                        "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId= {1} AND A.CostCentreId IN (" +
                        "SELECT CostCentreId FROM [{0}]..CostCentreTrans WHERE CompanyId={1}) AND A.ParentAccountId={4} " +
                        "GROUP BY A.ParentAccountId,CostCentreId " +
                        "UNION ALL " +
                        "SELECT AccountId,CostCentreId,CurrentBalance FROM [{2}].dbo.CCBalance " +
                        "WHERE CompanyId={1} AND AccountId={4} AND CostCentreId IN (SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                        ") A GROUP BY AccountId,CostCentreId ORDER BY AccountId,CostCentreId
                    */
                        $selectFill = $sql->select();
                        $selectFill->from(array("ET" => "FA_EntryTrans"))
                            ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                            ->columns(array("AccountId","CostCentreId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                        $selectFill->where("ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId=$companyId AND ET.AccountId=$accountId");
                        $selectFill->group(new Expression('ET.AccountId,CostCentreId'));

                        $select1 = $sql->select();
                        $select1->from(array('A' => 'FA_CCAccount'))
                            ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountId=AM.AccountId", array(), $select1::JOIN_INNER)
                            ->columns(array("ParentAccountId","CostCentreId", "Amount" => new Expression("SUM(OpeningBalance)")));
                        $select1->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId=$companyId AND A.ParentAccountID=$accountId");
                        $select1->group(new Expression('A.ParentAccountId,CostCentreId'));
                        $select1->combine($selectFill, 'Union ALL');

                        $select2 = $sql->select();
                        $select2->from(array('A' => 'FA_CCBalance'))
                            ->columns(array("AccountId","CostCentreId", "CurrentBalance"));
                        $select2->where("CompanyId=$companyId AND AccountId=$accountId");
                        $select2->combine($select1, 'Union ALL');

                        $select->from(array("g" => $select2))
                            ->columns(array("AccountId" => new Expression("g.AccountId"),"CompanyId" => new Expression("$companyId")
                            ,"CostCentreId" => new Expression("g.CostCentreId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                        $select->group(new Expression('g.AccountId,g.CostCentreId'));
                        $select->order("g.AccountId,g.CostCentreId");

                        $insert = $sql->insert();
                        $insert->into('FA_CCBalance');
                        $insert->columns(array('AccountId','CompanyId','CostCentreId','CurrentBalance'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        /*
                         * INSERT INTO [{0}].dbo.CCSLBalance(AccountId,SubLedgerId,CompanyId,CostCentreId,CurrentBalance) " +
                        "SELECT AccountId,SubLedgerId,{1},CostCentreId, SUM(Amount)[O/B] FROM (" +
                        "SELECT ET.AccountId,ET.SubLedgerId,ET.CostCentreId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                        "FROM [{2}].dbo.EntryTrans ET INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                        "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId= {1} AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.CostCentreId IN (" +
                        "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) AND ET.AccountId={4} " +
                        "GROUP BY ET.AccountId,ET.SubLedgerId,CostCentreId " +
                        "UNION ALL " +
                        "SELECT A.ParentAccountId,A.SubLedgerId,A.CostCentreId, SUM(OpeningBalance) Amount FROM [{2}].dbo.CCAccount A " +
                        "INNER JOIN [{3}].dbo.AccountMaster AM ON A.ParentAccountId=AM.AccountId " +
                        "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId= {1} AND A.CostCentreId IN (" +
                        "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1})  AND A.ParentAccountId={4} " +
                        "GROUP BY A.ParentAccountId,A.SubLedgerId,A.CostCentreId " +
                        "UNION ALL " +
                        "SELECT AccountId,SubLedgerId,CostCentreId,CurrentBalance FROM [{2}].dbo.CCSLBalance " +
                        "WHERE CompanyId= {1} AND AccountId={4} AND CostCentreId IN (SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                        ") A GROUP BY AccountId,SubLedgerId,CostCentreId ORDER BY AccountId,CostCentreId,SubLedgerId
                         */

                        $selectFill = $sql->select();
                        $selectFill->from(array("ET" => "FA_EntryTrans"))
                            ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                            ->columns(array("AccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                        $selectFill->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId=$companyId AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.AccountId=$accountId");
                        $selectFill->group(new Expression('ET.AccountId,ET.SubLedgerId,CostCentreId'));

                        $select1 = $sql->select();
                        $select1->from(array('A' => 'FA_CCAccount'))
                            ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountId=AM.AccountId", array(), $select1::JOIN_INNER)
                            ->columns(array("ParentAccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(OpeningBalance)")));
                        $select1->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId=$companyId AND A.ParentAccountId=$accountId");
                        $select1->group(new Expression('A.ParentAccountId,A.SubLedgerId,CostCentreId'));
                        $select1->combine($selectFill, 'Union ALL');

                        $select2 = $sql->select();
                        $select2->from(array('A' => 'FA_CCSLBalance'))
                            ->columns(array("AccountId","SubLedgerId","CostCentreId", "CurrentBalance"));
                        $select2->where("CompanyId=$companyId AND AccountId=$accountId");
                        $select2->combine($select1, 'Union ALL');

                        $select->from(array("g" => $select2))
                            ->columns(array("AccountId" => new Expression("g.AccountId"),"SubLedgerId" => new Expression("g.SubLedgerId")
                            ,"CompanyId" => new Expression("$companyId")
                            ,"CostCentreId" => new Expression("g.CostCentreId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                        $select->group(new Expression('g.AccountId,g.SubLedgerId,g.CostCentreId'));
                        $select->order("g.AccountId,g.SubLedgerId,g.CostCentreId");

                        $insert = $sql->insert();
                        $insert->into('FA_CCSLBalance');
                        $insert->columns(array('AccountId','SubLedgerId','CompanyId','CostCentreId','CurrentBalance'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        /*
                         * INSERT INTO [{0}].dbo.CCBalance(AccountId,CompanyId,CostCentreId,CurrentBalance) " +
                         "SELECT AccountId,{1},CostCentreId, SUM(Amount)[O/B] FROM (" +
                         "SELECT ET.AccountId,ET.CostCentreId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                         "FROM [{2}].dbo.EntryTrans ET INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                         "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId= {1} AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.CostCentreId IN (" +
                         "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         "GROUP BY ET.AccountId,CostCentreId " +
                         "UNION ALL " +
                         "SELECT A.ParentAccountId,A.CostCentreId, SUM(OpeningBalance) Amount FROM [{2}].dbo.CCAccount A " +
                         "INNER JOIN [{3}].dbo.AccountMaster AM ON A.ParentAccountId=AM.AccountId " +
                         "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId= {1} AND A.CostCentreId IN (" +
                         "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         "GROUP BY A.ParentAccountId,CostCentreId " +
                         "UNION ALL " +
                         "SELECT AccountId,CostCentreId,CurrentBalance FROM [{2}].dbo.CCBalance " +
                         "WHERE CompanyId={1} AND CostCentreId IN (SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         ") A GROUP BY AccountId,CostCentreId ORDER BY AccountId,CostCentreId
                         */
                        $selectFill = $sql->select();
                        $selectFill->from(array("ET" => "FA_EntryTrans"))
                            ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                            ->columns(array("AccountId","CostCentreId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                        $selectFill->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId=$companyId AND ET.Approve='Y' AND ET.[PDC/Cancel]=0");
                        $selectFill->group(new Expression('ET.AccountId,CostCentreId'));

                        $select1 = $sql->select();
                        $select1->from(array('A' => 'FA_CCAccount'))
                            ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountId=AM.AccountId", array(), $select1::JOIN_INNER)
                            ->columns(array("ParentAccountId","CostCentreId", "Amount" => new Expression("SUM(OpeningBalance)")));
                        $select1->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId=$companyId ");
                        $select1->group(new Expression('A.ParentAccountId,CostCentreId'));
                        $select1->combine($selectFill, 'Union ALL');

                        $select2 = $sql->select();
                        $select2->from(array('A' => 'FA_CCBalance'))
                            ->columns(array("AccountId","CostCentreId", "CurrentBalance"));
                        $select2->where("CompanyId=$companyId");
                        $select2->combine($select1, 'Union ALL');

                        $select->from(array("g" => $select2))
                            ->columns(array("AccountId" => new Expression("g.AccountId"),"CompanyId" => new Expression("$companyId")
                            ,"CostCentreId" => new Expression("g.CostCentreId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                        $select->group(new Expression('g.AccountId,g.CostCentreId'));
                        $select->order("g.AccountId,g.CostCentreId");

                        $insert = $sql->insert();
                        $insert->into('FA_CCBalance');
                        $insert->columns(array('AccountId','CompanyId','CostCentreId','CurrentBalance'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        /*
                         * INSERT INTO [{0}].dbo.CCSLBalance(AccountId,SubLedgerId,CompanyId,CostCentreId,CurrentBalance) " +
                         "SELECT AccountId,SubLedgerId,{1},CostCentreId, SUM(Amount)[O/B] FROM (" +
                         "SELECT ET.AccountId,ET.SubLedgerId,ET.CostCentreId,SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END) Amount " +
                         "FROM [{2}].dbo.EntryTrans ET INNER JOIN [{3}]..AccountMaster AM ON ET.AccountId=AM.AccountId " +
                         "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId= {1} AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.CostCentreId IN (" +
                         "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         "GROUP BY ET.AccountId,ET.SubLedgerId,CostCentreId " +
                         "UNION ALL " +
                         "SELECT A.ParentAccountId,A.SubLedgerId,A.CostCentreId, SUM(OpeningBalance) Amount FROM [{2}].dbo.CCAccount A " +
                         "INNER JOIN [{3}].dbo.AccountMaster AM ON A.ParentAccountId=AM.AccountId " +
                         "WHERE AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId= {1} AND A.CostCentreId IN (" +
                         "SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         "GROUP BY A.ParentAccountId,A.SubLedgerId,A.CostCentreId " +
                         "UNION ALL " +
                         "SELECT AccountId,SubLedgerId,CostCentreId,CurrentBalance FROM [{2}].dbo.CCSLBalance " +
                         "WHERE CompanyId= {1} AND CostCentreId IN (SELECT CostCentreId FROM [{0}].dbo.CostCentreTrans WHERE CompanyId={1}) " +
                         ") A GROUP BY AccountId,SubLedgerId,CostCentreId ORDER BY AccountId,CostCentreId,SubLedgerId
                         */
                        $selectFill = $sql->select();
                        $selectFill->from(array("ET" => "FA_EntryTrans"))
                            ->join(array("AM" => "FA_AccountMaster"), "ET.AccountId=AM.AccountId", array(), $selectFill::JOIN_INNER)
                            ->columns(array("AccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END)")));
                        $selectFill->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND ET.CompanyId=$companyId AND ET.Approve='Y' AND ET.[PDC/Cancel]=0");
                        $selectFill->group(new Expression('ET.AccountId,ET.SubLedgerId,CostCentreId'));

                        $select1 = $sql->select();
                        $select1->from(array('A' => 'FA_CCAccount'))
                            ->join(array("AM" => "FA_AccountMaster"), "A.ParentAccountId=AM.AccountId", array(), $select1::JOIN_INNER)
                            ->columns(array("ParentAccountId","SubLedgerId","CostCentreId", "Amount" => new Expression("SUM(OpeningBalance)")));
                        $select1->where("AM.AccountType NOT IN ('BA','CA') AND AM.GroupId IN (3,4) AND A.CompanyId=$companyId");
                        $select1->group(new Expression('A.ParentAccountId,A.SubLedgerId,CostCentreId'));
                        $select1->combine($selectFill, 'Union ALL');

                        $select2 = $sql->select();
                        $select2->from(array('A' => 'FA_CCSLBalance'))
                            ->columns(array("AccountId","SubLedgerId","CostCentreId", "CurrentBalance"));
                        $select2->where("CompanyId=$companyId");
                        $select2->combine($select1, 'Union ALL');

                        $select->from(array("g" => $select2))
                            ->columns(array("AccountId" => new Expression("g.AccountId"),"SubLedgerId" => new Expression("g.SubLedgerId")
                            ,"CompanyId" => new Expression("$companyId")
                            ,"CostCentreId" => new Expression("g.CostCentreId"),"OpeningBalance" => new Expression("SUM(g.Amount)")));
                        $select->group(new Expression('g.AccountId,g.SubLedgerId,g.CostCentreId'));
                        $select->order("g.AccountId,g.SubLedgerId,g.CostCentreId");

                        $insert = $sql->insert();
                        $insert->into('FA_CCSLBalance');
                        $insert->columns(array('AccountId','SubLedgerId','CompanyId','CostCentreId','CurrentBalance'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    //DELETE FROM [{0}].dbo.BillCFInfo WHERE BillRegisterId IN (SELECT BillRegisterId FROM [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister WHERE CompanyId={1})
                    $subQuery1 = $sql->select();
                    $subQuery1->from("FA_BillRegister")
                        ->columns(array('BillRegisterId'))
                        ->where("CompanyId=$companyId");

                    $deleteTrans = $sql->delete();
                    $deleteTrans->from('FA_BillCFInfo');
                    $deleteTrans->where->expression('BillRegisterId IN ?', array($subQuery1));
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*
                     * INSERT INTO [" + sNewDBName + "].dbo.BillCFInfo(BillRegisterId,Balance) " +
                       "SELECT BillRegisterId,Balance=BillAmount-Advance-DebitAmount-PaidAmount-WriteOff " +
                       "FROM [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister
                        WHERE Approve='Y' AND BillAmount-Advance-DebitAmount-PaidAmount-WriteOff<>0 AND CompanyId=
                     */
                    $select = $sql->select();
                    $select->from(array('A' => 'FA_BillRegister'))
                        ->columns(array("BillRegisterId","Balance" => new Expression("(BillAmount-Advance-DebitAmount-PaidAmount-WriteOff)")));
                    $select->where("Approve='Y' AND BillAmount-Advance-DebitAmount-PaidAmount-WriteOff<>0 AND CompanyId=$companyId");

                    $insert = $sql->insert();
                    $insert->into('FA_BillCFInfo');
                    $insert->columns(array('BillRegisterId','Balance'));
                    $insert->Values($select);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    /*
                     * UPDATE [" + sNewDBName + "].dbo.CCAccount SET FromBill=1  FROM [" + sNewDBName + "].dbo.CCAccount A JOIN ( " +
                       "SELECT BR.AccountId,BR.CostCentreId FROM [" + sNewDBName + "].dbo.BillCFInfo  BCF " +
                       "INNER JOIN [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister BR ON BR.BillRegisterId=BCF.BillRegisterId WHERE BR.AccountId=" + obj.AccountId + " AND BR.CompanyId=" + arg_iCompId + ") B " +
                       "ON A.ParentAccountId=B.AccountId AND A.CostCentreId=B.CostCentreId WHERE A.ParentAccountId=" + obj.AccountId + " AND A.CompanyId=
                    */
                    $update = $sql->update();
                    $update->table("FA_Account")
                        ->set(array('FromBill' => new Expression ("1 FROM dbo.FA_Account A JOIN
                            (SELECT BR.AccountId,BR.CostCentreId FROM dbo.FA_BillCFInfo  BCF
                            INNER JOIN dbo.FA_BillRegister BR ON BR.BillRegisterId=BCF.BillRegisterId WHERE BR.AccountId=$accountId AND BR.CompanyId=$companyId) B
                            ON A.ParentAccountId=B.AccountId AND A.CostCentreId=B.CostCentreId WHERE A.ParentAccountId=$accountId AND A.CompanyId=$companyId")));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //"DELETE FROM [{0}].dbo.CCAccount WHERE OpeningBalance=0 AND SubLedgerId<>0
                    $deleteTrans = $sql->delete();
                    $deleteTrans->from('FA_CCAccount');
                    $deleteTrans->where("OpeningBalance=0 AND SubLedgerId<>0");
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*
                     * UPDATE [{0}].dbo.Account SET FromSL=1,FromCC=0 WHERE FromSL=0 AND AccountId IN (" +
                                                     "SELECT DISTINCT ParentAccountId FROM [{0}].dbo.SLAccount WHERE CompanyId={1}) AND CompanyId={1}
                     */
                    /*$subQuery1 = $sql->select();
                    $subQuery1->from("FA_SLAccount")
                        ->columns(array('ParentAccountId' => new Expression ("DISTINCT ParentAccountId")))
                        ->where("CompanyId=$companyId");

                    $update = $sql->update();
                    $update->table("FA_Account")
                        ->set(array('FromSL' => 1
                            ,'FromCC' =>0));
                    $update->where->expression('AccountId IN ?', array($subQuery1));
                    $update->where("CompanyId=$companyId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                    //DELETE FROM [{0}].dbo.CCAccount WHERE SubLedgerId=0 AND ParentAccountId IN (" +
                    //"SELECT AccountId FROM [{0}].dbo.Account WHERE FromSL=1 AND CompanyId={1}) AND CompanyId={1}
                    $subQuery1 = $sql->select();
                    $subQuery1->from("FA_Account")
                        ->columns(array('AccountId'))
                        ->where("FromSL=1 AND CompanyId=$companyId");

                    $deleteTrans = $sql->delete();
                    $deleteTrans->from('FA_CCAccount');
                    $deleteTrans->where("SubLedgerId=0 AND CompanyId=$companyId");
                    $deleteTrans->where->expression('ParentAccountId IN ?', array($subQuery1));
                    $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    /*
                     * UPDATE [{0}].dbo.Account SET FromSL=0,FromCC=1 WHERE FromCC=0 AND AccountId IN (" +
                                                     "SELECT DISTINCT ParentAccountId FROM [{0}].dbo.CCAccount WHERE SubLedgerId=0 AND CompanyId={1}) AND CompanyId={1}
                     */
                    $subQuery1 = $sql->select();
                    $subQuery1->from("FA_CCAccount")
                        ->columns(array('ParentAccountId' => new Expression ("DISTINCT ParentAccountId")))
                        ->where("SubLedgerId=0 AND CompanyId=$companyId");

                    $update = $sql->update();
                    $update->table("FA_Account")
                        ->set(array('FromSL' => 0
                        ,'FromCC' => 1));
                    $update->where->expression('AccountId IN ?', array($subQuery1));
                    $update->where("CompanyId=$companyId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
        }
    }

    public function slbalanceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $mode= $this->bsf->isNullCheck($postData['mode'], 'string');
                    $mainRowId= $this->bsf->isNullCheck($postData['mainRowId'], 'number');
                    $subRowId= $this->bsf->isNullCheck($postData['subRowId'], 'number');
                    if($mode =='saveBill'){
                        $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                        $m_iTypeId = $this->bsf->isNullCheck($postData['accountTypeId'], 'number');
                        $subLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_'.$mainRowId], 'number');
                        $fromBill = $this->bsf->isNullCheck($postData['fromBill_'.$mainRowId], 'string');

                        $deleteBillTrans = $sql->delete();
                        $deleteBillTrans->from('FA_CCAccount')
                            ->where("ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteBillTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        if($fromBill =='Y'){
                            $TransType="P";
                            $FromBill= 1;
                            if ($m_iTypeId == 9 || $m_iTypeId==53) {
                                $TransType = "P";
                                $FromBill = 1;
                                //grdViewSL.SetRowCellValue(m_lRow, "Debit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                            } else if ($m_iTypeId == 1 || $m_iTypeId == 2 || $m_iTypeId==11 || $m_iTypeId==50) {
                                $TransType = "R";
                                $FromBill = 1;
                                //grdViewSL.SetRowCellValue(m_lRow, "Debit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                            } else {
                                $TransType = "P";
                                $FromBill = 1;
                                //grdViewSL.SetRowCellValue(m_lRow, "Credit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                            }

                            for($i=1;$i<=$subRowId;$i++){
                                $billNo= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_billno_'.$i], 'string');
                                $billDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_billdate_'.$i], 'date')));
                                $CostcentreId= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_costcentre_'.$i], 'number');
                                $billAmt= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_billamount_'.$i], 'number');
                                $type= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_type_'.$i], 'number');
                                $branchId= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_branch_'.$i], 'number');
                                $billRegId= $this->bsf->isNullCheck($postData['sl_'.$mainRowId.'_BillRegisterId_' . $i], 'number');

                                if($CostcentreId == 0 || $billAmt== 0 || $type ==0)
                                    continue;

                                //Insert for Bill table
                                $RefTypeId=0;
                                $RefType="";
                                $BillType="";
                                if($type == 2) {
                                    $RefTypeId=2;
                                    $RefType="PV";
                                    $BillType="B";
                                } else if ($type == 3) {
                                    $RefTypeId=3;
                                    $RefType="WB";
                                    $BillType="B";
                                } else if ($type == 4) {
                                    $RefTypeId=4;
                                    $RefType="SB";
                                    $BillType= "B";
                                } else if ($type == 5) {
                                    $RefTypeId=5;
                                    $RefType="CB";
                                    $BillType="B";
                                } else if ($type == 6)  {
                                    $RefTypeId= 6;
                                    $RefType="PB";
                                    $BillType= "B";
                                } else if ($type == 7)  {
                                    $RefTypeId= 7;
                                    $RefType="PO";
                                    $BillType="A";
                                } else if ($type == 8) {
                                    $RefTypeId=8;
                                    $RefType= "WO";
                                    $BillType="A";
                                } else if ($type == 12)  {
                                    $RefTypeId=12;
                                    $RefType="SO";
                                    $BillType="A";
                                } else if ($type == 18) {
                                    $RefTypeId=18;
                                    $RefType="ES";
                                    $BillType="B";
                                } else if ($type == 21)  {
                                    $RefTypeId= 21;
                                    $RefType="TL";
                                    $BillType="B";
                                }  else if ($type == 25) {
                                    $RefTypeId=25;
                                    $RefType="HO";
                                    $BillType="A";
                                } else if ($type == 26) {
                                    $RefTypeId=26;
                                    $RefType="HB";
                                    $BillType="B";
                                } else if ($type == 16) {
                                    $RefTypeId = 16;
                                    $RefType = "EA";
                                    $BillType = "A";
                                }

                                /*
                                   * INSERT INTO dbo.BillRegister(BillDate,BillNo,RefType,RefTypeId,AccountId,SubLedgerId,BillAmount,TransType,CostCentreId,CompanyId,FYearId
                                   * ,BranchId,FromOB,BillType,Approve)
                                    SELECT @BillDate,@BillNo,@RefType,@RefTypeId,@AccountId,@SubLedgerId,@BillAmount,@TransType,@CCId,@CompanyId,@FyearId,@BranchId,@FromOB,@BillType,'Y'
                                    SET @BillRegId=SCOPE_IDENTITY()
                                   */
                                if($billRegId==0){
                                    $insert = $sql->insert();
                                    $insert->into('FA_BillRegister');
                                    $insert->Values(array('BillDate' => $billDate
                                    , 'BillNo' => $billNo
                                    , 'RefType' => $RefType
                                    , 'RefTypeId' => $RefTypeId
                                    , 'AccountId' => $accountId
                                    , 'SubLedgerId' => $subLedgerId
                                    , 'BillAmount' => $billAmt
                                    , 'TransType' => $TransType
                                    , 'CostCentreId' => $CostcentreId
                                    , 'CompanyId' => $companyId
                                    , 'FYearId' => $fYearId
                                    , 'BranchId' => $branchId
                                    , 'FromOB' => 1
                                    , 'BillType' => $BillType
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $billRegId= $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if($type==18){
                                        /*
                                         *  sSql = "INSERT INTO BillTransDet(BillRegisterId,SLTypeId,SubLedgerId,CostCentreId,Amount) " +
                                               "SELECT " + iRegId + ",4," + SubLedgerTypeBL.SLId + "," + SubLedgerTypeBL.CostCentreId + "," + dt.Rows[i]["BillAmount"] + "";

                                         */
                                        $insert = $sql->insert();
                                        $insert->into('FA_BillTransDet');
                                        $insert->Values(array('BillRegisterId' => $billRegId
                                        , 'SLTypeId' => 4
                                        , 'SubLedgerId' => $subLedgerId
                                        , 'CostCentreId' => $CostcentreId
                                        , 'Amount' => $billAmt));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else {
                                    /*
                                     * UPDATE dbo.BillRegister SET BillDate=@BillDate,BillNo=@BillNo,RefType=@RefType,RefTypeId=@RefTypeId,AccountId=@AccountId,SubLedgerId=@SubLedgerId,BillAmount=@BillAmount,
                                    TransType=@TransType,CostCentreId=@CCId,CompanyId=@CompanyId,FYearId=@FyearId,BranchId=@BranchId,FromOB=@FromOB, Approve='Y' WHERE BillRegisterId=@BillRegId

                                     */

                                    $update = $sql->update();
                                    $update->table('FA_BillRegister')
                                        ->set(array('BillDate' => $billDate
                                        , 'BillNo' => $billNo
                                        , 'RefType' => $RefType
                                        , 'RefTypeId' => $RefTypeId
                                        , 'AccountId' => $accountId
                                        , 'SubLedgerId' => $subLedgerId
                                        , 'BillAmount' => $billAmt
                                        , 'TransType' => $TransType
                                        , 'CostCentreId' => $CostcentreId
                                        , 'CompanyId' => $companyId
                                        , 'FYearId' => $fYearId
                                        , 'BranchId' => $branchId
                                        , 'FromOB' => 1
                                        , 'Approve' => 'Y' ))
                                        ->where("BillRegisterId=$billRegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    if($type==18){
                                        /*
                                         *  sSql = "INSERT INTO BillTransDet(BillRegisterId,SLTypeId,SubLedgerId,CostCentreId,Amount) " +
                                               "SELECT " + iRegId + ",4," + SubLedgerTypeBL.SLId + "," + SubLedgerTypeBL.CostCentreId + "," + dt.Rows[i]["BillAmount"] + "";

                                         */
                                        $deleteBillTrans = $sql->delete();
                                        $deleteBillTrans->from('FA_BillTransDet')
                                            ->where("BillRegisterId=$billRegId");
                                        $DelStatement = $sql->getSqlStringForSqlObject($deleteBillTrans);
                                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $insert = $sql->insert();
                                        $insert->into('FA_BillTransDet');
                                        $insert->Values(array('BillRegisterId' => $billRegId
                                        , 'SLTypeId' => 4
                                        , 'SubLedgerId' => $subLedgerId
                                        , 'CostCentreId' => $CostcentreId
                                        , 'Amount' => $billAmt));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }


                                //Main table insert
                                $dBalance=0;
                                $dBalance=(-1)*$billAmt;
                                /* $creditAmt=$this->bsf->isNullCheck($postData['credit_'.$mainRowId], 'number');
                                 if($creditAmt>0){
                                     $dBalance=(-1)*$creditAmt;
                                 } else {
                                     $dBalance=$this->bsf->isNullCheck($postData['debit_'.$mainRowId], 'number');
                                 }
     */

                                $select = $sql->select();
                                $select->from(array("a" => "FA_CCAccount"))
                                    ->columns(array("rowCount"=> new Expression("count(a.SubledgerId)")));
                                $select->where("a.ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CostcentreId and FromBill=1");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $CCAccountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $rowCount=$CCAccountList['rowCount'];
                                if($rowCount==0){
                                    /*
                                     * INSERT INTO dbo.CCAccount(ParentAccountId,SubledgerId,CompanyId,CostCentreId,OpeningBalance,FromBill)
                                        VALUES(@ParentAccId,@SubLedgerId,@CompanyId,@CCId,@Balance,@FromBill)
                                     */
                                    $insert = $sql->insert();
                                    $insert->into('FA_CCAccount');
                                    $insert->Values(array('ParentAccountId' => $accountId
                                    , 'SubledgerId' => $subLedgerId
                                    , 'CompanyId' => $companyId
                                    , 'CostCentreId' => $CostcentreId
                                    , 'OpeningBalance' => $dBalance
                                    , 'FromBill' => 1));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if($billAmt!=0){
                                        /*
                                         *UPDATE dbo.CCAccount SET OpeningBalance=@Balance, FromBill=@FromBill
                                            WHERE ParentAccountId=@ParentAccId AND SubledgerId=@SubLedgerId
                                            AND CompanyId=@CompanyId AND CostCentreId=@CCId
                                         */
                                        $update = $sql->update();
                                        $update->table('FA_CCAccount')
                                            ->set(array('OpeningBalance' => new Expression("OpeningBalance + $dBalance")
                                            , 'FromBill' => 1 ))
                                            ->where("ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CostcentreId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        } else {
                            for($i=1;$i<=$subRowId;$i++) {
                                $CostcentreId = $this->bsf->isNullCheck($postData['sl_' . $mainRowId . '_cc_' . $i], 'number');
                                $ccDebit= $this->bsf->isNullCheck($postData['sl_' . $mainRowId . '_ccDebit_' . $i], 'number');
                                $ccCredit= $this->bsf->isNullCheck($postData['sl_' . $mainRowId . '_ccCredit_' . $i], 'number');

                                $dBalance=0;
                                if($ccCredit>0){
                                    $dBalance=(-1)*$ccCredit;
                                } else {
                                    $dBalance=$ccDebit;
                                }

                                if ($CostcentreId == 0 || ($ccDebit == 0 && $ccCredit == 0))
                                    continue;
                                //Insert CC table

                                $select = $sql->select();
                                $select->from(array("a" => "FA_CCAccount"))
                                    ->columns(array("rowCount"=> new Expression("count(a.SubledgerId)")));
                                $select->where("a.ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CostcentreId and FromBill=0");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $CCAccountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $rowCount=$CCAccountList['rowCount'];
                                if($rowCount==0){
                                    /*
                                     * INSERT INTO dbo.CCAccount(ParentAccountId,SubledgerId,CompanyId,CostCentreId,OpeningBalance,FromBill)
                                        VALUES(@ParentAccId,@SubLedgerId,@CompanyId,@CCId,@Balance,@FromBill)
                                     */
                                    $insert = $sql->insert();
                                    $insert->into('FA_CCAccount');
                                    $insert->Values(array('ParentAccountId' => $accountId
                                    , 'SubledgerId' => $subLedgerId
                                    , 'CompanyId' => $companyId
                                    , 'CostCentreId' => $CostcentreId
                                    , 'OpeningBalance' => $dBalance
                                    , 'FromBill' => 0));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if($dBalance!=0){
                                        /*
                                         *UPDATE dbo.CCAccount SET OpeningBalance=@Balance, FromBill=@FromBill
                                            WHERE ParentAccountId=@ParentAccId AND SubledgerId=@SubLedgerId
                                            AND CompanyId=@CompanyId AND CostCentreId=@CCId
                                         */
                                        $update = $sql->update();
                                        $update->table('FA_CCAccount')
                                            ->set(array('OpeningBalance' => new Expression("OpeningBalance + $dBalance")))
                                            ->where("ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CostcentreId and FromBill=0");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                            }
                        }

                        $select = $sql->select();
                        $select->from(array("a" => "FA_CCAccount"))
                            ->columns(array("OB"=> new Expression("ISNULL(SUM(a.OpeningBalance),0)")));
                        $select->where("a.ParentAccountId=$accountId and CompanyId=$companyId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $CCAccountOBList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $totOB=$CCAccountOBList['OB'];
                        if($totOB!=0){
                            $update = $sql->update();
                            $update->table('FA_Account')
                                ->set(array('OpeningBalance' => $totOB
                                , 'FromSL' => 1 ))
                                ->where("AccountId=$accountId AND CompanyId=$companyId and FYearId=$fYearId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $update = $sql->update();
                            $update->table('FA_Account')
                                ->set(array('OpeningBalance' => 0
                                , 'FromSL' => 1 ))
                                ->where("AccountId=$accountId AND CompanyId=$companyId and FYearId=$fYearId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                    }
                    $result='success';
                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $accountId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                    $m_iTypeId = $this->bsf->isNullCheck($postData['accountTypeId'], 'number');
                    $subLedgerId = $this->bsf->isNullCheck($postData['subLedgerId'], 'number');
                    $CCId = $this->bsf->isNullCheck($postData['CCId'], 'number');
                    $dBalance=0;
                    $creditAmt=$this->bsf->isNullCheck($postData['credit'], 'number');
                    if($creditAmt>0){
                        $dBalance=(-1)*$creditAmt;
                    } else {
                        $dBalance=$this->bsf->isNullCheck($postData['debit'], 'number');
                    }
                    $rowSize = $this->bsf->isNullCheck($postData['rowSize'], 'number');
                    /*
                    SELECT Count(SubledgerId) FROM dbo.CCAccount WHERE
	                ParentAccountId=@ParentAccId AND SubledgerId=@SubLedgerId
                    AND CompanyId=@CompanyId AND CostCentreId=@CCId
                     */
                    $select = $sql->select();
                    $select->from(array("a" => "FA_CCAccount"))
                        ->columns(array("rowCount"=> new Expression("count(a.SubledgerId)")));
                    $select->where("a.ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CCId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $CCAccountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $rowCount=$CCAccountList['rowCount'];
                    if($rowCount==0){
                        /*
                         * INSERT INTO dbo.CCAccount(ParentAccountId,SubledgerId,CompanyId,CostCentreId,OpeningBalance,FromBill)
		                    VALUES(@ParentAccId,@SubLedgerId,@CompanyId,@CCId,@Balance,@FromBill)
                         */
                        $insert = $sql->insert();
                        $insert->into('FA_CCAccount');
                        $insert->Values(array('ParentAccountId' => $accountId
                        , 'SubledgerId' => $subLedgerId
                        , 'CompanyId' => $companyId
                        , 'CostCentreId' => $CCId
                        , 'OpeningBalance' => $dBalance
                        , 'FromBill' => 1));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        if($dBalance!=0){
                            /*
                             *UPDATE dbo.CCAccount SET OpeningBalance=@Balance, FromBill=@FromBill
                                WHERE ParentAccountId=@ParentAccId AND SubledgerId=@SubLedgerId
                                AND CompanyId=@CompanyId AND CostCentreId=@CCId
                             */
                            $update = $sql->update();
                            $update->table('FA_CCAccount')
                                ->set(array('OpeningBalance' => $dBalance
                                , 'FromBill' => 1 ))
                                ->where("ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CCId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            //DELETE FROM dbo.CCAccount WHERE ParentAccountId=@ParentAccId AND SubledgerId=@SubLedgerId AND CompanyId=@CompanyId AND CostCentreId=@CCId
                            $deleteCCAcc = $sql->delete();
                            $deleteCCAcc->from('FA_CCAccount')
                                ->where("ParentAccountId=$accountId AND SubledgerId=$subLedgerId AND CompanyId=$companyId AND CostCentreId=$CCId");
                            $DelStatement = $sql->getSqlStringForSqlObject($deleteCCAcc);
                            $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $TransType="P";
                    $FromBill= 1;
                    if ($m_iTypeId == 9 || $m_iTypeId==53) {
                        $TransType = "P";
                        $FromBill = 1;
                        //grdViewSL.SetRowCellValue(m_lRow, "Debit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                    } else if ($m_iTypeId == 1 || $m_iTypeId == 2 || $m_iTypeId==11 || $m_iTypeId==50) {
                        $TransType = "R";
                        $FromBill = 1;
                        //grdViewSL.SetRowCellValue(m_lRow, "Debit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                    } else {
                        $TransType = "P";
                        $FromBill = 1;
                        //grdViewSL.SetRowCellValue(m_lRow, "Credit", grdViewCCBill.Columns["BillAmount"].SummaryText);
                    }

                    for($i=1; $i <=$rowSize; $i++ ) {
                        $billDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['billDate_' . $i], 'date')));
                        $billNo= $this->bsf->isNullCheck($postData['billNo_' . $i], 'string');
                        $type= $this->bsf->isNullCheck($postData['type_' . $i], 'number');
                        $billAmt= $this->bsf->isNullCheck($postData['billAmount_' . $i], 'number');
                        $CostcentreId= $this->bsf->isNullCheck($postData['CostcentreId_' . $i], 'number');
                        $branchId= $this->bsf->isNullCheck($postData['branchId_' . $i], 'number');
                        $billRegId= $this->bsf->isNullCheck($postData['BillRegisterId_' . $i], 'number');

                        $RefTypeId=0;
                        $RefType="";
                        $BillType="";
                        if($type == 2) {
                            $RefTypeId=2;
                            $RefType="PV";
                            $BillType="B";
                        } else if ($type == 3) {
                            $RefTypeId=3;
                            $RefType="WB";
                            $BillType="B";
                        } else if ($type == 4) {
                            $RefTypeId=4;
                            $RefType="SB";
                            $BillType= "B";
                        } else if ($type == 5) {
                            $RefTypeId=5;
                            $RefType="CB";
                            $BillType="B";
                        } else if ($type == 6)  {
                            $RefTypeId= 6;
                            $RefType="PB";
                            $BillType= "B";
                        } else if ($type == 7)  {
                            $RefTypeId= 7;
                            $RefType="PO";
                            $BillType="A";
                        } else if ($type == 8) {
                            $RefTypeId=8;
                            $RefType= "WO";
                            $BillType="A";
                        } else if ($type == 12)  {
                            $RefTypeId=12;
                            $RefType="SO";
                            $BillType="A";
                        } else if ($type == 18) {
                            $RefTypeId=18;
                            $RefType="ES";
                            $BillType="B";
                        } else if ($type == 21)  {
                            $RefTypeId= 21;
                            $RefType="TL";
                            $BillType="B";
                        }  else if ($type == 25) {
                            $RefTypeId=25;
                            $RefType="HO";
                            $BillType="A";
                        } else if ($type == 26) {
                            $RefTypeId=26;
                            $RefType="HB";
                            $BillType="B";
                        } else if ($type == 16) {
                            $RefTypeId = 16;
                            $RefType = "EA";
                            $BillType = "A";
                        }

                        /*
                           * INSERT INTO dbo.BillRegister(BillDate,BillNo,RefType,RefTypeId,AccountId,SubLedgerId,BillAmount,TransType,CostCentreId,CompanyId,FYearId
                           * ,BranchId,FromOB,BillType,Approve)
                            SELECT @BillDate,@BillNo,@RefType,@RefTypeId,@AccountId,@SubLedgerId,@BillAmount,@TransType,@CCId,@CompanyId,@FyearId,@BranchId,@FromOB,@BillType,'Y'
                            SET @BillRegId=SCOPE_IDENTITY()
                           */
                        if($billRegId==0){
                            $insert = $sql->insert();
                            $insert->into('FA_BillRegister');
                            $insert->Values(array('BillDate' => $billDate
                                , 'BillNo' => $billNo
                                , 'RefType' => $RefType
                                , 'RefTypeId' => $RefTypeId
                                , 'AccountId' => $accountId
                                , 'SubLedgerId' => $subLedgerId
                                , 'BillAmount' => $billAmt
                                , 'TransType' => $TransType
                                , 'CostCentreId' => $CostcentreId
                                , 'CompanyId' => $companyId
                                , 'FYearId' => $fYearId
                                , 'BranchId' => $branchId
                                , 'FromOB' => 1
                                , 'BillType' => $BillType
                                , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $billRegId= $dbAdapter->getDriver()->getLastGeneratedValue();

                            if($type==18){
                                /*
                                 *  sSql = "INSERT INTO BillTransDet(BillRegisterId,SLTypeId,SubLedgerId,CostCentreId,Amount) " +
                                       "SELECT " + iRegId + ",4," + SubLedgerTypeBL.SLId + "," + SubLedgerTypeBL.CostCentreId + "," + dt.Rows[i]["BillAmount"] + "";

                                 */
                                $insert = $sql->insert();
                                $insert->into('FA_BillTransDet');
                                $insert->Values(array('BillRegisterId' => $billRegId
                                , 'SLTypeId' => 4
                                , 'SubLedgerId' => $subLedgerId
                                , 'CostCentreId' => $CostcentreId
                                , 'Amount' => $billAmt));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else {
                            /*
                             * UPDATE dbo.BillRegister SET BillDate=@BillDate,BillNo=@BillNo,RefType=@RefType,RefTypeId=@RefTypeId,AccountId=@AccountId,SubLedgerId=@SubLedgerId,BillAmount=@BillAmount,
                            TransType=@TransType,CostCentreId=@CCId,CompanyId=@CompanyId,FYearId=@FyearId,BranchId=@BranchId,FromOB=@FromOB, Approve='Y' WHERE BillRegisterId=@BillRegId

                             */

                            $update = $sql->update();
                            $update->table('FA_BillRegister')
                                ->set(array('BillDate' => $billDate
                                , 'BillNo' => $billNo
                                , 'RefType' => $RefType
                                , 'RefTypeId' => $RefTypeId
                                , 'AccountId' => $accountId
                                , 'SubLedgerId' => $subLedgerId
                                , 'BillAmount' => $billAmt
                                , 'TransType' => $TransType
                                , 'CostCentreId' => $CostcentreId
                                , 'CompanyId' => $companyId
                                , 'FYearId' => $fYearId
                                , 'BranchId' => $branchId
                                , 'FromOB' => 1
                                , 'Approve' => 'Y' ))
                                ->where("BillRegisterId=$billRegId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($type==18){
                                /*
                                 *  sSql = "INSERT INTO BillTransDet(BillRegisterId,SLTypeId,SubLedgerId,CostCentreId,Amount) " +
                                       "SELECT " + iRegId + ",4," + SubLedgerTypeBL.SLId + "," + SubLedgerTypeBL.CostCentreId + "," + dt.Rows[i]["BillAmount"] + "";

                                 */
                                $deleteBillTrans = $sql->delete();
                                $deleteBillTrans->from('FA_BillTransDet')
                                    ->where("BillRegisterId=$billRegId");
                                $DelStatement = $sql->getSqlStringForSqlObject($deleteBillTrans);
                                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $insert = $sql->insert();
                                $insert->into('FA_BillTransDet');
                                $insert->Values(array('BillRegisterId' => $billRegId
                                , 'SLTypeId' => 4
                                , 'SubLedgerId' => $subLedgerId
                                , 'CostCentreId' => $CostcentreId
                                , 'Amount' => $billAmt));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
echo 'post';die;
                    $connection->commit();
                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            /*
             SELECT CCA.ParentAccountId AccountId,CCA.SubLedgerId,
SubLedgerName=LTRIM(SLM.SubLedgerName) ,
CC.CostCentreName,CASE WHEN OpeningBalance>=0 THEN OpeningBalance ELSE 0 END Debit,
CASE WHEN OpeningBalance<0 THEN ABS(OpeningBalance) ELSE 0 END Credit,Balance=OpeningBalance FROM FA_CCAccount CCA
INNER JOIN WF_CostCentre CC ON CC.CostCentreId=CCA.CostCentreId
INNER JOIN FA_SubLedgerMaster SLM ON SLM.SubLedgerId=CCA.SubLedgerId
--LEFT JOIN [dnhFA].dbo.CMSLDet CCSL ON CCSL.SLId=CCA.SubLedgerId AND CCSL.CompanyId=CCA.CompanyId
WHERE CCA.CompanyId=1 AND CCA.ParentAccountId=46
--CCA.CostCentreId NOT IN ( SELECT CostCentreId FROM [dnhwF].dbo.UserFACostCentreTrans WHERE UserId=1)
ORDER BY LTRIM(SLM.SubLedgerName)

             */

            $select = $sql->select();
            $select->from(array("CCA" => "FA_CCAccount"))
                ->columns(array( "AccountId"=>new Expression("CCA.ParentAccountId"),"SubLedgerId", "SubLedgerName"=>new Expression("LTRIM(SLM.SubLedgerName)") ,
                          "CostCentreName"=>new Expression("CC.CostCentreName"),"Debit"=>new Expression("CASE WHEN OpeningBalance>=0 THEN OpeningBalance ELSE 0 END"),
                          "Credit"=>new Expression("CASE WHEN OpeningBalance<0 THEN ABS(OpeningBalance) ELSE 0 END"),"Balance" => new Expression("OpeningBalance")))
                ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=CCA.CostCentreId", array(), $select::JOIN_INNER)
                ->join(array("SLM" => "FA_SubLedgerMaster"), "SLM.SubLedgerId=CCA.SubLedgerId", array(), $select::JOIN_INNER);

            $select->where("CCA.CompanyId=$companyId AND CCA.ParentAccountId=$accountId");
            $select->order("SubLedgerName");
            $statement = $sql->getSqlStringForSqlObject($select);
            $slBalanceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Load Bill det For FromBill Yes
            //--if(g_lCPYearId == 0)
                $select = $sql->select();
                $select->from(array("a" => "FA_BillRegister"))
                    ->columns(array('BillRegisterId','BillNo',"BillDate" => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"),'BillAmount', 'PaidAmount', 'AccountId','SubLedgerId','Type'=>new Expression("a.RefTypeId"),'CostCentreId','Approve','BranchId'));
                $select->where("a.FromOB=1 AND CompanyId=$companyId AND FYearId=$fYearId and AccountId=$accountId");
            //--else
               /* $select = $sql->select();
                $select->from(array("BR" => "FA_BillRegister"))
                    ->join(array("BCF" => "FA_BillCFInfo"), "BCF.BillRegisterId=BR.BillRegisterId ", array(), $select::JOIN_INNER)
                    ->columns(array('BillRegisterId','BillNo','BillDate','BillAmount'=>new Expression("BCF.Balance"),'PaidAmount'=>new Expression('1-1')
                    ,'AccountId','SubLedgerId','Type'=>new Expression("RefTypeId"),'CostCentreId','Approve','BranchId'));
                $select->where("CompanyId=$companyId");*/

            //$select->where("a.SubLedgerId=$SLId and AccountId=$accountId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $billDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->billDet=$billDet;

            /*select SubledgerId,CostCentreId,case when OpeningBalance >= 0 then OpeningBalance else 0 end as Debit,
            case when OpeningBalance < 0  then -1*OpeningBalance else 0 end Credit from FA_CCAccount
            WHere ParentAccountId=46 and CompanyId=1 and fromBill=0*/
            $select = $sql->select();
            $select->from(array("a" => "FA_CCAccount"))
                ->columns(array('SubLedgerId','CostCentreId',"Debit" => new Expression("case when OpeningBalance >= 0 then OpeningBalance else 0 end")
                ,'Credit'=>new Expression("case when OpeningBalance < 0  then -1*OpeningBalance else 0 end ")));
            $select->where("a.ParentAccountId=$accountId and a.CompanyId=$companyId and a.fromBill=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $billCCDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->billCCDet=$billCCDet;

            //Branch --if(ist table row value of slId == current SubLedgerId)
            $select = $sql->select();
            $select->from(array("SLM" => "FA_SubLedgerMaster"))
                ->join(array("BR" => "Vendor_Branch"), "SLM.RefId=BR.VendorId", array(), $select::JOIN_INNER)
                ->columns(array('SubLedgerId','BranchId'=>new Expression("BR.BranchId"),'BranchName'=>new Expression("BR.BranchName")));
            $select->where("SLM.SubLedgerTypeId=1");
            $statement = $sql->getSqlStringForSqlObject($select);
            $branchList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->branchList=$branchList;

            //Cost center
            $select = $sql->select();
            $select->from(array("a" => "WF_CostCentre"))
                ->columns(array('CostCentreId','CostCentreName'));
            $select->where("a.CompanyId=$companyId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ccList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ccList=$ccList;

            //get type ids
            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array('TypeId','IsPurchase','IsWork','IsService','IsBuyer','IsClient'));
            $select->where("a.AccountId=$accountId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $accList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $m_iTypeId = $accList['TypeId'];
            $ListTypeIds="";
            if($m_iTypeId == 1){
                $ListTypeIds="TypeId=6";
            } else if ($m_iTypeId == 2){
                $ListTypeIds="TypeId=5";
            } else if ($m_iTypeId == 4) {
                if($accList['IsPurchase']==1 && $accList['IsWork']==1 && $accList['IsService']==1){
                    $ListTypeIds = "TypeId=2 Or TypeId=3 Or TypeId=4 Or TypeId=26";
                } else if ($accList['IsPurchase']==1 && $accList['IsWork']==1){
                    $ListTypeIds = "TypeId=2 Or TypeId=3 ";
                } else if ($accList['IsPurchase']==1 && $accList['IsService']==1){
                    $ListTypeIds = "TypeId=2 Or TypeId=4 Or TypeId=26";
                } else if ($accList['IsWork']==1 && $accList['IsService']==1){
                    $ListTypeIds = "TypeId=3 Or TypeId=4 Or TypeId=26";
                } else if ($accList['IsPurchase']==1){
                    $ListTypeIds = "TypeId=2";
                } else if ($accList['IsWork']==1){
                    $ListTypeIds = "TypeId=3";
                } else if ($accList['IsService']==1){
                    $ListTypeIds = "TypeId=4 Or TypeId=26 ";
                } else{
                    $ListTypeIds = "TypeId=2 Or TypeId=3 Or TypeId=4 Or TypeId=26";
                }
            } else if ($m_iTypeId == 9) {
                $ListTypeIds="TypeId IN (7,8,12,25)";
            } else if ($m_iTypeId == 17) {
                $ListTypeIds= "TypeId IN (18)";
            } else if ($m_iTypeId == 40) {
                $ListTypeIds="TypeId IN (21)";
            } else if ($m_iTypeId == 53) {
                $ListTypeIds= "TypeId IN (16)";
            }


            $select = $sql->select();
            $select->from(array("a" => "FA_InvoiceType"))
                ->columns(array('TypeId','TypeName'));
            $select->where("a.TypeId>1");
            if($ListTypeIds!=""){
                $select->where("$ListTypeIds");
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->typeList= $typeList;

            //load SLDEt
            if($m_iTypeId =='')
                $m_iTypeId=0;

            $subQuery= $sql->select();
            $subQuery->from(array("a" => "FA_SLAccountType"))
                ->columns(array('SLTypeId'));
            $subQuery->where("a.TypeId=$m_iTypeId");

            $select = $sql->select();
            $select->from(array("A" => "FA_SubLedgerMaster"))
                ->join(array("A1" => "FA_SubLedgerType"), "A.SubLedgerTypeId=A1.SubLedgerTypeId", array(), $select::JOIN_INNER)
                ->join(array("B" => "FA_CCAccount"),new Expression("A.SubLedgerId=B.SubledgerId AND B.ParentAccountId=$accountId AND B.CompanyId=$companyId"), array(), $select::JOIN_LEFT)
                ->join(array("C" => "FA_Account"),new Expression("B.ParentAccountId=C.AccountId AND C.AccountId=$accountId AND C.CompanyId=$companyId"), array(), $select::JOIN_LEFT)
                ->columns(array('SubLedgerId','SubLedgerName','SubLedgerTypeName'=>new Expression("A1.SubLedgerTypeName")
                ,'Debit'=>new Expression("CASE WHEN ISNULL(sum(B.OpeningBalance),0)>=0 THEN Convert (decimal(18,3),ISNULL(sum(B.OpeningBalance),0),0) ELSE Convert (decimal(18,3),0,0) END")
                ,'Credit'=>new Expression("CASE WHEN ISNULL(sum(B.OpeningBalance),0)<0 THEN Convert (decimal(18,3),ABS(ISNULL(sum(B.OpeningBalance),0)),0) ELSE Convert (decimal(18,3),0,0) END")
                ,'FromBill'=>new Expression("ISNULL(B.FromBill,0)"),'RefId'));
            $select->where->expression("a.SubLedgerTypeId IN ?",array($subQuery));
            $select->group(array('A.SubLedgerId','A.SubLedgerName','A1.SubLedgerTypeName','B.FromBill','A.RefId'));
            $select->order(array("A1.SubLedgerTypeName","A.SubLedgerName"));
            $select->quantifier('TOP(10)');
            $statement = $sql->getSqlStringForSqlObject($select);
            $slDet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->slDet=$slDet;

            $this->_view->slBalanceList = $slBalanceList;
            $this->_view->accountId = $accountId;
            $this->_view->m_iTypeId= $m_iTypeId;
            $this->_view->curCompanyId = $companyId;
            $this->_view->curFYearId = $fYearId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function loadcompanydetAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            if($type=='getLoadCompanyDetails'){
                $fiscalId = $this->bsf->isNullCheck($postData['fiscalId'], 'number');
                $companySession = new Container('faCompany');
                $companySession->companyId = 0;
                $companySession->fiscalId = $fiscalId;

                $transFisSelect = $sql->select();
                $transFisSelect->from('FA_FiscalyearTrans')
                    ->columns(array('CompanyId'));
                $transFisSelect->where("FYearId = $fiscalId");

                $select = $sql->select();
                $select->from(array("a" => "WF_CompanyMaster"))
                    ->columns(array("CompanyId", "CompanyName"));
                $select->where("DeleteFlag=0");
                $select->where->In('a.CompanyId', $transFisSelect);

                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accCompMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                //$this->_view->accCompMasterList = $accCompMasterList;
            } else if($type=='getsessionDetails') {
                $fYearId = $this->bsf->isNullCheck($postData['fiscalId'], 'number');
                $companyId = $this->bsf->isNullCheck($postData['companyId'], 'number');
                $companySession = new Container('faCompany');
                $companySession->companyId = $companyId;
                $g_lYearTransId=0;
                $select = $sql->select();
                $select->from('FA_FiscalYearTrans')
                    ->columns(array('FYearTransId'))
                    ->where("FYearId=$fYearId and CompanyId=$companyId");
                $stmt = $sql->getSqlStringForSqlObject($select);
                $refFiscalList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($refFiscalList) {
                    $g_lYearTransId = $refFiscalList['FYearTransId'];
                }
                $companySession->g_lYearTransId = $g_lYearTransId;

                //UPDATE FiscalYearTrans SET PostingDate=DateAdd(Day,-1,FromDate) WHERE PostingDate IS NULL
                $update = $sql->update();
                $update->table("FA_FiscalYearTrans")
                    ->set(array('PostingDate' => new Expression ("DateAdd(Day,-1,FromDate)")));
                $update->where("PostingDate IS NULL");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                //UPDATE FiscalYearTrans SET LockDate=DateAdd(Day,-1,FromDate) WHERE LockDate IS NULL
                $update = $sql->update();
                $update->table("FA_FiscalYearTrans")
                    ->set(array('LockDate' => new Expression ("DateAdd(Day,-1,FromDate)")));
                $update->where("LockDate IS NULL");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                //UPDATE FiscalYear SET PFYearId=B.FYearId FROM FiscalYear A JOIN (SELECT FYearId,NFYearId FROM FiscalYear WHERE NFYearId<>0) B ON A.FYearId=B.NFYearId WHERE B.NFYearId<>0 AND A.PFYearId=0
                $update = $sql->update();
                $update->table("FA_FiscalYear")
                    ->set(array('PFYearId' => new Expression ("B.FYearId FROM FA_FiscalYear A JOIN (SELECT FYearId,NFYearId FROM FA_FiscalYear WHERE NFYearId<>0) B ON A.FYearId=B.NFYearId WHERE B.NFYearId<>0 AND A.PFYearId=0")));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $g_lCompanyId=0;
                $g_lYearId=0;
                $g_lNYearId=0;
                $g_lPYearId=0;
                $g_lCPYearId=0;
                $g_lCNYearId=0;
                $g_dStartDate="";
                $g_dEndDate="";
                $g_sCompanyName="";
                $g_sFYCaption="";
                $g_sLFYCaption="";
                $g_iFYCurrencyId=0;
                $g_iFYCurrencyDigit= 2;
                $g_dFromPostingDate="";
                $g_dToPostingDate="";
                $g_dPostingDate="";
                $g_dLockDate="";
                $g_bFYLock= 0;

                $select = $sql->select();
                /*
                 *
                 * SELECT A.CompanyId,A.FYearId,B.NFYearId,B.PFYearId,A.CPFYearId,A.CNFYearId, B.FName,B.StartDate,B.EndDate,B.DBName,C.CompanyName,B.FName,0 Period ,C.CurrencyId,CM2.DecimalLength, " +
                       "B.CYCaption,B.LYCaption, A.PostingPeriod, A.PostingDate,A.LockDate,A.Freeze,C.Address1,C.Address2,C.Pincode,CM.CityName,SM.StateName,CM1.CountryName,  " +
                       "C.Phone,C.Fax,C.Mobile,C.ContactPerson,C.Email,C.Website,C.STNo,C.CSTNo,C.GIRNo,C.PANNo,C.TANNo,C.TNGSTNo,C.TIN,C.CIN,C.ShortName " +
                       "FROM dbo.FiscalYearTrans A INNER JOIN dbo.FiscalYear B ON A.FYearId=B.FYearId " +
                       "INNER JOIN [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CompanyMaster C ON A.CompanyId = C.CompanyId " +
                       "LEFT JOIN  [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CityMaster CM ON C.CityId= CM.CityId " +
                       "LEFT JOIN  [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.StateMaster SM ON SM.StateId= CM.StateId " +
                       "LEFT JOIN  [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CountryMaster CM1 ON CM1.CountryId= CM.CountryId " +
                       "INNER JOIN [" + BsfGlobal.g_sWorkFlowDBName + "].dbo.CurrencyMaster CM2 ON CM2.CurrencyId=C.CurrencyId WHERE A.FYearTransId=
                 */
                $select->from(array("a" => "FA_FiscalYearTrans"))
                    ->columns(array('CompanyId','FYearId','NFYearId'=> new Expression ("b.NFYearId"),'PFYearId'=> new Expression ("b.PFYearId")
                    ,'CPFYearId','CNFYearId','StartDate'=> new Expression ("b.StartDate"),'EndDate'=> new Expression ("b.EndDate")
                    ,'CompanyName'=> new Expression ("c.CompanyName"),'CYCaption'=> new Expression ("b.CYCaption"),'LYCaption'=> new Expression ("b.LYCaption")
                    ,'CurrencyId'=> new Expression ("c.CurrencyId"),'DecimalLength'=> new Expression ("CM2.DecimalLength"),'PostingPeriod','PostingDate'
                    ,'LockDate','Freeze'))
                    ->join(array("b" => "FA_FiscalYear"), "a.FYearId=b.FYearId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "WF_CompanyMaster"), "a.CompanyId=c.CompanyId", array(), $select::JOIN_INNER)
                    //->join(array("CM" => "WF_CityMaster"), "c.CityId= CM.CityId", array(), $select::JOIN_INNER)
                    ->join(array("CM2" => "WF_CurrencyMaster"), "c.CurrencyId= CM2.CurrencyId", array(), $select::JOIN_LEFT)
                    ->where("a.FYearTransId=$g_lYearTransId");
                $stmt = $sql->getSqlStringForSqlObject($select);
                $refFiscalDetList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($refFiscalDetList) {
                    $g_lCompanyId = $refFiscalDetList['CompanyId'];
                    $g_lYearId = $refFiscalDetList['FYearId'];
                    $g_lNYearId = $refFiscalDetList['NFYearId'];
                    $g_lPYearId = $refFiscalDetList['PFYearId'];
                    $g_lCPYearId = $refFiscalDetList['CPFYearId'];
                    $g_lCNYearId = $refFiscalDetList['CNFYearId'];
                    $g_dStartDate = $this->bsf->isNullCheck($refFiscalDetList['StartDate'], 'date');
                    $g_dEndDate = $this->bsf->isNullCheck($refFiscalDetList['EndDate'], 'date');
                    $g_sCompanyName = $refFiscalDetList['CompanyName'];
                    $g_sFYCaption = $refFiscalDetList['CYCaption'];
                    $g_sLFYCaption = $refFiscalDetList['LYCaption'];

                    $g_iFYCurrencyId = $refFiscalDetList['CurrencyId'];
                    $g_iFYCurrencyDigit= $refFiscalDetList['DecimalLength'];
                    $g_dFromPostingDate= date('d-m-Y',$g_dStartDate);
                    $g_dToPostingDate= date('d-m-Y', $g_dEndDate);
                    $g_dPostingDate= date('d-m-Y', strtotime('-1 days', $g_dStartDate));//$g_dStartDate.AddDays(-1);
                    $g_dLockDate= date('d-m-Y', strtotime('-1 days', $g_dStartDate));//$g_dStartDate.AddDays(-1);
                    $g_bFYLock= $refFiscalDetList['Freeze'];
                }
                /*echo $companyId. "--";
                echo $g_lYearTransId. "--";
                echo $g_lNYearId. "--";
                echo $g_lPYearId. "--";
                echo $g_lCPYearId. "--";
                echo $g_lCNYearId. "--";
                echo $g_dStartDate. "--";
                echo $g_dEndDate. "--";
                echo $g_sCompanyName. "--";
                die;*/
                $companySession->g_lCompanyId = $g_lCompanyId;
                $companySession->g_lYearId = $g_lYearId;
                $companySession->g_lNYearId = $g_lNYearId;
                $companySession->g_lPYearId = $g_lPYearId;
                $companySession->g_lCPYearId = $g_lCPYearId;
                $companySession->g_lCNYearId = $g_lCNYearId;
                $companySession->g_dStartDate = $g_dStartDate;
                $companySession->g_dEndDate = $g_dEndDate;
                $companySession->g_sCompanyName = $g_sCompanyName;
                $companySession->g_sFYCaption = $g_sFYCaption;
                $companySession->g_sLFYCaption = $g_sLFYCaption;

                $companySession->g_iFYCurrencyId = $g_iFYCurrencyId;
                $companySession->g_iFYCurrencyDigit = $g_iFYCurrencyDigit;
                $companySession->g_dFromPostingDate = $g_dFromPostingDate;
                $companySession->g_dToPostingDate = $g_dToPostingDate;
                $companySession->g_dPostingDate = $g_dPostingDate;
                $companySession->g_dLockDate = $g_dLockDate;
                $companySession->g_bFYLock = $g_bFYLock;
                $accCompMasterList = array();
            }
            $response->setContent(json_encode($accCompMasterList));
            return $response;
        }else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function paymentAdviceRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();
                    $slTypeId = $postData['slTypeId'];
                    $type = $postData['mode'];
                    $resp =array();
                    if($type == 'getSLList'){
                        $select = $sql->select();
                        $select->from(array("a" => "FA_PayAdviceDet"))
                            ->join(array("b" => "FA_SubLedgerType"), "a.SLTypeId=b.SubLedgerTypeId", array('SubLedgerTypeName','SubLedgerTypeId'), $select::JOIN_INNER)
                            ->columns(array("PayAdviceId","PayAdviceDate"=> new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"),"PayAdviceNo"
                            ,"SLedgerId","BillAmount","Approve" => new Expression("case when a.Approve='A' then 'Advance' else 'Bill' end ")));
                        if($slTypeId != 0){
                            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId and b.SubLedgerTypeId=$slTypeId");
                        }else{
                            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId");
                        }
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $resp= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
//                    $this->redirect()->toRoute('fa/default', array('controller' => 'index', 'action' => 'groupcompanytransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_PayAdviceDet"))
                ->join(array("b" => "FA_SubLedgerType"), "a.SLTypeId=b.SubLedgerTypeId", array('SubLedgerTypeName','SubLedgerTypeId'), $select::JOIN_INNER)
                ->columns(array("PayAdviceId","PayAdviceDate"=> new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"),"PayAdviceNo","TotalAmount"
                ,"SLedgerId","BillAmount","Approve","BillType" => new Expression("case when a.BillType='A' then 'Advance' when a.BillType='B' then 'Bill' when a.BillType='O' then 'Advance/Bill' end ")));
            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $paymentadviceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //select * from FA_SubLedgerType where SubLedgerTypeId in (select SLTypeId from FA_PayAdviceDet)
            $select1 = $sql->select();
            $select1->from(array("a1" => "FA_PayAdviceDet"))
                    ->columns(array("SLTypeId"));

            $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerType"))
                    ->columns(array("SubLedgerTypeId","SubLedgerTypeName"));
            $select->where('a.SubLedgerTypeId IN('.$select1->getSqlString().')');
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $slList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->paymentadviceList = $paymentadviceList;
            $this->_view->slList = $slList;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function paymentjournalAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $endDate= date('d-M-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));
        $g_iFYCurrencyId= $this->bsf->isNullCheck(CommonHelper::getFA_SessionFYCurrencyId(),'number');

        $EntryId= $this->bsf->isNullCheck($this->params()->fromRoute('entryId'),'number');

        if($this->getRequest()->isXmlHttpRequest()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $connection = $dbAdapter->getDriver()->getConnection();
            $postData = $request->getPost();
            $type= $this->bsf->isNullCheck($postData['type'], 'string');
            $bookType= $this->bsf->isNullCheck($postData['paymentBookType'], 'string');

                try {
                    $resp = array();
                    switch($type){
                        case 'loadSubLedgerName':
                            $ledgerTypeId= $this->bsf->isNullCheck($postData['ledgerType'], 'string');
                            if($ledgerTypeId != 0){
                                $ledgerTypeId =$ledgerTypeId;
                            }else{
                                $ledgerTypeId =0;
                            }
                            $select = $sql->select();
                            $select->from(array("a" => "FA_SubLedgerMaster"))
                                ->columns(array("data" => new Expression("SubLedgerId"),"value" => new Expression("SubLedgerName")));
                            $select->where("a.SubLedgerTypeId IN($ledgerTypeId)");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resp['SubLedgerNameList'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;

                        case 'loadBookName':
                            $paymentBookType= $this->bsf->isNullCheck($postData['paymentBookType'], 'string');
                            if($paymentBookType == 'B'){
                                $paymentBookType ='BA';
                            }else{
                                $paymentBookType ='CA';
                            }
                            $select = $sql->select(); //BookDetails or cash details
                            $select->from(array("a" => "FA_Account"))
                                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
                            $select->where("AccountType IN ('$paymentBookType') and a.CompanyId=$companyId and a.FYearId=$fYearId");
                            $select->order(array("b.AccountName"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resp['BookList'] =$bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            break;

                        case 'loadPayAdvDet':
                            /*Pick List for payadvance det Query Start*/
                            $EntryId=0;//for edit mode
//                            $CurrencyId=$g_iFYCurrencyId;
                            $CurrencyId=0;
//                            $AccountId=46; //data availabe for this account id
                            $AccountId=$this->bsf->isNullCheck($postData['AccountId'], 'string');

                            $subQue1 = $sql->select();
                            $subQue1->from(array("sq1" => "WF_CurrencyMaster"))
                                ->columns(array("CurrencyName"))
                                ->where("CurrencyId=$CurrencyId");

                            $subQue4 = $sql->select();
                            $subQue4->from(array("squ4" => "FA_BillRegister"))
                                ->columns(array("BillRegisterId"))
                                ->where("CompanyId=$companyId AND BillDate<='$endDate' AND AccountId IN ($AccountId)");

                            $subQue2 = $sql->select();
                            $subQue2->from(array("sq2" => "FA_PayAdviceTrans"))
                                ->columns(array("PayAdviceId"))
                                ->where("CompanyId=$companyId AND EntryId=0 AND BillRegisterId IN (".$subQue4->getSqlString().")");

                            /*$subQue4a = $sql->select();
                            $subQue4a->from(array("sq3" => "FA_PayAdviceTrans"))
                                ->columns(array("PayAdviceId"))
                                ->where("CompanyId=$companyId AND EntryId=0 AND BillRegisterId IN");*/

                            $subQuery2 = $sql->select();
                            $subQuery2->from(array("sq2" => "WF_CurrencyMaster"))
                                ->columns(array("CurrencyName"))
                                ->where("CurrencyId=a.CurrencyId");

                            $selectPickList = $sql->select();
                            $selectPickList ->from(array("a" => "FA_PayAdviceDet"))
                                ->columns(array('PayAdviceId','PayAdviceDate'=> new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"),'PayAdviceNo','TotalAmount','CurrencyId'=>new Expression("A.CurrencyId"),'BillAmount','ReturnAmount'
                                ,'CurrencyName'=>new Expression("Case when a.CurrencyId=0 then(".$subQue1->getSqlString().")"." else(".$subQuery2->getSqlString().") end")
                                ,'ForexRate'=>new Expression("CAST( a.PayForexRate AS decimal(18,6))")
                                ,'NetAmount'=>new Expression("CASE WHEN a.CurrencyId<>0 THEN CAST( 0 AS decimal(18,3)) ELSE TotalAmount END")
                                ,'Sel'=>new Expression("CAST( 1 AS Bit)")));
                            $selectPickList->where("CompanyId=$companyId AND Approve='Y' AND PayAdviceId IN (".$subQue2->getSqlString().") ");//AND PayAdviceId NOT IN (".$subQue2->getSqlString().")
                            //Need to add above condition to query

                            $subQuery1 = $sql->select();
                            $subQuery1->from(array("sq1" => "WF_CurrencyMaster"))
                                ->columns(array("CurrencyName"))
                                ->where("CurrencyId= $CurrencyId");

                            if($EntryId != 0) {
                                $subQuery3 = $sql->select();
                                $subQuery3->from(array("sq3" => "FA_PayAdviceTrans"))
                                    ->columns(array("PayAdviceId"))
                                    ->where("CompanyId=$companyId AND EntryId=$EntryId");

                                $select = $sql->select();
                                $select->from(array("a" => "FA_PayAdviceDet"))
                                    ->columns(array('PayAdviceId', 'PayAdviceDate' => new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"), 'PayAdviceNo', 'TotalAmount', 'CurrencyId', 'BillAmount', 'ReturnAmount'
                                    , 'CurrencyName' => new Expression("Case when a.CurrencyId=0 then(" . $subQuery1->getSqlString() . ")" . " else(" . $subQuery2->getSqlString() . ") end")
                                    , 'ForexRate' => new Expression("CAST( a.PayForexRate AS decimal(18,6))")
                                    , 'NetAmount' => new Expression("CASE WHEN a.CurrencyId<>0 THEN CAST( (a.TotalAmount*a.PayForexRate) AS decimal(18,3)) ELSE TotalAmount END")
                                    , 'Sel' => new Expression("CAST( 1 AS Bit)")));
                                $select->where("CompanyId=$companyId AND Approve='Y' AND PayAdviceId IN (" . $subQuery3->getSqlString() . ")");
                                $selectPickList->combine($select, 'Union ALL');
                            }

                            $selectFin = $sql->select();
                            $selectFin->from(array("g" => $selectPickList))
                                ->columns(array("*"));
                            $selectFin->order("g.PayAdviceId");
                            $statement = $sql->getSqlStringForSqlObject($selectFin);
                            $resp['PayAdvPickList'] =$selectPickList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            /*Pick List for payadvance det Query End*/
                            break;

                        case 'loadPayAdvTrans':
                            $payAdvPickIds= $this->bsf->isNullCheck($postData['payAdvPickIds'], 'string');
                            $AccountId=$this->bsf->isNullCheck($postData['AccountId'], 'string');
                            $EntryId=0;//for edit mode
                            /*
                             *
                            SELECT A.PayAdviceId, A.BillRegisterId,B.SubLedgerId,B.CostCentreId,CC.CostCentreName CCName
                            ,A.ApproveAmount [Current],  B.CurrencyId, B.Advance, A.ApproveAmount ForexAmount
                            , A.BillForexRate ForexRate,B.BranchId
                            FROM FA_PayAdviceTrans A
                            INNER JOIN FA_BillRegister B ON A.BillRegisterId=B.BillRegisterId
                            INNER JOIN WF_CostCentre CC ON CC.CostCentreId=B.CostCentreId
                            WHERE B.AccountId=46 AND A.PayAdviceId IN (
                            SELECT PayAdviceId FROM FA_PayAdviceDet WHERE CompanyId=1 and FYearId=1 AND
                            PayAdviceDate<='31-Mar-2018' AND Approve='Y' ) AND A.EntryId=0

                            UNION ALL

                            SELECT A.PayAdviceId, A.BillRegisterId,B.SubLedgerId,B.CostCentreId,CC.CostCentreName CCName
                            ,A.ApproveAmount [Current],  B.CurrencyId, B.Advance, A.ApproveAmount ForexAmount,
                            A.BillForexRate ForexRate,B.BranchId
                            FROM FA_PayAdviceTrans A
                            INNER JOIN FA_BillRegister B ON A.BillRegisterId=B.BillRegisterId
                            INNER JOIN WF_CostCentre CC ON CC.CostCentreId=B.CostCentreId
                            WHERE B.AccountId=46 AND A.PayAdviceId IN (
                            SELECT PayAdviceId FROM FA_PayAdviceDet WHERE CompanyId=1 and FYearId=1 AND
                            PayAdviceDate<='31-Mar-2018' AND Approve='Y' ) AND A.EntryId=$iEntryId AND EntryId<>0
                             */

                            $subQuery = $sql->select();
                            $subQuery->from(array("a" => "FA_PayAdviceDet"))
                                ->columns(array("PayAdviceId"))
                                ->where("CompanyId=$companyId and FYearId=$fYearId AND PayAdviceDate<='$endDate' AND Approve='Y'");

                            $select = $sql->select();
                            $select->from(array("A" => "FA_PayAdviceTrans"))
                                ->join(array("B" => "FA_BillRegister"), "A.BillRegisterId=B.BillRegisterId", array(), $select::JOIN_INNER)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "B.SubLedgerId=SLM.SubLedgerId", array(), $select::JOIN_INNER)
                                ->join(array("CC" => "WF_CostCentre"), "B.CostCentreId=CC.CostCentreId", array(), $select::JOIN_INNER)
                                ->columns(array('PayTransId','PayAdviceId','BillRegisterId','BillNo'=>new Expression("B.BillNo"),'ApproveAmount'
                                ,'BillDate'=>new Expression("FORMAT(B.BillDate, 'dd-MM-yyyy')"),'SubLedgerId'=>new Expression("SLM.SubLedgerId")
                                ,'SubLedgerName'=>new Expression("SLM.SubLedgerName"),'CostCentreId'=>new Expression("B.CostCentreId")
                                ,'CostCentreName'=>new Expression("CC.CostCentreName"),'CCName'=>new Expression("CC.CostCentreName")
                                ,'AccountId'=>new Expression("B.AccountId"),'Current'=>new Expression("A.ApproveAmount")
                                ,'CurrencyId'=>new Expression("B.CurrencyId"),'Advance'=>new Expression("B.Advance")
                                ,'ForexAmount'=>new Expression("A.ApproveAmount"),'ForexRate'=>new Expression("A.BillForexRate")
                                ,'BranchId'=>new Expression("B.BranchId"),'SubLedgerTypeId'=>new Expression("SLM.SubLedgerTypeId")
                                ));
                            $select->where->expression("B.AccountId IN ($AccountId) AND A.EntryId=0 AND PayAdviceId IN ?",array($subQuery));

                            $selectEdit = $sql->select();
                            $selectEdit->from(array("A" => "FA_PayAdviceTrans"))
                                ->join(array("B" => "FA_BillRegister"), "A.BillRegisterId=B.BillRegisterId", array(), $selectEdit::JOIN_INNER)
                                ->join(array("SLM" => "FA_SubLedgerMaster"), "B.SubLedgerId=SLM.SubLedgerId", array(), $selectEdit::JOIN_INNER)
                                ->join(array("CC" => "WF_CostCentre"), "B.CostCentreId=CC.CostCentreId", array(), $selectEdit::JOIN_INNER)
                                ->columns(array('PayTransId','PayAdviceId','BillRegisterId','BillNo'=>new Expression("B.BillNo"),'ApproveAmount'
                                ,'BillDate'=>new Expression("FORMAT(B.BillDate, 'dd-MM-yyyy')"),'SubLedgerId'=>new Expression("SLM.SubLedgerId")
                                ,'SubLedgerName'=>new Expression("SLM.SubLedgerName"),'CostCentreId'=>new Expression("B.CostCentreId")
                                ,'CostCentreName'=>new Expression("CC.CostCentreName"),'CCName'=>new Expression("CC.CostCentreName")
                                ,'AccountId'=>new Expression("B.AccountId"),'Current'=>new Expression("A.ApproveAmount")
                                ,'CurrencyId'=>new Expression("B.CurrencyId"),'Advance'=>new Expression("B.Advance")
                                ,'ForexAmount'=>new Expression("A.ApproveAmount"),'ForexRate'=>new Expression("A.BillForexRate")
                                ,'BranchId'=>new Expression("B.BranchId"),'SubLedgerTypeId'=>new Expression("SLM.SubLedgerTypeId")
                                ));
                            $selectEdit->where->expression("B.AccountId IN ($AccountId) AND A.EntryId=$EntryId AND EntryId<>0 AND PayAdviceId IN ?",array($subQuery));
                            $selectEdit->combine($select, 'Union ALL');
                            $statement = $sql->getSqlStringForSqlObject($selectEdit);
                            $resp['payAdvTrans'] =$payAdvTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            /*$select = $sql->select();
                            $select->from(array("A" => "FA_PayAdviceTrans"))
                                ->join(array("B" => "FA_BillRegister"), "A.BillRegisterId=B.BillRegisterId", array(), $select::JOIN_INNER)
                                ->join(array("C" => "FA_SubLedgerMaster"), "B.SubLedgerId=C.SubLedgerId", array(), $select::JOIN_INNER)
                                ->join(array("D" => "WF_CostCentre"), "B.CostCentreId=D.CostCentreId", array(), $select::JOIN_INNER)
                                ->columns(array('PayTransId','PayAdviceId','BillRegisterId','BillNo'=>new Expression("B.BillNo"),'ApproveAmount'
                                ,'BillDate'=>new Expression("FORMAT(B.BillDate, 'dd-MM-yyyy')"),'SubLedgerId'=>new Expression("C.SubLedgerId")
                                ,'SubLedgerName'=>new Expression("C.SubLedgerName"),'CostCentreId'=>new Expression("B.CostCentreId")
                                ,'CostCentreName'=>new Expression("D.CostCentreName"),'AccountId'=>new Expression("B.AccountId")
                                ));
                            $select->where("PayAdviceId IN ($payAdvPickIds) and (B.CostCentreId<>0)");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resp['payAdvTrans'] =$payAdvTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

                            break;

                        case 'bookDet':
                            $bookId= $this->bsf->isNullCheck($postData['bookId'], 'number');
                            $payMode= $this->bsf->isNullCheck($postData['payMode'], 'number');

                            $select = $sql->select();
                            $select->from(array("a" => "FA_CashBankDet"))
                                ->columns(array('AccountType' => new Expression("CashOrBank")))
                                ->where("a.AccountId=$bookId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resp['AccTypeDet']=$fromBookName= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $resp['chequeDet']=array();

                            if($payMode != 3) {
                                $select = $sql->select();
                                $select->from(array("a" => "FA_ChequeTrans"))
                                    ->columns(array('data' => new Expression('ChequeTransId'), 'value' => new Expression('ChequeNo')))
                                    ->where("a.AccountId=$bookId AND Used=0 AND Cancel=0");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $resp['chequeDet'] = $fromBookName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }

                            break;

                        case 'loadQualEdit':
                            $ReferenceId=$this->bsf->isNullCheck($postData['EntryId'], 'number');
                            $PayAdvId=$this->bsf->isNullCheck($postData['PayAdviceId'], 'number');
                            $BillRegisterId=$this->bsf->isNullCheck($postData['BillRegisterId'], 'number');

                            $subQuery = $sql->select();
                            $subQuery->from(array("a" => "FA_EntryTaxTrans"))
                                ->columns(array("QualifierId"))
                                ->where("ReferenceId=$ReferenceId and a.PayAdvId=$PayAdvId and a.BillRegisterId=$BillRegisterId and a.YesNo=1");

                            $unselectQual = $sql->select();
                            $unselectQual->from(array("a" => "Proj_QualifierTrans"))
                                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $unselectQual::JOIN_INNER)
                                ->columns(array('QualifierId','YesNo'=> new Expression("1-1"),'Expression','ExpPer','TaxablePer','TaxPer','Sign'
                                ,'SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer'
                                ,'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))")
                                ,'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))")
                                ,'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))")
                                ,'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))")
                                ,'KKCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SBCessAmt'=> new Expression("CAST(0 As Decimal(18,2))")
                                ,'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SortId','TaxAccountId'=>new Expression("1-1"),'TaxSubLedgerId'=>new Expression("1-1")
                                ,'QualifierName'=>new Expression("b.QualifierName"),'QualifierTypeId'=>new Expression("b.QualifierTypeId"),'RefId'=>new Expression("b.RefNo")
                                ));
                            $unselectQual->where(array('a.QualType' => 'W'));
                            $unselectQual->where->expression("a.QualifierId Not IN ?",array($subQuery));

                            $selectQual = $sql->select();
                            $selectQual->from(array("a" => "FA_EntryTaxTrans"))
                                ->join(array("c" => "Proj_QualifierTrans"), "a.QualifierId=c.QualifierId", array(), $selectQual::JOIN_INNER)
                                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $selectQual::JOIN_INNER)
                                ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign'
                                ,'SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer'
                                ,'BaseAmount'=> new Expression("ExpressionAmt"),'ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt'
                                ,'EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId'=> new Expression("c.SortId"),'TaxAccountId','TaxSubLedgerId'
                                ,'QualifierName'=> new Expression("b.QualifierName"),'QualifierTypeId'=> new Expression("b.QualifierTypeId")
                                ,'RefId'=> new Expression("b.RefNo")
                                ));
                            $selectQual->where("a.ReferenceId=$ReferenceId and a.PayAdvId=$PayAdvId and a.BillRegisterId=$BillRegisterId and a.YesNo=1 and c.QualType='W'");
                            $selectQual ->combine($unselectQual,"Union All");

                            $selectFin = $sql->select();
                            $selectFin->from(array("g" => $selectQual))
                                ->columns(array("*"));
                            $selectFin->order("g.SortId");
                            $statement = $sql->getSqlStringForSqlObject($selectFin);
                            $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $sHtml = Qualifier::getFAQualifier($qualList);
                            $resp['QualHtml']=$sHtml;
                            break;
                            /*select * from (
                                SELECT [a].[QualifierId] AS [QualifierId], 0 as YesNo, [a].[Expression] AS [Expression],
 [a].[ExpPer] AS [ExpPer], [a].[TaxablePer] AS [TaxablePer], [a].[TaxPer] AS [TaxPer],
  [a].[Sign] AS [Sign], [a].[SurCharge] AS [SurCharge], [a].[EDCess] AS [EDCess],
   [a].[HEDCess] AS [HEDCess], [a].[KKCess] AS [KKCess], [a].[SBCess] AS [SBCess],
   [a].[NetPer] AS [NetPer], CAST(0 As Decimal(18,2)) AS [BaseAmount],
   CAST(0 As Decimal(18,2)) AS [ExpressionAmt], CAST(0 As Decimal(18,2)) AS [TaxableAmt],
   CAST(0 As Decimal(18,2)) AS [TaxAmt], CAST(0 As Decimal(18,2)) AS [SurChargeAmt],
   CAST(0 As Decimal(18,2)) AS [EDCessAmt], CAST(0 As Decimal(18,2)) AS [HEDCessAmt],
   CAST(0 As Decimal(18,2)) AS [KKCessAmt], CAST(0 As Decimal(18,2)) AS [SBCessAmt],
   CAST(0 As Decimal(18,2)) AS [NetAmt], [b].[QualifierName] AS [QualifierName],
   [b].[QualifierTypeId] AS [QualifierTypeId], RefNo AS [RefId],a.SortId,0 as TaxAccountId,0 as TaxSubLedgerId FROM [Proj_QualifierTrans] AS [a]
   INNER JOIN [Proj_QualifierMaster] AS [b] ON [a].[QualifierId]=[b].[QualifierId]
   WHERE [a].[QualType] = 'W'
                        and a.QualifierId Not in (select QualifierId from FA_EntryTaxTrans a
   Where a.ReferenceId=73 and a.PayAdvId=10 and a.BillRegisterId=30 and a.YesNo=1)
   union all
   select a.QualifierId,a.YesNo,a.Expression,
   [a].[ExpPer] AS [ExpPer], [a].[TaxablePer] AS [TaxablePer], [a].[TaxPer] AS [TaxPer],
  [a].[Sign] AS [Sign], [a].[SurCharge] AS [SurCharge], [a].[EDCess] AS [EDCess],
   [a].[HEDCess] AS [HEDCess], [a].[KKCess] AS [KKCess], [a].[SBCess] AS [SBCess],
   [a].[NetPer] AS [NetPer]
   ,ExpressionAmt as BaseAmount,ExpressionAmt,TaxableAmt, TaxAmt, SurChargeAmt,
   EDCessAmt, HEDCessAmt, KKCessAmt, SBCessAmt, NetAmt
   , [b].[QualifierName] AS [QualifierName],
   [b].[QualifierTypeId] AS [QualifierTypeId],[b].RefNo as RefId,c.SortId,a.TaxAccountId,a.TaxSubLedgerId
    from FA_EntryTaxTrans a
	INNER JOIN [Proj_QualifierTrans] as [c] on [a].QualifierId=[c].QualifierId
	INNER JOIN [Proj_QualifierMaster] AS [b] ON [c].[QualifierId]=[b].[QualifierId]
   Where a.ReferenceId=73 and a.PayAdvId=10 and a.BillRegisterId=30 and a.YesNo=1 and c.QualType='W'
   ) G

   ORDER BY G.SortId ASC
                        */

                        default:
                            $resp='default';
                            break;
                    }

                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }
//                $response->setContent();
                $response->setContent(json_encode($resp));
                return $response;
        } else {
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection->beginTransaction();
                $iEntryId= $this->bsf->isNullCheck($postData['EntryId'], 'number');
                $adviceRowId= $this->bsf->isNullCheck($postData['operationalJournalRowId'], 'number');
                $EntryType="P";
                $sTransType = "P";
                $JournalType="O";
                $voucherNo = $this->bsf->isNullCheck($postData['voucherNo'], 'string');
                $voucherDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate'], 'date')));
                $BookId = $this->bsf->isNullCheck($postData['bookName'], 'number');
                $AccountIds = $this->bsf->isNullCheck($postData['accountHeadIds'], 'string');
                $AccountId=0;
                $AccountIdCount = $this->bsf->isNullCheck($postData['accountHeadLength'], 'number');
                if($AccountIdCount==1){
                    $AccountId= $this->bsf->isNullCheck($AccountIds, 'number');
                }
                //$PayAdvIds=$this->bsf->isNullCheck($postData['payAdvPickIds'], 'string'); //selected picklist
                $BankOrCash= $this->bsf->isNullCheck($postData['paymentBook'], 'string');

                $SubLedgerTypeId = 0;
                $SubLedgerId = 0;
                $ServiceTypeId = 0;
                $FromCostCentreId = 0;
                $ToCostCentreId = 0;
                $StateId = 0;
                $ChequeTransId = 0;
                $ChequeNo="";
                $ChequeDate="";
                $ChequeDescription="";
                if($BankOrCash=="B"){
                    $ChequeTransId = $this->bsf->isNullCheck($postData['chequeTransId'], 'number');
                    $ChequeNo = $this->bsf->isNullCheck($postData['transactionNo'], 'string');
                    $ChequeDescription = "";//$this->bsf->isNullCheck($postData['chequeDescription'], 'string');
                }
                $prevChequeTransId = $this->bsf->isNullCheck($postData['previousChequeTransId'], 'number');
                $PayMode=$this->bsf->isNullCheck($postData['paymentMode'], 'string');
                if($PayMode=="RTGS/NEFT (Fund Transfer)"){
                    $ChequeNo = "(RTGS/NEFT)";
                }
                $ChequeDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['transactionDate'], 'date')));
                $PDC=0;
                $BranchId=0;
                $Narration = "";//$this->bsf->isNullCheck($postData['narration'], 'string');
                $OtherCharges =$this->bsf->isNullCheck($postData['bankCharges'], 'number');
                $OtherAccountId=0;
                $m_iHOCCId =$this->bsf->isNullCheck($postData['hiddenGetHOAccountId'], 'number');
                if($OtherCharges!=0){
                    $OtherAccountId=$this->bsf->isNullCheck($postData['bankExpenseAccountId'], 'number');//bankExpenseAccountId
                }
                $Amount  = $this->bsf->isNullCheck($postData['netAmount'], 'number') + $OtherCharges;//netAmountwithQualifier+other charges
                $mode="A";

                try {

                    if($iEntryId==0) {
                        $insert = $sql->insert();
                        $insert->into('FA_EntryMaster');
                        $insert->Values(array('VoucherDate' => $voucherDate
                        , 'VoucherNo' => $voucherNo
                        , 'RelatedVNo' => ''
                        , 'BankCashVoucherNo' => ''
                        , 'BookVoucherNo' => ''
                        , 'JournalType' => $JournalType
                        , 'EntryType' => $EntryType
                        , 'BookId' => $BookId
                        , 'AccountId' => $AccountId
                        , 'SubLedgerTypeId' => $SubLedgerTypeId
                        , 'SubLedgerId' => $SubLedgerId
                        , 'PayType' => $PayMode
                        , 'ChequeTransId' => $ChequeTransId
                        , 'ChequeNo' => $ChequeNo
                        , 'ChequeDate' => $ChequeDate
                        , 'ChequeDescription' => $ChequeDescription
                        , 'Narration' => $Narration
                        , 'Amount' => $Amount
                        , 'FromCostCentreId' => $FromCostCentreId
                        , 'CostCentreId' => $ToCostCentreId
                        , 'CompanyId' => $companyId
                        , 'RefNo' => ''
                        , 'RelatedRefNo' => ''
                        , 'PDC' => $PDC
                        , 'BranchId' => $BranchId
                        , 'OtherCharges' => $OtherCharges
                        , 'OtherAccountId' => $OtherAccountId
                        , 'Approve' => 'Y'
                        , 'IsAppReady' => 1
                        , 'FYearId' => $fYearId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iEntryId= $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $mode="E";

                        $iHistoryId = 0;
                        $select = $sql->select();
                        $select->from(array('a' => 'FA_EntryMaster' ))
                            ->columns(array( 'EntryId', 'VoucherNo', 'VoucherDate', 'BankCashVoucherNo', 'BookVoucherNo', 'EntryType', 'JournalType','BookId','AccountId'
                            , 'SubLedgerTypeId','SubLedgerId','BranchId','PayType','ChequeTransId','ChequeNo','ChequeDate','ChequeDescription','Narration','Amount','FromCostCentreId','Cancel','CancelDate'
                            , 'CostCentreId','BRS','BRSDate','PDC','CompanyId','IsLock','UserId'=>new Expression("$userId"),'FYearId' ));
                        $select->where("a.EntryId=$iEntryId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastEntryMaster' );
                        $insert->columns(array('EntryId', 'VoucherNo', 'VoucherDate', 'BankCashVoucherNo', 'BookVoucherNo', 'EntryType', 'JournalType', 'BookId', 'AccountId'
                               ,'SubLedgerTypeId', 'SubLedgerId', 'BranchId', 'PayType', 'ChequeTransId', 'ChequeNo', 'ChequeDate', 'ChequeDescription', 'Narration', 'Amount', 'FromCostCentreId', 'Cancel', 'CancelDate'
                               ,'CostCentreId', 'BRS', 'BRSDate', 'PDC', 'CompanyId', 'IsLock', 'UserId','FYearId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        $iHistoryId= $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_EntryTrans' ))
                            ->columns(array('HistoryId'=>new Expression("$iHistoryId"), 'EntryTransId', 'VoucherDate','VoucherNo','RefId','TransType','RefType'
                            ,'AccountId','RelatedAccountId','SubLedgerTypeId','SubLedgerId'
                            ,'RelatedSLId','CostCentreId','BranchId','Amount','Remarks','FromAdjust','TaxId','RemitId','CompanyId','PDC/Cancel'
                            , 'UserId'=>new Expression("$userId") ));
                        $select->where("a.RefId=$iEntryId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastEntryTrans' );
                        $insert->columns(array('HistoryId','EntryTransId','VoucherDate','VoucherNo','RefId','TransType','RefType','AccountId','RelatedAccountId','SubLedgerTypeId','SubLedgerId'
                               ,'RelatedSLId','CostCentreId','BranchId','Amount','Remarks','FromAdjust','TaxId','RemitId','CompanyId','PDC/Cancel','UserId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_Adjustment' ))
                            ->columns(array('HistoryId'=>new Expression("$iHistoryId"), 'AdjustmentId', 'EntryType','EntryId','AdviceId','BillRegisterId'
                            ,'RefDate', 'RefNo', 'ChqNo', 'ChqDate', 'Amount', 'Refund', 'CompanyId', 'FYearId'
                            ,'WebUpdate', 'UserId'=>new Expression("$userId"),'QualAmount','NetAmount' ));
                        $select->where("a.EntryId=$iEntryId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastAdjustment' );
                        $insert->columns(array('HistoryId','AdjustmentId','EntryType','EntryId','AdviceId','BillRegisterId','RefDate'
                        ,'RefNo','ChqNo','ChqDate','Amount','Refund','CompanyId','FYearId','WebUpdate','UserId','QualAmount','NetAmount'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_EntryTaxTrans' ))
                            ->columns(array('HistoryId'=>new Expression("$iHistoryId"), 'ReferenceId','PayAdvId','BillRegisterId','RefDate','RefNo','CostCentreId','RefSLId','JVType','QualifierId','QualTypeId'
                            ,'BillValue','GrossValue','ExpPer','TaxablePer','TaxPer','SurCharge','EDCess','HEDCess','KKCess','SBCess'
                            ,'NetPer','ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt'
                            ,'NetAmt','TaxAccountId','TaxSubLedgerId','CompanyId','FYearId'
                            ,'RemitId','Expression','Sign','YesNo','Cancel', 'UserId'=>new Expression("$userId") ));
                        $select->where("a.ReferenceId=$iEntryId ");

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastEntryTaxTrans' );
                        $insert->columns(array('HistoryId', 'ReferenceId','PayAdvId','BillRegisterId','RefDate','RefNo','CostCentreId','RefSLId','JVType','QualifierId','QualTypeId'
                        ,'BillValue','GrossValue','ExpPer','TaxablePer','TaxPer','SurCharge','EDCess','HEDCess','KKCess','SBCess'
                        ,'NetPer','ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt'
                        ,'NetAmt','TaxAccountId','TaxSubLedgerId','CompanyId','FYearId'
                        ,'RemitId','Expression','Sign','YesNo','Cancel', 'UserId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        //UPDATE [{0}].dbo.ChequeTrans SET Amount=0,Used=0,Cancel=0,CancelDate=NULL,CancelRemarks='', PDC=0,PDCDate=NULL, PDCClear=0, PDCClearDate=NULL, IssueDate=NULL,
                        //ReconDate=NULL, FYearId=0 WHERE ChequeTransId={1}", BsfGlobal.g_sFaDBName, argEntry.OChequeTransId
                        $update = $sql->update();
                        $update->table("FA_ChequeTrans")
                            ->set(array('Amount' => 0, 'Used' => 0, 'Cancel' => 0, 'CancelDate' => null, 'CancelRemarks' => ''
                            , 'PDC' => 0, 'PDCDate' => null, 'PDCClear' => 0, 'PDCClearDate' => null, 'IssueDate' => null
                            , 'ReconDate' => null, 'FYearId' => 0));
                        $update->where("ChequeTransId=$prevChequeTransId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*
                         * UPDATE [{0}].dbo.ForexBillRegister SET PaidAmount=PaidAmount-SummedAmt FROM [{0}].dbo.ForexBillRegister FBR " +
                                                 "JOIN (SELECT BillRegisterId,Sum(ApproveAmount) SummedAmt FROM [{0}].dbo.PayAdviceTrans WHERE EntryId={1} AND CompanyId={2} AND FYearId={3}  " +
                                                 "GROUP BY BillRegisterId) PAT ON PAT.BillRegisterId=FBR.BillRegisterId", BsfGlobal.g_sFaDBName, argEntry.EntryId, BsfGlobal.g_lCompanyId, BsfGlobal.g_lYearId
                         */
                        $update = $sql->update();
                        $update->table("FA_ForexBillRegister")
                            ->set(array('PaidAmount' => new Expression ("PaidAmount-SummedAmt FROM FA_ForexBillRegister FBR
                                JOIN (SELECT BillRegisterId,Sum(ApproveAmount) SummedAmt FROM FA_PayAdviceTrans
                                WHERE EntryId=$iEntryId AND CompanyId=$companyId AND FYearId=$fYearId
                                GROUP BY BillRegisterId) PAT ON PAT.BillRegisterId=FBR.BillRegisterId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        #region Advance (DC,HO,PO,SO,WO,SA & WA)

                        /*
                         * sSql = "SELECT A.BillRegisterId,A.ReferenceId,A.RefType,B.ApproveAmount,A.BillType FROM [" + BsfGlobal.g_sFaDBName + "].dbo.BillRegister A INNER JOIN [" + BsfGlobal.g_sFaDBName + "].dbo.PayAdviceTrans B " +
                                       "ON A.BillRegisterId=B.BillRegisterId WHERE A.RefType IN ('PO','PT','WO','WA','DC','HO','SO','SA') AND B.EntryId=" + iEntryId + " AND B.FYearId=" + BsfGlobal.g_lYearId + "";

                         */

                        //SELECT TermsId, Title,TermType FROM [{0}].dbo.TermsMaster WHERE Title IN ('Advance','Against Test Certificate','Against Delivery')", BsfGlobal.g_sWorkFlowDBName

                        $select = $sql->select();
                        $select->from(array("A" => "FA_BillRegister"))
                            ->join(array("B" => "FA_PayAdviceTrans"), "A.BillRegisterId=B.BillRegisterId", array(), $select::JOIN_INNER)
                            ->columns(array('BillRegisterId','ReferenceId','RefType','ApproveAmount'=>new Expression ("B.ApproveAmount"),'BillType'));
                        $select->where("A.RefType IN ('PO','PT','WO','WA','DC','HO','SO','SA') AND B.EntryId=$iEntryId AND B.FYearId=$fYearId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $billRegDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($billRegDetList as &$billRegDetLists) {
                            if($billRegDetLists['RefType']=="PO"){
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Advance' AND TermType='S'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iPORegId = $billRegDetLists['ReferenceId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];

                                    $update = $sql->update();
                                    $update->table('MMS_POPaymentTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - " . $Amt) ))
                                        ->where("PORegisterId=$iPORegId AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="PT") {
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Against Test Certificate' AND TermType='S'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iPORegId = $billRegDetLists['ReferenceId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];
                                    /*
                                     *UPDATE [{0}].dbo.POPaymentTerms SET PaidAmount=PaidAmount-{1} WHERE PORegisterId={2}
                                     * AND TermsId={3}", BsfGlobal.g_sMMSDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"], iTermsId
                                     */
                                    $update = $sql->update();
                                    $update->table('MMS_POPaymentTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - ". $Amt) ))
                                        ->where("PORegisterId=$iPORegId AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="DC") {
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Against Delivery' AND TermType='S'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iPORegId = $billRegDetLists['ReferenceId'];
                                    $ibill_RegId = $billRegDetLists['BillRegisterId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];
                                    /*
                                     * UPDATE [{0}].dbo.POPaymentTerms SET PaidAmount=PaidAmount-{1} WHERE TermsId={2} AND PORegisterId =" +
                                     "(SELECT TOP 1 PORegisterId FROM [{0}].dbo.DCAdvance WHERE DCRegisterId={3} AND RefId={4})",

                                     */
                                    $update = $sql->update();
                                    $update->table('MMS_POPaymentTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - ".$Amt) ))
                                        ->where("PORegisterId in (SELECT TOP 1 PORegisterId FROM MMS_DCAdvance WHERE DCRegisterId=$iPORegId AND RefId=$ibill_RegId) AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="WO") {
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Advance' AND TermType='W'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iWORegId = $billRegDetLists['ReferenceId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];
                                    /*
                                     * UPDATE [{0}].dbo.WOPaymentTermsNew SET PaidAmount=PaidAmount-{1} WHERE WORegisterId={2}
                                     * AND TermsId={3}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"], iTermsId);
                                     */
                                    $update = $sql->update();
                                    $update->table('WPM_WOGeneralTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - ". $Amt) ))
                                        ->where("WORegisterId=$iWORegId AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    /*
                                     * UPDATE [{0}].dbo.WORegister SET AdvanceAmt=AdvanceAmt-{1}
                                     * WHERE WORegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"]
                                     */
                                    $update = $sql->update();
                                    $update->table('WPM_WORegister')
                                        ->set(array('AdvanceAmt' => new Expression("AdvanceAmt - " . $Amt) ))
                                        ->where("WORegisterId=$iWORegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="HO") {
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Advance' AND TermType='W'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iHORegId = $billRegDetLists['ReferenceId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];
                                    /*
                                     * UPDATE [{0}].dbo.HOPaymentTermsNew SET PaidAmount=PaidAmount-{1} WHERE HORegisterId={2}
                                     * AND TermsId={3}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"], iTermsId*/
                                    $update = $sql->update();
                                    $update->table('WPM_HOGeneralTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - ".$Amt) ))
                                        ->where("HORegisterId=$iHORegId AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    /*
                                     * UPDATE [{0}].dbo.HORegister SET AdvanceAmt=AdvanceAmt-{1} WHERE
                                     * HORegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"]*/
                                    $update = $sql->update();
                                    $update->table('WPM_HORegister')
                                        ->set(array('AdvanceAmt' => new Expression("AdvanceAmt - " . $Amt) ))
                                        ->where("HORegisterId=$iHORegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="SO") {
                                $select = $sql->select();
                                $select->from(array("A" => "WF_TermsMaster"))
                                    ->columns(array('TermsId','Title','TermType'));
                                $select->where("Title ='Advance' AND TermType='W'");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($termsDetList) > 0) {
                                    $iTermsId = $termsDetList[0]['TermsId'];
                                    $iSORegId = $billRegDetLists['ReferenceId'];
                                    $Amt= $billRegDetLists['ApproveAmount'];
                                    /*
                                     * UPDATE [{0}].dbo.SOPaymentTermsNew SET PaidAmount=PaidAmount-{1} WHERE SORegisterId={2}
                                     * AND TermsId={3}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"], iTermsId*/
                                    $update = $sql->update();
                                    $update->table('WPM_GeneralTerms')
                                        ->set(array('PaidAmount' => new Expression("PaidAmount - " . $Amt) ))
                                        ->where("SORegisterId=$iSORegId AND TermsId=$iTermsId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    /*
                                     * UPDATE [{0}].dbo.SORegister SET AdvanceAmt=AdvanceAmt-{1}
                                     * WHERE SORegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"]*/
                                    $update = $sql->update();
                                    $update->table('WPM_SORegister')
                                        ->set(array('AdvanceAmt' => new Expression("AdvanceAmt - " . $Amt) ))
                                        ->where("SORegisterId=$iSORegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else if($billRegDetLists['RefType']=="WA") {
                                $iWARegId = $billRegDetLists['ReferenceId'];
                                $Amt = $billRegDetLists['ApproveAmount'];
                                /*
                                 * UPDATE [{0}].dbo.BillRegister SET AdvAmount=AdvAmount-{1}
                                 * WHERE BillRegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"]
                                 */
                                $update = $sql->update();
                                $update->table('WPM_WorkBillRegister')
                                    ->set(array('AdvanceAmt' => new Expression("AdvanceAmt - " . $Amt) ))
                                    ->where("BillRegisterId=$iWARegId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if($billRegDetLists['RefType']=="SA") {
                                $iSARegId = $billRegDetLists['ReferenceId'];
                                $d_Amount = $billRegDetLists['ApproveAmount'];
                                /*
                                 * UPDATE [{0}].dbo.SDRegister SET AdvancePaid=AdvancePaid-{1}
                                 * WHERE SDRegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[j]["ApproveAmount"], dt.Rows[j]["ReferenceId"]
                                 */
                                $update = $sql->update();
                                $update->table('WPM_SDRegister')
                                    ->set(array('AdvancePaid' => new Expression("AdvancePaid - " . $d_Amount) ))
                                    ->where("SDRegisterId=$iSARegId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }


                        #endregion
                        $update = $sql->update();
                        $update->table("FA_BillRegister")
                            ->set(array('PaidAmount' => new Expression ("A.PaidAmount-B.Amount FROM FA_BillRegister A
                                JOIN (SELECT BillRegisterId,SUM(Amount) Amount FROM FA_Adjustment
                                WHERE EntryId=$iEntryId AND CompanyId=$companyId AND FYearId=$fYearId
                                GROUP BY BillREgisterId) B ON A.BillRegisterId=B.BillRegisterId")));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        /*
                         * sSql = String.Format("UPDATE [{0}].dbo.BillRegister SET PaidAmount= A.PaidAmount-B.Amount FROM [{0}].dbo.BillRegister A JOIN (SELECT BillRegisterId,SUM(Amount) Amount FROM [{0}].dbo.Adjustment WHERE EntryId={1} AND CompanyId={2} AND FYearId={3} GROUP BY BillREgisterId) B ON A.BillRegisterId=B.BillRegisterId", BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lCompanyId, BsfGlobal.g_lYearId);
                        Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                        Command.ExecuteNonQuery();

                        sSql = String.Format("DELETE FROM [{0}].dbo.Adjustment WHERE EntryId={1} AND CompanyId={2} AND FYearId={3}", BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lCompanyId, BsfGlobal.g_lYearId);
                        Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                        Command.ExecuteNonQuery();

                        sSql = String.Format("DELETE FROM dbo.DenominationTrans WHERE EntryId={0}", iEntryId);
                        Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                        Command.ExecuteNonQuery();

                        sSql = String.Format("DELETE FROM TaxEntryTrans WHERE ReferenceId={0}", iEntryId);
                        Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                        Command.ExecuteNonQuery();*/

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_Adjustment')
                            ->where("EntryId=$iEntryId AND CompanyId=$companyId AND FYearId=$fYearId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryMasterBreakup')
                            ->where("EntryId=$iEntryId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTaxTrans')
                            ->where("ReferenceId=$iEntryId");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*
                         *
                        if (argEntry.EntryType == "P")
                        {
                            sSql = String.Format("UPDATE [{0}].dbo.PayAdviceTrans SET EntryId=0,FYearId=0 WHERE EntryId={1} AND CompanyId={2} AND FYearId={3}", BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lCompanyId, BsfGlobal.g_lYearId);
                            Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                            Command.ExecuteNonQuery();

                            sSql = String.Format("UPDATE [{0}].dbo.BillRefDet SET EntryId=0,FYearId=0 WHERE EntryId={1} AND FYearId={2}", BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lYearId);
                            Command = new SqlCommand(sSql, BsfGlobal.g_CompanyDB, tran);
                            Command.ExecuteNonQuery();
                        }*/
                        $update = $sql->update();
                        $update->table("FA_PayAdviceTrans")
                            ->set(array('EntryId' => 0, 'FYearId' => 0));
                        $update->where("EntryId=$iEntryId AND CompanyId=$companyId AND FYearId=$fYearId ");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table("FA_BillRefDet")
                            ->set(array('EntryId' => 0, 'FYearId' => 0));
                        $update->where("EntryId=$iEntryId AND FYearId=$fYearId ");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DELETE FROM EntryTrans WHERE RefType='{1}' AND RefId={0}", iEntryId, argEntry.JournalType);
                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTrans')
                            ->where("RefId=$iEntryId and RefType='O'");
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_EntryMaster')
                            ->set(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RelatedVNo' => ''
                            , 'BankCashVoucherNo' => ''
                            , 'BookVoucherNo' => ''
                            , 'JournalType' => $JournalType
                            , 'EntryType' => $EntryType
                            , 'BookId' => $BookId
                            , 'AccountId' => $AccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId
                            , 'SubLedgerId' => $SubLedgerId
                            , 'PayType' => $PayMode
                            , 'ChequeTransId' => $ChequeTransId
                            , 'ChequeNo' => $ChequeNo
                            , 'ChequeDate' => $ChequeDate
                            , 'ChequeDescription' => $ChequeDescription
                            , 'Narration' => $Narration
                            , 'Amount' => $Amount
                            , 'FromCostCentreId' => $FromCostCentreId
                            , 'CostCentreId' => $ToCostCentreId
                            , 'CompanyId' => $companyId
                            , 'RefNo' => ''
                            , 'RelatedRefNo' => ''
                            , 'PDC' => $PDC
                            , 'BranchId' => $BranchId
                            , 'OtherCharges' => $OtherCharges
                            , 'OtherAccountId' => $OtherAccountId
                            , 'Approve' => 'Y'
                            , 'IsAppReady' => 1
                            ))
                            ->where(array('EntryId' => $iEntryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    //Other charges AccPosting
                    if($OtherCharges!=0) {
                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iEntryId
                        , 'VoucherNo' => $voucherNo
                        , 'VoucherDate' => $voucherDate
                        , 'TransType' => 'D'
                        , 'RefType' => 'O'
                        , 'AccountId' => $OtherAccountId
                        , 'RelatedAccountId' => $BookId
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $m_iHOCCId
                        , 'Amount' => $OtherCharges
                        , 'Remarks' => ''
                        , 'IRemarks' => ''
                        , 'FromAdjust' => 0
                        , 'TaxId' => 0
                        , 'RefNo' => ''
                        , 'CompanyId' => $companyId
                        , 'Approve' => 'Y'));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iEntryId
                        , 'VoucherNo' => $voucherNo
                        , 'VoucherDate' => $voucherDate
                        , 'TransType' => 'C'
                        , 'RefType' => 'O'
                        , 'AccountId' => $BookId
                        , 'RelatedAccountId' => $OtherAccountId
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $m_iHOCCId
                        , 'Amount' => $OtherCharges
                        , 'Remarks' => ''
                        , 'IRemarks' => ''
                        , 'FromAdjust' => 0
                        , 'TaxId' => 0
                        , 'RefNo' => ''
                        , 'CompanyId' => $companyId
                        , 'Approve' => 'Y'));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    for($i=1;$i<=$adviceRowId;$i++) {
                        if($this->bsf->isNullCheck($postData['OTick_' . $i], 'string')=="")
                            continue;

                        $payAdviceRegId = $this->bsf->isNullCheck($postData['OPayAdviceId_' . $i], 'string');
                        //$sLTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                        //$sLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                        //$costcentreId = $this->bsf->isNullCheck($postData['curCostCentreId_' . $i], 'number');
                        $dcurAmount= $this->bsf->isNullCheck($postData['OAmount_' . $i], 'number');
                        //$AccountId= $this->bsf->isNullCheck($postData['curAmount_' . $i], 'number');

                        if ($payAdviceRegId == 0 || $dcurAmount == 0)
                            continue;

                        /*
                         * 	INSERT INTO dbo.EntryTrans(RefId,VoucherDate,VoucherNo,TransType,RefType,AccountId,RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,
                         * RelatedSLId,CostCentreId,Amount,Remarks,IRemarks,FromAdjust, TaxId,RefNo,CompanyId,Approve)
	                    VALUES(@RefId,@VoucherDate,@VoucherNo, @TransType,@RefType,@AccountId,@RelatedAccountId,@SubLedgerTypeId,@SubLedgerId, @RelatedSLTypeId,@RelatedSLId,
                        @CostCentreId,@Amount,@Narration, @Remarks, @FromAdjust, @TaxId,@RefVNo,@CompanyId,@Approve)
                         */
                       /* $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iEntryId
                        , 'VoucherNo' => $voucherNo
                        , 'VoucherDate' => $voucherDate
                        , 'TransType' => 'D'
                        , 'RefType' => 'O'
                        , 'AccountId' => $AccountId
                        , 'RelatedAccountId' => $BookId
                        , 'SubLedgerTypeId' => $sLTypeId
                        , 'SubLedgerId' => $sLedgerId
                        , 'RelatedSLTypeId' => 0
                        , 'RelatedSLId' => 0
                        , 'CostCentreId' => $costcentreId
                        , 'Amount' => $dcurAmount
                        , 'Remarks' => ''
                        , 'IRemarks' => ''
                        , 'FromAdjust' => 0
                        , 'TaxId' => 0
                        , 'RefNo' => ''
                        , 'CompanyId' => $companyId
                        , 'Approve' => 'Y'));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('FA_EntryTrans');
                        $insert->Values(array('RefId' => $iEntryId
                        , 'VoucherNo' => $voucherNo
                        , 'VoucherDate' => $voucherDate
                        , 'TransType' => 'C'
                        , 'RefType' => 'O'
                        , 'AccountId' => $BookId
                        , 'RelatedAccountId' => $AccountId
                        , 'SubLedgerTypeId' => 0
                        , 'SubLedgerId' => 0
                        , 'RelatedSLTypeId' => $sLTypeId
                        , 'RelatedSLId' => $sLedgerId
                        , 'CostCentreId' => $costcentreId
                        , 'Amount' => $dcurAmount
                        , 'Remarks' => ''
                        , 'IRemarks' => ''
                        , 'FromAdjust' => 0
                        , 'TaxId' => 0
                        , 'RefNo' => ''
                        , 'CompanyId' => $companyId
                        , 'Approve' => 'Y'));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                       */

                        $billRegRowId=$this->bsf->isNullCheck($postData['advice_'. $i.'_rowid'], 'number');
                        for($j=1;$j<=$billRegRowId;$j++) {
                            $payBillRegId = $this->bsf->isNullCheck($postData['advice_'. $i.'_billregisterid_' . $j], 'number');
                            $payBillbaseAmt = $this->bsf->isNullCheck($postData['advice_'. $i.'_bill_' . $j. '_approveAmt'], 'number');
                            $payBillQualAmt = $this->bsf->isNullCheck($postData['advice_'. $i.'_bill_' . $j. '_qualifierAmt'], 'number');
                            $payBillNetAmt = $this->bsf->isNullCheck($postData['advice_'. $i.'_bill_' . $j. '_netAmt'], 'number');
                            $payBillAccountId = $this->bsf->isNullCheck($postData['advice_'. $i.'_accountid_' . $j], 'number');
                            $sLTypeId = $this->bsf->isNullCheck($postData['advice_'. $i.'_subledgertypeid_' . $j], 'number');
                            $sLedgerId = $this->bsf->isNullCheck($postData['advice_'. $i.'_subledgerid_' . $j], 'number');
                            $costcentreId = $this->bsf->isNullCheck($postData['advice_'. $i.'_costcentreid_' . $j], 'number');
                            $payCurrencyId= $this->bsf->isNullCheck($postData['advice_'. $i.'_CurrencyId_' . $j], 'number');
                            $payForexRate= $this->bsf->isNullCheck($postData['advice_'. $i.'_ForexRate_' . $j], 'number');

                            if($payForexRate>0){
                                $dFRate=$payForexRate;
                            }

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iEntryId
                            , 'VoucherNo' => $voucherNo
                            , 'VoucherDate' => $voucherDate
                            , 'TransType' => 'D'
                            , 'RefType' => 'O'
                            , 'AccountId' => $payBillAccountId
                            , 'RelatedAccountId' => $BookId
                            , 'SubLedgerTypeId' => $sLTypeId
                            , 'SubLedgerId' => $sLedgerId
                            , 'RelatedSLTypeId' => 0
                            , 'RelatedSLId' => 0
                            , 'CostCentreId' => $costcentreId
                            , 'Amount' => $payBillbaseAmt
                            , 'Remarks' => ''
                            , 'IRemarks' => ''
                            , 'FromAdjust' => 0
                            , 'TaxId' => 0
                            , 'RefNo' => ''
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('RefId' => $iEntryId
                            , 'VoucherNo' => $voucherNo
                            , 'VoucherDate' => $voucherDate
                            , 'TransType' => 'C'
                            , 'RefType' => 'O'
                            , 'AccountId' => $BookId
                            , 'RelatedAccountId' => $payBillAccountId
                            , 'SubLedgerTypeId' => 0
                            , 'SubLedgerId' => 0
                            , 'RelatedSLTypeId' => $sLTypeId
                            , 'RelatedSLId' => $sLedgerId
                            , 'CostCentreId' => $costcentreId
                            , 'Amount' => $payBillbaseAmt
                            , 'Remarks' => ''
                            , 'IRemarks' => ''
                            , 'FromAdjust' => 0
                            , 'TaxId' => 0
                            , 'RefNo' => ''
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            /*
                             * sSql = String.Format("INSERT INTO [{0}].dbo.Adjustment (EntryType,EntryId,BillRegisterId,RefDate, RefNo,Amount,CompanyId,FYearId,ChqNo
                             * ,ChqDate,AdviceId,ChqTransId,BookId) " +
                                     "VALUES ('{1}',{2},{3},'{4:dd/MMM/yyyy}','{5}',{6},{7},{8},'{9}','{10}',{11},{12},{13}) ", BsfGlobal.g_sFaDBName,
                            objAdj.EntryType, iEntryId, objAdj.BillRegId, argEntry.VoucherDate, argEntry.VoucherNo, objAdj.PaidAmt, BsfGlobal.g_lCompanyId, BsfGlobal.g_lYearId, argEntry.ChequeNo, argEntry.ChequeDate.ToString("dd/MMM/yyyy"), objAdj.AdviceId, argEntry.ChequeTransId, argEntry.BookId);
                             */
                            $insert = $sql->insert();
                            $insert->into('FA_Adjustment');
                            $insert->Values(array('EntryType' => $BankOrCash
                            , 'EntryId' => $iEntryId
                            , 'BillRegisterId' => $payBillRegId
                            , 'RefDate' => $voucherDate
                            , 'RefNo' => $voucherNo
                            , 'Amount' => $payBillbaseAmt
                            , 'QualAmount' => $payBillQualAmt
                            , 'NetAmount' => $payBillNetAmt
                            , 'CompanyId' => $companyId
                            , 'FYearId' => $fYearId
                            , 'ChqNo' => $ChequeNo
                            , 'ChqDate' => $ChequeDate
                            , 'AdviceId' => $payAdviceRegId
                            , 'ChqTransId' => $ChequeTransId
                            , 'BookId' => $BookId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($payCurrencyId != 0){
                                //UPDATE [{0}].dbo.ForexBillRegister SET PaidAmount= PaidAmount+{1}
                                //WHERE BillRegisterId ={2}", BsfGlobal.g_sFaDBName, objAdj.ForexAmt, objAdj.BillRegId);
                                $update = $sql->update();
                                $update->table('FA_ForexBillRegister')
                                    ->set(array('PaidAmount' => new Expression("PaidAmount + " . $payBillbaseAmt) ))
                                    ->where(array('BillRegisterId' => $payBillRegId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            #region Advance (PO/DC,SO,SD,HO,WO,WA)
                            /*
                             * sSql = String.Format("SELECT BillRegisterId,ReferenceId,RefType,ApproveAmount,BillType FROM [{0}].dbo.BillRegister " +
                                             "WHERE RefType IN ('PO','PT','WO','WA','DC','HO','SO','SA') AND BillRegisterId={1}", BsfGlobal.g_sFaDBName, objAdj.BillRegId);

                             */
                            $select = $sql->select();
                            $select->from(array("A" => "FA_BillRegister"))
                                ->columns(array('BillRegisterId','ReferenceId','RefType','ApproveAmount','BillType'));
                            $select->where("RefType IN ('PO','PT','WO','WA','DC','HO','SO','SA') AND BillRegisterId=$payBillRegId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $billRegDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if(count($billRegDetList) > 0) {
                                if($billRegDetList[0]['RefType']=="PO"){
                                    //SELECT TermsId, Title,TermType FROM [{0}].dbo.TermsMaster
                                    //WHERE Title IN ('Advance','Against Test Certificate','Against Delivery') and Title ='Advance' AND TermType='S'
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Advance' AND TermType='S'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iPORegId = $billRegDetList[0]['ReferenceId'];
                                        /*
                                         * UPDATE [{0}].dbo.POPaymentTerms SET PaidAmount=PaidAmount+{1} WHERE PORegisterId={2} AND TermsId={3}",
                                             BsfGlobal.g_sMMSDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"], iTermsId
                                         */
                                        $update = $sql->update();
                                        $update->table('MMS_POPaymentTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + " . $payBillbaseAmt) ))
                                            ->where("PORegisterId=$iPORegId AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="PT") {
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Against Test Certificate' AND TermType='S'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iPORegId = $billRegDetList[0]['ReferenceId'];
                                        /*
                                         * UPDATE [{0}].dbo.POPaymentTerms SET PaidAmount=PaidAmount+{1} WHERE PORegisterId={2} AND TermsId={3}",
                                             BsfGlobal.g_sMMSDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"], iTermsId
                                         */
                                        $update = $sql->update();
                                        $update->table('MMS_POPaymentTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + ". $payBillbaseAmt) ))
                                            ->where("PORegisterId=$iPORegId AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="DC") {
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Against Delivery' AND TermType='S'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iPORegId = $billRegDetList[0]['ReferenceId'];
                                        $ibill_RegId = $billRegDetList[0]['BillRegisterId'];
                                        /*
                                         * UPDATE [{0}].dbo.POPaymentTerms SET PaidAmount=PaidAmount+{1} WHERE TermsId={2} AND PORegisterId =" +
                                         "(SELECT TOP 1 PORegisterId FROM [{0}].dbo.DCAdvance WHERE DCRegisterId={3} AND RefId={4})
                                         */
                                        $update = $sql->update();
                                        $update->table('MMS_POPaymentTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + ".$payBillbaseAmt) ))
                                            ->where("PORegisterId in (SELECT TOP 1 PORegisterId FROM MMS_DCAdvance WHERE DCRegisterId=$iPORegId AND RefId=$ibill_RegId) AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="WO") {
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Advance' AND TermType='W'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iWORegId = $billRegDetList[0]['ReferenceId'];
                                        /*
                                         * UPDATE [{0}].dbo.WOPaymentTermsNew SET PaidAmount=PaidAmount+{1}
                                         * WHERE WORegisterId={2} AND TermsId={3}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"], iTermsId
                                         */
                                        $update = $sql->update();
                                        $update->table('WPM_WOGeneralTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + ". $payBillbaseAmt) ))
                                            ->where("WORegisterId=$iWORegId AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        /*
                                         * UPDATE [{0}].dbo.WORegister SET AdvanceAmt=AdvanceAmt+{1}
                                         * WHERE WORegisterId={2}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"]);
                                         */
                                        $update = $sql->update();
                                        $update->table('WPM_WORegister')
                                            ->set(array('AdvanceAmt' => new Expression("AdvanceAmt + " . $payBillbaseAmt) ))
                                            ->where("WORegisterId=$iWORegId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="HO") {
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Advance' AND TermType='W'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iHORegId = $billRegDetList[0]['ReferenceId'];
                                        /*
                                         * UPDATE [{0}].dbo.HOPaymentTermsNew SET PaidAmount=PaidAmount+{1}
                                         * WHERE HORegisterId={2} AND TermsId={3}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"], iTermsId*/
                                        $update = $sql->update();
                                        $update->table('WPM_HOGeneralTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + ".$payBillbaseAmt) ))
                                            ->where("HORegisterId=$iHORegId AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        /*
                                         * UPDATE [{0}].dbo.HORegister SET AdvanceAmt=AdvanceAmt+{1}
                                         * WHERE HORegisterId={2}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"]*/
                                        $update = $sql->update();
                                        $update->table('WPM_HORegister')
                                            ->set(array('AdvanceAmt' => new Expression("AdvanceAmt + " . $payBillbaseAmt) ))
                                            ->where("HORegisterId=$iHORegId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="SO") {
                                    $select = $sql->select();
                                    $select->from(array("A" => "WF_TermsMaster"))
                                        ->columns(array('TermsId','Title','TermType'));
                                    $select->where("Title ='Advance' AND TermType='W'");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $termsDetList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($termsDetList) > 0) {
                                        $iTermsId = $termsDetList[0]['TermsId'];
                                        $iSORegId = $billRegDetList[0]['ReferenceId'];
                                        /*
                                         * UPDATE [{0}].dbo.SOPaymentTermsNew SET PaidAmount=PaidAmount+{1}
                                         * WHERE SORegisterId={2} AND TermsId={3}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"], iTermsId*/
                                        $update = $sql->update();
                                        $update->table('WPM_GeneralTerms')
                                            ->set(array('PaidAmount' => new Expression("PaidAmount + " . $payBillbaseAmt) ))
                                            ->where("SORegisterId=$iSORegId AND TermsId=$iTermsId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        /*
                                         * UPDATE [{0}].dbo.SORegister SET AdvanceAmt=AdvanceAmt+{1}
                                         * WHERE SORegisterId={2}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"]*/
                                        $update = $sql->update();
                                        $update->table('WPM_SORegister')
                                            ->set(array('AdvanceAmt' => new Expression("AdvanceAmt + " . $payBillbaseAmt) ))
                                            ->where("SORegisterId=$iSORegId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else if($billRegDetList[0]['RefType']=="WA") {
                                    $iWARegId = $billRegDetList[0]['ReferenceId'];
                                    /*
                                     * sSql = String.Format("UPDATE [{0}].dbo.BillRegister SET AdvAmount=AdvAmount+{1}
                                     * WHERE BillRegisterId={2}", BsfGlobal.g_sWPMDBName, objAdj.PaidAmt, dt.Rows[0]["ReferenceId"]);

                                     */
                                    $update = $sql->update();
                                    $update->table('WPM_WorkBillRegister')
                                        ->set(array('AdvanceAmt' => new Expression("AdvanceAmt + " . $payBillbaseAmt) ))
                                        ->where("BillRegisterId=$iWARegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else if($billRegDetList[0]['RefType']=="SA") {
                                    $iSARegId = $billRegDetList[0]['ReferenceId'];
                                    $d_Amount = $billRegDetList[0]['ApproveAmount'];
                                    /*
                                     * UPDATE [{0}].dbo.SDRegister SET AdvancePaid=AdvancePaid+{1}
                                     * WHERE SDRegisterId={2}", BsfGlobal.g_sWPMDBName, dt.Rows[0]["ApproveAmount"], dt.Rows[0]["ReferenceId"]
                                     */
                                    $update = $sql->update();
                                    $update->table('WPM_SDRegister')
                                        ->set(array('AdvancePaid' => new Expression("AdvancePaid + " . $d_Amount) ))
                                        ->where("SDRegisterId=$iSARegId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            //new Expression("PayAdvAmount + ".$currentAmt)
                            $update = $sql->update();
                            $update->table('FA_BillRegister')
                                ->set(array('PaidAmount' => new Expression("PaidAmount + " .$payBillbaseAmt) ))
                                ->where("BillRegisterId=$payBillRegId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $update = $sql->update();
                            $update->table('FA_PayAdviceDet')
                                ->set(array('PayForexRate' => $payForexRate ))
                                ->where("PayAdviceId=$payAdviceRegId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            /*
                             * sSql = String.Format("UPDATE [{0}].dbo.BillRegister SET PaidAmount= PaidAmount+{1}
                             * WHERE BillRegisterId ={2}", BsfGlobal.g_sFaDBName, objAdj.PaidAmt, objAdj.BillRegId);


                        sSql = String.Format("UPDATE [{0}].dbo.PayAdviceDet SET PayForexRate={1}
                            WHERE PayAdviceId = {2}", BsfGlobal.g_sFaDBName, objAdj.ForexRate, objAdj.AdviceId);

                             */

                            #endregion

                            //EntryMaster Account Brakeup
                            $select = $sql->select();
                            $select->from(array("A" => "FA_EntryMasterBreakup"))
                                ->columns(array('EntryAccTransId'));
                            $select->where("A.EntryId = $iEntryId and a.AccountId=$payBillAccountId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $EntryAccbreakupList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if(count($EntryAccbreakupList) > 0) {
                                $iEntryAccTransId = $EntryAccbreakupList[0]['EntryAccTransId'];
                                $update = $sql->update();
                                $update->table("FA_EntryMasterBreakup")
                                    ->set(array('Amount' => new Expression ("Amount + " . $payBillbaseAmt)));
                                $update->where("EntryId=$iEntryId AND AccountId=$payBillAccountId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                $insert = $sql->insert();
                                $insert->into('FA_EntryMasterBreakup');
                                $insert->Values(array('EntryId' => $iEntryId
                                , 'AccountId' => $payBillAccountId
                                , 'Amount' => $payBillbaseAmt));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $update = $sql->update();
                            $update->table("FA_PayAdviceTrans")
                                ->set(array('EntryId' => $iEntryId, 'FYearId' => $fYearId));
                            $update->where("PayAdviceId=$payAdviceRegId and BillRegisterId=$payBillRegId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //qUALIFIER
                            $billRegQualRowId=$this->bsf->isNullCheck($postData['QualifierAccTable_advice_' . $i . '_bill_' . $j. '_rowid'], 'number');
                            for($k=1;$k<=$billRegQualRowId;$k++) {
                                $iYesNo=0;
                                if($this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_YesNo_'. $k], 'string')!=""){
                                    $iYesNo=1;
                                }

                                if ($iYesNo == 0)
                                    continue;

                                $iQualifierId = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_Id_'. $k], 'number');
                                $sExpression = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_Exp_'. $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_Amount_'. $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_ExpPer_'. $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_QualTypeId_'. $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_sign_'. $k], 'string');
                                $iQualSLId = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_subledgerid_'. $k], 'number');
                                $iQualAccId = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_accountid_'. $k], 'number');

                                $dCessPer = 0;
                                $dEDPer = 0;
                                $dHEdPer = 0;
                                $dKKCess = 0;
                                $dSBCess = 0;
                                $dCessAmt = 0;
                                $dEDAmt = 0;
                                $dHEdAmt = 0;
                                $dKKCessAmt = 0;
                                $dSBCessAmt = 0;

                                if ($iQualTypeId == 1) {
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j .'_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['accTag_advice_' . $i . '_bill_' . $j . '_Amount_' . $k], 'number');
                                }

                                /*
                                * sSql = String.Format("INSERT INTO TaxEntryTrans(ReferenceId,TaxId,TaxRate,TaxAmount,SurRate,SurAmount,EduRate,EduAmount,HEduRate, " +
                                 "HEduAmount,TaxNetRate,TaxNetAmount,TaxAccountId,QualTypeId,QualifierId,Expression,Add_Less_Flag
                                ,TaxablePer,TaxablePerRate,ExpAmount,Description,TaxSubLedgerId)  " +
                                 "VALUES ({0},{1},{2},{3},{4},{5},{6},{7},{8},{9},{10},{11},{12},{13},{14},'{15}','{16}',{17},{18},{19},'{20}',{21})",
                                 iEntryId, obj.TaxId, obj.TaxRate, obj.TaxAmount, obj.SurRate, obj.SurAmount, obj.EduRate, obj.EduAmount,
                                 obj.HEduRate, obj.HEduAmount, obj.TaxNetRate, obj.TaxNetAmount, obj.TaxAccountId,
                                 obj.TaxId, obj.QualifierId, obj.Expression, obj.AddLessFlag, obj.TaxablePer, obj.TaxablePerRate, obj.ExpAmount, obj.Description, iQSLId);

 */
                                $insert = $sql->insert();
                                $insert->into('FA_EntryTaxTrans');
                                $insert->Values(array('ReferenceId' => $iEntryId,
                                    'PayAdvId' => $payAdviceRegId,
                                    'BillRegisterId' => $payBillRegId,
                                    'CostCentreId' => $costcentreId,'RefSLId' => $sLedgerId,'JVType' => 'O',
                                    'QualTypeId' => $iQualTypeId,
                                    'QualifierId' => $iQualifierId,
                                    'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer,
                                    'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt,
                                    'TaxableAmt' => $dTaxableAmt,'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt,
                                    'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt,
                                    'TaxAccountId' => $iQualAccId,
                                    'TaxSubLedgerId' => $iQualSLId,
                                    'CompanyId' => $companyId,
                                    'FYearId' => $fYearId,
                                    'Expression' => $sExpression,
                                    'Sign' => $sSign,
                                    'YesNo' => $iYesNo
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //Qualifier AccPosting
                                if($dNetAmt!=0 && $iYesNo==1) {
                                    $Trans_Type="C";
                                    if($sSign=="+"){
                                        $Trans_Type="D";
                                    }
                                    $iQualSLTypeId=0;
                                    if($iQualTypeId == 1 || $iQualTypeId == 2 || $iQualTypeId == 3 || $iQualTypeId == 4 || $iQualTypeId == 10 || $iQualTypeId == 12){
                                        $iQualSLTypeId=8;
                                    } else if($iQualTypeId == 18){
                                        $iQualSLTypeId=0;
                                    } else {
                                        $iQualSLTypeId=9;
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $iEntryId
                                    , 'VoucherNo' => $voucherNo
                                    , 'VoucherDate' => $voucherDate
                                    , 'TransType' => $Trans_Type
                                    , 'RefType' => 'O'
                                    , 'AccountId' => $iQualAccId
                                    , 'RelatedAccountId' => $payBillAccountId
                                    , 'SubLedgerTypeId' => $iQualSLTypeId
                                    , 'SubLedgerId' => $iQualSLId
                                    , 'RelatedSLTypeId' => $sLTypeId
                                    , 'RelatedSLId' => $sLedgerId
                                    , 'CostCentreId' => $costcentreId
                                    , 'Amount' => $dNetAmt
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'FromAdjust' => 0
                                    , 'TaxId' => $iQualTypeId
                                    , 'RefNo' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    if($Trans_Type=="D"){
                                        $Trans_Type="C";
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('RefId' => $iEntryId
                                    , 'VoucherNo' => $voucherNo
                                    , 'VoucherDate' => $voucherDate
                                    , 'TransType' => $Trans_Type
                                    , 'RefType' => 'O'
                                    , 'AccountId' => $payBillAccountId
                                    , 'RelatedAccountId' => $iQualAccId
                                    , 'SubLedgerTypeId' => $sLTypeId
                                    , 'SubLedgerId' => $sLedgerId
                                    , 'RelatedSLTypeId' => $iQualSLTypeId
                                    , 'RelatedSLId' => $iQualSLId
                                    , 'CostCentreId' => $costcentreId
                                    , 'Amount' => $dNetAmt
                                    , 'Remarks' => ''
                                    , 'IRemarks' => ''
                                    , 'FromAdjust' => 0
                                    , 'TaxId' => $iQualTypeId
                                    , 'RefNo' => ''
                                    , 'CompanyId' => $companyId
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                        }
                    }

                    //UPDATE [{0}].dbo.ChequeTrans SET Amount ={1}, Used=1,IssueDate='{2:dd/MMM/yyyy}',FYearId={3} WHERE ChequeTransId={3}
                    if($ChequeTransId!=0){
                        $update = $sql->update();
                        $update->table("FA_ChequeTrans")
                            ->set(array('Amount' => $OtherCharges, 'Used' => 1, 'IssueDate' => $ChequeDate, 'FYearId' => $fYearId));
                        $update->where("ChequeTransId=$ChequeTransId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Payment-Journal-Edit','E','FA-Payment Journal Details',$iEntryId,0, $companyId, 'FA','',$userId, 0 ,0);//pass refno after FA as voucher $adviceNo
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Payment-Journal-Add','N','FA-Payment Journal Details',$iEntryId,0, $companyId, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "journalbook"));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerType"))
                ->columns(array("SubLedgerTypeId","SubLedgerTypeName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $subLedgerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->subLedgerList = $subLedgerList;

            $select = $sql->select();
            $select->from(array("a" => "WF_Costcentre"))
                ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName")));
//                ->where("CompanyId=".$CompanyId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $costCenterdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->costCenterdata = $costCenterdata;

            /*Default Bank is Selected in Load*/
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
            $select->where("AccountType IN ('BA') and a.CompanyId=$companyId and a.FYearId=$fYearId");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->bookNameDet= $bookNameDet;

            $select = $sql->select();//Account Head details
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountId=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('data'=>new Expression('a.AccountId'),'AccountType'=>new Expression("b.AccountType"),'AccountId'
                ,"value"=>new Expression("b.AccountName"),"AccountName"=>new Expression("b.AccountName"),'TypeId'=>new Expression("b.TypeId")));
            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId and b.TypeId IN (1,2,4,8,9,17,36,40,51,52,53,56)");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $accountHeadDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->accountHeadDet = $accountHeadDet;

            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array('AccountId','AccountName'))
                ->where("TypeId=7 and LastLevel='Y'")
                ->order(array("AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $expenseAccount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->expenseAccount = $expenseAccount;

            //QualifierAccount
            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array('AccountId','AccountName','TypeId'))
                ->where("LastLevel='Y' AND  TypeId IN (7,12,13,14,15,22,30,31,32,33,54,55,57,58)")
                ->order(array(new Expression("AccountName,AccountId")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualAccountList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->qualAccountList = $qualAccountList;

            //QualifierSubLedgerNameList
            $select = $sql->select();
            $select->from(array("a" => "FA_SubLedgerMaster"))
                ->columns(array('SubLedgerId','SubLedgerName','RefId','SubLedgerTypeId','SLRatio','ServiceTypeId','StateId'))
                ->where("RefId<>0 AND SubLedgerTypeId IN (8,9,1)")//IsActive=1 AND
                ->order(array("SubLedgerName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualSubLegderList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->qualSubLegderList = $qualSubLegderList;

            /*
             *

            if (argTransType == "R")
                {
                    if (argEntryId != 0)
                    {

        SELECT G.BillRegisterId,G.BillDate,G.BillNo,G.CostCentreId,G.TypeName,G.BillAmount, G.ApproveAmount Amount,G.PaidAmount Paid,
        G.ApproveAmount-G.PaidAmount Balance,CAST(SUM(G.CurrentAmount) AS Decimal(18,3)) [Current],CAST(SUM(G.CurrentAmount) AS Decimal(18,3)) OldCurrent, C.CostCentreName FROM (
        SELECT A.BillRegisterId,A.BillDate,A.BillNo,A.CostCentreId,TypeName=A.RefType,A.BillAmount,A.ApproveAmount,A.PaidAmount,
        CAST (0 AS Decimal(18,3)) CurrentAmount FROM FA_BillRegister A
        WHERE A.Approve='Y' AND A.BillAmount-A.PaidAmount>0 AND A.AccountId=46
        AND A.TransType='P' AND A.CompanyId=1 AND A.BillDate <= '28-May-2017' and A.RefType NOT IN ('CB','PB')
        UNION ALL
        SELECT A.BillRegisterId,A.BillDate,A.BillNo,A.CostCentreId,TypeName=A.RefType,A.BillAmount,A.ApproveAmount,A.PaidAmount,
        CAST (ISNULL(Amount,0) AS Decimal(18,3)) CurrentAmount FROM FA_BillRegister A
        LEFT JOIN FA_Adjustment B on A.BillRegisterId=B.BillRegisterId
        WHERE A.Approve='Y' AND B.EntryId= 1 AND B.CompanyId=1 AND A.BillDate<='28-May-2017' and A.RefType NOT IN ('CB','PB') ) G
        INNER JOIN WF_CostCentre C ON C.CostCentreId=G.CostCentreId
        GROUP BY G.BillRegisterId,G.BillDate,G.BillNo,G.CostCentreId,G.BillAmount,G.ApproveAmount,G.TypeName,G.PaidAmount, C.CostCentreName
            } else {
            SELECT SLM.SubLedgerId,SLM.SubLedgerName,A.BillRegisterId,A.BillDate,A.BillNo,A.CostCentreId,TypeName=A.RefType,A.BillAmount, A.ApproveAmount Amount,
        A.PaidAmount Paid,A.ApproveAmount-A.PaidAmount Balance,CAST (0 AS Decimal(18,3)) [Current],CAST (0 AS Decimal(18,3)) OldCurrent, C.CostCentreName,CAST(0 AS Bit) Sel
        FROM FA_BillRegister A INNER JOIN FA_SubLedgerMaster SLM ON SLM.SubLedgerId=A.SubLedgerId
        INNER JOIN WF_CostCentre C ON C.CostCentreId=A.CostCentreId
		WHERE A.Approve='Y' AND A.BillAmount-A.PaidAmount<>0 AND A.AccountId = 46 AND A.TransType = 'P'
        AND A.BillDate<='28-May-2017' and A.RefType NOT IN ('CB','PB')
	    ORDER BY SLM.SubLedgerName, A.BillDate

                   $select = $sql->select();
            $select->from(array("a" => "FA_BillRegister"))
                ->join(array("b" => "FA_SubLedgerMaster"), "b.SubLedgerId=a.SubLedgerId", array(), $select::JOIN_INNER)
                ->join(array("c" => "WF_CostCentre"), "c.CostCentreId=a.CostCentreId", array(), $select::JOIN_INNER)
                ->columns(array('SubLedgerId'=> new Expression("b.SubLedgerId"),'SubLedgerName'=> new Expression("b.SubLedgerName"),'BillRegisterId','BillDate','BillNo','CostCentreId'
                ,'TypeName'=>new Expression("a.RefType"),'BillAmount', 'Amount'=> new Expression("a.ApproveAmount"),
                'Paid'=> new Expression("a.PaidAmount"),'Balance'=> new Expression("A.ApproveAmount-A.PaidAmount")
                ,'Current'=> new Expression("CAST (0 AS Decimal(18,3))"),'OldCurrent'=> new Expression("CAST (0 AS Decimal(18,3))")
                , 'CostCentreName'=> new Expression("c.CostCentreName"),'Sel'=> new Expression("CAST(0 AS Bit)")));
            $select->where("a.Approve='Y' AND a.BillAmount-a.PaidAmount<>0 AND a.AccountId = 46 AND a.TransType = 'P'
            AND a.BillDate<='28-May-2017' and a.RefType NOT IN ('CB','PB')");
            $select->order(array("b.SubLedgerName","a.BillDate"));
            $statement = $sql->getSqlStringForSqlObject($select);

            }
}else{

SELECT SLM.SubLedgerId,SLM.SubLedgerName,PayAdviceId, PayAdviceDate,PayAdviceNo,TotalAmount, A.CurrencyId,
CurrencyName=(CASE WHEN A.CurrencyId=0 THEN (SELECT CurrencyName FROM  WF_CurrencyMaster WHERE CurrencyId=0)  --$BsfGlobal.g_iFYCurrencyId
ELSE (SELECT CurrencyName FROM  WF_CurrencyMaster WHERE CurrencyId=A.CurrencyId) END),
CAST( 0 AS decimal(18,6)) ForexRate, CASE WHEN A.CurrencyId<>0 THEN CAST( 0 AS decimal(18,3)) ELSE TotalAmount END NetAmount,
CAST( 0 AS Bit) Sel  FROM FA_PayAdviceDet A
INNER JOIN FA_SubLedgerMaster SLM ON SLM.SubLedgerId=A.SLedgerId
WHERE A.CompanyId=1 AND A.Approve='Y' AND PayAdviceId IN (
SELECT PayAdviceId FROM FA_PayAdviceTrans WHERE CompanyId=1 AND EntryId=0 AND BillRegisterId IN (
SELECT BillRegisterId FROM FA_BillRegister WHERE CompanyId=1
 AND BillDate<='28-may-2017' AND AccountId=46 ))
AND PayAdviceId NOT IN (SELECT PayAdviceId FROM FA_PayAdviceTrans
WHERE CompanyId=1 AND EntryId=0 AND BillRegisterId IN (
SELECT BillRegisterId FROM FA_BillRegister WHERE CompanyId=1
AND BillDate<='28-may-2017' AND AccountId=46 ))

            if (argEntryId != 0) {
            UNION ALL SELECT PayAdviceId, PayAdviceDate,PayAdviceNo,TotalAmount,CurrencyId,CurrencyName=(CASE WHEN A.CurrencyId=0 THEN
    (SELECT CurrencyName FROM  WF_CurrencyMaster WHERE CurrencyId= 1) ELSE
    (SELECT CurrencyName FROM  WF_CurrencyMaster WHERE CurrencyId=A.CurrencyId) END),CAST( A.PayForexRate AS decimal(18,6)) ForexRate,
    CASE WHEN CurrencyId<>0 THEN CAST( (A.TotalAmount*A.PayForexRate) AS decimal(18,3)) ELSE TotalAmount END NetAmount, CAST( 1 AS Bit) Sel  FROM FA_PayAdviceDet A
            left join  FA_SubLedgerMaster b on b.SubLedgerId=a.SubLedgerId
    WHERE SLedgerId=1 AND CompanyId=1 AND Approve='Y' AND PayAdviceId IN (
    SELECT PayAdviceId FROM FA_PayAdviceTrans WHERE CompanyId=1 AND EntryId=2)

            }
            }
             */

            $select = $sql->select();
            $select->from(array("a" => "Proj_QualifierTrans"))
                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer',
                    'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                    'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                    'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'KKCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SBCessAmt'=> new Expression("CAST(0 As Decimal(18,2))")
                ,'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAccountId'=>new Expression("1-1"),'TaxSubLedgerId'=>new Expression("1-1")));
            $select->where(array('a.QualType' => 'W'));
            $select->order('a.SortId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sHtml=Qualifier::getFAQualifier($qualList);
            $this->_view->qualHtml = $sHtml;
            $this->_view->qualList = $qualList;

            $getHOAccId=0;
            $select = $sql->select();
            $select->from(array("a" => "WF_CostCentre"))
               ->columns(array('CostCentreId'));
            $select->where("HO=1");
            $statement = $sql->getSqlStringForSqlObject($select);
            $getHOAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($getHOAccList) > 0) {
                $getHOAccId = $getHOAccList[0]['CostCentreId'];
            }

            //edit
            $allowEdit=1;
            $select = $sql->select();
            $select->from(array("a" => "FA_EntryMaster"))
                ->join(array("b" => "FA_AccountMaster"), "a.BookId=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('VoucherDate'=>new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"),'VoucherNo','BankCashVoucherNo','BookVoucherNo','EntryType','JournalType','BookID',
                    'AccountID','SubLedgerTypeId','PayType','ChequeNo','ChequeDate'=>new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','ChequeTransId',
                    'Narration','Amount','FromCostCentreID','CostCentreId','CompanyId','IsLock','BranchId','RefNo','RelatedRefNo',
                    'RelatedVNo','OtherCharges','OtherAccountId','BookSLId','PDC','AccountType'=>new Expression("b.AccountType"),'Approve' ));
            $select->where(array('a.EntryID' => $EntryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $regOPJouralList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if($regOPJouralList){
                if($regOPJouralList['Approve']=="Y"){
                    $allowEdit=0;
                }
            }
            $this->_view->regOPJouralList = $regOPJouralList;

            if($EntryId != 0) {
                $BookID = $regOPJouralList['BookID'];
                $ChequeTransId = $regOPJouralList['ChequeTransId'];
                $select = $sql->select();
                $select->from(array("a" => "FA_CashBankDet"))
                    ->columns(array('AccountType' => new Expression("CashOrBank")))
                    ->where("a.AccountId=$BookID");
                $statement = $sql->getSqlStringForSqlObject($select);
                $OJAccType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->OJAccType = $OJAccType;

                $OJChequeDet = array();

                if ($regOPJouralList['PayType'] != '3') {
                    $select = $sql->select();
                    $select->from(array("a" => "FA_ChequeTrans"))
                        ->columns(array('data' => new Expression('ChequeTransId'), 'value' => new Expression('ChequeNo')))
                        ->where("(a.AccountId=$BookID AND Used=0 AND Cancel=0) or a.ChequeTransId=$ChequeTransId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $OJChequeDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->OJChequeDet = $OJChequeDet;
            }
            /*
            SELECT EntryID,VoucherDate,VoucherNo,BankCashVoucherNo,BookVoucherNo,EntryType,JournalType,BookID,
	AccountID,SubLedgerTypeId,SubLedgerID,PayType,ChequeNo,ChequeDate,ChequeDescription,ChequeTransId,
	Narration,Amount,FromCostCentreID,CostCentreId,CompanyId,IsLock,BranchId,RefNo,RelatedRefNo,RelatedVNo,OtherCharges,OtherAccountId,BookSLId,PDC FROM FA_EntryMaster
	Where EntryID=73

	SELECT A.RefID,A.TransType,A.RefType,A.AccountId,B.AccountName,A.RelatedAccountId,
	A.SubLedgerTypeId,A.SubLedgerId,A.RelatedSLTypeId,A.RelatedSLId,A.CostCentreId,A.TaxId, A.Amount FROM
	FA_EntryTrans A
	INNER JOIN FA_AccountMaster B on A.AccountID=B.AccountId
	WHERE RefID=73 and RefType='O'
            */
            $AccountId="0,";
            $select = $sql->select();
            $select->from(array("a" => "FA_EntryMasterBreakup"))
                ->columns(array('AccountId'));
            $select->where(array('a.EntryId' => $EntryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $regOPJouralAccBreakupList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($regOPJouralAccBreakupList as &$regOPJouralAccBreakupLists) {
                $AccountId = $AccountId . $regOPJouralAccBreakupLists["AccountId"] . ",";
            }

            if($AccountId != ""){
                $AccountId = rtrim($AccountId,',');
            }

            $CurrencyId=0;
            $subQue1 = $sql->select();
            $subQue1->from(array("sq1" => "WF_CurrencyMaster"))
                ->columns(array("CurrencyName"))
                ->where("CurrencyId=$CurrencyId");

            $subQue4 = $sql->select();
            $subQue4->from(array("squ4" => "FA_BillRegister"))
                ->columns(array("BillRegisterId"))
                ->where("CompanyId=$companyId AND BillDate<='$endDate' AND AccountId IN ($AccountId)");

            $subQue2 = $sql->select();
            $subQue2->from(array("sq2" => "FA_PayAdviceTrans"))
                ->columns(array("PayAdviceId"))
                ->where("CompanyId=$companyId AND EntryId=0 AND BillRegisterId IN (".$subQue4->getSqlString().")");

            /*$subQue4a = $sql->select();
            $subQue4a->from(array("sq3" => "FA_PayAdviceTrans"))
                ->columns(array("PayAdviceId"))
                ->where("CompanyId=$companyId AND EntryId=0 AND BillRegisterId IN");*/

            $subQuery2 = $sql->select();
            $subQuery2->from(array("sq2" => "WF_CurrencyMaster"))
                ->columns(array("CurrencyName"))
                ->where("CurrencyId=a.CurrencyId");

            $selectPickList = $sql->select();
            $selectPickList ->from(array("a" => "FA_PayAdviceDet"))
                ->columns(array('PayAdviceId','PayAdviceDate'=> new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"),'PayAdviceNo','TotalAmount','CurrencyId'=>new Expression("A.CurrencyId"),'BillAmount','ReturnAmount'
                ,'CurrencyName'=>new Expression("Case when a.CurrencyId=0 then(".$subQue1->getSqlString().")"." else(".$subQuery2->getSqlString().") end")
                ,'ForexRate'=>new Expression("CAST( a.PayForexRate AS decimal(18,6))")
                ,'NetAmount'=>new Expression("CASE WHEN a.CurrencyId<>0 THEN CAST( 0 AS decimal(18,3)) ELSE TotalAmount END")
                ,'Sel'=>new Expression("CAST( 0 AS Bit)")));
            $selectPickList->where("CompanyId=$companyId AND Approve='Y' AND PayAdviceId IN (".$subQue2->getSqlString().") ");//AND PayAdviceId NOT IN (".$subQue2->getSqlString().")
            //Need to add above condition to query

            $subQuery1 = $sql->select();
            $subQuery1->from(array("sq1" => "WF_CurrencyMaster"))
                ->columns(array("CurrencyName"))
                ->where("CurrencyId= $CurrencyId");

            if($EntryId != 0) {
                $subQuery3 = $sql->select();
                $subQuery3->from(array("sq3" => "FA_PayAdviceTrans"))
                    ->columns(array("PayAdviceId"))
                    ->where("CompanyId=$companyId AND EntryId=$EntryId");

                $select = $sql->select();
                $select->from(array("a" => "FA_PayAdviceDet"))
                    ->columns(array('PayAdviceId', 'PayAdviceDate' => new Expression("FORMAT(a.PayAdviceDate, 'dd-MM-yyyy')"), 'PayAdviceNo', 'TotalAmount', 'CurrencyId', 'BillAmount', 'ReturnAmount'
                    , 'CurrencyName' => new Expression("Case when a.CurrencyId=0 then(" . $subQuery1->getSqlString() . ")" . " else(" . $subQuery2->getSqlString() . ") end")
                    , 'ForexRate' => new Expression("CAST( a.PayForexRate AS decimal(18,6))")
                    , 'NetAmount' => new Expression("CASE WHEN a.CurrencyId<>0 THEN CAST( (a.TotalAmount*a.PayForexRate) AS decimal(18,3)) ELSE TotalAmount END")
                    , 'Sel' => new Expression("CAST( 1 AS Bit)")));
                $select->where("CompanyId=$companyId AND Approve='Y' AND PayAdviceId IN (" . $subQuery3->getSqlString() . ")");
                $selectPickList->combine($select, 'Union ALL');
            }
            $selectFin = $sql->select();
            $selectFin->from(array("g" => $selectPickList))
                ->columns(array("*"));
            $selectFin->order("g.PayAdviceId");
            $statement = $sql->getSqlStringForSqlObject($selectFin);
            $editAdviceDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->editAdviceDet = $editAdviceDet;

            $subQuery = $sql->select();
            $subQuery->from(array("a" => "FA_PayAdviceDet"))
                ->columns(array("PayAdviceId"))
                ->where("CompanyId=$companyId and FYearId=$fYearId AND PayAdviceDate<='$endDate' AND Approve='Y'");

            $select = $sql->select();
            $select->from(array("A" => "FA_PayAdviceTrans"))
                ->join(array("B" => "FA_BillRegister"), "A.BillRegisterId=B.BillRegisterId", array(), $select::JOIN_INNER)
                ->join(array("SLM" => "FA_SubLedgerMaster"), "B.SubLedgerId=SLM.SubLedgerId", array(), $select::JOIN_INNER)
                ->join(array("CC" => "WF_CostCentre"), "B.CostCentreId=CC.CostCentreId", array(), $select::JOIN_INNER)
                ->columns(array('PayTransId','PayAdviceId','BillRegisterId','BillNo'=>new Expression("B.BillNo"),'ApproveAmount'
                ,'BillDate'=>new Expression("FORMAT(B.BillDate, 'dd-MM-yyyy')"),'SubLedgerId'=>new Expression("SLM.SubLedgerId")
                ,'SubLedgerName'=>new Expression("SLM.SubLedgerName"),'CostCentreId'=>new Expression("B.CostCentreId")
                ,'CostCentreName'=>new Expression("CC.CostCentreName"),'CCName'=>new Expression("CC.CostCentreName")
                ,'AccountId'=>new Expression("B.AccountId"),'Current'=>new Expression("A.ApproveAmount")
                ,'CurrencyId'=>new Expression("B.CurrencyId"),'Advance'=>new Expression("B.Advance")
                ,'ForexAmount'=>new Expression("A.ApproveAmount"),'ForexRate'=>new Expression("A.BillForexRate")
                ,'BranchId'=>new Expression("B.BranchId"),'SubLedgerTypeId'=>new Expression("SLM.SubLedgerTypeId")
                ));
            $select->where->expression("B.AccountId IN ($AccountId) AND A.EntryId=0 AND PayAdviceId IN ?",array($subQuery));

            $selectEdit = $sql->select();
            $selectEdit->from(array("A" => "FA_PayAdviceTrans"))
                ->join(array("B" => "FA_BillRegister"), "A.BillRegisterId=B.BillRegisterId", array(), $selectEdit::JOIN_INNER)
                ->join(array("SLM" => "FA_SubLedgerMaster"), "B.SubLedgerId=SLM.SubLedgerId", array(), $selectEdit::JOIN_INNER)
                ->join(array("CC" => "WF_CostCentre"), "B.CostCentreId=CC.CostCentreId", array(), $selectEdit::JOIN_INNER)
                ->columns(array('PayTransId','PayAdviceId','BillRegisterId','BillNo'=>new Expression("B.BillNo"),'ApproveAmount'
                ,'BillDate'=>new Expression("FORMAT(B.BillDate, 'dd-MM-yyyy')"),'SubLedgerId'=>new Expression("SLM.SubLedgerId")
                ,'SubLedgerName'=>new Expression("SLM.SubLedgerName"),'CostCentreId'=>new Expression("B.CostCentreId")
                ,'CostCentreName'=>new Expression("CC.CostCentreName"),'CCName'=>new Expression("CC.CostCentreName")
                ,'AccountId'=>new Expression("B.AccountId"),'Current'=>new Expression("A.ApproveAmount")
                ,'CurrencyId'=>new Expression("B.CurrencyId"),'Advance'=>new Expression("B.Advance")
                ,'ForexAmount'=>new Expression("A.ApproveAmount"),'ForexRate'=>new Expression("A.BillForexRate")
                ,'BranchId'=>new Expression("B.BranchId"),'SubLedgerTypeId'=>new Expression("SLM.SubLedgerTypeId")
                ));
            $selectEdit->where->expression("B.AccountId IN ($AccountId) AND A.EntryId=$EntryId AND EntryId<>0 AND PayAdviceId IN ?",array($subQuery));
            $selectEdit->combine($select, 'Union ALL');
            $statement = $sql->getSqlStringForSqlObject($selectEdit);
            $loadBillTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->editBillTrans = $loadBillTrans;

            $this->_view->getHOAccId = $getHOAccId;
            $this->_view->EntryId= $EntryId;
            $this->_view->allowEdit= $allowEdit;

            $aVNo = CommonHelper::getVoucherNo(608, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->aVNo= $aVNo;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function paymentjournalregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();
        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');
        $bookType= $this->bsf->isNullCheck($this->params()->fromRoute('bookType'),'string');
        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
                $type= $this->bsf->isNullCheck($postData['type'], 'string');
                switch($type) {
                    case 'loadBookName':
                        $paymentBookType = $this->bsf->isNullCheck($postData['paymentBookType'], 'string');
                        $select = $sql->select(); //BookDetails or cash details
                        $select->from(array("a" => "FA_Account"))
                            ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                            ->columns(array('AccountID', 'AccountName' => new Expression('b.AccountName'), 'AccountType' => new Expression("b.AccountType")));
                        $select->where("AccountType IN ('$paymentBookType') and a.CompanyId=$companyId and a.FYearId=$FYearId");
                        $select->order(array("b.AccountName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['BookList'] = $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        break;

                    default:
                        $resp='default';
                        break;
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $CBDet='';
            if($bookType == 'CA'){
                $CBDet='CA';
            }else{
                $CBDet='BA';
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
            $select->where("AccountType IN ('$CBDet') and a.CompanyId=$companyId and a.FYearId=$FYearId");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $fromBookList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $selectGroup = $sql->select();
            $selectGroup->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                ,'Debit' => new Expression("CASE WHEN a.EntryType IN ('R') THEN a.Amount ELSE 0 END")
                ,'Credit' => new Expression("CASE WHEN a.EntryType IN ('P','T') THEN a.Amount ELSE 0 END")
                ,'PDCDebit' => new Expression("CASE WHEN a.EntryType IN ('R') THEN CASE WHEN PDC=0 THEN a.Amount ELSE 0 END ELSE 0 END")
                ,'PDCCredit' => new Expression("CASE WHEN a.EntryType IN ('P','T') THEN CASE WHEN PDC=0 THEN a.Amount ELSE 0 END ELSE 0 END")
                ,'Narration','ChequeNo','ChequeDate' => new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ,'FromAdjust'=> new Expression("ISNULL((SELECT TOP 1 EntryId FROM FA_Adjustment AD WHERE AD.EntryId=A.EntryId AND AD.FYearId=5),0) ")
                ))
                ->join(array('b' => 'FA_AccountMaster'), 'a.AccountID=b.AccountID', array(), $selectGroup::JOIN_LEFT)
                ->join(array('sl' => 'FA_SubLedgerMaster'), 'sl.SubLedgerId=a.SubLedgerId', array(), $selectGroup::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'a.CostCentreId=c.CostCentreId', array(), $selectGroup::JOIN_LEFT)
                ->where("a.BookID=$accountId AND a.CompanyId=$companyId AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectGroup->where("(((a.PDC=1 AND a.RefEntryId=0) OR (a.PDC=1 AND a.GPEntryId=0 )) OR a.PDC=0) ");
            $statement = $sql->getSqlStringForSqlObject($selectGroup);

            $selectMulti = $sql->select();
            $selectMulti->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                ,'Debit' => new Expression("a.Amount")
                ,'Credit' => new Expression("1-1")
                ,'PDCDebit' => new Expression("CASE WHEN PDC=0 THEN a.Amount ELSE 0 END")
                ,'PDCCredit' => new Expression("1-1")
                ,'Narration','ChequeNo','ChequeDate' => new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ,'FromAdjust'=> new Expression("ISNULL((SELECT TOP 1 EntryId FROM FA_Adjustment AD WHERE AD.EntryId=A.EntryId AND AD.FYearId=5),0)")
                ))
                ->join(array('b' => 'FA_AccountMaster'), 'a.BookId=b.AccountId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('sl' => 'FA_SubLedgerMaster'), 'sl.SubLedgerId=a.SubLedgerId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'a.CostCentreId=c.CostCentreId', array(), $selectMulti::JOIN_LEFT)
                ->where("a.AccountId=$accountId AND a.CompanyId=$companyId and a.JournalType<>'M' AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectMulti->where("(((a.PDC=1 AND a.RefEntryId=0) OR (a.PDC=1 AND a.GPEntryId=0 )) OR a.PDC=0) ");
            $selectMulti->combine($selectGroup,'Union ALL');

            $selectFinal = $sql->select();
            $selectFinal->from(array("g"=>$selectMulti))
                ->columns(array("EntryId","VoucherDate", "VoucherNo", "RefNo", "RelatedRefNo","AccountName","SubLedgerName"
                ,"EntryType","JournalType"=> new Expression("g.JournalType"),"Debit"=> new Expression("g.Debit-g.Others"),"Credit"=> new Expression("g.Credit")
                ,"PDCDebit"=> new Expression("g.PDCDebit-g.Others"),"PDCCredit"=> new Expression("g.PDCCredit")
                ,"Narration","ChequeNo","ChequeDate","ChequeDescription","CostCentreName","IsLock","PDC"
                ,"Document"=>new Expression("''"),"IsAppReady","Approve","TypeId","SubLedgerTypeId","Print"=>new Expression("CAST(1-1 As bit)"),"FromAdjust"
                ))
                ->where("g.EntryType='P'");
            $selectFinal->order(new Expression("g.VoucherDate,g.VoucherNo"));
            $statement = $sql->getSqlStringForSqlObject($selectFinal);
            $regList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->FYearId=$FYearId;
            $this->_view->accountId=$accountId;
            $this->_view->companyId=$companyId;
            $this->_view->regList=$regList;
            $this->_view->fromBookList=$fromBookList;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;
            $this->_view->bookType=$bookType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function depositentryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $EntryId = $this->bsf->isNullCheck($this->params()->fromRoute('entryId'),'number');

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
                $type= $this->bsf->isNullCheck($postData['type'], 'string');
                switch($type) {
                    case 'loadChequeDetails':
                        $bookId= $this->bsf->isNullCheck($postData['bookId'], 'number');
                        //$bookType= $this->bsf->isNullCheck($postData['bookType'], 'string');
                        $select = $sql->select();
                        $select->from(array("a" => "FA_Account"))
                            ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                            ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
                        $select->where("a.AccountId=$bookId");
                        $select->order(array("b.AccountName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if($bookNameDet){
                            $bookType=$bookNameDet['AccountType'];
                        }else{
                            $bookType='';
                        }

//                        $curDate= date('Y-m-d', strtotime($this->bsf->isNullCheck(date('d-m-Y'), 'date')));
                        $curDate= Date('d-m-Y');

                        /*Query 1*/
                        $select1 = $sql->select();
                        $select1->from(array("RR" => "FA_ReceiptRegister"))
                            ->join(array("SLM" => "FA_SubLedgerMaster"), "RR.SubLedgerId=SLM.SubLedgerId", array(), $select1::JOIN_INNER)
                            ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select1::JOIN_INNER)
                            ->columns(array('ReceiptId', 'VoucherDate' => new Expression("'$curDate'"), 'OVDate' => new Expression("CAST(getDate() AS DateTime)")
                            ,'VoucherNo'=> new Expression("CAST('' AS Varchar(50))"),'RefVNo'=> new Expression("CAST('' AS varchar(50))")
                            , 'ReceiptDate' => new Expression("FORMAT(RR.ReceiptDate, 'dd-MM-yyyy')"),'ReceiptNo','RefType'=> new Expression("RR.RefType")
                            ,'SubLedgerId'=> new Expression("RR.SubLedgerId"),'SubLedgerTypeId'=> new Expression("SLM.SubLedgerTypeId")
                            ,'SubLedgerName'=> new Expression("SLM.SubLedgerName")
                            ,'RefInfo'=> new Expression("RR.FlatInfo"),'CostCentreName'=> new Expression("CC.CostCentreName")
                            ,'ReceiptAmount','ChequeNo', 'ChequeDate' => new Expression("FORMAT(RR.ChequeDate, 'dd-MM-yyyy')")
                            ,'BankName','CostCentreId','Remarks','PDC'=> new Expression("CAST(0 AS bit)"),'Sel'=> new Expression("CAST(0 AS bit)")
                            ,'Type'=> new Expression("'B'"),'RefAccId'=> new Expression("RR.AccountId")
                            ));
                        $select1->where("RR.EntryId=0  AND RR.CompanyId=$companyId AND RR.ReceiptAmount<>0");
                        if($bookType =='BA')
                            $select1->where("RR.ChequeDate IS NOT NULL");
                        else
                            $select1->where("RR.ChequeDate IS NULL");
                        /*Query 2*/
                        $select2 = $sql->select();
                        $select2->from(array("RR" => "FA_ClientReceiptInfo"))
                            ->join(array("SLM" => "FA_SubLedgerMaster"), "RR.SubLedgerId=SLM.SubLedgerId", array(), $select2::JOIN_INNER)
                            ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select2::JOIN_INNER)
                            ->columns(array('ReceiptId', 'VoucherDate' => new Expression("'$curDate'"), 'OVDate' => new Expression("CAST(getDate() AS DateTime)")
                            ,'VoucherNo'=> new Expression("CAST('' AS Varchar(50))"),'RefVNo'=> new Expression("CAST('' AS varchar(50))")
                            , 'ReceiptDate' => new Expression("FORMAT(RR.ReceiptDate, 'dd-MM-yyyy')"),'ReceiptNo','RefType'=> new Expression("RR.RefType")
                            ,'SubLedgerId'=> new Expression("RR.SubLedgerId"),'SubLedgerTypeId'=> new Expression("SLM.SubLedgerTypeId")
                            ,'SubLedgerName'=> new Expression("SLM.SubLedgerName")
                            ,'RefInfo'=> new Expression("''"),'CostCentreName'=> new Expression("CC.CostCentreName")
                            ,'ReceiptAmount','ChequeNo', 'ChequeDate' => new Expression("FORMAT(RR.ChequeDate, 'dd-MM-yyyy')")
                            ,'BankName','CostCentreId','Remarks','PDC'=> new Expression("CAST(0 AS bit)"),'Sel'=> new Expression("CAST(0 AS bit)")
                            ,'Type'=> new Expression("'L'"),'RefAccId'=> new Expression("RR.AccountId")
                            ));
                        $select2->where("RR.EntryId=0  AND RR.CompanyId=$companyId AND RR.ReceiptAmount<>0");
                        if($bookType =='BA')
                            $select2->where("RR.ChequeDate IS NOT NULL");
                        else
                            $select2->where("RR.ChequeDate IS NULL");
                        $select2->combine($select1,'Union ALL');
                        /*Query 3*/
                        $select3 = $sql->select();
                        $select3->from(array("RR" => "FA_EmpReceiptInfo"))
                            ->join(array("SLM" => "FA_SubLedgerMaster"), "RR.SubLedgerId=SLM.SubLedgerId", array(), $select3::JOIN_INNER)
                            ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select3::JOIN_INNER)
                            ->columns(array('ReceiptId', 'VoucherDate' => new Expression("'$curDate'"), 'OVDate' => new Expression("CAST(getDate() AS DateTime)")
                            ,'VoucherNo'=> new Expression("CAST('' AS Varchar(50))"),'RefVNo'=> new Expression("CAST('' AS varchar(50))")
                            , 'ReceiptDate' => new Expression("FORMAT(RR.ReceiptDate, 'dd-MM-yyyy')"),'ReceiptNo','RefType'=> new Expression("RR.RefType")
                            ,'SubLedgerId'=> new Expression("RR.SubLedgerId"),'SubLedgerTypeId'=> new Expression("SLM.SubLedgerTypeId")
                            ,'SubLedgerName'=> new Expression("SLM.SubLedgerName")
                            ,'RefInfo'=> new Expression("''"),'CostCentreName'=> new Expression("CC.CostCentreName")
                            ,'ReceiptAmount','ChequeNo', 'ChequeDate' => new Expression("FORMAT(RR.ChequeDate, 'dd-MM-yyyy')")
                            ,'BankName','CostCentreId','Remarks','PDC'=> new Expression("CAST(0 AS bit)"),'Sel'=> new Expression("CAST(0 AS bit)")
                            ,'Type'=> new Expression("'E'"),'RefAccId'=> new Expression("RR.AccountId")
                            ));
                        $select3->where("RR.EntryId=0 AND RR.CompanyId=$companyId AND RR.ReceiptAmount<>0");
                        if($bookType =='BA')
                            $select3->where("RR.ChequeDate IS NOT NULL");
                        else
                            $select3->where("RR.ChequeDate IS NULL");
                        $select3->combine($select2,'Union ALL');
                        /*Query 4*/
                        $select4 = $sql->select();
                        $select4->from(array("RR" => "FA_FIReceiptInfo"))
                            ->join(array("SLM" => "FA_SubLedgerMaster"), "RR.SubLedgerId=SLM.SubLedgerId", array(), $select4::JOIN_INNER)
                            ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select4::JOIN_INNER)
                            ->columns(array('ReceiptId', 'VoucherDate' => new Expression("'$curDate'"), 'OVDate' => new Expression("CAST(getDate() AS DateTime)")
                            ,'VoucherNo'=> new Expression("CAST('' AS Varchar(50))"),'RefVNo'=> new Expression("CAST('' AS varchar(50))")
                            , 'ReceiptDate' => new Expression("FORMAT(RR.ReceiptDate, 'dd-MM-yyyy')"),'ReceiptNo','RefType'=> new Expression("RR.RefType")
                            ,'SubLedgerId'=> new Expression("RR.SubLedgerId"),'SubLedgerTypeId'=> new Expression("SLM.SubLedgerTypeId")
                            ,'SubLedgerName'=> new Expression("SLM.SubLedgerName")
                            ,'RefInfo'=> new Expression("''"),'CostCentreName'=> new Expression("CC.CostCentreName")
                            ,'ReceiptAmount','ChequeNo', 'ChequeDate' => new Expression("FORMAT(RR.ChequeDate, 'dd-MM-yyyy')")
                            ,'BankName','CostCentreId','Remarks','PDC'=> new Expression("CAST(0 AS bit)"),'Sel'=> new Expression("CAST(0 AS bit)")
                            ,'Type'=> new Expression("'F'"),'RefAccId'=> new Expression("RR.AccountId")
                            ));
                        $select4->where("RR.EntryId=0 AND RR.CompanyId=$companyId AND RR.ReceiptAmount<>0");
                        if($bookType =='BA')
                            $select4->where("RR.ChequeDate IS NOT NULL");
                        else
                            $select4->where("RR.ChequeDate IS NULL");
                        $select4->combine($select3,'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($select4);
                        $resp['chequeMaster']=$chequeMaster= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $resp['bookType']=$bookType;
                        break;

                    default:
                        $resp='default';
                        break;
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $iRefEntryId= $this->bsf->isNullCheck($postData['EntryId'], 'number');
                $BookId= $this->bsf->isNullCheck($postData['bookName'], 'number');
                $BookType= $this->bsf->isNullCheck($postData['bookType'], 'string');//cash or Bank
                $adviceRowId= $this->bsf->isNullCheck($postData['rowId'], 'number');
                $mode = "A";
                try {
                    $connection->beginTransaction();
                    if($iRefEntryId==0) {
                        $icount=0;
                        for ($i = 1; $i <= $adviceRowId; $i++) {
                            if ($this->bsf->isNullCheck($postData['tick_' . $i], 'string') == "")
                                continue;

                            $icount=$icount+1;
                            $iReceiptId = $this->bsf->isNullCheck($postData['ReceiptId_' . $i], 'number');
                            $voucherNo = $this->bsf->isNullCheck($postData['voucherNo_' . $i], 'string');
                            $voucherDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate_' . $i], 'date')));
                            //$receiptDate= $this->bsf->isNullCheck($postData['reciptDate_' . $i], 'string');
                            $receiptDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['reciptDate_' . $i], 'date')));
                            $receiptNo= $this->bsf->isNullCheck($postData['reciptNo_' . $i], 'string');
                            $RefVNo = $this->bsf->isNullCheck($postData['refVNo_' . $i], 'string');
                            $sRefType = $this->bsf->isNullCheck($postData['refType_' . $i], 'string');
                            $sRType = $this->bsf->isNullCheck($postData['Type_' . $i], 'string');
                            $iRefAccId = $this->bsf->isNullCheck($postData['RefAccId_' . $i], 'number');
                            $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                            $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                            $CostCentreId = $this->bsf->isNullCheck($postData['costCentreId_' . $i], 'number');
                            $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
                            $ChequeNo = $this->bsf->isNullCheck($postData['chequeNo_' . $i], 'string');
                            //$ChequeTransId = $this->bsf->isNullCheck($postData['chequeTransId_' . $i], 'string');
                            if ($BookType == "C") {
                                $ChequeDate = "";
                            } else {
                                //$ChequeDate = $this->bsf->isNullCheck($postData['chequeDate_' . $i], 'string');
                                $ChequeDate =  date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['chequeDate_' . $i], 'date')));
                            }
                            $ChequeDescription = $this->bsf->isNullCheck($postData['bank_' . $i], 'string');
                            $Narration = $this->bsf->isNullCheck($postData['narrition_' . $i], 'string');
                            $PDC = $this->bsf->isNullCheck($postData['pdc_' . $i], 'number');

                            $TransType = "C";
                            if ($sRType == "B")
                                $m_sJVType = "B";
                            else if ($sRType == "L")
                                $m_sJVType = "L";
                            else if ($sRType == "F")
                                $m_sJVType = "F";
                            else if ($sRType == "E")
                                $m_sJVType = "E";
                            else
                                $m_sJVType = "";

                            if ($TransType == "D") {
                                $sTransType = "P";
                            } else {
                                $sTransType = "R";
                            }

                            $insert = $sql->insert();
                            $insert->into('FA_EntryMaster');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RelatedVNo' => ''
                            , 'RefNo' => $RefVNo
                            , 'RelatedRefNo' => ''
                            , 'BookVoucherNo' => ''
                            , 'BankCashVoucherNo' => ''
                            , 'JournalType' => $m_sJVType
                            , 'EntryType' => $sTransType
                            , 'BookId' => $BookId
                            , 'AccountId' => $iRefAccId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId
                            , 'SubLedgerId' => $SubLedgerId
                            , 'Narration' => $Narration
                            , 'Amount' => $Amount
                            , 'CostCentreId' => $CostCentreId
                            , 'PayType' => ''
                            , 'ChequeTransId' => 0
                            , 'ChequeNo' => $ChequeNo
                            , 'ChequeDate' => $ChequeDate
                            , 'ChequeDescription' => $ChequeDescription
                            , 'CompanyId' => $companyId
                            , 'Approve' => 'Y'
                            , 'IsAppReady' => 1
                            , 'PDC' => $PDC
                            , 'FYearId' => $FYearId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $iRowEntryId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RefId' => $iRowEntryId
                            , 'TransType' => $TransType
                            , 'RefType' => $m_sJVType
                            , 'AccountId' => $iRefAccId
                            , 'RelatedAccountId' => $BookId //AccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId//SLTypeId
                            , 'SubLedgerId' => $SubLedgerId//BuyerId
                            , 'RelatedSLTypeId' => 0 //RSLTypeId
                            , 'RelatedSLId' => 0
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => $Narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'RefNo' => $RefVNo
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($TransType=="C"){
                                $TransType="D";
                            } else {
                                $TransType="C";
                            }
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RefId' => $iRowEntryId
                            , 'TransType' => $TransType
                            , 'RefType' => $m_sJVType
                            , 'AccountId' => $BookId
                            , 'RelatedAccountId' =>  $iRefAccId
                            , 'SubLedgerTypeId' => 0
                            , 'SubLedgerId' => 0
                            , 'RelatedSLTypeId' => $SubLedgerTypeId
                            , 'RelatedSLId' => $SubLedgerId
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => $Narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'RefNo' => $RefVNo
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($icount==1){
                                $iRefEntryId =$iRowEntryId;
                            }
                            $update = $sql->update();
                            $update->table('FA_EntryMaster')
                                ->set(array('RefEntryId' => $iRefEntryId
                                , 'RefFYearId' => $FYearId ))
                                ->where(array('EntryId' => $iRowEntryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*
                             * SELECT A.ReceiptTransId,A.ReceiptId,SLTypeId=8,A.SubLedgerId,A.AccountId,A.Amount,A.TransType,
RSLTypeId=3,B.SubLedgerId BuyerId FROM FA_ReceiptTaxDet A
INNER JOIN FA_ReceiptRegister B ON A.ReceiptId=B.ReceiptId
WHERE B.EntryId=0
UNION ALL
SELECT A.ReceiptTransId,A.ReceiptId,A.SLTypeId,A.SubLedgerId,A.AccountId,A.Amount,
A.TransType,RSLTypeId=2,B.SubLedgerId BuyerId FROM FA_ClientReceiptTrans A
INNER JOIN FA_ClientReceiptInfo B ON A.ReceiptId=B.ReceiptId WHERE B.EntryId=0
                             */

                            if ($sRefType == "BR" || $sRefType == "LRR" || $sRefType=="CR" || $sRefType=="FCR" || $sRefType=="FR"){

                                $select = $sql->select();
                                $select->from(array("a"=>"FA_ReceiptTaxDet"))
                                    ->join(array("b" => "FA_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $select::JOIN_INNER)
                                    ->columns(array('ReceiptTransId','ReceiptId','SLTypeId'=>new Expression("8"),'SubLedgerId'
                                    ,'AccountId','Amount','TransType','RSLTypeId'=>new Expression("3"),'BuyerId'=>new Expression("b.SubLedgerId")))
                                    ->where("b.EntryId=0 and a.ReceiptId=$iReceiptId");

                                $selectFinal = $sql->select();
                                $selectFinal->from(array("a"=>"FA_ClientReceiptTrans"))
                                    ->join(array("b" => "FA_ClientReceiptInfo"), "a.ReceiptId=b.ReceiptId", array(), $selectFinal::JOIN_INNER)
                                    ->columns(array('ReceiptTransId','ReceiptId','SLTypeId','SubLedgerId'
                                    ,'AccountId','Amount','TransType','RSLTypeId'=>new Expression("2"),'BuyerId'=>new Expression("b.SubLedgerId")))
                                    ->where("b.EntryId=0 and a.ReceiptId=$iReceiptId");
                                $selectFinal->combine($select,'Union ALL');
                                $statementfeed = $sql->getSqlStringForSqlObject($selectFinal);
                                $taxDetResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach($taxDetResult as &$taxDetResults) {

                                    /*
                                     * INSERT INTO dbo.EntryTrans(VoucherDate,VoucherNo,RefId,TransType,RefType,AccountId,
                                     * RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,
                                     * Amount,Remarks,IRemarks,CompanyId,RefNo,Approve)
VALUES(@VoucherDate,@VoucherNo, @EntryId,@TransType,@JournalType,@AccountId
                                    ,@RelatedAccountId,@SubLedgerTypeId,@SubLedgerId,@RelatedSLTypeId,@RelatedSLId, @CostCentreId
                                    ,@Amount, @Narration,@Remarks, @CompanyId,@RefVNo,@Approve)

                                     */
                                    $sRowType= $taxDetResults['TransType'];
                                    $iRowAccountId= $taxDetResults['AccountId'];
                                    $iRowSLTypeId= $taxDetResults['SLTypeId'];
                                    $iRowSubLedgerId= $taxDetResults['SubLedgerId'];
                                    $iRowBuyerId= $taxDetResults['BuyerId'];
                                    $iRowRSLTypeId= $taxDetResults['RSLTypeId'];
                                    $dRowAmount= $taxDetResults['Amount'];

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('VoucherDate' => $voucherDate
                                    , 'VoucherNo' => $voucherNo
                                    , 'RefId' => $iRowEntryId
                                    , 'TransType' => $sRowType
                                    , 'RefType' => $m_sJVType
                                    , 'AccountId' => $iRefAccId
                                    , 'RelatedAccountId' => $iRowAccountId //AccountId
                                    , 'SubLedgerTypeId' => $iRowSLTypeId//SLTypeId
                                    , 'SubLedgerId' => $iRowBuyerId//BuyerId
                                    , 'RelatedSLTypeId' =>$iRowRSLTypeId //RSLTypeId
                                    , 'RelatedSLId' => $iRowSubLedgerId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dRowAmount
                                    , 'Remarks' => $Narration
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'RefNo' => $RefVNo
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    if($sRowType=="C"){
                                        $sRowType="D";
                                    } else {
                                        $sRowType="C";
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('VoucherDate' => $voucherDate
                                    , 'VoucherNo' => $voucherNo
                                    , 'RefId' => $iRowEntryId
                                    , 'TransType' => $sRowType
                                    , 'RefType' => $m_sJVType
                                    , 'AccountId' => $iRowAccountId
                                    , 'RelatedAccountId' => $iRefAccId //AccountId
                                    , 'SubLedgerTypeId' => $iRowRSLTypeId//SLTypeId
                                    , 'SubLedgerId' => $iRowSubLedgerId//BuyerId
                                    , 'RelatedSLTypeId' =>$iRowSLTypeId //RSLTypeId
                                    , 'RelatedSLId' => $iRowBuyerId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dRowAmount
                                    , 'Remarks' => $Narration
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'RefNo' => $RefVNo
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                #region Buyer Receipt
                                if ($m_sJVType == "B") { // Buyer Receipt
                                    if ($sRType == "B"){

                                        /*
                                         *  sSql = String.Format("UPDATE [{0}]..ReceiptRegister SET BookId={1}, EntryId={2},FYearId={3} WHERE ReceiptId={4}",
                                                        BsfGlobal.g_sFaDBName, obj.BookId, iEntryId, BsfGlobal.g_lYearId, obj.ReceiptId)

                                        sSql = String.Format("UPDATE [{0}]..ReceiptTaxDet SET EntryId={1},FYearId={2} WHERE ReceiptId={3}",
                                                        BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lYearId, obj.ReceiptId);
                                         */
                                        $update = $sql->update();
                                        $update->table('FA_ReceiptRegister')
                                            ->set(array('BookId' => $BookId
                                            , 'EntryId' => $iRowEntryId
                                            , 'FYearId' => $FYearId ))
                                            ->where(array('ReceiptId' => $iReceiptId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $update = $sql->update();
                                        $update->table('FA_ReceiptTaxDet')
                                            ->set(array('EntryId' => $iRowEntryId
                                            , 'FYearId' => $FYearId ))
                                            ->where(array('ReceiptId' => $iReceiptId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                    $update = $sql->update();
                                    $update->table('FA_EntryTrans')
                                        ->set(array('RefNo' => $RefVNo
                                        , 'RefDate' => $voucherDate ));
                                    $update->where("RefType='B' AND RefId=$iRowEntryId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    /*
                                     * sSql = String.Format("UPDATE EntryTrans SET RefNo='{0}',RefDate='{1:dd-MMM-yyyy}' WHERE RefType='B' AND RefId={2}", obj.RefNo, obj.RefDate, iEntryId);

                                     */

                                    //ProgressBillFAUpdate Pending
                                }
                                #endregion

                                #region Client Receipt
                                if ($m_sJVType == "L") { // Client Receipt

                                }
                                #endregion
                            }

                            /*//update chequeTrans
                            $update = $sql->update();
                            $update->table('FA_ChequeTrans')
                                ->set(array('Used' => 0
                                , 'Cancel' => 0
                                , 'CancelRemarks' => ''
                                , 'PDCDate' => null
                                , 'PDCClear' => 0
                                , 'IssueDate' => null
                                , 'FYearId' => 0
                                ))
                                ->where(array('ChequeTransId' => $ChequeTransId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            if($ChequeTransId!=0 ){

                                    $update = $sql->update();
                                    $update->table('FA_ChequeTrans')
                                        ->set(array('Amount' => $Amount
                                        , 'Used' => 1
                                        , 'PDC' => 1
                                        , 'PDCDate' => $ChequeDate
                                        , 'IssueDate' => $voucherDate
                                        , 'FYearId' => $FYearId
                                        ))
                                        ->where(array('ChequeTransId' => $ChequeTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }*/

                        }
                    } else {
                        $mode = "E";
                        //delete
                        /*
                         * sSql = "INSERT INTO PastEntryMaster (EntryId,VoucherNo,VoucherDate,BankCashVoucherNo,BookVoucherNo,EntryType,JournalType,BookId,AccountId, " +
                               "SubLedgerTypeId,SubLedgerId,BranchId,PayType,ChequeTransId,ChequeNo,ChequeDate,ChequeDescription,Narration,Amount,FromCostCentreId,Cancel,CancelDate, " +
                               "CostCentreId,BRS,BRSDate,PDC,CompanyId,IsLock,UserId ) " +
                               "SELECT EntryId,VoucherNo,VoucherDate,BankCashVoucherNo,BookVoucherNo,EntryType,JournalType,BookId,AccountId, " +
                               "SubLedgerTypeId,SubLedgerId,BranchId,PayType,ChequeTransId,ChequeNo,ChequeDate,ChequeDescription,Narration,Amount,FromCostCentreId,Cancel,CancelDate, " +
                               "CostCentreId,BRS,BRSDate,PDC,CompanyId,IsLock," + BsfGlobal.g_lUserId + " FROM EntryMaster WHERE EntryId=" + obj.EntryId + " SELECT SCOPE_IDENTITY() ";

                         */
                        $iHistoryId = 0;
                        $subQuery1 = $sql->select();
                        $subQuery1->from("FA_EntryMaster")
                            ->columns(array('EntryId'))
                            ->where("RefEntryId=$iRefEntryId and RefEntryId<>0");

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_EntryMaster' ))
                            ->columns(array( 'EntryId', 'VoucherNo', 'VoucherDate', 'BankCashVoucherNo', 'BookVoucherNo', 'EntryType', 'JournalType','BookId','AccountId'
                            , 'SubLedgerTypeId','SubLedgerId','BranchId','PayType','ChequeTransId','ChequeNo','ChequeDate','ChequeDescription','Narration','Amount','FromCostCentreId','Cancel','CancelDate'
                            , 'CostCentreId','BRS','BRSDate','PDC','CompanyId','IsLock','UserId'=>new Expression("$userId"),'FYearId' ));
                        //$select->where("a.EntryId=$iEntryId ");
                        $select->where->expression('a.EntryId IN ?', array($subQuery1));

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastEntryMaster' );
                        $insert->columns(array('EntryId', 'VoucherNo', 'VoucherDate', 'BankCashVoucherNo', 'BookVoucherNo', 'EntryType', 'JournalType', 'BookId', 'AccountId'
                        ,'SubLedgerTypeId', 'SubLedgerId', 'BranchId', 'PayType', 'ChequeTransId', 'ChequeNo', 'ChequeDate', 'ChequeDescription', 'Narration', 'Amount', 'FromCostCentreId', 'Cancel', 'CancelDate'
                        ,'CostCentreId', 'BRS', 'BRSDate', 'PDC', 'CompanyId', 'IsLock', 'UserId','FYearId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        $iHistoryId= $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from(array('a' => 'FA_EntryTrans' ))
                            ->columns(array('HistoryId'=>new Expression("$iHistoryId"), 'EntryTransId', 'VoucherDate','VoucherNo','RefId','TransType','RefType'
                            ,'AccountId','RelatedAccountId','SubLedgerTypeId','SubLedgerId'
                            ,'RelatedSLId','CostCentreId','BranchId','Amount','Remarks','FromAdjust','TaxId','RemitId','CompanyId','PDC/Cancel'
                            , 'UserId'=>new Expression("$userId") ));
                        //$select->where("a.RefId=$iEntryId ");
                        $select->where->expression('a.RefId IN ?', array($subQuery1));

                        $insert = $sql->insert();
                        $insert->into( 'FA_PastEntryTrans' );
                        $insert->columns(array('HistoryId','EntryTransId','VoucherDate','VoucherNo','RefId','TransType','RefType','AccountId','RelatedAccountId','SubLedgerTypeId','SubLedgerId'
                        ,'RelatedSLId','CostCentreId','BranchId','Amount','Remarks','FromAdjust','TaxId','RemitId','CompanyId','PDC/Cancel','UserId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );


                        $deleteTrans = $sql->delete();
                        $deleteTrans->from('FA_EntryTrans')
                            ->where("RefType='B'");
                        $deleteTrans->where->expression('RefId IN ?', array($subQuery1));
                        $DelStatement = $sql->getSqlStringForSqlObject($deleteTrans);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_ReceiptRegister')
                            ->set(array('BookId' => 0
                            , 'EntryId' => 0
                            , 'FYearId' => 0 ));
                        $update->where->expression('EntryId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('FA_ReceiptTaxDet')
                            ->set(array('EntryId' => 0
                            , 'FYearId' => 0 ));
                        $update->where->expression('EntryId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        for ($i = 1; $i <= $adviceRowId; $i++) {
                            if ($this->bsf->isNullCheck($postData['tick_' . $i], 'string') == "")
                                continue;

                            $iRowEntryId = $this->bsf->isNullCheck($postData['EntryId_' . $i], 'number');
                            $iReceiptId = $this->bsf->isNullCheck($postData['ReceiptId_' . $i], 'number');
                            $voucherNo = $this->bsf->isNullCheck($postData['voucherNo_' . $i], 'string');
                            $voucherDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['voucherDate_' . $i], 'date')));
                            //$receiptDate= $this->bsf->isNullCheck($postData['reciptDate_' . $i], 'string');
                            $receiptDate= date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['reciptDate_' . $i], 'date')));
                            $receiptNo= $this->bsf->isNullCheck($postData['reciptNo_' . $i], 'string');
                            $RefVNo = $this->bsf->isNullCheck($postData['refVNo_' . $i], 'string');
                            $sRefType = $this->bsf->isNullCheck($postData['refType_' . $i], 'string');
                            $sRType = $this->bsf->isNullCheck($postData['Type_' . $i], 'string');
                            $iRefAccId = $this->bsf->isNullCheck($postData['RefAccId_' . $i], 'number');
                            $SubLedgerId = $this->bsf->isNullCheck($postData['subLedgerId_' . $i], 'number');
                            $SubLedgerTypeId = $this->bsf->isNullCheck($postData['subLedgerTypeId_' . $i], 'number');
                            $CostCentreId = $this->bsf->isNullCheck($postData['costCentreId_' . $i], 'number');
                            $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
                            $ChequeNo = $this->bsf->isNullCheck($postData['chequeNo_' . $i], 'string');
                            //$ChequeTransId = $this->bsf->isNullCheck($postData['chequeTransId_' . $i], 'string');
                            if ($BookType == "C") {
                                $ChequeDate = "";
                            } else {
                                //$ChequeDate = $this->bsf->isNullCheck($postData['chequeDate_' . $i], 'string');
                                $ChequeDate =  date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['chequeDate_' . $i], 'date')));
                            }
                            $ChequeDescription = $this->bsf->isNullCheck($postData['bank_' . $i], 'string');
                            $Narration = $this->bsf->isNullCheck($postData['narrition_' . $i], 'string');
                            $PDC = $this->bsf->isNullCheck($postData['pdc_' . $i], 'number');

                            $TransType = "C";
                            if ($sRType == "B")
                                $m_sJVType = "B";
                            else if ($sRType == "L")
                                $m_sJVType = "L";
                            else if ($sRType == "F")
                                $m_sJVType = "F";
                            else if ($sRType == "E")
                                $m_sJVType = "E";
                            else
                                $m_sJVType = "";

                            if ($TransType == "D") {
                                $sTransType = "P";
                            } else {
                                $sTransType = "R";
                            }

                            //update EntryMaster
                            $update = $sql->update();
                            $update->table('FA_EntryMaster')
                                ->set(array('VoucherDate' => $voucherDate
                                , 'VoucherNo' => $voucherNo
                                , 'RelatedVNo' => ''
                                , 'RefNo' => $RefVNo
                                , 'RelatedRefNo' => ''
                                , 'BookVoucherNo' => ''
                                , 'BankCashVoucherNo' => ''
                                , 'JournalType' => $m_sJVType
                                , 'EntryType' => $sTransType
                                , 'BookId' => $BookId
                                , 'AccountId' => $iRefAccId
                                , 'SubLedgerTypeId' => $SubLedgerTypeId
                                , 'SubLedgerId' => $SubLedgerId
                                , 'Narration' => $Narration
                                , 'Amount' => $Amount
                                , 'CostCentreId' => $CostCentreId
                                , 'PayType' => ''
                                , 'ChequeTransId' => 0
                                , 'ChequeNo' => $ChequeNo
                                , 'ChequeDate' => $ChequeDate
                                , 'ChequeDescription' => $ChequeDescription
                                , 'CompanyId' => $companyId
                                , 'Approve' => 'Y'
                                , 'IsAppReady' => 1
                                , 'PDC' => $PDC ))
                            ->where(array('EntryId' => $iRowEntryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RefId' => $iRowEntryId
                            , 'TransType' => $TransType
                            , 'RefType' => $m_sJVType
                            , 'AccountId' => $iRefAccId
                            , 'RelatedAccountId' => $BookId //AccountId
                            , 'SubLedgerTypeId' => $SubLedgerTypeId//SLTypeId
                            , 'SubLedgerId' => $SubLedgerId//BuyerId
                            , 'RelatedSLTypeId' => 0 //RSLTypeId
                            , 'RelatedSLId' => 0
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => $Narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'RefNo' => $RefVNo
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($TransType=="C"){
                                $TransType="D";
                            } else {
                                $TransType="C";
                            }
                            $insert = $sql->insert();
                            $insert->into('FA_EntryTrans');
                            $insert->Values(array('VoucherDate' => $voucherDate
                            , 'VoucherNo' => $voucherNo
                            , 'RefId' => $iRowEntryId
                            , 'TransType' => $TransType
                            , 'RefType' => $m_sJVType
                            , 'AccountId' => $BookId
                            , 'RelatedAccountId' =>  $iRefAccId
                            , 'SubLedgerTypeId' => 0
                            , 'SubLedgerId' => 0
                            , 'RelatedSLTypeId' => $SubLedgerTypeId
                            , 'RelatedSLId' => $SubLedgerId
                            , 'CostCentreId' => $CostCentreId
                            , 'Amount' => $Amount
                            , 'Remarks' => $Narration
                            , 'IRemarks' => ''
                            , 'CompanyId' => $companyId
                            , 'RefNo' => $RefVNo
                            , 'Approve' => 'Y'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            $update = $sql->update();
                            $update->table('FA_EntryMaster')
                                ->set(array('RefEntryId' => $iRefEntryId
                                , 'RefFYearId' => $FYearId ))
                                ->where(array('EntryId' => $iRowEntryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            /*
                             * SELECT A.ReceiptTransId,A.ReceiptId,SLTypeId=8,A.SubLedgerId,A.AccountId,A.Amount,A.TransType,
RSLTypeId=3,B.SubLedgerId BuyerId FROM FA_ReceiptTaxDet A
INNER JOIN FA_ReceiptRegister B ON A.ReceiptId=B.ReceiptId
WHERE B.EntryId=0
UNION ALL
SELECT A.ReceiptTransId,A.ReceiptId,A.SLTypeId,A.SubLedgerId,A.AccountId,A.Amount,
A.TransType,RSLTypeId=2,B.SubLedgerId BuyerId FROM FA_ClientReceiptTrans A
INNER JOIN FA_ClientReceiptInfo B ON A.ReceiptId=B.ReceiptId WHERE B.EntryId=0
                             */

                            if ($sRefType == "BR" || $sRefType == "LRR" || $sRefType=="CR" || $sRefType=="FCR" || $sRefType=="FR"){

                                $select = $sql->select();
                                $select->from(array("a"=>"FA_ReceiptTaxDet"))
                                    ->join(array("b" => "FA_ReceiptRegister"), "a.ReceiptId=b.ReceiptId", array(), $select::JOIN_INNER)
                                    ->columns(array('ReceiptTransId','ReceiptId','SLTypeId'=>new Expression("8"),'SubLedgerId'
                                    ,'AccountId','Amount','TransType','RSLTypeId'=>new Expression("3"),'BuyerId'=>new Expression("b.SubLedgerId")))
                                    ->where("b.EntryId=0 and a.ReceiptId=$iReceiptId");

                                $selectFinal = $sql->select();
                                $selectFinal->from(array("a"=>"FA_ClientReceiptTrans"))
                                    ->join(array("b" => "FA_ClientReceiptInfo"), "a.ReceiptId=b.ReceiptId", array(), $selectFinal::JOIN_INNER)
                                    ->columns(array('ReceiptTransId','ReceiptId','SLTypeId','SubLedgerId'
                                    ,'AccountId','Amount','TransType','RSLTypeId'=>new Expression("2"),'BuyerId'=>new Expression("b.SubLedgerId")))
                                    ->where("b.EntryId=0 and a.ReceiptId=$iReceiptId");
                                $selectFinal->combine($select,'Union ALL');
                                $statementfeed = $sql->getSqlStringForSqlObject($selectFinal);
                                $taxDetResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach($taxDetResult as &$taxDetResults) {

                                    /*
                                     * INSERT INTO dbo.EntryTrans(VoucherDate,VoucherNo,RefId,TransType,RefType,AccountId,
                                     * RelatedAccountId,SubLedgerTypeId,SubLedgerId,RelatedSLTypeId,RelatedSLId,CostCentreId,
                                     * Amount,Remarks,IRemarks,CompanyId,RefNo,Approve)
VALUES(@VoucherDate,@VoucherNo, @EntryId,@TransType,@JournalType,@AccountId
                                    ,@RelatedAccountId,@SubLedgerTypeId,@SubLedgerId,@RelatedSLTypeId,@RelatedSLId, @CostCentreId
                                    ,@Amount, @Narration,@Remarks, @CompanyId,@RefVNo,@Approve)

                                     */
                                    $sRowType= $taxDetResults['TransType'];
                                    $iRowAccountId= $taxDetResults['AccountId'];
                                    $iRowSLTypeId= $taxDetResults['SLTypeId'];
                                    $iRowSubLedgerId= $taxDetResults['SubLedgerId'];
                                    $iRowBuyerId= $taxDetResults['BuyerId'];
                                    $iRowRSLTypeId= $taxDetResults['RSLTypeId'];
                                    $dRowAmount= $taxDetResults['Amount'];

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('VoucherDate' => $voucherDate
                                    , 'VoucherNo' => $voucherNo
                                    , 'RefId' => $iRowEntryId
                                    , 'TransType' => $sRowType
                                    , 'RefType' => $m_sJVType
                                    , 'AccountId' => $iRefAccId
                                    , 'RelatedAccountId' => $iRowAccountId //AccountId
                                    , 'SubLedgerTypeId' => $iRowSLTypeId//SLTypeId
                                    , 'SubLedgerId' => $iRowBuyerId//BuyerId
                                    , 'RelatedSLTypeId' =>$iRowRSLTypeId //RSLTypeId
                                    , 'RelatedSLId' => $iRowSubLedgerId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dRowAmount
                                    , 'Remarks' => $Narration
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'RefNo' => $RefVNo
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    if($sRowType=="C"){
                                        $sRowType="D";
                                    } else {
                                        $sRowType="C";
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('FA_EntryTrans');
                                    $insert->Values(array('VoucherDate' => $voucherDate
                                    , 'VoucherNo' => $voucherNo
                                    , 'RefId' => $iRowEntryId
                                    , 'TransType' => $sRowType
                                    , 'RefType' => $m_sJVType
                                    , 'AccountId' => $iRowAccountId
                                    , 'RelatedAccountId' => $iRefAccId //AccountId
                                    , 'SubLedgerTypeId' => $iRowRSLTypeId//SLTypeId
                                    , 'SubLedgerId' => $iRowSubLedgerId//BuyerId
                                    , 'RelatedSLTypeId' =>$iRowSLTypeId //RSLTypeId
                                    , 'RelatedSLId' => $iRowBuyerId
                                    , 'CostCentreId' => $CostCentreId
                                    , 'Amount' => $dRowAmount
                                    , 'Remarks' => $Narration
                                    , 'IRemarks' => ''
                                    , 'CompanyId' => $companyId
                                    , 'RefNo' => $RefVNo
                                    , 'Approve' => 'Y'));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                #region Buyer Receipt
                                if ($m_sJVType == "B") { // Buyer Receipt
                                    if ($sRType == "B"){

                                        /*
                                         *  sSql = String.Format("UPDATE [{0}]..ReceiptRegister SET BookId={1}, EntryId={2},FYearId={3} WHERE ReceiptId={4}",
                                                        BsfGlobal.g_sFaDBName, obj.BookId, iEntryId, BsfGlobal.g_lYearId, obj.ReceiptId)

                                        sSql = String.Format("UPDATE [{0}]..ReceiptTaxDet SET EntryId={1},FYearId={2} WHERE ReceiptId={3}",
                                                        BsfGlobal.g_sFaDBName, iEntryId, BsfGlobal.g_lYearId, obj.ReceiptId);
                                         */
                                        $update = $sql->update();
                                        $update->table('FA_ReceiptRegister')
                                            ->set(array('BookId' => $BookId
                                            , 'EntryId' => $iRowEntryId
                                            , 'FYearId' => $FYearId ))
                                            ->where(array('ReceiptId' => $iReceiptId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $update = $sql->update();
                                        $update->table('FA_ReceiptTaxDet')
                                            ->set(array('EntryId' => $iRowEntryId
                                            , 'FYearId' => $FYearId ))
                                            ->where(array('ReceiptId' => $iReceiptId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                    $update = $sql->update();
                                    $update->table('FA_EntryTrans')
                                        ->set(array('RefNo' => $RefVNo
                                        , 'RefDate' => $voucherDate ));
                                    $update->where("RefType='B' AND RefId=$iRowEntryId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    /*
                                     * sSql = String.Format("UPDATE EntryTrans SET RefNo='{0}',RefDate='{1:dd-MMM-yyyy}' WHERE RefType='B' AND RefId={2}", obj.RefNo, obj.RefDate, iEntryId);
                                     */
                                    //ProgressBillFAUpdate Pending
                                }
                                #endregion

                                #region Client Receipt
                                if ($m_sJVType == "L") { // Client Receipt

                                }
                                #endregion
                            }


                        }
                    }
                    $connection->commit();
                    if ($mode == "E") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Deposit-Entry-Edit','E','FA-Deposit Entry Details',$iRefEntryId,0, $companyId, 'FA','',$userId, 0 ,0);//pass refno after FA as voucher $adviceNo
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'FA-Deposit-Entry-Add','N','FA-Deposit Entry Details',$iRefEntryId,0, $companyId, 'FA','',$userId, 0 ,0);
                    }
                    $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "journalbook"));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            /*
             * SELECT A.AccountID,B.AccountName,AccountType
			  FROM FA_Account A  INNER JOIN FA_AccountMaster B on A.AccountID=B.AccountId
			  WHERE AccountType IN ('BA','CA') AND A.CompanyId=1 and FYearId=1
			  ORDER BY B.AccountName


SELECT ReceiptId, CAST('22/May/2017' AS DateTime) VoucherDate,CAST(getDate() AS DateTime)
OVDate, CAST('' AS Varchar(50)) VoucherNo,CAST('' AS varchar(50)) RefVNo, ReceiptDate,ReceiptNo
,RR.RefType,RR.SubLedgerId, SLM.SubLedgerName,RefInfo=FlatInfo,CC.CostCentreName,
RR.ReceiptAmount,RR.ChequeNo,RR.ChequeDate,RR.BankName,RR.CostCentreId,RR.Remarks,
 PDC=CAST(0 AS bit), CAST(0 AS bit) Sel,Type='B',RefAccId=RR.AccountId FROM
 FA_ReceiptRegister RR
 INNER JOIN FA_SubLedgerMaster SLM ON RR.SubLedgerId=SLM.SubLedgerId
 INNER JOIN WF_CostCentre CC ON CC.CostCentreId=RR.CostCentreId
 WHERE RR.EntryId=0  AND RR.CompanyId=3  AND RR.ReceiptAmount<>0
 AND RR.ChequeDate IS NOT NULL
 UNION ALL
 SELECT ReceiptId,CAST('22/May/2017' AS DateTime) VoucherDate,CAST(getDate() AS DateTime) OVDate,
  CAST('' AS Varchar(50)) VoucherNo,CAST('' AS varchar(50)) RefVNo, ReceiptDate,ReceiptNo,
  RR.RefType,RR.SubLedgerId, SLM.SubLedgerName,RefInfo='',CC.CostCentreName,
   RR.ReceiptAmount,RR.ChequeNo,RR.ChequeDate,RR.BankName,RR.CostCentreId,RR.Remarks,
    PDC=CAST(0 AS bit),CAST(0 AS bit) Sel, Type='L',RefAccId=RR.AccountId FROM FA_ClientReceiptInfo RR
	INNER JOIN FA_SubLedgerMaster SLM ON RR.SubLedgerId=SLM.SubLedgerId
	INNER JOIN WF_CostCentre CC ON CC.CostCentreId=RR.CostCentreId
	WHERE RR.EntryId=0  AND RR.CompanyId=3  AND RR.ReceiptAmount<>0 AND RR.ChequeDate IS NOT NULL
	UNION ALL
	SELECT ReceiptId,CAST('22/May/2017' AS DateTime) VoucherDate,CAST(getDate() AS DateTime) OVDate,
	CAST('' AS Varchar(50)) VoucherNo,CAST('' AS varchar(50)) RefVNo, ReceiptDate,ReceiptNo,
	RR.RefType,RR.SubLedgerId, SLM.SubLedgerName,RefInfo='',CC.CostCentreName, RR.ReceiptAmount,
	RR.ChequeNo,RR.ChequeDate,RR.BankName,RR.CostCentreId,RR.Remarks, PDC=CAST(0 AS bit),
	CAST(0 AS bit) Sel, Type='E',RefAccId=RR.AccountId FROM FA_EmpReceiptInfo RR
	INNER JOIN FA_SubLedgerMaster SLM ON RR.SubLedgerId=SLM.SubLedgerId
	INNER JOIN WF_CostCentre CC ON CC.CostCentreId=RR.CostCentreId
	WHERE RR.EntryId=0 AND RR.CompanyId=3  AND RR.ReceiptAmount<>0 AND RR.ChequeDate IS NOT NULL
	UNION ALL
	SELECT ReceiptId,CAST('22/May/2017' AS DateTime) VoucherDate,CAST(getDate() AS DateTime) OVDate,
	CAST('' AS Varchar(50)) VoucherNo,CAST('' AS varchar(50)) RefVNo, ReceiptDate,ReceiptNo,RR.RefType,
	RR.SubLedgerId, SLM.SubLedgerName,RefInfo='',CC.CostCentreName, RR.ReceiptAmount,RR.ChequeNo,
	RR.ChequeDate,RR.BankName,RR.CostCentreId,RR.Remarks, PDC=CAST(0 AS bit),CAST(0 AS bit) Sel,
	 Type='F',RefAccId=RR.AccountId FROM FA_FIReceiptInfo RR
	 INNER JOIN FA_SubLedgerMaster SLM ON RR.SubLedgerId=SLM.SubLedgerId
	 INNER JOIN WF_CostCentre CC ON CC.CostCentreId=RR.CostCentreId
	 WHERE RR.EntryId=0 AND RR.CompanyId=3  AND RR.ReceiptAmount<>0 AND RR.ChequeDate IS NOT NULL


           SELECT A.ReceiptTransId,A.ReceiptId,SLTypeId=8,A.SubLedgerId,A.AccountId,A.Amount,A.TransType,
RSLTypeId=3,B.SubLedgerId BuyerId FROM FA_ReceiptTaxDet A
INNER JOIN FA_ReceiptRegister B ON A.ReceiptId=B.ReceiptId
WHERE B.EntryId=0
UNION ALL
SELECT A.ReceiptTransId,A.ReceiptId,A.SLTypeId,A.SubLedgerId,A.AccountId,A.Amount,
A.TransType,RSLTypeId=2,B.SubLedgerId BuyerId FROM FA_ClientReceiptTrans A
INNER JOIN FA_ClientReceiptInfo B ON A.ReceiptId=B.ReceiptId WHERE B.EntryId=0

SELECT A.ReceiptTransId,A.ReceiptId,A.SLTypeId,A.SubLedgerId
,A.AccountId,A.Amount,A.TransType FROM FA_ReceiptTransDet A  WHERE A.EntryId=0
             */

            $select1 = $sql->select();
            $select1->from(array("A" => "FA_ReceiptTaxDet"))
                ->join(array("B" => "FA_ReceiptRegister"), "A.ReceiptId=B.ReceiptId", array(), $select1::JOIN_INNER)
                ->columns(array('ReceiptTransId', 'ReceiptId','SLTypeId'=>new Expression("8"),'SubLedgerId','AccountId','Amount','TransType'
                ,'RSLTypeId'=> new Expression("3"),'BuyerId'=> new Expression("B.SubLedgerId")
                ));
            $select1->where("B.EntryId=0");

            $select2 = $sql->select();
            $select2->from(array("A" => "FA_ClientReceiptTrans"))
                ->join(array("B" => "FA_ClientReceiptInfo"), "A.ReceiptId=B.ReceiptId", array(), $select2::JOIN_INNER)
                ->columns(array('ReceiptTransId', 'ReceiptId','SLTypeId','SubLedgerId','AccountId','Amount','TransType'
                ,'RSLTypeId'=> new Expression("2"),'BuyerId'=> new Expression("B.SubLedgerId")
                ));
            $select2->where("B.EntryId=0");
            $select2->combine($select1,'Union ALL');
            $statement = $sql->getSqlStringForSqlObject($select2);
            $clientRecipt= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->clientRecipt=$clientRecipt;

            $select = $sql->select();
            $select->from(array("A" => "FA_ReceiptTransDet"))
                ->columns(array('ReceiptTransId', 'ReceiptId','SLTypeId','SubLedgerId','AccountId','Amount','TransType'
                ));
            $select->where("A.EntryId=0");
            $statement = $sql->getSqlStringForSqlObject($select2);
            $ReceiptTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ReceiptTrans=$ReceiptTrans;

            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
            $select->where("AccountType IN ('BA','CA') and a.CompanyId=$companyId and a.FYearId=$FYearId");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->bookNameDet=$bookNameDet;

            //For Edit Entry
            $bookId=0;
            $allowEdit=1;
            if($EntryId!=0){
                $select = $sql->select();
                $select->from(array("a" => "FA_EntryMaster"))
                            ->columns(array("RefEntryId","Approve"))
                            ->where("EntryId=$EntryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $refEntryIdList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($refEntryIdList){
                    $EntryId=$refEntryIdList['RefEntryId'];
                    if($refEntryIdList['Approve']=="Y"){
                        $allowEdit=0;
                    }
                }
            }

            $select = $sql->select();
            $select->from(array("a" => "FA_EntryMaster"))
                ->columns(array('BookId'=>new Expression('distinct BookId')));
            $select->where("RefEntryId=$EntryId and RefEntryId<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if($bookNameDet){
                $bookId=$bookNameDet['BookId'];
            }

/*
             * SELECT EM.EntryId,ReceiptId,EM.VoucherDate,OVDate=EM.VoucherDate, EM.VoucherNo,RefVNo=EM.RefNo, ReceiptDate,ReceiptNo,RR.RefType,RR.SubLedgerId, SLM.SubLedgerName,CC.CostCentreName,RefInfo=FlatInfo,
RR.ReceiptAmount,EM.ChequeNo,EM.ChequeDate,EM.ChequeDescription BankName,EM.CostCentreId,EM.Narration Remarks,PDC=EM.PDC, CAST(1 AS bit) Sel,Type='B',RefAccId=RR.AccountId
FROM FA_ReceiptRegister RR INNER JOIN FA_SubLedgerMaster SLM ON RR.SubLedgerId=SLM.SubLedgerId
INNER JOIN WF_CostCentre CC ON CC.CostCentreId=RR.CostCentreId
INNER JOIN FA_EntryMaster EM ON EM.EntryId=RR.EntryId
WHERE RR.CompanyId=2 AND  RR.FYearId=1 AND
RR.EntryId in (SELECT EntryId FROM [FA_EntryMaster] AS [a] WHERE RefEntryId=$EntryId and RefEntryId<>0)
             */
            $select = $sql->select();
            $select->from(array("RR" => "FA_ReceiptRegister"))
                ->join(array("SLM" => "FA_SubLedgerMaster"), "RR.SubLedgerId=SLM.SubLedgerId", array(), $select::JOIN_INNER)
                ->join(array("CC" => "WF_CostCentre"), "CC.CostCentreId=RR.CostCentreId", array(), $select::JOIN_INNER)
                ->join(array("EM" => "FA_EntryMaster"), "EM.EntryId=RR.EntryId", array(), $select::JOIN_INNER)
                ->columns(array('EntryId'=>new Expression('EM.EntryId'),'ReceiptId'
                ,'VoucherDate' => new Expression("FORMAT(EM.VoucherDate, 'dd-MM-yyyy')"),'OVDate' => new Expression("FORMAT(EM.VoucherDate, 'dd-MM-yyyy')")
                ,'VoucherNo'=>new Expression('EM.VoucherNo'),'RefNo'=>new Expression('EM.RefNo')
                ,'ReceiptDate' => new Expression("FORMAT(RR.ReceiptDate, 'dd-MM-yyyy')"),'ReceiptNo','RefType','SubLedgerId'
                ,'SubLedgerName'=>new Expression('SLM.SubLedgerName'),'CostCentreName'=>new Expression('CC.CostCentreName')
                ,'RefInfo'=>new Expression('RR.FlatInfo'),'ReceiptAmount','ChequeNo'=>new Expression('EM.ChequeNo')
                ,'ChequeDate' => new Expression("FORMAT(EM.ChequeDate, 'dd-MM-yyyy')"),'BankName'=>new Expression('EM.ChequeDescription')
                ,'CostCentreId'=>new Expression('EM.CostCentreId'),'Remarks'=>new Expression('EM.Narration')
                ,'PDC'=>new Expression('EM.PDC'),'Sel'=>new Expression('CAST(1 AS bit)'),'Type'=>new Expression("'B'")
                ,'RefAccId'=>new Expression('RR.AccountId')
                ));
            $select->where("RR.CompanyId=$companyId AND  RR.FYearId=$FYearId AND RR.EntryId in (SELECT EntryId FROM FA_EntryMaster WHERE RefEntryId=$EntryId and RefEntryId<>0)");
            $statement = $sql->getSqlStringForSqlObject($select);
            $chequeMasterEdit= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->chequeMasterEdit=$chequeMasterEdit;


            $this->_view->EntryId=$EntryId;
            $this->_view->allowEdit=$allowEdit;
            $this->_view->bookId=$bookId;

            $aVNo = CommonHelper::getVoucherNo(611, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->aVNo= $aVNo;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function depositregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');
        $bookType= $this->bsf->isNullCheck($this->params()->fromRoute('bookType'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                $type= $this->bsf->isNullCheck($postData['type'], 'string');
                switch($type) {
                    case 'loadBookName':
                        $paymentBookType = $this->bsf->isNullCheck($postData['paymentBookType'], 'string');
                        $select = $sql->select(); //BookDetails or cash details
                        $select->from(array("a" => "FA_Account"))
                            ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                            ->columns(array('AccountID', 'AccountName' => new Expression('b.AccountName'), 'AccountType' => new Expression("b.AccountType")));
                        $select->where("AccountType IN ('$paymentBookType') and a.CompanyId=$companyId and a.FYearId=$FYearId");
                        $select->order(array("b.AccountName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['BookList'] = $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        break;

                    default:
                        $resp='default';
                        break;
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $CBDet='';
            if($bookType == 'CA'){
                $CBDet='CA';
            }else{
                $CBDet='BA';
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
            $select->where("AccountType IN ('$CBDet') and a.CompanyId=$companyId and a.FYearId=$FYearId");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $fromBookList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $selectMulti = $sql->select();
            $selectMulti->from(array("a"=>"FA_EntryMaster"))
                ->columns(array('EntryId'=> new Expression("a.RefEntryId"), 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                ,'Debit' => new Expression("a.Amount")
                ,'Credit' => new Expression("1-1")
                ,'PDCDebit' => new Expression("CASE WHEN PDC=0 THEN a.Amount ELSE 0 END")
                ,'PDCCredit' => new Expression("1-1")
                ,'Narration','ChequeNo','ChequeDate' => new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ,'FromAdjust'=> new Expression("ISNULL((SELECT TOP 1 EntryId FROM FA_Adjustment AD WHERE AD.EntryId=A.EntryId AND AD.FYearId=5),0)")
                ))
                ->join(array('b' => 'FA_AccountMaster'), 'a.BookId=b.AccountId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('sl' => 'FA_SubLedgerMaster'), 'sl.SubLedgerId=a.SubLedgerId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'a.CostCentreId=c.CostCentreId', array(), $selectMulti::JOIN_LEFT)
                ->where("b.AccountId=$accountId And a.CompanyId=$companyId and a.JournalType IN ('B') AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");// AND a.AccountId=$accountId AND a.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
            $selectMulti->where("(((a.PDC=1 AND a.RefEntryId=0) OR (a.PDC=1 AND a.GPEntryId=0 )) OR a.PDC=0) ");
            $statement = $sql->getSqlStringForSqlObject($selectMulti);
            $regList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->FYearId=$FYearId;
            $this->_view->accountId=$accountId;
            $this->_view->companyId=$companyId;
            $this->_view->regList=$regList;
            $this->_view->fromBookList=$fromBookList;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;
            $this->_view->bookType=$bookType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function journalbookAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $accountId = $this->bsf->isNullCheck($this->params()->fromRoute('accountId'),'number');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');
        $bookType= $this->bsf->isNullCheck($this->params()->fromRoute('bookType'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                $type= $this->bsf->isNullCheck($postData['type'], 'string');
                switch($type) {
                    case 'loadBookName':
                        $paymentBookType = $this->bsf->isNullCheck($postData['paymentBookType'], 'string');
                        $select = $sql->select(); //BookDetails or cash details
                        $select->from(array("a" => "FA_Account"))
                            ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                            ->columns(array('AccountID', 'AccountName' => new Expression('b.AccountName'), 'AccountType' => new Expression("b.AccountType")));
                        $select->where("AccountType IN ('$paymentBookType') and a.CompanyId=$companyId and a.FYearId=$FYearId");
                        $select->order(array("b.AccountName"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['BookList'] = $bookNameDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        break;

                    default:
                        $resp='default';
                        break;
                }

            }
//            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $CBDet='';
            if($bookType == 'CA'){
                $CBDet='CA';
            }else{
                $CBDet='BA';
            }
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->join(array("b" => "FA_AccountMaster"), "a.AccountID=b.AccountId", array(), $select::JOIN_INNER)
                ->columns(array('AccountID','AccountName'=>new Expression('b.AccountName'),'AccountType'=>new Expression("b.AccountType")));
            $select->where("AccountType IN ('$CBDet') and a.CompanyId=$companyId and a.FYearId=$FYearId");
            $select->order(array("b.AccountName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $fromBookList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $selectGroup = $sql->select();
            $selectGroup->from(array("A"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(A.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                ,'AccountName' => new Expression("CASE WHEN A.AccountId=0 THEN 'Cash Management' ELSE B.AccountName END")
                ,'SubLedgerName' => new Expression("LTRIM(SL.SubLedgerName + ' ' + ISNULL(CCSL.Remarks,''))")
                ,'Debit' => new Expression("CASE WHEN A.EntryType IN ('R') THEN A.Amount ELSE 0 END")
                ,'Credit' => new Expression("CASE WHEN A.EntryType IN ('P','T') THEN A.Amount ELSE 0 END")
                ,'PDCDebit' => new Expression("CASE WHEN A.EntryType IN ('R') THEN CASE WHEN PDC=0 THEN A.Amount ELSE 0 END ELSE 0 END")
                ,'PDCCredit' => new Expression("CASE WHEN A.EntryType IN ('P','T') THEN CASE WHEN PDC=0 THEN A.Amount ELSE 0 END ELSE 0 END")
                ,'Narration','ChequeNo','ChequeDate' => new Expression("FORMAT(A.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','CostCentreName'=> new Expression("C.CostCentreName")
                ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                ,'Approve','TypeId'=> new Expression("B.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN A.AccountId=0 THEN OtherCharges ELSE 0 END")
                ,'FromAdjust'=> new Expression("ISNULL((SELECT TOP 1 EntryId FROM FA_Adjustment AD WHERE AD.EntryId=A.EntryId AND AD.FYearId=2),0)")
                ))
                ->join(array('B' => 'FA_AccountMaster'), 'A.AccountID=B.AccountId', array(), $selectGroup::JOIN_LEFT)
                ->join(array('SL' => 'FA_SubLedgerMaster'), 'SL.SubLedgerId=A.SubLedgerId', array(), $selectGroup::JOIN_LEFT)
                ->join(array('CCSL' => 'FA_CCSLDet'), 'SL.SubLedgerId=CCSL.SLId AND CCSL.CCId=A.CostCentreId', array(), $selectGroup::JOIN_LEFT)
                ->join(array('C' => 'WF_CostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $selectGroup::JOIN_LEFT)
                ->where("A.BookID=$accountId AND A.CompanyId=$companyId AND A.FYearId=$FYearId AND A.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectGroup->where("(((A.PDC=1 AND A.RefEntryId=0) OR (A.PDC=1 AND A.GPEntryId=0 )) OR A.PDC=0) ");
            $statement = $sql->getSqlStringForSqlObject($selectGroup);

            $selectMulti = $sql->select();
            $selectMulti->from(array("A"=>"FA_EntryMaster"))
                ->columns(array('EntryId', 'EntryType','VoucherDate' => new Expression("FORMAT(a.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo' , 'RefNo','RelatedRefNo'
                ,'AccountName' => new Expression("CASE WHEN a.AccountId=0 THEN 'Cash Management' ELSE b.AccountName END")
                ,'SubLedgerName' => new Expression("LTRIM(sl.SubLedgerName)")
                ,'Debit' => new Expression("a.Amount")
                ,'Credit' => new Expression("1-1")
                ,'PDCDebit' => new Expression("CASE WHEN PDC=0 THEN a.Amount ELSE 0 END")
                ,'PDCCredit' => new Expression("1-1")
                ,'Narration','ChequeNo','ChequeDate' => new Expression("FORMAT(a.ChequeDate, 'dd-MM-yyyy')"),'ChequeDescription','CostCentreName'=> new Expression("c.CostCentreName")
                ,'JournalType','IsLock', 'PDC' => new Expression("CASE WHEN PDC=0 THEN CAST(0 AS Bit) ELSE CAST(1 AS Bit) END"),'IsAppReady'
                ,'Approve','TypeId'=> new Expression("b.TypeId"),'SubLedgerTypeId','Others'=> new Expression("CASE WHEN a.AccountId=621 THEN OtherCharges ELSE 0 END")
                ,'FromAdjust'=> new Expression("ISNULL((SELECT TOP 1 EntryId FROM FA_Adjustment AD WHERE AD.EntryId=A.EntryId AND AD.FYearId=5),0)")
                ))
                ->join(array('B' => 'FA_AccountMaster'), 'A.BookId=B.AccountId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('SL' => 'FA_SubLedgerMaster'), 'SL.SubLedgerId=A.SubLedgerId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('CCSL' => 'FA_CCSLDet'), 'SL.SubLedgerId=CCSL.SLId AND CCSL.CCId=A.CostCentreId', array(), $selectMulti::JOIN_LEFT)
                ->join(array('C' => 'WF_CostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $selectMulti::JOIN_LEFT)
                ->where("A.AccountId=$accountId AND A.CompanyId=$companyId AND A.FYearId=$FYearId AND A.EntryType='T' AND A.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");
            $selectMulti->where("(((A.PDC=1 AND A.RefEntryId=0) OR (A.PDC=1 AND A.GPEntryId=0 )) OR A.PDC=0)");
            $selectMulti->combine($selectGroup,'Union ALL');

            $selectFinal = $sql->select();
            $selectFinal->from(array("G"=>$selectMulti))
                ->columns(array("EntryId","VoucherDate", "VoucherNo", "RefNo", "RelatedRefNo","AccountName","SubLedgerName"
                ,"EntryType","JournalType"=> new Expression("G.JournalType"),"Debit"=> new Expression("G.Debit-G.Others"),"Credit"=> new Expression("G.Credit")
                ,"PDCDebit"=> new Expression("G.PDCDebit-G.Others"),"PDCCredit"=> new Expression("G.PDCCredit")
                ,"Narration","ChequeNo","ChequeDate","ChequeDescription","CostCentreName","IsLock","PDC"
                ,"Document"=>new Expression("''"),"IsAppReady","Approve","TypeId","SubLedgerTypeId","Print"=>new Expression("CAST(1-1 As bit)"),"FromAdjust"
                ));
            $selectFinal->order(new Expression("G.VoucherDate,G.VoucherNo"));
            $statement = $sql->getSqlStringForSqlObject($selectFinal);
            $regList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->FYearId=$FYearId;
            $this->_view->accountId=$accountId;
            $this->_view->companyId=$companyId;
            $this->_view->regList=$regList;
            $this->_view->fromBookList=$fromBookList;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;
            $this->_view->bookType=$bookType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

}
