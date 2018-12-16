<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Vendor\Controller;

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

use PHPExcel;
use PHPExcel_IOFactory;
use Application\View\Helper\CommonHelper;

use DOMPDF;

class IndexController extends AbstractActionController{
	public function __construct(){
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
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		$resp = array();	
		if($request->isPost()){
				$postParam = $request->getPost();
				$EmailList = $postParam['emails'];
				//var_dump($EmailList);
				//die;
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    foreach($EmailList as $Email){
                        if($Email!="")
                        {
                            $inviteInsert = $sql->insert('Vendor_UserInvite');
                            $inviteInsert->values(array('EmailId'  => $Email, 'UserId'  => '0'));
                            $inviteStatement = $sql->getSqlStringForSqlObject($inviteInsert);
                            $dbAdapter->query($inviteStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        if($Email='') {
                            $mailData = array(
                                array(
                                    'name' => 'URL',
                                    'content' => ''
                                )
                            );
                            $viewRenderer->MandrilSendMail()->sendMailTo($Email, $config['general']['mandrilEmail'], 'Inviting Vendors', 'Email_Invite', $mailData);
                        }
                    }

				    $connection->commit();
				}
				catch(PDOException $e){
					$connection->rollback();
					//print "Error!: " . $e->getMessage() . "</br>";
					array_push($resp, "Error!: " . $e->getMessage());
				}			
			}
		$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		return $this->_view;
    }
	public function vendorRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
         /*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'updateOnline'){
					$selectVendor = $sql->select(array("a"=>"Vendor_Master"));
					$selectVendor->columns(array("Password", "VendorName"), array("Email1"))
								->join(array("b"=>"Vendor_Contact"), "a.VendorId=b.VendorID", array("Email1"), $selectVendor::JOIN_LEFT)
								->where(array("a.VendorId"=>$postParam['vendorId']));
					$selStatement = $sql->getSqlStringForSqlObject($selectVendor);
					$results = $dbAdapter->query($selStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					/*mail start*/
					
					/*mail end*/
					$data = array("AllowOnline"=>$postParam['allow_online']);
					if($results['Password'] == '')
						$data = array("AllowOnline"=>$postParam['allow_online'], "UserName"=>$results['VendorName'], "Password"=>$results['VendorName']);
					
					$updateVendor = $sql->update("Vendor_Master");
					$updateVendor->set($data)
								->where(array("VendorId"=>$postParam['vendorId']));
					$statement = $sql->getSqlStringForSqlObject($updateVendor);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					array_push($resp, array('success'=>'success'));
				}
				else if($postParam['mode'] == 'getDetails'){
					$select = $sql->select();
					$select->from(array('a' => 'Vendor_Master'))
							->join(array('b' => 'Vendor_Contact'), 'a.VendorId=b.VendorId',array('CAddress','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'),$select:: JOIN_LEFT)
							->join(array('c' => 'Vendor_Statutory'),'a.VendorId=c.VendorId',array('FirmType','EYear','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo','ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),$select:: JOIN_LEFT)
							->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId',array('CityName'),$select:: JOIN_LEFT)
							->columns(array('VendorId','PhoneNumber','VendorName','cityId','Supply','Contract','CompanyMailid','Service','Pincode','PANNo','RegAddress','LogoPath','Manufacture','Dealer','Distributor','WebRegistration','AllowOnline',"CreatedDate"=>new Expression("Convert(varchar(10),CreatedDate,105)")),
									array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'),
									array('FirmType','EYear','PANNo','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo','ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),array('CityName'))
							->where(array("a.VendorId"=>$postParam['vendorId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $resp['encodevendor']=$this->bsf->encode($resp['data']['VendorId']);
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			//$response->setContent($statement);
			return $response;
		}
		else if($request->isPost()){
		}


        $select = $sql->select();
        $select->from(array('a' =>'Vendor_Master'))
            ->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId',array('CityName'),$select:: JOIN_LEFT)
            ->columns(array(new Expression("a.VendorId,a.VendorName,CASE WHEN a.Supply=1 THEN 'Yes' Else 'No' END as Supply,
            CASE WHEN a.Contract=1 THEN 'Yes' Else 'No' END as Contract,
            CASE WHEN a.Service=1 THEN 'Yes' Else 'No' END as Service,
            a.CityId,a.Pincode,a.RegAddress,CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,
            [Registered Vendor]= CASE WHEN (Select Count(VendorId) From Vendor_Registration Where VendorId=a.VendorId) > 0 THEN 'Yes' Else 'No' END, LogoPath")),array('CityName'))
            ->order('a.vendorName asc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $vendorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

		$this->_view->vendorList = $vendorList;
		return $this->_view;
    }
	public function vendorDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorId= $this->params()->fromRoute('vendorid');
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){
			
			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}

		$select = $sql->select();
		$select->from(array('a' => 'Vendor_Master'))
				->join(array('b' => 'Vendor_Contact'), 'a.VendorId=b.VendorId',array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'),$select:: JOIN_LEFT)
				->join(array('c' => 'Vendor_Statutory'),'a.VendorId=c.VendorId',array('FirmType','EYear','PANNo','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo','ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),$select:: JOIN_LEFT)
				->join(array('d' => 'WF_CityMaster'), 'a.CityId=d.CityId',array('CityName'),$select:: JOIN_LEFT)
				->columns(array('VendorId','VendorName','cityId','Supply','Contract','Service','Pincode','RegAddress'),
						array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'),
						array('FirmType','EYear','PANNo','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo','ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),array('CityName'))
				->where(array("a.VendorId=".$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsVendor   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		return $this->_view;
    }
	public function basicDetailAction() {

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);

//        $vNo = CommonHelper::getVoucherNo(206,date('Y/m/d') ,0,0, $dbAdapter,"");
//        $this->_view->vNo = $vNo;
//        $this->_view->genType = $vNo["genType"];

        //general
        $voNo = CommonHelper::getVoucherNo(206, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->voNo = $voNo;
        $vNo = $voNo['voucherNo'];
        $this->_view->vNo = $vNo;

        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId = $this->bsf->decode($this->params()->fromRoute('vendorid'));
        $basic = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'number');

        /*Ajax Request*/
        if ($request->isXmlHttpRequest()) {
            $resp = array();

            if ($request->isPost()) {

                $postParams = $request->getPost();
//                 echo"<pre>";
//                 print_r($postParams);
//                echo"</pre>";
//                 die;
//                return;

                /*Vendor name validation*/
                if ($postParams['mode'] == 'vendorName') {

                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Master'))
                        ->columns(array('VendorName'))
                        ->where(array('a.VendorName' => $postParams['vendorname']))
                        ->where('a.VendorId != ' . $postParams['vendorid']);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } /*Auto fill state and country ajax*/
                else if ($postParams['mode'] == 'cityCheck') {
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CityMaster'))
                        ->join(array('b' => 'WF_StateMaster'), 'a.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_INNER)
                        ->join(array('c' => 'WF_CountryMaster'), 'a.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_INNER)
                        ->columns(array('CityId', 'CityName'), array('StateId', 'StateName'), array('CountryId', 'CountryName'))
                        ->where(array('a.CityId' => $postParams['cid']));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($resp));
            return $response;
        } else if ($request->isPost()) {
            $postParams = $request->getPost();
//            echo"<pre>";
//                 print_r($postParams);
//                echo"</pre>";
//                 die;
//                return;

            $files = $request->getFiles();

            $DealId = $postParams['Select1'];



            //changes//
//            $DealId = $postParams['Select1'];
//            $Manufacture=0;
//            $Distributor=0;
//            $Dealer=0;
//            foreach($DealId as $DealId){
//                if($DealId==1){
//                    $Manufacture =1;
//                }
//                if($DealId==2){
//                    $Distributor =1;
//                }
//                if($DealId==3){
//                    $Dealer =1;
//                }
//            }
//            $SupId = $postParams['Select'];
//            $Supply=0;
//            $Contract=0;
//            $Service=0;
//            foreach($SupId as $SupId){
//                if($Supply==1){
//                    $Manufacture =1;
//                }
//                if($SupId==2){
//                    $Contract =1;
//                }
//                if($SupId==3){
//                    $Service =1;
//                }
//            }
            //changes last//

//            if($DealId[0]==1){
//                $Manufacture = $DealId[0];
//            }else if($DealId[0]==2) {
//                $Distributor = $DealId[0];
//            }
//            else if($DealId[0]==3) {
//                $Dealer = $DealId[0];
//            }
//
//            if($DealId[1]==1){
//                $Manufacture = $DealId[0];
//            }else if($DealId[1]==2) {
//                $Distributor = $DealId[0];
//            }
//            else if($DealId[1]==3) {
//                $Dealer = $DealId[0];
//            }
//
//            if($DealId[2]==1){
//                $Manufacture = $DealId[0];
//            }else if($DealId[2]==2) {
//                $Distributor = $DealId[0];
//            }
//            else if($DealId[2]==3) {
//                $Dealer = $DealId[0];
//            }



            if (count($DealId) > 0) {
                    if($DealId[0]!='') {
                        $Manufacture = $DealId[0];
                    }
                    else{
                        $Manufacture = 0;
                    }
                    if($DealId[1]!='') {
                        $Distributor = $DealId[1];
                    }
                    else {
                        $Distributor = 0;
                    }
                    if ($DealId[2] != '') {
                        $Dealer = $DealId[2];
                    } else {
                        $Dealer = 0;
                    }

            $SupId = $postParams['Select'];
            if (count($SupId) > 0) {
                if($SupId[0]!='') {
                    $Supply = $SupId[0];
                }
                else{
                    $Supply =0;
                }
                if($SupId[1]!=0) {
                    $Contract = $SupId[1];
                }
                else{
                    $Contract=0;
                }
                if($SupId[2]!=0) {
                    $Service = $SupId[2];
                }
                else{
                    $Service=0;
                }


                $cId = $postParams['CompanyId'];
                if($cId == 'Company'){
                    $cid = 0;
                }
                else{
                    $cid = 1;
                }

            $VendorCode = $this->bsf->isNullCheck($postParams['vendorcode'], 'string');
            $VendorName = $this->bsf->isNullCheck($postParams['vendorname'], 'string');
            $PANNo = $this->bsf->isNullCheck($postParams['panno'], 'string');
            $SupplyType = $this->bsf->isNullCheck($postParams['pantype'], 'string');
            $RegAddress = $this->bsf->isNullCheck($postParams['regaddress'], 'string');
            $CityId = $this->bsf->isNullCheck($postParams['city'], 'number');
            $AadharNo = $this->bsf->isNullCheck($postParams['aadharno'], 'number');
            $PinCode = $this->bsf->isNullCheck($postParams['pincode'], 'string');
            $AllowOnline = $this->bsf->isNullCheck($postParams['allowonline'], 'number');
            $vendorId = $this->bsf->isNullCheck($postParams['VendorId'], 'number');
            $Cnc = $this->bsf->isNullCheck($postParams['cnc'], 'number');
            $Ssi = $this->bsf->isNullCheck($postParams['ssi'], 'number');
            $CompanyMailId = $this->bsf->isNullCheck($postParams['companymailid'], 'string');
            $ServiceTypeId = $this->bsf->isNullCheck($postParams['ServiceTypeId'], 'number');
            $PhoneNumber = $this->bsf->isNullCheck($postParams['phnno'], 'number');
//            $RaBill = $this->bsf->isNullCheck($postParams['raBill'], 'number');
            $cityName = $this->bsf->isNullCheck($postParams['city'], 'string');
            $stateName = $this->bsf->isNullCheck($postParams['state'], 'string');
            $countryName = $this->bsf->isNullCheck($postParams['country'], 'string');
            $cityDetails = $viewRenderer->commonHelper()->getCityDetails($cityName, $stateName, $countryName);


            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId'))
                ->where(array('Code' => $VendorCode))
                ->where('VendorId != ' . $vendorId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $resultsVenCode = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if (count($resultsVenCode) > 0) {
                $renderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
                $url = $renderer->basePath('/vendor/index/basic-detail');
                echo "<script>alert('Vendor Code Already Found'); window.location='" . $url . "';</script>";

            } else {
                $select = $sql->select();
                $select->from('Vendor_Master')
                    ->columns(array('VendorId'))
                    ->where(array('VendorName' => $VendorName))
                    ->where('VendorId != ' . $vendorId);

                $statement = $sql->getSqlStringForSqlObject($select);
                $resultsVen = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (count($resultsVen) == 0) {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    try {
                        if ($vendorId == 0) {
                            CommonHelper::getVoucherNo(206, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                            $insert = $sql->insert('Vendor_Master');
                            $newData = array(
                                //'Code' => $postParams['vendorname'],
                                'Code' => $VendorCode,
                                'VendorName' => $VendorName,
                                'PANNo' => $PANNo,
                                'Supply' => $Supply,
                                'Contract' => $Contract,
                                'Service' => $Service,
                                'PANNo' => $PANNo,
                                'SupplyType' => $SupplyType,
                                'Company' => $cid,
                                'CompanyMailid' => $CompanyMailId,
                                'AadharNo' => $AadharNo,
                                'RegAddress' => $RegAddress,
                                'CityId' => $cityDetails['CityId'],
                                'PinCode' => $PinCode,
                                'AllowOnline' => $AllowOnline,
                                'Manufacture' => $Manufacture,
                                'Distributor' => $Distributor,
                                'Dealer' => $Dealer,
                                'Cnc' => $Cnc,
                                'Ssi' => $Ssi,
                                'ServiceTypeId' => $ServiceTypeId,
                                'PhoneNumber' => $PhoneNumber
//                                'RaBill' => $RaBill
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $results1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $vendorId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            if ($files['files']['name']) {
                                $dir = 'public/uploads/vendor/' . $vendorId . '/vendor-logo/';
                                if (!is_dir($dir))
                                    mkdir($dir, 0755, true);

                                $ext = pathinfo($files['files']['name'], PATHINFO_EXTENSION);
                                $path = $dir . 'vendorlogo_' . $vendorId . '.' . $ext;
                                move_uploaded_file($files['files']['tmp_name'], $path);

                                $updateLogo = $sql->update();
                                $updateLogo->table('Vendor_Master');
                                $updateLogo->set(array(
                                    'VendorLogo' => 1,//allowonline
                                    'LogoPath' => explode('public/', $path)[1],
                                ))
                                    ->where(array('VendorId' => $vendorId));
                                $updateLogoStmt = $sql->getSqlStringForSqlObject($updateLogo);
                                $dbAdapter->query($updateLogoStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else {

                            $dir = 'public/uploads/vendor/' . $vendorId . '/vendor-logo/';

                            if (!is_dir($dir))
                                mkdir($dir, 0755, true);
                            $pathname = '';
                            if ($files['files']['name']) {

                                $filesArr = glob($dir . '/*'); // get all file names
                                // Print_R($filesArr);die;
                                foreach ($filesArr as $file) { // iterate files
                                    if (is_file($file))
                                        unlink($file); // delete file
                                }
                                $ext = pathinfo($files['files']['name'], PATHINFO_EXTENSION);
                                $path = $dir . 'vendorlogo_' . $vendorId . '.' . $ext;
                                move_uploaded_file($files['files']['tmp_name'], $path);
                                $pathname = explode('public/', $path)[1];
                                // Print_r($pathname);die;
                            }
//							if($pathname == '')
//								$pathname = $postParams['LogoPath'];

                            $select = $sql->update();
                            $select->table('Vendor_Master');
                            $select->set(array(
                                'Code' => $VendorCode,
                                'VendorName' => $VendorName,
                                'Supply' => $Supply,
                                'Contract' => $Contract,
                                'Service' => $Service,
                                'PANNo' => $PANNo,
                                'SupplyType' => $SupplyType,
                                'Company' => $cid,
                                'CompanyMailId' => $CompanyMailId,
                                'AadharNo' => $AadharNo,
                                'RegAddress' => $RegAddress,
                                'CityId' => $cityDetails['CityId'],
                                'PinCode' => $PinCode,
                                'AllowOnline' => $AllowOnline,
                                'Manufacture' => $Manufacture,
                                'Distributor' => $Distributor,
                                'Dealer' => $Dealer,
                                'LogoPath' => $pathname,
                                'Cnc' => $Cnc,
                                'Ssi' => $Ssi,
                                'ServiceTypeId' => $ServiceTypeId,
                                 'PhoneNumber' => $PhoneNumber
//                                'RaBill' => $RaBill
                            ));
                            $select->where(array('VendorId' => $vendorId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();

                        if($postParams['saveExit']=='2') {
                            $this->redirect()->toRoute('vendor/basic-detail', array('controller' => 'index', 'action' => 'index'));
                        } else {
                            $this->redirect()->toRoute('vendor/contact-detail', array('controller' => 'index', 'action' =>'contact-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                        }

//                        $this->redirect()->toRoute('vendor/contact-detail', array('controller' => 'index', 'action' => 'contact-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                    } catch (PDOException $e) {
                        $connection->rollback();
                        print "Error!: " . $e->getMessage() . "</br>";
                    }
                } else {
                    $renderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
                    $url = $renderer->basePath('/vendor/index/basic-detail');
                    echo "<script>alert('Vendor Name Already Found'); window.location='" . $url . "';</script>";
                }

            }
        }
    }
}
		$select = $sql->select();
		$select->from(array("a"=>"Vendor_Master"))
			   ->columns(array('VendorId','VendorName','CompanyMailId','Supply','Contract','Cnc','Service','CityId','Pincode','PANNo','SupplyType','Company','AadharNo','RegAddress','Ssi','AllowOnline', 'LogoPath','Manufacture','Dealer','Distributor','ServiceTypeId','RaBill','Code','PhoneNumber'))
			   ->join(array('b'=>'WF_CityMaster'), 'a.CityId=b.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
			   ->join(array('c'=>'WF_StateMaster'), 'c.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
			   ->join(array('d' => 'WF_CountryMaster'), 'd.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)				   
			   ->where(array('VendorId'=>$vendorId));
	    $statement = $sql->getSqlStringForSqlObject($select);
		$basicResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$citySelect = $sql->select();		
		$citySelect->from('WF_CityMaster')
			->columns(array('CityId', 'CityName'));
		$cityStatement = $sql->getSqlStringForSqlObject($citySelect);
		$cityResult = $dbAdapter->query($cityStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$workSelect = $sql->select();		
		$workSelect->from('Vendor_ServiceType')
			->columns(array('ServiceType', 'ServiceTypeId'));
		$workStatement = $sql->getSqlStringForSqlObject($workSelect);
		$workResult = $dbAdapter->query($workStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Common function

        $aVNo = CommonHelper::getVoucherNo(206, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->genType = $aVNo["genType"];
        if (!$aVNo["genType"])
            $this->_view->woNo = "";
        else
            $this->_view->woNo = $aVNo["voucherNo"];

		$this->_view->rs = $basicResult;
		$this->_view->cityResult = $cityResult;
		$this->_view->workResult = $workResult;
		$this->_view->vendorId = $vendorId;
		$this->_view->mode = $basic;
		return $this->_view;
    }
	public function contactDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();

		$response = $this->getResponse();
		$vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){

			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
        else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//                 print_r($postParams);
//                echo"</pre>";
//                 die;
//                return;

            if($vendorId!=0){
                $connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{
						$select = $sql->delete();
						$select->from('Vendor_Contact')
									->where(array('VendorId'=>$vendorId));
								
                        $DelStatement = $sql->getSqlStringForSqlObject($select);
						$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $rowCount = $postParams['RowCount'];
						foreach(range(1,$rowCount) as $count){
							if($postParams['Contact_Address_'.$count]!="" || $postParams['Contact_Person_'.$count]!="" || $postParams['Contact_No_'.$count]!="" || $postParams['Contact_Email_'.$count]!="" || $postParams['Contact_Web_'.$count]!="" )
							{
								$insert = $sql->insert('Vendor_Contact');
								$insert->values(array(
                                    'VendorID'  => $vendorId,
									'CAddress'  =>  $this->bsf->isNullCheck($postParams['Contact_Address_'.$count], 'string'),
									//'Phone1' => $postParams['phoneno_'.$count],
									//'Fax1' => $postParams['faxno_'.$count],
									'CPerson1'  =>  $this->bsf->isNullCheck($postParams['Contact_Person_'.$count], 'string'),
									'ContactNo1'  => $this->bsf->isNullCheck($postParams['Contact_No_'.$count], 'number'),
									'Email1'  => $this->bsf->isNullCheck($postParams['Contact_Email_'.$count], 'string'),
									'WebName'  => $this->bsf->isNullCheck($postParams['Contact_Web_'.$count],'string'),
									'ContactType'  => $this->bsf->isNullCheck($postParams['contact_Type_'.$count], 'string')
								));
							    $statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							} 
						}	

					$connection->commit();
                    if($postParams['saveExit']=='2') {
                        $this->redirect()->toRoute('vendor/contact-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                    } else {
                        $this->redirect()->toRoute('vendor/statutory-detail', array('controller' => 'index', 'action' =>'statutory-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                    }

				}
				catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";				
				}	
			}			
		}
		
		$select = $sql->select();
		$select->from('Vendor_Contact')
			   ->columns(array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'))
			   ->where->like('VendorId', $vendorId );
		$statement = $sql->getSqlStringForSqlObject($select);
		$contactVendor   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->vendorId =$vendorId;
		$this->_view->contactVendor = $contactVendor;			
		return $this->_view;
    }
	public function statutoryDetailAction()	{
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();

        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        $statutory= $this->bsf->isNullCheck($this->params()->fromRoute('mode'),'number');
        /*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			if($vendorId!=0){
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{
					$select = $sql->delete();
					$select->from('Vendor_Statutory')
								->where(array('VendorId'=>$vendorId));

                    $CstDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['cstdate'], 'string')));
					$DelStatement = $sql->getSqlStringForSqlObject($select);			
					$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $insert = $sql->insert('Vendor_Statutory');
					$newData = array(
						'VendorID'  => $vendorId,
						'FirmType'  => $this->bsf->isNullCheck($postParams['constitutionfirm'], 'string'),
						'EYear'  =>$this->bsf->isNullCheck($postParams['yearofestablishment'], 'number'),
//						'PANNo' =>$this->bsf->isNullCheck($postParams['panno'], 'string'),
//                        'AadharNo' => $this->bsf->isNullCheck($postParams['aadharno'],'number'),
						'TANNo' => $this->bsf->isNullCheck($postParams['tanno'], 'string'),
						'CSTNo'  => $this->bsf->isNullCheck($postParams['cstno'], 'string'),
						'CSTDate'  =>$CstDate,
						'TINNo'  =>  $this->bsf->isNullCheck($postParams['tinno'], 'string'),
						'ChequeonName'  => $this->bsf->isNullCheck($postParams['con'], 'string'),
						'ServiceTaxNo'  => $this->bsf->isNullCheck($postParams['servicetaxno'], 'string'),
						'TNGSTNo'  => $this->bsf->isNullCheck($postParams['tngstno'], 'string'),
						'SSIREGDNo'  => $this->bsf->isNullCheck($postParams['ssiregdno'], 'string'),
						'ServiceTaxCir'  => $this->bsf->isNullCheck($postParams['servicetaxcircle'], 'string'),
						'EPFNo'  => $this->bsf->isNullCheck($postParams['epino'], 'string'),
						'ESINo'  => $this->bsf->isNullCheck($postParams['esino'], 'string'),
						'ExciseRegNo'  => $this->bsf->isNullCheck($postParams['exciseregno'], 'string'),
						'ExciseRange'  =>  $this->bsf->isNullCheck($postParams['exciserange'], 'string'),
						'Excisedivision'  => $this->bsf->isNullCheck($postParams['excisedivision'], 'string'),
						'ECCno'  => $this->bsf->isNullCheck($postParams['eccno'], 'string'),
						'AvailExcise'  => $this->bsf->isNullCheck($postParams['availExcise'], 'number'),
						'VatRemittance'  => $this->bsf->isNullCheck($postParams['vatRemittance'], 'number'),
						'RemittanceDate'  => date('Y-M-d',strtotime($this->bsf->isNullCheck($postParams['remittanceDate'], 'date')))
					);
					$insert->values($newData);
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					
					//VendorMaster PAN detail Update
					$iCompanyFound=0;
//					if($postParams['pantype']=="Company"){
//						$iCompanyFound=1;
//					}
//                    $cId = $postParams['CompanyId'];
//                    if($cId == 'Company'){
//                        $cid = 0;
//                    }
//                    else{
//                        $cid = 1;
//                    }
//					$select = $sql->update();
//					$select->table('Vendor_Master');
//					$select->set(array(
//						'SupplyType'  => $postParams['pantype'],
//						'Company'  => $cid//$iCompanyFound
//
//					 ));
//					$select->where(array('VendorId'=>$vendorId));
//				    $statement = $sql->getSqlStringForSqlObject($select);
//					$results2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$connection->commit();
                    if($postParams['saveExit']=='2') {
                        $this->redirect()->toRoute('vendor/statutory-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                    } else {
                        $this->redirect()->toRoute('vendor/bankfinance-detail', array('controller' => 'index', 'action' =>'bankfinance-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                    }

//                     $VendorDetailList = $vendorId;
//					if($VendorDetailList!=0){
//						$this->redirect()->toRoute('vendor/bankfinance-detail', array('controller' => 'index','action' => 'bankfinance-detail','vendorid' => $this->bsf->encode($vendorId)));
//					}
				}
				catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";				
				}								
			}			
		}
        $Results = false;
		if($vendorId !="") {
            $select = $sql->select();
            $select->from(array('a' => 'Vendor_Statutory'))
                ->join(array('b' => 'Vendor_Master'), 'a.VendorId=b.VendorId', array('SupplyType', 'Company'), $select:: JOIN_LEFT)
                ->columns(array('FirmType', 'EYear', 'TANNo', 'CSTNo', 'CSTDate', 'TINNo', 'ServiceTaxNo', 'TNGSTNo', 'SSIREGDNo', 'ServiceTaxCir', 'EPFNo', 'ESINo', 'AvailExcise',
                    'ExciseVendor', 'ExciseRegNo', 'Excisedivision', 'ExciseRange', 'ECCno', 'VatRemittance', 'RemittanceDate', 'ChequeonName'), array('SupplyType', 'Company'))
                ->where(array('a.VendorId' => $vendorId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $Results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }
        $this->_view->statutoryResults = $Results;
        $this->_view->smode = $statutory;
        $this->_view->vendorId =$vendorId;
        return $this->_view;
    }
	public function bankfinanceDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
		else if($request->isPost()){
			$postParams = $request->getPost();

			if($vendorId!=0){
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{
					$select = $sql->delete();
					$select->from('Vendor_StatutoryBankDetail')
								->where(array('VendorId'=>$vendorId));

					$DelStatement = $sql->getSqlStringForSqlObject($select);
					$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					//echo json_encode($postParams);die;
					$def ="";
					$rowCount = $postParams['RowCount'];
					foreach(range(1,$rowCount) as $count){
						if($postParams['Account_No_'.$count]!="" || $postParams['Bank_Name_'.$count]!="" || $postParams['Branch_Name_'.$count]!="" || $postParams['Branch_Code_'.$count]!="" || $postParams['MICR_Code_'.$count]!="" || $postParams['IFSC_Code_'.$count]!="")
						{
							if(isset($postParams['def_'.$count])){
								$def = 1;
							} else {
								$def = 0;
							}
							$insert = $sql->insert('Vendor_StatutoryBankDetail');
							$insert->values(array(
								'VendorId'  =>$vendorId ,
								'BankAccountNo'  => $this->bsf->isNullCheck($postParams['Account_No_'.$count], 'number'),
								'AccountType' => $this->bsf->isNullCheck($postParams['Account_Type_'.$count], 'string'),
								'BankName' => $this->bsf->isNullCheck($postParams['Bank_Name_'.$count], 'string'),
								'BranchName'  => $this->bsf->isNullCheck($postParams['Branch_Name_'.$count], 'string'),
								'BranchCode'  => $this->bsf->isNullCheck($postParams['Branch_Code_'.$count], 'string'),
								'MICRCode'  =>$this->bsf->isNullCheck($postParams['MICR_Code_'.$count], 'string'),
								'IFSCCode'  => $this->bsf->isNullCheck($postParams['IFSC_Code_'.$count], 'string'),
								'DefaultBank' => $def

							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					$connection->commit();
                    if($postParams['saveExit']=='2') {
                        $this->redirect()->toRoute('vendor/bankfinance-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                    } else {
                        $this->redirect()->toRoute('vendor/branch', array('controller' => 'index', 'action' =>'branch', 'vendorid' => $this->bsf->encode($vendorId)));
                    }

//					$this->redirect()->toRoute('vendor/branch', array('controller' => 'index','action' => 'branch','vendorid' => $this->bsf->encode($vendorId)));
				}
				catch(PDOException $e){
					$connection->rollback();
					print "Error!:return $this->_view; " . $e->getMessage() . "</br>";
				}
			}
		}

		$select = $sql->select();
		$select->from('Vendor_StatutoryBankDetail')
			   ->columns(array('BankAccountNo','AccountType','BankName','BranchName','BranchCode','MICRCode','IFSCCode','DefaultBank'))
			   ->where(array('VendorId'=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$bankResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->bankResult = $bankResult;
		$this->_view->vendorId = $vendorId;
		return $this->_view;
    }
	public function experienceDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));

        /*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
        else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			if($vendorId!=0){
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{
					$select = $sql->delete();
					$select->from('Vendor_Experience')
								->where(array('VendorId'=>$vendorId));

					$DelStatement = $sql->getSqlStringForSqlObject($select);
					$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $rowCount =$postParams['RowCount'];
					foreach(range(1,$rowCount) as $count){
						if($postParams['Name_of_Work_'.$count]!="" || $postParams['Name_of_Client_'.$count]!="" || $postParams['Value_'.$count]!="" || $postParams['Period_'.$count]!="" || $postParams['Type_'.$count]!="" || $postParams['Fromdate_'.$count]!="" ||$postParams['Todate_'.$count]!="")
						{
							$insert = $sql->insert('Vendor_Experience');
							$insert->values(array(
								'VendorId'  => $vendorId,
								'WorkDescription'  => $this->bsf->isNullCheck($postParams['Name_of_Work_'.$count], 'string'),
								'ClientName' =>$this->bsf->isNullCheck($postParams['Name_of_Client_'.$count], 'string'),
								'Value' =>$this->bsf->isNullCheck($postParams['Value_'.$count], 'number'),
								'Period'  => $this->bsf->isNullCheck($postParams['Period_'.$count], 'string'),
								'FromDate'  => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['Fromdate_'.$count], 'string'))),
								'ToDate'  =>  date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['Todate_'.$count], 'string'))),
								'Type'  => $this->bsf->isNullCheck($postParams['Type_'.$count], 'string')

							));
						    $statement = $sql->getSqlStringForSqlObject($insert); 
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					$connection->commit();
                    if($postParams['saveExit']=='2') {
                        $this->redirect()->toRoute('vendor/experience-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                    } else {
                        $this->redirect()->toRoute('vendor/vendor-terms', array('controller' => 'index', 'action' =>'vendor-terms', 'vendorid' => $this->bsf->encode($vendorId)));
                    }

//                    $this->redirect()->toRoute('vendor/vendor-terms', array('controller' => 'index','action' => 'vendor-terms','vendorid' => $this->bsf->encode($vendorId)));
				}
				catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";
				}
			}
		}

		$select = $sql->select();
		$select->from('Vendor_Experience')
			   ->columns(array(new Expression("WorkDescription ,ClientName,Value,Period,Type, Convert(Varchar(10),FromDate,105) as FromDate, Convert(Varchar(10),ToDate,105) as ToDate")))
			   ->where(array('VendorId'=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$experienceResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $typeSelect = $sql->select();
        $typeSelect->from('Vendor_Experience')
            ->columns(array('ExperienceId', 'Type'));
        $typeStatement = $sql->getSqlStringForSqlObject($typeSelect);
        $typeResult = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->vendorId = $vendorId;
		$this->_view->experienceResult = $experienceResult;
        $this->_view->typeResult =$typeResult;
		return $this->_view;
    }
    public function uploadFileAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $vid = $this->params()->fromRoute('vendorid');
            $eid = $this->params()->fromRoute('expid');

            if ($request->isPost()) {
                //Write your Ajax post code here
                $resp =  array();
                if($eid == 0 && ($vid != '' || $vid != 0))
                    $dir = 'public/uploads/vendor/doc1_files';
                else
                    $dir = 'public/uploads/vendor/exp/'.$vid.'-' .$eid. '/';

                if($request->getPost('mode')){
                    unlink($dir.$_POST['fname']);
                }
                else{
                    $files = $request->getFiles();


                    if(!is_dir($dir))
                        mkdir($dir, 0755, true);

                    $i = 1;
                    $fname = $files['file']['name'];
                    $parts = pathinfo($files['file']['name']);
                    while(file_exists($dir.$fname)){
                        $fname = $parts['filename'].' ('.$i.').'.$parts['extension'];
                        $i++;
                    }
                    move_uploaded_file($files["file"]["tmp_name"], $dir.$fname);

                    $resp['fname'] = $fname;
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if ($request->isPost()){
            //Write your Normal form post code here

        }
    }
	public function onlineRegistrationAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){
				$postParams = $request->getPost();

				if($postParams['mode'] == 'vendorName'){
					$select = $sql->select();
					$select->from(array('a' => 'Vendor_Master'))
							->columns(array('VendorName'))
							->where(array('a.VendorName'=>$postParams['vendorname']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
				else if($postParams['mode'] == 'city'){
					$select = $sql->select();
					$select->from(array('a' => 'WF_CityMaster'))
							->join(array('b'=>'WF_StateMaster'), 'a.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_INNER)
							->join(array('c' => 'WF_CountryMaster'), 'a.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_INNER)
							->columns(array('CityId', 'CityName'),array('StateId', 'StateName'),array('CountryId', 'CountryName'))
							->where(array('a.CityId' => $postParams['cid']));

					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
        else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
           // return;
			$files = $request->getFiles();
            $vendorId = $postParams['VendorId'];
            $cityName = $postParams['city'];
            $stateName = $postParams['state'];
            $countryName = $postParams['country'];
            $cityDetails = $viewRenderer->commonHelper()->getCityDetails($cityName, $stateName, $countryName);

//            $dir = 'public/uploads/vendor/' . $vendorId . '/vendor-logo/';

//            if (!is_dir($dir))
//                mkdir($dir, 0755, true);
//            $pathname = '';
//            if ($files['files']['name']) {
//
//                $filesArr = glob($dir . '/*'); // get all file names
//                foreach ($filesArr as $file) { // iterate files
//                    if (is_file($file))
//                        unlink($file); // delete file
//                }
//                $ext = pathinfo($files['files']['name'], PATHINFO_EXTENSION);
//                $path = $dir . 'vendorlogo_' . $vendorId . '.' . $ext;
//                move_uploaded_file($files['files']['tmp_name'], $path);
//                $pathname = explode('public/', $path)[1];
//            }



			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{

                $SupId = $postParams['Select'];

                $Supply=0;
                $Contract=0;
                $Service=0;

                 $cnt =count($SupId);
                if($cnt==1){
                    if($SupId[0]==1){$Supply = $SupId[0];}
                      if($SupId[0]==2){$Contract = $SupId[0];}
                     if($SupId[0]==3){$Service = $SupId[0];}
                 }
                if($cnt==2){
                    if($SupId[0]==1){$Supply = $SupId[0];}
                     if($SupId[0]==2){ $Contract = $SupId[0];}
                     if($SupId[0]==3){$Service = $SupId[0];}
                     if($SupId[1]==1){$Contract = $SupId[1];}
                     if($SupId[1]==2){ $Supply = $SupId[1];}
                    if($SupId[1]==3){$Service = $SupId[1];}
                }
                 if($cnt==3){
                    if($SupId[0]==1){ $Supply = $SupId[0];}
                    if($SupId[0]==2){ $Contract = $SupId[0];}
                    if($SupId[0]==3){ $Service = $SupId[0];}
                    if($SupId[1]==1){ $Supply = $SupId[1];}
                     if($SupId[1]==2){ $Contract = $SupId[1];}
                     if($SupId[1]==3){$Service = $SupId[1];}
                     if($SupId[2]==1){ $Supply = $SupId[2];}
                     if($SupId[2]==2){$Contract = $SupId[2];}
                     if($SupId[2]==3){$Service = $SupId[2];}
                 }


//                if (count($SupId) > 0) {
//                    $cntstd=count($SupId);
//
//
//                    if (count($SupId[0] > 0 ) && $SupId[0] != '') {
//                        $Supply = $SupId[0];
//                    } else {
//                        $Supply = 0;
//                    }
//                    if (count($SupId[1] > 0 ) && $SupId[1] != 0) {
//                        $Contract = $SupId[1];
//                    } else {
//                        $Contract = 0;
//                    }
//                    if (count($SupId[2] > 0 ) && $SupId[2] != 0) {
//                        $Service = $SupId[2];
//                    } else {
//                        $Service = 0;
//                    }
//                }


                $DealId = $postParams['Select1'];
                $Manufacture=0;
                $Distributor=0;
                $Dealer=0;

                $dealcnt=count($DealId);

                if($dealcnt==1){
                         if($DealId[0]==1){$Manufacture = $DealId[0];}
                    else if($DealId[0]==2){$Distributor = $DealId[0];}
                    else if($DealId[0]==3){$Dealer = $DealId[0];}
                }
                if($dealcnt==2){
                    if($DealId[0]==1){$Manufacture = $DealId[0];}
                    if($DealId[0]==2){ $Distributor = $DealId[0];}
                    if($DealId[0]==3){$Dealer = $DealId[0];}
                    if($DealId[1]==1){$Distributor = $DealId[1];}
                    if($DealId[1]==2){ $Manufacture = $DealId[1];}
                    if($DealId[1]==3){$Dealer = $DealId[1];}
                }
                 if($dealcnt==3){
                     if($DealId[0]==1){ $Manufacture = $DealId[0];}
                     if($DealId[0]==2){ $Distributor = $DealId[0];}
                     if($DealId[0]==3){ $Dealer = $DealId[0];}
                     if($DealId[1]==1){ $Manufacture = $DealId[1];}
                     if($DealId[1]==2){ $Distributor = $DealId[1];}
                     if($DealId[1]==3){$Dealer = $DealId[1];}
                     if($DealId[2]==1){ $Manufacture = $DealId[2];}
                     if($DealId[2]==2){$Distributor = $DealId[2];}
                     if($DealId[2]==3){$Dealer = $DealId[2];}
                }

//                if (count($DealId) > 0) {
//                    if ($DealId[0] != '') {
//                        $Manufacture = 1;
//                    } else {
//                        $Manufacture = 0;
//                    }
//                    if ($DealId[1] != '') {
//                        $Distributor = 1;
//                    } else {
//                        $Distributor = 0;
//                    }
//                    if ($DealId[2] != '') {
//                        $Dealer = 1;
//                    } else {
//                        $Dealer = 0;
//                    }
//                }

                $cId = $postParams['CompanyId'];
                if ($cId == 'Company') {
                    $cid = 0;
                } else {
                    $cid = 1;
                }

                        /*basic insert*/
				 $basicInsert = $sql->insert('Vendor_Master');
				$basicInsert->values(array(
					//'Code' => $postParams['code'],

					'VendorName'  => $postParams['vendorname'],
					'Supply' => $Supply,
					'Contract' => $Contract,
					'Service'  => $Service,
                    'Manufacture' => $Manufacture,
                    'Distributor' =>$Distributor,
                    'Dealer' => $Dealer,
                    'PANNo' => $postParams['panno'],
                    'SupplyType'=> $postParams['pantype'],
                    'Company' => $cid,
                    'AadharNo' => $postParams['aadharno'],
                    'ServiceTypeId' => $postParams['ServiceTypeId'],
					'RegAddress'  => $postParams['regaddress'],
					'CityId'  => $cityDetails['CityId'],
					'PinCode'  => $postParams['pincode']
//                    'LogoPath' => $pathname
				));
				$basicstatement = $sql->getSqlStringForSqlObject($basicInsert);
				$dbAdapter->query($basicstatement, $dbAdapter::QUERY_MODE_EXECUTE);
				$vendorId = $dbAdapter->getDriver()->getLastGeneratedValue();

				if($files['files']['name']){
					$dir = 'public/uploads/vendor/'.$vendorId.'/vendor-logo/';
					if(!is_dir($dir))
						mkdir($dir, 0755, true);

					$ext = pathinfo($files['files']['name'], PATHINFO_EXTENSION);
					$path = $dir.'vendorlogo_'.$vendorId.'.'.$ext;
					move_uploaded_file($files['files']['tmp_name'], $path);

					$updateLogo = $sql->update();
					$updateLogo->table('Vendor_Master');
					$updateLogo->set(array(
								'VendorLogo' => 1,//allowonline
								'LogoPath' => explode('public/', $path)[1],
							))
							->where(array('VendorId'=>$vendorId));
					$updateLogoStmt = $sql->getSqlStringForSqlObject($updateLogo);
					$dbAdapter->query($updateLogoStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				/*contact insert*/
				$contactInsert = $sql->insert('Vendor_Contact');
				$contactInsert->values(array(
                    'VendorID'  => $vendorId,
//					'Phone1'  => $postParams['phoneno'],
//					'Fax1' => $postParams['faxno'],
					'CPerson1'  => $postParams['contactperson'],
					'ContactNo1'  => $postParams['contactno'],
                    'CAddress'  => $postParams['Contact_Address'],
					'Email1'  => $postParams['contactemail'],
                    'WebName' => $postParams['webaddress'],
					'ContactType'  => $postParams['contact_Type']
				));
				$contactstatement = $sql->getSqlStringForSqlObject($contactInsert);
				$dbAdapter->query($contactstatement, $dbAdapter::QUERY_MODE_EXECUTE);



//				$contactInsert->values(array(
//					'VendorID'  => $vendorId,
//					'Phone1'  => $postParams['sec_phoneno'],
//					'Fax1' => $postParams['sec_faxno'],
//					'WebName' => $postParams['sec_webaddress'],
//					'CPerson1'  => $postParams['sec_contactperson'],
//					'ContactNo1'  => $postParams['sec_contactno'],
//					'Email1'  => $postParams['sec_contactemail'],
//					'ContactType'  => '2'
//					));
//				$contactstatement = $sql->getSqlStringForSqlObject($contactInsert);
//				$dbAdapter->query($contactstatement, $dbAdapter::QUERY_MODE_EXECUTE);

				/*statutory insert*/
				$statutoryInsert = $sql->insert('Vendor_Statutory');
				$statutoryInsert->values(array(
					'VendorID'  => $vendorId,
					'FirmType'  => $postParams['constitutionfirm'],
					'EYear' => $postParams['yearofestablishment'],
					'TINNo' => $postParams['tinno'],
					'ChequeonName'  => $postParams['con'],
					'PANNo'  => $postParams['panno'],
					'TANNo'  => $postParams['tanno'],
					'CSTNo'  => $postParams['cstno'],
					'ServiceTaxNo'=> $postParams['servicetaxno'],
					'TNGSTNo'=> $postParams['tngstno'],
					'SSIREGDNo'=> $postParams['ssiregdno'],
					'ServiceTaxCir'=> $postParams['servicetaxcircle'],
					'EPFNo'=> $postParams['epino'],
					'ESINo'=> $postParams['esino'],
					'ExciseRegNo'=> $postParams['exciseregno'],
					'ExciseRange'=> $postParams['exciserange'],
					'Excisedivision'=> $postParams['excisedivision'],
					'ECCno'=> $postParams['eccno']

				));
				$statutoryStatement = $sql->getSqlStringForSqlObject($statutoryInsert);
				$results = $dbAdapter->query($statutoryStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				$connection->commit();
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
        $workSelect = $sql->select();
        $workSelect->from('Vendor_ServiceType')
            ->columns(array('ServiceType', 'ServiceTypeId'));
        $workStatement = $sql->getSqlStringForSqlObject($workSelect);
        $workResult = $dbAdapter->query($workStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from('WF_CityMaster')
			   ->columns(array('CityId','CityName'));
        $statement = $sql->getSqlStringForSqlObject($select);
		$resultsCity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->resultsCity = $resultsCity;
		$this->_view->workResult = $workResult;
		return $this->_view;
    }
	public function assessmentmasterDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
        else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$rowCount =$postParams['RowCount'];
				foreach(range(1,$rowCount) as $count){
					$bSupply=0;
					$bContrac=0;
					$bService=0;
					if(isset($postParams['Supply_'.$count]))
						$bSupply=1;

					if(isset($postParams['Contract_'.$count]))
						$bContrac=1;

					if(isset($postParams['Service_'.$count]))
						$bService=1;

					if($postParams['Id_'.$count] > 0){
						$select = $sql->update();
						$select->table('Vendor_CheckListMaster');
						$select->set(array(
							'Description'  => $postParams['Description_'.$count],
							'Supply' => $bSupply,
							'Contract' => $bContrac,
							'Service'  => $bService,
							'MaxPoint'  => $postParams['MaxPoint_'.$count],
							'AssessmentType'  => $postParams['AssessmentType_'.$count]

						 ));
						$select->where(array('CheckListId'=>$postParams['Id_'.$count]));
						$statement = $sql->getSqlStringForSqlObject($select);
						$results2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					else{
						if($postParams['Description_'.$count]!="" || $postParams['MaxPoint_'.$count]!="" || $postParams['AssessmentType_'.$count]!="")
						{
							$insert = $sql->insert('Vendor_CheckListMaster');
							$newData = array(
								'Description'  => $postParams['Description_'.$count],
								'Supply' => $bSupply,
								'Contract' => $bContrac,
								'Service'  => $bService,
								'MaxPoint'  => $postParams['MaxPoint_'.$count],
								'AssessmentType'  => $postParams['AssessmentType_'.$count]

							);
							$insert->values($newData);
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
				}
				$connection->commit();
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		$select = $sql->select();
		$select->from('Vendor_CheckListMaster')
			   ->columns(array('CheckListId','Description','Supply','Contract','Service','MaxPoint','AssessmentType'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$assessmentResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->assessmentResult = $assessmentResult;
		return $this->_view;
    }
	public function branchAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){

			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$selectBranch = $sql->select();
				$selectBranch->from("Vendor_Branch")
							->where(array('VendorId'=>$vendorId));
				$stmtBranch = $sql->getSqlStringForSqlObject($selectBranch);
				$resultBranch = $dbAdapter->query($stmtBranch, $dbAdapter::QUERY_MODE_EXECUTE);
				foreach($resultBranch as $rdata){
					$deleteBranch = $sql->delete();
					$deleteBranch->from("Vendor_BranchContactDetail")
								->where(array('BranchId'=>$rdata['BranchId']));

					$stmtBranch = $sql->getSqlStringForSqlObject($deleteBranch);
					$dbAdapter->query($stmtBranch, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				$deleteBranch = $sql->delete();
				$deleteBranch->from("Vendor_Branch")
							->where(array('VendorId'=>$vendorId));

				$stmtBranch = $sql->getSqlStringForSqlObject($deleteBranch);
				$dbAdapter->query($stmtBranch, $dbAdapter::QUERY_MODE_EXECUTE);

				foreach(range(1, $postParams['branchTotal']) as $btotal){
					if($postParams['branch_name_'.$btotal]!="" || $postParams['branch_address_'.$btotal]!="" || $postParams['branch_tin_'.$btotal]!="" || $postParams['branch_phone_'.$btotal]!="" || $postParams['branch_cheque_'.$btotal]!="" )
					{
						$branchInsert = $sql->insert("Vendor_Branch");
						$branchInsert->values(array("VendorId"=>$vendorId, "BranchName"=>$postParams['branch_name_'.$btotal], "CityId"=>$postParams['branch_city_'.$btotal], "Address"=>$postParams['branch_address_'.$btotal],
													"TINNo"=>$postParams['branch_tin_'.$btotal], "Phone"=>$postParams['branch_phone_'.$btotal], "ChequeNo"=>$postParams['branch_cheque_'.$btotal]));
                        $requestStatement = $sql->getSqlStringForSqlObject($branchInsert);
						$dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

						$branchId = $dbAdapter->getDriver()->getLastGeneratedValue();

						foreach(range(1, $postParams['contactTotal_'.$btotal]) as $ctotal){
							if($postParams['contactperson_b'.$btotal.'_'.$ctotal]!="" || $postParams['designation_b'.$btotal.'_'.$ctotal]!="" || $postParams['contactno_b'.$btotal.'_'.$ctotal]!="" || $postParams['contactemail_b'.$btotal.'_'.$ctotal]!="" || $postParams['contactfax_b'.$btotal.'_'.$ctotal]!="" )
							{
								$contactInsert = $sql->insert("Vendor_BranchContactDetail");
								$contactInsert->values(array("BranchId"=>$branchId, "ContactPerson"=>$postParams['contactperson_b'.$btotal.'_'.$ctotal], "Designation"=>$postParams['designation_b'.$btotal.'_'.$ctotal], "ContactNo"=>$postParams['contactno_b'.$btotal.'_'.$ctotal],
															"Email"=>$postParams['contactemail_b'.$btotal.'_'.$ctotal], "Fax"=>$postParams['contactfax_b'.$btotal.'_'.$ctotal]));

								$requestStatement = $sql->getSqlStringForSqlObject($contactInsert);
								$dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							}
						}
					}
				}
				$connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/branch', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/experience-detail', array('controller' => 'index', 'action' =>'experience-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//				$this->redirect()->toRoute('vendor/experience-detail', array('controller' => 'index','action' => 'experience-detail','vendorid' => $this->bsf->encode($vendorId)));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		$citySelect =  $sql->select();
		$citySelect->from("WF_CityMaster");
		$cityStmt = $sql->getSqlStringForSqlObject($citySelect);
		$cityResult = $dbAdapter->query($cityStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$branchSelect = $sql->select();
		$branchSelect->from(array("a"=>"Vendor_Branch"))
				->join(array("b"=>"WF_CityMaster"), "b.CityId=a.CityId", array("CityName"), $branchSelect::JOIN_LEFT)
				->where(array('VendorId' => $vendorId));

		$branchStmt = $sql->getSqlStringForSqlObject($branchSelect);
		$branchResult = $dbAdapter->query($branchStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$resp=array();
		foreach($branchResult as $data){
			$contactSelect = $sql->select();
			$contactSelect->from("Vendor_BranchContactDetail")
						->where(array('BranchId' => $data['BranchId']));

			$contactStmt = $sql->getSqlStringForSqlObject($contactSelect);
			$data['contact'] = $dbAdapter->query($contactStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			array_push($resp, $data);
		}

		$this->_view->branchCity = $cityResult;
		$this->_view->vendorId = $vendorId;
		$this->_view->branchResult = json_encode($resp);
		return $this->_view;
	}
    public function vendorTermsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()) {
            $postParams = $request->getPost();
//            echo"<pre>";
//                 print_r($postParams);
//                echo"</pre>";
//                 die;
//                return;
            $postcount = $this->bsf->isNullCheck($postParams['count'], 'number');
            $CreditDays = $this->bsf->isNullCheck($postParams['creditperiod'], 'number');
            $MaxLeadTime = $this->bsf->isNullCheck($postParams['maxleadtime'], 'string');
            $TermsAndCondition = $this->bsf->isNullCheck($postParams['terms'], 'string');
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                if($postcount==0){
                    $insert = $sql->insert('Vendor_Terms');
                    $insert->values(array(
                        'VendorId'  => $vendorId,
                        'CreditDays'  => $CreditDays,
                        'MaxLeadTime'  => $MaxLeadTime,
                        'TermsAndCondition'  => $TermsAndCondition,
                    ));
                   $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                else if($postcount==1) {
                    $update = $sql->update();
                    $update->table('Vendor_Terms');
                    $update->set(array(
                        'CreditDays'  => $CreditDays,
                        'MaxLeadTime'  => $MaxLeadTime,
                        'TermsAndCondition'  => $TermsAndCondition,
                    ));
                    $update->where(array('VendorId'=>$vendorId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/vendor-terms', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/assessment-detail', array('controller' => 'index', 'action' =>'assessment-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//                $this->redirect()->toRoute('vendor/assessment-detail', array('controller' => 'index','action' => 'assessment-detail','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $select = $sql->select();
        $select->from('Vendor_Terms')
            ->columns(array('CreditDays','MaxLeadTime','TermsAndCondition'))
            ->where(array("VendorId"=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $termResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->termResult= $termResult;
        $this->_view->vendorId = $vendorId;
        return $this->_view;
    }
    public function assessmentDetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if ($request->isPost()){
            $postParams = $request->getPost();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $select = $sql->delete();
                $select->from('Vendor_CheckListTrans')
                    ->where(array('VendorId'=>$vendorId));

                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Supply
                $cid =explode(",", $postParams['hidcid']);
                foreach($cid as $count){
                    if($count!="" || $count!=0)	{
                        $points=0;
                        if($postParams['point_'.$count]!="")
                            $points=$postParams['point_'.$count];

                        $insert = $sql->insert('Vendor_CheckListTrans');
                        $insert->values(array(
                            'CheckListId'  => $count,
                            'VendorId'  => $vendorId,
                            'RegType' => 'S',
                            'Points'  => $points
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                //Contract
                $cid1 =explode(",", $postParams['hidcid1']);
                foreach($cid1 as $count1){
                    if($count1!="" || $count1!=0){
                        $points=0;
                        if($postParams['Contract_point_'.$count1]!="")
                            $points=$postParams['Contract_point_'.$count1];

                        $insert  = $sql->insert('Vendor_CheckListTrans');
                        $insert->values(array(
                            'CheckListId'  => $count1,
                            'VendorId'  => $vendorId,
                            'RegType' => 'C',
                            'Points'  => $points
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                //Service
                $cid2 =explode(",", $postParams['hidcid2']);
                foreach($cid2 as $count2){
                    if($count2!="" || $count2!=0){
                        $points=0;
                        if($postParams['Service_point_'.$count2]!="")
                            $points=$postParams['Service_point_'.$count2];

                         $insert = $sql->insert('Vendor_CheckListTrans');
                        $insert->values(array(
                            'CheckListId'  => $count2,
                            'VendorId'  => $vendorId,
                            'RegType' => 'R',
                            'Points'  => $points
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/assessment-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/vendor-registration', array('controller' => 'index', 'action' =>'vendor-registration', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//                $this->redirect()->toRoute('vendor/vendor-registration', array('controller' => 'index','action' => 'vendor-registration','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $supplyGradeName="";
        $supplyContractName="";
        $supplyServiceName="";

        //Supply
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType",'RN'=> new Expression("row_number() over (order by CheckListId)")))
            ->group(new expression('AssessmentType,CheckListId'))
            ->where(array('Supply'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsVen1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Trans
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            //->where('a.VendorId = ".$vendorId."');
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'S');

        $Subselect2 = $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'S');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Supply like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Contract
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType",'CRN' => new Expression("row_number() over (order by CheckListId)")))
            ->group( new expression('AssessmentType,CheckListId'))
            ->where(array('Contract'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsContractVen1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //Trans
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'C');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            //->where(array('VendorId'=>$vendisXmlHttpRequestorId));
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'C');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Contract like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsContractVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Service
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType", 'SRN' => new Expression("row_number() over (order by CheckListId)")))
            ->group( new expression('AssessmentType,CheckListId'))
            ->where(array('Service'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsServiceVen1   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Trans
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            //->where('a.VendorId = ".$vendorId."');
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'R');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'R');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Service like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsServiceVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Vendor_CheckListTrans')
            ->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
            ->where(array('VendorId'=>$vendorId));
        $select->where->and->expression('RegType like ?', 'S');
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsGraSupply = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $supplyPoint= $resultsGraSupply['Amount'];

        if($supplyPoint!=""){
            $select = $sql->select();
            $select->from('Vendor_GradeMaster')
                ->columns(array('GradeId','GradeName'));
            //$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');
            $select->where("FValue < '".$supplyPoint."' AND TValue >= '".$supplyPoint."'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $resultsGraSupply2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $supplyGradeName= $resultsGraSupply2['GradeName'];
        }
        //ContractGrade
        $select = $sql->select();
        $select->from('Vendor_CheckListTrans')
            ->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
            ->where(array('VendorId'=>$vendorId));
        $select->where->and->expression('RegType like ?', 'C');
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsGraContract = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $contractPoint= $resultsGraContract['Amount'];

        if($contractPoint!=""){
            $select = $sql->select();
            $select->from('Vendor_GradeMaster')
                ->columns(array('GradeId','GradeName'));
            //$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');
            $select->where("FValue < '".$contractPoint."' AND TValue >= '".$contractPoint."'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $resultsGraContract2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $supplyContractName= $resultsGraContract2['GradeName'];
        }

        //ServiceGrade
        $select = $sql->select();
        $select->from('Vendor_CheckListTrans')
            ->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
            ->where(array('VendorId'=>$vendorId));
        $select->where->and->expression('RegType like ?', 'R');
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsGraService = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $servicePoint= $resultsGraService['Amount'];

        if($servicePoint!=""){
            $select = $sql->select();
            $select->from('Vendor_GradeMaster')
                ->columns(array('GradeId','GradeName'));
            //$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');
            $select->where("FValue < '".$servicePoint."' AND TValue >= '".$servicePoint."'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $resultsGraService2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $supplyServiceName= $resultsGraService2['GradeName'];
        }

        $this->_view->vendorId=$vendorId;
        $this->_view->mainResult = $resultsVen1;
        $this->_view->subResult = $resultsVen2;
        $this->_view->mainResultContract = $resultsContractVen1;
        $this->_view->subResultContract = $resultsContractVen2;
        $this->_view->mainResultService = $resultsServiceVen1;
        $this->_view->subResultService = $resultsServiceVen2;
        $this->_view->SupplyGrade = $supplyGradeName;
        $this->_view->ContractGrade = $supplyContractName;
        $this->_view->ServiceGrade = $supplyServiceName;
        return $this->_view;
    }
    public function vendorRegistrationAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $postParams = $request->getPost();
//                  echo"<pre>";
//                 print_r($postParams);
//                  echo"</pre>";
//                 die;
//                   return;

            $regdate = date('m-d-Y',strtotime($postParams['regdate'])) ;
            $regno = $postParams['regno'];
            $regId = $postParams['registerId'];
            $remarks = $postParams['remarks'];
            $supply =  $postParams['supplyFound'];
            $contract = $postParams['contractFound'];
            $service =  $postParams['serviceFound'];
            $supplyLife = $postParams['supply'];
            $contractLife =  $postParams['contract'];
            $serviceLife =  $postParams['service'];


            $slife = 0;
            $clife = 0;
            $hlife = 0;

            if($supply==1)
            {
                if($supplyLife=="1") {
                    $slife = 1;
                    $sfdate = date('d-m-Y',strtotime($postParams['sfdate']));
                    $stdate = date('d-m-Y',strtotime($postParams['stdate']));
                }	else {
                    $slife = 0;
                    $sfdate = NULL;
                    $stdate = NULL;
                }
            }

            if($contract==1)
            {
                if($contractLife=="1") {
                    $clife = 1;
                    $cfdate = date('d-m-Y',strtotime($postParams['cfdate']));
                    $ctdate = date('d-m-Y',strtotime($postParams['ctdate']));
                }	else {
                    $clife = 0;
                    $cfdate = NULL;
                    $ctdate = NULL;
                }
            }

            if($service==1)
            {
                if($serviceLife=="1") {
                    $hlife = 1;
                    $hfdate = date('d-m-Y',strtotime($postParams['hfdate']));
                    $htdate = date('d-m-Y',strtotime($postParams['htdate']));
                }	else {
                    $hlife = 0;
                    $hfdate = NULL;
                    $htdate = NULL;
                }
            }
            /*
            if($postParams['supply']){
                if($supply=="yes") {
                    $supply=1;
                    $slife = 1;
                    $sfdate = "";
                    $stdate = "";
                }	else {
                    $supply=1;
                    $slife = 0;
                    $sfdate = date('m-d-Y',strtotime($postParams['sfdate']));
                    $stdate = date('m-d-Y',strtotime($postParams['stdate']));
                }
            } else {
                $supply = 0;
                $slife = 0;
                $sfdate = "";
                $stdate = "";

            }
            if($postParams['contract']) {
                if($contract=="yes") {
                    $contract = 1;
                    $clife = 1;
                    $cfdate = "";
                    $ctdate = "";
                }	else {
                    $contract = 1;
                    $clife = 0;
                    $cfdate = date('m-d-Y',strtotime($postParams['cfdate']));
                    $ctdate = date('m-d-Y',strtotime($postParams['ctdate']));
                }
            } else {
                $contract = 0;
                $clife = 0;
                $cfdate = "";
                $ctdate = "";
            }
            if($postParams['service']) {
                if($service=="yes") {
                    $service = 1;
                    $hlife = 1;
                    $hfdate = "";
                    $htdate = "";
                }	else {
                    $service = 1;
                    $hlife = 0;
                    $hfdate = date('m-d-Y',strtotime($postParams['hfdate']));
                    $htdate = date('m-d-Y',strtotime($postParams['htdate']));
                }
            } else {
                $service = 0;
                $hlife = 0;
                $hfdate = "";
                $htdate = "";
            }
            */

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                if($regId==0){
                    $insert      = $sql->insert('Vendor_Registration');
                    $newData     = array(
                        'VendorId'  => $vendorId,
                        'RegDate'  => $regdate,
                        'RegNo'  => $regno,
                        'Remarks' => $remarks,
                        'Supply' => $supply,
                        'Contract' => $contract,
                        'Service' => $service,
                        'SLifeTime' => $slife,
                        'CLifeTime' => $clife,
                        'HLifeTime' => $hlife,
                        'SFDate' => $sfdate,
                        'CFDate' => $cfdate,
                        'HFDate' => $hfdate,
                        'STDate' => $stdate,
                        'CTDate' => $ctdate,
                        'HTDate' => $htdate
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->update();
                    $select->table('Vendor_Master');
                    $select->set(array(
                        'VendorStatus'  =>  'R',
                    ));
                    $select->where(array('VendorId'=>$vendorId));
                    $statementUpdate = $sql->getSqlStringForSqlObject($select);
                    $resultsVendMaster   = $dbAdapter->query($statementUpdate, $dbAdapter::QUERY_MODE_EXECUTE);

                }
                else {
                    $update = $sql->update();
                    $update->table('Vendor_Registration');
                    $updateData     = array(
                        'VendorId'  => $vendorId,
                        'RegDate'  => $regdate,
                        'RegNo'  => $regno,
                        'Remarks' => $remarks,
                        'Supply' => $supply,
                        'Contract' => $contract,
                        'Service' => $service,
                        'SLifeTime' => $slife,
                        'CLifeTime' => $clife,
                        'HLifeTime' => $hlife,
                        'SFDate' => $sfdate,
                        'CFDate' => $cfdate,
                        'HFDate' => $hfdate,
                        'STDate' => $stdate,
                        'CTDate' => $ctdate,
                        'HTDate' => $htdate,
                    );
                    $update->set($updateData);
                    $update->where(array('RegisterId'=>$regId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }
                $connection->commit();
                $this->_view->status = "Registered successfully";
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/vendor-registration', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/resource', array('controller' => 'index', 'action' =>'resource', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//                $this->redirect()->toRoute('vendor/resource', array('controller' => 'index','action' => 'resource','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('VendorName','Supply','Contract','Service'))
            ->where(array('VendorId'=>$vendorId));
        $statementMaster = $sql->getSqlStringForSqlObject($select);
        $resultData = $dbAdapter->query($statementMaster, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Vendor_Registration')
            ->columns(array('RegisterId','RegDate','RegNo','Remarks','SLifeTime','CLifeTime','HLifeTime','SFDate','STDate','CFDate','CTDate','HFDate','HTDate'))
            ->where(array('VendorId'=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $regResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->resultMasterData = $resultData;
        $this->_view->resultData = $regResult;
        $this->_view->vendorId = $vendorId;
        return $this->_view;
    }
	public function resourceAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			//echo json_encode($postParams);die;
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$deleteMan = $sql->delete();
				$deleteMan->from('Vendor_ManPower')
							->where(array('VendorId'=>$vendorId));

				$manDelStmt = $sql->getSqlStringForSqlObject($deleteMan);
				$dbAdapter->query($manDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

				$deleteMachine = $sql->delete();
				$deleteMachine->from('Vendor_Machinery')
							->where(array('VendorId'=>$vendorId));

				$machineDelStmt = $sql->getSqlStringForSqlObject($deleteMachine);
				$dbAdapter->query($machineDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

				$deleteTech = $sql->delete();
				$deleteTech->from('Vendor_TechPersons')
							->where(array('VendorId'=>$vendorId));
				$techDelStmt = $sql->getSqlStringForSqlObject($deleteTech);
				$dbAdapter->query($techDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				$msid = array_filter(explode(",", $postParams['hidmanId']));
				$masid = array_filter(explode(",", $postParams['hidmachineId']));
				foreach($msid as $mid){
					$manInsert = $sql->insert("Vendor_ManPower");
					$manInsert->values(array(
                        "Resource_ID"=>$mid,
                        "VendorId"=>$vendorId,
                        "Qty"=>$this->bsf->isNullCheck($postParams['quantity_'.$mid], 'number')
                    ));
				    $insertManStmt = $sql->getSqlStringForSqlObject($manInsert);
					$dbAdapter->query($insertManStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				foreach($masid as $maid){
					$machineInsert = $sql->insert("Vendor_Machinery");
					$machineInsert->values(array(
                        "Resource_ID"=>$maid,
                        "VendorId"=>$vendorId,
                        "Qty"=>$this->bsf->isNullCheck($postParams['machine_quantity_'.$maid], 'number')
                ));

					$insertMachineStmt = $sql->getSqlStringForSqlObject($machineInsert);
					$dbAdapter->query($insertMachineStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				foreach(range(1, $postParams['hidTechnicalId']) as $tid){
					$techInsert = $sql->insert("Vendor_TechPersons");
					$techInsert->values(array(
                        "VendorId"=>$vendorId,
                        "PersonName"=>$this->bsf->isNullCheck($postParams['person_name_'.$tid], 'string'),
                        "Qualification"=>$this->bsf->isNullCheck($postParams['qualification_'.$tid], 'string'),
                        "Experience"=>$this->bsf->isNullCheck($postParams['designation_'.$tid], 'number'),
                        "Designation"=>$this->bsf->isNullCheck($postParams['designation_'.$tid], 'string')
                    ));

					$insertTechStmt = $sql->getSqlStringForSqlObject($techInsert);
					$dbAdapter->query($insertTechStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				$connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/resource', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/financial', array('controller' => 'index', 'action' =>'financial', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//				$this->redirect()->toRoute('vendor/financial', array('controller' => 'index','action' => 'financial','vendorid' => $this->bsf->encode($vendorId)));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}

		}

		$manSelect = $sql->select();
		$manSelect->from(array("a"=>"Proj_Resource"))
				->columns(array("data"=>"ResourceId", "Code", "value"=>"ResourceName", "TypeId", "ResourceGroupId"))
				->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("UnitId", "UnitName", "UnitDescription"), $manSelect::JOIN_LEFT)
				->where(array("a.TypeId"=>"1"));
	    $manStmt = $sql->getSqlStringForSqlObject($manSelect);
		$manResult = $dbAdapter->query($manStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$machineSelect = $sql->select();
		$machineSelect->from(array("a"=>"Proj_Resource"))
					->columns(array("data"=>"ResourceId", "Code", "value"=>"ResourceName", "TypeId", "ResourceGroupId"))
					->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("UnitId", "UnitName", "UnitDescription"), $machineSelect::JOIN_LEFT)
					->where(array("a.TypeId"=>"3"));
		$machineStmt = $sql->getSqlStringForSqlObject($machineSelect);
		$machineResult = $dbAdapter->query($machineStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$manView = $sql->select();
		$manView->from(array("a"=>"Vendor_ManPower"))
				->columns(array("ManPowerTransId", "VendorId", "Qty"))
				->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("ResourceId", "ResourceName", "TypeId", "ResourceGroupId", "Code"), $manView::JOIN_LEFT)
				->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("UnitId", "UnitName", "UnitDescription"), $manView::JOIN_LEFT)
				->where(array("VendorId"=>$vendorId));
		$manViewStmt = $sql->getSqlStringForSqlObject($manView);
		$manViewResult = $dbAdapter->query($manViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		//echo json_encode($manViewResult);die;
		$machineView = $sql->select();
		$machineView->from(array("a"=>"Vendor_Machinery"))
				->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("*"), $machineView::JOIN_LEFT)
				->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("*"), $machineView::JOIN_LEFT)
				->where(array("VendorId"=>$vendorId));
		$machineViewStmt = $sql->getSqlStringForSqlObject($machineView);
		$machineViewResult = $dbAdapter->query($machineViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$techSelect = $sql->select();
		$techSelect->from("Vendor_TechPersons")
						->where(array("VendorId"=>$vendorId));
		$techStmt = $sql->getSqlStringForSqlObject($techSelect);
		$techResult = $dbAdapter->query($techStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$resResult = array_merge(array_column($manResult, 'value'), array_column($machineResult, 'value'));

		$this->_view->vendorId = $vendorId;
		$this->_view->resResult = $resResult;
		$this->_view->manResult = $manResult;
		$this->_view->machineResult = $machineResult;
		$this->_view->manSelect = $manViewResult;
		$this->_view->machineSelect = $machineViewResult;
		$this->_view->techResult = $techResult;
		return $this->_view;
	}
	public function gradeAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$deleteGrade = $sql->delete();
				$deleteGrade->from('Vendor_GradeMaster');

				$gradeDelStmt = $sql->getSqlStringForSqlObject($deleteGrade);
				$dbAdapter->query($gradeDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

				foreach(range(1, $postParams['hidGradeId']) as $gid){
					if($postParams['grade_name_'.$gid]!="" || $postParams['first_value_'.$gid]!="" || $postParams['second_value_'.$gid]!="" )
					{
						$gradeInsert = $sql->insert("Vendor_GradeMaster");
						$gradeInsert->values(array("GradeName"=>$postParams['grade_name_'.$gid], "FValue"=>$postParams['first_value_'.$gid],
												"TValue"=>$postParams['second_value_'.$gid]));

						$insertGradeStmt = $sql->getSqlStringForSqlObject($gradeInsert);
						$dbAdapter->query($insertGradeStmt, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
				$connection->commit();
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		$gradeSelect = $sql->select();
		$gradeSelect->from("Vendor_GradeMaster");
		$gradeStmt = $sql->getSqlStringForSqlObject($gradeSelect);
		$gradeResult = $dbAdapter->query($gradeStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->gradeResult = $gradeResult;
		return $this->_view;
	}
	public function financialAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;

			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$deleteFinancial = $sql->delete();
				$deleteFinancial->from('Vendor_TurnOver')
								->where(array("VendorId"=>$vendorId));

				$finanDelStmt = $sql->getSqlStringForSqlObject($deleteFinancial);
				$dbAdapter->query($finanDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

				foreach(range(1, $postParams['hidFinId']) as $fid){
					if($postParams['financial_year_'.$fid]!="" || $postParams['financial_value_'.$fid]!="" )
					{
						$finanInsert = $sql->insert("Vendor_TurnOver");
						$finanInsert->values(array(
                            "VendorId"=>$vendorId,
                            "FYear"=>$this->bsf->isNullCheck($postParams['financial_year_'.$fid], 'string'),
                            "Value"=>$this->bsf->isNullCheck($postParams['financial_value_'.$fid], 'number')
                        ));
						$insertFinanStmt = $sql->getSqlStringForSqlObject($finanInsert);
						$dbAdapter->query($insertFinanStmt, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
				$connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/financial', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/supply', array('controller' => 'index', 'action' =>'supply', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//                $this->redirect()->toRoute('vendor/supply', array('controller' => 'index','action' => 'supply','vendorid' =>  $this->bsf->encode($vendorId)));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		$finanSelect =  $sql->select();
		$finanSelect->from("Vendor_TurnOver")
					->where(array("VendorId"=>$vendorId));

		$finanStmt = $sql->getSqlStringForSqlObject($finanSelect);
		$financialResult = $dbAdapter->query($finanStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->financialResult = $financialResult;
		$this->_view->vendorId = $vendorId;
		return $this->_view;
	}
    public function supplyAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $postParams = $request->getPost();
            //echo json_encode($postParams);die;
//            print_r($postParams); die;
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $deleteMan = $sql->delete();
                $deleteMan->from('Vendor_MaterialTrans')
                    ->where(array('VendorId'=>$vendorId));

                $manDelStmt = $sql->getSqlStringForSqlObject($deleteMan);
                $dbAdapter->query($manDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $deleteLog = $sql->delete();
                $deleteLog->from('Vendor_Logistics')
                    ->where(array('VendorId'=>$vendorId));

                $logDelStmt = $sql->getSqlStringForSqlObject($deleteLog);
                $dbAdapter->query($logDelStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $msid = array_filter(explode(",", $postParams['hidsupplyId']));

                foreach($msid as $mid){
                    $materialInsert = $sql->insert("Vendor_MaterialTrans");
                    $materialInsert->values(array(
                        "Resource_ID"=>$mid,
                        "VendorId"=>$vendorId,
                        "Priority"=>$this->bsf->isNullCheck($postParams['supply_priority_'.$mid], 'string'),
                        "SupplyType"=>$this->bsf->isNullCheck($postParams['supply_type_'.$mid], 'string'),
                        "LeadTime"=>$this->bsf->isNullCheck($postParams['supply_lead_time_'.$mid], 'number'),
                        "CreditDays"=>$this->bsf->isNullCheck($postParams['supply_credit_days_'.$mid], 'number'),
                        "ContactPerson"=> $this->bsf->isNullCheck($postParams['supply_contact_person_'.$mid], 'string'),
                        "ContactNo"=>$this->bsf->isNullCheck($postParams['supply_contact_no_'.$mid], 'number'),
                        "Email"=>$this->bsf->isNullCheck($postParams['supply_email_'.$mid], 'string'),
                        "PotentialQty"=>$this->bsf->isNullCheck($postParams['supply_potential_qty_'.$mid], 'number')
                    ));

                    $insertMaterialStmt = $sql->getSqlStringForSqlObject($materialInsert);
                    $dbAdapter->query($insertMaterialStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $logInsert = $sql->insert("Vendor_Logistics");
                $logInsert->values(array(
                    "VendorId"=>$vendorId,
                    "TransportArrange"=>$this->bsf->isNullCheck($postParams['transport_provided'], 'string'),
                    "Unload"=>$this->bsf->isNullCheck($postParams['unload'], 'string'),
                    "Insurance"=>$this->bsf->isNullCheck($postParams['insurance'], 'string'),
                    "Delivery"=> $this->bsf->isNullCheck($postParams['delivery_upto'], 'string'),
                    "TransportMode"=>$this->bsf->isNullCheck($postParams['transport_mode'], 'string')
                ));

                $insertLogStmt = $sql->getSqlStringForSqlObject($logInsert);
                $dbAdapter->query($insertLogStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/supply', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/works', array('controller' => 'index', 'action' =>'works', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//                $this->redirect()->toRoute('vendor/works', array('controller' => 'index','action' => 'works','vendorid' =>  $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }

        }

        $suuplySelect =  $sql->select();
        $suuplySelect->from(array("a"=>"Proj_Resource"))
            ->columns(array("data"=>"ResourceId", "Code", "value"=>"ResourceName", "TypeId", "ResourceGroupId"))
            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("UnitId", "UnitName", "UnitDescription"), $suuplySelect::JOIN_LEFT)
            ->where(array("a.TypeId"=>"2"));
        $supplyStmt = $sql->getSqlStringForSqlObject($suuplySelect);
        $supplyResult = $dbAdapter->query($supplyStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $supplyView = $sql->select();
        $supplyView->from(array("a"=>"Vendor_MaterialTrans"))
            ->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("*"), $supplyView::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("*"), $supplyView::JOIN_LEFT)
            ->where(array("VendorId"=>$vendorId));

        $supplyViewStmt = $sql->getSqlStringForSqlObject($supplyView);
        $supplyViewResult = $dbAdapter->query($supplyViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $logisticView = $sql->select();
        $logisticView->from("Vendor_Logistics")
            ->where(array("VendorId"=>$vendorId));

        $logisticViewStmt = $sql->getSqlStringForSqlObject($logisticView);
        $logisticViewResult = $dbAdapter->query($logisticViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $resArray = array_column($supplyResult, 'value');

        $this->_view->vendorId = $vendorId;
        $this->_view->resResult = $resArray;
        $this->_view->supplyResult = $supplyResult;
        $this->_view->supplyViewResult = $supplyViewResult;
        $this->_view->logisticViewResult = $logisticViewResult;
        return $this->_view;
    }

    public function worksAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();
			if($request->isPost()){


			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;
		}
        else if ($request->isPost()) {
			$postParams = $request->getPost();
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				$select = $sql->delete();
				$select->from('Vendor_WorkGroup')
							->where(array('VendorId'=>$vendorId));
				$DelStatement = $sql->getSqlStringForSqlObject($select);
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				$select = $sql->delete();
				$select->from('Vendor_ActivityTrans')
							->where(array('VendorId'=>$vendorId));
				$DelStatement = $sql->getSqlStringForSqlObject($select);
				$register3 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//WorkGroup
				$cid =explode(",", $postParams['hidcid']);
				foreach($cid as $count){
					if($count!="" || $count!=0)	{
						$insert = $sql->insert('Vendor_WorkGroup');
						$insert->values(array(
							'WorkGroupId'  => $count,
							'VendorId'  => $vendorId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}

				//ActivityGroup
				$cid1 =explode(",", $postParams['hidcid1']);
				foreach($cid1 as $count1){
					if($count1!="" || $count1!=0){
						$insert = $sql->insert('Vendor_ActivityTrans');
						$insert->values(array(
							'ResourceGroupId'  => $count1,
							'VendorId'  => $vendorId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
				$connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/works', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/service', array('controller' => 'index', 'action' =>'service', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//				$this->redirect()->toRoute('vendor/service', array('controller' => 'index','action' => 'service','vendorid' => $this->bsf->encode($vendorId)));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}

		//WorkGroupMaster
		$select1 = $sql->select();
		$select1->from(array("a"=>"Vendor_WorkGroup"))
			->columns(array("WorkGroupId", "Sel"=>new Expression("1")), array("WorkGroupName"))
			->join(array("c"=>"Proj_WorkGroupMaster"), "a.WorkGroupId=c.WorkGroupId", array("WorkGroupName"), $select1::JOIN_INNER)
			->where(array('a.VendorId'=>$vendorId));
	     $statementSel2 = $sql->getSqlStringForSqlObject($select1);
		 $resultsSelWG  = $dbAdapter->query($statementSel2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$Subselect2= $sql->select();
		$Subselect2->from("Vendor_WorkGroup")
			 ->columns(array("WorkGroupId"))
			 //->where('VendorId=5');
			 ->where(array('VendorId'=>$vendorId));

		$select2 = $sql->select();
		$select2->from(array("a"=>'Proj_WorkGroupMaster'))
			->columns(array("WorkGroupId", "Sel"=>new Expression("1-1"), "WorkGroupName"))
			->where->notIn('a.WorkGroupId',$Subselect2);
        $select2->order('WorkGroupName ASC');
        $statement2 = $sql->getSqlStringForSqlObject($select2);
		$resultsWG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		//ActivityGroupMaster
		$Subselect2= $sql->select();
		$Subselect2->from("Vendor_ActivityTrans")
			 ->columns(array("ResourceGroupId"))
			 //->where('VendorId=5');
			 ->where(array('VendorId'=>$vendorId));

		$select2 = $sql->select();
		$select2->from(array("a"=>'Proj_ResourceGroup'))
			->columns(array("ResourceGroupId", "Sel"=>new Expression("1-1"), "ResourceGroupName"))
			->where->notIn('a.ResourceGroupId',$Subselect2);
        $select2->order('ResourceGroupName ASC');
        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsAG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//		$select2->where->and->expression('a.TypeId like ?', '4');
//        $select2->combine($select1,'Union ALL');

//        $select3 = $sql->select();
//        $select3->from(array("g"=>$select2))
//            ->columns(array("ResourceGroupId" , "Sel", "ResourceGroupName"))
//            ->order("ResourceGroupName asc");
//        $statement2 = $sql->getSqlStringForSqlObject($select3);
//		$resultsAG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_ActivityTrans"))
            ->columns(array("ResourceGroupId", "Sel"=>new Expression("1")), array("ResourceGroupName"))
            ->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $statementSel2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelAG  = $dbAdapter->query($statementSel2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->vendorId = $vendorId;
		$this->_view->ResultWG = $resultsWG;
		$this->_view->ResultSelWG = $resultsSelWG;
		$this->_view->ResultSelAG = $resultsSelAG;
		$this->_view->ResultAG = $resultsAG;
		return $this->_view;
	}
    public function serviceAction()	{
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));

        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postParams = $request->getPost();
                $ServiceCode = $this->bsf->isNullCheck($postParams['servicecode'], 'string');
                $ServiceName = $this->bsf->isNullCheck($postParams['servicename'], 'string');
                $ServiceGroupId = $this->bsf->isNullCheck($postParams['servicetype'], 'string');
                $UnitId = $this->bsf->isNullCheck($postParams['unittype'], 'string');
                $ServiceDescription = $this->bsf->isNullCheck($postParams['description'], 'string');
                if($postParams['mode']=="serviceAdd"){
                    //ServiceGroupId,UniId,ServiceDescription
                    $cityInsert = $sql->insert("Proj_ServiceTypeMaster");
                    $cityInsert->values(array(
                        "ServiceCode"=>$ServiceCode,
                        "ServiceTypeName"=> $ServiceName,
                        "ServiceGroupId"=>$ServiceGroupId,
                        "UnitId"=>$UnitId,
                        "ServiceDescription"=>$ServiceDescription
                    ));

                    $insertCityStmt = $sql->getSqlStringForSqlObject($cityInsert);
                    $dbAdapter->query($insertCityStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $resp['data'] = $dbAdapter->getDriver()->getLastGeneratedValue();
                }
                else if($postParams['mode']=="serviceUpdate"){
                    $update = $sql->update();
                    $update ->table('Proj_ServiceTypeMaster');
                    $updateData     = array(
                        "ServiceCode"  => $ServiceCode,
                        "ServiceTypeName" => $ServiceName,
                        "ServiceGroupId"=>$ServiceGroupId,
                        "UnitId"=>$UnitId,
                        "ServiceDescription"=>$ServiceDescription
                    );
                    $update->set($updateData);
                    $update->where(array("ServiceId"=>$postParams['serviceid']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                else if($postParams['mode']=="getService"){
                    $select1 = $sql->select();
                    $select1->from(array("a"=>"Proj_ServiceTypeMaster"))
                        ->columns(array("ServiceTypeId","ServiceTypeName"));
                    $select1->where(array('a.ServiceTypeId'=>$postParams['pid']));

                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $resp['data']   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if ($request->isPost()) {
            $postParams = $request->getPost();
//            echo"<pre>";
//            print_r($postParams);
//            echo"</pre>";
//            die;
//            return;
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
            try{
                $select = $sql->delete();
                $select->from('Vendor_ServiceTrans')
                    ->where(array('VendorId'=>$vendorId));
                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->delete();
                $select->from('Vendor_HireMachineryTrans')
                    ->where(array('VendorId'=>$vendorId));
                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register3 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //ServiceMaster
                $cid = explode(",", $postParams['hidcid']);
                foreach($cid as $count){
                    if($count!="" || $count!=0){
                        $insert = $sql->insert('Vendor_ServiceTrans');
                        $insert->values(array(
                            'ServiceId'  => $count,
                            'VendorId'  => $vendorId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                //HireMachinery
                $cid1 =explode(",", $postParams['hidcid1']);
                foreach($cid1 as $count1){
                    if($count1!="" || $count1!=0){
                        $insert = $sql->insert('Vendor_HireMachineryTrans');
                        $insert->values(array(
                            'ResourceId'  => $count1,
                            'VendorId'  => $vendorId
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/service', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/others', array('controller' => 'index', 'action' =>'others', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//               $vend=$this->bsf->decode($this->params()->fromRoute('vendorid'));
//                $this->redirect()->toRoute('vendor/others', array('controller' => 'index','action' => 'others','vendorid' => $this->bsf->encode($vend)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_ServiceGroup"))
            ->columns(array("ServiceGroupId", "ServiceGroupName"))
            ->order('a.ServiceGroupName ');
        $statement = $sql->getSqlStringForSqlObject($select1);
        $resultsVen1   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select1 = $sql->select();
        $select1->from(array("a"=>"Proj_UOM"))
            ->columns(array("UnitId", "UnitName"))
            ->order('a.UnitName ');
        $statement = $sql->getSqlStringForSqlObject($select1);
        $resultsVen2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //ServiceMaster
//        $select1 = $sql->select();
//        $select1->from(array("a"=>"Vendor_ServiceTrans"))
//            ->columns(array("ServiceId", "Sel"=>new Expression("1")), array("ServiceName"))
//            ->join(array("c"=>"Proj_ServiceTypeMaster"), "a.ServiceId=c.ServiceTypeId", array("ServiceTypeName"), $select1::JOIN_INNER)
//            ->where(array('a.VendorId'=>$vendorId));
        //$select1->order('c.ServiceName ');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_ServiceTrans")
            ->columns(array("ServiceId"))
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Proj_ServiceTypeMaster'))
            ->columns(array("ServiceTypeId", "Sel"=>new Expression("1-1"), "ServiceTypeName"))
            ->where->notIn('a.ServiceTypeId',$Subselect2);
//        $select2->combine($select1,'Union ALL');
        //$select2->order('a.ServiceName ');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsWG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select  = $sql->select();
        $select->from(array("a"=>"Vendor_ServiceTrans"))
            ->columns(array("ServiceId", "Sel"=>new Expression("1")), array("ServiceName"))
            ->join(array("c"=>"Proj_ServiceTypeMaster"), "a.ServiceId=c.ServiceTypeId", array("ServiceTypeName"), $select::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select = $sql->getSqlStringForSqlObject($select);
        $resultsSelWG  = $dbAdapter->query($select, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Machinery
        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_HireMachineryTrans")
            ->columns(array("ResourceId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Proj_Resource'))
            ->columns(array("ResourceId", "Sel"=>new Expression("1-1"), "ResourceName"))
            ->where->notIn('a.ResourceId',$Subselect2);
//        $select2->where->and->expression('a.TypeId like ?', '3');
//        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsAG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a"=>"Vendor_HireMachineryTrans"))
            ->columns(array("ResourceId", "Sel"=>new Expression("1")), array("ResourceName"))
            ->join(array("c"=>"Proj_Resource"), "a.ResourceId=c.ResourceId", array("ResourceName"), $select::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select =  $sql->getSqlStringForSqlObject($select);
        $resultsSelAG  = $dbAdapter->query($select, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->vendorId = $vendorId;
        $this->_view->ResultWG = $resultsWG;
        $this->_view->ResultAG = $resultsAG;
        $this->_view->ResultSelWG = $resultsSelWG;
        $this->_view->ResultSelAG = $resultsSelAG;
        $this->_view->Result1 = $resultsVen1;
        $this->_view->Result2 = $resultsVen2;
        return $this->_view;
    }
    public function othersAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())    {
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
       // Print_r($vendorId);die;

        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postParams = $request->getPost();
                $CityName = $this->bsf->isNullCheck($postParams['city'], 'string');
                $StateId = $this->bsf->isNullCheck($postParams['state'], 'number');
                $CountryId = $this->bsf->isNullCheck($postParams['country'], 'number');
                /*City insert*/
                if($postParams['mode'] == 'city'){
                    $cityInsert = $sql->insert("WF_CityMaster");
                    $cityInsert->values(array(
                        "CityName"=>$CityName,
                        "StateId"=>$StateId,
                        "CountryId"=>$CountryId
                    ));

                    $insertCityStmt = $sql->getSqlStringForSqlObject($cityInsert);
                    $dbAdapter->query($insertCityStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $resp['data'] = $dbAdapter->getDriver()->getLastGeneratedValue();
                }
                /*Cert get*/
                else if($postParams['mode'] == 'getCert'){
                    $certSelect = $sql->select();
                    $certSelect->from("Vendor_CertificateMaster");

                    $certStmt = $sql->getSqlStringForSqlObject($certSelect);
                    $resp['data'] = $dbAdapter->query($certStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                /*Cert get*/
                else if($postParams['mode'] == 'checkCert'){
                    $certSelect = $sql->select();
                    $certSelect->from("Vendor_CertificateTrans")
                        ->where(array("CertificateId"=>$postParams['cid']));

                    $certStmt = $sql->getSqlStringForSqlObject($certSelect);
                    $certViewResult = $dbAdapter->query($certStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $resp['data'] = 0;
                    if(count($certViewResult) > 0)
                        $resp['data'] = 1;
                }
                else if($postParams['mode'] == 'insertCertificate'){
                    $selectCert = $sql->select();
                    $selectCert->from("Vendor_CertificateMaster")
                        ->columns(array("CertificateId"));

                    $selectCertStmt = $sql->getSqlStringForSqlObject($selectCert);
                    $certResult = $dbAdapter->query($selectCertStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $mainArr = array();
                    foreach($certResult as $data){
                        array_push($mainArr, $data['CertificateId']);
                    }
                    $subArr = array();
                    foreach(range(1, $postParams['len']) as $i){
                        if($postParams['desc_'.$i]!="" )
                        {
                            $hiddenid = $this->bsf->isNullCheck($postParams['hidcid_'.$i], 'number');
                            if($hiddenid == 0){
                                $certInsert = $sql->insert("Vendor_CertificateMaster");
                                $certInsert->values(array("cerDescription"=>$postParams['desc_'.$i]
                                ));
                                $insertCertStmt = $sql->getSqlStringForSqlObject($certInsert);
                                $dbAdapter->query($insertCertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            else{
                                $certUpdate = $sql->update("Vendor_CertificateMaster");
                                $certUpdate->set(array("cerDescription"=>$postParams['desc_'.$i]))
                                    ->where(array("CertificateId"=>$hiddenid));

                                $updateCertStmt = $sql->getSqlStringForSqlObject($certUpdate);
                                $dbAdapter->query($updateCertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                array_push($subArr, $hiddenid);
                            }
                        }
                    }
                    $result=array_diff($mainArr,$subArr);
                    if(count($result) > 0){
                        foreach($result as $key=>$value){
                            $deleteCert = $sql->delete();
                            $deleteCert->from("Vendor_CertificateMaster")
                                ->where(array("CertificateId"=>$value));

                            $certStmt = $sql->getSqlStringForSqlObject($deleteCert);
                            $dbAdapter->query($certStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }
                else if($postParams['mode'] == 'getCertificate'){
                    //$vid = $postParams['vid'];
                    /*certificate select*/
                    $certSelect1 = $sql->select();
                    $certSelect1->from(array("a"=>"Vendor_CertificateTrans"))
                        ->columns(array("CertificateId", "Sel"=>new Expression("1")), array("CerDescription"))
                        ->join(array("b"=>"Vendor_CertificateMaster"), "a.CertificateId=b.CertificateId", array("CerDescription"), $certSelect1::JOIN_INNER)
                        ->where(array('a.VendorId'=>$vendorId));

                    $certSubSelect= $sql->select();
                    $certSubSelect->from("Vendor_CertificateTrans")
                        ->columns(array("CertificateId"))
                        ->where(array('VendorId'=>$vendorId));

                    $certSelect2 = $sql->select();
                    $certSelect2->from(array("a"=>'Vendor_CertificateMaster'))
                        ->columns(array("CertificateId", "Sel"=>new Expression("1-1"), "CerDescription"))
                        ->where->notIn('a.CertificateId',$certSubSelect);

                    $certSelect2->combine($certSelect1,'Union ALL');

                    $certStmt= $sql->getSqlStringForSqlObject($certSelect2);
                    $resp['data'] = $dbAdapter->query($certStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                /*Trans get*/
                else if($postParams['mode'] == 'getTrans'){
                    $transSelect = $sql->select();
                    $transSelect->from("Vendor_TransportMaster");

                    $transStmt = $sql->getSqlStringForSqlObject($transSelect);
                    $resp['data'] = $dbAdapter->query($transStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                /*Trans get*/
                else if($postParams['mode'] == 'checkTrans'){
                    $transSelect = $sql->select();
                    $transSelect->from("Vendor_Transport")
                        ->where(array("TransportId"=>$postParams['tid']));

                    $transStmt = $sql->getSqlStringForSqlObject($transSelect);
                    $transViewResult = $dbAdapter->query($transStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $resp['data'] = 0;
                    if(count($transViewResult) > 0)
                        $resp['data'] = 1;
                }
                else if($postParams['mode'] == 'insertTransport'){
                    $selectTrans = $sql->select();
                    $selectTrans->from("Vendor_TransportMaster")
                        ->columns(array("TransportId"));

                    $selectTransStmt = $sql->getSqlStringForSqlObject($selectTrans);
                    $transResult = $dbAdapter->query($selectTransStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $mainArr = array();
                    foreach($transResult as $data){
                        array_push($mainArr, $data['TransportId']);
                    }
                    $subArr = array();
                    foreach(range(1, $postParams['len']) as $i){
                        if($postParams['transport_name_'.$i]!="" )
                        {
                            if($postParams['hidtid_'.$i] == 0){
                                $transInsert = $sql->insert("Vendor_TransportMaster");
                                $transInsert->values(array("TransportName"=>$postParams['transport_name_'.$i]));

                                $insertTransStmt = $sql->getSqlStringForSqlObject($transInsert);
                                $dbAdapter->query($insertTransStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            else{
                                $transUpdate = $sql->update("Vendor_TransportMaster");
                                $transUpdate->set(array("TransportName"=>$postParams['transport_name_'.$i]))
                                    ->where(array("TransportId"=>$postParams['hidtid_'.$i]));

                                $updateTransStmt = $sql->getSqlStringForSqlObject($transUpdate);
                                $dbAdapter->query($updateTransStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                array_push($subArr, $postParams['hidtid_'.$i]);
                            }
                        }
                    }
                    /*$a1=array("a"=>"red","b"=>"green","c"=>"blue","d"=>"yellow");
                    $a2=array("e"=>"red","f"=>"green","g"=>"blue");*/

                    $result=array_diff($mainArr,$subArr);
                    if(count($result) > 0){
                        foreach($result as $key=>$value){
                            $deleteTrans = $sql->delete();
                            $deleteTrans->from("Vendor_TransportMaster")
                                ->where(array("TransportId"=>$value));

                            $transStmt = $sql->getSqlStringForSqlObject($deleteTrans);
                            $dbAdapter->query($transStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }
                else if($postParams['mode'] == 'getTransport'){
                    $vid = $postParams['vid'];
                    /*certificate select*/
                    $transSelect1 = $sql->select();
                    $transSelect1->from(array("a"=>"Vendor_Transport"))
                        ->columns(array("TransportId", "Sel"=>new Expression("1")), array("TransportName"))
                        ->join(array("b"=>"Vendor_TransportMaster"), "a.TransportId=b.TransportId", array("TransportName"), $transSelect1::JOIN_INNER)
                        ->where(array('a.VendorId'=>$vendorId));

                    $transSubSelect= $sql->select();
                    $transSubSelect->from("Vendor_Transport")
                        ->columns(array("TransportId"))
                        ->where(array('VendorId'=>$vendorId));

                    $transSelect2 = $sql->select();
                    $transSelect2->from(array("a"=>'Vendor_TransportMaster'))
                        ->columns(array("TransportId", "Sel"=>new Expression("1-1"), "TransportName"))
                        ->where->notIn('a.TransportId',$transSubSelect);

                    $transSelect2->combine($transSelect1,'Union ALL');

                    $transSelectStmt= $sql->getSqlStringForSqlObject($transSelect2);
                    $resp['data'] = $dbAdapter->query($transSelectStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $postParams = $request->getPost();
//

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                /*Submit data*/
                $files = $request->getFiles();
                $locDelete = $sql->delete();
                $locDelete->from('Vendor_Location')
                    ->where(array('VendorId'=>$vendorId)); echo"<pre>";
//                 print_r($postParams);
//                echo"</pre>";
//                 die;
//                return;

                $deleteLocStmt = $sql->getSqlStringForSqlObject($locDelete);
                $dbAdapter->query($deleteLocStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $certDelete = $sql->delete();
                $certDelete->from('Vendor_CertificateTrans')
                    ->where(array('VendorId'=>$vendorId));

                $deleteCertStmt = $sql->getSqlStringForSqlObject($certDelete);
                $dbAdapter->query($deleteCertStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $transDelete = $sql->delete();
                $transDelete->from('Vendor_Transport')
                    ->where(array('VendorId'=>$vendorId));

                $deleteTransStmt = $sql->getSqlStringForSqlObject($transDelete);
                $dbAdapter->query($deleteTransStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $lid = array_filter(explode(",", $postParams['hidlocId']));
                $certid = array_filter(explode(",", $postParams['hidcertId']));
                $transid = array_filter(explode(",", $postParams['hidtransId']));

                foreach($lid as $cid){
                    $locInsert = $sql->insert("Vendor_Location");
                    $locInsert->values(array("VendorId"=>$vendorId, "CityId"=>$cid));

                    $insertLocStmt = $sql->getSqlStringForSqlObject($locInsert);
                    $dbAdapter->query($insertLocStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                foreach($certid as $ccid){
                    $certInsert = $sql->insert("Vendor_CertificateTrans");
                    $certInsert->values(array("VendorId"=>$vendorId, "CertificateId"=>$ccid));

                    $insertCertStmt = $sql->getSqlStringForSqlObject($certInsert);
                    $dbAdapter->query($insertCertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                foreach($transid as $tid){
                    $transInsert = $sql->insert("Vendor_Transport");
                    $transInsert->values(array("VendorId"=>$vendorId, "TransportId"=>$tid));

                    $insertTransStmt = $sql->getSqlStringForSqlObject($transInsert);
                    $dbAdapter->query($insertTransStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }


                $selectEnclosure = $sql->select();
                $selectEnclosure->from("Vendor_Enclosure")
                    ->columns(array("EnclosureId"))
                    ->where(array("VendorId"=>$vendorId));

                $selectEncStmt = $sql->getSqlStringForSqlObject($selectEnclosure);
                $EncResult = $dbAdapter->query($selectEncStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $mainArr = array();
                foreach($EncResult as $data){
                    array_push($mainArr, $data['EnclosureId']);
                }
                $subArr = array();


                foreach(range(1, $postParams['hidEnclosureId']) as $eid) {
                    $files = $request->getFiles();
                   // Print_r($files); die;
                    if ($postParams['hidenId_' . $eid] != 0 || $postParams['hidenId_' . $eid]!=''){

                        $dir = 'public/uploads/vendorDirectory/enclosure/'.$vendorId.'/' . $postParams['hidenId_' . $eid] . '/';
                      //  Print_r($dir);die;
                        if(!is_dir($dir))
                            mkdir($dir, 0755, true);
                        $pathname = '';
                        if($files['en_location_'.$eid]['name']){
                            $filesArr = glob($dir.'/*'); // get all file names
                            foreach($filesArr as $file){ // iterate files
                                if(is_file($file))
                                    unlink($file); // delete file
                            }
                            $ext = pathinfo($files['en_location_'.$eid]['name'], PATHINFO_EXTENSION);
                            $path = $dir.'enclosure'.$vendorId.'.'.$eid;
                            move_uploaded_file($files['en_location_'.$eid]['tmp_name'], $path);
                            $pathname = explode('public/', $path)[1];
                          //  Print_r($ext); die;
                        }
                        if($pathname == '')
                            $pathname=$this->bsf->isNullCheck($postParams['en_location_'.$eid],'string');
//                            $pathname = $postParams['LogoPath'];

                        $enclosureUpdate = $sql->update("Vendor_Enclosure");
                        $enclosureUpdate->set(array("VendorId" => $vendorId, "Date" => date('Y-m-d', strtotime($postParams['en_date_' . $eid])),
                            "Name" => $postParams['en_name_' . $eid], "Type" => $postParams['en_type_' . $eid]
                        ))
                            ->where(array("EnclosureId" => $postParams['hidenId_' . $eid]));

                        $updateEnclosureStmt = $sql->getSqlStringForSqlObject($enclosureUpdate);
                        $dbAdapter->query($updateEnclosureStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        array_push($subArr, $postParams['hidenId_' . $eid]);
                    } else {

                        if($postParams['en_name_' . $eid] != '' || $postParams['en_name_' . $eid] != NULL  ) {
                            $enclosureInsert = $sql->insert("Vendor_Enclosure");
                            $enclosureInsert->values(array("VendorId" => $vendorId,
                                // "Location" => $path,
                                "Date" => date('Y-m-d', strtotime($postParams['en_date_' . $eid])),
                                "Name" => $postParams['en_name_' . $eid],
                                "Type" => $postParams['en_type_' . $eid]
                            ));
                            $insertEnclosureStmt = $sql->getSqlStringForSqlObject($enclosureInsert);

                            $dbAdapter->query($insertEnclosureStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $enid = $dbAdapter->getDriver()->getLastGeneratedValue();
                            //print_r($files['en_location_'.$eid]['name']); die;
                           if( $files['en_location_' . $eid] !='' ){
                            $dir = 'public/uploads/vendorDirectory/enclosure/' . $vendorId . '/' . $enid . '/';
                            if (!is_dir($dir))
                                mkdir($dir, 0755, true);
                            // Print_R($dir) ; die;
                            $ext = pathinfo($files['en_location_' . $eid]['name'], PATHINFO_EXTENSION);
                            $path = $dir . 'enclosure' . $vendorId . '.' . $ext;
                            move_uploaded_file($files['en_location_' . $eid]['tmp_name'], $path);
                            $pathname = explode('public/', $path)[1];
                            // Print_r($pathname); die;
                            $updateLogo = $sql->update();
                            $updateLogo->table('Vendor_Enclosure');
                            $updateLogo->set(array(
                                // 'VendorId' => 1,//allowonline
                                'Location' => $pathname,
                            ))
                                ->where(array('VendorId' => $vendorId));
                            $updateLogoStmt = $sql->getSqlStringForSqlObject($updateLogo);
                            $dbAdapter->query($updateLogoStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }}

                    }
                }



                $result=array_diff($mainArr,$subArr);
                if(count($result) > 0){
                    foreach($result as $key=>$value){
                        $deleteEnclosure = $sql->delete();
                        $deleteEnclosure->from("Vendor_Enclosure")
                            ->where(array("EnclosureId"=>$value));

                        $encStmt = $sql->getSqlStringForSqlObject($deleteEnclosure);
                        $dbAdapter->query($encStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/others', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/manufacture-detail', array('controller' => 'index', 'action' =>'manufacture-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//                $this->redirect()->toRoute('vendor/manufacture-detail', array('controller' => 'index','action' => 'manufacture-detail','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e)
            {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $citySelect = $sql->select();
        $citySelect->from("WF_CityMaster");

        $cityStmt= $sql->getSqlStringForSqlObject($citySelect);
        $cityResult = $dbAdapter->query($cityStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $stateSelect = $sql->select();
        $stateSelect->from("WF_StateMaster");

        $stateStmt= $sql->getSqlStringForSqlObject($stateSelect);
        $stateResult = $dbAdapter->query($stateStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $countrySelect = $sql->select();
        $countrySelect->from("WF_CountryMaster");

        $countryStmt= $sql->getSqlStringForSqlObject($countrySelect);
        $countryResult = $dbAdapter->query($countryStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $locSelect = $sql->select();
        $locSelect->from(array("a"=>"Vendor_Location"))
            ->columns(array("CityId", "Sel"=>new Expression("1")), array("CityName", "StateId", "CountryId"))
            ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName", "StateId", "CountryId"), $locSelect::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $locSelStmt= $sql->getSqlStringForSqlObject($locSelect);
        $locSelResult = $dbAdapter->query($locSelStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


//        $locSelect1 = $sql->select();
//        $locSelect1->from(array("a"=>"Vendor_Location"))
//            ->columns(array("CityId", "Sel"=>new Expression("1")), array("CityName", "StateId", "CountryId"))
//            ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName", "StateId", "CountryId"), $locSelect1::JOIN_INNER)
//            ->where(array('a.VendorId'=>$vendorId));

        $Subselect= $sql->select();
        $Subselect->from("Vendor_Location")
            ->columns(array("CityId"))
            ->where(array('VendorId'=>$vendorId));

        $locSelect2 = $sql->select();
        $locSelect2->from(array("a"=>'WF_CityMaster'))
            ->columns(array("CityId", "Sel"=>new Expression("1-1"), "CityName", "StateId", "CountryId"))
            ->where->notIn('a.CityId',$Subselect);


//        $locSelect2->combine($locSelect1,'Union ALL');


        $locStmt= $sql->getSqlStringForSqlObject($locSelect2);
        $locResult = $dbAdapter->query($locStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $certSelect = $sql->select();
        $certSelect->from(array("a"=>"Vendor_CertificateTrans"))
            ->columns(array("CertificateId", "Sel"=>new Expression("1")), array("CerDescription"))
            ->join(array("b"=>"Vendor_CertificateMaster"), "a.CertificateId=b.CertificateId", array("CerDescription"), $certSelect::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $certSelStmt= $sql->getSqlStringForSqlObject($certSelect);
        $certSelResult = $dbAdapter->query($certSelStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*certificate select*/
//        $certSelect1 = $sql->select();
//        $certSelect1->from(array("a"=>"Vendor_CertificateTrans"))
//            ->columns(array("CertificateId", "Sel"=>new Expression("1")), array("CerDescription"))
//            ->join(array("b"=>"Vendor_CertificateMaster"), "a.CertificateId=b.CertificateId", array("CerDescription"), $certSelect1::JOIN_INNER)
//            ->where(array('a.VendorId'=>$vendorId));

        $certSubSelect= $sql->select();
        $certSubSelect->from("Vendor_CertificateTrans")
            ->columns(array("CertificateId"))
            ->where(array('VendorId'=>$vendorId));

        $certSelect2 = $sql->select();
        $certSelect2->from(array("a"=>'Vendor_CertificateMaster'))
            ->columns(array("CertificateId", "Sel"=>new Expression("1-1"), "CerDescription"))
            ->where->notIn('a.CertificateId',$certSubSelect);

//        $certSelect2->combine($certSelect1,'Union ALL');

        $certStmt= $sql->getSqlStringForSqlObject($certSelect2);
        $certResult = $dbAdapter->query($certStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Certificate Add*/
        $certSelect1 = $sql->select();
        $certSelect1->from(array("a"=>"Vendor_CertificateTrans"))
            ->columns(array("CertificateId", "Sel"=>new Expression("1")), array("CerDescription"))
            ->join(array("b"=>"Vendor_CertificateMaster"), "a.CertificateId=b.CertificateId", array("CerDescription"), $certSelect1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));

        $certSubSelect= $sql->select();
        $certSubSelect->from("Vendor_CertificateTrans")
            ->columns(array("CertificateId"))
            ->where(array('VendorId'=>$vendorId));

        $certSelect2 = $sql->select();
        $certSelect2->from(array("a"=>'Vendor_CertificateMaster'))
            ->columns(array("CertificateId", "Sel"=>new Expression("1-1"), "CerDescription"))
            ->where->notIn('a.CertificateId',$certSubSelect);

        $certSelect2->combine($certSelect1,'Union ALL');

        $certStmt1= $sql->getSqlStringForSqlObject($certSelect2);
        $certResadd = $dbAdapter->query($certStmt1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Transport select*/

        $transSelect = $sql->select();
        $transSelect->from(array("a"=>"Vendor_Transport"))
            ->columns(array("TransportId", "Sel"=>new Expression("1")), array("TransportName"))
            ->join(array("b"=>"Vendor_TransportMaster"), "a.TransportId=b.TransportId", array("TransportName"), $transSelect::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $transSelStmt= $sql->getSqlStringForSqlObject($transSelect);
        $transSelResult = $dbAdapter->query($transSelStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//        $transSelect1 = $sql->select();
//        $transSelect1->from(array("a"=>"Vendor_Transport"))
//            ->columns(array("TransportId", "Sel"=>new Expression("1")), array("TransportName"))
//            ->join(array("b"=>"Vendor_TransportMaster"), "a.TransportId=b.TransportId", array("TransportName"), $transSelect1::JOIN_INNER)
//            ->where(array('a.VendorId'=>$vendorId));

        $transSubSelect= $sql->select();
        $transSubSelect->from("Vendor_Transport")
            ->columns(array("TransportId"))
            ->where(array('VendorId'=>$vendorId));

        $transSelect2 = $sql->select();
        $transSelect2->from(array("a"=>'Vendor_TransportMaster'))
            ->columns(array("TransportId", "Sel"=>new Expression("1-1"), "TransportName"))
            ->where->notIn('a.TransportId',$transSubSelect);

//        $transSelect2->combine($transSelect1,'Union ALL');

        $transSelectStmt= $sql->getSqlStringForSqlObject($transSelect2);
        $transSelectResult = $dbAdapter->query($transSelectStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Transport Add*/
        $transSelect1 = $sql->select();
        $transSelect1->from(array("a"=>"Vendor_Transport"))
            ->columns(array("TransportId", "Sel"=>new Expression("1")), array("TransportName"))
            ->join(array("b"=>"Vendor_TransportMaster"), "a.TransportId=b.TransportId", array("TransportName"), $transSelect1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));

        $transSubSelect= $sql->select();
        $transSubSelect->from("Vendor_Transport")
            ->columns(array("TransportId"))
            ->where(array('VendorId'=>$vendorId));

        $transSelect2 = $sql->select();
        $transSelect2->from(array("a"=>'Vendor_TransportMaster'))
            ->columns(array("TransportId", "Sel"=>new Expression("1-1"), "TransportName"))
            ->where->notIn('a.TransportId',$transSubSelect);

        $transSelect2->combine($transSelect1,'Union ALL');

        $transSelectStmt1= $sql->getSqlStringForSqlObject($transSelect2);
        $transResadd = $dbAdapter->query($transSelectStmt1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Enclosure select*/
        $enclosureSelect = $sql->select();
        $enclosureSelect->from("Vendor_Enclosure")
            ->columns(array("EnclosureId", "VendorId", "Location", "Date"=>new Expression("Convert(varchar(10),Date,105)"), "Name", "Type", "Remarks"))
            ->where(array("VendorId"=>$vendorId));

        $enclosureStmt= $sql->getSqlStringForSqlObject($enclosureSelect);
        $enclosureResult = $dbAdapter->query($enclosureStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->vendorId = $vendorId;
        $this->_view->cityResult = $cityResult;
        $this->_view->stateResult = $stateResult;
        $this->_view->countryResult = $countryResult;
        $this->_view->locResult = $locResult;
        $this->_view->locSelResult = $locSelResult;
        $this->_view->certResult = $certResult;
        $this->_view->certResadd = $certResadd;
        $this->_view->transResadd = $transResadd;
        $this->_view->certSelResult = $certSelResult;
        $this->_view->transResult = $transSelectResult;
        $this->_view->transSelResult = $transSelResult;
        $this->_view->enclosureResult = $enclosureResult;
        return $this->_view;
    }
    public function manufactureDetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId = $this->params()->fromRoute('vendorid');
        $vendorId=$this->bsf->decode($vendorId);
        //$vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
       // Print_r($vendorId);die;
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){


            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $postParams = $request->getPost();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $select = $sql->delete();
                $select->from('Vendor_SupplierDet')
                    ->where(array('VendorId'=>$vendorId));
                $select->where->and->expression('SupplierType like ?', 'M');
                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Manufacture
                $cid =explode(",", $postParams['hidcid']);
                foreach($cid as $count){
                    if($count!="" || $count!=0)	{
                        $insert = $sql->insert('Vendor_SupplierDet');
                        $newData = array(
                            'SupplierVendorId'  => $count,
                            'VendorId'  => $vendorId,
                            'SupplierType'  => 'M'
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/manufacture-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/dealer-detail', array('controller' => 'index', 'action' =>'dealer-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//                $this->redirect()->toRoute('vendor/dealer-detail', array('controller' => 'index','action' => 'dealer-detail','vendorid' =>  $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        //Manufacture
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_SupplierDet"))
            ->columns(array("VendorId"=>"SupplierVendorId", "Sel"=>new Expression("1")), array("VendorName"))
            ->join(array("c"=>"Vendor_Master"), "a.SupplierVendorId=c.VendorId", array("VendorName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.SupplierType like ?', 'M');
        $statementSelect2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelWG  = $dbAdapter->query($statementSelect2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_SupplierDet")
            ->columns(array("SupplierVendorId"))
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_Master'))
            ->columns(array("VendorId", "Sel"=>new Expression("1-1"), "VendorName"))
            ->where(array("a.Manufacture"=>1))
            ->where->notIn('a.VendorId',$Subselect2)
            ->and->expression('a.VendorId not like ?', $vendorId);
        //$select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsWG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->vendorId = $vendorId;
        $this->_view->ResultWG = $resultsWG;
        $this->_view->ResultSelWG = $resultsSelWG;
        return $this->_view;
    }
    public function dealerDetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $postParams = $request->getPost();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $select = $sql->delete();
                $select->from('Vendor_SupplierDet')
                    ->where(array('VendorId'=>$vendorId));
                $select->where->and->expression('SupplierType like ?', 'D');
                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Dealer
                $cid =explode(",", $postParams['hidcid']);
                foreach($cid as $count){
                    if($count!="" || $count!=0)	{
                        $insert = $sql->insert('Vendor_SupplierDet');
                        $newData = array(
                            'SupplierVendorId'  => $count,
                            'VendorId'  => $vendorId,
                            'SupplierType'  => 'D'
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/dealer-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/distributor-detail', array('controller' => 'index', 'action' =>'distributor-detail', 'vendorid' => $this->bsf->encode($vendorId)));
                }
//                $this->redirect()->toRoute('vendor/distributor-detail', array('controller' => 'index','action' => 'distributor-detail','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
        //Dealer
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_SupplierDet"))
            ->columns(array("VendorId"=>"SupplierVendorId", "Sel"=>new Expression("1")), array("VendorName"))
            ->join(array("c"=>"Vendor_Master"), "a.SupplierVendorId=c.VendorId", array("VendorName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.SupplierType like ?', 'D');
        $statementSelect2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelWG  = $dbAdapter->query($statementSelect2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_SupplierDet")
            ->columns(array("SupplierVendorId"))
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_Master'))
            ->columns(array("VendorId", "Sel"=>new Expression("1-1"), "VendorName"))
            ->where(array("a.Dealer"=>1))
            ->where->notIn('a.VendorId',$Subselect2)
            ->and->expression('a.VendorId not like ?', $vendorId);
        //$select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsWG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->vendorId = $vendorId;
        $this->_view->ResultWG = $resultsWG;
        $this->_view->ResultSelWG = $resultsSelWG;
        return $this->_view;
    }
    public function distributorDetailAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){

            $postParams = $request->getPost();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $select = $sql->delete();
                $select->from('Vendor_SupplierDet')
                    ->where(array('VendorId'=>$vendorId));
                $select->where->and->expression('SupplierType like ?', 'S');
                $DelStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Distributor
                $cid =explode(",", $postParams['hidcid']);
                foreach($cid as $count){
                    if($count!="" || $count!=0)	{
                        $insert = $sql->insert('Vendor_SupplierDet');
                        $newData = array(
                            'SupplierVendorId'  => $count,
                            'VendorId'  => $vendorId,
                            'SupplierType'  => 'S'
                        );
                        $insert->values($newData);
                      $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $connection->commit();
                if($postParams['saveExit']=='2') {
                    $this->redirect()->toRoute('vendor/distributor-detail', array('controller' => 'index', 'action' => 'vendor-register'));
                } else {
                    $this->redirect()->toRoute('vendor/vehicleregister', array('controller' => 'index', 'action' =>'vehicleregister', 'vendorid' => $this->bsf->encode($vendorId)));
                }

//                $this->redirect()->toRoute('vendor/vehicleregister', array('controller' => 'index','action' => 'vehicleregister','vendorid' => $this->bsf->encode($vendorId)));
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        //Distributor
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_SupplierDet"))
            ->columns(array("VendorId"=>"SupplierVendorId", "Sel"=>new Expression("1")), array("VendorName"))
            ->join(array("c"=>"Vendor_Master"), "a.SupplierVendorId=c.VendorId", array("VendorName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.SupplierType like ?', 'S');
        $statementSelect2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelWG  = $dbAdapter->query($statementSelect2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_SupplierDet")
            ->columns(array("SupplierVendorId"))
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_Master'))
            ->columns(array("VendorId", "Sel"=>new Expression("1-1"), "VendorName"))
            ->where(array("a.Distributor"=>1))
            ->where->notIn('a.VendorId',$Subselect2)
            ->and->expression('a.VendorId not like ?', $vendorId);
        //$select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsWG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->vendorId = $vendorId;
        $this->_view->ResultWG = $resultsWG;
        $this->_view->ResultSelWG = $resultsSelWG;
        return $this->_view;
    }
	public function servicemasterDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
			
			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		$select = $sql->select();
		$select->from(array("a"=>"Vendor_ServiceMaster"))
			   ->columns(array("ServiceId","ServiceCode","ServiceName","ServiceDescription","ServiceGroupId","UnitId"), array("ServiceGroupName"), array("UnitName"))
			   ->join(array("b"=>"Vendor_ServiceGroup"), "a.ServiceGroupId=b.ServiceGroupId", array("ServiceGroupName"), $select::JOIN_LEFT)
				->join(array("c"=>"Proj_UOM"), "a.UnitId=c.UnitId", array("UnitName"), $select::JOIN_LEFT)			   
				->order('a.ServiceCode ')
				->order('a.ServiceName ');
				
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsVen1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->Result = $resultsVen1;			
		return $this->_view;
    }



	public function uploadxlAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				$resp['data'] = array();
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{					
					foreach(range(1, $postParam['len']) as $i){
						$select = $sql->select();		
						$select->from('Vendor_Master')
							->where(array("VendorName"=>$postParam['vendor_'.$i.'_1']));
						$statement = $sql->getSqlStringForSqlObject($select);
						$resultsRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						if(count($resultsRes) == 0){
							/*Vendor details*/
							$vendorInsert = $sql->insert('Vendor_Master');
							$vendorInsert->values(array('VendorName' => $postParam['vendor_'.$i.'_1'], 'Supply' => $postParam['vendor_'.$i.'_9'],	
													'Contract' => $postParam['vendor_'.$i.'_10'], 'Service' => $postParam['vendor_'.$i.'_11'],
													'RegAddress' => $postParam['vendor_'.$i.'_2']
												));
							$vendorStatement = $sql->getSqlStringForSqlObject($vendorInsert);
							$dbAdapter->query($vendorStatement, $dbAdapter::QUERY_MODE_EXECUTE);				
							$vid = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$contactInsert = $sql->insert('Vendor_Contact');
							$contactInsert->values(array('ContactNo1'  => $postParam['vendor_'.$i.'_3'], 'Email1'  => $postParam['vendor_'.$i.'_4'],
														'VendorID'=>$vid
											));
							$contactStatement = $sql->getSqlStringForSqlObject($contactInsert);
							$dbAdapter->query($contactStatement, $dbAdapter::QUERY_MODE_EXECUTE);								
							
							
							$statutoryInsert = $sql->insert('Vendor_Statutory');
							$statutoryInsert->values(array('PANNo'  => $postParam['vendor_'.$i.'_8'], 'CSTNo'  => $postParam['vendor_'.$i.'_5'],
															'TINNo' => $postParam['vendor_'.$i.'_7'],	'TNGSTNo'  => $postParam['vendor_'.$i.'_6'],
															'VendorID'=>$vid
													));
							$statutoryStatement = $sql->getSqlStringForSqlObject($statutoryInsert);
							$dbAdapter->query($statutoryStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							array_push($resp['data'], $i);
						}
					}
					$connection->commit();					
				}
				catch(PDOException $e){
					$connection->rollback();
					//print "Error!: " . $e->getMessage() . "</br>";
					array_push($resp['data'], "Error!: " . $e->getMessage());
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){
			$files = $request->getFiles();
			if($files['files']['tmp_name']){
				if(!$files['files']['error']){
					$inputFileName = $files['files']['tmp_name'];
					$extension = strtoupper(pathinfo($files['files']['name'], PATHINFO_EXTENSION));
					if($extension == 'XLSX' || $extension == 'ODS' || $extension == 'XLS'){
						$this->_view->files = $files;
						
						$select = $sql->select();		
						$select->from(array('a' => 'Vendor_Master'))
							->columns(array('VendorName'));
						$statement = $sql->getSqlStringForSqlObject($select);
						$resultsRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$vendorName = array();
						foreach($resultsRes as $data){
							array_push($vendorName, strtolower($data['VendorName']));
						}
						$this->_view->vendorName = $vendorName;
					}
					else{
						$this->_view->error = "Please upload an XLSX, XLS, ODS file";
					}				
				}
				else{
					$this->_view->error = $files['files']['error'];
				}			
			}
		}
		return $this->_view;
	}
	public function unapproveVendorAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp =  array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'approveVendor'){
					$appVendor = $sql->update('Vendor_Master');
					$appVendor->set(array('Approve'=>'Y'))
							->where(array('VendorId'=>$postParam['vendorId']));
							
					$statement = $sql->getSqlStringForSqlObject($appVendor);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					array_push($resp, array('success'=>'success'));
				}
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			//$response->setContent($statement);
			return $response;				
		}
		else if($request->isPost()){
			
		}
		
		$select = $sql->select();
		$select->from(array('a' =>'Vendor_Master'))
				->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId',array('CityName'),$select:: JOIN_LEFT)
				->columns(array(new Expression("a.VendorId,a.VendorName,CASE WHEN a.Supply=1 THEN 'Yes' Else 'No' END as Supply,
													CASE WHEN a.Contract=1 THEN 'Yes' Else 'No' END as Contract,
													CASE WHEN a.Service=1 THEN 'Yes' Else 'No' END as Service,
													a.CityId,a.Pincode,a.RegAddress,CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve, LogoPath,Convert(varchar(10),CreatedDate,105) as CreatedDate")),array('CityName'))
				->where(array("approve"=>"N"))
				->order('a.vendorName asc');
		$statement = $sql->getSqlStringForSqlObject($select);
		$vendorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	

		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		
		$this->_view->vendorList = $vendorList;
		return $this->_view;
    }
	public function vendorLoginAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				$selectVendor = $sql->select();
				$selectVendor->from("Vendor_Master")
							->where(array("UserName"=>$postParam['email'], "Password"=>$postParam['password']));
				$statement = $sql->getSqlStringForSqlObject($selectVendor);
				$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$resp['data'] = 1;
				foreach($results as $data){
					if($data['AllowOnline'] == 1)
						$resp['data'] = 2;
					else
						$resp['data'] = 3;
				}			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){

		}
		return $this->_view;
    }
	
	public function contactDetailtestAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		$vendorId= $this->params()->fromRoute('vendorid');
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
			
			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
        else if($request->isPost()){
			$postParams = $request->getPost();
			$vendorId = $postParams['VendorId'];
			if($vendorId!=0){
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try{					
					if($postParams['addmore']==0){							
						$insert = $sql->insert('Vendor_Contact');
						$insert->values(array(
							'VendorID'  => $vendorId,
							'CAddress'  => $postParams['regaddress'],								
							'Phone1' => $postParams['phoneno'],
							'Fax1' => $postParams['faxno'],
							'CPerson1'  => $postParams['contactperson'],
							'ContactNo1'  => $postParams['contactno'],
							'Email1'  => $postParams['contactemail'],
							'WebName'  => $postParams['webaddress'],
							'ContactType'  => $postParams['contactType']									
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$results1   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
					}
					else{
						$select = $sql->delete();
						$select->from('Vendor_Contact')
									->where(array('VendorId'=>$vendorId));
								
						$DelStatement = $sql->getSqlStringForSqlObject($select);			
						$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						$rowCount =$postParams['RowCount'];
						foreach(range(1,$rowCount) as $count){
						if($postParams['Contact_Address_'.$count]="" && $postParams['Contact_Person_'.$count]="" && $postParams['Contact_No_'.$count]="" && $postParams['Contact_Email_'.$count]="" && $postParams['Contact_Web_'.$count]="" )
						{
								$insert = $sql->insert('Vendor_Contact');
								$insert->values(array(
									'VendorID'  => $vendorId,
									'CAddress'  => $postParams['Contact_Address_'.$count],								
									//'Phone1' => $postParams['phoneno_'.$count],
									//'Fax1' => $postParams['faxno_'.$count],
									'CPerson1'  => $postParams['Contact_Person_'.$count],
									'ContactNo1'  => $postParams['Contact_No_'.$count],
									'Email1'  => $postParams['Contact_Email_'.$count],
									'WebName'  => $postParams['Contact_Web_'.$count],
									'ContactType'  => $postParams['contact_Type_'.$count]
													
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);	
							}
						}	
								
					}
					$connection->commit();
					$VendorDetailList = $vendorId;
					if($VendorDetailList!=0){
						$this->redirect()->toRoute('vendor/statutory-detail', array('controller' => 'index','action' => 'statutory-detail','vendorid' => $vendorId));
					}		
				}
				catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";				
				}	
			}			
		}
		
		$select = $sql->select();
		$select->from('Vendor_Contact')
			   ->columns(array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'))
			   ->where->like('VendorId', $vendorId );
        $statement = $sql->getSqlStringForSqlObject($select);
		$contactVendor   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->VendorId = $this->params()->fromRoute('vendorid');
		$this->_view->contactVendor = $contactVendor;			
		return $this->_view;
    }
	public function vendorProfileAction(){
	if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		$vendorId= $this->params()->fromRoute('vendorid');
		if($request->isPost()){
			$postParams = $request->getPost();
            $vendorId = $postParams['VendorId'];
            if($vendorId!=0){
                $this->redirect()->toRoute('vendor/basic-detail', array('controller' => 'index','action' => 'basic-detail','vendorid' => $vendorId));
            }
		}
		$supplyGradeName="";
		$supplyContractName="";
		$supplyServiceName="";
		
		$select = $sql->select();
		$select->from(array("a"=>"Vendor_Master"))
			   ->columns(array('VendorId','VendorName','Supply','Contract','Service','CityId','Pincode','RegAddress','LogoPath'))
			   ->join(array('b'=>'WF_CityMaster'), 'a.CityId=b.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
			   ->join(array('c'=>'WF_StateMaster'), 'c.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
			   ->join(array('d' => 'WF_CountryMaster'), 'd.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)				   
			   ->where(array('VendorId'=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$basicResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$selectvendorvalid = $sql->select();
		$selectvendorvalid->from(array("a"=>"Vendor_Registration"))
			   ->columns(array("STDate"=>new Expression("Convert(varchar(10),a.STDate,105)"),"CTDate"=>new Expression("Convert(varchar(10),a.CTDate,105)"),
			   "HTDate"=>new Expression("Convert(varchar(10),a.HTDate,105)")))	   
			   ->where(array('a.VendorId'=>$vendorId));
		$statementvendorvalid = $sql->getSqlStringForSqlObject($selectvendorvalid);
		$vendorvalidResult = $dbAdapter->query($statementvendorvalid, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$select = $sql->select();
		$select->from(array('a' =>'Vendor_Contact'))
                ->join(array('b'=>'Vendor_Master'), 'a.VendorId=b.VendorId', array('PhoneNumber'), $select:: JOIN_LEFT)
			   ->columns(array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'))
            ->where(array('a.VendorId'=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$contactVendor   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$select = $sql->select();
		$select->from(array('a' => 'Vendor_Statutory'))
		   ->join(array('b'=> 'Vendor_Master'), 'a.VendorId=b.VendorId', array('SupplyType', 'Company'), $select:: JOIN_LEFT)			  
			->columns(array('FirmType','EYear','PANNo','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo',
							'ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),array('SupplyType', 'Company'))
			 ->where(array('a.VendorId'=>$vendorId));
			
		$statement = $sql->getSqlStringForSqlObject($select);
		$statutoryResults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//SupplyGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'S');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraSupply = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$supplyPoint= $resultsGraSupply['Amount'];

		if($supplyPoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$supplyPoint."' AND TValue >= '".$supplyPoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraSupply2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyGradeName= $resultsGraSupply2['GradeName'];
		}
		//ContractGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'C');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraContract = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$contractPoint= $resultsGraContract['Amount'];

		if($contractPoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$contractPoint."' AND TValue >= '".$contractPoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraContract2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyContractName= $resultsGraContract2['GradeName'];
		}
		
		//ServiceGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'R');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraService = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$servicePoint= $resultsGraService['Amount'];

		if($servicePoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$servicePoint."' AND TValue >= '".$servicePoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraService2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyServiceName= $resultsGraService2['GradeName'];
		}

        //Bank Details
        $select = $sql->select();
        $select->from(array('a' => 'Vendor_StatutoryBankDetail'))
            ->columns(array('BankAccountNo','AccountType' => new Expression("case when a.AccountType = 'C' then 'Current A/C' when a.AccountType = 'C' then 'Savings A/C' else '' end ")
            ,'BankName','BranchName','BranchCode','MICRCode','IFSCCode','DefaultBank'=> new Expression("case when a.DefaultBank = 1 then 'Yes' else 'No' end")))
            ->where(array('VendorId'=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $bankResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //experience-details
        $select = $sql->select();
        $select->from(array('a'=>'Vendor_Experience'))
            ->columns(array(new Expression("ExperienceId,WorkDescription ,ClientName,CAST(a.Value As Decimal(18,3)) AS [Value],Period,Case when a.Type = '2' then 'Contractor' when a.Type = '1' then 'Supplier' when a.Type = '3' then 'Service Provider' end as Type
            , Convert(Varchar(10),FromDate,105) as FromDate, Convert(Varchar(10),ToDate,105) as ToDate")))
            ->where(array('VendorId'=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $experienceResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // terms details
        $select = $sql->select();
        $select->from('Vendor_Terms')
            ->columns(array('CreditDays','MaxLeadTime','TermsAndCondition'))
            ->where(array("VendorId"=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $termResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Vendor registration
        $select = $sql->select();
        $select->from(array('a'=>'Vendor_Registration'))
            ->join(array('b'=> 'Vendor_Master'), 'a.VendorId=b.VendorId', array('VendorName','Supply','Contract','Service'), $select:: JOIN_LEFT)
            ->columns(array('RegisterId','RegDate'=> new Expression("FORMAT(a.RegDate, 'dd-MM-yyyy')"),'RegNo','Remarks','SLifeTime','CLifeTime','HLifeTime'
            ,'SFDate'=> new Expression("FORMAT(a.SFDate, 'dd-MM-yyyy')"),'STDate'=> new Expression("FORMAT(a.STDate, 'dd-MM-yyyy')")
            ,'CFDate'=> new Expression("FORMAT(a.CFDate, 'dd-MM-yyyy')"),'CTDate'=> new Expression("FORMAT(a.CTDate, 'dd-MM-yyyy')")
            ,'HFDate'=> new Expression("FORMAT(a.HFDate, 'dd-MM-yyyy')"),'HTDate'=> new Expression("FORMAT(a.HTDate, 'dd-MM-yyyy')")))
            ->where(array('a.VendorId'=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $regResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //vehicle register
        $select = $sql->select();
        $select -> from('Vendor_VehicleMaster')
            -> columns(array('VendorId','VehicleId','VehicleRegNo','VehicleName'))
            -> where(array("VendorId"=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $vehicleRegister=$dbAdapter->query($statement,$dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Basic Details
        $select = $sql->select();
        $select->from(array("a"=>"Vendor_Master"))
            ->columns(array('VendorId','VendorName','Supply','Contract','Cnc','Service','CityId','Pincode','PANNo','SupplyType','Company','AadharNo','RegAddress','Ssi','AllowOnline', 'LogoPath','Manufacture','Dealer','Distributor','ServiceTypeId','RaBill','Code'))
            ->join(array('b'=>'WF_CityMaster'), 'a.CityId=b.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
            ->join(array('c'=>'WF_StateMaster'), 'c.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
            ->join(array('d' => 'WF_CountryMaster'), 'd.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)
            ->where(array('VendorId'=>$vendorId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $basicResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $workSelect = $sql->select();
        $workSelect->from('Vendor_ServiceType')
            ->columns(array('ServiceType', 'ServiceTypeId'));
        $workStatement = $sql->getSqlStringForSqlObject($workSelect);
        $workResult = $dbAdapter->query($workStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Financial Details
        $finanSelect =  $sql->select();
        $finanSelect->from("Vendor_TurnOver")
                    ->where(array("VendorId"=>$vendorId));
        $finanStmt = $sql->getSqlStringForSqlObject($finanSelect);
        $financialResult = $dbAdapter->query($finanStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //supply
        $supplyView = $sql->select();
        $supplyView->from(array("a"=>"Vendor_MaterialTrans"))
            ->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("*"), $supplyView::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("*"), $supplyView::JOIN_LEFT)
            ->where(array("VendorId"=>$vendorId));
        $supplyViewStmt = $sql->getSqlStringForSqlObject($supplyView);
        $supplyViewResult = $dbAdapter->query($supplyViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $logisticView = $sql->select();
        $logisticView->from("Vendor_Logistics")
            ->where(array("VendorId"=>$vendorId));
        $logisticViewStmt = $sql->getSqlStringForSqlObject($logisticView);
        $logisticViewResult = $dbAdapter->query($logisticViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //resource
        $manView = $sql->select();
        $manView->from(array("a"=>"Vendor_ManPower"))
            ->columns(array("ManPowerTransId", "VendorId", "Qty"))
            ->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("ResourceId", "ResourceName", "TypeId", "ResourceGroupId", "Code"), $manView::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("UnitId", "UnitName", "UnitDescription"), $manView::JOIN_LEFT)
            ->where(array("VendorId"=>$vendorId));
        $manViewStmt = $sql->getSqlStringForSqlObject($manView);
        $manViewResult = $dbAdapter->query($manViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $machineView = $sql->select();
        $machineView->from(array("a"=>"Vendor_Machinery"))
            ->join(array('b' => 'Proj_Resource'), 'a.Resource_ID=b.ResourceId', array("*"), $machineView::JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'c.UnitId=b.UnitId', array("*"), $machineView::JOIN_LEFT)
            ->where(array("VendorId"=>$vendorId));
        $machineViewStmt = $sql->getSqlStringForSqlObject($machineView);
        $machineViewResult = $dbAdapter->query($machineViewStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $techSelect = $sql->select();
        $techSelect->from("Vendor_TechPersons")
            ->where(array("VendorId"=>$vendorId));
        $techStmt = $sql->getSqlStringForSqlObject($techSelect);
        $techResult = $dbAdapter->query($techStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //WorkGroupMaster
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_WorkGroup"))
            ->columns(array("WorkGroupId", "Sel"=>new Expression("1")))
            ->join(array("c"=>"Proj_WorkGroupMaster"), "a.WorkGroupId=c.WorkGroupId", array("WorkGroupName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $statementSel2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelWG  = $dbAdapter->query($statementSel2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //ActivityGroupMaster
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_ActivityTrans"))
            ->columns(array("ResourceGroupId", "Sel"=>new Expression("1")), array("ResourceGroupName"))
            ->join(array("c"=>"Proj_ResourceGroup"), "a.ResourceGroupId=c.ResourceGroupId", array("ResourceGroupName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('c.TypeId like ?', '4');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_ActivityTrans")
            ->columns(array("ResourceGroupId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));

        $select2 = $sql->select();
        $select2->from(array("a"=>'Proj_ResourceGroup'))
            ->columns(array("ResourceGroupId", "Sel"=>new Expression("1-1"), "ResourceGroupName"))
            ->where->notIn('a.ResourceGroupId',$Subselect2);
        $select2->where->and->expression('a.TypeId like ?', '4');

        $select2->combine($select1,'Union ALL');

        $select3 = $sql->select();
        $select3->from(array("g"=>'Proj_ResourceGroup'))
            //->columns(array("Id","Name","Type"));
            ->columns(array("ResourceGroupId", "Sel", "ResourceGroupName"))
            ->order("ResourceGroupName asc");
        $statement2 = $sql->getSqlStringForSqlObject($select3);
        $select3 = $sql->select();
        $select3->from(array("g"=>$select2))
            ->columns(array("ResourceGroupId" , "Sel", "ResourceGroupName"))
            ->order("ResourceGroupName asc");
        $select3->where(array('sel=1'));
        $statement2 = $sql->getSqlStringForSqlObject($select3);
        $resultsAG  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Manufacture
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_SupplierDet"))
            ->columns(array("VendorId"=>"SupplierVendorId", "Sel"=>new Expression("1")), array("VendorName"))
            ->join(array("c"=>"Vendor_Master"), "a.SupplierVendorId=c.VendorId", array("VendorName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.SupplierType like ?', 'M');
        $statementSelect2 = $sql->getSqlStringForSqlObject($select1);
        $resultsSelWGLits  = $dbAdapter->query($statementSelect2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Dealer
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_SupplierDet"))
            ->columns(array("VendorId"=>"SupplierVendorId", "Sel"=>new Expression("1")), array("VendorName"))
            ->join(array("c"=>"Vendor_Master"), "a.SupplierVendorId=c.VendorId", array("VendorName"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.SupplierType like ?', 'D');
        $statementSelect2 = $sql->getSqlStringForSqlObject($select1);
        $resultSelWG  = $dbAdapter->query($statementSelect2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        //Assessment details-supply
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType",'RN'=> new Expression("row_number() over (order by CheckListId)")))
            ->group(new expression('AssessmentType,CheckListId'))
            ->where(array('Supply'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsVen1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Trans
        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            //->where('a.VendorId = ".$vendorId."');
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'S');

        $Subselect2 = $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'S');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Supply like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Assessment details-Contract
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType",'CRN' => new Expression("row_number() over (order by CheckListId)")))
            ->group( new expression('AssessmentType,CheckListId'))
            ->where(array('Contract'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsContractVen1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'C');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            //->where(array('VendorId'=>$vendisXmlHttpRequestorId));
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'C');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Contract like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsContractVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Assessment details-Service
        $select = $sql->select();
        $select->from('Vendor_CheckListMaster')
            ->columns(array("AssessmentType", 'SRN' => new Expression("row_number() over (order by CheckListId)")))
            ->group( new expression('AssessmentType,CheckListId'))
            ->where(array('Service'=>'1'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsServiceVen1   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select1 = $sql->select();
        $select1->from(array("a"=>"Vendor_CheckListTrans"))
            ->columns(array("CheckListId","Points", "Sel"=>new Expression("1")), array("Description","MaxPoint","AssessmentType"))
            ->join(array("c"=>"Vendor_CheckListMaster"), "a.CheckListId=c.CheckListId", array("Description","MaxPoint","AssessmentType"), $select1::JOIN_INNER)
            //->where('a.VendorId = ".$vendorId."');
            ->where(array('a.VendorId'=>$vendorId));
        $select1->where->and->expression('a.RegType like ?', 'R');

        $Subselect2= $sql->select();
        $Subselect2->from("Vendor_CheckListTrans")
            ->columns(array("CheckListId"))
            //->where('VendorId=5');
            ->where(array('VendorId'=>$vendorId));
        $Subselect2->where->and->expression('RegType like ?', 'R');

        $select2 = $sql->select();
        $select2->from(array("a"=>'Vendor_CheckListMaster'))
            ->columns(array("CheckListId", "Points"=>new Expression("1-1"), "Sel"=>new Expression("1-1"), "Description","MaxPoint","AssessmentType"))
            ->where->notIn('a.CheckListId',$Subselect2);
        $select2->where->and->expression('a.Service like ?', '1');
        $select2->combine($select1,'Union ALL');

        $statement2 = $sql->getSqlStringForSqlObject($select2);
        $resultsServiceVen2  = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Branch
        $citySelect =  $sql->select();
        $citySelect->from("WF_CityMaster");
        $cityStmt = $sql->getSqlStringForSqlObject($citySelect);
        $cityResult = $dbAdapter->query($cityStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $branchSelect = $sql->select();
        $branchSelect->from(array("a"=>"Vendor_Branch"))
            ->join(array("b"=>"WF_CityMaster"), "b.CityId=a.CityId", array("CityName"), $branchSelect::JOIN_LEFT)
            ->where(array('VendorId' => $vendorId));

        $branchStmt = $sql->getSqlStringForSqlObject($branchSelect);
        $branchResult = $dbAdapter->query($branchStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $resp=array();
        foreach($branchResult as $data){
            $contactSelect = $sql->select();
            $contactSelect->from("Vendor_BranchContactDetail")
                ->where(array('BranchId' => $data['BranchId']));

            $contactStmt = $sql->getSqlStringForSqlObject($contactSelect);
            $data['contact'] = $dbAdapter->query($contactStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            array_push($resp, $data);
        }

        /*Ajax Request*/
		if($request->isXmlHttpRequest()){
			//$resp = array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'RegisterVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'R'))
								->where(array("VendorId"=>$postParam['vendor_id']));
				    $statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				else if($postParam['mode'] == 'BlockVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'B'))
								->where(array("VendorId"=>$postParam['vendor_id']));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				else if($postParam['mode'] == 'AskVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'A'))
								->where(array("VendorId"=>$postParam['vendor_id']));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}				
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){

		}
		
		$this->_view->rs = $basicResult;
		$this->_view->vendorvalidResult = $vendorvalidResult;
		$this->_view->VendorId = $this->params()->fromRoute('vendorid');
		$this->_view->contactVendor = $contactVendor;
		$this->_view->statutoryResults = $statutoryResults;
		$this->_view->SupplyGrade = $supplyGradeName;
		$this->_view->ContractGrade = $supplyContractName;
		$this->_view->ServiceGrade = $supplyServiceName;
		$this->_view->bankResult = $bankResult;
		$this->_view->experienceResult = $experienceResult;
		$this->_view->termResult = $termResult;
		$this->_view->regResult = $regResult;
		$this->_view->basicResult = $basicResult;
		$this->_view->workResult = $workResult;
		$this->_view->vehicleRegister = $vehicleRegister;
        $this->_view->financialResult = $financialResult;
        $this->_view->supplyViewResult = $supplyViewResult;
        $this->_view->logisticViewResult = $logisticViewResult;
        $this->_view->manStmt = $manViewResult;
        $this->_view->machineViewResult = $machineViewResult;
        $this->_view->techResult = $techResult;
        $this->_view->resultsSelWG = $resultsSelWG;
        $this->_view->resultsAG = $resultsAG;
        $this->_view->resultsSelWGList = $resultsSelWGLits;
        $this->_view->resultSelWG = $resultSelWG;
        $this->_view->mainResult = $resultsVen1;
        $this->_view->subResult = $resultsVen2;
        $this->_view->mainResultContract = $resultsContractVen1;
        $this->_view->subResultContract = $resultsContractVen2;
        $this->_view->mainResultService = $resultsServiceVen1;
        $this->_view->subResultService = $resultsServiceVen2;
        $this->_view->branchCity = $cityResult;
        $this->_view->vendorId = $vendorId;
        $this->_view->branchResult = json_encode($resp);
        return $this->_view;
	}
	
	public function testAction(){
		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		$select = $sql->select();
		$select->from(array("a"=>"VM_RequestDecision"))
			   ->columns(array('DecisionId','RDecisionNo','DecDate'))
			   ->join(array('b'=>'VM_ReqDecTrans'), 'a.DecisionId=b.DecisionId', array(), $select:: JOIN_INNER)
			   ->join(array('c'=>'VM_RequestRegister'), 'b.RequestId=c.RequestId', array(), $select:: JOIN_INNER)				   
			   ->where(array('c.CostCentreId'=>array('1','2'),
					'a.RequestType'=>array('2')
			   ))
			   ->group(new expression('a.DecisionId,a.RDecisionNo,a.DecDate'))
			   ->order('a.DecDate desc');
			   
			   	$select = $sql->select();
				$select->from(array("a"=>"Vendor_Master"))
					   ->columns(array('VendorId','VendorName'))						   				   
					   ->where(array('a.Approve'=>array('Y')  ))
					   ->order('a.VendorName');
				$select = $sql->select(); 
					$select->from(array("a"=>"WF_TermsMaster"))
						->columns(array('TermsId', 'Title'))
						->where->like('a.TermType', 'S');
					$select->order("a.Title");
			
			//Resource			
			$select = $sql->select();		
			$select->from(array('a'=>'VM_ReqDecQtyTrans'))
				->columns(array("Quantity"=>new Expression("sum(a.IndentQty)")), array("ResourceId"), array("Code","ResourceName","UnitId"), array("UnitName"))
				->join(array('b'=>'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array("ResourceId"), $select:: JOIN_INNER)
				->join(array('c'=>'Proj_Resource'), 'b.ResourceId=c.ResourceId', array("Code","ResourceName","UnitId"), $select:: JOIN_INNER)
				->join(array('d'=>'Proj_UOM'), 'c.UnitId=d.UnitId', array("UnitName"), $select:: JOIN_LEFT)			   
				->where(array('a.DecisionId'=>array('1','2')))
				->group(new expression('b.ResourceId,c.Code,c.ResourceName,c.UnitId,d.UnitName'));

			//Decision			
			$select = $sql->select();		
			$select->from(array('a'=>'VM_ReqDecQtyTrans'))
				->columns(array("TransId","DecisionId","ReqTransId","IndentQty"), array("RDecisionNo","DecDate"), array("ResourceId"))
				->join(array('a1'=>'VM_RequestDecision'), 'a.DecisionId=a1.DecisionId', array("RDecisionNo","DecDate"), $select:: JOIN_INNER)
				->join(array('b'=>'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array("ResourceId"), $select:: JOIN_INNER)			   
				->where(array('a.DecisionId'=>array('1','2'),
				'b.ResourceId'=>array('1') ));
					
					
					
					
					//Trans	
					
		/*$select1 = $sql->select(); 
		$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
			->columns(array("Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
			->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $select1::JOIN_INNER)
			->where(array('a.DecisionId'=>array('45','46')));
			 
		$select2 = $sql->select(); 
		$select2->from(array("a"=>'VM_RFQTrans'))
			->columns(array("Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
			->where(array('a.DecisionId'=>array('45','46')));			
		$select2->combine($select1,'Union ALL');

		$selectmaster = $sql->select(); 
		$selectmaster->from(array("g"=>$select2))
		->columns(array("ResourceId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) ")),array("Code","ResourceName","UnitId"))
		->join(array("b"=>"Proj_Resource"), "g.ResourceId=b.ResourceId", array("Code","ResourceName","UnitId"), $selectmaster::JOIN_INNER)
		->group(new expression('g.ResourceId,b.Code,b.ResourceName,b.UnitId'));
		
		echo $statement = $sql->getSqlStringForSqlObject($selectmaster);*/
		
		/*$selectDecs1 = $sql->select(); 
		$selectDecs1->from(array("a"=>"VM_ReqDecQtyTrans"))
			->columns(array("TransId","DecisionId","Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
			->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $selectDecs1::JOIN_INNER)
			->where(array('a.DecisionId'=>array('45','46'),
			'b.ResourceId'=>array('1002')  ));
		
		$selectDecs2 = $sql->select(); 
		$selectDecs2->from(array("a"=>'VM_RFQTrans'))
			->columns(array("TransId"=>"DecisionTransId","DecisionId","Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
			->where(array('a.DecisionId'=>array('45','46'), 
			'a.ResourceId'=>array('1002') ));			
		$selectDecs2->combine($selectDecs1,'Union ALL');

		$selectDecsmaster = $sql->select(); 
		$selectDecsmaster->from(array("g"=>$selectDecs2))
		->columns(array("TransId","DecisionId","ResourceId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) ")),array("RDecisionNo","DecDate"))
		->join(array("a1"=>"VM_RequestDecision"), "g.DecisionId=a1.DecisionId", array("RDecisionNo","DecDate"), $selectDecsmaster::JOIN_INNER)
		->group(new expression('g.TransId,g.DecisionId,g.ResourceId,a1.RDecisionNo,a1.DecDate'));
		*/
		
		$select1 = $sql->select(); 
			$select1->from(array("a"=>"VM_ReqDecQtyTrans"))
					->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
					->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $select1::JOIN_INNER)
					->where(array('a.DecisionId'=>array('45','46')));
				 
			$select2 = $sql->select(); 
			$select2->from(array("a"=>'VM_RFQTrans'))
					->columns(array("hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("(a.Quantity*(-1))"),"ResourceId"))
					->where(array('a.DecisionId'=>array('45','46') ));							
			$select2->combine($select1,'Union ALL');
			
			$select3 = $sql->select(); 
			$select3->from(array("a"=>'VM_RFQTrans'))
					->columns(array("hidQty"=>new Expression("a.Quantity"),"Quantity"=>new Expression("1-1"),"ResourceId"))
					->where(array('a.DecisionId'=>array('45','46'),
							'a.RFQId'=>array('1')	));							
			$select3->combine($select2,'Union ALL');
			
			$resSelect = $sql->select(); 
			$resSelect->from(array("g"=>$select3))
					->columns(array("ResourceId","Quantity"=>new Expression("sum(isnull(g.Quantity,0)) "),"hidQty"=>new Expression("sum(isnull(g.hidQty,0)) ")),array("Code","ResourceName","UnitId"), array("UnitName"))
					->join(array("b"=>"Proj_Resource"), "g.ResourceId=b.ResourceId", array("Code","ResourceName","UnitId"), $resSelect::JOIN_INNER)
					->join(array('d'=>'Proj_UOM'), 'b.UnitId=d.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)	
					->group(new expression('g.ResourceId,b.Code,b.ResourceName,b.UnitId,d.UnitName'));
			
			//Start Decision
			/*$selectDecs1 = $sql->select(); 
			$selectDecs1->from(array("a"=>"VM_ReqDecQtyTrans"))
				->columns(array("TransId","DecisionId","hidQty"=>new Expression("1-1"),"Quantity"=>new Expression("a.IndentQty")), array("ResourceId"))
				->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("ResourceId"), $selectDecs1::JOIN_INNER)
				->where(array('a.DecisionId'=>array('45','46'),
				'b.ResourceId'=>array('1003')  ));
			*/
			
			/*select a.ReqAHTransId RequestAHTransId, [b].[AnalysisId] AS [AnalysisId],[b].[ReqTransId] AS [ReqTransId],
 [b].[ResourceId] AS [ResourceId], [b].[ItemId] AS [ItemId], CAST(b.ReqQty As Decimal(18,5)) AS [Quantity], 
 CAST(b.ReqQty-(b.IndentApproveQty+b.TransferApproveQty+b.HireApproveQty+b.ProductionApproveQty) As Decimal(18,5)) AS [BalQty], 
 CAST(a.IndentQty As Decimal(18,5)) AS [IndentApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [TransferApproveQty], 
 CAST(a.ProductionQty As Decimal(18,5)) AS [ProductionApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HireApproveQty], 
 CAST(a.IndentQty As Decimal(18,5)) AS [HiddenIApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [HiddenTApproveQty], 
 CAST(a.ProductionQty As Decimal(18,5)) AS [HiddenPApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HiddenHApproveQty] 
  from [VM_ReqDecQtyAnalTrans] a
 INNER JOIN VM_RequestAnalTrans b on a.ReqAHTransId=b.RequestAHTransId
 INNER JOIN [VM_ReqDecQtyTrans] c on a.DecisionId=c.DecisionId and a.ReqTransId=c.ReqTransId
 Where c.[DecisionId] = '28' and c.ReqTransId=1018*/
 
	$selectDecs2 = $sql->select(); 
	$selectDecs2->from(array("a"=>'VM_ReqDecQtyAnalTrans'))
		->columns(array(new Expression("a.ReqAHTransId RequestAHTransId, [b].[AnalysisId] AS [AnalysisId],[b].[ReqTransId] AS [ReqTransId],
			 [b].[ResourceId] AS [ResourceId], [b].[ItemId] AS [ItemId], CAST(b.ReqQty As Decimal(18,5)) AS [Quantity], 
			 CAST(b.ReqQty-(b.IndentApproveQty+b.TransferApproveQty+b.HireApproveQty+b.ProductionApproveQty) As Decimal(18,5)) AS [BalQty], 
			 CAST(a.IndentQty As Decimal(18,5)) AS [IndentApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [TransferApproveQty], 
			 CAST(a.ProductionQty As Decimal(18,5)) AS [ProductionApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HireApproveQty], 
			 CAST(a.IndentQty As Decimal(18,5)) AS [HiddenIApproveQty], CAST(a.TransferQty As Decimal(18,5)) AS [HiddenTApproveQty], 
			 CAST(a.ProductionQty As Decimal(18,5)) AS [HiddenPApproveQty], CAST(a.HireQty As Decimal(18,5)) AS [HiddenHApproveQty]")))
		->join(array("b"=>"VM_RequestAnalTrans"), "a.ReqAHTransId=b.RequestAHTransId", array(), $selectDecs2::JOIN_INNER)
		->join(array("c"=>"VM_ReqDecQtyTrans"), "a.DecisionId=c.DecisionId and a.ReqTransId=c.ReqTransId", array(), $selectDecs2::JOIN_INNER)
		->where(array('c.DecisionId'=>array('28'),
			'c.ReqTransId'=>array('1018')));			

	$selectDecs1 = $sql->select(); 
	$selectDecs1->from(array("a"=>"VM_ReqDecQtyAnalTrans"))
		->columns(array("ReqAHTransId"))
		->where(array('a.DecisionId'=>array('28'),
		'a.ReqTransId'=>array('1018')  ));
		
	$selectDecs3 = $sql->select(); 
	$selectDecs3->from(array("a"=>'VM_RequestAnalTrans'))
		->columns(array(new Expression("a.RequestAHTransId,a.AnalysisId, [a].[ReqTransId] AS [ReqTransId],
			 [a].[ResourceId] AS [ResourceId], [a].[ItemId] AS [ItemId], CAST(a.ReqQty As Decimal(18,5)) AS [Quantity], 
			 CAST(a.ReqQty-(a.IndentApproveQty+a.TransferApproveQty+a.HireApproveQty+a.ProductionApproveQty) As Decimal(18,5)) AS [BalQty], 
			 CAST(0 As Decimal(18,5)) AS [IndentApproveQty], CAST(0 As Decimal(18,5)) AS [TransferApproveQty], 
			 CAST(0 As Decimal(18,5)) AS [ProductionApproveQty], CAST(0 As Decimal(18,5)) AS [HireApproveQty], 
			 CAST(0 As Decimal(18,5)) AS [HiddenIApproveQty], CAST(0 As Decimal(18,5)) AS [HiddenTApproveQty], 
			 CAST(0 As Decimal(18,5)) AS [HiddenPApproveQty], CAST(0 As Decimal(18,5)) AS [HiddenHApproveQty]")))
		->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array(), $selectDecs3::JOIN_INNER)
		->where(array('a.ReqTransId'=>array('1018')))
		->where->notIn('a.RequestAHTransId',$selectDecs1);				
	$selectDecs3->combine($selectDecs2,'Union ALL');
		
	$decSelect = $sql->select(); 
	$decSelect->from(array("g"=>$selectDecs3))
		->columns(array("*"),array("WbsName"))
		->join(array("d"=>"Proj_WBSMaster"), "g.AnalysisId=d.WBSId", array("WbsName"), $decSelect::JOIN_INNER);
		
		
		
		$RfqId=1;
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RFQRegister"));
		$selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
								a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
		$selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		
					
		$selectTotsentRFQ = $sql->select();
		$selectTotsentRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotsentRFQ->columns(array(new Expression("a.RFQRegId,COUNT(*) as totalsent,0 Received")))
					->join(array("c"=>"VM_RFQVendorTrans"), "a.RFQRegId=c.RFQId", array(), $selectTotsentRFQ::JOIN_LEFT)
					->group(new expression('a.RFQRegId'));
					
		$selectTotRecRFQ = $sql->select();
		$selectTotRecRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotRecRFQ->columns(array(new Expression("a.RFQRegId,0 totalsent,COUNT(*) as Received")))
					->join(array("c"=>"VM_RequestForVendor"), "a.RFQRegId=c.RFQId", array(), $selectTotRecRFQ::JOIN_INNER)
					->group(new expression('a.RFQRegId'));					
		$selectTotRecRFQ->combine($selectTotsentRFQ,'Union ALL');
			
		$selectRFQ = $sql->select(); 
		$selectRFQ->from(array("G"=>$selectTotRecRFQ))
				->columns(array(new Expression("G.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate, 
					CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification, 
					sum(G.totalsent) as totalsent,Sum(G.Received) as Received,Sum(G.totalsent-G.Received) as Pending, 
					CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,b.TypeName  ") ))
				->join(array("a"=>"VM_RFQRegister"), "G.RFQRegId=a.RFQRegId", array(), $selectRFQ::JOIN_INNER)
				->join(array('b'=>'Proj_ResourceType'), 'a.RFQType=b.TypeId', array(), $selectRFQ:: JOIN_LEFT)	
				->group(new expression('G.RFQRegId,a.RFQDate,a.RFQNo,b.TypeName,a.TechVerification,a.Approve'))
				->order("a.RFQDate Desc");
					
					
		$vendorId = 3054;		
		$selectRFQ = $sql->select();
		$selectRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectRFQ->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,
						CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,
						CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve")), array(), array("TypeName"))
					->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQRegId=b.RFQId", array(), $selectRFQ::JOIN_INNER)
					->join(array("c"=>"Proj_ResourceType"), "a.RFQType=c.TypeId", array("TypeName"), $selectRFQ::JOIN_LEFT);
		$selectRFQ->where(array("b.VendorId"=>$vendorId))
					->order("a.RFQDate Desc");
		
		$rfqId = 1;		
		$selectRFQ = $sql->select();
		$selectRFQ->from(array("a"=>"VM_RequestForVendor"))
					->columns(array("RegId")) 
					->where(array("a.RFQId"=>$rfqId, "a.VendorId"=>$vendorId ));
					
		/*
		SELECT G.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate, 
		CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification, 
		sum(G.totalsent) as totalsent,Sum(G.Received) as Received,Sum(G.totalsent-G.Received) as Pending, 
		 CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,b.TypeName 
		 FROM (
		 select a.RFQRegId,COUNT(*) as totalsent,0 Received from [VM_RFQRegister] a
		 Left JOIN [VM_RFQVendorTrans] AS [c] ON [a].[RFQRegId]=[c].[RFQId]
		 GROUP BY a.RFQRegId
		 union all
		  select a.RFQRegId,0 totalsent,COUNT(*) as Received from [VM_RFQRegister] a
		 INNER JOIN VM_RequestForVendor AS [b] ON [a].[RFQRegId]=[b].[RFQId]
		 GROUP BY a.RFQRegId
		 ) G INNER JOIN [VM_RFQRegister] a on G.RFQRegId=a.RFQRegId
		 LEFT JOIN [Proj_ResourceType] AS [b] ON [a].[RFQType]=[b].[TypeId] 
		 GROUP BY G.RFQRegId,a.RFQDate,a.RFQNo,b.TypeName,a.TechVerification,a.Approve
		 */
		 
		$rfqId = 1;		
		$selectRFQVendorList = $sql->select();
		$selectRFQVendorList->from(array("a"=>"VM_RFQVendorTrans"));
		$selectRFQVendorList->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $selectRFQVendorList::JOIN_INNER)				
					->where(array("a.RFQId"=>$rfqId))
					->order("b.VendorName");
		
		/*select b.VendorId, a.ResourceId,a1.Code,a1.ResourceName,a.Quantity,a.Rate,a.Amount from VM_RFVTrans a 
INNER JOIN Proj_Resource a1 on a.ResourceId=a1.ResourceId
INNER JOIN VM_RequestFormVendorRegister b on a.RegId=b.RegId
WHere b.RFQId=1*/ 
		
		$selectRFQRestrans = $sql->select();
		$selectRFQRestrans->from(array("a"=>"VM_RFVTrans"));
		$selectRFQRestrans->columns(array("ResourceId","Quantity","Rate","Amount"), array("Code","ResourceName"), array("VendorId"))
					->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $selectRFQRestrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegId=c.RegId", array("VendorId"), $selectRFQRestrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$rfqId));
		/*select b.VendorId,a.TermsId,a1.SlNo,a1.Title,a.ValueFromNet,a.Per,a.Value,a.Period from VM_RFVTerms a 
INNER JOIN WF_TermsMaster a1 on a.TermsId=a1.TermsId
INNER JOIN VM_RequestFormVendorRegister b on a.RegisterId=b.RegId
WHere b.RFQId=1 */
			$rfqRegId=1007;
		$selectRFQTermtrans = $sql->select();
		$selectRFQTermtrans->from(array("a"=>"VM_RFVTerms"));
		$selectRFQTermtrans->columns(array("TermsId","ValueFromNet","Per","Value","Period"), array("SlNo","Title"), array("VendorId"))
					->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("SlNo","Title"), $selectRFQTermtrans::JOIN_INNER)
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RegisterId=c.RegId", array("VendorId"), $selectRFQTermtrans::JOIN_INNER)					
					->where(array("c.RFQId"=>$rfqId));
					
					 $selectRFQTermtrans->where(array('b.TermType' => 'S'));
            $selectRFQTermtrans->where(array('c.RFQId' => $rfqRegId),\Zend\Db\Sql\Where::OP_OR);
			
		//AND OR Statement in Query for SELECT `users`.* FROM `users` WHERE `is_locked` = 0 OR `is_active` = 1 AND `role_id` = 1 
		$select->where(array('is_locked' => 0,'is_active' => 1),\Zend\Db\Sql\Where::COMBINED_BY_OR);
		$select->where(array('role_id' => 1),\Zend\Db\Sql\Where::OP_AND);
		
		
		$statement = $sql->getSqlStringForSqlObject($selectRFQTermtrans);
		$basicResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
	}

	public function unregistervendorProfileAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();

		$vendorId= $this->params()->fromRoute('vendorid');
		if($request->isPost()){
			$postParams = $request->getPost();
			$vendorId = $postParams['VendorId'];
			if($vendorId!=0){			
				$this->redirect()->toRoute('vendor/basic-detail', array('controller' => 'index','action' => 'basic-detail','vendorid' => $vendorId));
			}
		}
		$supplyGradeName="";
		$supplyContractName="";
		$supplyServiceName="";
		
		$select = $sql->select();
		$select->from(array("a"=>"Vendor_Master"))
			   ->columns(array('VendorId','VendorName','Supply','Contract','Service','CityId','Pincode','RegAddress','LogoPath',"CreatedDate"=>new Expression("Convert(varchar(10),CreatedDate,105)")))
			   ->join(array('b'=>'WF_CityMaster'), 'a.CityId=b.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
			   ->join(array('c'=>'WF_StateMaster'), 'c.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
			   ->join(array('d' => 'WF_CountryMaster'), 'd.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)				   
			   ->where(array('VendorId'=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$basicResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$select = $sql->select();
		$select->from('Vendor_Contact')
			   ->columns(array('CAddress','Phone1','Fax1','CPerson1','CDesignation1','ContactNo1','Email1','WebName','ContactType'))
			   ->where->like('VendorId', $vendorId );
		$statement = $sql->getSqlStringForSqlObject($select);
		$contactVendor   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$select = $sql->select();
		$select->from(array('a' => 'Vendor_Statutory'))
		   ->join(array('b'=> 'Vendor_Master'), 'a.VendorId=b.VendorId', array('SupplyType', 'Company'), $select:: JOIN_LEFT)			  
			->columns(array('FirmType','EYear','PANNo','TANNo','CSTNo','TINNo','ServiceTaxNo','TNGSTNo','SSIREGDNo','ServiceTaxCir','EPFNo','ESINo',
							'ExciseVendor','ExciseRegNo', 'Excisedivision','ExciseRange','ECCno','ChequeonName'),array('SupplyType', 'Company'))
			 ->where(array('a.VendorId'=>$vendorId));
			
		$statement = $sql->getSqlStringForSqlObject($select);
		$statutoryResults = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//SupplyGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'S');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraSupply = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$supplyPoint= $resultsGraSupply['Amount'];

		if($supplyPoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$supplyPoint."' AND TValue >= '".$supplyPoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraSupply2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyGradeName= $resultsGraSupply2['GradeName'];
		}
		//ContractGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'C');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraContract = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$contractPoint= $resultsGraContract['Amount'];

		if($contractPoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$contractPoint."' AND TValue >= '".$contractPoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraContract2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyContractName= $resultsGraContract2['GradeName'];
		}
		
		//ServiceGrade
		$select = $sql->select();			
		$select->from('Vendor_CheckListTrans')
				->columns(array('Amount' => new Expression('SUM(Vendor_CheckListTrans.Points)')))
				->where(array('VendorId'=>$vendorId));
				$select->where->and->expression('RegType like ?', 'R');	
		$statement = $sql->getSqlStringForSqlObject($select);
		$resultsGraService = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$servicePoint= $resultsGraService['Amount'];

		if($servicePoint!=""){
			$select = $sql->select();			
			$select->from('Vendor_GradeMaster')
					->columns(array('GradeId','GradeName'));
					//$select->where->and->expression($iSupplyPoint. ' Between FValue and TValue');	
			$select->where("FValue < '".$servicePoint."' AND TValue >= '".$servicePoint."'");
			$statement = $sql->getSqlStringForSqlObject($select);
			$resultsGraService2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$supplyServiceName= $resultsGraService2['GradeName'];
		}
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			//$resp = array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				if($postParam['mode'] == 'RegisterVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'R',"Approve"=>'Y'))
								->where(array("VendorId"=>$postParam['vendor_id']));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				else if($postParam['mode'] == 'BlockVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'B',"Approve"=>'Y'))
								->where(array("VendorId"=>$postParam['vendor_id']));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				else if($postParam['mode'] == 'AskVendor'){
					$vendorUpdate = $sql->update("Vendor_Master");
					$vendorUpdate->set(array("VendorStatus"=>'A',"Approve"=>'Y'))
								->where(array("VendorId"=>$postParam['vendor_id']));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}				
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){

		}
		
		$this->_view->rs = $basicResult;
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		$this->_view->VendorId = $this->params()->fromRoute('vendorid');
		$this->_view->contactVendor = $contactVendor;
		$this->_view->statutoryResults = $statutoryResults;
		$this->_view->SupplyGrade = $supplyGradeName;
		$this->_view->ContractGrade = $supplyContractName;
		$this->_view->ServiceGrade = $supplyServiceName;
		return $this->_view;
	}

	public function licenseAction(){
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || LandBank Enquiry");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		
		$vendorId = $this->params()->fromRoute('vendorid');
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $postData = $request->getPost();
				$select = $sql->delete();
						$select->from('vendor_licenseTrans')
								->where(array('VendorId'=>$vendorId));
						$delStatement = $sql->getSqlStringForSqlObject($select);			
				$dbAdapter->query($delStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				$select = $sql->delete();
						$select->from('vendor_LicenseValidTrans')
								->where(array('VendorId'=>$vendorId));
						$delStatement = $sql->getSqlStringForSqlObject($select);			
				$dbAdapter->query($delStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						
                $ownerDetailId = $this->bsf->isNullCheck($postData['ownerdetailid'], 'number');
                for ($i = 1; $i <= $ownerDetailId; $i++) {
                    $licenseName = $this->bsf->isNullCheck($postData['licenseName_' . $i], 'string');
					$licenseId = $this->bsf->isNullCheck($postData['licenseId_' . $i], 'string');

                    if ($licenseName == "")
                        continue;
						
					if($licenseId == "new"){
						$insert = $sql->insert();
						$insert->into('Vendor_LicenseMaster');
						$insert->Values(array('LicenseName' => $licenseName));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						$masterId = $dbAdapter->getDriver()->getLastGeneratedValue();
						$insert = $sql->insert();
						$insert->into('vendor_licenseTrans');
						$insert->Values(array('VendorId' => $vendorId,
							'LicenseMasterId' => $this->bsf->isNullCheck($masterId, 'number'),
							));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$transId = $dbAdapter->getDriver()->getLastGeneratedValue();
					} else {
						$insert = $sql->insert();
						$insert->into('vendor_licenseTrans');
						$insert->Values(array('VendorId' => $vendorId,
							'LicenseMasterId' => $this->bsf->isNullCheck($postData['licenseId_' . $i], 'number'),
							));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$transId = $dbAdapter->getDriver()->getLastGeneratedValue();
					}

                    $ownerInfoId = $this->bsf->isNullCheck($postData['coownerid_' . $i], 'number');
                    for ($j = 1; $j <= $ownerInfoId; $j++) {
                        $regDate =  date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['trans_' . $i . '_regDate_' . $j], 'string')));
                        $regNo = $this->bsf->isNullCheck($postData['trans_' . $i . '_regNo_' . $j], 'string');
						$fromDate =  date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['trans_' . $i . '_startDate_' . $j], 'string')));
						$toDate =  date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['trans_' . $i . '_toDate_' . $j], 'string')));
						$remarks = $this->bsf->isNullCheck($postData['trans_' . $i . '_remarks_' . $j], 'string');
						
                        if ($regNo == "" || $regDate == "")
                            continue;

                        $insert = $sql->insert();
                        $insert->into('vendor_LicenseValidTrans');
                        $insert->Values(array('LicenseTransId' => $transId,
                            'VendorRegNo' => $regNo, 
							'RegDate' => $regDate,
                            'ValidFrom' => $fromDate,
                            'ValidTo' => $toDate,
                            'Remarks' => $remarks,
							'VendorId' => $vendorId));
						$statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } 
                }

                $connection->commit();
                //$this->redirect()->toRoute('project/landbankenquiry', array('controller' => 'landbank', 'action' => 'enquiry'));
            } catch (PDOException $e) {
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
            }
        } else {
           /* $aVNo = CommonHelper::getVoucherNo(102, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"]; */
            
            if (isset($vendorId) && $vendorId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'vendor_licenseTrans'))
					->join(array("b"=>"vendor_licenseMaster"), "a.LicenseMasterId=b.LicenseMasterId", array("LicenseName"), $select::JOIN_LEFT)
					 ->where(array("VendorId" => $vendorId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $transDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($transDetail as &$validTrans){
					$select = $sql->select();
					$select->from(array('a' => 'vendor_licenseValidTrans'))
						->where("VendorId = ".$vendorId." and LicenseTransId = ".$validTrans['LicenseTransId']);
					$statement = $sql->getSqlStringForSqlObject($select);
					$validTrans['validtrans'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				}
			$this->_view->transDetail = $transDetail;
            }
			
			$select = $sql->select();
            $select->from(array('a' => 'Vendor_LicenseMaster'))
                ->columns(array('data' => 'LicenseMasterId', 'value' => 'LicenseName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_license = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->vendorId = (isset($vendorId) && $vendorId != 0) ? $vendorId : 0;
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
	}

    public function  checklicensefoundAction() {
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

                $LicenseName = $this->bsf->isNullCheck($this->params()->fromPost('LicenseName'), 'string');
                $select = $sql->select();
                $select->from('Vendor_LicenseMaster')
                    ->columns( array( 'LicenseMasterId'))
                    ->where( "LicenseName='$LicenseName'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }

	public function ohseAction(){
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
		
		$vendorId = $this->params()->fromRoute('vendorid');
		
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
        $select->from(array('a' => 'Vendor_OhseMaster'))
                ->columns(array('data' => 'VendorOhseRegId', 'value' => 'Description'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_ohse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
				//begin trans try block example starts
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try {
				
					$postData = $request->getPost();
					$select = $sql->delete();
							$select->from('Vendor_OhseTrans')
									->where(array('VendorId'=>$vendorId));
							$delStatement = $sql->getSqlStringForSqlObject($select);			
					$dbAdapter->query($delStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
					$ownerDetailId = $this->bsf->isNullCheck($postData['ownerdetailid'], 'number');
					for ($i = 1; $i <= $ownerDetailId; $i++) {
						$ohseName = $this->bsf->isNullCheck($postData['ohseName_' . $i], 'string');
						$ohseRegId = $this->bsf->isNullCheck($postData['ohseRegId_' . $i], 'string');
						$remarks = $this->bsf->isNullCheck($postData['remarks_' . $i], 'string');

						if ($ohseRegId == "")
							continue;

						if($ohseRegId == "new"){
							$insert = $sql->insert();
							$insert->into('Vendor_OhseMaster');
							$insert->Values(array('Description' => $ohseName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$masterId = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$insert = $sql->insert();
							$insert->into('Vendor_OhseTrans');
							$insert->Values(array('VendorId' => $vendorId,
								'VendorOhseRegId' => $this->bsf->isNullCheck($masterId, 'number'),
								'Remarks' => $remarks
								));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$transId = $dbAdapter->getDriver()->getLastGeneratedValue();
						} else {
							$insert = $sql->insert();
							$insert->into('Vendor_OhseTrans');
							$insert->Values(array('VendorId' => $vendorId,
								'VendorOhseRegId' => $this->bsf->isNullCheck($postData['ohseRegId_' . $i], 'number'),
								'Remarks' => $remarks
								));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$transId = $dbAdapter->getDriver()->getLastGeneratedValue();
						}
					}
					$connection->commit();
				} catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";
				}
					
			}
			//begin trans try block example ends
			if (isset($vendorId) && $vendorId != 0) {
                $select = $sql->select();
                $select->from(array('a' => 'Vendor_OhseTrans'))
					->join(array("b"=>"Vendor_OhseMaster"), "a.VendorOhseRegId=b.VendorOhseRegId", array("Description"), $select::JOIN_LEFT)
					 ->where(array("VendorId" => $vendorId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->transDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
			return $this->_view;
		}
	}
	public function  checkohsefoundAction() {
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

                $ohseName = $this->bsf->isNullCheck($this->params()->fromPost('ohseName'), 'string');
                $select = $sql->select();
                $select->from('Vendor_OhseMaster')
                    ->columns( array( 'VendorOhseRegId'))
                    ->where( "Description='$ohseName'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (sizeof($results) !=0 )
                    $ans ='Y';

                return $this->getResponse()->setContent($ans);
            }
        }
    }



	public function vehicleAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));


		//$vehicleId = $this->params()->fromRoute('vehicleid');
		$this->_view->vendorId = $vendorId;
		//$this->_view->vehicleId = $vehicleId;


		$sql = new Sql($dbAdapter);
		
		if(isset($vendorId) && $vendorId !=""){
			$select = $sql->select();
			$select->from(array('a' => 'Vendor_VehicleMaster'))
					->columns(array('data' => 'VehicleId', 'value' => 'VehicleRegNo'))
					->where( "VendorId='$vendorId'");
            $statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->vehicle = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray(); 
		}
//		if(isset($vehicleId) && $vehicleId !=""){
//			$select = $sql->select();
//			$select	->from(array('a' => 'Vendor_VehicleMaster'))
//					->where( "vehicleId='$vehicleId' and VendorId ='$vendorId' ");
//			$statement = $sql->getSqlStringForSqlObject($select);
//			$this->_view->vehicleData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//		}
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                switch($Type) {
                    case 'vehicleDetails':

                        $vehicleId = $this->bsf->isNullCheck($this->params()->fromPost('vehicleid'), 'number');
                        $vendorId= $this->bsf->isNullCheck($this->params()->fromPost('vendorId'), 'number');
                        $select = $sql->select();
                        $select	->from(array('a' => 'Vendor_VehicleMaster'))
                                ->where( "vehicleId='$vehicleId' and VendorId ='$vendorId' ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $vehicleData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($vehicleData));
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
                $postData = $request->getPost();
//                   echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                   return;
                $vendorId=$this->bsf->isNullCheck($postData['vendorId'], 'number');
				if($vendorId !=0){

					$vehicleId = $this->bsf->isNullCheck($postData['vehicleId'], 'number');
					$bLength = $this->bsf->isNullCheck($postData['bLength'], 'number');
					$bBreadth = $this->bsf->isNullCheck($postData['bBreadth'], 'number');
					$bHeight = $this->bsf->isNullCheck($postData['bHeight'], 'number');
					$bQty = $this->bsf->isNullCheck($postData['bQty'], 'number');
					$tMaxLength = $this->bsf->isNullCheck($postData['tMaxLength'], 'number');
					$tMaxBreadth = $this->bsf->isNullCheck($postData['tMaxBreadth'], 'number');
					$tMaxHeight = $this->bsf->isNullCheck($postData['tMaxHeight'], 'number');
					$tMaxQty = $this->bsf->isNullCheck($postData['tMaxQty'], 'number');
					$tMinLength = $this->bsf->isNullCheck($postData['tMinLength'], 'number');
					$tMinBreadth = $this->bsf->isNullCheck($postData['tMinBreadth'], 'number');
					$tMinHeight = $this->bsf->isNullCheck($postData['tMinHeight'], 'number');
					$tMinQty = $this->bsf->isNullCheck($postData['tMinQty'], 'number');
					$bctotal = $this->bsf->isNullCheck($postData['bctotal'], 'number');
					$perQty = $this->bsf->isNullCheck($postData['perQty'], 'number');
					$netQty = $this->bsf->isNullCheck($postData['netQty'], 'number');
					$remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');
					$connection = $dbAdapter->getDriver()->getConnection();
					$connection->beginTransaction();
					try {
							if(isset($vehicleId) && $vehicleId !=""){
							$update = $sql->update("Vendor_VehicleMaster");
							$update->set(array(
								'BLLen' => $bLength,
								'BLBreadth' => $bBreadth,
								'BLHeight' => $bHeight,
								'BLQty' => $bQty,
								'TSMAXLen' => $tMaxLength,
								'TSMAXBreadth' => $tMaxBreadth,
								'TSMAXHeight' => $tMaxHeight,
								'TSMAXQty' => $tMaxQty,
								'TSMinLen' => $tMinLength,
								'TSMinBreadth' => $tMinBreadth,
								'TSMinHeight' => $tMinHeight,
								'TSMinQty' => $tMinQty,
								'Total1' => $bctotal,
								'Total2' => $perQty,
								'NetTotal' => $netQty,
								'Remarks' => $remarks
								));
								$update -> where("VehicleId = '$vehicleId' and VendorId = '$vendorId'");
							 $statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							}
						$connection->commit();
					} catch(PDOException $e){
						$connection->rollback();
						print "Error!: " . $e->getMessage() . "</br>";
					}
//					if(isset($vehicleId)){
//						//$this->redirect()->toRoute("vendor/vehicle", array("controller" => "index","action" => "vehicle" ,"vendorid" =>"$vendorId","vehicleid" =>"$vehicleId"));
//                        $this->redirect()->toRoute('vendor/vehicle', array('controller' => 'index', 'action' => 'vehicle', 'vendorid' => $vendorId,'vehicleid' => $vehicleId ));
//					} else {
						//$this->redirect()->toRoute("vendor/vehicle", array("controller" => "index","action" => "vehicle" ,"vendorid" => encode($vendorId)));
                    $this->redirect()->toRoute('vendor/vehicle', array('controller' => 'index','action' => 'vehicle','vendorid' =>$this->bsf->encode($vendorId)));
					//}
				}
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

    public function vehiclemasterAction(){
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
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
        $vehicleId= $this->bsf->decode($this->params()->fromRoute('vehicleid'));
        //$vendorId = $this->params()->fromRoute('vendorid');
        //$vehicleId = $this->params()->fromRoute('vehicleid');
        if(isset($vehicleId) && $vehicleId != 0)
        {

            $select = $sql->select();
            $select -> from('Vendor_VehicleMaster')
                -> columns(array('VendorId','VehicleId','VehicleRegNo','VehicleName'))
                -> where("VendorId=$vendorId And VehicleId=$vehicleId ");

            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->vehicleDetail=$dbAdapter->query($statement,$dbAdapter::QUERY_MODE_EXECUTE)->current();
        }
        if($this->getRequest()->isXmlHttpRequest())
        {

            $request = $this->getRequest();
            if ($request->isPost())
            {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        }
        else
        {
            $request = $this->getRequest();
            if ($request->isPost())
            {
                //Write your Normal form post code here
                $postData = $request->getPost();
                //echo "<pre>";
                //print_r($postData);

                if(isset($vehicleId) && $vehicleId != 0)
                {
                    $regNo = $postData['txtRegNo'];
                    $regName = $postData['txtVehicleName'];
                    //echo "</pre>";die;
                    $update = $sql->update ('vendor_vehiclemaster');
                    $updateData = array(
                        'VehicleRegNo' => $regNo,
                        'VehicleName' => $regName
                    );
                    $update->set($updateData)
                        ->where ("vendorid=$vendorId And vehicleid=$vehicleId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $this->redirect()->toRoute("vendor/vehicleregister", array("controller" => "index","action" => "vehicleregister",'vendorid' => $vendorId));
                }
                else
                {
                    $regNo = $postData['txtRegNo'];
                    $regName = $postData['txtVehicleName'];
                    //echo "</pre>";die;
                    $insert = $sql->insert('vendor_vehiclemaster');
                    $newData = array(
                        'VehicleRegNo' => $regNo,
                        'VehicleName' => $regName,
                        'VendorId' => $vendorId
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $this->redirect()->toRoute("vendor/vehicleregister", array("controller" => "index","action" => "vehicleregister",'vendorid' => $vendorId));
                }
            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try
            {
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


	public function vehicleregisterAction(){
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
		$response = $this->getResponse();
        $vendorId= $this->bsf->decode($this->params()->fromRoute('vendorid'));
		$this->_view->vendorId = $vendorId;
		
		/*Ajax Request*/
		if($this->getRequest()->isXmlHttpRequest()){
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
               // Print_r($postParams); die;

				$Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');

				switch($Type) {
                    case 'vehicleDetails':

                        $vehicleId = $this->bsf->isNullCheck($this->params()->fromPost('vehicleid'), 'number');                     
                        $select = $sql->select();
                        $select	->from(array('a' => 'Vendor_VehicleMaster'))
								->columns(array(new Expression("VehicleId,VendorId,VehicleRegNo,VehicleName,CAST(BLLen As Decimal(18,3)) As BLLen,
								   CAST(BLBreadth As Decimal(18,3)) As BLBreadth,CAST(BLHeight As Decimal(18,3)) As BLHeight,CAST(BLQty As Decimal(18,3)) As BLQty,
								   CAST(TSMAXLen As Decimal(18,3)) As TSMAXLen,CAST(TSMAXBreadth As Decimal(18,3)) As TSMAXBreadth,CAST(TSMAXHeight As Decimal(18,3)) As TSMAXHeight,
								   CAST(TSMAXQty As Decimal(18,3)) As TSMAXQty,CAST(TSMinLen As Decimal(18,3)) As TSMinLen,CAST(TSMinBreadth As Decimal(18,3)) As TSMinBreadth,
								   CAST(TSMinHeight As Decimal(18,3)) As TSMinHeight,CAST(TSMinQty As Decimal(18,3)) As TSMinQty,CAST(Total1 As Decimal(18,3)) As Total1,
								   CAST(Total2 As Decimal(18,3)) As Total2,CAST(NetTotal As Decimal(18,3)) As NetTotal,Remarks " )))
                                ->where( "vehicleId='$vehicleId'");
                        $statement = $sql->getSqlStringForSqlObject($select);
						$vehicleData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($vehicleData));
                        return $response;
                        break;

                        case 'vehreg':

                            $VendorId = $this->bsf->isNullCheck($postParams['VendorId'], 'number');
                            $vehicleregno =$this->bsf->isNullCheck($postParams['vehiclereg'], 'string');
                            $vehicleregname = $this->bsf->isNullCheck($postParams['vehiclename'], 'string');



                            $insert = $sql->insert();
                            $insert->into('Vendor_VehicleMaster');
                            $insert->Values(array(
                                'VendorId' => $VendorId,
                                'VehicleRegNo' => $vehicleregno,
                                'VehicleName' => $vehicleregname,

                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $status="inserted";
                            $this->_view->setTerminal(true);
                            $response->setContent(json_encode($status));
                            return $response;
                            break;
						
					case 'delete':
					
						$vehicleId = $this->bsf->isNullCheck($this->params()->fromPost('VehicleId'), 'number');
						$delete = $sql->delete();
						$delete->from('Vendor_VehicleMaster')
							->where(array("VehicleId='$vehicleId'"));
						$DelStatement = $sql->getSqlStringForSqlObject($delete);
						$DeleteVeg = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($DeleteVeg));
                        return $response;
                        break;

                    case 'vehiclerefresh':
                        $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('vendorId'),'number');
                        $select = $sql->select();
                        $select	->from(array('a' => 'Vendor_VehicleMaster'))
                            ->columns(array(new Expression("VehicleId As data,VehicleRegNo As value")))
                            ->where( "VendorId='$vendorId'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $vehicleData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($vehicleData));
                        return $response;
                        break;

					case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
			}
        }else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postData = $request->getPost();
//                print_r($postData); die;
				for($i=1;$i<$postData['RowCount'];$i++) {	

					$vehicleid = $this->bsf->isNullCheck($postData['vehicleId_'.$i], 'number');
					$vehiclereg = $this->bsf->isNullCheck($postData['vehiclereg_'.$i], 'string');
					$vehiclename = $this->bsf->isNullCheck($postData['vehiclename_'.$i], 'string'); 					
					if($vehicleid == 0) {					
						$insert = $sql->insert('Vendor_VehicleMaster');
						$newData = array(
							'VendorId'		=> $vendorId,
							'VehicleRegNo'		=> $vehiclereg,
							'VehicleName' 		=>$vehiclename);
						$insert->values($newData);
			            $statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					} else {
						$update = $sql->update();
						$update->table('Vendor_VehicleMaster');
						$update->set(array(
							'VehicleRegNo'		=> $vehiclereg,
							'VehicleName' 		=>$vehiclename,
						));
						$update->where(array('VehicleId'=>$vehicleid));
					    $statement = $sql->getSqlStringForSqlObject($update);
						$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

					}
				}
				if($vendorId !=0){ 

					$vehicleId = $this->bsf->isNullCheck($postData['vehicleId'], 'number');
					$bLength = $this->bsf->isNullCheck($postData['bLength'], 'number');
					$bBreadth = $this->bsf->isNullCheck($postData['bBreadth'], 'number');
					$bHeight = $this->bsf->isNullCheck($postData['bHeight'], 'number');
					$bQty = $this->bsf->isNullCheck($postData['bQty'], 'number');
					$tMaxLength = $this->bsf->isNullCheck($postData['tMaxLength'], 'number');
					$tMaxBreadth = $this->bsf->isNullCheck($postData['tMaxBreadth'], 'number');
					$tMaxHeight = $this->bsf->isNullCheck($postData['tMaxHeight'], 'number');
					$tMaxQty = $this->bsf->isNullCheck($postData['tMaxQty'], 'number');
					$tMinLength = $this->bsf->isNullCheck($postData['tMinLength'], 'number');
					$tMinBreadth = $this->bsf->isNullCheck($postData['tMinBreadth'], 'number');
					$tMinHeight = $this->bsf->isNullCheck($postData['tMinHeight'], 'number');
					$tMinQty = $this->bsf->isNullCheck($postData['tMinQty'], 'number');
					$bctotal = $this->bsf->isNullCheck($postData['bctotal'], 'number');
					$perQty = $this->bsf->isNullCheck($postData['perQty'], 'number');
					$netQty = $this->bsf->isNullCheck($postData['netQty'], 'number');
					$remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');
					
					if(isset($vehicleId) && $vehicleId !=""){ 
						$update = $sql->update("Vendor_VehicleMaster");
						$update->set(array(
							'BLLen' => $bLength,
							'BLBreadth' => $bBreadth,
							'BLHeight' => $bHeight,
							'BLQty' => $bQty,
							'TSMAXLen' => $tMaxLength,
							'TSMAXBreadth' => $tMaxBreadth,
							'TSMAXHeight' => $tMaxHeight,
							'TSMAXQty' => $tMaxQty,
							'TSMinLen' => $tMinLength,
							'TSMinBreadth' => $tMinBreadth,
							'TSMinHeight' => $tMinHeight,
							'TSMinQty' => $tMinQty,
							'Total1' => $bctotal,
							'Total2' => $perQty,
							'NetTotal' => $netQty,
							'Remarks' => $remarks
							));
						$update -> where("VehicleId = '$vehicleId' and VendorId = '$vendorId'");
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
			}
			$select = $sql->select();
			$select->from(array('a' => 'Vendor_VehicleMaster'))
					->columns(array('data' => 'VehicleId', 'value' => 'VehicleRegNo'))
					->where( "VendorId='$vendorId'");
            $statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->vehicle = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			$select = $sql->select();
			$select -> from('Vendor_VehicleMaster')
					-> columns(array('VendorId','VehicleId','VehicleRegNo','VehicleName'))
					-> where(array("VendorId"=>$vendorId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$vehicleRegister=$dbAdapter->query($statement,$dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$this->_view->vehicleRegister = $vehicleRegister;		
			
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}   
	}

	public function vendorRenewalAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorId = $this->bsf->decode($this->params()->fromRoute('vendorid'));
		$regId = $this->bsf->decode( $this->params()->fromRoute('regid'));
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){
			$postParams = $request->getPost();
			$regdate = date('m-d-Y',strtotime($postParams['regdate'])) ;
			$refno = $postParams['refno'];
			$remarks = $postParams['remarks'];
			$supply = $postParams['supplyFound'];
			$contract = $postParams['contractFound'];
			$service = $postParams['serviceFound'];
			$type = $postParams['type'];
			$supplyLife = $postParams['supply'];
			$contractLife = $postParams['contract'];
			$serviceLife = $postParams['service'];
			$registerId= $postParams['registerId'];
			$regTransId= $postParams['regTransId'];
			$slife = 0;
			$sfdate = "";
			$stdate = "";
			$clife = 0;
			$cfdate = "";
			$ctdate = "";
			$hlife = 0;
			$hfdate = "";
			$htdate = "";
			$sBlock= 0;
			$cBlock= 0;
			$hBlock= 0;
			$unBlock = 0;
			
			if($type == "R"){
				if($supply==1){
					if($supplyLife=="yes") {
						$slife = 1;
					}	else {
						$slife = 0;
						$sfdate = date('m-d-Y',strtotime($postParams['sfdate']));
						$stdate = date('m-d-Y',strtotime($postParams['stdate']));
					}
				}			
				if($contract==1){	
					if($contractLife=="yes") {
						$clife = 1;			
					}	else {
						$clife = 0;
						$cfdate = date('m-d-Y',strtotime($postParams['cfdate']));
						$ctdate = date('m-d-Y',strtotime($postParams['ctdate']));
					}
				}
				if($service==1){	
					if($serviceLife=="yes") {
						$hlife = 1;			
					}	else {
						$hlife = 0;
						$hfdate = date('m-d-Y',strtotime($postParams['hfdate']));
						$htdate = date('m-d-Y',strtotime($postParams['htdate']));
					}
				}
			}
			if($type == "S"){
				if($supply==1){
					$sfdate = date('m-d-Y',strtotime($postParams['suspendsupplyfdate']));
					$stdate = date('m-d-Y',strtotime($postParams['suspendsupplytdate']));
				}
				if($contract==1){
					$cfdate = date('m-d-Y',strtotime($postParams['suspendcontractfdate']));
					$ctdate = date('m-d-Y',strtotime($postParams['suspendcontracttdate']));
				}
				if($service==1){
					$hfdate = date('m-d-Y',strtotime($postParams['suspendservicefdate']));
					$htdate = date('m-d-Y',strtotime($postParams['suspendservicetdate']));
				}
			}
			if($type == "B"){
				if(!isset($postParams['unblock'])){
					if($supply==1){
						$sBlock= 1;
					}
					if($contract==1){
						$cBlock= 1;
					}
					if($service==1){
						$hBlock= 1;
					}
				} else {
					$unBlock = 1;
				}
			}
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
				if($regTransId==0){
					$insert      = $sql->insert('Vendor_RegTrans');
					$newData     = array(
					'VendorId'  => $vendorId,
					'RegId'  => $registerId,
					'RDate'  => $regdate,
					'RefNo'  => $refno,
					'StatusType'  => $type,
					'Remarks' => $remarks,
					'Supply' => $supply,
					'Contract' => $contract,
					'Service' => $service,
					'SLifeTime' => $slife,
					'CLifeTime' => $clife,
					'HLifeTime' => $hlife,
					'SFDate' => $sfdate,
					'CFDate' => $cfdate,
					'HFDate' => $hfdate,
					'STDate' => $stdate,
					'CTDate' => $ctdate,
					'HTDate' => $htdate,
					'UnBlock' => $unBlock,
					);
					$insert->values($newData);
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$regTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
				}
				else {
					$update = $sql->update();
					$update->table('Vendor_RegTrans');
					$updateData     = array(
					'RDate'  => $regdate,
					'RefNo'  => $refno,
					'StatusType'  => $type,
					'Remarks' => $remarks,
					'Supply' => $supply,
					'Contract' => $contract,
					'Service' => $service,
					'SLifeTime' => $slife,
					'CLifeTime' => $clife,
					'HLifeTime' => $hlife,
					'SFDate' => $sfdate,
					'CFDate' => $cfdate,
					'HFDate' => $hfdate,
					'STDate' => $stdate,
					'CTDate' => $ctdate,
					'HTDate' => $htdate,
					'UnBlock' => $unBlock,
					);
					$update->set($updateData);
					$update->where(array('RegTransId'=>$regTransId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				if($unBlock == 1){
					$status ="R";
				} else {
					$status ="B";
				}
				if($type =='B'){
					$update = $sql->update();
					$update->table('Vendor_Master');
					$updateData     = array(
						'SBlock'  => $sBlock,
						'CBlock'  => $cBlock,
						'HBlock'  => $hBlock,
						'VendorStatus'  => $status,
					);
					$update->set($updateData);
					$update->where(array('VendorId'=>$vendorId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				$connection->commit();
				$this->_view->status = "Registered successfully";
				$this->redirect()->toRoute('vendor/vendor-renewal', array('controller' => 'index','action' => 'vendor-renewal','vendorid' =>$this->bsf->encode($vendorId),'regid' =>$this->bsf->encode($regTransId)));
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";				
			}
		} 
		
		$select = $sql->select();
		$select->from('Vendor_Master')
			   ->columns(array('VendorName','VendorId'))
			   ->where("vendorStatus ='R' or vendorStatus ='B' ");
		$statementMaster = $sql->getSqlStringForSqlObject($select);
		$vendorList = $dbAdapter->query($statementMaster, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->vendorList = $vendorList;
	
		if($vendorId != null && $vendorId != 0){
			$select = $sql->select();
			$select->from('Vendor_Master')
				   ->columns(array('VendorName','Supply','Contract','Service'))
				   ->where("VendorId=$vendorId and vendorStatus ='R'");
			$statementMaster = $sql->getSqlStringForSqlObject($select);
			$resultData = $dbAdapter->query($statementMaster, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			$select = $sql->select();
			$select->from('Vendor_Registration')
				   ->columns(array('RegNo','RegisterId'))
				   ->where("VendorId=$vendorId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$registrationResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->resultMasterData = $resultData;
			$this->_view->registrationData = $registrationResult;		
			$this->_view->vendorId = $vendorId;
		} 
		if($regId != ''){
			$select = $sql->select();
			$select->from('Vendor_RegTrans')
				   ->columns(array('RegTransId','RegId','RDate','RefNo','StatusType','Remarks','SLifeTime','CLifeTime','HLifeTime','SFDate','STDate','CFDate','CTDate','HFDate','HTDate','UnBlock'))
				   ->where("VendorId=$vendorId and RegTransId=$regId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$regTransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->regTransResult = $regTransResult;
		}
		return $this->_view;
	}
//sdadasd
	public function renewalRegisterAction(){
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
                $venid=$postParams['VendorId'];
                $select = $sql->select();
                $select->from('Vendor_RegTrans')
                    ->columns(array("RegTransId"=>new Expression("ISNULL(MAX(RegTransId),0)")))
                    ->where("VendorId=$venid");
                $statement = $sql->getSqlStringForSqlObject($select);
                $renResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->regTransResult = $renResult;
                $response->setContent($renResult['RegTransId']);
                return $response;
			}

		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}

            $selectRenewal1 = $sql->select();
            $selectRenewal1 -> from(array("a"=>"Vendor_Registration"));
            $selectRenewal1 -> columns(array(new Expression("a.RegisterId RegTransId,a.VendorId,Convert(Varchar(10),A.RegDate,103) RDate,A.RegNo RefNo,A.RegNo,b.VendorName,'Register' Status,Case When A.Supply=1 Then 'Yes' Else 'No' End Supply,Case When A.Contract=1 Then 'Yes' Else 'No' End Contract,Case When A.Service=1 Then 'Yes' Else 'No' End Service,Case When A.Approve='P' Then 'Partial' When A.Approve='Y' Then 'Yes' Else 'No' End  As Approve")))
                            -> join(array("b"=>"Vendor_Master"),"a.VendorId=b.VendorId",array(),$selectRenewal1::JOIN_INNER);

            $selectRenewal2 = $sql->select();
            $selectRenewal2 -> from(array("a"=>"Vendor_RegTrans"));
            $selectRenewal2 -> columns(array(new Expression("a.RegTransId,a.VendorId,Convert(Varchar(10),A.RDate,103) RDate,A.RefNo,C.RegNo,B.VendorName,Case When A.StatusType='R' then 'Renewal' else 	Case When A.StatusType='S' then 'Suspend' else
                               Case When A.StatusType='B' then 'BlackList' end end end Status,Case When A.Supply=1 Then 'Yes' Else 'No' End Supply,Case When A.Contract=1 Then 'Yes' Else 'No' End Contract,Case When A.Service=1 Then 'Yes' Else 'No' End Service,Case When A.Approve='P' Then 'Partial' When A.Approve='Y' Then 'Yes' Else 'No' End As Approve  ")))
                            -> join(array("b"=>"Vendor_Master"),"a.VendorId=b.VendorId",array(),$selectRenewal2::JOIN_INNER)
                            ->join(array("c"=>"Vendor_Registration"),"a.RegId=C.RegisterId",array(),$selectRenewal2::JOIN_INNER);
            $selectRenewal2->combine($selectRenewal1,"Union ALL");

            $selectRenFinal = $sql -> select();
            $selectRenFinal -> from(array("G"=>$selectRenewal2))
                            ->columns(array(new Expression("G.RegTransId,G.VendorId,G.RDate,G.RefNo,G.RegNo,G.VendorName,G.Status,G.Supply,G.Contract,G.Service,G.Approve As Approve")))
                            ->order("G.RDate","G.RefNo");
            $statement = $sql->getSqlStringForSqlObject($selectRenFinal);
            $regResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->_view->regResult = $regResult;
            return $this->_view;
		}
	}

    public function deleterenewalAction()
    {
        $renewal = $this->getRequest();
        if ($renewal->isPost()) {
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $status = '';
                $RegTransId = $this->bsf->isNullCheck($this->params()->fromPost('RegTransId'), 'number');
                $sql = new Sql($dbAdapter);
                $response = $this->getResponse();
                $connection->beginTransaction();
                $deleteVendorRenewal = $sql->delete();
                $deleteVendorRenewal->from('Vendor_RegTrans')
                    ->where('RegTransId=' . $RegTransId);
                $DelStatement = $sql->getSqlStringForSqlObject($deleteVendorRenewal);
                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $status = 'Deleted';
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
            $response->setContent($status);
            return $response;
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
    public function getResourceFieldDataAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                try {
                    $mode = $this->bsf->isNullCheck($this->params()->fromPost('mode'), 'string' );

                    if($mode=="title") {
                        //Write your Ajax post code here
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();

                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/vendor/resourceitem/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/vendor/resourceitem/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                foreach ($xlData as $j => $value) {
                                    if($value!="") {
                                        $data[] = array('Field' => $value);
                                    }
                                }
                            } else {
                                break;
                            }
                            $icount = $icount + 1;
                        }


                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else if($mode=="body") {
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();
                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/vendor/resourceitem/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/vendor/resourceitem/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;

                        $RType = $postData['arrHeader'];
                        $bValid = true;

                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                if(isset($xlData)) { 				
								// echo"<pre>";
								   // print_r($xlData);
								   // echo"</pre>";
								   // die;
								   // return;
                                    foreach ($xlData as $j => $value) {
                                        if (trim($value) != "") {
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
                                                if (trim($sField) == "VendorName") { 
                                                    $col_1 = intval($j);
                                                }
                                                if (trim($sField) == "Address") { 
                                                    $col_2 = intval($j);
                                                }
                                                if (trim($sField) == "City") {
                                                    $col_3 = intval($j);
                                                }
                                                if (trim($sField) == "Pincode") {
                                                    $col_4 = intval($j);
                                                }
                                                if (trim($sField) == "ContactPerson") {
                                                    $col_5 = intval($j);
                                                }
                                                if (trim($sField) == "ContactNo") {
                                                    $col_6 = intval($j);
                                                }
                                                if (trim($sField) == "MobileNo") {
                                                    $col_7 = intval($j);
                                                }
                                                if (trim($sField) == "PhoneNo") {
                                                    $col_8 = intval($j);
                                                }
                                                if (trim($sField) == "EmailId") {
                                                    $col_9 = intval($j);
                                                }
                                                if (trim($sField) == "FaxNo") {
                                                    $col_10 = intval($j);
                                                }
                                                if (trim($sField) == "Supply") {
                                                    $col_11 = intval($j);
                                                }
                                                if (trim($sField) == "Contract") {
                                                    $col_12 = intval($j);
                                                }
                                                if (trim($sField) == "Service") {
                                                    $col_13 = intval($j);
                                                }
                                                if (trim($sField) == "CSTNo") {
                                                    $col_14 = intval($j);
                                                }
                                                if (trim($sField) == "TINGST") {
                                                    $col_15 = intval($j);
                                                }
                                                if (trim($sField) == "TINNo") {
                                                    $col_16 = intval($j);
                                                }
                                                if (trim($sField) == "PANNo") {
                                                    $col_17 = intval($j);
                                                }
                                                if (trim($sField) == "ServiceTaxNo") {
                                                    $col_18 = intval($j);
                                                }
                                                if (trim($sField) == "BankName") {
                                                    $col_19 = intval($j);
                                                }
                                                if (trim($sField) == "Branch") {
                                                    $col_20 = intval($j);
                                                }
                                                if (trim($sField) == "ACNo") {
                                                    $col_21 = intval($j);
                                                }
                                                if (trim($sField) == "IFSCCode") {
                                                    $col_22 = intval($j);
                                                }
                                                if (trim($sField) == "RegNo") {
                                                    $col_23 = intval($j);
                                                }
                                                if (trim($sField) == "RegDate") {
                                                    $col_24 = intval($j);
                                                }
                                                if (trim($sField) == "ChequeName") {
                                                    $col_25 = intval($j);
                                                }
                                                if (trim($sField) == "WorkNature") {
                                                    $col_26 = intval($j);
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $VendorName="";
                                $Address="";
                                $City="";
                                $Pincode="";
                                $CPerson="";
                                $Cno="";
                                $Mobno="";
                                $Phnno="";
                                $Emailid="";
                                $Faxno="";
                                $Supply="";
                                $Contract="";
                                $Service="";
                                $Cstno="";
                                $Tingst="";
                                $Tinno="";
                                $Panno="";
                                $Sertaxno="";
                                $Bankname="";
                                $Branch="";
                                $Accno="";
                                $Ifsccode="";
                                $Regno="";
                                $Regdate="";
                                $Chequename="";
                                $Worknature="";

                                //$ProjectName="";
                                if (isset($col_1) && !is_null($col_1) && trim($col_1)!="" && isset($xlData[$col_1])) {
                                    $VendorName =$this->bsf->isNullCheck(trim($xlData[$col_1]),'string');
                                }
                                if (isset($col_2) && !is_null($col_2) && trim($col_2)!="" && isset($xlData[$col_2])) {
                                    $Address =$this->bsf->isNullCheck(trim($xlData[$col_2]),'string');
                                }
                                if (isset($col_3) && !is_null($col_3) && trim($col_3)!="" && isset($xlData[$col_3])) {
                                    $City = $this->bsf->isNullCheck(trim($xlData[$col_3]),'string');
                                }
                                if (isset($col_4) && !is_null($col_4) && trim($col_4)!="" && isset($xlData[$col_4])) {
                                    $Pincode = $this->bsf->isNullCheck(trim($xlData[$col_4]),'string');
                                }
                                if (isset($col_5) && !is_null($col_5) && trim($col_5)!="" && isset($xlData[$col_5])) {
                                    $CPerson = $this->bsf->isNullCheck(trim($xlData[$col_5]),'string');
                                }
                                if (isset($col_6) && !is_null($col_6) && trim($col_6)!="" && isset($xlData[$col_6])) {
                                    $Cno = $this->bsf->isNullCheck(trim($xlData[$col_6]),'string');
                                }
                                if (isset($col_7) && !is_null($col_7) && trim($col_7)!="" && isset($xlData[$col_7])) {
                                    $Mobno = $this->bsf->isNullCheck(trim($xlData[$col_7]),'string');
                                }
                                if (isset($col_8) && !is_null($col_8) && trim($col_8)!="" && isset($xlData[$col_8])) {
                                    $Phnno = $this->bsf->isNullCheck(trim($xlData[$col_8]),'string');
                                }
                                if (isset($col_9) && !is_null($col_9) && trim($col_9)!="" && isset($xlData[$col_9])) {
                                    $Emailid = $this->bsf->isNullCheck(trim($xlData[$col_9]),'string');
                                }
                                if (isset($col_10) && !is_null($col_10) && trim($col_10)!="" && isset($xlData[$col_10])) {
                                    $Faxno = $this->bsf->isNullCheck(trim($xlData[$col_10]),'string');
                                }
                                if (isset($col_11) && !is_null($col_11) && trim($col_11)!="" && isset($xlData[$col_11])) {
                                    $Supply = $this->bsf->isNullCheck(trim($xlData[$col_11]),'string');
                                }
                                if (isset($col_12) && !is_null($col_12) && trim($col_12)!="" && isset($xlData[$col_12])) {
                                    $Contract = $this->bsf->isNullCheck(trim($xlData[$col_12]),'string');
                                }
                                if (isset($col_13) && !is_null($col_13) && trim($col_13)!="" && isset($xlData[$col_13])) {
                                    $Service = $this->bsf->isNullCheck(trim($xlData[$col_13]),'string');
                                }
                                if (isset($col_14) && !is_null($col_14) && trim($col_14)!="" && isset($xlData[$col_14])) {
                                    $Cstno = $this->bsf->isNullCheck(trim($xlData[$col_14]),'string');
                                }
                                if (isset($col_15) && !is_null($col_15) && trim($col_15)!="" && isset($xlData[$col_15])) {
                                    $Tingst = $this->bsf->isNullCheck(trim($xlData[$col_15]),'string');
                                }
                                if (isset($col_16) && !is_null($col_16) && trim($col_16)!="" && isset($xlData[$col_16])) {
                                    $Tinno = $this->bsf->isNullCheck(trim($xlData[$col_16]),'string');
                                }
                                if (isset($col_17) && !is_null($col_17) && trim($col_17)!="" && isset($xlData[$col_17])) {
                                    $Panno = $this->bsf->isNullCheck(trim($xlData[$col_17]),'string');
                                }
                                if (isset($col_18) && !is_null($col_18) && trim($col_18)!="" && isset($xlData[$col_18])) {
                                    $Sertaxno = $this->bsf->isNullCheck(trim($xlData[$col_18]),'string');
                                }
                                if (isset($col_19) && !is_null($col_19) && trim($col_19)!="" && isset($xlData[$col_19])) {
                                    $Bankname = $this->bsf->isNullCheck(trim($xlData[$col_19]),'string');
                                }
                                if (isset($col_20) && !is_null($col_20) && trim($col_20)!="" && isset($xlData[$col_20])) {
                                    $Branch = $this->bsf->isNullCheck(trim($xlData[$col_20]),'string');
                                }
                                if (isset($col_21) && !is_null($col_21) && trim($col_21)!="" && isset($xlData[$col_21])) {
                                    $Accno = $this->bsf->isNullCheck(trim($xlData[$col_21]),'string');
                                }
                                if (isset($col_22) && !is_null($col_22) && trim($col_22)!="" && isset($xlData[$col_22])) {
                                    $Ifsccode = $this->bsf->isNullCheck(trim($xlData[$col_22]),'string');
                                }
                                if (isset($col_23) && !is_null($col_23) && trim($col_23)!="" && isset($xlData[$col_23])) {
                                    $Regno = $this->bsf->isNullCheck(trim($xlData[$col_23]),'string');
                                }
                                if (isset($col_24) && !is_null($col_24) && trim($col_24)!="" && isset($xlData[$col_24])) {
                                    $Regdate = $this->bsf->isNullCheck(trim($xlData[$col_24]),'string');
                                }
                                if (isset($col_25) && !is_null($col_25) && trim($col_25)!="" && isset($xlData[$col_25])) {
                                    $Chequename = $this->bsf->isNullCheck(trim($xlData[$col_25]),'string');
                                }
                                if (isset($col_26) && !is_null($col_26) && trim($col_26)!="" && isset($xlData[$col_26])) {
                                    $Worknature = $this->bsf->isNullCheck(trim($xlData[$col_26]),'string');
                                }

                                if($VendorName!="" || $Address!="" || $City!="" || $Pincode!="" || $CPerson!="" || $Cno!=""  || $Mobno!=""  || $Phnno!=""  || $Emailid!=""  || $Faxno!=""  || $Supply!=""  || $Contract!="" || $Service!="" || $Cstno!=""  || $Tingst!="" || $Tinno!="" || $Panno!="" || $Sertaxno!="" || $Bankname!="" || $Branch!="" || $Accno!="" || $Ifsccode!="" || $Regno!="" || $Regno!="" || $Regdate!="" || $Chequename!="" || $Worknature!="") {
                                    $data[] = array('Valid' => $bValid, 'VendorName' => $VendorName, 'Address' => $Address, 'City' => $City, 'Pincode' => $Pincode, 'ContactPerson' => $CPerson,  'ContactNo' => $Cno,  'MobileNo' => $Mobno,  'PhoneNo' => $Phnno,  'EmailId' => $Emailid,  'FaxNo' => $Faxno, 'Supply' => $Supply, 'Contract' => $Contract,
                                        'Service' => $Service, 'CSTNo' => $Cstno, 'TINGST' => $Tingst,  'TINNo' => $Tinno,  'PANNo' => $Panno,  'ServiceTaxNo' => $Sertaxno,  'BankName' => $Bankname, 'Branch' => $Branch, 'ACNo' => $Accno, 'IFSCCode' => $Ifsccode, 'RegNo' => $Regno,'RegDate' => $Regdate, 'ChequeName' => $Chequename, 'WorkNature' => $Worknature,);
                                }
                            }
                            $icount++;
                        }

                        if ($bValid == false) {
                            $data[] = array('Valid' => $bValid);
                        }
                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else {
                        $postData = $request->getPost();
                        $rowCount = $postData['rowCount'];
                        $VendorCode = '';
                        $vendorId='';

                        $data = array();
                        for ($i = 0; $i <= $rowCount; $i++) {
                            $VendorName = $this->bsf->isNullCheck(trim($postData['excelvname_' . $i]), 'string');
                            $Address = $this->bsf->isNullCheck(trim($postData['exceladdress_' . $i]), 'string');
                            $City = $this->bsf->isNullCheck(trim($postData['excelcity_' . $i]), 'string');
                            $Pincode = $this->bsf->isNullCheck(trim($postData['excelpincode_' . $i]), 'string');
                            $CPerson = $this->bsf->isNullCheck(trim($postData['excelcperson_' . $i]), 'string');
                            $Cno = $this->bsf->isNullCheck(trim($postData['excelcno_' . $i]), 'string');
                            $Mobno = $this->bsf->isNullCheck(trim($postData['excelmobno_' . $i]), 'string');
                            $Phnno = $this->bsf->isNullCheck(trim($postData['excelphnno_' . $i]), 'string');
                            $Emailid = $this->bsf->isNullCheck(trim($postData['excelemail_' . $i]), 'string');
                            $Faxno = $this->bsf->isNullCheck(trim($postData['excelfaxno_' . $i]), 'string');
                            $Supply = $this->bsf->isNullCheck(trim($postData['excelsupply_' . $i]), 'string');
                            $Contract = $this->bsf->isNullCheck(trim($postData['excelservice_' . $i]), 'string');
                            $Service = $this->bsf->isNullCheck(trim($postData['excelcontract_' . $i]), 'string');
                            $Cstno = $this->bsf->isNullCheck(trim($postData['excelcstno_' . $i]), 'string');
                            $Tingst = $this->bsf->isNullCheck(trim($postData['exceltingst_' . $i]), 'string');
                            $Tinno = $this->bsf->isNullCheck(trim($postData['exceltinno_' . $i]), 'string');
                            $Panno = $this->bsf->isNullCheck(trim($postData['excelpanno_' . $i]), 'string');
                            $Sertaxno = $this->bsf->isNullCheck(trim($postData['excelservicetaxno_' . $i]), 'string');
                            $Bankname = $this->bsf->isNullCheck(trim($postData['excelbankname_' . $i]), 'string');
                            $Branch = $this->bsf->isNullCheck(trim($postData['excelbranch_' . $i]), 'string');
                            $Accno = $this->bsf->isNullCheck(trim($postData['excelacno_' . $i]), 'string');
                            $Ifsccode = $this->bsf->isNullCheck(trim($postData['excelifsccode_' . $i]), 'string');
                            $Regno = $this->bsf->isNullCheck(trim($postData['excelregno_' . $i]), 'string');
                            $Regdate = $this->bsf->isNullCheck(trim($postData['excelregdate_' . $i]), 'string');
                            $Chequename = $this->bsf->isNullCheck(trim($postData['excelchquename_' . $i]), 'string');
                            $Worknature = $this->bsf->isNullCheck(trim($postData['excelworknautre_' . $i]), 'string');

                            if ($VendorName == "" && $Address == "" && $City == "" && $Pincode == "" && $CPerson == "" && $Cno == "" && $Mobno == "" && $Phnno == "" && $Emailid == "" && $Faxno == "" && $Supply == "" && $Contract == "" && $Service == "" && $Cstno == "" && $Tingst == "" && $Tinno == "" && $Panno == "" && $Sertaxno == "" && $Bankname == "" && $Branch == "" && $Accno == "" && $Ifsccode == "" && $Regno == "" && $Regdate == "" && $Chequename == "" && $Worknature == "") {
                                continue;
                            }
                            $error = 0;
                            if ($VendorName == "") {
                                $nameArray = array($VendorName, 1);
                                $addressArray = array($Address, 0);
								$worknatureArray = array($Worknature, 0);
								$error = 1;
                            } else {
                                $nameArray = array($VendorName, 0);
                            }

                            if ($Address == "") {
                                $addressArray = array($Address, 1);
                                $worknatureArray = array($Worknature, 0);
                                $error = 1;
                            } else {
                                $addressArray = array($Address, 0);
                            }
                            if ($Worknature == "") {
                                $worknatureArray = array($Worknature, 1);
                                $error = 1;
                            } else {
                                $worknatureArray = array($Worknature, 0);
                            }

							$cityArray = array($City, 0);
							$pincodeArray = array($Pincode, 0);
							$cpersonArray = array($CPerson, 0);					
							$cnoArray = array($Cno, 0);
							$mobnoArray = array($Mobno, 0);
							$phnnoArray = array($Phnno, 0);
							$emailArray = array($Emailid, 0);
							$faxnoArray = array($Faxno, 0);
							$supplyArray = array($Supply, 0);
							$contractArray = array($Contract, 0);
							$serviceArray = array($Service, 0);
							$cstnoArray = array($Cstno, 0);
							$tingstArray = array($Tingst, 0);
							$tinnoArray = array($Tinno, 0);
							$pannnoArray = array($Panno, 0);
							$sertaxArray = array($Sertaxno, 0);
							$banknameArray = array($Bankname, 0);
							$branchArray = array($Branch, 0);
							$accnoArray = array($Accno, 0);
							$ifsccodeArray = array($Ifsccode, 0);
							$regnoArray = array($Regno, 0);
							$regdateArray = array($Regdate, 0);
							$cnameArray = array($Chequename, 0);

                            if ($error == 0) {

                                if($City!="") {
                                    $select = $sql->select();
                                    $select->from('WF_CityMaster')
                                        ->columns(array('CityId', 'CityName'))
                                        ->where(array('CityName' => $City));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $cityCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($cityCheck)>0) {
                                        $City= $cityCheck[0]['CityId'];

                                    } else {
                                        $City=0;
                                    }
                                } else {
                                    $City=0;
                                }

                                if($Worknature!="") {
                                    $select = $sql->select();
                                    $select->from('Vendor_ServiceType')
                                        ->columns(array('ServiceType', 'ServiceTypeId'))
                                        ->where(array('ServiceType' => $Worknature));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $workCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($workCheck)>0) {
                                        $Worknature= $workCheck[0]['ServiceTypeId'];

                                    } else {
                                        $Worknature=0;
                                    }
                                } else {
                                    $Worknature=0;
                                }

                                $insert = $sql->insert('Vendor_Master');
                                $newData = array(
                                    'VendorName' => $VendorName,
                                    'RegAddress' => $Address,
                                    'Pincode' => $Pincode,
                                    'CityId' => $City,
                                    'Supply' => $Supply,
                                    'Contract' => $Contract,
                                    'Service' => $Service,
                                    'ServiceTypeId' => $Worknature,
                                    'PANNO' => $Panno
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $vendorId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $insert = $sql->insert('Vendor_Contact');
                                 $newData = array(
                                     'VendorID'  => $vendorId,
                                     'CPerson1' => $CPerson,
                                     'ContactNo1' => $Cno,
                                     'Mobile1' => $Mobno,
                                     'Phone1' => $Phnno,
                                     'Email1' => $Emailid,
                                     'Fax1' => $Faxno
                                 );
                                 $insert->values($newData);
                                 $statement = $sql->getSqlStringForSqlObject($insert);
                                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                 $insert = $sql->insert('Vendor_Registration');
                                 $newData = array(
                                     'VendorID'  => $vendorId,
                                     'RegDate' => $Regdate,
                                     'RegNo' => $Regno
                                 );
                                 $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                 $insert = $sql->insert('Vendor_StatutoryBankDetail');
                                 $newData = array(
                                     'VendorID'  => $vendorId,
                                     'BankAccountNo' => $Accno,
                                     'BankName' => $Bankname,
                                     'BranchName' => $Branch,
                                     'IFSCCode' => $Ifsccode
                                 );
                                 $insert->values($newData);
                                 $statement = $sql->getSqlStringForSqlObject($insert);
                                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                 $insert = $sql->insert('Vendor_Statutory');
                                 $newData = array(
                                     'VendorID'  => $vendorId,
                                     'TINNO' => $Tinno,
                                     'ServiceTaxNo' => $Sertaxno,
                                     'TNGSTNo' => $Tingst,
                                     'ChequeonName' => $Chequename,
                                     'CSTNo' => $Cstno
                                 );
                                 $insert->values($newData);
                                 $statement = $sql->getSqlStringForSqlObject($insert);
                                 $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

							else {
								$data[] = array('VendorName' => $nameArray, 
								'Address' => $addressArray,
								'City' => $cityArray,
								'Pincode' => $pincodeArray,
								'ContactPerson' => $cpersonArray,
								'ContactNo' => $cnoArray,
								'MobileNo' => $mobnoArray,
								'PhoneNo' => $phnnoArray,
								'EmailId' => $emailArray,
								'FaxNo' => $faxnoArray,
								'Supply' => $supplyArray,
								'Contract' => $contractArray, 
								'Service' => $serviceArray, 
								'CSTNo' => $cstnoArray, 
								'TINGST' => $tingstArray, 
								'TINNo' => $tinnoArray, 
								'PANNo' => $pannnoArray, 
								'ServiceTaxNo' => $sertaxArray, 
								'BankName' => $banknameArray, 
								'Branch' => $branchArray, 
								'ACNo' => $accnoArray, 
								'IFSCCode' => $ifsccodeArray, 
								'RegNo' => $regnoArray,
								'RegDate' => $regdateArray,
								'ChequeName' => $cnameArray, 
								'WorkNature' => $worknatureArray );
							}
                        }

                    }
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($data));

                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        }
    }
}