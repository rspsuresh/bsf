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
use Application\View\Helper\CommonHelper;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class BsfController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function indexAction(){
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
				$postData = $request->getPost();
				//Write your Ajax post code here
    			//$result =  $postData['value'];
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($jsn);
				return $response;
			}
		}
	}

    public function clientrequestAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']));
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $sclientid ="";
                foreach($jdata as $mydata){
                    foreach($mydata as $values){
                        $sClientName = $values->ClientName;
                        $sEmail = $values->EMail;
                        $current_date = date("Y-m-d H:i:s");
                        $insert = $sql->insert();
                        $insert->into('CB_MClientMaster');
                        $insert->Values(array('ClientName' => $sClientName, 'EMail' => $sEmail,'Request'=>1,'RequestOn'=>$current_date));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $sclientid = $sclientid . $dbAdapter->getDriver()->getLastGeneratedValue() . ",";
                    }
                }
                $connection->commit();
                $sclientid = rtrim($sclientid, ',');
                $response = $this->getResponse();
                if ($sclientid != "") {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MClientMaster'))
                        ->columns(array('MClientId','Request','RequestOn'))
                        ->where("MClientId IN ($sclientid)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    // trigger mail for client
                    $mailData = array(
                        array(
                            'name' => 'CLIENTID',
                            'content' => $sclientid
                        )
                    );
                    $viewRenderer->MandrilSendMail()->sendMailTo( $config['general']['mandrilEmail'], $config['general']['mandrilEmail'], 'BSF Integration Request', 'cb_bsf_integration_approval', $mailData );
                }
                return $response;
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

    public function clientacceptAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $jdata = $postParams['data'];
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'CB_MClientMaster'))
                ->columns(array('MClientId','Accepted','AcceptOn'))
                ->where("MClientId = $jdata and Accepted=1 and Confirmed=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data = array();
            $data['trans'] = $results;
            $response = $this->getResponse();
            $response->setContent(json_encode($data));

            return $response;
        }
    }

    public function clientconfirmAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {

                $postParams = $request->getPost();
                $jdata = $postParams['data'];

                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);

                $current_date = date("Y-m-d H:i:s");

                $update = $sql->update();
                $update->table('CB_MClientMaster');
                $update->set(array('Confirmed' => 1,'ConfirmOn'=>$current_date))
                       ->where("MClientId = $jdata");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();

                $select = $sql->select();
                $select->from(array('a' => 'CB_MClientMaster'))
                    ->columns(array('MClientId','Confirmed','ConfirmOn'))
                    ->where("MClientId = $jdata");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $data = array();
                $data['trans'] = $results;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));

                return $response;
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

    public function getbillformattestAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $woId = $postParams['woId'];
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            if ($woId == 0) {
                $select->from(array('a' => 'cb_BillFormatTemplate'))
                    ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('RowName', 'TypeName'), $select::JOIN_LEFT)
                    ->columns(array('BillFormatId', 'Slno', 'Description', 'Formula', 'Bold', 'Italic', 'Underline', 'SortId'), array('RowName', 'TypeName'));
            } else {
                $select->from(array('a' => 'cb_BillFormatTrans'))
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

    public function vendorrequestAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']));
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $sTransId ="";
                foreach($jdata as $mydata){
                    foreach($mydata as $values){
                        $iClientId = $values->MClientId;
                        $sEmail =  $this->bsf->isNullCheck($values->EMail,'string');
                        $iVendorId = $values->VendorId;
                        if ($sEmail != "") {
                            $select = $sql->select();
                            $select->from('CB_SubscriberMaster')
                                ->columns(array('SubscriberId'))
                                ->where(array("EMail" => $sEmail));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (!empty($bills)) {
                                $iSubscriberId = $bills->SubscriberId;
                                $current_date = date("Y-m-d H:i:s");
                                $insert = $sql->insert();
                                $insert->into('CB_MClientTrans');
                                $insert->Values(array('MClientId' => $iClientId, 'SubscriberId' => $iSubscriberId, 'VendorId' => $iVendorId, 'Request' => 1, 'RequestOn' => $current_date));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $sTransId = $sTransId . $dbAdapter->getDriver()->getLastGeneratedValue() . ",";
                            }
                        }
                    }
                }
                $connection->commit();

                $sTransId = rtrim($sTransId, ',');
                $response = $this->getResponse();
                if ($sTransId != "") {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MClientTrans'))
                        ->columns(array('MClientId','SubscriberId','VendorId','Request','RequestOn'))
                        ->where("TransId in ($sTransId)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                }
                return $response;
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

    public function vendoracceptAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $jdata = $postParams['data'];
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'CB_MClientTrans'))
                ->columns(array('VendorId','Accepted','AcceptOn'))
                ->where("MClientId = $jdata and Accepted=1 and Confirmed=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data = array();
            $data['trans'] = $results;
            $response = $this->getResponse();
            $response->setContent(json_encode($data));
            return $response;
        }
    }

    public function vendorconfirmAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']));
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $sTransId ="";
                foreach($jdata as $mydata){
                    foreach($mydata as $values){
                        $iClientId = $values->MClientId;
                        $iSubscriberId = $values->RASubscriptionId;

                        $select = $sql->select();
                        $select->from('CB_MClientTrans')
                            ->columns(array('TransId'))
                            ->where("MClientId = $iClientId and SubscriberId = $iSubscriberId");
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        if (!empty($bills)) {
                            $iTransId = $bills->TransId;

                            $current_date = date("Y-m-d H:i:s");

                            $update = $sql->update();
                            $update->table('CB_MClientTrans');
                            $update->set(array('Confirmed' => 1, 'ConfirmOn' => $current_date))
                                ->where("TransId = $iTransId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $sTransId = $sTransId . $iTransId . ",";
                        }
                    }
                }
                $connection->commit();
                $sTransId = rtrim($sTransId, ',');
                $response = $this->getResponse();
                if ($sTransId != "") {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MClientTrans'))
                        ->columns(array('MClientId','SubscriberId','VendorId','Confirmed','ConfirmOn'))
                        ->where("TransId in ($sTransId)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                }
                return $response;
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

    public function workorderupdateAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']), true);
                $sql = new Sql($dbAdapter);
                $connection->beginTransaction();
                $WorkOrderId = 0;
                $iSubscriberId=0;
                foreach ($jdata['WO'] as $values) {
                    $iWoRegisterId = $values['WORegisterId'];
                    $sWONo = $values['WONo'];
                    $sWODate = $values['WODate'];
                    $sOrderType = 'N';
                    $sAgtType = $values['OrderType'];
                    $iMClientId = $values['ClientId'];
                    $sStartDate = $values['StartDate'];
                    $sEndDate = $values['EndDate'];
                    $sPeriodType = $values['PeriodType'];
                    $dAmount = $values['Amount'];
                    $sProjectName = $values['ProjectName'];
                    $sProjectTypeName = $values['ProjectTypeName'];
                    $iDuration = $values['Duration'];
                    $iSubscriberId = $values['SubscriberId'];
                    $iProjectId = 0;
                    $iProjectTypeId = 0;
                    $iClientId = 0;
                    $sWoType =$values['WOType'];

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_ClientMaster'))
                        ->columns(array('ClientId'))
                        ->where("SubscriberId = $iSubscriberId and MClientId= '$iMClientId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($projects)) {
                        $iClientId = $projects->ClientId;
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_ProjectMaster'))
                        ->columns(array('ProjectId'))
                        ->where("SubscriberId = $iSubscriberId and ProjectName= '$sProjectName'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($projects)) {
                        $iProjectId = $projects->ProjectId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('CB_ProjectMaster');
                        $insert->Values(array('ProjectName' => $sProjectName, 'ClientId'=>$iClientId, 'SubscriberId' => $iSubscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iProjectId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_ProjectTypeMaster'))
                        ->columns(array('ProjectTypeId'))
                        ->where("SubscriberId = $iSubscriberId and ProjectTypeName= '$sProjectName'");
                    $statement = $sql->getSqlStringForSqlObject($select);

                    $projecttype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($projecttype)) {
                        $iProjectTypeId = $projecttype->ProjectTypeId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('CB_ProjectTypeMaster');
                        $insert->Values(array('ProjectTypeName' => $sProjectName, 'SubscriberId' => $iSubscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iProjectTypeId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $insert = $sql->insert();
                    $insert->into('CB_WORegister');
                    $insert->Values(array('WODate' => date('Y-m-d', strtotime($sWODate))
                    , 'OrderType' => $sOrderType,'AgreementType'=>$sAgtType, 'WONo' => $sWONo
                    , 'ProjectId' => $iProjectId, 'ProjectTypeId' => $iProjectTypeId, 'ClientId' => $iClientId
                    , 'AgreementNo' => $sWONo, 'AgreementDate' => date('Y-m-d', strtotime($sWODate))
                    , 'StartDate' => date('Y-m-d', strtotime($sStartDate)), 'EndDate' => date('Y-m-d', strtotime($sEndDate)), 'PeriodType' => $sPeriodType
                    , 'Duration' => $iDuration, 'OrderAmount' => $dAmount, 'LiveWO' => 0, 'SubscriberId' => $iSubscriberId, 'BsfWORegisterId' => $iWoRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $WorkOrderId = $dbAdapter->getDriver()->getLastGeneratedValue();

                }

                $iSortId=0;
                $iResId=0;
                $iWBSId=0;
                $iIOWId=0;
                $iParentId=0;
                $iBOQTransid=0;
                $iPrevWBSId=0;
                $iPrevResId=0;

                foreach ($jdata['WOTrans'] as $values) {
                    $sTranstype = $values['TransType'];
                    $sAgtNo = $values['Code'];
                    $sSpec = $values['Specification'];
                    $dQty= $values['Qty'];
                    $dRate= $values['Rate'];
                    $dAmount= $values['Amount'];
                    $sUnit= $values['UnitId'];
                    $iUnitId=0;
                    $iSortId = $values['SortId'];
                    $sHeaderType = $values['HeaderType'];
                    $sHeader = $values['Header'];
                    $iBsfIOWId = $values['BsfIOWId'];
                    $iBsfResourceId = $values['BsfResourceId'];
                    $iBsfWBSId = $values['BsfWBSId'];

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_UOM'))
                        ->columns(array('UnitId'))
                        ->where("UnitName= '$sUnit'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iUnitId = $units->UnitId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_UOM');
                        $insert->Values(array('UnitName' => $sUnit));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iUnitId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    if ($sWoType =="A") {
                        if ($iResId != $iBsfResourceId) {
                            $iParentId = 0;
                            $iResId = $iBsfResourceId;
                            $iWBSId=$iBsfWBSId;
                            $iPrevWBSId=0;
                            $iPrevResId=0;
                        } else if ($iWBSId != $iBsfWBSId) {
                            $sHeaderType = "W";
                            $iParentId = $iPrevResId;
                            $iWBSId = $iBsfWBSId;
                            $iPrevWBSId=0;
                        } else if ($iBsfWBSId == 0) {
                            $iParentId = $iBOQTransid;
                        }
                        else
                        {
                            $iParentId = 0;
                        }
                    } else if ($sWoType =="I") {
                        if ($iIOWId != $iBsfIOWId) {
                            $iParentId = 0;
                            $iIOWId = $iBsfIOWId;
                        } else
                        {
                            $iParentId = $iBOQTransid;
                        }
                    }

                    $insert = $sql->insert();
                    $insert->into('CB_WOBOQ');
                    $insert->Values(array('WORegisterId' => $WorkOrderId, 'TransType' => $sTranstype, 'AgtNo' => $sAgtNo
                            , 'Specification' => $sSpec, 'UnitId' => $iUnitId, 'Qty' => $dQty
                            , 'Rate' => $dRate, 'Amount' => $dAmount,'HeaderType'=>$sHeaderType,'Header'=>$sHeader
                            , 'ParentId'=>$iParentId,'WBSId'=>$iPrevWBSId,'BsfIOWId'=>$iBsfIOWId,'BsfResourceId'=>$iBsfResourceId,'BsfWBSId'=>$iBsfWBSId,'SortId' => $iSortId));
                    $statement = $sql->getSqlStringForSqlObject($insert);

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $iBOQTransid = $dbAdapter->getDriver()->getLastGeneratedValue();
                    if ($sHeaderType == "W") $iPrevWBSId =$iBOQTransid;
                    if ($iPrevResId ==0) $iPrevResId=$iBOQTransid;
                }

                $update = $sql->update();
                $update->table('CB_WOBOQ');
                $update->set(array('WOBOQId' => new Expression("WOBOQTransId")));
                $update->where("WORegisterId = $WorkOrderId and WOBOQId=0");
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);



//                foreach ($jdata['BillFormat'] as $values) {
//
//                    $iBillFormatId = $values['BillFormatId'];
//                    $sDescription = $values['Description'];
//                    $sFormula = $values['Formula'];
//                    $iBold = $values['Bold'];
//                    $sSing= $values['Sign'];
//                    $iSortId= $values['SortId'];
//
//                    $insert = $sql->insert();
//                    $insert->into('CB_BillFormatTrans');
//                    $insert->Values(array('WorkOrderId' => $WorkOrderId, 'BillFormatId' => $iBillFormatId, 'Description' => $sDescription
//                    , 'Formula' => $sFormula, 'Bold' => $iBold, 'Sign' => $sSing
//                    , 'SortId' => $iSortId));
//                    $statement = $sql->getSqlStringForSqlObject($insert);
//                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                }


                $dMobAdvRecPer =0;
                $dRetPer =0;

                foreach ($jdata['Terms'] as $values) {
                    $dMobAdv = $values['MobilisationAmount'];
                    $dMobAdvPer = $values['MobilisationPercent'];
                    $dMobAdvRecPer = $values['MobilisationRecovery'];
                    $dRetPer = $values['RetentionPercent'];

                    $insert = $sql->insert();
                    $insert->into('CB_WOTerms');
                    $insert->Values(array('WORegisterId' => $WorkOrderId, 'MobilisationPercent' => $dMobAdvPer, 'MobilisationAmount' => $dMobAdv
                    , 'MobilisationRecovery' => $dMobAdvRecPer, 'RetentionPercent' => $dRetPer));
                    $statement = $sql->getSqlStringForSqlObject($insert);

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach ($jdata['MaterialAdvance'] as $values) {
                    $sResName = $values['Resource_Name'];
                    $sUnit= $values['Unit_Name'];
                    $dRate =$values['Rate'];
                    $dPer =$values['AdvPercent'];

                    $iUnitId=0;
                    $iMaterialId=0;

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_UOM'))
                        ->columns(array('UnitId'))
                        ->where("UnitName= '$sUnit'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iUnitId = $units->UnitId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_UOM');
                        $insert->Values(array('UnitName' => $sUnit));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iUnitId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MaterialMaster'))
                        ->columns(array('MaterialId'))
                        ->where("MaterialName= '$sResName'  and UnitId=$iUnitId and SubscriberId=$iSubscriberId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iMaterialId = $units->MaterialId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('CB_MaterialMaster');
                        $insert->Values(array('MaterialName' => $sResName,'UnitId' => $iUnitId,'SubscriberId' => $iSubscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMaterialId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $insert = $sql->insert();
                    $insert->into('CB_WOMaterialAdvance');
                    $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $iMaterialId, 'Rate' => $dRate
                    , 'AdvPercent' => $dPer));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach ($jdata['PriceEscalation'] as $values) {
                    $sResName = $values['Resource_Name'];
                    $sUnit= $values['Unit_Name'];
                    $dRate =$values['Rate'];
                    $dPer =$values['EscalationPer'];
                    $sRateCondition = $values['RateCondition'];
                    $dActualRate =$values['ActualRate'];

                    $iUnitId=0;
                    $iMaterialId=0;

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_UOM'))
                        ->columns(array('UnitId'))
                        ->where("UnitName= '$sUnit'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iUnitId = $units->UnitId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_UOM');
                        $insert->Values(array('UnitName' => $sUnit));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iUnitId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MaterialMaster'))
                        ->columns(array('MaterialId'))
                        ->where("MaterialName= '$sResName'  and UnitId=$iUnitId and SubscriberId=$iSubscriberId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iMaterialId = $units->MaterialId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('CB_MaterialMaster');
                        $insert->Values(array('MaterialName' => $sResName,'UnitId' => $iUnitId,'SubscriberId' => $iSubscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMaterialId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $insert = $sql->insert();
                    $insert->into('CB_WOMaterialBaseRate');
                    $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $iMaterialId, 'Rate' => $dRate
                    , 'EscalationPer' => $dPer,'RateCondition'=>$sRateCondition,'ActualRate'=>$dActualRate));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach ($jdata['MaterialExclude'] as $values) {
                    $sResName = $values['Resource_Name'];
                    $sUnit= $values['Unit_Name'];
                    $dRate =$values['Rate'];
                    $sType =$values['SType'];

                    $iUnitId=0;
                    $iMaterialId=0;

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_UOM'))
                        ->columns(array('UnitId'))
                        ->where("UnitName= '$sUnit'");
                    $statement = $sql->getSqlStringForSqlObject($select);

                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iUnitId = $units->UnitId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('Proj_UOM');
                        $insert->Values(array('UnitName' => $sUnit));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iUnitId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MaterialMaster'))
                        ->columns(array('MaterialId'))
                        ->where("MaterialName= '$sResName'  and UnitId=$iUnitId and SubscriberId=$iSubscriberId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($units)) {
                        $iMaterialId = $units->MaterialId;
                    } else {
                        $insert = $sql->insert();
                        $insert->into('CB_MaterialMaster');
                        $insert->Values(array('MaterialName' => $sResName,'UnitId' => $iUnitId,'SubscriberId' => $iSubscriberId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $iMaterialId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }

                    $insert = $sql->insert();
                    $insert->into('CB_WOExcludeMaterial');
                    $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $iMaterialId, 'Rate' => $dRate
                    , 'SType' => $sType));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $select = $sql->select();
                $select->from( array('a' => 'CB_BillFormatTemplate' ))
                       ->columns(array( 'BillFormatId', 'Slno', 'Description', 'Sign', 'Header','WorkOrderId'=>new Expression("$WorkOrderId"),'Formula','Bold','Italic','Underline','SortId'));

                $insert = $sql->insert();
                $insert->into( 'CB_BillFormatTrans' );
                $insert->columns(array('BillFormatId','Slno','Description','Sign','Header','WorkOrderId','Formula','Bold','Italic','Underline','SortId'));
                $insert->Values( $select );
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                if ($dMobAdvRecPer !=0)
                {
                    $sFormula = '(R1+R2)*' .$dMobAdvRecPer.'%';
                    $iFormatId= 5;

                    $update = $sql->update();
                    $update->table('CB_BillFormatTrans');
                    $update->set(array('Formula' => $sFormula));
                    $update->where("BillFormatId = $iFormatId and WorkOrderId = $WorkOrderId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                }
                if ($dRetPer !=0)
                {
                    $sFormula = '(R1+R2)*' .$dRetPer.'%';
                    $iFormatId= 9;

                    $update = $sql->update();
                    $update->table('CB_BillFormatTrans');
                    $update->set(array('Formula' => $sFormula));
                    $update->where("BillFormatId = $iFormatId and WorkOrderId = $WorkOrderId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }


                $connection->commit();

                $response = $this->getResponse();
                if ($WorkOrderId != 0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_WORegister'))
                        ->columns(array('WorkOrderId','BsfWORegisterId'))
                        ->where("WorkOrderId=$WorkOrderId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                }
                return $response;
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

    public function getsubmitbillAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $iMClientId = $postParams['data'];
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillMaster'))
                ->join(array('b' => 'CB_WORegister' ), 'a.WORegisterId=b.WorkOrderId', array('BsfWORegisterId'), $select:: JOIN_LEFT)
                ->join(array('c' => 'CB_ClientMaster' ), 'b.ClientId=c.ClientId', array(), $select:: JOIN_LEFT)
                ->columns(array('BillId','BillNo','BillDate','SubmitAmount','FromDate','ToDate'))
                ->where("c.MClientId = $iMClientId and a.IsSubmittedBill=1 and a.BsfBillRefId=0 and b.BsfWORegisterId<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data = array();
            $data['register'] = $results;

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillAbstract'))
                ->join(array('b' => 'CB_BillMaster' ), 'a.BillId=b.BillId', array(), $select:: JOIN_LEFT)
                ->join(array('c' => 'CB_WORegister' ), 'b.WORegisterId=c.WorkOrderId', array('BsfWORegisterId'), $select:: JOIN_LEFT)
                ->join(array('d' => 'CB_ClientMaster' ), 'c.ClientId=d.ClientId', array(), $select:: JOIN_LEFT)
                ->columns(array('BillId','BillFormatId','CurAmount','Formula','BillAbsId'))
                ->where("d.MClientId = $iMClientId and b.IsSubmittedBill=1 and b.BsfBillRefId=0 and c.BsfWORegisterId<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data['trans'] = $results;

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillBOQ'))
                ->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('BsfIOWId','BsfResourceId','BsfWBSId'), $select:: JOIN_LEFT)
                ->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId', array('BillId'), $select:: JOIN_LEFT)
                ->join(array('d' => 'CB_BillMaster'), 'c.BillId=d.BillId', array(), $select:: JOIN_LEFT)
                ->join(array('e' => 'CB_WORegister'), 'd.WORegisterId=e.WorkOrderId', array('BsfWORegisterId'), $select:: JOIN_LEFT)
                ->join(array('f' => 'CB_ClientMaster' ), 'e.ClientId=f.ClientId', array(), $select:: JOIN_LEFT)
                ->columns(array('BillBOQId','BillAbsId','BillFormatId','CurQty','Rate','CurAmount'))
                ->where("f.MClientId = $iMClientId and d.IsSubmittedBill=1 and d.BsfBillRefId=0 and e.BsfWORegisterId<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data['BOQ'] = $results;

            $response = $this->getResponse();
            $response->setContent(json_encode($data));

            return $response;
        }
    }

    public function certifybillupdateAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']), true);
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $iBillId = 0;
                foreach ($jdata['Reg'] as $values) {
                    $iBillId = $values['RABillId'];
                    $dCerAmt = $values['CerAmount'];
                    $IsCertifiedBill = 1;

                    $update = $sql->update();
                    $update->table('CB_BillMaster');
                    $update->set(array('CertifyAmount' => $dCerAmt,'Certified' =>$IsCertifiedBill, 'IsCertifiedBill' => $IsCertifiedBill));
                    $update->where("BillId = $iBillId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach ($jdata['FormatTrans'] as $values) {
                    $iBillAbsId = $values['BillAbsId'];
                    $dCerAmt = $values['CerAmount'];

                    $update = $sql->update();
                    $update->table('CB_BillAbstract');
                    $update->set(array('CerCurAmount' => $dCerAmt, 'CerCumAmount' => new Expression("CerPrevAmount+$dCerAmt")));
                    $update->where("BillAbsId = $iBillAbsId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach ($jdata['BOQ'] as $values) {
                    $iBillBOQId = $values['BillBOQId'];
                    $dCerQty = $values['CerQty'];
                    $dCerRate = $values['CerRate'];
                    $dCerAmt = $values['CerAmount'];

                    $update = $sql->update();
                    $update->table('CB_BillBOQ');
                    $update->set(array('CerCurQty' => $dCerQty, 'CerRate' => $dCerRate, 'CerCurAmount' => $dCerAmt, 'CerCumQty' => new Expression("CerPrevQty+$dCerQty"), 'CerCumAmount' => new Expression("CerPrevAmount+$dCerAmt")));
                    $update->where("BillBOQId= $iBillBOQId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $connection->commit();

                $response = $this->getResponse();
                if ($iBillId != 0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_BillMaster'))
                        ->columns(array('BillId'))
                        ->where("BillId=$iBillId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                }
                return $response;
             } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }


    public function billRefupdateAction()
    {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $postParams = $request->getPost();
                $jdata = json_decode(utf8_encode($postParams['data']), true);
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                $iBillId=0;
                foreach ($jdata['Reg'] as $values) {
                    $iBillId = $values['RABillId'];
                    $iRegId = $values['RegisterId'];

                    $update = $sql->update();
                    $update->table('CB_BillMaster');
                    $update->set(array('BsfBillRefId' => $iRegId));
                    $update->where("BillId = $iBillId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $connection->commit();

                $response = $this->getResponse();
                if ($iBillId != 0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_BillMaster'))
                        ->columns(array('BsfBillRefId'))
                        ->where("BillId=$iBillId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data = array();
                    $data['trans'] = $results;
                    $response = $this->getResponse();
                    $response->setContent(json_encode($data));
                }
                return $response;

            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
    }

	public function postTestAction(){
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
		}
	}
}