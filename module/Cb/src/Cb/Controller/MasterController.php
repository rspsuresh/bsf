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
use Application\View\Helper\CommonHelper;

class MasterController extends AbstractActionController
{
	public function __construct()	{
		$this->bsf = new \BuildsuperfastClass();
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
        
	}

	public function clientAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $session_pref = new Container('subscriber_pref');
        $this->_view->NoOfClientCount = $session_pref->NoOfClientCount;

        $select = $sql->select();
        $select->from( array( 'c' => 'CB_ClientMaster' ))
			->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=c.CityId', array('CityName'), $select::JOIN_LEFT)
            ->columns( array( 'ClientId','ClientName','Address', 'Email'))
            ->where("c.DeleteFlag='0' and c.SubscriberId=$subscriberId")
            ->order('c.ClientId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->clientReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
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
                            $select->from( 'CB_WORegister' )
                                ->columns( array( 'ClientId' ) )
                                ->where( array( 'ClientId' => $ClientId ) );

                            $select2 = $sql->select();
                            $select2->from( 'CB_ReceiptRegister' )
                                ->columns( array( 'ClientId' ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $select2->combine( $select, 'Union ALL' );

                            $select1 = $sql->select();
                            $select1->from( 'CB_ProjectMaster' )
                                ->columns( array( 'ClientId' ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $select1->combine( $select2, 'Union ALL' );

                            $statement = $sql->getSqlStringForSqlObject( $select1 );
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
                            $select->from( 'CB_ClientMaster' )
                                ->columns( array( 'ClientName' ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $clientname = $bills->ClientName;

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table( 'CB_ClientMaster' )
                                ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'Remarks' => $Remarks ) )
                                ->where( array( 'ClientId' => $ClientId ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                            CommonHelper::insertCBLog( 'Client-Master-Delete', $ClientId, $clientname, $dbAdapter );
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
        
        // csrf validation
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
                    $update->table('CB_ClientMaster')
                        ->set(array('ClientName' => $clientName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email))
                        ->where(array('ClientId' => $ClientId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Client-Master-Edit',$ClientId,$clientName,$dbAdapter);

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
	
	public function addclientAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest())	{

            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from( array( 'c' => 'CB_ClientMaster' ))
                ->columns( array( 'ClientId'))
                ->where("c.DeleteFlag='0' and c.SubscriberId=$subscriberId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $clients = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $session_pref = new Container('subscriber_pref');
            if(count($clients) >= $session_pref->NoOfClientCount) {

                $response->setStatusCode('201');
                $response->setContent('Client limit exceeded');
                return $response;
            }

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
                    $insert->into('CB_ClientMaster');
                    $insert->Values(array('ClientName' => $clientName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email,'SubscriberId'=>$subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('Client-Master-Add',$clientId,$clientName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from( array( 'c' => 'CB_ClientMaster' ))
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

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
						$select->from( array( 'c' => 'CB_ClientMaster' ))
							->columns( array( 'ClientId'))
                            ->where( "ClientName='$clientName' and ClientId<> '$clientId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
						$clientName = $this->params()->fromPost('clientNamenew');
						$select->from( array( 'c' => 'CB_ClientMaster' ))
							->columns( array( 'ClientId'))
                            ->where( "ClientName='$clientName' and SubscriberId=$subscriberId and DeleteFlag=0");
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

	public function vendorAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Vendor Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $select = $sql->select();
        $select->from( array( 'vm' => 'CB_VendorMaster' ))
			->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=vm.CityId', array('CityName'), $select::JOIN_LEFT)
            ->columns( array( 'VendorId','VendorName','Address', 'Email', 'Mobile' ))
            ->where("vm.DeleteFlag='0' and vm.SubscriberId=$subscriberId")
            ->order('vm.VendorId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->vendorReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function checkvendorfoundAction(){
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

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('vendorId'), 'number');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($vendorId != 0){
						$vendorName = $this->bsf->isNullCheck($this->params()->fromPost('vendorName'), 'string');
						$select->from( array( 'c' => 'CB_VendorMaster' ))
							->columns( array( 'VendorId'))
                            ->where( "VendorName='$vendorName' and VendorId<> '$vendorId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else {
						$vendorName = $this->bsf->isNullCheck($this->params()->fromPost('vendorNamenew'), 'string');
						$select->from( array( 'c' => 'CB_VendorMaster' ))
							->columns( array( 'VendorId'))
                            ->where( "VendorName='$vendorName' and SubscriberId=$subscriberId and DeleteFlag=0");
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
	
	public function addvendorAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();

                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                try {                   
                    $vendorName = $this->bsf->isNullCheck($this->params()->fromPost('vendorNamenew'), 'string');
					$address = $this->bsf->isNullCheck($this->params()->fromPost('addressnew'), 'string');
					$cityName = $this->bsf->isNullCheck($this->params()->fromPost('citynew'), 'string');
					$stateName = $this->bsf->isNullCheck($this->params()->fromPost('statenew'), 'string');
					$countryName = $this->bsf->isNullCheck($this->params()->fromPost('countrynew'), 'string');
					$email = $this->bsf->isNullCheck($this->params()->fromPost('emailnew'), 'string');
					$mobile = $this->bsf->isNullCheck($this->params()->fromPost('mobilenew'), 'string');
					
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
					
                    $insert = $sql->insert();
                    $insert->into('CB_VendorMaster');
                    $insert->Values(array('vendorName' => $vendorName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email, 'Mobile' => $mobile,'SubscriberId'=>$subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$vendorId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('Vendor-Master-Add',$vendorId,$vendorName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from( array( 'vm' => 'CB_VendorMaster' ))
							->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=vm.CityId', array('CityName'), $select::JOIN_LEFT)
							->columns( array( 'VendorId','VendorName','Address', 'Email', 'Mobile' ))
                            ->where( "VendorId='$vendorId'" );
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
	
	public function editvendorAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $vendorId = $this->bsf->isNullCheck($this->params()->fromPost('vendorId'), 'number');
                    $vendorName = $this->bsf->isNullCheck($this->params()->fromPost('vendorName'), 'string');
					$address = $this->bsf->isNullCheck($this->params()->fromPost('address'), 'string');
					$cityName = $this->bsf->isNullCheck($this->params()->fromPost('city'), 'string');
					$stateName = $this->bsf->isNullCheck($this->params()->fromPost('state'), 'string');
					$countryName = $this->bsf->isNullCheck($this->params()->fromPost('country'), 'string');
					$email = $this->bsf->isNullCheck($this->params()->fromPost('email'), 'string');
					$mobile = $this->bsf->isNullCheck($this->params()->fromPost('mobile'), 'string');
					
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
                    $update->table('CB_VendorMaster')
                        ->set(array('VendorName' => $vendorName, 'Address' => $address, 'CityId' => $cityId, 'Email' => $email, 'Mobile' => $mobile))
                        ->where(array('VendorId' => $vendorId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Vendor-Master-Edit',$vendorId,$vendorName,$dbAdapter);

                    $connection->commit();

                    $status = 'Edit';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
	
	public function deletevendorAction(){
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
                    $vendorId = $this->params()->fromPost('VendorId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    // check for already exists
                    $select = $sql->select();
                    $select->from('CB_BillMaterialBillTrans')
                        ->columns(array('VendorId'))
                        ->where(array('VendorId' => $vendorId));

                    $select1 = $sql->select();
                    $select1->from('CB_BillPriceEscalationBillTrans')
                        ->columns(array('VendorId'))
                        ->where(array('VendorId' => $vendorId));
                    $select1->combine($select,'Union ALL');

                    $select2 = $sql->select();
                    $select2->from('CB_BillVendorBill')
                        ->columns(array('VendorId'))
                        ->where(array('VendorId' => $vendorId));
                    $select2->combine($select1,'Union ALL');

                    $statement = $sql->getSqlStringForSqlObject( $select2 );
                    $vendors = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(count($vendors) > 0) {
                        $response->setStatusCode(201)->setContent($status);
                    } else {
                        $connection->beginTransaction();
                        $select = $sql->select();
                        $select->from('CB_VendorMaster')
                            ->columns(array('VendorName'))
                            ->where(array('VendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $vName=$bills->VendorName;

                        $update = $sql->update();
                        $update->table('CB_VendorMaster')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                            ->where(array('VendorId' => $vendorId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('Vendor-Master-Delete',$vendorId,$vName,$dbAdapter);

                        $connection->commit();
                        $status = 'deleted';
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

	public function materialAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Material Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

		$select = $sql->select();
        $select->from( array('mm' => 'CB_MaterialMaster'))
			->join( array('pu' => 'Proj_UOM'), 'pu.UnitId=mm.UnitId', array('UnitName'), $select::JOIN_LEFT)
            ->columns( array( 'MaterialId','MaterialName','UnitId'))
            ->where("mm.DeleteFlag='0' and mm.SubscriberId=$subscriberId")
            ->order('mm.MaterialId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->materialReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		// unit list
		$unitSelect = $sql->select();
		$unitSelect->from('Proj_UOM')
				->columns(array("data"=>"UnitId", "value"=>"UnitName"));
		$unitStmt = $sql->getSqlStringForSqlObject($unitSelect);
		$units = $dbAdapter->query($unitStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->units = $units;
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function checkmaterialFoundAction(){
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
					$materialId = $this->params()->fromPost('materialId');

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($materialId != null){
						$materialName = $this->params()->fromPost('materialName');	
						$select->from( array( 'c' => 'CB_MaterialMaster' ))
							->columns( array( 'MaterialId'))
                            ->where( "MaterialName='$materialName' and MaterialId<> '$materialId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
						$materialName = $this->params()->fromPost('materialNamenew');
						$select->from( array( 'c' => 'CB_MaterialMaster' ))
							->columns( array( 'MaterialId'))
                            ->where( "MaterialName='$materialName' and SubscriberId=$subscriberId and DeleteFlag=0");
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
	
	public function addmaterialAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {                   
                    $materialName = $this->params()->fromPost('materialNamenew');
					$unitId = $this->params()->fromPost('unitNew');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('CB_MaterialMaster');
                    $insert->Values(array('MaterialName' => $materialName, 'UnitId' => $unitId,'SubscriberId' => $subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$materialId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('Material-Master-Add',$materialId,$materialName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from( array('mm' => 'CB_MaterialMaster'))
							->join( array('pu' => 'Proj_UOM'), 'pu.UnitId=mm.UnitId', array('UnitName'), $select::JOIN_LEFT)
							->columns( array( 'MaterialId','MaterialName','UnitId'))
                            ->where( "MaterialId='$materialId'" );
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
	
	public function editmaterialAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $materialId = $this->params()->fromPost('materialId');
                    $materialName = $this->params()->fromPost('materialName');
					$unitId = $this->params()->fromPost('unitId');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('CB_MaterialMaster')
                        ->set(array('MaterialName' => $materialName, 'unitId' => $unitId))
                        ->where(array('MaterialId' => $materialId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Material-Master-Edit',$materialId,$materialName,$dbAdapter);

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
	
	public function deletematerialAction(){
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
                    $materialId =  $this->bsf->isNullCheck($this->params()->fromPost('materialId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select = $sql->select();
                            $select->from('CB_BillMaterialAdvance')
                                ->columns(array('MaterialId'))
                                ->where(array('MaterialId' => $materialId));

                            $select1 = $sql->select();
                            $select1->from('CB_BillPriceEscalation')
                                ->columns(array('MaterialId'))
                                ->where(array('MaterialId' => $materialId));
                            $select1->combine($select,'Union ALL');

                            $select2 = $sql->select();
                            $select2->from('CB_BillMaterialRecovery')
                                ->columns(array('MaterialId'))
                                ->where(array('MaterialId' => $materialId));
                            $select2->combine($select1,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from('CB_BillFreeSupplyMaterial')
                                ->columns(array('MaterialId'))
                                ->where(array('MaterialId' => $materialId));
                            $select3->combine($select2,'Union ALL');

                            $select4 = $sql->select();
                            $select4->from('CB_BillMaterialRecovery')
                                ->columns(array('MaterialId'))
                                ->where(array('MaterialId' => $materialId));
                            $select4->combine($select3,'Union ALL');

                            $statement = $sql->getSqlStringForSqlObject( $select4 );
                            $material = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($material) > 0) {
                                $response->setStatusCode( 201 )->setContent( $status );
                                return $response;
                            }

                            $response->setStatusCode('200')->setContent('Not used');
                            return $response;

                            break;
                        case 'update':
                            $select = $sql->select();
                            $select->from( 'CB_MaterialMaster' )
                                ->columns( array( 'MaterialName' ) )
                                ->where( array( 'MaterialId' => $materialId ) );
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $mname = $bills->MaterialName;

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table( 'CB_MaterialMaster' )
                                ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'Remarks' => $Remarks ) )
                                ->where( array( 'MaterialId' => $materialId ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                            CommonHelper::insertCBLog( 'Material-Master-Delete', $materialId, $mname, $dbAdapter );
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

	public function projecttypeAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || ProjectType Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

		$select = $sql->select();
        $select->from( 'CB_ProjectTypeMaster' )
            ->columns( array( 'ProjectTypeId','ProjectTypeName' ))
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId")
            ->order('ProjectTypeId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->projecttypeReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function deleteprojecttypeAction(){
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
                    $ProjectTypeId = $this->params()->fromPost('ProjectTypeId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    // check for already exists
                    $select1 = $sql->select();
                    $select1->from('CB_ProjectMaster')
                        ->columns(array('ProjectTypeId'))
                        ->where(array('ProjectTypeId' => $ProjectTypeId));
                    $statement = $sql->getSqlStringForSqlObject( $select1 );
                    $type = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    if(count($type) > 0) {
                        $response->setStatusCode(201)->setContent($status);
                    } else {
                        $select = $sql->select();
                        $select->from( 'CB_ProjectTypeMaster' )
                            ->columns( array( 'ProjectTypeName' ) )
                            ->where( array( 'ProjectTypeId' => $ProjectTypeId ) );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $ptypename = $bills->ProjectTypeName;

                        $connection->beginTransaction();
                        $update = $sql->update();
                        $update->table( 'CB_ProjectTypeMaster' )
                            ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'Remarks' => $Remarks ) )
                            ->where( array( 'ProjectTypeId' => $ProjectTypeId ) );
                        $statement = $sql->getSqlStringForSqlObject( $update );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                        CommonHelper::insertCBLog( 'ProjectType-Master-Delete', $ProjectTypeId, $ptypename, $dbAdapter );

                        $connection->commit();
                        $status = 'deleted';
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
	
	public function editprojecttypeAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $ProjectTypeId = $this->params()->fromPost('ProjectTypeId');
                    $projecttypeName = $this->params()->fromPost('projecttypeName');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('CB_ProjectTypeMaster')
                        ->set(array('ProjectTypeName' => $projecttypeName))
                        ->where(array('ProjectTypeId' => $ProjectTypeId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('ProjectType-Master-Edit',$ProjectTypeId,$projecttypeName,$dbAdapter);

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
	
	public function addprojecttypeAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {                   
                    $projecttypeName = $this->params()->fromPost('projecttypeNamenew');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('CB_ProjectTypeMaster');
                    $insert->Values(array('ProjectTypeName' => $projecttypeName,'SubscriberId' =>$subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$projecttypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('ProjectType-Master-Add',$projecttypeId,$projecttypeName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from( 'CB_ProjectTypeMaster' )
                            ->columns( array( 'ProjectTypeId', 'ProjectTypeName' ))
                            ->where( "ProjectTypeId='$projecttypeId'" );
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
	
	public function checkprojecttypefoundAction(){
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
					$ProjectTypeId = $this->params()->fromPost('ProjectTypeId');

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($ProjectTypeId != null){
						$projecttypeName = $this->params()->fromPost('projecttypeName');	
						$select->from( array( 'c' => 'CB_ProjectTypeMaster' ))
							->columns( array( 'ProjectTypeId'))
                            ->where( "ProjectTypeName='$projecttypeName' and ProjectTypeId<> '$ProjectTypeId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
						$projecttypeName = $this->params()->fromPost('projecttypeNamenew');
						$select->from( array( 'c' => 'CB_ProjectTypeMaster' ))
							->columns( array( 'ProjectTypeId'))
                            ->where( "ProjectTypeName='$projecttypeName' and SubscriberId=$subscriberId and DeleteFlag=0");
					}
								
					$statement = $sql->getSqlStringForSqlObject($select);
					$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    
    public function workgroupAction(){
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
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

		$select = $sql->select();
        $select->from( 'CB_WorkGroupMaster' )
            ->columns( array( 'WorkGroupId','WorkGroupName' ))
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->workgroupReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function deleteworkgroupAction(){
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
                    $WorkGroupId = $this->params()->fromPost('WorkGroupId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('CB_WorkGroupMaster')
                        ->columns(array('WorkGroupName'))
                        ->where(array('WorkGroupId' => $WorkGroupId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $ptypename =$bills->WorkGroupName;

                    $update = $sql->update();
                    $update->table('CB_WorkGroupMaster')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                        ->where(array('WorkGroupId' => $WorkGroupId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('WorkGroup-Master-Delete',$WorkGroupId,$ptypename,$dbAdapter);

                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
	
	public function editworkgroupAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $WorkGroupId = $this->params()->fromPost('WorkGroupId');
                    $workgroupName = $this->params()->fromPost('workgroupName');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('CB_WorkGroupMaster')
                        ->set(array('WorkGroupName' => $workgroupName))
                        ->where(array('WorkGroupId' => $WorkGroupId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('WorkGroup-Master-Edit',$WorkGroupId,$workgroupName,$dbAdapter);

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
	
	public function addworkgroupAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {                   
                    $workgroupName = $this->params()->fromPost('workgroupNamenew');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('CB_WorkGroupMaster');
                    $insert->Values(array('WorkGroupName' => $workgroupName,'SubscriberId' =>$subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$workgroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('WorkGroup-Master-Add',$workgroupId,$workgroupName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from( 'CB_WorkGroupMaster' )
                            ->columns( array( 'WorkGroupId', 'WorkGroupName' ))
                            ->where( "WorkGroupId='$workgroupId'" );
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
	
	public function checkworkgroupFoundAction(){
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
					$WorkGroupId = $this->params()->fromPost('WorkGroupId');

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($WorkGroupId != null){
						$workgroupName = $this->params()->fromPost('workgroupName');	
						$select->from( array( 'c' => 'CB_WorkGroupMaster' ))
							->columns( array( 'WorkGroupId'))
                            ->where( "WorkGroupName='$workgroupName' and WorkGroupId<> '$WorkGroupId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
						$workgroupName = $this->params()->fromPost('workgroupNamenew');
						$select->from( array( 'c' => 'CB_WorkGroupMaster' ))
							->columns( array( 'WorkGroupId'))
                            ->where( "WorkGroupName='$workgroupName' and SubscriberId=$subscriberId and DeleteFlag=0");
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
    
	public function projectAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        $session_pref = new Container('subscriber_pref');
        $this->_view->NoOfClientCount = $session_pref->NoOfClientCount;

        $select = $sql->select();
		$select->from(array("a"=>"CB_ProjectMaster"));
		$select->columns(array("ProjectId","ProjectName","ProjectDescription","ProjectTypeId"
					,"ProjectTypeName"=>new Expression("b.ProjectTypeName"),"ClientId","ClientName"=>new Expression("c.ClientName"),"Address"))
					->join(array("b"=>"CB_ProjectTypeMaster"), "a.ProjectTypeId=b.ProjectTypeId", array(), $select::JOIN_LEFT)
					->join(array("c"=>"CB_ClientMaster"), "a.ClientId=c.ClientId", array(), $select::JOIN_LEFT)	
					->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=a.CityId', array('CityName'), $select::JOIN_LEFT)
                    ->where("a.DeleteFlag='0' and a.SubscriberId=$subscriberId")
                    ->order('a.ProjectId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->projectReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		// Project Types
		$select = $sql->select();
		$select->from( 'CB_ProjectTypeMaster' )
			->columns( array( 'data' => 'ProjectTypeId', 'value' => 'ProjectTypeName' ) )
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->projecttypes = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

		// Clients
		$select = $sql->select();
		$select->from( 'CB_ClientMaster' )
			->columns( array( 'data' => 'ClientId', 'value' => 'ClientName' ) )
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->clients = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function deleteprojectAction(){
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
                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select1 = $sql->select();
                            $select1->from('CB_WORegister')
                                ->columns(array('ProjectId'))
                                ->where(array('ProjectId' => $ProjectId));

                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $project = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            if(count($project) > 0) {
                                $response->setStatusCode( 201 )->setContent( $status );
                                return $response;
                            }

                            $response->setStatusCode('200')->setContent('Not used');
                            return $response;
                            break;
                        case 'update':
                            $select = $sql->select();
                            $select->from('CB_ProjectMaster')
                                ->columns(array('ProjectName'))
                                ->where(array('ProjectId' => $ProjectId));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $pname=$bills->ProjectName;

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table('CB_ProjectMaster')
                                ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('ProjectId' => $ProjectId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            CommonHelper::insertCBLog('Project-Master-Delete',$ProjectId,$pname,$dbAdapter);

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
	
	public function editprojectAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
					$postData = $request->getPost();
                    $ProjectId = $this->bsf->isNullCheck( $postData['projectId'], 'number' );
                    $projectName = $this->bsf->isNullCheck( $postData['projectName'], 'string' );
					$projecttypeName = $this->bsf->isNullCheck( $postData['ProjectTypeName'], 'string' );
					$projecttypeId = $this->bsf->isNullCheck( $postData['ProjectTypeId'], 'number' );
					$clientName = $this->bsf->isNullCheck( $postData['ClientName'], 'string' );
					$clientId = $this->bsf->isNullCheck( $postData['ClientId'], 'number' );
					$projectDescription = $this->bsf->isNullCheck( $postData['projectDescription'], 'string' );
					$address = $this->bsf->isNullCheck( $postData['address'], 'string' );
					$cityName = $this->params()->fromPost('city');
					$stateName = $this->params()->fromPost('state');
					$countryName = $this->params()->fromPost('country');
					
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

					if ( $projecttypeId == 'new' || $projecttypeId == '0' ) {
						$insert = $sql->insert();
						$insert->into( 'CB_ProjectTypeMaster' );
						$insert->Values( array( 'ProjectTypeName' => $projecttypeName,'SubscriberId'=>$subscriberId));
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$projecttypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        CommonHelper::insertCBLog('ProjectType-Master-Add',$projecttypeId,$projecttypeName,$dbAdapter);

					}
					
					if ( $clientId == 'new' || $clientId == '0' ) {
						$insert = $sql->insert();
						$insert->into( 'CB_ClientMaster' );
						$insert->Values( array( 'ClientName' => $clientName, 'Address' => $address,'SubscriberId'=>$subscriberId) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        CommonHelper::insertCBLog('Client-Master-Add',$clientId,$clientName,$dbAdapter);
					}					

                    $update = $sql->update();
                    $update->table('CB_ProjectMaster')
                        ->set(array('ProjectName' => $projectName, 'ProjectDescription' => $projectDescription, 'ProjectTypeId' => $projecttypeId
						, 'ClientId' => $clientId, 'Address' => $address, 'CityId' => $cityId))
                        ->where(array('ProjectId' => $ProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Project-Master-Edit',$ProjectId,$projectName,$dbAdapter);

                    $connection->commit();
					
					$select = $sql->select();
					$select->from(array("a"=>"CB_ProjectMaster"));
					$select->columns(array("ProjectId","ProjectName","ProjectDescription","ProjectTypeId"
								,"ProjectTypeName"=>new Expression("b.ProjectTypeName"),"ClientId","ClientName"=>new Expression("c.ClientName"),"Address"))
								->join(array("b"=>"CB_ProjectTypeMaster"), "a.ProjectTypeId=b.ProjectTypeId", array(), $select::JOIN_LEFT)
								->join(array("c"=>"CB_ClientMaster"), "a.ClientId=c.ClientId", array(), $select::JOIN_LEFT)
								->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=a.CityId', array('CityName'), $select::JOIN_LEFT)
								->where(array('a.ProjectId' => $ProjectId));
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
	
	public function addprojectAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {                   
                    $postData = $request->getPost();
                    $projectName = $this->bsf->isNullCheck( $postData['projectName'], 'string' );
					$projecttypeName = $this->bsf->isNullCheck( $postData['ProjectTypeName'], 'string' );
					$projecttypeId = $this->bsf->isNullCheck( $postData['ProjectTypeId'], 'number' );
					$clientName = $this->bsf->isNullCheck( $postData['ClientName'], 'string' );
					$clientId = $this->bsf->isNullCheck( $postData['ClientId'], 'number' );
					$projectDescription = $this->bsf->isNullCheck( $postData['projectDescription'], 'string' );
					$address = $this->bsf->isNullCheck( $postData['address'], 'string' );
					$cityName = $this->params()->fromPost('city');
					$stateName = $this->params()->fromPost('state');
					$countryName = $this->params()->fromPost('country');
					
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

					if ( $projecttypeId == 'new' || $projecttypeId == '0' ) {
						$insert = $sql->insert();
						$insert->into( 'CB_ProjectTypeMaster' );
						$insert->Values( array( 'ProjectTypeName' => $projecttypeName,'SubscriberId'=>$subscriberId));
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$projecttypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        CommonHelper::insertCBLog('ProjectType-Master-Add',$projecttypeId,$projecttypeName,$dbAdapter);

					}
					
					if ( $clientId == 'new' || $clientId == '0' ) {
						$insert = $sql->insert();
						$insert->into( 'CB_ClientMaster' );
						$insert->Values( array( 'ClientName' => $clientName, 'Address' => $address,'SubscriberId'=>$subscriberId) );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$clientId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        CommonHelper::insertCBLog('Client-Master-Add',$clientId,$clientName,$dbAdapter);
					}

                    $insert = $sql->insert();
                    $insert->into('CB_ProjectMaster');
                    $insert->Values(array('ProjectName' => $projectName, 'ProjectDescription' => $projectDescription, 'ProjectTypeId' => $projecttypeId
						, 'ClientId' => $clientId, 'Address' => $address, 'CityId' => $cityId,'SubscriberId'=>$subscriberId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$projectId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('Project-Master-Add',$projectId,$projectName,$dbAdapter);

                    $connection->commit();
	
					$select = $sql->select();
					$select->from(array("a"=>"CB_ProjectMaster"));
					$select->columns(array("ProjectId","ProjectName","ProjectDescription","ProjectTypeId"
								,"ProjectTypeName"=>new Expression("b.ProjectTypeName"),"ClientId","ClientName"=>new Expression("c.ClientName"),"Address"))
								->join(array("b"=>"CB_ProjectTypeMaster"), "a.ProjectTypeId=b.ProjectTypeId", array(), $select::JOIN_LEFT)
								->join(array("c"=>"CB_ClientMaster"), "a.ClientId=c.ClientId", array(), $select::JOIN_LEFT)	
								->join( array('cm' => 'WF_CityMaster'), 'cm.CityId=a.CityId', array('CityName'), $select::JOIN_LEFT)
								->where( "a.ProjectId='$projectId'" );
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
	
	public function checkprojectfoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
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
							
						$select->from( array( 'c' => 'CB_ProjectMaster' ))
							->columns( array( 'ProjectId'))
                            ->where( "ProjectName='$projectName' and ProjectId<> '$projectId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
						
						$select->from( array( 'c' => 'CB_ProjectMaster' ))
							->columns( array( 'ProjectId'))
                            ->where( "ProjectName='$projectName' and SubscriberId=$subscriberId and DeleteFlag=0");
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
	

	public function templateAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Template Master");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql( $dbAdapter );

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		$select = $sql->select();
        $select->from( 'CB_TemplateMaster' )
            ->columns( array( 'TemplateId','TemplateName' ))
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId")
            ->order('TemplateId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->templateReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
	
	public function deletetemplateAction(){
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
                    $TemplateId = $this->params()->fromPost('TemplateId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('CB_TemplateMaster')
                        ->columns(array('TemplateName'))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $template = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if(!$template) {
                        $response->setStatusCode(201)->setContent('Failed');
                        return $response;
                    }

                    $update = $sql->update();
                    $update->table('CB_TemplateMaster')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Excel-Template-Master-Delete',$TemplateId,$template['TemplateName'],$dbAdapter);
                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
	
	public function addtemplateAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {                   
                    $templateName = $this->params()->fromPost('TemplateName');
                    $Description = $this->params()->fromPost('Description');
                    $CellName = $this->params()->fromPost('CellName');
                    $SelectedColumns = $this->params()->fromPost('SelectedColumns');

                    $connection->beginTransaction();

					$insert = $sql->insert();
                    $insert->into('CB_TemplateMaster');
                    $insert->Values(array('TemplateName' => $templateName,'SubscriberId'=>$subscriberId,'Description'=>$Description
                                    ,'CellName'=> $CellName, 'SelectedColumns' => $SelectedColumns));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$templateId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$select = $sql->select();
					$select->from( 'CB_TemplateMaster' )
                            ->columns( array( 'TemplateId', 'TemplateName' ))
                            ->where( "TemplateId='$templateId'" );
					$statement = $sql->getSqlStringForSqlObject($select);
					$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    CommonHelper::insertCBLog('Excel-Template-Master-Add',$templateId,$templateName,$dbAdapter);
                    $connection->commit();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }
                $response = $this->getResponse();
                $response->setContent($status);
                return $response;
            }
        }
    }
	
	public function updatetemplateAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();

                $subscriberId = $this->auth->getIdentity()->SubscriberId;
                try {
                    $TemplateId = $this->params()->fromPost('TemplateId');
                    $TemplateName = $this->params()->fromPost('TemplateName');
                    $Description = $this->params()->fromPost('Description');
                    $CellName = $this->params()->fromPost('CellName');
                    $SelectedColumns = $this->params()->fromPost('SelectedColumns');

                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('CB_TemplateMaster')
                        ->set(array('TemplateName' => $TemplateName,'Description'=> $Description, 'CellName'=> $CellName
                              , 'SelectedColumns' => $SelectedColumns))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    
                    CommonHelper::insertCBLog('Excel-Template-Master-Edit',$TemplateId,$TemplateName,$dbAdapter);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( 'CB_TemplateMaster' )
                        ->columns( array( 'TemplateId', 'TemplateName' ))
                        ->where( "TemplateId='$TemplateId' AND SubscriberId='$subscriberId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($results)
                        return $this->getResponse()->setContent(json_encode($results));

                    return $this->getResponse()->setStatus(201)->setContent('Not Found');

                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }
            }
        }
    }
	
	public function checktemplateFoundAction(){
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
					$templateId = $this->params()->fromPost('TemplateId');

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($templateId != null){
						$templateName = $this->params()->fromPost('templateName');	
						$select->from( array( 'c' => 'CB_TemplateMaster' ))
							->columns( array( 'TemplateId'))
                            ->where( "TemplateName='$templateName' and TemplateId<> '$templateId' and SubscriberId=$subscriberId and DeleteFlag=0");
					} else{
                        $templateName = $this->params()->fromPost('templateNamenew');
						
						$select->from( array( 'c' => 'CB_TemplateMaster' ))
							->columns( array( 'TemplateId'))
                            ->where( "TemplateName='$templateName' and SubscriberId=$subscriberId and DeleteFlag=0");
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


    public function getexceltemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

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

                $TemplateId = $this->bsf->isNullCheck($this->params()->fromPost('TemplateId'), 'number');
                $select = $sql->select();
                $select->from('CB_TemplateMaster')
                    ->columns( array('Description','CellName', 'SelectedColumns'))
                    ->where( "TemplateId='$TemplateId' AND SubscriberId='$subscriberId'" );
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($results)
                    return $this->getResponse()->setContent(json_encode($results));

                return $this->getResponse()->setStatus(201)->setContent('Not Found');
            }
        }
    }
    
    public function nonagreementitemAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Non Agreement Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

		$subscriberId = $this->auth->getIdentity()->SubscriberId;

		$select = $sql->select();
        $select->from( array('a' => 'CB_NonAgtItemMaster' ))
            ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array('WONo', 'ProjectId'), $select::JOIN_LEFT)
            ->join(array('e' => 'CB_ProjectMaster'), 'e.ProjectId = b.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
            ->join( array('c' => 'Proj_UOM'), 'c.UnitId=a.UnitId', array('UnitName'), $select::JOIN_LEFT)
            ->join( array('d' => 'CB_WorkGroupMaster'), 'd.WorkGroupId=a.WorkGroupId', array('WorkGroupId', 'WorkGroupName'), $select::JOIN_LEFT)
            ->columns( array( 'NonBOQId', 'SlNo', 'Specification', 'WORegisterId', 'Rate', 'UnitId'))
            ->where("a.DeleteFlag='0' and b.SubscriberId=$subscriberId")
            ->order('a.NonBOQId');
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->nonAgtItemReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        // project list
        $select = $sql->select();
        $select->from( 'CB_ProjectMaster')
            ->columns( array( 'data' => 'ProjectId', 'value' => 'ProjectName') )
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $arrProjects = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        $this->_view->projectReg = json_encode($arrProjects);
        
        // unit list
		$unitSelect = $sql->select();
		$unitSelect->from('Proj_UOM')
				->columns(array("data"=>"UnitId", "value"=>"UnitName"));
		$unitStmt = $sql->getSqlStringForSqlObject($unitSelect);
		$units = $dbAdapter->query($unitStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->units = $units;
        
        $select = $sql->select();
        $select->from( 'CB_WorkGroupMaster' )
            ->columns( array( "data" => 'WorkGroupId', "value" => 'WorkGroupName' ))
            ->where("DeleteFlag='0' and SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->workgroups = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}
    
    public function addnonagtitemAction(){
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

        $subscriberId = $this->auth->getIdentity()->SubscriberId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                $sql = new Sql($dbAdapter);
                $response = $this->getResponse();
                try {
                    $projectId = $this->bsf->isNullCheck( $this->params()->fromPost('projectId'), 'number' );
                    $woNo = $this->bsf->isNullCheck( $this->params()->fromPost('woNo'), 'number' );
                    $slNo = $this->bsf->isNullCheck( $this->params()->fromPost('slNo'), 'number');
                    $specification = $this->bsf->isNullCheck( $this->params()->fromPost('specification'), 'string');
                    $unitId = $this->bsf->isNullCheck( $this->params()->fromPost('unitId'), 'number');
                    $rate = $this->bsf->isNullCheck( $this->params()->fromPost('rate'), 'number');
                    
                    $connection->beginTransaction();
                    $insert = $sql->insert();
                    $insert->into('CB_NonAgtItemMaster');
                    $insert->Values(array('SlNo' => $slNo,'Specification' =>$specification, 'WORegisterId' => $woNo, 'UnitId' => $unitId
                                    , 'Rate' => $rate, 'WorkGroupId' => '0', 'SubscriberId' => $subscriberId ));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$nonBOQId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    CommonHelper::insertCBLog('NonAgreementItem-Master-Add',$nonBOQId,$slNo,$dbAdapter);
                    $connection->commit();
					
					$select = $sql->select();
                    $select->from( array('a' => 'CB_NonAgtItemMaster' ))
                        ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array('WONo', 'ProjectId'), $select::JOIN_LEFT)
                        ->join(array('e' => 'CB_ProjectMaster'), 'e.ProjectId = b.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                        ->join( array('c' => 'Proj_UOM'), 'c.UnitId=a.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns( array( 'NonBOQId', 'SlNo', 'Specification', 'WORegisterId', 'Rate', 'UnitId'))
                        ->where( "NonBOQId='$nonBOQId' AND b.SubscriberId='$subscriberId'" );
					$statement = $sql->getSqlStringForSqlObject($select);
					$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(201);
                    $status = "Failed!";
                }
                $response->setContent($status);
                return $response;
            }
        }
    }
    
    public function checknonagtitemfoundAction(){
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
					$NonBOQId = $this->params()->fromPost('NonBOQId');
					$woNo = $this->params()->fromPost('woNo');
					$slNo = $this->params()->fromPost('slNo');
					$specification = $this->params()->fromPost('specification');

                    $subscriberId = $this->auth->getIdentity()->SubscriberId;
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					if($NonBOQId != null){	
						$select->from( array('a' => 'CB_NonAgtItemMaster'))
                            ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
							->columns( array( 'NonBOQId'))
                            ->where( "SlNo='$slNo' and NonBOQId<>'$NonBOQId' and a.WORegisterId='$woNo' and b.SubscriberId=$subscriberId and a.DeleteFlag=0");
					} else{
						$select->from( array('a' => 'CB_NonAgtItemMaster'))
                            ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
							->columns( array('NonBOQId'))
                            ->where( "SlNo='$slNo' and a.WORegisterId='$woNo' and b.SubscriberId=$subscriberId and a.DeleteFlag=0");
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
    
    public function editnonagtitemAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                $sql = new Sql($dbAdapter);

                try {
                    $subscriberId = $this->auth->getIdentity()->SubscriberId;

                    $NonBOQId = $this->bsf->isNullCheck($this->params()->fromPost('NonBOQId'), 'number' );
                    $projectId = $this->bsf->isNullCheck( $this->params()->fromPost('projectId'), 'number' );
                    $woNo = $this->bsf->isNullCheck( $this->params()->fromPost('woNo'), 'number' );
                    $slNo = $this->bsf->isNullCheck( $this->params()->fromPost('slNo'), 'number');
                    $specification = $this->bsf->isNullCheck( $this->params()->fromPost('specification'), 'string');
                    $unitId = $this->bsf->isNullCheck( $this->params()->fromPost('unitId'), 'number');
                    $rate = $this->bsf->isNullCheck( $this->params()->fromPost('rate'), 'number');

                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('CB_NonAgtItemMaster')
                        ->set(array('SlNo' => $slNo,'Specification' =>$specification, 'WORegisterId' => $woNo, 'UnitId' => $unitId, 'Rate' => $rate, 'WorkGroupId' => '0' ))
                        ->where(array('NonBOQId' => $NonBOQId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('NonAgtItem-Master-Edit',$NonBOQId,$slNo,$dbAdapter);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array('a' => 'CB_NonAgtItemMaster' ))
                        ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array('WONo', 'ProjectId'), $select::JOIN_LEFT)
                        ->join(array('e' => 'CB_ProjectMaster'), 'e.ProjectId = b.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                        ->join( array('c' => 'Proj_UOM'), 'c.UnitId=a.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns( array( 'NonBOQId', 'SlNo', 'Specification', 'WORegisterId', 'Rate', 'UnitId'))
                        ->where( "NonBOQId='$NonBOQId' AND a.SubscriberId='$subscriberId'" );
					$statement = $sql->getSqlStringForSqlObject($select);
					$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $this->getResponse()->setStatusCode(201)->setContent('Failed!');
                }

               return $this->getResponse()->setContent($status);
            }
        }
    }
    
    public function deletenonagtitemAction(){
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
                    $NonBOQId = $this->params()->fromPost('NonBOQId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    // check for already exists
                    $select1 = $sql->select();
                    $select1->from('CB_BillBOQ')
                        ->columns(array('NonBOQId'))
                        ->where(array('NonBOQId' => $NonBOQId));

                    $statement = $sql->getSqlStringForSqlObject( $select1 );
                    $item = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    if(count($item) > 0) {
                        $response->setStatusCode(201)->setContent($status);
                    } else {
                        $connection->beginTransaction();
                        $select = $sql->select();
                        $select->from('CB_NonAgtItemMaster')
                            ->columns(array('SlNo'))
                            ->where(array('NonBOQId' => $NonBOQId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $row = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        $slNo = $row->SlNo;

                        $update = $sql->update();
                        $update->table('CB_NonAgtItemMaster')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                            ->where(array('NonBOQId' => $NonBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('NonAgtItem-Master-Delete',$NonBOQId,$slNo,$dbAdapter);

                        $connection->commit();

                        $status = 'deleted';

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
}