<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Workflow\Controller;

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

class IndexController extends AbstractActionController
{
    public function __construct()
    {
        $this->bsf = new \BuildsuperfastClass();
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

    public function newCompanyAction(){
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

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $files = $request->getFiles();
                    $postParams = $request->getPost();
//                    echo"<pre>";
//                    print_r($postParams);
//                    echo"</pre>";
//                    die;


                    $companyId = $this->bsf->isNullCheck($postParams['cId'], 'number');
                    if($companyId==0) {
                        $url = '';
                        $Curl = '';
                        if ($files['logoImage']['name']) {
                            $url = "public/uploads/workflow/companylogo/";
                            $filename = $this->bsf->uploadFile($url, $files['logoImage']);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/workflow/companylogo/' . $filename;
                            }
                        }

//                        if ($files['coverImage']['name']) {
//                            $Curl = "public/uploads/workflow/companycover/";
//                            $Cfilename = $this->bsf->uploadFile($Curl, $files['coverImage']);
//                            if ($Cfilename) {
//                                // update valid files only
//                                $Curl = 'uploads/workflow/companycover/' . $Cfilename;
//                            }
//                        }
                        $branchDetails = json_decode($postParams['branchDetails']);


                        //General And Contact Details
                        $companyName = $this->bsf->isNullCheck($postParams['cName'], 'string');
                        $shortName = $this->bsf->isNullCheck($postParams['sName'], 'string');
                        $businessType = $this->bsf->isNullCheck($postParams['bType'], 'number');
                        $mobileNumber = $this->bsf->isNullCheck($postParams['mNum'], 'number');
                        $companyAddress = $this->bsf->isNullCheck($postParams['cAddress'], 'string');
                        $emailAddress = $this->bsf->isNullCheck($postParams['eAddress'], 'string');
                        $phoneNumber = $this->bsf->isNullCheck($postParams['cPhone'], 'number');
                        $faxNumber = $this->bsf->isNullCheck($postParams['cFax'], 'string');
                        $cWebsite = $this->bsf->isNullCheck($postParams['cWebsite'], 'string');
                        $cContact = $this->bsf->isNullCheck($postParams['cContact'], 'string');
                        $currencyId = $this->bsf->isNullCheck($postParams['cCurrency'], 'number');

                        //Statutory Details
                        $ieNo = $this->bsf->isNullCheck($postParams['ieNo'], 'string');
                        $estNo = $this->bsf->isNullCheck($postParams['estNo'], 'string');
                        $pfNo = $this->bsf->isNullCheck($postParams['pfNo'], 'string');
                        $incomeTax = $this->bsf->isNullCheck($postParams['incomeTax'], 'number');
                        $panNo = $this->bsf->isNullCheck($postParams['panNo'], 'string');
                        $tanNo = $this->bsf->isNullCheck($postParams['tanNo'], 'string');
                        $tinNo = $this->bsf->isNullCheck($postParams['tinNo'], 'string');
                        $cinNo = $this->bsf->isNullCheck($postParams['cinNo'], 'string');
                        $stNo = $this->bsf->isNullCheck($postParams['stNo'], 'string');
                        $cstNo = $this->bsf->isNullCheck($postParams['cstNo'], 'string');
                        $esiNo = $this->bsf->isNullCheck($postParams['esiNo'], 'string');

                        $insert = $sql->insert('WF_CompanyMaster');
                        $newData = array(
                            //Write your Ajax post code here
                            'CompanyName' => $companyName,
                            'ShortName' => $shortName,
                            'Mobile' => $mobileNumber,
//                            'CoverPhoto' => $Curl,
                            'Address' => $companyAddress,
                            'LogoPath' => $url,
                            'Email' => $emailAddress,
                            'Phone' => $phoneNumber,
                            'Fax' => $faxNumber,
                            'Website' => $cWebsite,
                            'CurrencyId' => $currencyId,
                            'ContactPerson' => $cContact,
                            'CreatedDate' => date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $companyId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $insert = $sql->insert('Wf_BusinessTypeTrans');
                        $newData = array(
                            'BusinessTypeId' => $businessType,
                            'CompanyId' => $companyId
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert('WF_Statutory');
                        $newData = array(
                            //Write your Ajax post code here
                            'CompanyId' => $companyId,
                            'IENo' => $ieNo,
                            'EstablishmentNo' => $estNo,
                            'PFNo' => $pfNo,
                            'IncomeTax' => $incomeTax,
                            'PanNo' => $panNo,
                            'TanNo' => $tanNo,
                            'TinNo' => $tinNo,
                            'CinNo' => $cinNo,
                            'CSTNo' => $cstNo,
                            'ESINo' => $esiNo,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'STNo' => $stNo
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($branchDetails as $el) {
                            $el_array = explode('|', $el);
                            $branchName = $this->bsf->isNullCheck($el_array[0], 'string');
                            $branchAddress = $this->bsf->isNullCheck($el_array[1], 'string');
                            $branchPhone = $this->bsf->isNullCheck($el_array[2], 'string');
                            $branchEmail = $this->bsf->isNullCheck($el_array[3], 'string');
                            $ContactPerson = $this->bsf->isNullCheck($el_array[4], 'string');
                            $branchFax = $this->bsf->isNullCheck($el_array[5], 'string');
                            $branchStNo = $this->bsf->isNullCheck($el_array[6], 'string');
                            $branchCstNo = $this->bsf->isNullCheck($el_array[7], 'string');

                            $insert = $sql->insert('WF_CompanyBranch');
                            $newData = array(
                                //Write your Ajax post code here
                                'CompanyId' => $companyId,
                                'BranchName' => $branchName,
                                'Address' => $branchAddress,
                                'Phone' => $branchPhone,
                                'EmailAddress' => $branchEmail,
                                'ContactPerson' => $ContactPerson,
                                'Fax' => $branchFax,
                                'StNo' => $branchStNo,
                                'CstNo' => $branchCstNo
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        $url = $postParams['logoHide'];
//                        $Curl = $postParams['coverHide'];

                        if ($files['logoImage']['name']!="undefined" && $files['logoImage']['name']!="") {
                            $url = "public/uploads/workflow/companylogo/";
                            $filename = $this->bsf->uploadFile($url, $files['logoImage']);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/workflow/companylogo/' . $filename;
                            }
                        }

//                        if ($files['coverImage']['name']!="undefined" && $files['coverImage']['name']!="") {
//                            $Curl = "public/uploads/workflow/companycover/";
//                            $Cfilename = $this->bsf->uploadFile($Curl, $files['coverImage']);
//                            if ($Cfilename) {
//                                // update valid files only
//                                $Curl = 'uploads/workflow/companycover/' . $Cfilename;
//                            }
//                        }
                        $branchDetails = json_decode($postParams['branchDetails']);

                        //General And Contact Details
                        $companyName = $this->bsf->isNullCheck($postParams['cName'], 'string');
                        $shortName = $this->bsf->isNullCheck($postParams['sName'], 'string');
                        $businessType = $this->bsf->isNullCheck($postParams['bType'], 'number');
                        $mobileNumber = $this->bsf->isNullCheck($postParams['mNum'], 'number');
                        $companyAddress = $this->bsf->isNullCheck($postParams['cAddress'], 'string');
                        $emailAddress = $this->bsf->isNullCheck($postParams['eAddress'], 'string');
                        $phoneNumber = $this->bsf->isNullCheck($postParams['cPhone'], 'number');
                        $faxNumber = $this->bsf->isNullCheck($postParams['cFax'], 'string');
                        $cWebsite = $this->bsf->isNullCheck($postParams['cWebsite'], 'string');
                        $cContact = $this->bsf->isNullCheck($postParams['cContact'], 'string');
                        $currencyId = $this->bsf->isNullCheck($postParams['cCurrency'], 'number');

                        //Statutory Details
                        $ieNo = $this->bsf->isNullCheck($postParams['ieNo'], 'string');
                        $estNo = $this->bsf->isNullCheck($postParams['estNo'], 'string');
                        $pfNo = $this->bsf->isNullCheck($postParams['pfNo'], 'string');
                        $incomeTax = $this->bsf->isNullCheck($postParams['incomeTax'], 'number');
                        $panNo = $this->bsf->isNullCheck($postParams['panNo'], 'string');
                        $tanNo = $this->bsf->isNullCheck($postParams['tanNo'], 'string');
                        $tinNo = $this->bsf->isNullCheck($postParams['tinNo'], 'string');
                        $cinNo = $this->bsf->isNullCheck($postParams['cinNo'], 'string');
                        $stNo = $this->bsf->isNullCheck($postParams['stNo'], 'string');
                        $cstNo = $this->bsf->isNullCheck($postParams['cstNo'], 'string');
                        $esiNo = $this->bsf->isNullCheck($postParams['esiNo'], 'string');

                        $update = $sql->update();
                        $update->table('WF_CompanyMaster');
                        $update->set(array('CompanyName' => $companyName,
                            'ShortName' => $shortName,
                            'Mobile' => $mobileNumber,
//                            'CoverPhoto' => $Curl,
                            'Address' => $companyAddress,
                            'LogoPath' => $url,
                            'Email' => $emailAddress,
                            'Phone' => $phoneNumber,
                            'Fax' => $faxNumber,
                            'Website' => $cWebsite,
                            'CurrencyId' => $currencyId,
                            'ContactPerson' => $cContact,
                            'CreatedDate' => date('Y-m-d H:i:s')));
                        $update->where("CompanyId=$companyId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Wf_BusinessTypeTrans');
                        $update->set(array('BusinessTypeId' => $businessType ));
                        $update->where("CompanyId=$companyId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('WF_Statutory');
                        $update->set(array('IENo' => $ieNo,
                            'EstablishmentNo' => $estNo,
                            'PFNo' => $pfNo,
                            'IncomeTax' => $incomeTax,
                            'PanNo' => $panNo,
                            'TanNo' => $tanNo,
                            'TinNo' => $tinNo,
                            'CinNo' => $cinNo,
                            'CSTNo' => $cstNo,
                            'ESINo' => $esiNo,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'STNo' => $stNo));
                        $update->where("CompanyId=$companyId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($branchDetails as $el) {
                            $el_array = explode('|', $el);
                            $branchName = $this->bsf->isNullCheck($el_array[0], 'string');
                            $branchAddress = $this->bsf->isNullCheck($el_array[1], 'string');
                            $branchPhone = $this->bsf->isNullCheck($el_array[2], 'string');
                            $branchEmail = $this->bsf->isNullCheck($el_array[3], 'string');
                            $ContactPerson = $this->bsf->isNullCheck($el_array[4], 'string');
                            $branchFax = $this->bsf->isNullCheck($el_array[5], 'string');
                            $branchStNo = $this->bsf->isNullCheck($el_array[6], 'string');
                            $branchCstNo = $this->bsf->isNullCheck($el_array[7], 'string');
                            $branchId = $this->bsf->isNullCheck($el_array[8], 'number');
                            $updateFlag = $this->bsf->isNullCheck($el_array[9], 'number');

                            if($branchId==0) {
                                $insert = $sql->insert('WF_CompanyBranch');
                                $newData = array(
                                    //Write your Ajax post code here
                                    'CompanyId' => $companyId,
                                    'BranchName' => $branchName,
                                    'Address' => $branchAddress,
                                    'Phone' => $branchPhone,
                                    'EmailAddress' => $branchEmail,
                                    'ContactPerson' => $ContactPerson,
                                    'Fax' => $branchFax,
                                    'StNo' => $branchStNo,
                                    'CstNo' => $branchCstNo
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if($updateFlag==1) {
                                    $update = $sql->update();
                                    $update->table('WF_CompanyBranch');
                                    $update->set(array('CompanyId' => $companyId,
                                        'BranchName' => $branchName,
                                        'Address' => $branchAddress,
                                        'Phone' => $branchPhone,
                                        'EmailAddress' => $branchEmail,
                                        'ContactPerson' => $ContactPerson,
                                        'Fax' => $branchFax,
                                        'StNo' => $branchStNo,
                                        'CstNo' => $branchCstNo));
                                    $update->where("BranchId=$branchId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setStatusCode(200);
                    return $response;
                } catch(PDOException $e) {
                    $connection->rollback();
                    $response = $this->getResponse()->setStatusCode(400);
                    return $response;
                }

            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
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
            } else {
                $companyId = $this->bsf->isNullCheck($this->params()->fromRoute('companyId'), 'number');
                if($companyId!=0) {
                    $select = $sql->select();
                    $select->from('WF_CompanyMaster')
                        ->columns(array('*'))
                        ->where(array('CompanyId' => $companyId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultCompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('WF_Statutory')
                        ->columns(array('*'))
                        ->where(array('CompanyId' => $companyId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultStatutory = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('WF_CompanyBranch')
                        ->columns(array('*'))
                        ->where(array('CompanyId' => $companyId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultBranches = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->branchCount = count($this->_view->resultBranches);

                    $select = $sql->select();
                    $select->from("WF_BusinessTypeTrans")
                        ->columns(array('BusinessTypeId'))
                        ->where(array('CompanyId' => $companyId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->bussinessTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }

                $comSelect = $sql->select();
                $comSelect->from('WF_CompanyMaster')
                    ->columns(array('CompanyId', 'CompanyName'));
                $comStatement = $sql->getSqlStringForSqlObject($comSelect);
                $this->_view->arr_company = $dbAdapter->query($comStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_CompanyMaster"))
                    ->columns(array('CompanyName'))
                    ->where(array("CompanyId" => $companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->CompanyName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->companydtl = $companyId;

                $select = $sql->select();
                $select->from('WF_BusinessTypeMaster')
                    ->columns(array('BusinessTypeId', 'BusinessTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->businessTypeDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_CurrencyMaster')
                    ->columns(array('data' => 'CurrencyId', 'value' => new Expression("CurrencyShort + ' - ' + CurrencyName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->currencyDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->companyId = $companyId;
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }


    public function costCenterAction(){
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

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $files = $request->getFiles();
                    $postParams = $request->getPost();
                    $centerId = $this->bsf->isNullCheck($postParams['centerId'], 'number');
                    if($centerId==0) {
                        $url = '';
//                        $Curl = '';
                        if ($files['logoImage']['name']) {
                            $url = "public/uploads/workflow/costcenterlogo/";
                            $filename = $this->bsf->uploadFile($url, $files['logoImage']);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/workflow/costcenterlogo/' . $filename;
                            }
                        }

//                        if ($files['coverImage']['name']) {
//                            $Curl = "public/uploads/workflow/costcentercover/";
//                            $Cfilename = $this->bsf->uploadFile($Curl, $files['coverImage']);
//                            if ($Cfilename) {
//                                // update valid files only
//                                $Curl = 'uploads/workflow/costcentercover/' . $Cfilename;
//                            }
//                        }

                        //General And Contact Details
                        $centerName = $this->bsf->isNullCheck($postParams['centerName'], 'string');
                        $companyId = $this->bsf->isNullCheck($postParams['companyId'], 'number');
                        $mobileNumber = $this->bsf->isNullCheck($postParams['mNum'], 'number');
                        $companyAddress = $this->bsf->isNullCheck($postParams['cAddress'], 'string');
                        $emailAddress = $this->bsf->isNullCheck($postParams['eAddress'], 'string');
                        $phoneNumber = $this->bsf->isNullCheck($postParams['cPhone'], 'number');
                        $faxNumber = $this->bsf->isNullCheck($postParams['cFax'], 'string');
                        $cWebsite = $this->bsf->isNullCheck($postParams['cWebsite'], 'string');
                        $cContact = $this->bsf->isNullCheck($postParams['cContact'], 'string');
                        $branchId = $this->bsf->isNullCheck($postParams['branchId'], 'number');

                        $insert = $sql->insert('WF_CostCentre');
                        $newData = array(
                            //Write your Ajax post code here
                            'CostCentreName' => $centerName,
                            'CompanyId' => $companyId,
                            'Mobile' => $mobileNumber,
//                            'CostCentreCover' => $Curl,
                            'Address' => $companyAddress,
                            'CostCentreLogo' => $url,
                            'Email' => $emailAddress,
                            'Phone' => $phoneNumber,
                            'Fax' => $faxNumber,
                            'Website' => $cWebsite,
                            'BranchId' => $branchId,
                            'ContactPerson' => $cContact
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $url = $postParams['logoHide'];
//                        $Curl = $postParams['coverHide'];

                        if ($files['logoImage']['name']!="undefined" && $files['logoImage']['name']!="") {
                            $url = "public/uploads/workflow/costcenterlogo/";
                            $filename = $this->bsf->uploadFile($url, $files['logoImage']);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/workflow/costcenterlogo/' . $filename;
                            }
                        }

//                        if ($files['coverImage']['name']!="undefined" && $files['coverImage']['name']!="") {
//                            $Curl = "public/uploads/workflow/costcentercover/";
//                            $Cfilename = $this->bsf->uploadFile($Curl, $files['coverImage']);
//                            if ($Cfilename) {
//                                // update valid files only
//                                $Curl = 'uploads/workflow/costcentercover/' . $Cfilename;
//                            }
//                        }

                        //General And Contact Details
                        $centerName = $this->bsf->isNullCheck($postParams['centerName'], 'string');
                        $companyId = $this->bsf->isNullCheck($postParams['companyId'], 'number');
                        $mobileNumber = $this->bsf->isNullCheck($postParams['mNum'], 'number');
                        $companyAddress = $this->bsf->isNullCheck($postParams['cAddress'], 'string');
                        $emailAddress = $this->bsf->isNullCheck($postParams['eAddress'], 'string');
                        $phoneNumber = $this->bsf->isNullCheck($postParams['cPhone'], 'number');
                        $faxNumber = $this->bsf->isNullCheck($postParams['cFax'], 'string');
                        $cWebsite = $this->bsf->isNullCheck($postParams['cWebsite'], 'string');
                        $cContact = $this->bsf->isNullCheck($postParams['cContact'], 'string');
                        $branchId = $this->bsf->isNullCheck($postParams['branchId'], 'number');

                        $update = $sql->update();
                        $update->table('WF_CostCentre');
                        $update->set(array('CostCentreName' => $centerName,
                            'CompanyId' => $companyId,
                            'Mobile' => $mobileNumber,
//                            'CostCentreCover' => $Curl,
                            'Address' => $companyAddress,
                            'CostCentreLogo' => $url,
                            'Email' => $emailAddress,
                            'Phone' => $phoneNumber,
                            'Fax' => $faxNumber,
                            'Website' => $cWebsite,
                            'BranchId' => $branchId,
                            'ContactPerson' => $cContact));
                        $update->where("CostCentreId=$centerId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setStatusCode(200);
                    return $response;
                } catch(PDOException $e) {
                    $connection->rollback();
                    $response = $this->getResponse()->setStatusCode(400);
                    return $response;
                }
            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
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
            } else {
                $centerId = $this->bsf->isNullCheck($this->params()->fromRoute('CostCentreId'), 'number');
                if($centerId!=0) {
                    $select = $sql->select();
                    $select->from('WF_CostCentre')
                        ->columns(array('*'))
                        ->where(array('CostCentreId' => $centerId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resultCostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->resultCostCenter = $resultCostCenter;

                    $select = $sql->select();
                    $select->from('WF_CompanyBranch')
                        ->columns(array('BranchId', 'BranchName'))
                        ->where(array('CompanyId' => $resultCostCenter['CompanyId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->branchdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from('WF_OperationalCostCentre')
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where(array('FACostCentreId' => $centerId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $select = $sql->select();
                $select->from( 'WF_BusinessTypeMaster' )
                    ->columns(array("BusinessTypeId", "BusinessTypeName"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->businessTypeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from('Proj_ProjectTypeMaster')
                    ->columns(array('ProjectTypeId', 'ProjectTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $comSelect = $sql->select();
                $comSelect->from('WF_CostCentre')
                    ->columns(array('CostCentreId', 'CostCentreName'));
                $comStatement = $sql->getSqlStringForSqlObject($comSelect);
                $this->_view->arr_costcenter = $dbAdapter->query($comStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_CostCentre"))
                    ->columns(array('CostCentreName'))
                    ->where(array("CostCentreId" => $centerId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->CenterName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->centerId =$centerId;

                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array('CompanyId', 'CompanyName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->companyDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function companyBranchAction() {
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();
                    $companyId = $this->bsf->isNullCheck($postParams['CompanyId'], 'number');
                    $select = $sql->select();
                    $select->from('WF_CompanyBranch')
                        ->columns(array('BranchId', 'BranchName'))
                        ->where(array('CompanyId' => $companyId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $branchName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setStatusCode(200)->setContent(json_encode($branchName));
                    return $response;
                } catch (PDOException $e) {
                    $connection->rollback();
                }

            }
        }
    }
    public function getOPcostcentredetailsAction() {
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $postParams = $request->getPost();
                    $ccId = $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');
                    $select = $sql->select();
                    $select->from('WF_OperationalCostCentre')
                        ->where(array('CostCentreId' => $ccId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $branchName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setStatusCode(200)->setContent(json_encode($branchName));
                    return $response;
                } catch (PDOException $e) {

                }

            }
        }
    }
    public function costcenterGridAction(){
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
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                if($searchStr!="") {
                    $select = $sql->select();
                    $select->from('WF_CostCentre')
                        ->columns(array("*"));
                    $select->where("CostCentreName LIKE '%" . $searchStr . "%' OR Email LIKE '%" . $searchStr . "%' OR Mobile LIKE '%" . $searchStr . "%' OR Phone LIKE '%" . $searchStr . "%' OR ContactPerson LIKE '%" . $searchStr . "%' OR Website LIKE '%" . $searchStr . "%'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costCenterSearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $select = $sql->select();
                    $select->from('WF_CostCentre')
                        ->columns(array("*"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costCenterSearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $costCentreList ="";
                $costCentreList .= '<ul class="col-lg-12 company_grid animated fadeIn">';
                foreach($costCenterSearch as $i):
                    $costCentreLogo="";
                    if(isset($i['CostCentreLogo']) && trim($i['CostCentreLogo'])!='') {
                        $costCentreLogo = $viewRenderer->basePath().'/'.$i['CostCentreLogo'];
                    } else {
                        $costCentreLogo = $viewRenderer->basePath().'/images/avatar.jpg';
                    }
                    $costCentreCover="";
                    if(isset($i['CostCentreCover']) && trim($i['CostCentreCover'])!='') {
                        $costCentreCover = $viewRenderer->basePath().'/'.$i['CostCentreCover'];
                    } else {
                        $costCentreCover = $viewRenderer->basePath().'/images/companyview_cover.jpg';
                    }
                    $costCentreMap = $viewRenderer->basePath().'/images/company-map1.jpg';
                    $costCentreList .= '<li class="col-lg-3 col-md-6 col-sm-6 padlr0">
                            <div class="compgrid_image" style="background: url('.$costCentreCover.');">
                                <span class="comp_arrowlink"><a href="costcenter-view-view/'.$i['CostCentreId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="right" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                                <div class="compgrid_logo brad_50 m_auto">
                                    <span><img class="brad_50" src="'.$costCentreLogo.'" /></span>
                                </div>
                                <h3 class="compgrid_title">'.$i['CostCentreName'].'</h3>
                            </div>
                            <div class="comp_editlink brad_50"><a href="cost-center/'.$i['CostCentreId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="bottom" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                            <div class="compgrid_content">
                                <div class="col-lg-6 col-sm-6 col-xs-6 padlr0">
                                    <p class="comp_address">'.$i['Address'].',<br/>

                                    </p>
                                </div>
                                <div class="col-lg-6 col-sm-6 col-xs-6 padlr0">
                                    <div class="comp_map" style="background-image:url('.$costCentreMap.');">
                                    </div>
                                </div>
                                <p class="clear"><span><i class="fa fa-user"></i></span>'.$i['ContactPerson'].'<span class="vendor_phone"><i class="fa fa-phone"></i>'.$i['Mobile'].'</span></p>
                                <a href="#" class="vwstrtr_btn ripple">View Structure</a>
                            </div>
                        </li>';
                endforeach;
                $costCentreList .= '</ul>';
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setStatusCode(200)->setContent($costCentreList);
                return $response;
            }
        } else {
            if($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from('WF_CostCentre')
                    ->columns(array("*"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function costcenterGridlistAction(){
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
        $request = $this->getRequest();

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                if($searchStr!="") {
                    $select = $sql->select();
                    $select->from('WF_CostCentre')
                        ->columns(array("*"));
                    $select->where("CostCentreName LIKE '%" . $searchStr . "%' OR Email LIKE '%" . $searchStr . "%' OR Mobile LIKE '%" . $searchStr . "%' OR Phone LIKE '%" . $searchStr . "%' OR ContactPerson LIKE '%" . $searchStr . "%' OR Website LIKE '%" . $searchStr . "%'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costCenterSearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $select = $sql->select();
                    $select->from('WF_CostCentre')
                        ->columns(array("*"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $costCenterSearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $costCentreList ="";
                foreach($costCenterSearch as $i):
                    $costCentreLogo="";
                    if(isset($i['CostCentreLogo']) && trim($i['CostCentreLogo'])!='') {
                        $costCentreLogo = $viewRenderer->basePath().'/'.$i['CostCentreLogo'];
                    } else {
                        $costCentreLogo = $viewRenderer->basePath().'/images/avatar.jpg';
                    }
                    $costCentreMap = $viewRenderer->basePath().'/images/company-map1.jpg';
                    $costCentreList .= '<div class="col-lg-12 col-md-6 col-sm-6  bids_list compgdlist brad_3 padlr0">
                        <span class="comp_arrowlink"><a href="costcenter-view/'.$i['CostCentreId'].'" class="brad_50" data-toggle="tooltip" data-placement="right" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                        <div class="comp_editlink brad_50"><a href="cost-center/'.$i['CostCentreId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="left" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                        <div class="col-lg-7 padlr0">
                            <div class="col-lg-9">
                                <div class="compgrid_logo brad_50 float_l">
                                    <span><img class="brad_50" src="'.$costCentreLogo.'" /></span>
                                </div>
                                <h1>'.$i['CostCentreName'].'<br>
                                    <span class="m_top10"><span><i class="fa fa-user"></i></span>'.$i['ContactPerson'].'<span class="vendor_phone"><i class="fa fa-phone"></i>'.$i['Mobile'].'</span></span>
                                </h1>
                            </div>
                            <div class="col-lg-3 padlr0">
                                <div class="comp_map" style="background-image:url('.$costCentreMap.')">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 bidvendor_detail compgdlist_detail">
                            <p><span class="p_label"><span class="mapaddress_icon"><i class="fa fa-map-marker"></i></span>  Address :</span>'.$i['Address'].'</p>
                            <a href="#" class="vwstrtr_btn m_top0 ripple">View Structure</a>
                        </div>
                    </div>';
                endforeach;
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setStatusCode(200)->setContent($costCentreList);
                return $response;
            }
        } else {
            if($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from('WF_CostCentre')
                    ->columns(array("*"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

        return $this->_view;
    }

    public function costcenterViewAction(){
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

            } else {
                $costCentreId = $this->params()->fromRoute('CostCentreId');

                $select = $sql->select();
                $select->from('WF_CostCentre')
                    ->columns(array('*'))
                    ->where(array('CostCentreId'=>$costCentreId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultCostCenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $companyId=$resultCostCenter['CompanyId'];
                $branchId=$resultCostCenter['BranchId'];
                $this->_view->resultCostCenter=$resultCostCenter;

                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array('CompanyName'))
                    ->where(array('CompanyId'=>$companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultCompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('WF_CompanyBranch')
                    ->columns(array('BranchName'))
                    ->where(array('BranchId'=>$branchId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultBranch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->companyId = $companyId;
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

    public function uploadAction(){
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                if($postParams['mode'] == 'upload'){
                    $files = $request->getFiles();


                    if($files['logo']['name']){
                        $dir = 'uploads/workflow/userlogo/1/';
                        if(!is_dir($dir)){
                            mkdir($dir, 0755, true);
                            $ext = pathinfo($files['logo']['name'], PATHINFO_EXTENSION);
                            Print_r($ext);die;
                            move_uploaded_file($files['logo']['tmp_name'], $dir);
                        }
                    }
                }
            }
        }
        $this->_view->setTerminal(true);
    }
    public function companyViewAction(){
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

            } else {
                $companyId = $this->params()->fromRoute('companyId');

                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array('*'))
                    ->where(array('CompanyId'=>$companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultCompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $currencyId=$resultCompany['CurrencyId'];
                $this->_view->resultCompany=$resultCompany;

                $select = $sql->select();
                $select->from('WF_CurrencyMaster')
                    ->columns(array('data' => 'CurrencyId', 'value' => new Expression("CurrencyShort + ' - ' + CurrencyName")))
                    ->where(array('CurrencyId'=>$currencyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->currencyView = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('WF_Statutory')
                    ->columns(array('*'))
                    ->where(array('CompanyId'=>$companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultStatutory = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('WF_CompanyBranch')
                    ->columns(array('*'))
                    ->where(array('CompanyId'=>$companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultBranches = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_BusinessTypeTrans"))
                    ->join(array("b" => "WF_BusinessTypeMaster"), "a.BusinessTypeId=b.BusinessTypeId", array('BusinessTypeName'), $select::JOIN_INNER)
                    ->columns(array('BusinessTypeId','TransId'))
                    ->where(array('CompanyId'=>$companyId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->bussinessTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    public function usersAction(){
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
        $powerUser = $this->auth->getIdentity()->PowerUser;
        $curUser = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $mode=$this->bsf->isNullCheck($postParams['mode'],'string');
                try {
                    if ($mode == 'search') {
                        $searchStr = $this->bsf->isNullCheck($postParams['searchStr'], 'string');

                        $select = $sql->select();
                        $select->from(array("a"=>'WF_Users'))
                            ->join(array("b"=>"WF_PositionMaster"), "a.PositionId=b.PositionId", array("PositionName"), $select::JOIN_LEFT)
                            ->join(array("c"=>"WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name"), $select::JOIN_LEFT)
                            ->join(array("d"=>"WF_LevelMaster"), "a.LevelId=d.LevelId", array("LevelName"), $select::JOIN_LEFT)
                            ->columns(array('*'));
                        if($searchStr!="") {
                            $select->where("b.PositionName LIKE '%" . $searchStr . "%' OR c.Dept_Name LIKE '%" . $searchStr . "%' OR d.LevelName LIKE '%" . $searchStr . "%' OR a.EmployeeName LIKE '%" . $searchStr . "%' OR a.UserName LIKE '%" . $searchStr . "%' OR a.Mobile LIKE '%" . $searchStr . "%' OR a.Email LIKE '%" . $searchStr . "%' OR a.Phone LIKE '%" . $searchStr . "%'");
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $userFilter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $userList = "";
                        $userList .= '<ul class="col-lg-12 users_grid animated fadeIn">';

                        foreach ($userFilter as $i):
                            if($curUser==1) {
                                $editSymbol="display:block;";
                                $active = 'onclick="activate('.$i['UserId'].','.$i['Lock'].');"';
                            } else {
                                $editSymbol="display:none;";
                                $active = '';
                            }
                            $userLogo = "";
                            if (isset($i['UserLogo']) && trim($i['UserLogo']) != '') {
                                $userLogo = $viewRenderer->basePath() . '/' . $i['UserLogo'];
                            } else {
                                $userLogo = $viewRenderer->basePath() . '/images/avatar.jpg';
                            }
                            $lockType = "";
                            if ($i['Lock'] == 0) {
                                $lockType = 'Active';
                                $unlockSymbol="display:none;";
                                $lockSymbol="display:block;";
                            } else {
                                $lockType = 'De-active';
                                $unlockSymbol="display:block;";
                                $lockSymbol="display:none;";
                            }

                            $userList .= '<li class="col-lg-4 col-md-6 col-sm-6 padlr0">
                        <div class="col-lg-8 col-md-8 col-sm-8 col-xs-8">
                            <h3>' . $i['EmployeeName'] . '</h3>
                            <p>' . $i['PositionName'] . '</p>
                            <p class="user_email"><i class="fa fa-envelope" aria-hidden="true"></i> '.$i['Email'].'</p>
                            <p class="user_phone"><span><i class="fa fa-phone-square" aria-hidden="true"></i> '.$i['Mobile'].'</span></p>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4">
                            <span class="comp_arrowlink"><a href="user-view/' . $i['UserId'] . '" class="brad_50" data-toggle="tooltip" data-placement="left" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                            <div style="'.$editSymbol.'" class="user_editlink comp_editlink brad_50"><a href="user-entry/' . $i['UserId'] . '" class="ripple brad_50" data-toggle="tooltip" data-placement="top" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                            <div class="usersgrid_logo compgrid_logo brad_50 m_auto">
                                <span><img class="brad_50" src="' . $userLogo . '" /></span>
                            </div>
                        </div>
                        <ul>
                            <li>' . $i['Dept_Name'] . '</li>
                            <li>' . $i['LevelName'] . '</li>
                            <li>' . $i['UserName'] . '</li>
                        </ul>
                        <a id="lock_type_'.$i['UserId'].'" href="javascript:void(0);" '.$active.' class="act_deactivate_icon ripple" data-toggle="tooltip" data-placement="left" data-original-title="'.$lockType.'">
                            	<span id="unlock_symbol_'.$i['UserId'].'" style="'.$unlockSymbol.'" class="act_span_icon"><i class="fa fa-check-square-o"></i></span>
                            	<span id="lock_symbol_'.$i['UserId'].'" style="'.$lockSymbol.'" class="deact_span_icon"><i class="fa fa-check-square"></i></span>
                        </a>
                    </li>';
                        endforeach;
                        $userList .= '</ul>';
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setStatusCode(200)->setContent($userList);
                        return $response;
                    }  else {
                        $userId = $this->bsf->isNullCheck($postParams['userId'], 'string');
                        $type = $this->bsf->isNullCheck($postParams['type'], 'number');
                        if ($type == 1) {
                            $type = 0;
                        } else {
                            $type = 1;
                        }
                        $update = $sql->update();
                        $update->table('WF_Users');
                        $update->set(array(
                            'Lock' => $type
                        ));
                        $update->where(array('UserId' => $userId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setStatusCode(200)->setContent($type);
                        return $response;

                    }
                } catch(PDOException $e) {
                    $response = $this->getResponse()->setStatusCode(400);
                    return $response;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Normal form post code here
            } else {
                $select = $sql->select();
                $select->from(array("a"=>'WF_Users'))
                    ->join(array("b"=>"WF_PositionMaster"), "a.PositionId=b.PositionId", array("PositionName"), $select::JOIN_LEFT)
                    ->join(array("c"=>"WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name"), $select::JOIN_LEFT)
                    ->join(array("d"=>"WF_LevelMaster"), "a.LevelId=d.LevelId", array("LevelName"), $select::JOIN_LEFT)
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->powerUser=$powerUser;
                $this->_view->curUser=$curUser;

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function userEntryAction(){
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

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $files = $request->getFiles();
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {


                    $iUserId = $this->bsf->isNullCheck($postParams['userId'], 'number');
                    $userId = $this->bsf->isNullCheck($postParams['userId'], 'number');
                    $userName = $this->bsf->isNullCheck($postParams['userName'], 'string');
                    $empName = $this->bsf->isNullCheck($postParams['empname'], 'string');
                    $userDob = $this->bsf->isNullCheck($postParams['user_dob'], 'date');
                    $userDob = date('Y-m-d', strtotime($userDob));
                    $powerUser = $this->bsf->isNullCheck($postParams['poweruser'], 'number');
                    $departmentId = $this->bsf->isNullCheck($postParams['department'], 'number');
                    $positionId = $this->bsf->isNullCheck($postParams['position'], 'number');
                    $levelId = $this->bsf->isNullCheck($postParams['level'], 'number');
                    $phoneNum = $this->bsf->isNullCheck($postParams['phone'], 'string');
                    $mobileNum = $this->bsf->isNullCheck($postParams['mobile'], 'string');
                    $userAddress = $this->bsf->isNullCheck($postParams['address'], 'string');
                    $emailAddress = $this->bsf->isNullCheck($postParams['email'], 'string');

//                    $defaultCCId = $this->bsf->isNullCheck($postParams['CostCentre_default'], 'number');
//                        $companyId = $this->bsf->isNullCheck($postParams['Company_name'], 'number');
//                        $cId = $this->bsf->isNullCheck($postParams['cid'], 'string');
//                        $cId1 = $this->bsf->isNullCheck($postParams['cid1'], 'string');
//                        $cId2 = $this->bsf->isNullCheck($postParams['cid2'], 'string');
//                        $powerUser = $this->bsf->isNullCheck($postParams['power_user'], 'number');
                    //$costCentreIds = $postParams['CostCentre_name'];
//                        $teamIds = $postParams['team'];
                    //$checkSupId=$this->bsf->isNullCheck($postParams['superior'], 'number');

                    $url = '';
                    $Curl = '';
                    if ($files['logo']['name']) {
                        $url = "public/uploads/workflow/userlogo/";
                        $filename = $this->bsf->uploadFile($url, $files['logo']);
                        if ($filename) {
                            // update valid files only
                            $url = 'uploads/workflow/userlogo/' . $filename;
                        }
                    }

                    // for reverse costcentre select

                    if($postParams['uMode'] == 'username'){
                        $select = $sql->select();
                        $select->from(array('a' => 'WF_Users'))
                            ->columns(array('UserName'))
                            ->where(array('a.UserName'=> $postParams['UName']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    else if($postParams['uMode'] == 'mblnumcheck'){
                        $select = $sql->select();
                        $select->from(array('a' => 'WF_Users'))
                            ->columns(array('Phone'))
                            ->where(array('a.Phone'=> $postParams['MblNum']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    else {
                        if ($iUserId == 0) {

//                            if ($files['cover']['name']) {
//                                $Curl = "public/uploads/workflow/usercover/";
//                                $Cfilename = $this->bsf->uploadFile($Curl, $files['cover']);
//                                if ($Cfilename) {
//                                    // update valid files only
//                                    $Curl = 'uploads/workflow/usercover/' . $Cfilename;
//                                }
//                            }

                            $insert = $sql->insert('WF_Users');
                            $newData = array(
                                'UserName' => $userName,
                                'EmployeeName' => $empName,
                                'DeptId' => $departmentId,
                                'PositionId' => $positionId,
                                'LevelId' => $levelId,

                                'ModifiedDate' => date('Y-m-d H:i:s'),
                                'CreatedDate' => date('Y-m-d H:i:s'),
                                'UserLogo' => $url,
//                                    'UserCover' => $Curl,
                                'Phone' => $phoneNum,
                                'Mobile' => $mobileNum,
                                'Address' => $userAddress,
                                'Email' => $emailAddress,
//                                    'DefaultCCId' => $defaultCCId,
//                                    'CompanyId' => $companyId,
                                'UserDob' => $userDob,
                                'PowerUser' => $powerUser
                            );
                            $insert->values($newData);
                            echo $statement = $sql->getSqlStringForSqlObject($insert);die;
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $userId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        } else {
                            $update = $sql->update();
                            $update->table('WF_Users')
                                ->set(array('UserName' => $userName,
                                    'EmployeeName' => $empName,
                                    'DeptId' => $departmentId,
                                    'PositionId' => $positionId,
                                    'LevelId' => $levelId,
                                    'ModifiedDate' => date('Y-m-d H:i:s'),
                                    'CreatedDate' => date('Y-m-d H:i:s'),
                                    'UserLogo' => $url,
                                    'Phone' => $phoneNum,
                                    'Mobile' => $mobileNum,
                                    'Address' => $userAddress,
                                    'Email' => $emailAddress,
                                    'PowerUser' => $powerUser,
                                    'UserDob' => $userDob))
                                ->where(array('UserId' => $userId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bSuperior'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserSuperiorTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $superiorTrans = json_decode($this->bsf->isNullCheck($postParams['superiorTrans'],'string'), true);
                        foreach($superiorTrans as $trans) {
                            $iSUserId= $this->bsf->isNullCheck($trans['SUserId'], 'number');

                            $insert = $sql->insert('WF_UserSuperiorTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'SUserId'  => $iSUserId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bAlternate'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserAlternateTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $alternateTrans = json_decode($this->bsf->isNullCheck($postParams['alternateTrans'],'string'), true);
                        foreach($alternateTrans as $trans) {
                            $iAUserId= $this->bsf->isNullCheck($trans['AUserId'], 'number');

                            $insert = $sql->insert('WF_UserAlternateTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'AUserId'  => $iAUserId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bTeam'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserTeamTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $teamTrans = json_decode($this->bsf->isNullCheck($postParams['teamTrans'],'string'), true);
                        foreach($teamTrans as $trans) {
                            $iTeamId= $this->bsf->isNullCheck($trans['TeamId'], 'number');

                            $insert = $sql->insert('WF_UserTeamTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'TeamId'  => $iTeamId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bActivity'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserActivityTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $activityTrans = json_decode($this->bsf->isNullCheck($postParams['activityTrans'],'string'), true);
                        foreach($activityTrans as $trans) {
                            $iActivityId = $this->bsf->isNullCheck($trans['ActivityId'], 'number');

                            $insert = $sql->insert('WF_UserActivityTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'ActivityId'  => $iActivityId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    if ($postParams['bRole'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserRoleTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $roleTrans = json_decode($this->bsf->isNullCheck($postParams['roleTrans'],'string'), true);
                        foreach($roleTrans as $trans) {
                            $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');

                            $insert = $sql->insert('WF_UserRoleTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'RoleId'  => $iRoleId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $permissionTrans = json_decode($this->bsf->isNullCheck($postParams['permissionTrans'],'string'), true);
                        foreach($permissionTrans as $trans) {
                            $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');

                            $insert = $sql->insert('WF_UserRoleTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'RoleId'  => $iRoleId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $variantTrans = json_decode($this->bsf->isNullCheck($postParams['variantTrans'],'string'), true);
                        foreach($variantTrans as $trans) {
                            $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');
                            $dVariant= $this->bsf->isNullCheck($trans['Variant'], 'number');

                            $insert = $sql->insert('WF_UserRoleTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'RoleId'  => $iRoleId,
                                'Variant' => $dVariant
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bAlert'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserAlertTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $alertTrans = json_decode($this->bsf->isNullCheck($postParams['alertTrans'],'string'), true);
                        foreach($alertTrans as $trans) {
                            $iAlertId = $this->bsf->isNullCheck($trans['AlertId'], 'number');

                            $insert = $sql->insert('WF_UserAlertTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'AlertId'  => $iAlertId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bCompany'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserCompanyTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $companyTrans = json_decode($this->bsf->isNullCheck($postParams['companyTrans'],'string'), true);
                        foreach($companyTrans as $trans) {
                            $iCompanyId = $this->bsf->isNullCheck($trans['CompanyId'], 'number');

                            $insert = $sql->insert('WF_UserCompanyTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'CompanyId'  => $iCompanyId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bCostCentre'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserCostCentreTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $costCentreTrans = json_decode($this->bsf->isNullCheck($postParams['costcentreTrans'],'string'), true);
                        foreach($costCentreTrans as $trans) {
                            $iCostCentreId = $this->bsf->isNullCheck($trans['CostCentreId'], 'number');

                            $insert = $sql->insert('WF_UserCostCentreTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'CostCentreId'  => $iCostCentreId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    if ($postParams['bProject'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserProjectTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $projectTrans = json_decode($this->bsf->isNullCheck($postParams['projectTrans'],'string'), true);
                        foreach($projectTrans as $trans) {
                            $iProjectId = $this->bsf->isNullCheck($trans['ProjectId'], 'number');

                            $insert = $sql->insert('WF_UserProjectTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'ProjectId'  => $iProjectId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bWorkType'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserWorkTypeTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $worktypeTrans = json_decode($this->bsf->isNullCheck($postParams['worktypeTrans'],'string'), true);
                        foreach($worktypeTrans as $trans) {
                            $iWorkTypeId = $this->bsf->isNullCheck($trans['WorkTypeId'], 'number');

                            $insert = $sql->insert('WF_UserWorkTypeTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'WorkTypeId'  => $iWorkTypeId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    if ($postParams['bResourceType'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserResourceTypeTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $resourceTypeTrans = json_decode($this->bsf->isNullCheck($postParams['resourcetypeTrans'],'string'), true);
                        foreach($resourceTypeTrans as $trans) {
                            $iResourceTypeId = $this->bsf->isNullCheck($trans['ResourceTypeId'], 'number');

                            $insert = $sql->insert('WF_UserResourceTypeTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'TypeId'  => $iResourceTypeId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bResourceGroup'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserResourceGroupTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $resourceGroupTrans = json_decode($this->bsf->isNullCheck($postParams['resourcegroupTrans'],'string'), true);
                        foreach($resourceGroupTrans as $trans) {
                            $iResourceGroupId = $this->bsf->isNullCheck($trans['ResourceGroupId'], 'number');

                            $insert = $sql->insert('WF_UserResourceGroupTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'ResourceGroupId'  => $iResourceGroupId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bServiceType'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserServiceTypeTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $serviceTypeTrans = json_decode($this->bsf->isNullCheck($postParams['servicetypeTrans'],'string'), true);
                        foreach($serviceTypeTrans as $trans) {
                            $iServiceTypeId = $this->bsf->isNullCheck($trans['ServiceTypeId'], 'number');

                            $insert = $sql->insert('WF_UserServiceTypeTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'ServiceTypeId'  => $iServiceTypeId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bAccount'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserAccountTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $accountTrans = json_decode($this->bsf->isNullCheck($postParams['accountTrans'],'string'), true);
                        foreach($accountTrans as $trans) {
                            $iAccountId = $this->bsf->isNullCheck($trans['AccountId'], 'number');

                            $insert = $sql->insert('WF_UserAccountTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'AccountId'  => $iAccountId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bAccountType'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserAccountTypeTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $accountTypeTrans = json_decode($this->bsf->isNullCheck($postParams['accounttypeTrans'],'string'), true);
                        foreach($accountTypeTrans as $trans) {
                            $iAccountTypeId = $this->bsf->isNullCheck($trans['AccountTypeId'], 'number');

                            $insert = $sql->insert('WF_UserAccountTypeTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'TypeId'  => $iAccountTypeId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    if ($postParams['bSubLedgerType'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserSubLedgerTypeTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $subLedgerTypeTrans = json_decode($this->bsf->isNullCheck($postParams['subledgertypeTrans'],'string'), true);
                        foreach($subLedgerTypeTrans as $trans) {
                            $iSubLedgerTypeId = $this->bsf->isNullCheck($trans['SubLedgerTypeId'], 'number');

                            $insert = $sql->insert('WF_UserSubLedgerTypeTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'SubLedgerTypeId'  => $iSubLedgerTypeId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    if ($postParams['bInformAlert'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserInformAlertTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $informalertTrans = json_decode($this->bsf->isNullCheck($postParams['informalertTrans'],'string'), true);
                        foreach($informalertTrans as $trans) {
                            $iAlertId = $this->bsf->isNullCheck($trans['AlertId'], 'number');
                            $iScreen = $this->bsf->isNullCheck($trans['Screen'], 'number');
                            $iEMail = $this->bsf->isNullCheck($trans['EMail'], 'number');
                            $iSMS = $this->bsf->isNullCheck($trans['SMS'], 'number');

                            $insert = $sql->insert('WF_UserInformAlertTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'AlertId'  => $iAlertId,
                                'Screen'  => $iScreen,
                                'EMail'  => $iEMail,
                                'SMS'  => $iSMS
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    if ($postParams['bInformPendingwork'] == true) {
                        if ($iUserId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserInformPendingworkTrans')
                                ->where(array("UserId" => $userId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $informpendingworkTrans = json_decode($this->bsf->isNullCheck($postParams['informpendingworkTrans'],'string'), true);
                        foreach($informpendingworkTrans as $trans) {
                            $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');
                            $iScreen = $this->bsf->isNullCheck($trans['Screen'], 'number');
                            $iEMail = $this->bsf->isNullCheck($trans['EMail'], 'number');
                            $iSMS = $this->bsf->isNullCheck($trans['SMS'], 'number');

                            $insert = $sql->insert('WF_UserInformPendingworkTrans');
                            $insert->values(array(
                                'UserId'  => $userId,
                                'RoleId'  => $iRoleId,
                                'Screen'  => $iScreen,
                                'EMail'  => $iEMail,
                                'SMS'  => $iSMS
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

//                    } else if($postParams['uMode'] == 'position') {
//                        $positionId = $this->bsf->isNullCheck($postParams['uPositionId'], 'number');
//
//                        $select = $sql->select();
//                        $select->from('WF_PositionMaster')
//                            ->columns(array('DeptId', 'LevelId'))
//                            ->where(array('PositionId'=>$positionId));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $deptLevelDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                        $select = $sql->select();
//                        $select->from('WF_LevelMaster')
//                            ->columns(array('LevelId','LevelName','OrderId'))
//                            ->where(array("DeleteFlag" => '0','LevelId'=>$deptLevelDetails['LevelId']));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $levelSelectIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                        $select = $sql->select();
//                        $select->from('WF_Department')
//                            ->columns(array('DeptId','Dept_Name'))
//                            ->where(array("DeleteFlag" => '0','DeptId'=>$deptLevelDetails['DeptId']));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $deptIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                        $subSelect1 = $sql->select();
//                        $subSelect1->from('WF_LevelMaster')
//                            ->columns(array('LevelId'))
//                            ->where->lessThan('OrderId', $levelSelectIdDetails['OrderId']);
//
//                        $select = $sql->select();
//                        $select->from('WF_Users')
//                            ->columns(array('UserId', 'EmployeeName'))
//                            ->where->expression('LevelId IN ?', array($subSelect1));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $superUserDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                        $levelIdDetails = json_encode(array($deptIdDetails,$levelSelectIdDetails,$superUserDetails));
//                    } else if($postParams['uMode'] == 'Department') {
//                        $DeptId = $this->bsf->isNullCheck($postParams['DeptId'], 'number');
//
//                        $select = $sql->select();
//                        $select->from(array("a"=>"WF_DepartmentPositionTrans"))
//                            ->join(array("b"=>"WF_PositionMaster"), "a.PositionId=b.PositionId", array("PositionName"), $select::JOIN_LEFT)
//                            ->columns(array('PositionId'))
//                            ->where(array("a.DeptId" => $DeptId));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $deptIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $levelIdDetails = json_encode(array($deptIdDetails));
//                    }
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(count($resp));
                    return $response;
                } catch(PDOException $e){
                    $connection->rollback();
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $files = $request->getFiles();
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $userId = $this->bsf->isNullCheck($this->params()->fromRoute('userId'), 'number');
                $powerUser = $this->auth->getIdentity()->PowerUser;
                $curUserId = $this->auth->getIdentity()->UserId;
                $this->_view->userId = $userId;



//                $permission=0;
//                if($powerUser==0) {
//                    if($curUserId!=$userId) {
//                        $permission=0;
//                    } else {
//                        $permission=2;
//                    }
//                } else {
//                    $permission=1;
//                }
//                $teamSelectedId = array();
//
//                if($userId!=0) {
//                    if($permission==0) {
//                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "users"));
//                    }
//
//                    $select = $sql->select();
//                    $select->from('WF_UserTeamTrans')
//                        ->columns(array('TeamId'))
//                        ->where(array("UserId" => $userId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resultTeamId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    foreach($resultTeamId as $teamSelect) {
//                        $teamSelectedId[]=$teamSelect['TeamId'];
//                    }
//                    $select = $sql->select();
//                    $select->from(array("a"=>"WF_Users"))
//                        ->join(array("b"=>"WF_LevelMaster"), "a.LevelId=b.LevelId", array("LevelName"), $select::JOIN_LEFT)
//                        ->join(array("c"=>"WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name","DeptId"), $select::JOIN_LEFT)
//                        ->columns(array("*"))
//                        ->where(array("UserId" => $userId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                    $this->_view->resultsMain = $resultsMain;
//
//                    $select = $sql->select();
//                    $select->from('WF_LevelMaster')
//                        ->columns(array('OrderId'))
//                        ->where(array("DeleteFlag" => '0','LevelId'=>$resultsMain['LevelId']));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $levelSelectIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                    $select = $sql->select();
//                    $select->from('WF_UserSuperiorTrans')
//                        ->columns(array('SUserId'))
//                        ->where(array('UserId'=>$userId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->superiorDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//
//                    $subSelect1 = $sql->select();
//                    $subSelect1->from('WF_LevelMaster')
//                        ->columns(array('LevelId'))
//                        ->where->lessThan('OrderId', $levelSelectIdDetails['OrderId']);
//
//                    $select = $sql->select();
//                    $select->from('WF_Users')
//                        ->columns(array('UserId', 'EmployeeName'))
//                        ->where->expression('LevelId IN ?', array($subSelect1));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->superUserDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    $subQuery = $sql->select();
//                    $subQuery->from('WF_UserCostCentreTrans')
//                        ->columns(array('CostCentreId'))
//                        ->where(array("UserId" => $userId));
//
//                    $select = $sql->select();
//                    $select->from('WF_CostCentre')
//                        ->columns(array('CostCentreId'));
//                    $select->where->expression('CostCentreId NOT IN ?', array($subQuery));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->resultCostCentreId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    //activity, role, & alert
//                    $deptId=$this->bsf->isNullCheck($resultsMain['DeptId'], 'number');
//
//                    $subAct = $sql->select();
//                    $subAct->from('WF_DepartmentActivityTrans')
//                        ->columns(array('ActivityId'))
//                        ->where(array("DeptId" => $deptId));
//
//                    $subSelect1 = $sql->select();
//                    $subSelect1->from('WF_UserActivityTrans')
//                        ->columns(array('ActivityId'))
//                        ->where("UserId=$userId");
//
//                    $select = $sql->select();
//                    $select->from('WF_ActivityMaster')
//                        ->columns(array('ActivityId', 'ActivityName'))
//                        ->where->expression('ActivityId NOT IN ?', array($subSelect1));
//                    $select->where->expression('ActivityId IN ?', array($subAct));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultActivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                    $subQuery = $sql->select();
//                    $subQuery->from('WF_ActivityRoleTrans')
//                        ->columns(array('RoleId'))
//                        ->where->expression('ActivityId IN ?', array($subSelect1));
//
//
//                    $subSelect2 = $sql->select();
//                    $subSelect2->from('WF_UserRoleTrans')
//                        ->columns(array('RoleId'))
//                        ->where("UserId=$userId");
//
//                    $select = $sql->select();
//                    $select->from('WF_TaskTrans')
//                        ->columns(array('RoleName', 'RoleId'));
//                    $select ->where->expression('RoleId IN ?', array($subQuery));
//                    $select ->where->expression('RoleId NOt IN ?', array($subSelect2));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultRole = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    $select = $sql->select();
//                    $select->from('WF_TaskTrans')
//                        ->columns(array('RoleName', 'RoleId'))
//                        ->where->expression('RoleId IN ?', array($subSelect2));
//                    $select ->where->expression('RoleId IN ?', array($subQuery));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultDeptRoleSel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                    $subSelect1 = $sql->select();
//                    $subSelect1->from('WF_UserAlertTrans')
//                        ->columns(array('AlertId'))
//                        ->where("UserId=$userId");
//
//                    $subSelect2 = $sql->select();
//                    $subSelect2->from('WF_DepartmentAlertTrans')
//                        ->columns(array('AlertId'))
//                        ->where("DeptId=$deptId");
//
//                    $select = $sql->select();
//                    $select->from('WF_AlertMaster')
//                        ->columns(array('AlertId', 'AlertName'))
//                        ->where->expression('AlertId IN ?', array($subSelect2));
//                    $select->where->expression('AlertId NOT IN ?', array($subSelect1));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultAlert = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                    //Fill Dept Details
//                    $select = $sql->select();
//                    $select->from(array("a" => "WF_UserActivityTrans"))
//                        ->columns(array("UserId", "ActivityId"))
//                        ->join(array("b" => "WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array("ActivityName"), $select::JOIN_INNER)
//                        ->where(array('UserId' => $userId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultDeptActivitySel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    $select = $sql->select();
//                    $select->from(array("a" => "WF_UserAlertTrans"))
//                        ->columns(array("UserId", "AlertId"))
//                        ->join(array("b" => "WF_AlertMaster"), "a.AlertId=b.AlertId", array("AlertName"), $select::JOIN_INNER)
//                        ->where(array('UserId' => $userId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->ResultDeptAlertSel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                }
//                if($userId==0) {
//                    if ($permission == 0 || $permission == 2) {
//                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "users"));
//
//                    }
//                }
//                $this->_view->teamSelectedId=$teamSelectedId;
//                $select = $sql->select();
//                $select->from(array('a'=>'WF_TeamMaster'))
//                    ->where(array("a.DeleteFlag"=>0));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->teamDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if ($userId !=0) {
                    $select = $sql->select();
                    $select->from(array("a" => "WF_Users"))
                        ->join(array("b" => "WF_LevelMaster"), "a.LevelId=b.LevelId", array("LevelName"), $select::JOIN_LEFT)
                        ->join(array("c" => "WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name", "DeptId"), $select::JOIN_LEFT)
                        ->columns(array("*"))
                        ->where(array("UserId" => $userId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }

                $comSelect = $sql->select();
                $comSelect->from('WF_Users')
                    ->columns(array('UserId', 'UserName'));
                $comStatement = $sql->getSqlStringForSqlObject($comSelect);
                $this->_view->arr_user = $dbAdapter->query($comStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_Users"))
                    ->columns(array('UserName'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->UserName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->userdtl = $userId;

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'))
                    ->where("DeleteFlag =0 and UserId<> $userId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_Department')
                    ->columns(array('DeptId','Dept_Name'))
                    ->where(array("DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->departmentDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_LevelMaster')
                    ->columns(array('LevelId','LevelName'))
                    ->where(array("DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->levelDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_PositionMaster')
                    ->columns(array('PositionId','PositionName'))
                    ->where(array("DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->positionIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array('CompanyId','CompanyName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultCompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_CostCentre')
                    ->columns(array('CostCentreId','CostCentreName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserSuperiorTrans'))
                    ->join(array("b" => "WF_Users"), "a.SUserId=b.UserId", array('UserName'), $select::JOIN_INNER)
                    ->columns(array('SUserId'))
                    ->where(array("a.UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->superiorTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserAlternateTrans'))
                    ->join(array("b" => "WF_Users"), "a.AUserId=b.UserId", array('UserName'), $select::JOIN_INNER)
                    ->columns(array('AUserId'))
                    ->where(array("a.UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->alternateTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_TeamMaster')
                    ->columns(array('TeamId','TeamName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->teamMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>"WF_UserTeamTrans"))
                    ->join(array("b"=>"WF_TeamMaster"), "a.TeamId=b.TeamId", array("TeamName"), $select::JOIN_LEFT)
                    ->columns(array('TeamId'))
                    ->where(array("a.UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->teamTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from('WF_ActivityMaster')
                    ->columns(array('ActivityId','ActivityName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->activityMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>"WF_UserActivityTrans"))
                    ->join(array("b"=>"WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array("ActivityName"), $select::JOIN_LEFT)
                    ->columns(array('ActivityId'))
                    ->where(array("a.UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->activityTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subSelect1 = $sql->select();
                $subSelect1->from(array("a"=>"WF_ActivityTaskTrans"))
                    ->join(array("b"=>"WF_UserActivityTrans"), "a.ActivityId=b.ActivityId", array(), $subSelect1::JOIN_LEFT)
                    ->columns(array('TaskId'))
                    ->where(array("b.UserId" => $userId));

//                $subSelect1 = $sql->select();
//                $subSelect1->from('WF_UserActivityTrans')
//                    ->columns(array('ActivityId'))
//                    ->where(array("UserId" => $userId));


                $select = $sql->select();
                $select->from(array("a"=>'WF_TaskMaster'))
                    ->columns(array('TaskId','TaskName'))
                    ->where->expression("a.TaskId IN ?", array($subSelect1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->roleMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a"=>'WF_TaskTrans'))
                    ->join(array("b" => "WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
                    ->columns(array('RoleId','RoleName'))
                    ->where->expression("a.RoleType='C' and b.TaskId IN ?", array($subSelect1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->permissionMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_TaskTrans'))
                    ->join(array("b" => "WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
                    ->columns(array('RoleId','RoleName'))
                    ->where->expression("a.RoleType='V' and b.TaskId IN ?", array($subSelect1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->variantMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserRoleTrans'))
                    ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('TaskName','RoleType'), $select::JOIN_INNER)
                    ->columns(array('RoleId'))
                    ->where("a.UserId = $userId and b.RoleType in ('N','E','D','A')");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->roleTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserRoleTrans'))
                    ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName'), $select::JOIN_INNER)
                    ->columns(array('RoleId'))
                    ->where("a.UserId = $userId and b.RoleType ='C'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->permissionTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserRoleTrans'))
                    ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName'), $select::JOIN_INNER)
                    ->columns(array('RoleId','Variant'))
                    ->where("a.UserId = $userId and b.RoleType ='V'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->variantTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_TaskTrans'))
                    ->columns(array('RoleId', 'TaskName','RoleType','RoleName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ResultTaskTransMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_AlertMaster')
                    ->columns(array('AlertId', 'AlertName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->alertMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserAlertTrans'))
                    ->join(array("b" => "WF_AlertMaster"), "a.AlertId=b.AlertId", array('AlertName'), $select::JOIN_INNER)
                    ->columns(array('AlertId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->alertTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array('CompanyId', 'CompanyName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->companyMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserCompanyTrans'))
                    ->join(array("b" => "WF_CompanyMaster"), "a.CompanyId=b.CompanyId", array('CompanyName'), $select::JOIN_INNER)
                    ->columns(array('CompanyId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->companyTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array('CostCentreId', 'CostCentreName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->costcentreMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserCostcentreTrans'))
                    ->join(array("b" => "WF_OperationalCostCentre"), "a.CostcentreId=b.CostcentreId", array('CostCentreName'), $select::JOIN_INNER)
                    ->columns(array('CostcentreId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->costcentreTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserProjectTrans'))
                    ->join(array("b" => "Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->columns(array('ProjectId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_WorkTypeMaster')
                    ->columns(array('WorkTypeId', 'WorkTypeName'=>new Expression("WorkType")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->worktypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserWorkTypeTrans'))
                    ->join(array("b" => "Proj_WorkTypeMaster"), "a.WorkTypeId=b.WorkTypeId", array('WorkTypeName'=>new Expression("WorkType")), $select::JOIN_INNER)
                    ->columns(array('WorkTypeId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->worktypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ResourceType')
                    ->columns(array('ResourceTypeId'=>new Expression("TypeId"), 'ResourceTypeName'=>new Expression("TypeName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcetypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserResourceTypeTrans'))
                    ->join(array("b" => "Proj_ResourceType"), "a.TypeId=b.TypeId", array('ResourceTypeName'=>new Expression("TypeName")), $select::JOIN_INNER)
                    ->columns(array('ResourceTypeId'=>new Expression("a.TypeId")))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcetypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ResourceGroup')
                    ->columns(array('ResourceGroupId', 'ResourceGroupName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcegroupMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserResourceGroupTrans'))
                    ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array('ResourceGroupName'), $select::JOIN_INNER)
                    ->columns(array('ResourceGroupId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourcegroupTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ServiceTypeMaster')
                    ->columns(array(new Expression("Top 3 ServiceTypeId, ServiceTypeName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->servicetypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a"=>'WF_UserServiceTypeTrans'))
                    ->join(array("b" => "Proj_ServiceTypeMaster"), "a.ServiceTypeId=b.ServiceTypeId", array('ServiceTypeName'), $select::JOIN_INNER)
                    ->columns(array('ServiceTypeId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->servicetypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from('FA_AccountMaster')
                    ->columns(array('AccountId', 'AccountName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->accountMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserAccountTrans'))
                    ->join(array("b" => "FA_AccountMaster"), "a.AccountId=b.AccountId", array('AccountName'), $select::JOIN_INNER)
                    ->columns(array('AccountId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->accountTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from('FA_AccountType')
                    ->columns(array('AccountTypeId'=>new Expression("TypeId"), 'AccountTypeName'=>new Expression("TypeName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->accounttypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserAccountTypeTrans'))
                    ->join(array("b" => "FA_AccountType"), "a.TypeId=b.TypeId", array('AccountTypeName'=>new Expression("TypeName")), $select::JOIN_INNER)
                    ->columns(array('AccountTypeId'=>new Expression("a.TypeId")))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->accounttypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('FA_SubLedgerType')
                    ->columns(array('SubLedgerTypeId', 'SubLedgerTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->subledgertypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserSubLedgerTypeTrans'))
                    ->join(array("b" => "FA_SubLedgerType"), "a.SubLedgerTypeId=b.SubLedgerTypeId", array('SubLedgerTypeName'), $select::JOIN_INNER)
                    ->columns(array('SubLedgerTypeId'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->subledgertypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserInformPendingworkTrans'))
                    ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName'), $select::JOIN_INNER)
                    ->columns(array('RoleId','Screen','EMail','SMS'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->pendingworkinformTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserInformAlertTrans'))
                    ->join(array("b" => "WF_AlertMaster"), "a.AlertId=b.AlertId", array('AlertName'), $select::JOIN_INNER)
                    ->columns(array('AlertId','Screen','EMail','SMS'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->alertinformTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function companyGridAction(){
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
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                if($searchStr!="") {
                    $select = $sql->select();
                    $select->from('WF_CompanyMaster')
                        ->columns(array("*"));
                    $select->where("CompanyName LIKE '%" . $searchStr . "%' OR ShortName LIKE '%" . $searchStr . "%' OR Email LIKE '%" . $searchStr . "%' OR Mobile LIKE '%" . $searchStr . "%' OR Phone LIKE '%" . $searchStr . "%' OR ContactPerson LIKE '%" . $searchStr . "%' OR Website LIKE '%" . $searchStr . "%'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $select = $sql->select();
                    $select->from('WF_CompanyMaster')
                        ->columns(array("*"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $companyList ="";
                $companyList.='<ul class="col-lg-12 company_grid animated fadeIn">';
                foreach($companySearch as $i):
                    $userLogo="";
                    if(isset($i['LogoPath']) && trim($i['LogoPath'])!='') {
                        $userLogo = $viewRenderer->basePath().'/'.$i['LogoPath'];
                    } else {
                        $userLogo = $viewRenderer->basePath().'/images/avatar.jpg';
                    }
                    $companyCover="";
                    if(isset($i['CoverPhoto']) && trim($i['CoverPhoto'])!='') {
                        $companyCover = $viewRenderer->basePath().'/'.$i['CoverPhoto'];
                    } else {
                        $companyCover = $viewRenderer->basePath().'/images/companyview_cover.jpg';
                    }
                    $companyMap = $viewRenderer->basePath().'/images/company-map1.jpg';
                    $companyList .= '<li class="col-lg-3 col-md-6 col-sm-6 padlr0">
                            <div class="compgrid_image" style="background: url('.$companyCover.');">
                                <span class="comp_arrowlink"><a href="company-view/'.$i['CompanyId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="right" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                                <div class="compgrid_logo brad_50 m_auto">
                                    <span><img class="brad_50" src="'.$userLogo.'" /></span>
                                </div>
                                <h3 class="compgrid_title">'.$i['CompanyName'].'</h3>
                            </div>
                            <div class="comp_editlink brad_50"><a href="new-company/'.$i['CompanyId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="bottom" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                            <div class="compgrid_content">
                                <div class="col-lg-6 col-sm-6 col-xs-6 padlr0">
                                    <p class="comp_address">'.$i['Address'].',<br/>

                                    </p>
                                </div>
                                <div class="col-lg-6 col-sm-6 col-xs-6 padlr0">
                                    <div class="comp_map" style="background-image:url('.$companyMap.');">
                                    </div>
                                </div>
                                <p class="clear"><span><i class="fa fa-user"></i></span>'.$i['ContactPerson'].'<span class="vendor_phone"><i class="fa fa-phone"></i>'.$i['Mobile'].'</span></p>
                                <a href="#" class="vwstrtr_btn ripple">View Structure</a>
                            </div>
                        </li>';
                endforeach;
                $companyList .= '</ul>';
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setStatusCode(200)->setContent($companyList);
                return $response;
            }
        } else {
            if($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array("*"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }
        return $this->_view;
    }

    public function companyGridlistAction(){
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
        $request = $this->getRequest();


        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                if($searchStr!="") {
                    $select = $sql->select();
                    $select->from('WF_CompanyMaster')
                        ->columns(array("*"));
                    $select->where("CompanyName LIKE '%" . $searchStr . "%' OR ShortName LIKE '%" . $searchStr . "%' OR Email LIKE '%" . $searchStr . "%' OR Mobile LIKE '%" . $searchStr . "%' OR Phone LIKE '%" . $searchStr . "%' OR ContactPerson LIKE '%" . $searchStr . "%' OR Website LIKE '%" . $searchStr . "%'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $select = $sql->select();
                    $select->from('WF_CompanyMaster')
                        ->columns(array("*"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $companyList ="";
                foreach($companySearch as $i):
                    $userLogo="";
                    if(isset($i['LogoPath']) && trim($i['LogoPath'])!='') {
                        $userLogo = $viewRenderer->basePath().'/'.$i['LogoPath'];
                    } else {
                        $userLogo = $viewRenderer->basePath().'/images/avatar.jpg';
                    }
                    $companyMap = $viewRenderer->basePath().'/images/company-map1.jpg';
                    $companyList .= '<div class="col-lg-12 col-md-6 col-sm-6  bids_list compgdlist brad_3 padlr0">
                                        <span class="comp_arrowlink"><a href="company-view/'.$i['CompanyId'].'" class="brad_50" data-toggle="tooltip" data-placement="right" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                                        <div class="comp_editlink brad_50"><a href="new-company/'.$i['CompanyId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="left" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                                        <div class="col-lg-7 padlr0">
                                            <div class="col-lg-9">
                                                <div class="compgrid_logo brad_50 float_l">
                                                    <span><img class="brad_50" src="'.$userLogo.'" /></span>
                                                </div>
                                                <h1>'.$i['CompanyName'].'<br>
                                                    <span class="m_top10"><span><i class="fa fa-user"></i></span>'.$i['ContactPerson'].'<span class="vendor_phone"><i class="fa fa-phone"></i>'.$i['Mobile'].'</span></span>
                                                </h1>
                                            </div>
                                            <div class="col-lg-3 padlr0">
                                                <div class="comp_map" style="background-image:url('.$companyMap.')"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-5 bidvendor_detail compgdlist_detail">
                                            <p><span class="p_label"><span class="mapaddress_icon"><i class="fa fa-map-marker"></i></span>  Address :</span>'.$i['Address'].'</p>
                                             <a href="#" class="vwstrtr_btn m_top0 ripple">View Structure</a>
                                        </div>
                                    </div>';
                endforeach;
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setStatusCode(200)->setContent($companyList);
                return $response;
            }
        } else {
            if($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from('WF_CompanyMaster')
                    ->columns(array("*"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }
        return $this->_view;
    }
    public function sampleAction(){
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

        //print_r( $this->params()->fromRoute());

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $files=
                    //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {


            }


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function reminderAction(){
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
                //update on/off//
                $update = $sql->update();
                $update->table('WF_Reminder');
                $update->set(array(
                    'Type'  => $this->bsf->isNullCheck($postParams['rCheck'],'number'),
                ));
                $update->where(array('ReminderId'=>$this->bsf->isNullCheck($postParams['remindId'],'number')));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
//                $postParams = $request->getPost();
//                //Print_r($postParams);die;
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//                //add reminders//
//                $insert  = $sql->insert('WF_Reminder');
//                $newData = array(
//                    'Type'   => $this->bsf->isNullCheck($postParams['r_check'],'number'),
//                    'RDescription'  => $this->bsf->isNullCheck($postParams['r_description'],'string'),
//                    'RepeatEvery'  => $this->bsf->isNullCheck($postParams['repeat_every'],'number'),
//                    'RDate' =>date('Y-m-d',strtotime($postParams['r_date'])),
//                    //'Users'  => $this->bsf->isNullCheck($postParams['r_users'],'number'),
//                    'CreatedDate'=>date('Y-m-d H:i:s')
//                );
//                $insert->values($newData);
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                $reminderId = $dbAdapter->getDriver()->getLastGeneratedValue();
//                foreach ($postParams['r_users'] as $value){
//                    $select = $sql->insert('WF_RemindUsers');
//                    $newData = array(
//                        'ReminderId' => $reminderId,
//                        'ReminderUserId'=> $value,
//                    );
//                    $select->values($newData);
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                }
//                $connection->commit();
//                $FeedId = $this->params()->fromQuery('FeedId');
//                $AskId = $this->params()->fromQuery('AskId');
//                if(isset($FeedId) && $FeedId!="") {
//                    $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'reminder'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
//                } else {
//                    $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'reminder'));
//                }
            } else {
                //WorkFlow Users//
                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Register values//
                $select = $sql->select();
                $select->from('WF_Reminder')
                    ->columns(array(new Expression("ReminderId,CONVERT(varchar(10),RDate,105) as RDate,RDescription ,Type,'' EmployeeName,RepeatEvery")))
                    ->where(array('DeleteFlag' => 0))
                    ->order('ReminderId desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $reminders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $icount = 0;
                foreach ($reminders as $resu){
                    $reminderId = $resu['ReminderId'];
                    $struser="";
                    $selectMultiUser = $sql->select();
                    $selectMultiUser->from(array("a"=>"WF_RemindUsers"));
                    $selectMultiUser->columns(array("ReminderId"),array("ReminderUserId"))
                        ->join(array("b"=>"WF_Users"), "a.ReminderUserId=b.UserId", array("UserName"), $selectMultiUser::JOIN_INNER);
                    $selectMultiUser->where(array("a.ReminderId"=>$reminderId));
                    $statementMultiUser = $sql->getSqlStringForSqlObject($selectMultiUser);
                    $multiUser = $dbAdapter->query($statementMultiUser, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $user = array();
                    $strUser="";
                    if($multiUser ){
                        foreach($multiUser as $multiUr){
                            array_push($user, $multiUr['UserName']);
                        }
                        $strUser = implode(",", $user);
                    }
                    $reminders[$icount]['UserName']=$strUser;

                    $icount=$icount+1;
                }


                $this->_view->reminders=$reminders;
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function reminderDeleteAction(){
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
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    $delete = $sql->delete();
                    $delete->from('WF_RemindUsers')
                        ->where(array('ReminderId' => $RegisterId,));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $deletereminder = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);



                    $update = $sql->update();
                    $update->table('WF_Reminder')
                        ->set(array('DeleteFlag' => '1','DeletedDate' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('ReminderId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    //$this->redirect()->toRoute('workflow/reminder', array('controller' => 'index', 'action' => 'reminder'));
                    $status = 'deleted';

                }

                catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function organisationdepartmentAction(){
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
                $levelIdDetails =  "";
//                if($postParams['uMode'] == 'role') {
//                    $activityIds = json_decode($postParams['cId']);
//                    $deptId = $postParams['deptId'];
//                    if (count($activityIds) != 0) {
//                        $subQuery = $sql->select();
//                        $subQuery->from('WF_ActivityRoleTrans')
//                            ->columns(array('RoleId'))
//                            ->where(array("ActivityId" => $activityIds));
//
//                        $subSelect1 = $sql->select();
//                        $subSelect1->from('WF_DepartmentRoleTrans')
//                            ->columns(array('RoleId'))
//                            ->where("DeptId=$deptId");
//
//                        $select = $sql->select();
//                        $select->from('WF_TaskTrans')
//                            ->columns(array('RoleName', 'RoleId'))
//                            ->where->expression('RoleId IN ?', array($subQuery));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $roleIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $levelIdDetails = array($roleIdDetails);
//                    } else {
//                        $roleIdDetails = '';
//                        $selectRoleIdDetails = '';
//                        $levelIdDetails = array($roleIdDetails, $selectRoleIdDetails);
//                    }
                if($postParams['uMode'] == 'searchpos'){
                    $searchVal = $this->bsf->isNullCheck($postParams['searchVal'],'string');

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_Department'))
                        ->join(array("b" => "WF_PositionType"), "a.DeptTypeId=b.PositionTypeId", array("Dept_Type"=>new Expression("isnull(PositionTypeName,'None')")), $select::JOIN_LEFT)
                        ->columns(array('DeptId', 'Dept_Name','Email','DeptTypeId'));
                    if($searchVal!="") {
                        $select->where("(a.Dept_Name LIKE '%" . $searchVal . "%' OR b.PositionTypeName LIKE '%" . $searchVal . "%')");
                    }
                    $select->where(array("a.DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $levelIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $insert = $sql->insert('WF_Department');
                    $insert->values(array(
                        'Dept_Name'  => $postParams['deptName_0'],
                        'EMail'  => $postParams['emailId_0']
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $deptId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    //Activity
                    $cid =explode(",", $postParams['hidcid']);
                    foreach($cid as $count){
                        if($count!="" || $count!=0)	{
                            $insert = $sql->insert('WF_DepartmentActivityTrans');
                            $insert->values(array(
                                'ActivityId'  => $count,
                                'DeptId'  => $deptId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Roles
                    $cid1 =explode(",", $postParams['hidcid1']);
                    foreach($cid1 as $count1){
                        if($count1!="" || $count1!=0){
                            $insert = $sql->insert('WF_DepartmentRoleTrans');
                            $insert->values(array(
                                'RoleId'  => $count1,
                                'DeptId'  => $deptId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Alert
                    $cid2 =explode(",", $postParams['hidcid2']);
                    foreach($cid2 as $count2){
                        if($count2!="" || $count2!=0){
                            $insert = $sql->insert('WF_DepartmentAlertTrans');
                            $insert->values(array(
                                'AlertId'  => $count2,
                                'DeptId'  => $deptId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationdepartment"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationdepartment"));
                    }
//                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationdepartment"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }


            $select = $sql->select();
            $select->from(array("a"=>'WF_PositionType'))
                ->columns(array('PositionTypeId','PositionTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultPositionType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a"=>'WF_Department'))
                ->join(array("b" => "WF_PositionType"), "a.DeptTypeId=b.PositionTypeId", array("Dept_Type"=>new Expression("isnull(PositionTypeName,'None')")), $select::JOIN_LEFT)
                ->columns(array('DeptId', 'Dept_Name','Email','DeptTypeId'));
            $select->where(array("a.DeleteFlag"=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultDeptReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }



    public function functionalityNamesAction(){
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
        $select = $sql->select();

        $select = $sql->select();
        $select->from('WF_FunctionalityName')
            ->columns(array("*"));
        //->where(array("ModuleId"=>'8'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsfunctions = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                /*$functionName=$this->bsf->isNullCheck($postParams['remindId'],'string');
                $module=$this->bsf->isNullCheck($postParams['moduleId'],'number');
                //update on/off//
                        $update = $sql->update();
                        $update->table('WF_FunctionalityName');
                        $update->set(array(
                         'DisplayName'  => $this->bsf->isNullCheck($postParams['rCheck'],'string'),
                         'CreatedDate'=>date('d-m-y H:i:s'),
                        ));
                        $update->where(array('FunctionName'=>$functionName, 'ModuleId'=>$module));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $result =  "";
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent($result);
                        return $response;*/
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                //Print_r($postParams);die;
                $count= $postParams['rowCount'];

                for($i=1;$i<=$count;$i++){
                    $update = $sql->update();
                    $update->table('WF_FunctionalityName');
                    $update->set(array(
                        'DisplayName'  => $this->bsf->isNullCheck($postParams['displayname_'.$i],'string'),
                        'ModifiedDate'=>date('Y-m-d H:i:s'),
                    ));
                    $update->where(array('FunctionName'=>$postParams['functionname_'.$i], 'ModuleId'=>$postParams['moduleId_'.$i]));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }


    public function orgdepartmentupdateAction(){
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
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                if($postParams['mode']=='edit') {
                    $deptId = $this->bsf->isNullCheck($postParams['DepId'], 'number');
                    $this->_view->mode=$postParams['mode'];
                    $this->_view->DeptId=$deptId;

                    $select = $sql->select();
                    $select->from(array("a" => 'WF_Department'))
                        ->columns(array('*'))
                        ->where(array('DeptId' => $deptId, 'DeleteFlag' => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultDeptRegs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('WF_ActivityMaster')
                        ->columns(array('ActivityId', 'ActivityName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultActivity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from('WF_AlertMaster')
                        ->columns(array('AlertId', 'AlertName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultAlert = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subSelect1 = $sql->select();
                    $subSelect1->from('WF_DepartmentPositionTrans')
                        ->columns(array('PositionId'))
                        ->where("DeptId=$deptId");

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_PositionMaster'))
                        ->columns(array('PositionId', 'PositionName'))
                        ->where->expression('PositionId NOT IN ?', array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultPosition = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => 'WF_PositionType'))
                        ->columns(array('PositionTypeId','PositionTypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultPositionType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_DepartmentPositionTrans'))
                        ->join(array("b" => "WF_PositionMaster"), "a.PositionId=b.PositionId", array("PositionName"), $select::JOIN_INNER)
                        ->columns(array('DeptId', 'PositionId'))
                        ->where(array('a.DeptId' => $deptId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultPositionSel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_DepartmentActivityTrans'))
                        ->join(array("b" => "WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array("ActivityName"), $select::JOIN_INNER)
                        ->columns(array('DeptId', 'PositionId','ActivityId'))
                        ->where(array('a.DeptId' => $deptId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultActivityTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_DepartmentAlertTrans'))
                        ->join(array("b" => "WF_AlertMaster"), "a.AlertId=b.AlertId", array("AlertName"), $select::JOIN_INNER)
                        ->columns(array('DeptId', 'PositionId','AlertId'))
                        ->where(array('a.DeptId' => $deptId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultAlertTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_DepartmentRoleTrans'))
                        ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('TaskName','RoleType'), $select::JOIN_INNER)
                        ->columns(array('DeptId', 'PositionId','RoleId'))
                        ->where("a.DeptId = $deptId and b.RoleType in ('N','E','D','A')");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultRoleTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_DepartmentRoleTrans'))
                        ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName'), $select::JOIN_INNER)
                        ->columns(array('DeptId', 'PositionId','RoleId'))
                        ->where("a.DeptId = $deptId and b.RoleType ='C'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultPermissionTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskTrans'))
                        ->columns(array('RoleId', 'TaskName','RoleType'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ResultTaskTransMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $deptId = $postParams['DeptId'];

                    if ($deptId !=0) {
                        $delete = $sql->delete();
                        $delete->from('WF_DepartmentPositionTrans')
                            ->where(array('DeptId' => $deptId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WF_DepartmentActivityTrans')
                            ->where(array('DeptId' => $deptId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WF_DepartmentRoleTrans')
                            ->where(array('DeptId' => $deptId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WF_DepartmentAlertTrans')
                            ->where(array('DeptId' => $deptId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update("WF_Department");
                        $update->set(array("Dept_Name" => $postParams['deptName'], "DeptTypeId"=> $postParams['positionTypeId'], "EMail" => $postParams['emailId']))
                            ->where(array("DeptId" => $deptId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert('WF_Department');
                        $insert->values(array("Dept_Name" => $postParams['deptName'], "DeptTypeId"=> $postParams['positionTypeId'], "EMail" => $postParams['emailId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $deptId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    //Activity
                    $pcid =explode(",", $postParams['spositioncid']);
                    foreach($pcid as $count){
                        if($count!="" || $count!=0)	{
                            $insert = $sql->insert('WF_DepartmentPositionTrans');
                            $insert->values(array(
                                'PositionId'  => $count,
                                'DeptId'  => $deptId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $activityTrans = json_decode($this->bsf->isNullCheck($postParams['arrActivityTrans'],'string'), true);
                    foreach($activityTrans as $trans) {
                        $iDeptId = $this->bsf->isNullCheck($trans['DeptId'], 'number');
                        $iPositionId = $this->bsf->isNullCheck($trans['PositionId'], 'number');
                        $iActivityId = $this->bsf->isNullCheck($trans['ActivityId'], 'number');

                        $select = $sql->select();
                        $select->from(array("a" => 'WF_DepartmentActivityTrans'))
                            ->columns(array('TransId'))
                            ->where(array('a.DeptId' => $iDeptId, 'a.PositionId' => $iPositionId, 'ActivityId' => $iActivityId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $transId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if ($transId['TransId'] < 0) {

                            $insert = $sql->insert('WF_DepartmentActivityTrans');
                            $insert->values(array(
                                'DeptId' => $iDeptId,
                                'PositionId' => $iPositionId,
                                'ActivityId' => $iActivityId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $alertTrans = json_decode($this->bsf->isNullCheck($postParams['arrAlertTrans'],'string'), true);
                    foreach($alertTrans as $trans) {
                        $iDeptId = $this->bsf->isNullCheck($trans['DeptId'], 'number');
                        $iPositionId = $this->bsf->isNullCheck($trans['PositionId'], 'number');
                        $iAlertId = $this->bsf->isNullCheck($trans['AlertId'], 'number');

                        $select = $sql->select();
                        $select->from(array("a" => 'WF_DepartmentAlertTrans'))
                            ->columns(array('TransId'))
                            ->where(array('a.DeptId' => $iDeptId, 'a.PositionId' => $iPositionId, 'AlertId' => $iAlertId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $transId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if ($transId['TransId'] < 0) {

                            $insert = $sql->insert('WF_DepartmentAlertTrans');
                            $insert->values(array(
                                'DeptId' => $iDeptId,
                                'PositionId' => $iPositionId,
                                'AlertId' => $iAlertId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $roleTrans = json_decode($this->bsf->isNullCheck($postParams['arrRoleTrans'],'string'), true);
                    foreach($roleTrans as $trans) {
                        $iDeptId = $this->bsf->isNullCheck($trans['DeptId'], 'number');
                        $iPositionId = $this->bsf->isNullCheck($trans['PositionId'], 'number');
                        $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');

                        $select = $sql->select();
                        $select->from(array("a" => 'WF_DepartmentRoleTrans'))
                            ->columns(array('TransId'))
                            ->where(array('a.DeptId' => $iDeptId, 'a.PositionId' => $iPositionId, 'RoleId' => $iRoleId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $transId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if ($transId['TransId'] < 0) {
                            $insert = $sql->insert('WF_DepartmentRoleTrans');
                            $insert->values(array(
                                'DeptId' => $iDeptId,
                                'PositionId' => $iPositionId,
                                'RoleId' => $iRoleId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $permissionTrans = json_decode($this->bsf->isNullCheck($postParams['arrPermissionTrans'],'string'), true);
                    foreach($permissionTrans as $trans) {
                        $iDeptId = $this->bsf->isNullCheck($trans['DeptId'], 'number');
                        $iPositionId = $this->bsf->isNullCheck($trans['PositionId'], 'number');
                        $iRoleId = $this->bsf->isNullCheck($trans['RoleId'], 'number');

                        $select = $sql->select();
                        $select->from(array("a" => 'WF_DepartmentRoleTrans'))
                            ->columns(array('TransId'))
                            ->where(array('a.DeptId' => $iDeptId, 'a.PositionId' => $iPositionId, 'RoleId' => $iRoleId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $transId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if ($transId['TransId'] < 0) {

                            $insert = $sql->insert('WF_DepartmentRoleTrans');
                            $insert->values(array(
                                'DeptId' => $iDeptId,
                                'PositionId' => $iPositionId,
                                'RoleId' => $iRoleId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationdepartment"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

        }
    }

    public function deleteorgdepartmentAction(){
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
                    $DeptId = $this->bsf->isNullCheck($this->params()->fromPost('DepId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $deptName = $this->bsf->isNullCheck($this->params()->fromPost('deptName'), 'string');

                            $select = $sql->select();
                            $select->from(array("a"=>'WF_Department'))
                                ->columns(array('DeptId'));
                            $select->where("a.DeleteFlag='0' And a.Dept_Name='$deptName' and a.DeptId<> $DeptId");

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
                            $select->from(array("a"=>'WF_Users'))
                                ->columns(array('DeptId'));
                            $select->where("a.DeleteFlag='0' And a.DeptId=$DeptId");
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $deptCheck = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if(count($deptCheck)>0) {
                                $response->setStatusCode(201)->setContent( $status );
                                return $response;
                            } else {
                                $connection->beginTransaction();
                                $update = $sql->update();
                                $update->table('WF_Department')
                                    ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                    ->where(array('DeptId' => $DeptId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $connection->commit();
                                $status = 'deleted';
                                $response->setStatusCode(200)->setContent($status);
                                return $response;
                                break;
                            }
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }
            }
        }
    }

    public function approvalsettingAction(){
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
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $delete = $sql->delete();
                    $delete->from('WF_ApprovalSetting');
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $delApprSet = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //valuebase Approval
                    $cid =explode(",", $postParams['hidcid']);
                    foreach($cid as $count){
                        if($count!="" || $count!=0)	{
                            $insert = $sql->insert('WF_ApprovalSetting');
                            $insert->values(array(
                                'RoleId'  => $count,
                                'ValueApproval'  => 1
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "approvalsetting"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            $selectAppset = $sql->select();
            $selectAppset->from(array("a"=>"WF_ApprovalSetting"))
                ->columns(array("RoleId", "RoleName"=>new Expression("b.RoleName"), "Sel"=>new Expression("1")))
                ->join(array("b"=>new Expression("WF_TaskTrans")), "a.RoleId=b.RoleId", array(), $selectAppset::JOIN_INNER)
                ->where("b.RoleType='A'");

            $selectAppselecting= $sql->select();
            $selectAppselecting->from("WF_ApprovalSetting")
                ->columns(array("RoleId"));

            $selectRole = $sql->select();
            $selectRole->from(array("a"=>"WF_TaskTrans"))
                ->columns(array("RoleId", "RoleName", "Sel"=>new Expression("1-1")))
                ->where("a.RoleType='A'")
                ->where->notIn('a.RoleId',$selectAppselecting);
            $selectRole->combine($selectAppset,'Union ALL');

            $select = $sql->select();
            $select->from(array("g"=>$selectRole))
                ->columns(array("*"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultAppSettingDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function currencySettingsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Currency Setting");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $this->_view->setTerminal(true);
            if ($request->isPost()) {
                $postData = $request->getPost();
                $currencyName = $this->bsf->isNullCheck( $postData['currencyName'], 'string' );
                $shortName = $this->bsf->isNullCheck( $postData['shortName'], 'string' );
                $decimalName = $this->bsf->isNullCheck( $postData['decimalName'], 'string' );
                $decimalShortName = $this->bsf->isNullCheck( $postData['decimalShortName'], 'string' );
                $decimalLength = $this->bsf->isNullCheck( $postData['decimalLength'], 'number' );
                $abbreviation = $this->bsf->isNullCheck( $postData['abbreviation'], 'string' );
                $country = $this->bsf->isNullCheck( $postData['country'], 'number' );
                $digitGrouping = $this->bsf->isNullCheck( $postData['digitGrouping'], 'string' );
                $summaryUnit = $this->bsf->isNullCheck( $postData['summaryUnit'], 'number' );
                $summaryText = $this->bsf->isNullCheck( $postData['summaryText'], 'string' );

                $reqtype = $this->bsf->isNullCheck( $postData['reqtype'], 'string' );
                try{
                    $connection = $dbAdapter->getDriver()->getConnection();
                    switch($reqtype) {
                        case 'add':
                            $connection->beginTransaction();

                            $insert = $sql->insert();
                            $insert->into('WF_CurrencyMaster');
                            $insert->Values(array('CurrencyName' => $currencyName, 'CurrencyShort' => $shortName, 'DecimalName' => $decimalName, 'DecimalShort' =>$decimalShortName
                            , 'DecimalLength' => $decimalLength, 'Abbreviation' => $abbreviation,'CountryId' => $country,'CreatedUserId' => $this->auth->getIdentity()->UserId
                            ,'CreatedDate' => date('Y-m-d'),'DigitGroup' => $digitGrouping,'SummaryGroupUnit' => $summaryUnit,'SummaryGroupText' => $summaryText));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $currencyId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $connection->commit();

                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_CurrencyMaster' ))
                                ->where("a.CurrencyId=$currencyId AND DeleteFlag='0'");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            return $this->getResponse()->setContent(json_encode($results));
                            break;
                        case 'update':
                            $connection->beginTransaction();
                            $currencyId = $this->bsf->isNullCheck( $postData['currencyId'], 'number' );
                            $update = $sql->update();
                            $update->table('WF_CurrencyMaster');
                            $update->set(array('CurrencyName' => $currencyName, 'CurrencyShort' => $shortName, 'DecimalName' => $decimalName, 'DecimalShort' =>$decimalShortName
                            , 'DecimalLength' => $decimalLength, 'Abbreviation' => $abbreviation,'CountryId' => $country,'ModifiedUserId' => $this->auth->getIdentity()->UserId
                            ,'ModifiedDate' => date('Y-m-d'),'DigitGroup' => $digitGrouping,'SummaryGroupUnit' => $summaryUnit,'SummaryGroupText' => $summaryText))
                                ->where("CurrencyId=$currencyId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $connection->commit();

                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_CurrencyMaster' ))
                                ->where("a.CurrencyId=$currencyId AND DeleteFlag='0'");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            return $this->getResponse()->setContent(json_encode($results));
                            break;
                        case 'delete':
                            $currencyId = $this->bsf->isNullCheck( $postData['currencyId'], 'number' );
                            $remarks = $this->bsf->isNullCheck( $postData['remarks'], 'string' );

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table('WF_CurrencyMaster')
                                ->set(array('DeleteFlag' => '1','DeletedDate' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $remarks))
                                ->where(array('CurrencyId' => $currencyId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $connection->commit();
                            return $this->getResponse()->setContent('deleted');
                            break;
                        case "check-name":
                            $currencyId = $this->bsf->isNullCheck( $postData['currencyId'], 'number' );

                            $select = $sql->select();
                            if($currencyId != 0){
                                $select->from( array( 'a' => 'WF_CurrencyMaster' ))
                                    ->columns( array( 'CurrencyId'))
                                    ->where( "CurrencyName='$currencyName' and CurrencyId<>'$currencyId' and DeleteFlag=0");
                            } else{
                                $select->from( array( 'a' => 'WF_CurrencyMaster' ))
                                    ->columns( array( 'CurrencyId'))
                                    ->where( "CurrencyName='$currencyName' and DeleteFlag=0");
                            }

                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            return $this->getResponse()->setContent(json_encode(array('results' => $results)));
                            break;
                        case 'default':
                            return $this->getResponse()->setStatusCode(400)->setContent('Bad Request');
                            break;

                    }
                }
                catch(PDOException $e){
                    $connection->rollback();
                    return $this->getResponse()->setStatusCode(400)->setContent('Error occured');
                }
            }
        } else {
            $select = $sql->select();
            $select->from('WF_CurrencyMaster')
                ->where("DeleteFlag=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->currenyReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('WF_CountryMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_country = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function organisationlevelAction(){
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
//                $postParams = $request->getPost();
//                $cId =json_decode($this->params()->fromPost('cId'));
//                $levelId =$this->params()->fromPost('levelId');
//                $res ="";
//                if(count($cId)>0) {
//                    if($levelId==0) {
//                        $select = $sql->select();
//                        $select->from('WF_TaskTrans')
//                            ->columns(array('RoleName', 'RoleId','ValueApproval'))
//                            ->where(array('RoleId' => $cId));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $l=0;
//                        $k=0;
//                        if(isset($result)) {
//                            foreach ($result as $rData) {
//                                if ($rData['ValueApproval']==1) {
//                                    $l += 1;
//                                    $k += 1;
//                                    $res .= "<tr>
//                                <td width='5%'>" . $k . "</td>
//                                <td width='55%'>" . $rData['RoleName'] . "</td>
//                                <td width='20%'><input type='text'
//                                    name='FromVal_" . $l . "'
//                                    id='FromVal_" . $l . "'
//                                    onblur='return FormatNum(this, 2);'
//                                    onkeypress='return isDecimal(event,this);'
//                                class='tbl_input' value='0.00' />
//                                </td>
//                                <td width='20%' class='tbl_input_td'>
//                                    <input type='text'
//                                    name='ToVal_" . $l . "'
//                                    id='ToVal_" . $l . "'
//                                    onblur='return FormatNum(this, 2);'
//                                    onkeypress='return isDecimal(event,this);'
//                                    class='tbl_input' value='0.00' />
//                                    <input type='hidden' name='aRoleId_" . $l . "'
//                                    id='aRoleId_" . $l . "'
//                                    value='" . $rData['RoleId'] . "'/></td>
//                                </tr>";
//                                } else {
//                                    $l += 1;
//                                    $res .= "<tr style='display:none;'>
//                                <td width='5%'>" . $k . "</td>
//                                <td width='55%'>" . $rData['RoleName'] . "</td>
//                                <td width='20%'><input type='text'
//                                    name='FromVal_" . $l . "'
//                                    id='FromVal_" . $l . "'
//                                    onblur='return FormatNum(this, 2);'
//                                    onkeypress='return isDecimal(event,this);'
//                                class='tbl_input' value='0.00' />
//                                </td>
//                                <td width='20%' class='tbl_input_td'>
//                                    <input type='text'
//                                    name='ToVal_" . $l . "'
//                                    id='ToVal_" . $l . "'
//                                    onblur='return FormatNum(this, 2);'
//                                    onkeypress='return isDecimal(event,this);'
//                                    class='tbl_input' value='0.00' />
//                                    <input type='hidden' name='aRoleId_" . $l . "'
//                                    id='aRoleId_" . $l . "'
//                                    value='" . $rData['RoleId'] . "'/></td>
//                                </tr>";
//                                }
//                            }
//                            $res .= "<input type='hidden' name='froToRowId_0' id='froToRowId_0' value='" . $l . "'>";
//                        }
//                    } else {
//                        $select = $sql->select();
//                        $select->from('WF_TaskTrans')
//                            ->columns(array('RoleName', 'RoleId','ValueApproval'))
//                            ->where(array('RoleId' => $cId));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $l=0;
//                        $k=0;
//                        if(isset($result)) {
//                            foreach ($result as $rData) {
//                                $select = $sql->select();
//                                $select->from("WF_LevelTrans")
//                                    ->columns(array("LevelId", "RoleId", "ValueFrom", "ValueTo"))
//                                    ->where(array('RoleId' => $rData['RoleId'], 'levelId' => $levelId));
//                                $statement = $sql->getSqlStringForSqlObject($select);
//                                $checkVal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
//                                if ($checkVal != "") {
//                                    $fromVal = $checkVal['ValueFrom'];
//                                    $toVal = $checkVal['ValueTo'];
//                                } else {
//                                    $fromVal = "0.00";
//                                    $toVal = "0.00";
//                                }
//                                if($rData['ValueApproval']==1) {
//                                    $l += 1;
//                                    $k += 1;
//                                    $res .= "<tr>
//                                    <td width='5%'>" . $k . "</td>
//                                    <td width='55%'>" . $rData['RoleName'] . "</td>
//                                    <td width='20%'><input type='text'
//                                        name='FromVal_" . $l . "'
//                                        id='FromVal_" . $l . "'
//                                        onblur='return FormatNum(this, 2);'
//                                        onkeypress='return isDecimal(event,this);'
//                                    class='tbl_input' value='" . $fromVal . "' />
//                                    </td>
//                                    <td width='20%' class='tbl_input_td'>
//                                        <input type='text'
//                                        name='ToVal_" . $l . "'
//                                        id='ToVal_" . $l . "'
//                                        onblur='return FormatNum(this, 2);'
//                                        onkeypress='return isDecimal(event,this);'
//                                        class='tbl_input' value='" . $toVal . "' />
//                                        <input type='hidden' name='aRoleId_" . $l . "'
//                                        id='aRoleId_" . $l . "'
//                                        value='" . $rData['RoleId'] . "'/></td>
//                                    </tr>";
//                                } else {
//                                    $l += 1;
//                                    $res .= "<tr style='display:none;'>
//                                    <td width='5%'>" . $l . "</td>
//                                    <td width='55%'>" . $rData['RoleName'] . "</td>
//                                    <td width='20%'><input type='text'
//                                        name='FromVal_" . $l . "'
//                                        id='FromVal_" . $l . "'
//                                        onblur='return FormatNum(this, 2);'
//                                        onkeypress='return isDecimal(event,this);'
//                                    class='tbl_input' value='" . $fromVal . "' />
//                                    </td>
//                                    <td width='20%' class='tbl_input_td'>
//                                        <input type='text'
//                                        name='ToVal_" . $l . "'
//                                        id='ToVal_" . $l . "'
//                                        onblur='return FormatNum(this, 2);'
//                                        onkeypress='return isDecimal(event,this);'
//                                        class='tbl_input' value='" . $toVal . "' />
//                                        <input type='hidden' name='aRoleId_" . $l . "'
//                                        id='aRoleId_" . $l . "'
//                                        value='" . $rData['RoleId'] . "'/></td>
//                                        </tr>";
//                                }
//                            }
//                            $res .= "<input type='hidden' name='froToRowId_".$levelId."' id='froToRowId_".$levelId."' value='" . $l . "'>";
//                        }
//                    }
//                }
//                $this->_view->setTerminal(true);
//                $response = $this->getResponse()->setContent($res);
//                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//                try{

//                    $insert = $sql->insert('WF_LevelMaster');
//                    $insert->values(array(
//                        'LevelName'  => $postParams['levelName_0'],
////                        'Rate'    => $postParams['rate'],
////                        'Percentage'    => $postParams['percentage'],
////                        'Lumpsum'    => $postParams['lumpsum']
//                    ));
//                    $statement = $sql->getSqlStringForSqlObject($insert);
//                    $results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    $levelId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                    $update = $sql->update("WF_LevelMaster");
//                    $update->set(array("OrderId"=>$levelId))
//                        ->where(array("LevelId"=>$levelId));
//                    $updateStmt = $sql->getSqlStringForSqlObject($update);
//                    $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                    $levelId=0;
//
//                    $fromToValRowId = $this->bsf->isNullCheck($postParams['criticalrowid_'.$levelId], 'number');
//                    if($fromToValRowId > 0) {
//                        for($i=1;$i<=$fromToValRowId;$i++) {
//                            $iroleId= $this->bsf->isNullCheck($postParams['criticalroleid_'.$levelId .'_'.$i], 'number');
//                            $bFound =  isset($postData['chkcriticalrole_'.$levelId.'_'.$iroleId]) ? 1 : 0;
//                            if ($bFound==1) {
//                                $insert = $sql->insert('WF_LevelTrans');
//                                $insert->values(array(
//                                    'RoleId' => $iroleId,
//                                    'LevelId' => $levelId,
//                                    'ValueFrom' => $this->bsf->isNullCheck($postParams['fromvalue_' . $levelId . '_' . $iroleId], 'number'),
//                                    'ValueTo' => $this->bsf->isNullCheck($postParams['tovalue_' . $levelId . '_' . $iroleId], 'number')
//                                ));
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//                        }
//                    }
//
//                    $fromToValRowId = $this->bsf->isNullCheck($postParams['variancerowid_'.$levelId], 'number');
//                    if($fromToValRowId > 0) {
//                        for($i=1;$i<=$fromToValRowId;$i++) {
//                            $iroleId= $this->bsf->isNullCheck($postParams['varianceroleid_'.$levelId .'_'.$i], 'number');
//                            $bFound =  isset($postData['chkvariancerole_'.$levelId.'_'.$iroleId]) ? 1 : 0;
//                            if ($bFound==1) {
//                                $insert = $sql->insert('WF_LevelVariant');
//                                $insert->values(array(
//                                    'RoleId' => $iroleId,
//                                    'LevelId' => $levelId,
//                                    'Variant' => $this->bsf->isNullCheck($postParams['variance_' . $levelId . '_' . $iroleId], 'number'),
//                                ));
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//                        }
//                    }

//                    $fromToValRowId = $this->bsf->isNullCheck($postParams['froToRowId_0'], 'number');
//
//                    if($fromToValRowId > 0) {
//                        for($i=1;$i<=$fromToValRowId;$i++) {
//
//                            $insert = $sql->insert('WF_LevelTrans');
//                            $insert->values(array(
//                                'RoleId'  => $this->bsf->isNullCheck($postParams['aRoleId_'.$i], 'number'),
//                                'LevelId'  => $levelId,
//                                'ValueFrom' => $this->bsf->isNullCheck($postParams['FromVal_'.$i], 'number'),
//                                'ValueTo' => $this->bsf->isNullCheck($postParams['ToVal_'.$i], 'number')
//                            ));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }
//                    $rowCountValueApp = $this->bsf->isNullCheck($postParams['hiddenValRowId'], 'number');
//                    if($rowCountValueApp!="" || $rowCountValueApp!=0)
//                    {
//                        for ($x = 1; $x <= $rowCountValueApp; $x++) {
//                            $insert = $sql->insert('WF_LevelVariant');
//                            $insert->values(array(
//                                'RoleId'  => $postParams['hiddenRoleId_'.$x],
//                                'LevelId'  => $levelId,
//                                'Variant' => $postParams['value_'.$x],
//                                'VariantType' => $postParams['valApproveType_'.$x]
//                            ));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }



//                    $connection->commit();
//                    $FeedId = $this->params()->fromQuery('FeedId');
//                    $AskId = $this->params()->fromQuery('AskId');
//                    if(isset($FeedId) && $FeedId!="") {
//                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationlevel"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
//                    } else {
//                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationlevel"));
//                    }

//                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationlevel"));
//                }
//                catch(PDOException $e){
//                    $connection->rollback();
//                    print "Error!: " . $e->getMessage() . "</br>";
//                }
            } else {
                $select = $sql->select();
                $select->from(array("a"=>'WF_LevelMaster'))
                    ->columns(array('LevelId','LevelName','OrderId'))
                    ->where("DeleteFlag='0'");
                $select->order('OrderId asc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultLevelReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from(array("a"=>'WF_TaskTrans'))
//                    ->columns(array('RoleId', 'RoleName'));
//                $select->where("RoleType='A'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultCriticalAct = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("RoleId","RoleName"));
//                $select->where("RoleType='V'");
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->ResultValueApproval = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                //Fill Level Details
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("RoleId","RoleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->ResultCriticalActSel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_LevelTrans"))
//                    ->columns(array("RoleId","LevelId",'ValueFrom','ValueTo'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->criticalLevelTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("RoleId","RoleName"))
//                    ->where(array('RoleType'=>'V'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->ResultValueAppLevel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_LevelVariant"))
//                    ->columns(array("RoleId","LevelId",'Variant'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->varianceLevelTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from(array("a"=>"WF_LevelVariant"))
//                    ->columns(array("LevelId", "RoleId","Variant","VariantType"), array("RoleName"))
//                    ->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array("RoleName"), $select::JOIN_INNER);
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->ResultValueAppLevel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function orglevelupdateAction(){
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
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $levelId = $postParams['LevelId'];

                    $delete = $sql->delete();
                    $delete->from('WF_LevelTrans')
                        ->where(array('LevelId'=>$levelId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WF_LevelVariant')
                        ->where(array('LevelId'=>$levelId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

//                    $update = $sql->update("WF_LevelMaster");
//                    $update->set(array("LevelName"=>$postParams['levelName'],
//                        'Rate'    => $postParams['rate'],
//                        'Percentage'    => $postParams['percentage'],
//                        'Lumpsum'    => $postParams['lumpsum']))
//                        ->where(array("LevelId"=>$levelId));
//                    $updateStmt = $sql->getSqlStringForSqlObject($update);
//                    $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    //CriticalRole
                    $fromToValRowId = $this->bsf->isNullCheck($postParams['criticalrowid_'.$levelId], 'number');
                    if($fromToValRowId > 0) {
                        for($i=1;$i<=$fromToValRowId;$i++) {
                            $iroleId= $this->bsf->isNullCheck($postParams['criticalroleid_'.$levelId .'_'.$i], 'number');
                            $bFound =  isset($postData['chkcriticalrole_'.$levelId.'_'.$iroleId]) ? 1 : 0;
                            if ($bFound==1) {
                                $insert = $sql->insert('WF_LevelTrans');
                                $insert->values(array(
                                    'RoleId' => $iroleId,
                                    'LevelId' => $levelId,
                                    'ValueFrom' => $this->bsf->isNullCheck($postParams['fromvalue_' . $levelId . '_' . $iroleId], 'number'),
                                    'ValueTo' => $this->bsf->isNullCheck($postParams['tovalue_' . $levelId . '_' . $iroleId], 'number')
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }

                    $fromToValRowId = $this->bsf->isNullCheck($postParams['variancerowid_'.$levelId], 'number');
                    if($fromToValRowId > 0) {
                        for($i=1;$i<=$fromToValRowId;$i++) {
                            $iroleId= $this->bsf->isNullCheck($postParams['varianceroleid_'.$levelId .'_'.$i], 'number');
                            $bFound =  isset($postData['chkvariancerole_'.$levelId.'_'.$iroleId]) ? 1 : 0;
                            if ($bFound==1) {
                                $insert = $sql->insert('WF_LevelVariant');
                                $insert->values(array(
                                    'RoleId' => $iroleId,
                                    'LevelId' => $levelId,
                                    'Variant' => $this->bsf->isNullCheck($postParams['variance_' . $levelId . '_' . $iroleId], 'number'),
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }

//                    $rowCountValueApp = $postParams['hiddenValRowId'];
//                    if($rowCountValueApp!="" || $rowCountValueApp!=0)
//                    {
//                        for ($x = 1; $x <= $rowCountValueApp; $x++) {
//                            $insert = $sql->insert('WF_LevelVariant');
//                            $insert->values(array(
//                                'RoleId'  => $this->bsf->isNullCheck($postParams['hiddenRoleId_'.$x],'number'),
//                                'LevelId'  => $this->bsf->isNullCheck($levelId,'number'),
//                                'Variant' => $this->bsf->isNullCheck($postParams['value_'.$x],'number'),
//                                'VariantType' => $this->bsf->isNullCheck($postParams['valApproveType_'.$x],'string')
//                            ));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }
                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationlevel"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }
        }
    }

    public function deleteorglevelAction(){
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
                    $LevelId = $this->bsf->isNullCheck($this->params()->fromPost('LevelId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $levelName = $this->bsf->isNullCheck($this->params()->fromPost('levelName'), 'string');

                            $select = $sql->select();
                            $select->from(array("a"=>'WF_LevelMaster'))
                                ->columns(array('LevelId'));
                            $select->where("a.DeleteFlag='0' And a.LevelName='$levelName' and a.LevelId<> $LevelId");
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
                            $select->from(array("a"=>'WF_Users'))
                                ->columns(array('LevelId'));
                            $select->where("a.DeleteFlag='0' And a.LevelId=$LevelId");
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $levelCheck = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if(count($levelCheck)>0) {
                                $response->setStatusCode(201)->setContent( $status );
                                return $response;
                            } else {
                                $connection->beginTransaction();
                                $update = $sql->update();
                                $update->table('WF_LevelMaster')
                                    ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                    ->where(array('LevelId' => $LevelId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $connection->commit();

                                $status = 'deleted';
                                $response->setStatusCode(200)->setContent($status);
                                return $response;
                                break;
                            }
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

            }
        }
    }



    public function newsAction(){
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


        //Register values//

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                //update on/off//
                $update = $sql->update();
                $update->table('WF_News');
                $update->set(array(
                    'Type'  => $this->bsf->isNullCheck($postParams['nCheck'],'number'),
                ));
                $update->where(array('NewsId'=>$this->bsf->isNullCheck($postParams['newsId'],'number')));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
//                //Write your Normal form post code here
//                $postParams = $request->getPost();
//                //Print_r($postParams);die;
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//                //add reminders//
//                $insert  = $sql->insert('WF_News');
//                $newData = array(
//                    'Type'   => $this->bsf->isNullCheck($postParams['n_check'],'number'),
//                    'NDescription'  => $this->bsf->isNullCheck($postParams['n_description'],'string'),
//                    'FromDate' =>date('Y-m-d',strtotime($postParams['frm_date'])),
//                    'ToDate'  =>date('Y-m-d',strtotime($postParams['to_date'])),
//                    'CreatedDate'=>date('Y-m-d H:i:s')
//                );
//                $insert->values($newData);
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                $connection->commit();
//                $FeedId = $this->params()->fromQuery('FeedId');
//                $AskId = $this->params()->fromQuery('AskId');
//                if(isset($FeedId) && $FeedId!="") {
//                    $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'news'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
//                } else {
//                    $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'news'));
//                }
            }


            $select = $sql->select();
            $select->from('WF_News')
                ->columns(array('NewsId','NDescription','FromDate','ToDate','Type'))
                ->where(array('DeleteFlag' => 0))
                ->order('NewsId desc');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->news = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //begin trans try block example starts


            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function newsDeleteAction(){
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
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('WF_News')
                        ->set(array('DeleteFlag' => '1','DeletedDate' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('NewsId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    //$this->redirect()->toRoute('workflow/reminder', array('controller' => 'index', 'action' => 'reminder'));
                    $status = 'deleted';

                }
                catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function organisationpositionAction(){
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
                $postParams = $request->getPost();
                $levelIdDetails =  "";
                if($postParams['uMode'] == 'searchpos'){
                    $searchVal = $this->bsf->isNullCheck($postParams['searchVal'],'string');

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_PositionMaster'))
//                        ->join(array("c"=>"WF_positionType"), "a.PositionTypeId=c.PositionTypeId", array("PositionTypeName","PositionTypeId"), $select::JOIN_LEFT)
                        ->columns(array('*'));
//                    if($searchVal!="") {
//                        $select->where("(c.PositionTypeName LIKE '%" . $searchVal . "%' OR a.PositionName LIKE '%" . $searchVal . "%')");
//
//                    }
                    if($searchVal!="") {
                        $select->where("(a.PositionName LIKE '%" . $searchVal . "%')");

                    }

                    $select->where(array("a.DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $levelIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $insert = $sql->insert('WF_PositionMaster');
                    $insert->values(array(
                        'PositionName'  => $postParams['positionName_0']
//                        'DeptId'  => $postParams['departmentId_0'],
//                        'LevelId'  => $postParams['levelId_0'],
//                        'PositionTypeId'  => $postParams['positionTypeId_0']
                    ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $posId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationposition"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationposition"));
                    }
//                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationposition"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }


            $select = $sql->select();
            $select->from(array("a"=>'WF_PositionMaster'))
//                ->join(array("c"=>"WF_positionType"), "a.PositionTypeId=c.PositionTypeId", array("PositionTypeName","PositionTypeId"), $select::JOIN_LEFT)
                ->columns(array('*'));
            $select->where(array("a.DeleteFlag"=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultPosReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function orgpositionupdateAction() {
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
                $mode = $this->bsf->isNullCheck($postParams['mode'],'string');

                if($mode=='add') {
                    $select = $sql->select();
                    $select->from(array("a" => 'WF_PositionType'))
                        ->columns(array('*'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultPositionTypeSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->mode=$mode;
                } else {
                    $posId = $this->bsf->isNullCheck($postParams['posId'],'number');

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_PositionMaster'))
                        ->columns(array('*'));
                    $select->where(array("DeleteFlag"=>0,"PositionId"=>$posId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resultPosRegs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->resultPosRegs=$resultPosRegs;

//                    $select = $sql->select();
//                    $select->from(array("a"=>'WF_PositionType'))
//                        ->columns(array('*'));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->resultPositionTypeSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->mode=$mode;
                }

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{
                    $posId = $this->bsf->isNullCheck($postParams['PosId'],'number');
                    $update = $sql->update("WF_PositionMaster");
                    $update->set(array("PositionName"=>$postParams['positionName']
//                    ,"PositionTypeId"=>$postParams['positionTypeId']
                    ))
                        ->where(array("PositionId"=>$posId));
                    $updateStmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationposition"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

        }
    }

    public function deleteorgpositionAction(){
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
                    $PosId = $this->bsf->isNullCheck($this->params()->fromPost('PosId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $positionName = $this->bsf->isNullCheck($this->params()->fromPost('positionName'), 'string');

                            $select = $sql->select();
                            $select->from(array("a"=>'WF_PositionMaster'))
                                ->columns(array('PositionId'));
                            $select->where("a.DeleteFlag='0' And a.PositionName='$positionName' and a.PositionId<> $PosId");
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
                            $select->from(array("a"=>'WF_Users'))
                                ->columns(array('PositionId'));
                            $select->where("a.DeleteFlag='0' And a.PositionId=$PosId");
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $posCheck = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if(count($posCheck)>0) {
                                $response->setStatusCode(201)->setContent( $status );
                                return $response;
                            } else {
                                $connection->beginTransaction();
                                $update = $sql->update();
                                $update->table('WF_PositionMaster')
                                    ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                    ->where(array('PositionId' => $PosId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $connection->commit();

                                $status = 'deleted';
                                $response->setStatusCode(200)->setContent($status);
                                return $response;
                                break;
                            }
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }
            }
        }
    }


    public function settingsAction(){
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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                try {
                    $pendingSms = $this->bsf->isNullCheck($postParams['pendingsms'], 'number');
                    $pendingEmail = $this->bsf->isNullCheck($postParams['pendingemail'], 'number');
                    $pendingApproval = $this->bsf->isNullCheck($postParams['approval'], 'number');
                    $pendingReady = $this->bsf->isNullCheck($postParams['ready'], 'number');
//                    $mlSd = $this->bsf->isNullCheck($postParams['mlsd'], 'number');
//                    $mlSs = $this->bsf->isNullCheck($postParams['mlss'], 'number');
//                    $mlDs = $this->bsf->isNullCheck($postParams['mlds'], 'number');
                    $allUser = $this->bsf->isNullCheck($postParams['alluser'], 'number');
                    $passwordReset = $this->bsf->isNullCheck($postParams['reset'], 'number');
                    $payReminder = $this->bsf->isNullCheck($postParams['payReminder'], 'number');
                    $smsDate = NULL;
                    if ($postParams['smsdate']) {
                        $smsDate = date('Y-m-d', strtotime($postParams['smsdate']));
                    }
                    $emailDate = NULL;
                    if ($postParams['emaildate']) {
                        $emailDate = date('Y-m-d', strtotime($postParams['emaildate']));
                    }
                    $select = $sql->select();
                    $select->from('WF_GeneralSetting')
                        ->columns(array('SettingId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $settingDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $count = count($settingDetails);
                    if($count>0) {
                        $update = $sql->update();
                        $update->table( 'WF_GeneralSetting' )
                            ->set(array('PendingWorkEMail'  => $pendingEmail,
                                'PendingWorkSMS'  => $pendingSms,
                                'PendingWorkEMailDate'  => $emailDate,
                                'PendingWorkSMSDate'  => $smsDate,
                                'PasswordReset'  => $passwordReset,
                                'AutoApproval'  => $pendingApproval,
                                'AutoReady'  => $pendingReady,
//                                'MultiLoginSD'  => $mlSd,
//                                'MultiLoginSS'  => $mlSs,
//                                'MultiLoginDS'  => $mlDs,
                                'MailAllUser' => $allUser,
                                'PaymentReminderDays' => $payReminder));
                        $statement = $sql->getSqlStringForSqlObject( $update );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    } else {
                        $insert = $sql->insert('WF_GeneralSetting');
                        $insert->values(array('PendingWorkEMail'  => $pendingEmail,
                            'PendingWorkSMS'  => $pendingSms,
                            'PendingWorkEMailDate'  => $emailDate,
                            'PendingWorkSMSDate'  => $smsDate,
                            'PasswordReset'  => $passwordReset,
                            'AutoApproval'  => $pendingApproval,
                            'AutoReady'  => $pendingReady,
//                            'MultiLoginSD'  => $mlSd,
//                            'MultiLoginSS'  => $mlSs,
//                            'MultiLoginDS'  => $mlDs,
                            'MailAllUser' => $allUser,
                            'PaymentReminderDays' => $payReminder));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "settings"));


                } catch (PDOException $e) {
                    $connection->rollback();
                }
            } else {
                $select = $sql->select();
                $select->from('WF_GeneralSetting')
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->settingDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function userViewAction(){
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
                $postParams = $request->getPost();

                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                //Write your Normal form post code here

            } else {
                $userId = $this->bsf->isNullCheck($this->params()->fromRoute('userId'), 'number');

                $select = $sql->select();
                $select->from(array("a"=>"WF_Users"))
                    ->join(array("b"=>"WF_LevelMaster"), "a.LevelId=b.LevelId", array("LevelName"), $select::JOIN_LEFT)
                    ->join(array("c"=>"WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name"), $select::JOIN_LEFT)
                    ->join(array("d"=>"WF_PositionMaster"), "a.PositionId=d.PositionId", array("PositionName"), $select::JOIN_LEFT)
                    ->join(array("e"=>"WF_CompanyMaster"), "a.CompanyId=e.CompanyId", array("CompanyName"), $select::JOIN_LEFT)
                    ->join(array("f"=>"WF_CostCentre"), "a.DefaultCCId=f.CostCentreId", array("CostCentreName"), $select::JOIN_LEFT)
                    ->columns(array("*"))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>"WF_UserSuperiorTrans"))
                    ->join(array("b"=>"WF_Users"), "a.SUserId=b.UserId", array("EmployeeName"), $select::JOIN_LEFT)
                    ->columns(array())
                    ->where(array('a.UserId'=>$userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->superiorDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subQuery = $sql->select();
                $subQuery->from('WF_UserCostCentreTrans')
                    ->columns(array('CostCentreId'))
                    ->where(array("UserId" => $userId));

                $select = $sql->select();
                $select->from('WF_CostCentre')
                    ->columns(array('CostCentreId','CostCentreName'));
                $select->where->expression('CostCentreId NOT IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Feed Query
                $select = $sql->select();
                $select->from(array('a' =>'WF_Feeds'))
                    ->columns(array('*',
                        'FeedLikesCount'=>new Expression("(select COUNT(LikeId) from WF_Likes where WF_Likes.FeedId = a.FeedId)"),
                        'UserLike'=>new Expression("(select COUNT(LikeId) from WF_Likes where WF_Likes.FeedId = a.FeedId AND WF_Likes.UserId = '".$this->auth->getIdentity()->UserId."')"),
                        'FeedCommentsCount'=>new Expression("(select COUNT(CommentId) from WF_Comments where WF_Comments.FeedId = a.FeedId)"),
                        'FeedParentCount'=>new Expression("(select COUNT(FeedId) from WF_Feeds as ParentFeeds where ParentFeeds.ParentId = a.FeedId AND ParentFeeds.DeleteFlag = '0')"),
                    ))
                    ->join(array('b' => 'WF_BirthDyWishes'), 'a.BirthdayId=b.BirthdyId', array('BdayWish'=>'Description','BdayWishBy'=>'UserId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_PhotoShare'), 'a.PhotoShareId=c.PhotoShareId', array('PhotoMessage'=>'Message'), $select::JOIN_LEFT)
                    ->join(array('h' => 'WF_users'), 'a.UserId=h.UserId', array('UserName'=>'EmployeeName','UserAvatar'=>'Userlogo'), $select::JOIN_LEFT)
                    ->where("((a.FeedType = 'birthday' AND a.DeleteFlag = '0' AND a.BirthdayId IN (Select BirthdyId from WF_BirthDyWishes where WisherId = '".$userId."'))
						OR (a.FeedType = 'status' AND a.DeleteFlag = '0' AND a.UserId = '".$userId."')
						OR (a.FeedType = 'photo' AND a.DeleteFlag = '0' AND a.UserId = '".$userId."'))")
                    ->order('a.createdDate DESC')
                    ->limit(10)
                    ->offset(0);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->feedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Total Feeds Count
                $select = $sql->select();
                $select->from(array('a' =>'WF_Feeds'))
                    ->columns(array('TotalFeeds'=>new Expression("count(*)")))
                    ->where("((a.FeedType = 'birthday' AND a.DeleteFlag = '0' AND a.BirthdayId IN (Select BirthdyId from WF_BirthDyWishes where WisherId = '".$userId."'))
						OR (a.FeedType = 'status' AND a.DeleteFlag = '0' AND a.UserId = '".$userId."')
						OR (a.FeedType = 'photo' AND a.DeleteFlag = '0' AND a.UserId = '".$userId."'))");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalFeeds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function approvalSettingsAction(){
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
                $postParams = $request->getPost();
                $sType= $this->bsf->isNullCheck($postParams['TypeId'],'number');
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                $arrList = array();
                if ($sType==1) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_TaskTrans"))
                        ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("RoleId"),'Name'=>new Expression("RoleName")));
                    if ($searchStr=="") $select->where("a.RoleType='A'");
                    else  $select->where("a.RoleType='A' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.RoleName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($sType==2) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_TaskTrans"))
                        ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
                        ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("RoleId"),'Name'=>new Expression("RoleName")));
                    if ($searchStr=="") $select->where("a.TaskType='C'");
                    else  $select->where("a.TaskType ='C' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.RoleName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }  else if ($sType==3) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_AlertMaster"))
                        ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("AlertId"),'Name'=>new Expression("AlertName")));
                    if ($searchStr=="") $select->where("a.AlertType='I'");
                    else  $select->where("a.AlertType='I' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.AlertName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }  else if ($sType==4) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_AlertMaster"))
                        ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("AlertId"),'Name'=>new Expression("AlertName")));
                    if ($searchStr=="") $select->where("a.AlertType='E'");
                    else  $select->where("a.AlertType='E' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.AlertName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($sType==5) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_AlertMaster"))
                        ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("AlertId"),'Name'=>new Expression("AlertName")));
                    if ($searchStr=="") $select->where("a.AlertType='R'");
                    else  $select->where("a.AlertType='R' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.AlertName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($sType==6) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_AlertMaster"))
                        ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("AlertId"),'Name'=>new Expression("AlertName")));
                    if ($searchStr=="") $select->where("a.AlertType='P'");
                    else  $select->where("a.AlertType='P' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.AlertName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($sType==7) {
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_AlertMaster"))
                        ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                        ->columns(array('Id'=>new Expression("AlertId"),'Name'=>new Expression("AlertName")));
                    if ($searchStr=="") $select->where("a.AlertType='C'");
                    else  $select->where("a.AlertType='C' and (c.ModuleName LIKE '%" . $searchStr . "%' OR a.AlertName LIKE '%" . $searchStr . "%')");
                    $select->order('c.ModuleName asc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($arrList));
                return $response;

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{
                    $typeFound = $postParams['typeL'];
                    if($typeFound == "1") { //Approval
                        $roleId = $postParams['RoleId'];
                        $approvalBased="";
                        $not_required=0;
                        $value_required=0;
                        $special_required=0;
                        $multiApproval=0;
                        if(isset($postParams['not_required_'.$roleId]) ) {
                            $not_required=1;
                        }

                        if(isset($postParams['va_required_'.$roleId]) ) {
                            $value_required=1;
                        }

                        if(isset($postParams['sp_required_'.$roleId]) ) {
                            $special_required=1;
                        }

                        if($postParams['approvalBased'] !="N") {
                            $multiApproval=1;
                            $approvalBased= $postParams['approvalBased'];
                        }

                        $update = $sql->update("WF_TaskTrans");
                        $update->set(array("MultiApproval"=>$multiApproval, "ValueApproval"=>$value_required
                        ,"NotRequired"=>$not_required,"SpecialApproval"=>$special_required
                        ,"ApprovalBased"=>$approvalBased,"MaxLevel"=>$postParams['maxLevel']))
                            ->where(array("RoleId"=>$roleId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if($typeFound == "3"){ //Alert-Internal
                        $alertId = $postParams['AlertId'];
                        $screen=0;
                        $email=0;
                        $sms=0;
                        if(isset($postParams['screen_'.$alertId]) ) {
                            $screen=1;
                        }
                        if(isset($postParams['eMail_'.$alertId]) ) {
                            $email=1;
                        }
                        if(isset($postParams['sms_'.$alertId]) ) {
                            $sms=1;
                        }
                        $frequecyType = "None";
                        $fFrequecyPeriod = 0;
                        $informType = "I";
                        $informPeriodType = "None";
                        $iInformPeriod = 0;
                        $alertMsg ="";

                        $update = $sql->update("WF_AlertMaster");
                        $update->set(array("Screen"=>$screen, "EMail"=>$email,"SMS"=>$sms
                        ,"FrequencyType"=>$frequecyType,"FrequencyPeriod"=>$fFrequecyPeriod,"InformType"=>$informType
                        ,"InformPeriodType"=>$informPeriodType,"InformPeriod"=>$iInformPeriod,"AlertMsg"=>$alertMsg ))
                            ->where(array("AlertId"=>$alertId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }  else if($typeFound == "4"){ //Alert-External
                        $alertId = $postParams['AlertId'];
                        $screen=0;
                        $email=0;
                        $sms=0;

                        if(isset($postParams['eMail_'.$alertId]) ) {
                            $email=1;
                        }
                        if(isset($postParams['sms_'.$alertId]) ) {
                            $sms=1;
                        }
                        $frequecyType = "None";
                        $fFrequecyPeriod = 0;
                        $informType = "I";
                        $informPeriodType = "None";
                        $iInformPeriod = 0;
                        $alertMsg =$postParams['alert_message'];

                        $update = $sql->update("WF_AlertMaster");
                        $update->set(array("Screen"=>$screen, "EMail"=>$email,"SMS"=>$sms
                        ,"FrequencyType"=>$frequecyType,"FrequencyPeriod"=>$fFrequecyPeriod,"InformType"=>$informType
                        ,"InformPeriodType"=>$informPeriodType,"InformPeriod"=>$iInformPeriod,"AlertMsg"=>$alertMsg ))
                            ->where(array("AlertId"=>$alertId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if($typeFound == "5"){ //Alert-Reminder
                        $alertId = $postParams['AlertId'];
                        $screen=0;
                        $email=0;
                        $sms=0;
                        if(isset($postParams['screen_'.$alertId]) ) {
                            $screen=1;
                        }
                        if(isset($postParams['eMail_'.$alertId]) ) {
                            $email=1;
                        }
                        if(isset($postParams['sms_'.$alertId]) ) {
                            $sms=1;
                        }
                        $frequecyType = $postParams['frequencyPeriodType'];
                        $frequecyPeriod=0;
                        if($frequecyType != "None"){
                            $frequecyPeriod = $postParams['frequencyPeriodCount'];
                        }
                        $informType = $postParams['informType'];
                        $informPeriodType ="None";
                        $iInformPeriod=0;
                        if($informType != "I"){
                            $informPeriodType = $postParams['informPeriodType'];
                            if($informPeriodType != "None"){
                                $iInformPeriod =$postParams['informPeriodCount'];
                            }
                        }
                        $alertMsg ="";

                        $update = $sql->update("WF_AlertMaster");
                        $update->set(array("Screen"=>$screen, "EMail"=>$email,"SMS"=>$sms
                        ,"FrequencyType"=>$frequecyType,"FrequencyPeriod"=>$frequecyPeriod,"InformType"=>$informType
                        ,"InformPeriodType"=>$informPeriodType,"InformPeriod"=>$iInformPeriod,"AlertMsg"=>$alertMsg ))
                            ->where(array("AlertId"=>$alertId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if($typeFound == "6"){ //Alert-Report
                        $alertId = $postParams['AlertId'];
                        $effort_form = date('Y-m-d', strtotime($postParams['effort_form']));
                        $screen=0;
                        $email=0;
                        if(isset($postParams['screen_'.$alertId]) ) {
                            $screen=1;
                        }
                        if(isset($postParams['eMail_'.$alertId]) ) {
                            $email=1;
                        }
                        $FTime="";
                        $FDay =0;
                        $FWeek=0;
                        $frequecyType = $postParams['frequencyPeriodType'];
                        if($frequecyType == "Day"){
                            $FTime = $postParams['fTime'];
                        } else if($frequecyType == "Month"){
                            $FDay = $postParams['fDay'];
                        } else if($frequecyType == "Week"){
                            $FWeek = $postParams['fWeek'];
                        }

                        $frequecyPeriod = $postParams['frequencyPeriodCount'];

                        $update = $sql->update("WF_AlertMaster");
                        $update->set(array("Screen"=>$screen, "EMail"=>$email
                        ,"FrequencyType"=>$frequecyType,"FrequencyPeriod"=>$frequecyPeriod,"SDate"=>$effort_form
                        ,"FTime"=>$FTime,"FDay"=>$FDay,"FWeek"=>$FWeek
                        ))
                            ->where(array("AlertId"=>$alertId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if($typeFound == "7"){ //Custom-Reminder-Alert
                        $alertId = $postParams['AlertId'];
                        $effort_form = date('Y-m-d', strtotime($postParams['effort_form']));
                        $screen=0;
                        $email=0;
                        $sms=0;
                        if(isset($postParams['screen_'.$alertId]) ) {
                            $screen=1;
                        }
                        if(isset($postParams['eMail_'.$alertId]) ) {
                            $email=1;
                        }
                        if(isset($postParams['sms_'.$alertId]) ) {
                            $sms=1;
                        }
                        $FTime="";
                        $FDay =0;
                        $FWeek=0;
                        $frequecyType = $postParams['frequencyPeriodType'];
                        if($frequecyType == "Day"){
                            $FTime = $postParams['fTime'];
                        } else if($frequecyType == "Month"){
                            $FDay = $postParams['fDay'];
                        } else if($frequecyType == "Week"){
                            $FWeek = $postParams['fWeek'];
                        }

                        $frequecyPeriod = $postParams['frequencyPeriodCount'];

                        $update = $sql->update("WF_AlertMaster");
                        $update->set(array("Screen"=>$screen, "EMail"=>$email,"SMS"=>$sms
                        ,"FrequencyType"=>$frequecyType,"FrequencyPeriod"=>$frequecyPeriod,"SDate"=>$effort_form
                        ,"FTime"=>$FTime,"FDay"=>$FDay,"FWeek"=>$FWeek
                        ))
                            ->where(array("AlertId"=>$alertId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if($typeFound == "2"){ //Critical Roles
                        $roleId = $postParams['RoleId'];
                        $screen=0;
                        $email=0;
                        $sms=0;
                        if(isset($postParams['screen_'.$roleId]) ) {
                            $screen=1;
                        }
                        if(isset($postParams['eMail_'.$roleId]) ) {
                            $email=1;
                        }
                        if(isset($postParams['sms_'.$roleId]) ) {
                            $sms=1;
                        }
                        $processPeriod = 0;
                        $processType = $postParams['processPeriodType'];
                        if($processType != "None"){
                            $processPeriod = $postParams['processPeriodCount'];
                        }

                        $gracePeriod = 0;
                        $graceType = $postParams['gracePeriodType'];
                        if($graceType != "None"){
                            $gracePeriod = $postParams['gracePeriodCount'];
                        }

                        $FTime="";
                        $FDay =0;
                        $FWeek=0;
                        $frequecyType = $postParams['frequencyPeriodType'];
                        if($frequecyType == "Day"){
                            $FTime = $postParams['fTime'];
                        } else if($frequecyType == "Month"){
                            $FDay = $postParams['fDay'];
                        } else if($frequecyType == "Week"){
                            $FWeek = $postParams['fWeek'];
                        }

                        $frequecyPeriod = $postParams['frequencyPeriodCount'];

                        //delete WF_RoleTrans
                        $select = $sql->delete();
                        $select->from("WF_RoleTrans")
                            ->where(array('RoleId'=>$roleId));
                        $DelRoleTransStatement = $sql->getSqlStringForSqlObject($select);
                        $dbAdapter->query($DelRoleTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert('WF_RoleTrans');
                        $insert->values(array('RoleId'  => $roleId,'ProcessType'  => $processType,'ProcessPeriod'  => $processPeriod,
                            'IntervalType'  => $graceType,'IntervalPeriod'  => $gracePeriod,
                            'FreqencyType'  => $frequecyType,'FreqencyPeriod'  => $frequecyPeriod,
                            'FTime'  => $FTime,'FDay'  => $FDay,'FWeek' => $FWeek,
                            'Screen' => $screen,'Email' => $email,'SMS' => $sms
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    //$this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "approval-settings"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $type = $this->bsf->isNullCheck($this->params()->fromRoute('type'), 'string');
            if ($type =="") $type="1";
            $this->_view->type =$type;
            $sTypeName ="Approval";
            $select = $sql->select();

            $select->from(array("a"=>"WF_TaskTrans"))
                ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
                ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                ->columns(array('Id'=>new Expression("RoleId"),'Name'=>new Expression("RoleName")))
                ->where("a.RoleType='A'");
            $select->order('c.ModuleName asc');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultAppSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            if($type=="1") { //Approval
//                $sTypeName="Approval";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("ModuleId" => new Expression("b.ModuleId"),"ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
//                    ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->join(array("d" => "WF_ActivityRoleTrans"), 'a.RoleId=d.RoleId', array(), $select::JOIN_LEFT)
//                    ->where("a.RoleType='A'");
//                $select->group(new Expression("b.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("RoleId", "RoleName","MultiApproval","ValueApproval","ApprovalBased","NotRequired","SpecialApproval","MaxLevel"
//                    ,"ModuleName" => new Expression("c.ModuleName"),"RoleUsed" => new Expression("Case When d.RoleId is Null then Convert(bit,0,0) else Convert(bit,1,1) end ")))
//                    ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
//                    ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->join(array("d" => "WF_ActivityRoleTrans"), 'a.RoleId=d.RoleId', array(), $select::JOIN_LEFT)
//                    ->where("a.RoleType='A'");
//                $select->group(new Expression("a.RoleId,a.RoleName,c.ModuleName,a.MultiApproval,a.ValueApproval,a.ApprovalBased,d.RoleId,a.NotRequired,a.SpecialApproval,a.MaxLevel"));
//                $select->order(new Expression("c.ModuleName,a.RoleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultAppSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="3"){ //Alert-Internal
//                $sTypeName="Alert-Internal";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("ModuleId","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='I'");
//                $select->group(new Expression("a.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("AlertId", "AlertName","Screen","EMail","SMS","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='I'");
//                $select->order(new Expression("c.ModuleName,a.AlertName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultInternalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="4"){ //Alert-External
//                $sTypeName="Alert-External";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("ModuleId","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='E'");
//                $select->group(new Expression("a.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("AlertId", "AlertName","Screen","EMail","SMS","AlertMsg","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='E'");
//                $select->order(new Expression("c.ModuleName,a.AlertName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultInternalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="5"){ //Alert-Reminder
//                $sTypeName="Alert-Reminder";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("ModuleId","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='R'");
//                $select->group(new Expression("a.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("AlertId", "AlertName","Screen","EMail","SMS","AlertMsg","FrequencyType","FrequencyPeriod","InformType","InformPeriodType","InformPeriod","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='R'");
//                $select->order(new Expression("c.ModuleName,a.AlertName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultInternalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="6"){ //Alert-Report
//                $sTypeName="Alert-Reports";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("ModuleId","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='P'");
//                $select->group(new Expression("a.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("AlertId", "AlertName","Screen","EMail","SMS","FWeek","FrequencyType","FrequencyPeriod","SDate","FTime","FDay","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='P'");
//                $select->order(new Expression("c.ModuleName,a.AlertName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultInternalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="7"){ //Custom-Reminder-Alert
//                $sTypeName="Alert-Custom-Reminder";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("ModuleId","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.Moduleid=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='C'");
//                $select->group(new Expression("a.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_AlertMaster"))
//                    ->columns(array("AlertId", "AlertName","Screen","EMail","SMS","FWeek","FrequencyType","FrequencyPeriod","SDate" => new Expression("CONVERT(varchar(10),a.SDate,105)"),"FTime","FDay","ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("c" => "WF_Module"), 'a.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->where("AlertType='C'");
//                $select->order(new Expression("c.ModuleName,a.AlertName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultInternalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            } else if($type=="2"){ //Critical Roles
//                $sTypeName="Critical Roles";
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("ModuleId" => new Expression("b.ModuleId"),"ModuleName" => new Expression("c.ModuleName")))
//                    ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
//                    ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->join(array("d" => "WF_ActivityRoleTrans"), 'a.RoleId=d.RoleId', array(), $select::JOIN_INNER)
//                    ->where("a.TaskType='C'");
//                $select->group(new Expression("b.ModuleId,c.ModuleName"));
//                $select->order(new Expression("c.ModuleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultmoduleSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                $select = $sql->select();
//                $select->from(array("a"=>"WF_TaskTrans"))
//                    ->columns(array("RoleId", "RoleName","ModuleName" => new Expression("c.ModuleName")
//                    ,"ProcessType" => new Expression("e.ProcessType"),"ProcessPeriod" => new Expression("e.ProcessPeriod"),"IntervalType"=> new Expression("e.IntervalType")
//                    ,"IntervalPeriod"=> new Expression("e.IntervalPeriod"),"FreqencyType"=> new Expression("e.FreqencyType"),"FreqencyPeriod"=> new Expression("e.FreqencyPeriod")
//                    ,"FTime"=> new Expression("e.FTime"),"FDay"=> new Expression("e.FDay"),"FWeek"=> new Expression("e.FWeek")
//                    ,"Screen"=> new Expression("e.Screen"),"Email"=> new Expression("e.Email"),"SMS"=> new Expression("e.SMS")))
//                    ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
//                    ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array(), $select::JOIN_INNER)
//                    ->join(array("d" => "WF_ActivityRoleTrans"), 'a.RoleId=d.RoleId', array(), $select::JOIN_INNER)
//                    ->join(array("e" => "WF_RoleTrans"), 'a.RoleId=e.RoleId', array(), $select::JOIN_LEFT)
//                    ->where("a.TaskType='C'");
//                $select->group(new Expression("a.RoleId,a.RoleName,c.ModuleName,e.ProcessType,e.ProcessPeriod,e.IntervalType,e.IntervalPeriod,e.FreqencyType,e.FreqencyPeriod,e.FTime,e.FDay,e.FWeek,e.Screen,e.Email,e.SMS"));
//                $select->order(new Expression("c.ModuleName,a.RoleName"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultcriticalSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//            }
            $this->_view->typeName =$sTypeName;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function vouchertypeGenerationAction(){
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
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                try {
                    $typeId = $this->params()->fromPost('typeId');
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    switch($Type) {
                        case 'voucher':
                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_VoucherTypeTrans' ))
                                ->join(array('b' => 'WF_VoucherTypeMaster'), 'a.TypeId=b.TypeId', array(), $select::JOIN_INNER)
                                ->columns( array( 'GenType','PeriodWise','CCId','CompanyId','PreFix','Suffix','StartNo','Width', 'BaseType'=> new Expression("b.BaseType")
                                ,'CompanyRequired'=> new Expression("b.CompanyRequired"),'CCRequired'=> new Expression("b.CCRequired")))
                                ->where( "a.TypeId=$typeId");

                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                            $status = json_encode( $results);
                            $response->setContent($status);
                            return $response;
                            break;
                        case 'period':

                            /*
                            Select A.PeriodId,A.PeriodDescription,A.FromDate,A.ToDate,
                           Case When B.Monthwise is Null then 'No' else 'Yes' End Monthwise,
                           Convert(bit,Case When B.TypeId is Null then 0 else 1 end) Sel from WF_VoucherPeriodMaster A
                           Left Join WF_VoucherTypePeriod B on A.PeriodId=B.PeriodId and B.TypeId=102 and B.CCId=0 and B.CompanyId = 0
                           Order by FromDate
                            */
                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_VoucherPeriodMaster' ))
                                ->join(array('b' => 'WF_VoucherTypePeriod'), new Expression("a.PeriodId=b.PeriodId and b.TypeId='$typeId' and b.CCId=0 and b.CompanyId = 0"), array(), $select::JOIN_LEFT)
                                ->columns( array( 'PeriodId','PeriodDescription',"FromDate" =>new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')") ,"ToDate" =>new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")
                                , 'Monthwise'=> new Expression("Case When b.Monthwise is Null then 'No' else 'Yes' End")
                                ,'Sel'=> new Expression("Convert(bit,Case When b.TypeId is Null then 0 else 1 end)")));
                            $select->order(new Expression("a.FromDate"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_VoucherTypePeriod' ))
                                ->columns( array( 'TypeId','PeriodId','Monthwise','PreFix','Suffix','StartNo','Width'));
                            $select->where( "a.TypeId=$typeId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $periodTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            $select = $sql->select();
                            $select->from( array( 'a' => 'WF_VoucherTypePeriodTrans' ))
                                ->columns( array( 'TypeId','PeriodId','Year','Month','PreFix','Suffix','StartNo','Width'));
                            $select->where( "a.TypeId=$typeId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $periodmonthwiseTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            $response->setContent(json_encode( array('PeriodMaster' => $results, 'PeriodTrans' => $periodTrans, 'PeriodmonthwiseTrans' => $periodmonthwiseTrans)));
                            return $response;
                            break;

                        case 'search':

                            $searchVal = $this->bsf->isNullCheck($this->params()->fromPost('searchVal'), 'string');

                            $select = $sql->select();
                            $select->from(array("a"=>"WF_VoucherTypeMaster"))
                                ->columns(array("TypeId" ,"TypeName" ,"ModuleId" ,"ModuleName" => new Expression("b.ModuleName")))
                                ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array(), $select::JOIN_INNER);
                            $select->order(new Expression("b.ModuleName"));
                            if($searchVal!="") {
                                $select->where("(b.ModuleName LIKE '%" . $searchVal . "%' OR a.TypeName LIKE '%" . $searchVal . "%')");
                            }
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $levelIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $this->_view->setTerminal(true);
                            $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                            return $response;

                    }


                } catch (PDOException $e) {
//                    $connection->rollback();
                    $response->setStatusCode('400');
                }


                //$this->_view->setTerminal(true);
                //$response = $this->getResponse()->setContent($result);
                //return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
//                //Write your Normal form post code here
//                $postParams = $request->getPost();
//
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//                try{
//                    $entryFrom = $this->bsf->isNullCheck($postParams['entryFrom'], 'string');
//                    if($entryFrom=="Voucher"){
//                        $typeId = $this->bsf->isNullCheck($postParams['TypeId'], 'number');
//                        $auto = $this->bsf->isNullCheck($postParams['auto'], 'number');
//                        $period_wise=0;
//                        if(isset($postParams['period_wise_'.$typeId]) ) {
//                            $period_wise=1;
//                        }
//                        $ccRequired=$postParams['ccrequire_'.$typeId];
//                        $coRequired=$postParams['corequire_'.$typeId];
//                        $baseType = $this->bsf->isNullCheck($postParams['baseType_'.$typeId], 'number');
//
//                        $prefix = $this->bsf->isNullCheck($postParams['prefix'], 'string');
//                        $startNo = $this->bsf->isNullCheck($postParams['startNo'], 'number');
//                        $width = $this->bsf->isNullCheck($postParams['width'], 'number');
//                        $suffix = $this->bsf->isNullCheck($postParams['suffix'], 'string');
//
//                        $sBaseType="GE";
//                        if($baseType==3){
//                            $sBaseType="CC";
//                        } else if($baseType==2) {
//                            $sBaseType="CO";
//                        }
//
//                        $update = $sql->update("WF_VoucherTypeMaster");
//                        $update->set(array("BaseType"=>$sBaseType, "CCRequired"=>$ccRequired
//                        ,"CompanyRequired"=>$coRequired))
//                            ->where(array("TypeId"=>$typeId));
//                        $updateStmt = $sql->getSqlStringForSqlObject($update);
//                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $argCompanyId=0;
//                        $argCCId=0;
//                        $select = $sql->select();
//                        $select->from(array("a"=>"WF_VoucherTypeTrans"))
//                            ->columns(array('TypeId'))
//                            ->where("a.TypeId=$typeId and a.CompanyId=$argCompanyId and a.CCId=$argCCId ");
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $transResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        if(count($transResult) > 0) {
//                            $update = $sql->update("WF_VoucherTypeTrans");
//                            $update->set(array("GenType"=>$auto, "PeriodWise"=>$period_wise))
//                                ->where(array("TypeId"=>$typeId, "CompanyId"=>$argCompanyId, "CCId"=>$argCCId));
//                            $updateStmt = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                        } else {
//                            $insert = $sql->insert('WF_VoucherTypeTrans');
//                            $insert->values(array('TypeId'  => $typeId,'GenType'  => $auto,'PeriodWise'  => $period_wise,
//                                'CompanyId'  => $argCompanyId,'CCId'  => $argCCId
//                            ));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//
//                        if($period_wise==0){
//                            $update = $sql->update("WF_VoucherTypeTrans");
//                            $update->set(array("PreFix"=>$prefix, "StartNo"=>$startNo, "Width"=>$width
//                            , "Suffix"=>$suffix))
//                                ->where(array("TypeId"=>$typeId, "CompanyId"=>$argCompanyId, "CCId"=>$argCCId));
//                            $updateStmt = $sql->getSqlStringForSqlObject($update);
//                            $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                        } else {
//                            //PeriowiseUpdate
//                            $PeriodId=0;
//                            $rowid = $this->bsf->isNullCheck($postParams['periodrowid_' . $typeId],'number');
//                            for ( $i = 1; $i <= $rowid; $i++ ) {
//                                $PeriodId = $this->bsf->isNullCheck( $postParams['PeriodId_' . $i], 'number' );
//                                $Monthwise=$this->bsf->isNullCheck( $postParams['monthwise_' . $i], 'number' );
//
//                                $select = $sql->select();
//                                $select->from(array("a"=>"WF_VoucherTypePeriod"))
//                                    ->columns(array('TypeId'))
//                                    ->where("a.TypeId=$typeId and a.PeriodId=$PeriodId and a.CompanyId=$argCompanyId and a.CCId=$argCCId ");
//                                $statement = $sql->getSqlStringForSqlObject($select);
//                                $transPeriodResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                                if(count($transPeriodResult) > 0) {
//                                    $update = $sql->update("WF_VoucherTypePeriod");
//                                    $update->set(array("Monthwise"=>$Monthwise))
//                                        ->where(array("TypeId"=>$typeId, "PeriodId"=>$PeriodId, "CompanyId"=>$argCompanyId, "CCId"=>$argCCId));
//                                    $updateStmt = $sql->getSqlStringForSqlObject($update);
//                                    $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                                } else {
//                                    $insert = $sql->insert('WF_VoucherTypePeriod');
//                                    $insert->values(array('TypeId'  => $typeId, 'PeriodId'  => $PeriodId, 'Monthwise'  => $Monthwise,
//                                        'CompanyId'  => $argCompanyId,'CCId'  => $argCCId
//                                    ));
//                                    $statement = $sql->getSqlStringForSqlObject($insert);
//                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                                }
//                                //echo "PeriodId=";
//                                //echo $PeriodId;
//
//                                //echo "monwise=";
//                                //echo $Monthwise;
//                                if($Monthwise==0){
//                                    $monthrowid = $this->bsf->isNullCheck($postParams['type_'.$i .'_rowid'],'number');
//                                    for ( $j = 1; $j <= $monthrowid; $j++ ) {
//                                        $PeriodId = $this->bsf->isNullCheck( $postParams['type_'.$i .'_PeriodId_' . $j], 'number' );
//                                        $prefix = $this->bsf->isNullCheck( $postParams['type_'.$i .'_prefix_' . $j], 'string' );
//                                        $startNo = $this->bsf->isNullCheck( $postParams['type_'.$i .'_startNo_' . $j], 'number' );
//                                        $width = $this->bsf->isNullCheck( $postParams['type_'.$i .'_width_' . $j], 'number' );
//                                        $suffix = $this->bsf->isNullCheck( $postParams['type_'.$i .'_suffix_' . $j], 'string' );
//
//                                        $update = $sql->update("WF_VoucherTypePeriod");
//                                        $update->set(array("PreFix"=>$prefix, "StartNo"=>$startNo, "Width"=>$width, "Suffix"=>$suffix))
//                                            ->where(array("TypeId"=>$typeId, "PeriodId"=>$PeriodId, "CompanyId"=>$argCompanyId, "CCId"=>$argCCId));
//                                        $updateStmt = $sql->getSqlStringForSqlObject($update);
//                                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                                    }
//                                } else {
//                                    $monthrowid = $this->bsf->isNullCheck($postParams['type_'.$i .'_rowid'],'number');
//                                    for ( $j = 1; $j <= $monthrowid; $j++ ) {
//                                        $PeriodId = $this->bsf->isNullCheck( $postParams['type_'.$i .'_PeriodId_' . $j], 'number' );
//                                        $MonthId = $this->bsf->isNullCheck( $postParams['type_'.$i .'_Month_' . $j], 'number' );
//                                        $YearId = $this->bsf->isNullCheck( $postParams['type_'.$i .'_Year_' . $j], 'number' );
//                                        $prefix = $this->bsf->isNullCheck( $postParams['type_'.$i .'_prefix_' . $j], 'string' );
//                                        $startNo = $this->bsf->isNullCheck( $postParams['type_'.$i .'_startNo_' . $j], 'number' );
//                                        $width = $this->bsf->isNullCheck( $postParams['type_'.$i .'_width_' . $j], 'number' );
//                                        $suffix = $this->bsf->isNullCheck( $postParams['type_'.$i .'_suffix_' . $j], 'string' );
//
//                                        $select = $sql->select();
//                                        $select->from(array("a"=>"WF_VoucherTypePeriodTrans"))
//                                            ->columns(array('TypeId'))
//                                            ->where("a.TypeId=$typeId and a.PeriodId=$PeriodId and a.CompanyId=$argCompanyId and a.CCId=$argCCId and a.Month=$MonthId and a.Year=$YearId ");
//                                        $statement = $sql->getSqlStringForSqlObject($select);
//                                        $transPeriodMonthResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                                        if(count($transPeriodMonthResult) > 0) {
//                                            $update = $sql->update("WF_VoucherTypePeriodTrans");
//                                            $update->set(array("PreFix"=>$prefix, "StartNo"=>$startNo, "Width"=>$width, "Suffix"=>$suffix))
//                                                ->where(array("TypeId"=>$typeId, "PeriodId"=>$PeriodId, "CompanyId"=>$argCompanyId, "CCId"=>$argCCId, "Month"=>$MonthId, "Year"=>$YearId));
//                                            $updateStmt = $sql->getSqlStringForSqlObject($update);
//                                            $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                                        } else {
//                                            $insert = $sql->insert('WF_VoucherTypePeriodTrans');
//                                            $insert->values(array('TypeId'  => $typeId, 'PeriodId'  => $PeriodId
//                                            ,'CompanyId'  => $argCompanyId,'CCId'  => $argCCId,'Month'  => $MonthId,'Year'  => $YearId
//                                            ,'PreFix'  => $prefix,'StartNo'  => $startNo,'Width'  => $width,'Suffix'  => $suffix
//                                            ));
//                                            $statement = $sql->getSqlStringForSqlObject($insert);
//                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                                        }
//                                    }
//                                }
//                            }
//
//                        }
//
//                    } else {
//                        $rowdeleteids = rtrim($this->bsf->isNullCheck($postParams['rowdeleteids'],'string'), ",");
//                        if($rowdeleteids !== '') {
//                            $delete = $sql->delete();
//                            $delete->from('WF_VoucherPeriodMaster')
//                                ->where("PeriodId IN ($rowdeleteids)");
//                            $statement = $sql->getSqlStringForSqlObject($delete);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//
//                        $rowid = $this->bsf->isNullCheck($postParams['rowid'],'number');
//                        for ( $i = 1; $i <= $rowid; $i++ ) {
//
//                            $periodName = $this->bsf->isNullCheck( $postParams[ 'periodName_' . $i ], 'string' );
//                            $fromdate = $this->bsf->isNullCheck( $postParams[ 'fromdate_' . $i ], 'string' );
//                            $todate = $this->bsf->isNullCheck( $postParams[ 'todate_' . $i ], 'string' );
//
//                            $PeriodId = $this->bsf->isNullCheck( $postParams[ 'PeriodId_' . $i ], 'number' );
//                            $UpdateRow = $this->bsf->isNullCheck( $postParams[ 'UpdateRow_' . $i ], 'number' );
//
//                            if ($periodName == '' || $fromdate == '' || $todate == '')
//                                continue;
//
//                            if($PeriodId == 0) {
//                                $insert = $sql->insert();
//                                $insert->into( 'WF_VoucherPeriodMaster' );
//                                $insert->Values( array( 'PeriodDescription' => $periodName, 'FromDate' => date('Y-m-d', strtotime($fromdate)), 'ToDate' => date('Y-m-d', strtotime($todate)) ));
//                                $statement = $sql->getSqlStringForSqlObject( $insert );
//                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//                            } else if($PeriodId != 0) {
//                                $update = $sql->update();
//                                $update->table( 'WF_VoucherPeriodMaster' )
//                                    ->set( array( 'PeriodDescription' => $periodName, 'FromDate' => date('Y-m-d', strtotime($fromdate)),  'ToDate' => date('Y-m-d', strtotime($todate)) ) )
//                                    ->where(array('PeriodId' => $PeriodId));
//                                $statement = $sql->getSqlStringForSqlObject( $update );
//                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//                            }
//
//                        }
//                    }
//                    $connection->commit();
//                    //$this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "approval-settings"));
//                } catch(PDOException $e){
//                    $connection->rollback();
//                    print "Error!: " . $e->getMessage() . "</br>";
//                }
            }

            $select = $sql->select();
            $select->from(array("a"=>"WF_VoucherTypeMaster"))
                ->columns(array("TypeId" ,"TypeName" ,"ModuleId" ,"ModuleName" => new Expression("b.ModuleName")))
                ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array(), $select::JOIN_INNER);
            $select->order(new Expression("b.ModuleName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultVoucherSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a"=>"WF_CompanyMaster"))
                ->columns(array("CompanyId" ,"CompanyName"))
                ->where(array('DeleteFlag'=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultCompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a"=>"WF_OperationalCostCentre"))
                ->columns(array("CostCentreId" ,"CostCentreName"));
//                ->where(array('DeleteFlag'=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Select PeriodId,PeriodDescription,FromDate,ToDate From VoucherPeriodMaster
            $select = $sql->select();
            $select->from(array("a"=>"WF_VoucherPeriodMaster"))
                ->columns(array("PeriodId" ,"PeriodDescription" ,"FromDate" =>new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')") ,"ToDate" =>new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultPeriodList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function userprofileAction(){
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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams=$request->getPost();
                    $files = $request->getFiles();
                    $empName = $this->bsf->isNullCheck($postParams['emp_name'], 'string');
                    $uGender = $this->bsf->isNullCheck($postParams['gender'], 'string');
                    $uAddress = $this->bsf->isNullCheck($postParams['address'], 'string');
                    $uEmail = $this->bsf->isNullCheck($postParams['email'], 'string');
                    $uPhone = $this->bsf->isNullCheck($postParams['phone'], 'number');
                    $uMobile = $this->bsf->isNullCheck($postParams['mobile'], 'number');
                    $userDob = $this->bsf->isNullCheck($postParams['dob'], 'date');
                    $userDob = date('Y-m-d', strtotime($userDob));

                    $url = '';
                    if ($files['files']['name']) {
                        $url = "public/uploads/workflow/userlogo/";
                        $filename = $this->bsf->uploadFile($url, $files['files']);
                        if ($filename) {
                            $url = 'uploads/workflow/userlogo/' . $filename;
                        }
                    } else {
                        $url = $this->bsf->isNullCheck($postParams['file_default'], 'string');
                    }

                    $update = $sql->update();
                    $update->table('WF_Users');
                    $update->set(array(
                        'EmployeeName' => $empName,
                        'ModifiedDate' => date('Y-m-d H:i:s'),
                        'UserLogo' => $url,
                        'Gender' => $uGender,
                        'Phone' => $uPhone,
                        'Mobile' => $uMobile,
                        'Address' => $uAddress,
                        'Email' => $uEmail,
                        'UserDob' => $userDob
                    ));
                    $update->where(array('UserId'=>$this->auth->getIdentity()->UserId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                }
                $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "userprofile"));

            } else {
                $select = $sql->select();
                $select->from(array("a"=>'WF_Users'))
                    ->join(array("b"=>"WF_PositionMaster"), "a.PositionId=b.PositionId", array("PositionName"), $select::JOIN_INNER)
                    ->join(array("c"=>"WF_Department"), "a.DeptId=c.DeptId", array("Dept_Name"), $select::JOIN_INNER)
                    ->join(array("d"=>"WF_LevelMaster"), "a.LevelId=d.LevelId", array("LevelName"), $select::JOIN_INNER)
                    ->join(array("e"=>"WF_CompanyMaster"), "a.CompanyId=e.CompanyId", array("CompanyName"), $select::JOIN_INNER)
                    ->columns(array('*'))
                    ->where(array("a.UserId"=>$this->auth->getIdentity()->UserId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }

            //begin trans try block example starts
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function teamAction() {
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
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();

                $mode = $this->bsf->isNullCheck($postParams['mode'], 'string');
                $this->_view->setTerminal(true);
//                if($mode=='add') {
//                    $select = $sql->select();
//                    $select->from(array("a" => 'WF_Department'))
//                        ->columns(array('*'));
//                    $select->where("DeleteFlag='0'");
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resultDeptSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $result="";
//
//                    $result .= '<div class="col-lg-12 clear" id="ocDivDept" style="display:none;">
//            <div class="col-lg-12 padlr0 adddprtmnt_box animated fadeInUp">
//                <form method="post" role="form" id="addForm">
//                    <button type="button" class="close mainTr" data-dismiss="modal" aria-label="Close"><span><i class="fa fa-times"></i></span></button>
//                    <div class="col-lg-3 col-md-3 col-sm-3 padlr0">
//                        <div class="form-group col-lg-12 req_flds">
//                            <input type="hidden" name="teamId" id="teamId" value="0"/>
//                            <input type="text" name="teamName" id="teamName" class="form-control lbl_move" label="Team Name..."  value=""/>
//                            <div class="error_message"><p>Please enter Team Name...</p> </div>
//                        </div>
//                    </div>';
////                    <div class="col-lg-3 col-md-3 col-sm-3 padlr0">
////                        <div class="form-group col-lg-12 req_flds">
////                            <select class="single_dropdown lbl_move" name="departmentId" id="departmentId" style="width:100%;" label="Department..." >
////                                <option></option>';
////
////                                if(isset($resultDeptSelect)) {
////                                    foreach ($resultDeptSelect as $resultDeptsels) {
////                                        $result .= '<option value="'.$resultDeptsels['DeptId'].'">'.$resultDeptsels['Dept_Name'].'</option>';
////                                    }
////                                }
////
////                                $result .= '</select>
////                                        <div class="error_message"><p>Please select Department Name...</p> </div>
////                                    </div>
////                                </div>
//                    $result .= '</form>
//                            <div class="col-lg-12 savebtn_area padlr0 marg0 clear">
//                                <ul>
//                                    <li class="save_btn float_r" id="submitData">
//                                        <button type="button" data-slide="next" data-stepno="4" data-toggle="tooltip" data-placement="left" class="ripple editSubmitBtn" title="Submit">Submit</button>
//                                    </li>
//                                </ul>
//                            </div>
//                        </div>
//                    </div>';
//                    $response = $this->getResponse()->setStatusCode(200)->setContent($result);
//                    return $response;
//
//                } else if ($mode == 'edit') {
//                    $teamId = $this->bsf->isNullCheck($postParams['teamId'], 'number');
//
//                    $select = $sql->select();
//                    $select->from(array("a" => 'WF_TeamMaster'))
//                        ->columns(array('*'));
//                    $select->where(array("DeleteFlag" => 0, "TeamId" => $teamId));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resultTeamRegs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//                    $select = $sql->select();
//                    $select->from(array("a" => 'WF_Department'))
//                        ->columns(array('*'));
//                    $select->where("DeleteFlag='0'");
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $resultDeptSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $result="";
//                    $postUrl = $viewRenderer->basePath().'/workflow/index/team';
//
//                    $result .= '<tr id="rtDivDept" style="display:none;"  class="close_tr">
//                                    <td colspan="3">
//                                        <form method="post" action="'.$postUrl.'" id="editform">
//                                            <input type="hidden" name="teamId" id="teamId" value="'.$resultTeamRegs['TeamId'].'" />
//                                            <div class="adddprtmnt_box col-lg-12 padlr0 animated fadeInUp">
//                                                <button type="button" class="close mainTr" data-expandid="'.$resultTeamRegs['TeamId'].'" rel="rt" data-dismiss="modal" aria-label="Close"><span><i class="fa fa-times"></i></span></button>
//                                                <div class="col-lg-3 col-md-3 col-sm-3 padlr0">
//                                                    <div class="form-group col-lg-12 req_flds">
//                                                        <input type="text" name="teamName" id="teamName" class="form-control lbl_move" label="Team Name..."  value="'.$resultTeamRegs['TeamName'].'" />
//                                                        <div class="error_message"><p>Please enter Team Name...</p> </div>
//                                                    </div>
//                                                </div>';
////                                                <div class="col-lg-3 col-md-3 col-sm-3 padlr0">
////                                                    <div class="form-group col-lg-12 req_flds">
////                                                        <select class="single_dropdown lbl_move" name="departmentId" id="departmentId" style="width:100%;" label="Department..." >
////                                                            <option></option>';
////
////                    if(isset($resultDeptSelect)) {
////                        foreach($resultDeptSelect as $resultDeptsels){
////                            if($resultDeptsels['DeptId']==$resultTeamRegs['DeptId']) { $deptMatch = 'selected'; } else { $deptMatch=""; };
////                            $result.='<option value="'.$resultDeptsels['DeptId'].'"  '.$deptMatch.' >'.$resultDeptsels['Dept_Name'].'</option>';
////                        } }
////                    $result.='</select>
////                    <div class="error_message"><p>Please select Department Name...</p> </div>
////                    </div>
////                    </div>
//                    $result .= '<div class="col-lg-12 savebtn_area padlr0 marg0 clear">
//                        <ul>
//                            <li class="save_btn float_r">
//                                <button type="button" data-editid="'.$resultTeamRegs['TeamId'].'" data-slide="next" data-stepno="4" data-toggle="tooltip" data-placement="left" class="ripple editSubmitBtn" title="Update">Update</button>
//                            </li>
//                        </ul>
//                    </div>
//                    </div>
//                    </form>
//                    </td>
//                    </tr>';
//
//                    $response = $this->getResponse()->setStatusCode(200)->setContent($result);
//                    return $response;
//
//                }
                if ($mode == "check") {
                    $teamName = $this->bsf->isNullCheck($this->params()->fromPost('teamName'), 'string');
                    $teamId = $this->bsf->isNullCheck($postParams['teamId'], 'number');

                    $select = $sql->select();
                    $select->from(array("a" => 'WF_TeamMaster'))
                        ->columns(array('TeamId'));
                    $select->where("a.DeleteFlag='0' And a.TeamName='$teamName' and a.TeamId<> $teamId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $client = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if (count($client) > 0) {
                        $response->setStatusCode(201)->setContent('Failed');
                        return $response;
                    }

                    $response->setStatusCode(200)->setContent('Not used');
                    return $response;
                } else if ($mode == "delete") {
                    $teamId = $this->bsf->isNullCheck($postParams['teamId'], 'number');
                    $Remarks = $this->bsf->isNullCheck($postParams['Remarks'], 'string');

                    $select = $sql->select();
                    $select->from(array("a" => 'WF_UserTeamTrans'))
                        ->columns(array('TeamId'));
                    $select->where("a.TeamId=$teamId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $posCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    if (count($posCheck) > 0) {
                        $response->setStatusCode(201)->setContent('Failed');
                        return $response;
                    } else {
                        try {
                            $update = $sql->update();
                            $update->table('WF_TeamMaster')
                                ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('TeamId' => $teamId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $status = 'deleted';
                            $response->setStatusCode(200)->setContent($status);
                            return $response;
                        } catch(PDOException $e){
                            $response->setStatusCode(500);
                            $response->setContent('Internal error!');
                        }
                    }

                } else if($mode == 'searchpos'){
                    $searchVal = $this->bsf->isNullCheck($postParams['searchVal'],'string');

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TeamMaster'))
                        ->columns(array('TeamId','TeamName'));
                    if($searchVal!="") {
                        $select->where("(a.TeamName LIKE '%" . $searchVal . "%')");
                    }
                    $select->where(array("a.DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $levelIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                    return $response;

                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
//                $connection = $dbAdapter->getDriver()->getConnection();
//                $connection->beginTransaction();
//
//                try {
//                    $postData = $request->getPost();
//                    $teamName = $this->bsf->isNullCheck($postData['teamName'], 'string');
//                    $teamId = $this->bsf->isNullCheck($postData['teamId'], 'number');
////                    $deptId = $this->bsf->isNullCheck($postData['departmentId'], 'number');
//                    if($teamId!=0) {
//                        $update = $sql->update("WF_TeamMaster");
//                        $update->set(array("TeamName"=>$teamName
////                        , "DeptId"=>$deptId
//                        ));
//                        $update ->where(array("TeamId"=>$teamId));
//                        $updateStmt = $sql->getSqlStringForSqlObject($update);
//                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                    } else {
//                        $insert = $sql->insert('WF_TeamMaster');
//                        $newData = array(
//                            //Write your Ajax post code here
//                            'TeamName' => $teamName,
////                            'DeptId' => $deptId,
//                            'CreatedDate' => date('Y-m-d H:i:s')
//                        );
//                        $insert->values($newData);
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                    }
//
//
//                    $connection->commit();
//                    $FeedId = $this->params()->fromQuery('FeedId');
//                    $AskId = $this->params()->fromQuery('AskId');
//                    if(isset($FeedId) && $FeedId!="") {
//                        $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'team'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
//                    } else {
//                        $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'team'));
//                    }
//                } catch(PDOException $e){
//                    $connection->rollback();
//                    print "Error!: " . $e->getMessage() . "</br>";
//                }
            } else {

                $select = $sql->select();
                $select->from(array("a"=>'WF_TeamMaster'))
//                    ->join(array("b"=>"WF_Department"), "a.DeptId=b.DeptId", array("Dept_Name"), $select::JOIN_LEFT)
                    ->columns(array('*'));
                $select->where(array("a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultTeamRegs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function userlistAction(){
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
        $request = $this->getRequest();


        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $searchStr=$this->bsf->isNullCheck($postParams['searchStr'],'string');
                if($searchStr!="") {
                    $select = $sql->select();
                    $select->from(array("a" => "WF_Users"))
                        ->join(array("b" => "WF_LevelMaster"), "a.LevelId=b.LevelId", array('LevelName'), $select::JOIN_LEFT)
                        ->join(array("c" => "WF_Department"), "a.DeptId=c.DeptId", array('Dept_Name'), $select::JOIN_LEFT)
                        ->join(array("d" => "WF_PositionMaster"), "a.PositionId=d.PositionId", array('PositionName'), $select::JOIN_LEFT)
                        ->columns(array('UserId','UserName','Address','Mobile','EMail','UserLogo'));
                    $select->where("UserName LIKE '%" . $searchStr . "%' OR LevelName LIKE '%" . $searchStr . "%' OR a.Email LIKE '%" . $searchStr . "%' OR a.Mobile LIKE '%" . $searchStr . "%' OR Address LIKE '%" . $searchStr . "%' OR PositionName LIKE '%" . $searchStr . "%' OR Dept_Name LIKE '%" . $searchStr . "%'  OR LevelName LIKE '%" . $searchStr . "%'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $select = $sql->select();
                    $select->from(array("a" => "WF_Users"))
                        ->join(array("b" => "WF_LevelMaster"), "a.LevelId=b.LevelId", array('LevelName'), $select::JOIN_LEFT)
                        ->join(array("c" => "WF_Department"), "a.DeptId=c.DeptId", array('Dept_Name'), $select::JOIN_LEFT)
                        ->join(array("d" => "WF_PositionMaster"), "a.PositionId=d.PositionId", array('PositionName'), $select::JOIN_LEFT)
                        ->columns(array('UserId','UserName','Address','Mobile','EMail','UserLogo'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $companySearch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $companyList ="";
                foreach($companySearch as $i):
                    $userLogo="";
                    if(isset($i['UserLogo']) && trim($i['UserLogo'])!='') {
                        $userLogo = $viewRenderer->basePath().'/'.$i['UserLogo'];
                    } else {
                        $userLogo = $viewRenderer->basePath().'/images/avatar.jpg';
                    }
                    $companyMap = $viewRenderer->basePath().'/images/company-map1.jpg';
                    $companyList .= '<div class="col-lg-12 col-md-6 col-sm-6  bids_list compgdlist brad_3 padlr0">
                                        <span class="comp_arrowlink"><a href="company-view/'.$i['UserId'].'" class="brad_50" data-toggle="tooltip" data-placement="right" title="View&nbsp;Profile"><i class="fa fa-gg"></i></a></span>
                                        <div class="comp_editlink brad_50"><a href="new-company/'.$i['UserId'].'" class="ripple brad_50" data-toggle="tooltip" data-placement="left" title="Edit&nbsp;Profile"><i class="fa fa-pencil"></i></a></div>
                                        <div class="col-lg-7 padlr0">
                                            <div class="col-lg-9">
                                                <div class="compgrid_logo brad_50 float_l">
                                                    <span><img class="brad_50" src="'.$userLogo.'" /></span>
                                                </div>
                                                <h1>'.$i['UserName'].'<br>
                                                    <span class="m_top10"><span><i class="fa fa-user"></i></span>'.$i['PositionName'].'<span class="vendor_phone"><i class="fa fa-phone"></i>'.$i['Mobile'].'</span></span>
                                                </h1>
                                            </div>
                                            <div class="col-lg-3 padlr0">
                                                <div class="comp_map" style="background-image:url('.$companyMap.')"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-5 bidvendor_detail compgdlist_detail">
                                            <p><span class="p_label"><span class="mapaddress_icon"><i class="fa fa-map-marker"></i></span>  Address :</span>'.$i['Address'].'</p>
                                             <a href="#" class="vwstrtr_btn m_top0 ripple">View Structure</a>
                                        </div>
                                    </div>';
                endforeach;
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setStatusCode(200)->setContent($companyList);
                return $response;
            }
        } else {
            if($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from(array("a" => "WF_Users"))
                    ->join(array("b" => "WF_LevelMaster"), "a.LevelId=b.LevelId", array('LevelName'), $select::JOIN_LEFT)
                    ->join(array("c" => "WF_Department"), "a.DeptId=c.DeptId", array('Dept_Name'), $select::JOIN_LEFT)
                    ->join(array("d" => "WF_PositionMaster"), "a.PositionId=d.PositionId", array('PositionName'), $select::JOIN_LEFT)
                    ->columns(array('UserId','UserName','Address','Mobile','EMail','UserLogo'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
        }
        return $this->_view;
    }
    public function getDeptPositionAction()
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

                $postParams = $request->getPost();
                $results = array();
                $sPositionId =  $this->bsf->isNullCheck($postParams['sPositionId'],'string');
                if  ($sPositionId !="") {
                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from('WF_PositionMaster')
                        ->columns(array('PositionId', 'PositionName'))
                        ->where("PositionId IN ($sPositionId)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $data = array();
                $data['trans'] = $results;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    public function getActivityRolesAction()
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

                $postParams = $request->getPost();
                $ActivityIds =  $this->bsf->isNullCheck($postParams['ActivityId'],'string');
                $taskTrans = array();
                $roleTrans = array();
                if  ($ActivityIds !="") {

                    $sql = new Sql($dbAdapter);
                    $subSelect1 = $sql->select();
                    $subSelect1->from('WF_ActivityTaskTrans')
                        ->columns(array('TaskId'))
                        ->where("ActivityId IN ($ActivityIds)");

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskMaster'))
                        ->columns(array('TaskId', 'TaskName'))
                        ->where->expression('TaskId IN ?', array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $taskTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskTrans'))
                        ->join(array("b" => "WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
                        ->columns(array('RoleId', 'RoleName'))
                        ->where->expression("a.RoleType='C' and b.TaskId IN ?", array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $roleTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $data = array();
                $data['taskTrans'] = $taskTrans;
                $data['roleTrans'] = $roleTrans;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    public function getUserActivityRolesAction()
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

                $postParams = $request->getPost();
                $ActivityIds =  $this->bsf->isNullCheck($postParams['ActivityId'],'string');
                $roleTrans = array();
                $permissionTrans = array();
                $variantTrans = array();
                if  ($ActivityIds !="") {

                    $sql = new Sql($dbAdapter);
                    $subSelect1 = $sql->select();
                    $subSelect1->from('WF_ActivityTaskTrans')
                        ->columns(array('TaskId'))
                        ->where("ActivityId IN ($ActivityIds)");

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskMaster'))
                        ->columns(array('TaskId', 'TaskName'))
                        ->where->expression('TaskId IN ?', array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $roleTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskTrans'))
                        ->join(array("b" => "WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
                        ->columns(array('RoleId', 'RoleName'))
                        ->where->expression("a.RoleType='C' and b.TaskId IN ?", array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $permissionTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskTrans'))
                        ->join(array("b" => "WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
                        ->columns(array('RoleId', 'RoleName'))
                        ->where->expression("a.RoleType='V' and b.TaskId IN ?", array($subSelect1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $variantTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $data = array();
                $data['roleTrans'] = $roleTrans;
                $data['permissionTrans'] = $permissionTrans;
                $data['variantTrans'] = $variantTrans;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    public function teameditAction(){
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
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $teamId = $this->bsf->isNullCheck($postParams['teamId'], 'number');
                $this->_view->teamId = $teamId;

                $teamName = "";
                if ($teamId !=0) {
                    $select = $sql->select();
                    $select->from('WF_TeamMaster')
                        ->columns(array('TeamId', 'TeamName'))
                        ->where(array('TeamId' => $teamId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $teamMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($teamMaster)) $teamName = $teamMaster['TeamName'];
                }
                $this->_view->teamName = $teamName;

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'WF_UserTeamTrans'))
                    ->join(array("b" => "WF_Users"), "a.UserId=b.UserId", array('UserName'), $select::JOIN_INNER)
                    ->columns(array('UserId'))
                    ->where(array('a.TeamId'=>$teamId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                return $this->_view;

//				$result =  "";
//				$this->_view->setTerminal(true);
//				$response = $this->getResponse()->setContent($result);
//				return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    $teamName = $this->bsf->isNullCheck($postData['teamName'], 'string');
                    $teamId = $this->bsf->isNullCheck($postData['teamId'], 'number');
                    $iteamId = $this->bsf->isNullCheck($postData['teamId'], 'number');

                    if($teamId !=0) {
                        $update = $sql->update("WF_TeamMaster");
                        $update->set(array("TeamName"=>$teamName));
                        $update ->where(array("TeamId"=>$teamId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $insert = $sql->insert('WF_TeamMaster');
                        $newData = array(
                            'TeamName' => $teamName,
                            'CreatedDate' => date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $teamId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    if ($this->bsf->isNullCheck($postData['bUser'],'number') == 1) {
                        if ($iteamId !=0) {
                            $delete = $sql->delete();
                            $delete->from('WF_UserTeamTrans')
                                ->where(array("TeamId" => $teamId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $userTrans = json_decode($this->bsf->isNullCheck($postData['arrUserTrans'], 'string'), true);
                        foreach ($userTrans as $trans) {
                            $iUserId = $this->bsf->isNullCheck($trans['UserId'], 'number');

                            $insert = $sql->insert('WF_UserTeamTrans');
                            $insert->values(array(
                                'TeamId' => $teamId,
                                'UserId' => $iUserId
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'team'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('workflow/default', array('controller' => 'index', 'action' => 'team'));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }
    }

    public function vouchereditAction(){
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
                $typeId = $this->bsf->isNullCheck($postParams['typeId'], 'number');
                $ccId = $this->bsf->isNullCheck($postParams['ccId'], 'number');
                $compId = $this->bsf->isNullCheck($postParams['compId'], 'number');
                $this->_view->typeId = $typeId;
                $this->_view->ccId = $ccId;
                $this->_view->compId = $compId;

                $select = $sql->select();
                $select->from('WF_VoucherTypeMaster')
                    ->columns(array('TypeId','TypeName','CompanyRequired','CCRequired','BaseType'=>new Expression("Case When BaseType='' then 'GE' else BaseType End")))
                    ->where(array('TypeId'=>$typeId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->voucherMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from('WF_VoucherTypeTrans')
                    ->columns( array( 'GenType','PeriodWise','PreFix','Suffix','StartNo','Width'))
                    ->where(array('TypeId'=>$typeId,'CCId'=>$ccId,'CompanyId'=>$compId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->voucherTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from( array( 'a' => 'WF_VoucherPeriodMaster' ))
                    ->columns( array( 'PeriodId','PeriodDescription','FromDate'=>new Expression("FORMAT(FromDate, 'dd-MM-yyyy')"),'ToDate'=>new Expression("FORMAT(ToDate, 'dd-MM-yyyy')")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->periodLists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from('WF_VoucherTypePeriod')
                    ->columns(array('PeriodId','Monthwise','Prefix','Suffix','StartNo','Width'))
                    ->where(array('TypeId' => $typeId,'CompanyId'=>$compId,'CCId'=>$ccId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->period = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_VoucherTypePeriodTrans')
                    ->columns(array('PeriodId','Month','Year','Prefix','Suffix','StartNo','Width'))
                    ->where(array('TypeId' => $typeId,'CompanyId'=>$compId,'CCId'=>$ccId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->periodTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $postData = $request->getPost();
                    $typeId = $this->bsf->isNullCheck($postData['typeId'], 'string');
                    $compId= $this->bsf->isNullCheck($postData['compId'], 'number');
                    $ccId = $this->bsf->isNullCheck($postData['ccId'], 'number');

                    if ($compId ==0 && $ccId ==0) {
                        $baseType =$this->bsf->isNullCheck($postData['basetype'], 'string');
                        $update = $sql->update();
                        $update->table('WF_VoucherTypeMaster');
                        $update->set(array('BaseType' => $baseType));
                        $update->where(array('TypeId'=>$typeId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $genType = $this->bsf->isNullCheck($postData['gentype'], 'number');
                    $periodwise = $this->bsf->isNullCheck($postData['periodwise'], 'number');
                    $sPrefix= $this->bsf->isNullCheck($postData['mprefix'], 'string');
                    $sSuffix = $this->bsf->isNullCheck($postData['msuffix'], 'string');
                    $iStartNo= $this->bsf->isNullCheck($postData['mstartno'], 'number');
                    $iWidth = $this->bsf->isNullCheck($postData['mwidth'], 'number');

                    $update = $sql->update();
                    $update->table('WF_VoucherTypeTrans');
                    $update->set(array('GenType' => $genType,'PeriodWise'=>$periodwise,'Prefix'=>$sPrefix,
                        'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                    $update->where(array('TypeId' => $typeId,'CompanyId'=>$compId,'CCId'=>$ccId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    if ($result->getAffectedRows() <=0) {
                        $insert = $sql->insert();
                        $insert->into('WF_VoucherTypeTrans');
                        $insert->Values(array('TypeId'=>$typeId,'CompanyId'=>$compId,'CCId'=>$ccId,'GenType' => $genType,'PeriodWise'=>$periodwise,'Prefix'=>$sPrefix,
                            'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $arrperiod = json_decode($this->bsf->isNullCheck($postData['arrPeriod'],'string'), true);
                    foreach($arrperiod as $trans) {
                        $iPeriodId= $this->bsf->isNullCheck($trans['PeriodId'], 'number');
                        $monthwise= $this->bsf->isNullCheck($trans['Monthwise'], 'number');
                        $sPrefix= $this->bsf->isNullCheck($trans['Prefix'], 'string');
                        $sSuffix = $this->bsf->isNullCheck($trans['Suffix'], 'string');
                        $iStartNo= $this->bsf->isNullCheck($trans['StartNo'], 'number');
                        $iWidth = $this->bsf->isNullCheck($trans['Width'], 'number');

                        $update = $sql->update();
                        $update->table('WF_VoucherTypePeriod');
                        $update->set(array('Monthwise' => $monthwise,'Prefix'=>$sPrefix,
                            'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                        $update->where(array('TypeId' => $typeId,'PeriodId'=>$iPeriodId,'CompanyId'=>$compId,'CCId'=>$ccId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        if ($result->getAffectedRows() <=0) {
                            $insert = $sql->insert();
                            $insert->into('WF_VoucherTypePeriod');
                            $insert->Values(array('TypeId'=>$typeId,'PeriodId'=>$iPeriodId,'CompanyId'=>$compId,'CCId'=>$ccId,'MonthWise'=>$monthwise,'Prefix'=>$sPrefix,
                                'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $arrperiodTrans = json_decode($this->bsf->isNullCheck($postData['arrPeriodTrans'],'string'), true);
                    foreach($arrperiodTrans as $trans) {
                        $iPeriodId= $this->bsf->isNullCheck($trans['PeriodId'], 'number');
                        $iMonth= $this->bsf->isNullCheck($trans['Month'], 'number');
                        $iYear= $this->bsf->isNullCheck($trans['Year'], 'number');
                        $sPrefix= $this->bsf->isNullCheck($trans['Prefix'], 'string');
                        $sSuffix = $this->bsf->isNullCheck($trans['Suffix'], 'string');
                        $iStartNo= $this->bsf->isNullCheck($trans['StartNo'], 'number');
                        $iWidth = $this->bsf->isNullCheck($trans['Width'], 'number');

                        $update = $sql->update();
                        $update->table('WF_VoucherTypePeriodTrans');
                        $update->set(array('Prefix'=>$sPrefix,
                            'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                        $update->where(array('TypeId' => $typeId,'PeriodId'=>$iPeriodId,'Month'=>$iMonth,'Year'=>$iYear,'CompanyId'=>$compId,'CCId'=>$ccId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        if ($result->getAffectedRows() <=0) {
                            $insert = $sql->insert();
                            $insert->into('WF_VoucherTypePeriodTrans');
                            $insert->Values(array('TypeId'=>$typeId,'PeriodId'=>$iPeriodId,'CompanyId'=>$compId,'CCId'=>$ccId,'Month'=>$iMonth,'Year'=>$iYear,'Prefix'=>$sPrefix,
                                'Suffix'=>$sSuffix,'StartNo'=>$iStartNo,'Width'=>$iWidth));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "vouchertype-generation"));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function checkpositionFoundAction(){
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
                try {
                    $positionId = $this->params()->fromPost('positionId');
                    $positionName = $this->params()->fromPost('positionName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $ans = 'N';

                    $select = $sql->select();
                    $select->from('WF_PositionMaster')
                        ->columns(array('PositionId'))
                        ->where("PositionId<>$positionId and PositionName = '$positionName'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if (!empty($results)) $ans = 'Y';

                    $response = $this->getResponse();
                    $response->setContent($ans);
                    return $response;

                } catch (PDOException $e) {
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    public function addnewpositionAction(){
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
                $response = $this->getResponse();

                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $positionName = $this->params()->fromPost('positionName');

                    $sql = new Sql($dbAdapter);
                    $insert = $sql->insert();
                    $insert->into('WF_PositionMaster');
                    $insert->Values(array('PositionName' => $positionName));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $select = $sql->select();
                $select->from(array("a"=>'WF_PositionMaster'))
                    ->columns(array('PositionId', 'PositionName'))
                    ->where(array("DeleteFlag" =>'0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $positionMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response->setContent(json_encode($positionMaster));
                return $response;
            }
        }
    }

    public function getvoucherperioddetailsAction() {
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $postParams = $request->getPost();
                    $typeId = $this->bsf->isNullCheck($postParams['typeId'], 'number');
                    $periodId= $this->bsf->isNullCheck($postParams['periodId'], 'number');
                    $compId = $this->bsf->isNullCheck($postParams['compId'], 'number');
                    $ccId = $this->bsf->isNullCheck($postParams['ccId'], 'number');

                    $select = $sql->select();
                    $select->from('WF_VoucherTypePeriod')
                        ->columns(array('Monthwise','Prefix','Suffix','StartNo','Width'))
                        ->where(array('TypeId' => $typeId,'PeriodId'=>$periodId,'CompanyId'=>$compId,'CCId'=>$ccId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $period = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('WF_VoucherTypePeriodTrans')
                        ->columns(array('Month','Year','Prefix','Suffix','StartNo','Width'))
                        ->where(array('TypeId' => $typeId,'PeriodId'=>$periodId,'CompanyId'=>$compId,'CCId'=>$ccId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $periodTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $data = array();
                    $data['period'] = $period;
                    $data['periodtrans'] = $periodTrans;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                    return $response;
                } catch (PDOException $e) {

                }

            }
        }
    }
    public function updatevoucherperiodAction() {
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                try {
                    $postParams = $request->getPost();
                    $period = json_decode($this->bsf->isNullCheck($postParams['arrPeriodMaster'],'string'), true);
                    foreach($period as $trans) {
                        $iPeriodId= $this->bsf->isNullCheck($trans['PeriodId'], 'number');
                        $sPeriodName= $this->bsf->isNullCheck($trans['PeriodDescription'], 'string');
                        $dFromDate= $this->bsf->isNullCheck($trans['FromDate'], 'date');
                        $dToDate= $this->bsf->isNullCheck($trans['ToDate'], 'date');
                        if ($iPeriodId ==0) {
                            $insert = $sql->insert('WF_VoucherPeriodMaster');
                            $insert->values(array(
                                'PeriodDescription' => $sPeriodName,
                                'FromDate' => date('Y-m-d', strtotime($dFromDate)),
                                'ToDate' => date('Y-m-d', strtotime($dToDate))
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $update = $sql->update();
                            $update->table('WF_VoucherPeriodMaster');
                            $update->set(array(
                                'PeriodDescription' => $sPeriodName,
                                'FromDate' => date('Y-m-d', strtotime($dFromDate)),
                                'ToDate' => date('Y-m-d', strtotime($dToDate))
                            ));
                            $update->where(array('PeriodId'=>$iPeriodId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }


                    $select = $sql->select();
                    $select->from(array("a"=>"WF_VoucherPeriodMaster"))
                        ->columns(array("PeriodId" ,"PeriodDescription" ,"FromDate" =>new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')") ,"ToDate" =>new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $periodMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $data = array();
                    $data['periodMaster'] = $periodMaster;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                    return $response;
                } catch (PDOException $e) {

                }
            }
        }
    }

    public function approvalsettingeditAction(){
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
                $postParams = $request->getPost();
                $iTypeId= $this->bsf->isNullCheck($postParams['TypeId'],'number');
                $iId= $this->bsf->isNullCheck($postParams['Id'],'number');
                $actionType = $this->bsf->isNullCheck($postParams['ActionType'],'string');

                if ($actionType =="Edit") {
                    $this->_view->typeId = $iTypeId;
                    $arrList = array();

                    $sql = new Sql($dbAdapter);
                    if ($iTypeId == 1) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_TaskTrans"))
                            ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
                            ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('RoleId', 'RoleName', 'MultiApproval', 'ValueApproval', 'ApprovalBased', 'NotRequired', 'SpecialApproval', 'MaxLevel'))
                            ->where(array('RoleId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    } else if ($iTypeId == 2) {

                        $select = $sql->select();
                        $select->from(array("a" => "WF_TaskTrans"))
                            ->join(array("b" => "WF_TaskMaster"), 'a.TaskName=b.TaskName', array(), $select::JOIN_INNER)
                            ->join(array("c" => "WF_Module"), 'b.ModuleId=c.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->join(array("d" => "WF_RoleTrans"), 'a.RoleId=d.RoleId', array('ProcessType', 'ProcessPeriod', 'IntervalType', 'IntervalPeriod', 'FreqencyType','FreqencyPeriod','FTime', 'FDay', 'FWeek'), $select::JOIN_LEFT)
                            ->columns(array('RoleId', 'RoleName'))
                            ->where(array('a.RoleId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }  else if ($iTypeId == 3) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_AlertMaster"))
                            ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('AlertId', 'AlertName','AlertMsg'))
                            ->where(array('a.AlertId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }  else if ($iTypeId == 4) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_AlertMaster"))
                            ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('AlertId', 'AlertName','AlertMsg'))
                            ->where(array('a.AlertId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }  else if ($iTypeId == 5) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_AlertMaster"))
                            ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('AlertId', 'AlertName','InformType','InformPeriodType','InformPeriod','FrequencyType','FrequencyPeriod'))
                            ->where(array('a.AlertId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }  else if ($iTypeId == 6) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_AlertMaster"))
                            ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('AlertId', 'AlertName','FrequencyType','FrequencyPeriod','SDate','FDay','FWeek','FTime'))
                            ->where(array('a.AlertId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }  else if ($iTypeId == 7) {
                        $select = $sql->select();
                        $select->from(array("a" => "WF_AlertMaster"))
                            ->join(array("b" => "WF_Module"), 'a.ModuleId=b.ModuleId', array('ModuleName'), $select::JOIN_INNER)
                            ->columns(array('AlertId', 'AlertName','FrequencyType','FrequencyPeriod','SDate','FDay','FWeek','FTime'))
                            ->where(array('a.AlertId' => $iId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }

                    $this->_view->setTerminal(true);
                    return $this->_view;
                } else {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();
                    try {

                        $postData = $request->getPost();
                        $sql = new Sql($dbAdapter);

                        if ($iTypeId ==1) {
                            $roleId =  $iId;
                            $not_required  = $this->bsf->isNullCheck($postData['notrequired'], 'number');
                            $approvalBased =  $this->bsf->isNullCheck($postData['approvalBased'], 'string');
                            if ($approvalBased==""  || $approvalBased=="N") $multiApproval =0;
                            else $multiApproval =1;
                            $value_required =  $this->bsf->isNullCheck($postData['valueapproval'], 'number');
                            $maxlevel=  $this->bsf->isNullCheck($postData['maxLevel'], 'number');
                            $special_required=  $this->bsf->isNullCheck($postData['specialapproval'], 'number');

                            $update = $sql->update("WF_TaskTrans");
                            $update->set(array("MultiApproval"=>$multiApproval, "ValueApproval"=>$value_required
                            ,"NotRequired"=>$not_required,"SpecialApproval"=>$special_required
                            ,"ApprovalBased"=>$approvalBased,"MaxLevel"=>$maxlevel))
                                ->where(array("RoleId"=>$roleId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if ($iTypeId ==2) {
                            $roleId =  $iId;
                            $ProcessType = $this->bsf->isNullCheck($postData['ProcessType'], 'string');
                            $ProcessPeriod =  $this->bsf->isNullCheck($postData['ProcessPeriod'], 'number');
                            $IntervalType =  $this->bsf->isNullCheck($postData['IntervalType'], 'string');
                            $IntervalPeriod =  $this->bsf->isNullCheck($postData['IntervalPeriod'], 'number');
                            $FreqencyType =  $this->bsf->isNullCheck($postData['FreqencyType'], 'string');
                            $FreqencyPeriod =  $this->bsf->isNullCheck($postData['FreqencyPeriod'], 'number');
                            $FTime = date('Y-m-d H:i', strtotime(date('Y-m-d') . ' ' . $postData['FTime']));
                            $FDay=  $this->bsf->isNullCheck($postData['FDay'], 'number');
                            $FWeek=  $this->bsf->isNullCheck($postData['FWeek'], 'number');

                            $update = $sql->update("WF_RoleTrans");
                            $update->set(array("ProcessType"=>$ProcessType, "ProcessPeriod"=>$ProcessPeriod
                            ,"IntervalType"=>$IntervalType,"IntervalPeriod"=>$IntervalPeriod
                            ,"FreqencyType"=>$FreqencyType,"FreqencyPeriod"=>$FreqencyPeriod,'FTime'=>$FTime,'FDay'=>$FDay,'FWeek'=>$FWeek))
                                ->where(array("RoleId"=>$roleId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            if ($result->getAffectedRows() <=0) {
                                $insert = $sql->insert();
                                $insert->into('WF_RoleTrans');
                                $insert->Values(array('RoleId'=>$roleId,"ProcessType"=>$ProcessType, "ProcessPeriod"=>$ProcessPeriod
                                ,"IntervalType"=>$IntervalType,"IntervalPeriod"=>$IntervalPeriod
                                ,"FreqencyType"=>$FreqencyType,"FreqencyPeriod"=>$FreqencyPeriod,'FTime'=>$FTime,'FDay'=>$FDay,'FWeek'=>$FWeek));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else if ($iTypeId ==3) {
                            $alertId = $iId;
                            $sAlertMsg = $this->bsf->isNullCheck($postData['AlertMsg'], 'string');

                            $update = $sql->update("WF_AlertMaster");
                            $update->set(array("AlertMsg"=>$sAlertMsg))
                                ->where(array("AlertId"=>$alertId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if ($iTypeId ==4) {
                            $alertId = $iId;
                            $sAlertMsg = $this->bsf->isNullCheck($postData['AlertMsg'], 'string');

                            $update = $sql->update("WF_AlertMaster");
                            $update->set(array("AlertMsg"=>$sAlertMsg))
                                ->where(array("AlertId"=>$alertId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if ($iTypeId ==5) {
                            $alertId = $iId;
                            $InformType = $this->bsf->isNullCheck($postData['InformType'], 'string');
                            $InformPeriodType =  $this->bsf->isNullCheck($postData['InformPeriodType'], 'string');
                            $InformPeriod =  $this->bsf->isNullCheck($postData['InformPeriod'], 'number');
                            $FrequencyType =  $this->bsf->isNullCheck($postData['FrequencyType'], 'string');
                            $FrequencyPeriod =  $this->bsf->isNullCheck($postData['FrequencyPeriod'], 'number');

                            $update = $sql->update("WF_AlertMaster");
                            $update->set(array('InformType'=>$InformType,'InformPeriodType'=>$InformPeriodType,'InformPeriod'=>$InformPeriod
                            ,'FrequencyType'=>$FrequencyType,'FrequencyPeriod'=>$FrequencyPeriod))
                                ->where(array("AlertId"=>$alertId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if ($iTypeId==6) {
                            $alertId =  $iId;
                            $SDate = date('Y-m-d', strtotime($postData['SDate']));
                            $FrequencyType = $this->bsf->isNullCheck($postData['FrequencyType'], 'string');
                            $FrequencyPeriod =  $this->bsf->isNullCheck($postData['FrequencyPeriod'], 'number');
                            $FTime = date('Y-m-d H:i', strtotime(date('Y-m-d') . ' ' . $postData['FTime']));
                            $FDay =  $this->bsf->isNullCheck($postData['FDay'], 'number');
                            $FWeek =  $this->bsf->isNullCheck($postData['FWeek'], 'number');
                            $update = $sql->update("WF_AlertMaster");
                            $update->set(array("SDate"=>$SDate,"FrequencyType"=>$FrequencyType, "FrequencyPeriod"=>$FrequencyPeriod
                            ,'FTime'=>$FTime,'FDay'=>$FDay,'FWeek'=>$FWeek))
                                ->where(array("AlertId"=>$alertId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else if ($iTypeId==7) {
                            $alertId =  $iId;
                            $AlertName= $this->bsf->isNullCheck($postData['AlertName'], 'string');
                            $SDate = date('Y-m-d', strtotime($postData['SDate']));
                            $FrequencyType = $this->bsf->isNullCheck($postData['FrequencyType'], 'string');
                            $FrequencyPeriod =  $this->bsf->isNullCheck($postData['FrequencyPeriod'], 'number');
                            $FTime = date('Y-m-d H:i', strtotime(date('Y-m-d') . ' ' . $postData['FTime']));
                            $FDay =  $this->bsf->isNullCheck($postData['FDay'], 'number');
                            $FWeek =  $this->bsf->isNullCheck($postData['FWeek'], 'number');
                            $update = $sql->update("WF_AlertMaster");
                            $update->set(array("AlertName"=>$AlertName,"SDate"=>$SDate,"FrequencyType"=>$FrequencyType, "FrequencyPeriod"=>$FrequencyPeriod
                            ,'FTime'=>$FTime,'FDay'=>$FDay,'FWeek'=>$FWeek))
                                ->where(array("AlertId"=>$alertId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setStatusCode(200);
                        return $response;

                    } catch(PDOException $e){
                        $connection->rollback();
                        $response = $this->getResponse()->setStatusCode(400);
                        return $response;
                    }
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function checkalertFoundAction(){
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
                try {
                    $alertId = $this->params()->fromPost('alertId');
                    $alertName = $this->params()->fromPost('alertName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $ans = 'N';

                    $select = $sql->select();
                    $select->from('WF_AlertMaster')
                        ->columns(array('AlertId'))
                        ->where("AlertId<>$alertId and AlertName = '$alertName'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if (!empty($results)) $ans = 'Y';

                    $response = $this->getResponse();
                    $response->setContent($ans);
                    return $response;

                } catch (PDOException $e) {
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    public function addnewalertAction(){
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
                $response = $this->getResponse();

                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $alertName = $this->params()->fromPost('alertName');

                    $sql = new Sql($dbAdapter);
                    $insert = $sql->insert();
                    $insert->into('WF_AlertMaster');
                    $insert->Values(array('AlertName' => $alertName,'AlertType'=>'C'));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $select = $sql->select();
                $select->from(array("a"=>'WF_AlertMaster'))
                    ->columns(array('AlertId', 'AlertName'))
                    ->where(array("AlertType" =>'C'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $alertMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response->setContent(json_encode($alertMaster));
                return $response;
            }
        }
    }

    public function remindereditAction(){
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

                $postParams = $request->getPost();
                $ireminderId= $this->bsf->isNullCheck($postParams['reminderId'],'number');
                $actionType = $this->bsf->isNullCheck($postParams['ActionType'],'string');
                if ($actionType=='Edit') {
                    $this->_view->reminderId= $ireminderId;
                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from('WF_Reminder')
                        ->columns(array('RDescription','RDate','RepeatEvery','Type'))
                        ->where(array('ReminderId' => $ireminderId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->reminder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('WF_Users')
                        ->columns(array('UserId','UserName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from('WF_RemindUsers')
                        ->columns(array('ReminderUserId'))
                        ->where(array('ReminderId' => $ireminderId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $rUsers = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $reminderUsers = array();
                    foreach($rUsers as $trans) {
                        $reminderUsers[] = $trans['ReminderUserId'];
                    }
                    $this->_view->reminderUsers = $reminderUsers;

                    $this->_view->setTerminal(true);
                    return $this->_view;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $reminderId = $postParams['editReminderId'];
                    $sql = new Sql($dbAdapter);

                    $bFound =  isset($postParams['r_check']) ? 1 : 0;

                    if ($reminderId==0) {
                        $insert = $sql->insert('WF_Reminder');
                        $insert->values(array(
                            'Type' => $bFound,
                            'RDescription' => $this->bsf->isNullCheck($postParams['r_description'], 'string'),
                            'RepeatEvery' => $this->bsf->isNullCheck($postParams['repeat_every'], 'number'),
                            'RDate' => date('Y-m-d', strtotime($postParams['r_date'])),
                            'CreatedDate' => date('Y-m-d H:i:s')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $reminderId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $update = $sql->update("WF_Reminder");
                        $update->set(array(
                            'Type' => $bFound,
                            'RDescription' => $this->bsf->isNullCheck($postParams['r_description'], 'string'),
                            'RepeatEvery' => $this->bsf->isNullCheck($postParams['repeat_every'], 'number'),
                            'RDate' => date('Y-m-d', strtotime($postParams['r_date']))))
                            ->where(array("ReminderId" => $reminderId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WF_RemindUsers')
                            ->where(array("ReminderId" => $reminderId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    foreach ($postParams['r_users'] as $value){
                        $insert = $sql->insert('WF_RemindUsers');
                        $insert->values(array(
                            'ReminderId' => $reminderId,
                            'ReminderUserId'=> $value));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "reminder"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function newseditAction(){
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

                $postParams = $request->getPost();
                $iNewsId= $this->bsf->isNullCheck($postParams['newsId'],'number');
                $actionType = $this->bsf->isNullCheck($postParams['ActionType'],'string');
                if ($actionType=='Edit') {
                    $this->_view->newsId= $iNewsId;
                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from('WF_News')
                        ->columns(array('NDescription','FromDate','ToDate','Type'))
                        ->where(array('NewsId' => $iNewsId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->news = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->setTerminal(true);
                    return $this->_view;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                $postParams = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $newsId = $postParams['editNewsId'];
                    $sql = new Sql($dbAdapter);

                    $fDate = date('Y-m-d', strtotime($postParams['frm_date']));
                    $tDate = date('Y-m-d', strtotime($postParams['to_date']));
                    $bFound =  isset($postParams['n_check']) ? 1 : 0;

                    if ($newsId==0) {
                        $insert = $sql->insert('WF_News');
                        $insert->values(array('NDescription'  => $postParams['n_description'],'FromDate'=>$fDate,'ToDate'=>$tDate,'Type'=>$bFound));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $update = $sql->update("WF_News");
                        $update->set(array("NDescription" => $postParams['n_description'],'FromDate'=>$fDate,'ToDate'=>$tDate,'Type'=>$bFound))
                            ->where(array("NewsId" => $newsId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "news"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function leveleditAction(){
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

                $postParams = $request->getPost();
                $iLevelId= $this->bsf->isNullCheck($postParams['LevelId'],'number');
                $actionType = $this->bsf->isNullCheck($postParams['ActionType'],'string');
                if ($actionType=='Edit') {
                    $this->_view->levelId = $iLevelId;

                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from(array("a"=>'WF_LevelMaster'))
                        ->columns(array('LevelId','LevelName'))
                        ->where(array('LevelId'=>$iLevelId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultLevelReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a"=>'WF_TaskTrans'))
                        ->columns(array('RoleId', 'RoleName'));
                    $select->where(array('ValueApproval'=>1));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->valueApprovalMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>"WF_LevelTrans"))
                        ->columns(array("RoleId",'ValueFrom','ValueTo'))
                        ->where(array('LevelId'=>$iLevelId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->valueLevelTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>"WF_TaskTrans"))
                        ->columns(array("RoleId","RoleName"));
                    $select->where("RoleType='V'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->varianceApprovalMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>"WF_LevelVariant"))
                        ->columns(array("RoleId",'Variant'))
                        ->where(array('LevelId'=>$iLevelId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->varianceLevelTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->setTerminal(true);
                    return $this->_view;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                //begin trans try block example starts
                $postParams = $request->getPost();
//                    echo"<pre>";
//                    print_r($postParams);
//                    echo"</pre>";
//                    die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try{

                    $levelId = $postParams['levelId'];
                    $sql = new Sql($dbAdapter);

                    if ($levelId==0) {


                        $select = $sql->select();
                        $select->from(array("a"=>"WF_LevelMaster"))
                            ->columns(array("OrderId"))
                            ->where(array('a.DeleteFlag'=>0));
                        $select->order("a.OrderId Desc");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $lastOrder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $orderId=0;
                        if($lastOrder!="") {
                            $orderId=$lastOrder['OrderId'];
                        }
                        $orderId++;
                        $insert = $sql->insert('WF_LevelMaster');
                        $insert->values(array('LevelName'  => $postParams['levelName'],'OrderId'=>$orderId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $levelId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $update = $sql->update("WF_LevelMaster");
                        $update->set(array("LevelName" => $postParams['levelName']))
                            ->where(array("LevelId" => $levelId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('WF_LevelTrans')
                        ->where(array('LevelId'=>$levelId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WF_LevelVariant')
                        ->where(array('LevelId'=>$levelId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $fromToValRowId = $this->bsf->isNullCheck($postParams['criticalrowid'], 'number');
                    for($i=1;$i<$fromToValRowId;$i++) {
                        $iroleId= $this->bsf->isNullCheck($postParams['criticalroleid_'.$i], 'number');
                        $bFound =  isset($postParams['chkcriticalrole_'.$iroleId]) ? 1 : 0;
                        if ($bFound==1) {
                            $insert = $sql->insert('WF_LevelTrans');
                            $insert->values(array(
                                'RoleId' => $iroleId,
                                'LevelId' => $levelId,
                                'ValueFrom' => $this->bsf->isNullCheck($postParams['fromvalue_' . $iroleId], 'number'),
                                'ValueTo' => $this->bsf->isNullCheck($postParams['tovalue_' . $iroleId], 'number')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }


                    $fromToValRowId = $this->bsf->isNullCheck($postParams['variancerowid'], 'number');
                    if($fromToValRowId > 0) {
                        for($i=1;$i<$fromToValRowId;$i++) {
                            $iroleId= $this->bsf->isNullCheck($postParams['varianceroleid_'.$i], 'number');
                            $bFound =  isset($postParams['chkvariancerole_'.$iroleId]) ? 1 : 0;
                            if ($bFound==1) {
                                $insert = $sql->insert('WF_LevelVariant');
                                $insert->values(array(
                                    'RoleId' => $iroleId,
                                    'LevelId' => $levelId,
                                    'Variant' => $this->bsf->isNullCheck($postParams['variance_' . $iroleId], 'number'),
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "organisationlevel"));
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function updatelevelsortorderAction(){
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

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $sql = new Sql($dbAdapter);
                    $arrLevel = json_decode($this->bsf->isNullCheck($postData['arrLevel'],'string'), true);
                    foreach($arrLevel as $trans) {
                        $iLevelId = $trans['LevelId'];
                        $iOrderId = $trans['OrderId'];

                        $update = $sql->update("WF_LevelMaster");
                        $update->set(array("OrderId" => $iOrderId))
                            ->where(array("LevelId" => $iLevelId));
                        $updateStmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setStatusCode(200);
                    return $response;

                } catch(PDOException $e){
                    $connection->rollback();
                    $response = $this->getResponse()->setStatusCode(400);
                    return $response;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

            }

            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}