<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wpm\Controller;

use Zend\Json\Expr;
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
use Application\View\Helper\Qualifier;

use PHPExcel;
use PHPExcel_IOFactory;

class LabourstrengthController extends AbstractActionController
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

    public function entryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Strength");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $labourId = $this->params()->fromRoute('labourId');
        $this->_view->labourId = (isset($labourId) && $labourId != 0) ? $labourId : 0;


        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $files = $request->getFiles();

                $upUrl = '';
                if ($files['file']['name']) {
                    $dir = 'public/uploads/wpm/labour/entry_xlsx/';
                    $filename = $this->bsf->uploadFile($dir, $files['file']);
                    if ($filename) {
                        $upUrl = '/uploads/wpm/labour/entry_xlsx/'.$filename;

                    }
                }

                $postData['URL'] = $upUrl;

                $path = $_SERVER['DOCUMENT_ROOT'] . $viewRenderer->basePath();
                $file_csv = "public/uploads/wpm/labour/" . md5(time()) . ".csv";
                $this->_convertXLStoCSV($path .$upUrl, $file_csv);

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
                //$this->_view->excelData = $data;
                // delete csv file
                fclose($file);
                unlink($file_csv);

                $this->_view->setTerminal(false);
                $response = $this->getResponse()->setContent(json_encode($data));
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

                    $ilabourId = $this->bsf->isNullCheck($postData['labourId'], 'number');
                    if($postData['back_index'] == 1){
//                        echo '<pre>'; print_r($postData); die;
                        $this->_view->backvalues=$postData;
                       /* $this->_view->LabourNameWise=$postData['LabourNameWise'];
                        $this->_view->backCostCentreName=$postData['backCostCentreName'];*/
                    }

//                    if ($ilabourId == 0) {
//
//
//                        $insert = $sql->insert();
//                        $insert->into('WPM_LabourStrengthRegister');
//                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
//                        , 'LSDate' => date('Y-m-d', strtotime($postData['labDate']))
//                        , 'EntryForm' => $this->bsf->isNullCheck($postData['entryForm'], 'string')
//                        , 'WBSRequired' => $this->bsf->isNullCheck($postData['mtWbs'], 'number')
//                        , 'LabourNameWise' => $this->bsf->isNullCheck($postData['mtLabour'], 'number')
//                        , 'DeleteFlag' => $this->bsf->isNullCheck('0', 'number')));
//                        $statement = $sql->getSqlStringForSqlObject($insert);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        $labourId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                        $upUrl = '';
//                        if ($files['upFile']['name']) {
//                            $dir = 'public/uploads/wpm/labour/' . $labourId . '/';
//                            $filename = $this->bsf->uploadFile($dir, $files['upFile']);
//                            if ($filename) {
//                                // update valid files only
//                                $upUrl = '/uploads/wpm/labour/' . $labourId . '/' . $filename;
//                            }
//                        }
//
//                        $update = $sql->update();
//                        $update->table('WPM_LabourStrengthRegister');
//                        $update->set(array('URL' => $upUrl));
//                        $update->where(array('LSRegisterId' => $labourId));
//                        $statement = $sql->getSqlStringForSqlObject($update);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $connection->commit();
//
//                        $this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'entry-form', 'labourId' => $labourId, 'type' => 'e'));
//                    } else {
//                        $upUrl = '';
//                        if ($files['upFile']['name']) {
//                            $dir = 'public/uploads/wpm/labour/' . $ilabourId . '/';
//                            $filename = $this->bsf->uploadFile($dir, $files['upFile']);
//                            if ($filename) {
//                                // update valid files only
//                                $upUrl = '/uploads/wpm/labour/' . $ilabourId . '/' . $filename;
//                            }
//                        }
//
//                        $update = $sql->update();
//                        $update->table('WPM_LabourStrengthRegister');
//                        $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
//                        , 'LSDate' => date('Y-m-d', strtotime($postData['labDate']))
//                        , 'EntryForm' => $this->bsf->isNullCheck($postData['entryForm'], 'string')
//                        , 'WBSRequired' => $this->bsf->isNullCheck($postData['mtWbs'], 'number')
//                        , 'LabourNameWise' => $this->bsf->isNullCheck($postData['mtLabour'], 'number')
//                        , 'URL' => $upUrl));
//                        $update->where(array('LSRegisterId' => $ilabourId));
//                        $statement = $sql->getSqlStringForSqlObject($update);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $connection->commit();
//                        $this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'entry-form', 'labourId' => $ilabourId, 'type' => 'e'));
//                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

//            if ($this->_view->labourId != 0) {
//                //Labour Strength Register
//                $select = $sql->select();
//                $select->from(array('a' => 'WPM_LabourStrengthRegister'))
//                    ->where('a.LSRegisterId = ' . $labourId);
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->lsRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            }

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName"),'wbs'=>new Expression("WBSReqWPM")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function entryFormAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Strength");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $labourId = $this->params()->fromRoute('labourId');
        $typeLs = $this->params()->fromRoute('type');

        $this->_view->labourId = (isset($labourId) && $labourId != 0) ? $labourId : 0;
        $this->_view->typeLs = (isset($typeLs) && $typeLs != '') ? $typeLs : '';
        $CostCentreId = 0;
        //Labour Strength Register
        if ($labourId != '') {
            //$this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'entry'));
            $select = $sql->select();
            $select->from(array('a' => 'WPM_LabourStrengthRegister'))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName','WBSReqWPM','wbsvalue' => new Expression("b.WBSReqWPM"),))
                ->where('a.LSRegisterId = ' . $labourId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->lsRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $CostCentreId = $this->_view->lsRegister['CostCentreId'];
            $wbscheck=$this->_view->lsRegister['WBSReqWPM'];
        }

        /*if(empty($this->_view->lsRegister)) {
            $this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'entry'));
        }*/

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $costcentrid = $postParams['CostcentreId'];
                $type= $postParams['type'];
                switch($type)
                {
                    case 'labourlist':
                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_LabourMaster'))
                            ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Vendor_Master'), 'a.VendorId = d.VendorId', array('GroupName' =>
                                new Expression("Case When a.VendorId !=0 then d.VendorName else b.LabourGroupName + '(Internal)' end"), 'VendorgroupId' =>
                                new Expression("Case When a.VendorId !=0 then d.VendorId else b.LabourGroupID  end")), $select::JOIN_LEFT)
                            ->where(array("CostCentreId=$costcentrid and Deactivate=0"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $laboutlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response = $this->getResponse();
                        $response->setContent(json_encode($laboutlist));
                        return $response;
                        break;
                    case 'rateqty':

                        $resourceIds = $postParams['resourceIds'];

                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('ProjectId'))
                            ->where(array('a.Deactivate' => 0, 'a.CostCentreId' => $costcentrid));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $projectId = $costcenter['ProjectId'];

                        $ratearray=array();

                        $ratearray['ORate']=0;
                        $ratearray['PRate']=0;
                        $ratearray['PqRate']=0;

                        $select =$sql->select();
                        $select->from(array('a' => 'WPM_LRATypeTrans'))
                            ->columns(array('Rate'=>new Expression("top 1 a.Rate")))
                            ->join(array('b' => 'WPM_LRARegister'),' a.LRARegisterId=b.LRARegisterId', array())
                            ->where(array("a.ResourceId = $resourceIds and b.CostCentreId=$costcentrid"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rateorg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(isset($rateorg['Rate']))
                        {
                            $ratearray['ORate']=$rateorg['Rate'];
                        }



                        $select = $sql->select();
                        $select->from('Proj_ProjectResource')
                            ->columns(array('Rate'))
                            ->where(array("ProjectId =$projectId and ResourceId=$resourceIds"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rateorg2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(isset($rateorg2->Rate))
                        {
                            $ratearray['PRate']=$rateorg2->Rate;
                        }


                        $select = $sql->select();
                        $select->from('Proj_Resource')
                            ->columns(array('Rate'))
                            ->where(array("ResourceId=$resourceIds"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $rateorg3= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(isset($rateorg3->Rate))
                        {
                            $ratearray['PqRate']= $rateorg3->Rate;
                        }

                        $this->_view->setTerminal(false);
                        $response = $this->getResponse();
                        $response->setContent(json_encode($ratearray));
                        return $response;
                        break;
                }

            }

        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                   // print_r($postData);die;
                   // print_r($postData);die;
                    $wbscheck= $postData['wbscheck'];

                    $files = $request->getFiles();

                    if (!is_null($postData['frm_index'])) {
                        if (!isset($postData['LabourNameWise'])) {
                            $postData['LabourNameWise'] = 0;
                        }
                        $CostCentreId = $postData['CostCentreId'];

                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('CostCentreName'))
                            ->where(array('a.Deactivate' => 0, 'a.CostCentreId' => $CostCentreId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $postData['CostCentreName'] = $costcenter['CostCentreName'];

                        $upUrl = '';
                        if ($files['upFile']['name']) {
                            $dir = 'public/uploads/wpm/labour/entry_xlsx/';
                            $filename = $this->bsf->uploadFile($dir, $files['upFile']);
                            if ($filename) {
                                // update valid files only
                                $upUrl = '/uploads/wpm/labour/entry_xlsx/' . $filename;
                            }
                        }

                        $postData['URL'] = $upUrl;
                        $this->_view->lsRegister = $postData;
                        $this->_view->typeLs = 'e';
                        //$this->_view->filename=$filename;
                    } else {
                        $lsRegId = $this->bsf->isNullCheck($postData['labourId'], 'number');
                        $lsNo = $postData['lsNo'];
                        $lsCCNo = $postData['lsCCNo'];
                        $lsCoNo = $postData['lsCompNo'];

                        if ($postData['typeLs'] == 'e') {
                            $select = $sql->select();
                            $select->from(array('a' => 'WF_OperationalCostCentre'))
                                ->columns(array('CompanyId'))
                                ->where(array('a.CostCentreId' => $postData['costCentreId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $lsaVNo = CommonHelper::getVoucherNo(406, date('Y-m-d', strtotime($postData['labDate'])), 0, 0, $dbAdapter, "I");
                            if ($lsaVNo["genType"] == true) {
                                $lsNo = $lsaVNo["voucherNo"];
                                //echo $lsNo;die;
                            } else {
                                $lsNo = $postData['lsNo'];
                                //echo $lsNo;die;
                            }

                            $lsccaVNo = CommonHelper::getVoucherNo(406, date('Y-m-d', strtotime($postData['labDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                            if ($lsccaVNo["genType"] == true) {
                                $lsCCNo = $lsccaVNo["voucherNo"];
                            } else {
                                $lsCCNo = $postData['lsCCNo'];
                            }

                            $lscoaVNo = CommonHelper::getVoucherNo(406, date('Y-m-d', strtotime($postData['labDate'])), $costcenter['CompanyId'], 0, $dbAdapter, "I");
                            if ($lscoaVNo["genType"] == true) {
                                $lsCoNo = $lscoaVNo["voucherNo"];
                            } else {
                                $lsCoNo = $postData['lsCompNo'];
                            }
                        }

                        if ($lsRegId == 0) {
                            //print_r($postData);die;
                            if($wbscheck==0)
                            {
                                $wbs=0;
                            }
                            else if($wbscheck==1)
                            {
                                $wbs=$this->bsf->isNullCheck($postData['WBSRequired'], 'number');
                            }
                            $insert = $sql->insert();
                            $insert->into('WPM_LabourStrengthRegister');
                            $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                            , 'LSDate' => date('Y-m-d', strtotime($postData['labDate']))
                            , 'EntryForm' => $this->bsf->isNullCheck($postData['EntryForm'], 'string')
                            , 'WBSRequired' => $wbs
                            , 'LabourNameWise' => $this->bsf->isNullCheck($postData['LabourNameWise'], 'number')
                            , 'LSNo' => $this->bsf->isNullCheck($lsNo, 'string')
                            , 'LSCCNo' => $this->bsf->isNullCheck($lsCCNo, 'string')
                            , 'LSCompNo' => $this->bsf->isNullCheck($lsCoNo, 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'DeleteFlag' => $this->bsf->isNullCheck('0', 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $lsRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $inType = 'N';
                            $inName = 'WPM-LabourStrength-Add';
                            $inDesc = 'LabourStrength-Add';
                            $sRefNo = $postData['refNo'];

                        } else {
                            $update = $sql->update();
                            $update->table('WPM_LabourStrengthRegister');
                            $update->set(array('LSDate' => date('Y-m-d', strtotime($postData['labDate']))
                            , 'LSNo' => $this->bsf->isNullCheck($lsNo, 'string')
                            , 'LSCCNo' => $this->bsf->isNullCheck($lsCCNo, 'string')
                            , 'LSCompNo' => $this->bsf->isNullCheck($lsCoNo, 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                            $update->where(array('LSRegisterId' => $lsRegId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $inType = 'E';
                            $inName = 'WPM-LabourStrength-Edit';
                            $inDesc = 'LabourStrength-Edit';
                            $sRefNo = $postData['refNo'];
                        }

                        $subQuery = $sql->select();
                        $subQuery->from('WPM_LSVendorTrans')
                            ->columns(array('LSVendorTransId'))
                            ->where(array('LSRegisterId' => $lsRegId));

                        $delete = $sql->delete();
                        $delete->from('WPM_LSWBSTrans')
                            ->where->expression('LSVendorTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_LSTypeTrans')
                            ->where->expression('LSVendorTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_LSVendorTrans')
                            ->where("LSRegisterId = $lsRegId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_LSLabourTrans')
                            ->where("LSRegisterId = $lsRegId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $conRows = $this->bsf->isNullCheck($postData['conRows'], 'number');
                        for ($i = 1; $i <= $conRows; $i++) {
                            $grpvendortype=$this->bsf->isNullCheck($postData['grpvendortype_' . $i], 'string');
                            if($grpvendortype=='V')
                            {
                                $contractorid= $this->bsf->isNullCheck($postData['conId_' . $i], 'number');
                                $venId=0;
                            }
                            else if($grpvendortype=='G')
                            {
                                $contractorid =0;
                                $venId=$this->bsf->isNullCheck($postData['conId_' . $i], 'number');
                            }
                            $conId = $this->bsf->isNullCheck($postData['conId_' . $i], 'number');
                            if ($conId != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_LSVendorTrans');
                                $insert->Values(array('LSRegisterId' => $lsRegId
                                , 'VendorId' => $contractorid
                                ,  'GroupId'=> $venId
                                , 'Qty' => $this->bsf->isNullCheck($postData['conQty_' . $i], 'number')
                                , 'Amount' => $this->bsf->isNullCheck($postData['conAmount_' . $i], 'number')
                                , 'OTHrs' => $this->bsf->isNullCheck($postData['conOtHrs_' . $i], 'number')
                                , 'OTAmount' => $this->bsf->isNullCheck($postData['conOtAmount_' . $i], 'number')
                                , 'NetAmount' => $this->bsf->isNullCheck($postData['conNetAmount_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $lsvTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                if ($wbscheck == 1)
                                {
                                    $wbsRows = $this->bsf->isNullCheck($postData['wbsRows_' . $i], 'number');
                                    for ($j = 1; $j <= $wbsRows; $j++) {
                                        $wbsId = $this->bsf->isNullCheck($postData['wbsId_' . $conId . '_' . $j], 'number');

                                        if ($wbsId != 0) {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_LSWBSTrans');
                                            $insert->Values(array('LSVendorTransId' => $lsvTransId
                                            , 'WBSId' => $wbsId
                                            , 'Qty' => $this->bsf->isNullCheck($postData['wbsQty_' . $conId . '_' . $j], 'number')
                                            , 'Amount' => $this->bsf->isNullCheck($postData['wbsAmount_' . $conId . '_' . $j], 'number')
                                            , 'OTHrs' => $this->bsf->isNullCheck($postData['wbsOtHrs_' . $conId . '_' . $j], 'number')
                                            , 'OTAmount' => $this->bsf->isNullCheck($postData['wbsOtAmount_' . $conId . '_' . $j], 'number')
                                            , 'NetAmount' => $this->bsf->isNullCheck($postData['wbsNetAmount_' . $conId . '_' . $j], 'number')));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $lswbsTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $labRows = $this->bsf->isNullCheck($postData['labRows_' . $conId . '_' . $j], 'number');
                                            for ($k = 1; $k <= $labRows; $k++) {
                                                $labId = $this->bsf->isNullCheck($postData['labId_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number');

                                                if ($labId != 0) {
                                                    $insert = $sql->insert();
                                                    $insert->into('WPM_LSTypeTrans');
                                                    $insert->Values(array('LSVendorTransId' => $lsvTransId
                                                    , 'LSWBSTransId' => $lswbsTransId
                                                    , 'ResourceId' => $labId
                                                    , 'Qty' => $this->bsf->isNullCheck($postData['labQty_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'Rate' => $this->bsf->isNullCheck($postData['labRate_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'Amount' => $this->bsf->isNullCheck($postData['labAmount_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'OTHrs' => $this->bsf->isNullCheck($postData['labOtHrs_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'OTRate' => $this->bsf->isNullCheck($postData['labOtRate_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'OTAmount' => $this->bsf->isNullCheck($postData['labOtAmount_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')
                                                    , 'NetAmount' => $this->bsf->isNullCheck($postData['labNetAmount_' . $conId . '_' . $wbsId . '_' . $j . '_' . $k], 'number')));
                                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }

                                            }
                                        }

                                    }
                                } //end
                                else
                                {
                                    $labRows = $this->bsf->isNullCheck($postData['labRows_' . $i], 'number');
                                    for ($k = 1; $k <= $labRows; $k++) {
                                        $labId = $this->bsf->isNullCheck($postData['labId_' . $i . '_' .$k], 'number');

                                        if ($labId != 0) {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_LSTypeTrans');
                                            $insert->Values(array('LSVendorTransId' => $lsvTransId
                                            , 'LSWBSTransId' => $wbscheck
                                            , 'ResourceId' => $labId
                                            , 'Qty' => $this->bsf->isNullCheck($postData['labQty_' . $i . '_' . $k], 'number')
                                            , 'Rate' => $this->bsf->isNullCheck($postData['labRate_' . $i . '_'. $k], 'number')
                                            , 'Amount' => $this->bsf->isNullCheck($postData['labAmount_' . $i . '_' . $k], 'number')
                                            , 'OTHrs' => $this->bsf->isNullCheck($postData['labOtHrs_' . $i. '_' . $k], 'number')
                                            , 'OTRate' => $this->bsf->isNullCheck($postData['labOtRate_' . $i . '_' . $k], 'number')
                                            , 'OTAmount' => $this->bsf->isNullCheck($postData['labOtAmount_' . $i . '_' . $k], 'number')
                                            , 'NetAmount' => $this->bsf->isNullCheck($postData['labNetAmount_' . $i . '_' . $k], 'number')));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                    }


                                }

                            }
                        }

                        $labConRows = $this->bsf->isNullCheck($postData['labConRows'], 'number');
                        for ($i = 1; $i <= $labConRows; $i++) {
                            $labWbsId = 0;
                            $labConId = $this->bsf->isNullCheck($postData['labConId_' . $i], 'number');

                            if ($postData['mtWbs'] == 1) {
                                $labWbsId = $this->bsf->isNullCheck($postData['labWbsId_' . $i], 'number');
                                $labWbsName = $this->bsf->isNullCheck($postData['labWbsName_' . $i], 'string');

                                if ($labWbsId == 0) {
                                    if ($labWbsName != '') {
                                        $insert = $sql->insert();
                                        $insert->into('Proj_WBSMaster');
                                        $insert->Values(array('WBSName' => $labWbsName, 'ProjectId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $labWbsId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }
                            }

                            $inTime = date('Y-m-d H:i', strtotime($postData['labConDate_' . $i] . ' ' . $postData['labConInTime_' . $i]));
                            $outTime = date('Y-m-d H:i', strtotime($postData['labConDate_' . $i] . ' ' . $postData['labConOutTime_' . $i]));

                            if ($labConId != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_LSLabourTrans');
                                $insert->Values(array('LSRegisterId' => $lsRegId
                                , 'LabourId' => $labConId
                                , 'WBSId' => $this->bsf->isNullCheck($postData['labWbsId_' . $i], 'number')
                                , 'InTime' => $inTime
                                , 'OutTime' => $outTime
                                , 'Qty' => $this->bsf->isNullCheck($postData['labConQty_' . $i], 'number')
                                , 'Rate' => $this->bsf->isNullCheck($postData['labConRate_' . $i], 'number')
                                , 'Amount' => $this->bsf->isNullCheck($postData['labConAmount_' . $i], 'number')
                                , 'OTHrs' => $this->bsf->isNullCheck($postData['labConOtHrs_' . $i], 'number')
                                , 'OTRate' => $this->bsf->isNullCheck($postData['labConOtRate_' . $i], 'number')
                                , 'OTAmount' => $this->bsf->isNullCheck($postData['labConOtAmount_' . $i], 'number')
                                , 'NetAmount' => $this->bsf->isNullCheck($postData['labConNetAmount_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        }

                        $this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'labour-strength-register'));
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $lsRegId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($CostCentreId == 0) {
                $this->redirect()->toRoute('wpm/entry-form', array('controller' => 'labourstrength', 'action' => 'entry'));
            }

            $select = $sql->select();
            $select->from(array('a' => 'WPM_LabourMaster'))
                ->columns(array('LabourId', 'LabourName', 'LabourTypeId', 'LabourGroupId', 'IsCheck' => new Expression("'0'")))
                ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_Resource'), 'a.LabourTypeId = c.ResourceId', array('ResourceName'))
                ->join(array('d' => 'Vendor_Master'), 'a.VendorId = d.VendorId', array('Contractor' => new Expression("Case When a.VendorId !=0 then d.VendorName else b.LabourGroupName + '(Internal)' end")), $select::JOIN_LEFT)
                ->where('CostCentreId = ' . $CostCentreId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('WPM_labourMaster')
                ->columns(array('LabourName'))
                ->where('Deactivate = 0 and CostCentreId= '.$CostCentreId.'');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'WPM_LabourMaster'))
                ->columns(array('data' => 'LabourId', 'value' => 'LabourName','IsCheck' => new Expression("'0'")))
                ->where('Deactivate = 0 and CostCentreId= '.$CostCentreId.'');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourlistforcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Getting ProjectId from Operational CostCentre
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('ProjectId'))
                ->where(array('a.Deactivate' => 0, 'a.CostCentreId' => $CostCentreId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $projectId = $costcenter['ProjectId'];

            //WBS Master
            $select = $sql->select();
            $select->from('Proj_WBSMaster')
                ->columns(array('data' => 'WBSId', 'value' => 'WBSName'))
                ->where(array('ProjectId' => $projectId, 'LastLevel' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->wbsMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



            if ($labourId != '') {
                //Labour Strength Vendor Trans
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
            }

            if ($this->_view->lsRegister['EntryForm'] == 'E' ) {

                $path = $_SERVER['DOCUMENT_ROOT'] . $viewRenderer->basePath();
                $file_csv = "public/uploads/wpm/labour/" . md5(time()) . ".csv";
                $this->_convertXLStoCSV($path . $this->_view->lsRegister['URL'], $file_csv);

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

                $this->_view->excelData = $data;

                // delete csv file
                fclose($file);
                unlink($file_csv);
            }

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('data' => 'VendorId', "type" => new Expression("'V'"), 'value' => 'VendorName', "IsCheck"))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Labour Group Master
            $select = $sql->select();
            $select->from('WPM_LabourGroupMaster')
                ->columns(array("data" => 'LabourGroupId', "type" => new Expression("'G'"), "value" => new Expression("LabourGroupName + '(Internal)' "),'IsCheck' => new Expression("'0'")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->groupMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Labour Master
            $select = $sql->select();
            $select->from('WPM_LabourMaster')
                ->columns(array('data' => 'LabourId', 'value' => 'LabourName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //labour type list

            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array("data" => 'ResourceId', "value" => 'ResourceName', "rate" => 'Rate'))
                ->where(array('TypeId' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Field Data
            //$labFieldData = array('1' => 'Labour Name', '2' => 'WBS Name', '3' => 'In Time', '4' => 'Out Time', '5' => 'Day', '6' => 'Rate', '7' => 'Amount', '8' => 'OT Hours', '9' => 'OT Rate', '10' => 'OT Amount', '11' => 'Net Amount');
            $fieldData = array('1' => 'Contractor Name', '2' => 'WBS Name', '3' => 'Labour Name', '4' => 'Labour Type', '5' => 'Date', '6' => 'In Time', '7' => 'Out Time', '8' => 'Day', '9' => 'Hours', '10' => 'Qty', '11' => 'Rate', '12' => 'OT Hours', '13' => 'OT Rate');
            $this->_view->assignData = $fieldData;

            $aVNo = CommonHelper::getVoucherNo(406, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->lsNo = $aVNo["voucherNo"];
            else
                $this->_view->lsNo = "";

            $this->_view->lsTypeId = '406';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getExcelDataAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_labourMaster')
                    ->columns(array('LabourName'))
                    ->where('Deactivate = 0 and CostCentreId= '.$postData['costCentreId'].'');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labourName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $path = $_SERVER['DOCUMENT_ROOT'] . $viewRenderer->basePath();
                $file_csv = "public/uploads/wpm/labour/entry_xlsx/". md5(time()) . ".csv";
                $this->_convertXLStoCSV($path . $postData['fileUrl'], $file_csv);

                $file = fopen($file_csv, "r");
                $icount = 0;
                $exArray = array();
                while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                    if ($icount != 0) {
                        $ContractorName = '';
                        $WBSName = '';
                        $LabourName = '';
                        $LabourType = '';
                        $Date = '';
                        $InTime = '';
                        $OutTime = '';
                        $Day = '';
                        $Hours = '';
                        $Qty = '';
                        $Rate = '';
                        $OTHours = '';
                        $OTRate = '';

                        $exRows = $this->bsf->isNullCheck($postData['excelCount'], 'number');
                        for ($i = 1; $i <= $exRows; $i++) {
                            if (isset($postData['dtField_' . $i])) {
                                $expData = explode('##', $postData['dtField_' . $i]);
                                $expVal = $expData[0];
                                $expVar = $expData[1];
                                if ($xlData[$expVal - 1] != '') {
                                    $$expVar = $xlData[$expVal - 1];
                                    $exArray[$expVar][] = $$expVar;
                                }
                            }
                        }
                    }
                    $icount = $icount + 1;
                }
                $this->_view->exlArray = $exArray;

                // delete csv file
                fclose($file);
                unlink($file_csv);

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        }
    }

    public function labourMasterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
//				$connection = $dbAdapter->getDriver()->getConnection();
//				$connection->beginTransaction();
//
//				try {
//					$postData = $request->getPost();
//
//					$subType = $postData['subType'];
//					$labourId = $this->bsf->isNullCheck($postData['labourId'],'number');
//					$labName = $this->bsf->isNullCheck($postData['labourName'],'string');
//					$lgName = $this->bsf->isNullCheck($postData['lgName'],'string');
//
//                    $vendorId = 0;
//                    $labGrpId = 0;
//                    if(isset($postData['vendorId'])) {
//                        $vendorId = $this->bsf->isNullCheck($postData['vendorId'], 'number');
//                    }
//                    if(isset($postData['labourGroupId'])) {
//                        $labGrpId = $this->bsf->isNullCheck($postData['labourGroupId'], 'number');
//                    }
//					if($labourId == 0) {
//						if($labName != '' && $subType == 'l') {
//							$insert = $sql->insert();
//							$insert->into('WPM_LabourMaster');
//							$insert->Values(array('LabourName' => $labName
//								, 'VendorId' => $vendorId
//                                , 'LabourGroupId' => $labGrpId
//								, 'LabourTypeId' => $this->bsf->isNullCheck($postData['labourTypeId'],'number')
//								, 'Code' => $this->bsf->isNullCheck($postData['idNo'],'string')
//								, 'Address' => $this->bsf->isNullCheck($postData['address'],'string')
//								, 'CityId' => $this->bsf->isNullCheck($postData['cityId'],'number')
//								, 'PinCode' => $this->bsf->isNullCheck($postData['pinCode'],'string')
//								, 'Mobile' => $this->bsf->isNullCheck($postData['mobile'],'string')
//								, 'Email' => $this->bsf->isNullCheck($postData['email'],'string')
//								, 'PFNo' => $this->bsf->isNullCheck($postData['pfNo'],'string')
//								, 'ESINo' => $this->bsf->isNullCheck($postData['esiNo'],'string')));
//							$statement = $sql->getSqlStringForSqlObject($insert);
//							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//						}
//					} else {
//						$update = $sql->update();
//						$update->table('WPM_LabourMaster');
//						$update->set(array('LabourName' => $labName
//                            , 'VendorId' => $vendorId
//                            , 'LabourGroupId' => $labGrpId
//                            , 'LabourTypeId' => $this->bsf->isNullCheck($postData['labourTypeId'],'number')
//                            , 'Code' => $this->bsf->isNullCheck($postData['idNo'],'string')
//                            , 'Address' => $this->bsf->isNullCheck($postData['address'],'string')
//                            , 'CityId' => $this->bsf->isNullCheck($postData['cityId'],'number')
//                            , 'PinCode' => $this->bsf->isNullCheck($postData['pinCode'],'string')
//                            , 'Mobile' => $this->bsf->isNullCheck($postData['mobile'],'string')
//                            , 'Email' => $this->bsf->isNullCheck($postData['email'],'string')
//                            , 'PFNo' => $this->bsf->isNullCheck($postData['pfNo'],'string')
//                            , 'ESINo' => $this->bsf->isNullCheck($postData['esiNo'],'string')));
//						$update->where(array('LabourId' => $labourId));
//						$statement = $sql->getSqlStringForSqlObject($update);
//						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//					}
//
//					if($lgName != '' && $subType == 'g') {
//						$insert = $sql->insert();
//						$insert->into('WPM_LabourGroupMaster');
//						$insert->Values(array('LabourGroupName' => $lgName));
//						$statement = $sql->getSqlStringForSqlObject($insert);
//						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//					}
//
//					$connection->commit();
//					$this->redirect()->toRoute('wpm/labour-master', array('controller' => 'labourstrength', 'action' => 'labour-master'));
//				} catch(PDOException $e){
//					$connection->rollback();
//					print "Error!: " . $e->getMessage() . "</br>";
//				}
            }

            //Vendor Master
//			$select = $sql->select();
//			$select->from( 'Vendor_Master' )
//					->columns(array('VendorId', 'VendorName'))
//					->where(array('Contract' => 1));
//			$statement = $sql->getSqlStringForSqlObject( $select );
//			$this->_view->vendorMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//
//			//Labour Group Master
//			$select = $sql->select();
//			$select->from( 'WPM_LabourGroupMaster' )
//					->columns(array('LabourGroupId', 'LabourGroupName'));
//			$statement = $sql->getSqlStringForSqlObject( $select );
//			$this->_view->labourGroupMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//
//            //Type Master
//            $select = $sql->select();
//            $select->from( 'Proj_Resource' )
//                ->columns(array('ResourceId', 'ResourceName'))
//                ->where(array('TypeId' => 1));
//            $statement = $sql->getSqlStringForSqlObject( $select );
//            $this->_view->typeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//
//			//City Master
//			$select = $sql->select();
//			$select->from( 'WF_CityMaster' )
//					->columns(array('CityId', 'CityName'));
//			$statement = $sql->getSqlStringForSqlObject( $select );
//			$this->_view->cityMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//
//            //State Master
//            $select = $sql->select();
//            $select->from( 'WF_StateMaster' )
//                ->columns(array('StateId', 'StateName'));
//            $statement = $sql->getSqlStringForSqlObject( $select );
//            $this->_view->stateMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//
//            //Country Master
//            $select = $sql->select();
//            $select->from( 'WF_CountryMaster' )
//                ->columns(array('CountryId', 'CountryName'));
//            $statement = $sql->getSqlStringForSqlObject( $select );
//            $this->_view->countryMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            $select = $sql->select();
            $select->from(array('a' => 'WPM_LabourMaster'))
                ->join(array('b' => 'WF_CityMaster'), 'a.CityId = b.CityId', array('CityName'), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_OperationalCostCentre'), 'a.CostCentreId = c.CostCentreId', array(), $select::JOIN_LEFT)
                ->join(array('d' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId= d.LabourGroupId', array(), $select::JOIN_LEFT)
                ->join(array('e' => 'Vendor_Master'), 'a.VendorId= e.VendorId', array(), $select::JOIN_LEFT)
                ->join(array('f' => 'Proj_Resource'), 'a.LabourTypeId= f.ResourceId', array(), $select::JOIN_LEFT)
                ->join(array('g' => 'WPM_LabourUsed'), 'a.LabourId=g.LabourId', array('Used' => new Expression("Case When g.LabourId is null then 'No' else 'Yes' end")), $select::JOIN_LEFT)
                ->columns(array('LabourId', 'Code', 'LabourName', 'TypeName' => new Expression("f.ResourceName"),
                    'Contractor' => new Expression("Case When a.LabourGroupId <>0 then d.LabourGroupName else e.VendorName end"),
                    'GroupType' => new Expression("Case When a.LabourGroupId <>0 then 'Internal' else 'Vendor' end"),
                    'CostCentre' => new Expression("c.CostCentreName"), 'CityName' => new Expression('b.CityName'), 'Mobile'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function lmImportAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Entry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $upUrl = '';
        $fieldData = array('1' => 'Labour Name', '2' => 'ID No', '3' => 'Address', '4' => 'City', '5' => 'Pin Code', '6' => 'Mobile', '7' => 'Email', '8' => 'PF No', '9' => 'ESI No', '10' => 'Labour Type', '11' => 'Group Name', '12' => 'Vendor');

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $files = $request->getFiles();
                    $postData = $request->getPost();

                    if (!is_null($postData['excelCount'])) {
                        $path = $_SERVER['DOCUMENT_ROOT'] . $viewRenderer->basePath();
                        $file_csv = "public/uploads/wpm/labourmaster/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($path . $postData['uplUrl'], $file_csv);

                        $file = fopen($file_csv, "r");

                        $refNo = '';
                        $refVNo = CommonHelper::getVoucherNo(416, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                        if ($refVNo["genType"] == true) {
                            $refNo = $refVNo["voucherNo"];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_LabourRegister');
                        $insert->Values(array('RefDate' => date('Y-m-d')
                        , 'RefNo' => $this->bsf->isNullCheck($refNo, 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iLabourId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $icount = 0;
                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                            if ($icount != 0) {
                                $error = 0;
                                $LabourName = '';
                                $IDNo = '';
                                $Address = '';
                                $City = '';
                                $PinCode = '';
                                $Mobile = '';
                                $Email = '';
                                $PFNo = '';
                                $ESINo = '';
                                $LabourType = '';
                                $GroupName = '';
                                $Vendor = '';

                                $exRows = $this->bsf->isNullCheck($postData['excelCount'], 'number');
                                for ($i = 1; $i <= $exRows; $i++) {
                                    if (isset($postData['dtField_' . $i]) && $postData['dtField_' . $i] != '') {
                                        $expData = explode('##', $postData['dtField_' . $i]);
                                        $expVal = $expData[0];
                                        $expVar = $expData[1];
                                        if ($xlData[$expVal - 1] != '') {
                                            $$expVar = trim($xlData[$expVal - 1]);
                                        }
                                    }
                                }

                                if ($City != '') {
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WF_CityMaster'))
                                        ->columns(array('CityId'))
                                        ->where(array('a.CityName' => $City));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $checkCity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    if ($checkCity['CityId'] != '') {
                                        $City = $checkCity['CityId'];
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('WF_CityMaster');
                                        $insert->Values(array('CityName' => $City));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $City = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }
                                if ($LabourType != '') {
                                    $select = $sql->select();
                                    $select->from(array('a' => 'Proj_Resource'))
                                        ->columns(array('ResourceId'))
                                        ->where(array('a.ResourceName' => $LabourType, 'a.TypeId' => 1));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $checkLabType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    if ($checkLabType['ResourceId'] != '') {
                                        $LabourType = $checkLabType['ResourceId'];
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('Proj_Resource');
                                        $insert->Values(array('ResourceName' => $LabourType, 'TypeId' => 1));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $LabourType = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }
                                if ($GroupName != '') {
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_LabourGroupMaster'))
                                        ->columns(array('LabourGroupId'))
                                        ->where(array('a.LabourGroupName' => $GroupName));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $checkLabGrp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    if ($checkLabGrp['LabourGroupId'] != '') {
                                        $GroupName = $checkLabGrp['LabourGroupId'];
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_LabourGroupMaster');
                                        $insert->Values(array('LabourGroupName' => $GroupName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $GroupName = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    if ($IDNo != '' && $IDNo != 0) {
                                        $select = $sql->select();
                                        $select->from('WPM_LabourTrans')
                                            ->columns(array("Count" => new Expression("Count(*)")))
                                            ->where(array('Code' => $IDNo, 'LabourGroupId' => $GroupName));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $chkResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if ($chkResult['Count'] != 0) {
                                            $error = 1;
                                        }
                                    }
                                }
                                if ($Vendor != '') {
                                    $select = $sql->select();
                                    $select->from(array('a' => 'Vendor_Master'))
                                        ->columns(array('VendorId'))
                                        ->where(array('a.VendorName' => $Vendor, 'a.Contract' => 1));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $checkVendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    if ($checkVendor['VendorId'] != '') {
                                        $Vendor = $checkVendor['VendorId'];
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('Vendor_Master');
                                        $insert->Values(array('VendorName' => $Vendor, 'Contract' => 1));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $Vendor = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    if ($IDNo != '' && $IDNo != 0) {
                                        $select = $sql->select();
                                        $select->from('WPM_LabourTrans')
                                            ->columns(array("Count" => new Expression("Count(*)")))
                                            ->where(array('Code' => $IDNo, 'VendorId' => $Vendor));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $chkResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if ($chkResult['Count'] != 0) {
                                            $error = 1;
                                        }
                                    }
                                }

                                if ($error == 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_LabourTrans');
                                    $insert->Values(array('LabourRegisterId' => $iLabourId
                                    , 'LabourName' => $LabourName
                                    , 'VendorId' => $this->bsf->isNullCheck($Vendor, 'number')
                                    , 'LabourTypeId' => $this->bsf->isNullCheck($LabourType, 'number')
                                    , 'LabourGroupId' => $this->bsf->isNullCheck($GroupName, 'number')
                                    , 'Code' => $this->bsf->isNullCheck($IDNo, 'string')
                                    , 'Address' => $this->bsf->isNullCheck($Address, 'string')
                                    , 'CityId' => $this->bsf->isNullCheck($City, 'number')
                                    , 'PinCode' => $this->bsf->isNullCheck($PinCode, 'string')
                                    , 'Mobile' => $this->bsf->isNullCheck($Mobile, 'string')
                                    , 'Email' => $this->bsf->isNullCheck($Email, 'string')
                                    , 'PFNo' => $this->bsf->isNullCheck($PFNo, 'string')
                                    , 'ESINo' => $this->bsf->isNullCheck($ESINo, 'string')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $icount = $icount + 1;
                        }

                        // delete csv file
                        fclose($file);
                        unlink($file_csv);

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'WPM-Labour-Master-Add', 'N', 'Labour-Add', $iLabourId, 0, 0, 'WPM', $refNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/labour-master', array('controller' => 'labourstrength', 'action' => 'labour-master'));
                    } else if ($files['upFile']['name']) {
                        $dir = 'public/uploads/wpm/labourmaster/';
                        $filename = $this->bsf->uploadFile($dir, $files['upFile']);
                        if ($filename) {
                            // update valid files only
                            $upUrl = '/uploads/wpm/labourmaster/' . $filename;
                        }
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($upUrl != '') {
                $path = $_SERVER['DOCUMENT_ROOT'] . $viewRenderer->basePath();
                $file_csv = "public/uploads/wpm/labourmaster/" . md5(time()) . ".csv";
                $this->_convertXLStoCSV($path . $upUrl, $file_csv);

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

                $this->_view->excelData = $data;

                // delete csv file
                fclose($file);
                unlink($file_csv);
            }

            $this->_view->assignData = $fieldData;
            $this->_view->uplUrl = $upUrl;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getLabourMasterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                if ($postData['labGrpId'] != '') {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_LabourMaster'))
                        ->join(array('b' => 'WF_CityMaster'), 'a.CityId = b.CityId', array('CityName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WF_OperationalCostCentre'), 'a.CostCentreId = c.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->where('a.LabourGroupId = ' . $postData['labGrpId']);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_LabourMaster'))
                        ->join(array('b' => 'WF_CityMaster'), 'a.CityId = b.CityId', array('CityName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WF_OperationalCostCentre'), 'a.CostCentreId = c.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->where('a.VendorId = ' . $postData['vendorId']);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                //$this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        }
    }

    public function checkLabourAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('WPM_LabourMaster')
                    ->columns(array("Count" => new Expression("Count(*)")));
                if ($postParams['lType'] == 1) {
                    $select->where(array('Code' => $postParams['code'], 'LabourGroupId' => $postParams['id']));
                } else {
                    $select->where(array('Code' => $postParams['code'], 'VendorId' => $postParams['id']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function checkLabourCodeAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('WPM_LabourTrans')
                    ->columns(array("Count" => new Expression("Count(*)")));
                if ($postParams['lType'] == 1) {
                    $select->where(array('Code' => $postParams['code'], 'LabourGroupId' => $postParams['id']));
                } else {
                    $select->where(array('Code' => $postParams['code'], 'VendorId' => $postParams['id']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function checkLabourCodePfAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('WPM_LabourTrans')
                    ->columns(array("Count" => new Expression("Count(*)")));
                if ($postParams['lType'] == 1) {
                    $select->where(array('PFNo' => $postParams['code'], 'LabourGroupId' => $postParams['id']));
                } else {
                    $select->where(array('PFNo' => $postParams['code'], 'VendorId' => $postParams['id']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function checkLabourEsiAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('WPM_LabourTrans')
                    ->columns(array("Count" => new Expression("Count(*)")));
                $select->where(array('ESINo' => $postParams['code']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function editDeleteLabourMasterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");


        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $labourId = $postData['labourId'];

                $sql = new Sql($dbAdapter);
                if ($labourId != '' && $postData['type'] == 'e') {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_LabourMaster'))
                        ->join(array('b' => 'WF_CityMaster'), 'a.CityId = b.CityId', array('CityName', 'StateId', 'CountryId'), $select::JOIN_LEFT)
                        //->join(array('c' => 'WF_StateMaster'), 'b.StateId = c.StateId', array('StateName'), $select::JOIN_LEFT)
                        //->join(array('d' => 'WF_CountryMaster'), 'b.CountryId = d.CountryId', array('CountryName'), $select::JOIN_LEFT)
                        ->where('a.LabourId = ' . $labourId);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                } else if ($labourId != '' && $postData['type'] == 'd') {
                    $delete = $sql->delete();
                    $delete->from('WPM_LabourMaster')
                        ->where(array("LabourId" => $labourId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                //$this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        }
    }

    public function rateApprovalEntryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Rate Approval");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $lraId = $this->params()->fromRoute('lraId');
        $mode = $this->params()->fromRoute('mode');
        $this->_view->lraId = (isset($lraId) && $lraId != 0) ? $lraId : 0;
        $this->_view->mode = (isset($mode) && $mode != '') ? $mode : 'a';

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                if (isset($postData['resourceIds']) && $postData['resourceIds'] != '')
                    $resourceIds = substr($postData['resourceIds'], 0, -1);
                else
                    $resourceIds = 0;

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('ProjectId'))
                    ->where(array('a.Deactivate' => 0, 'a.CostCentreId' => $postData['ccId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $projectId = $costcenter['ProjectId'];
                //print_r($projectId);die;
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectResource'))
                    ->columns(array('data' => 'ResourceId', 'Rate', 'Include' => new Expression('1-1')))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('value' => 'ResourceName', 'UnitId' => 'UnitId', 'ARate' => 'Rate'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where(array('a.ProjectId' => $projectId, 'b.TypeId' => 1, 'a.RateType' => 'L'));
                if ($resourceIds != 0) {
                    $select->where('a.ResourceId IN (' . $resourceIds . ')');
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    //print_r($postData);die;
                    $ilraId = $this->bsf->isNullCheck($postData['lraId'], 'number');
                    $iMode = $this->bsf->isNullCheck($postData['mode'], 'string');

                    if ($iMode == 'a' || $iMode == 'ex') {
                        $nlraRegId = $ilraId;

                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('CompanyId'))
                            ->where(array('a.CostCentreId' => $postData['costCentreId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        /*$lraaVNo = CommonHelper::getVoucherNo(412, date('Y-m-d', strtotime($postData['lraDate'])), 0, 0, $dbAdapter, "I");
                        if ($lraaVNo["genType"] == true) {
                            $lraNo = $lraaVNo["voucherNo"];
                        } else {
                            $lraNo = $postData['lraNo'];
                        }*/




                        $wordChunks = explode("_", $postData['lraNo']);
                        if(count($wordChunks) ==1)
                        {
                            $lraNo=$postData['lraNo']."_1";
                        }
                        else if(count($wordChunks) >1)
                        {
                            $word=$wordChunks[1]+1;
                            $lraNo=$wordChunks[0]."_".$word;
                        }

                        //print_r($lraNo);die;
                        $lraccaVNo = CommonHelper::getVoucherNo(412, date('Y-m-d', strtotime($postData['lraDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                        if ($lraccaVNo["genType"] == true) {
                            $lraCCNo = $lraccaVNo["voucherNo"];
                        } else {
                            $lraCCNo = $postData['cclraNo'];
                        }

                        $lracoaVNo = CommonHelper::getVoucherNo(412, date('Y-m-d', strtotime($postData['lraDate'])), $costcenter['CompanyId'], 0, $dbAdapter, "I");
                        if ($lracoaVNo["genType"] == true) {
                            $lraCoNo = $lracoaVNo["voucherNo"];
                        } else {
                            $lraCoNo = $postData['complraNo'];
                        }
                        if ($postData['Type'] == 'g') {
                            $groupId = $this->bsf->isNullCheck($postData['vendorid'], 'number');
                            $vendorId = 0;
                        } else {
                            $vendorId = $this->bsf->isNullCheck($postData['vendorid'], 'number');
                            $groupId = 0;
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_LRARegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                        , 'VendorId' => $vendorId
                        , 'LabourGroupId' => $groupId
                        , 'NLRARegisterId' => $this->bsf->isNullCheck($nlraRegId, 'number')
                        , 'LRADate' => date('Y-m-d', strtotime($postData['lraDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'LRANo' => $this->bsf->isNullCheck($lraNo, 'string')
                        , 'LRACCNo' => $this->bsf->isNullCheck($lraCCNo, 'string')
                        , 'LRACompNo' => $this->bsf->isNullCheck($lraCoNo, 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        , 'Live' => $this->bsf->isNullCheck(1, 'number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ilraId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if ($nlraRegId != 0) {
                            $update = $sql->update();
                            $update->table('WPM_LRARegister');
                            $update->set(array('Live' => $this->bsf->isNullCheck(0, 'number')));
                            $update->where(array('LRARegisterId' => $nlraRegId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $inType = 'N';
                        $inName = 'WPM-LabourRate-Add';
                        $inDesc = 'LabourRate-Add';
                        $sRefNo = $lraNo;
                    } else {
                        $update = $sql->update();
                        $update->table('WPM_LRARegister');
                        $update->set(array('LRADate' => date('Y-m-d', strtotime($postData['lraDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'LRANo' => $this->bsf->isNullCheck($postData['lraNo'], 'string')
                        , 'LRACCNo' => $this->bsf->isNullCheck($postData['cclraNo'], 'string')
                        , 'LRACompNo' => $this->bsf->isNullCheck($postData['complraNo'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')));
                        $update->where(array('LRARegisterId' => $ilraId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $inType = 'E';
                        $inName = 'WPM-LabourRate-Edit';
                        $inDesc = 'LabourRate-Edit';
                        $sRefNo = $postData['lraNo'];
                    }

                    if ($iMode == 'e') {
                        $delete = $sql->delete();
                        $delete->from('WPM_LRATypeTrans')
                            ->where("LRARegisterId = $ilraId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $typeRows = $this->bsf->isNullCheck($postData['typeRows'], 'number');
                    for ($i = 1; $i <= $typeRows; $i++) {
                        $typeId = $this->bsf->isNullCheck($postData['typeId_' . $i], 'number');

                        if ($typeId != 0) {
                            $insert = $sql->insert();
                            $insert->into('WPM_LRATypeTrans');
                            $insert->Values(array('LRARegisterId' => $ilraId
                            , 'ResourceId' => $typeId
                            , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                            , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                            , 'OTRate' => $this->bsf->isNullCheck($postData['otRate_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $ilraId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/rate-approval-entry', array('controller' => 'labourstrength', 'action' => 'rate-approval-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $projectId = 0;
            $ccId = 0;
            if ($this->_view->lraId != 0) {
                //Labour Rate Approval Register
                /*$select = $sql->select();
				$select->from(array('a' => 'WPM_LRARegister'))
					->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
					->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
					->where('a.LRARegisterId = ' . $lraId);*/
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LRARegister'))
                    ->columns(array("LRARegisterId", "Approve","LRANo", "CostCentreId", "Narration","VendorId","LRACCNo", "LRACompNo", "RefNo", 'VendorName' => new Expression("Case When a.VendorId <>0 then c.VendorName else d.LabourGroupName + '(Internal)' end"),
                        "LRADate" => new Expression("FORMAT(a.LRADate, 'dd-MM-yyyy')"),
                        "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"),
                        "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"),
                        "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = d.LabourGroupId', array(), $select::JOIN_LEFT)
                    ->where('a.LRARegisterId = ' . $lraId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $lraRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->lraRegister = $lraRegister;

                if (!empty($lraRegister)) $ccId = $lraRegister['CostCentreId'];

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('ProjectId'))
                    ->where(array('a.Deactivate' => 0, 'a.CostCentreId' => $ccId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if (!empty($costcenter)) $projectId = $costcenter['ProjectId'];

                //Labour Rate Approval Type Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LRATypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('ResourceName', 'AEstRate' => 'Rate'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId = d.ResourceId', array('EstRate' => 'Rate'), $select::JOIN_LEFT)
                    ->where(array('d.ProjectId' => $projectId, 'a.LRARegisterId' => $lraId, 'd.RateType' => 'L'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->lraTypeTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->costCentreId = $ccId;

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                //->columns(array("data" => 'VendorId', "value" => 'VendorName'))
                ->columns(array("id" => 'VendorId', "type" => new Expression("'V'"), "value" => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Labour Group Master
            $select = $sql->select();
            $select->from('WPM_LabourGroupMaster')
                //->columns(array("data" => 'LabourGroupId', "value" => 'LabourGroupName'));
                ->columns(array("id" => 'LabourGroupId', "type" => new Expression("'G'"), "value" => 'LabourGroupName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->groupMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Resource Master
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectResource'))
                ->columns(array('data' => 'ResourceId', 'Rate'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('value' => 'ResourceName', 'UnitId' => 'UnitId', 'ARate' => 'Rate'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
                ->where(array('b.TypeId' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->typeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $aVNo = CommonHelper::getVoucherNo(412, date('Y/m/d'), 0, 0, $dbAdapter, "");

            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->lraNo = $aVNo["voucherNo"];
            else
                $this->_view->lraNo = "";

            $this->_view->lraTypeId = '412';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getOrdersAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_LRARegister')
                    ->columns(array('data' => 'LRARegisterId', 'value' => 'LRANo'))
                    ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId'],'Live=1'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function serviceOrderAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Service Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $serOrdId = $this->bsf->isNullCheck($this->params()->fromRoute('serviceOrderId'),'number');
        $amdId = $this->bsf->isNullCheck($this->params()->fromRoute('amdId'), 'number');
//        $typeSo = $this->bsf->isNullCheck($this->params()->fromRoute('type'), 'string');
        $this->_view->serOrdId = (isset($serOrdId) && $serOrdId != 0) ? $serOrdId : 0;
//        $this->_view->typeSo = (isset($typeSo) && $typeSo != '') ? $typeSo : '';
        $this->_view->frmInd = 0;
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $select = $sql->select();
                $select->from(array("d" => "VM_RequestTrans"))
                    ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'CostCentreId'), $select::JOIN_INNER);
                $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_INNER);
                $select->join(array('z' => 'Proj_OHService'), 'z.ServiceId=c.ServiceId', array(), $select::JOIN_INNER);
                $select->join(array('oc' => 'WF_OperationalCostCentre'), 'z.ProjectId=oc.projectid', array('CostCentreId'), $select::JOIN_INNER);
                $select->columns(array('RequestTransId', 'Quantity', 'RequestId', 'SOQty', 'BalQty' => new Expression("d.Quantity-d.SOQty"), 'CurQty' => new Expression("Cast(0 As Decimal(18,3))"),'HiddenQty'=>new Expression("Cast(d.SOQty As Decimal(18,3))")))
                    ->where(array('c.ServiceId'=>$postParams['serviceid'],'b.CostCentreId'=> $postParams['CostCenterId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();


                try {
                    $postData = $request->getPost();
//                    echo '<pre>'; print_r($postData); die;
                    if (!is_null($postData['frm_index'])) {
                        $this->_view->frmInd = 1;
                        $CostCentre = $postData['CostCentre'];
                        $this->_view->ccId = $CostCentre;
                        $vendorId = $postData['VendorId'];
                        $this->_view->venId = $vendorId;
                        $serviceTypeId = $postData['serviceTypeId'];
                        $requestTransIds = $postData['requestTransIds'];
                        if ($requestTransIds == '') {
                            $requestTransIds = 0;
                        } else {
                            $requestTransIds = implode(',', $postData['requestTransIds']);
                        }
                        $this->_view->serTypeId = $serviceTypeId;

                        $select = $sql->select();
                        $select->from('wf_OperationalCostCentre')
                            ->columns(array('CostCentreId', 'CostCentreName', 'CompanyId'))
                            ->where(array('CostCentreId' => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->costCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('vendor_master')
                            ->columns(array('VendorId', 'VendorName'))
                            ->where(array('VendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('Proj_ServiceTypeMaster')
                            ->columns(array('ServiceTypeId', 'ServiceTypeName'))
                            ->where(array('ServiceTypeId' => $serviceTypeId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->serviceType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array("d" => "VM_RequestTrans"))
                            ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'CostCentreId'), $select::JOIN_LEFT);
                        $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT);
                        $select->columns(array('RequestTransId', 'Quantity', 'RequestId', 'SOQty', 'BalQty' => new Expression("d.Quantity-d.SOQty"), 'CurQty' => new Expression("d.Quantity-d.SOQty"),'HiddenQty' => new Expression("CAST(d.SOQty As Decimal(18,3))")))
                            ->where(array("d.RequestTransId IN($requestTransIds)"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("d" => "VM_RequestTrans"))
                            ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'CostCentreId'), $select::JOIN_INNER);
                        $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_INNER);
                        $select->join(array('e' => 'Proj_ServiceTypeMaster'), 'c.ServiceTypeId=e.ServiceTypeId', array(), $select::JOIN_INNER);
                        $select->join(array('z' => 'Proj_OHService'), 'z.ServiceId=c.ServiceId', array('Rate', 'eQty'=>'Qty', 'Amount'), $select::JOIN_INNER);
                        $select->join(array('y' => 'Proj_Uom'), 'y.UnitId=d.UnitId', array('UnitId', 'UnitName'), $select::JOIN_INNER);
                        $select->join(array('oc' => 'WF_OperationalCostCentre'), 'z.ProjectId=oc.projectid', array(), $select::JOIN_INNER);
                        $select->columns(array('RequestTransId', 'RequestId','WorkUnitId'=>'WorkUnit','Qty'=>'Quantity'))
                            ->where(array("d.RequestTransId IN($requestTransIds) and oc.CostCentreId=$CostCentre"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_OHService'))
                            ->columns(array())
                            ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId',array('ServiceTypeId'),$select::JOIN_INNER)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'a.ProjectId = c.ProjectId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'Proj_ServiceTypeMaster'), 'b.ServiceTypeId = d.ServiceTypeId',array('ServiceTypeName'),$select::JOIN_LEFT)
                            ->where(array('c.CostCentreId'=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->service_Types = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    } else if($serOrdId == 0 || $amdId != 0) {
                        $iSerOrdId = $this->bsf->isNullCheck($serOrdId, 'number');

                        $iTypeSo = $this->bsf->isNullCheck($postData['typeSo'], 'string');

                        $soNo = $postData['soNo'];
                        $soCCNo = $postData['ccSoNo'];
                        $soCoNo = $postData['compSoNo'];

                        if($amdId == 0) {
                            $amendment = 0;
                            $soaVNo = CommonHelper::getVoucherNo(403, date('Y-m-d', strtotime($postData['soDate'])), 0, 0, $dbAdapter, "I");
                            if ($soaVNo["genType"] == true) {
                                $soNo = $soaVNo["voucherNo"];
                            } else {
                                $soNo = $postData['soNo'];
                            }

                            $soccaVNo = CommonHelper::getVoucherNo(403, date('Y-m-d', strtotime($postData['soDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                            if ($soccaVNo["genType"] == true) {
                                $soCCNo = $soccaVNo["voucherNo"];
                            } else {
                                $soCCNo = $postData['ccSoNo'];
                            }

                            $socoaVNo = CommonHelper::getVoucherNo(403, date('Y-m-d', strtotime($postData['soDate'])), $postData['companyId'], 0, $dbAdapter, "I");
                            if ($socoaVNo["genType"] == true) {
                                $soCoNo = $socoaVNo["voucherNo"];
                            } else {
                                $soCoNo = $postData['compSoNo'];
                            }
                        } else {
                            $soNewNo = explode('_', $soNo);
                            if(!isset($soNewNo[1])) {
                                $soNo =  $soNo.'_1';
                            } else {
                                $incSoNo = ($soNewNo[1] + 1);
                                $soNo =  $soNewNo[0].'_'.$incSoNo;
                            }
                            $amendment = 1;
                        }

                        if ($iSerOrdId == 0) {

                            $inType = 'N';
                            $inName = 'WPM-ServiceOrder-Add';
                            $inDesc = 'ServiceOrder-Add';
                            $sRefNo = $postData['refNo'];

                            $insert = $sql->insert();
                            $insert->into('WPM_SORegister');
                            $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                            , 'ServiceTypeId' => $this->bsf->isNullCheck($postData['setServiceType'], 'number')
                            , 'SODate' => date('Y-m-d', strtotime($postData['soDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'SONo' => $this->bsf->isNullCheck($soNo, 'string')
                            , 'SOCCNo' => $this->bsf->isNullCheck($soCCNo, 'string')
                            , 'SOCompNo' => $this->bsf->isNullCheck($soCoNo, 'string')
                            , 'ServiceType' => $this->bsf->isNullCheck($postData['updatework'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Contract' => $this->bsf->isNullCheck($postData['isContract'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            , 'ScopeOfWork' => $this->bsf->isNullCheck($postData['scopework'], 'string')
                            , 'Amendment' => $this->bsf->isNullCheck($amendment, 'number')
                            , 'ASORegisterId' => $this->bsf->isNullCheck($amdId, 'number')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $iSerOrdId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                            for ($i = 1; $i < $serviceRows; $i++) {
                                $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');
                                $SOServiceTransId = 0;
                                if ($serviceId != 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_SOServiceTrans');
                                    $insert->Values(array('SORegisterId' => $iSerOrdId
                                    , 'ServiceId' => $serviceId
                                    , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                    , 'WorkUnitId' => $this->bsf->isNullCheck($postData['workingUnitId_' . $i], 'number')
                                    , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                    , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $SOServiceTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $reqRowId = $this->bsf->isNullCheck($postData['req_' . $i . '_rowid'], 'number');
                                    for ($k = 1; $k <= $reqRowId; $k++) {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_SORequesttrans');
                                        $insert->Values(array('SOServiceTransId' => $SOServiceTransId
                                        , 'ServiceId' => intval($serviceId)
                                        , 'RequestTransId' => intval($this->bsf->isNullCheck($postData['req_' . $i . '_transid_' . $k], 'number'))
                                        , 'Qty' => floatval($this->bsf->isNullCheck($postData['req_' . $i . '_curqty_' . $k], 'number'))));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $update = $sql->update();
                                        $update->table('VM_RequestTrans');
                                        $update->set(array(
                                            'SOQty' => new Expression('SOQty+'.$this->bsf->isNullCheck($postData['req_' . $i . '_curqty_' . $k], 'number').''),
                                            'BalQty' => new Expression('BalQty-'.$this->bsf->isNullCheck($postData['req_' . $i . '_curqty_' . $k], 'number').'')
                                        ));
                                        $update->where(array('RequestTransId' => intval($this->bsf->isNullCheck($postData['req_' . $i . '_transid_' . $k], 'number')) ,'ResourceId'=>intval($serviceId)));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_SOQualTrans');
                                $insert->Values(array('SORegisterId' => $iSerOrdId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $termsTotal = $postData['trowid'];
                            $valueFrom = 0;
                            if ($postData['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postData['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postData['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }
                            for ($t = 1; $t < $termsTotal; $t++) {
                                if ($this->bsf->isNullCheck($postData['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                    }
                                    $termsInsert = $sql->insert('WPM_SOGeneralTerms');
                                    $termsInsert->values(array("SORegisterId" => $iSerOrdId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            //payment schedule
                            $pTerms = ($this->bsf->isNullCheck($postData['pRowId'], 'number'));
                            for ($p = 1; $p < $pTerms; $p++) {
                                if($this->bsf->isNullCheck($postData['wPer_' . $p], 'number') > 0) {
                                    $pTermsInsert = $sql->insert('WPM_SOpaymentscheduletrans');
                                    $pTermsInsert->values(array("SORegisterId" => $iSerOrdId,
                                        "ScheduleName" => $this->bsf->isNullCheck($postData['sDesc_' . $p], 'string'),
                                        "ScheduleType" => $this->bsf->isNullCheck($postData['sType_' . $p], 'string'),
                                        "WorkPer" => $this->bsf->isNullCheck($postData['wPer_' . $p], 'number'),
                                        "AdvancePer" => $this->bsf->isNullCheck($postData['aPer_' . $p], 'number'),
                                    ));
                                    $pTermsStatement = $sql->getSqlStringForSqlObject($pTermsInsert);
                                    $dbAdapter->query($pTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            if($amdId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_SORegister');
                                $update->set(array('LiveWO' => 0));
                                $update->where(array('SORegisterId' => $amdId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        } else {
                            $inType = 'E';
                            $inName = 'WPM-ServiceOrder-Edit';
                            $inDesc = 'ServiceOrder-Edit';
                            $sRefNo = $postData['refNo'];

                            $update = $sql->update();
                            $update->table('WPM_SORegister');
                            $update->set(array('SODate' => date('Y-m-d', strtotime($postData['soDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'SONo' => $this->bsf->isNullCheck($soNo, 'string')
                            , 'SOCCNo' => $this->bsf->isNullCheck($soCCNo, 'string')
                            , 'SOCompNo' => $this->bsf->isNullCheck($soCoNo, 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'ServiceType' => $this->bsf->isNullCheck($postData['updatework'], 'string')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Contract' => $this->bsf->isNullCheck($postData['isContract'], 'number')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            , 'ScopeOfWork' => $this->bsf->isNullCheck($postData['scopework'], 'string')
                            , 'ServiceTypeId' => $this->bsf->isNullCheck($postData['setServiceType'], 'number')
                            ));
                            $update->where(array('SORegisterId' => $iSerOrdId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('WPM_SOQualTrans');
                            $delete->where(array("SORegisterId" => $iSerOrdId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delPOPayTrans = $sql->delete();
                            $delPOPayTrans->from('WPM_SOGeneralTerms')
                                ->where(array("SORegisterId" => $iSerOrdId));
                            $POPayStatement = $sql->getSqlStringForSqlObject($delPOPayTrans);
                            $dbAdapter->query($POPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_SOQualTrans');
                                $insert->Values(array('SORegisterId' => $iSerOrdId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $termsTotal = $postData['trowid'];
                            $valueFrom = 0;
                            if ($postData['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postData['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postData['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }
                            for ($t = 1; $t < $termsTotal; $t++) {
                                if ($this->bsf->isNullCheck($postData['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                    }
                                    $termsInsert = $sql->insert('WPM_SOGeneralTerms');
                                    $termsInsert->values(array("SORegisterId" => $iSerOrdId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $delete = $sql->delete();
                            $delete->from('WPM_SOpaymentscheduletrans')
                                ->where("SORegisterId = $iSerOrdId");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $pTerms = ($this->bsf->isNullCheck($postData['pRowId'], 'number'));
                            for ($p = 1; $p < $pTerms; $p++) {
                                if($this->bsf->isNullCheck($postData['wPer_' . $p], 'number') > 0) {
                                    $pTermsInsert = $sql->insert('WPM_SOpaymentscheduletrans');
                                    $pTermsInsert->values(array("SORegisterId" => $iSerOrdId,
                                        "ScheduleName" => $this->bsf->isNullCheck($postData['sDesc_' . $p], 'string'),
                                        "ScheduleType" => $this->bsf->isNullCheck($postData['sType_' . $p], 'string'),
                                        "WorkPer" => $this->bsf->isNullCheck($postData['wPer_' . $p], 'number'),
                                        "AdvancePer" => $this->bsf->isNullCheck($postData['aPer_' . $p], 'number'),
                                    ));
                                    $pTermsStatement = $sql->getSqlStringForSqlObject($pTermsInsert);
                                    $dbAdapter->query($pTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $delete = $sql->delete();
                            $delete->from('WPM_SORequesttrans')
                                ->where("SOServiceTransId in(select SOServiceTransId from WPM_SOServiceTrans where SORegisterId=$iSerOrdId)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('WPM_SOServiceTrans')
                                ->where("SORegisterId = $iSerOrdId");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                            for ($i = 1; $i <= $serviceRows; $i++) {
                                $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');

                                if ($serviceId != 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_SOServiceTrans');
                                    $insert->Values(array('SORegisterId' => $iSerOrdId
                                    , 'ServiceId' => $serviceId
                                    , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                    , 'WorkUnitId' => $this->bsf->isNullCheck($postData['workingUnitId_' . $i], 'number')
                                    , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                    , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $SOServiceTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }

                                $reqRowId = $this->bsf->isNullCheck($postData['req_' . $i . '_rowid'], 'number');
                                for ($k = 1; $k <= $reqRowId; $k++) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_SORequestTrans');
                                    $insert->Values(array('SOServiceTransId' => $SOServiceTransId
                                    , 'ServiceId' => $serviceId
                                    , 'RequestTransId' => intval($this->bsf->isNullCheck($postData['req_' . $i . '_transid_' . $k], 'number'))
                                    , 'Qty' => floatval($this->bsf->isNullCheck($postData['req_' . $i . '_curqty_' . $k], 'number'))));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $orderQty=$this->bsf->isNullCheck($postData['req_' . $i . '_curqty_' . $k], 'number');
                                    $hiddenQty=$this->bsf->isNullCheck($postData['req_' . $i . '_hiddenQty_' . $k], 'number');
                                    $update = $sql->update();
                                    $update->table('VM_RequestTrans');
                                    $update->set(array(
                                        'SOQty' => new Expression("(SOQty+$orderQty)-$hiddenQty"),
                                        'BalQty' => new Expression("BalQty-($orderQty-$hiddenQty)")
                                    ));
                                    $update->where(array('RequestTransId' => intval($this->bsf->isNullCheck($postData['req_' . $i . '_transid_' . $k], 'number')) ,'ResourceId'=>intval($serviceId)));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
//
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iSerOrdId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'register'));
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            if ($this->_view->serOrdId != 0) {
                //Service Order Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SORegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName', 'CostCentreId'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName', 'VendorId'))
                    ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName', 'ServiceTypeId'))
                    ->join(array('e' => 'Proj_ServiceMaster'), 'e.ServiceTypeId = d.ServiceTypeId', array('ServiceId'))
                    ->where('a.SORegisterId = ' . $serOrdId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->soRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $serviceId = $this->_view->soRegister['ServiceId'];
                $this->_view->serTypeId = $this->_view->soRegister['ServiceTypeId'];
//                $SORegisterId = $this->_view->soRegister['SORegisterId'];
                $CostCentreId = $this->_view->soRegister['CostCentreId'];
                $this->_view->narration = $this->_view->soRegister['Narration'];
                $this->_view->scope = $this->_view->soRegister['ScopeOfWork'];


                $select = $sql->select();
                $select->from(array("a" => "WPM_SOServiceTrans"))
                    ->where(array("a.SORegisterId" => $serOrdId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_payTerms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                //Service Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SOServiceTrans'))
                    ->columns(array(
                        "SORegisterId" => new Expression("a.SORegisterId"),
                        "ServiceId" => new Expression("a.ServiceId"),
                        "UnitId" => new Expression("a.UnitId"),
                        "WorkUnitId" => new Expression("a.WorkUnitId"),
                        "Qty" => new Expression('CAST(a.Qty As Decimal(18,3))'),
                        "Amount" => new Expression('CAST(a.Amount As Decimal(18,2))'),
                        "Rate" => new Expression('CAST(a.Rate As Decimal(18,2))'),
                    ))
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('Desc' => new Expression('ServiceName')), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SORegister'), 'e.SORegisterId = a.SORegisterId', array('CostCentreId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('h' => 'WF_OperationalCostCentre'), 'e.CostCentreId = h.CostCentreId', array(), $select::JOIN_LEFT)
                    ->join(array('f' => 'Proj_OHService'), 'a.ServiceId= f.ServiceId and h.ProjectId=f.ProjectId', array('eQty'=>'Qty'), $select::JOIN_LEFT)
                    ->where('a.SORegisterId = ' . $serOrdId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select10 = $sql->select();
                $select10->from(array('a1' => 'WPM_SORequestTrans'))
                    ->columns(array('RequestTransId'))
                    ->join(array('b1' => 'WPM_SOServiceTrans'), 'a1.SOServiceTransId = b1.SOServiceTransId', array(), $select10::JOIN_INNER);
                $select10->where("B1.ServiceId=$serviceId and B1.SORegisterId=$serOrdId");
                $statement = $sql->getSqlStringForSqlObject($select10);
                $arrreqtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $resid = array();
                foreach ($arrreqtrans as $arrreqtran) {
                    array_push($resid, $arrreqtran['RequestTransId']);
                }
                $resIDS = implode(",", $resid);

                if ($resIDS == "") {
                    $resIDS = 0;
                }

                $select1 = $sql->select();
                $select1->from(array("d" => "VM_RequestTrans"))
                    ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'CostCentreId'), $select::JOIN_LEFT);
                $select1->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT);
                $select1->columns(array('RequestTransId', 'Quantity', 'RequestId', 'SOQty',
                    'BalQty' => new Expression("d.Quantity-d.SOQty"), 'HiddenQty' => new Expression("CAST(0 As Decimal(18,3))"),
                    'CurQty' => new Expression("d.Quantity-d.SOQty")))
                    ->where(array("d.RequestTransId NOT IN(Select RequestTransId From [WPM_SORequestTrans] d Inner Join WPM_SOServiceTrans e on d.SOServiceTransId=e.SOServiceTransId where e.SORegisterId=$serOrdId) and b.CostCentreId=$CostCentreId and d.BalQty > 0 and c.ServiceId=$serviceId"));

                $select = $sql->select();
                $select->from(array("a" => "VM_RequestTrans"))
                    ->columns(array('RequestTransId', 'Quantity', 'RequestId', 'SOQty','HiddenQty' => new Expression("CAST(e.Qty As Decimal(18,3))"),
                        'BalQty' => new Expression("A.BalQty"), 'CurQty' => new Expression("e.Qty")))
                    ->join(array('d' => 'VM_RequestRegister'), 'a.RequestId=d.RequestId', array('RequestDate' => new Expression("FORMAT(d.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'CostCentreId'), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_ServiceMaster'), 'a.ResourceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SORequestTrans'),'a.RequestTransId=e.RequestTransId',array(),$select::JOIN_INNER)
                    ->join(array('f' => 'WPM_SOServiceTrans'),'e.SOServiceTransId=f.SOServiceTransId',array(),$select::JOIN_INNER)
                    ->where(array("f.SORegisterId= $serOrdId"));
                $select->combine($select1,'Union ALL');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a'=>'Proj_OHService'))
                    ->columns(array())
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId',array('ServiceTypeId'),$select::JOIN_INNER)
                    ->join(array('c' => 'WF_OperationalCostCentre'), 'a.ProjectId = c.ProjectId',array(),$select::JOIN_INNER)
                    ->join(array('d' => 'Proj_ServiceTypeMaster'), 'b.ServiceTypeId = d.ServiceTypeId',array('ServiceTypeName'),$select::JOIN_LEFT)
                    ->where(array('c.CostCentreId'=>$CostCentreId));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->service_Types = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WPM_SOQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.SORegisterId' => $serOrdId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $selTer = $sql->select();
                $selTer->from(array("a" => "WPM_SOGeneralTerms"))
                    ->columns(array("ValueFromNet"))
                    ->where('SORegisterId=' . $serOrdId . '');
                $terStatement = $sql->getSqlStringForSqlObject($selTer);
                $terResult = $dbAdapter->query($terStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->valuefrom = $this->bsf->isNullCheck($terResult['ValueFromNet'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "WPM_SOpaymentscheduletrans"))
                    ->where(array("a.SORegisterId" => $serOrdId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_payTerms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                    ->where(array("TermType" => 'W'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    //->columns(array('data' => 'TermsId',))
                    ->columns(array(new Expression("a.TermsId As data,a.SlNo,a.Title As value,CAST(b.Per As Decimal(18,3)) As Per,
                                CAST(b.Value As Decimal(18,3)) As Val,b.Period As Period,b.TDate As [Dte],b.TString As [Strg],a.Per As IsPer,
                                a.Value As IsValue,a.Period As IsPeriod,a.TDate As IsTDate,a.TSTring As IsTString,a.IncludeGross")))
                    ->join(array('b' => 'WPM_SOGeneralTerms'), 'a.TermsId=b.TermsId', array(), $select::JOIN_INNER)
                    ->where(array("b.SORegisterId" => $serOrdId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_edit_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            } else {
                //Operational Cost Centre
                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_QualifierTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")));
                $select->where(array('a.QualType' => 'W'));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;

                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $this->_view->valuefrom = 0;
                //Vendor Master

                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    //->columns(array('data' => 'TermsId',))
                    ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TString As IsTString,IncludeGross")))
                    ->where(array("TermType" => 'W'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId', 'VendorName'))
                    ->where(array('Contract' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Service Type Master
                $select = $sql->select();
                $select->from('Proj_ServiceTypeMaster')
                    ->columns(array('ServiceTypeId', 'ServiceTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $aVNo = CommonHelper::getVoucherNo(403, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == true)
                    $this->_view->soNo = $aVNo["voucherNo"];
                else
                    $this->_view->soNo = "";

                $this->_view->soTypeId = '403';
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getServicesAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $costCentre = $postData['ccId'];
                $sTypeId = $postData['sTypeId'];
                //Service Master
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ServiceMaster'))
                    ->columns(array('data' =>new Expression("Distinct a.ServiceId"), 'value' => new Expression("Case When isnull(a.ServiceCode,'') <> '' Then a.ServiceCode + ' - ' + a.ServiceName Else a.ServiceName End")))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId' => 'UnitId','UnitName' => 'UnitName'), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_OHService'), new Expression("a.ServiceId = c.ServiceId"), array('Rate','Qty','Amount'), $select::JOIN_INNER)
                    ->join(array('d' => 'WF_OperationalCostCentre'), new Expression("c.ProjectId = d.ProjectId  and d.CostCentreId=$costCentre"), array('ProjectId'), $select::JOIN_INNER)
                    ->join(array('e' => 'WPM_SORegister'), new Expression("d.CostCentreId = e.CostCentreId"), array(), $select::JOIN_LEFT)
                    ->join(array('f' => 'WPM_SOServiceTrans'), new Expression("e.SORegisterId = f.SORegisterId and a.ServiceId = f.ServiceId"), array('OQty'=>new Expression("sum(f.Qty)")), $select::JOIN_LEFT)
                    ->join(array('g' => 'WPM_SBRegister'), new Expression("d.CostCentreId = g.CostCentreId"), array(), $select::JOIN_LEFT)
                    ->join(array('h' => 'WPM_SBServiceTrans'), new Expression("g.SBRegisterId = h.SBRegisterId and a.ServiceId = h.ServiceId"), array('BQty'=>new Expression("sum(h.Qty)")), $select::JOIN_LEFT)
                    ->where(array("a.ServiceTypeId=$sTypeId"))
                    ->group(new Expression("a.ServiceId,a.ServiceCode,a.ServiceName,b.UnitId,b.UnitName,c.Rate,c.Qty,c.Amount,d.ProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function serviceDoneAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Service Done");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $serDoneId = $this->params()->fromRoute('serviceDoneId');
        $this->_view->serDoneId = (isset($serDoneId) && $serDoneId != 0) ? $serDoneId : 0;

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $SOId = $this->bsf->isNullCheck($postParams['SOId'], 'number');
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ServiceMaster'))
                    ->columns(array('ServiceId' => 'ServiceId', 'ServiceName' => 'ServiceName', 'UnitId' => 'UnitId'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where(array("a.ServiceId = $SOId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    $iSerDoneId = $this->bsf->isNullCheck($postData['serDoneId'], 'number');

                    if ($iSerDoneId == 0) {
                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('CompanyId'))
                            ->where(array('a.CostCentreId' => $postData['costCentreId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $sdaVNo = CommonHelper::getVoucherNo(410, date('Y-m-d', strtotime($postData['sdDate'])), 0, 0, $dbAdapter, "I");
                        if ($sdaVNo["genType"] == true) {
                            $sdNo = $sdaVNo["voucherNo"];
                        } else {
                            $sdNo = $postData['sdNo'];
                        }

                        $sdccaVNo = CommonHelper::getVoucherNo(410, date('Y-m-d', strtotime($postData['sdDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                        if ($sdccaVNo["genType"] == true) {
                            $sdCCNo = $sdccaVNo["voucherNo"];
                        } else {
                            $sdCCNo = $postData['ccSdNo'];
                        }

                        $sdcoaVNo = CommonHelper::getVoucherNo(410, date('Y-m-d', strtotime($postData['sdDate'])), $costcenter['CompanyId'], 0, $dbAdapter, "I");
                        if ($sdcoaVNo["genType"] == true) {
                            $sdCoNo = $sdcoaVNo["voucherNo"];
                        } else {
                            $sdCoNo = $postData['compSdNo'];
                        }
                        $inType = 'N';
                        $inName = 'WPM-ServiceDone-Add';
                        $inDesc = 'ServiceDone-Add';
                        $sRefNo = $postData['refNo'];

                        $insert = $sql->insert();
                        $insert->into('WPM_SDRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                        , 'SORegisterId' => $this->bsf->isNullCheck($postData['soRegId'], 'number')
                        , 'SDDate' => date('Y-m-d', strtotime($postData['sdDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'SDNo' => $this->bsf->isNullCheck($sdNo, 'string')
                        , 'SDCCNo' => $this->bsf->isNullCheck($sdCCNo, 'string')
                        , 'SDCompNo' => $this->bsf->isNullCheck($sdCoNo, 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Specification' => $this->bsf->isNullCheck($postData['specification'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iSerDoneId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                        for ($i = 1; $i <= $serviceRows; $i++) {
                            $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');

                            if ($serviceId != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_SDServiceTrans');
                                $insert->Values(array('SDRegisterId' => $iSerDoneId
                                , 'ServiceId' => $serviceId
                                , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')
                                , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    } else {
                        $inType = 'E';
                        $inName = 'WPM-ServiceDone-Edit';
                        $inDesc = 'ServiceDone-Edit';
                        $sRefNo = $postData['refNo'];

                        $update = $sql->update();
                        $update->table('WPM_SDRegister');
                        $update->set(array('SDDate' => date('Y-m-d', strtotime($postData['sdDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'SDNo' => $this->bsf->isNullCheck($postData['sdNo'], 'string')
                        , 'SDCCNo' => $this->bsf->isNullCheck($postData['ccSdNo'], 'string')
                        , 'SDCompNo' => $this->bsf->isNullCheck($postData['compSdNo'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Specification' => $this->bsf->isNullCheck($postData['specification'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $update->where(array('SDRegisterId' => $iSerDoneId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_SDServiceTrans')
                            ->where("SDRegisterId = $iSerDoneId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                        for ($i = 1; $i <= $serviceRows; $i++) {
                            $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');

                            if ($serviceId != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_SDServiceTrans');
                                $insert->Values(array('SDRegisterId' => $iSerDoneId
                                , 'ServiceId' => $serviceId
                                , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')
                                , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iSerDoneId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/service-done', array('controller' => 'labourstrength', 'action' => 'service-done-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->serDoneId != 0) {
                //Service Done Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SDRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WPM_SORegister'), 'a.SORegisterId = d.SORegisterId', array('SONo', 'Amount'), $select::JOIN_LEFT)
                    ->where('a.SDRegisterId = ' . $serDoneId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sdRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //Service Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SDServiceTrans'))
                    ->columns(array(
                        'HiddenQty'=>new Expression("a.Qty"),'SDServiceTransId','SDRegisterid','ServiceId','UnitId','Qty',
                        'Rate','Amount'))
                    ->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array(), $select::JOIN_LEFT)
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('ServiceName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SOServiceTrans'), 'd.SORegisterId = e.SORegisterId and b.ServiceId=e.ServiceId', array('SOQty'=>'Qty'), $select::JOIN_LEFT)
                    ->where('a.SDRegisterId = ' . $serDoneId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sdServiceTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => "CostCentreId", 'value' => "CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('data' => 'VendorId', 'value' => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $aVNo = CommonHelper::getVoucherNo(410, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->sdNo = $aVNo["voucherNo"];
            else
                $this->_view->sdNo = "";

            $this->_view->sdTypeId = '410';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getServiceOrdersAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_SORegister')
                    ->columns(array('data' => 'SORegisterId', 'value' => 'SONo'))
                    ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getOrderServicesAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                //Service Master
                $sql = new Sql($dbAdapter);
                $soRegId=$postData['soRegId'];

                $subQuery = $sql->select();
                $subQuery->from('WPM_SOServiceTrans')
                    ->columns(array('ServiceId'))
                    ->where(array("SORegisterId =$soRegId"));

                $Query1 = $sql->select();
                $Query1->from(array('a'=>'WPM_SDServiceTrans'))
                    ->columns(array('ServiceId','Qty'=>new Expression('sum(a.Qty)')))
                    ->join(array('b' => 'WPM_SDRegister'), 'a.SDRegisterId = b.SDRegisterId', array('SORegisterId'), $Query1::JOIN_INNER)
                    ->where(array("SORegisterId = $soRegId group by B.SORegisterId,A.ServiceId"));

                $select = $sql->select();
                $select->from(array('d' => 'WPM_SOServiceTrans'))
                    ->columns(array('Qty','Rate','BalQty'=>new Expression("(d.Qty-G.Qty)"),'Amount'))
                    ->join(array('c' => 'WPM_SORegister'), 'd.SORegisterId = c.SORegisterId', array('SORegisterId'), $select::JOIN_INNER)
                    ->join(array('a' => 'Proj_ServiceMaster'), 'd.ServiceId = a.ServiceId', array('data' => 'ServiceId', 'value' => 'ServiceName'), $select::JOIN_INNER)
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName','UnitId' => 'UnitId'), $select::JOIN_LEFT)
                    ->join(array('G' => $Query1), 'c.SORegisterId=G.SORegisterId AND d.ServiceId=G.ServiceId', array('SDQty'=>'Qty'), $select::JOIN_LEFT)
                    ->where(array("c.SORegisterId = $soRegId"))
                    ->where->expression('a.ServiceId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function serviceBillAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Service Bill");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $serBillId = $this->params()->fromRoute('serviceBillId');
        $this->_view->serBillId = (isset($serBillId) && $serBillId != 0) ? $serBillId : 0;
        $this->_view->frmInd = 0;

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $select = $sql->select();

                $select->from(array("d" => "WPM_SDServiceTrans"))
                    ->join(array('b' => 'WPM_SDRegister'), 'd.SDRegisterId=b.SDRegisterId', array('SDDate' => new Expression("FORMAT(b.SDDate, 'dd-MM-yyyy')"), 'SDNo', 'CostCentreId'), $select::JOIN_LEFT);
                $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ServiceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT);
                $select->columns(array('SDServiceTransId', 'Qty', 'SDRegisterId', 'SOQty' => new Expression('CAST(0 As Decimal(18,3))'), 'BalQty' => new Expression('CAST(Qty As Decimal(18,3))'), 'CurQty' => new Expression('CAST(0 As Decimal(18,3))')))
                    ->where(array('b.CostCentreId' => $postParams['CostCenterId'], 'c.ServiceId' => $postParams['serviceid']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    if (!is_null($postData['frm_index'])) {
                        $this->_view->frmInd = 1;
                        $this->_view->srId = '';
                        $this->_view->sdrId = '';
                        $CostCentre = $postData['costCentreId'];
                        $this->_view->ccId = $CostCentre;
                        $vendorId = $postData['vendorId'];
                        $this->_view->venId = $vendorId;
                        $sorId=$postData['soRegId'];
                        if ($sorId == '') {
                            $sorId = 0;
                        } else {
                            $sorId =  $postData['soRegId'];
                        }
//                        if (isset($postData['soRegId'])) {
//                            if($postData['soRegId'] == ''){
//                                $sorId=0;
//                            }else {
//                                $sorId = $postData['soRegId'];
//                            }
                        $this->_view->sorId = $sorId;
//                        }

                        $this->_view->sbiType = $postData['serBillType'];
                        $sdServiceTransIds = $postData['requestTransIds'];
                        if ($sdServiceTransIds == '') {
                            $sdServiceTransIds = 0;
                        } else {
                            $sdServiceTransIds = implode(',', $postData['requestTransIds']);
                        }
                        $select = $sql->select();
                        $select->from('wf_OperationalCostCentre')
                            ->columns(array('CostCentreId', 'CostCentreName', 'CompanyId'))
                            ->where(array('CostCentreId' => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->costCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('WPM_SOGeneralTerms')
                            ->columns(array('value'))
                            ->where(array('SORegisterId' => $sorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->recoveryTypeAmount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                        $select = $sql->select();
                        $select->from('vendor_master')
                            ->columns(array('VendorId', 'VendorName'))
                            ->where(array('VendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('WPM_SORegister')
                            ->columns(array('SORegisterId', 'SONo'))
                            ->where(array('SORegisterId' => $sorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->SO = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                        $select = $sql->select();
                        $select->from(array("d" => "WPM_SDServiceTrans"))
                            ->join(array('b' => 'WPM_SDRegister'), 'd.SDRegisterId=b.SDRegisterId', array('SDDate' => new Expression("FORMAT(b.SDDate, 'dd-MM-yyyy')"), 'SDNo', 'CostCentreId'), $select::JOIN_LEFT);
                        $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ServiceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT);
                        $select->columns(array('SDServiceTransId', 'Qty', 'SDRegisterId', 'SOQty' => new Expression('CAST(0 As Decimal(18,3))'), 'BalQty' => new Expression('CAST(Qty As Decimal(18,3))'), 'CurQty' => new Expression('CAST(0 As Decimal(18,3))')))
                            ->where(array("d.SDServiceTransId IN($sdServiceTransIds)"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $selectIds = $sql->select();
                        $selectIds->from('WPM_SOServiceTrans')
                            ->columns(array('ServiceId','Rate'))
                            ->where(array("SORegisterId in(Select SORegisterId from WPM_SDRegister Where
                             SDRegisterId IN(select distinct SDRegisterId from WPM_SDServiceTrans where SDServiceTransId in($sdServiceTransIds)))"));

                        $select = $sql->select();
                        $select->from(array("A" => "WPM_SDServiceTrans"))
                            ->join(array('B' => 'Proj_ServiceMaster'), 'A.ServiceId=B.ServiceId', array('Desc'=>'ServiceName','ServiceId'), $select::JOIN_LEFT)
                            ->join(array('D' => 'Proj_Uom'), 'A.UnitId=D.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT);
                        $select->join(array('C' => $selectIds), 'A.ServiceId=C.ServiceId', array('Rate'), $select::JOIN_LEFT);
                        $select->columns(array('Qty'=>new Expression("Sum(A.Qty)")))
                            ->where(array("A.SDRegisterId IN(select distinct SDRegisterId from WPM_SDServiceTrans where
                            SDServiceTransId in($sdServiceTransIds)) Group by B.ServiceId,B.ServiceCode,B.ServiceName,C.Rate,D.UnitName,D.UnitId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a'=>'Proj_OHService'))
                            ->columns(array())
                            ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId',array('ServiceTypeId'),$select::JOIN_INNER)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'a.ProjectId = c.ProjectId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'Proj_ServiceTypeMaster'), 'b.ServiceTypeId = d.ServiceTypeId',array('ServiceTypeName'),$select::JOIN_LEFT)
                            ->where(array('c.CostCentreId'=>$CostCentre));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->service_Types = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        $select = $sql->select();
                        $select->from(array('A'=>'WF_CompanyMailSetting'))
                            ->columns(array("AdvanceDeductWithoutPaid"));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $AdvanceWithoutPaid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] ==1 ) {
                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_SOGeneralTerms'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.Value) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->join(array('B' => 'WF_TermsMaster'), 'A.TermsId=B.TermsId',array(),$select1::JOIN_INNER)
                                ->where(array("B.Title ='Advance' and B.TermType='W' and A.SORegisterId in ($sorId)"));

                        }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_SORegister'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.AdvanceAmt) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->where(array("A.SORegisterId in ($sorId)"));

                        }
                        $select2 = $sql->select();
                        $select2->from(array('A' => 'WPM_Sbrecoverytrans'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(sum(A.Amount) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                            ->where(array("SBRegisterId in (Select SBRegisterId from WPM_SBRegister Where SORegisterId in ($sorId))"));
                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array('A' => 'WPM_RetentionReleaseRegister'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(sum(A.AdvanceAmt) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                            ->where(array("RRRegisterId in (Select RRRegisterId from WPM_RetentionReleaseRegister Where OrderType='S' and OrderId in ($sorId))"));
                        $select3->combine($select2,'Union ALL');
                        if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 1 ) {
                            $select10 = $sql->select();
                            $select10->from(array('A' => 'WPM_SDRegister'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(AdvAmount) As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->where(array("A.SORegisterId in ($sorId)"));
                            $select10->combine($select3,'Union ALL');

                        }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                            $select10 = $sql->select();
                            $select10->from(array('A' => 'WPM_SDRegister'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(AdvancePaid) As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->where(array("A.SORegisterId in ($sorId)"));
                            $select10->combine($select3,'Union ALL');
                        }
                        $select4 = $sql->select();
                        $select4->from(array('A' => 'WPM_Sbrecoverytrans'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(A.Amount As Decimal(18,3))')))
                            ->where(array("SBRegisterId in (Select SBRegisterId from WPM_SBRegister Where SORegisterId in ($sorId)) and RecoveryTypeId=4"));
                        $select4->combine($select10,'Union ALL');

                        $select = $sql->select();
                        $select->from(array('G' => $select4))
                            ->columns(array('MobAdvance'=>new Expression('CAST(Sum(G.MobAdvance) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(G.CashAdvance) As Decimal(18,3))'),
                                'TotalAdvance' => new Expression('CAST(Sum(G.MobAdvance+G.CashAdvance) As Decimal(18,3))'),'Recovery' => new Expression('CAST(Sum(G.Recovery) As Decimal(18,3))'),
                                'Balance' => new Expression('Case When Sum(G.MobAdvance+G.CashAdvance-G.Recovery) > 0 then Sum(G.MobAdvance+G.CashAdvance-G.Recovery) Else 0 end')
                            ,'CurAmt' => new Expression('CAST(Sum(G.CurAmt) As Decimal(18,3))')));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->advRecv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//Union All
//Select 0 MobAdvance,Sum(Amount) CashAdvance,0 Recovery,0 CurAmt from FA_CashRecovery  Where RegisterId in (" + sHORegId + ") and
// RefType='SO'

                    } else {
                        $iSerBillId = $this->bsf->isNullCheck($serBillId, 'number');
                        $soRegId = $this->bsf->isNullCheck($postData['soRegisterId'], 'number');
//                        $serBillType = $this->bsf->isNullCheck($postData['serBillType'], 'string');
//
//                        $soRegId = 0;
//                        $sdRegId = 0;
//                        if ($serBillType == 'D') {
//                            $sdRegId = $postData['sdRegId'];
//                        } else if ($serBillType == 'O') {
//                            $soRegId = $postData['soRegId'];
//                        }

                        if ($iSerBillId == 0) {
                            $sbaVNo = CommonHelper::getVoucherNo(409, date('Y-m-d', strtotime($postData['sbDate'])), 0, 0, $dbAdapter, "I");
                            if ($sbaVNo["genType"] == true) {
                                $sbNo = $sbaVNo["voucherNo"];
                            } else {
                                $sbNo = $postData['sbNo'];
                            }

                            $sbccaVNo = CommonHelper::getVoucherNo(409, date('Y-m-d', strtotime($postData['sbDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                            if ($sbccaVNo["genType"] == true) {
                                $sbCCNo = $sbccaVNo["voucherNo"];
                            } else {
                                $sbCCNo = $postData['ccSbNo'];
                            }

                            $sbcoaVNo = CommonHelper::getVoucherNo(409, date('Y-m-d', strtotime($postData['sbDate'])), $postData['companyId'], 0, $dbAdapter, "I");
                            if ($sbcoaVNo["genType"] == true) {
                                $sbCoNo = $sbcoaVNo["voucherNo"];
                            } else {
                                $sbCoNo = $postData['compSbNo'];
                            }
                            $inType = 'N';
                            $inName = 'WPM-ServiceBill-Add';
                            $inDesc = 'ServiceBill-Add';
                            $sRefNo = $postData['refNo'];

                            $insert = $sql->insert();
                            $insert->into('WPM_SBRegister');
                            $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                            , 'SORegisterId' => $soRegId
                            , 'SBDate' => date('Y-m-d', strtotime($postData['sbDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'SBNo' => $this->bsf->isNullCheck($sbNo, 'string')
                            , 'SBCCNo' => $this->bsf->isNullCheck($sbCCNo, 'string')
                            , 'SBCompNo' => $this->bsf->isNullCheck($sbCoNo, 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'Specification' => $this->bsf->isNullCheck($postData['specification'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $iSerBillId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                            for ($i = 1; $i <= $serviceRows; $i++) {
                                $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');

                                if ($serviceId != 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_SBServiceTrans');
                                    $insert->Values(array('SBRegisterId' => $iSerBillId
                                    , 'ServiceId' => $serviceId
                                    , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                    , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                    , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $SBServiceTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                                $reqRowId = $this->bsf->isNullCheck($postData['ser_' . $i . '_rowid'], 'number');
                                for ($k = 1; $k <= $reqRowId; $k++) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_SBAdjustmentTrans');
                                    $insert->Values(array('SBServiceTransId' => intval($SBServiceTransId)
                                    , 'ServiceId' => intval($serviceId)
                                    , 'SBRegisterId' => intval($iSerBillId)
                                    , 'SDRegisterId' => intval($this->bsf->isNullCheck($postData['ser_' . $k . '_id_' . $k], 'number'))
                                    , 'Qty' => floatval($this->bsf->isNullCheck($postData['ser_' . $i . '_curqty_' . $k], 'number'))));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $insert = $sql->insert();
                                    $insert->into('WPM_SBSDTrans');
                                    $insert->Values(array(
                                        'SBRegisterId' => intval($iSerBillId)
                                    , 'SDRegisterId' => intval($this->bsf->isNullCheck($postData['ser_' . $k . '_id_' . $k], 'number'))
                                    ));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_SBQualTrans');
                                $insert->Values(array('SBRegisterId' => $iSerBillId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $recoveryRows = $this->bsf->isNullCheck($postData['recoveryRows'], 'number');
                            for ($r = 1; $r <= $recoveryRows; $r++) {
                                if ($this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number') > 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_sbrecoverytrans');
                                    $insert->Values(array('SBRegisterId' => $iSerBillId
                                    , 'RecoveryTypeId' => $this->bsf->isNullCheck($postData['recoveryId_' . $r], 'number')
                                    , 'AccountId' => $this->bsf->isNullCheck($postData['recoveryAccount_' . $r], 'number')
                                    , 'Sign' => $this->bsf->isNullCheck($postData['recoverySign_' . $r], 'string')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                        } else {
                            $inType = 'E';
                            $inName = 'WPM-ServiceBill-Edit';
                            $inDesc = 'ServiceBill-Edit';
                            $sRefNo = $postData['refNo'];

                            $update = $sql->update();
                            $update->table('WPM_SBRegister');
                            $update->set(array('SBDate' => date('Y-m-d', strtotime($postData['sbDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'SBNo' => $this->bsf->isNullCheck($postData['sbNo'], 'string')
                            , 'SBCCNo' => $this->bsf->isNullCheck($postData['ccSbNo'], 'string')
                            , 'SBCompNo' => $this->bsf->isNullCheck($postData['compSbNo'], 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'Specification' => $this->bsf->isNullCheck($postData['specification'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                            $update->where(array('SBRegisterId' => $iSerBillId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('WPM_SBQualTrans');
                            $delete->where(array("SBRegisterId" => $iSerBillId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_SBQualTrans');
                                $insert->Values(array('SBRegisterId' => $iSerBillId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $delete = $sql->delete();
                                $delete->from('WPM_SBServiceTrans')
                                    ->where("SBRegisterId = $iSerBillId");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $serviceRows = $this->bsf->isNullCheck($postData['serviceRows'], 'number');
                                for ($i = 1; $i <= $serviceRows; $i++) {
                                    $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $i], 'number');

                                    if ($serviceId != 0) {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_SBServiceTrans');
                                        $insert->Values(array('SBRegisterId' => $iSerBillId
                                        , 'ServiceId' => $serviceId
                                        , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                        , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                        , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                        , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $delete = $sql->delete();
                                $delete->from('WPM_sbrecoverytrans')
                                    ->where("SBRegisterId = $$iSerBillId");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $recoveryRows = $this->bsf->isNullCheck($postData['recoveryRows'], 'number');
                                for ($r = 1; $r <= $recoveryRows; $r++) {
                                    if ($this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number') > 0) {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_sbrecoverytrans');
                                        $insert->Values(array('sBRegisterId' => $iSerBillId
                                        , 'RecoveryTypeId' => $this->bsf->isNullCheck($postData['recoveryId_' . $r], 'number')
                                        , 'AccountId' => $this->bsf->isNullCheck($postData['recoveryAccount_' . $r], 'number')
                                        , 'Sign' => $this->bsf->isNullCheck($postData['recoverySign_' . $r], 'string')
                                        , 'Amount' => $this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iSerBillId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'bill-register'));
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->serBillId != 0) {
                //Service Bill Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SBRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                    ->where('a.SBRegisterId = ' . $serBillId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $check= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SBRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT);
                if($check['SDRegisterId'] != 0) {
                    $select->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array('SDNo'), $select::JOIN_LEFT);
                }
                if($check['SORegisterId'] != 0) {
                    $select ->join(array('f' => 'WPM_SORegister'), 'a.SORegisterId = f.SORegisterId', array('SONo'), $select::JOIN_INNER);
                }
                $select->where('a.SBRegisterId = ' . $serBillId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //Service Trans
                $CostCentreId = $this->_view->sbRegister['CostCentreId'];
                $sorId=$check['SORegisterId'];
                //Service Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SBServiceTrans'))
                    ->columns(array(
                        "SBServiceTransId" => new Expression("a.SBServiceTransId"),
                        "SBRegisterId" => new Expression("a.SBRegisterId"),
                        "ServiceId" => new Expression("a.ServiceId"),
                        "UnitId" => new Expression("a.UnitId"),
                        "Qty" => new Expression('CAST(a.Qty As Decimal(18,3))'),
                        "Amount" => new Expression('CAST(a.Amount As Decimal(18,2))'),
                        "Rate" => new Expression('CAST(a.Rate As Decimal(18,2))'),
                    ))
                    ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('Desc' => new Expression('ServiceName'),'ServiceId'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_SBRegister'), 'e.SBRegisterId = a.SBRegisterId', array('CostCentreId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->where('a.SBRegisterId = ' . $serBillId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select10 = $sql->select();
                $select10->from(array('a1' => 'WPM_SBAdjustmentTrans'))
                    ->columns(array())
                    ->join(array('b1' => 'WPM_SDServiceTrans'), 'a1.SDRegisterId = b1.SDRegisterId', array('SDServiceTransId'), $select10::JOIN_INNER);
                $select10->where("a1.SBRegisterId=$serBillId");
                $statement = $sql->getSqlStringForSqlObject($select10);
                $arrreqtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $resid = array();
                foreach ($arrreqtrans as $arrreqtran) {
                    array_push($resid, $arrreqtran['SDServiceTransId']);
                }
                $resIDS = implode(",", $resid);

                if ($resIDS == "") {
                    $resIDS = 0;
                }

                $SBSelect = $sql->select();
                $SBSelect->from('WPM_SBRegister')
                    ->columns(array("SBRegisterId", "SDRegisterId"))
                    ->where(array("SBRegisterId=$serBillId"));

                $select = $sql->select();
                $select->from(array("D" => "WPM_SDServiceTrans"))
                    ->join(array('B' => 'WPM_SDRegister'), 'd.SDRegisterId=b.SDRegisterId', array('SDDate' => new Expression("FORMAT(b.SDDate, 'dd-MM-yyyy')"), 'SDNo', 'CostCentreId'), $select::JOIN_LEFT)
                    ->join(array('C' => 'Proj_ServiceMaster'), 'd.ServiceId=c.ServiceId', array('Desc' => 'ServiceName', 'ServiceId'), $select::JOIN_LEFT)
                    ->join(array('E' => $SBSelect), 'd.SDRegisterId=e.SDRegisterId', array(), $select::JOIN_LEFT)
                    ->join(array('F' => 'WPM_SBServiceTrans'), 'E.SBRegisterId=F.SBRegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('SDServiceTransId', 'Qty', 'SDRegisterId', 'SOQty' => new Expression('CAST(0 As Decimal(18,3))'),
                        'BalQty' => new Expression('CAST((D.Qty) As Decimal(18,3))'), 'CurQty' => new Expression('CAST(F.Qty As Decimal(18,3))')))
                    ->where(array("d.SDServiceTransId in ($resIDS)"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WPM_SBQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.SBRegisterId' => $serBillId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $select = $sql->select();
                $select->from(array('a' => 'WPM_ServiceRecoveryType'))
                    ->columns(array('RecoveryTypeId', 'RecoveryTypeName',
                        'AccountId' => new Expression("isnull(b.AccountId,0)"),
                        'Amount' => new Expression("isnull(b.Amount,0.000)"),
                        'Sign' => new Expression("isnull(b.Sign,'')"),
                    ))
                    ->join(array("b" => "WPM_sbrecoverytrans"), new Expression("a.RecoveryTypeId=b.RecoveryTypeId and b.SBRegisterId =$serBillId"), array(), $select::JOIN_LEFT);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('FA_AccountMaster');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryAccounts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('A'=>'WF_CompanyMailSetting'))
                    ->columns(array("AdvanceDeductWithoutPaid"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $AdvanceWithoutPaid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] ==1 ) {
                    $select1 = $sql->select();
                    $select1->from(array('A' => 'WPM_SOGeneralTerms'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.Value) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->join(array('B' => 'WF_TermsMaster'), 'A.TermsId=B.TermsId',array(),$select1::JOIN_INNER)
                        ->where(array("B.Title ='Advance' and B.TermType='W' and A.SORegisterId in ($sorId)"));

                }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                    $select1 = $sql->select();
                    $select1->from(array('A' => 'WPM_SORegister'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.AdvanceAmt) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->where(array("A.SORegisterId in ($sorId)"));

                }
                $select2 = $sql->select();
                $select2->from(array('A' => 'WPM_Sbrecoverytrans'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(sum(A.Amount) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                    ->where(array("SBRegisterId in (Select SBRegisterId from WPM_SBRegister Where SORegisterId in ($sorId))"));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array('A' => 'WPM_RetentionReleaseRegister'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(sum(A.AdvanceAmt) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                    ->where(array("RRRegisterId in (Select RRRegisterId from WPM_RetentionReleaseRegister Where OrderType='S' and OrderId in ($sorId))"));
                $select3->combine($select2,'Union ALL');
                if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 1 ) {
                    $select10 = $sql->select();
                    $select10->from(array('A' => 'WPM_SDRegister'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(AdvAmount) As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->where(array("A.SORegisterId in ($sorId)"));
                    $select10->combine($select3,'Union ALL');

                }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                    $select10 = $sql->select();
                    $select10->from(array('A' => 'WPM_SDRegister'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(AdvancePaid) As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->where(array("A.SORegisterId in ($sorId)"));
                    $select10->combine($select3,'Union ALL');
                }
                $select4 = $sql->select();
                $select4->from(array('A' => 'WPM_Sbrecoverytrans'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(A.Amount As Decimal(18,3))')))
                    ->where(array("SBRegisterId in (Select SBRegisterId from WPM_SBRegister Where SORegisterId in ($sorId)) and RecoveryTypeId=4"));
                $select4->combine($select10,'Union ALL');

                $select = $sql->select();
                $select->from(array('G' => $select4))
                    ->columns(array('MobAdvance'=>new Expression('CAST(Sum(G.MobAdvance) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(G.CashAdvance) As Decimal(18,3))'),
                        'TotalAdvance' => new Expression('CAST(Sum(G.MobAdvance+G.CashAdvance) As Decimal(18,3))'),'Recovery' => new Expression('CAST(Sum(G.Recovery) As Decimal(18,3))'),
                        'Balance' => new Expression('Case When Sum(G.MobAdvance+G.CashAdvance-G.Recovery) > 0 then Sum(G.MobAdvance+G.CashAdvance-G.Recovery) Else 0 end')
                    ,'CurAmt' => new Expression('CAST(Sum(G.CurAmt) As Decimal(18,3))')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->advRecv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            }
            if ($this->_view->serBillId == 0) {
                $select = $sql->select();
                $select->from(array("a" => "Proj_QualifierTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")));
                $select->where(array('a.QualType' => 'W'));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                //Operational Cost Centre
                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Vendor Master
                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId', 'VendorName'))
                    ->where(array('Contract' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WPM_ServiceRecoveryType');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('FA_AccountMaster');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryAccounts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $aVNo = CommonHelper::getVoucherNo(409, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == true)
                    $this->_view->sbNo = $aVNo["voucherNo"];
                else
                    $this->_view->sbNo = "";

                $this->_view->sbTypeId = '409';
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getServiceDoneAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_SDRegister')
                    ->columns(array('data' => 'SDRegisterId', 'value' => 'SDNo'))
                    ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getDoneServicesAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $soRegId=$this->bsf->isNullCheck($postData['soRegId'],'number');
                $ccId=$this->bsf->isNullCheck($postData['costId'],'number');
                $serTypeId=$this->bsf->isNullCheck($postData['serTypeId'],'number');
                //Service Master
                $sql = new Sql($dbAdapter);
                if($soRegId != 0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_SOServiceTrans'))
                        ->columns(array(
                            "SORegisterId" => new Expression("a.SORegisterId"),
                            "data" => new Expression("a.ServiceId"),
                            "UnitId" => new Expression("a.UnitId"),
                            "WorkUnitId" => new Expression("a.WorkUnitId"),
                            "Qty" => new Expression('CAST(a.Qty As Decimal(18,3))'),
                            "Amount" => new Expression('CAST(a.Amount As Decimal(18,2))'),
                            "Rate" => new Expression('CAST(a.Rate As Decimal(18,2))'),
                        ))
                        ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId', array('value' => new Expression('ServiceName')), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_SORegister'), 'e.SORegisterId = a.SORegisterId', array('CostCentreId'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->join(array('h' => 'WF_OperationalCostCentre'), 'e.CostCentreId = h.CostCentreId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_OHService'), 'a.ServiceId= f.ServiceId and h.ProjectId=f.ProjectId', array('eQty' => 'Qty'), $select::JOIN_LEFT)
                        ->where(array("a.SORegisterId = $soRegId"));
                }else{
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ServiceMaster'))
                        ->columns(array('data' =>new Expression("Distinct a.ServiceId"), 'value' => new Expression("Case When isnull(a.ServiceCode,'') <> '' Then a.ServiceCode + ' - ' + a.ServiceName Else a.ServiceName End")))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId' => 'UnitId','UnitName' => 'UnitName'), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_OHService'), new Expression("a.ServiceId = c.ServiceId"), array('Rate','Qty','Amount'), $select::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'), new Expression("c.ProjectId = d.ProjectId  and d.CostCentreId=$ccId"), array('ProjectId'), $select::JOIN_INNER)
                        ->join(array('e' => 'WPM_SORegister'), new Expression("d.CostCentreId = e.CostCentreId"), array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'WPM_SOServiceTrans'), new Expression("e.SORegisterId = f.SORegisterId and a.ServiceId = f.ServiceId"), array('OQty'=>new Expression("sum(f.Qty)")), $select::JOIN_LEFT)
                        ->join(array('g' => 'WPM_SBRegister'), new Expression("d.CostCentreId = g.CostCentreId"), array(), $select::JOIN_LEFT)
                        ->join(array('h' => 'WPM_SBServiceTrans'), new Expression("g.SBRegisterId = h.SBRegisterId and a.ServiceId = h.ServiceId"), array('BQty'=>new Expression("sum(h.Qty)")), $select::JOIN_LEFT)
                        ->where(array("a.ServiceTypeId=$serTypeId"))
                        ->group(new Expression("a.ServiceId,a.ServiceCode,a.ServiceName,b.UnitId,b.UnitName,c.Rate,c.Qty,c.Amount,d.ProjectId"));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function labourStrengthRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Strength");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_LabourStrengthRegister'))
            ->columns(array("LSRegisterId", "LSNo","Approved"=>new Expression("Case When a.Approve='Y' then 'Yes' else 'No' end"),
                "display"=>new Expression("Case When a.Approve='Y' then 'none' else 'block' end") ,"LSDate" => new Expression("FORMAT(a.LSDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT);
        $select->order('a.LSRegisterId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->lsRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteLsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $lsRegId = $this->params()->fromPost('lsRegId');
                    $response = $this->getResponse();

                    $subQuery = $sql->select();
                    $subQuery->from('WPM_LSVendorTrans')
                        ->columns(array('LSVendorTransId'))
                        ->where(array('LSRegisterId' => $lsRegId));

                    $delete = $sql->delete();
                    $delete->from('WPM_LSWBSTrans')
                        ->where->expression('LSVendorTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LSTypeTrans')
                        ->where->expression('LSVendorTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LSVendorTrans')
                        ->where("LSRegisterId = $lsRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LSLabourTrans')
                        ->where("LSRegisterId = $lsRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LabourStrengthRegister')
                        ->where("LSRegisterId = $lsRegId");
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

    public function rateApprovalRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Rate Approval");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_LRARegister'))
            ->columns(array("LRARegisterId", "LRANo", 'VendorName' => new Expression("Case When a.VendorId <>0 then c.VendorName else d.LabourGroupName + '(Internal)' end"),
                "LRADate" => new Expression("FORMAT(a.LRADate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"),
                "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')"), 'Approve' => new Expression("Case When a.Approve='Y' then 'Yes' when a.Approve='P' then 'Partial' else 'No' end")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = d.LabourGroupId', array(), $select::JOIN_LEFT)
            ->where(array('a.Live' => 1));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->lraRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteLraAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $lraRegId = $this->params()->fromPost('lraRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_LRATypeTrans')
                        ->where("LRARegisterId = $lraRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LRARegister')
                        ->where("LRARegisterId = $lraRegId");
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

    public function serviceOrderRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Service Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_SORegister'))
            ->columns(array("SORegisterId", "SONo", "SODate" => new Expression("FORMAT(a.SODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName'), $select::JOIN_LEFT);

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->soRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteSoAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $soRegId = $this->params()->fromPost('soRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_SOServiceTrans')
                        ->where("SORegisterId = $soRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_SORegister')
                        ->where("SORegisterId = $soRegId");
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

    public function serviceDoneRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Service Done");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_SDRegister'))
            ->columns(array("SDRegisterId", "SDNo",
                "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                "SDDate" => new Expression("FORMAT(a.SDDate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_SORegister'), 'a.SORegisterId = d.SORegisterId', array('SONo'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->sdRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteSdAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $sdRegId = $this->params()->fromPost('sdRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_SDServiceTrans')
                        ->where("SDRegisterId = $sdRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_SDRegister')
                        ->where("SDRegisterId = $sdRegId");
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

    public function serviceBillRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Serviace Bill");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_SBRegister'))
            ->columns(array("SBRegisterId","Amount", "SBNo", "SBDate" => new Expression("FORMAT(a.SBDate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array('SDNo'), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_SORegister'), 'a.SORegisterId = e.SORegisterId', array('SONo'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->sbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteSbAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $sbRegId = $this->params()->fromPost('sbRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_SBServiceTrans')
                        ->where("SBRegisterId = $sbRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_SBRegister')
                        ->where("SBRegisterId = $sbRegId");
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

    public function hireOrderAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Hire Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $hireOrdId = $this->bsf->isNullCheck($this->params()->fromRoute('hireOrderId'),'number');
        $amdId = $this->bsf->isNullCheck($this->params()->fromRoute('amdId'), 'number');
//        $typeHo = $this->bsf->isNullCheck($this->params()->fromRoute('type'), 'string');
        $this->_view->hireOrdId = (isset($hireOrdId) && $hireOrdId != 0) ? $hireOrdId : 0;
//        $this->_view->typeHo = (isset($typeHo) && $typeHo != 0) ? $typeHo : '';

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');

                $response = $this->getResponse();
                switch ($Type) {
                    case 'selectBranchCPerson':
                        $BranchId = $this->bsf->isNullCheck($this->params()->fromPost('branchid'), 'number');
                        $selbranchcperson = $sql->select();
                        $selbranchcperson->from(array("a" => "Vendor_BranchContactDetail"))
                            ->columns(array('data' => new Expression("a.BranchTransId"), 'value' => new Expression("a.ContactPerson")))
                            ->where('BranchId=' . $BranchId . '');
                        $statement = $sql->getSqlStringForSqlObject($selbranchcperson);
                        $arr_cperson = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_cperson));
                        return $response;
                        break;
                    case 'selectCPersonno':
                        $BranchTransId = $this->bsf->isNullCheck($this->params()->fromPost('branchtransid'), 'number');
                        $selbranchcpersonno = $sql->select();
                        $selbranchcpersonno->from(array("a" => "Vendor_BranchContactDetail"))
                            ->columns(array(
                                'ContactNo' => new Expression("a.ContactNo"),
                                'Designation' => new Expression("a.Designation"),
                                'Fax' => new Expression("a.Fax"),
                                'Email' => new Expression("a.Email")
                            ))
                            ->where('BranchTransId=' . $BranchTransId . '');
                        $statement = $sql->getSqlStringForSqlObject($selbranchcpersonno);
                        $arr_cpersonno = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_cpersonno));
                        return $response;
                        break;
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
//					echo '<pre>'; print_r($postData); die;

                    if (!is_null($postData['frm_index'])) {
                        $this->_view->frmInd = 1;
                        $CostCentre = $postData['CostCentre'];
                        $this->_view->ccId = $CostCentre;
                        $vendorId = $postData['VendorId'];
                        $this->_view->venId = $vendorId;
                        $hireTypeId = $postData['eHireTypeId'];
//                        echo $CostCentre,$vendorId,$hireTypeId;die;
//                            $requestTransIds =$postData['requestTransIds'];
//                            if($requestTransIds==''){
//                                $requestTransIds = 0;
//                            }else{
//                                $requestTransIds = implode(',',$postData['requestTransIds']);
//                            }
                        $this->_view->hireTypeId = $hireTypeId;

                        $select = $sql->select();
                        $select->from('wf_OperationalCostCentre')
                            ->columns(array('CostCentreId', 'CostCentreName', 'ProjectId', 'CompanyId'))
                            ->where(array('CostCentreId' => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->costCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('vendor_master')
                            ->columns(array('VendorId', 'VendorName'))
                            ->where(array('VendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('WPM_hireTypeMaster')
                            ->columns(array('HireTypeId', 'HireTypeName'))
                            ->where(array('HireTypeId' => $hireTypeId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->hireType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        //branch details
                        $selBranch = $sql->select();
                        $selBranch->from(array("a" => "Vendor_Branch"))
                            ->columns(array("BranchId" => new Expression("a.BranchId"), "BranchName" => new Expression("a.BranchName")))
                            ->where('a.VendorId=' . $this->_view->vendor['VendorId'] . '');
                        $branchStatement = $sql->getSqlStringForSqlObject($selBranch);
                        $branch = $dbAdapter->query($branchStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->branch = $branch;
//                            $select = $sql->select();
//                            $select->from(array("d" => "VM_RequestTrans"))
//                                ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo','CostCentreId'), $select::JOIN_LEFT);
//                            $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName','ServiceId'), $select::JOIN_LEFT);
//                            $select->columns(array('RequestTransId', 'Quantity', 'RequestId','WOQty','BalQty' => new Expression("d.Quantity-d.WOQty"),'CurQty'=>new Expression("d.Quantity-d.WOQty"),))
//                                ->where(array("d.RequestTransId IN($requestTransIds)"));
//                            $statement = $sql->getSqlStringForSqlObject($select);
//                            $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                            $select = $sql->select();
//                            $select->from(array("d" => "VM_RequestTrans"))
//                                ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo','CostCentreId'), $select::JOIN_LEFT);
//                            $select->join(array('c' => 'Proj_ServiceMaster'), 'd.ResourceId=c.ServiceId', array('Desc' => 'ServiceName','ServiceId'), $select::JOIN_LEFT);
//                            $select->join(array('z' => 'Proj_OHService'), 'z.ServiceId=c.ServiceId', array('Rate','Qty','Amount'), $select::JOIN_LEFT);
//                            $select->join(array('y' => 'Proj_Uom'), 'y.UnitId=d.UnitId', array('UnitId','UnitName'), $select::JOIN_LEFT);
//                            $select->columns(array('RequestTransId', 'RequestId'))
//                                ->where(array("d.RequestTransId IN($requestTransIds)"));
//                            $statement = $sql->getSqlStringForSqlObject($select);
//                            $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else if($hireOrdId == 0 || $amdId != 0) {

                        $iHireOrdId = $this->bsf->isNullCheck($hireOrdId, 'number');
                        $hireTypeId = $this->bsf->isNullCheck($postData['eHireTypeId'], 'number');

                        $iTypeHo = $this->bsf->isNullCheck($postData['typeHo'], 'string');

                        $hoNo = $postData['hoNo'];
                        $hoCCNo = $postData['ccHoNo'];
                        $hoCoNo = $postData['compHoNo'];

                        if($amdId == 0) {
                            $amendment = 0;
                            $hoaVNo = CommonHelper::getVoucherNo(402, date('Y-m-d', strtotime($postData['hoDate'])), 0, 0, $dbAdapter, "I");
                            if ($hoaVNo["genType"] == true) {
                                $hoNo = $hoaVNo["voucherNo"];
                            } else {
                                $hoNo = $postData['hoNo'];
                            }

                            $hoccaVNo = CommonHelper::getVoucherNo(402, date('Y-m-d', strtotime($postData['hoDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                            if ($hoccaVNo["genType"] == true) {
                                $hoCCNo = $hoccaVNo["voucherNo"];
                            } else {
                                $hoCCNo = $postData['ccHoNo'];
                            }

                            $hocoaVNo = CommonHelper::getVoucherNo(402, date('Y-m-d', strtotime($postData['hoDate'])), $postData['companyId'], 0, $dbAdapter, "I");
                            if ($hocoaVNo["genType"] == true) {
                                $hoCoNo = $hocoaVNo["voucherNo"];
                            } else {
                                $hoCoNo = $postData['compHoNo'];
                            }
                        } else {
                            $hoNewNo = explode('_', $hoNo);
                            if(!isset($hoNewNo[1])) {
                                $hoNo =  $hoNo.'_1';
                            } else {
                                $incHoNo = ($hoNewNo[1] + 1);
                                $hoNo =  $hoNewNo[0].'_'.$incHoNo;
                            }
//                            print_r($woNo);die;

                            $amendment = 1;
                        }

                        if ($iHireOrdId == 0) {
                            $inType = 'N';
                            $inName = 'WPM-HireOrder-Add';
                            $inDesc = 'HireOrder-Add';
                            $sRefNo = $postData['refNo'];

                            $insert = $sql->insert();
                            $insert->into('WPM_HORegister');
                            $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                            , 'HireTypeId' => $hireTypeId
                            , 'HODate' => date('Y-m-d', strtotime($postData['hoDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'HONo' => $this->bsf->isNullCheck($hoNo, 'string')
                            , 'HOCCNo' => $this->bsf->isNullCheck($hoCCNo, 'string')
                            , 'HOCompNo' => $this->bsf->isNullCheck($hoCoNo, 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'HOContactName' => $this->bsf->isNullCheck($postData['HOContactName'], 'string')
                            , 'HOContactNo' => $this->bsf->isNullCheck($postData['HOContactNo'], 'string')
                            , 'SiteContactName' => $this->bsf->isNullCheck($postData['sContactName'], 'string')
                            , 'SiteContactNo' => $this->bsf->isNullCheck($postData['sContactNo'], 'string')
                            , 'SiteAddress' => $this->bsf->isNullCheck($postData['sAddress'], 'string')
                            , 'BranchId' => $this->bsf->isNullCheck($postData['branch'], 'number')
                            , 'BranchTransId' => $this->bsf->isNullCheck($postData['Contact'], 'number')
                            , 'ScopeofWork' => $this->bsf->isNullCheck($postData['scopeWork'], 'string')
                            , 'Amendment' => $this->bsf->isNullCheck($amendment, 'number')
                            , 'AHORegisterId' => $this->bsf->isNullCheck($amdId, 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $iHireOrdId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_HOQualTrans');
                                $insert->Values(array('HORegisterId' => $iHireOrdId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $termsTotal = $postData['trowid'];
                            $valueFrom = 0;
                            if ($postData['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postData['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postData['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }
                            for ($t = 1; $t < $termsTotal; $t++) {
                                if ($this->bsf->isNullCheck($postData['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                    }
                                    $termsInsert = $sql->insert('WPM_HOGeneralTerms');
                                    $termsInsert->values(array("HORegisterId" => $iHireOrdId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            if($amdId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_HORegister');
                                $update->set(array('LiveWO' => 0));
                                $update->where(array('HORegisterId' => $amdId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else {
                            $inType = 'E';
                            $inName = 'WPM-HireOrder-Edit';
                            $inDesc = 'HireOrder-Edit';
                            $sRefNo = $postData['refNo'];

                            $update = $sql->update();
                            $update->table('WPM_HORegister');
                            $update->set(array('HODate' => date('Y-m-d', strtotime($postData['hoDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'HireTypeId' => $hireTypeId
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'HONo' => $this->bsf->isNullCheck($hoNo, 'string')
                            , 'HOCCNo' => $this->bsf->isNullCheck($hoCCNo, 'string')
                            , 'HOCompNo' => $this->bsf->isNullCheck($hoCoNo, 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                            , 'HOContactName' => $this->bsf->isNullCheck($postData['HOContactName'], 'string')
                            , 'HOContactNo' => $this->bsf->isNullCheck($postData['HOContactNo'], 'string')
                            , 'SiteContactName' => $this->bsf->isNullCheck($postData['sContactName'], 'string')
                            , 'SiteContactNo' => $this->bsf->isNullCheck($postData['sContactNo'], 'string')
                            , 'SiteAddress' => $this->bsf->isNullCheck($postData['sAddress'], 'string')
                            , 'BranchId' => $this->bsf->isNullCheck($postData['branch'], 'number')
                            , 'BranchTransId' => $this->bsf->isNullCheck($postData['Contact'], 'number')
                            , 'ScopeofWork' => $this->bsf->isNullCheck($postData['scopeWork'], 'string')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            ));
                            $update->where(array('HORegisterId' => $iHireOrdId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('WPM_HOQualTrans');
                            $delete->where(array("HORegisterId" => $iHireOrdId));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delPOPayTrans = $sql->delete();
                            $delPOPayTrans->from('WPM_HOGeneralTerms')
                                ->where(array("HORegisterId" => $iHireOrdId));
                            $POPayStatement = $sql->getSqlStringForSqlObject($delPOPayTrans);
                            $dbAdapter->query($POPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_HOQualTrans');
                                $insert->Values(array('HORegisterId' => $iHireOrdId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $termsTotal = $postData['trowid'];
                            $valueFrom = 0;
                            if ($postData['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postData['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postData['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }
                            for ($t = 1; $t < $termsTotal; $t++) {
                                if ($this->bsf->isNullCheck($postData['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                    }
                                    $termsInsert = $sql->insert('WPM_HOGeneralTerms');
                                    $termsInsert->values(array("HORegisterId" => $iHireOrdId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        $delete = $sql->delete();
                        $delete->from('WPM_HOTypeTrans')
                            ->where("HORegisterId = $iHireOrdId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $hireRows = $this->bsf->isNullCheck($postData['hireRows'], 'number');
                        for ($i = 1; $i <= $hireRows; $i++) {
                            $hireId = $this->bsf->isNullCheck($postData['hireId_' . $i], 'number');

                            $wQty = 0;
                            $wUnit = 0;
                            $totQty = 0;
                            if ($hireTypeId == 1 || $hireTypeId == 2) {
                                $wQty = $this->bsf->isNullCheck($postData['workingQty_' . $i], 'number');
                                $wUnit = $this->bsf->isNullCheck($postData['workingUnitId_' . $i], 'number');
                                $totQty = $this->bsf->isNullCheck($postData['totalQty_' . $i], 'number');
                            }

                            if ($hireId != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_HOTypeTrans');
                                $insert->Values(array('HORegisterId' => $iHireOrdId
                                , 'ResourceId' => $hireId
                                , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                , 'WorkingQty' => $wQty
                                , 'WorkingUnitId' => $wUnit
                                , 'TotalQty' => $totQty
                                , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iHireOrdId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'register'));
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->hireOrdId != 0) {
                //Hire Order Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_HORegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName', 'ProjectId'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'))
                    ->where('a.HORegisterId = ' . $hireOrdId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hoRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array('a' => 'WPM_HOTypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('data' => 'ResourceId', 'ResourceName', 'UnitId' => 'UnitId', 'WorkRate' => 'WorkRate', 'WorkUnitId'), $select::JOIN_INNER)
                    ->join(array('d' => 'Proj_projectResource'), 'a.ResourceId = d.ResourceId', array('eRate' => 'Rate', 'eQty' => 'Qty'), $select::JOIN_INNER)
                    ->join(array('f' => 'WPM_HORegister'), 'f.HORegisterId = a.HORegisterId', array(), $select::JOIN_INNER)
                    ->join(array('g' => 'WF_OperationalCostCentre'), 'g.CostCentreId = f.CostCentreId and g.ProjectId=d.ProjectId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'e.UnitId = b.WorkUnitId', array('wUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where('a.HORegisterId = ' . $hireOrdId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hoHireTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //branch contact details
                $selBranch = $sql->select();
                $selBranch->from(array("a" => "Vendor_BranchContactDetail"))
                    ->columns(array("BranchTransId" => new Expression("a.BranchTransId"), 'ContactPerson', 'Designation', 'ContactNo', 'Email', 'Fax'))
                    ->where('a.BranchTransId=' . $this->_view->hoRegister['BranchTransId'] . '');
                $branchStatement = $sql->getSqlStringForSqlObject($selBranch);
                $this->_view->branchContactDetail = $dbAdapter->query($branchStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //branch details
                $selBranch = $sql->select();
                $selBranch->from(array("a" => "Vendor_Branch"))
                    ->columns(array("BranchId" => new Expression("a.BranchId"), "BranchName" => new Expression("a.BranchName")))
                    ->where('a.VendorId=' . $this->_view->hoRegister['VendorId'] . '');
                $branchStatement = $sql->getSqlStringForSqlObject($selBranch);
                $this->_view->branch = $dbAdapter->query($branchStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $selbranchcperson = $sql->select();
                $selbranchcperson->from(array("a" => "Vendor_BranchContactDetail"))
                    ->columns(array('data' => new Expression("a.BranchTransId"), 'value' => new Expression("a.ContactPerson")))
                    ->where('BranchId=' . $this->_view->hoRegister['BranchId'] . '');
                $statement = $sql->getSqlStringForSqlObject($selbranchcperson);
                $cPerson = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->cPerson = $cPerson;

                $select = $sql->select();
                $select->from(array("a" => "WPM_HOQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.HORegisterId' => $hireOrdId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $selTer = $sql->select();
                $selTer->from(array("a" => "WPM_HOGeneralTerms"))
                    ->columns(array("ValueFromNet"))
                    ->where('HORegisterId=' . $hireOrdId . '');
                $terStatement = $sql->getSqlStringForSqlObject($selTer);
                $terResult = $dbAdapter->query($terStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->valuefrom = $this->bsf->isNullCheck($terResult['ValueFromNet'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    //->columns(array('data' => 'TermsId',))
                    ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                    ->where(array("TermType" => 'W'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    //->columns(array('data' => 'TermsId',))
                    ->columns(array(new Expression("a.TermsId As data,a.SlNo,a.Title As value,CAST(b.Per As Decimal(18,3)) As Per,
                                CAST(b.Value As Decimal(18,3)) As Val,b.Period As Period,b.TDate As [Dte],b.TString As [Strg],a.Per As IsPer,
                                a.Value As IsValue,a.Period As IsPeriod,a.TDate As IsTDate,a.TSTring As IsTString,a.IncludeGross")))
                    ->join(array('b' => 'WPM_HOGeneralTerms'), 'a.TermsId=b.TermsId', array(), $select::JOIN_INNER)
                    ->where(array("b.HORegisterId" => $hireOrdId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_edit_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WPM_HireTypeMaster')
                    ->columns(array('HireTypeId', 'HireTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hireTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'UnitId' => 'UnitId', 'WorkRate' => 'WorkRate', 'WorkUnitId'))
                    ->join(array('c' => 'Proj_projectResource'), 'a.ResourceId = c.ResourceId', array('Rate', 'Qty'), $select::JOIN_LEFT)
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'd.UnitId = a.WorkUnitId', array('wUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where(array('a.TypeId' => 3, 'ProjectId' => $this->_view->hoRegister['ProjectId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            } else {

                //Operational Cost Centre
                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Vendor Master
                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId', 'VendorName'))
                    ->where(array('Contract' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "Proj_QualifierTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")));
                $select->where(array('a.QualType' => 'W'));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;

                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $this->_view->valuefrom = 0;
                //Vendor Master

                $select = $sql->select();
                $select->from(array("a" => "WF_TermsMaster"))
                    //->columns(array('data' => 'TermsId',))
                    ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                    ->where(array("TermType" => 'W'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                //Hire Type Master
                $select = $sql->select();
                $select->from('WPM_HireTypeMaster')
                    ->columns(array('HireTypeId', 'HireTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hireTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'UnitId' => 'UnitId', 'WorkRate' => 'WorkRate', 'WorkUnitId'))
                    ->join(array('c' => 'Proj_projectResource'), 'a.ResourceId = c.ResourceId', array('Rate', 'Qty'), $select::JOIN_LEFT)
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'd.UnitId = a.WorkUnitId', array('wUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where(array('a.TypeId' => 3, 'ProjectId' => $this->_view->costCentre['ProjectId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resourceMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                //Project Resource
//                $select = $sql->select();
//                $select->from(array('a' => 'Proj_Resource'))
//                    ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'UnitId' => 'UnitId','WorkRate'=>'WorkRate'))
//                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_LEFT)
//                    ->where(array('a.TypeId' => 3));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resourceMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Working Unit
                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $aVNo = CommonHelper::getVoucherNo(402, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == true)
                    $this->_view->hoNo = $aVNo["voucherNo"];
                else
                    $this->_view->hoNo = "";

                $this->_view->hoTypeId = '402';
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function hireBillAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Hire Bill");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $hireBillId = $this->params()->fromRoute('hireBillId');
        $this->_view->hireBillId = (isset($hireBillId) && $hireBillId != 0) ? $hireBillId : 0;
        $this->_view->frmInd = 0;
        $this->_view->hbRegister = '';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                $this->_view->setTerminal(true);
//                $response = $this->getResponse()->setContent(json_encode());
//                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    if (!is_null($postData['frm_index'])) {
                        $this->_view->frmInd = 1;
                        $ccId = $postData['costCentreId'];
                        $this->_view->ccId = $ccId;
                        $venId = $postData['vendorId'];
                        $this->_view->venId = $venId;
                        $hrId = $postData['hoRegId'];
                        $this->_view->hrId = $hrId;
                        $orderType = $postData['OrderType'];
                        $this->_view->orderType = $orderType;

                        $select = $sql->select();
                        $select->from('wf_OperationalCostCentre')
                            ->columns(array('CostCentreId', 'CostCentreName', 'CompanyId'))
                            ->where(array('CostCentreId' => $ccId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->costCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('vendor_master')
                            ->columns(array('VendorId', 'VendorName'))
                            ->where(array('VendorId' => $venId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_HORegister'))
                            ->columns(array('HORegisterId'))
                            ->join(array('b' => 'WPM_HireTypeMaster'), 'a.HireTypeId = b.HireTypeId', array('HireTypeId', 'HireTypeName'), $select::JOIN_LEFT)
                            ->where(array('HORegisterId' => $hrId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->hireType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('A'=>'WF_CompanyMailSetting'))
                            ->columns(array("AdvanceDeductWithoutPaid"));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $AdvanceWithoutPaid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] ==1 ) {
                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_HOGeneralTerms'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.Value) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->join(array('B' => 'WF_TermsMaster'), 'A.TermsId=B.TermsId',array(),$select1::JOIN_INNER)
                                ->where(array("B.Title ='Advance' and B.TermType='W' and A.HORegisterId in ($hrId)"));

                        }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                            $select1 = $sql->select();
                            $select1->from(array('A' => 'WPM_HORegister'))
                                ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.AdvanceAmt) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                    'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                                ->where(array("A.HORegisterId in ($hrId)"));

                        }
                        $select2 = $sql->select();
                        $select2->from(array('A' => 'WPM_Hbrecoverytrans'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(sum(A.Amount) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                            ->where(array("HBRegisterId in (Select HBRegisterId from WPM_HBRegister Where HORegisterId in ($hrId))"));
                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array('A' => 'WPM_RetentionReleaseRegister'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(sum(A.AdvanceAmt) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                            ->where(array("RRRegisterId in (Select RRRegisterId from WPM_RetentionReleaseRegister Where OrderType='H' and OrderId in ($hrId))"));
                        $select3->combine($select2,'Union ALL');

                        $select4 = $sql->select();
                        $select4->from(array('A' => 'WPM_Hbrecoverytrans'))
                            ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                                'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(A.Amount As Decimal(18,3))')))
                            ->where(array("HBRegisterId in (Select HBRegisterId from WPM_HBRegister Where HORegisterId in ($hrId)) "));
                        $select4->combine($select3,'Union ALL');

                        $select = $sql->select();
                        $select->from(array('G' => $select4))
                            ->columns(array('MobAdvance'=>new Expression('CAST(Sum(G.MobAdvance) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(G.CashAdvance) As Decimal(18,3))'),
                                'TotalAdvance' => new Expression('CAST(Sum(G.MobAdvance+G.CashAdvance) As Decimal(18,3))'),'Recovery' => new Expression('CAST(Sum(G.Recovery) As Decimal(18,3))'),
                                'Balance' => new Expression('Case When Sum(G.MobAdvance+G.CashAdvance-G.Recovery) > 0 then Sum(G.MobAdvance+G.CashAdvance-G.Recovery) Else 0 end')
                            ,'CurAmt' => new Expression('CAST(Sum(G.CurAmt) As Decimal(18,3))')));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->advRecv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    } else {
                        $iHireBillId = $this->bsf->isNullCheck($hireBillId, 'number');
                        $hireTypeId = $this->bsf->isNullCheck($postData['eHireTypeId'], 'number');

                        if ($iHireBillId == 0) {
                            $hbaVNo = CommonHelper::getVoucherNo(408, date('Y-m-d', strtotime($postData['hbDate'])), 0, 0, $dbAdapter, "I");
                            if ($hbaVNo["genType"] == true) {
                                $hbNo = $hbaVNo["voucherNo"];
                            } else {
                                $hbNo = $postData['hbNo'];
                            }

                            $hbccaVNo = CommonHelper::getVoucherNo(408, date('Y-m-d', strtotime($postData['hbDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                            if ($hbccaVNo["genType"] == true) {
                                $hbCCNo = $hbccaVNo["voucherNo"];
                            } else {
                                $hbCCNo = $postData['ccHbNo'];
                            }

                            $hbcoaVNo = CommonHelper::getVoucherNo(408, date('Y-m-d', strtotime($postData['hbDate'])), $postData['companyId'], 0, $dbAdapter, "I");
                            if ($hbcoaVNo["genType"] == true) {
                                $hbCoNo = $hbcoaVNo["voucherNo"];
                            } else {
                                $hbCoNo = $postData['compHbNo'];
                            }
                            $inType = 'N';
                            $inName = 'WPM-HireBill-Add';
                            $inDesc = 'HireBill-Add';
                            $sRefNo = $postData['refNo'];

                            $insert = $sql->insert();
                            $insert->into('WPM_HBRegister');
                            $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                            , 'HORegisterId' => $this->bsf->isNullCheck($postData['hoRegId'], 'number')
                            , 'HBDate' => date('Y-m-d', strtotime($postData['hbDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'HBNo' => $this->bsf->isNullCheck($hbNo, 'string')
                            , 'HBCCNo' => $this->bsf->isNullCheck($hbCCNo, 'string')
                            , 'HBCompNo' => $this->bsf->isNullCheck($hbCoNo, 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'RecoveryAmount' => $this->bsf->isNullCheck($postData['recoveryServiceBill'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $iHireBillId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_HBQualTrans');
                                $insert->Values(array('HBRegisterId' => $iHireBillId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $hireRows = $this->bsf->isNullCheck($postData['hireRows'], 'number');
                            for ($i = 1; $i <= $hireRows; $i++) {
                                $hireId = $this->bsf->isNullCheck($postData['hireId_' . $i], 'number');

                                $wQty = 0;
                                $wUnit = 0;
                                $totQty = 0;
                                if ($hireTypeId == 1 || $hireTypeId == 2) {
                                    $wQty = $this->bsf->isNullCheck($postData['workingQty_' . $i], 'number');
                                    $wUnit = $this->bsf->isNullCheck($postData['workingUnitId_' . $i], 'number');
                                    $totQty = $this->bsf->isNullCheck($postData['totalQty_' . $i], 'number');
                                }

                                if ($hireId != 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_HBTypeTrans');
                                    $insert->Values(array('HBRegisterId' => $iHireBillId
                                    , 'ResourceId' => $hireId
                                    , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                    , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                    , 'WorkingQty' => $wQty
                                    , 'WorkingUnitId' => $wUnit
                                    , 'TotalQty' => $totQty
                                    , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            $recoveryRows = $this->bsf->isNullCheck($postData['recoveryRows'], 'number');
                            for ($r = 1; $r <= $recoveryRows; $r++) {
                                if ($this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number') > 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_hbrecoverytrans');
                                    $insert->Values(array('HBRegisterId' => $iHireBillId
                                    , 'RecoveryTypeId' => $this->bsf->isNullCheck($postData['recoveryId_' . $r], 'number')
                                    , 'AccountId' => $this->bsf->isNullCheck($postData['recoveryAccount_' . $r], 'number')
                                    , 'Sign' => $this->bsf->isNullCheck($postData['recoverySign_' . $r], 'string')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                        } else {

                            $inType = 'E';
                            $inName = 'WPM-HireBill-Edit';
                            $inDesc = 'HireBill-Edit';
                            $sRefNo = $postData['refNo'];

                            $update = $sql->update();
                            $update->table('WPM_HBRegister');
                            $update->set(array('HBDate' => date('Y-m-d', strtotime($postData['hbDate']))
                            , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                            , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                            , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                            , 'HBNo' => $this->bsf->isNullCheck($postData['hbNo'], 'string')
                            , 'HBCCNo' => $this->bsf->isNullCheck($postData['ccHbNo'], 'string')
                            , 'HBCompNo' => $this->bsf->isNullCheck($postData['compHbNo'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['totalAmount'], 'number')
                            , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                            , 'RecoveryAmount' => $this->bsf->isNullCheck($postData['recoveryServiceBill'], 'number')
                            , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                            , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')));
                            $update->where(array('HBRegisterId' => $iHireBillId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('WPM_HBTypeTrans')
                                ->where("HBRegisterId = $iHireBillId");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $hireRows = $this->bsf->isNullCheck($postData['hireRows'], 'number');
                            for ($i = 1; $i <= $hireRows; $i++) {
                                $hireId = $this->bsf->isNullCheck($postData['hireId_' . $i], 'number');

                                $wQty = 0;
                                $wUnit = 0;
                                $totQty = 0;
                                if ($hireTypeId == 1 || $hireTypeId == 2) {
                                    $wQty = $this->bsf->isNullCheck($postData['workingQty_' . $i], 'number');
                                    $wUnit = $this->bsf->isNullCheck($postData['workingUnitId_' . $i], 'number');
                                    $totQty = $this->bsf->isNullCheck($postData['totalQty_' . $i], 'number');
                                }

                                if ($hireId != 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_HBTypeTrans');
                                    $insert->Values(array('HBRegisterId' => $iHireBillId
                                    , 'ResourceId' => $hireId
                                    , 'UnitId' => $this->bsf->isNullCheck($postData['unitId_' . $i], 'number')
                                    , 'Qty' => $this->bsf->isNullCheck($postData['qty_' . $i], 'number')
                                    , 'WorkingQty' => $wQty
                                    , 'WorkingUnitId' => $wUnit
                                    , 'TotalQty' => $totQty
                                    , 'Rate' => $this->bsf->isNullCheck($postData['rate_' . $i], 'number')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $delete = $sql->delete();
                            $delete->from('WPM_hbrecoverytrans')
                                ->where("HBRegisterId = $iHireBillId");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $recoveryRows = $this->bsf->isNullCheck($postData['recoveryRows'], 'number');
                            for ($r = 1; $r <= $recoveryRows; $r++) {
                                if ($this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number') > 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_hbrecoverytrans');
                                    $insert->Values(array('HBRegisterId' => $iHireBillId
                                    , 'RecoveryTypeId' => $this->bsf->isNullCheck($postData['recoveryId_' . $r], 'number')
                                    , 'AccountId' => $this->bsf->isNullCheck($postData['recoveryAccount_' . $r], 'number')
                                    , 'Sign' => $this->bsf->isNullCheck($postData['recoverySign_' . $r], 'string')
                                    , 'Amount' => $this->bsf->isNullCheck($postData['recoveryAmount_' . $r], 'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }

                            $delete = $sql->delete();
                            $delete->from('WPM_HBQualTrans')
                                ->where("HBRegisterId = $iHireBillId");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $i = 1;
                            $qRowCount = $this->bsf->isNullCheck($postData['QualRRowId__' . $i], 'number');
                            for ($k = 1; $k <= $qRowCount; $k++) {
                                $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                                $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                                $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                                $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $iQualTypeId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                                $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

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
                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                    $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                    $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                    $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                    $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                                } else if ($iQualTypeId == 2) {

                                    $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                    $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                    $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                    $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                    $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                                } else {
                                    $dTaxablePer = 100;
                                    $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                    $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                    $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                    $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_HBQualTrans');
                                $insert->Values(array('HBRegisterId' => $iHireBillId,
                                    'QualifierId' => $iQualifierId, 'YesNo' => $iYesNo, 'Expression' => $sExpression, 'ExpPer' => $dExpPer, 'TaxablePer' => $dTaxablePer, 'TaxPer' => $dTaxPer,
                                    'Sign' => $sSign, 'SurCharge' => $dCessPer, 'EDCess' => $dEDPer, 'HEDCess' => $dHEdPer, 'KKCess' => $dKKCess, 'SBCess' => $dSBCess, 'NetPer' => $dNetPer, 'ExpressionAmt' => $dExpAmt, 'TaxableAmt' => $dTaxableAmt,
                                    'TaxAmt' => $dTaxAmt, 'SurChargeAmt' => $dCessAmt, 'EDCessAmt' => $dEDAmt, 'HEDCessAmt' => $dHEdAmt, 'KKCessAmt' => $dKKCessAmt, 'SBCessAmt' => $dSBCessAmt, 'NetAmt' => $dNetAmt, 'MixType' => 'S'));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iHireBillId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'bill-register'));
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->hireBillId != 0) {
                //Hire Bill Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_HBRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_HORegister'), 'a.HORegisterId = d.HORegisterId', array('HONo', 'HireTypeId'))
                    ->join(array('e' => 'WPM_HireTypeMaster'), 'e.HireTypeId = d.HireTypeId', array('HireTypeName'))
                    ->where('a.HBRegisterId = ' . $hireBillId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $hbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->hbRegister=$hbRegister;
                $hrId=$hbRegister['HORegisterId'];

                //Hire Trans
                $select = $sql->select();
                $select->from(array('a' => 'WPM_HBTypeTrans'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('data' => 'ResourceId', 'ResourceName', 'UnitId' => 'UnitId', 'WorkRate' => 'WorkRate', 'WorkUnitId'), $select::JOIN_INNER)
                    ->join(array('d' => 'Proj_projectResource'), 'a.ResourceId = d.ResourceId', array('eRate' => 'Rate', 'eQty' => 'Qty'), $select::JOIN_INNER)
                    ->join(array('f' => 'WPM_HBRegister'), 'f.HBRegisterId = a.HBRegisterId', array(), $select::JOIN_INNER)
                    ->join(array('g' => 'WF_OperationalCostCentre'), 'g.CostCentreId = f.CostCentreId and g.ProjectId=d.ProjectId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId = c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'e.UnitId = b.WorkUnitId', array('wUnitName' => 'UnitName'), $select::JOIN_LEFT)
                    ->where('a.HBRegisterId = ' . $hireBillId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->hbHireTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "WPM_HBQualTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                        'EDCessAmt', 'HEDCessAmt', 'KKCessAmt', 'SBCessAmt', 'NetAmt'));
                $select->where(array('a.MixType' => 'S', 'a.HBRegisterId' => $hireBillId));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                $select = $sql->select();
                $select->from(array('a' => 'WPM_ServiceRecoveryType'))
                    ->columns(array('RecoveryTypeId', 'RecoveryTypeName',
                        'AccountId' => new Expression("isnull(b.AccountId,0)"),
                        'Amount' => new Expression("isnull(b.Amount,0.000)"),
                        'Sign' => new Expression("isnull(b.Sign,'')"),
                    ))
                    ->join(array("b" => "WPM_hbrecoverytrans"), new Expression("a.RecoveryTypeId=b.RecoveryTypeId and b.HBRegisterId =$hireBillId"), array(), $select::JOIN_LEFT);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('A'=>'FA_AccountMaster'))
                    ->columns(array('data'=>'AccountId','value'=>'AccountName','TypeId'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryAccounts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('A'=>'WF_CompanyMailSetting'))
                    ->columns(array("AdvanceDeductWithoutPaid"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $AdvanceWithoutPaid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                if($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] ==1 ) {
                    $select1 = $sql->select();
                    $select1->from(array('A' => 'WPM_HOGeneralTerms'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.Value) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->join(array('B' => 'WF_TermsMaster'), 'A.TermsId=B.TermsId',array(),$select1::JOIN_INNER)
                        ->where(array("B.Title ='Advance' and B.TermType='W' and A.HORegisterId in ($hrId)"));

                }elseif($AdvanceWithoutPaid['AdvanceDeductWithoutPaid'] == 0 ){
                    $select1 = $sql->select();
                    $select1->from(array('A' => 'WPM_HORegister'))
                        ->columns(array('MobAdvance'=>new Expression('CAST(Sum(A.AdvanceAmt) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                            'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                        ->where(array("A.HORegisterId in ($hrId)"));

                }
                $select2 = $sql->select();
                $select2->from(array('A' => 'WPM_Hbrecoverytrans'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(sum(A.Amount) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                    ->where(array("HBRegisterId in (Select HBRegisterId from WPM_HBRegister Where HORegisterId in ($hrId))"));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array('A' => 'WPM_RetentionReleaseRegister'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(sum(A.AdvanceAmt) As Decimal(18,3))'),'CurAmt' => new Expression('CAST(0 As Decimal(18,3))')))
                    ->where(array("RRRegisterId in (Select RRRegisterId from WPM_RetentionReleaseRegister Where OrderType='H' and OrderId in ($hrId))"));
                $select3->combine($select2,'Union ALL');

                $select4 = $sql->select();
                $select4->from(array('A' => 'WPM_Hbrecoverytrans'))
                    ->columns(array('MobAdvance'=>new Expression('CAST(0 As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(0 As Decimal(18,3))'),
                        'Recovery' => new Expression('CAST(0 As Decimal(18,3))'),'CurAmt' => new Expression('CAST(A.Amount As Decimal(18,3))')))
                    ->where(array("HBRegisterId in (Select HBRegisterId from WPM_HBRegister Where HORegisterId in ($hrId)) "));
                $select4->combine($select3,'Union ALL');

                $select = $sql->select();
                $select->from(array('G' => $select4))
                    ->columns(array('MobAdvance'=>new Expression('CAST(Sum(G.MobAdvance) As Decimal(18,3))'),'CashAdvance' => new Expression('CAST(Sum(G.CashAdvance) As Decimal(18,3))'),
                        'TotalAdvance' => new Expression('CAST(Sum(G.MobAdvance+G.CashAdvance) As Decimal(18,3))'),'Recovery' => new Expression('CAST(Sum(G.Recovery) As Decimal(18,3))'),
                        'Balance' => new Expression('Case When Sum(G.MobAdvance+G.CashAdvance-G.Recovery) > 0 then Sum(G.MobAdvance+G.CashAdvance-G.Recovery) Else 0 end')
                    ,'CurAmt' => new Expression('CAST(Sum(G.CurAmt) As Decimal(18,3))')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->advRecv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            }
            if ($this->_view->hireBillId == 0) {
                $select = $sql->select();
                $select->from(array("a" => "Proj_QualifierTrans"))
                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                    ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer',
                        'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")));
                $select->where(array('a.QualType' => 'W'));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $sHtml = Qualifier::getQualifier($qualList);
                $this->_view->qualHtml = $sHtml;
                $sHtml = Qualifier::getQualifier($qualList, "R");
                $this->_view->qualRHtml = $sHtml;

                //Operational Cost Centre
                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Vendor Master
                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId', 'VendorName'))
                    ->where(array('Contract' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Working Unit
                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array('UnitId', 'UnitName'))
                    ->where(array('WorkUnit' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workingUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WPM_ServiceRecoveryType');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('A'=>'FA_AccountMaster'))
                    ->columns(array('data'=>'AccountId','value'=>'AccountName','TypeId'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recoveryAccounts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $aVNo = CommonHelper::getVoucherNo(408, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == true)
                    $this->_view->hbNo = $aVNo["voucherNo"];
                else
                    $this->_view->hbNo = "";

                $this->_view->hbTypeId = '408';
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getHireOrdersAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_HORegister')
                    ->columns(array('data' => 'HORegisterId', 'value' => 'HONo', 'typeId' => 'HireTypeId'))
                    ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getHoResourcesAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                //Service Master
                $sql = new Sql($dbAdapter);
                $subQuery = $sql->select();
                $subQuery->from('WPM_HOTypeTrans')
                    ->columns(array('ResourceId'))
                    ->where(array('HORegisterId' => $postData['hoRegId']));
                $HORegId = $postData['hoRegId'];
                //Project Resource
                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'UnitId' => 'UnitId'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName' => 'UnitName'), $select::JOIN_INNER)
                    ->join(array('c' => 'WPM_HOTypeTrans'), new Expression("a.ResourceId = c.ResourceId and HORegisterId=$HORegId"), array('WorkingUnitId', 'Rate', 'TotalQty'), $select::JOIN_INNER)
                    ->where->expression('a.ResourceId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function hireOrderRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Hire Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_HORegister'))
            ->columns(array("HORegisterId", "HONo", "HODate" => new Expression("FORMAT(a.HODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->hoRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteHoAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $hoRegId = $this->params()->fromPost('hoRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_HOTypeTrans')
                        ->where("HORegisterId = $hoRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_HORegister')
                        ->where("HORegisterId = $hoRegId");
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

    public function hireBillRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Hire Bill");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_HBRegister'))
            ->columns(array("HBRegisterId", "HBNo", "HBDate" => new Expression("FORMAT(a.HBDate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_HORegister'), 'a.HORegisterId = e.HORegisterId', array('HONo'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->hbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteHbAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $hbRegId = $this->params()->fromPost('hbRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_HBTypeTrans')
                        ->where("HBRegisterId = $hbRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_HBRegister')
                        ->where("HBRegisterId = $hbRegId");
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

    public function retentionReleaseAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Retention Release");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $retRelId = $this->params()->fromRoute('retRelId');
        $this->_view->retRelId = (isset($retRelId) && $retRelId != 0) ? $retRelId : 0;

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    $iRetRelId = $this->bsf->isNullCheck($postData['retRelId'], 'number');

                    if ($iRetRelId == 0) {
                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('CompanyId'))
                            ->where(array('a.CostCentreId' => $postData['costCentreId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $rraVNo = CommonHelper::getVoucherNo(413, date('Y-m-d', strtotime($postData['rrDate'])), 0, 0, $dbAdapter, "I");
                        if ($rraVNo["genType"] == true) {
                            $rrNo = $rraVNo["voucherNo"];
                        } else {
                            $rrNo = $postData['rrNo'];
                        }

                        $rrccaVNo = CommonHelper::getVoucherNo(413, date('Y-m-d', strtotime($postData['rrDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                        if ($rrccaVNo["genType"] == true) {
                            $rrCCNo = $rrccaVNo["voucherNo"];
                        } else {
                            $rrCCNo = $postData['ccRrNo'];
                        }

                        $rrcoaVNo = CommonHelper::getVoucherNo(413, date('Y-m-d', strtotime($postData['rrDate'])), $costcenter['CompanyId'], 0, $dbAdapter, "I");
                        if ($rrcoaVNo["genType"] == true) {
                            $rrCoNo = $rrcoaVNo["voucherNo"];
                        } else {
                            $rrCoNo = $postData['compRrNo'];
                        }
                        $sign = '';
                        if ($postData['roundingOffAmt'] < 0) {
                            $sign = '-';
                        } else {
                            $sign = '+';
                        }
                        $inType = 'N';
                        $inName = 'WPM-RetentionRelease-Add';
                        $inDesc = 'RetentionRelease-Add';
                        $sRefNo = $postData['refNo'];

                        $insert = $sql->insert();
                        $insert->into('WPM_RetentionReleaseRegister');
                        $insert->Values(array('OrderType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                        , 'OrderId' => $this->bsf->isNullCheck($postData['eOrderNo'], 'number')
                        , 'BillId' => $this->bsf->isNullCheck($postData['billId'], 'number')
                        , 'RRDate' => date('Y-m-d', strtotime($postData['rrDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RRNo' => $this->bsf->isNullCheck($rrNo, 'string')
                        , 'RRCCNo' => $this->bsf->isNullCheck($rrCCNo, 'string')
                        , 'RRCompNo' => $this->bsf->isNullCheck($rrCoNo, 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        , 'ReleaseAccountId' => $this->bsf->isNullCheck($postData['retentionAccountId'], 'number')
                        , 'AdvanceAccountId' => $this->bsf->isNullCheck($postData['advanceRecoverAccountId'], 'number')
                        , 'penaltyAccountId' => $this->bsf->isNullCheck($postData['penaltyAccountId'], 'number')
                        , 'roundingAccountId' => $this->bsf->isNullCheck($postData['roundingOffAccountId'], 'number')
                        , 'withHeldAccountId' => $this->bsf->isNullCheck($postData['withHeldAccountId'], 'number')
                        , 'AdvanceAmt' => $this->bsf->isNullCheck($postData['advanceRecoverAmt'], 'number')
                        , 'NetAmount' => $this->bsf->isNullCheck($postData['contractNetAmt'], 'number')
                        , 'PenaltyAmt' => $this->bsf->isNullCheck($postData['penaltyAmt'], 'number')
                        , 'ReleaseAmt' => $this->bsf->isNullCheck($postData['retentionAmt'], 'number')
                        , 'RoundingAmount' => $this->bsf->isNullCheck($postData['roundingOffAmt'], 'number')
                        , 'withHeldAmount' => $this->bsf->isNullCheck($postData['withHeldAmt'], 'number')
                        , 'RoundingSign' => $this->bsf->isNullCheck($sign, 'string')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iRetRelId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $retRowid = $this->bsf->isNullCheck($postData['retRowid'], 'number');
                        for ($i = 1; $i <= $retRowid; $i++) {

                            $insert = $sql->insert();
                            $insert->into('WPM_BillRetentionAdjustment');
                            $insert->Values(array(
                                'AdjBillRegisterId' => $this->bsf->isNullCheck($postData['billRegisterId_' . $i], 'number')
                            , 'ReleaseRegisterId' => $iRetRelId
                            , 'BillType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['currentamt_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $withheldRowid = $this->bsf->isNullCheck($postData['withheldRowid'], 'number');
                        for ($k = 1; $k <= $withheldRowid; $k++) {

                            $insert = $sql->insert();
                            $insert->into('WPM_BillWithHeldAdjustment');
                            $insert->Values(array(
                                'AdjBillRegisterId' => $this->bsf->isNullCheck($postData['billRegisterId_' . $k], 'number')
                            , 'ReleaseRegisterId' => $iRetRelId
                            , 'BillType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['currentamt_' . $k], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else {
                        $inType = 'E';
                        $inName = 'WPM-RetentionRelease-Edit';
                        $inDesc = 'RetentionRelease-Edit';
                        $sRefNo = $postData['refNo'];

                        $update = $sql->update();
                        $update->table('WPM_RetentionReleaseRegister');
                        $update->set(array('RRDate' => date('Y-m-d', strtotime($postData['rrDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RRNo' => $this->bsf->isNullCheck($postData['rrNo'], 'string')
                        , 'RRCCNo' => $this->bsf->isNullCheck($postData['ccRrNo'], 'string')
                        , 'RRCompNo' => $this->bsf->isNullCheck($postData['compRrNo'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $update->where(array('RRRegisterId' => $iRetRelId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                        $subQuery = $sql->select();
//                        $subQuery->from('WPM_RetentionReleaseTrans')
//                            ->columns(array('RRTransId'))
//                            ->where(array('RRRegisterId' => $iRetRelId));
//
//                        $delete = $sql->delete();
//                        $delete->from('WPM_RetentionReleaseBillTrans')
//                            ->where->expression('RRTransId IN ?', array($subQuery));
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $delete = $sql->delete();
//                        $delete->from('WPM_RetentionReleaseTrans')
//                            ->where("RRRegisterId = $iRetRelId");
//                        $statement = $sql->getSqlStringForSqlObject($delete);
//                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                        $desRows = $this->bsf->isNullCheck($postData['desRows'], 'number');
//                        for ($i = 1; $i <= $desRows; $i++) {
//                            $descId = $this->bsf->isNullCheck($postData['rreDescId_' . $i], 'number');
//
//                            $insert = $sql->insert();
//                            $insert->into('WPM_RetentionReleaseTrans');
//                            $insert->Values(array('RRRegisterId' => $iRetRelId
//                            , 'DescriptionId' => $descId
//                            , 'AccountHead' => $this->bsf->isNullCheck($postData['accountHead_' . $i], 'number')
//                            , 'Amount' => $this->bsf->isNullCheck($postData['amount_' . $i], 'number')));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            $iRRTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                            $billRows = $this->bsf->isNullCheck($postData['billRows_' . $i], 'number');
//                            for ($j = 1; $j <= $billRows; $j++) {
//                                $billId = $this->bsf->isNullCheck($postData['billId_' . $i . '_' . $j], 'number');
//                                //$billDate = date('Y-m-d', strtotime($postData['billDate_'.$i.'_'.$j]))
//                                $billDate = date('Y-m-d', strtotime('18-10-2016'));
//
//                                if ($billId != 0) {
//                                    $insert = $sql->insert();
//                                    $insert->into('WPM_RetentionReleaseBillTrans');
//                                    $insert->Values(array('RRTransId' => $iRRTransId
//                                    , 'BillId' => $billId
//                                    , 'BillDate' => $billDate
//                                    , 'PrevAmount' => $this->bsf->isNullCheck($postData['billPrev_' . $i . '_' . $j], 'number')
//                                    , 'BalanceAmount' => $this->bsf->isNullCheck($postData['billBal_' . $i . '_' . $j], 'number')
//                                    , 'CurrentAmount' => $this->bsf->isNullCheck($postData['billCur_' . $i . '_' . $j], 'number')));
//                                    $statement = $sql->getSqlStringForSqlObject($insert);
//                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                                }
//                            }
//                        }
                        $retRowid = $this->bsf->isNullCheck($postData['retRowid'], 'number');
                        for ($i = 1; $i <= $retRowid; $i++) {

                            $insert = $sql->insert();
                            $insert->into('WPM_BillRetentionAdjustment');
                            $insert->Values(array(
                                'AdjBillRegisterId' => $this->bsf->isNullCheck($postData['billRegisterId_' . $i], 'number')
                            , 'ReleaseRegisterId' => $iRetRelId
                            , 'BillType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['currentamt_' . $i], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $withheldRowid = $this->bsf->isNullCheck($postData['withheldRowid'], 'number');
                        for ($k = 1; $k <= $withheldRowid; $k++) {

                            $insert = $sql->insert();
                            $insert->into('WPM_BillWithHeldAdjustment');
                            $insert->Values(array(
                                'AdjBillRegisterId' => $this->bsf->isNullCheck($postData['billRegisterId_' . $k], 'number')
                            , 'ReleaseRegisterId' => $iRetRelId
                            , 'BillType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                            , 'Amount' => $this->bsf->isNullCheck($postData['currentamt_' . $k], 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iRetRelId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/retention-release', array('controller' => 'labourstrength', 'action' => 'retention-release-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->retRelId != 0) {
                //Retention Release Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_RetentionReleaseRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('e' => 'FA_AccountMaster'), 'a.AdvanceAccountId = e.AccountId', array('AdvanceAccountName' => 'AccountName'),$select::JOIN_LEFT)
                    ->join(array('f' => 'FA_AccountMaster'), 'a.PenaltyAccountId = f.AccountId', array('PenaltyAccountName' => 'AccountName'),$select::JOIN_LEFT)
                    ->join(array('g' => 'FA_AccountMaster'), 'a.ReleaseAccountId = g.AccountId', array('ReleaseAccountName' => 'AccountName'),$select::JOIN_LEFT)
                    ->join(array('h' => 'FA_AccountMaster'), 'a.RoundingAccountId = h.AccountId', array('RoundingAccountName' => 'AccountName'),$select::JOIN_LEFT)
                    ->join(array('i' => 'FA_AccountMaster'), 'a.WithHeldAccountId = i.AccountId', array('WithHeldAccountName' => 'AccountName'),$select::JOIN_LEFT)
                    ->where('a.RRRegisterId = ' . $retRelId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rrRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $costCentreId = $this->_view->rrRegister['CostCentreId'];
                $vendorId = $this->_view->rrRegister['VendorId'];
                $OrderId = $this->_view->rrRegister['OrderId'];

                $select = $sql->select();
                $select->from(array('a' => 'WPM_RetentionReleaseRegister'))
                    ->columns(array());
                if($this->_view->rrRegister['OrderType']= 'W') {
                    $select->join(array('b' => 'WPM_WORegister'), 'b.WORegisterId = a.OrderId', array('OrderNo' => 'WONo', 'OrderId' => 'WORegisterId'), $select::JOIN_LEFT);
                }else if($this->_view->rrRegister['OrderType']= 'H'){
                    $select->join(array('b' => 'WPM_HORegister'), 'b.HORegisterId = a.OrderId', array('OrderNo'=>'WONo','OrderId'=>'HORegisterId'),$select::JOIN_LEFT);
                }else if($this->_view->rrRegister['OrderType']= 'H'){
                    $select->join(array('b' => 'WPM_SORegister'), 'b.SORegisterId = a.OrderId', array('OrderNo'=>'WONo','OrderId'=>'SORegisterId'),$select::JOIN_LEFT);
                }
                $select->where('a.RRRegisterId = ' . $retRelId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->order = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($this->_view->rrRegister['OrderType']= 'W') {
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
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                //RR Trans
//                $select1 = $sql->select();
//                $select1->from(array('a'=>'WPM_WorkBillFormatTrans'))
//                    ->columns(array(
//                        'BillRegisterId' => new Expression("a.BillRegisterId"),
//                        'EDate' => new Expression("Convert(varchar(10),b.EDate,105)"),
//                        'RefNo' => new Expression("b.VNo"),
//                        'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
//                        'ReleaseAmount' => new Expression("Cast(0 as Decimal(18,3))"),
//                        'Balance' => new Expression("Cast(0 as Decimal(18,3))"),
//                        'CurAmount' => new Expression("Cast(0 as Decimal(18,3))"),
//                        'PrevAmount' => new Expression("Cast(0 as Decimal(18,3))"),
//                        'Sel' => new Expression("Convert(bit,0,1)"),
//                        'OB' => new Expression("Convert(bit,0,1)"),
//                    ))
//                    ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select1::JOIN_INNER)
//                    ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
//                    ->where(array("C.Sign='-' and A.Sign='-' and B.Approve='Y' and B.BillCertify=0 and B.CostCentreId = $costCentreId
//                    and B.VendorId = $vendorId and B.WORegisterId=1 and C.FormatTypeId=10 Group by A.BillRegisterId,B.Edate,B.VNo"));
//                $statement = $sql->getSqlStringForSqlObject($select1);
//                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                //Bill Trans
//                $subQuery = $sql->select();
//                $subQuery->from('WPM_RetentionReleaseTrans')
//                    ->columns(array('RRTransId'))
//                    ->where(array('RRRegisterId' => $retRelId));
//
//                $select = $sql->select();
//                $select->from(array('a' => 'WPM_RetentionReleaseBillTrans'))
//                    ->join(array('b' => 'Proj_WBSMaster'), 'a.BillId = b.WBSId', array('WBSName'), $select::JOIN_LEFT)
//                    ->where->expression('a.RRTransId IN ?', array($subQuery));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->rrBillTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => "CostCentreId", 'value' => "CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('data' => 'VendorId', 'value' => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //$this->_view->rreDescription = array('1' => 'Retention', '2' => 'Advance Recovery', '3' => 'Withheld Amount', '4' => 'Penalty, If Any', '5' => 'Rounding Off', '6' => 'Net Amount');
            //Account Head
            $select = $sql->select();
            $select->from('FA_AccountMaster')
                ->columns(array('data' => 'AccountId', 'value' => 'AccountName','TypeId'))
                ->where(array("LastLevel='Y'" ));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->accountMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //WBS Master
            $select = $sql->select();
            $select->from('Proj_WBSMaster')
                ->columns(array('data' => 'WBSId', 'value' => 'WBSName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->wbsMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $aVNo = CommonHelper::getVoucherNo(413, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->rrNo = $aVNo["voucherNo"];
            else
                $this->_view->rrNo = "";

            $this->_view->rrTypeId = '413';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getRenRelOrdersAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                if ($postData['type'] == 'W') {
                    $select = $sql->select();
                    $select->from('WPM_WORegister')
                        ->columns(array('data' => 'WORegisterId', 'value' => new Expression("WONo")))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($postData['type'] == 'H') {
                    $select = $sql->select();
                    $select->from('WPM_HORegister')
                        ->columns(array('data' => 'HORegisterId', 'value' => new Expression("HONo")))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($postData['type'] == 'S') {
                    $select = $sql->select();
                    $select->from('WPM_SORegister')
                        ->columns(array('data' => 'SORegisterId', 'value' => new Expression("SONo")))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }

        }
    }

    public function getRenRelBillAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);

                $select1 = $sql->select();
                $select1->from('WPM_WorkBillRegister')
                    ->columns(array('data' => 'BillRegisterId', 'value' => new Expression("BillNo + ' (WB)'")))
                    ->where(array('BillRegisterId' => $postData['ordNo']));

                $select2 = $sql->select();
                $select2->from('WPM_SBRegister')
                    ->columns(array('data' => 'SBRegisterId', 'value' => new Expression("SBNo + ' (SB)'")))
                    ->where(array('SBRegisterId' => $postData['ordNo']));
                $select2->combine($select1, 'Union ALL');

                $select3 = $sql->select();
                $select3->from('WPM_HBRegister')
                    ->columns(array('data' => 'HBRegisterId', 'value' => new Expression("HBNo + ' (HB)'")))
                    ->where(array('HBRegisterId' => $postData['ordNo']));
                $select3->combine($select2, 'Union ALL');

                $statement = $sql->getSqlStringForSqlObject($select3);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        }
    }

    public function retentionReleaseRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Retention Release Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $this->_view->orderType = '';

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    $this->_view->orderType = $postData['OrderType'];
                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }
            $select = $sql->select();
            $select->from(array('a' => 'WPM_RetentionReleaseRegister'))
                ->columns(array("RRRegisterId",
                    "RRNo",
                    "NetAmount" => new Expression("Cast(a.NetAmount as Decimal(18,3))"),
                    "RRDate" => new Expression("FORMAT(a.RRDate, 'dd-MM-yyyy')"),
                    "OrderNo" => new Expression("Case When A.OrderType='H' then f.HONo when A.OrderType='S' then g.SONo else E.WONo end "),
                    "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                    "OrderType" => new Expression("Case When A.OrderType='H' then 'HireOrder' when A.OrderType='S' then 'ServiceOrder' else 'WorkOrder' end")
                ))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                ->join(array('e' => 'WPM_WORegister'), new Expression("a.OrderId = e.WORegisterId and A.OrderType='W'"), array('WONo', 'WORegisterId'), $select::JOIN_LEFT)
                ->join(array('f' => 'WPM_HORegister'), new Expression("a.OrderId = f.HORegisterId and A.OrderType='H'"), array('HONo', 'HORegisterId'), $select::JOIN_LEFT)
                ->join(array('g' => 'WPM_SORegister'), new Expression("a.OrderId = g.SORegisterId and A.OrderType='S'"), array('SONo', 'SORegisterId'), $select::JOIN_LEFT);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->rrRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteRrAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $rrRegId = $this->params()->fromPost('rrRegId');
                    $response = $this->getResponse();

                    $subQuery = $sql->select();
                    $subQuery->from('WPM_RetentionReleaseTrans')
                        ->columns(array('RRTransId'))
                        ->where(array('RRRegisterId' => $rrRegId));

                    $delete = $sql->delete();
                    $delete->from('WPM_RetentionReleaseBillTrans')
                        ->where->expression('RRTransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_RetentionReleaseTrans')
                        ->where("RRRegisterId = $rrRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_RetentionReleaseRegister')
                        ->where("RRRegisterId = $rrRegId");
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

    public function advanceAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Advance Recommendation");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $advRecId = $this->params()->fromRoute('advRecId');
        $this->_view->advRecId = (isset($advRecId) && $advRecId != 0) ? $advRecId : 0;

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    $iAdvRecId = $this->bsf->isNullCheck($postData['advRecId'], 'number');

                    if ($iAdvRecId == 0) {
                        $select = $sql->select();
                        $select->from(array('a' => 'WF_OperationalCostCentre'))
                            ->columns(array('CompanyId'))
                            ->where(array('a.CostCentreId' => $postData['costCentreId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $araVNo = CommonHelper::getVoucherNo(415, date('Y-m-d', strtotime($postData['arDate'])), 0, 0, $dbAdapter, "I");
                        if ($araVNo["genType"] == true) {
                            $arNo = $araVNo["voucherNo"];
                        } else {
                            $arNo = $postData['arNo'];
                        }

                        $arccaVNo = CommonHelper::getVoucherNo(415, date('Y-m-d', strtotime($postData['arDate'])), 0, $postData['costCentreId'], $dbAdapter, "I");
                        if ($arccaVNo["genType"] == true) {
                            $arCCNo = $arccaVNo["voucherNo"];
                        } else {
                            $arCCNo = $postData['ccArNo'];
                        }

                        $arcoaVNo = CommonHelper::getVoucherNo(415, date('Y-m-d', strtotime($postData['arDate'])), $costcenter['CompanyId'], 0, $dbAdapter, "I");
                        if ($arcoaVNo["genType"] == true) {
                            $arCoNo = $arcoaVNo["voucherNo"];
                        } else {
                            $arCoNo = $postData['compArNo'];
                        }
                        $inType = 'N';
                        $inName = 'WPM-Advance-Recommendation-Add';
                        $inDesc = 'Advance-Recommendation-Add';
                        $sRefNo = $postData['refNo'];

                        $insert = $sql->insert();
                        $insert->into('WPM_AdvanceRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                        , 'TypeId' => $this->bsf->isNullCheck($postData['advAgainst'], 'number')
                        , 'Reference' => $this->bsf->isNullCheck($postData['reference'], 'number')
                        , 'ARDate' => date('Y-m-d', strtotime($postData['arDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'ARNo' => $this->bsf->isNullCheck($arNo, 'string')
                        , 'ARCCNo' => $this->bsf->isNullCheck($arCCNo, 'string')
                        , 'ARCompNo' => $this->bsf->isNullCheck($arCoNo, 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'OrderType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                        , 'PaidAmount' => $this->bsf->isNullCheck($postData['advance'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $billid = $dbAdapter->getDriver()->getLastGeneratedValue();

//                        $inType = 'N';
//                        $inName = 'WPM-WorkBill-Add';
//                        $inDesc = 'WorkBill-Add';
//                        $sRefNo = $postData['reference'];

                    } else {

                        //print_r($postData);die;
                        $inType = 'E';
                        $inName = 'WPM-Advance-Recommendation-Edit';
                        $inDesc = 'Advance-Recommendation-Edit';
                        $sRefNo = $postData['refNo'];

                        $delete = $sql->delete();
                        $delete->from('WPM_AdvanceRegister')
                            ->where("ARRegisterId = $iAdvRecId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*$update = $sql->update();
                        $update->table('WPM_AdvanceRegister');
                        $update->set(array('ARDate' => date('Y-m-d', strtotime($postData['arDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'ARNo' => $this->bsf->isNullCheck($postData['arNo'], 'string')
                        , 'ARCCNo' => $this->bsf->isNullCheck($postData['ccArNo'], 'string')
                        , 'ARCompNo' => $this->bsf->isNullCheck($postData['compArNo'], 'string')
                        , 'OrderType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'PaidAmount' => $this->bsf->isNullCheck($postData['advance'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $update->where(array('ARRegisterId' => $iAdvRecId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/


                        $insert = $sql->insert();
                        $insert->into('WPM_AdvanceRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['uCostCenterId'], 'number')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['uVendorId'], 'number')
                        , 'TypeId' => $this->bsf->isNullCheck($postData['advAgainst'], 'number')
                        , 'Reference' => $this->bsf->isNullCheck($postData['ref'], 'number')
                        , 'ARDate' => date('Y-m-d', strtotime($postData['arDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'ARNo' => $this->bsf->isNullCheck($postData['arNo'], 'string')
                        , 'ARCCNo' => $this->bsf->isNullCheck($postData['ccArNo'], 'string')
                        , 'ARCompNo' => $this->bsf->isNullCheck($postData['compArNo'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['ref'], 'string')
                        , 'OrderType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                        , 'PaidAmount' => $this->bsf->isNullCheck($postData['advance'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $billid = $dbAdapter->getDriver()->getLastGeneratedValue();
//                        $inType = 'E';
//                        $inName = 'WPM-WorkBill-Edit';
//                        $inDesc = 'WorkBill-Add';
//                        $sRefNo = $postData['ref'];
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iAdvRecId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/advance', array('controller' => 'labourstrength', 'action' => 'advance-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->advRecId != 0) {
                //Advance Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_AdvanceRegister'))
                    ->columns(array('ARRegisterId', 'CostCentreId', 'VendorId', 'TypeId', 'Reference', 'ARDate', 'RefDate', 'ARNo', 'ARCCNo',
                        'ARCompNo', 'RefNo', 'TotalAmount', 'PreviousPaidAmount', 'CurrentPaidAmount', 'Narration', 'DeleteFlag', 'Approve', 'PaidAmount', 'Type',
                        'OrderId', 'OrderType', "OrderNo" => new Expression("Case When A.OrderType='H' then e.HONo when A.OrderType='S' then f.SONo else d.WONo end ")))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'WPM_WORegister'), 'd.WORegisterId = a.Reference', array(), $select::JOIN_LEFT)
                    ->join(array('e' => 'WPM_HORegister'), 'e.HORegisterId = a.Reference', array(), $select::JOIN_LEFT)
                    ->join(array('f' => 'WPM_SORegister'), 'f.SORegisterId = a.Reference', array(), $select::JOIN_LEFT)
//                    ->join(array('d' => 'WPM_AdvanceTypeMaster'), 'a.TypeId = d.TypeId', array('TypeName'))
                    ->where('a.ARRegisterId = ' . $advRecId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            }

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => "CostCentreId", 'value' => "CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('data' => 'VendorId', 'value' => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array("id" => 'VendorId', "type" => new Expression("'V'"), "value" => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Labour Group Master
            $select = $sql->select();
            $select->from('WPM_LabourGroupMaster')
                //->columns(array("data" => 'LabourGroupId', "value" => 'LabourGroupName'));
                ->columns(array("id" => 'LabourGroupId', "type" => new Expression("'G'"), "value" =>new Expression("LabourGroupName +'(Internal)'") ));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->groupMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Advance Type Master
            $select = $sql->select();
            $select->from('WPM_AdvanceTypeMaster')
                ->columns(array('TypeId', 'TypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->aaTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $aVNo = CommonHelper::getVoucherNo(415, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->arNo = $aVNo["voucherNo"];
            else
                $this->_view->arNo = "";

            $this->_view->arTypeId = '415';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getReferenceAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $type = $postData['type'];

                if ($type == 'W') {
                    $select = $sql->select();
                    $select->from('WPM_WORegister')
                        ->columns(array('data' => 'WORegisterId', 'value' => 'WONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($type == 'H') {
                    $select = $sql->select();
                    $select->from('WPM_HORegister')
                        ->columns(array('data' => 'HORegisterId', 'value' => 'HONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($type == 'S') {
                    $select = $sql->select();
                    $select->from('WPM_SORegister')
                        ->columns(array('data' => 'SORegisterId', 'value' => 'SONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getRetentionGridAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $sql = new Sql($dbAdapter);
                $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('VendorId'), 'number');
                $costCentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                $OrderId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'), 'number');
                $orderType = $this->bsf->isNullCheck($this->params()->fromPost('orderType'), 'number');
                if($orderType == 'W') {
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
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }else if($orderType == 'S'){

                }
            }

            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function advanceRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Advance Recommendation");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_AdvanceRegister'))
            ->columns(array("ARRegisterId",
                "ARNo",
                "PaidAmount",
                "ARDate" => new Expression("FORMAT(a.ARDate, 'dd-MM-yyyy')"),
                "OrderNo" => new Expression("Case When A.OrderType='H' then f.HONo when A.OrderType='S' then g.SONo else E.WONo end "),
                "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                "OrderType" => new Expression("Case When A.OrderType='H' then 'HireOrder' when A.OrderType='S' then 'ServiceOrder' else 'WorkOrder' end")
            ))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_WORegister'), new Expression("a.Reference = e.WORegisterId and A.OrderType='W'"), array(), $select::JOIN_LEFT)
            ->join(array('f' => 'WPM_HORegister'), new Expression("a.Reference = f.HORegisterId and A.OrderType='H'"), array(), $select::JOIN_LEFT)
            ->join(array('g' => 'WPM_SORegister'), new Expression("a.Reference = g.SORegisterId and A.OrderType='S'"), array(), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function bindDetailsAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $sql = new Sql($dbAdapter);
                $response = $this->getResponse();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');

                $arrNew = array();
                switch ($Type) {
                    case 'woAmount':
                        $orderId = $this->bsf->isNullCheck($this->params()->fromPost('orderId'), 'number');
                        $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('vendorId'), 'number');
                        $costCentreId = $this->bsf->isNullCheck($this->params()->fromPost('costCentreId'), 'number');
                        $reference = $this->bsf->isNullCheck($this->params()->fromPost('reference'), 'number');
//                        $order = $this->bsf->isNullCheck($this->params()->fromPost('order'), 'string');

                        $data = array();

                        $select3 = $sql->select();
                        $select3->from(array("A" => 'WPM_WORegister'))
                            ->columns(array('Amount' => new Expression("CAST(ISNULL(SUM(A.Amount),0) As Decimal(18,3))")))
                            ->where(array('Approve' => "Y", 'LiveWO' => 1, 'WORegisterId' => $orderId));
                        $statement = $sql->getSqlStringForSqlObject($select3);
                        $Amt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_WORegister'))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->columns(array("RefDate" => new Expression("WODate"), "RefNo" => new Expression("WONo"), "Amount"))
                            ->where(array('Approve' => "Y", 'LiveWO' => 1, 'a.WORegisterId' => $orderId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $WOTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data['WOTrans'] = $WOTrans;

                        $arrNew['Amount'] = 0;
                        $arrNew['BilledAmount'] = 0;
                        $arrNew['mobAdvance'] = 0;
                        $arrNew['PaidAmount'] = 0;
                        $arrNew['TotalAdvance'] = 0;
                        $arrNew['deductAmount'] = 0;
                        $arrNew['balAmount'] = 0;
                        $arrNew['eligible'] = 0;

                        if (!empty($Amt)) $arrNew['Amount'] = floatval($Amt['Amount']);

                        $select3 = $sql->select();
                        $select3->from(array("a" => 'WPM_WorkBillFormatTrans'))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select3::JOIN_INNER)
                            ->columns(array('BilledAmount' => new Expression("CAST(ISNULL(SUM(A.Amount),0) As Decimal(18,3))")))
                            ->where(array('b.CostCentreId' => $costCentreId, 'a.BillFormatId' => 1, 'b.vendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($select3);
                        $bAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_WorkBillFormatTrans'))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId= b.BillRegisterId', array('RefDate' => new Expression("b.EDate"),'RefDate'=>"BillDate",'RefNo'=>"BillNo", 'VNo' => new Expression("b.VNo")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'b.CostCentreId = c.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->columns(array("Amount"))
                            ->where(array('b.CostCentreId' => $costCentreId, 'a.BillFormatId' => 1, 'b.vendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $BillTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data['BillTrans'] = $BillTrans;

                        if (!empty($bAmt)) $arrNew['BilledAmount'] = floatval($bAmt['BilledAmount']);

                        $select3 = $sql->select();
                        $select3->from(array("A" => 'WPM_AdvanceRegister'))
                            ->columns(array('PaidAmount' => new Expression("CAST(ISNULL(SUM(A.PaidAmount),0) As Decimal(18,3))")))
                            ->where(array('CostCentreId' => $costCentreId, 'VendorId' => $vendorId, 'Reference' => $reference));
                        $statement1 = $sql->getSqlStringForSqlObject($select3);
                        $pAmt = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_AdvanceRegister'))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->columns(array('RefDate' => new Expression("a.ARDate"), 'RefNo' => new Expression("a.ARNo"), 'Amount' => new Expression("a.PaidAmount")))
                            ->where(array('a.CostCentreId' => $costCentreId, 'a.VendorId' => $vendorId, 'a.Reference' => $reference, 'a.PaidAmount<>0'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $advTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data['AdvTrans'] = $advTrans;

                        if (!empty($pAmt)) $arrNew['PaidAmount'] = floatval($pAmt['PaidAmount']);
                        $arrNew['TotalAdvance'] = floatval($arrNew['mobAdvance']) + floatval($arrNew['PaidAmount']);

                        $select3 = $sql->select();
                        $select3->from(array('a' => 'WPM_WorkBillFormatTrans'))
                            ->columns(array('deductAmount' => new Expression("CAST(ISNULL(SUM(a.Amount),0) As Decimal(18,3))")))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select3::JOIN_INNER)
                            ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select3::JOIN_INNER)
                            ->where(array('b.CostCentreId' => $costCentreId, 'c.FormatTypeId' => 9, 'b.vendorId' => $vendorId, 'c.Sign' => '-'));
                        $statement = $sql->getSqlStringForSqlObject($select3);
                        $dAMt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_WorkBillFormatTrans'))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId= b.BillRegisterId', array('RefDate' => new Expression("b.EDate"), 'VNo' => new Expression("b.VNo")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'), 'b.CostCentreId = d.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->columns(array('Amount'))
                            ->where(array('b.CostCentreId' => $costCentreId, 'c.FormatTypeId' => 9, 'b.vendorId' => $vendorId, 'c.Sign' => '-'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $advDTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data['AdvDTrans'] = $advDTrans;


                        if (!empty($dAMt)) $arrNew['deductAmount'] = floatval($dAMt['deductAmount']);
                        $arrNew['balAmount'] = floatval($arrNew['TotalAdvance']) - floatval($arrNew['deductAmount']);
                        if ($arrNew['balAmount'] < 0) $arrNew['balAmount'] = 0;
                        $calAmt = floatval($arrNew['BilledAmount']) + floatval($arrNew['balAmount']);

                        $arrNew['eligible'] = $arrNew['Amount'] - $calAmt;
                        if ($arrNew['eligible'] < 0) $arrNew['eligible'] = 0;

                        $data['Details'] = $arrNew;

                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($data));
                        return $response;
                        break;
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }

        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function deleteArAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $arRegId = $this->params()->fromPost('arRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_AdvanceRegister')
                        ->where("ARRegisterId = $arRegId");
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

    public function securityDepositAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Security Deposit");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $secDepId = $this->params()->fromRoute('secDepId');
        $sdType = $this->params()->fromRoute('type');
        $this->_view->secDepId = (isset($secDepId) && $secDepId != 0) ? $secDepId : 0;
        $this->_view->sdType = (isset($sdType) && $sdType != '') ? $sdType : '';

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();

                    $iSecDepId = $this->bsf->isNullCheck($postData['secDepId'], 'number');
                    $status = $this->bsf->isNullCheck($postData['sdType'], 'string');

                    if ($iSecDepId == 0 && $status == 'N') {
                        $inType = 'N';
                        $inName = 'WPM-SecurityDeposit-Add';
                        $inDesc = 'SecurityDeposit-Add';
                        $sRefNo = $postData['refNo'];

                        $insert = $sql->insert();
                        $insert->into('WPM_SecurityDepositRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postData['costCentreId'], 'number')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['vendorId'], 'number')
                        , 'OrderId' => $this->bsf->isNullCheck($postData['orderNo'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['amount'], 'number')
                        , 'SDAmount' => $this->bsf->isNullCheck($postData['sdAmt'], 'number')
                        , 'PayModeId' => $this->bsf->isNullCheck($postData['payMode'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postData['sdDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($postData['sdNo'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'OrderType' => $this->bsf->isNullCheck($postData['orderType'], 'string')
                        , 'BankName' => $this->bsf->isNullCheck($postData['bankName'], 'string')
                        , 'ValidUpto' => date('Y-m-d', strtotime($postData['validUpto']))
                        , 'Status' => $this->bsf->isNullCheck($status, 'string')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if ($iSecDepId != 0 && $status == 'N') {
                        $inType = 'E';
                        $inName = 'WPM-SecurityDeposit-Edit';
                        $inDesc = 'SecurityDeposit-Edit';
                        $sRefNo = $postData['refNo'];

                        $update = $sql->update();
                        $update->table('WPM_SecurityDepositRegister');
                        $update->set(array('Amount' => $this->bsf->isNullCheck($postData['amount'], 'number')
                        , 'SDAmount' => $this->bsf->isNullCheck($postData['sdAmt'], 'number')
                        , 'PayModeId' => $this->bsf->isNullCheck($postData['payMode'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postData['sdDate']))
                        , 'RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($postData['sdNo'], 'string')
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'FromDate' => date('Y-m-d', strtotime($postData['fromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'BankName' => $this->bsf->isNullCheck($postData['bankName'], 'string')
                        , 'ValidUpto' => date('Y-m-d', strtotime($postData['validUpto']))
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')));
                        $update->where(array('SDRegisterId' => $iSecDepId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if ($iSecDepId != 0 && $status == 'E') {
                        $update = $sql->update();
                        $update->table('WPM_SecurityDepositRegister');
                        $update->set(array('RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'ToDate' => date('Y-m-d', strtotime($postData['toDate']))
                        , 'ValidUpto' => date('Y-m-d', strtotime($postData['validUpto']))
                        , 'Status' => $this->bsf->isNullCheck($status, 'string')));
                        $update->where(array('SDRegisterId' => $iSecDepId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else if ($iSecDepId != 0 && $status == 'R') {
                        $update = $sql->update();
                        $update->table('WPM_SecurityDepositRegister');
                        $update->set(array('RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Status' => $this->bsf->isNullCheck($status, 'string')));
                        $update->where(array('SDRegisterId' => $iSecDepId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $iSecDepId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/advance', array('controller' => 'labourstrength', 'action' => 'security-deposit-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            if ($this->_view->secDepId != 0) {
                //Security Deposit Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_SecurityDepositRegister'))
                    ->where('a.SDRegisterId = ' . $secDepId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $sdOrder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($sdOrder['OrderType'] == 'W') {
                    $select = $sql->select();
                    $select->from('WPM_WORegister')
                        ->columns(array('data' => 'WORegisterId', 'value' => 'WONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $sdOrder['CostCentreId'], 'VendorId' => $sdOrder['VendorId']));
                } else if ($sdOrder['OrderType'] == 'H') {
                    $select = $sql->select();
                    $select->from('WPM_HORegister')
                        ->columns(array('data' => 'HORegisterId', 'value' => 'HONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $sdOrder['CostCentreId'], 'VendorId' => $sdOrder['VendorId']));
                } else if ($sdOrder['OrderType'] == 'S') {
                    $select = $sql->select();
                    $select->from('WPM_SORegister')
                        ->columns(array('data' => 'SORegisterId', 'value' => 'SONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $sdOrder['CostCentreId'], 'VendorId' => $sdOrder['VendorId']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_orderNo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_SecurityDepositRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'))
                    ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'))
                    ->join(array('d' => 'Proj_PaymentModeMaster'), 'a.PayModeId = d.TransId', array('PaymentMode'))
                    ->where('a.SDRegisterId = ' . $secDepId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sdRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }

            //Operational Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => "CostCentreId", 'value' => "CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('data' => 'VendorId', 'value' => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('WPM_SecurityDepositRegister')
                ->columns(array("SDRegisterId", "RefNo"))
                ->where(array('Status' => 'N'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->depositSelect = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Payment Mode Master
            $select = $sql->select();
            $select->from('Proj_PaymentModeMaster')
                ->columns(array('TransId', 'PaymentMode'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->paymentMode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $aVNo = CommonHelper::getVoucherNo(414, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->sdNo = $aVNo["voucherNo"];
            else
                $this->_view->sdNo = "";

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function getSdOrdersAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $type = $postData['orderType'];

                if ($type == 'W') {
                    $select = $sql->select();
                    $select->from('WPM_WORegister')
                        ->columns(array('data' => 'WORegisterId', 'value' => 'WONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($type == 'H') {
                    $select = $sql->select();
                    $select->from('WPM_HORegister')
                        ->columns(array('data' => 'HORegisterId', 'value' => 'HONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                } else if ($type == 'S') {
                    $select = $sql->select();
                    $select->from('WPM_SORegister')
                        ->columns(array('data' => 'SORegisterId', 'value' => 'SONo', 'Amount' => 'Amount'))
                        ->where(array('CostCentreId' => $postData['ccId'], 'VendorId' => $postData['vId']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getSdIdAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('WPM_SecurityDepositRegister')
                    ->columns(array('Id' => 'SDRegisterId'))
                    ->where(array('SDRegisterId' => $postData['ordId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function securityDepositRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Security Deposit");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_SecurityDepositRegister'))
            ->columns(array("SDRegisterId",
                "TransNo",
                "Amount"=>'SDAmount',
                "TransDate" => new Expression("FORMAT(a.TransDate, 'dd-MM-yyyy')"),
                "OrderNo" => new Expression("Case When A.OrderType='H' then f.HONo when A.OrderType='S' then g.SONo else E.WONo end "),
                "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                "OrderType" => new Expression("Case When A.OrderType='H' then 'HireOrder' when A.OrderType='S' then 'ServiceOrder' else 'WorkOrder' end")
            ))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'Proj_PaymentModeMaster'), 'a.PayModeId = d.TransId', array('PaymentMode'), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_WORegister'), new Expression("a.OrderId = e.WORegisterId and A.OrderType='W'"), array('WONo', 'WORegisterId'), $select::JOIN_LEFT)
            ->join(array('f' => 'WPM_HORegister'), new Expression("a.OrderId = f.HORegisterId and A.OrderType='H'"), array('HONo', 'HORegisterId'), $select::JOIN_LEFT)
            ->join(array('g' => 'WPM_SORegister'), new Expression("a.OrderId = g.SORegisterId and A.OrderType='S'"), array('SONo', 'SORegisterId'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->sdRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteSecDepAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $sdRegId = $this->params()->fromPost('sdRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_SecurityDepositRegister')
                        ->where("SDRegisterId = $sdRegId");
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

    public function getCompCcNoAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $response = $this->getResponse();
                $postData = $request->getPost();

                $typeId = $postData['typeId'];
                $ccId = $postData['ccId'];

                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CompanyId"))
                    ->where('CostCentreId = ' . $ccId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $opCostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $compId = $opCostCentre['CompanyId'];

                $ccaVNo = CommonHelper::getVoucherNo($typeId, date('Y/m/d'), 0, $ccId, $dbAdapter, "");
                if ($ccaVNo["genType"] == true)
                    $ccNo = $ccaVNo["voucherNo"];
                else
                    $ccNo = "";

                $coaVNo = CommonHelper::getVoucherNo($typeId, date('Y/m/d'), $compId, 0, $dbAdapter, "");
                if ($coaVNo["genType"] == true)
                    $compNo = $coaVNo["voucherNo"];
                else
                    $compNo = "";

                $result = $ccNo . '###' . $compNo;
                $response->setContent($result);
                return $response;
            }
        }
    }

    public function getStateCountryAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'WF_CityMaster'))
                    ->join(array('b' => 'WF_StateMaster'), 'a.StateId = b.StateId', array('StateName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_CountryMaster'), 'a.CountryId = c.CountryId', array('CountryName'), $select::JOIN_LEFT)
                    ->columns(array('StateId', 'CountryId'))
                    ->where(array('CityId' => $postData['cityId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function labourEntryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $labourId = $this->bsf->isNullCheck($this->params()->fromRoute('labourId'), 'number');
        $labourRegId = $this->bsf->isNullCheck($this->params()->fromRoute('labourRegId'), 'number');

        $this->_view->labourRegId = (isset($labourRegId) && $labourRegId != 0) ? $labourRegId : 0;
        $this->_view->labourId = (isset($labourId) && $labourId != 0) ? $labourId : 0;
        $this->_view->mode = (isset($labourRegId) && $labourRegId != 0) ? 'e' : 'a';

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode());
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $files = $request->getFiles();
                    $postData = $request->getPost();
                    $iLabourId = $this->bsf->isNullCheck($postData['labourId'], 'number');
                    $ilabourRegId = $this->bsf->isNullCheck($postData['labourRegId'], 'number');

                    $iMode = $this->bsf->isNullCheck($postData['mode'], 'number');

                    if ($ilabourRegId == 0) {
                        $refVNo = CommonHelper::getVoucherNo(416, date('Y-m-d', strtotime($postData['refDate'])), 0, 0, $dbAdapter, "I");
                        if ($refVNo["genType"] == true) {
                            $refNo = $refVNo["voucherNo"];
                        } else {
                            $refNo = $postData['refNo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_LabourRegister');
                        $insert->Values(array('RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RefNo' => $this->bsf->isNullCheck($refNo, 'string')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['remarks'], 'string')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCentreId'], 'number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ilabourRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $inType = 'N';
                        $inName = 'WPM-Labour-Master-Add';
                        $inDesc = 'Labour-Add';
                        $sRefNo = $refNo;
                    } else {
                        $update = $sql->update();
                        $update->table('WPM_LabourRegister');
                        $update->set(array('RefDate' => date('Y-m-d', strtotime($postData['refDate']))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['refNo'], 'string')
                        , 'Remarks' => $this->bsf->isNullCheck($postData['remarks'], 'string')));
                        $update->where(array('LabourRegisterId' => $ilabourRegId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $inType = 'E';
                        $inName = 'WPM-Labour-Master-Edit';
                        $inDesc = 'Labour-Edit';
                        $sRefNo = $postData['refNo'];
                    }

                    if ($iMode == 'e') {
                        $delete = $sql->delete();
                        $delete->from('WPM_LabourTrans')
                            ->where("LabourRegisterId = $ilabourRegId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_LabourTrans")
                            ->columns(array("LabourTransId"));
                        $subQuery->where(array('LabourRegisterId' => $labourRegId));

                        $delete = $sql->delete();
                        $delete->from('WPM_LabourDocumentTrans')
                            ->where->expression('LabourTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $rows = $this->bsf->isNullCheck($postData['rows'], 'number');
                    for ($i = 1; $i <= $rows; $i++) {
                        $labName = $this->bsf->isNullCheck($postData['labourName_' . $i], 'string');
                        $groupId = $this->bsf->isNullCheck($postData['groupId_' . $i], 'number');
                        $vendorId = $this->bsf->isNullCheck($postData['vendorId_' . $i], 'number');
                        $typeId = $this->bsf->isNullCheck($postData['typeId_' . $i], 'number');
                        $Address = $this->bsf->isNullCheck($postData['address_' . $i], 'string');
                        $cityname = $this->bsf->isNullCheck($postData['city_' . $i], 'string');
                        $statename = $this->bsf->isNullCheck($postData['state_' . $i], 'string');
                        $countryname = $this->bsf->isNullCheck($postData['country_' . $i], 'string');
                        $pincode = $this->bsf->isNullCheck($postData['pinCode_' . $i], 'number');

                        // country check

                        $select = $sql->select();
                        $select->from('WF_CountryMaster')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array('CountryName' => $countryname));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $chkResultcountry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        // state check

                        $select = $sql->select();
                        $select->from('WF_StateMaster')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array('StateName' => $statename));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $chkResultstate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        //city check

                        $select = $sql->select();
                        $select->from('WF_CityMaster')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array('CityName' => $cityname));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $chkResultcity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if ($chkResultcountry['Count'] == 0) {
                            $insert = $sql->insert();
                            $insert->into('WF_CountryMaster');
                            $insert->Values(array('CountryName' => $countryname));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        } else {
                            $select = $sql->select();
                            $select->from('WF_CountryMaster')
                                ->where(array('CountryName' => $countryname));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $chkResultcountry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $countryId = $chkResultcountry['CountryId'];
                        }
                        if ($chkResultstate['Count'] == 0) {
                            $insert = $sql->insert();
                            $insert->into('WF_StateMaster');
                            $insert->Values(array('StateName' => $statename));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        } else {

                            $select = $sql->select();
                            $select->from('WF_StateMaster')
                                ->where(array('StateName' => $statename));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $state = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $stateId = $state['StateID'];
                        }
                        if ($chkResultcity['Count'] == 0) {
                            $insert = $sql->insert();
                            $insert->into('WF_CityMaster');
                            $insert->Values(array('CityName' => $cityname, 'StateId' => $stateId, 'CountryId' => $countryId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        } else {

                            $select = $sql->select();
                            $select->from('WF_CityMaster')
                                ->where(array('CityName' => $cityname));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $city = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $cityId = $city['CityId'];

                        }

                        if ($groupId == 0 && $vendorId == 0) {
                            $groupName = $this->bsf->isNullCheck($postData['groupName_' . $i], 'string');

                            $select = $sql->select();
                            $select->from('WPM_LabourGroupMaster')
                                ->columns(array("Count" => new Expression("Count(*)")))
                                ->where(array('LabourGroupName' => $groupName));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $chkResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if ($chkResult['Count'] == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_LabourGroupMaster');
                                $insert->Values(array('LabourGroupName' => $groupName));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $groupId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            } else {
                                $select = $sql->select();
                                $select->from('WPM_LabourGroupMaster')
                                    ->where(array('LabourGroupName' => $groupName));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $chkResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $groupId = $chkResult['LabourGroupId'];
                            }
                        }
                        $profileUrl = $postData['imageurl_' . $i];
                        if ($profileUrl == '') {
                            $purl = '';
                            $pCurl = '';
                            if ($files['my_file_' . $i]['name']) {
                                $purl = "public/uploads/wpm/wpmlabourprofile/";
                                $pfilename = $this->bsf->uploadFile($purl, $files['my_file_' . $i]);
                                if ($pfilename) {
                                    // update valid files only
                                    $profileUrl = 'uploads/wpm/wpmlabourprofile/' . $pfilename;
                                }
                            }
                        }

                        if ($labName != '' && ($groupId != 0 || $vendorId != 0) && $typeId != 0) {

                            $insert = $sql->insert();
                            $insert->into('WPM_LabourTrans');
                            $insert->Values(array('LabourRegisterId' => $ilabourRegId
                            , 'LabourName' => $labName
                            , 'LabourGroupId' => $groupId
                            , 'VendorId' => $vendorId
                            , 'LabourTypeId' => $typeId
                            , 'Code' => $this->bsf->isNullCheck($postData['idNo_' . $i], 'string')
                            , 'Address' => $this->bsf->isNullCheck($postData['address_' . $i], 'string')
                            , 'CityId' => $cityId
                            , 'PinCode' => $pincode
                            , 'Mobile' => $this->bsf->isNullCheck($postData['mobile_' . $i], 'string')
                            , 'Email' => $this->bsf->isNullCheck($postData['email_' . $i], 'string')
                            , 'PFNo' => $this->bsf->isNullCheck($postData['pfNo_' . $i], 'string')
                            , 'ESINo' => $this->bsf->isNullCheck($postData['esiNo_' . $i], 'string')
                            , 'AdharNo' => $this->bsf->isNullCheck($postData['adharNo_' . $i], 'string')
                            , 'LabourId' => $this->bsf->isNullCheck($postData['labourId_' . $i], 'number')
                            , 'LabourProfile' => $profileUrl
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $LabourtransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                        }
                        $DocId = $this->bsf->isNullCheck($postData['tenderdocumentrowid_' . $i], 'number');
                        for ($j = 1; $j <= $DocId; $j++) {
                            $sUrl = $postData['document_' . $i . '_url_' . $j];
                            if ($sUrl == '' && isset($files['tenderDocFile_' . $i . '_ttol_' . $j]['name'])) {
                                $url = '';
                                //echo $files['tenderDocFile_'.$i.'_ttol_'.$j]['name'];
                                if ($files['tenderDocFile_' . $i . '_ttol_' . $j]['name']) {
                                    $url = "public/uploads/wpm/wpmdocfile/";
                                    $filename = $this->bsf->uploadFile($url, $files['tenderDocFile_' . $i . '_ttol_' . $j]);
                                    if ($filename) {
                                        $sUrl = 'uploads/wpm/wpmdocfile/' . $filename;
                                    }
                                }
                            }
                            if ($postData['doctype_' . $i . '_auto_' . $j] != 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_LabourDocumentTrans');
                                $insert->Values(array('LabourTransId' => $LabourtransId
                                , 'DocumentType' => $this->bsf->isNullCheck($postData['doctype_' . $i . '_auto_' . $j], 'number')
                                , 'DocumentName' => $this->bsf->isNullCheck($postData['document_' . $i . '_desc_' . $j], 'string')
                                , 'DocumentUrl' => $sUrl
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $ilabourRegId, 0, 0, 'WPM', $sRefNo, $this->auth->getIdentity()->UserId, 0, 0);
                    $this->redirect()->toRoute('wpm/labour-entry', array('controller' => 'labourstrength', 'action' => 'labour-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            $iCostCentreId = 0;
            $sCostCentreName = "";

            if ($labourRegId != 0) {
                //Labour Register
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->where('a.LabourRegisterId = ' . $labourRegId);
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
                    ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName' => new Expression("Case When                          a.VendorId !=0 then h.VendorName else b.LabourGroupName  end")), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'a.LabourTypeId = c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId = d.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_StateMaster'), 'd.StateId = e.StateId', array('StateName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'WF_CountryMaster'), 'd.CountryId = f.CountryId', array('CountryName'), $select::JOIN_LEFT)
                    ->join(array('h' => 'Vendor_Master'), 'a.VendorId = h.VendorId', array(), $select::JOIN_LEFT)
                    ->where(array('a.LabourRegisterId' => $labourRegId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                // labour documents

                $subQuery = $sql->select();
                $subQuery->from("WPM_LabourTrans")
                    ->columns(array("LabourTransId"));
                $subQuery->where(array('LabourRegisterId' => $labourRegId));

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourDocumentTrans'))
                    ->join(array('b' => 'WPM_LabourDocumentType'), 'a.DocumentType = b.DocumentId', array('Doc' => new Expression("b.DocumentType"), 'DocumentId'))
                    ->where->expression('LabourTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->documents = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            } else if ($labourId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourMaster'))
                    ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array('LabourGroupName' => new Expression("Case When                          a.VendorId !=0 then h.VendorName else b.LabourGroupName  end")), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_Resource'), 'a.LabourTypeId = c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_CityMaster'), 'a.CityId = d.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_StateMaster'), 'd.StateId = e.StateId', array('StateName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'WF_CountryMaster'), 'd.CountryId = f.CountryId', array('CountryName'), $select::JOIN_LEFT)
                    ->join(array('h' => 'Vendor_Master'), 'a.VendorId = h.VendorId', array(), $select::JOIN_LEFT)
                    ->where(array('a.LabourId' => $labourId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'WPM_LabourMaster'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->where(array('a.LabourId' => $labourId));
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
                    ->join(array('b' => 'WPM_LabourDocumentType'), 'a.DocumentType = b.DocumentId', array('Doc' => new Expression("b.DocumentType"), 'DocumentId'))
                    ->where(array('a.LabourId' => $labourId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->documents = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->costCentreId = $iCostCentreId;
            $this->_view->costCentreName = $sCostCentreName;

            //Vendor Master
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array("id" => 'VendorId', "type" => new Expression("'V'"), "value" => 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vendorMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Labour Group Master
            $select = $sql->select();
            $select->from('WPM_LabourGroupMaster')
                //->columns(array("data" => 'LabourGroupId', "value" => 'LabourGroupName'));
                ->columns(array("id" => 'LabourGroupId', "type" => new Expression("'G'"), "value" => 'LabourGroupName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->groupMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Type Master
            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array("data" => 'ResourceId', "value" => 'ResourceName'))
                ->where(array('TypeId' => 1));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->typeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Cost Centre

            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => 'CostCentreId', 'value' => 'CostCentreName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->inarr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //City Master
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array("data" => 'CityId', "value" => 'CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->cityMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //labour code
            $select = $sql->select();
            $select->from('WPM_LabourTrans')
                ->columns(array("Code"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->labourcode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //
            //Doc Type
            $select = $sql->select();
            $select->from('WPM_LabourDocumentType')
                ->columns(array("data" => 'DocumentId', "value" => 'DocumentType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->doctype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //labour code set up

            $resCode = "";

            $select->from('WPM_LabourCodeSetup')
                ->columns(array('GenType', 'Prefix', 'Suffix', 'Width', 'Separator', 'MaxNo'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


            $aVNo = CommonHelper::getVoucherNo(416, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->refNo = $aVNo["voucherNo"];
            else
                $this->_view->refNo = "";

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function labourRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_LabourRegister'))
            ->columns(array("LabourRegisterId", "RefNo", "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"),
                "Approve" => new Expression("Case When a.Approve ='Y' then 'Yes' else 'No' end "), "Display" => new Expression("Case When a.Approve ='Y' then 'none' else 'show' end ")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->where(array('a.DeleteFlag' => 0));
        $select->order(array('a.RefDate ASC', 'a.LabourRegisterId ASC'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->labourRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteLabourAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $labRegId = $this->params()->fromPost('labRegId');
                    $response = $this->getResponse();

                    $delete = $sql->delete();
                    $delete->from('WPM_LabourTrans')
                        ->where("LabourRegisterId = $labRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_LabourRegister')
                        ->where("LabourRegisterId = $labRegId");
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

    function _convertXLStoCSV($infile, $outfile)
    {
        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);
        $objPHPExcel->setActiveSheetIndex(0);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);
    }

    public function labourcodeAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $igentype = ($postData['gentype'] == 'manual') ? 0 : 1;
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                if ($postData['codefound'] == 1) {
                    $update = $sql->update();
                    $update->table('WPM_LabourCodeSetup');
                    $update->set(array(
                        'GenType' => $igentype, 'Prefix' => $postData['prefix'], 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'Separator' => $postData['separator']
                    ));

                    $statement = $sql->getSqlStringForSqlObject($update);
                } else {
                    $insert = $sql->insert();
                    $insert->into('WPM_LabourCodeSetup');
                    $insert->Values(array('GenType' => $igentype, 'Prefix' => $postData['prefix'], 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'Separator' => $postData['separator']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                }
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('wpm/wpmdashboard', array('controller' => 'wpmdashboard', 'action' => 'wpmdashboard'));
            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }

        }
        $select = $sql->select();
        $select->from('WPM_LabourCodeSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->code = $code;
        $this->_view->codefound = (!empty($code)) ? 1 : 0;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function getLabourTypeListAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);
                $iCCId = $this->bsf->isNullCheck($this->params()->fromPost('costCentreId'), 'number');
                $type=   $this->bsf->isNullCheck($this->params()->fromPost('type'), 'string');
                switch($type){
                    case 'labourname':

                        $select = $sql->select();
                        $select->from('WPM_labourMaster')
                            ->columns(array('LabourName'))
                            ->where('Deactivate = 0 and CostCentreId= '.$iCCId.'');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $labourname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(false);
                        $response = $this->getResponse()->setContent(json_encode($labourname));
                        return $response;
                        break;

                    case 'typelist':
                        $select = $sql->select();
                        $select->from('Proj_Resource')
                            ->columns(array('ResourceId', 'ResourceName'))
                            ->where(array('TypeId' => 1));
                        $select->order('ResourceName ASC');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrTypelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response = $this->getResponse();
                        $response->setContent(json_encode($arrTypelist));
                        return $response;
                        break;
                }

            }
        }
    }

    public function getprojectWBSListAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $sql = new Sql($dbAdapter);

                $iCCId = $this->bsf->isNullCheck($this->params()->fromPost('costCentreId'), 'number');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WBSMaster'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.ProjectId = b.ProjectId', array(), $select::JOIN_INNER)
                    ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => "WBSName"))
                    ->where(array('b.CostCentreId' => $iCCId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrTypelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $response = $this->getResponse();
                $response->setContent(json_encode($arrTypelist));
                return $response;
            }
        }
    }


    public function withHeldAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $sql = new Sql($dbAdapter);
                $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('VendorId'), 'number');
                $costCentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                $OrderId = $this->bsf->isNullCheck($this->params()->fromPost('WORegisterId'), 'number');

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
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getlabourfielddataAction()
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

    public function uploadlabourdataAction()
    {
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
                $select->from(array('a' => 'WF_CityMaster'))
                    ->join(array('b' => 'WF_StateMaster'), 'a.StateId=b.StateId', array('StateName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'WF_CountryMaster'), 'a.CountryId=c.CountryId', array('CountryName'), $select:: JOIN_LEFT)
                    ->columns(array('CityId', 'CityName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrCity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_Resource')
                    ->columns(array('ResourceId', 'ResourceName'))
                    ->where(array('TypeId' => 1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrResource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId', 'VendorName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrVendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WPM_LabourGroupMaster')
                    ->columns(array('LabourGroupId', 'LabourGroupName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrGroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                                    if (trim($sField) == "LabourName")
                                        $col_1 = $j;
                                    if (trim($sField) == "InternalGroupName")
                                        $col_2 = $j;
                                    if (trim($sField) == "VendorName")
                                        $col_3 = $j;
                                    if (trim($sField) == "LabourType")
                                        $col_4 = $j;
                                    if (trim($sField) == "IDNo")
                                        $col_5 = $j;
                                    if (trim($sField) == "Address")
                                        $col_6 = $j;
                                    if (trim($sField) == "City")
                                        $col_7 = $j;
                                    if (trim($sField) == "PinCode")
                                        $col_8 = $j;
                                    if (trim($sField) == "Mobile")
                                        $col_9 = $j;
                                    if (trim($sField) == "EMail")
                                        $col_10 = $j;
                                    if (trim($sField) == "PFNo")
                                        $col_11 = $j;
                                    if (trim($sField) == "AadhaarNo")
                                        $col_12 = $j;
                                    if (trim($sField) == "ESINo")
                                        $col_13 = $j;

                                }
                            }
                        } else {
                            if (!isset($col_1) || !isset($col_4)) {
                                $bValid = false;
                                break;
                            }

                            // check for null
                            if (is_null($col_1) || is_null($col_4)) {
                                $bValid = false;
                                break;
                            }

                            $sLabourName = "";
                            $sGroupName = "";
                            $sVendorName = "";
                            $sLabourType = "";
                            $sIDNo = "";
                            $sAddress = "";
                            $sCity = "";
                            $sPincode = "";
                            $sMobile = "";
                            $sEMail = "";
                            $sPFNo = "";
                            $sAdharNo = "";
                            $sESINo = "";
                            $iCityId = 0;
                            $sStateName = "";
                            $sCountryName = "";
                            $iVendorId = 0;
                            $iGroupId = 0;
                            $iLabourTypeId = 0;

                            if (isset($col_1) && !is_null($col_1)) {
                                if (isset($xlData[$col_1]) && !is_null($xlData[$col_1])) $sLabourName = $this->bsf->isNullCheck($xlData[$col_1], 'string');
                            }

                            if (isset($col_2) && !is_null($col_2)) {
                                if (isset($xlData[$col_2]) && !is_null($xlData[$col_2])) $sGroupName = $this->bsf->isNullCheck($xlData[$col_2], 'string');
                            }

                            if ($sGroupName != "") {
                                $arr = array();
                                $arr = array_filter($arrGroup, function ($v) use ($sGroupName) {
                                    return strtoupper(trim($v['LabourGroupName'])) == strtoupper(trim($sGroupName));
                                });
                                $arrkey = array_keys($arr);
                                if (!empty($arrkey)) {
                                    $akey = $arrkey[0];
                                    $iGroupId = $arr[$akey]['LabourGroupId'];
                                    $sGroupName = $arr[$akey]['LabourGroupName'];
                                }
                            }


                            if (isset($col_3) && !is_null($col_3)) {
                                if (isset($xlData[$col_3]) && !is_null($xlData[$col_3])) $sVendorName = $this->bsf->isNullCheck($xlData[$col_3], 'string');
                            }

                            if ($sVendorName != "") {
                                $arr = array();
                                $arr = array_filter($arrVendor, function ($v) use ($sVendorName) {
                                    return strtoupper(trim($v['VendorName'])) == strtoupper(trim($sVendorName));
                                });
                                $arrkey = array_keys($arr);
                                if (!empty($arrkey)) {
                                    $akey = $arrkey[0];
                                    $iVendorId = $arr[$akey]['VendorId'];
                                    $sVendorName = $arr[$akey]['VendorName'];
                                }
                            }

                            if ($iVendorId != 0) {
                                $sGroupName = $sVendorName;
                            } else if ($sGroupName == "" && $sVendorName != "") {
                                $sGroupName = $sVendorName;
                            }

                            if (isset($col_4) && !is_null($col_4)) {
                                if (isset($xlData[$col_4]) && !is_null($xlData[$col_4])) $sLabourType = $this->bsf->isNullCheck($xlData[$col_4], 'string');
                            }

                            if ($sLabourType != "") {
                                $arr = array();
                                $arr = array_filter($arrResource, function ($v) use ($sLabourType) {
                                    return strtoupper(trim($v['ResourceName'])) == strtoupper(trim($sLabourType));
                                });
                                $arrkey = array_keys($arr);
                                if (!empty($arrkey)) {
                                    $akey = $arrkey[0];
                                    $iLabourTypeId = $arr[$akey]['ResourceId'];
                                    $sLabourType = $arr[$akey]['ResourceName'];
                                }
                            }


                            if (isset($col_5) && !is_null($col_5)) {
                                if (isset($xlData[$col_5]) && !is_null($xlData[$col_5])) $sIDNo = $this->bsf->isNullCheck($xlData[$col_5], 'string');
                            }

                            if (isset($col_6) && !is_null($col_6)) {
                                if (isset($xlData[$col_6]) && !is_null($xlData[$col_6])) $sAddress = $this->bsf->isNullCheck($xlData[$col_6], 'string');
                            }

                            if (isset($col_7) && !is_null($col_7)) {
                                if (isset($xlData[$col_7]) && !is_null($xlData[$col_7])) $sCity = $this->bsf->isNullCheck($xlData[$col_7], 'string');
                            }

                            if ($sCity != "") {
                                $arr = array();
                                $arr = array_filter($arrCity, function ($v) use ($sCity) {
                                    return strtoupper(trim($v['CityName'])) == strtoupper(trim($sCity));
                                });
                                $arrkey = array_keys($arr);
                                if (!empty($arrkey)) {
                                    $akey = $arrkey[0];
                                    $iCityId = $arr[$akey]['CityId'];
                                    $sStateName = $arr[$akey]['StateName'];
                                    $sCountryName = $arr[$akey]['CountryName'];
                                    $sCity = $arr[$akey]['CityName'];
                                }
                            }

                            if (isset($col_8) && !is_null($col_8)) {
                                if (isset($xlData[$col_8]) && !is_null($xlData[$col_8])) $sPincode = $this->bsf->isNullCheck($xlData[$col_8], 'string');
                            }

                            if (isset($col_9) && !is_null($col_9)) {
                                if (isset($xlData[$col_9]) && !is_null($xlData[$col_9])) $sPincode = $this->bsf->isNullCheck($xlData[$col_9], 'string');
                            }

                            if (isset($col_10) && !is_null($col_10)) {
                                if (isset($xlData[$col_10]) && !is_null($xlData[$col_10])) $sPincode = $this->bsf->isNullCheck($xlData[$col_10], 'string');
                            }

                            if (isset($col_11) && !is_null($col_11)) {
                                if (isset($xlData[$col_11]) && !is_null($xlData[$col_11])) $sPincode = $this->bsf->isNullCheck($xlData[$col_11], 'string');
                            }

                            if (isset($col_12) && !is_null($col_12)) {
                                if (isset($xlData[$col_12]) && !is_null($xlData[$col_12])) $sPincode = $this->bsf->isNullCheck($xlData[$col_12], 'string');
                            }

                            if (isset($col_13) && !is_null($col_13)) {
                                if (isset($xlData[$col_13]) && !is_null($xlData[$col_13])) $sPincode = $this->bsf->isNullCheck($xlData[$col_13], 'string');
                            }

                            if ($sLabourName == "" || $sLabourType == "") continue;

                            $data[] = array('Valid' => $bValid, 'LabourName' => $sLabourName, 'GroupName' => $sGroupName, 'LabourType' => $sLabourType, 'IDNo' => $sIDNo,
                                'Address' => $sAddress, 'City' => $sCity, 'CityId' => $iCityId, 'StateName' => $sStateName, 'CountryName' => $sCountryName, 'Pincode' => $sPincode, 'Mobile' => $sMobile, 'Email' => $sEMail, 'PFNo' => $sPFNo,
                                'AdharNo' => $sAdharNo, 'ESINo' => $sESINo, 'GroupId' => $iGroupId, 'VendorId' => $iVendorId, 'LabourTypeId' => $iLabourTypeId);
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
    public function labourDeactivateAction()
    {

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $deactivateId = $this->params()->fromRoute('DeactivateId');
        $this->_view->deactivateId  = (isset($deactivateId) && $deactivateId != 0) ? $deactivateId : 0;

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Deactivate");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);


        if ($this->getRequest()->isXmlHttpRequest()) {

        }
        else {

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $narration=$postParams['narration'];
                $tranferlabour=json_decode($postParams['tranferlabour'],true);
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    if($deactivateId ==0) {


                        $woaVNo = CommonHelper::getVoucherNo(417, date('Y-m-d', strtotime($postParams['TransferDate'])), 0, 0, $dbAdapter, "I");
                        if ($woaVNo["genType"] == true) {
                            $TrNo = $woaVNo["voucherNo"];
                        } else {
                            $TrNo = $postParams['WONo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_labourDeactivateRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo' => $postParams['refNo']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $deactivateid = $dbAdapter->getDriver()->getLastGeneratedValue();


                        foreach ($tranferlabour as $list) {
                            $LabourId = $list['LabourId'];

                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' => 1))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('WPM_labourDeactivateTrans');
                            $insert->Values(array('LabourDeactivateId' => $deactivateid
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                    else
                    {

                        $TrNo = $postParams['TrNo'];
                        $update = $sql->update();
                        $update->table('WPM_labourDeactivateRegister');
                        $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo'=>$postParams['refNo']))
                            ->where("LabourDeactivateId = $deactivateId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);




                        $delete = $sql->delete();
                        $delete->from('WPM_labourDeactivateTrans')
                            ->where("TransId IN(select TransId from WPM_labourDeactivateTrans where LabourDeactivateId=$deactivateId)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($tranferlabour as $list) {
                            $LabourId = $list['LabourId'];

                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' => 1))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('WPM_labourDeactivateTrans');
                            $insert->Values(array('LabourDeactivateId' =>$deactivateId
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $adlid=$postParams['addIds'];
                        if(count($adlid) >0)
                        {
                            foreach($adlid as $lid)
                            {
                                $update = $sql->update();
                                $update->table('WPM_labourMaster');
                                $update->set(array('Deactivate' =>1 ))
                                    ->where("LabourId = $LabourId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }

                        }

                    }

                    $connection->commit();
                    $this->redirect()->toRoute('wpm/default', array('controller' => 'labourstrength', 'action' => 'labour-deactivate'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

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
                    ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId')))
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

            $aVNo = CommonHelper::getVoucherNo(417, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->TrNo = $aVNo["voucherNo"];
            else
                $this->_view->TrNo = "";

            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }

    }
    public function labourActivateAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $ActivateId = $this->params()->fromRoute('ActivateId');
        $this->_view->activateId  = (isset($ActivateId) && $ActivateId != 0) ? $ActivateId : 0;

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Activate");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);


        if ($this->getRequest()->isXmlHttpRequest()) {

        }
        else {

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //var_dump($postParams);die;
                $narration=$postParams['narration'];
                $tranferlabour=json_decode($postParams['tranferlabour'],true);
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    if($ActivateId ==0) {


                        $woaVNo = CommonHelper::getVoucherNo(417, date('Y-m-d', strtotime($postParams['TransferDate'])), 0, 0, $dbAdapter, "I");
                        if ($woaVNo["genType"] == true) {
                            $TrNo = $woaVNo["voucherNo"];
                        } else {
                            $TrNo = $postParams['WONo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_labourActivateRegister');
                        $insert->Values(array('CostCentreId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo' => $postParams['refNo']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $activateid = $dbAdapter->getDriver()->getLastGeneratedValue();


                        foreach ($tranferlabour as $list) {
                            $LabourId = $list['LabourId'];

                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' =>0))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('WPM_labourActivateTrans');
                            $insert->Values(array('LabourActivateId' => $activateid
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                    else
                    {

                        $TrNo = $postParams['TrNo'];
                        $update = $sql->update();
                        $update->table('WPM_labourActivateRegister');
                        $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo'=>$postParams['refNo']))
                            ->where("LabourActivateId = $ActivateId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_labourActivateTrans')
                            ->where("TransId IN(select TransId from WPM_labourActivateTrans where LabourActivateId=$ActivateId)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($tranferlabour as $list) {
                            $LabourId = $list['LabourId'];

                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' => 0))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $insert = $sql->insert();
                            $insert->into('WPM_labourActivateTrans');
                            $insert->Values(array('LabourActivateId' =>$ActivateId
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        /* if(count($postParams['deleteIds'])>0) {
                             $deletelabid=$postParams['deleteIds'];
                             foreach($deletelabid as $labid)
                             {
                                 $update = $sql->update();
                                 $update->table('WPM_labourMaster');
                                 $update->set(array('Deactivate' => 0))
                                     ->where("LabourId = $labid");
                                 $statement = $sql->getSqlStringForSqlObject($update);
                                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                             }

                         }*/

                    }

                    $connection->commit();
                    $this->redirect()->toRoute('wpm/default', array('controller' => 'labourstrength', 'action' => 'labour-activate'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }

            if($ActivateId !=0)
            {
                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourActivateRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->where('a.LabourActivateId = ' . $ActivateId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->transferregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourActivateTrans'))
                    ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                    ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId')))
                    ->where('a.LabourActivateId = ' . $ActivateId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->updateprelablist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //finding labours
                $select = $sql->select();
                $select->from('WPM_labourActivateTrans')
                    ->columns(array('LabourId'))
                    ->where('LabourActivateId='.$ActivateId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->labourres = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }

            $aVNo = CommonHelper::getVoucherNo(417, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->TrNo = $aVNo["voucherNo"];
            else
                $this->_view->TrNo = "";

            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function labourtransferAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");

        $transferId = $this->params()->fromRoute('transId');
        $this->_view->transferId  = (isset($transferId) && $transferId != 0) ? $transferId : 0;
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Labour Transfer");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $costcentrid = $postParams['ccId'];
                $flag =$postParams['flag'];
                $deletetransid = $postParams['ltransid'];
                $type = $postParams['type'];
                if (isset($postParams['resourceIds']) && $postParams['resourceIds'] != '')
                    $resourceIds = substr($postParams['resourceIds'], 0, -1);
                else
                    $resourceIds = 0;

                if (isset($postParams['deleteIds']) && $postParams['deleteIds'] != '')
                    $delIds = substr($postParams['deleteIds'], 0, -1);
                else
                    $delIds = 0;

                switch ($type) {
                    case 'labourlist':
                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_LabourMaster'))
                            ->join(array('b' => 'WPM_LabourGroupMaster'), 'a.LabourGroupId = b.LabourGroupId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Vendor_Master'), 'a.VendorId = d.VendorId', array('GroupName' =>
                                new Expression("Case When a.VendorId !=0 then d.VendorName else b.LabourGroupName + '(Internal)' end")), $select::JOIN_LEFT)
                            ->columns(array('LabourId', 'LabourName', 'LabourTypeId', 'LabourGroupId', 'IsCheck' => new Expression("'0'")))
                            ->where(array("CostCentreId = $costcentrid and Deactivate=$flag"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_labour = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(false);
                        $response = $this->getResponse()->setContent(json_encode($arr_labour));
                        return $response;
                        break;

                    case 'reslabour':
                        $select = $sql->select();
                        $select->from('WPM_LabourMaster')
                            ->columns(array('LabourId', 'LabourName', 'LabourTypeId', 'LabourGroupId'));
                        $select->where("LabourId  IN ($resourceIds) and  CostCentreId=$costcentrid ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_reslabour = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(false);
                        $response = $this->getResponse()->setContent(json_encode($arr_reslabour));
                        return $response;
                        break;

                    case 'newlabour':

                        $select = $sql->select();
                        $select->from(array('a'=>'WPM_LabourMaster'))
                            ->columns(array('LabourId', 'LabourName', 'LabourTypeId', 'LabourGroupId','IsCheck' => new Expression("'0'")));
                        $select->where("a.LabourId NOT IN ($resourceIds) and  a.CostCentreId=$costcentrid and a.Deactivate=$flag");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_newlabour = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(false);
                        $response = $this->getResponse()->setContent(json_encode($arr_newlabour));
                        return $response;
                        break;
                    case 'Deletetransfer':


                        $update = $sql->update();
                        $update->table('WPM_labourMaster');
                        $update->set(array('Deactivate' => 0))
                            ->where("LabourId IN(select LabourId from WPM_labourTransferTrans where LabourTransferId=$deletetransid)");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $delete = $sql->delete();
                        $delete->from('WPM_labourTransferTrans')
                            ->where("TransId IN(select TransId from WPM_labourTransferTrans where LabourTransferId=$deletetransid)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);



                        $delete = $sql->delete();
                        $delete->from('WPM_labourTransferRegister')
                            ->where("LabourTransferId = $deletetransid");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $response = $this->getResponse();
                        $response->setStatusCode('200');

                        return $response;
                        break;
                }
            }
        } else {

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $fromcostcentrid=$postParams['fromcostCentreId'];
                $tocostcentreid=$postParams['tocostCentreId'];
                $narration=$postParams['narration'];
                $transfertype=$postParams['transfertype'];
                $transferId=$postParams['transferid'];
                $count=$postParams['tranferlabourcount'];
                $tranferlabour=json_decode($postParams['tranferlabour'],true);

                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    if($transferId ==0) {

                        $woaVNo = CommonHelper::getVoucherNo(417, date('Y-m-d', strtotime($postParams['TransferDate'])), 0, 0, $dbAdapter, "I");
                        if ($woaVNo["genType"] == true) {
                            $TrNo = $woaVNo["voucherNo"];
                        } else {
                            $TrNo = $postParams['WONo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_labourTransferRegister');
                        $insert->Values(array('FCostCentreIId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TCostCentreId' => $this->bsf->isNullCheck($postParams['tocostCentreId'], 'number')
                        , 'TransferType' => $transfertype
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postParams['FromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postParams['ToDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo'=>$postParams['refNo']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $Transferid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach ($tranferlabour as $list) {

                            $LabourId = $list['LabourId'];
                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' =>0))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($transfertype =='P')
                            {

                                $LabourId = $list['LabourId'];
                                $update = $sql->update();
                                $update->table('WPM_labourMaster');
                                $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postParams['tocostCentreId'], 'number')))
                                    ->where("LabourId = $LabourId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            }


                            $insert = $sql->insert();
                            $insert->into('WPM_labourTransferTrans');
                            $insert->Values(array('LabourTransferId' => $Transferid
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                    }
                    else
                    {

                        $TrNo = $postParams['TrNo'];
                        $update = $sql->update();
                        $update->table('WPM_labourTransferRegister');
                        $update->set(array('FCostCentreIId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')
                        , 'TCostCentreId' => $this->bsf->isNullCheck($postParams['tocostCentreId'], 'number')
                        , 'TransferType' => $postParams['transfertypelabour']
                        , 'TransDate' => date('Y-m-d', strtotime($postParams['TransferDate']))
                        , 'FromDate' => date('Y-m-d', strtotime($postParams['FromDate']))
                        , 'ToDate' => date('Y-m-d', strtotime($postParams['ToDate']))
                        , 'TransNo' => $this->bsf->isNullCheck($TrNo, 'string')
                        , 'Narration' => $narration
                        , 'RefNo'=>$postParams['refNo']))
                            ->where("LabourTransferId = $transferId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);



                        $delete = $sql->delete();
                        $delete->from('WPM_labourTransferTrans')
                            ->where("LabourTransferId = $transferId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach ($tranferlabour as $list) {
                            $LabourId = $list['LabourId'];

                            $update = $sql->update();
                            $update->table('WPM_labourMaster');
                            $update->set(array('Deactivate' => 0))
                                ->where("LabourId = $LabourId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            if($transfertype =='P')
                            {

                                $LabourId = $list['LabourId'];
                                $update = $sql->update();
                                $update->table('WPM_labourMaster');
                                $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postParams['tocostCentreId'], 'number')))
                                    ->where("LabourId = $LabourId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);



                            }

                            $insert = $sql->insert();
                            $insert->into('WPM_labourTransferTrans');
                            $insert->Values(array('LabourTransferId' =>$transferId
                            , 'LabourId' => $this->bsf->isNullCheck($LabourId, 'number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        if(count($postParams['deleteIds'])>0) {
                            $deletelabid=$postParams['deleteIds'];
                            foreach($deletelabid as $labid)
                            {
                                if($transfertype =='P')
                                {
                                    $update = $sql->update();
                                    $update->table('WPM_labourMaster');
                                    $update->set(array('CostCentreId' => $this->bsf->isNullCheck($postParams['fromcostCentreId'], 'number')))
                                        ->where("LabourId = $labid");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }
                    $connection->commit();
                    $this->redirect()->toRoute('wpm/default', array('controller' => 'labourstrength', 'action' => 'labourtransfer'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            }
            if($transferId !=0)
            {
                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourTransferRegister'))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.FCostCentreIId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WF_OperationalCostCentre'), 'a.TCostCentreId = c.CostCentreId', array('ToCostCentreName'=>new Expression("c.CostCentreName")), $select::JOIN_LEFT)
                    ->where('a.LabourTransferId = ' . $transferId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->transferregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array('a' => 'WPM_labourTransferTrans'))
                    ->join(array('b' => 'WPM_LabourMaster'), 'a.LabourId = b.LabourId', array(), $select::JOIN_LEFT)
                    ->columns(array('LabourName'=>new Expression('b.LabourName'),'LabourId'=>new  Expression('b.LabourId')))
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

            }
            // tr No

            $aVNo = CommonHelper::getVoucherNo(417, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if($transferId ==0) {
                if ($aVNo["genType"] == true)
                    $this->_view->TrNo = $aVNo["voucherNo"];
                else
                    $this->_view->TrNo = "";
            }
            // Getting Cost Centre
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('data' => new Expression("CostCentreId"), 'value' => new Expression("CostCentreName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->CostCentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }

    public function labourtransferRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_labourTransferRegister'))
            ->columns(array("RefNo","LabourTransferId","TransDate" => new Expression("FORMAT(a.TransDate, 'dd-MM-yyyy')"),
                "TransferType" => new Expression("Case When a.TransferType ='T' then 'Temporary' else 'Permanent' end ")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.FCostCentreIId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'WF_OperationalCostCentre'), 'a.TCostCentreId = c.CostCentreId', array('ToCostCentreName'=>new Expression("c.CostCentreName")), $select::JOIN_LEFT)
            ->order(array('a.TransDate ASC', 'a.LabourTransferId ASC'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->labourtransRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function labourActivateRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Ativate Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_labourActivateRegister'))
            ->columns(array("RefNo","LabourActivateId","TransDate" => new Expression("FORMAT(a.TransDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->order(array('a.TransDate ASC', 'a.LabourActivateId ASC'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->labourtransRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }
    public function labourDeactivateRegisterAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Labour Deactivate Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_labourDeactivateRegister'))
            ->columns(array("RefNo","LabourDeactivateId","TransDate" => new Expression("FORMAT(a.TransDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->order(array('a.TransDate ASC', 'a.LabourDeactivateId ASC'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->labourtransRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }
    public function getOrderQtyAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getorderqty':
                        $ccId = $this->bsf->isNullCheck($this->params()->fromPost('ccId'), 'number');
                        $soId = $this->bsf->isNullCheck($this->params()->fromPost('soId'), 'number');
                        $serviceId = $this->bsf->isNullCheck($this->params()->fromPost('serviceId'), 'number');
                        $wobArr = array();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_OHService"))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.ProjectId=b.ProjectId', array(), $select::JOIN_INNER)
                            ->columns(array('eQty'=>new Expression("Cast(a.Qty As Decimal(18,3))"),'Amount'))
                            ->where("a.ServiceId=$serviceId and b.CostCentreId=$ccId ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['eQty'] = $wo_qty['eQty'];
                        $wobArr['eAmt'] = $wo_qty['Amount'];

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_SOServiceTrans"))
                            ->join(array('b' => 'WPM_SORegister'), 'a.SORegisterId=b.SORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_ServiceMaster'), 'a.ServiceId=c.ServiceId', array('ServiceId'), $select::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'), 'b.CostCentreId=d.CostCentreId', array(), $select::JOIN_INNER)
                            ->columns(array('OQty'=>new Expression("Cast(a.Qty As Decimal(18,3))")))
                            ->where("b.SORegisterId not in ($soId) and d.CostCentreId=$ccId group by c.ServiceId,a.Qty");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['OQty'] = $wo_qty['OQty'];

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_SBServiceTrans"))
                            ->join(array('b' => 'WPM_SBRegister'), 'a.SBRegisterId=b.SBRegisterId', array(), $select::JOIN_INNER)
                            ->columns(array('bQty'=>new Expression("Cast(a.Qty As Decimal(18,3))")))
                            ->where("a.ServiceId=$serviceId and b.CostCentreId=$ccId ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['bQty'] = $wo_qty['bQty'];

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($wobArr));
                        return $response;
                        break;

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        }
    }
}