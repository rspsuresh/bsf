<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace warehouse\Controller;

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

class IndexController extends AbstractActionController
{
	public function __construct()	{
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


	public function warehouseCreateAction(){
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
        $warehouseid = $this->bsf->isNullCheck($this->params()->fromRoute('warehouseid'), 'number');

		$response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
			$postParams = $request->getPost();
				//Write your Ajax post code here
				if($postParams['mode'] == 'cityCheck'){
					$select = $sql->select();		
					$select->from(array('a' => 'WF_CityMaster'))
						->join(array('b'=>'WF_StateMaster'), 'a.StateId=b.StateId', array('StateId', 'StateName'), $select:: JOIN_INNER)
						->join(array('c' => 'WF_CountryMaster'), 'a.CountryId=c.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_INNER)
						->columns(array('CityId', 'CityName'),array('StateId', 'StateName'),array('CountryId', 'CountryName'))
						->where(array('a.CityId'=>$postParams['cid']));
								
					$statement = $sql->getSqlStringForSqlObject($select);
					$resp['data'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();						
				}
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		} else if($request->isPost()){
			$postParams = $request->getPost();
			//Insert
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try{
                if(isset($warehouseid) && $warehouseid!='') {
                    $updatewh = $sql -> update();
                    $updatewh->table('MMS_WareHouse');
                    $updatewh->set(array(
                        'WareHouseNo'=>$postParams['warehouseNo'],
                        'WareHouseName' => $postParams['warehouseName'],
                        'Address' => $postParams['commAddress'],
                        'CityId' => $postParams['city'],
                        'PinCode' => $postParams['pincode'],
                        'Manageby' => $postParams['manageby']
                    ))
                    ->where(array("WareHouseId"=>$warehouseid));
                    $statement = $sql->getSqlStringForSqlObject($updatewh);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    $this->redirect()->toRoute('warehouse/warehouse-register', array('controller' => 'index', 'action' => 'warehouseRegister'));
                }
                else {
                    $insert = $sql->insert('MMS_WareHouse');
                    $newData = array(
                        'WareHouseNo' => $postParams['warehouseNo'],
                        'WareHouseName' => $postParams['warehouseName'],
                        'Address' => $postParams['commAddress'],
                        'CityId' => $postParams['city'],
                        'PinCode' => $postParams['pincode'],
                        'Manageby' => $postParams['manageby']
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $wareHouseId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();
                    $this->redirect()->toRoute('warehouse/warehouse-plan', array('controller' => 'index', 'action' => 'warehouse-plan', 'warehouseid' => $wareHouseId));
                }
			}
			catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";				
			}					
		}
        else
        {
            if(isset($warehouseid) && $warehouseid!='') {
                $this->_view->whid = $warehouseid;
                $select = $sql->select();
                $select->from(array('a' => 'MMS_WareHouse'))
                    ->columns(array(new Expression('a.WareHouseNo,a.WareHouseName,a.Address,a.CityId,c.StateName,d.CountryName,a.Pincode As Pincode,a.ManageBy As Manageby')))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array(),$select::JOIN_LEFT)
                    ->join(array("c" => "WF_StateMaster"),"b.StateId=c.StateId",array(),$select::JOIN_LEFT)
                    ->join(array("d" => "WF_CountryMaster"),"c.CountryId=d.CountryId",array(),$select::JOIN_LEFT)
                    ->where("a.WareHouseId=$warehouseid");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->whreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            }
        }
		
		$citySelect = $sql->select();		
		$citySelect->from('WF_CityMaster')
			->columns(array('CityId', 'CityName'));
		$cityStatement = $sql->getSqlStringForSqlObject($citySelect);
		$cityResult = $dbAdapter->query($cityStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->cityResult = $cityResult;
		return $this->_view;
	}

	public function warehousePlanAction(){
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
		
		$warehouseId= $this->params()->fromRoute('warehouseid');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParam = $request->getPost();
			
				if($postParam['mode'] == 'UpdatewarehouseDet'){				
					$rowSelect = $sql->select();		
					$rowSelect->from('MMS_WareHouseDetails')
						->columns(array('TransId','Id','WareHouseId'))
						->where(array("warehouseId"=>$postParam['warehouseId'],"Id"=>$postParam['icurRowId'] ));
					$rowStatement = $sql->getSqlStringForSqlObject($rowSelect);
					$rowResult = $dbAdapter->query($rowStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($rowResult)==0){
						$warehouseDetInsert = $sql->insert('MMS_WareHouseDetails');
						$warehouseDetInsert->values(array("WareHouseId"=>$postParam['warehouseId'], "Id"=>$postParam['icurRowId'], 
								"Description"=>$postParam['icurName'], "ParentId"=>$postParam['icurParentId'], 
								"TypeId"=>$postParam['icurTypeId'], "Length"=>0, "Breadth"=>0,
								"Height"=>0, "Capacity"=>0));									
						$warehouseDetStatement = $sql->getSqlStringForSqlObject($warehouseDetInsert);
						$dbAdapter->query($warehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					} else {
						$warehouseDetUpdate = $sql->update("MMS_WareHouseDetails");
						$warehouseDetUpdate->set(array("Description"=>$postParam['icurName'], "ParentId"=>$postParam['icurParentId'], 
								"TypeId"=>$postParam['icurTypeId'], "Length"=>0, "Breadth"=>0,
								"Height"=>0, "Capacity"=>0))
									->where(array("warehouseId"=>$postParam['warehouseId'],"Id"=>$rowResult['Id']));
						$statement = $sql->getSqlStringForSqlObject($warehouseDetUpdate);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}					
				}
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParam = $request->getPost();

				//Write your Normal form post code here
				$json = json_decode($postParam['warehouseJson'], true);
				//begin trans try block example starts
				$warehouseId = $postParam['warehouseId'];
				
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try {
					
					$select = $sql->delete();
					$select->from("MMS_WareHouseDetails")
								->where(array('WareHouseId'=>$warehouseId));
					$delwarehouseDetStatement = $sql->getSqlStringForSqlObject($select);
					$register2 = $dbAdapter->query($delwarehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					foreach($json as $ware){
						$warehouseDetInsert = $sql->insert('MMS_WareHouseDetails');
						$warehouseDetInsert->values(array("WareHouseId"=>$warehouseId, "Id" => $ware['Id'], "Description"=>$ware['Name'], 
						"ParentId"=>$ware['ParentID'], "TypeId"=>$ware['TypeId'], "Length"=>$ware['Length'], "Breadth"=>$ware['Breadth'],
						"Height"=>$ware['Height'], "Capacity"=>$ware['Capacity']));									
						$warehouseDetStatement = $sql->getSqlStringForSqlObject($warehouseDetInsert);
                        $dbAdapter->query($warehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //$TransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    $whSelect = $sql->select();
                    $whSelect->from('MMS_WareHouseDetails')
                        ->columns(array('TransId','Id','WareHouseId'))
                        ->where(array("WareHouseId"=>$warehouseId));
                    $whSelectStatement = $sql->getSqlStringForSqlObject($whSelect);
                    $warehouseResult = $dbAdapter->query($whSelectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $arr1 = array();
                    foreach($warehouseResult as $whResults) {
                        $arr1['TransId']=$whResults['TransId'];
                        $arr1['Id']=$whResults['Id'];
                        $arr1['WareHouseId']=$whResults['WareHouseId'];


                        $whSelect = $sql->select();
                        $whSelect->from('MMS_WareHouseDetails')
                            ->columns(array("*"))
                            ->where(array("ParentId"=>$arr1['Id'],"WareHouseId"=>$arr1['WareHouseId']));
                        $whSelStatement = $sql->getSqlStringForSqlObject($whSelect);
                        $whResult = $dbAdapter->query($whSelStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($whResult) == 0){
                            $update = $sql->update();
                            $update->table('MMS_WareHouseDetails')
                                ->set(array('LastLevel' => 1))
                                ->where(array('TransId' => $arr1['TransId'],'WareHouseId'=>$warehouseId,));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
				} catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";
				}
				//begin trans try block example ends
			}
		}

		$warehouseSelect = $sql->select();		
		$warehouseSelect->from('MMS_WareHouse')
			->columns(array('WareHouseId', 'Name'=>'WareHouseName', 'Length', 'Breadth', 'Height'=>'Depth', 'Capacity'=>'Weight'))
			->where(array("WareHouseId"=>$warehouseId));
		 $warehouseStatement = $sql->getSqlStringForSqlObject($warehouseSelect);
		$warehouseResult = $dbAdapter->query($warehouseStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($warehouseResult)==0){
			$this->redirect()->toRoute('warehouse/default', array('controller' => 'index','action' => 'warehouse-create'));
		} else {
			$resp = array_merge($warehouseResult[0], array("Id"=>1,  "TypeId"=>0, "Type"=>"", "ParentID"=>0));
		}
		$this->_view->warehouseResult = $resp;
		$this->_view->warehouseId = $warehouseId;
		return $this->_view;
	}
	
	public function warehousePlaneditAction(){
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
		
		$warehouseId= $this->params()->fromRoute('warehouseid');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			$resp = array();
			if ($request->isPost()) {
				$postParam = $request->getPost();
			
				if($postParam['mode'] == 'DeletewarehouseDet'){				
					/*$rowSelect = $sql->select();		
					$rowSelect->from('MMS_WareHouseDetails')
						->columns(array('Id'))
						->where(array("warehouseId"=>$postParam['warehouseId'],"ParentId"=>$postParam['icurRowId'] ));
					$rowStatement = $sql->getSqlStringForSqlObject($rowSelect);
					$rowResult = $dbAdapter->query($rowStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($rowResult)!=0){
						$warehouseDetInsert = $sql->insert('MMS_WareHouseDetails');
						$warehouseDetInsert->values(array("WareHouseId"=>$postParam['warehouseId'], "Id"=>$postParam['icurRowId'], 
								"Description"=>$postParam['icurName'], "ParentId"=>$postParam['icurParentId'], 
								"TypeId"=>$postParam['icurTypeId'], "Length"=>0, "Breadth"=>0,
								"Height"=>0, "Capacity"=>0));									
						$warehouseDetStatement = $sql->getSqlStringForSqlObject($warehouseDetInsert);
						$dbAdapter->query($warehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}*/
					$finalRowId = CommonHelper::GetWareHouseParentId($postParam['warehouseId'],$postParam['icurRowId'],$dbAdapter);
					$finalRowIdDel = substr($finalRowId, 0, -1);
					
					if($finalRowIdDel!="")
					{
						$select = $sql->delete();
						$select->from('MMS_WareHouseDetails')
									->where(array('warehouseId'=>$postParam['warehouseId'],'Id'=>explode(',', $finalRowIdDel)));
						$DelStatement = $sql->getSqlStringForSqlObject($select);	
						$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					array_push($resp, "success");				
				}
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($resp));
				return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
			$postParam = $request->getPost();
			//print_r($postParam);die;
				//Write your Normal form post code here
				$json = json_decode($postParam['warehouseJson'], true);
				//begin trans try block example starts
				$warehouseId = $postParam['warehouseId'];
				
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try {
					foreach($json as $ware){
						/*$warehouseDetInsert = $sql->insert('MMS_WareHouseDetails');
						$warehouseDetInsert->values(array("WareHouseId"=>$warehouseId, "Id" => $ware['Id'], "Description"=>$ware['Name'], 
						"ParentId"=>$ware['ParentID'], "TypeId"=>$ware['TypeId'], "Length"=>$ware['Length'], "Breadth"=>$ware['Breadth'],
						"Height"=>$ware['Height'], "Capacity"=>$ware['Capacity']));									
						$warehouseDetStatement = $sql->getSqlStringForSqlObject($warehouseDetInsert);
						$dbAdapter->query($warehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);*/
						
						$rowSelect = $sql->select();		
						$rowSelect->from('MMS_WareHouseDetails')
							->columns(array('TransId','Id','WareHouseId'))
							->where(array("warehouseId"=>$warehouseId,"Id"=>$ware['Id'] ));
						$rowStatement = $sql->getSqlStringForSqlObject($rowSelect);
						$rowResult = $dbAdapter->query($rowStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($rowResult)==0){
							$warehouseDetInsert = $sql->insert('MMS_WareHouseDetails');
							$warehouseDetInsert->values(array("WareHouseId"=>$warehouseId, "Id"=>$ware['Id'], 
									"Description"=>$ware['Name'], "ParentId"=>$ware['ParentID'], "TypeId"=>$ware['TypeId'], "Length"=>$ware['Length'], "Breadth"=>$ware['Breadth'],
									"Height"=>$ware['Height'], "Capacity"=>$ware['Capacity']));									
							$warehouseDetStatement = $sql->getSqlStringForSqlObject($warehouseDetInsert);
							$dbAdapter->query($warehouseDetStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						} else {
							$warehouseDetUpdate = $sql->update("MMS_WareHouseDetails");
							$warehouseDetUpdate->set(array("Description"=>$ware['Name'], "ParentId"=>$ware['ParentID'],
									"TypeId"=>$ware['TypeId'], "Length"=>$ware['Length'], "Breadth"=>$ware['Breadth'],
									"Height"=>$ware['Height'], "Capacity"=>$ware['Capacity']))
										->where(array("warehouseId"=>$warehouseId,"Id"=>$ware['Id']));
							$statement = $sql->getSqlStringForSqlObject($warehouseDetUpdate);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
                    //Lastlevel update
                    $whUpdate = $sql->update("MMS_WareHouseDetails");
                    $whUpdate->set(array("LastLevel" => 0 ))
                             ->where(array("warehouseId"=>$warehouseId));
                    $whupdatestatement = $sql->getSqlStringForSqlObject($whUpdate);
                    $dbAdapter->query($whupdatestatement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $whSelect = $sql->select();
                    $whSelect->from('MMS_WareHouseDetails')
                        ->columns(array('TransId','Id','WareHouseId'))
                        ->where(array("WareHouseId"=>$warehouseId));
                    $whSelectStatement = $sql->getSqlStringForSqlObject($whSelect);
                    $warehouseResult = $dbAdapter->query($whSelectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $arr1 = array();
                    foreach($warehouseResult as $whResults) {
                        $arr1['TransId']=$whResults['TransId'];
                        $arr1['Id']=$whResults['Id'];
                        $arr1['WareHouseId']=$whResults['WareHouseId'];


                        $whSelect = $sql->select();
                        $whSelect->from('MMS_WareHouseDetails')
                            ->columns(array("*"))
                            ->where(array("ParentId"=>$arr1['Id'],"WareHouseId"=>$arr1['WareHouseId']));
                        $whSelStatement = $sql->getSqlStringForSqlObject($whSelect);
                        $whResult = $dbAdapter->query($whSelStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($whResult) == 0){
                            $update = $sql->update();
                            $update->table('MMS_WareHouseDetails')
                                ->set(array('LastLevel' => 1))
                                ->where(array('TransId' => $arr1['TransId'],'WareHouseId'=>$warehouseId,));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

					$connection->commit();
				} catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";
				}
				//begin trans try block example ends
			}
		}

		$warehouseSelect = $sql->select();		
		$warehouseSelect->from('MMS_WareHouseDetails')
			->columns(array('Id', 'Name'=>'Description','TypeId','ParentID',
			'Type'=> new Expression("CASE WHEN TypeId=1 THEN 'Section' WHEN TypeId=2 THEN 'Rack' WHEN TypeId=3 THEN 
			'Bulk' WHEN TypeId=4 THEN 'Open' WHEN TypeId=5 THEN 'Bin' Else '' END"), 'Length', 'Breadth', 'Height', 'Capacity'))
			->where(array("WareHouseId"=>$warehouseId));
		$warehouseStatement = $sql->getSqlStringForSqlObject($warehouseSelect);
		$resp = $dbAdapter->query($warehouseStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resp)==0){
			$warehouseSelect = $sql->select();		
			$warehouseSelect->from('MMS_WareHouse')
				->columns(array('WareHouseId', 'Name'=>'WareHouseName', 'Length', 'Breadth', 'Height'=>'Depth', 'Capacity'=>'Weight'))
				->where(array("WareHouseId"=>$warehouseId));
			$warehouseStatement = $sql->getSqlStringForSqlObject($warehouseSelect);
			$warehouseResult = $dbAdapter->query($warehouseStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($warehouseResult)==0){
				$this->redirect()->toRoute('warehouse/default', array('controller' => 'index','action' => 'warehouse-create'));
			} else {
				$resp = array_merge($warehouseResult[0], array("Id"=>1,  "TypeId"=>0, "Type"=>"", "ParentID"=>0));
			}
		} 
		$this->_view->warehouseResult = $resp;
		$this->_view->warehouseId = $warehouseId;
		return $this->_view;
	}
	
	public function testAction(){
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
		
		$warehouseId= $this->params()->fromRoute('warehouseid');
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
				
		}

		
		return $this->_view;
	}
	
	public function warehouseManagementAction(){}

	public function warehousestructureAction(){}

	public function warehouseRegisterAction(){
        if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest()){
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
                    $regSelect = $sql->select();
                    $regSelect -> from(array("a" => "MMS_WareHouse" ))
                            ->columns(array(new Expression("a.WareHouseId,a.WareHouseNo,a.WareHouseName,a.Address + ' ' + b.CityName + ' ' + c.StateName + ' ' + d.CountryName + ' ' + a.PinCode As Address ")))
                            ->join(array("b" => "WF_CityMaster"),"a.CityId=b.CityId",array(),$regSelect::JOIN_LEFT)
                            ->join(array("c" => "WF_StateMaster"),"b.StateId=c.StateId",array(),$regSelect::JOIN_LEFT )
                            ->join(array("d" => "WF_CountryMaster"),"c.CountryId=d.CountryId",array(),$regSelect::JOIN_LEFT);
                    $regSelect -> order(new Expression("a.WareHouseId DESC"));
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if($request->isPost()){

        }
        return $this->_view;
	}

	public function materialstorageAction(){
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
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $this->_view->setTerminal(true);
                $postData = $request->getPost();
                $result =  "";
                $ResourceId = $this->bsf->isNullCheck($postData['ResourceId'], 'number');
                $ItemId = $this->bsf->isNullCheck($postData['ItemId'], 'number');
                $stype = $this->bsf->isNullCheck($postData['stype'], 'string');

                $del = $sql->delete();
                $del->from('mms_materialstoragetype')
                    ->where(array("ResourceId" => $ResourceId, "ItemId" => $ItemId));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $typeid=0;
                if($postData['stype'] == 'Section'){$typeid=1;} else if($postData['stype'] == "Rack") {$typeid=2; }
                else if($postData['stype'] == 'Bulk') {$typeid=3;} else if($postData['stype'] == 'Open') {$typeid=4;}
                else if ($postData['stype'] == 'Bin') { $typeid=5;} else {$typeid=0;}

                if($typeid > 0){

                    $insert = $sql->insert('mms_materialstoragetype');
                    $newData = array(
                        'ResourceId' =>$ResourceId,
                        'ItemId' =>$ItemId,
                        'TypeId'=> $typeid
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $response = $this->getResponse()->setContent($result);
                return $response;
			}
            else
            {

            }
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {

				//Write your Normal form post code here
				
			}
            else
            {
                $selM = $sql -> select();
                $selM->from(array("a" => "Proj_Resource"))
                    ->columns(array(new Expression("a.ResourceId,ISNULL(b.BrandId,0) As ItemId,Case When ISNULL(b.BrandId,0) > 0 Then b.ItemCode Else a.Code End As Code,
                              Case When ISNULL(b.BrandId,0) > 0 Then b.BrandName Else a.ResourceName End As Resource,
                              Case When ISNULL(b.BrandId,0) > 0 Then d.UnitName Else c.UnitName End As Unit,
                              Case When e.TypeId=0 Then 'None' When e.TypeId=1 Then 'Section' When e.TypeId=2 Then 'Rack'
                              When e.TypeId=3 Then 'Bulk' When e.TypeId=4 Then 'Open' When e.TypeId=5 Then 'Bin' Else 'None' End As StorageType   ")))
                    ->join(array("b" => "MMS_Brand"),"a.ResourceId=b.ResourceId",array(),$selM::JOIN_LEFT)
                    ->join(array("c" => "Proj_UOM"),"a.UnitId=c.UnitId",array(),$selM::JOIN_LEFT)
                    ->join(array("d" => "Proj_UOM"),"b.UnitId=d.UnitId",array(),$selM::JOIN_LEFT)
                    ->join(array("e" => "MMS_MaterialStorageType"),new expression("a.ResourceId=e.ResourceId and isnull(b.BrandId,0)=e.ItemId"),array(),$selM::JOIN_LEFT)
                    ->where("a.TypeId=2");
                $statement = $sql->getSqlStringForSqlObject($selM);
                $this->_view->arr_matstorage = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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