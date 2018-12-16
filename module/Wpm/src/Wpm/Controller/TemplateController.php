<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wpm\Controller;

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

class TemplateController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function workorderheaderAction(){
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
    public function getHireOrderAction(){
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
                $postParams = $request->getPost();
                $hoId = $postParams['HOId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_HOTypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('data' => 'ResourceId', 'ResourceName', 'UnitId' => 'UnitId','WorkRate'=>'WorkRate','WorkUnitId'), $select::JOIN_INNER)
                    ->join(array('d' => 'Proj_projectResource'), 'a.ResourceId = d.ResourceId', array('eRate'=>'Rate','eQty'=>'Qty'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'e.UnitId = b.WorkUnitId', array('wUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where('a.HORegisterId = ' . $hoId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hoHireTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_HORegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName','ProjectId'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'))
                    ->where('a.HORegisterId = ' . $hoId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hoRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "WPM_HOQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.HORegisterId' => $hoId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getServiceOrderAction(){
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
                $postParams = $request->getPost();
                $soId = $postParams['SOId'];
                $this->_view->soId=$soId;
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SOServiceTrans'))
                    ->columns(array(
                        "SOServiceTransId"=>new Expression("a.SOServiceTransId"),
                        "SORegisterId"=>new Expression("a.SORegisterId"),
                        "ServiceId"=>new Expression("a.ServiceId"),
                        "UnitId"=>new Expression("a.UnitId"),
                        "Qty"=>new Expression('CAST(a.Qty As Decimal(18,3))'),
                        "Amount"=>new Expression('CAST(a.Amount As Decimal(18,2))'),
                        "Rate"=>new Expression('CAST(a.Rate As Decimal(18,2))'),
                    ))
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('Desc'=>new Expression('ServiceName')), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SORegister'), 'e.SORegisterId = a.SORegisterId', array('CostCentreId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.SORegisterId = ' . $soId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SORegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName','CostCentreId'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName','VendorId'))
                    ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName','ServiceTypeId'))
                    ->join(array('e' => 'Proj_ServiceMaster'), 'e.ServiceTypeId = d.ServiceTypeId', array('ServiceId'))
                    ->where('a.SORegisterId = ' . $soId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->soRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "WPM_SOQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.SORegisterId' => $soId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getServiceBillAction(){
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
                $postParams = $request->getPost();
                $sbId = $postParams['SBId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SBServiceTrans'))
                    ->columns(array(
                        "SBServiceTransId"=>new Expression("a.SBServiceTransId"),
                        "SBRegisterId"=>new Expression("a.SBRegisterId"),
                        "ServiceId"=>new Expression("a.ServiceId"),
                        "UnitId"=>new Expression("a.UnitId"),
                        "Qty"=>new Expression('CAST(a.Qty As Decimal(18,3))'),
                        "Amount"=>new Expression('CAST(a.Amount As Decimal(18,2))'),
                        "Rate"=>new Expression('CAST(a.Rate As Decimal(18,2))'),
                    ))
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('Desc'=>new Expression('ServiceName')), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SBRegister'), 'e.SBRegisterId = a.SBRegisterId', array('CostCentreId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.SBRegisterId = ' . $sbId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceBillTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SBRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreId','CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array('SDNo'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SBServiceTrans'), 'a.SBRegisterId = e.SBRegisterId', array('ServiceId'), $select::JOIN_LEFT)
                    ->where('a.SBRegisterId = ' . $sbId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "WPM_SBQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.SBRegisterId' => $sbId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_ServiceRecoveryType'))
                    ->columns(array('RecoveryTypeId', 'RecoveryTypeName',
                        'AccountId' => new Expression("isnull(b.AccountId,0)"),
                        'Amount' => new Expression("isnull(b.Amount,0.000)"),
                        'Sign' => new Expression("isnull(b.Sign,'')"),
                    ))
                    ->join(array("b" => "WPM_sbrecoverytrans"), new Expression("a.RecoveryTypeId=b.RecoveryTypeId and b.SBRegisterId =$sbId"), array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'FA_AccountMaster'), 'b.AccountId = c.AccountId', array('AccountName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getHireBillAction(){
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
                $postParams = $request->getPost();
                $hbId = $postParams['HBId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_HBTypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.HBRegisterId = ' . $hbId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hireBillTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_HBRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_HORegister'), 'a.HORegisterId = d.HORegisterId', array('HONo', 'HireTypeId'))
                    ->join(array('e' => 'WPM_HireTypeMaster'), 'e.HireTypeId = d.HireTypeId', array('HireTypeName'))
                    ->where('a.HBRegisterId = ' . $hbId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "WPM_HbQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.HBRegisterId' => $hbId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_ServiceRecoveryType'))
                    ->columns(array('RecoveryTypeId', 'RecoveryTypeName',
                        'AccountId' => new Expression("isnull(b.AccountId,0)"),
                        'Amount' => new Expression("isnull(b.Amount,0.000)"),
                        'Sign' => new Expression("isnull(b.Sign,'')"),
                    ))
                    ->join(array("b" => "WPM_hbrecoverytrans"), new Expression("a.RecoveryTypeId=b.RecoveryTypeId and b.HBRegisterId =$hbId"), array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'FA_AccountMaster'), 'b.AccountId = c.AccountId', array('AccountName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getRetentionReleaseAction(){
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
                $postParams = $request->getPost();
                $rrId = $postParams['RRId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_RetentionReleaseRegister'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('b' => 'FA_AccountMaster'), 'a.AdvanceAccountId = b.AccountId', array('AdvanceAccountName'=>'AccountName'))
                    ->join(array('f' => 'FA_AccountMaster'), 'a.PenaltyAccountId = f.AccountId', array('PenaltyAccountName' => 'AccountName'))
                    ->join(array('g' => 'FA_AccountMaster'), 'a.ReleaseAccountId = g.AccountId', array('ReleaseAccountName' => 'AccountName'))
                    ->join(array('h' => 'FA_AccountMaster'), 'a.RoundingAccountId = h.AccountId', array('RoundingAccountName' => 'AccountName'))
                    ->join(array('i' => 'FA_AccountMaster'), 'a.WithHeldAccountId = i.AccountId', array('WithHeldAccountName' => 'AccountName'))
                    ->where('a.RRRegisterId = ' . $rrId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->retentionTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $vendorId=$this->_view->retentionTrans['VendorId'];
                $costCentreId=$this->_view->retentionTrans['CostCentreId'];
                $OrderId=$this->_view->retentionTrans['OrderId'];
                $OrderType=$this->_view->retentionTrans['OrderType'];

                if($OrderType == 'W') {
                    $select1 = $sql->select();
                    $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                        ->columns(array(
                            'BillRegisterId' => new Expression("a.BillRegisterId"),
                            'EDate' => new Expression("Convert(varchar(10),b.EDate,105)"),
                            'RefNo' => new Expression("b.VNo"),
                            'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                            'ReleaseAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'Balance' => new Expression("Cast(0 as Decimal(18,3))"),
                            'CurAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'PrevAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'Sel' => new Expression("Convert(bit,0,1)"),
                            'OB' => new Expression("Convert(bit,0,1)"),
                        ))
                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select1::JOIN_INNER)
                        ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                        ->where(array("C.Sign='-' and A.Sign='-' and B.Approve='Y' and B.BillCertify=0 and B.CostCentreId = $costCentreId
                    and B.VendorId = $vendorId and B.WORegisterId=$OrderId and C.FormatTypeId=10 Group by A.BillRegisterId,B.Edate,B.VNo"));
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->releaseDetailTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select1 = $sql->select();
                    $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                        ->columns(array(
                            'BillRegisterId' => new Expression("a.BillRegisterId"),
                            'EDate' => new Expression("Convert(varchar(10),b.EDate,105)"),
                            'RefNo' => new Expression("b.VNo"),
                            'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                            'ReleaseAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'Balance' => new Expression("Cast(0 as Decimal(18,3))"),
                            'CurAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'PrevAmount' => new Expression("Cast(0 as Decimal(18,3))"),
                            'Sel' => new Expression("Convert(bit,0,1)"),
                            'OB' => new Expression("Convert(bit,0,1)"),
                        ))
                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select1::JOIN_INNER)
                        ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                        ->where(array("C.Sign='-' and A.Sign='-' and B.Approve='Y' and B.BillCertify=0 and B.CostCentreId = $costCentreId
                    and B.VendorId = $vendorId and B.WORegisterId=$OrderId and C.FormatTypeId=23 Group by A.BillRegisterId,B.Edate,B.VNo"));
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $this->_view->withHeldDetailTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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

    public function labourregisterviewAction(){
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
                $postParams = $request->getPost();
                $labourregId = $postParams['LabourregId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId',array('CostCentreName'),$select::JOIN_LEFT)
                    ->where('a.LabourRegisterId = ' . $labourregId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $labRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->labRegister = $labRegister;
                if (!empty($labRegister)) {
                    $iCostCentreId = $labRegister['CostCentreId'];
                    $sCostCentreName = $labRegister['CostCentreName'];
                }

                //Labour Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourTrans'))
                    ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName'=>new Expression("Case When                          a.VendorId !=0 then h.VendorName else b.LabourGroupName  end")), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'a.LabourTypeId = c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId = d.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_StateMaster'), 'd.StateId = e.StateId', array('StateName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'WF_CountryMaster'), 'd.CountryId = f.CountryId', array('CountryName'), $select::JOIN_LEFT)
                    ->join(array('h' => 'Vendor_Master'), 'a.VendorId = h.VendorId', array(), $select::JOIN_LEFT)
                    ->where(array('a.LabourRegisterId' =>$labourregId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // labour documents

                $subQuery = $sql->select();
                $subQuery->from("WPM_LabourTrans")
                    ->columns(array("LabourTransId"));
                $subQuery->where(array('LabourRegisterId' => $labourregId));

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourDocumentTrans'))
                    ->join(array('b' => 'WPM_LabourDocumentType'), 'a.DocumentType = b.DocumentId',array('Doc'=>new Expression("b.DocumentType"),'DocumentId'))
                    ->where->expression('LabourTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->documents = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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

    public function labourmasterviewAction(){
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

                $postParams = $request->getPost();
                $labourId = $postParams['LabourId'];
                $result =  "";

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourMaster'))
                    ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName'=>new Expression("Case When                          a.VendorId !=0 then h.VendorName else b.LabourGroupName  end")), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'a.LabourTypeId = c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId = d.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_StateMaster'), 'd.StateId = e.StateId', array('StateName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'WF_CountryMaster'), 'd.CountryId = f.CountryId', array('CountryName'), $select::JOIN_LEFT)
                    ->join(array('h' => 'Vendor_Master'), 'a.VendorId = h.VendorId', array(), $select::JOIN_LEFT)
                    ->where(array('a.LabourId' =>$labourId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourMaster'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId',array('CostCentreName'),$select::JOIN_LEFT)
                    ->where(array('a.LabourId' =>$labourId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $labMAster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if (!empty($labMAster)) {
                    $iCostCentreId = $labMAster['CostCentreId'];
                    $sCostCentreName = $labMAster['CostCentreName'];
                }


                if (!empty($labRegister)) {
                    $iCostCentreId = $labRegister['CostCentreId'];
                    $sCostCentreName = $labRegister['CostCentreName'];
                }

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourMasterDocumentTrans'))
                    ->join(array('b' => 'WPM_LabourDocumentType'), 'a.DocumentType = b.DocumentId',array('Doc'=>new Expression("b.DocumentType"),'DocumentId'))
                    ->where(array('a.LabourId' =>$labourId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->documents = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getSecurityDepositAction(){
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
                $postParams = $request->getPost();
                $rrId = $postParams['SDId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SecurityDepositRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'Proj_PaymentModeMaster'), 'a.PayModeId = d.TransId', array('PaymentMode'))
                    ->where('a.SDRegisterId = ' . $rrId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sdRegisteraDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getServiceDoneAction(){
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
                $postParams = $request->getPost();
                $sdId = $postParams['SDId'];
//
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SDServiceTrans'))
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('ServiceName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.SDRegisterId = ' . $sdId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sdServiceTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getRateApprovalAction(){
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
                $postParams = $request->getPost();
                $lraId = $postParams['lraId'];
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LRATypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('ResourceName', 'AEstRate' => 'Rate'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId = d.ResourceId', array('EstRate' => 'Rate'), $select::JOIN_LEFT)
                    ->where(array('a.LRARegisterId' => $lraId, 'd.RateType' => 'L'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lraTypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WPM_LRARegister')
                    ->columns(array('LRARegisterId'))
                    ->where(array('LRARegisterId' => $lraId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->Lrarow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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


	public function contractorViewAction(){
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
                //Labour Strength Vendor Trans
                $postParams = $request->getPost();
                $labourId = $postParams['LSRegisterId'];
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LSVendorTrans'))
                    ->join(array('b' => 'Vendor_Master'), 'a.VendorId = b.VendorId', array('VendorName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WPM_LabourGroupMaster'), 'a.GroupId = c.LabourGroupId', array('LabourGroupName'=>new Expression("LabourGroupName +'(Internal)'")), $select::JOIN_LEFT)
                    ->where('a.LSRegisterId = ' . $labourId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lsVendorTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $subQuery = $sql->select();
                $subQuery->from('WPM_LSVendorTrans')
                    ->columns(array('LSVendorTransId'))
                    ->where(array('LSRegisterId' => $labourId));

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LSWBSTrans'))
                    ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId = b.WBSId', array('WBSName'), $select::JOIN_LEFT)
                    ->where->expression('a.LSVendorTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lsWbsTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LSTypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->where->expression('a.LSVendorTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lsTypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Labour Strength Labour Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LSLabourTrans'))
                    ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array('LabourName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId = c.WBSId', array('WBSName'), $select::JOIN_LEFT)
                    ->where('a.LSRegisterId = ' . $labourId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lsLabourTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourStrengthRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->where('a.LSRegisterId = ' . $labourId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lsRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CostCentreId = $this->_view->lsRegister['CostCentreId'];

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getWorkCompletionAction(){
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
                $postParams = $request->getPost();
                $WCEId = $postParams['WCEId'];

                $select = $sql->select();
                $select->from( array( 'a' => 'WPM_WCERegister' ) )
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                    ->columns( array("WCERegisterId", "WCENo", "WCEDate" => new Expression("FORMAT(a.WCEDate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                    , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")
                    , "RefNo", "CCWCENo", "CompWCENo", "WOType", "Amount", 'Narration') )
                    ->where( "a.DeleteFlag=0 AND a.WCERegisterId=$WCEId" );
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->wceregister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                //print_r($this->_view->wceregister);exit;
                $WorkType = $this->_view->wceregister['WOType'];
                $this->_view->WorkType = $WorkType;
                if($WorkType == 'iow') {
                    // get iow lists
                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WCETrans"))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.SerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                        //->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('DescId' => 'IOWId','WCETransId'))
                        ->where('a.WCERegisterId='.$WCEId)
                        ->group(new Expression('a.IowId,b.SerialNo,b.Specification,c.UnitName,c.UnitId,a.WCETransId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select();
                    $subQuery->from("WPM_WCETrans")
                        ->columns(array('WCETransId'))
                        ->where('WCERegisterId ='.$WCEId);

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WCEIOWTrans"))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('SerialNo','Specification'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=a.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                        ->columns(array('WCEIOWTransId','WCETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                        ->where->expression('a.WCETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
//
//                $select = $sql->select();
//                $select->from(array('a' => 'WPM_SDServiceTrans'))
//                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('ServiceName'), $select::JOIN_LEFT)
//                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
//                    ->where('a.SDRegisterId = ' . $sdId);
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->sdServiceTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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

    public function getLabourTransferAction(){
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
                //Labour Strength Vendor Trans
                $postParams = $request->getPost();
                $transferId = $postParams['LabourTransferId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourTransferRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.FCostCentreIId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_OperationalCostCentre'), 'a.TCostCentreId = c.CostCentreId', array('ToCostCentreName'=>new Expression("c.CostCentreName")), $select::JOIN_LEFT)
                    ->where('a.LabourTransferId = ' . $transferId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->transferregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


               /* $select = $sql->select();
                $select->from(array('a' => 'WPM_labourTransferTrans'))
                    ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                    ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId')))
                    ->where('a.LabourTransferId = ' . $transferId);
                $statement = $sql->getSqlStringForSqlObject($select);*/

                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourTransferTrans'))
                    ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'b.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'WPM_LabourGroupMaster'), 'b.LabourGroupId = d.LabourGroupId', array(), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_OperationalCostCentre'), 'b.CostCentreId = e.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId'),'Name'=>new Expression("case when b.VendorId <>0 then  c.VendorName else d.LabourGroupName + '(Internal)' end ")))
                    ->where('a.LabourTransferId = ' . $transferId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->updateprelablist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //finding labours
                $select = $sql->select();
                $select->from('WPM_labourTransferTrans')
                    ->columns(array('LabourId'))
                    ->where('LabourTransferId='.$transferId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labourres = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;

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
    public function getLabourDeactivateAction(){
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
                //Labour Strength Vendor Trans
                $postParams = $request->getPost();
                $deactivateId = $postParams['LabourDeactivateId'];

                if($deactivateId !=0)
                {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_labourDeactivateRegister'))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->where('a.LabourDeactivateId = ' . $deactivateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->transferregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_labourDeactivateTrans'))
                        ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'b.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_LabourGroupMaster'), 'b.LabourGroupId = d.LabourGroupId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_OperationalCostCentre'), 'b.CostCentreId = e.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId'),'Name'=>new Expression("case when b.VendorId <>0 then  c.VendorName else d.LabourGroupName + '(Internal)' end ")))
                        ->where('a.LabourDeactivateId = ' . $deactivateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->updateprelablist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //finding labours
                    $select = $sql->select();
                    $select->from('WPM_labourDeactivateTrans')
                        ->columns(array('LabourId'))
                        ->where('LabourDeactivateId='.$deactivateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->labourres = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }



                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;

            }
        }
    }
    public function getLabourActivateAction(){
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
                //Labour Strength Vendor Trans
                $postParams = $request->getPost();
                $activateId = $postParams['LabourActivateId'];

                if($activateId !=0)
                {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_labourActivateRegister'))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->where('a.LabourActivateId = ' . $activateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->transferregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_labourActivateTrans'))
                        ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'b.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_LabourGroupMaster'), 'b.LabourGroupId = d.LabourGroupId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_OperationalCostCentre'), 'b.CostCentreId = e.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId'),'Name'=>new Expression("case when b.VendorId <>0 then  c.VendorName else d.LabourGroupName + '(Internal)' end ")))
                        ->where('a.LabourActivateId = ' . $activateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->updateprelablist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //finding labours
                    $select = $sql->select();
                    $select->from('WPM_labourActivateTrans')
                        ->columns(array('LabourId'))
                        ->where('LabourActivateId='.$activateId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->labourres = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

               /* $aVNo = CommonHelper::getVoucherNo(417, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->TrNo = "";
                else
                    $this->_view->TrNo = $aVNo["voucherNo"];*/


                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;

            }
        }
    }
    public function getWorkOrderAction(){
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
                $postParams = $request->getPost();
                $woId = $postParams['WOId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_WORegister'))
                    ->columns(array('WOType'))
                    ->where('a.WORegisterId = ' . $woId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $woType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($woType['WOType'] == 'iow'){
                    $table='WPM_WOWBSTrans';
                    $machId='a.IOWId = b.ResourceId';
                }else if($woType['WOType'] == 'activity'){
                    $table='WPM_WOIOWTrans';
                    $machId='a.ResourceId = b.ResourceId';
                }
                $this->_view->WOType=$woType['WOType'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_WOTrans'))
                    ->join(array('b' => 'Proj_Resource'), $machId, array('data' => 'ResourceId', 'ResourceName', 'UnitId' => 'UnitId'), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.WORegisterId = ' . $woId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->woTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if($woType['WOType'] == 'activity') {
                    $subQuery = $sql->select();
                    $subQuery->from("WPM_WOTrans")
                        ->columns(array('WOTransId'))
                        ->where('WORegisterId =' . $woId);

                    $resQuery = $sql->select();
                    $resQuery->from("WPM_WOTrans")
                        ->columns(array('ResourceId'))
                        ->where('WORegisterId =' . $woId);

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WOIOWTrans"))
                        ->join(array('f' => 'Proj_ProjectDetails'), 'a.IOWId=f.ProjectIOWId', array('eQty' => 'Qty', 'ResourceId'), $select::JOIN_LEFT)
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectIOW'), 'a.IOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array('cQty' => 'Qty'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                        ->columns(array('WOIOWTransId', 'WOTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'Rate', 'Amount'))
                        ->group(new Expression('a.WOIOWTransId,a.WOTransId,a.IOWId,a.Qty,a.Rate,a.Amount,f.Qty,b.RefSerialNo,b.Specification,e.Qty,c.Qty,d.WBSId,d.WBSName,d.ParentText,f.ResourceId'))
                        ->where->expression('a.WOTransId IN ?', array($subQuery))
                        ->where->expression('f.ResourceId IN ?', array($resQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }else if($woType['WOType'] == 'iow') {
                    $subQuery = $sql->select();
                    $subQuery->from("WPM_WOTrans")
                        ->columns(array('WOTransId'))
                        ->where('WORegisterId =' . $woId);

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WOWBSTrans"))
                        //->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                        //->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_WBSTrans'), 'b.WBSId=c.WBSId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                        ->columns(array('WOIOWTransId' => 'WOWBSTransId', 'WOTransId', 'Qty', 'Rate', 'Amount'))
                        ->group(new Expression('a.WOWBSTransId,a.WOTransId,a.Qty,a.Rate,a.Amount,b.WBSId,b.WBSName,b.ParentText,c.Qty'))
                        ->where->expression('a.WOTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $select = $sql->select();
                $select->from(array('a' => 'WPM_HORegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName','ProjectId'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'))
                    ->where('a.HORegisterId = ' . $woId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hoRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "Proj_WOQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.WORegisterId' => $woId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
    public function getWorkProgressAction(){
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
                $postParams = $request->getPost();
                $DPEId = $postParams['DPEId'];
                $this->_view->DPEId=$DPEId;
                $select = $sql->select();
                $select->from( array( 'a' => 'WPM_DPERegister' ) )
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorId', 'VendorName'), $select::JOIN_LEFT)
                    ->columns( array("DPERegisterId", "DPENo", "DPEDate" => new Expression("FORMAT(a.DPEDate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                    , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")
                    , "RefNo", "CCDPENo", "CompDPENo", "WOType", "Amount", 'Narration','WORegisterId', 'Approve') )
                    ->where( "a.DeleteFlag=0 AND a.DPERegisterId=$DPEId" );
                $statement = $sql->getSqlStringForSqlObject( $select );
                $dperegister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $this->_view->dperegister = $dperegister;

                $WorkType = $this->_view->dperegister['WOType'];
                $this->_view->WorkType = $WorkType;

                $select = $sql->select();
                $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                    ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'WBSReqWPM', 'CompanyId' ) )
                    ->where( "Deactivate=0 AND CostCentreId=".$this->_view->dperegister['CostCentreId'] );
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $projectId = $this->_view->costcenter['ProjectId'];
                $this->_view->projectId = $projectId;
                if($WorkType == 'activity') {
                    // get resource lists
                    $select = $sql->select();
                    $select->from(array("a" => "WPM_DPETrans"))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Desc' => new Expression("b.Code+ ' ' +b.ResourceName"), 'Rate'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId=d.ResourceId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('DPETransId','DescId' => 'ResourceId','Qty','Rate', 'Amount'))
                        ->where('a.DPERegisterId='.$DPEId)
                        ->where("d.ProjectId=".$projectId)
                        ->group(new Expression('a.DPETransId,a.ResourceId,a.Qty,a.Rate,a.Amount,b.Code,b.ResourceName,b.Rate,d.Qty,c.UnitName,c.UnitId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select();
                    $subQuery->from("WPM_DPETrans")
                        ->columns(array('DPETransId'))
                        ->where('DPERegisterId ='.$DPEId);

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_DPEIOWTrans"))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_DPEMeasurement' ), 'a.DPEIOWTransId=e.DPEIOWTransId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                        ->columns(array('DPEIOWTransId','DPETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                        ->where->expression('a.DPETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_ProjectResource"))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('value' => new Expression("Code+ ' ' +ResourceName + ' ' + Case When a.RateType='M' Then '- Manual' When a.RateType='A' Then '- Machinery' Else '' End"), 'data' => 'ResourceId'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                        ->columns(array('Qty', 'Rate', 'RateType'))
                        ->where("a.ProjectId=".$projectId)
                        ->where("b.TypeId=4")
                        ->order('a.ResourceId');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($WorkType == 'iow') {
                    // get iow lists

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_DPETrans"))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.RefSerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('DPETransId', 'DescId' => 'IOWId', 'Qty'))
                        ->where('a.DPERegisterId='.$DPEId)
                        ->where("b.ProjectId=".$projectId);
                    //->group(new Expression('a.IowId,b.RefSerialNo,b.Specification,c.UnitName,c.UnitId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select();
                    $subQuery->from("WPM_DPETrans")
                        ->columns(array('DPETransId'))
                        ->where('DPERegisterId ='.$DPEId);

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_DPEWBSTrans"))
                        ->join(array('b' => 'WPM_DPETrans'), 'a.DPETransId=b.DPETransId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_WBSTrans'), 'd.WBSId=c.WBSId AND b.IOWId=c.ProjectIOWId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_DPEMeasurement' ), 'a.DPEWBSTransId=e.DPEWBSTransId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                        ->columns(array('DPEIOWTransId' => 'DPEWBSTransId', 'DPETransId', 'Qty'))
                        ->group(new Expression('a.DPEWBSTransId,a.DPETransId,a.Qty,d.WBSId,d.WBSName,d.ParentText,c.Qty,e.Measurement,e.CellName,e.SelectedColumns'))
                        ->where->expression('a.DPETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_ProjectIOWMaster"))
                        ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate', 'Qty'), $select::JOIN_LEFT)
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_WBSTrans'), 'd.ProjectIOWId=a.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->columns(array('value' => new Expression("a.RefSerialNo+ ' ' +a.Specification"), 'data' => 'ProjectIOWId'))
                        ->where("a.ProjectId=".$projectId)
                        ->where('a.UnitId <> 0')
                        ->order('a.ProjectIOWId')
                        ->group(new Expression('a.ProjectIOWId,a.RefSerialNo,a.Specification,c.Rate,c.Qty,b.UnitName,b.UnitId'));
                    if($this->_view->wbsReq != '' && $this->_view->wbsReq == 1) {
                        $select->where('d.WBSId != 0');
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
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
}