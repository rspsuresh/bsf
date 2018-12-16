<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Cb\Controller;

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
use PHPExcel;
use PHPExcel_IOFactory;
use Application\View\Helper\CommonHelper;

class WorkorderController extends AbstractActionController {

    public function __construct() {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation
        if ($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if ($this->getRequest()->isXmlHttpRequest()) {
                    // AJAX
                    $response->setStatusCode(401)
                            ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
                    return;
                }
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Work Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $userId = $this->auth->getIdentity()->CbUserId;

        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $data = 'N';
                $SearchStr = $this->bsf->isNullCheck($this->params()->fromRoute('SearchStr'), 'string');
                $select = $sql->select();
                $response = $this->getResponse();
                switch ($RType) {
                    case 'orderno':
                        $select->from('CB_WORegister')
                                ->columns(array('WorkOrderId'))
                                ->where("WONo='$SearchStr' AND DeleteFlag=0 AND SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                    case 'getProject':
                        $clientId = $this->bsf->isNullCheck($postData['data'], 'number');

                        $select = $sql->select();
                        $select->from('CB_ProjectMaster')
                                ->columns(array('data' => 'ProjectId', 'value' => 'ProjectName'))
                                ->where("ClientId='$clientId' AND DeleteFlag=0");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                    case 'getAmentmentWO':
                        $projectId = $this->bsf->isNullCheck($postData['data'], 'number');

                        $select = $sql->select();
                        $select->from('CB_WORegister')
                                ->columns(array('data' => 'WorkOrderId', 'value' => 'WONo'))
                                ->where("ProjectId='$projectId' AND PWorkOrderId=0");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                    case 'clientname':
                        $session_pref = new Container('subscriber_pref');
                        $select = $sql->select();
                        $select->from( 'CB_ClientMaster' )
                            ->columns( array( 'count' => new Expression('COUNT(ClientId)')))
                            ->where("DeleteFlag='0' AND SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $clients = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        // check if plan count exceed
                        if($clients && $clients['count'] >= $session_pref->NoOfClientCount) {
                            $response->setContent($session_pref->NoOfClientCount);
                            $response->setStatusCode(201);
                            return $response;
                        }

                        $select = $sql->select();
                        $select->from('CB_ClientMaster')
                                ->columns(array('ClientId'))
                                ->where("ClientName='$SearchStr' AND DeleteFlag='0' AND SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                    case 'materialname':
                        $select->from('CB_MaterialMaster')
                                ->columns(array('MaterialId'))
                                ->where("MaterialName='$SearchStr'  AND SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                    case 'workorderdetails':
                        $select->from(array('a' => 'CB_WORegister'))
                                ->join(array('d' => 'CB_ProjectMaster'), 'a.ProjectId=d.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                                ->join(array('b' => 'CB_ProjectTypeMaster'), 'd.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select::JOIN_LEFT)
                                ->join(array('c' => 'CB_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select::JOIN_LEFT)
                                ->columns(array('ProjectDescription'))
                                ->where("a.WorkOrderId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $data = json_encode($results);
                        break;
                    case 'projectworkorders':
                        $select->from(array('a' => 'CB_WORegister'))
                                ->columns(array('WorkOrderId', 'WONo'))
                                ->where("ProjectId=$SearchStr and DeleteFlag='0' and LiveWO ='0'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                    case 'Amendment':
                        $select->from(array('a' => 'CB_WORegister'))
                                ->columns(array('PWorkOrderId', 'WONo'))
                                ->where("a.WorkOrderId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pwoId = $results->PWorkOrderId;
                        $sWONo = $results->WONo;
                        $wocount = 1;
                        if ($results->PWorkOrderId != 0) {
                            $select->from(array('a' => 'CB_WORegister'))
                                    ->columns(array('WorkOrderId', 'WONo'))
                                    ->where("a.WorkOrderId=$pwoId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $nresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $sWONo = $nresults->WONo;

                            $select = $sql->select();
                            $select->from('CB_WORegister')
                                    ->columns(array('Orders' => new Expression("Count(WorkOrderId)")))
                                    ->where("DeleteFlag='0' and PWorkOrderId =$pwoId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $nresults1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $wocount = $nresults1->Orders + 1;
                        } else {
                            $pwoId = $SearchStr;
                        }
                        $sWONo = $sWONo . '-A' . $wocount;
                        $adata = [$sWONo, $pwoId];
                        $data = json_encode($adata);
                        break;
                    case 'projectdetails':
                        $select->from(array('a' => 'CB_ProjectMaster'))
                                ->join(array('b' => 'CB_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select::JOIN_LEFT)
                                ->join(array('c' => 'CB_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select::JOIN_LEFT)
                                ->columns(array('ProjectName', 'ProjectDescription', 'ProjectId', 'ProjectTypeId', 'ClientId'), array('ProjectTypeName'), array('ClientName'))
                                ->where("a.ProjectId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $data = json_encode($results);
                        break;
                    case 'PrevWorkOrder':

                        $select = $sql->select();
                        $select->from(array("a" => "CB_WORegister"))
                                ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array("ProjectTypeId", "ProjectDescription", "ProjectName"), $select::JOIN_LEFT)
                                ->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                                    , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                                    , "PeriodType", "Duration", "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')")
                                    , "AuthorityName", "AuthorityAddress", "AgreementType", "BudgetType","BudgetAmount", "PeriodType", "Duration", "OrderAmount", "OrderPercent"
                                    , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                        $select->where(array('a.DeleteFlag' => '0', 'a.WorkOrderId' => $SearchStr));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $aworegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOTerms"))
                                ->columns(array('MobilisationPercent', 'MobilisationAmount', 'MobilisationRecovery', 'RetentionPercent', 'PaymentSubmitPercent', 'PaymentFromSubmitDays', 'CertifyDays', 'PaymentFromCertifyDays', 'Notes', 'PeriodType', 'PeriodDay', 'PeriodWeekDay'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awoterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => "CB_WODocuments"))
                                ->columns(array('DocumentId', 'Type', 'Description', 'URL'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awodocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => "CB_WODepositTrans"))
                                ->columns(array('DepositType', 'DepostMode', 'RefNo', 'RefDate', 'Amount', 'ValidUpto'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awodeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //WOOtherTerms
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOOtherTerms"))
                                ->columns(array('TermsTitle', 'TermsDescription'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awootherterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOMaterialBaseRate
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOMaterialBaseRate"))
                                ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                                ->columns(array('MaterialId', 'Rate', 'EscalationPer', 'RateCondition', 'ActualRate'), array('MaterialName'), array('UnitName'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialbase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOMaterialExclude
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOExcludeMaterial"))
                                ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                                ->columns(array('MaterialId', 'Rate', 'SType'), array('MaterialName'), array('UnitName'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialexcl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOMaterialAdvance
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOMaterialAdvance"))
                                ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                                ->columns(array('MaterialId', 'AdvPercent'), array('MaterialName'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialadv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOTechSpec
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOTechSpec"))
                                ->columns(array('Title', 'Specification'))
                                ->where("WORegisterId=$SearchStr");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awotechterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOBillFormat
                        $select = $sql->select();
                        $select->from(array('a' => "CB_BillFormatTrans"))
                                ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('TypeName', 'RowName'), $select:: JOIN_LEFT)
                                ->columns(array('BillFormatId', 'SortId', 'SlNo', 'Description', 'Sign', 'Formula', 'Bold', 'Italic', 'Underline'), array('TypeName', 'RowName'))
                                ->where("WorkOrderId=$SearchStr");
						$select->order('a.SortId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awobillformat = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        // boq
                        $select = $sql->select();
                        $select->from(array('a' => "CB_WOBOQ"))
                                ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                                ->columns(array('WOBOQId', 'WOBOQTransId', 'TransType', 'SortId', 'WorkGroupId', 'AgtNo', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'ClientRate', 'ClientAmount', 'Rate', 'Amount', 'RateVariance', 'Header','HeaderType'))
                                ->where("a.WORegisterId=$SearchStr")
                                ->order('a.SortId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awoboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $adata['WORegister'] = $aworegister;
                        $adata['Terms'] = $awoterms;
                        $adata['Document'] = $awodocument;
                        $adata['Deposit'] = $awodeposit;
                        $adata['OTerms'] = $awootherterms;
                        $adata['PriceEsc'] = $awomaterialbase;
                        $adata['Supply'] = $awomaterialexcl;
                        $adata['Advance'] = $awomaterialadv;
                        $adata['Tech'] = $awotechterms;
                        $adata['BillFormat'] = $awobillformat;
                        $adata['BOQ'] = $awoboq;

                        $data = json_encode($adata);
                        break;
                }
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();

            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                $postData = $request->getPost();
                $files = $request->getFiles();

                $woid = $this->bsf->isNullCheck($postData['workorderid'], 'number');
                $delFilesUrl = array();
                try {
                    if ($woid == 0) {
                        // Create Work order
                        $orderType = $this->bsf->isNullCheck($postData['Order'], 'string');
                        $AgreementNo = $this->bsf->isNullCheck($postData['AgreementNo'], 'string');
                        $AgreementDate = $this->bsf->isNullCheck($postData['AgreementDate'], 'date');
                        $AuthorityName = $this->bsf->isNullCheck($postData['AuthorityName'], 'string');
                        $AuthorityAddress = $this->bsf->isNullCheck($postData['AuthorityAddress'], 'string');
                        $AgreementType = $this->bsf->isNullCheck($postData['AgreementType'], 'string');
                        $BudgetType = $this->bsf->isNullCheck($postData['BudgetType'], 'string');
                        $BudgetAmount = $this->bsf->isNullCheck($postData['BudgetAmount'], 'number');
                        $StartDate = $this->bsf->isNullCheck($postData['StartDate'], 'date');
                        $EndDate = $this->bsf->isNullCheck($postData['EndDate'], 'date');
                        $PeriodType = $this->bsf->isNullCheck($postData['PeriodType'], 'string');
                        $Duration = $this->bsf->isNullCheck($postData['Duration'], 'number');
                        $OrderAmount = $this->bsf->isNullCheck($postData['OrderAmount'], 'number');
                        $OrderPercent = $this->bsf->isNullCheck($postData['OrderPercent'], 'number');

                        // index form fields
                        $cityId = 0;
                        $stateId = 0;
                        $countryId = 0;
                        $clientAddress = "";

                        if ($postData['ProjectTypeId'] == 'new') {
                            $projectTypeName = $this->bsf->isNullCheck($postData['ProjectTypeName'], 'string');
                            $insert = $sql->insert();
                            $insert->into('CB_ProjectTypeMaster');
                            $insert->Values(array('ProjectTypeName' => $projectTypeName, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $projectTypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            CommonHelper::insertCBLog('ProjectType-Master-Add', $projectTypeId, $projectTypeName, $dbAdapter);
                        } else
                            $projectTypeId = $this->bsf->isNullCheck($postData['ProjectTypeId'], 'number');

                        $clientAddress = $this->bsf->isNullCheck($postData['MClientAddress'], 'string');
                        if ($postData['ClientId'] == 'new') {
                            $clientName = $this->bsf->isNullCheck($postData['MClientName'], 'string');

                            //$cityId = $this->bsf->isNullCheck( $postData[ 'MCityId' ], 'string' );
                            $cityName = $this->bsf->isNullCheck($postData['MCityId'], 'string');

                            //start
                            if ($cityName != "") {
                                $stateName = $this->bsf->isNullCheck($postData['state'], 'string');
                                $countryName = $this->bsf->isNullCheck($postData['country'], 'string');
                                // check city found
                                $select = $sql->select();
                                $select->from('WF_CityMaster')
                                        ->columns(array('CityId'))
                                        ->where("CityName='$cityName'")
                                        ->limit(1);
                                $city_stmt = $sql->getSqlStringForSqlObject($select);
                                $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if ($city) {
                                    // city found
                                    $cityId = $city['CityId'];
                                } else {

                                    // check for state
                                    $select = $sql->select();
                                    $select->from('WF_StateMaster')
                                            ->columns(array('StateId', 'CountryId'))
                                            ->where("StateName='$stateName'")
                                            ->limit(1);
                                    $state_stmt = $sql->getSqlStringForSqlObject($select);
                                    $state = $dbAdapter->query($state_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if ($state) {
                                        $stateId = $state['StateId'];
                                        $countryId = $state['CountryId'];
                                    } else {
                                        // state not found
                                        // check for country
                                        // get country id
                                        $select = $sql->select();
                                        $select->from('WF_CountryMaster')
                                                ->columns(array('CountryId'))
                                                ->where("CountryName='$countryName'")
                                                ->limit(1);
                                        $cntry_stmt = $sql->getSqlStringForSqlObject($select);
                                        $country = $dbAdapter->query($cntry_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if ($country) {
                                            // country found
                                            $countryId = $country['CountryId'];
                                        } else {
                                            // country not found have to insert
                                            $insert = $sql->insert();
                                            $insert->into('WF_CountryMaster');
                                            $insert->Values(array('CountryName' => $countryName));
                                            $stmt = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        }

                                        // add state
                                        $insert = $sql->insert();
                                        $insert->into('WF_StateMaster');
                                        $insert->Values(array('StateName' => $stateName, 'CountryId' => $countryId));
                                        $stmt = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    // add city
                                    $insert = $sql->insert();
                                    $insert->into('WF_CityMaster');
                                    $insert->Values(array('CityName' => $cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
                                    $stmt = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                            }
                            //End
                            $insert = $sql->insert();
                            $insert->into('CB_ClientMaster');
                            $insert->Values(array('ClientName' => $clientName, 'Address' => $clientAddress, 'CityId' => $cityId, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            CommonHelper::insertCBLog('Client-Master-Add', $clientId, $clientName, $dbAdapter);
                        } else
                            $clientId = $this->bsf->isNullCheck($postData['ClientId'], 'number');

                        if ($postData['ProjectId'] == 'new') {
                            $projectName = $this->bsf->isNullCheck($postData['ProjectName'], 'string');
                            $projectdesc = $this->bsf->isNullCheck($postData['ProjectDescription'], 'string');
                            $insert = $sql->insert();
                            $insert->into('CB_ProjectMaster');
                            $insert->Values(array('ProjectName' => $projectName, 'ProjectDescription' => htmlspecialchars($projectdesc), 'ProjectTypeId' => $projectTypeId, 'ClientId' => $clientId, 'CityId' => $cityId, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $projectId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            CommonHelper::insertCBLog('Project-Master-Add', $projectId, $projectName, $dbAdapter);
                        } else
                            $projectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');

                        $PrevWOId = $this->bsf->isNullCheck($postData['PWorkOrderId'], 'number');
                        $iLiveWO = 0;
                        if ($PrevWOId != 0) {
                            $iLiveWO = 1;
                        }

                        $sNewWONo = $this->bsf->isNullCheck($postData['WONo'], 'string');
                        $woDate = date('Y-m-d', strtotime($postData['WODate']));
                        $woNo = $this->bsf->isNullCheck($postData['WONo'], 'string');
                        $woStartDate = date('Y-m-d', strtotime($StartDate));
                        $woEndDate = date('Y-m-d', strtotime($EndDate));
                        $woAgreementDate = date('Y-m-d', strtotime($AgreementDate));
                        $insert = $sql->insert();
                        $insert->into('CB_WORegister');
                        $insert->Values(array('WODate' => $woDate
                            , 'OrderType' => $orderType, 'WONo' => $woNo
                            , 'ProjectId' => $projectId, 'ProjectTypeId' => $projectTypeId, 'ClientId' => $clientId
                            , 'PWorkOrderId' => $this->bsf->isNullCheck($postData['PWorkOrderId'], 'number')
                            , 'AgreementNo' => $AgreementNo, 'AgreementDate' => $woAgreementDate, 'AuthorityName' => $AuthorityName, 'AuthorityAddress' => $AuthorityAddress, 'AgreementType' => $AgreementType
                            , 'StartDate' => $woStartDate, 'EndDate' => $woEndDate, 'PeriodType' => $PeriodType , 'BudgetType' => $BudgetType, 'BudgetAmount' => $BudgetAmount
                            , 'Duration' => $Duration, 'OrderAmount' => $OrderAmount, 'OrderPercent' => $OrderPercent, 'PWorkOrderId' => $PrevWOId, 'LiveWO' => $iLiveWO, 'SubscriberId' => $subscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $WorkOrderId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //BOQ
                        $iWGId=0;
                        $iParentId=0;
                        $iOParentId=0;
                        $iWBSId=0;
                        foreach($postData['boqrefids'] as $i) {
                            $delflag = $this->bsf->isNullCheck($postData['boqdelflag_' . $i], 'string');
                            if ($delflag != 'Y') {
                                $boqid = $this->bsf->isNullCheck($postData['boqid_' . $i], 'number');
                                $iSortId = $this->bsf->isNullCheck($postData['boqSortid_' . $i], 'number');
                                $agtno = $this->bsf->isNullCheck($postData['agtno_' . $i], 'string');
                                $sTranstype = $this->bsf->isNullCheck($postData['boqtranstype_' . $i], 'string');
                                if ($sTranstype == "H") {
                                    $header = $this->bsf->isNullCheck($postData['header_' . $i], 'string');
                                    $sHeaderType = $this->bsf->isNullCheck($postData['headertype_' . $i], 'string');

                                    if ($sHeaderType == "W") {
                                        $iWBSId = 0;
                                        $iParentId = 0;
                                        $iWGId = 0;
                                    } else if ($sHeaderType == "G") {
                                        $iOParentId = 0;
                                        $iParentId = 0;
                                        $iWGId = 0;
                                    } else if ($sHeaderType == "P") {
                                        $iParentId = $iOParentId;
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('CB_WOBOQ');
                                    $insert->Values(array('WOBOQId' => $boqid, 'WORegisterId' => $WorkOrderId, 'TransType' => $sTranstype, 'AgtNo' => $agtno, 'Header' => $header, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId, 'HeaderType' => $sHeaderType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $WoBoqId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if ($sHeaderType == "W") {
                                        $iWBSId = $WoBoqId;
                                    } else if ($sHeaderType == "G") {
                                        $iWGId = $WoBoqId;
                                    } else if ($sHeaderType == "P") {
                                        $iOParentId = $iParentId;
                                        $iParentId = $WoBoqId;
                                    }
                                } else {
                                    $spec = $this->bsf->isNullCheck($postData['spec_' . $i], 'string');
                                    $shortspec = $this->bsf->isNullCheck($postData['shortspec_' . $i], 'string');
                                    $unitid = $this->bsf->isNullCheck($postData['unitid_' . $i], 'number');
                                    $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                    $rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                    $amt = $this->bsf->isNullCheck($postData['amt_' . $i], 'number');
                                    $budgetamount = $this->bsf->isNullCheck($postData['budgetamount_' . $i], 'number');
                                    $ClientRate = $this->bsf->isNullCheck($postData['crate_' . $i], 'number');
                                    $ClientAmount = $this->bsf->isNullCheck($postData['camt_' . $i], 'number');
                                    $RateVariance = $this->bsf->isNullCheck($postData['ratevariance_' . $i], 'number');

                                    if ($spec == '' || $unitid == 0 || $qty == 0 || $rate == 0 || $amt == 0)
                                        continue;

                                    if ($AgreementType == 'I' && ($ClientRate == '' || $ClientAmount == '' || $RateVariance == ""))
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('CB_WOBOQ');
                                    $insert->Values(array('WOBOQId' => $boqid, 'WORegisterId' => $WorkOrderId, 'TransType' => $sTranstype, 'AgtNo' => $agtno
                                    , 'Specification' => $spec, 'ShortSpec' => $shortspec, 'UnitId' => $unitid, 'Qty' => $qty
                                    , 'Rate' => $rate, 'Amount' => $amt, 'BudgetAmount' => $budgetamount,'ClientRate' => $ClientRate, 'ClientAmount' => $ClientAmount
                                    , 'RateVariance' => $RateVariance, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        $update = $sql->update();
                        $update->table('CB_WOBOQ');
                        $update->set(array('WOBOQId' => new Expression("WOBOQTransId")));
                        $update->where("WORegisterId = $WorkOrderId and WOBOQId=0");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // Documents fields
                        $documentrowid = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                        for ($i = 1; $i <= $documentrowid; $i++) {
                            $Type = $this->bsf->isNullCheck($postData['docType_' . $i], 'string');
                            $Description = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');

                            if ($Type == '' || $Description == '')
                                continue;

                            $url = '';
                            if($files['docFile_' . $i]['name']){

                                $dir = 'public/uploads/cb/workorder/'.$WorkOrderId.'/';
                                $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                                if($filename) {
                                    // update valid files only
                                    $url = '/uploads/cb/workorder/'.$WorkOrderId.'/' . $filename;
                                }
                            }

                            $insert = $sql->insert();
                            $insert->into('CB_WODocuments');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'Type' => $Type, 'Description' => $Description, 'URL' => $url));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $docId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        //Deposit
                        $depositowid = $this->bsf->isNullCheck($postData['depositrowid'], 'number');

                        for ($i = 1; $i <= $depositowid; $i++) {
                            $depositType = $this->bsf->isNullCheck($postData['deposit_' . $i], 'string');
                            $depositmode = $this->bsf->isNullCheck($postData['paymode_' . $i], 'string');
                            $refno = $this->bsf->isNullCheck($postData['drefno_' . $i], 'string');
                            $refdate = $this->bsf->isNullCheck($postData['drefdate_' . $i], 'date');
                            $amount = $this->bsf->isNullCheck($postData['damount_' . $i], 'number');
                            $validupto = $this->bsf->isNullCheck($postData['dvaliddate_' . $i], 'date');

                            if ($depositType == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WODepositTrans');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'DepositType' => $depositType, 'DepostMode' => $depositmode, 'RefNo' => $refno, 'RefDate' => date('Y-m-d', strtotime($refdate)), 'Amount' => $amount, 'ValidUpto' => date('Y-m-d', strtotime($validupto))));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //Material
                        $materialrowid = $this->bsf->isNullCheck($postData['newmaterialrowid'], 'number');
                        $NewMaterialIds = array();
                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $materialname = $this->bsf->isNullCheck($postData['newmaterial_' . $i], 'string');
                            $unitid = $this->bsf->isNullCheck($postData['newmunitid_' . $i], 'number');
                            $newMaterialid = $this->bsf->isNullCheck($postData['newmaterialid_' . $i], 'string');
                            if ($materialname == '')
                                continue;
                            $insert = $sql->insert();
                            $insert->into('CB_MaterialMaster');
                            $insert->Values(array('MaterialName' => $materialname, 'UnitId' => $unitid, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $generatedMaterialId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $NewMaterialIds[$newMaterialid] = $generatedMaterialId;

                            CommonHelper::insertCBLog('Material-Master-Add', $generatedMaterialId, $materialname, $dbAdapter);
                        }

                        // Other Terms fields
                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOOtherTerms');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Tech Terms
                        $techrowid = $this->bsf->isNullCheck($postData['techrowid'], 'number');
                        for ($i = 1; $i <= $techrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['techTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['techDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOTechSpec');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'Title' => $TermsTitle, 'Specification' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Base Rate fields
                        $materialrowid = $this->bsf->isNullCheck($postData['materialrowid'], 'number');
                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialId_' . $i], 'string');
                            $Rate = $this->bsf->isNullCheck($postData['materialRate_' . $i], 'number');
                            $escper = $this->bsf->isNullCheck($postData['escalationper_' . $i], 'number');
                            $NewRate = $this->bsf->isNullCheck($postData['materialnewRate_' . $i], 'number');
                            $RateBase = $this->bsf->isNullCheck($postData['materialbase_' . $i], 'string');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOMaterialBaseRate');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        // Material Exclude fields
                        $materialExcrowid = $this->bsf->isNullCheck($postData['materialExcrowid'], 'number');
                        for ($i = 1; $i <= $materialExcrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialExcId_' . $i], 'string');
                            $MaterialRate = $this->bsf->isNullCheck($postData['materialExcRate_' . $i], 'number');
                            $SupplyType = $this->bsf->isNullCheck($postData['materialExcType_' . $i], 'string');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOExcludeMaterial');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Advance fields
                        $materialAdvrowid = $this->bsf->isNullCheck($postData['materialAdvrowid'], 'number');
                        for ($i = 1; $i <= $materialAdvrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialAdvId_' . $i], 'string');
                            $Rate = $this->bsf->isNullCheck($postData['materialAdvRate_' . $i], 'number');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOMaterialAdvance');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'AdvPercent' => $Rate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //BillFormat
                        $billformatrowid = $this->bsf->isNullCheck($postData['billformatrowid'], 'number');
                        for ($i = 1; $i <= $billformatrowid; $i++) {
                            $billformatid = $this->bsf->isNullCheck($postData['billformatid_' . $i], 'number');
                            $billformatsortid = $this->bsf->isNullCheck($postData['billformatsortid_' . $i], 'number');
//                            $typename = $this->bsf->isNullCheck($postData['typename_' . $i], 'string');
                            $desc = $this->bsf->isNullCheck($postData['billformatdesc_' . $i], 'string');
                            $formula = $this->bsf->isNullCheck($postData['formula_' . $i], 'string');
                            $slno = $this->bsf->isNullCheck($postData['slno_' . $i], 'string');

                            $check_bold = isset($postData['textbold_' . $i]) ? 1 : 0;
                            $check_italic = isset($postData['textitalic_' . $i]) ? 1 : 0;
                            $check_underline = isset($postData['textunderline_' . $i]) ? 1 : 0;

                            if ($billformatid == 0 && $desc == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_BillFormatTrans');
                            $insert->Values(array('WorkOrderId' => $WorkOrderId, 'BillFormatId' => $billformatid, 'SortId' => $billformatsortid, 'Description' => $desc
                                            , 'Formula' => $formula, 'Slno' => $slno, 'Bold' => $check_bold, 'Italic' => $check_italic, 'Underline' => $check_underline));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //Mobilisation and other fields
                        $insert = $sql->insert();
                        $insert->into('CB_WOTerms');
                        $insert->Values(array('WORegisterId' => $WorkOrderId, 'MobilisationPercent' => $this->bsf->isNullCheck($postData['MobilisationPercent'], 'number')
                            , 'MobilisationAmount' => $this->bsf->isNullCheck($postData['MobilisationAmount'], 'number'), 'MobilisationRecovery' => $this->bsf->isNullCheck($postData['MobilisationRecovery'], 'number'), 'RetentionPercent' => $this->bsf->isNullCheck($postData['RetentionPercent'], 'number')
                            , 'PaymentSubmitPercent' => $this->bsf->isNullCheck($postData['PaymentSubmitPercent'], 'number'), 'PaymentFromSubmitDays' => $this->bsf->isNullCheck($postData['PaymentFromSubmitDays'], 'number')
                            , 'CertifyDays' => $this->bsf->isNullCheck($postData['CertifyDays'], 'number'), 'PaymentFromCertifyDays' => $this->bsf->isNullCheck($postData['PaymentFromCertifyDays'], 'number')
                            , 'PeriodType' => $this->bsf->isNullCheck($postData['billperiodtype'], 'number'), 'PeriodDay' => $this->bsf->isNullCheck($postData['billperiodday'], 'number')
                            , 'PeriodWeekDay' => $this->bsf->isNullCheck($postData['billperiodweekday'], 'number'), 'Notes' => $this->bsf->isNullCheck($postData['Notes'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('WorkOrder-Add', $WorkOrderId, $sNewWONo, $dbAdapter);


                        // get and set mail datas
                        $select = $sql->select();
                        $select->from("CB_ClientMaster")
                            ->columns(array('ClientName'))
                            ->where("ClientId=$clientId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $client = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from("CB_ProjectMaster")
                            ->columns(array('ProjectName', 'ProjectDescription'))
                            ->where("ProjectId=$projectId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $project = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        switch($PeriodType) {
                            case 'D';
                                $period = 'Day(s)';
                                break;
                            case 'M';
                                $period = 'Month(s)';
                                break;
                            case 'Y';
                                $period = 'Year(s)';
                                break;
                        }

                        //workorder created mail alert
                        $mailData = array(
                            array(
                                'name' => 'ORDERID',
                                'content' => $woNo
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => date('d-m-Y', strtotime($woDate))
                            ),
                            array(
                                'name' => 'AMOUNT',
                                'content' => $viewRenderer->commonHelper()->sanitizeNumber($OrderAmount,2,true)
                            ),
                            array(
                                'name' => 'PROJECTNAME',
                                'content' => $project['ProjectName']
                            ),
                            array(
                                'name' => 'DESCRIPTION',
                                'content' => $project['ProjectDescription']
                            ),
                            array(
                                'name' => 'CLIENTNAME',
                                'content' => $client['ClientName']
                            ),
                            array(
                                'name' => 'AGREEMENTDATE',
                                'content' => date('d-m-Y', strtotime($woAgreementDate))
                            ),
                            array(
                                'name' => 'DURATION',
                                'content' => $Duration . $period
                            ),
                            array(
                                'name' => 'STARTDATE',
                                'content' => date('d-m-Y', strtotime($woStartDate))
                            ),
                            array(
                                'name' => 'ENDDATE',
                                'content' => date('d-m-Y', strtotime($woEndDate))
                            )
                        );

                        $select = $sql->select();
                        $select->from("CB_SubscriberMaster")
                            ->columns(array('Email'))
                            ->where("SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $subscriber = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if($subscriber && $subscriber['Email'] != '') {
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo($subscriber['Email'], $config['general']['mandrilEmail'], 'New Workorder Created', 'cb_wo_new', $mailData );
                        }

                        $select = $sql->select();
                        $select->from("CB_Users")
                            ->columns(array('Email'))
                            ->where("CbUserId=$userId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $user = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if($user && $user['Email'] != '' && ($subscriber && $subscriber['Email'] != $user['Email'])) {
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo( $user[ 'Email' ], $config['general']['mandrilEmail'], 'New Workorder Created', 'cb_wo_new', $mailData );
                        }
                    } else {
                        $AgreementNo = $this->bsf->isNullCheck($postData['AgreementNo'], 'string');
                        $AgreementDate = $this->bsf->isNullCheck($postData['AgreementDate'], 'date');
                        $AuthorityName = $this->bsf->isNullCheck($postData['AuthorityName'], 'string');
                        $AuthorityAddress = $this->bsf->isNullCheck($postData['AuthorityAddress'], 'string');
                        $AgreementType = $this->bsf->isNullCheck($postData['AgreementType'], 'string');
                        $BudgetType = $this->bsf->isNullCheck($postData['BudgetType'], 'string');
                        $BudgetAmount = $this->bsf->isNullCheck($postData['BudgetAmount'], 'number');
                        $StartDate = $this->bsf->isNullCheck($postData['StartDate'], 'date');
                        $EndDate = $this->bsf->isNullCheck($postData['EndDate'], 'date');
                        $PeriodType = $this->bsf->isNullCheck($postData['PeriodType'], 'string');
                        $Duration = $this->bsf->isNullCheck($postData['Duration'], 'number');
                        $OrderAmount = $this->bsf->isNullCheck($postData['OrderAmount'], 'number');
                        $OrderPercent = $this->bsf->isNullCheck($postData['OrderPercent'], 'number');
                        $sNewWONo = $this->bsf->isNullCheck($postData['WONo'], 'string');

                        $update = $sql->update();
                        $update->table('CB_WORegister');
                        $update->set(array('AgreementNo' => $AgreementNo, 'AgreementDate' => date('Y-m-d', strtotime($AgreementDate)), "AuthorityName" => $AuthorityName,
                            "AuthorityAddress" => $AuthorityAddress, "AgreementType" => $AgreementType, "BudgetType" => $BudgetType, "BudgetAmount" => $BudgetAmount, "StartDate" => date('Y-m-d', strtotime($StartDate)),
                            "EndDate" => date('Y-m-d', strtotime($EndDate)), "PeriodType" => $PeriodType, "Duration" => $Duration, "OrderAmount" => $OrderAmount,
                            "OrderPercent" => $OrderPercent));
                        $update->where(array('WorkOrderId' => $woid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $iWGId=0;
                        $iParentId=0;
                        $iOParentId=0;
                        $iWBSId=0;


                        // delete all boqs
                        if(isset($postData['deleteBOQs']) && $postData['deleteBOQs'] == 1) {
                            $delete = $sql->delete();
                            $delete->from( 'CB_WOBOQ' )
                                ->where( array( 'WORegisterId' => $woid ) );
                            $statement = $sql->getSqlStringForSqlObject( $delete );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }

                        foreach($postData['boqrefids'] as $i) {
                            $delflag = $this->bsf->isNullCheck($postData['boqdelflag_' . $i], 'string');
                            $boqtransid = $this->bsf->isNullCheck($postData['boqtransid_' . $i], 'number');
                            if ($delflag != 'Y') {
                                $boqid = $this->bsf->isNullCheck($postData['boqid_' . $i], 'number');
                                $iSortId = $this->bsf->isNullCheck($postData['boqSortid_' . $i], 'number');
                                $agtno = $this->bsf->isNullCheck($postData['agtno_' . $i], 'string');
                                $sTranstype = $this->bsf->isNullCheck($postData['boqtranstype_' . $i], 'string');
                                if ($sTranstype == "H") {
                                    $header = $this->bsf->isNullCheck($postData['header_' . $i], 'string');
                                    $sHeaderType = $this->bsf->isNullCheck($postData['headertype_' . $i], 'string');

                                    if ($sHeaderType == "W") {
                                        $iWBSId = 0;
                                        $iParentId = 0;
                                        $iWGId = 0;
                                    } else if ($sHeaderType == "G") {
                                        $iOParentId = 0;
                                        $iParentId = 0;
                                        $iWGId = 0;
                                    } else if ($sHeaderType == "P") {
                                        $iParentId = $iOParentId;
                                    }

                                    if ($boqtransid == 0) {
                                        $insert = $sql->insert();
                                        $insert->into('CB_WOBOQ');
                                        $insert->Values(array('WOBOQId' => $boqid, 'WORegisterId' => $woid, 'TransType' => $sTranstype, 'AgtNo' => $agtno, 'Header' => $header, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId, 'HeaderType' => $sHeaderType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $WoBoqId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    } else {
                                        $update = $sql->update();
                                        $update->table('CB_WOBOQ');
                                        $update->set(array('WOBOQId' => $boqid, 'WORegisterId' => $woid, 'TransType' => $sTranstype, 'AgtNo' => $agtno, 'Header' => $header, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId, 'HeaderType' => $sHeaderType));
                                        $update->where(array('WOBOQTransId' => $boqtransid));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $WoBoqId = $boqtransid;
                                    }

                                    if ($sHeaderType == "W") {
                                        $iWBSId = $WoBoqId;
                                    } else if ($sHeaderType == "G") {
                                        $iWGId = $WoBoqId;
                                    } else if ($sHeaderType == "P") {
                                        $iOParentId = $iParentId;
                                        $iParentId = $WoBoqId;
                                    }
                                } else {
                                    $spec = $this->bsf->isNullCheck($postData['spec_' . $i], 'string');
                                    $shortspec = $this->bsf->isNullCheck($postData['shortspec_' . $i], 'string');
                                    $unitid = $this->bsf->isNullCheck($postData['unitid_' . $i], 'number');
                                    $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                    $rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                    $amt = $this->bsf->isNullCheck($postData['amt_' . $i], 'number');
                                    $budgetamount = $this->bsf->isNullCheck($postData['budgetamount_' . $i], 'number');
                                    $ClientRate = $this->bsf->isNullCheck($postData['crate_' . $i], 'number');
                                    $ClientAmount = $this->bsf->isNullCheck($postData['camt_' . $i], 'number');
                                    $RateVariance = $this->bsf->isNullCheck($postData['ratevariance_' . $i], 'number');

                                    if ($spec == '' || $unitid == 0 || $qty == 0 || $rate == 0 || $amt == 0)
                                        continue;

                                    if ($AgreementType == 'I' && ($ClientRate == '' || $ClientAmount == '' || $RateVariance == ""))
                                        continue;

                                    if ($boqtransid == 0) {
                                        $insert = $sql->insert();
                                        $insert->into('CB_WOBOQ');
                                        $insert->Values(array('WOBOQId' => $boqid, 'WORegisterId' => $woid, 'TransType' => $sTranstype, 'AgtNo' => $agtno
                                        , 'Specification' => $spec, 'ShortSpec' => $shortspec, 'UnitId' => $unitid, 'Qty' => $qty
                                        , 'Rate' => $rate, 'Amount' => $amt, 'BudgetAmount' => $budgetamount, 'ClientRate' => $ClientRate, 'ClientAmount' => $ClientAmount
                                        , 'RateVariance' => $RateVariance, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else {
                                        $update = $sql->update();
                                        $update->table('CB_WOBOQ');
                                        $update->set(array('WOBOQId' => $boqid, 'WORegisterId' => $woid, 'TransType' => $sTranstype, 'AgtNo' => $agtno
                                        , 'Specification' => $spec, 'ShortSpec' => $shortspec, 'UnitId' => $unitid, 'Qty' => $qty
                                        , 'Rate' => $rate, 'Amount' => $amt, 'ClientRate' => $ClientRate, 'ClientAmount' => $ClientAmount
                                        , 'RateVariance' => $RateVariance, 'WorkGroupId' => $iWGId, 'WBSId' => $iWBSId, 'ParentId' => $iParentId, 'SortId' => $iSortId));
                                        $update->where(array('WOBOQTransId' => $boqtransid));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            } else {
                                $delete = $sql->delete();
                                $delete->from('CB_WOBOQ')
                                    ->where(array('WOBOQTransId' => $boqtransid));
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $update = $sql->update();
                        $update->table('CB_WOBOQ');
                        $update->set(array('WOBOQId' => new Expression("WOBOQTransId")));
                        $update->where("WORegisterId = $woid and WOBOQId=0");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $documentrowid = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                        for ($i = 1; $i <= $documentrowid; $i++) {
                            $Type = $this->bsf->isNullCheck($postData['docType_' . $i], 'string');
                            $Description = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');
                            $url = $this->bsf->isNullCheck($postData['docFile_' . $i], 'string');
                            $docId = $this->bsf->isNullCheck($postData['docId_' . $i], 'number');

                            $docDel = FALSE;
                            if($docId) {
                                $docDel = $this->bsf->isNullCheck($postData['docDel_' . $docId], 'number');
                            }

                            if ($Type == '' || $Description == '')
                                continue;

                            if ($docDel) {
                                $delete = $sql->delete();
                                $delete->from('CB_WODocuments')
                                        ->where(array("DocumentId" => $docId));
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                if($url != '' || !is_null($url)) {
                                    $delFilesUrl[] = 'public' . $url;
                                }
                            } else {

                                if($files['docFile_' . $i]['name']){

                                    $dir = 'public/uploads/cb/workorder/'.$woid.'//';
                                    $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                                    if($filename) {
                                        // upload valid files only
                                        $url = '/uploads/cb/workorder/'.$woid.'/' . $filename;
                                    }
                                }

                                if ($docId) {
                                    $update = $sql->update();
                                    $update->table('CB_WODocuments')
                                        ->set(array('Type' => $Type, 'Description' => $Description, 'URL' => $url))
                                        ->where(array('DocumentId' => $docId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('CB_WODocuments');
                                    $insert->Values(array('WORegisterId' => $woid, 'Type' => $Type, 'Description' => $Description, 'URL' => $url));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                        //Material
                        $materialrowid = $this->bsf->isNullCheck($postData['newmaterialrowid'], 'number');
                        $NewMaterialIds = array();

                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $materialname = $this->bsf->isNullCheck($postData['newmaterial_' . $i], 'string');
                            $unitid = $this->bsf->isNullCheck($postData['newmunitid_' . $i], 'number');
                            $newMaterialid = $this->bsf->isNullCheck($postData['newmaterialid_' . $i], 'string');

                            if ($materialname == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_MaterialMaster');
                            $insert->Values(array('MaterialName' => $materialname, 'UnitId' => $unitid, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $generatedMaterialId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $NewMaterialIds[$newMaterialid] = $generatedMaterialId;

                            CommonHelper::insertCBLog('Material-Master-Add', $generatedMaterialId, $materialname, $dbAdapter);
                        }

                        //Deposit
                        $delete = $sql->delete();
                        $delete->from('CB_WODepositTrans')
                                ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $depositowid = $this->bsf->isNullCheck($postData['depositrowid'], 'number');
                        for ($i = 1; $i <= $depositowid; $i++) {
                            $depositType = $this->bsf->isNullCheck($postData['deposit_' . $i], 'string');
                            $depositmode = $this->bsf->isNullCheck($postData['paymode_' . $i], 'string');
                            $refno = $this->bsf->isNullCheck($postData['drefno_' . $i], 'string');
                            $refdate = $this->bsf->isNullCheck($postData['drefdate_' . $i], 'date');
                            $amount = $this->bsf->isNullCheck($postData['damount_' . $i], 'number');
                            $validupto = $this->bsf->isNullCheck($postData['dvaliddate_' . $i], 'date');

                            if ($depositType == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WODepositTrans');
                            $insert->Values(array('WORegisterId' => $woid, 'DepositType' => $depositType, 'DepostMode' => $depositmode, 'RefNo' => $refno, 'RefDate' => date('Y-m-d', strtotime($refdate)), 'Amount' => $amount, 'ValidUpto' => date('Y-m-d', strtotime($validupto))));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Other Terms fields
                        $delete = $sql->delete();
                        $delete->from('CB_WOOtherTerms')
                                ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOOtherTerms');
                            $insert->Values(array('WORegisterId' => $woid, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Tech Terms
                        $delete = $sql->delete();
                        $delete->from('CB_WOTechSpec')
                            ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $techrowid = $this->bsf->isNullCheck($postData['techrowid'], 'number');
                        for ($i = 1; $i <= $techrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['techTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['techDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOTechSpec');
                            $insert->Values(array('WORegisterId' => $woid, 'Title' => $TermsTitle, 'Specification' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Base Rate fields
                        $delete = $sql->delete();
                        $delete->from('CB_WOMaterialBaseRate')
                                ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $materialrowid = $this->bsf->isNullCheck($postData['materialrowid'], 'number');
                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialId_' . $i], 'string');
                            $Rate = $this->bsf->isNullCheck($postData['materialRate_' . $i], 'number');
                            $escper = $this->bsf->isNullCheck($postData['escalationper_' . $i], 'number');
                            $NewRate = $this->bsf->isNullCheck($postData['materialnewRate_' . $i], 'number');
                            $RateBase = $this->bsf->isNullCheck($postData['materialbase_' . $i], 'string');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOMaterialBaseRate');
                            $insert->Values(array('WORegisterId' => $woid, 'MaterialId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        // Material Exclude fields
                        $delete = $sql->delete();
                        $delete->from('CB_WOExcludeMaterial')
                                ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $materialExcrowid = $this->bsf->isNullCheck($postData['materialExcrowid'], 'number');
                        for ($i = 1; $i <= $materialExcrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialExcId_' . $i], 'string');
                            $MaterialRate = $this->bsf->isNullCheck($postData['materialExcRate_' . $i], 'number');
                            $SupplyType = $this->bsf->isNullCheck($postData['materialExcType_' . $i], 'string');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOExcludeMaterial');
                            $insert->Values(array('WORegisterId' => $woid, 'MaterialId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Advance fields
                        $delete = $sql->delete();
                        $delete->from('CB_WOMaterialAdvance')
                                ->where(array("WORegisterId" => $woid));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $materialAdvrowid = $this->bsf->isNullCheck($postData['materialAdvrowid'], 'number');
                        for ($i = 1; $i <= $materialAdvrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialAdvId_' . $i], 'string');
                            $Rate = $this->bsf->isNullCheck($postData['materialAdvRate_' . $i], 'number');

                            if (substr($MaterialId, 0, 3) == 'New')
                                $MaterialId = $NewMaterialIds[$MaterialId];

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('CB_WOMaterialAdvance');
                            $insert->Values(array('WORegisterId' => $woid, 'MaterialId' => $MaterialId, 'AdvPercent' => $Rate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //BillFormat
                        $billformatrowid = $this->bsf->isNullCheck($postData['billformatrowid'], 'number');

                        // delete format rows
                        if(isset($postData['deleteBillFormats']) && $postData['deleteBillFormats'] != NULL) {
                            $deleteBillFormats = $this->bsf->isNullCheck($postData['deleteBillFormats'], 'number');
                            $delete = $sql->delete();
                            $delete->from('CB_BillFormatTrans')
                                ->where("WorkOrderId = '$woid'");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $billformatrowdeleteids = rtrim($this->bsf->isNullCheck($postData['billformatrowdeleteids'],'string'), ",");
                        if($billformatrowdeleteids !== '') {
                            $delete = $sql->delete();
                            $delete->from('CB_BillFormatTrans')
                                ->where("BillFormatTransId IN ($billformatrowdeleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // insert or update format rows
                        for ($i = 1; $i <= $billformatrowid; $i++) {
                            $billFormatTransId = $this->bsf->isNullCheck($postData['billFormatTransId_' . $i], 'number');
                            $billformatid = $this->bsf->isNullCheck($postData['billformatid_' . $i], 'number');
                            $billformatsortid = $this->bsf->isNullCheck($postData['billformatsortid_' . $i], 'number');
                            $typename = $this->bsf->isNullCheck($postData['typename_' . $i], 'string');
                            $desc = $this->bsf->isNullCheck($postData['billformatdesc_' . $i], 'string');
                            $formula = $this->bsf->isNullCheck($postData['formula_' . $i], 'string');
                            $slno = $this->bsf->isNullCheck($postData['slno_' . $i], 'string');

                            $check_bold = isset($postData['textbold_' . $i]) ? 1 : 0;
                            $check_italic = isset($postData['textitalic_' . $i]) ? 1 : 0;
                            $check_underline = isset($postData['textunderline_' . $i]) ? 1 : 0;

                            if ($billformatid == 0 && $desc == '')
                                continue;

                            if($billFormatTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into( 'CB_BillFormatTrans' );
                                $insert->Values( array( 'WorkOrderId' => $woid, 'BillFormatId' => $billformatid, 'SortId' => $billformatsortid, 'Description' => $desc
                                                 , 'Formula' => $formula, 'Slno' => $slno, 'Bold' => $check_bold, 'Italic' => $check_italic, 'Underline' => $check_underline ) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            } else {
                                $update = $sql->update();
                                $update->table( 'CB_BillFormatTrans' );
                                $update->set( array('SortId' => $billformatsortid, 'Description' => $desc, 'Formula' => $formula
                                              , 'Slno' => $slno, 'Bold' => $check_bold, 'Italic' => $check_italic, 'Underline' => $check_underline ) )
                                    ->where(array('BillFormatTransId' => $billFormatTransId));
                                $statement = $sql->getSqlStringForSqlObject( $update );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }
                        }

                        //Mobilisation and other fields
                        $update = $sql->update();
                        $update->table('CB_WOTerms');
                        $update->set(array('MobilisationPercent' => $this->bsf->isNullCheck($postData['MobilisationPercent'], 'number')
                            , 'MobilisationAmount' => $this->bsf->isNullCheck($postData['MobilisationAmount'], 'number'), 'MobilisationRecovery' => $this->bsf->isNullCheck($postData['MobilisationRecovery'], 'number'), 'RetentionPercent' => $this->bsf->isNullCheck($postData['RetentionPercent'], 'number')
                            , 'PaymentSubmitPercent' => $this->bsf->isNullCheck($postData['PaymentSubmitPercent'], 'number'), 'PaymentFromSubmitDays' => $this->bsf->isNullCheck($postData['PaymentFromSubmitDays'], 'number')
                            , 'CertifyDays' => $this->bsf->isNullCheck($postData['CertifyDays'], 'number'), 'PaymentFromCertifyDays' => $this->bsf->isNullCheck($postData['PaymentFromCertifyDays'], 'number')
                            , 'PeriodType' => $this->bsf->isNullCheck($postData['billperiodtype'], 'number'), 'PeriodDay' => $this->bsf->isNullCheck($postData['billperiodday'], 'number')
                            , 'PeriodWeekDay' => $this->bsf->isNullCheck($postData['billperiodweekday'], 'number'), 'Notes' => $this->bsf->isNullCheck($postData['Notes'], 'string')));
                        $update->where(array('WORegisterId' => $woid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('WorkOrder-Edit', $woid, $sNewWONo, $dbAdapter);
                    }
                    $connection->commit();

                    // deleted files
                    if(!empty($delFilesUrl)) {
                        foreach($delFilesUrl as $url) {
                            unlink($url);
                        }
                    }

                    $this->redirect()->toRoute('cb/workorder', array('controller' => 'workorder', 'action' => 'index'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');

                if ($editid != 0) {
                    // New orders
                    $select = $sql->select();
                    $select->from(array("a" => "CB_WORegister"))
                            ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array("ProjectTypeId", "ProjectDescription", "ProjectName"), $select::JOIN_LEFT)
                            ->join(array('c' => 'CB_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                            ->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                                , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                                , "PeriodType", "Duration", "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')")
                                , "AuthorityName", "AuthorityAddress", "AgreementType", "BudgetType","BudgetAmount", "PeriodType", "Duration", "OrderAmount", "OrderPercent"
                                , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                    $select->where(array('a.DeleteFlag' => '0', 'a.WorkOrderId' => $editid));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // BOQ
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOBOQ"))
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('WOBOQId', 'WOBOQTransId', 'TransType', 'SortId', 'WorkGroupId', 'AgtNo', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'ClientRate', 'ClientAmount', 'Rate', 'Amount', 'RateVariance', 'Header','HeaderType', 'BudgetAmount'))
                            ->where("a.WORegisterId=$editid")
                            ->order('a.SortId');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //BillFormat
                    $select = $sql->select();
                    $select->from(array('a' => "CB_BillFormatTrans"))
                            ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('TypeName', 'RowName'), $select:: JOIN_LEFT)
                            ->columns(array('BillFormatId', 'SortId', 'SlNo', 'Description', 'Sign', 'Formula', 'Bold', 'Italic', 'Underline','BillFormatTransId'), array('TypeName', 'RowName'))
                            ->where("WorkOrderId=$editid");
					$select->order('a.SortId');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wobillformat = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Terms
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOTerms"))
                            ->columns(array('MobilisationPercent', 'MobilisationAmount', 'MobilisationRecovery', 'RetentionPercent', 'PaymentSubmitPercent', 'PaymentFromSubmitDays', 'CertifyDays', 'PaymentFromCertifyDays', 'Notes', 'PeriodType', 'PeriodDay', 'PeriodWeekDay'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->woterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //WOOtherTerms
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOOtherTerms"))
                            ->columns(array('TermsTitle', 'TermsDescription'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wootherterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOTechSpec
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOTechSpec"))
                            ->columns(array('Title', 'Specification'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wotechterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialBaseRate
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOMaterialBaseRate"))
                            ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId', 'Rate', 'EscalationPer', 'RateCondition', 'ActualRate'), array('MaterialName'), array('UnitName'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialbase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialExclude
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOExcludeMaterial"))
                            ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId', 'Rate', 'SType'), array('MaterialName'), array('UnitName'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialexcl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialAdvance
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WOMaterialAdvance"))
                            ->join(array('b' => 'CB_MaterialMaster'), 'a.MaterialId=b.MaterialId', array('MaterialName'), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId', 'AdvPercent'), array('MaterialName'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialadv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WODocuments
                    $select = $sql->select();
                    $select->from(array('a' => "CB_WODocuments"))
                            ->columns(array('DocumentId', 'Type', 'Description', 'URL'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wodocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => "CB_WODepositTrans"))
                            ->columns(array('DepositType', 'DepostMode', 'RefNo', 'RefDate', 'Amount', 'ValidUpto'))
                            ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wodeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $session_pref = new Container('subscriber_pref');
                $this->_view->NoOfClientCount = $session_pref->NoOfClientCount;

                // Projects
                $select = $sql->select();
                $select->from('CB_ProjectMaster')
                        ->columns(array('data' => 'ProjectId', 'value' => 'ProjectName'))
                        ->where("DeleteFlag=0 and SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Project Types
                $select = $sql->select();
                $select->from('CB_ProjectTypeMaster')
                        ->columns(array('data' => 'ProjectTypeId', 'value' => 'ProjectTypeName'))
                        ->where("DeleteFlag=0 and SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Clients
                $select = $sql->select();
                $select->from('CB_ClientMaster')
                        ->columns(array('data' => 'ClientId', 'value' => 'ClientName'))
                        ->where("DeleteFlag=0 and SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->clients = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // City List
                $select = $sql->select();
                $select->from('WF_CityMaster')
                        ->columns(array('CityId', 'CityName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->citylists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Unit List
                $select = $sql->select();
                $select->from('Proj_UOM')
                        ->columns(array("data" => 'UnitId', "value" => 'UnitName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Material List
                $select = $sql->select();
                $select->from(array('a' => "CB_MaterialMaster"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'MaterialId', "value" => 'MaterialName'))
                        ->where("DeleteFlag=0 and SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //BillFormatMaster
                $select = $sql->select();
                $select->from('CB_BillFormatMaster')
                        ->columns(array("data" => 'BillFormatId', "rowname" => 'RowName', "value" => 'TypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->billformatlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Deposit
                $select = $sql->select();
                $select->from('CB_DepositMaster')
                        ->columns(array("data" => 'TransId', "value" => 'DepositType'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->depositlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('CB_PaymentModeMaster')
                        ->columns(array("data" => 'TransId', "value" => 'PaymentMode'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->paymentmode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //WOData
                $select = $sql->select();
                $select->from(array('a' => "CB_WORegister"))
                        ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
                        ->columns(array('WorkOrderId', 'WONo'), array('ProjectName'))
                        ->where("a.DeleteFlag=0 and a.SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $wodataresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $newArray = array("WorkOrderId" => "0", "WONo" => "Default", "ProjectName" => "Default");
                array_unshift($wodataresults, $newArray);
                $this->_view->wodata = $wodataresults;

                $this->_view->workorderid = $editid;
                $this->_view->mode = $mode;
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function checkforusageAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $checkFor = $this->bsf->isNullCheck($this->params()->fromPost('checkfor'), 'string');
                $woId = $this->bsf->isNullCheck($this->params()->fromPost('woid'), 'number');
                $materialId = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');

                $data ='N';
                $select = $sql->select();
                switch ($checkFor) {
                    case 'PriceEscalation':
                        $tableName = 'CB_BillPriceEscalation';
                        break;
                    case 'MaterialAdvance':
                        $tableName = 'CB_BillMaterialAdvance';
                        break;
                    case 'ClientSupply':
                        $type = $this->bsf->isNullCheck($this->params()->fromPost('type'), 'string');
                        if($type == 'C') {// chargeable material
                            $tableName = 'CB_BillMaterialRecovery';
                        } else if($type == 'F') { // free supply material
                            $tableName = 'CB_BillFreeSupplyMaterial';
                        }
                        break;
                    default:
                        return $this->getResponse()->setContent($data);
                        break;
                }

                $select->from( array( 'a' => $tableName ) )
                    ->join( array( 'b' => 'CB_BillAbstract' ), 'a.BillAbsId=b.BillAbsId', array( 'BillAbsId' ), $select:: JOIN_LEFT )
                    ->join( array( 'c' => 'CB_BillMaster' ), 'b.BillId=c.BillId', array( 'BillId' ), $select:: JOIN_LEFT )
                    ->columns( array( "MaterialId" ) )
                    ->where( "c.WORegisterId = $woId and a.MaterialId = $materialId and c.DeleteFlag = '0' and c.SubscriberId =$subscriberId" )
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject( $select );
                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                if ($results)
                    $data = 'Y';

                return $this->getResponse()->setContent($data);
            }
        }
    }

    public function checkbillformatusageAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $woId = $this->bsf->isNullCheck($this->params()->fromPost('woid'), 'number');
                $BillFormatTransId = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');

                $data ='N';

                $select = $sql->select();
                $select->from( array( 'a' => 'CB_BillAbstract' ) )
                    ->join( array( 'b' => 'CB_BillMaster' ), 'a.BillId=b.BillId', array( 'BillId' ), $select:: JOIN_LEFT )
                    ->columns( array( "BillAbsId" ) )
                    ->where( "b.WORegisterId = $woId and a.BillFormatTransId = $BillFormatTransId and b.DeleteFlag = '0' and b.SubscriberId =$subscriberId
                                and (a.CumAmount <> '0' or a.PrevAmount <> '0' or a.CurAmount <> '0' or a.CerCumAmount <> '0' or a.CerPrevAmount <> '0' or a.CerCurAmount <> '0')" )
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject( $select );
                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                if ($results)
                    $data = 'Y';

                return $this->getResponse()->setContent($data);
            }
        }
    }

    public function checkboqusageAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $woId = $this->bsf->isNullCheck($this->params()->fromPost('woid'), 'number');
                $woboqId = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');
                $transType = $this->bsf->isNullCheck($this->params()->fromPost('transType'), 'string');

                $data ='N';

                $select = $sql->select();
                $select->from( array( 'a' => 'CB_BillBOQ' ) )
                    ->join( array( 'c' => 'CB_BillAbstract' ), 'c.BillAbsId=a.BillAbsId', array( 'BillAbsId' ), $select:: JOIN_LEFT )
                    ->join( array( 'b' => 'CB_BillMaster' ), 'c.BillId=b.BillId', array( 'BillId' ), $select:: JOIN_LEFT )
                    ->columns( array( "BillAbsId" ) )
                    ->where( "b.WORegisterId = $woId and a.WOBOQId = $woboqId and b.DeleteFlag = '0' and b.SubscriberId = $subscriberId" );
                $statement = $sql->getSqlStringForSqlObject( $select );
                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                if (sizeof($results) != 0)
                    $data = 'Y';

                return $this->getResponse()->setContent($data);
            }
        }
    }

    public function checkprojectfoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $projectName = $this->bsf->isNullCheck($this->params()->fromPost('ProjectName'), 'string');
                $select = $sql->select();
                $select->from('CB_ProjectMaster')
                    ->columns( array( 'ProjectId'))
                    ->where( "ProjectName='$projectName' AND DeleteFlag=0 AND SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

    public function checkmaterialfoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())	{
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $materialName = $this->bsf->isNullCheck($this->params()->fromPost('MaterialName'), 'string');
                $select = $sql->select();
                $select->from('CB_MaterialMaster')
                    ->columns( array( 'MaterialId'))
                    ->where( "MaterialName='$materialName' AND DeleteFlag=0 AND SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

    public function editheaderAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation
        if ($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if ($this->getRequest()->isXmlHttpRequest()) {
                    // AJAX
                    $response->setStatusCode(401)
                            ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
                    return;
                }
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Work Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            
            $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
            if ($request->isPost()) {
                $postData = $request->getPost();
                
                $WorkOrderId = $this->bsf->isNullCheck($postData['WorkOrderId'], 'number');
                $WONo = $this->bsf->isNullCheck($postData['WONo'], 'string');
                $WODate = $this->bsf->isNullCheck($postData['WODate'], 'date');
                
                $update = $sql->update();
                $update->table('CB_WORegister')
                    ->set(array('WONo' => $WONo, 'WODate' => $WODate))
                    ->where(array('WorkOrderId' => $WorkOrderId))
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        } else {
            $request = $this->getRequest();
            
            $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
            if ($request->isPost()) {
                    
            } else {
                $select = $sql->select();
                $select->from(array("a" => "CB_WORegister"))
                        ->join(array('b' => 'CB_ProjectTypeMaster'), 'b.ProjectTypeId=a.ProjectTypeId', array("ProjectTypeName"), $select::JOIN_LEFT)
                        ->join(array('c' => 'CB_ClientMaster'), 'c.ClientId=a.ClientId', array("ClientName"), $select::JOIN_LEFT)
                        ->join(array('d' => 'CB_ProjectMaster'), 'a.ProjectId=d.ProjectId', array('ProjectName', 'ProjectDescription'), $select:: JOIN_LEFT)
                        ->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')"), 'WONo'));
                $select->where(array('a.DeleteFlag' => '0', 'a.WorkOrderId' => $editid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }
        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function uploadboqdataAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation
        if ($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if ($this->getRequest()->isXmlHttpRequest()) {
                    // AJAX
                    $response->setStatusCode(401)
                            ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
                }
            }
        }

        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $uploadedFile = $request->getFiles();
                $postData = $request->getPost();
                $RType = $postData['arrHeader'];

                if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                    $response->setContent('Invalid File Format');
                    $response->setStatusCode(400);
                    return $response;
                }

                try {
                    $file_csv = "public/uploads/cb/tmp/" . md5(time()) . ".csv";
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
                                    if (trim($sField) == "Agt No")
                                        $col_1 = $j;
                                    if (trim($sField) == "Specification")
                                        $col_2 = $j;
                                    if (trim($sField) == "Short Spec")
                                        $col_3 = $j;
                                    if (trim($sField) == "Unit")
                                        $col_4 = $j;
                                    if (trim($sField) == "Qty")
                                        $col_5 = $j;
                                    if (trim($sField) == "Client Rate")
                                        $col_6 = $j;
                                    if (trim($sField) == "Client Amount")
                                        $col_7 = $j;
                                    if (trim($sField) == "Rate")
                                        $col_8 = $j;
                                    if (trim($sField) == "Amount")
                                        $col_9 = $j;
                                    if (trim($sField) == "WorkGroup")
                                        $col_11 = $j;
                                    if (trim($sField) == "Budget")
                                        $col_12 = $j;
                                }
                            }
                        } else {
                            if (!isset($col_1) || !isset($col_2) || !isset($col_4) || !isset($col_5) || !isset($col_8) || !isset($col_9)) {
                                $bValid = false;
                                break;
                            }

                            // check for null
                            if (is_null($col_1) || is_null($col_2) || is_null($col_4) || is_null($col_5) || is_null($col_8) || is_null($col_9)) {
                                $bValid = false;
                                break;
                            }

                            $sworkgroup = "";
                            $shortSpec="";
                            $iUnitId=0;
                            $iWorkGroupId=0;
                            $dCAmt=0;
                            $dCRate=0;
                            $dvar=0;

                            if (isset($col_3) && !is_null($col_3))
                                $shortSpec=$this->bsf->isNullCheck($xlData[$col_3], 'string');

                            if (isset($col_6) && !is_null($col_6))
                                $dCRate=$this->bsf->isNullCheck($xlData[$col_6], 'number');

                            if (isset($col_7) && !is_null($col_7))
                                $dCAmt=$this->bsf->isNullCheck($xlData[$col_7], 'number');

                            if (isset($col_11) && !is_null($col_11))
                                $sworkgroup =$this->bsf->isNullCheck($xlData[$col_11], 'string');

                            if(isset($xlData[$col_4]) && !is_null($xlData[$col_4])) {
                                $select = $sql->select();
                                $select->from( 'Proj_UOM' )
                                    ->columns( array( 'UnitId', 'UnitName' ) )
                                    ->where( array( "UnitName='$xlData[$col_4]'" ) );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                $row = $results->current();
                                if ( !empty( $row ) )
                                    $iUnitId = $row[ 'UnitId' ];
                            }
                            $select = $sql->select();
                            $select->from('CB_WorkGroupMaster')
                                    ->columns(array('WorkGroupId', 'WorkGroupName'))
                                    ->where(array("WorkGroupName='$sworkgroup'"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $workgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            if (!empty($workgroup))
                                $iWorkGroupId =  $workgroup['WorkGroupId'];

                            $agtNo = '';
                            if(isset($xlData[$col_1]) && !is_null($xlData[$col_1]))
                                $agtNo = $this->bsf->isNullCheck($xlData[$col_1], 'string');

                            $Spec = '';
                            if(isset($xlData[$col_2]) && !is_null($xlData[$col_2]))
                                $Spec = $this->bsf->isNullCheck($xlData[$col_2], 'string');

                            $Unit = '';
                            if(isset($xlData[$col_4]) && !is_null($xlData[$col_4]))
                                $Unit = $this->bsf->isNullCheck($xlData[$col_4], 'string');

                            $qty = 0;
                            if(isset($xlData[$col_5]) && !is_null($xlData[$col_5]))
                                $qty = $this->bsf->isNullCheck($xlData[$col_5], 'number');

                            $Rate = 0;
                            if(isset($xlData[$col_8]) && !is_null($xlData[$col_8]))
                                $Rate = $this->bsf->isNullCheck($xlData[$col_8], 'number');

                            $Amount = 0;
                            if(isset($xlData[$col_9]) && !is_null($xlData[$col_9]))
                                $Amount = $this->bsf->isNullCheck($xlData[$col_9], 'number');

                            $Budget = 0;
                            $col_12 = '';
                            if(isset($xlData[$col_12]) && !is_null($xlData[$col_12]))
                                $Budget = $this->bsf->isNullCheck($xlData[$col_12], 'number');

                            $data[] = array('Valid' => $bValid, 'AgtNo' => $agtNo, 'Spec' => $Spec, 'WorkGroup' => $sworkgroup, 'WorkGroupId' => $iWorkGroupId,
                                     'ShortSpec' => $shortSpec, 'UnitId' => $iUnitId, 'Unit' => $Unit, 'Qty' => $qty,
                                     'CRate' => $dCRate, 'CAmount' => $dCAmt, 'Rate' => $Rate, 'Amount' => $Amount, 'RVariance' => $dvar, 'Budget' => $Budget);
                        }
                        $icount = $icount + 1;
                    }

                    if ($bValid == false) {
                        $data[] = array('Valid' => $bValid);
                    }

                    // delete csv file
                    fclose($file);
                    unlink($file_csv);
                } catch (Exception $ex) {
                    $data[] = array('Valid' => $bValid);
                }

                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    public function getboqfielddataAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        // csrf validation
        if ($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if ($this->getRequest()->isXmlHttpRequest()) {
                    // AJAX
                    $response->setStatusCode(401)
                            ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
                }
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

                $file_csv = "public/uploads/cb/tmp/" . md5(time()) . ".csv";
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

    function _convertXLStoCSV($infile, $outfile) {

        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);
        $objPHPExcel->setActiveSheetIndex(0);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);
    }

    function _validateUploadFile($file) {
        $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
        $mime_types = array('application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel');
        $exts = array('csv', 'xls', 'xlsx');

        if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
            return false;

        return true;
    }

    public function registerAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || WorkOrder Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $select = $sql->select();
        $select->from(array("a" => "CB_WORegister"))
                ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array('ProjectName', 'ProjectDescription'), $select::JOIN_LEFT)
                ->join(array('d' => 'CB_ClientMaster'), 'a.ClientId=d.ClientId', array('ClientName'), $select::JOIN_LEFT)
                ->join(array("c" => "CB_ProjectTypeMaster"), "b.ProjectTypeId =c.ProjectTypeId", array("ProjectTypeName"), $select::JOIN_LEFT)
                ->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                    , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                    , "PeriodType", "Duration", "OrderAmount"))
                ->where("a.DeleteFlag='0' and A.LiveWO ='0' and a.SubscriberId=$subscriberId")
                ->order('a.WorkOrderId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('CB_WORegister')
                ->columns(array('Orders' => new Expression("Count(WorkOrderId)")))
                ->where("DeleteFlag='0' and LiveWO ='0' and SubscriberId =$subscriberId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ordercount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('CB_WORegister')
                ->columns(array('OrderAmt' => new Expression("Sum(OrderAmount)")))
                ->where("DeleteFlag='0' and LiveWO ='0' and SubscriberId =$subscriberId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ordervalue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' =>'CB_BillAbstract'))
                ->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_LEFT)
                ->columns(array('CurAmount' => new Expression("Sum(CerCurAmount)")))
                ->where("a.BillFormatId='1' AND b.SubscriberId =$subscriberId AND b.DeleteFlag='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->workvalue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
                ->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                ->columns(array('Amount' => new Expression("sum(OrderAmount)")), array('ClientName'))
                ->where("a.DeleteFlag='0' and a.LiveWO ='0' and a.SubscriberId =$subscriberId")
                ->group('ClientName');

        $select1 = $sql->select();
        $select1->from(array("g" => $select))
                ->columns(array('*'))
                ->order('g.Amount Desc')
                ->limit(5);
        $statement = $sql->getSqlStringForSqlObject($select1);
        $this->_view->clientorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
                ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'CB_ProjectTypeMaster'), 'b.ProjectTypeId=c.ProjectTypeId', array('ProjectTypeName'), $select:: JOIN_LEFT)
                ->columns(array('Amount' => new Expression("sum(OrderAmount)")), array('ClientName'))
                ->where("a.DeleteFlag='0' and b.DeleteFlag='0' and c.DeleteFlag='0' and a.LiveWO ='0' and a.SubscriberId =$subscriberId")
                ->group(new Expression('c.ProjectTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projecttypeorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
                ->columns(array('Mon' => new Expression("month(WODate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),3) + '-' + ltrim(str(Year(WODate)))"), 'Amount' => new Expression("sum(OrderAmount)")))
                ->where("a.DeleteFlag='0' and a.LiveWO ='0' and a.SubscriberId =$subscriberId")
                ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),3),Year(WODate)'));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->monorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getbillformatdetailsAction() {
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
                $woId = $postParams['woId'];

                $select = $sql->select();
                //Select ,TypeName,Description,Formula,Bold,Italic,Underline from CB_BillFormatTrans
                if ($woId == 0) {
                    $select->from(array('a' => 'CB_BillFormatTemplate'))
                            ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                            ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'));
                } else {
                    $select->from(array('a' => 'CB_BillFormatTrans'))
                            ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                            ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'))
                            ->where(array("WorkOrderId=$woId"));
                }

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $data = array();
                $data['trans'] = $results;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    public function getbillformattestAction() {

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $woId = $postParams['woId'];
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            //Select ,TypeName,Description,Formula,Bold,Italic,Underline from CB_BillFormatTrans
            if ($woId == 0) {
                $select->from(array('a' => 'CB_BillFormatTemplate'))
                        ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                        ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'));
            } else {
                $select->from(array('a' => 'CB_BillFormatTrans'))
                        ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                        ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'))
                        ->where(array("WorkOrderId=$woId"));
            }

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data = array();
            $data['trans'] = $results;
            $response = $this->getResponse();
            $response->setContent(json_encode($data));
            return $response;
        }
    }

    public function deleteorderAction() {
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
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $WorkOrderId = $this->bsf->isNullCheck($this->params()->fromPost('WorkOrderId'), 'number');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('CB_WoRegister')
                        ->columns(array('WONo'))
                        ->where(array("WorkOrderId" => $WorkOrderId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $sWONo="";
                    if (!empty($bills)) { $sWONo = $bills->WONo; }

                    switch($Type) {
                        case 'check':
                            // check for client billing
                            $select = $sql->select();
                            $select->from('CB_BillMaster')
                                ->columns(array('BillId'))
                                ->where(array('DeleteFlag' => '0', "WORegisterId" => $WorkOrderId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if (count($bills)) {
                                $response->setStatusCode('403');
                                $response->setContent('Not able to delete this work order, since there were client billing entries!');
                                return $response;
                            }

                            // check for receipts
                            $select = $sql->select();
                            $select->from('CB_ReceiptRegister')
                                ->columns(array('ReceiptId'))
                                ->where(array('DeleteFlag' => '0', "WORegisterId" => $WorkOrderId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $receipts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            if (count($receipts)) {
                                $response->setStatusCode('403');
                                $response->setContent('Not able to delete this work order, since there were receipts entries!');
                                return $response;
                            }

                            $response->setStatusCode('200');
                            $response->setContent('Not used');
                            return $response;
                            break;
                        case 'update':
                            $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), "string");

                            $select = $sql->select();
                            $select->from('CB_WORegister')
                                ->columns(array('WorkOrderId', 'PWorkOrderId'))
                                ->where(array('WorkOrderId' => $WorkOrderId))
                                ->order('WorkOrderId desc');
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $PrevOrder = intval($bills->PWorkOrderId);
                            $CurOrder = intval($bills->WorkOrderId);

                            $update = $sql->update();
                            $update->table('CB_WORegister')
                                ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('WorkOrderId' => $WorkOrderId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if ($PrevOrder != 0) {
                                $update = $sql->update();
                                $update->table('CB_WORegister')
                                    ->set(array('LiveWO' => '0'))
                                    ->where(array('WorkOrderId' => $CurOrder));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            CommonHelper::insertCBLog('WorkOrder-Delete', $WorkOrderId, '$sWONo', $dbAdapter);
                            $connection->commit();
                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    public function dashboardcbAction() {

    }
    public function dashboardcb2Action() {

    }
}
