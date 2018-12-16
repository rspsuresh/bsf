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
use BuildsuperfastClass;
use PHPExcel;
use PHPExcel_IOFactory;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
class QualifierController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function qualifiermasterAction(){
        //$this->layout("layout/layout");

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Qualifier Master");
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_QualifierMaster'))
                ->join(array("b"=>"Proj_QualifierTypeMaster"), "a.QualifierTypeId=b.QualifierTypeId", array('QualifierTypeName'), $select::JOIN_INNER);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualifierlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_QualifierTypeMaster'))
                ->columns( array( 'data' => 'QualifierTypeId', 'value' => 'QualifierTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualifiertypelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;


            //begin trans try block example starts
//			$connection = $dbAdapter->getDriver()->getConnection();
//			$connection->beginTransaction();
//			try {
//				$connection->commit();
//			} catch(PDOException $e){
//				$connection->rollback();
//				print "Error!: " . $e->getMessage() . "</br>";
//			}
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function checkqualifierfoundAction(){
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
                try {
                    $projectId = $this->params()->fromPost('projectId');
                    $projectName = $this->params()->fromPost('projectName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($projectId != null || $projectId != 0){
                        $select->from( array( 'c' => 'Proj_QualifierMaster' ))
                            ->columns( array( 'QualifierId'))
                            ->where( "QualifierName='$projectName' and QualifierId<> '$projectId'");
                    } else{
                        $select->from( array( 'c' => 'Proj_QualifierMaster' ))
                            ->columns( array( 'QualifierId'))
                            ->where( "QualifierName='$projectName'");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function editqualifierAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();

                    $iQualifierId = $this->bsf->isNullCheck( $postData['qualifierId'], 'number' );
                    $sQualifierName = $this->bsf->isNullCheck( $postData['qualifierName'], 'string' );
                    $sQualifierTypeName = $this->bsf->isNullCheck( $postData['qualifierTypeName'], 'string' );
                    $iQualifierTypeId = $this->bsf->isNullCheck( $postData['qualifierTypeId'], 'number' );
                    $iRoundDecimal = $this->bsf->isNullCheck( $postData['roundDecimal'], 'number' );

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Proj_QualifierMaster')
                        ->set(array('QualifierName' => $sQualifierName, 'QualifierTypeId' => $iQualifierTypeId
                        , 'RoundDecimal' => $iRoundDecimal))
                        ->where(array('QualifierId' => $iQualifierId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_QualifierMaster'));
                    $select->columns(array("QualifierId","QualifierTypeId","QualifierName"
                    ,"QualifierTypeName"=>new Expression("b.QualifierTypeName"),"RoundDecimal"))
                        ->join(array("b"=>"Proj_QualifierTypeMaster"), "a.QualifierTypeId=b.QualifierTypeId", array(), $select::JOIN_INNER)
                        ->where(array('a.QualifierId' => $iQualifierId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                    //$status = 'Edit';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addqualifierAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $sQualifierName = $this->params()->fromPost('qualifierName');
                    $iQualifierTypeId= $this->params()->fromPost('qualifierTypeId');
                    $iRoundDecimal= $this->params()->fromPost('roundDecimal');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_QualifierMaster');
                    $insert->Values(array('QualifierName' => $sQualifierName,'QualifierTypeId' =>$iQualifierTypeId,'RoundDecimal'=>$iRoundDecimal));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $iQualifierId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $sRefNo = 'R' . $iQualifierId;

                    $update = $sql->update();
                    $update->table('Proj_QualifierMaster')
                        ->set(array('RefNo' => $sRefNo))
                        ->where(array('QualifierId' => $iQualifierId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_QualifierMaster'))
                        ->join(array("b"=>"Proj_QualifierTypeMaster"), "a.QualifierTypeId=b.QualifierTypeId", array('QualifierTypeName'), $select::JOIN_INNER)
                        ->where(array('a.QualifierId' => $iQualifierId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function qualifiersettingAction(){
        //$this->layout("layout/layout");

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
                //Write your Ajax post code here

                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $sQualType = $this->bsf->isNullCheck($this->params()->fromRoute('Type'), 'string');
                $data = 'N';
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $response = $this->getResponse();
                if ($RType == "qualifierdetails")
                {
                    $select->from(array('a' => 'Proj_QualifierTrans'))
                        ->join(array('b' => 'Proj_QualifierMaster'), 'a.QualifierId=b.QualifierId', array('RefNo','QualifierName'), $select::JOIN_INNER)
                        ->columns(array('QualTransId', 'YesNo', 'QualifierId', 'Expression', 'ExpPer','Sign','SortId'))
                        ->where("a.Qualtype='$sQualType'")
                        ->order('a.SortId');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $adata['qualTrans'] = $qualTrans;
                    $data = json_encode($adata);
                }

                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $sQualType = $this->bsf->isNullCheck($postData['qualType'], 'string');
                    $iQualRowId = $this->bsf->isNullCheck($postData['qualrowid'], 'number');
                    for ($i = 1; $i <$iQualRowId; $i++) {
                        $sdelflag = $this->bsf->isNullCheck($postData['delflag_' . $i], 'string');
                        $iQualTransId = $this->bsf->isNullCheck($postData['qualTranId_' . $i], 'number');
                        $check_value = isset($postData['incl_' . $i]) ? 1 : 0;
                        $iQualifierId = $this->bsf->isNullCheck($postData['qualifierId_' . $i], 'number');
                        $sExpression = $this->bsf->isNullCheck($postData['exp_' . $i], 'string');
                        $dExpPer = $this->bsf->isNullCheck($postData['expper_' . $i], 'number');
                        $sSign = $this->bsf->isNullCheck($postData['sign_' . $i], 'string');
                        $iSortId = $this->bsf->isNullCheck($postData['sortId_' . $i], 'number');

                        $sql = new Sql($dbAdapter);

                        if ($sdelflag !='Y') {

                            if ($iQualTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into('Proj_QualifierTrans');
                                $insert->Values(array('YesNo' => $check_value, 'QualifierId' => $iQualifierId, 'Expression' => $sExpression,
                                    'ExpPer' => $dExpPer, 'Sign' => $sSign, 'QualType' => $sQualType, 'SortId' => $iSortId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {

                                $update = $sql->update();
                                $update->table('Proj_QualifierTrans')
                                    ->set(array('YesNo' => $check_value, 'QualifierId' => $iQualifierId, 'Expression' => $sExpression,
                                        'ExpPer' => $dExpPer, 'Sign' => $sSign, 'QualType' => $sQualType, 'SortId' => $iSortId))
                                    ->where(array('QualTransId' => $iQualTransId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else {
                            if ($iQualTransId != 0) {
                                $delete = $sql->delete();
                                $delete->from('Proj_QualifierTrans')
                                    ->where(array('QualTransId' => $iQualTransId));
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    $connection->commit();

                    $this->redirect()->toRoute('project/qualifiersetting', array('controller' => 'qualifier', 'action' => 'qualifiersetting'));

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            //begin trans try block example starts
            //begin trans try block example ends

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_QualifierMaster'))
                ->columns(array("data" => 'QualifierId',"refNo" => 'RefNo', "value" => 'QualifierName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualifierlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function tdssettingAction(){
        //$this->layout("layout/layout");

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

            $iPeriodId=0;

            $iPeriodId=$this->bsf->isNullCheck($this->params()->fromRoute('periodid'),'number');


            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'FA_TDSType'))
                ->join(array("b"=>"FA_TDSSetting"), new Expression("a.TDSTypeId=b.TDSTypeId and b.PeriodId=$iPeriodId"), array('SectionId','TaxablePer','TaxPer','SurCharge','EDCess','HEDCess','NetTax'), $select::JOIN_LEFT)
                ->join(array("c"=>"FA_TDSSection"), "b.SectionId=c.SectionId", array('Section'), $select::JOIN_LEFT)
                ->columns(array('TDSTypeId','TDSType'))
                ->where("a.TDSTypeId !=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->tdstypelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'FA_TDSSection'))
                ->columns(array("data" => 'SectionId',"value" => 'Section'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->sectionlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'FA_QualPeriod'))
                ->columns(array('PeriodId','PeriodName','FDate' => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')") ,'TDate' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
                ->where("a.QualType ='T'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->periodlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            $this->_view->periodid = $iPeriodId;


            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function updatetdssettingAction(){
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

                $iPeriodId = $this->bsf->isNullCheck($this->params()->fromPost('PeriodId'), 'number');
                $iTDSTypeId = $this->bsf->isNullCheck($this->params()->fromPost('TDSTypeId'), 'number');
                $iSectionId = $this->bsf->isNullCheck($this->params()->fromPost('SectionId'), 'number');
                $dTaxable = $this->bsf->isNullCheck($this->params()->fromPost('Taxable'), 'number');
                $dTax = $this->bsf->isNullCheck($this->params()->fromPost('Tax'), 'number');
                $dCess = $this->bsf->isNullCheck($this->params()->fromPost('Cess'), 'number');
                $dEDCess = $this->bsf->isNullCheck($this->params()->fromPost('EDCess'), 'number');
                $dHEDCess = $this->bsf->isNullCheck($this->params()->fromPost('HEDCess'), 'number');
                $dNetTax = $this->bsf->isNullCheck($this->params()->fromPost('NetTax'), 'number');

                $data = 'N';

                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from(array('a' => 'FA_TDSSetting'))
                    ->columns(array("TDSTypeId"))
                    ->where("a.TDSTypeId = $iTDSTypeId and a.PeriodId = $iPeriodId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($results) $data = 'Y';

                $bupdate = 'N';

                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($data == "Y") {
                        $update = $sql->update();
                        $update->table('FA_TDSSetting')
                            ->set(array('TDSTypeId' => $iTDSTypeId, 'PeriodId' => $iPeriodId,'SectionId' => $iSectionId
                            , 'TaxablePer' => $dTaxable,'TaxPer' => $dTax,'SurCharge' => $dCess,'EDCess' => $dEDCess,'HEDCess' => $dHEDCess, 'NetTax' => $dNetTax))
                            ->where("TDSTypeId = $iTDSTypeId and PeriodId = $iPeriodId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_TDSSetting');
                        $insert->Values(array('TDSTypeId' => $iTDSTypeId, 'PeriodId' => $iPeriodId,'SectionId' => $iSectionId
                        , 'TaxablePer' => $dTaxable,'TaxPer' => $dTax,'SurCharge' => $dCess,'EDCess' => $dEDCess,'HEDCess' => $dHEDCess, 'NetTax' => $dNetTax));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                    }

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    $bupdate = 'Y';

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                return $this->getResponse()->setContent($bupdate);
            }
        }
    }


    public function checkPeriodUsedAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $postParams = $request->getPost();
                $iPeriodId = $postParams['PeriodId'];
                $sType = $postParams['Type'];

                $select = $sql->select();
                if ($sType=='T') {
                    $select->from('FA_TDSSetting')
                        ->columns(array('PeriodId'))
                        ->where(array("PeriodId" => $iPeriodId));
                } else {
                    $select->from('FA_ServiceTaxSetting')
                        ->columns(array('PeriodId'))
                        ->where(array("PeriodId" => $iPeriodId));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans = 'N';
                if (!empty($results)) $ans = 'Y';

                $response = $this->getResponse();
                $response->setContent($ans);
                return $response;
            }
        }
    }



    public function servicetaxsettingAction(){
        //$this->layout("layout/layout");

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

            $iPeriodId=0;

            $iPeriodId=$this->bsf->isNullCheck($this->params()->fromRoute('periodid'),'number');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'WPM_WorkTypeMaster'))
                ->join(array("b"=>"FA_ServiceTaxSetting"), new Expression("a.WorkType=b.WorkType and b.PeriodId=$iPeriodId"), array('TaxablePer','TaxPer','SurCharge','KKCess','NetTax','ReversePer','SBCess'), $select::JOIN_LEFT)
                ->columns(array('WorkTypeName','WorkType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->stlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'FA_QualPeriod'))
                ->columns(array('PeriodId','PeriodName','FDate' => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')") ,'TDate' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
                ->where("a.QualType ='S'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->periodlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;


            $this->_view->periodid = $iPeriodId;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function updatestsettingAction(){
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
                $iPeriodId = $this->bsf->isNullCheck($this->params()->fromPost('PeriodId'), 'number');
                $sWorkType = $this->bsf->isNullCheck($this->params()->fromPost('WorkType'), 'string');
                $dTaxable = $this->bsf->isNullCheck($this->params()->fromPost('Taxable'), 'number');
                $dTax = $this->bsf->isNullCheck($this->params()->fromPost('Tax'), 'number');
                $dKKCess = $this->bsf->isNullCheck($this->params()->fromPost('KKCess'), 'number');
                $dSBCess = $this->bsf->isNullCheck($this->params()->fromPost('SBCess'), 'number');
                $dNetTax = $this->bsf->isNullCheck($this->params()->fromPost('NetTax'), 'number');
                $dReverseTax = $this->bsf->isNullCheck($this->params()->fromPost('ReversePer'), 'number');


                $data = 'N';

                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from(array('a' => 'FA_ServiceTaxSetting'))
                    ->columns(array("WorkType"))
                    ->where("a.WorkType = '$sWorkType' and a.PeriodId = $iPeriodId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($results) $data = 'Y';

                $bupdate = 'N';

                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($data == "Y") {
                        $update = $sql->update();
                        $update->table('FA_ServiceTaxSetting')
                            ->set(array('WorkType' => $sWorkType, 'PeriodId' => $iPeriodId
                            , 'TaxablePer' => $dTaxable,'TaxPer' => $dTax,'KKCess' => $dKKCess,'NetTax' => $dNetTax,'ReversePer'=> $dReverseTax,'SBCess'=> $dSBCess))
                            ->where("WorkType= '$sWorkType' and PeriodId = $iPeriodId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                    } else {
                        $insert = $sql->insert();
                        $insert->into('FA_ServiceTaxSetting');
                        $insert->Values(array('WorkType' => $sWorkType, 'PeriodId' => $iPeriodId
                        , 'TaxablePer' => $dTaxable,'TaxPer' => $dTax,'KKCess' => $dKKCess, 'NetTax' => $dNetTax,'ReversePer'=> $dReverseTax,'SBCess'=> $dSBCess));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                    }

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    $bupdate = 'Y';

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                return $this->getResponse()->setContent($bupdate);
            }
        }
    }


    public function updateperiodmasterAction(){
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
                $aPeriodList = $this->params()->fromPost('PeriodList');
                $sType =  $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $bupdate = 'N';
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $delete = $sql->delete();
                    $delete->from('FA_QualPeriod')
                        ->where(array('QualType' => $sType));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    foreach ($aPeriodList as $data) {
                        $sPeriodName = $this->bsf->isNullCheck($data['PeriodName'], 'string');
                        $sFDate = date('Y-m-d',strtotime($this->bsf->isNullCheck($data['FDate'], 'string')));
                        $sTDate = $this->bsf->isNullCheck($data['TDate'], 'string');
                        $bTDate = false;
                        if ($sTDate !="") {
                            $sTDate =  date('Y-m-d',strtotime($sTDate));
                            $bTDate = true;
                        }

                        $insert = $sql->insert();
                        $insert->into('FA_QualPeriod');
                        if ($bTDate==true) {
                            $insert->Values(array('PeriodName' => $sPeriodName, 'QualType' => $sType
                            , 'FDate' => $sFDate, 'TDate' => $sTDate));
                        } else {
                            $insert->Values(array('PeriodName' => $sPeriodName, 'QualType' => $sType
                            , 'FDate' => $sFDate));
                        }
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    $bupdate = 'Y';
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

//                $iPeriodId = $this->bsf->isNullCheck($this->params()->fromPost('PeriodId'), 'number');
//                $sPeriodName = $this->bsf->isNullCheck($this->params()->fromPost('PeriodName'), 'string');
//                $sType =  $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
//                $sFDate = date('Y-m-d',strtotime($this->bsf->isNullCheck($this->params()->fromPost('FDate'), 'string')));
//                $sTDate = $this->bsf->isNullCheck($this->params()->fromPost('TDate'), 'string');
//                $bTDate = false;
//                if ($sTDate !="") {
//                   $sTDate =  date('Y-m-d',strtotime($sTDate));
//                   $bTDate = true;
//                }

//                $data = 'N';
//
//                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
//                $sql = new Sql($dbAdapter);
//
//                $select = $sql->select();
//                $select->from(array('a' => 'FA_QualPeriod'))
//                    ->columns(array("PeriodId"))
//                    ->where("QualType= '$sType' and PeriodId = $iPeriodId");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//                if ($results) $data = 'Y';
//
//                $bupdate = 'N';
//
//                $sql = new Sql($dbAdapter);
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//                try {
//                    if ($data == "Y") {
//                        $update = $sql->update();
//                        if ($bTDate == true) {
//                            $update->table('FA_QualPeriod')
//                                ->set(array('PeriodName' => $sPeriodName
//                                , 'FDate' => $sFDate, 'TDate' => $sTDate))
//                                ->where("QualType= '$sType' and PeriodId = $iPeriodId");
//                        } else {
//                            $update->table('FA_QualPeriod')
//                                ->set(array('PeriodName' => $sPeriodName
//                                , 'FDate' => $sFDate))
//                                ->where("QualType= '$sType' and PeriodId = $iPeriodId");
//                        }
//
//                        $statement = $sql->getSqlStringForSqlObject($update);
//                    } else {
//                        $insert = $sql->insert();
//                        $insert->into('FA_QualPeriod');
//                        if ($bTDate==true) {
//                            $insert->Values(array('PeriodName' => $sPeriodName, 'QualType' => $sType
//                            , 'FDate' => $sFDate, 'TDate' => $sTDate));
//                        } else {
//                            $insert->Values(array('PeriodName' => $sPeriodName, 'QualType' => $sType
//                            , 'FDate' => $sFDate));
//                        }
//                      $statement = $sql->getSqlStringForSqlObject($insert);
//                    }
//
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    $connection->commit();
//                    $bupdate = 'Y';
//
//                } catch(PDOException $e){
//                    $connection->rollback();
//                    print "Error!: " . $e->getMessage() . "</br>";
//                }
                return $this->getResponse()->setContent($bupdate);
            }
        }
    }

    public function gethsnfielddataAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $uploadedFile = $request->getFiles();
                if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                    $response->setContent('Invalid File Format');
                    $response->setStatusCode(400);
                    return $response;
                }

                $file_csv = "public/uploads/rfc/tmp/" . md5(time()) . ".csv";
                $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                $data = array();
                $file = fopen($file_csv, "r");

                $icount = 0;
                $bValid = true;

                while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                    if ($icount == 0) {
                        foreach ($xlData as $j => $value) {
                            $data[] = array('Field' => $value);
                        }
                    } else {
                        break;
                    }
                    $icount = $icount + 1;
                }

                if ($bValid == false) {
                    $data[] = array('Valid' => $bValid);
                }

                // delete csv file
                fclose($file);
                unlink($file_csv);

                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    function _validateUploadFile($file)
    {
        $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
        $mime_types = array('application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel');
        $exts = array('csv', 'xls', 'xlsx');

        if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
            return false;

        return true;
    }
    function _convertXLStoCSV($infile, $outfile) {
        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);

        return $objPHPExcel->getActiveSheet()->getTitle();
    }

    public function hsnmasterAction(){
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_HSNMaster'))
                ->join(array("b"=>"Proj_HSNSectionMaster"), "a.SectionId=b.SectionId", array('SectionName'=>new Expression("SLNo + '. ' + SectionName")), $select::JOIN_INNER)
                ->columns(array('HSNId','HSNCode','Description','HSNType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->hsnmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            $sql = new Sql($dbAdapter);
//            $select = $sql->select();
//            $select->from(array('a' => 'Proj_HSNSectionMaster'))
//                ->columns(array('SectionId','SectionName'=>new Expression("SLNo + '. ' + SectionName")));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->sectionmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;
//
//            $sql = new Sql($dbAdapter);
//            $select = $sql->select();
//            $select->from(array('a' => 'Proj_QualifierTypeMaster'))
//                ->columns( array( 'data' => 'QualifierTypeId', 'value' => 'QualifierTypeName'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->qualifiertypelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function uploadhsndataAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation

        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $uploadedFile = $request->getFiles();
                $postData = $request->getPost();
                $RType = $postData['arrHeader'];

                $select = $sql->select();
                $select->from(array('a' => 'Proj_HSNMaster'))
                    ->columns(array('HSNId','HSNCode'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                    $response->setContent('Invalid File Format');
                    $response->setStatusCode(400);
                    return $response;
                }

                try {
                    $file_csv = "public/uploads/rfc/tmp/" . md5(time()) . ".csv";
                    $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                    $data = array();
                    $file = fopen($file_csv, "r");

                    $icount = 0;
                    $bValid = true;

                    while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                        if ($icount == 0) {
                            foreach ($xlData as $j => $value) {
                                $bFound = false;
                                $sField = "";
                                foreach (json_decode($RType) as $k) {
                                    if (trim($value) == trim($k->efield)) {
                                        $sField = $k->field;
                                        $bFound = true;
                                        break;
                                    }
                                }
                                if ($bFound == true) {
                                    if (trim($sField) == "HSNCode")
                                        $col_1 = $j;
                                    if (trim($sField) == "Description")
                                        $col_2 = $j;
                                    if (trim($sField) == "SectionId")
                                        $col_3 = $j;
                                }
                            }
                        } else {
                            $bValid = true;
                            if (!isset($col_1) || !isset($col_2) || !isset($col_3)) {
                                $bValid = false;
                            }

                            // check for null

                            if (is_null($col_1) || is_null($col_2) || is_null($col_3)) {
                                $bValid = false;
                            }
                            if ($bValid == true) {

                                $sCode = 0;
                                $sDescription = "";
                                $iSectionId = 0;
                                $sType="";
                                $iParentId=0;

                                if (isset($col_1) && !is_null($col_1)) {
                                    if (isset($xlData[$col_1]) && !is_null($xlData[$col_1])) $sCode = $this->bsf->isNullCheck($xlData[$col_1], 'string');
                                }

                                if (isset($col_2) && !is_null($col_2)) {
                                    if (isset($xlData[$col_2]) && !is_null($xlData[$col_2])) $sDescription = $this->bsf->isNullCheck($xlData[$col_2], 'string');
                                }

                                if (isset($col_3) && !is_null($col_3)) {
                                    if (isset($xlData[$col_3]) && !is_null($xlData[$col_3])) $iSectionId = $this->bsf->isNullCheck($xlData[$col_3], 'number');
                                }

                                if (substr($sCode,2,1) ==0 &&  substr($sCode,3,1)==0) {
                                    $sType = "C";
                                    $iParentId=0;
                                } else if (substr($sCode,4,1) ==0 &&  substr($sCode,5,1)==0) {
                                    $sType = "H";
                                    $iParentId=0;
                                    $sPCode = substr($sCode,0,2) . '000000';

                                    $arr = array();
                                    $arr = array_filter($arrMaster, function($v) use($sPCode) { return trim($v['HSNCode']) == trim($sPCode); });
                                    $arrkey = array_keys($arr);
                                    if (!empty($arrkey)) {
                                        $akey = $arrkey[0];
                                        $iParentId = $arr[$akey]['HSNId'];
                                    }
                                } else {
                                    $sType = "S";
                                    $iParentId=0;
                                    $sPCode = substr($sCode,0,4) . '0000';
                                    $arr = array();
                                    $arr = array_filter($arrMaster, function($v) use($sPCode) { return trim($v['HSNCode']) == trim($sPCode); });
                                    $arrkey = array_keys($arr);
                                    if (!empty($arrkey)) {
                                        $akey = $arrkey[0];
                                        $iParentId = $arr[$akey]['HSNId'];
                                    }
                                }
                                if ($iSectionId !=0 && $sDescription !="") {
                                    $update = $sql->update();
                                    $update->table('Proj_HSNMaster');
                                    $update->set(array('Description' => $sDescription, 'SectionId' => $iSectionId, 'ParentId' => $iParentId, 'HSNType' => $sType));
                                    $update->where(array('HSNCode' => $sCode));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    if ($result->getAffectedRows() <= 0) {
                                        $insert = $sql->insert();
                                        $insert->into('Proj_HSNMaster');
                                        $insert->Values(array('HSNCode' => $sCode, 'Description' => $sDescription, 'SectionId' => $iSectionId, 'ParentId' => $iParentId, 'HSNType' => $sType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $identity = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $arrhsn = array();
                                        $arrhsn['HSNId'] = $identity;
                                        $arrhsn['HSNCode'] = $sCode;
                                        array_push($arrMaster, $arrhsn);
                                    }
                                }
                            }

                            //  var_dump($arrMaster);

                            //$data[] = array('Valid' => $bValid, 'HSNCode' => $sCode, 'Description' => $sDescription, 'SectionId' => $iSectionId);
                        }
                        $icount = $icount + 1;
                    }

//                    if ($bValid == false) {
//                        $data[] = array('Valid' => $bValid);
//                    }

                    // delete csv file
                    fclose($file);
                    unlink($file_csv);
                } catch (Exception $ex) {
                    //$data[] = array('Valid' => $bValid);
                }
                $response->setContent($bValid);
                return $response;
            }
        }
    }

    public function sacmasterAction(){
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_SACMaster'))
                ->join(array("b"=>"Proj_SACSectionMaster"), "a.SectionId=b.SectionId", array('SectionName'=>new Expression("SLNo + '. ' + SectionName")), $select::JOIN_INNER)
                ->columns(array('SACId','SACCode','Description','SACType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->sacmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function uploadsacdataAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation

        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $uploadedFile = $request->getFiles();
                $postData = $request->getPost();
                $RType = $postData['arrHeader'];

                $select = $sql->select();
                $select->from(array('a' => 'Proj_SACMaster'))
                    ->columns(array('SACId','SACCode'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                    $response->setContent('Invalid File Format');
                    $response->setStatusCode(400);
                    return $response;
                }

                try {
                    $file_csv = "public/uploads/rfc/tmp/" . md5(time()) . ".csv";
                    $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                    $data = array();
                    $file = fopen($file_csv, "r");

                    $icount = 0;
                    $bValid = true;

                    while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                        if ($icount == 0) {
                            foreach ($xlData as $j => $value) {
                                $bFound = false;
                                $sField = "";
                                foreach (json_decode($RType) as $k) {
                                    if (trim($value) == trim($k->efield)) {
                                        $sField = $k->field;
                                        $bFound = true;
                                        break;
                                    }
                                }
                                if ($bFound == true) {
                                    if (trim($sField) == "SACCode")
                                        $col_1 = $j;
                                    if (trim($sField) == "Description")
                                        $col_2 = $j;
                                    if (trim($sField) == "SectionId")
                                        $col_3 = $j;
                                }
                            }
                        } else {
                            $bValid = true;
                            if (!isset($col_1) || !isset($col_2) || !isset($col_3)) {
                                $bValid = false;
                            }

                            // check for null

                            if (is_null($col_1) || is_null($col_2) || is_null($col_3)) {
                                $bValid = false;
                            }
                            if ($bValid == true) {

                                $sCode = 0;
                                $sDescription = "";
                                $iSectionId = 0;
                                $sType="";
                                $iParentId=0;

                                if (isset($col_1) && !is_null($col_1)) {
                                    if (isset($xlData[$col_1]) && !is_null($xlData[$col_1])) $sCode = $this->bsf->isNullCheck($xlData[$col_1], 'string');
                                }

                                if (isset($col_2) && !is_null($col_2)) {
                                    if (isset($xlData[$col_2]) && !is_null($xlData[$col_2])) $sDescription = $this->bsf->isNullCheck($xlData[$col_2], 'string');
                                }

                                if (isset($col_3) && !is_null($col_3)) {
                                    if (isset($xlData[$col_3]) && !is_null($xlData[$col_3])) $iSectionId = $this->bsf->isNullCheck($xlData[$col_3], 'number');
                                }

                                if (strlen(trim($sCode)) ==4) {
                                    $sType = "C";
                                    $iParentId=0;
                                } else if (strlen(trim($sCode)) ==5) {
                                    $sType = "H";
                                    $iParentId=0;
                                    $sPCode = substr($sCode,0,4);

                                    $arr = array();
                                    $arr = array_filter($arrMaster, function($v) use($sPCode) { return trim($v['SACCode']) == trim($sPCode); });
                                    $arrkey = array_keys($arr);
                                    if (!empty($arrkey)) {
                                        $akey = $arrkey[0];
                                        $iParentId = $arr[$akey]['SACId'];
                                    }
                                } else {
                                    $sType = "S";
                                    $iParentId=0;
                                    $sPCode = substr($sCode,0,5);
                                    $arr = array();
                                    $arr = array_filter($arrMaster, function($v) use($sPCode) { return trim($v['SACCode']) == trim($sPCode); });
                                    $arrkey = array_keys($arr);
                                    if (!empty($arrkey)) {
                                        $akey = $arrkey[0];
                                        $iParentId = $arr[$akey]['SACId'];
                                    }
                                }
                                if ($iSectionId !=0 && $sDescription !="") {
                                    $update = $sql->update();
                                    $update->table('Proj_SACMaster');
                                    $update->set(array('Description' => $sDescription, 'SectionId' => $iSectionId, 'ParentId' => $iParentId, 'SACType' => $sType));
                                    $update->where(array('SACCode' => $sCode));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    if ($result->getAffectedRows() <= 0) {
                                        $insert = $sql->insert();
                                        $insert->into('Proj_SACMaster');
                                        $insert->Values(array('SACCode' => $sCode, 'Description' => $sDescription, 'SectionId' => $iSectionId, 'ParentId' => $iParentId, 'SACType' => $sType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $identity = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $arrsac = array();
                                        $arrsac['SACId'] = $identity;
                                        $arrsac['SACCode'] = $sCode;
                                        array_push($arrMaster, $arrsac);
                                    }
                                }
                            }

                            //  var_dump($arrMaster);

                            //$data[] = array('Valid' => $bValid, 'HSNCode' => $sCode, 'Description' => $sDescription, 'SectionId' => $iSectionId);
                        }
                        $icount = $icount + 1;
                    }

//                    if ($bValid == false) {
//                        $data[] = array('Valid' => $bValid);
//                    }

                    // delete csv file
                    fclose($file);
                    unlink($file_csv);
                } catch (Exception $ex) {
                    //$data[] = array('Valid' => $bValid);
                }
                $response->setContent($bValid);
                return $response;
            }
        }
    }
}