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
use Zend\Paginator\Paginator;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\Qualifier;
use Application\View\Helper\CommonHelper;

class TenderController extends AbstractActionController
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();

        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function enquiryAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);


        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $enqId = $this->bsf->isNullCheck($this->params()->fromRoute('TenderEnquiryId'), 'number');
                if($enqId == 0) {
                    $Name = $this->bsf->isNullCheck($request->getPost('name'), 'string');
                    $type = $this->bsf->isNullCheck($request->getPost('type'), 'string');
                    if($type == 'NameOfWork') {
                        $select = $sql->select();
                        $select->from('Proj_TenderEnquiry')
                            ->where(array("NameOfWork"=>$Name));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_Name = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($arr_Name) != 0) {
                            $nameExist = 'N';
                        } else {
                            $nameExist = 'Y';
                        }

                        $response = $this->getResponse();
                        $response->setContent(json_encode($nameExist));
                        return $response;
                    }
                }
            }
        } else {
            $aVNo = CommonHelper::getVoucherNo(103, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];

            $this->_view->genType = $aVNo["genType"];


            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    $files = $request->getFiles();

                    $newsDate = NULL;
                    if($postData['NewsDate']) {
                        $newsDate = date('Y-m-d', strtotime($postData['NewsDate']));
                    }

                    $iTenderEnquiryId = $this->bsf->isNullCheck($postData['TenderEnquiryId'], 'number');
                    $enquiryType = $this->bsf->isNullCheck($postData['enquiryType'],'string');

                    //Adding new client
                    if ($postData['ClientId'] == 'new') {
                        $clientName = $this->bsf->isNullCheck($postData['MClientName'], 'string');
                        $clientAddress = $this->bsf->isNullCheck($postData['MClientAddress'], 'string');
                        $cityName = $this->bsf->isNullCheck($postData['MCityId'], 'string');
                        $phoneNo = $this->bsf->isNullCheck($postData['phoneNo'], 'string');
                        $eMail = $this->bsf->isNullCheck($postData['eMail'], 'string');

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
                        if($cityId!=0){
                            $cityId=$cityId;
                        }else{
                            $cityId=0;
                        }
                        $insert = $sql->insert();
                        $insert->into('Proj_ClientMaster');
                        $insert->Values(array('ClientName' => $clientName, 'Address' => $clientAddress, 'CityId' => $cityId,'EMail'=>$eMail,'Phone'=>$phoneNo,));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //CommonHelper::insertCBLog('Client-Master-Add', $clientId, $clientName, $dbAdapter);
                    } else {
                        $clientId = $this->bsf->isNullCheck($postData['ClientId'], 'number');
                        $clientName = $this->bsf->isNullCheck($postData['ClientName'], 'string');
                    }

                    if ($iTenderEnquiryId != 0) {
                        //update
                        $update = $sql->update();
                        $update->table('Proj_TenderEnquiry');

                        if ($enquiryType =='E') {
                            $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                            , 'EnquiryType' => $enquiryType
                            , 'NameOfWork' => "Additional-Work - " . $this->bsf->isNullCheck($postData['projectName'], 'string')
                            , 'QuoteType' => $this->bsf->isNullCheck($postData['quotetype'], 'string')
                            , 'CostCentreId' => $this->bsf->isNullCheck($postData['costcentreId'], 'number')
                            , 'ClientId' => $this->bsf->isNullCheck($postData['pclientId'], 'number')
                            , 'WORegisterId' => $this->bsf->isNullCheck($postData['workorderId'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        } else {
                            $update->set(array('RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                            , 'EnquiryType' => $enquiryType
                            , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'], 'string')
                            , 'SourceType' => $this->bsf->isNullCheck($postData['SourceType'], 'number')
                            , 'SourceName' => $this->bsf->isNullCheck($postData['SourceName'], 'string')
                            , 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'], 'string')
                            , 'NewsDate' => $newsDate
                            , 'NewsPageNo' => $this->bsf->isNullCheck($postData['NewsPageNo'], 'number')
                            , 'ClientId' => $clientId
                            , 'ClientName' => $clientName
                            , 'CityId' => $this->bsf->isNullCheck($postData['pcityid'], 'number')
                            , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'], 'number')
                            , 'Location' => $this->bsf->isNullCheck($postData['plocation'], 'string')
                            , 'ProjectTypeName' => $this->bsf->isNullCheck($postData['projectTypeName'], 'string')
                            , 'ProposalCost' => $this->bsf->isNullCheck($postData['ProposalCost'], 'number')
                            , 'ProjectDuration' => $this->bsf->isNullCheck($postData['ProjectDuration'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'Duration' => $this->bsf->isNullCheck($postData['Duration'], 'string')));
                        }

                        $update->where(array('TenderEnquiryId'=>$iTenderEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $sessionTender = new Container('sessionTender');
                        $sessionTender->enquiryId = $iTenderEnquiryId;
                        $sessionTender->edit = 'yes';

                        /*	if($files['tenderDocument']['name']){
                                $dir = 'public/uploads/project/tender/';
                                if(!is_dir($dir))
                                    mkdir($dir, 0755, true);

                                $docExt = pathinfo($files['tenderDocument']['name'], PATHINFO_EXTENSION);
                                $path = $dir.$tenderenquiryId.'.'.$docExt;
                                move_uploaded_file($files['tenderDocument']['tmp_name'], $path);

                                $updateDocExt = $sql->update();
                                $updateDocExt->table('Proj_TenderEnquiry');
                                $updateDocExt->set(array(
                                            'DocExt' => $this->bsf->isNullCheck($docExt,'string')
                                        ))
                                        ->where(array('TenderEnquiryId'=>$iTenderEnquiryId));
                                $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            }*/
                        //Delete and insert Document
                        $delete = $sql->delete();
                        $delete->from('Proj_TenderDocumentTrans')
                            ->where("TenderEnquiryId=$iTenderEnquiryId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $documentRowId = $this->bsf->isNullCheck($postData['tenderdocumentrowid'], 'number');
                        for ($i = 1; $i <= $documentRowId; $i++) {
                            $type = $this->bsf->isNullCheck($postData['DocType_' . $i], 'string');
                            $desc = $this->bsf->isNullCheck($postData['DocDesc_' . $i], 'string');
                            $url = $this->bsf->isNullCheck($postData['tenderDocFile_' . $i], 'string');

                            if ($type == "" || $desc == "")
                                continue;

                            if($url == '') {
                                if ($files['tenderDocFile_' . $i]['name']) {

                                    $dir = 'public/uploads/project/tenderenquiry/' . $iTenderEnquiryId . '/';
                                    $filename = $this->bsf->uploadFile($dir, $files['tenderDocFile_' . $i]);

                                    if ($filename) {
                                        // update valid files only
                                        $url = '/uploads/project/tenderenquiry/' . $iTenderEnquiryId . '/' . $filename;
                                    }
                                }
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderDocumentTrans');
                            $insert->Values(array('TenderEnquiryId' => $iTenderEnquiryId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        if ($enquiryType =="E") $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'tenderregister'));
                        else $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'enquiry-details'));
                    } else {

                        //Insert
                        $aVNo = CommonHelper::getVoucherNo(103, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == false)
                            $RefNo = $postData['RefNo'];
                        else
                            $RefNo = $aVNo["voucherNo"];

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderEnquiry');
                        if ($enquiryType =='E') {
                            $insert->Values(array('RefNo' => $this->bsf->isNullCheck($RefNo, 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                            , 'EnquiryType' => $enquiryType
                            , 'NameOfWork' => "NT - " . $this->bsf->isNullCheck($postData['projectName'], 'string')
                            , 'QuoteType' => $this->bsf->isNullCheck($postData['quotetype'], 'string')
                            , 'CostCentreId' => $this->bsf->isNullCheck($postData['costcentreId'], 'number')
                            , 'ClientId' => $this->bsf->isNullCheck($postData['pclientId'], 'number')
                            , 'WORegisterId' => $this->bsf->isNullCheck($postData['workorderId'], 'number')
                            , 'RefEnquiryId' => $this->bsf->isNullCheck($postData['refenquiryId'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        } else {
                            $insert->Values(array('RefNo' => $this->bsf->isNullCheck($RefNo, 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))
                            , 'EnquiryType' => $enquiryType
                            , 'NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'], 'string')
                            , 'SourceType' => $this->bsf->isNullCheck($postData['SourceType'], 'number')
                            , 'SourceName' => $this->bsf->isNullCheck($postData['SourceName'], 'string')
                            , 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'], 'string')
                            , 'NewsDate' => $newsDate
                            , 'NewsPageNo' => $this->bsf->isNullCheck($postData['NewsPageNo'], 'number')
                            , 'ClientId' => $clientId
                            , 'ClientName' => $clientName
                            , 'CityId' => $this->bsf->isNullCheck($postData['pcityid'], 'number')
                            , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'], 'number')
                            , 'Location' => $this->bsf->isNullCheck($postData['plocation'], 'string')
                            , 'ProjectTypeName' => $this->bsf->isNullCheck($postData['projectTypeName'], 'string')
                            , 'ProposalCost' => $this->bsf->isNullCheck($postData['ProposalCost'], 'number')
                            , 'ProjectDuration' => $this->bsf->isNullCheck($postData['ProjectDuration'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'Duration' => $this->bsf->isNullCheck($postData['Duration'], 'string')));
                        }
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $tenderenquiryId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $sessionTender = new Container('sessionTender');
                        $sessionTender->enquiryId = $tenderenquiryId ;
                        $sessionTender->edit = 'no';

                        /*if($files['tenderDocument']['name']){
                              $dir = 'public/uploads/project/tender/';
                              if(!is_dir($dir))
                                  mkdir($dir, 0755, true);

                              $docExt = pathinfo($files['tenderDocument']['name'], PATHINFO_EXTENSION);
                              $path = $dir.$tenderenquiryId .'.'.$docExt;
                              move_uploaded_file($files['tenderDocument']['tmp_name'], $path);

                              $updateDocExt = $sql->update();
                              $updateDocExt->table('Proj_TenderEnquiry');
                              $updateDocExt->set(array(
                                          'DocExt' => $this->bsf->isNullCheck($docExt,'string')
                                      ))
                                      ->where(array('TenderEnquiryId'=>$tenderenquiryId));
                              $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                              $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                          }*/

                        //Documents
                        $documentRowId = $this->bsf->isNullCheck($postData['tenderdocumentrowid'], 'number');
                        for ($i = 1; $i <= $documentRowId; $i++) {
                            $type = $this->bsf->isNullCheck($postData['DocType_' . $i], 'string');
                            $desc = $this->bsf->isNullCheck($postData['DocDesc_' . $i], 'string');

                            if ($type == "" || $desc == "")
                                continue;

                            $url = '';

                            if ($files['tenderDocFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/tenderenquiry/' . $tenderenquiryId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['tenderDocFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/tenderenquiry/' . $tenderenquiryId . '/' . $filename;
                                }
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderDocumentTrans');
                            $insert->Values(array('TenderEnquiryId ' => $tenderenquiryId, 'Type' => $type, 'Description' => $desc, 'URL' => $url));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        if ($enquiryType =="E") $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'tenderregister'));
                        else $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'enquiry-details'));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                }
            }

            //Fetching data from City Master
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId','CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->cityMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Clients
            $select = $sql->select();
            $select->from('Proj_ClientMaster')
                ->columns(array('data' => 'ClientId', 'value' => 'ClientName'))
                ->where("DeleteFlag = 0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->clients = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array('ClientId','TenderEnquiryId'), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId', array(), $select:: JOIN_INNER)
                ->columns(array('data' => 'CostCentreId', 'value' => new Expression("a.CostCentreName"),'WORegisterId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projectTypeSelect = $sql->select();
            $projectTypeSelect->from(array("a"=>"Proj_ProjectTypeMaster"))
                ->columns(array('data'=>"ProjectTypeId",'value'=>"ProjectTypeName"));
            $projectTypeStmt = $sql->getSqlStringForSqlObject($projectTypeSelect);
            $this->_view->projectTypeResult = $dbAdapter->query($projectTypeStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $tenderEnquiryId = $this->params()->fromRoute('TenderEnquiryId');

            if (isset($tenderEnquiryId) && $tenderEnquiryId != 0 ) {
                $select = $sql->select();

                $select->from(array('a' => 'Proj_TenderEnquiry'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'WF_CityMaster'), 'a.CityId=c.CityId', array('CityName'=>new Expression("Case When a.CityId=0 then a.Location else c.CityName end")  ), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=d.ProjectTypeId', array('ProjectTypeName'=>new Expression("Case when a.ProjectTypeId=0 then A.ProjectTypeName else d.ProjectTypeName end")), $select:: JOIN_LEFT)
                    ->where('TenderEnquiryId=' . $tenderEnquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_TenderDocumentTrans')
                    ->where('TenderEnquiryId=' . $tenderEnquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderDocumentTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }

            $this->_view->tenderEnquiryId = (isset($tenderEnquiryId) && $tenderEnquiryId != 0) ? $tenderEnquiryId : 0;

            $this->_view->sourceType = $this->bsf->getSourceType();
            return $this->_view;
        }
    }

    public function enquiryDetailsAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $sessionTender = new Container('sessionTender');
        $enquiryId = $sessionTender->enquiryId;
        $enquiryMode = $sessionTender->edit;
        $addCl = $sessionTender->addCl;

        $this->_view->addCl = (isset($addCl) && $addCl != 0) ? $addCl : 0;
        $sessionTender->addCl = '';

        if($enquiryId != '') {
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Tender Enquiry");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            //Fetching data from CheckList Master
            $select = $sql->select();
            $select->from('Proj_CheckListMaster')
                ->columns(array('CheckListId','CheckListName'))
                ->where(array("TypeId = 5", "DeleteFlag = 0"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $checkListMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->checkListMaster = $checkListMaster;

//            Select EnclosureId,EnclosureName from
            $select = $sql->select();
            $select->from('Proj_TenderEnclosureMaster')
                ->columns(array('EnclosureId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $enclosureList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from CheckList Master
            $select = $sql->select();
            $select->from('Proj_TenderProcessMaster')
                ->columns(array('TenderProcessId','ProcessName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $processMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->processMaster = $processMaster;

            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    //echo '<pre>'; print_r($postData); die;

                    $enquiryId = $postData['TenderEnquiryId'];
                    $actMode = $postData['actionMode'];

                    $newsDate = NULL;
                    if($postData['NewsDate']) {
                        $newsDate = date('Y-m-d', strtotime($postData['NewsDate']));
                    }

                    //Adding new client
                    if ($postData['ClientId'] == 'new') {
                        $clientName = $this->bsf->isNullCheck($postData['MClientName'], 'string');
                        $clientAddress = $this->bsf->isNullCheck($postData['MClientAddress'], 'string');
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
                        $insert->into('Proj_ClientMaster');
                        $insert->Values(array('ClientName' => $clientName, 'Address' => $clientAddress, 'CityId' => $cityId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //CommonHelper::insertCBLog('Client-Master-Add', $clientId, $clientName, $dbAdapter);
                    } else {
                        $clientId = $this->bsf->isNullCheck($postData['ClientId'], 'number');
                        $clientName = $this->bsf->isNullCheck($postData['ClientName'], 'string');
                    }

                    $updateEnquiry = $sql->update();
                    $updateEnquiry->table('Proj_TenderEnquiry');
                    $updateEnquiry->set(array('NameOfWork' => $this->bsf->isNullCheck($postData['NameOfWork'],'string')
                    , 'SourceType' => $this->bsf->isNullCheck($postData['SourceType'],'number')
                    , 'SourceName' => $this->bsf->isNullCheck($postData['SourceName'],'string')
                    , 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'],'string')
                    , 'NewsDate' =>  $newsDate
                    , 'NewsPageNo' => $this->bsf->isNullCheck($postData['NewsPageNo'],'number')
                    , 'ClientId' => $clientId
                    , 'ClientName' => $clientName
                    , 'CityId' => $this->bsf->isNullCheck($postData['pcityid'],'string')
                    , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')
                    , 'ProjectTypeName' => $this->bsf->isNullCheck($postData['projectTypeName'],'string')
                    , 'Location' => $this->bsf->isNullCheck($postData['plocation'],'string')
                    , 'ProposalCost' => $this->bsf->isNullCheck($postData['ProposalCost'],'number')
                    , 'ProjectDuration' => $this->bsf->isNullCheck($postData['ProjectDuration'],'number')
                    , 'Duration' => $this->bsf->isNullCheck($postData['Duration'],'string')
                    , 'Notes' => $this->bsf->isNullCheck($postData['Notes'],'string')))
                       ->where(array('TenderEnquiryId'=>$enquiryId));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateEnquiry);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    if($actMode == 'no') {
                        $insert = $sql->insert();
                        $insert->into('Proj_TenderDetails');
                        $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                        , 'TenderNo' => $this->bsf->isNullCheck($postData['TenderNo'],'string')
                        , 'TenderDate' =>  date('Y-m-d', strtotime($postData['TenderDate']))
                        , 'TenderProcessType' => $this->bsf->isNullCheck($postData['TenderProcessType'],'number')
                        , 'TenderTypeId' => $this->bsf->isNullCheck($postData['TenderTypeId'],'number')
//                        , 'ExistingContract' => $this->bsf->isNullCheck($postData['ExistingContract'],'number')
//                        , 'WorkOrderNo' => $this->bsf->isNullCheck($postData['WorkOrderNo'],'number')
                        , 'ProjectAddress' => $this->bsf->isNullCheck($postData['ProjectAddress'],'string')
                        , 'OfficeAddress' => $this->bsf->isNullCheck($postData['OfficeAddress'],'string')
                        , 'ContactPerson' => $this->bsf->isNullCheck($postData['ContactPerson'],'string')
                        , 'Designation' => $this->bsf->isNullCheck($postData['Designation'],'string')
                        , 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'],'string')
                        , 'ContactEmail' => $this->bsf->isNullCheck($postData['ContactEmail'],'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $mdvu = NULL;
                        $sdvu = NULL;
                        if($postData['MoneyDepositValidUpto']) {
                            $mdvu = date('Y-m-d', strtotime($postData['MoneyDepositValidUpto']));
                        }
                        if($postData['SecurityDepositValidUpto']) {
                            $sdvu = date('Y-m-d', strtotime($postData['SecurityDepositValidUpto']));
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderCostDetail');
                        $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                        , 'ReqAnyCostForDoc' => $this->bsf->isNullCheck($postData['ReqAnyCostForDoc'],'number')
                        , 'AnyCostForDocCost' => $this->bsf->isNullCheck($postData['AnyCostForDocCost'],'number')
                        , 'AnyCostForDocMOP' => $this->bsf->isNullCheck($postData['AnyCostForDocMOP'],'string')
                        , 'AnyCostForDocIFO' => $this->bsf->isNullCheck($postData['AnyCostForDocIFO'],'string')
                        , 'ReqMoneyDeposit' => $this->bsf->isNullCheck($postData['ReqMoneyDeposit'],'number')
                        , 'MoneyDepositCost' => $this->bsf->isNullCheck($postData['MoneyDepositCost'],'number')
                        , 'MoneyDepositMOP' => $this->bsf->isNullCheck($postData['MoneyDepositMOP'],'string')
                        , 'MoneyDepositIFO' => $this->bsf->isNullCheck($postData['MoneyDepositIFO'],'string')
                        , 'MoneyDepositValidUpto' => $mdvu
                        , 'ReqSecurityDeposit' => $this->bsf->isNullCheck($postData['ReqSecurityDeposit'],'number')
                        , 'SecurityDepositCost' => $this->bsf->isNullCheck($postData['SecurityDepositCost'],'number')
                        , 'SecurityDepositMOP' => $this->bsf->isNullCheck($postData['SecurityDepositMOP'],'string')
                        , 'SecurityDepositIFO' => $this->bsf->isNullCheck($postData['SecurityDepositIFO'],'string')
                        , 'SecurityDepositValidUpto' => $sdvu
                        , 'MobilisationAdvAvailable' => $this->bsf->isNullCheck($postData['MobilisationAdvAvailable'],'number')
                        , 'MobilisationAdvValue' => $this->bsf->isNullCheck($postData['MobilisationAdvValue'],'string')
                        , 'MobilisationAdvRemarks' => $this->bsf->isNullCheck($postData['MobilisationAdvRemarks'],'string')
                        , 'OtherCostDetail' => $this->bsf->isNullCheck($postData['OtherCostDetail'],'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $impDateCount = $postData['ImpDateCount'];
                        for($i=1;$i<=$impDateCount;$i++) {
                            if($this->bsf->isNullCheck($postData['TypeOfDate_'.$i],'string') != "") {
                                $tDate = NULL;
                                if($postData['TDate_'.$i]) {
                                    $tDate = date('Y-m-d', strtotime($postData['TDate_'.$i]));
                                }

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderDates');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                                , 'TypeOfDate' => $this->bsf->isNullCheck($postData['TypeOfDate_'.$i],'string')
                                , 'TDate' => $tDate
                                , 'PlaceOfAddress' => $this->bsf->isNullCheck($postData['PlaceOfAddress_'.$i],'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //$consultantCount = $postData['ConsultantCount'];
                        $consultantCount = $postData['consultantrowid'];
                        for($i=1;$i<=$consultantCount;$i++) {
                            if($this->bsf->isNullCheck($postData['ConsultantId_'.$i],'number') != 0) {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderConsultant');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                                , 'ConsultantId' => $this->bsf->isNullCheck($postData['ConsultantId_'.$i],'number')
                                , 'ConsultantType' => $this->bsf->isNullCheck($postData['ConsultantType_'.$i],'number')
                                , 'ConsultantAddress' => $this->bsf->isNullCheck($postData['ConsultantAddress_'.$i],'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if($this->bsf->isNullCheck($postData['ConsultantName_'.$i],'string') != '') {
                                    $insert = $sql->insert();
                                    $insert->into('Proj_ConsultantMaster');
                                    $insert->Values(array('ConsultantName' => $this->bsf->isNullCheck($postData['ConsultantName_' . $i], 'string')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $consultId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('Proj_TenderConsultant');
                                    $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                    , 'ConsultantId' => $this->bsf->isNullCheck($consultId, 'number')
                                    , 'ConsultantType' => $this->bsf->isNullCheck($postData['ConsultantType_' . $i], 'number')
                                    , 'ConsultantAddress' => $this->bsf->isNullCheck($postData['ConsultantAddress_' . $i], 'string')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderNotesTrans');
                            $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number'), 'NoteTitle' => $TermsTitle, 'NoteDescription' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        foreach($checkListMaster as $cl) {
                            $i = $cl['CheckListId'];
                            $tarDate = NULL;

                            if($this->bsf->isNullCheck($postData['chkList_'.$i],'number') != "") {
                                if ($postData['targetDate_' . $i] != '') {
                                    $tarDate = date('Y-m-d', strtotime($postData['targetDate_' . $i]));
                                }

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderCheckListTrans');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                , 'CheckListId' => $this->bsf->isNullCheck($postData['chkListId_' . $i], 'number')
                                , 'UserId' => $this->bsf->isNullCheck($postData['userId_' . $i], 'number')
                                , 'TargetDate' => $tarDate
                                , 'Critical' => $this->bsf->isNullCheck($postData['critical_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        foreach ($enclosureList as $encl) {
                            $i = $encl['EnclosureId'];
                            if ($this->bsf->isNullCheck($postData['checkenc_'.$i],'number') ==1) {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderEnclosureTrans');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                , 'EnclosureId' =>$i));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }


                        foreach($processMaster as $ps) {
                            $i = $ps['TenderProcessId'];
                            $tarDate = NULL;
                            $psStatus = 0;

                            if($this->bsf->isNullCheck($postData['proStep_'.$i],'number') != "") {
                                if ($postData['psTargetDate_' . $i] != '') {
                                    $tarDate = date('Y-m-d', strtotime($postData['psTargetDate_' . $i]));
                                }
                                if (isset($postData['psStatus_' . $i]) && $postData['psStatus_' . $i] == 1) {
                                    $psStatus = 1;
                                }

                                //, 'Critical' => $this->bsf->isNullCheck($postData['psCritical_' . $i], 'number')
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderProcessTrans');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                , 'TenderProcessId' => $this->bsf->isNullCheck($postData['proStepId_' . $i], 'number')
                                , 'UserId' => $this->bsf->isNullCheck($postData['psUserId_' . $i], 'number')
                                , 'TargetDate' => $tarDate
                                , 'Status' => $psStatus
                                , 'SortId' => $this->bsf->isNullCheck($postData['sortId_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $sessionTender->enquiryId = '';
                        $sessionTender->edit = '';

                        $connection->commit();
                        $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'enquiry'));

                    } else {
                        $updateEnquiry = $sql->update();
                        $updateEnquiry->table('Proj_TenderDetails');
                        $updateEnquiry->set(array('TenderNo' => $this->bsf->isNullCheck($postData['TenderNo'],'string')
                        , 'TenderDate' =>  date('Y-m-d', strtotime($postData['TenderDate']))
                        , 'TenderProcessType' => $this->bsf->isNullCheck($postData['TenderProcessType'],'number')
                        , 'TenderTypeId' => $this->bsf->isNullCheck($postData['TenderTypeId'],'number')
//                        , 'ExistingContract' => $this->bsf->isNullCheck($postData['ExistingContract'],'number')
//                        , 'WorkOrderNo' => $this->bsf->isNullCheck($postData['WorkOrderNo'],'number')
                        , 'ProjectAddress' => $this->bsf->isNullCheck($postData['ProjectAddress'],'string')
                        , 'OfficeAddress' => $this->bsf->isNullCheck($postData['OfficeAddress'],'string')
                        , 'ContactPerson' => $this->bsf->isNullCheck($postData['ContactPerson'],'string')
                        , 'Designation' => $this->bsf->isNullCheck($postData['Designation'],'string')
                        , 'ContactNo' => $this->bsf->isNullCheck($postData['ContactNo'],'string')
                        , 'ContactEmail' => $this->bsf->isNullCheck($postData['ContactEmail'],'string')))
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $updateStatement = $sql->getSqlStringForSqlObject($updateEnquiry);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $mdvu = NULL;
                        $sdvu = NULL;
                        if($postData['MoneyDepositValidUpto']) {
                            $mdvu = date('Y-m-d', strtotime($postData['MoneyDepositValidUpto']));
                        }
                        if($postData['SecurityDepositValidUpto']) {
                            $sdvu = date('Y-m-d', strtotime($postData['SecurityDepositValidUpto']));
                        }

                        $updateEnquiry = $sql->update();
                        $updateEnquiry->table('Proj_TenderCostDetail');
                        $updateEnquiry->set(array('ReqAnyCostForDoc' => $this->bsf->isNullCheck($postData['ReqAnyCostForDoc'],'number')
                        , 'AnyCostForDocCost' => $this->bsf->isNullCheck($postData['AnyCostForDocCost'],'number')
                        , 'AnyCostForDocMOP' => $this->bsf->isNullCheck($postData['AnyCostForDocMOP'],'string')
                        , 'AnyCostForDocIFO' => $this->bsf->isNullCheck($postData['AnyCostForDocIFO'],'string')
                        , 'ReqMoneyDeposit' => $this->bsf->isNullCheck($postData['ReqMoneyDeposit'],'number')
                        , 'MoneyDepositCost' => $this->bsf->isNullCheck($postData['MoneyDepositCost'],'number')
                        , 'MoneyDepositMOP' => $this->bsf->isNullCheck($postData['MoneyDepositMOP'],'string')
                        , 'MoneyDepositIFO' => $this->bsf->isNullCheck($postData['MoneyDepositIFO'],'string')
                        , 'MoneyDepositValidUpto' => $mdvu
                        , 'ReqSecurityDeposit' => $this->bsf->isNullCheck($postData['ReqSecurityDeposit'],'number')
                        , 'SecurityDepositCost' => $this->bsf->isNullCheck($postData['SecurityDepositCost'],'number')
                        , 'SecurityDepositMOP' => $this->bsf->isNullCheck($postData['SecurityDepositMOP'],'string')
                        , 'SecurityDepositIFO' => $this->bsf->isNullCheck($postData['SecurityDepositIFO'],'string')
                        , 'SecurityDepositValidUpto' => $sdvu
                        , 'MobilisationAdvAvailable' => $this->bsf->isNullCheck($postData['MobilisationAdvAvailable'],'number')
                        , 'MobilisationAdvValue' => $this->bsf->isNullCheck($postData['MobilisationAdvValue'],'string')
                        , 'MobilisationAdvRemarks' => $this->bsf->isNullCheck($postData['MobilisationAdvRemarks'],'string')))
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $updateStatement = $sql->getSqlStringForSqlObject($updateEnquiry);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderDates')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $impDateCount = $postData['ImpDateCount'];
                        for($i=1;$i<=$impDateCount;$i++) {
                            if($this->bsf->isNullCheck($postData['TypeOfDate_'.$i],'string') != "") {
                                $tDate = NULL;
                                if($postData['TDate_'.$i]) {
                                    $tDate = date('Y-m-d', strtotime($postData['TDate_'.$i]));
                                }

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderDates');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                                , 'TypeOfDate' => $this->bsf->isNullCheck($postData['TypeOfDate_'.$i],'string')
                                , 'TDate' => $tDate
                                , 'PlaceOfAddress' => $this->bsf->isNullCheck($postData['PlaceOfAddress_'.$i],'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderConsultant')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //$consultantCount = $postData['ConsultantCount'];
                        $consultantCount = $postData['consultantrowid'];
                        for($i=1;$i<=$consultantCount;$i++) {
                            if($this->bsf->isNullCheck($postData['ConsultantId_'.$i],'number') != "") {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderConsultant');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number')
                                , 'ConsultantId' => $this->bsf->isNullCheck($postData['ConsultantId_'.$i],'number')
                                , 'ConsultantType' => $this->bsf->isNullCheck($postData['ConsultantType_'.$i],'number')
                                , 'ConsultantAddress' => $this->bsf->isNullCheck($postData['ConsultantAddress_'.$i],'string')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if($this->bsf->isNullCheck($postData['ConsultantName_'.$i],'string') != '') {
                                    $insert = $sql->insert();
                                    $insert->into('Proj_ConsultantMaster');
                                    $insert->Values(array('ConsultantName' => $this->bsf->isNullCheck($postData['ConsultantName_' . $i], 'string')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $consultId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('Proj_TenderConsultant');
                                    $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                    , 'ConsultantId' => $this->bsf->isNullCheck($consultId, 'number')
                                    , 'ConsultantType' => $this->bsf->isNullCheck($postData['ConsultantType_' . $i], 'number')
                                    , 'ConsultantAddress' => $this->bsf->isNullCheck($postData['ConsultantAddress_' . $i], 'string')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderNotesTrans')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderNotesTrans');
                            $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId,'number'), 'NoteTitle' => $TermsTitle, 'NoteDescription' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderEnclosureTrans')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($enclosureList as $encl) {
                            $i = $encl['EnclosureId'];
                            if ($this->bsf->isNullCheck($postData['checkenc_'.$i],'number') ==1) {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderEnclosureTrans');
                                $insert->Values(array('TenderEnquiryId' => $enquiryId
                                , 'EnclosureId' =>$i));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderCheckListTrans')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach($checkListMaster as $cl) {
                            $i = $cl['CheckListId'];
                            $tarDate = NULL;

                            if($this->bsf->isNullCheck($postData['chkList_'.$i],'number') != "") {
                                if ($postData['targetDate_' . $i] != '') {
                                    $tarDate = date('Y-m-d', strtotime($postData['targetDate_' . $i]));
                                }

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderCheckListTrans');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                , 'CheckListId' => $this->bsf->isNullCheck($postData['chkListId_' . $i], 'number')
                                , 'UserId' => $this->bsf->isNullCheck($postData['userId_' . $i], 'number')
                                , 'TargetDate' => $tarDate
                                , 'Critical' => $this->bsf->isNullCheck($postData['critical_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderProcessTrans')
                            ->where(array('TenderEnquiryId'=>$enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach($processMaster as $ps) {
                            $i = $ps['TenderProcessId'];
                            $tarDate = NULL;
                            $psStatus = 0;

                            if($this->bsf->isNullCheck($postData['proStep_'.$i],'number') != "") {
                                if ($postData['psTargetDate_' . $i] != '') {
                                    $tarDate = date('Y-m-d', strtotime($postData['psTargetDate_' . $i]));
                                }
                                if (isset($postData['psStatus_' . $i]) && $postData['psStatus_' . $i] == 1) {
                                    $psStatus = 1;
                                }

                                //, 'Critical' => $this->bsf->isNullCheck($postData['psCritical_' . $i], 'number')
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderProcessTrans');
                                $insert->Values(array('TenderEnquiryId' => $this->bsf->isNullCheck($enquiryId, 'number')
                                , 'TenderProcessId' => $this->bsf->isNullCheck($postData['proStepId_' . $i], 'number')
                                , 'UserId' => $this->bsf->isNullCheck($postData['psUserId_' . $i], 'number')
                                , 'TargetDate' => $tarDate
                                , 'Status' => $psStatus
                                , 'SortId' => $this->bsf->isNullCheck($postData['sortId_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $sessionTender->enquiryId = '';
                        $sessionTender->edit = '';

                        $connection->commit();
                        $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'tenderregister'));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            $enquirySelect = $sql->select();
            $enquirySelect->from(array("a"=>"Proj_TenderEnquiry"))
                ->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId', array('CityName'=>new Expression("Case When a.CityId=0 then a.Location else b.CityName end")  ), $enquirySelect:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=c.ProjectTypeId', array('ProjectTypeName'=>new Expression("Case when a.ProjectTypeId=0 then a.ProjectTypeName else c.ProjectTypeName end")), $enquirySelect:: JOIN_LEFT)
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $enquiryStmt = $sql->getSqlStringForSqlObject($enquirySelect);
            $this->_view->enquiryResult = $dbAdapter->query($enquiryStmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $consultantSelect = $sql->select();
            $consultantSelect->from(array("a"=>"Proj_ConsultantMaster"))
                ->columns(array("data"=>"ConsultantId", "value"=>"ConsultantName"));
            $consultantStmt = $sql->getSqlStringForSqlObject($consultantSelect);
            $this->_view->conResult = $dbAdapter->query($consultantStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /*$consultantSelect = $sql->select();
            $consultantSelect->from(array("a"=>"Vendor_Master"))
                ->columns(array("data"=>"VendorId", "value"=>"VendorName"))
                ->where(array('ServiceTypeId=14'));
            $consultantStmt = $sql->getSqlStringForSqlObject($consultantSelect);
            $this->_view->conResult = $dbAdapter->query($consultantStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

            $tenderTypeSelect = $sql->select();
            $tenderTypeSelect->from(array("a"=>"Proj_TenderType"))
                ->columns(array("TenderTypeId", "TenderTypeName"));
            $tenderTypeStmt = $sql->getSqlStringForSqlObject($tenderTypeSelect);
            $this->_view->tenderTypeResult = $dbAdapter->query($tenderTypeStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Mode of Payment Select box
            $tenderTypeSelect = $sql->select();
            $tenderTypeSelect->from(array("a"=>"Proj_PaymentModeMaster"))
                ->columns(array("TransId", "PaymentMode"));
            $tenderTypeStmt = $sql->getSqlStringForSqlObject($tenderTypeSelect);
            $this->_view->paymentMode = $dbAdapter->query($tenderTypeStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projectTypeSelect = $sql->select();
            $projectTypeSelect->from(array("a"=>"Proj_ProjectTypeMaster"))
                ->columns(array('data'=>"ProjectTypeId",'value'=>"ProjectTypeName"));
            $projectTypeStmt = $sql->getSqlStringForSqlObject($projectTypeSelect);
            $this->_view->projectTypeResult = $dbAdapter->query($projectTypeStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if($enquiryMode == 'yes') {
                $select = $sql->select();
                $select->from('Proj_TenderDetails')
                    ->where('TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_TenderCostDetail')
                    ->where('TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderCostDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_TenderDates')
                    ->where('TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderDates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderConsultant'))
                    ->join(array('b' => 'Proj_ConsultantMaster'), 'a.ConsultantId = b.ConsultantId', array('ConsultantName'))
                    ->where('a.TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderConsultant = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                /*$select = $sql->select();
                $select->from(array('a' => 'Proj_TenderConsultant'))
                    ->join(array('b' => 'Vendor_Master'), 'a.ConsultantId = b.VendorId', array('ConsultantName'=>new Expression('VendorName')))
                    ->where('a.TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenderConsultant = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderNotesTrans"))
                    ->columns(array('NoteTitle', 'NoteDescription'))
                    ->where('TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->notetrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderCheckListTrans"))
                    ->columns(array('CheckListId', 'UserId', 'TargetDate', 'Critical', 'Status'))
                    ->where('TenderEnquiryId=' . $enquiryId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->chkListTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderProcessTrans"))
                    ->columns(array('TenderProcessId', 'UserId', 'TargetDate', 'Status', 'Critical', 'SortId','Flag'))
                    ->join(array('b' => 'Proj_TenderProcessMaster'), 'a.TenderProcessId = b.TenderProcessId', array('ProcessName'))
                    ->where('a.TenderEnquiryId=' . $enquiryId)
                    ->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->proStepTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $select = $sql->select();
            $select->from(array('a' => "Proj_TenderEnclosureMaster"))
                ->join(array('b' => 'Proj_TenderEnclosureTrans'), new expression("a.EnclosureId = b.EnclosureId and b.TenderEnquiryId='$enquiryId'"), array('Sel'=>new Expression("Case When b.EnclosureId is null then 'No' else 'Yes' end")), $select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_TenderSubmitEnclosure'), new expression("a.EnclosureId = c.EnclosureId and c.TenderEnquiryId='$enquiryId'"), array('Submitted'=>new Expression("Case When c.EnclosureId is null then 0 else 1 end")), $select:: JOIN_LEFT)
                ->columns(array('EnclosureId', 'EnclosureName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enclosureTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from City Master
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId','CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->cityMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Clients
            $select = $sql->select();
            $select->from('Proj_ClientMaster')
                ->columns(array('data' => 'ClientId', 'value' => 'ClientName'))
                ->where("DeleteFlag = 0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->clients = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from Users
            $select = $sql->select();
            $select->from('WF_Users')
                ->columns(array('UserId','UserName'))
                ->where(array("DeleteFlag = 0"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_CheckListTypeMaster' ))
                ->columns(array('TypeId','CheckListTypeName'));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->checkListType = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $this->_view->sourceType = $this->bsf->getSourceType();
            $this->_view->tenderProcessType = $this->bsf->getTenderProcessType();
            $this->_view->consultantType = $this->bsf->getConsultantType();
            $this->_view->actionMode = $enquiryMode;

            return $this->_view;
        } else {
            $this->redirect()->toRoute('project/enquiry', array('controller' => 'tender', 'action' => 'enquiry'));
        }
    }

    public function checkClientAction()
    {
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
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $data = 'N';
                $SearchStr = $this->bsf->isNullCheck($postData['search'], 'string');
                $select = $sql->select();
                $response = $this->getResponse();
                switch ($RType) {
                    case 'clientname':
                        $select = $sql->select();
                        $select->from('Proj_ClientMaster')
                            ->columns(array('ClientId'))
                            ->where("ClientName='$SearchStr' AND DeleteFlag='0'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                }
                $response->setContent($data);
                return $response;
            }
        }
    }

    public function tenderregisterAction(){

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

            } else  {
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderEnquiry'))
                    ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_CityMaster'), 'a.CityId=c.CityId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=d.ProjectTypeId', array(), $select::JOIN_LEFT)
                    ->columns(array('TenderEnquiryId', 'RefNo', 'RefDate','EnquiryType'=>new Expression("Case When EnquiryType='E' then 'Additional' else 'New' end"),'ClientName'=> new Expression('b.ClientName'),
                        'NameOfWork','ProjectTypeName'=>new Expression('Case When a.ProjectTypeId=0 then a.ProjectTypeName else d.ProjectTypeName end'),'CityName'=>new Expression("Case When a.CityId=0 then a.Location else c.CityName end"),'ProposalCost'));
                $select->order('a.RefDate DESC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arrEnquires  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select->from(array('a' => 'Proj_TenderEnquiry'))
//                    ->join(array('b' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
//                    ->join(array('c' => 'Proj_TenderType'), 'b.TenderTypeId=c.TenderTypeId', array('TenderTypeName'), $select::JOIN_LEFT)
//                    ->join(array('d' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=d.ProjectTypeId', array('ProjectTypeName'), $select::JOIN_LEFT)
//                    ->join(array('e' => 'Proj_EnquiryDocumentPurchase'), 'a.DocumentPurchase=e.EnquiryDocumentPurchaseId', array('EnquiryFollowupId'), $select::JOIN_LEFT)
//                    ->columns(array('TenderEnquiryId', 'RefNo', 'RefDate', 'NameOfWork', 'ProposalCost','ProjectDuration','Duration',
//                        'DocumentPurchase','Quoted','Submitted','BidWin','OrderReceived','WorkStarted','SpecId' => new Expression('TechnicalSpecificationId'),'EnquirySiteInvestigationId'));
//                $select->order('a.RefDate DESC');
                //$this->_view->arrEnquires = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                //echo '<pre>'; print_r($this->_view->arrEnquires); die;
                // set the current page to what has been passed in query string, or to 1 if none set


                $select = $sql->select();
                $select->from('Proj_TenderEnquiry')
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofenquiry = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("Quoted='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofquoted = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("BidWin='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofbidwin = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from('Proj_TenderEnquiry')
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("OrderReceived='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->nooforder = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_TenderEnquiry'))
//                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId', array('value'=>'CityName'), $select::JOIN_LEFT)
//                    ->columns(array('data' => new Expression("Distinct a.CityId")))
//                    ->where("a.CityId<>0");
//                $select->order('d.CityName');
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->arrlocation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_TenderEnquiry'))
//                    ->columns(array('value' => new Expression("Distinct a.NameOfWork"),'data'=> new Expression("'0'")));
//                $select->order('a.NameOfWork');
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->arrsource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $minDate = date("Y/m/d");
//                $maxDate = date("Y/m/d");
//
//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_TenderEnquiry'))
//                    ->columns(array('minDate' => new Expression("Min(a.RefDate)"),'maxDate'=> new Expression("Max(a.RefDate)")));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $minmaxDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//                if (!empty($minmaxDate)) {
//                    $minDate =  $this->bsf->isNullCheck($minmaxDate['minDate'],'date');
//                    $maxDate =  $this->bsf->isNullCheck($minmaxDate['maxDate'],'date');
//                }
//                $this->_view->minDate= $minDate;
//                $this->_view->maxDate= $maxDate;
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function getTenderRegisterAction() {

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $postParams = $request->getPost();

                $sOption =  $this->bsf->isNullCheck($postParams['SortOption'],'string');
                $sEnquiryNo= $this->bsf->isNullCheck($postParams['EnquiryNo'],'string');
                $dFromDate= $this->bsf->isNullCheck($postParams['FromDate'],'date');
                $dToDate= $this->bsf->isNullCheck($postParams['ToDate'],'date');
                $sSourceName= $this->bsf->isNullCheck($postParams['SourceName'],'string');
                $sLocation= $this->bsf->isNullCheck($postParams['Location'],'string');
                $dFromPrice= $this->bsf->isNullCheck($postParams['FromPrice'],'number');
                $dToPrice= $this->bsf->isNullCheck($postParams['ToPrice'],'number');

                $select = $sql->select();

                $select->from(array('a' => 'Proj_TenderEnquiry'))
                    ->join(array('b' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_TenderType'), 'b.TenderTypeId=c.TenderTypeId', array('TenderTypeName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=d.ProjectTypeId', array('ProjectTypeName'=> new Expression("Case When a.ProjectTypeId=0 then a.ProjectTypeName else d.ProjectTypeName end")), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_CityMaster'), 'a.CityId=e.CityId', array('CityName'=>new Expression("Case When a.CityId=0 then a.Location else e.CityName end")), $select::JOIN_LEFT)
                    ->columns(array('TenderEnquiryId', 'RefNo', 'RefDate', 'NameOfWork', 'ProposalCost','ProjectDuration','Duration',
                        'DocumentPurchase','Quoted','Submitted','BidWin','OrderReceived','WorkStarted','SpecId' => new Expression('TechnicalSpecificationId'),'EnquirySiteInvestigationId'));
                //$select->where->like('RefNo', $sEnquiryNo);
                $where = "a.RefDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.RefDate <='". date('d-M-Y', strtotime($dToDate)) ."'";
                if ($sEnquiryNo !="") {
                    $sEnquiryNo = '%' . $sEnquiryNo .'%';
                    $where =  $where . " and a.RefNo like('".$sEnquiryNo."')";
                }
                if ($sLocation !="") {
                    $sLocation = '%' . $sLocation .'%';
                    $where =  $where . " and e.CityName like('".$sLocation."')";
                }
                if ($sSourceName !="") {
                    $sSourceName = '%' . $sSourceName .'%';
                    $where =  $where . " and a.NameOfWork like('".$sSourceName."')";
                }

                if ($dFromPrice !=0 && $dToPrice !=0) {
                    $where = $where . " and a.ProposalCost  >= " . $dFromPrice . " and a.ProposalCost <= " . $dToPrice;
                } else if ($dFromPrice !=0 && $dToPrice==0) {
                    $where =  $where . " and a.ProposalCost = " . $dFromPrice;
                }  else if ($dFromPrice ==0 && $dToPrice!=0) {
                    $where =  $where . " and a.ProposalCost = " . $dToPrice;
                }

                $select->where($where);

                if ($sOption == "Most Recent") {
                    $select->order('a.RefDate DESC');
                } else if ($sOption == "NameOfWork") {
                    $select->order('a.NameOfWork');
                } else if ($sOption == "Location") {
                    $select->order('e.CityName');
                } else if ($sOption == "Cost-Low to High") {
                    $select->order('a.ProposalCost');
                } else if ($sOption == "Cost-High to Low") {
                    $select->order('a.ProposalCost DESC');
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


    public function quotationregisterAction(){
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

            } else {

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a" => "Proj_TenderQuotationRegister"))
                    ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('ProjectName'=> new Expression("NameOfWork"),'ClientName'), $select::JOIN_LEFT)
                    //->join(array('d' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=d.TenderEnquiryId', array(), $select::JOIN_LEFT)
                    ->join(array("e" => "Proj_ProjectTypeMaster"), "b.ProjectTypeId=e.ProjectTypeId", array('ProjectTypeName'=>new Expression("Case when b.ProjectTypeId=0 then b.ProjectTypeName else e.ProjectTypeName end")), $select::JOIN_LEFT)
                    ->columns(array("QuotationId", "TenderEnquiryId", "RefNo", "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                    ,'Approve'=>new Expression("Case When a.Approve='Y' then 'Yes' When a.Approve='P' then 'Partial' else 'No' end")))
                    ->order('a.QuotationId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofenquiry = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("Quoted='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofquoted = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from('Proj_WORegister')
                    ->columns(array('Orders' => new Expression("Count(WORegisterId)")))
                    ->where("LiveWO = 1");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ordercount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("BidWin='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofbidwin = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function workorderregisterAction(){
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

            } else {

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a" => "Proj_WORegister"))
                    ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_TenderWOSetup'), 'a.WORegisterId=d.WORegisterId', array('ProjectName'=>new Expression("ProjectDescription")), $select::JOIN_LEFT)
                    ->columns(array("WORegisterId",'EnquiryId'=>new Expression("a.TenderEnquiryId"), "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                    , "OrderAmount",'Approve'=>new Expression("Case When a.Approve='Y' then 'Yes' When a.Approve='P' then 'Partial' else 'No' end")))
                    ->where("A.LiveWO = 1")
                    ->order('a.WORegisterId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofenquiry = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( 'Proj_TenderEnquiry' )
                    ->columns( array( 'count' => new Expression('COUNT(TenderEnquiryId)')))
                    ->where("Quoted='1'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->noofquoted = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from('Proj_WORegister')
                    ->columns(array('Orders' => new Expression("Count(WORegisterId)")))
                    ->where("LiveWO = 1");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ordercount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Proj_WORegister')
                    ->columns(array('OrderAmt' => new Expression("Sum(OrderAmount)")))
                    ->where("LiveWO = 1");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ordervalue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }



            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function workorderAction()
    {
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

//        $sessionWork = new Container('sessionWork');
//        $tEnqId = $sessionWork->tEnqId;
//        $client = $sessionWork->clientId;
//
////        $this->_view->enqFollowup = '';
//        if($tEnqId !=0 && $client !=0){
//            $select = $sql->select();
//            $select->from('Proj_TenderEnquiry')
//                ->columns(array('ClientId','TenderEnquiryId','ClientName' => 'ClientName', 'EnquiryName' => new Expression("RefNo+ '-' +NameofWork")))
//                ->where("ClientId=$client and TenderEnquiryId=$tEnqId");
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $enqFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            $this->_view->enqFollowup=$enqFollowup;
//
//            $sessionWork->tEnqId = '';
//            $sessionWork->client = '';
//        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $data = 'N';
                $SearchStr = $this->bsf->isNullCheck($this->params()->fromRoute('SearchStr'), 'string');
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $response = $this->getResponse();
                switch ($RType) {
                    case 'orderno':
                        $sWONo = $this->bsf->isNullCheck($postData['data'], 'string');
                        $select->from('Proj_WORegister')
                            ->columns(array('WORegisterId'))
                            ->where("WONo='$sWONo'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                    case 'projectworkorders':
                        $enquiryid = $this->bsf->isNullCheck($postData['data'], 'number');
                        $select->from(array('a' => 'Proj_WORegister'))
                            ->columns(array('WORegisterId', 'WONo'))
                            ->where("TenderEnquiryId=$enquiryid and LiveWO ='1'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                    case 'Amendment':
                        $woregid = $this->bsf->isNullCheck($postData['data'], 'number');
                        $pwoId=0;
                        $sWONo ="";
                        $wocount=0;
                        $select->from(array('a' => 'Proj_WORegister'))
                            ->columns(array('PWORegisterId', 'WONo'))
                            ->where("a.WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($results)) {
                            $pwoId = $results->PWORegisterId;
                            $sWONo = $results->WONo;
                            $wocount = 1; }
                        if ($pwoId != 0) {
                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_WORegister'))
                                ->columns(array('WORegisterId', 'WONo'))
                                ->where("a.WORegisterId=$pwoId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $nresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $sWONo = $nresults->WONo;

                            $select = $sql->select();
                            $select->from('Proj_WORegister')
                                ->columns(array('Orders' => new Expression("Count(WORegisterId)")))
                                ->where("PWORegisterId =$pwoId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $nresults1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $wocount = $nresults1->Orders + 1;
                        } else {
                            $pwoId = $woregid;
                        }
                        if ($wocount != 0)  $sWONo = $sWONo . '-A' . $wocount;
                        $adata = [$sWONo, $pwoId];
                        $data = json_encode($adata);
                        break;
                    case 'getProject':
                        $clientId = $this->bsf->isNullCheck($postData['data'], 'number');
                        $select = $sql->select();
                        $select->from('Proj_TenderEnquiry')
                            ->columns(array('data' => 'TenderEnquiryId', 'value' => new Expression("RefNo+ '-' +NameofWork")))
                            //->where("ClientId='$clientId' and BidWin=1 and OrderReceived=0");
                            ->where("ClientId='$clientId'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                    case 'projectdetails':
                        $enquiryid = $this->bsf->isNullCheck($postData['data'], 'number');
                        $select->from(array('a' => 'Proj_TenderEnquiry'))
                            //->join(array('b' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('ProjectTypeId'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=c.ProjectTypeId', array('ProjectTypeName'=>new Expression("Case when a.ProjectTypeId=0 then a.ProjectTypeName else c.ProjectTypeName end")), $select::JOIN_LEFT)
                            ->columns(array('NameofWork', 'ProjectTypeId'))
                            ->where("a.TenderEnquiryId=$enquiryid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $data = json_encode($results);
                        break;
                    case 'PrevWorkOrder':
                        $woregid = $this->bsf->isNullCheck($postData['data'], 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WORegister"))
                            ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                            ->columns(array("WORegisterId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                            , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                            , "PeriodType", "Duration", "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')")
                            , "AuthorityName", "AuthorityAddress", "AgreementType", "Duration", "OrderAmount", "OrderPercent"
                            , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                        $select->where(array('a.WORegisterId' => $woregid));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $aworegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $sWONo ="";
                        if (!empty($aworegister)) $sWONo = $aworegister['WONo'];

                        $select = $sql->select();
                        $select->from('Proj_WORegister')
                            ->columns(array('Orders' => new Expression("Count(WORegisterId)")))
                            ->where("PWORegisterId =$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $nresults1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wocount = $nresults1->Orders + 1;
                        if ($wocount != 0)  $sWONo = $sWONo . '-A' . $wocount;


                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOTerms"))
                            ->columns(array('MobilisationPercent', 'MobilisationAmount', 'MobilisationRecovery', 'RetentionPercent', 'PaymentSubmitPercent', 'PaymentFromSubmitDays', 'CertifyDays', 'PaymentFromCertifyDays', 'Notes', 'PeriodType', 'PeriodDay', 'PeriodWeekDay'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awoterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WODocuments"))
                            ->columns(array('DocumentId', 'Type', 'Description', 'URL'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awodocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WODepositTrans"))
                            ->columns(array('DepositType', 'DepostMode', 'RefNo', 'RefDate', 'Amount', 'ValidUpto'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awodeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //WOOtherTerms
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOOtherTerms"))
                            ->columns(array('TermsTitle', 'TermsDescription'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awootherterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOMaterialPriceEscalation
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOMaterialPriceEscalation"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId'=>new Expression("a.ResourceId"), 'Rate', 'EscalationPer', 'RateCondition', 'ActualRate'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialbase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOClientSupplyMaterial
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOClientSupplyMaterial"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId'=>new Expression("a.ResourceId"), 'Rate', 'SType'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialexcl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOMaterialAdvance
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOMaterialAdvance"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                            ->columns(array('MaterialId'=>new Expression("a.ResourceId"), 'AdvPercent'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awomaterialadv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WOTechSpec
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_WOTechSpec"))
                            ->columns(array('Title', 'Specification'))
                            ->where("WORegisterId=$woregid");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $awotechterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                        // boq
//                        $select = $sql->select();
//                        $select->from(array('a' => "CB_WOBOQ"))
//                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
//                            ->columns(array('WOBOQId', 'WOBOQTransId', 'TransType', 'SortId', 'WorkGroupId', 'AgtNo', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'ClientRate', 'ClientAmount', 'Rate', 'Amount', 'RateVariance', 'Header','HeaderType'))
//                            ->where("a.WORegisterId=$SearchStr")
//                            ->order('a.SortId');
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $awoboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $adata['WORegister'] = $aworegister;
                        $adata['WONo'] = $sWONo;
                        $adata['Terms'] = $awoterms;
                        $adata['Document'] = $awodocument;
                        $adata['Deposit'] = $awodeposit;
                        $adata['OTerms'] = $awootherterms;
                        $adata['PriceEsc'] = $awomaterialbase;
                        $adata['Supply'] = $awomaterialexcl;
                        $adata['Advance'] = $awomaterialadv;
                        $adata['Tech'] = $awotechterms;
//                        $adata['BillFormat'] = $awobillformat;
//                        $adata['BOQ'] = $awoboq;

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
                $sql = new Sql($dbAdapter);
                $postData = $request->getPost();
                $files = $request->getFiles();

                $woid = $this->bsf->isNullCheck($postData['workorderid'], 'number');
                $iSetupId = $this->bsf->isNullCheck($postData['setupId'],'number');

                $delFilesUrl = array();
                try {
                    $orderType = $this->bsf->isNullCheck($postData['Order'], 'string');
//                    $woNo = $this->bsf->isNullCheck($postData['WONo'], 'string');
                    $projectId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');
                    $iQuotationId = $this->bsf->isNullCheck($postData['quotationId'], 'number');

                    if ($woid == 0) {

                        $aVNo = CommonHelper::getVoucherNo(103, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == false)
                            $RefNo = $postData['MWorkOrderNo'];
                        else
                            $RefNo = $aVNo["voucherNo"];

                        $woNo = $RefNo;

                        $wotype = 'Tender-Workorder-Add';
                        $wostype = 'N';
                        $amendment = $this->bsf->isNullCheck($postData['amendment'], 'number');
                        // Create Work order
                        $AgreementNo = $this->bsf->isNullCheck($postData['AgreementNo'], 'string');
                        $AgreementDate = $this->bsf->isNullCheck($postData['AgreementDate'], 'date');
                        $AuthorityName = $this->bsf->isNullCheck($postData['AuthorityName'], 'string');
                        $AuthorityAddress = $this->bsf->isNullCheck($postData['AuthorityAddress'], 'string');
                        $AgreementType = $this->bsf->isNullCheck($postData['AgreementType'], 'string');
                        $StartDate = $this->bsf->isNullCheck($postData['StartDate'], 'date');
                        $EndDate = $this->bsf->isNullCheck($postData['EndDate'], 'date');
                        $PeriodType = $this->bsf->isNullCheck($postData['PeriodType'], 'string');
                        $Duration = $this->bsf->isNullCheck($postData['Duration'], 'number');
                        $OrderAmount = $this->bsf->isNullCheck($postData['OrderAmount'], 'number');
                        $OrderPercent = $this->bsf->isNullCheck($postData['OrderPercent'], 'number');
                        $iCCId = $this->bsf->isNullCheck($postData['costcentreId'], 'number');

                        // index form fields
                        $cityId = 0;
                        $stateId = 0;
                        $countryId = 0;
                        $clientAddress = "";
                        $clientId=0;

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
                            $insert->into('Proj_ClientMaster');
                            $insert->Values(array('ClientName' => $clientName, 'Address' => $clientAddress, 'CityId' => $cityId, 'SubscriberId' => $subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $clientId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        } else
                            $clientId = $this->bsf->isNullCheck($postData['ClientId'], 'number');

                        $PrevWOId = $this->bsf->isNullCheck($postData['PWorkOrderId'], 'number');
                        $iLiveWO = 1;

//                        $sNewWONo = $this->bsf->isNullCheck($postData['WONo'], 'string');
                        $woDate = date('Y-m-d', strtotime($postData['MWorkOrderDate']));
                        $woStartDate = date('Y-m-d', strtotime($StartDate));
                        $woEndDate = date('Y-m-d', strtotime($EndDate));
                        $woAgreementDate = date('Y-m-d', strtotime($AgreementDate));
                        if ($clientId ==0) $clientId =  $this->bsf->isNullCheck($postData['TClientId'], 'number');

                        $insert = $sql->insert();
                        $insert->into('Proj_WORegister');
                        $insert->Values(array('WODate' => $woDate
                        , 'OrderType' => $orderType, 'WONo' => $woNo
                        , 'TenderEnquiryId' => $projectId,'QuotationId'=>$iQuotationId, 'ClientId' => $clientId
                        , 'AgreementNo' => $AgreementNo, 'AgreementDate' => $woAgreementDate, 'AuthorityName' => $AuthorityName, 'AuthorityAddress' => $AuthorityAddress, 'AgreementType' => $AgreementType
                        , 'StartDate' => $woStartDate, 'EndDate' => $woEndDate, 'PeriodType' => $PeriodType
                        , 'Duration' => $Duration, 'OrderAmount' => $OrderAmount, 'OrderPercent' => $OrderPercent, 'PWORegisterId' => $PrevWOId,'Amendment'=>$amendment,'LiveWO' => $iLiveWO,'CostCentreId'=>$iCCId));
                        $statement = $sql->getSqlStringForSqlObject($insert);

                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $woid = $dbAdapter->getDriver()->getLastGeneratedValue();


                        $select = $sql->select();
                        $select->from('Proj_TenderQuotationTrans')
                            ->columns(array('WORegisterId'=> new Expression("'$woid'"),'PrevQuotationTransId','QuotationTransId','WorkGroupId','ParentId','IOWs','SerialNo','RefSerialNo','Specification',
                                'ShortSpec','Header','UnitId','Rate','Qty','Amount','IOWId','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','SRate','RRate','MixType','ParentText','ProjectWorkGroupId','ProjectWorkGroupName','SiteMixRatio','ReadyMixRatio','DeleteFlag','QuotedRate','QuotedAmount',
                                'ProjectIOWId','WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate','ParentName','WorkTypeId'))
                            ->where(array("QuotationId='$iQuotationId'"));

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWOTrans');
                        $insert->columns(array('WORegisterId','PrevQuotationTransId','QuotationTransId','WorkGroupId','ParentId','IOWs','SerialNo','RefSerialNo','Specification',
                            'ShortSpec','Header','UnitId','Rate','Qty','Amount','IOWId','WorkingQty','RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','SRate','RRate','MixType','ParentText','ProjectWorkGroupId','ProjectWorkGroupName','SiteMixRatio','ReadyMixRatio','DeleteFlag','QuotedRate','QuotedAmount',
                            'ProjectIOWId','WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate','ParentName','WorkTypeId'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery = $sql->select();
                        $subQuery->from("Proj_TenderQuotationTrans")
                            ->columns(array("QuotationTransId"));
                        $subQuery->where(array('QuotationId' => $iQuotationId));

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_TenderWBSTrans"))
                            ->join(array('b' => 'Proj_TenderWOTrans'), 'a.QuotationTransId=b.QuotationTransId', array(), $select:: JOIN_LEFT)
                            ->columns(array('WORegisterId'=> new Expression("'$woid'"), 'TenderWOTransId'=>new Expression("b.TenderWOTransId"),'QuotationTransId','WBSId','SerialNo','Qty','Rate','Amount'))
                            ->where->expression('a.QuotationTransId IN ?', array($subQuery));

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWOWBSTrans');
                        $insert->columns(array('WORegisterId','TenderWOTransId','QuotationTransId','WBSId','SerialNo','Qty','Rate','Amount'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_TenderQuotationRateAnalysis"))
                            ->join(array('b' => 'Proj_TenderWOTrans'), 'a.QuotationTransId=b.QuotationTransId', array(), $select:: JOIN_LEFT)
                            ->columns(array('TenderWOTransId'=>new Expression("b.TenderWOTransId"),'IncludeFlag','ReferenceId','ResourceId','SubIOWId','Description','Qty','Rate','Amount','Formula','MixType','TransType','SortId','RateType','Wastage','WastageQty','WastageAmount','Weightage'))
                            ->where->expression('a.QuotationTransId IN ?', array($subQuery));

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWORateAnalysis');
                        $insert->columns(array('TenderWOTransId','IncludeFlag','ReferenceId','ResourceId','SubIOWId','Description','Qty','Rate','Amount','Formula','MixType','TransType','SortId','RateType','Wastage','WastageQty','WastageAmount','Weightage'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_TenderQuotationQualTrans"))
                            ->join(array('b' => 'Proj_TenderWOTrans'), 'a.QuotationTransId=b.QuotationTransId', array(), $select:: JOIN_LEFT)
                            ->columns(array('WORegisterId'=> new Expression("'$woid'"), 'TenderWOTransId'=>new Expression("b.TenderWOTransId"),'QuotationTransId',
                                'QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'))
                            ->where->expression('a.QuotationTransId IN ?', array($subQuery));

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWOQualTrans');
                        $insert->columns(array('WORegisterId','TenderWOTransId','QuotationTransId','QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer','ExpressionAmt','TaxableAmt','TaxAmt','SurChargeAmt','EDCessAmt','HEDCessAmt','KKCessAmt','SBCessAmt','NetAmt','SortId','MixType'));
                        $insert->Values($select);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*if($orderType == 'N') {
                            $this->_createProject($dbAdapter,$postData['ProjectDescription'],$woid,$postData['costCentreId'],$projectTypeId);
                        } else*/ if($orderType == 'A') {
                            $update = $sql->update();
                            $update->table('Proj_WORegister');
                            $update->set(array('LiveWO' => 0));
                            $update->where(array('WORegisterId' => $PrevWOId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }


                        $update = $sql->update();
                        $update->table('Proj_TenderWOTrans');
                        $update->set(array(
                            'PrevTenderWOTransId' => new Expression("TenderWOTransId")
                        ));
                        $update->where(array('PrevTenderWOTransId' => 0,'WORegisterId'=>$woid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        /*$update = $sql->update();
                        $update->table('Proj_TenderEnquiry')
                            ->set(array('WorkStarted' => $woid))
                            ->where(array('TenderEnquiryId' => $projectId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        $select = $sql->select();
                        $select->from('Proj_TenderProcessMaster')
                            ->columns(array('TenderProcessId'))
                            ->where(array('ProcessName' => 'Order Received'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $processId = $processName['TenderProcessId'];

                        $update = $sql->update();
                        $update->table('Proj_TenderProcessTrans')
                            ->set(array('Flag' => 1))
                            ->where(array('TenderEnquiryId' => $projectId, 'TenderProcessId' => $processId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //setup
                        if ($amendment==0) {
                            $insert = $sql->insert();
                            $insert->into('Proj_TenderWOSetup');
                            $insert->Values(array('WORegisterId' => $woid
                            , 'BusinessTypeId' => $this->bsf->isNullCheck($postData['businessType'],'number')
                            , 'ProjectDivision' => $this->bsf->isNullCheck($postData['costCentreId'],'number')
                            , 'ProjectDescription' => $this->bsf->isNullCheck($postData['ProjectDescription'],'string')
                            , 'ProjectTypeId' => $projectTypeId
                            , 'IsSEZ' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
                            , 'MaterialStock' => $this->bsf->isNullCheck($postData['materialStock'],'number')
                            , 'WorkProgress' => $this->bsf->isNullCheck($postData['workProgress'],'number')
                            , 'ClientBill' => $this->bsf->isNullCheck($postData['clientBill'],'number')
                            , 'LabourStrength' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
                            , 'MaterialConsumption' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
                            , 'PlantMachinery' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
                            , 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
                            , 'IssueRequire' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
                            , 'IssueRateBased' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
                            , 'IssueBased' => $this->bsf->isNullCheck($postData['issueBased'],'string')
                            , 'TransferBased' => $this->bsf->isNullCheck($postData['transferBased'],'string')
                            , 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
                            , 'OHBudgetFrom' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')
                            , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $isetupid = $this->bsf->isNullCheck($postData['setupId'], 'number');

                            $update = $sql->update();
                            $update->table('Proj_TenderWOSetup');
                            $update->set(array('BusinessTypeId' => $this->bsf->isNullCheck($postData['businessType'],'number')
                            , 'ProjectDivision' => $this->bsf->isNullCheck($postData['costCentreId'],'number')
                            , 'ProjectDescription' => $this->bsf->isNullCheck($postData['ProjectDescription'],'string')
                            , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')
                            , 'IsSEZ' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
                            , 'MaterialStock' => $this->bsf->isNullCheck($postData['materialStock'],'number')
                            , 'WorkProgress' => $this->bsf->isNullCheck($postData['workProgress'],'number')
                            , 'ClientBill' => $this->bsf->isNullCheck($postData['clientBill'],'number')
                            , 'LabourStrength' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
                            , 'MaterialConsumption' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
                            , 'PlantMachinery' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
                            , 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
                            , 'IssueRequire' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
                            , 'IssueRateBased' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
                            , 'IssueBased' => $this->bsf->isNullCheck($postData['issueBased'],'string')
                            , 'TransferBased' => $this->bsf->isNullCheck($postData['transferBased'],'string')
                            , 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
                            , 'OHBudgetFrom' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')));
                            $update->where(array('SetupId'=>$isetupid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        $wotype = 'Tender-Workorder-Edit';
                        $wostype = 'E';

                        $woNo = $this->bsf->isNullCheck($postData['WONo'], 'string');

                        $AgreementNo = $this->bsf->isNullCheck($postData['AgreementNo'], 'string');
                        $AgreementDate = $this->bsf->isNullCheck($postData['AgreementDate'], 'date');
                        $AuthorityName = $this->bsf->isNullCheck($postData['AuthorityName'], 'string');
                        $AuthorityAddress = $this->bsf->isNullCheck($postData['AuthorityAddress'], 'string');
                        $AgreementType = $this->bsf->isNullCheck($postData['AgreementType'], 'string');
                        $StartDate = $this->bsf->isNullCheck($postData['StartDate'], 'date');
                        $EndDate = $this->bsf->isNullCheck($postData['EndDate'], 'date');
                        $PeriodType = $this->bsf->isNullCheck($postData['PeriodType'], 'string');
                        $Duration = $this->bsf->isNullCheck($postData['Duration'], 'number');
                        $OrderAmount = $this->bsf->isNullCheck($postData['OrderAmount'], 'number');
                        $OrderPercent = $this->bsf->isNullCheck($postData['OrderPercent'], 'number');
//                        $sNewWONo = $this->bsf->isNullCheck($postData['WONo'], 'string');

                        $update = $sql->update();
                        $update->table('Proj_WORegister');
                        $update->set(array('AgreementNo' => $AgreementNo, 'AgreementDate' => date('Y-m-d', strtotime($AgreementDate)), "AuthorityName" => $AuthorityName,
                            "AuthorityAddress" => $AuthorityAddress, "AgreementType" => $AgreementType, "StartDate" => date('Y-m-d', strtotime($StartDate)),
                            "EndDate" => date('Y-m-d', strtotime($EndDate)), "PeriodType" => $PeriodType, "Duration" => $Duration, "OrderAmount" => $OrderAmount,
                            "OrderPercent" => $OrderPercent));
                        $update->where(array('WORegisterId' => $woid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select->from( array('a' => 'Proj_WODocuments'))
                            ->columns(array('URL'))
                            ->where("a.WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $drawingdel = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach($drawingdel as $delurl) {
                            $url = $delurl['URL'];
                            if($url != '' || !is_null($url)) {
                                $delFilesUrl[] = 'public' . $url;
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('Proj_WODocuments')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOTerms')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WODepositTrans')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOOtherTerms')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOTechSpec')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOMaterialAdvance')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOMaterialPriceEscalation')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOClientSupplyMaterial')
                            ->where("WORegisterId=$woid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //boq
//                        $delete = $sql->delete();
//                        $delete->from('Proj_TenderWOWorkGroupTrans')
//                            ->where(array("WORegisterId" => $woid));
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $delete = $sql->delete();
//                        $delete->from('Proj_TenderWOResourceTrans')
//                            ->where(array("WORegisterId" => $woid));
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $subQuery = $sql->select();
//                        $subQuery->from("Proj_TenderWOTrans")
//                            ->columns(array("TenderWOTransId"));
//                        $subQuery->where(array('WORegisterId' => $woid));
//
//                        $delete = $sql->delete();
//                        $delete->from('Proj_TenderWORateAnalysis')
//                            ->where->expression('TenderWOTransId IN ?', array($subQuery));
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $delete = $sql->delete();
//                        $delete->from('Proj_TenderWOTrans')
//                            ->where(array("WORegisterId" => $woid));
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        //boq

                        //setup
                        $update = $sql->update();
                        $update->table('Proj_TenderWOSetup');
                        $update->set(array('BusinessTypeId' => $this->bsf->isNullCheck($postData['businessType'],'number')
                        , 'ProjectDivision' => $this->bsf->isNullCheck($postData['costCentreId'],'number')
                        , 'ProjectDescription' => $this->bsf->isNullCheck($postData['ProjectDescription'],'string')
                        , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')
                        , 'IsSEZ' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
                        , 'MaterialStock' => $this->bsf->isNullCheck($postData['materialStock'],'number')
                        , 'WorkProgress' => $this->bsf->isNullCheck($postData['workProgress'],'number')
                        , 'ClientBill' => $this->bsf->isNullCheck($postData['clientBill'],'number')
                        , 'LabourStrength' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
                        , 'MaterialConsumption' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
                        , 'PlantMachinery' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
                        , 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
                        , 'IssueRequire' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
                        , 'IssueRateBased' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
                        , 'IssueBased' => $this->bsf->isNullCheck($postData['issueBased'],'string')
                        , 'TransferBased' => $this->bsf->isNullCheck($postData['transferBased'],'string')
                        , 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
                        , 'OHBudgetFrom' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')));
                        $update->where(array('SetupId'=>$iSetupId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //project master
                        /*$update = $sql->update();
                        $update->table('Proj_ProjectMaster');
                        $update->set(array('ProjectName' => $this->bsf->isNullCheck($postData['ProjectDescription'],'string')
                        , 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')));
                        $update->where(array('WORegisterId'=>$woid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/
                    }

                    $documentrowid = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                    for ($i = 1; $i <= $documentrowid; $i++) {
                        $Type = $this->bsf->isNullCheck($postData['docType_' . $i], 'string');
                        $Description = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');

                        if ($Type == '' || $Description == '')
                            continue;

                        $url = '';
                        if($files['docFile_' . $i]['name']){

                            $dir = 'public/uploads/project/workorder/'.$woid.'/';
                            $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                            if($filename) {
                                // update valid files only
                                $url = '/uploads/project/workorder/'.$woid.'/' . $filename;
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_WODocuments');
                        $insert->Values(array('WORegisterId' => $woid, 'Type' => $Type, 'Description' => $Description, 'URL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $docId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    //Mobilisation and other fields
                    $insert = $sql->insert();
                    $insert->into('Proj_WOTerms');
                    $insert->Values(array('WORegisterId' => $woid, 'MobilisationPercent' => $this->bsf->isNullCheck($postData['MobilisationPercent'], 'number')
                    , 'MobilisationAmount' => $this->bsf->isNullCheck($postData['MobilisationAmount'], 'number'), 'MobilisationRecovery' => $this->bsf->isNullCheck($postData['MobilisationRecovery'], 'number'), 'RetentionPercent' => $this->bsf->isNullCheck($postData['RetentionPercent'], 'number')
                    , 'PaymentSubmitPercent' => $this->bsf->isNullCheck($postData['PaymentSubmitPercent'], 'number'), 'PaymentFromSubmitDays' => $this->bsf->isNullCheck($postData['PaymentFromSubmitDays'], 'number')
                    , 'CertifyDays' => $this->bsf->isNullCheck($postData['CertifyDays'], 'number'), 'PaymentFromCertifyDays' => $this->bsf->isNullCheck($postData['PaymentFromCertifyDays'], 'number')
                    , 'PeriodType' => $this->bsf->isNullCheck($postData['billperiodtype'], 'number'), 'PeriodDay' => $this->bsf->isNullCheck($postData['billperiodday'], 'number')
                    , 'PeriodWeekDay' => $this->bsf->isNullCheck($postData['billperiodweekday'], 'number'), 'Notes' => $this->bsf->isNullCheck($postData['Notes'], 'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
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
                        $insert->into('Proj_WODepositTrans');
                        $insert->Values(array('WORegisterId' => $woid, 'DepositType' => $depositType, 'DepostMode' => $depositmode, 'RefNo' => $refno, 'RefDate' => date('Y-m-d', strtotime($refdate)), 'Amount' => $amount, 'ValidUpto' => date('Y-m-d', strtotime($validupto))));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Other Terms fields
                    $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                    for ($i = 1; $i <= $termrowid; $i++) {
                        $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                        $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                        if ($TermsTitle == '' || $TermsDescription == '')
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_WOOtherTerms');
                        $insert->Values(array('WORegisterId' => $woid, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
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
                        $insert->into('Proj_WOTechSpec');
                        $insert->Values(array('WORegisterId' => $woid, 'Title' => $TermsTitle, 'Specification' => $TermsDescription));
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
                        $insert->into('Proj_WOMaterialAdvance');
                        $insert->Values(array('WORegisterId' => $woid, 'ResourceId' => $MaterialId, 'AdvPercent' => $Rate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // Material PriceEscalation
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
                        $insert->into('Proj_WOMaterialPriceEscalation');
                        $insert->Values(array('WORegisterId' => $woid, 'ResourceId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // ClientSupplyMaterial
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
                        $insert->into('Proj_WOClientSupplyMaterial');
                        $insert->Values(array('WORegisterId' => $woid, 'ResourceId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    //boq
//                    $iRowId = $this->bsf->isNullCheck($postData['rowid'],'number');
//                    for ($i = 1; $i <= $iRowId; $i++) {
//                        $wgroupid=0;
//                        $myArray = explode('.', $postData['parentkeyid_' . $i]);
//                        if (count($myArray) >1) {$wgroupid=$myArray[0];$parentid=intval($myArray[1]); }
//                        else{ $wgroupid=$myArray[0];$parentid=0;}
//
//                        $iMiowid= $this->bsf->isNullCheck($postData['iowid_' . $i],'number');
//                        if ($wgroupid !=0) {
//                            $prevWoTransId = 0;
//                            if($postData['mode'] == 'edit') {
//                                $prevWoTransId = $postData['ePrevWOTransId_' . $i];
//                            } else {
//                                if($orderType == 'N') {
//                                    $prevWoTransId = 0;
//                                } else if($orderType == 'A') {
//                                    $prevWoTransId = $postData['prevWOTransId_' . $i];
//                                }
//                            }
//
//                            $insert = $sql->insert();
//                            $insert->into('Proj_TenderWOTrans');
//                            $insert->Values(array('PrevTenderWOTransId' => $prevWoTransId, 'WORegisterId' => $woid, 'IOWId'=>$iMiowid,'WorkGroupId' => $wgroupid, 'ParentId' => $parentid, 'RefSerialNo' => $this->bsf->isNullCheck($postData['refserialno_' . $i], 'string'), 'Specification' => $this->bsf->isNullCheck($postData['spec_' . $i], 'string'), 'UnitId' => $this->bsf->isNullCheck($postData['unitkeyid_' . $i], 'number'),
//                                'ShortSpec'=>$this->bsf->isNullCheck($postData['shortspec_' . $i],'string'),'ParentText'=>$this->bsf->isNullCheck($postData['parentid_' . $i],'string'),'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'), 'WorkingQty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AnalQty'], 'number'), 'RWorkingQty' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_AnalQty'], 'number'),
//                                'CementRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AQty'], 'number'), 'SandRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_BQty'], 'number'), 'MetalRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_CQty'], 'number'), 'ThickQty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_thick'], 'number'),
//                                'MixType' => $this->bsf->isNullCheck($postData['ratetype_'. $i],'string'),'QuotedRate' => $this->bsf->isNullCheck($postData['qrate_' . $i], 'number'),'QuotedAmount' => $this->bsf->isNullCheck($postData['qamt_' . $i], 'number')));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            $itransid = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                            $iactRowid = $this->bsf->isNullCheck($postData['rowinfoid_' . $i],'number');
//
//                            for ($j = 1; $j <= $iactRowid; $j++) {
//                                $iresid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_reskeyid_' . $j],'number');
//                                $iiowid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_iowkeyid_' . $j],'number');
//                                $irefid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_refid_' . $j],'number');
//                                $stype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
//                                $sdes = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resdes_' . $j],'string');
//                                $isortid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_rowrefid_' . $j],'number');
//                                $stranstype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
//                                $sratetype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_ratetype_' . $j],'string');
//
//                                if ($stype=="" || ($stype=="I" &&  $iiowid==0) || ($stype=="R" &&  $iresid==0) || $stype=="H" &&  $sdes=="")
//                                    continue;
//
//                                $insert = $sql->insert();
//                                $insert->into('Proj_TenderWORateAnalysis');
//
//                                if ($stype == "R" || $stype=="I") {
//                                    $check_value = isset($postData['rateanal_' . $i . '_inc_' . $j]) ? 1 : 0;
//                                    $insert->Values(array('TenderWOTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid,  'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resamt_' . $j], 'number'), 'Formula' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype));
//                                } else {
//                                    $check_value = 1;
//                                    $insert->Values(array('TenderWOTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid));
//                                }
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//
//                            $iactRowid = $this->bsf->isNullCheck($postData['rowinfoidR_' . $i],'number');
//
//                            for ($j = 1; $j <= $iactRowid; $j++) {
//                                $iresid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_reskeyid_' . $j],'number');
//                                $iiowid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_iowkeyid_' . $j],'number');
//                                $stype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
//                                $sdes = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resdes_' . $j],'string');
//                                $irefid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_refid_' . $j],'number');
//                                $isortid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_rowrefid_' . $j],'number');
//                                $stranstype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
//                                $sratetype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_ratetype_' . $j],'string');
//
//                                if ($stype=="" || ($stype=="I" &&  $iiowid==0) || ($stype=="R" &&  $iresid==0) || $stype=="H" &&  $sdes=="")
//                                    continue;
//
//                                $insert = $sql->insert();
//                                $insert->into('Proj_TenderWORateAnalysis');
//
//                                if ($stype == "R" || $stype=="I") {
//                                    $check_value = isset($postData['rateanal_' . $i . '_inc_' . $j]) ? 1 : 0;
//                                    $insert->Values(array('TenderWOTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid,'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resamt_' . $j], 'number'), 'Formula' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype));
//                                } else {
//                                    $check_value = 1;
//                                    $insert->Values(array('TenderWOTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid));
//                                }
//
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//                        }
//                    }
//
//                    $iwgrowid = $this->bsf->isNullCheck($postData['wgrowid'],'number');
//                    for ($j = 1; $j <= $iwgrowid; $j++) {
//
//                        $sWgName = $this->bsf->isNullCheck($postData['newwgname_' . $j],'string');
//                        $iWgTypeid = $this->bsf->isNullCheck($postData['newwgid_' . $j],'number');
//                        $iactresgroup = $this->bsf->isNullCheck($postData['newwgactivity_' . $j],'number');
//                        $iautoRA = $this->bsf->isNullCheck($postData['newwgrateanal_' . $j],'number');
//                        $iactresource = $this->bsf->isNullCheck($postData['newwgactivityres_' . $j],'number');
//
//                        if ($sWgName =="" || $iWgTypeid ==0) continue;
//
//                        $insert = $sql->insert();
//                        $insert->into('Proj_TenderWOWorkGroupTrans');
//                        $insert->Values(array('WORegisterId' => $woid, 'WorkTypeId' => $iWgTypeid, 'WorkGroupName' => $sWgName, 'ActivityResGroup' => $iactresgroup,'AutoRateAnalysis' => $iautoRA, 'ActivityResource' => $iactresource));
//                    }
//
//                    $iresrowid = $this->bsf->isNullCheck($postData['resrowid'],'number');
//                    for ($j = 1; $j <= $iresrowid; $j++) {
//
//                        $sresName = $this->bsf->isNullCheck($postData['newresname_' . $j],'string');
//                        $iresgroupid = $this->bsf->isNullCheck($postData['newresgroupid_' . $j],'number');
//                        $irestypeid= $this->bsf->isNullCheck($postData['newrestypeid_' . $j],'number');
//                        $iunitid = $this->bsf->isNullCheck($postData['newresunitid_' . $j],'number');
//                        $drate = $this->bsf->isNullCheck($postData['newresrate_' . $j],'string');
//
//                        if ($sresName =="" || $iresgroupid==0) continue;
//
//                        $insert = $sql->insert();
//                        $insert->into('Proj_TenderWOResourceTrans');
//                        $insert->Values(array('WORegisterId' => $woid, 'ResourceName'=> $sresName ,'ResourceGroupId' => $iresgroupid ,'TypeId' => $irestypeid ,'UnitId'=> $iunitid,'RateType'=>'L','LRate'=>$drate));
//                    }
                    //boq

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$wotype,$wostype,'',$woid,0,0,'Project',$woNo,$userId,0,0);
                    //$this->redirect()->toRoute('project/workorder', array('controller' => 'tender', 'action' => 'workorderregister'));
                    $this->redirect()->toRoute('project/workorder', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $projectId));

                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {

                $enquiryId=  $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');
                $iQuotationId=0;
                $setupId = 0;
                $ipWOId=0;

                $aVNo = CommonHelper::getVoucherNo(101, date('Y/m/d'), 0, 0, $dbAdapter, "");

                if ($enquiryId !=0) {
                    if (($editid==0 && $mode=="") || $mode=='Amendment') {

                        $select = $sql->select();
                        $select->from('Proj_WORegister')
                            ->columns(array('WORegisterId'))
                            ->where(array('TenderEnquiryId'=>$enquiryId,'LiveWO'=>1))
                            ->order('WORegisterId Desc');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $quotreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($quotreg)) {
                            $editid = $quotreg['WORegisterId'];
                            if ($mode!='Amendment') $mode ='edit';
                        }
                    }
                }
                $sql = new Sql($dbAdapter);
                $sql = new Sql($dbAdapter);
                if ($editid != 0) {
                    //General
                    $select = $sql->select();
                    $select->from(array("a" => "Proj_WORegister"))
                        ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                        ->columns(array("WORegisterId","QuotationId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')"),'Approve'
                        , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                        , "PeriodType", "Duration", "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')")
                        , "AuthorityName", "AuthorityAddress", "AgreementType", "PeriodType", "Duration", "OrderAmount", "OrderPercent"
                        , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")));
                    $select->where(array('a.WORegisterId' => $editid));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($woregister)) $iQuotationId = $woregister['QuotationId'];

                    $this->_view->woregister = $woregister;

                    //WODocuments
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WODocuments"))
                        ->columns(array('DocumentId', 'Type', 'Description', 'URL'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wodocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOTerms
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOTerms"))
                        ->columns(array('MobilisationPercent', 'MobilisationAmount', 'MobilisationRecovery', 'RetentionPercent', 'PaymentSubmitPercent', 'PaymentFromSubmitDays', 'CertifyDays', 'PaymentFromCertifyDays', 'Notes', 'PeriodType', 'PeriodDay', 'PeriodWeekDay'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->woterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //DepositTrans
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WODepositTrans"))
                        ->columns(array('DepositType', 'DepostMode', 'RefNo', 'RefDate', 'Amount', 'ValidUpto'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wodeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOOtherTerms
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOOtherTerms"))
                        ->columns(array('TermsTitle', 'TermsDescription'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wootherterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOTechSpec
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOTechSpec"))
                        ->columns(array('Title', 'Specification'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wotechterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //WOMaterialPriceEscalation
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOMaterialPriceEscalation"))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('MaterialId'=> new Expression("a.ResourceId"), 'Rate', 'EscalationPer', 'RateCondition', 'ActualRate'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialbase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOClientSupplyMaterial
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOClientSupplyMaterial"))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('MaterialId'=> new Expression("a.ResourceId"), 'Rate', 'SType'), array('MaterialName'), array('UnitName'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialexcl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialAdvance
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_WOMaterialAdvance"))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('MaterialName'=>new Expression("ResourceName")), $select:: JOIN_LEFT)
                        ->columns(array('MaterialId'=> new Expression("a.ResourceId"), 'AdvPercent'))
                        ->where("WORegisterId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->womaterialadv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //setup
                }

                if ($iQuotationId ==0) {
                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationRegister')
                        ->columns(array('QuotationId'))
                        ->where(array('TenderEnquiryId'=>$enquiryId,'LiveQuotation'=>1))
                        ->order('QuotationId Desc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $quotreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($quotreg)) {
                        $iQuotationId = $quotreg['QuotationId'];
                    }
                }

                $select = $sql->select();
                $select->from( 'WF_CompanyMaster' )
                    ->columns(array("CompanyId", "CompanyName"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->companyMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from( 'WF_CostCentre' )
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->costCentre = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from('Proj_TenderEnquiry')
                    ->columns(array('data' => 'TenderEnquiryId', 'value' => new Expression("RefNo+ '-' +NameofWork")));
                //->where("BidWin=1 and OrderReceived=0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Project Types
                $select = $sql->select();
                $select->from('Proj_ProjectTypeMaster')
                    //->columns(array('data' => 'ProjectTypeId', 'value' => 'ProjectTypeName'));
                    ->columns(array('ProjectTypeId', 'ProjectTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Clients
                $select = $sql->select();
                $select->from('Proj_ClientMaster')
                    ->columns(array('data' => 'ClientId', 'value' => 'ClientName'));
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
                $select->from(array('a' => "Proj_Resource"))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                    ->columns(array("data" => 'ResourceId', "value" => 'ResourceName'))
                    ->where("a.TypeId=2");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Deposit
                $select = $sql->select();
                $select->from('Proj_DepositMaster')
                    ->columns(array("data" => 'TransId', "value" => 'DepositType'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->depositlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_PaymentModeMaster')
                    ->columns(array("data" => 'TransId', "value" => 'PaymentMode'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->paymentmode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array('a' =>'Proj_TenderEnquiry'))
                    ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select::JOIN_LEFT)
                    ->columns(array('NameOfWork','ClientId'))
                    ->where(array('a.TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $enquiryName ="";
                $clientName = "";
                $iclientId =0;
                if (!empty($followupName)) {
                    $enquiryName = $followupName['NameOfWork'];
                    $clientName = $followupName['ClientName'];
                    $iclientId = $followupName['ClientId'];
                }
                $this->_view->EnquiryName = $enquiryName;
                $this->_view->clientName = $clientName;
                $this->_view->clientId = $iclientId;

                //WOData
                $select = $sql->select();
                $select->from(array('a' => "Proj_WORegister"))
                    ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('ProjectName'=>new Expression("NameOfWork")), $select:: JOIN_LEFT)
                    ->columns(array('WORegisterId', 'WONo'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $wodataresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $newArray = array("WORegisterId" => "0", "WONo" => "Default", "ProjectName" => "Default");
                array_unshift($wodataresults, $newArray);
                $this->_view->wodata = $wodataresults;

                // Business Type Master
                $select = $sql->select();
                $select->from( 'WF_BusinessTypeMaster' )
                    ->columns(array("BusinessTypeId", "BusinessTypeName"))
                    ->where("BType='C'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->businessTypeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                if ($editid == 0) {
                    if ($aVNo["genType"] == false)
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];
                }

                $sWONo = "";
                $iWOId = 0;
                $iCCId = 0;


                $select = $sql->select();
                $select->from('Proj_WORegister')
                    ->columns(array('WONo', 'WORegisterId','CostCentreId'))
                    ->where(array('TenderEnquiryId' => $enquiryId, 'LiveWO' => 1))
                    ->order('WORegisterId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $nresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($nresults)) {
                    $sWONo = $nresults['WONo'];
                    $iWOId = $nresults['WORegisterId'];
                    $iCCId = $nresults['CostCentreId'];
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderWOSetup'))
                    ->join(array('b' => 'WF_BusinessTypeMaster'), 'a.BusinessTypeId = b.BusinessTypeId', array('BusinessTypeName'))
                    ->where('WORegisterId = ' . $iWOId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->woSetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $setupId = $this->_view->woSetup['SetupId'];

                if ($mode =='Amendment') {
                    $select = $sql->select();
                    $select->from('Proj_WORegister')
                        ->columns(array('Orders' => new Expression("Count(WORegisterId)")))
                        ->where("PWORegisterId =$iWOId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $nresults1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $wocount = $nresults1->Orders + 1;

                    if ($wocount != 0) $sWONo = $sWONo . '-A' . $wocount;

                    $ipWOId = $iWOId;
                    $editid = 0;
                    $mode = 'add';

                    $this->_view->svNo = $sWONo;
                }

                $this->_view->setupId = (isset($setupId) && $setupId != 0) ? $setupId : 0;
                $this->_view->genType = $aVNo["genType"];

                $this->_view->enquiryId=$enquiryId;
                $this->_view->workorderid = $editid;
                $this->_view->pworkorderid = $ipWOId;
                $this->_view->costcentreId = $iCCId;
                $this->_view->quotationid = $iQuotationId;
                $this->_view->mode = $mode;


            }

            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function bidstatusAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Bid Status");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $EnquiryFollowupId = $this->params()->fromRoute('EnquiryFollowupId');
        $enquiryId = $this->params()->fromRoute('enquiryId');
        $Date = date('d-m-Y');
        $enquiryId = $this->bsf->isNullCheck($enquiryId, 'number');
        $CallTypeId = 9;
        $EnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId, 'number');
        $resContractBidId = 0;

        if($EnquiryFollowupId == 0) {
            $select = $sql->select();
            $select->from('Proj_EnquiryFollowup')
                ->columns(array('EnquiryFollowupId'))
                ->where(array("TenderEnquiryId=$enquiryId and CallTypeId=$CallTypeId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $EnquiryFollowupId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $EnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId['EnquiryFollowupId'], 'number');
        }

        if($EnquiryFollowupId != 0) {
            //Load Values for edit page
            $select = $sql->select();
            $select->from(array('a' => 'Proj_EnquiryFollowup'))
                ->columns(array("RefDate" => new Expression("Convert(varchar(10),a.RefDate,105)")))
                ->join(array('b' => 'Proj_ContractBidStatus'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('BidTransId','TenderEnquiryId','BStatus','NoOfParticipants','Position','Remarks','Measurement'),$select::JOIN_LEFT)
                ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $resContractBid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
            $this->_view->ContractBidStatus = $resContractBid;

            if(count($resContractBid) > 0) {
                $resContractBidId = $resContractBid['BidTransId'];
            }
            //Proj_ContractBidCompetitorTrans
            $select = $sql->select();
            $select->from('Proj_ContractBidCompetitorTrans')
                ->where(array('BidTransId'=>$resContractBidId));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->BidCompetitorTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        }

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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $postData = $request->getPost();

                try {
                    if($EnquiryFollowupId != 0) {
                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }
                        $CallTypeId = 9;
                        $NoOfParticipants = $this->bsf->isNullCheck($postData['Participants'], 'number');
                        //Proj_EnquiryFollowup
                        $update = $sql->update();
                        $update->table('Proj_EnquiryFollowup');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')))
                            ->where(array('EnquiryFollowupId'=>$EnquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $EnquiryFollowupId;

                        //Query to take BidTransId
//                        $select = $sql->select();
//                        $select->from(array('a' => 'Proj_EnquiryFollowup'))
//                            ->join(array('b' => 'Proj_ContractBidStatus'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('BidTransId','TenderEnquiryId','RefDate','BStatus','NoOfParticipants','Position','Remarks'),$select::JOIN_LEFT)
//                            ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
//                        $statement = $sql->getSqlStringForSqlObject( $select );
//                        $resContractBidId= $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
//                        if(count($resContractBid ) >0 ) {
//                            echo $resContractBidId = $resContractBidId['BidTransId'];die;
//                        }

                        //Proj_ContractBidStatus
                        $update = $sql->update();
                        $update->table('Proj_ContractBidStatus');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'BStatus' =>$this->bsf->isNullCheck($postData['Status'], 'string')
                        ,'NoOfParticipants'=>$NoOfParticipants
                        ,'Position' => $this->bsf->isNullCheck($postData['Position'], 'string')
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        ,'Measurement' => $this->bsf->isNullCheck($postData['Measurement'], 'string')
                        ,'EnquiryFollowupId'=> $FollowupId))
                            ->where(array('BidTransId' =>  $resContractBidId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Proj_ContractBidCompetitorTrans
                        $delete = $sql->delete();
                        $delete->from('Proj_ContractBidCompetitorTrans')
                            ->where("BidTransId=$resContractBidId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        for($i = 1;$i<=$NoOfParticipants;$i++) {
                            $insert = $sql->insert();
                            $insert->into('Proj_ContractBidCompetitorTrans');
                            $insert->Values(array('BidTransId' =>  $resContractBidId
                            , 'Position' =>  $this->bsf->isNullCheck($postData['Position_'.$i], 'string')
                            ,'CompetitorName' => $this->bsf->isNullCheck($postData['ContractorName_'.$i], 'string')
                            ,'Amount'=> $this->bsf->isNullCheck($postData['Amount_'.$i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup','enquiryId' => $enquiryId));
                    } else {
                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }
                        $CallTypeId = 9;
                        $NoOfParticipants = $this->bsf->isNullCheck($postData['Participants'], 'number');
                        //Proj_EnquiryFollowup
                        $insert = $sql->insert();
                        $insert->into('Proj_EnquiryFollowup');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Proj_ContractBidStatus
                        $insert = $sql->insert();
                        $insert->into('Proj_ContractBidStatus');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'BStatus' =>$this->bsf->isNullCheck($postData['Status'], 'string')
                        ,'NoOfParticipants'=>$NoOfParticipants
                        ,'Position' => $this->bsf->isNullCheck($postData['Position'], 'string')
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        ,'Measurement' => $this->bsf->isNullCheck($postData['Measurement'], 'string')
                        ,'EnquiryFollowupId'=> $FollowupId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $BidTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Proj_ContractBidCompetitorTrans
                        for($i = 1;$i<=$NoOfParticipants;$i++) {
                            $insert = $sql->insert();
                            $insert->into('Proj_ContractBidCompetitorTrans');
                            $insert->Values(array('BidTransId' =>  $BidTransId
                            , 'Position' =>  $this->bsf->isNullCheck($postData['Position_'.$i], 'string')
                            ,'CompetitorName' => $this->bsf->isNullCheck($postData['ContractorName_'.$i], 'string')
                            ,'Amount'=> $this->bsf->isNullCheck($postData['Amount_'.$i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        /*$update = $sql->update();
                        $update->table('Proj_TenderEnquiry')
                            ->set(array('BidTransId' => $BidTransId))
                            ->where(array('TenderEnquiryId' => $enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        $select = $sql->select();
                        $select->from('Proj_TenderProcessMaster')
                            ->columns(array('TenderProcessId'))
                            ->where(array('ProcessName' => 'Commercial-Bid-Opening'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $processId = $processName['TenderProcessId'];

                        $update = $sql->update();
                        $update->table('Proj_TenderProcessTrans')
                            ->set(array('Flag' => 1))
                            ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup','enquiryId' => $enquiryId));
                    }

                } catch(PDOException $e){
                    $connection->rollback();
                }

            }

            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array("NameOfWork"))
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $enquiryName ="";
            if (!empty($followupName)) $enquiryName = $followupName['NameOfWork'];
            $this->_view->EnquiryName = $enquiryName;

            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderQuotationTrans'))
                ->join(array('b' => 'Proj_TenderQuotationRegister'), 'a.QuotationId=b.QuotationId', array(),$select::JOIN_LEFT)
                ->columns(array('SerialNo','Specification'))
                ->where(array('b.TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->quotations = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->enquiryId = $enquiryId;
            $this->_view->Date= $Date;
            $this->_view->EnquiryFollowupId= $EnquiryFollowupId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function documentAction(){
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
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $files = $request->getFiles();
//                    echo '<pre>'; print_r($files); die;
                    $iEnquiryId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');

                    $select = $sql->select();
                    $select->from( array('a' => 'Proj_ContractDocumentTrans'))
                        ->columns(array('dURL'))
                        ->where("a.TenderEnquiryId=$iEnquiryId");
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $drawingdel = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    foreach($drawingdel as $delurl) {
                        $url = $delurl['dURL'];
                        if($url != '' || !is_null($url)) {
                            $delFilesUrl[] = 'public' . $url;
                        }
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_ContractDocumentTrans')
                        ->where("TenderEnquiryId=$iEnquiryId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $documentrowid = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                    for ($i = 1; $i <= $documentrowid; $i++) {
                        $iTypeId = $this->bsf->isNullCheck($postData['docTypeId_' . $i], 'number');
                        $sDescription = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');
                        $iUserId = $this->bsf->isNullCheck($postData['userId_' . $i], 'number');
                        $sremarks = $this->bsf->isNullCheck($postData['remarks_' . $i], 'string');
                        $dRefDate = date('Y-m-d', strtotime($postData['refdate_'.$i]));
                        $url = $this->bsf->isNullCheck($postData['docFile_' . $i], 'string');

                        if ($iTypeId == 0 || $sDescription == '')
                            continue;

                        if($url == '') {
                            if ($files['docFile_' . $i]['name']) {

                                $dir = 'public/uploads/project/tender/document/' . $iEnquiryId . '/';
                                $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                                if ($filename) {
                                    // update valid files only
                                    $url = '/uploads/project/tender/document/' . $iEnquiryId . '/' . $filename;
                                }
                            }
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_ContractDocumentTrans');
                        $insert->Values(array('TenderEnquiryId' => $iEnquiryId, 'RefDate' => date('Y-m-d', strtotime($dRefDate)),'DocumentTypeId' => $iTypeId,'UserId'=>$iUserId, 'DocumentDescription' => $sDescription,'Remarks'=> $sremarks, 'dURL' => $url));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $DocumentId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        /*$update = $sql->update();
                        $update->table('Proj_TenderEnquiry');
                        $update->set(array('DocumentId' => $DocumentId))
                            ->where(array('TenderEnquiryId'=>$iEnquiryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        $select = $sql->select();
                        $select->from('Proj_TenderProcessMaster')
                            ->columns(array('TenderProcessId'))
                            ->where(array('ProcessName' => 'Documents'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $processId = $processName['TenderProcessId'];

                        $update = $sql->update();
                        $update->table('Proj_TenderProcessTrans')
                            ->set(array('Flag' => 1))
                            ->where(array('TenderEnquiryId' => $iEnquiryId, 'TenderProcessId' => $processId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $iEnquiryId));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }

        $iEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');

        $select = $sql->select();
        $select->from(array('a' => "Proj_ContractDocumentTrans"))
            ->join(array('b' => 'Proj_ContractDocumentTypeMaster'), 'a.DocumentTypeId=b.DocumentTypeId', array('DocumentTypeName'), $select:: JOIN_LEFT)
            ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array('UserName'), $select:: JOIN_LEFT)
            ->columns(array('DocumentId', 'RefDate','DocumentTypeId','UserId','DocumentDescription','Remarks','dURL'))
            ->where("TenderEnquiryId=$iEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arrdocument = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_ContractDocumentTypeMaster')
            ->columns(array("data" => 'DocumentTypeId', "value" => 'DocumentTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arrdocumenttype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array("data" => 'UserId', "value" => 'UserName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arruser = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->enquiryId = $iEnquiryId;

        $select = $sql->select();
        $select->from('Proj_TenderEnquiry')
            ->columns(array("NameOfWork"))
            ->where(array('TenderEnquiryId'=>$iEnquiryId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $enquiryName ="";
        if (!empty($followupName)) $enquiryName = $followupName['NameOfWork'];
        $this->_view->EnquiryName = $enquiryName;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

        return $this->_view;

    }

    public function preQualstatusAction(){
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

        $EnquiryFollowupId = $this->params()->fromRoute('EnquiryFollowupId');
        $enquiryId = $this->params()->fromRoute('enquiryId');
        $CallTypeId = $this->params()->fromRoute('CallTypeId');
        $Date =date('d-m-Y');;
        $enquiryId = $this->bsf->isNullCheck($enquiryId, 'number');
        $EnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId, 'number');
        $CallTypeId = $this->bsf->isNullCheck($CallTypeId, 'number');
        if($EnquiryFollowupId == 0) {
            $select = $sql->select();
            $select->from('Proj_EnquiryFollowup')
                ->columns(array('EnquiryFollowupId'))
                ->where(array("TenderEnquiryId=$enquiryId and CallTypeId=$CallTypeId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $EnquiryFollowupId = $resContractMeeting = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $EnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId['EnquiryFollowupId'], 'number');
        }

        if($EnquiryFollowupId != 0) {
            //Load Values for edit page
            $select = $sql->select();
            $select->from(array('a' => 'Proj_EnquiryFollowup'))
                ->columns(array("RefDate" => new Expression("Convert(varchar(10),a.RefDate,105)")))
                ->join(array('b' => 'Proj_ContractPreQualBidStatus'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('PreQualStatusId','TenderEnquiryId','CallTypeId','BStatus','NoOfParticipants','Remarks'),$select::JOIN_LEFT)
                ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->ContractBidStatus =$resContractBid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
            if(count($resContractBid ) >0 ) {
                $resContractBid = $resContractBid['PreQualStatusId'];
            }
        }


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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $postData = $request->getPost();

                try {
                    if($EnquiryFollowupId != 0) {
                        //Update
//                        echo '<pre>'; print_r($postData); die;
                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }
                        $NoOfParticipants = $this->bsf->isNullCheck($postData['Participants'], 'number');
                        //Proj_EnquiryFollowup
                        $update = $sql->update();
                        $update->table('Proj_EnquiryFollowup');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')))
                            ->where(array('EnquiryFollowupId'=>$EnquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $EnquiryFollowupId;

                        //Query to take PreQualStatusId
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_EnquiryFollowup'))
                            ->join(array('b' => 'Proj_ContractPreQualBidStatus'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('PreQualStatusId','TenderEnquiryId','CallTypeId','RefDate','BStatus','NoOfParticipants','Remarks'),$select::JOIN_LEFT)
                            ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $resContractBidId = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        if(count($resContractBidId) >0 ) {
                            $resContractBidId = $resContractBidId['PreQualStatusId'];
                        }

                        //Proj_EnquiryFollowup
                        $update = $sql->update();
                        $update->table('Proj_ContractPreQualBidStatus');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        ,'CallTypeId'=>$CallTypeId
                        ,'EnquiryFollowupId'=> $FollowupId
                        ,'RefDate' => $RefDate
                        ,'BStatus' =>$this->bsf->isNullCheck($postData['Status'], 'string')
                        ,'NoOfParticipants'=>$NoOfParticipants
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')))
                            ->where(array('PreQualStatusId' =>  $resContractBidId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
                    } else {
                        //Insert

//                    echo '<pre>'; print_r($postData); die;
                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }
                        $NoOfParticipants = $this->bsf->isNullCheck($postData['Participants'], 'number');
                        //Proj_EnquiryFollowup
                        $insert = $sql->insert();
                        $insert->into('Proj_EnquiryFollowup');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Proj_ContractPreQualBidStatus
                        $insert = $sql->insert();
                        $insert->into('Proj_ContractPreQualBidStatus');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        ,'CallTypeId'=>$CallTypeId
                        ,'EnquiryFollowupId'=> $FollowupId
                        ,'RefDate' => $RefDate
                        ,'BStatus' =>$this->bsf->isNullCheck($postData['Status'], 'string')
                        ,'NoOfParticipants'=>$NoOfParticipants
                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $PreQualStatusId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        /*$update = $sql->update();
                        $update->table('Proj_TenderEnquiry')
                            ->set(array('PreQualStatusId' => $PreQualStatusId))
                            ->where(array("'TenderEnquiryId' = $enquiryId and 'CallTypeId'=$CallTypeId"));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        if($CallTypeId == 8)
                            $proName = 'Pre-Qualification';
                        else if($CallTypeId == 9)
                            $proName = 'Technical-Bid-Opening';

                        $select = $sql->select();
                        $select->from('Proj_TenderProcessMaster')
                            ->columns(array('TenderProcessId'))
                            ->where(array('ProcessName' => $proName));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $processId = $processName['TenderProcessId'];

                        $update = $sql->update();
                        $update->table('Proj_TenderProcessTrans')
                            ->set(array('Flag' => 1))
                            ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup','enquiryId' => $enquiryId));
                    }

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }


            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array("NameOfWork"))
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $enquiryName ="";
            if (!empty($followupName)) $enquiryName = $followupName['NameOfWork'];
            $this->_view->EnquiryName = $enquiryName;

            $this->_view->enquiryId = $enquiryId;
            $this->_view->Date= $Date;
            $this->_view->CallTypeId= $CallTypeId;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function meetingAction(){
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
        $enquiryFollowupId = $this->params()->fromRoute('EnquiryFollowupId');
        $EnquiryFollowupId=$this->bsf->isNullCheck($enquiryFollowupId,'number');
        $enquiryId = $this->params()->fromRoute('enquiryId');
        $CallTypeId = $this->params()->fromRoute('CallTypeId');
        $Date = date('d-m-Y');
        $enquiryId = $this->bsf->isNullCheck($enquiryId, 'number');
        $CallTypeId = $this->bsf->isNullCheck($CallTypeId, 'number');
        if($EnquiryFollowupId == 0) {
            $select = $sql->select();
            $select->from('Proj_EnquiryFollowup')
                ->columns(array('EnquiryFollowupId'))
                ->where(array("TenderEnquiryId=$enquiryId and CallTypeId=$CallTypeId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $EnquiryFollowupId = $resContractMeeting = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $EnquiryFollowupId = $this->bsf->isNullCheck($EnquiryFollowupId['EnquiryFollowupId'], 'number');
        }

        if($EnquiryFollowupId != 0) {
            //Load Values for edit page
            $select = $sql->select();
            $select->from(array('a' => 'Proj_EnquiryFollowup'))
                ->join(array('b' => 'Proj_ContractMeeting'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('MeetingId','TenderEnquiryId','CallTypeId','RefDate','Place','Agenda','Participants','Note'),$select::JOIN_LEFT)
                ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->ContractMeeting =$resContractMeeting = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
            if(count($resContractMeeting ) >0 ) {
                $resContractMeeting = $resContractMeeting ['MeetingId'];
            }
        }


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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $postData = $request->getPost();
//                echo '<pre>'; print_r($postData); die;
                try {
                    if($EnquiryFollowupId != 0) {
                        //Update
////
//                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }

                        //Proj_EnquiryFollowup
                        $update = $sql->update();
                        $update->table('Proj_EnquiryFollowup');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId))
//                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')
                            ->where(array('EnquiryFollowupId'=>$EnquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $EnquiryFollowupId;

                        //Query to take MeetingId
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_EnquiryFollowup'))
                            ->join(array('b' => 'Proj_ContractMeeting'), 'a.EnquiryFollowupId=b.EnquiryFollowupId', array('MeetingId','TenderEnquiryId','CallTypeId','RefDate','Place','Agenda','Participants','Note'),$select::JOIN_LEFT)
                            ->where(array('a.EnquiryFollowupId'=>$EnquiryFollowupId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->ContractMeeting =$resContractMeeting = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        if(count($resContractMeeting ) >0 ) {
                            $resContractMeeting = $resContractMeeting ['MeetingId'];
                        }

                        //Proj_EnquiryFollowup
                        $update = $sql->update();
                        $update->table('Proj_ContractMeeting');
                        $update->set(array('TenderEnquiryId' => $enquiryId
                        ,'CallTypeId'=>$CallTypeId
                        ,'EnquiryFollowupId'=> $FollowupId
                        ,'RefDate' => $RefDate
                        ,'Place' =>$this->bsf->isNullCheck($postData['Place'], 'string')
                        ,'Agenda' =>$this->bsf->isNullCheck($postData['Agenda'], 'string')
                        ,'Participants' =>$this->bsf->isNullCheck($postData['Participants'], 'string')
                        ,'Note'=>  $this->bsf->isNullCheck($postData['Note'], 'string')))
                            ->where(array('MeetingId' =>  $resContractMeeting));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
                    } else {
                        //Insert

//                    echo '<pre>'; print_r($postData); die;
                        $RefDate = NULL;
                        if($postData['ref_date']) {
                            $RefDate = date('Y-m-d', strtotime($postData['ref_date']));
                        }

                        //Proj_EnquiryFollowup
                        $insert = $sql->insert();
                        $insert->into('Proj_EnquiryFollowup');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        , 'RefDate' => $RefDate
                        ,'CallTypeId' =>$CallTypeId));
//                        ,'Remarks'=>  $this->bsf->isNullCheck($postData['Remarks'], 'string')
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $FollowupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Proj_ContractPreQualBidStatus
                        $insert = $sql->insert();
                        $insert->into('Proj_ContractMeeting');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId
                        ,'CallTypeId'=>$CallTypeId
                        ,'EnquiryFollowupId'=> $FollowupId
                        ,'RefDate' => $RefDate
                        ,'Place' =>$this->bsf->isNullCheck($postData['Place'], 'string')
                        ,'Agenda' =>$this->bsf->isNullCheck($postData['Agenda'], 'string')
                        ,'Participants' =>$this->bsf->isNullCheck($postData['Participants'], 'string')
                        ,'Note'=>  $this->bsf->isNullCheck($postData['Note'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $meetingId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        /*$update = $sql->update();
                        $update->table('Proj_TenderEnquiry')
                            ->set(array('MeetingId' => $meetingId))
                            ->where(array("'TenderEnquiryId' =$enquiryId and 'CallTypeId' =>$CallTypeId"));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        if($CallTypeId == 5)
                            $proName = 'Pre-Bid-Meeting';
                        else if($CallTypeId == 11)
                            $proName = 'Negotiation-Meeting';

                        $select = $sql->select();
                        $select->from('Proj_TenderProcessMaster')
                            ->columns(array('TenderProcessId'))
                            ->where(array('ProcessName' => $proName));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $processName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $processId = $processName['TenderProcessId'];

                        $update = $sql->update();
                        $update->table('Proj_TenderProcessTrans')
                            ->set(array('Flag' => 1))
                            ->where(array('TenderEnquiryId' => $enquiryId, 'TenderProcessId' => $processId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        $this->redirect()->toRoute('project/followup', array('controller' => 'followup', 'action' => 'followup','enquiryId' => $enquiryId));
                    }

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array("NameOfWork"))
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $enquiryName ="";
            if (!empty($followupName)) $enquiryName = $followupName['NameOfWork'];
            $this->_view->EnquiryName = $enquiryName;

            $this->_view->enquiryId = $enquiryId;
            $this->_view->Date= $Date;
            $this->_view->CallTypeId= $CallTypeId;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function tenderQuotationAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || RFC IOW");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            $identity = 0;
            $postData = $request->getPost();
            $iQuotationId = $this->bsf->isNullCheck($postData['quotationId'],'number');
            try {
                if ($iQuotationId != 0) {
                    $identity = $iQuotationId;

                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationRegister')
                        ->columns(array('TenderEnquiryId'))
                        ->where(array('QuotationId' => $identity));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $quoteReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $iEnquiryId = 0;
                    if (!empty($quoteReg)) $iEnquiryId= $quoteReg['TenderEnquiryId'];

                    $sRefNo = $this->bsf->isNullCheck($postData['refno'], 'string');
                    $update = $sql->update();
                    $update->table('Proj_TenderQuotationRegister');
                    $update->set(array(
                        'RefNo' => $sRefNo,
                        'RefDate' => date('Y-m-d', strtotime($postData['refdate'])),
                    ));
                    $update->where(array('QuotationId' => $identity));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationTrans')
                        ->where(array("QuotationId" => $identity));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    $delete = $sql->delete();
//                    $delete->from('Proj_TenderWorkGroup')
//                        ->where(array("TenderEnquiryId" => $iEnquiryId));
//                    $statement = $sql->getSqlStringForSqlObject($delete);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationResourceTrans')
                        ->where(array("QuotationId" => $identity));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationResourceUsed')
                        ->where(array("QuotationId" => $identity));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $subQuery = $sql->select();
                    $subQuery->from("Proj_TenderQuotationTrans")
                        ->columns(array("QuotationTransId"));
                    $subQuery->where(array('QuotationId' => $identity));

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationQualTrans')
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationRateAnalysis')
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWBSTrans')
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $iRowId = $this->bsf->isNullCheck($postData['rowid'],'number');

                    for ($i = 1; $i <= $iRowId; $i++) {

                        $wgroupid=$this->bsf->isNullCheck($postData['workgroupid_' . $i],'string');
                        if (substr($wgroupid, 0, 2) == '0.') $wgroupid= 0;
                        $wtypeid=$this->bsf->isNullCheck($postData['worktypeid_' . $i],'number');
                        $pwgroupid=$this->bsf->isNullCheck($postData['pworkgroupid_' . $i],'string');
                        if (substr($pwgroupid, 0, 2) == '0.') $pwgroupid= 0;
                        $libiowid =  $this->bsf->isNullCheck($postData['libiowkeyid_' . $i],'number');
                        $iHeader = $this->bsf->isNullCheck($postData['raheaderval_' . $i],'number');
                        $sparentName = $this->bsf->isNullCheck($postData['newparentname_' . $i], 'string');
                        $ipiowid = $this->bsf->isNullCheck($postData['piowid_' . $i], 'number');


//                        $myArray = explode('.', $postData['parentid_' . $i]);
//                        if (count($myArray) >1) {$wgroupid=$myArray[0];$parentid=intval($myArray[1]); }
//                        else{ $wgroupid=$myArray[0];$parentid=0;}
//
//                        $siowspec = $this->bsf->isNullCheck($postData['spec_' . $i],'string');
//                        $iMiowid= $this->bsf->isNullCheck($postData['iowid_' . $i],'number');
//                        if ($siowspec =="") continue;
//
//                        $pwgid = $this->bsf->isNullCheck($postData['workgroupid_'. $i],'string');
//                        if (substr($pwgid, 0, 2) == '0.') {
//                            $pwgid= 0;
//                        }

                        $sSpec = $this->bsf->isNullCheck($postData['spec_' . $i], 'string');
                        if ($sSpec =="") continue;

                        $dWasteAmt = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totwastage'], 'number');
                        $dBaseRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totbaserate'], 'number');
                        $dQualValue= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totqualrate'], 'number');
                        $dTotRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totrate'], 'number');
                        $dNetRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totnetrate'], 'number');

                        $dRWasteAmt = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totwastage'], 'number');
                        $dRBaseRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totbaserate'], 'number');
                        $dRQualValue= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totqualrate'], 'number');
                        $dRTotRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totrate'], 'number');
                        $dRNetRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totnetrate'], 'number');

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationTrans');
                        $insert->Values(array('QuotationId' => $identity,'IOWId'=>$libiowid,'WorkGroupId' => $wgroupid,
                            'WorkTypeId'=>$wtypeid, 'ParentId' => $ipiowid,'RefSerialNo'=>$this->bsf->isNullCheck($postData['refserialno_' . $i],'string'),  'SerialNo' => $this->bsf->isNullCheck($postData['serialno_' . $i],'string'), 'Specification' => $sSpec, 'UnitId' => $this->bsf->isNullCheck($postData['unitkeyid_' . $i],'number'),
                            'ShortSpec'=>$this->bsf->isNullCheck($postData['shortspec_' . $i],'string'),'ParentText'=>$this->bsf->isNullCheck($postData['parentid_' . $i],'string'),'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'),'Amount' => $this->bsf->isNullCheck($postData['amt_' . $i], 'number'),'WorkingQty'=> $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AnalQty'],'number'),'RWorkingQty'=> $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_AnalQty'],'number'),
                            'CementRatio'=> $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AQty'],'number'),'SandRatio'=> $this->bsf->isNullCheck($postData['rateanal_' . $i . '_BQty'],'number'),'MetalRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_CQty'],'number'),'ThickQty'=> $this->bsf->isNullCheck($postData['rateanal_' . $i . '_thick'],'number'),
                            'ProjectWorkGroupId' => $pwgroupid, 'ProjectWorkGroupName' => $this->bsf->isNullCheck($postData['pworkgroupname_'. $i],'string'),
                            'MixType' => $this->bsf->isNullCheck($postData['ratetype_'. $i],'string'), 'QuotedRate' => $this->bsf->isNullCheck($postData['qrate_'. $i],'number'), 'QuotedAmount' => $this->bsf->isNullCheck($postData['qamt_'. $i],'number'),'WastageAmt' => $dWasteAmt, 'BaseRate' => $dBaseRate, 'QualifierValue' => $dQualValue,
                            'TotalRate' => $dTotRate, 'NetRate' => $dNetRate, 'RWastageAmt' => $dRWasteAmt, 'RBaseRate' => $dRBaseRate, 'RQualifierValue' => $dRQualValue,
                            'RTotalRate' => $dRTotRate, 'RNetRate' => $dRNetRate,'ParentName'=>$sparentName,'Header'=>$iHeader));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $itransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $iwbsRowId = $this->bsf->isNullCheck($postData['wbstable_' . $i .'_rows'],'number');
                        for ($n = 1; $n <= $iwbsRowId; $n++) {
                            $iwbsId = $this->bsf->isNullCheck($postData['wbstable_' . $i .'_wbsid_' . $n],'number');
                            $dwbsqty = floatval($this->bsf->isNullCheck($postData['wbstable_' . $i .'_qty_' . $n],'number'));
                            if ($dwbsqty !=0 && $iwbsId !=0) {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderWBSTrans');
                                $insert->Values(array('TenderEnquiryId'=> $postData['enquiryId'],'QuotationTransId' => $itransid, 'WBSId' => $iwbsId, 'Qty' => $dwbsqty));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $iactRowid = $this->bsf->isNullCheck($postData['rowinfoid_' . $i],'number');
                        for ($j = 1; $j <= $iactRowid; $j++) {
                            $iresid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_reskeyid_' . $j],'number');
                            if (substr($iresid, 0, 2) == '0.') $iresid= 0;
                            $iiowid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_iowkeyid_' . $j],'number');
                            $stype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
                            $sdes = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resdes_' . $j],'string');
                            $irefid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_refid_' . $j],'number');
                            $isortid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_rowrefid_' . $j],'number');
                            $stranstype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
                            $sratetype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_ratetype_' . $j],'string');
                            $dweigtage = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_Weightage_' . $j], 'number');
                            $dwasteper = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_Wastage_' . $j], 'number');
                            $dwasteqty = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_WastageQty_' . $j], 'number');
                            $dwasteamt = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_WastageAmount_' . $j], 'number');
                            $sresName = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_newresname_' . $j], 'string');

                            if ($stype=="") { continue;}

                            if ($stype=="I" &&  $iiowid ==0) { continue;}
                            else if ($stype=="R" &&  $iresid ==0) { continue;}
                            else if ($stype=="H" &&  $sdes =="") { continue;}

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationRateAnalysis');

                            if ($stype == "R" || $stype=="I") {
                                $check_value = isset($postData['rateanal_' . $i . '_inc_' . $j]) ? 1 : 0;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid, 'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resamt_' . $j], 'number'),
                                    'Formula' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype,'Weightage'=>$dweigtage,'Wastage'=>$dwasteper,'WastageQty'=>$dwasteqty,'WastageAmount'=>$dwasteamt,'ResourceName'=>$sresName));
                            } else {
                                $check_value = 1;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid,'ResourceName'=>$sresName));
                            }
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $iactRowidR = $this->bsf->isNullCheck($postData['rowinfoidR_' . $i],'number');
                        for ($j = 1; $j <= $iactRowidR; $j++) {

                            $iresid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_reskeyid_' . $j],'number');
                            if (substr($iresid, 0, 2) == '0.') $iresid= 0;
                            $iiowid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_iowkeyid_' . $j],'number');
                            $stype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
                            $sdes = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resdes_' . $j],'string');
                            $irefid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_refid_' . $j],'number');
                            $isortid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_rowrefid_' . $j],'number');
                            $stranstype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
                            $sratetype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_ratetype_' . $j],'string');
                            $dweigtage = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_Weightage_' . $j], 'number');
                            $dwasteper = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_Wastage_' . $j], 'number');
                            $dwasteqty = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_WastageQty_' . $j], 'number');
                            $dwasteamt = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_WastageAmount_' . $j], 'number');
                            $sresName = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_newresname_' . $j], 'string');

                            if ($stype=="") { continue;}

                            if ($stype=="I" &&  $iiowid ==0) { continue;}
                            else if ($stype=="R" &&  $iresid ==0) { continue;}
                            else if ($stype=="H" &&  $sdes =="") { continue;}

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationRateAnalysis');

                            if ($stype == "R" || $stype=="I") {
                                $check_value = isset($postData['rateanalR_' . $i . '_inc_' . $j]) ? 1 : 0;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid, 'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resamt_' . $j], 'number'), 'Formula' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype,'Weightage'=>$dweigtage,'Wastage'=>$dwasteper,'WastageQty'=>$dwasteqty,'WastageAmount'=>$dwasteamt,'ResourceName'=>$sresName));
                            } else {
                                $check_value = 1;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid,'ResourceName'=>$sresName));

                            }
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['Qual_' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Sign_' . $k], 'string');


                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['Qual_' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['Qual_' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetAmt_' . $k], 'number');
                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationQualTrans');
                            $insert->Values(array('QuotationTransId' =>$itransid,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess, 'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'S'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRRowId_'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['QualR_' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['QualR_' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Sign_' . $k], 'string');

                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['QualR_' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['QualR_' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetAmt_' . $k], 'number');

                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationQualTrans');
                            $insert->Values(array('QuotationTransId' =>$itransid,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'R'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $update = $sql->update();
                    $update->table('Proj_TenderQuotationTrans');
                    $update->set(array(
                        'PrevQuotationTransId' => new Expression("QuotationTransId")
                    ));
                    $update->where(array('PrevQuotationTransId' => 0,'QuotationId'=>$identity));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $iresRowId = $this->bsf->isNullCheck($postData['resrows'],'number');
                    for ($x = 1; $x <= $iresRowId; $x++) {
                        $iresid = $this->bsf->isNullCheck($postData['rateresid_' . $x],'number');
                        $dresQty = $this->bsf->isNullCheck($postData['rateresqty_' . $x],'number');
                        $dresRate = $this->bsf->isNullCheck($postData['rateresrate_' . $x],'number');
                        $dresAmt = $this->bsf->isNullCheck($postData['rateresamt_' . $x],'number');

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationResourceUsed');
                        $insert->Values(array('QuotationId' =>$identity,
                            'ResourceId'=>$iresid,'Qty'=>$dresQty,'Rate'=>$dresRate,'Amount'=>$dresAmt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $iwgrowid = $this->bsf->isNullCheck($postData['newwgowid'],'number');
                    for ($j = 1; $j <= $iwgrowid; $j++) {
                        $sWgslNo = $this->bsf->isNullCheck($postData['newwgslno_' . $j],'string');
                        $sWgName = $this->bsf->isNullCheck($postData['newwgname_' . $j],'string');
                        $ilWgid = $this->bsf->isNullCheck($postData['newlwgid_' . $j],'number');
                        $ilWgtypeid = $this->bsf->isNullCheck($postData['newwgtypeid_' . $j],'number');


                        if ($sWgName =="") continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWorkGroup');
                        $insert->Values(array('TenderEnquiryId' => $postData['enquiryId'],'SerialNo' =>$sWgslNo, 'WorkGroupId' => $ilWgid, 'WorkGroupName' => $sWgName,'WorkTypeId'=>$ilWgtypeid));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $wgid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $update = $sql->update();
                        $update->table('Proj_TenderQuotationTrans');
                        $update->set(array(
                            'ProjectWorkGroupId' => $wgid,
                        ));
                        $update->where(array('ProjectWorkGroupName' => $sWgName,'ProjectWorkGroupId'=>0,'QuotationId'=>$identity));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

//                    $iwgrowid = $this->bsf->isNullCheck($postData['wgrowid'],'number');
//                    for ($j = 1; $j <= $iwgrowid; $j++) {
//
//                        $sWgName = $this->bsf->isNullCheck($postData['newwgname_' . $j],'string');
//                        $iWgTypeid = $this->bsf->isNullCheck($postData['newwgid_' . $j],'number');
//                        $iactresgroup = $this->bsf->isNullCheck($postData['newwgactivity_' . $j],'number');
//                        $iautoRA = $this->bsf->isNullCheck($postData['newwgrateanal_' . $j],'number');
//                        $iactresource = $this->bsf->isNullCheck($postData['newwgactivityres_' . $j],'number');
//
//                        if ($sWgName =="" || $iWgTypeid ==0) continue;
//
//                        $insert = $sql->insert();
//                        $insert->into('Proj_TenderQuotationWorkGroupTrans');
//                        $insert->Values(array('QuotationId' => $identity, 'WorkTypeId' => $iWgTypeid, 'WorkGroupName' => $sWgName, 'ActivityResGroup' => $iactresgroup,'AutoRateAnalysis' => $iautoRA, 'ActivityResource' => $iactresource));
//                    }

                    $iresrowid = $this->bsf->isNullCheck($postData['resrowid'],'number');
                    for ($j = 1; $j <= $iresrowid; $j++) {
                        $sresName = $this->bsf->isNullCheck($postData['newresname_' . $j],'string');
                        $iresgroupid = $this->bsf->isNullCheck($postData['newresgroupid_' . $j],'number');
                        $irestypeid= $this->bsf->isNullCheck($postData['newrestypeid_' . $j],'number');
                        $iunitid = $this->bsf->isNullCheck($postData['newresunitid_' . $j],'number');
                        $drate = $this->bsf->isNullCheck($postData['newresrate_' . $j],'string');

                        if ($sresName =="" || $iresgroupid==0) continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationResourceTrans');
                        $insert->Values(array('QuotationId' => $identity, 'ResourceName'=> $sresName ,'ResourceGroupId' => $iresgroupid ,'TypeId' => $irestypeid ,'UnitId'=> $iunitid,'RateType'=>'L','LRate'=>$drate));
                    }

                    //for other cost
                    $arr_type_tables = array('1' => 'Proj_TenderOHItemTrans', '2' => 'Proj_TenderOHMaterialTrans'
                    , '3' => 'Proj_TenderOHLabourTrans', '4' => 'Proj_TenderOHServiceTrans', '5' => 'Proj_TenderOHMachineryTrans'
                    , '6' => 'Proj_TenderOHAdminExpenseTrans' , '7' => 'Proj_TenderOHSalaryTrans', '8' => 'Proj_TenderOHFuelTrans');
                    $arr_type_fields = array('1' => 'ProjectIOWId', '2' => 'ResourceId', '3' => 'ResourceId', '5' => 'MResourceId'
                    , '4' => 'ServiceId', '6' => 'ExpenseId', '7' => 'PositionId', '8' => 'MResourceId');
                    $arr_rfctype_fields = array('1' => 'TenderItemTransId', '2' => 'TenderMaterialTransId', '3' => 'TenderLabourTransId', '5' => 'TenderMachineryTransId'
                    , '4' => 'TenderServiceTransId', '6' => 'TenderExpenseTransId', '7' => 'TenderSalaryTransId', '8' => 'TenderFuelTransId');

                    // insert new oh(s)
                    $NewOtherCostRowId = $this->bsf->isNullCheck($postData['NewOtherCostRowId'],'number');
                    $NewOhIds = array();
                    for ($v = 1; $v <= $NewOtherCostRowId; $v++) {
                        $ohId = $this->bsf->isNullCheck($postData['NewOtherCostId_' . $v], 'string');
                        $ohName = $this->bsf->isNullCheck($postData['NewOtherCostName_' . $v], 'string');
                        $ohTypeId = $this->bsf->isNullCheck($postData['NewOtherCostType_' . $v], 'string');

                        $insert = $sql->insert();
                        $insert->into('Proj_OHMaster')
                            ->Values(array('OHName' => $ohName, 'OHTypeId' => $ohTypeId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $generatedOhId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $NewOhIds[$ohId] = $generatedOhId;
                    }

                    // delete rfc other cost
                    /*$deleteids = trim($postData[ 'rowdeleteids'], ",");
                    if($deleteids !== '' && $deleteids !== '0') {
                        $delete = $sql->delete();
                        $delete->from('Proj_TenderOHTrans')
                            ->where("TenderTransId IN ($deleteids)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // delete machinery trans details
                        $subquery = $sql->select();
                        $subquery -> from("Proj_TenderOHMachineryTrans")
                            ->columns(array('TenderMachineryTransId'))
                            ->where("TenderTransId IN ($deleteids)");
                        $delete = $sql->delete();
                        $delete->from('Proj_TenderOHMachineryDetails')
                            ->where->expression('TenderMachineryTransId IN ?', array($subquery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // delete type trans
                        foreach($arr_type_tables as $tables) {
                            $delete = $sql->delete();
                            $delete->from($tables)
                                ->where("TenderTransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }*/

                    $subQuery = $sql->select();
                    $subQuery->from("Proj_TenderOHTrans")
                        ->columns(array("TenderTransId"));
                    $subQuery->where(array('QuotationId' => $identity));

                    // delete machinery trans details
                    $mtdsubquery = $sql->select();
                    $mtdsubquery->from("Proj_TenderOHMachineryTrans")
                        ->columns(array('TenderMachineryTransId'))
                        ->where->expression('TenderTransId IN ?', array($subQuery));
                    $delete = $sql->delete();
                    $delete->from('Proj_TenderOHMachineryDetails')
                        ->where->expression('TenderMachineryTransId IN ?', array($mtdsubquery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach($arr_type_tables as $tables) {
                        $delete = $sql->delete();
                        $delete->from($tables)
                            ->where->expression('TenderTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderOHTrans')
                        ->where(array("QuotationId" => $identity));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $iRowId = $this->bsf->isNullCheck($postData['ocrowid'], 'number');
                    for ($i = 1; $i <= $iRowId; $i++) {
                        $ohTypeId = $this->bsf->isNullCheck($postData['ohtypeid_' . $i], 'number');
                        $amt = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                        // check for vendorId
                        $ohId = $postData['ohid_' . $i];
                        if(substr($ohId, 0, 3) == 'New')
                            $ohId = $NewOhIds[ $ohId ];
                        else
                            $ohId = $this->bsf->isNullCheck($ohId,'number');

                        if ($ohId == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderOHTrans');
                        $insert->Values(array('QuotationId' => $identity, 'OHId' => $ohId, 'Amount' => $amt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $itransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if(!array_key_exists($ohTypeId, $arr_type_tables))
                            continue;
//                        $transid = $this->bsf->isNullCheck($postData['TransId_' . $i], 'number');
//                        $updaterow = $this->bsf->isNullCheck($postData['UpdateRow_' . $i], 'number');
//
//                        $ohTypeId = $this->bsf->isNullCheck($postData['ohtypeid_' . $i], 'number');
//                        $amt = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
//
//                        // check for vendorId
//                        $ohId = $postData['ohid_' . $i];
//                        if(substr($ohId, 0, 3) == 'New')
//                            $ohId = $NewOhIds[ $ohId ];
//                        else
//                            $ohId = $this->bsf->isNullCheck($ohId,'number');
//
//                        if ($ohId == 0)
//                            continue;
//
//                        if($transid == 0 && $updaterow == 0) {
//                            $insert = $sql->insert();
//                            $insert->into( 'Proj_TenderOHTrans' );
//                            $insert->Values( array( 'QuotationId' => $identity, 'OHId' => $ohId, 'Amount' => $amt ) );
//                            $statement = $sql->getSqlStringForSqlObject( $insert );
//                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//                            $transid = $dbAdapter->getDriver()->getLastGeneratedValue();
//                        } else if($transid != 0 && $updaterow == 1){
//                            $update = $sql->update();
//                            $update->table('Proj_TenderOHTrans');
//                            $update->set(array( 'QuotationId' => $identity, 'OHId' => $ohId, 'Amount' => $amt ));
//                            $update->where(array('TenderTransId' => $transid));
//                            $statement = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        }
//
//                        if(!array_key_exists($ohTypeId, $arr_type_tables))
//                            continue;

                        $typedeleteids = trim($postData[ 'type_' . $i . '_rowdeleteids'], ",");
                        if($typedeleteids !== '' && $typedeleteids !== '0') {
                            $delete = $sql->delete();
                            $delete->from($arr_type_tables[ $ohTypeId ])
                                ->where($arr_rfctype_fields[$ohTypeId] . " IN (" .$typedeleteids . ")");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $typeRowid = $this->bsf->isNullCheck($postData['type_'.$i.'_rowid'],'number');
                        for ( $j = 1; $j <= $typeRowid; $j++ ) {
                            $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desctypeid_' . $j ], 'number' );
                            $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_amount_' . $j ], 'number' );

//                            if($desctypeid == 0)
//                                continue;

                            $insertFields = array( 'TenderTransId' => $itransid, $arr_type_fields[ $ohTypeId ] => $desctypeid, 'Amount' => $amt);

                            if($ohTypeId == '8') { // fuel
                                $fuelid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_fuelid_' . $j ], 'number' );
                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($fuelid == 0 || $qty == 0 )
                                    continue;

                                $insertFields = array_merge(array('FResourceId' => $fuelid, 'Qty' => $qty, 'Rate' => $rate),$insertFields);
                            } else if($ohTypeId == '6') { // salary
                                $Expense = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desc_' . $j ], 'string' );
                                $insertFields = array_merge(array('ExpenseName'=> $Expense),$insertFields);
                            } else if($ohTypeId == '7') { // salary
                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
                                $wmonths = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_workingmonths_' . $j ], 'number' );
                                $salpermonth = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_salpermonth_' . $j ], 'number' );
                                $position = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desc_' . $j ], 'string' );

                                if ($nos == 0 || $salpermonth == 0 || $wmonths == 0)
                                    continue;

                                $insertFields = array_merge(array('PositionName'=> $position, 'Nos' => $nos, 'cMonths' => $wmonths, 'Salary' => $salpermonth),$insertFields);
                            } else if($ohTypeId == '5') { // machinery
                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
                                $wQty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_wqty_' . $j ], 'number' );
                                if ($wQty==0) $wQty=1;
                                $tQty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_tqty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($nos == 0 || $wQty == 0 || $tQty == 0 || $rate == 0)
                                    continue;

                                $insertFields = array_merge(array('Nos' => $nos, 'WorkingQty' => $wQty, 'TotalQty' => $tQty, 'Rate' => $rate),$insertFields);
                            } else if($ohTypeId != '6') { //item, material, labour, service
                                $unitid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_unitid_' . $j ], 'number' );
                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($unitid == 0 || $qty == 0 )
                                    continue;

                                $insertFields = array_merge(array('Qty' => $qty, 'Rate' => $rate),$insertFields);
                            }
                            $insert = $sql->insert();
                            $insert->into( $arr_type_tables[ $ohTypeId ] );
                            $insert->Values($insertFields);
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $typetransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                            if($ohTypeId != '5')
                                continue;

                            // machinery projectiows
                            $typeRowid = $this->bsf->isNullCheck($postData['type_'.$i.'_m_'.$j.'_rowid'],'number');
                            for ( $k = 1; $k <= $typeRowid; $k++ ) {
                                $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_desctypeid_' . $k ], 'number' );
                                $percent = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_per_' . $k ], 'number' );
                                $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_amount_' . $k ], 'number' );

                                if($desctypeid == 0 || $percent == 0 || $amt == 0)
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderOHMachineryDetails');
                                $insert->Values(array('TenderMachineryTransId' => $typetransid, 'ProjectIOWId' => $desctypeid, 'Percentage' => $percent, 'Amount' => $amt));
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }
                        }

//                        for ( $j = 1; $j <= $typeRowid; $j++ ) {
//                            $typetransid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_TransId_' . $j ], 'number' );
//                            $typeupdaterow = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_UpdateRow_' . $j ], 'number' );
//
//                            $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desctypeid_' . $j ], 'number' );
//                            $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_amount_' . $j ], 'number' );
//                            if($desctypeid == 0)
//                                continue;
//
//                            $insertFields = array( 'TenderTransId' => $transid, $arr_type_fields[ $ohTypeId ] => $desctypeid, 'Amount' => $amt);
//
//                            if($ohTypeId == '8') { // fuel
//                                $fuelid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_fuelid_' . $j ], 'number' );
//                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
//                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );
//
//                                if ($fuelid == 0 || $qty == 0 )
//                                    continue;
//
//                                $insertFields = array_merge(array('FResourceId' => $fuelid, 'Qty' => $qty, 'Rate' => $rate),$insertFields);
//                            } else if($ohTypeId == '7') { // salary
//                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
//                                $wmonths = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_workingmonths_' . $j ], 'number' );
//                                $salpermonth = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_salpermonth_' . $j ], 'number' );
//
//                                if ($nos == 0 || $salpermonth == 0 || $wmonths == 0)
//                                    continue;
//
//                                $insertFields = array_merge(array('Nos' => $nos, 'cMonths' => $wmonths, 'Salary' => $salpermonth),$insertFields);
//                            } else if($ohTypeId == '5') { // machinery
//                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
//                                $wQty = floatval($this->bsf->isNullCheck( $postData[ 'type_' . $i . '_wqty_' . $j ], 'number' ));
//                                if ($wQty==0) $wQty=1;
//                                $tQty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_tqty_' . $j ], 'number' );
//                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );
//
//                                if ($nos == 0 || $wQty == 0 || $tQty == 0 || $rate == 0)
//                                    continue;
//
//                                $insertFields = array_merge(array('Nos' => $nos, 'WorkingQty' => $wQty, 'TotalQty' => $tQty, 'Rate' => $rate),$insertFields);
//                            } else if($ohTypeId != '6') { //item, material, labour, service
//                                $unitid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_unitid_' . $j ], 'number' );
//                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
//                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );
//
//                                if ($unitid == 0 || $qty == 0 )
//                                    continue;
//
//                                $insertFields = array_merge(array('Qty' => $qty, 'Rate' => $rate),$insertFields);
//                            }
//
//                            if($typetransid == 0 && $typeupdaterow == 0) {
//                                $insert = $sql->insert();
//                                $insert->into( $arr_type_tables[ $ohTypeId ] );
//                                $insert->Values( $insertFields );
//                                $statement = $sql->getSqlStringForSqlObject( $insert );
//                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//                                $typetransid = $dbAdapter->getDriver()->getLastGeneratedValue();
//                            } else if($typetransid != 0 && $typeupdaterow == 1){
//                                $update = $sql->update();
//                                $update->table($arr_type_tables[ $ohTypeId ]);
//                                $update->set($insertFields);
//                                $update->where(array($arr_rfctype_fields[$ohTypeId] => $typetransid));
//                                $statement = $sql->getSqlStringForSqlObject($update);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                            }
//
//                            if($ohTypeId != '5')
//                                continue;
//
//                            // machinery projectiows
//                            $machinerydeleteids = trim($postData['type_'.$i.'_m_'.$j.'_rowdeleteids'], ",");
//                            if($machinerydeleteids !== '' && $machinerydeleteids !== '0') {
//                                $delete = $sql->delete();
//                                $delete->from('Proj_TenderOHMachineryDetails')
//                                    ->where('TenderMachineryDetailId IN (' .$machinerydeleteids .')');
//                                $statement = $sql->getSqlStringForSqlObject($delete);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//
//                            $typeRowid = $this->bsf->isNullCheck($postData['type_'.$i.'_m_'.$j.'_rowid'],'number');
//                            for ( $k = 1; $k <= $typeRowid; $k++ ) {
//                                $mdetailtransid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_TransId_' . $k ], 'number' );
//                                $mdetailupdaterow = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_UpdateRow_' . $k ], 'number' );
//
//                                $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_desctypeid_' . $k ], 'number' );
//                                $percent = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_per_' . $k ], 'number' );
//                                $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_amount_' . $k ], 'number' );
//
//                                if($desctypeid == 0 || $percent == 0 || $amt == 0)
//                                    continue;
//
//                                if($mdetailtransid == 0 && $mdetailupdaterow == 0) {
//                                    $insert = $sql->insert();
//                                    $insert->into( 'Proj_TenderOHMachineryDetails' );
//                                    $insert->Values( array( 'TenderMachineryTransId' => $typetransid, 'ProjectIOWId' => $desctypeid, 'Percentage' => $percent, 'Amount' => $amt ) );
//                                    $statement = $sql->getSqlStringForSqlObject( $insert );
//                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//                                } else if($mdetailtransid != 0 && $mdetailupdaterow == 1){
//                                    $update = $sql->update();
//                                    $update->table('Proj_TenderOHMachineryDetails');
//                                    $update->set(array( 'TenderMachineryTransId' => $typetransid, 'ProjectIOWId' => $desctypeid, 'Percentage' => $percent, 'Amount' => $amt ) );
//                                    $update->where(array('TenderMachineryDetailId' => $mdetailtransid));
//                                    $statement = $sql->getSqlStringForSqlObject($update);
//                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                                }
//                            }
//                        }
                    }
                    //for other cost

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Tender-Quotation-Edit','E','',$identity,0,0,'Project',$sRefNo,$userId,0,0);
                    $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $postData['enquiryId']));
                } else {

                    $aVNo = CommonHelper::getVoucherNo(112,date('Y-m-d', strtotime($postData['refdate'])) ,0,0, $dbAdapter,"I");
                    if ($aVNo["genType"] ==true)
                        $sVno = $aVNo["voucherNo"];
                    else
                        $sVno = $this->bsf->isNullCheck($postData['refno'],'string');

                    $amendment = $this->bsf->isNullCheck($postData['amendment'], 'number');
                    $pQId = $this->bsf->isNullCheck($postData['pquotationId'], 'number');

                    $insert = $sql->insert();
                    $insert->into('Proj_TenderQuotationRegister');
                    $insert->Values(array('RefNo' => $sVno, 'RefDate' => date('Y-m-d', strtotime($postData['refdate'])), 'TenderEnquiryId' => $postData['enquiryId'],'AQuotationId'=>$pQId,'Amendment'=>$amendment));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $identity = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $update = $sql->update();
                    $update->table('Proj_TenderEnquiry')
                        ->set(array('QuotationId' => $identity))
                        ->where(array('TenderEnquiryId' => $postData['enquiryId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $iRowId = $this->bsf->isNullCheck($postData['rowid'],'number');
                    for ($i = 1; $i <= $iRowId; $i++) {
                        $wgroupid=$this->bsf->isNullCheck($postData['workgroupid_' . $i],'string');
                        if (substr($wgroupid, 0, 2) == '0.') $wgroupid= 0;
                        $wtypeid=$this->bsf->isNullCheck($postData['worktypeid_' . $i],'number');
                        $pwgroupid=$this->bsf->isNullCheck($postData['pworkgroupid_' . $i],'string');
                        if (substr($pwgroupid, 0, 2) == '0.') $pwgroupid= 0;
                        $libiowid =  $this->bsf->isNullCheck($postData['libiowkeyid_' . $i],'number');
                        $iHeader = $this->bsf->isNullCheck($postData['raheaderval_' . $i],'number');
                        $sparentName = $this->bsf->isNullCheck($postData['newparentname_' . $i], 'string');
                        $ipiowid = $this->bsf->isNullCheck($postData['piowid_' . $i], 'number');

                        $sSpec = $this->bsf->isNullCheck($postData['spec_' . $i], 'string');
                        if ($sSpec =="") continue;

                        $dWasteAmt = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totwastage'], 'number');
                        $dBaseRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totbaserate'], 'number');
                        $dQualValue= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totqualrate'], 'number');
                        $dTotRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totrate'], 'number');
                        $dNetRate= $this->bsf->isNullCheck($postData['rateanal_' . $i . '_totnetrate'], 'number');

                        $dRWasteAmt = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totwastage'], 'number');
                        $dRBaseRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totbaserate'], 'number');
                        $dRQualValue= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totqualrate'], 'number');
                        $dRTotRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totrate'], 'number');
                        $dRNetRate= $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_totnetrate'], 'number');

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationTrans');
                        $insert->Values(array('QuotationId' => $identity, 'IOWId'=>$libiowid,'WorkGroupId' => $wgroupid,
                            'WorkTypeId'=>$wtypeid, 'ParentId' => $ipiowid, 'RefSerialNo' => $this->bsf->isNullCheck($postData['refserialno_' . $i], 'string'), 'SerialNo' => $this->bsf->isNullCheck($postData['serialno_' . $i], 'string'), 'Specification' => $sSpec, 'UnitId' => $this->bsf->isNullCheck($postData['unitkeyid_' . $i], 'number'),
                            'ShortSpec'=>$this->bsf->isNullCheck($postData['shortspec_' . $i],'string'),'ParentText'=>$this->bsf->isNullCheck($postData['parentid_' . $i],'string'),'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number'),'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number'),'Amount' => $this->bsf->isNullCheck($postData['amt_' . $i], 'number'), 'WorkingQty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AnalQty'], 'number'), 'RWorkingQty' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_AnalQty'], 'number'),
                            'CementRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_AQty'], 'number'), 'SandRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_BQty'], 'number'), 'MetalRatio' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_CQty'], 'number'), 'ThickQty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_thick'], 'number'),
                            'ProjectWorkGroupId' => $pwgroupid, 'ProjectWorkGroupName' => $this->bsf->isNullCheck($postData['pworkgroupname_'. $i],'string'),
                            'MixType' => $this->bsf->isNullCheck($postData['ratetype_'. $i],'string'), 'QuotedRate' => $this->bsf->isNullCheck($postData['qrate_'. $i],'number'), 'QuotedAmount' => $this->bsf->isNullCheck($postData['qamt_'. $i],'number'),'WastageAmt' => $dWasteAmt, 'BaseRate' => $dBaseRate, 'QualifierValue' => $dQualValue,
                            'TotalRate' => $dTotRate, 'NetRate' => $dNetRate, 'RWastageAmt' => $dRWasteAmt, 'RBaseRate' => $dRBaseRate, 'RQualifierValue' => $dRQualValue,
                            'RTotalRate' => $dRTotRate, 'RNetRate' => $dRNetRate,'ParentName'=>$sparentName,'Header'=>$iHeader));
                        $statement = $sql->getSqlStringForSqlObject($insert);

                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $itransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $iwbsRowId = $this->bsf->isNullCheck($postData['wbstable_' . $i .'_rows'],'number');
                        for ($n = 1; $n <= $iwbsRowId; $n++) {
                            $iwbsId = $this->bsf->isNullCheck($postData['wbstable_' . $i .'_wbsid_' . $n],'number');
                            $dwbsqty = floatval($this->bsf->isNullCheck($postData['wbstable_' . $i .'_qty_' . $n],'number'));
                            if ($dwbsqty !=0 && $iwbsId !=0) {
                                $insert = $sql->insert();
                                $insert->into('Proj_TenderWBSTrans');
                                $insert->Values(array('TenderEnquiryId'=> $postData['enquiryId'],'QuotationTransId' => $itransid, 'WBSId' => $iwbsId, 'Qty' => $dwbsqty));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $iactRowid = $this->bsf->isNullCheck($postData['rowinfoid_' . $i],'number');
                        for ($j = 1; $j <= $iactRowid; $j++) {

                            $iresid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_reskeyid_' . $j],'number');
                            if (substr($iresid, 0, 2) == '0.') $iresid= 0;

                            $iiowid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_iowkeyid_' . $j],'number');

                            $irefid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_refid_' . $j],'number');
                            $stype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
                            $sdes = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resdes_' . $j],'string');
                            $isortid = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_rowrefid_' . $j],'number');
                            $stranstype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_type_' . $j],'string');
                            $sratetype = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_ratetype_' . $j],'string');
                            $dweigtage = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_Weightage_' . $j], 'number');
                            $dwasteper = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_Wastage_' . $j], 'number');
                            $dwasteqty = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_WastageQty_' . $j], 'number');
                            $dwasteamt = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_WastageAmount_' . $j], 'number');
                            $sresName = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_newresname_' . $j], 'string');

                            if ($stype=="" || ($stype=="I" &&  $iiowid==0) || ($stype=="R" &&  $iresid==0) || $stype=="H" &&  $sdes=="")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationRateAnalysis');

                            if ($stype == "R" || $stype=="I") {
                                $check_value = isset($postData['rateanal_' . $i . '_inc_' . $j]) ? 1 : 0;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid,  'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_resamt_' . $j], 'number'), 'Formula' => $this->bsf->isNullCheck($postData['rateanal_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype,'Weightage'=>$dweigtage,'Wastage'=>$dwasteper,'WastageQty'=>$dwasteqty,'WastageAmount'=>$dwasteamt,'ResourceName'=>$sresName));
                            } else {
                                $check_value = 1;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'S','TransType' => $stranstype,'SortId' =>$isortid,'ResourceName'=>$sresName));
                            }
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $iactRowid = $this->bsf->isNullCheck($postData['rowinfoidR_' . $i],'number');

                        for ($j = 1; $j <= $iactRowid; $j++) {
                            $iresid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_reskeyid_' . $j],'number');
                            if (substr($iresid, 0, 2) == '0.') $iresid= 0;
                            $iiowid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_iowkeyid_' . $j],'number');
                            $stype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
                            $sdes = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resdes_' . $j],'string');
                            $irefid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_refid_' . $j],'number');
                            $isortid = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_rowrefid_' . $j],'number');
                            $stranstype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_type_' . $j],'string');
                            $sratetype = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_ratetype_' . $j],'string');
                            $dweigtage = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_Weightage_' . $j], 'number');
                            $dwasteper = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_Wastage_' . $j], 'number');
                            $dwasteqty = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_WastageQty_' . $j], 'number');
                            $dwasteamt = $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_WastageAmount_' . $j], 'number');
                            $sresName = $this->bsf->isNullCheck($postData['rateanal_' . $i . '_newresname_' . $j], 'string');

                            if ($stype=="" || ($stype=="I" &&  $iiowid==0) || ($stype=="R" &&  $iresid==0) || $stype=="H" &&  $sdes=="")
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationRateAnalysis');

                            if ($stype == "R" || $stype=="I") {
                                $check_value = isset($postData['rateanal_' . $i . '_inc_' . $j]) ? 1 : 0;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'ResourceId' => $iresid,'SubIOWId' => $iiowid, 'Qty' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resqty_' . $j], 'number'), 'Rate' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resrate_' . $j], 'number'), 'Amount' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_resamt_' . $j], 'number'), 'Formula' => $this->bsf->isNullCheck($postData['rateanalR_' . $i . '_formula_' . $j], 'string'), 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid,'RateType'=>$sratetype,'Weightage'=>$dweigtage,'Wastage'=>$dwasteper,'WastageQty'=>$dwasteqty,'WastageAmount'=>$dwasteamt,'ResourceName'=>$sresName));
                            } else {
                                $check_value = 1;
                                $insert->Values(array('QuotationTransId' => $itransid, 'IncludeFlag' => $check_value, 'Referenceid' => $irefid, 'Description' => $sdes , 'MixType' => 'R','TransType' => $stranstype,'SortId' =>$isortid,'ResourceName'=>$sresName));
                            }

                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        // }
                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['Qual_' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Sign_' . $k], 'string');


                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['Qual_' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['Qual_' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_NetAmt_' . $k], 'number');
                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationQualTrans');
                            $insert->Values(array('QuotationTransId' =>$itransid,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess, 'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'S'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRRowId_'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['QualR_' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['QualR_' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Sign_' . $k], 'string');

                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['QualR_' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['QualR_' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_NetAmt_' . $k], 'number');

                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR_' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderQuotationQualTrans');
                            $insert->Values(array('QuotationTransId' =>$itransid,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'R'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $update = $sql->update();
                    $update->table('Proj_TenderQuotationTrans');
                    $update->set(array(
                        'PrevQuotationTransId' => new Expression("QuotationTransId")
                    ));
                    $update->where(array('PrevQuotationTransId' => 0,'QuotationId'=>$identity));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $iresRowId = $this->bsf->isNullCheck($postData['resrows'],'number');
                    for ($x = 1; $x <= $iresRowId; $x++) {
                        $iresid = $this->bsf->isNullCheck($postData['rateresid_' . $x],'number');
                        $dresQty = $this->bsf->isNullCheck($postData['rateresqty_' . $x],'number');
                        $dresRate = $this->bsf->isNullCheck($postData['rateresrate_' . $x],'number');
                        $dresAmt = $this->bsf->isNullCheck($postData['rateresamt_' . $x],'number');

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationResourceUsed');
                        $insert->Values(array('QuotationId' =>$identity,
                            'ResourceId'=>$iresid,'Qty'=>$dresQty,'Rate'=>$dresRate,'Amount'=>$dresAmt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $iwgrowid = $this->bsf->isNullCheck($postData['newwgowid'],'number');
                    for ($j = 1; $j <= $iwgrowid; $j++) {
                        $sWgslNo = $this->bsf->isNullCheck($postData['newwgslno_' . $j],'string');
                        $sWgName = $this->bsf->isNullCheck($postData['newwgname_' . $j],'string');
                        $ilWgid = $this->bsf->isNullCheck($postData['newlwgid_' . $j],'number');
                        $ilWgtypeid = $this->bsf->isNullCheck($postData['newwgtypeid_' . $j],'number');


                        if ($sWgName =="") continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderWorkGroup');
                        $insert->Values(array('TenderEnquiryId' => $postData['enquiryId'],'SerialNo' =>$sWgslNo, 'WorkGroupId' => $ilWgid, 'WorkGroupName' => $sWgName,'WorkTypeId'=>$ilWgtypeid));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $wgid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $update = $sql->update();
                        $update->table('Proj_TenderQuotationTrans');
                        $update->set(array(
                            'ProjectWorkGroupId' => $wgid,
                        ));
                        $update->where(array('ProjectWorkGroupName' => $sWgName,'ProjectWorkGroupId'=>0,'QuotationId'=>$identity));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

//                    $iwgrowid = $this->bsf->isNullCheck($postData['wgrowid'],'number');
//                    for ($j = 1; $j <= $iwgrowid; $j++) {
//                        $sWgName = $this->bsf->isNullCheck($postData['newwgname_' . $j],'string');
//                        $iWgTypeid = $this->bsf->isNullCheck($postData['newwgid_' . $j],'number');
//                        $iactresgroup = $this->bsf->isNullCheck($postData['newwgactivity_' . $j],'number');
//                        $iautoRA = $this->bsf->isNullCheck($postData['newwgrateanal_' . $j],'number');
//                        $iactresource = $this->bsf->isNullCheck($postData['newwgactivityres_' . $j],'number');
//
//                        if ($sWgName =="" || $iWgTypeid ==0) continue;
//
//                        $insert = $sql->insert();
//                        $insert->into('Proj_TenderQuotationWorkGroupTrans');
//                        $insert->Values(array('QuotationId' => $postData['enquiryId'], 'WorkTypeId' => $iWgTypeid, 'WorkGroupName' => $sWgName, 'ActivityResGroup' => $iactresgroup,'AutoRateAnalysis' => $iautoRA, 'ActivityResource' => $iactresource));
//                    }

                    $iresrowid = $this->bsf->isNullCheck($postData['resrowid'],'number');
                    for ($j = 1; $j <= $iresrowid; $j++) {

                        $sresName = $this->bsf->isNullCheck($postData['newresname_' . $j],'string');
                        $iresgroupid = $this->bsf->isNullCheck($postData['newresgroupid_' . $j],'number');
                        $irestypeid= $this->bsf->isNullCheck($postData['newrestypeid_' . $j],'number');
                        $iunitid = $this->bsf->isNullCheck($postData['newresunitid_' . $j],'number');
                        $drate = $this->bsf->isNullCheck($postData['newresrate_' . $j],'string');

                        if ($sresName =="" || $iresgroupid==0) continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuotationResourceTrans');
                        $insert->Values(array('QuotationId' => $identity, 'ResourceName'=> $sresName ,'ResourceGroupId' => $iresgroupid ,'TypeId' => $irestypeid ,'UnitId'=> $iunitid,'RateType'=>'L','LRate'=>$drate));
                    }

                    //for other cost
                    $arr_type_tables = array('1' => 'Proj_TenderOHItemTrans', '2' => 'Proj_TenderOHMaterialTrans'
                    , '3' => 'Proj_TenderOHLabourTrans', '4' => 'Proj_TenderOHServiceTrans', '5' => 'Proj_TenderOHMachineryTrans'
                    , '6' => 'Proj_TenderOHAdminExpenseTrans' , '7' => 'Proj_TenderOHSalaryTrans', '8' => 'Proj_TenderOHFuelTrans');
                    $arr_type_fields = array('1' => 'ProjectIOWId', '2' => 'ResourceId', '3' => 'ResourceId', '5' => 'MResourceId'
                    , '4' => 'ServiceId', '6' => 'ExpenseId', '7' => 'PositionId', '8' => 'MResourceId');

                    // insert new oh(s)
                    $NewOtherCostRowId = $this->bsf->isNullCheck($postData['NewOtherCostRowId'],'number');
                    $NewOhIds = array();
                    for ($v = 1; $v <= $NewOtherCostRowId; $v++) {
                        $ohId = $this->bsf->isNullCheck($postData['NewOtherCostId_' . $v], 'string');
                        $ohName = $this->bsf->isNullCheck($postData['NewOtherCostName_' . $v], 'string');
                        $ohTypeId = $this->bsf->isNullCheck($postData['NewOtherCostType_' . $v], 'string');

                        $insert = $sql->insert();
                        $insert->into('Proj_OHMaster')
                            ->Values(array('OHName' => $ohName, 'OHTypeId' => $ohTypeId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $generatedOhId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $NewOhIds[$ohId] = $generatedOhId;
                    }

                    $iRowId = $this->bsf->isNullCheck($postData['ocrowid'], 'number');
                    for ($i = 1; $i <= $iRowId; $i++) {
                        $ohTypeId = $this->bsf->isNullCheck($postData['ohtypeid_' . $i], 'number');
                        $amt = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                        // check for vendorId
                        $ohId = $postData['ohid_' . $i];
                        if(substr($ohId, 0, 3) == 'New')
                            $ohId = $NewOhIds[ $ohId ];
                        else
                            $ohId = $this->bsf->isNullCheck($ohId,'number');

                        if ($ohId == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderOHTrans');
                        $insert->Values(array('QuotationId' => $identity, 'OHId' => $ohId, 'Amount' => $amt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $itransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if(!array_key_exists($ohTypeId, $arr_type_tables))
                            continue;

                        $typeRowid = $this->bsf->isNullCheck($postData['type_'.$i.'_rowid'],'number');
                        for ( $j = 1; $j <= $typeRowid; $j++ ) {
                            $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desctypeid_' . $j ], 'number' );
                            $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_amount_' . $j ], 'number' );

//                            if($desctypeid == 0)
//                                continue;

                            $insertFields = array( 'TenderTransId' => $itransid, $arr_type_fields[ $ohTypeId ] => $desctypeid, 'Amount' => $amt);

                            if($ohTypeId == '8') { // fuel
                                $fuelid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_fuelid_' . $j ], 'number' );
                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($fuelid == 0 || $qty == 0 )
                                    continue;

                                $insertFields = array_merge(array('FResourceId' => $fuelid, 'Qty' => $qty, 'Rate' => $rate),$insertFields);
                            } else if($ohTypeId == '6') { // salary
                                $Expense = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desc_' . $j ], 'string' );
                                $insertFields = array_merge(array('ExpenseName'=> $Expense),$insertFields);
                            } else if($ohTypeId == '7') { // salary
                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
                                $wmonths = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_workingmonths_' . $j ], 'number' );
                                $salpermonth = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_salpermonth_' . $j ], 'number' );
                                $position = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_desc_' . $j ], 'string' );

                                if ($nos == 0 || $salpermonth == 0 || $wmonths == 0)
                                    continue;

                                $insertFields = array_merge(array('PositionName'=> $position,'Nos' => $nos, 'cMonths' => $wmonths, 'Salary' => $salpermonth),$insertFields);
                            } else if($ohTypeId == '5') { // machinery
                                $nos = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_nos_' . $j ], 'number' );
                                $wQty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_wqty_' . $j ], 'number' );
                                if ($wQty==0) $wQty=1;
                                $tQty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_tqty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($nos == 0 || $wQty == 0 || $tQty == 0 || $rate == 0)
                                    continue;

                                $insertFields = array_merge(array('Nos' => $nos, 'WorkingQty' => $wQty, 'TotalQty' => $tQty, 'Rate' => $rate),$insertFields);
                            } else if($ohTypeId != '6') { //item, material, labour, service
                                $unitid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_unitid_' . $j ], 'number' );
                                $qty = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_qty_' . $j ], 'number' );
                                $rate = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_rate_' . $j ], 'number' );

                                if ($unitid == 0 || $qty == 0 )
                                    continue;

                                $insertFields = array_merge(array('Qty' => $qty, 'Rate' => $rate),$insertFields);
                            }
                            $insert = $sql->insert();
                            $insert->into( $arr_type_tables[ $ohTypeId ] );
                            $insert->Values($insertFields);
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $typetransid = $dbAdapter->getDriver()->getLastGeneratedValue();

                            if($ohTypeId != '5')
                                continue;

                            // machinery projectiows
                            $typeRowid = $this->bsf->isNullCheck($postData['type_'.$i.'_m_'.$j.'_rowid'],'number');
                            for ( $k = 1; $k <= $typeRowid; $k++ ) {
                                $desctypeid = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_desctypeid_' . $k ], 'number' );
                                $percent = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_per_' . $k ], 'number' );
                                $amt = $this->bsf->isNullCheck( $postData[ 'type_' . $i . '_m_'. $j . '_amount_' . $k ], 'number' );

                                if($desctypeid == 0 || $percent == 0 || $amt == 0)
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('Proj_TenderOHMachineryDetails');
                                $insert->Values(array('TenderMachineryTransId' => $typetransid, 'ProjectIOWId' => $desctypeid, 'Percentage' => $percent, 'Amount' => $amt));
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }
                        }
                    }
                    //for other cost

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Tender-Quotation-Add','N','',$identity,0,0,'Project',$sVno,$userId,0,0);
                    $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $postData['enquiryId']));
                }
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        } else {
            $enquiryId =  $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
            $iQuotationId =  $this->bsf->isNullCheck($this->params()->fromRoute('quotationId'),'number');
            $smode =  $this->bsf->isNullCheck($this->params()->fromRoute('mode'),'string');


            if ($enquiryId !=0) {
                if (($iQuotationId==0 && $smode=="") || $smode=='revise') {
                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationRegister')
                        ->columns(array('QuotationId'))
                        ->where(array('TenderEnquiryId'=>$enquiryId,'LiveQuotation'=>1))
                        ->order('QuotationId Desc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $quotreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($quotreg)) {
                        $iQuotationId = $quotreg['QuotationId'];
                        if ($smode!='revise') $smode ='edit';
                    }
                }
            }

            $aVNo = CommonHelper::getVoucherNo(112, date('Y/m/d'), 0, 0, $dbAdapter, "");

            $iRefEnquiryId =0;
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('RefEnquiryId'))
                ->where(array('TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $enqreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($enqreg)) $iRefEnquiryId  = $enqreg['RefEnquiryId'];
            if ($iRefEnquiryId !=0) {
                $subQuery = $sql->select();
                $subQuery->from('Proj_TenderWorkGroup')
                    ->columns(array("MWorkGroupId"));
                $subQuery->where(array('TenderEnquiryId' => $enquiryId));

                $select = $sql->select();
                $select->from('Proj_TenderWorkGroup')
                    ->columns(array('TenderEnquiryId' => new Expression("$enquiryId"),'SerialNo','WorkGroupName','WorkGroupId','SortId','WorkTypeId','MWorkGroupId'))
                    ->where->expression("TenderEnquiryId= $iRefEnquiryId and MWorkGroupId Not IN ?", array($subQuery));

                $insert = $sql->insert();
                $insert->into('Proj_TenderWorkGroup');
                $insert->columns(array('TenderEnquiryId','SerialNo','WorkGroupName','WorkGroupId','SortId','WorkTypeId','MWorkGroupId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }


            $select = $sql->select();
            $select->from('Proj_TenderWorkGroup')
                ->columns(array('PWorkGroupId', 'SerialNo', 'WorkGroupName','WorkTypeId','WorkGroupId'))
                ->where(array("TenderEnquiryId"=>$enquiryId,"DeleteFlag" => 0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $workgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $parentdata = array();

            $k = 0;
            for ($i = 0; $i < count($workgroup); $i++) {
                $parentdata[$k]['data'] = $workgroup[$i]['PWorkGroupId'];
                $parentdata[$k]['value'] = $workgroup[$i]['SerialNo'] . '   ' . $workgroup[$i]['WorkGroupName'];
                $parentdata[$k]['worktypeid'] = $workgroup[$i]['WorkTypeId'];
                $parentdata[$k]['workgroupid'] = $workgroup[$i]['WorkGroupId'];
                $parentdata[$k]['pworkgroupid'] = $workgroup[$i]['PWorkGroupId'];
                $parentdata[$k]['iowid'] = 0;
                $parentdata[$k]['workgroupname'] = $workgroup[$i]['WorkGroupName'];
                $parentdata[$k]['parentname'] = "";
                $k = $k + 1;

                if ($iQuotationId !=0) {
                    $select = $sql->select();
                    $select->from('Proj_TenderQuotationTrans')
                        ->columns(array('PrevQuotationTransId', 'SerialNo', 'Specification' => new expression("Specification"), 'WorkTypeId'))
                        ->where(array("Header" => 1, "ProjectWorkGroupId" => $workgroup[$i]['PWorkGroupId']));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $parentiow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    for ($j = 0; $j < count($parentiow); $j++) {
                        $parentdata[$k]['data'] = $workgroup[$i]['PWorkGroupId'] . '.' . $parentiow[$j]['PrevQuotationTransId'];
                        $parentdata[$k]['value'] = $parentiow[$j]['SerialNo'] . '   ' . $parentiow[$j]['Specification'];
                        $parentdata[$k]['worktypeid'] = $workgroup[$i]['WorkTypeId'];
                        $parentdata[$k]['workgroupid'] = $workgroup[$i]['WorkGroupId'];
                        $parentdata[$k]['pworkgroupid'] = $workgroup[$i]['PWorkGroupId'];
                        $parentdata[$k]['iowid'] = $parentiow[$j]['PrevQuotationTransId'];
                        $parentdata[$k]['workgroupname'] = $workgroup[$i]['WorkGroupName'];
                        $parentdata[$k]['parentname'] = $parentiow[$j]['Specification'];
                        $k = $k + 1;
                    }
                }
            }

            $this->_view->parentiow = $parentdata;

            $select = $sql->select();
            $select->from('Proj_UOM')
                ->columns(array("data"=>'UnitId', "value"=>'UnitName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_UOM')
                ->columns(array('data' => 'UnitId', 'value' => 'UnitName'))
                ->where(array('WorkUnit'=>1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->wunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array("id"=>'ResourceId',"type"=>new Expression("'R'"),"value"=> new Expression("Code + ' ' +ResourceName")))
                ->where(array("DeleteFlag" => '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_Resource'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array(), $select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_LEFT)
                ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $select:: JOIN_LEFT)
                ->join(array('e' => 'Proj_UOM'), 'a.WorkUnitId=e.UnitId', array(), $select:: JOIN_LEFT)
                ->columns(array('ResourceId','TypeId','UnitId'=>new Expression("Case When a.TypeId=3 then a.WorkUnitId else a.UnitId end"),'Code','ResourceName','UnitName'=>new Expression("Case When a.TypeId=3 then e.UnitName else d.UnitName end"),'TypeName'=>new Expression('b.TypeName'),'ResourceGroup'=>new Expression('c.ResourceGroupName'),'Select'=>new Expression("'0'")))
                ->where(array("a.DeleteFlag" => '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->respicklist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_IOWMaster'))
                ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('WorkGroupId','WorkGroupName','WorkTypeId'), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_WorkTypeMaster'), 'b.WorkTypeId=c.WorkTypeId', array('WorkTypeName'=>new Expression('WorkType')), $select::JOIN_INNER)
                ->columns(array('IOWId', 'SerialNo','Specification','Header','Select'=>new Expression("'0'")))
                ->where(array('a.DeleteFlag'=>0));
            $select->order(array('SlNo ASC'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->iowpicklist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_IOWMaster')
                ->columns(array("id"=>'IOWId',"type"=>new Expression("'I'"),"value"=> new Expression("SerialNo + ' ' +Specification ")))
                ->where(array("DeleteFlag" => '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resiowlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_IOWMaster')
                ->columns(array('IOWId', 'Specification'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->iowlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ResourceGroup'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->columns(array('data' => 'ResourceGroupId', 'value' => 'ResourceGroupName','TypeId'))
                ->where(array("a.TypeId <> 4","a.LastLevel"=>1,"DeleteFlag"=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WorkGroupMaster'))
                ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('WorkType','WorkTypeId'), $select:: JOIN_LEFT)
                ->columns(array('data' => 'WorkGroupId', "value"=> new Expression("WorkGroupName")))
                ->where(array("DeleteFlag"=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->workgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderWorkGroup'))
                ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('WorkType','WorkTypeId'), $select:: JOIN_LEFT)
                ->columns(array('data' => 'PWorkGroupId', "value"=> new Expression("SerialNo + ' ' +WorkGroupName"),'WorkGroupName'))
                ->where(array("a.DeleteFlag"=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "Proj_QualifierTrans"))
                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer',
                    'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                    'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                    'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'KKCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SBCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
            $select->where(array('a.QualType' => 'W'));
            $select->order('a.SortId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sHtml=Qualifier::getQualifier($qualList);
            $this->_view->qualHtml = $sHtml;

            $sHtml=Qualifier::getQualifier($qualList,"R");
            $this->_view->qualRHtml = $sHtml;

            $sHtml=Qualifier::getQualifierG($qualList);
            $this->_view->qualGHtml = $sHtml;

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ResourceGroup'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->columns(array('data' => 'ResourceGroupId', 'value' => 'ResourceGroupName', 'TypeId'))
                ->where(array("a.LastLevel"=>1,"DeleteFlag" => '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->excelresgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_Resource'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=c.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                ->join(array('e' => 'Proj_UOM'), 'a.WorkUnitId=e.UnitId', array('WorkUnitName'=>new Expression("e.UnitName")), $select:: JOIN_LEFT)
                ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'TypeId','ResourceGroupId','MaterialType','UnitId','WorkUnitId'))
                ->where(array("a.DeleteFlag" => '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->excelresource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_WorkTypeMaster')
                ->columns(array('data' => 'WorkTypeId', 'value' => 'WorkType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->worktype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            if (!isset($iQuotationId)) $iQuotationId=0;
            if (!isset($editid)) $editid=0;

            // for other cost
            $select = $sql->select();
            $select->from('Proj_OHTypeMaster')
                ->columns(array('data' => 'OHTypeId', 'value' =>'OHTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $ohtypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->ohtypes = $ohtypes;

            if ($iQuotationId !=0) {
                $iQuotationId = $iQuotationId;

                $select = $sql->select();
                $select->from('Proj_TenderQuotationRegister')
                    ->columns(array('RefNo', 'RefDate','Approve'))
                    ->where(array("QuotationId" => $iQuotationId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a'=>'Proj_TenderQuotationTrans'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_TenderWorkGroup'), 'a.ProjectWorkGroupId=c.PWorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_WorkTypeMaster'), 'c.WorkTypeId=d.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness','WorkType'), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_WorkGroupMaster'), 'c.WorkGroupId=e.WorkGroupId', array('LWorkGroupName'=>new Expression("e.WorkGroupName")), $select:: JOIN_LEFT)
                    ->join(array('g' => 'Proj_IOWMaster'), 'a.IOWId=g.IOWId', array('LSpecification' => new Expression("g.Specification"),'LSerialNo' => new Expression("g.SerialNo")), $select:: JOIN_LEFT)
                    ->columns(array('*'))
                    ->where(array("QuotationId" => $iQuotationId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a'=>'Proj_TenderQuotationResourceUsed'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceName','UnitName'=>new Expression("Case When b.TypeId=3 then d.UnitName else c.UnitName end")), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'b.WorkUnitId=d.UnitId', array(), $select:: JOIN_LEFT)
                    ->columns(array('ResourceId', 'Qty','Rate','Amount'))
                    ->where(array("a.QuotationId" => $iQuotationId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceused = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $subQuery = $sql->select();
                $subQuery->from("Proj_TenderQuotationTrans")
                    ->columns(array("QuotationTransId"));
                $subQuery->where(array('QuotationId' => $iQuotationId));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderQuotationRateAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('QuotationTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId','ResourceId', 'Qty', 'Rate', 'Amount','Formula', 'MixType', 'TransType', 'Description','Wastage','WastageQty','WastageAmount','Weightage','RateType'), array('Code', 'ResourceName', 'TypeId'), array('UnitName'))
                    ->where->expression('QuotationTransId IN ?', array($subQuery));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderWBSTrans'))
                    ->join(array('b' => 'Proj_TenderWBSMaster'), 'a.WBSId=b.WBSId', array('WBSName'=> new Expression("ParentText +'->'+WBSName")), $select:: JOIN_LEFT)
                    ->columns(array('QuotationTransId', 'WBSId', 'Qty'))
                    ->where->expression('QuotationTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfcwbstrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderQuotationTrans'))
                    ->columns(array('QuotationTransId'))
                    ->where(array("a.QuotationId" => $iQuotationId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $qtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $arrqual = array();
                foreach ($qtrans as $trans) {
                    $qtransid = $trans['QuotationTransId'];

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_TenderQuotationQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'KKCessAmt', 'SBCessAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType' => 'S','QuotationTransId'=>$qtransid));
                    $select->order('a.SortId ASC');

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList);
                    $arrqual[$qtransid] = $sHtml;
                }

                $arrRqual = array();
                foreach ($qtrans as $trans) {
                    $qtransid = $trans['QuotationTransId'];

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_TenderQuotationQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType' => 'R','QuotationTransId'=>$qtransid));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList, "R");
                    $arrRqual[$iQuotationId] = $sHtml;
                }

                $arr_rfcohtrans = array();

                $select = $sql->select();
                $select->from( array( 'a' => 'Proj_TenderOHTrans' ) )
                    ->join( array( 'b' => 'Proj_OHMaster' ), 'a.OHId=b.OHId', array( 'OHName', 'OHTypeId' ), $select::JOIN_LEFT )
                    ->join( array( 'c' => 'Proj_OHTypeMaster' ), 'b.OHTypeId=c.OHTypeId', array( 'OHTypeName' ), $select::JOIN_LEFT )
                    ->where('a.QuotationId='.$iQuotationId);
                $statement = $sql->getSqlStringForSqlObject( $select );
                $rfcohtrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                // get rfc type trans
                foreach ($rfcohtrans as &$trans) {
                    $OHTypeId = $trans['OHTypeId'];

                    if($OHTypeId == '9' || $OHTypeId == '10') {
//                        $arr_rfcohtrans = array_merge($arr_rfcohtrans, $rfcohtrans);
                        continue;
                    }
                    switch($OHTypeId) {
                        case '1':
                            //oh item trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHItemTrans' ) )
                                ->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'DescTypeId' => 'ProjectIOWId','Desc' =>new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderItemTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '2':
                            //oh material trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHMaterialTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId','Desc' =>'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderMaterialTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '3':
                            //oh labour trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHLabourTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array(  'DescTypeId' => 'ResourceId','Desc' =>'ResourceName'  ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderLabourTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '4':
                            //oh service trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHServiceTrans' ) )
                                ->join( array( 'b' => 'Proj_ServiceMaster' ), 'a.ServiceId=b.ServiceId', array( 'DescTypeId' => 'ServiceId', 'Desc' => 'ServiceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderServiceTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '5':
                            //oh machinery trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHMachineryTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_UOM' ), 'b.WorkUnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderMachineryTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            // material details trans
                            foreach($rfctypetrans as &$typedetailtrans) {
                                $select = $sql->select();
                                $select->from( array( 'a' => 'Proj_TenderOHMachineryDetails' ) )
                                    ->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'Name' => new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                    ->where( 'a.TenderMachineryTransId=' .$typedetailtrans[ 'TenderMachineryTransId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $rfcmdetailstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                $typedetailtrans['details'] = $rfcmdetailstrans;
                            }
                            break;
                        case '6':
                            //oh admin expense trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHAdminExpenseTrans' ) )
                                ->join( array( 'b' => 'Proj_AdminExpenseMaster' ), 'a.ExpenseId=b.ExpenseId', array( 'DescTypeId' => 'ExpenseId', 'Desc' => new Expression("Case When a.ExpenseId =0 then a.ExpenseName else b.ExpenseName end")), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderExpenseTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '7':
                            //oh salary trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHSalaryTrans' ) )
                                ->join( array( 'b' => 'WF_PositionMaster' ), 'a.PositionId=b.PositionId', array( 'DescTypeId' => 'PositionId', 'Desc' => new Expression("Case When a.PositionId =0 then a.PositionName else b.PositionName end")), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderSalaryTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                        case '8':
                            //oh fuel trans
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_TenderOHFuelTrans' ) )
                                ->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId', 'Desc' => 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'c' => 'Proj_Resource' ), 'a.FResourceId=c.ResourceId', array( 'FuelId' => 'ResourceId', 'Fuel' => 'ResourceName' ), $select::JOIN_LEFT )
                                ->join( array( 'd' => 'Proj_UOM' ), 'c.UnitId=d.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                ->columns(array('RFCTypeTransId' => 'TenderFuelTransId', '*'))
                                ->where( 'a.TenderTransId=' .$trans[ 'TenderTransId' ] );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $rfctypetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            break;
                    }

                    if(count($rfctypetrans))
                        $trans['typeTrans'] = $rfctypetrans;
                }


                $arr_rfcohtrans = array_merge($arr_rfcohtrans, $rfcohtrans);
                $this->_view->arr_rfcohtrans = array_reverse($arr_rfcohtrans);
            }


            // for other cost
            $subQuery = $sql->select();
            $subQuery->from(array('a' => 'Proj_TenderOHTrans'))
                ->join(array('b' => 'Proj_TenderQuotationRegister'), 'a.QuotationId=b.QuotationId', array(), $select:: JOIN_INNER)
                ->columns(array("OHId"));
            $subQuery->where(array('b.TenderEnquiryId' => $enquiryId));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_OHMaster'))
                ->join(array('b' => 'Proj_OHTypeMaster'), 'a.OHTypeId=b.OHTypeId', array('OHTypeId','OHTypeName'), $select:: JOIN_LEFT)
                ->columns(array('data' => 'OHId', 'value' =>'OHName'))
                ->where->expression('a.OHTypeID<>1 and  OHId Not IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_ohs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // type list start
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ServiceMaster'))
                ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ServiceId', 'value' =>'ServiceName','Rate'=> new Expression("'0'")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_services = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_Resource'))
                ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ResourceId', 'value' =>'ResourceName','Rate'))
                -> where('a.TypeId=1');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_labours = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_Resource'))
                ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ResourceId', 'value' =>'ResourceName','Rate'))
                -> where('a.TypeId=2');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_materials = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_ProjectIOWMaster'))
                ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate'=>'QualRate'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ProjectIOWId', 'value' =>new Expression("SerialNo + ' ' + Specification")))
                //-> where("a.ProjectId=$projectId and a.UnitId <>0");
                -> where("a.UnitId <>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_items = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_AdminExpenseMaster'))
                ->columns(array('data' => 'ExpenseId', 'value' =>'ExpenseName'))
                -> where('a.DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_adminexpenses = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'WF_PositionMaster'))
                ->columns(array('data' => 'PositionId', 'value' =>'PositionName'))
                -> where('a.DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_wfpositions = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_Resource'))
                ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ResourceId', 'value' =>'ResourceName'))
                -> where("a.TypeId=2 AND MaterialType='F'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_fueltypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_Resource'))
                ->columns(array('data' => 'ResourceId', 'value' =>'ResourceName'))
                -> where("a.TypeId=3");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_materialtypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' =>'Proj_Resource'))
                ->join(array('b' => 'Proj_UOM'), 'b.UnitId=a.WorkUnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                ->columns(array('data' => 'ResourceId', 'value' =>'ResourceName','Rate'=>'WorkRate'))
                -> where('a.TypeId=3');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->oh_machineries = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            // type list end
            // for other cost


            $select = $sql->select();
            $select->from(array('a' =>'Proj_TenderEnquiry'))
                ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select::JOIN_LEFT)
                ->columns(array("NameOfWork"))
                ->where(array('a.TenderEnquiryId'=>$enquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $followupName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $enquiryName ="";
            $clientName = "";
            if (!empty($followupName)) {
                $enquiryName = $followupName['NameOfWork'];
                $clientName = $followupName['ClientName'];
            }
            $this->_view->EnquiryName = $enquiryName;
            $this->_view->clientName = $clientName;


            $codegenType = 0;
            $select = $sql->select();
            $select->from('Proj_ResourceCodeSetup')
                ->columns(array('GenType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $codesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($codesetup)) $codegenType = $codesetup['GenType'];
            $this->_view->codegenType = $codegenType;


            $groupcodegenType = 0;
            $select = $sql->select();
            $select->from('Proj_RGCodeSetup')
                ->columns(array('GenType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $groupcodesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($groupcodesetup)) $groupcodegenType = $groupcodesetup['GenType'];
            $this->_view->groupcodegenType = $groupcodegenType;

            $sRefNo = "";
            $iQId =0;

            $select = $sql->select();
            $select->from('Proj_TenderQuotationRegister')
                ->columns(array('RefNo', 'QuotationId'))
                ->where(array('TenderEnquiryId' => $enquiryId, 'LiveQuotation' => 1))
                ->order('QuotationId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $nresults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($nresults)) {
                $sRefNo = $nresults['RefNo'];
                $iQId = $nresults['QuotationId'];
            }


            $this->_view->genType = $aVNo["genType"];
            if ($iQuotationId == 0 ) {
                if ($aVNo["genType"] ==false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];
            }

            $ipQuotationId=0;
            if ($smode =='revise') {
                $select = $sql->select();
                $select->from('Proj_TenderQuotationRegister')
                    ->columns(array('Orders' => new Expression("Count(QuotationId)")))
                    ->where(array('AQuotationId' => $iQId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $nresults1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $wocount = $nresults1->Orders + 1;

                if ($wocount != 0) $sRefNo = $sRefNo . '-A' . $wocount;

                $ipQuotationId = $iQId;
                $iQuotationId = 0;
                $smode = 'add';
                $this->_view->svNo = $sRefNo;
            }

            $this->_view->mode = $smode;
            $this->_view->quotationId = (isset($iQuotationId) && $iQuotationId!=0 ) ? $iQuotationId : 0;
            $this->_view->pquotationId = $ipQuotationId;
            $this->_view->enquiryId = $enquiryId;

        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function quotationAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Quotation");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $tEnquiryId=$this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'),'number');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $data = 'N';
                $response = $this->getResponse();
                switch ($RType) {
                    case 'getProject':
                        $clientId = $this->bsf->isNullCheck($postData['data'], 'number');
                        $select = $sql->select();
                        $select->from('Proj_TenderEnquiry')
                            ->columns(array('data' => 'TenderEnquiryId', 'value' => new Expression("RefNo+ '-' +NameofWork")))
                            //->where("ClientId='$clientId' and BidWin=1 and OrderReceived=0");
                            ->where("ClientId='$clientId'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data = json_encode($results);
                        break;
                }
                $response->setContent($data);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                //echo '<pre>'; print_r($postData); die;

                if($postData['qType'] == 'R') {
                    /*$select = $sql->select();
                    $select->from('Proj_TenderQuotationRegister')
                        ->columns(array('QuotationId'))
                        ->where(array("TenderEnquiryId" => $postData['enquiryId'], "AQuotationId" => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $revisedQuotation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();*/

                    $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'tender', 'action' => 'tender-quotation', 'enquiryId' => $postData['enquiryId'], 'quotationId' => $postData['revQuoteId'], 'mode' => 'revise'));
                } else {
                    $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'tender', 'action' => 'tender-quotation', 'enquiryId' => $postData['enquiryId']));
                }
            }

            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('data' => 'TenderEnquiryId', 'value' => new Expression("RefNo+ '-' +NameofWork")));
            //->where("BidWin=1 and OrderReceived=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // Clients
            $select = $sql->select();
            $select->from('Proj_ClientMaster')
                ->columns(array('data' => 'ClientId', 'value' => 'ClientName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->clients = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->tEnquiryId=$tEnquiryId;
            if($tEnquiryId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderEnquiry'))
                    ->columns(array("NameOfWork"))
                    ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName','ClientId'), $select:: JOIN_LEFT)
                    ->where("a.TenderEnquiryId='$tEnquiryId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->eQuotation = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function deleteQuotationAction()
    {
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
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $quotationId = $this->params()->fromPost('quotationId');
                    $sql = new Sql($dbAdapter);

                    $response = $this->getResponse();

                    $connection->beginTransaction();

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWorkGroup')
                        ->where(array("QuotationId" => $quotationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationResourceTrans')
                        ->where(array("QuotationId" => $quotationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $subQuery = $sql->select();
                    $subQuery->from("Proj_TenderQuotationTrans")
                        ->columns(array("QuotationTransId"));
                    $subQuery->where(array('QuotationId' => $quotationId));

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationRateAnalysis')
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationTrans')
                        ->where(array("QuotationId" => $quotationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderQuotationRegister')
                        ->where(array("QuotationId" => $quotationId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function loadBoqAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $enquiryId = $this->bsf->isNullCheck($request->getPost('enquiryId'), 'number');
                $woId = $this->bsf->isNullCheck($request->getPost('woId'), 'number');

                $iQuotationId=0;
                $select = $sql->select();
                $select->from('Proj_TenderQuotationRegister')
                    ->columns(array('QuotationId'))
                    ->where(array('TenderEnquiryId'=>$enquiryId,'LiveQuotation'=>1))
                    ->order('QuotationId Desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $quotreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($quotreg)) $iQuotationId = $quotreg['QuotationId'];

//                $select = $sql->select();
//                $select->from('Proj_WorkGroupMaster')
//                    ->columns(array('WorkGroupId', 'SerialNo', 'WorkGroupName'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $workgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $parentdata = array();
//
//                $k=0;
//                for ($i=0;$i < count($workgroup);$i++)
//                {
//                    $parentdata[$k]['data'] = $workgroup[$i]['WorkGroupId'];
//                    $parentdata[$k]['value'] = $workgroup[$i]['SerialNo'] . '   ' . $workgroup[$i]['WorkGroupName'];
//
//                    $k=$k+1;
//
//                    $select = $sql->select();
//                    $select->from('Proj_IOWMaster')
//                        ->columns(array('IOWId', 'SerialNo', 'Specification'))
//                        ->where(array("IOWs" => 0,"WorkGroupId"=>$workgroup[$i]['WorkGroupId']));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $parentiow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    for ($j=0;$j < count($parentiow);$j++) {
//                        $parentdata[$k]['data'] =  $workgroup[$i]['WorkGroupId'].'.'.$parentiow[$j]['IOWId'];
//                        $parentdata[$k]['value'] = $parentiow[$j]['SerialNo'] . '   ' .$parentiow[$j]['Specification'];
//                        $k=$k+1;
//                    }
//                }
//
//                $this->_view->parentiow = $parentdata;

//                $select = $sql->select();
//                $select->from('Proj_UOM')
//                    ->columns(array("data"=>'UnitId', "value"=>'UnitName'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->unit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_Resource')
                    ->columns(array("id"=>'ResourceId',"type"=>new Expression("'R'"),"value"=> new Expression("Code + ' ' +ResourceName")))
                    ->where(array("DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_IOWMaster')
                    ->columns(array("id"=>'IOWId',"type"=>new Expression("'I'"),"value"=> new Expression("SerialNo + ' ' +Specification ")))
                    ->where(array("DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resiowlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_IOWMaster')
                    ->columns(array('IOWId', 'Specification'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->iowlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ResourceGroup'))
                    ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->columns(array('data' => 'ResourceGroupId', 'value' => 'ResourceGroupName','TypeId'), array('TypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from('Proj_WorkTypeMaster')
//                    ->columns(array('data' => 'WorkTypeId', 'value' => 'WorkType'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->worktype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if($woId == 0 && $enquiryId != 0) {
//                    $select = $sql->select();
//                    $select->from('Proj_TenderQuotationRegister')
//                        ->columns(array('QuotationId'))
//                        ->where(array("TenderEnquiryId" => $enquiryId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a'=>'Proj_TenderQuotationTrans'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_TenderWorkGroup'), 'a.ProjectWorkGroupId=c.PWorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_WorkTypeMaster'), 'c.WorkTypeId=d.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness','WorkType'), $select:: JOIN_LEFT)
                        ->join(array('e' => 'Proj_WorkGroupMaster'), 'c.WorkGroupId=e.WorkGroupId', array('LWorkGroupName'=>new Expression("e.WorkGroupName")), $select:: JOIN_LEFT)
                        ->columns(array('*'),array('UnitName'))
                        ->where(array("QuotationId" => $iQuotationId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                    $select = $sql->select();
//                    $select->from(array('a'=>'Proj_TenderQuotationTrans'))
//                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
//                        ->columns(array('*'),array('UnitName'))
//                        ->where(array("QuotationId" => $iQuotationId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select();
                    $subQuery->from("Proj_TenderQuotationTrans")
                        ->columns(array("QuotationTransId"));
                    $subQuery->where(array('QuotationId' => $iQuotationId));

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_TenderQuotationQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess','SBCess','NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'KKCessAmt','SBCessAmt','SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType'=>'S'));
                    $select->where->expression('QuotationTransId IN ?', array($subQuery));
                    $select->order('a.SortId ASC');

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList);
//                    $arrqual[$iRFCId] = $sHtml;

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderQuotationRateAnalysis'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('QuotationTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId','ResourceId', 'Qty', 'Rate', 'Amount','Formula', 'MixType', 'TransType', 'Description','Wastage','WastageQty','WastageAmount','Weightage','RateType','Wastage','WastageQty','WastageAmount','Weightage'))
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderWBSTrans'))
                        ->join(array('b' => 'Proj_TenderWBSMaster'), 'a.WBSId=b.WBSId', array('WBSName'=> new Expression("ParentText +'->'+WBSName")), $select:: JOIN_LEFT)
                        ->columns(array('QuotationTransId', 'WBSId', 'Qty'))
                        ->where->expression('QuotationTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->rfcwbstrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $this->_view->rfcid = $iQuotationId;
                } else if($woId != 0 && $enquiryId != 0) {
                    $select = $sql->select();
                    $select->from(array('a'=>'Proj_TenderWOTrans'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_TenderWorkGroup'), 'a.ProjectWorkGroupId=c.PWorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_WorkTypeMaster'), 'c.WorkTypeId=d.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness','WorkType'), $select:: JOIN_LEFT)
                        ->join(array('e' => 'Proj_WorkGroupMaster'), 'c.WorkGroupId=e.WorkGroupId', array('LWorkGroupName'=>new Expression("e.WorkGroupName")), $select:: JOIN_LEFT)
                        ->columns(array('QuotationTransId'=>new Expression("TenderWOTransId"),'RefSerialNo','Specification','ShortSpec','Qty','Rate','Amount','QuotedRate','QuotedAmount','IOWId','Header','UnitId','WorkGroupId','PrevTenderWOTransId','WorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','RWorkingQty','ProjectWorkGroupId','SiteMixRatio','ReadyMixRatio','BaseRate',
                            'NetRate','ParentName','PrevQuotationTransId','ProjectIOWId','ProjectWorkGroupName','QualifierValue','RBaseRate','RNetRate','RQualifierValue','RTotalRate','RWastageAmt','TotalRate','WastageAmt','WorkTypeId'))
                        ->where(array("WORegisterId" => $woId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if (empty($rfctrans)) {

                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_TenderQuotationTrans'))
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_TenderWorkGroup'), 'a.ProjectWorkGroupId=c.PWorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'Proj_WorkTypeMaster'), 'c.WorkTypeId=d.WorkTypeId', array('ConcreteMix','Cement','Sand','Metal','Thickness','WorkType'), $select:: JOIN_LEFT)
                            ->join(array('e' => 'Proj_WorkGroupMaster'), 'c.WorkGroupId=e.WorkGroupId', array('LWorkGroupName'=>new Expression("e.WorkGroupName")), $select:: JOIN_LEFT)
                            ->columns(array('*'))
                            ->where(array("QuotationId" => $iQuotationId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("Proj_TenderQuotationTrans")
                            ->columns(array("QuotationTransId"));
                        $subQuery->where(array('QuotationId' => $iQuotationId));

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_TenderQuotationRateAnalysis'))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('QuotationTransId', 'IncludeFlag', 'ReferenceId', 'SubIOWId','ResourceId', 'Qty', 'Rate', 'Amount','Formula', 'MixType', 'TransType', 'Description','Wastage','WastageQty','WastageAmount','Weightage','RateType','Wastage','WastageQty','WastageAmount','Weightage'))
                            ->where->expression('QuotationTransId IN ?', array($subQuery));
                        $select->order('a.SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_TenderWBSTrans'))
                            ->join(array('b' => 'Proj_TenderWBSMaster'), 'a.WBSId=b.WBSId', array('WBSName'=> new Expression("ParentText +'->'+WBSName")), $select:: JOIN_LEFT)
                            ->columns(array('QuotationTransId', 'WBSId', 'Qty'))
                            ->where->expression('QuotationTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->rfcwbstrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else {

                        $subQuery = $sql->select();
                        $subQuery->from("Proj_TenderWOTrans")
                            ->columns(array("TenderWOTransId"));
                        $subQuery->where(array('WORegisterId' => $woId));

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_TenderWOQualTrans"))
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                            ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess','SBCess','NetPer',
                                'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'KKCessAmt','SBCessAmt','SurChargeAmt',
                                'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
                        $select->where(array('a.MixType'=>'S'));
                        $select->where->expression('TenderWOTransId IN ?', array($subQuery));
                        $select->order('a.SortId ASC');

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
//                        $arrqual[$iRFCId] = $sHtml;

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_TenderWORateAnalysis'))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName', 'TypeId'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('QuotationTransId'=>new Expression("TenderWOTransId"), 'IncludeFlag', 'ReferenceId', 'SubIOWId','ResourceId', 'Qty', 'Rate', 'Amount','Formula', 'MixType', 'TransType', 'Description','RateType','Wastage','WastageQty','WastageAmount','Weightage'))
                            ->where->expression('TenderWOTransId IN ?', array($subQuery));
                        $select->order('a.SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->rfcrateanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_TenderWOWBSTrans'))
                            ->join(array('b' => 'Proj_TenderWBSMaster'), 'a.WBSId=b.WBSId', array('WBSName'=> new Expression("ParentText +'->'+WBSName")), $select:: JOIN_LEFT)
                            ->columns(array('QuotationTransId'=>new Expression("TenderWOTransId"), 'WBSId', 'Qty'))
                            ->where->expression('TenderWOTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->rfcwbstrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }


                    $this->_view->rfctrans = $rfctrans;



                    $this->_view->rfcid = $woId;
                }

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function deleteWorkorderAction()
    {
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
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $woId = $this->params()->fromPost('woId');
                    $sql = new Sql($dbAdapter);

                    $response = $this->getResponse();

                    $connection->beginTransaction();

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWOWorkGroupTrans')
                        ->where(array("WORegisterId" => $woId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWOResourceTrans')
                        ->where(array("WORegisterId" => $woId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $subQuery = $sql->select();
                    $subQuery->from("Proj_TenderWOTrans")
                        ->columns(array("TenderWOTransId"));
                    $subQuery->where(array('WORegisterId' => $woId));

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWORateAnalysis')
                        ->where->expression('TenderWOTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_TenderWOTrans')
                        ->where(array("WORegisterId" => $woId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_WORegister')
                        ->where(array("WORegisterId" => $woId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function updatetenderwbsmasterAction(){
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

        if($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);
                $iEnquiryId = $this->bsf->isNullCheck($this->params()->fromPost('EnquiryId'), 'number');
                $data= $this->params()->fromPost('treePostData');
                $wbsdata= $this->params()->fromPost('masterdata');
                $tempTransIds = array();
                if (!empty($wbsdata)) {
                    foreach ($wbsdata as $wdata) {
                        $wbsId = $this->bsf->isNullCheck($wdata['id'], 'string');
                        $name = $this->bsf->isNullCheck($wdata['name'], 'string');
                        $parentid = $this->bsf->isNullCheck($wdata['parentid'], 'string');
                        $sortId = $this->bsf->isNullCheck($wdata['sortId'], 'number');
                        $lastLevel = $this->bsf->isNullCheck($wdata['lastLevel'], 'number');

                        if (strpos($parentid, 'jqxWidget') !== false) {
                            $iparentid  =$tempTransIds[$parentid];
                        } else {
                            $iparentid = $parentid;
                        }

                        if (strpos($wbsId, 'jqxWidget') === false) {
                            $update = $sql->update();
                            $update->table('Proj_TenderWBSMaster');
                            $update->set(array(
                                'WBSName' => $name,'SortOrder'=>$sortId,'LastLevel'=>$lastLevel));
                            $update->where(array('WBSId' => $wbsId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        } else {
                            $insert = $sql->insert();
                            $insert->into( 'Proj_TenderWBSMaster' );
                            $insert->Values( array( 'TenderEnquiryId' => $iEnquiryId, 'SortOrder'=>$sortId,'LastLevel' => $lastLevel,'IOWUsed' =>0
                            ,'ParentId' => $iparentid, 'WBSName' => $name) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $tempTransIds[$wbsId] = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }
                    }
                    $this->_updateWBSParent($iEnquiryId,$dbAdapter);
                }

                if (!empty($data)) {
                    foreach ($data as $request) {
                        $wbsId = $this->bsf->isNullCheck($request['id'], 'number');
                        $wbsName = $this->bsf->isNullCheck($request['name'], 'string');
                        $action = $this->bsf->isNullCheck($request['action'], 'string');

                        if ($action == 'delete') {
                            $delete = $sql->delete();
                            $delete->from('Proj_TenderWBSMaster')
                                ->where(array("WBSId" => $wbsId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderWBSMaster'))
                    ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => 'WBSName','LastLevel','IOWUsed'))
                    ->where(array('TenderEnquiryId' => $iEnquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arr_wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (empty($arr_wbslist)) {
                    $insert = $sql->insert();
                    $insert->into('Proj_TenderWBSMaster');
                    $insert->Values(array('TenderEnquiryId' => $iProjectId, 'WBSName' => 'WBSName','LastLevel'=>1,'IOWUsed'=>0));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderWBSMaster'))
                        ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => 'WBSName','LastLevel','IOWUsed'))
                        ->where(array('TenderEnquiryId' => $iEnquiryId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arr_wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

                $response = $this->getResponse();
                $response->setContent(json_encode($arr_wbslist));
                return $response;
            }
        }
    }


    public function _createProject($dbAdapter,$ProjectName,$WoRegisterId,$costCentreId,$projectTypeId)
    {
        $sql = new Sql($dbAdapter);
        $insert = $sql->insert();
        $insert->into('Proj_ProjectMaster');
        $insert->Values(array('ProjectName' => $ProjectName, 'ProjectTypeId' => $projectTypeId, 'WORegisterId' => $WoRegisterId));
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        $projectId = $dbAdapter->getDriver()->getLastGeneratedValue();

        $insert = $sql->insert();
        $insert->into('WF_OperationalCostCentre');
        $insert->Values(array('CostCentreName' => $ProjectName
        , 'FACostCentreId' => $costCentreId
        , 'ProjectId' => $projectId));
        $statement = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
    public function _updateWBSParent($iEnquiryId,$dbAdapter) {

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_TenderWBSMaster')
            ->columns(array('WBSId'))
            ->where("TenderEnquiryId=$iEnquiryId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach ($wbslist as $trans) {
            $iWBSId = $trans['WBSId'];

            $statement = "exec Get_TenderWBS_Hierarchy_Parent @Id= " .$iWBSId;
            $parent= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $sParentText ="";
            foreach ($parent as $ptrans) {
                if ($ptrans['WBSId'] != $iWBSId) {
                    $sParentText = $sParentText . $ptrans['WBSName'] . "->";
                }
            }
            $sParentText  = rtrim($sParentText , '->');

            $update = $sql->update();
            $update->table('Proj_TenderWBSMaster');
            $update->set(array('ParentText' => $sParentText));
            $update->where(array('WBSId' => $iWBSId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }
    public function gettenderwbsmasterlistAction(){
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

        if($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);
                $iEnquiryId= $this->bsf->isNullCheck($this->params()->fromPost('EnquiryId'), 'number');


                $select = $sql->select();
                $select->from('Proj_TenderWBSMaster')
                    ->columns(array('data' => 'WBSId', 'value' => new Expression("ParentText + '->' + WBSName"),'ParentId'))
                    ->where(array('TenderEnquiryId' => $iEnquiryId,'LastLevel'=>1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arr_wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $response = $this->getResponse();
                $response->setContent(json_encode($arr_wbslist));
                return $response;
            }
        }
    }
    public function gettenderwbsmasterAction(){
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

        if($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);
                $iEnquiryId = $this->bsf->isNullCheck($this->params()->fromPost('EnquiryId'), 'number');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderWBSMaster'))
                    ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => 'WBSName','LastLevel','IOWUsed'))
                    ->where(array('TenderEnquiryId' => $iEnquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arr_wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (empty($arr_wbslist)) {
                    $insert = $sql->insert();
                    $insert->into('Proj_TenderWBSMaster');
                    $insert->Values(array('TenderEnquiryId' => $iEnquiryId, 'WBSName' => 'WBSName','LastLevel'=>1,'IOWUsed'=>0));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderWBSMaster'))
                        ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => 'WBSName','LastLevel','IOWUsed'))
                        ->where(array('TenderEnquiryId' => $iEnquiryId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arr_wbslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $response = $this->getResponse();
                $response->setContent(json_encode($arr_wbslist));
                return $response;
            }
        }
    }

    public function addchecklistmasterAction(){
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
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $result = array();
                    $name = $this->bsf->isNullCheck($this->params()->fromPost('name'), 'string');
                    $type = $this->bsf->isNullCheck($this->params()->fromPost('type'), 'number');

                    $connection->beginTransaction();

                    // create new checklist
                    $insert = $sql->insert();
                    $insert->into( 'Proj_CheckListMaster' )
                        ->Values( array( 'CheckListName' => $name, 'TypeId' => $type ) );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    $chklstId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $connection->commit();

                    $sessionTender = new Container('sessionTender');
                    $sessionTender->addCl = '1';

                    $result['Id'] = $chklstId;
                    $result['Name'] = $name;
                    return $this->getResponse()
                        ->setContent(json_encode($result))
                        ->setStatusCode(200);
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }

    public function addenclosuremasterAction(){
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
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $result = array();
                    $name = $this->bsf->isNullCheck($this->params()->fromPost('enclosureName'), 'string');

                    $connection->beginTransaction();

                    // create new checklist
                    $insert = $sql->insert();
                    $insert->into('Proj_TenderEnclosureMaster')
                        ->Values( array( 'EnclosureName' => $name) );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    $chklstId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $connection->commit();

                    $sessionTender = new Container('sessionTender');
                    $sessionTender->addCl = '1';

                    $result['Id'] = $chklstId;
                    $result['Name'] = $name;
                    return $this->getResponse()
                        ->setContent(json_encode($result))
                        ->setStatusCode(200);
                } catch ( PDOException $e ) {
                    $connection->rollback();
                    return $this->getResponse()
                        ->setStatusCode(400);
                }

            }
        }
    }
    public function findenclosureAction(){
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
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $name = $this->bsf->isNullCheck($this->params()->fromPost('enclosureName'), 'string');
//                $id = $this->bsf->isNullCheck($this->params()->fromPost('id'), 'number');

                $select = $sql->select();
                $select->from('Proj_TenderEnclosureMaster')
                    ->columns( array( 'EnclosureId'))
                    ->where(array("EnclosureName"=>$name));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (sizeof($results) !=0 )
                    return $this->getResponse()->setStatusCode(200)->setContent('Y');

                return $this->getResponse()->setStatusCode(201)->setContent('N');
            }
        }
    }

    public function loadProcessAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $enquiryId = $this->bsf->isNullCheck($request->getPost('enquiryId'), 'number');

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderProcessTrans"))
                    ->columns(array('TenderProcessId', 'Status','Flag'))
                    ->join(array('b' => 'Proj_TenderProcessMaster'), 'a.TenderProcessId = b.TenderProcessId', array('ProcessName'))
                    ->where('a.TenderEnquiryId=' . $enquiryId)
                    ->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->processSteps = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->enquiryId = $enquiryId;


                $quoteApprove="";
                $select = $sql->select();
                $select->from('Proj_TenderQuotationRegister')
                    ->columns(array('Approve'))
                    ->where("TenderEnquiryId=$enquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $quoteReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                if (!empty($quoteReg)) $quoteApprove = $quoteReg['Approve'];

                $woApprove="";
                $select = $sql->select();
                $select->from('Proj_WORegister')
                    ->columns(array('Approve'))
                    ->where("TenderEnquiryId=$enquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $woReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                if (!empty($woReg)) $woApprove = $woReg['Approve'];

                $this->_view->quoteApprove = $quoteApprove;
                $this->_view->woApprove = $woApprove;

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function clientmasterAction(){
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
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            $select = $sql->select();
            $select->from( array( 'c' => 'Proj_ClientMaster' ))
                ->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=c.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns( array( 'ClientId','ClientName','Address', 'Email','Phone'))
                ->where("c.DeleteFlag='0'")
                ->order('c.ClientName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->clientReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function addclientAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{

            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $clientName = $this->params()->fromPost('clientNamenew');
                    $address = $this->params()->fromPost('addressnew');
                    $cityName = $this->params()->fromPost('citynew');
                    $stateName = $this->params()->fromPost('statenew');
                    $countryName = $this->params()->fromPost('countrynew');
                    $email = $this->params()->fromPost('emailnew');
                    $phoneNew = $this->params()->fromPost('phoneNew');

                    $connection->beginTransaction();

                    // check city found
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId'))
                        ->where("CityName='$cityName'")
                        ->limit(1);
                    $city_stmt = $sql->getSqlStringForSqlObject($select);
                    $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $cityId = null;
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

                        $stateId = null;
                        $countryId = null;
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

                            if($country) {
                                // country found
                                $countryId = $country['CountryId'];
                            } else {
                                // country not found have to insert
                                $insert = $sql->insert();
                                $insert->into('WF_CountryMaster');
                                $insert->Values(array('CountryName'=>$countryName));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            // add state
                            $insert = $sql->insert();
                            $insert->into('WF_StateMaster');
                            $insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // add city
                        $insert = $sql->insert();
                        $insert->into('WF_CityMaster');
                        $insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_ClientMaster');
                    $insert->Values(array('ClientName' => $clientName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email,'Phone'=>$phoneNew));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'c' => 'Proj_ClientMaster' ))
                        ->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=c.CityId', array('CityName'), $select::JOIN_LEFT)
                        ->columns( array( 'ClientId','ClientName','Address', 'Email' ))
                        ->where( "ClientId='$clientId'" );
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
    public function checkclientFoundAction(){
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
                    $clientId = $this->params()->fromPost('clientId');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($clientId != null){
                        $clientName = $this->params()->fromPost('clientName');
                        $select->from( array( 'c' => 'Proj_ClientMaster' ))
                            ->columns( array( 'ClientId'))
                            ->where( "ClientName='$clientName' and ClientId<> '$clientId' and DeleteFlag=0");
                    } else{
                        $clientName = $this->params()->fromPost('clientNamenew');
                        $select->from( array( 'c' => 'Proj_ClientMaster' ))
                            ->columns( array( 'ClientId'))
                            ->where( "ClientName='$clientName' and DeleteFlag=0");
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
    public function deleteclientAction(){
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
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $ClientId = $this->bsf->isNullCheck($this->params()->fromPost('ClientId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select = $sql->select();
                            $select->from( 'Proj_TenderEnquiry' )
                                ->columns( array( 'ClientId' ) )
                                ->where( array( 'ClientId' => $ClientId ) );

//                            $select2 = $sql->select();
//                            $select2->from( 'CB_ReceiptRegister' )
//                                ->columns( array( 'ClientId' ) )
//                                ->where( array( 'ClientId' => $ClientId ) );
//                            $select2->combine( $select, 'Union ALL' );

//                            $select1 = $sql->select();
//                            $select1->from( 'CB_ProjectMaster' )
//                                ->columns( array( 'ClientId' ) )
//                                ->where( array( 'ClientId' => $ClientId ) );
//                            $select1->combine( $select2, 'Union ALL' );

                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $client = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if ( count( $client ) > 0 ) {
                                $response->setStatusCode( 201 )->setContent( $status );
                                return $response;
                            }

                            $response->setStatusCode('200')->setContent('Not used');
                            return $response;
                            break;
                        case 'update':
                            $select = $sql->select();
                            $select->from( 'Proj_ClientMaster' )
                                ->columns( array( 'ClientName' ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $clientname = $bills->ClientName;

                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table( 'Proj_ClientMaster' )
                                ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'Remarks' => $Remarks ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

//                            CommonHelper::insertCBLog( 'Client-Master-Delete', $ClientId, $clientname, $dbAdapter );
                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function editclientAction(){
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
                    $ClientId = $this->params()->fromPost('clientId');
                    $clientName = $this->params()->fromPost('clientName');
                    $address = $this->params()->fromPost('address');
                    $cityName = $this->params()->fromPost('city');
                    $stateName = $this->params()->fromPost('state');
                    $countryName = $this->params()->fromPost('country');
                    $email = $this->params()->fromPost('email');
                    $phoneNo = $this->params()->fromPost('phoneNo');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    // check city found
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId'))
                        ->where("CityName='$cityName'")
                        ->limit(1);
                    $city_stmt = $sql->getSqlStringForSqlObject($select);
                    $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $cityId = null;
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

                        $stateId = null;
                        $countryId = null;
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

                            if($country) {
                                // country found
                                $countryId = $country['CountryId'];
                            } else {
                                // country not found have to insert
                                $insert = $sql->insert();
                                $insert->into('WF_CountryMaster');
                                $insert->Values(array('CountryName'=>$countryName));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            // add state
                            $insert = $sql->insert();
                            $insert->into('WF_StateMaster');
                            $insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // add city
                        $insert = $sql->insert();
                        $insert->into('WF_CityMaster');
                        $insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $update = $sql->update();
                    $update->table('Proj_ClientMaster')
                        ->set(array('ClientName' => $clientName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email,'Phone'=>$phoneNo))
                        ->where(array('ClientId' => $ClientId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    CommonHelper::insertCBLog('Client-Master-Edit',$ClientId,$clientName,$dbAdapter);

                    $connection->commit();

                    $status = 'Edit';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function consultantmasterAction(){
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
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_ConsultantMaster' ))
                ->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=a.CityId', array('CityName'), $select::JOIN_LEFT)
                ->join(array('b' => 'Proj_TenderConsultant'), 'a.ConsultantId = b.ConsultantId', array(),$select::JOIN_LEFT)
                ->columns( array(new Expression("a.ConsultantId as ConsultantId,a.ConsultantName as ConsultantName,
                    a.Address as Address,Case When b.ConsultantType='1' Then 'Personal Consultant' When b.ConsultantType='2' Then 'Technical Consultant' When b.ConsultantType='3' Then 'Business Consultant' Else 'Executive Consultant' End As ConsultantTypeName")))
                ->where("a.DeleteFlag='0'")
                ->order('a.ConsultantName');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->consultantReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            $this->_view->consultantType = $this->bsf->getConsultantType();
//            print_r($this->_view->consultantType);die;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function addconsultantAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{

            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $consultantName = $this->params()->fromPost('consultantNamenew');
                    $address = $this->params()->fromPost('addressnew');
                    $cityName = $this->params()->fromPost('citynew');
                    $stateName = $this->params()->fromPost('statenew');
                    $countryName = $this->params()->fromPost('countrynew');
                    $newCType = $this->params()->fromPost('newCType');

                    $connection->beginTransaction();

                    // check city found
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId'))
                        ->where("CityName='$cityName'")
                        ->limit(1);
                    $city_stmt = $sql->getSqlStringForSqlObject($select);
                    $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $cityId = null;
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

                        $stateId = null;
                        $countryId = null;
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

                            if($country) {
                                // country found
                                $countryId = $country['CountryId'];
                            } else {
                                // country not found have to insert
                                $insert = $sql->insert();
                                $insert->into('WF_CountryMaster');
                                $insert->Values(array('CountryName'=>$countryName));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            // add state
                            $insert = $sql->insert();
                            $insert->into('WF_StateMaster');
                            $insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // add city
                        $insert = $sql->insert();
                        $insert->into('WF_CityMaster');
                        $insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_ConsultantMaster');
                    $insert->Values(array('ConsultantName' => $consultantName, 'Address' => $address, 'CityId' => $cityId));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $consultantId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $insert = $sql->insert();
                    $insert->into('Proj_TenderConsultant');
                    $insert->Values(array(
                        'ConsultantId' => $consultantId
                    , 'ConsultantType' => $newCType
                    , 'ConsultantAddress' => $address));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'c' => 'Proj_ConsultantMaster' ))
                        ->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=c.CityId', array('CityName'), $select::JOIN_LEFT)
                        ->columns( array( 'ConsultantId','ConsultantName','Address'))
                        ->where( "ConsultantId='$consultantId'" );
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
    public function checkconsultantFoundAction(){
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
                    $consultantId = $this->params()->fromPost('consultantId');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($consultantId != null){
                        $consultantName = $this->params()->fromPost('consultantName');
                        $select->from( array( 'c' => 'Proj_ConsultantMaster' ))
                            ->columns( array( 'ConsultantId'))
                            ->where( "ConsultantName='$consultantName' and ConsultantId<> '$consultantId' and DeleteFlag=0");
                    } else{
                        $consultantName = $this->params()->fromPost('consultantNamenew');
                        $select->from( array( 'c' => 'Proj_ConsultantMaster' ))
                            ->columns( array( 'ConsultantId'))
                            ->where( "ConsultantName='$consultantName' and DeleteFlag=0");
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
    public function deleteconsultantAction(){
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
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $consultantId = $this->bsf->isNullCheck($this->params()->fromPost('consultantId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select = $sql->select();
                            $select->from( 'Proj_TenderConsultant' )
                                ->columns( array( 'ConsultantId' ) )
                                ->where( array( 'ConsultantId' => $consultantId ) );

//                            $select2 = $sql->select();
//                            $select2->from( 'CB_ReceiptRegister' )
//                                ->columns( array( 'ClientId' ) )
//                                ->where( array( 'ClientId' => $ClientId ) );
//                            $select2->combine( $select, 'Union ALL' );

//                            $select1 = $sql->select();
//                            $select1->from( 'CB_ProjectMaster' )
//                                ->columns( array( 'ClientId' ) )
//                                ->where( array( 'ClientId' => $ClientId ) );
//                            $select1->combine( $select2, 'Union ALL' );

                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $client = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if ( count( $client ) > 0 ) {
                                $response->setStatusCode( 201 )->setContent( $status );
                                return $response;
                            }

                            $response->setStatusCode('200')->setContent('Not used');
                            return $response;
                            break;
                        case 'update':
                            $select = $sql->select();
                            $select->from( 'Proj_ConsultantMaster' )
                                ->columns( array( 'ConsultantName' ) )
                                ->where( array( 'ConsultantId' => $consultantId ));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $clientname = $bills->ClientName;

                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table( 'Proj_ConsultantMaster' )
                                ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'Remarks' => $Remarks ) )
                                ->where( array( 'ConsultantId' => $consultantId ));
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

//                            CommonHelper::insertCBLog( 'Client-Master-Delete', $ClientId, $clientname, $dbAdapter );
                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function editconsultantAction(){
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
                    $consultantId = $this->params()->fromPost('consultantId');
                    $consultantName = $this->params()->fromPost('consultantName');
                    $address = $this->params()->fromPost('address');
                    $cityName = $this->params()->fromPost('city');
                    $stateName = $this->params()->fromPost('state');
                    $countryName = $this->params()->fromPost('country');
                    $email = $this->params()->fromPost('email');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    // check city found
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId'))
                        ->where("CityName='$cityName'")
                        ->limit(1);
                    $city_stmt = $sql->getSqlStringForSqlObject($select);
                    $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $cityId = null;
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

                        $stateId = null;
                        $countryId = null;
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

                            if($country) {
                                // country found
                                $countryId = $country['CountryId'];
                            } else {
                                // country not found have to insert
                                $insert = $sql->insert();
                                $insert->into('WF_CountryMaster');
                                $insert->Values(array('CountryName'=>$countryName));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            // add state
                            $insert = $sql->insert();
                            $insert->into('WF_StateMaster');
                            $insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // add city
                        $insert = $sql->insert();
                        $insert->into('WF_CityMaster');
                        $insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $update = $sql->update();
                    $update->table('Proj_ConsultantMaster')
                        ->set(array('ConsultantName' => $consultantName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email))
                        ->where(array('ConsultantId' => $consultantId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    CommonHelper::insertCBLog('Client-Master-Edit',$ClientId,$clientName,$dbAdapter);

                    $connection->commit();

                    $status = 'Edit';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function checkConsultantAction()
    {
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
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck($postData['rtype'], 'string');
                $data = 'N';
                $SearchStr = $this->bsf->isNullCheck($postData['search'], 'string');
                $select = $sql->select();
                $response = $this->getResponse();
                switch ($RType) {
                    case 'consultname':
                        $select = $sql->select();
                        $select->from('Proj_ConsultantMaster')
                            ->columns(array('ConsultantId'))
                            ->where("ConsultantName='$SearchStr' AND DeleteFlag='0'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (sizeof($results) != 0)
                            $data = 'Y';
                        break;
                }
                $response->setContent($data);
                return $response;
            }
        }
    }

    public function getQuotationsAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_TenderQuotationRegister')
                    ->columns(array('data' => 'QuotationId', 'value' => 'RefNo'))
                    ->where(array("TenderEnquiryId" => $postData['enquiryId'], "LiveQuotation" => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function checkSerialFoundAction()
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
                $slno = $postParams['serialno'];
                $quotationid =  intval($this->bsf->isNullCheck($postParams['quotationid'],'number'));
                $prevtransid =  intval($this->bsf->isNullCheck($postParams['prevtransid'],'number'));

                $select = $sql->select();
                $select->from('Proj_TenderQuotationTrans')
                    ->columns(array('PrevQuotationTransId'))
                    ->where(array("SerialNo" => $slno,"PrevQuotationTransId !=$prevtransid","QuotationId" =>$quotationid));
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
    public function checkiowspecfoundAction()
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
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $quotationid =  intval($this->bsf->isNullCheck($postParams['quotationid'],'number'));
                $prevtransid =  intval($this->bsf->isNullCheck($postParams['prevtransid'],'number'));
                $spec =  $this->bsf->isNullCheck($postParams['spec'],'string');
//                $spec =  str_replace(' ', '', $spec);
                $spec = $this->bsf->sanitizeString($spec);

                $select = $sql->select();
                $select->from('Proj_TenderQuotationTrans')
                    ->columns(array('PrevQuotationTransId'))
                    ->where("dbo.fn_StripCharacters(Specification,'^a-zA-Z0-9') ='$spec' and PrevQuotationTransId <> $prevtransid and QuotationId=$quotationid");
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
    public function getBranchesAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $companyId = $postParams['companyId'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WF_CompanyBranch')
                    ->columns(array('BranchId', 'BranchName'))
                    ->where(array('CompanyId' => $companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }
    public function getiowMasterAction(){
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

        if($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);
                $iQId = $this->bsf->isNullCheck($this->params()->fromPost('QuotationId'), 'number');

                $subQuery = $sql->select();
                $subQuery->from("Proj_TenderQuotationTrans")
                    ->columns(array("IOWId"))
                    ->where(array('QuotationId'=>$iQId));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_IOWMaster'))
                    ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('WorkGroupId','WorkGroupName','WorkTypeId'), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_WorkTypeMaster'), 'b.WorkTypeId=c.WorkTypeId', array('WorkTypeName'=>new Expression('WorkType')), $select::JOIN_INNER)
                    ->columns(array('IOWId', 'SerialNo','Specification','Header','Select'=>new Expression("'0'")))
                    ->where->expression('a.DeleteFlag=0 and a.IOWId Not IN ?', array($subQuery));
                $select->order(array('SlNo ASC'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $parentdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response = $this->getResponse();
                $response->setContent(json_encode($parentdata));
                return $response;
            }
        }
    }

    public function tendersubmitformAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Tender Submit Form");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent('');
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                try {
                    $postData = $request->getPost();

                    $enquiryId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');
                    $iSubmitId = $this->bsf->isNullCheck($postData['submitId'], 'number');

                    $emdid = $this->bsf->isNullCheck($postData['emdid'], 'number');
                    $emdno = $this->bsf->isNullCheck($postData['emdno'], 'string');
                    $emddate = $this->bsf->isNullCheck($postData['emddate'], 'string');
                    $emdcost = $this->bsf->isNullCheck($postData['emdcost'], 'number');
                    $emdmode = $this->bsf->isNullCheck($postData['emdmode'], 'number');
                    $emdfavour = $this->bsf->isNullCheck($postData['emdfavour'], 'string');
                    $emdbankname = $this->bsf->isNullCheck($postData['emdbankname'], 'string');
                    $emdvalidupto = $this->bsf->isNullCheck($postData['emdvalidupto'], 'string');

                    if($emdvalidupto != '' || !is_null($emdvalidupto))
                        $emdvalidupto = date('Y-m-d H:i:s', strtotime($emdvalidupto));

                    if($emddate != '' || !is_null($emddate))
                        $emddate = date('Y-m-d H:i:s', strtotime($emddate));

                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    if ($iSubmitId ==0) {
                        $aVNo = CommonHelper::getVoucherNo(115, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == false)
                            $RefNo = $postData['RefNo'];
                        else
                            $RefNo = $aVNo["voucherNo"];

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderSubmitRegister');
                        $insert->Values(array('RefNo'=>$RefNo, 'RefDate' => date('Y-m-d', strtotime($postData['RefDate'])),'TenderEnquiryId' => $enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $RefNo = $postData['RefNo'];

                        $update = $sql->update();
                        $update->table('Proj_TenderSubmitRegister')
                            ->set(array('RefNo'=>$RefNo, 'RefDate' => date('Y-m-d', strtotime($postData['RefDate']))))
                            ->where(array('TenderSubmitId'=>$iSubmitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // clear closures
                    $delete = $sql->delete();
                    $delete->from('Proj_TenderSubmitEnclosure')
                        ->where("TenderEnquiryId=$enquiryId AND SubmitId=$iSubmitId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $enclosureIds = $postData['selectenc'];
                    foreach($enclosureIds as $id) {
                        $insert = $sql->insert();
                        $insert->into('Proj_TenderSubmitEnclosure');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId, 'SubmitId' => $iSubmitId,'EnclosureId' => $id));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if($emdid == 0) {
                        $insert = $sql->insert();
                        $insert->into('Proj_TenderEMDDetails');
                        $insert->Values(array('TenderEnquiryId' => $enquiryId, 'SubmitId' => $iSubmitId, 'EMDNO' => $emdno, 'EMDDate' => $emddate,
                            'EMDAmount' => $emdcost, 'EMDMode' => $emdmode, 'EMDInFavour' => $emdfavour, 'BankName' => $emdbankname, 'ValidUpto' => $emdvalidupto));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $update = $sql->update();
                        $update->table('Proj_TenderEMDDetails')
                            ->set(array('TenderEnquiryId' => $enquiryId, 'SubmitId' => $iSubmitId, 'EMDNO' => $emdno, 'EMDDate' => $emddate,
                                'EMDAmount' => $emdcost, 'EMDMode' => $emdmode, 'EMDInFavour' => $emdfavour, 'BankName' => $emdbankname, 'ValidUpto' => $emdvalidupto))
                            ->where("EMDId=$emdid");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $enquiryId));
                } catch(PDOException $e){
                    $connection->rollback();
                }
            } else {

                $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
                $iSubmitId=0;

                $select = $sql->select();
                $select->from('Proj_TenderSubmitRegister')
                    ->columns(array('TenderSubmitId'))
                    ->where(array('TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $subReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($subReg)) $iSubmitId = $subReg['TenderSubmitId'];

                if ($iSubmitId !=0) {
                    $select = $sql->select();
                    $select->from('Proj_TenderSubmitRegister')
                        ->columns(array('RefNo','RefDate'))
                        ->where(array('TenderSubmitId'=>$iSubmitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->submitReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Proj_TenderEMDDetails')
                        ->columns(array('EMDId','EMDDate','EMDNo','TenderEnquiryId','SubmitId','EMDMode','EMDInFavour','EMDAmount','ValidUpto'))
                        ->where(array('SubmitId'=>$iSubmitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->emdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }

                $emdRequired =0;
                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderEnquiry'))
                    ->join(array('b' => 'Proj_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=c.TenderEnquiryId', array('TenderNo'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_TenderCostDetail'), 'a.TenderEnquiryId=d.TenderEnquiryId', array('ReqMoneyDeposit','MoneyDepositCost','MoneyDepositMOP','MoneyDepositIFO','MoneyDepositValidUpto'), $select::JOIN_LEFT)
                    ->columns(array('NameOfWork'))
                    ->where(array('a.TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $enquiryReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->enquiryReg = $enquiryReg;
                if (!empty($enquiryReg)) {if (isset($enquiryReg['ReqMoneyDeposit']))  $emdRequired =$this->bsf->isNullCheck($enquiryReg['ReqMoneyDeposit'],'number');}
                $select = $sql->select();
                $select->from(array('a' => 'Proj_TenderCheckListTrans'))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array(), $select::JOIN_LEFT)
                    ->columns(array('CheckListId','ChecklistName'=>new Expression("b.CheckListName"),'UserName'=>new Expression("c.UserName"),'Status'))
                    ->where(array('a.TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arrcheckList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderEnclosureTrans"))
                    ->join(array('b' => 'Proj_TenderEnclosureMaster'), 'a.EnclosureId = b.EnclosureId', array('EnclosureName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_TenderSubmitEnclosure'), new expression("a.EnclosureId = c.EnclosureId and c.TenderEnquiryId='$enquiryId'"), array('Sel'=>new Expression("Case When c.EnclosureId is null then 'No' else 'Yes' end")), $select:: JOIN_LEFT)
                    ->columns(array('EnclosureId'))
                    ->where(array('a.TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->enclosureTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderEMDDetails"))
                    ->where(array('a.TenderEnquiryId'=>$enquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->emddetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>"Proj_PaymentModeMaster"))
                    ->columns(array("TransId", "PaymentMode"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->paymentMode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $aVNo = CommonHelper::getVoucherNo(115, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($iSubmitId == 0) {
                    if ($aVNo["genType"] == false)
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];
                }

                $emdRequired=1;
                $this->_view->submitId = $iSubmitId;
                $this->_view->enquiryId = $enquiryId;
                $this->_view->emdRequired = $emdRequired;

                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                return $this->_view;
            }
        }
    }

    public function emdregisterAction(){
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
            $select->from(array('a' => "Proj_TenderEMDDetails"))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId= b.TenderEnquiryId', array('NameOfWork'), $select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId= c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                ->join(array('d' => 'Proj_TenderDetails'), 'a.TenderEnquiryId = d.TenderEnquiryId', array('TenderNo'), $select:: JOIN_LEFT)
                ->columns(array('EMDId','EMDNo','EMDDate','EMDAmount','ValidUpto'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->emddetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function quantityRevisionAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Quantity Revision");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent('');
                return $response;
            }
        } else {
            $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
            $RegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('RegisterId'), 'number');
            $request = $this->getRequest();
            if ($request->isPost()) {
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    $postData = $request->getPost();
                    $refno = $this->bsf->isNullCheck($postData['refno'], 'string');
                    $refdate = $this->bsf->isNullCheck($postData['refdate'], 'string');

                    if($RegisterId == 0) {
                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuantityRevRegister');
                        $insert->Values(array('RefNo' => $refno, 'RefDate' => date('Y-m-d', strtotime($refdate)), 'TenderEnquiryId' => $enquiryId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $QuantityRevRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $QuantityRevRegisterId = $RegisterId;
                        $update = $sql->update();
                        $update->table('Proj_TenderQuantityRevRegister')
                            ->set(array('RefNo' => $refno, 'RefDate' => date('Y-m-d', strtotime($refdate)), 'TenderEnquiryId' => $enquiryId))
                            ->where(array('QuantityRevRegisterId'=>$RegisterId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // delete trans
                        $subQuery = $sql->select();
                        $subQuery->from("Proj_TenderQuantityRevTrans")
                            ->columns(array("TransId"));
                        $subQuery->where("QuantityRevRegisterId=$QuantityRevRegisterId");

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderWBSQuantityRevTrans')
                            ->where->expression('QuantityRevTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_TenderQuantityRevTrans')
                            ->where("QuantityRevRegisterId=$QuantityRevRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                    for ($i = 1; $i <= $rowid; $i++) {
                        $quotationid = $this->bsf->isNullCheck($postData['transid_' . $i], 'number');
                        $qty = $this->bsf->isNullCheck($postData['revqty_' . $i], 'number');

                        if ($qty == 0)
                            continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_TenderQuantityRevTrans');
                        $insert->Values(array('QuantityRevRegisterId' => $QuantityRevRegisterId, 'QuotationTransId' => $quotationid,
                            'Qty' => $qty));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $QuantityRevTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $wbsrowid = $this->bsf->isNullCheck($postData['wbstable_' . $i . '_rows'], 'number');
                        for ($n = 1; $n <= $wbsrowid; $n++) {
                            $wbsId = $this->bsf->isNullCheck($postData['wbstable_' . $i . '_wbsid_' . $n], 'number');
                            $qty = floatval($this->bsf->isNullCheck($postData['wbstable_' . $i . '_revqty_' . $n], 'number'));

                            if ($qty == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('Proj_TenderWBSQuantityRevTrans');
                            $insert->Values(array('QuantityRevTransId' => $QuantityRevTransId, 'QuotationTransId' => $quotationid,
                                'WBSId' => $wbsId, 'Qty' => $qty));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    if($RegisterId == 0) {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Tender-Quotation-Add','N','',$identity,0,0,'Project',$sVno,$userId,0,0);
                        $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $postData['enquiryId']));
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Tender-Quotation-Edit','E','',$identity,0,0,'Project',$sRefNo,$userId,0,0);
                        $this->redirect()->toRoute('project/tender-quotation', array('controller' => 'followup', 'action' => 'followup', 'enquiryId' => $postData['enquiryId']));
                    }

                } catch(PDOException $e){
                    $connection->rollback();
                }
            } else {
                if($enquiryId!=0) {
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_TenderEnquiry"))
                        ->join(array('b' => 'Proj_ClientMaster'), 'b.ClientId=a.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                        ->columns(array('RefNo', 'RefDate' => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"), 'NameOfWork'))
                        ->where("a.TenderEnquiryId=$enquiryId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tenderDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $select2 = $sql->select();
                    $select2->from( array('a' => 'Proj_TenderQuantityRevTrans' ))
                        ->join(array('b' => 'Proj_TenderQuotationTrans'), 'a.QuotationTransId=b.PrevQuotationTransId', array('QuotationTransId' => "PrevQuotationTransId", 'RefSerialNo', 'Specification', 'Rate','Qty', 'Amount'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array('UnitName' => new Expression("isnull(c.UnitName,'')")), $select:: JOIN_LEFT)
                        ->columns(array('RQty' => 'Qty'))
                        ->where("a.QuantityRevRegisterId=$RegisterId");

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_TenderQuotationTrans'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName' => new Expression("isnull(b.UnitName,'')")), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_TenderQuotationRegister'), 'a.QuotationId=c.QuotationId', array(), $select:: JOIN_LEFT)
                        ->columns(array('RQty' => new Expression("CAST(0 As Decimal(18,3))"),'QuotationTransId' => "PrevQuotationTransId", 'RefSerialNo', 'Specification', 'Rate','Qty', 'Amount'))
                        ->where("c.TenderEnquiryId=$enquiryId")
                        ->combine($select2,'Union ALL');

                    $selectFinal = $sql->select();
                    $selectFinal->from(array('g'=>$select))
                        ->columns(array('QuotationTransId', 'RefSerialNo', 'Specification', 'Rate','Amount','UnitName','Qty' => new Expression('SUM(g.Qty)'),'RQty' => new Expression('SUM(g.RQty)')));
                    $selectFinal->group(new Expression('g.QuotationTransId,g.RefSerialNo,g.Specification,g.Rate,g.Amount,g.UnitName'));
                    $statement = $sql->getSqlStringForSqlObject($selectFinal);
                    $this->_view->rfctrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select2 = $sql->select();
                    $select2->from( array('a' => 'Proj_TenderWBSQuantityRevTrans' ))
                        ->join(array('b' => 'Proj_TenderQuantityRevTrans'), 'a.QuantityRevTransId=b.TransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=a.WBSId', array('WBSName' => new Expression("d.ParentText+ '->' + d.WBSName")), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_TenderQuantityRevRegister'), 'b.QuantityRevRegisterId= c.QuantityRevRegisterId', array(), $select::JOIN_INNER)
                        ->columns( array('QuotationTransId', 'WBSId', 'RevQty' => new Expression("isnull(a.Qty,0)"),'CurQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->where("c.QuantityRevRegisterId=$RegisterId");

                    $select = $sql->select();
                    $select->from( array('a' => 'Proj_TenderWBSTrans' ))
                        ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSName' => new Expression("b.ParentText+ '->' + b.WBSName")), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_TenderWBSQuantityRevTrans'), 'b.WBSId=c.WBSId', array(), $select::JOIN_INNER)
                        ->columns(array('QuotationTransId', 'WBSId','RevQty' => new Expression("CAST(0 As Decimal(18,3))"),'CurQty' => new Expression("isnull(a.Qty,0)")))
                        ->where("a.TenderEnquiryId=$enquiryId");
                    $select->combine($select2,'Union ALL');

                    $selectFinal = $sql->select();
                    $selectFinal->from(array('g'=>$select))
                        ->columns(array('QuotationTransId','WBSId', 'CurQty' => new Expression('SUM(g.CurQty)'), 'RevQty' => new Expression('SUM(g.RevQty)'), 'WBSName'));
                    $selectFinal->group(new Expression('g.QuotationTransId,g.WBSId, g.WBSName'));
                    $statement = $sql->getSqlStringForSqlObject($selectFinal);
                    $this->_view->wbstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                }

                if($RegisterId != 0) {
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_TenderQuantityRevRegister"))
                        ->columns(array('RefNo', 'RefDate' => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")))
                        ->where("a.QuantityRevRegisterId=$RegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->QuantityRevRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }
            }

            $this->_view->enquiryId = $enquiryId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function quotationsortorderAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Quotation Sort Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $statusCode = 200;
                $result = 'No Data.';
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $postData = $request->getPost();
                    $type = $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type) {
                        case 'workgroups':
                            $connection->beginTransaction();
                            $iRowId = $this->bsf->isNullCheck($postData['wgrowid'], 'number');
                            for ($i = 1; $i <= $iRowId; $i++) {
                                $iWGId = $this->bsf->isNullCheck($postData['pworkgroupid_'.$i], 'number');
                                $SortId = $this->bsf->isNullCheck($postData['wsortid_'.$i], 'number');

                                $update = $sql->update();
                                $update->table('Proj_TenderWorkGroup');
                                $update->set(array('SortId' => $SortId));
                                $update->where(array('PWorkGroupId' => $iWGId));

                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $result = 'success';
                            $connection->commit();
                            break;
                        case 'iows':
                            $connection->beginTransaction();
                            $iRowId = $this->bsf->isNullCheck($postData['iowrowid'], 'number');
                            for ($i = 1; $i <= $iRowId; $i++) {
                                $QuotationTransId = $this->bsf->isNullCheck($postData['quotationtransid_'.$i], 'number');
                                $WorkGroupId = $this->bsf->isNullCheck($postData['iowpworkgroupid_'.$i], 'number');
                                $SortId = $this->bsf->isNullCheck($postData['sortid_'.$i], 'number');

                                $update = $sql->update();
                                $update->table('Proj_TenderQuotationTrans');
                                $update->set(array('SortId' => $SortId));
                                $update->where(array('ProjectWorkGroupId' => $WorkGroupId, 'QuotationTransId' => $QuotationTransId));

                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $connection->commit();

                            $result = 'success';
                            break;
                        case 'getiows':
                            $EnquiryId = $this->bsf->isNullCheck($postData['EnquiryId'], 'number');
                            $PWorkGroupId = $this->bsf->isNullCheck($postData['PWorkGroupId'], 'number');
                            $subQuery = $sql->select();
                            $subQuery->from(array('a' => "Proj_TenderQuotationRegister"))
                                ->columns(array('QuotationId'))
                                ->where("a.TenderEnquiryId=$EnquiryId");

                            $select = $sql->select();
                            $select->from(array('a' => "Proj_TenderQuotationTrans"))
                                ->columns(array('QuotationTransId','SerialNo','Specification', 'SortId', 'PWorkGroupId' => 'ProjectWorkGroupId'))
                                ->order("a.SortId")
                                ->where->expression("a.ProjectWorkGroupId=$PWorkGroupId AND a.QuotationId  IN ?", array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $iowlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $result = json_encode($iowlist);
                            break;
                        default:
                            $statusCode = 401;
                            break;
                    }
                } catch(PDOException $ex) {
                    $connection->rollback();
                    $statusCode = 400;
                }

                $response = $this->getResponse()
                    ->setStatusCode($statusCode)
                    ->setContent($result);
                return $response;
            }
        } else {
            $enquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryId'), 'number');
            if($enquiryId!=0) {
                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderQuotationRegister"))
                    ->columns(array('RefNo','TenderEnquiryId'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->quotationlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => "Proj_TenderWorkGroup"))
                    ->columns(array('PWorkGroupId','WorkGroupName','SerialNo', 'SortId'))
                    ->where("a.TenderEnquiryId=$enquiryId")
                    ->order("a.SortId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->wglist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->enquiryId = $enquiryId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
}