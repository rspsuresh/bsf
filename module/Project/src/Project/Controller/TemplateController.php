<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Project\Controller;

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
use Application\View\Helper\Qualifier;

class TemplateController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function getrfciowAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);


                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate','Approve','Narration'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCIOWTrans'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_RFCIOWRate'), 'a.RFCTransId=c.RFCTransId', array('WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=d.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness'), $select:: JOIN_LEFT)
                    ->columns(array('*'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->rfctrans = $rfcTrans;

                $arrqual = array();
                foreach ($rfcTrans as $trans) {
                    $rfctransid = $trans['RFCTransId'];

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_RFCIOWQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt'));
                    $select->where(array('a.MixType'=>'S','a.RFCTransId'=>$rfctransid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList);
                    $arrqual[$rfctransid] = $sHtml;
                }

                $arrRqual = array();
                foreach ($rfcTrans as $trans) {
                    $rfctransid = $trans['RFCTransId'];

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_RFCIOWQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt','KKCessAmt','SBCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType'=>'R','a.RFCTransId'=>$rfctransid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList,'R');
                    $arrRqual[$rfctransid] = $sHtml;
                }

                $this->_view->arrqual = $arrqual;
                $this->_view->arrRqual = $arrRqual;

                $subQuery = $sql->select();
                $subQuery->from("Proj_RFCIOWTrans")
                    ->columns(array("RFCTransId"));
                $subQuery->where(array('RFCRegisterId' => $iRFCId));

//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_RFCRateAnalysis'))
//                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
//                    ->join(array('c' => 'Proj_IOWMaster'), 'a.SubIOWId=c.IOWId', array('SerialNo', 'Specification'), $select:: JOIN_LEFT)
//                    ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'=>new Expression('c.UnitName')), $select:: JOIN_LEFT)
//                    ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('IOWUnitName'=>new Expression('d.UnitName')), $select:: JOIN_LEFT)
//                    ->columns(array('RFCTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'Description','Wastage','WastageQty','WastageAmount','Weightage','SortId','RateType'))
//                    ->where->expression('RFCTransId IN ?', array($subQuery));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRateAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_IOWMaster'), 'a.SubIOWId=c.IOWId', array('SerialNo', 'Specification'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'=>new Expression('d.UnitName')), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('IOWUnitName'=>new Expression('e.UnitName')), $select:: JOIN_LEFT)
                    ->columns(array('RFCTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType', 'TransType', 'Description','Wastage','WastageQty','WastageAmount','Weightage','SortId','RateType'))
                    ->where->expression('RFCTransId IN ?', array($subQuery));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcprojectiowAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);


                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRegister'))
                    ->join(array('b' => 'Proj_ProjectMaster'),'a.ProjectId=b.ProjectId', array('ProjectId', 'ProjectName'), $select::JOIN_LEFT)
                    ->columns(array('RefNo', 'RefDate', 'ProjectType', 'ProjectTypeName' => new Expression("Case a.ProjectType When  'B' then 'Budget' When 'P' then 'Plan' end")), array('ProjectId', 'ProjectName'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a'=>'Proj_RFCIOWTrans'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectWorkGroup'), 'a.PWorkGroupId=c.PWorkGroupId', array('ProjectWorkGroupName' => new Expression("c.SerialNo + ' ' +c.WorkGroupName")), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=e.WorkGroupId', array('WorkGroupName' => new Expression("c.SerialNo + ' ' +c.WorkGroupName")), $select:: JOIN_LEFT)
                    ->join(array('f' => 'Proj_RFCIOWRate'), 'a.RFCTransId=f.RFCTransId', array('WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate'), $select:: JOIN_LEFT)
                    ->join(array( 'd' => 'Proj_RFCProjectIOWMeasurement' ), 'a.RFCTransId=d.RFCTransId', array( 'Measurement','CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                    ->join(array('h' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=h.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness','WorkType'), $select:: JOIN_LEFT)
                    ->join(array('g' => 'Proj_IOWMaster'), 'a.IOWId=g.IOWId', array('LSerialNo'=>new Expression("g.SerialNo")), $select:: JOIN_LEFT)
                    ->columns(array('*'),array('UnitName'), array('ProjectWorkGroupName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;
                $this->_view->rfctrans =$rfcTrans;
                $arrqual = array();
                foreach ($rfcTrans as $trans) {
                    $rfctransid = $trans['RFCTransId'];

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_RFCIOWQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt','KKCessAmt','SBCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType'=>'S','a.RFCTransId'=>$rfctransid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList);
                    $arrqual[$rfctransid] = $sHtml;
                }

                $arrRqual = array();
                foreach ($rfcTrans as $trans) {
                    $rfctransid = $trans['RFCTransId'];
                    $select = $sql->select();
                    $select->from(array("a" => "Proj_RFCIOWQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt','KKCessAmt','SBCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType'=>'R','a.RFCTransId'=>$rfctransid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList,'R');
                    $arrRqual[$rfctransid] = $sHtml;
                }

                $this->_view->arrqual = $arrqual;
                $this->_view->arrRqual = $arrRqual;

                $subQuery = $sql->select();
                $subQuery->from("Proj_RFCIOWTrans")
                    ->columns(array("RFCTransId"));
                $subQuery->where(array('RFCRegisterId' => $iRFCId));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRateAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('RFCTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId','ResourceId', 'Qty', 'Rate', 'Amount','Formula', 'MixType', 'TransType','Wastage','WastageQty','WastageAmount','Weightage','SortId'))
                    ->where->expression('RFCTransId IN ?', array($subQuery));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCIOWWBSTrans'))
                    ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSName'=> new Expression("ParentText +'->'+WBSName")), $select:: JOIN_LEFT)
                    ->columns(array('RFCTransId', 'WBSId', 'Qty','Measurement', 'CellName', 'SelectedColumns'))
                    ->where->expression('a.RFCTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcwbstrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcresgroupAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration','Approve'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCResourceGroupTrans'))
                    ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ResourceGroup'), 'a.ParentId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
                    ->columns(array('ResourceGroupId', 'TypeId', 'ParentId','ParentName', 'Code', 'ResourceGroupName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $codegenType = 0;
                $select = $sql->select();
                $select->from('Proj_RGCodeSetup')
                    ->columns(array('GenType'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $codesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($codesetup)) $codegenType = $codesetup['GenType'];
                $this->_view->codegenType = $codegenType;

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcresgroupdeleteAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCResGroupDeleteTrans'))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array('Code', 'ResourceGroupName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ResourceType'), 'b.TypeId=c.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->columns(array('ResourceGroupId'), array('Code', 'ResourceGroupName'), array('TypeName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }
    public function getrfcworkgroupdeleteAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCWorkGroupDeleteTrans'))
                    ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('SerialNo', 'WorkGroupName'), $select:: JOIN_LEFT)
                    ->columns(array('WorkGroupId'), array('SerialNo', 'WorkGroupName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcresourcedeleteAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration','Approve'))
                    ->where(array("RFCRegisterId" => $iRFCId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCResourceDeleteTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_ResourceType'), 'b.TypeId=d.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->columns(array('ResourceId'), array('Code', 'ResourceName'), array('ResourceGroupName'), array('TypeName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfciowdeleteAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCIOWDeleteTrans'))
                    ->join(array('b' => 'Proj_IOWMaster'), 'a.IOWId=b.IOWId', array('SerialNo', 'Specification'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_WorkGroupMaster'), 'b.WorkGroupId=c.WorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                    ->columns(array('IOWId'), array('SerialNo', 'Specification'), array('WorkGroupName'))
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcprojectiowdeleteAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCProjectIOWDeleteTrans'))
                    ->join(array('g' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=g.ProjectIOWId', array('ProjectIOWId','ProjectId','RefSerialNo','SerialNo', 'ShortSpec', 'Specification'), $select:: JOIN_LEFT)
                    ->join(array('b' => 'Proj_UOM'), 'g.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectIOWTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcworktypeAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration','Approve'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCWorkTypeTrans'))
                    ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('WorkType'), $select:: JOIN_LEFT)
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCWorkTypeAnalysis'))
                    ->join(array('b' => 'Proj_RFCWorkTypeTrans'), 'a.RFCTransId=b.RFCTransId', array('*'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'c.ResourceId=a.ResourceId', array('*'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'd.UnitId=c.UnitId', array('*'), $select::JOIN_LEFT)
                    ->where(array("b.RFCRegisterId" => $iRFCId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->worktypeanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcworkgroupAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration','Approve'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCWorkGroupTrans'))
                    ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('WorkType', 'WorkTypeId'), $select:: JOIN_LEFT)
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCWorkGroupAnalysis'))
                    ->join(array('b' => 'Proj_RFCWorkGroupTrans'), 'a.RFCTransId=b.RFCTransId', array('*'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'c.ResourceId=a.ResourceId', array('*'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'd.UnitId=c.UnitId', array('*'), $select::JOIN_LEFT)
                    ->where(array("b.RFCRegisterId" => $iRFCId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->worktypeanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                $codegenType = 0;
                $select = $sql->select();
                $select->from('Proj_WorkGroupCodeSetup')
                    ->columns(array('GenType'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $codesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($codesetup)) $codegenType = $codesetup['GenType'];
                $this->_view->codegenType = $codegenType;

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcresourceAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate', 'Narration','Approve'))
                    ->where(array("RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCResourceTrans'))
                    ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitName','UnitId'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=d.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'a.WorkUnitId=e.UnitId', array('WorkUnitName' => 'UnitName'), $select:: JOIN_LEFT)
                    ->where(array("a.RFCRegisterId" => $iRFCId));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subQuery = $sql->select();
                $subQuery->from("Proj_RFCResourceTrans")
                    ->columns(array("RFCTransId"));
                $subQuery->where(array('RFCRegisterId' => $iRFCId));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCActivityTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceName', 'Code'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select:: JOIN_LEFT)
                    ->columns(array('RFCTransId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'ActivityType'), array('ResourceName', 'Code'), array('UnitName', 'UnitId'))
                    ->where->expression('RFCTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcactivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_RFCSteelTrans')
                    ->columns(array('RFCTransId', 'SteelDescription', 'SteelDia', 'Factor', 'Wastage'))
                    ->where->expression('RFCTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);

                $this->_view->rfcsteel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $codegenType = 0;
                $select = $sql->select();
                $select->from('Proj_ResourceCodeSetup')
                    ->columns(array('GenType'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $codesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($codesetup)) $codegenType = $codesetup['GenType'];
                $this->_view->codegenType = $codegenType;

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }
    public function getrfcothercostAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_OHTypeMaster')
                    ->columns(array('data' => 'OHTypeId', 'value' =>'OHTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $ohtypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->ohtypes = $ohtypes;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRegister'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'a.ProjectId=b.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                    ->columns(array('ProjectId', 'ProjectType', 'RefDate' => new Expression( "FORMAT(a.RefDate, 'dd-MM-yyyy')" ), 'RefNo','Approve'))
                    ->where(array('a.RFCRegisterId' => $rfcid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $projectId = $rfcRegister['ProjectId'];
                $this->_view->rfcregister = $rfcRegister;
                $this->_view->projecttype = $rfcRegister['ProjectType'];
                if ($rfcRegister['ProjectType'] == 'B')
                    $this->_view->projecttypename = 'Budget';
                else if ($rfcRegister['ProjectType'] == 'P')
                    $this->_view->projecttypename = 'Plan';

                $arr_rfcohtrans = array();
//                foreach($ohtypes as $type) {
//                    $OHTypeId = $type['data'];
                // get rfc oh trans
                $select = $sql->select();
                $select->from( array( 'a' => 'Proj_RFCOHTrans' ) )
                    ->join( array( 'b' => 'Proj_OHMaster' ), 'a.OHId=b.OHId', array( 'OHName', 'OHTypeId' ), $select::JOIN_LEFT )
                    ->join( array( 'c' => 'Proj_OHTypeMaster' ), 'b.OHTypeId=c.OHTypeId', array( 'OHTypeName' ), $select::JOIN_LEFT )
                    ->where('a.RFCRegisterId='.$rfcid);
                $statement = $sql->getSqlStringForSqlObject( $select );
                $rfcohtrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


                // get rfc type trans
                foreach ( $rfcohtrans as &$trans ) {

                    $OHTypeId = $trans['OHTypeId'];

                    if($OHTypeId == '9' || $OHTypeId == '10') {
                        $arr_rfcohtrans = array_merge($arr_rfcohtrans, $rfcohtrans);
                        continue;
                    }


                    switch($OHTypeId) {
                        case '1':
                            //oh item trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHItemTrans' ) )
                                ->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'DescTypeId' => 'ProjectIOWId','Desc' =>new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCItemTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '2':
                            //oh material trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHMaterialTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId','Desc' =>'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCMaterialTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '3':
                            //oh labour trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHLabourTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array(  'DescTypeId' => 'ResourceId','Desc' =>'ResourceName'  ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCLabourTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '4':
                            //oh service trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHServiceTrans' ) )
                                ->join( array( 'b' => 'Proj_ServiceMaster' ), 'a.ServiceId=b.ServiceId', array( 'DescTypeId' => 'ServiceId', 'Desc' => 'ServiceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCServiceTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '5':
                            //oh machinery trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHMachineryTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.WorkUnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCMachineryTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            // material details trans
                            foreach($rfctypetrans as &$typedetailtrans) {
                                $select = $sql->select();
                                $select->from( array( 'a' => 'Proj_RFCOHMachineryDetails' ) )
                                    ->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'Name' => new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                    ->where( 'a.RFCMachineryTransId=' .$typedetailtrans[ 'RFCMachineryTransId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $rfcmdetailstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                $typedetailtrans['details'] = $rfcmdetailstrans;
                            }
                            break;
                        case '6':
                            //oh admin expense trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHAdminExpenseTrans' ) )
                                ->join( array( 'b' => 'Proj_AdminExpenseMaster' ), 'a.ExpenseId=b.ExpenseId', array( 'DescTypeId' => 'ExpenseId', 'Desc' => 'ExpenseName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCExpenseTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '7':
                            //oh salary trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHSalaryTrans' ) )
                                ->join( array( 'b' => 'WF_PositionMaster' ), 'a.PositionId=b.PositionId', array( 'DescTypeId' => 'PositionId', 'Desc' => 'PositionName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCSalaryTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '8':
                            //oh fuel trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_RFCOHFuelTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId', 'Desc' => 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_Resource' ), 'a.FResourceId=c.ResourceId', array( 'FuelId' => 'ResourceId', 'Fuel' => 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'd' => 'Proj_UOM' ), 'c.UnitId=d.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'RFCFuelTransId', '*'))
                                ->where( 'a.RFCTransId=' .$trans[ 'RFCTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                    }

                    if(count($rfctypetrans))
                        $trans['typeTrans'] = $rfctypetrans;
                }

                $arr_rfcohtrans = array_merge($arr_rfcohtrans, $rfcohtrans);
                //}
                $this->_view->arr_rfcohtrans = array_reverse($arr_rfcohtrans);

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfciowplanAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate','ProjectId'))
                    ->where(array("RFCRegisterId" => $iRFCId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->rfcregister =$rfcregister;
                $projId=0;
                if (!empty($rfcregister)) $projId =$this->bsf->isNullCheck($rfcregister['ProjectId'],'number');

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'))
                    ->where(array('ProjectId' => $projId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectIOWMaster'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'=>new Expression("isnull(b.UnitName,'')")), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('BudgetQty'=>new Expression("isnull(c.Qty,0)")), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectIOWPlan'), 'a.ProjectIOWId=d.ProjectIOWId', array('PrevPlanQty'=>new Expression("isnull(d.Qty,0)")), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_RFCIOWTrans'), new Expression("a.ProjectIOWId=e.ProjectIOWId and e.RFCRegisterId=$iRFCId"), array('CurPlanQty'=>new Expression("isnull(e.Qty,0)")), $select:: JOIN_LEFT)
                    ->columns(array('ProjectIOWId','RefSerialNo', 'Specification'))
                    ->where(array("a.ProjectId"=>$projId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WBSTrans'))
                    ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSName' => new Expression("B.ParentText+ '->' + B.WBSName")), $select:: JOIN_INNER)
                    ->join(array('c' => 'Proj_WBSTransPlan'), new Expression("a.WBSId=c.WBSId and a.ProjectIOWId=c.ProjectIOWId"), array('PrevPlanQty' => new Expression("isnull(c.Qty,0)")), $select:: JOIN_LEFT)
                    ->columns(array('ProjectIOWId','WBSId','BudgetQty'=>new Expression("a.Qty"),'CurPlanQty' => new Expression("'0'")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $wbstrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getrfcresourcerateAction(){
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
                $rfcid = $postParams['rfcid'];
                $sql = new Sql($dbAdapter);

                $iRFCId = $rfcid;

                $select = $sql->select();
                $select->from('Proj_RFCRegister')
                    ->columns(array('RefNo', 'RefDate','ProjectId'))
                    ->where(array("RFCRegisterId" => $iRFCId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->rfcregister =$rfcregister;
                $projId=0;
                if (!empty($rfcregister)) $projId =$this->bsf->isNullCheck($rfcregister['ProjectId'],'number');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectResource'))
                    ->join(array('b' => 'Proj_Resource'),'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId', 'UnitId'),$select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ResourceType'),'b.TypeId=c.TypeId', array('TypeName'),$select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'),'b.UnitId=d.UnitId', array('UnitName'),$select::JOIN_LEFT)
                    ->columns(array('TransId','ResourceId','Qty','IncludeFlag','Rate','Amount','CInc'=>new Expression("IncludeFlag"),'CRate'=>new Expression("'0'"),'CAmount'=>new Expression("'0'")))
                    ->where(array('a.ProjectId' => $projId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_WBSMaster')
                    ->columns(array('WBSId', 'WBSName', 'ParentId'))
                    ->where('ProjectId = ' . $projId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projWBS = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // get activity rate analysis
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectResource'))
                    ->join(array('b' => 'Proj_Resource'),'a.ResourceId=b.ResourceId', array('ResourceName','ResourceGroupId','Code', 'TypeId'
                    , 'UnitId','LeadDays','AnalysisMQty','AnalysisAQty','RateType', 'LRate','MRate','ARate'
                    , 'AnalysisMQty', 'AnalysisAQty'),$select::JOIN_LEFT)
                    ->columns(array('ResourceId', 'Rate'))
                    ->where(array('a.ProjectId' => $projId, 'b.TypeId' => '4'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $projectdetailsactivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($projectdetailsactivity as &$activity) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ResourceActivityTrans'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', 'ResourceName','Code'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId','UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('ResourceId', 'Qty', 'Rate', 'Amount', 'ActivityType'))
                        ->where(array("a.MResourceId" => '1007', 'ActivityType' => 'A'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $activity['rateAnalysis'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ResourceActivityTrans'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', 'ResourceName','Code'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId','UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('ResourceId', 'Qty', 'Rate', 'Amount', 'ActivityType'))
                        ->where(array("a.MResourceId" => '1007', 'ActivityType' => 'M'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $activity['rateAnalysisR'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->projectdetailsactivity = $projectdetailsactivity;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('data' => 'ResourceId', 'value' => new Expression("Code + ' ' +ResourceName"), 'Rate', 'TypeId'), array('UnitName'))
                    ->where("a.DeleteFlag='0' and a.TypeId !='4' and (a.TypeId !='2' or MaterialType='F')");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

	public function getprojectiowresourceAction(){
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
                $resid = $postParams['resourceid'];
                $projectid = $postParams['projectid'];
                $type = $postParams['type'];
                $unit = $postParams['unit'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectDetails'))
                    ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('RefSerialNo','Specification'),$select::JOIN_LEFT)
                    ->columns(array('IncludeFlag','UnitName'=>new Expression("'$unit'"),'Qty','Rate','Amount'))
                    ->where(array('a.ProjectId' => $projectid,'a.ResourceId'=>$resid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
	}
}