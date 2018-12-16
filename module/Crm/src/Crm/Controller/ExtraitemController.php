<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Crm\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;
use Zend\Session\Container;

use Application\View\Helper\CommonHelper;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class ExtraitemController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function requestAction(){
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
		$userId = $this->auth->getIdentity()->UserId;
		$this->_view->arrVNo = CommonHelper::getVoucherNo(804, date('Y-m-d'), 0, 0, $dbAdapter, "");
		
		$select = $sql->select();
		$select->from(array("a" => "KF_UnitMaster"))
			->join(array("b" => "Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.deleteFlag=0"), array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array("ProjectId"), $select::JOIN_INNER)
			->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
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
					 $arrVNo = CommonHelper::getVoucherNo(804, date('m-d-Y',strtotime($postData['request_date'])), 0, 0, $dbAdapter, "I");
					 if($arrVNo['genType']== true){
						$extraRequestNo = $arrVNo['voucherNo'];
					} else {
						$extraRequestNo = $postData['ExtraRequestNo'];
					}
					
					 $requestDate = date('Y-m-d', strtotime($postData['request_date']));
					 $createdDate = date('Y-m-d');
					 $unitId = $postData['unitId'];
					 $extraItemRequestReg = array(
									'ExtraRequestNo' => $extraRequestNo,
									'RequestDate' => $requestDate,
									'UnitId' => $this->bsf->isNullCheck($unitId, 'number'),
									'CreatedDate' => $createdDate
								);

					 $insert = $sql->insert('Crm_ExtraItemRequestRegister');
					$insert->values($extraItemRequestReg);
					$stmt = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
					$extraItemRequestRegId = $dbAdapter->getDriver()->getLastGeneratedValue();
					//Print_r($extraItemRequestRegId); die;

                    for ($j = 1; $j <= intval($postData['itemRowId']); $j++) {
                        $include = $this->bsf->isNullCheck($postData['include_' . $j], 'number');
                        $qty = $this->bsf->isNullCheck($postData['qty_' . $j], 'number');
                        $qtdVal = $this->bsf->isNullCheck($postData['quotedVal_' . $j], 'number');
                        $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $j], 'number');

						if($include !=0 ) {
							$extraItemRequestTrans = array(
									'ExtraItemRequestRegId' => $extraItemRequestRegId,
									'ExtraItemId' => $extraItemId,
                                    'Quantity' => $qty,
                                    'QuotedValue' => $qtdVal
								);

							$insert = $sql->insert('Crm_ExtraItemRequestTrans');
							$insert->values($extraItemRequestTrans);
						    $stmt = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}

					$connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Request-Add','N','Extra-Bill-Request',$extraItemRequestRegId,0, 0, 'CRM', $extraRequestNo,$userId, 0 ,0);
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId)) {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'request-register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'request-register'));
                    }
//                    $this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'request-register'));

				} catch(PDOException $e){
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

	// AJAX Request
    public function projectextraitemAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        /* if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        } */

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    $ProjId = $this->bsf->isNullCheck($this->params()->fromPost('projId'), 'number' );
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }
//
//					$subQuery = $sql->select();
//					$subQuery->from(array("a" => "Crm_ExtraItemRequestTrans"))
//							->join(array("b" => "Crm_ExtraItemRequestRegister"), "a.ExtraItemRequestRegId=b.ExtraItemRequestRegId", array(), $subQuery::JOIN_INNER)
//							->columns(array('ExtraItemId'))
//							 ->where(array('b.UnitId'=>$UnitId));

                    // extra item list
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_ExtraItemMaster"))
                      // ->join(array("b" => "KF_UnitMaster"), "a.ProjectId=b.ProjectId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "a.MUnitId=c.UnitId", array('UnitName'), $select::JOIN_INNER)
                        ->columns(array('ExtraItemId','ItemDescription','Rate','Qty','Amount','isAlloted'=> new expression("CONVERT(bit, 0)")))
                        ->where(array('a.ProjectId'=>$ProjId,'a.DeleteFlag'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('extra_item_list' => $arrExtraItemList));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }

	public function masterAction(){
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
		$projectId = $this->params()->fromRoute('projectId');
		$userId = $this->auth->getIdentity()->UserId;
		if(isset($projectId)) {
			$where =" where projectId =".$projectId;
			
			$select = $sql->select();
			$select->from('Proj_ProjectMaster')
				->columns(array('ProjectId','ProjectName'))
				->where(array("ProjectId"=>$projectId));

			 $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->projectId = $projectId;
			
		}
		
		$select = $sql->select();
		$select->from(array("a" => "Proj_ProjectMaster"))
			//->join(array("c" => "Proj_ProjectMaster"), "d.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('data' => new Expression("projectId"), 'value' => new Expression("ProjectName")))
        ->order('ProjectId desc');
		 $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->projList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
		$select->from(array("a" => "Proj_UOM"))
			->columns(array('data' => 'UnitId', 'value' => new Expression("UnitName")));
        $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->itemUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
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

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();
                    foreach($postData as $key => $data) {
                        if(preg_match('/^desc_[\d]+$/', $key)) {

                            preg_match_all('/^desc_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $description = $this->bsf->isNullCheck($postData['desc_' . $id], 'string');
                            if($description == "") {
                                continue;
                            }

                            $extraItemTrans = array(
                                'ItemDescription' => $description,
                                'ProjectId' => $postData['projectId'],
								'MUnitId' => $this->bsf->isNullCheck($postData['code_' . $id], 'number'),
                                'Rate' => $this->bsf->isNullCheck($postData['rate_' . $id], 'number'),
                                'Qty' => 1,
								'Amount' => $this->bsf->isNullCheck($postData['rate_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_ExtraItemMaster');
                            $insert->values($extraItemTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
							$extraItemId = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$projectId = $postData['projectId'];
                        }
                    }
					
					$connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Item-Master-Add','N','Extra-Bill-Item-Master',$extraItemId,$projectId, 0, 'CRM', '',$userId, 0 ,0);
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) &&  $FeedId != "") {
                        if(isset($projectId)){
                            $this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register","projectId"=>$projectId), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {
                            $this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        }
                    } else {
                        if(isset($projectId)){
                            $this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register","projectId"=>$projectId));
                        } else {
                            $this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register"));
                        }
                    }
//

//                    if(isset($projectId)){
//						$this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register","projectId"=>$projectId));
//					} else {
//						$this->redirect()->toRoute("crm/register", array("controller" => "extraitem","action" => "register"));
//					}
				}

				
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function registerAction(){
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
		
		$projectId = $this->params()->fromRoute('projectId');
		if(isset($projectId)){

			$where =" where projectId =".$projectId;
			
			$select = $sql->select();
			$select->from('Proj_ProjectMaster')
				->columns(array('ProjectId','ProjectName'))
				->where(array("ProjectId"=>$projectId));
			$statement = $sql->getSqlStringForSqlObject($select); 
			
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->projectId = $projectId;
			
		}
        
		$select = $sql->select();
		$select->from(array('a'=>'Crm_LeadProjects'))
			->join(array('b' => 'Crm_Leads'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
			->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
			->columns(array('ProjectId','ProjectName'=>new expression("c.ProjectName")))
			->where(array('a.DeleteFlag' => 0))
			->group(new expression("a.ProjectId,c.ProjectName"));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		
		$select = $sql->select();
		$select->from(array("a" => "Crm_ExtraItemMaster"))
				->join(array("b" => "Proj_UOM"), "a.MUnitId=b.UnitId", array('UnitId','UnitName'), $select::JOIN_INNER)
				->where(array('a.DeleteFlag' => 0));
		if(isset($projectId) && $projectId!=""){
			$select->where(array('a.ProjectId' => $projectId));
		}
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->ExtraItemReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$unitSelect = $sql->select();
		$unitSelect->from('Proj_UOM')
				->columns(array("data"=>"UnitId", "value"=>"UnitName"));
		$unitStmt = $sql->getSqlStringForSqlObject($unitSelect);
		$units = $dbAdapter->query($unitStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->units = $units;
		
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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function editExtraitemAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
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
                    $extraItemId = $this->params()->fromPost('ExtraItemId');
                    $extraItemName = $this->params()->fromPost('ExtraItemName');
					$unitId = $this->params()->fromPost('unitId');
					$rate = $this->params()->fromPost('rate');
					
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Crm_ExtraItemMaster')
                        ->set(array('ItemDescription' => $extraItemName, 'MUnitId' => $unitId,'Rate' => $rate,'Amount' => $rate))
                        ->where(array('ExtraItemId' => $extraItemId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Item-Master-Modify','E','Extra-Bill-Item-Master',$extraItemId,0, 0, 'CRM', '',$userId, 0 ,0);


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
	public function deleteExtraitemAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $extraItemId =  $this->bsf->isNullCheck($this->params()->fromPost('ExtraItemId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select = $sql->select();
                            $select->from('Crm_UnitExtraItemTrans')
                                ->columns(array('ExtraItemId'))
                                ->where(array('ExtraItemId' => $extraItemId));

                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $extraItem = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($extraItem) > 0) {
                                $response->setStatusCode( 201 )->setContent( $status );
                                return $response;
                            }
                            $response->setStatusCode('200')->setContent('Not used');
                            return $response;

                            break;
                        case 'update':

                            $connection->beginTransaction();
                            $update = $sql->update();
                            $update->table( 'Crm_ExtraItemMaster' )
                                ->set( array( 'DeleteFlag' => '1', 'DeletedOn' => date( 'Y/m/d H:i:s' ), 'DeleteRemarks' => $Remarks ) )
                                ->where( array( 'ExtraItemId' => $extraItemId ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                            $connection->commit();
                            CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Item-Master-Delete','D','Extra-Bill-Item-Master',$extraItemId,0, 0, 'CRM', '',$userId, 0 ,0);


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
	public function checkExtraitemFoundAction(){
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
					$extraItemId = $this->params()->fromPost('ExtraItemId');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
					$select = $sql->select();
					$extraItemName = $this->params()->fromPost('ExtraItemName');	
					$select->from( array( 'c' => 'Crm_ExtraItemMaster' ))
						->columns( array( 'ExtraItemId'))
						->where( "ItemDescription='$extraItemName' and ExtraItemId<> '$extraItemId' and DeleteFlag=0");

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

	public function requestRegisterAction(){
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
		
		$projectId = $this->params()->fromRoute('projectId');
		if(isset($projectId)){

			$where =" where projectId =".$projectId;
			
			$select = $sql->select();
			$select->from('Proj_ProjectMaster')
				->columns(array('ProjectId','ProjectName'))
				->where(array("ProjectId"=>$projectId));
			$statement = $sql->getSqlStringForSqlObject($select); 
			
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->projectId = $projectId;
			
		}
        
		$select = $sql->select();
		$select->from(array('a'=>'Crm_LeadProjects'))
			->join(array('b' => 'Crm_Leads'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
			->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
			->columns(array('ProjectId','ProjectName'=>new expression("c.ProjectName")))
			->where(array('a.DeleteFlag' => 0))
			->group(new expression("a.ProjectId,c.ProjectName"));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$select = $sql->select();
		$select->from(array("a"=>"Crm_ExtraItemRequestRegister"));
		$select->columns(array(new Expression("a.ExtraItemRequestRegId,a.ExtraRequestNo,a.UnitId,Convert(varchar(10),a.RequestDate,105) as RequestDate")))
					->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array("UnitNo"), $select::JOIN_LEFT)
					->where(array('a.DeleteFlag'=>0))
					->order("a.CreatedDate Desc");
		if(isset($projectId) && $projectId!=""){
			$select->where(array('b.ProjectId' => $projectId));
		}
		$statement = $sql->getSqlStringForSqlObject($select);
		$gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->gridResult = $gridResult; 

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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function requestEditAction(){
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
        /*if ($request->isPost()
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
        }*/

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		
		$this->_view->arrVNo = CommonHelper::getVoucherNo(804, date('Y-m-d'), 0, 0, $dbAdapter, "");
		$userId = $this->auth->getIdentity()->UserId;
		$extraItemRegId = $this->bsf->isNullCheck($this->params()->fromRoute('regId'), 'number');
		$iQualCount = 0;
		$sql = new Sql($dbAdapter);

		$select = $sql->select();
		$select ->from(array("a" => "Crm_ExtraItemRequestRegister"))
				->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array("UnitNo"), $select::JOIN_LEFT)
				->where(array('a.ExtraItemRequestRegId' => $extraItemRegId ));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->regValue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$select = $sql->select();
		$select ->from(array("a" => "Crm_ExtraItemRequestTrans"))
				->where(array('a.ExtraItemRequestRegId' => $extraItemRegId ));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->transValue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();

                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
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
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
				$postData = $request->getPost();
                try {

                    
                    if($extraItemRegId <= 0) {
                        throw new \Exception('Invalid Extra Bill Register!');
                    }
					$extraRequestNo = $postData['ExtraRequestNo'];
					$requestDate = $postData['request_date'];
					
					$extraItemRequestReg = array(
							'ExtraRequestNo' => $this->bsf->isNullCheck($extraRequestNo, 'string'),
							'RequestDate' => date('Y-m-d', strtotime($requestDate))
						);

                    $update = $sql->update();
                    $update->table('Crm_ExtraItemRequestRegister')
                        ->set($extraItemRequestReg)
                        ->where(array('ExtraItemRequestRegId' => $extraItemRegId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    // delete trans
                    $delete = $sql->delete();
                    $delete->from('Crm_ExtraItemRequestTrans')
                        ->where(array('ExtraItemRequestRegId' => $extraItemRegId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    for ($j = 1; $j <= intval($postData['itemRowId']); $j++) {
                        $include = $this->bsf->isNullCheck($postData['include_' . $j], 'number');
                        $qty = $this->bsf->isNullCheck($postData['qty_' . $j], 'number');
                        $qtdVal = $this->bsf->isNullCheck($postData['quotedVal_' . $j], 'number');
                        $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $j], 'number');

                        if($include !=0 ) {
                            $extraItemRequestTrans = array(
                                'ExtraItemRequestRegId' => $extraItemRegId,
                                'ExtraItemId' => $extraItemId,
                                'Quantity' => $qty,
                                'QuotedValue' => $qtdVal
                            );

                            $insert = $sql->insert('Crm_ExtraItemRequestTrans');
                            $insert->values($extraItemRequestTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Request-Modify','E','Extra-Bill-Request',$extraItemRegId,0, 0, 'CRM', '',$userId, 0 ,0);
					$FeedId = $this->params()->fromQuery('FeedId');
					$AskId = $this->params()->fromQuery('AskId');
					if(isset($FeedId) && $FeedId!="") {
						$this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'request-register'),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
					} else {
						$this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'request-register'));
					}
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
	public function projectextraitemeditAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        /* if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        } */

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
					$RegId = $this->bsf->isNullCheck($this->params()->fromPost('RegId'), 'number' );
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }
					
					// $subQuery = $sql->select();
					// $subQuery->from(array("a" => "Crm_ExtraItemRequestTrans"))
							// ->columns(array('ExtraItemId'));

                    // // extra item list
					
                    // $select1 = $sql->select();
                    // $select1->from(array("a" => "Crm_ExtraItemMaster"))
                        // ->join(array("b" => "KF_UnitMaster"), "a.ProjectId=b.ProjectId", array(), $select1::JOIN_INNER)
                        // ->join(array("c" => "Proj_UOM"), "a.MUnitId=c.UnitId", array('UnitName'), $select1::JOIN_INNER)
                        // ->columns(array('ExtraItemId' => 'ExtraItemId', 'ItemDescription' => 'ItemDescription','Rate' => 'Rate','isAlloted'=> new expression("CONVERT(bit, 0)")))
                        // ->where(array('b.UnitId'=>$UnitId,'a.DeleteFlag'=>0))
						// ->where->expression('a.ExtraItemId  IN ?', array($subQuery));
						
					$select2 = $sql->select();
                    $select2->from(array("a" => "Crm_ExtraItemRequestTrans"))
                        ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array(), $select2::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "b.MUnitId=c.UnitId", array('UnitName'), $select2::JOIN_INNER)
                        ->columns(array('ExtraItemId', 'Qty'=>new Expression("a.Quantity"),'Amount'=>new Expression("a.QuotedValue"),'ItemDescription' => new expression("b.ItemDescription"),'Rate' => new expression("b.Rate"),'isAlloted'=> new expression("CONVERT(bit, 1)")))
                        ->where("a.ExtraItemRequestRegId = $RegId and b.DeleteFlag = 0");
						
					//$select2->combine($select1,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select2);
                    $arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('extra_item_list' => $arrExtraItemList));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }
	public function deleteExtraItemRequestAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $ExtraItemRequestRegId = $this->params()->fromPost('ExtraItemRequestRegId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

					
					
					
                    $update = $sql->update();
                    $update->table('Crm_ExtraItemRequestRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('ExtraItemRequestRegId' => $ExtraItemRequestRegId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$delete = $sql->delete();
					$delete->from('Crm_ExtraItemRequestTrans')
							->where(array('ExtraItemRequestRegId' => $ExtraItemRequestRegId));
					$stmt = $sql->getSqlStringForSqlObject($delete);
					$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-Request-Delete','D','Extra-Bill-Request',$ExtraItemRequestRegId,0, 0, 'CRM', '',$userId, 0 ,0);

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
	public function checkExtraItemUsedAction(){
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

                $postParams = $request->getPost();
                $ExtraItemRequestRegId= $postParams['ExtraItemRequestRegId'];
                $UnitId= $postParams['UnitId'];

				$subQuery = $sql->select();
                $subQuery->from(array('a' => 'Crm_ExtraItemRequestTrans'))
						->columns(array('ExtraItemId'))
						->where(array("a.ExtraItemRequestRegId"=>$ExtraItemRequestRegId));
				
                $select = $sql->select();
                $select->from(array('a' => 'Crm_ExtraBillTrans'))
                    ->join(array('b' => 'Crm_ExtraBillRegister'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId',array(), $select::JOIN_INNER)
                    ->columns(array('ExtraItemId'))
                    ->where(array("b.UnitId"=>$UnitId))
					->where->expression('a.ExtraItemId Not IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);

                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (!empty($results)) $ans ='Y';

                $response = $this->getResponse();
                $response->setContent($ans);
                return $response;
            }
        }
    }

	public function doneEntryAction(){
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
		$userId = $this->auth->getIdentity()->UserId;
		$this->_view->arrVNo = CommonHelper::getVoucherNo(818, date('Y-m-d'), 0, 0, $dbAdapter, "");
		
		$select = $sql->select();
		$select->from(array("a" => "KF_UnitMaster"))
			->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		/* $select = $sql->select();
					$select->from(array("a" => "Proj_QualifierTrans"))
						->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId'), $select::JOIN_INNER)
						->columns(array('QualifierId','YesNo','RefId' => new Expression("'R'+ rtrim(ltrim(str(RefId)))"),'Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
							'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
							'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
					$select->where(array('a.QualType' => 'C'));
					$statement = $sql->getSqlStringForSqlObject($select);
					$qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$sHtml=Qualifier::getQualifier($qualList);
					$iQualCount = $iQualCount+1;
					$sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
					$qualHtml = $sHtml; */
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

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			//try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();
					
					$arrTransVNo = CommonHelper::getVoucherNo(818, date('m-d-Y', strtotime($postData['done_date'])), 0, 0, $dbAdapter, "I");
					if($arrTransVNo['genType']== true){
						$extraItemDoneNo = $arrTransVNo['voucherNo'];
					} else {
						$extraItemDoneNo = $postData['ExtraItemDoneNo'];
					}
                    $insert = $sql->insert('Crm_ExtraItemDoneRegister');
                    $insertData = array(
                        'ExtraItemDoneNo'  => $extraItemDoneNo,
                        'ExtratItemDoneDate' => date('m-d-Y', strtotime($postData['done_date'])),
                        'UnitId' => $postData['unitId']
                    );
                    $insert->values($insertData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $extraItemDoneRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    foreach($postData as $key => $data) {
                        if(preg_match('/^extraItemId_[\d]+$/', $key)) {

                            preg_match_all('/^extraItemId_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $id], 'number');
                            if($extraItemId <= 0) {
                                continue;
                            }

                            $extraItemDoneTrans = array(
                                'ExtraItemDoneRegId' => $extraItemDoneRegId,
                                'ExtraItemId' => $extraItemId,
                                'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number'),
								'Quantity' => $this->bsf->isNullCheck($postData['transQuantity_' . $id], 'number'),
								'Rate' => $this->bsf->isNullCheck($postData['transRate_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_ExtraItemDoneTrans');
                            $insert->values($extraItemDoneTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select1 = $sql->select();
                            $select1->from(array("a" => "Crm_ExtraItemRequestTrans"))
                                ->join(array("b" => "Crm_ExtraItemRequestRegister"), "a.ExtraItemRequestRegId=b.ExtraItemRequestRegId", array(), $select::JOIN_INNER)
                                ->columns(array('ExtraItemRequestRegId'))
                                ->where(array('UnitId'=>$postData['unitId'],'ExtraItemId'=>$postData['extraItemId_' . $id]));

                            $update = $sql->update();
                            $update->table('Crm_ExtraItemRequestRegister');
                            $update->set(array(
                                'DoneId' =>1
                            ));
                            $update->where(array('ExtraItemRequestRegId'=>array($select1)));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
						//$this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
                    }
					/*
					//Qualifier 
					$j=1;
					$qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$j],'number');
					for ($k = 1; $k <= $qRowCount; $k++) {
						$iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Id_' . $k], 'number');
						$iYesNo = isset($postData['Qual_' . $j . '_YesNo_' . $k]) ? 1 : 0;
						$sExpression = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Exp_' . $k], 'string');
						$dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
						$dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
						$iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $j . '_TypeId_' . $k], 'number');
						$sSign = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Sign_' . $k], 'string');

						if ($iQualTypeId==1 ||$iQualTypeId==2) {
							$dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
							$dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
							$dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessPer_' . $k], 'number');
							$dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessPer_' . $k], 'number');
							$dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessPer_' . $k], 'number');
							$dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

							$dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
							$dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
							$dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessAmt_' . $k], 'number');
							$dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessAmt_' . $k], 'number');
							$dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessAmt_' . $k], 'number');
							$dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
						} else {
							$dTaxablePer = 100;
							$dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
							$dCessPer = 0;
							$dEDPer = 0;
							$dHEdPer = 0;
							$dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
							$dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
							$dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
							$dCessAmt = 0;
							$dEDAmt = 0;
							$dHEdAmt = 0;
							$dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
						}
				
						$insert = $sql->insert();
						$insert->into('Crm_ExtraBillQualifierTrans');
						$insert->Values(array('extraBillRegId' => $extraBillRegId,
						'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
						'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
						'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'NetAmt'=>$dNetAmt));

						$statement = $sql->getSqlStringForSqlObject($insert); 
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

					} */
					$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-WorkDone-Add','N','Extra-Bill-WorkDone',$extraItemDoneRegId,0, 0, 'CRM', $extraItemDoneNo,$userId, 0 ,0);

				}

				

            /*} catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }*/

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function extraItemListAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
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
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $servicereg = $this->params()->fromRoute('ServiceDoneRegId');
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here
                     
                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    $regId = $this->bsf->isNullCheck($this->params()->fromPost('regId'), 'number' );
					
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }

                    $subQuery = $sql->select();
                    $subQuery->from(array("a" => "Crm_ExtraItemDoneTrans"))
                        ->join(array("b" => "Crm_ExtraItemDoneRegister"), "a.ExtraItemDoneRegId=b.ExtraItemDoneRegId", array(), $subQuery::JOIN_INNER)
                        ->columns(array('ExtraItemId'))
                        ->where(array('UnitId'=>$UnitId));
					
					if($regId==0 && $this->params()->fromPost('mode')=='bill'){

                        $select = $sql->select();
                    $select->from(array("a" => "Crm_ExtraItemDoneTrans"))
                        ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('data' => 'ExtraItemId', 'value' => 'ItemDescription'), $select::JOIN_INNER)
						->join(array("c" => "Crm_ExtraItemDoneRegister"), "a.ExtraItemDoneRegId=c.ExtraItemDoneRegId", array('donebill'=>'ExtraItemDoneRegId'), $select::JOIN_INNER)
                        ->columns(array('Amount'=>new expression("a.Amount"),'Quantity'=>new expression("a.Quantity"),'Rate'=>new expression("a.Rate")),array('data' => 'ExtraItemId', 'value' => 'ItemDescription'))
                        ->where(array('c.UnitId'=>$UnitId,'c.BillDone'=>0));
					 $statement = $sql->getSqlStringForSqlObject($select);
                    $arrExtraItemRequestList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


//////
//					$subQuery = $sql->select();
//					$subQuery->from(array("a" => "Crm_ExtraBillTrans"))
//							->join(array("b" => "Crm_ExtraBillRegister"), "a.ExtraBillRegisterId=b.ExtraBillRegisterId", array(), $subQuery::JOIN_INNER)
//							->columns(array('ExtraItemId'))
//							 ->where("b.UnitId = $UnitId" );
//                    // extra item list
//                    $select = $sql->select();
//                    $select->from(array("a" => "Crm_ExtraItemDoneTrans"))
//                        ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('data' => 'ExtraItemId', 'value' => 'ItemDescription'), $select::JOIN_INNER)
//						->join(array("c" => "Crm_ExtraItemDoneRegister"), "a.ExtraItemDoneRegId=c.ExtraItemDoneRegId", array(), $select::JOIN_INNER)
//                        ->columns(array('Amount'=>new expression("a.Amount"),'Quantity'=>new expression("a.Quantity"),'Rate'=>new expression("a.Rate")),array('data' => 'ExtraItemId', 'value' => 'ItemDescription'))
//                        ->where(array('c.UnitId'=>$UnitId))
//						->where->expression('a.ExtraItemId Not IN ?', array($subQuery));
//				    $statement = $sql->getSqlStringForSqlObject($select);
//                    $arrExtraItemRequestList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					}

					else if($regId==0 && $this->params()->fromPost('mode')=='entry'){
                        $select = $sql->select();
                        $select->from(array("a" => "crm_ExtraItemRequestTrans"))
                            ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "Crm_ExtraItemRequestRegister"), "a.ExtraItemRequestRegId=c.ExtraItemRequestRegId", array(), $select::JOIN_INNER)
                           ->columns(array('data'=>new Expression("Distinct(a.ExtraItemId)"),'value'=>new expression("b.ItemDescription"),'Amount'=>new expression("a.QuotedValue"),'Quantity'=>new expression("a.Quantity"),'Rate'=>new expression("b.Rate")))
                            ->where(array('c.UnitId'=>$UnitId))
                            ->where(array('c.DoneId'=>0));
                       // ->where->expression('a.ExtraItemId NOT IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrExtraItemRequestList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					}
                    else {// subQuery


                        // extra item list
                        $select = $sql->select();
                        $select->from(array("a" => "crm_ExtraItemRequestTrans"))
                            ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('data' => 'ExtraItemId', 'value' => 'ItemDescription'), $select::JOIN_INNER)
                            ->join(array("c" => "Crm_ExtraItemRequestRegister"), "a.ExtraItemRequestRegId=c.ExtraItemRequestRegId", array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new expression("a.QuotedValue"),'Quantity'=>new expression("a.Quantity"),'Rate'=>new expression("b.Rate")),array('data' => 'ExtraItemId', 'value' => 'ItemDescription'))
                            ->where(array('c.UnitId'=>$UnitId))
                            ->where(array('c.DoneId'=>0));
                           // ->where->expression('a.ExtraItemId NOT IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrExtraItemRequestList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
					
                    $result =  json_encode(array('extraitem_list' => $arrExtraItemRequestList));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
					
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }
	public function doneEditAction(){
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
		$extraItemDoneRegId = $this->params()->fromRoute('extraItemDoneRegId');
		$sql = new Sql($dbAdapter);
		
		$this->_view->arrVNo = CommonHelper::getVoucherNo(818, date('Y-m-d'), 0, 0, $dbAdapter, "");
		$userId = $this->auth->getIdentity()->UserId;
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		$select = $sql->select();
		$select->from(array("f" => "Crm_ExtraItemDoneRegister"))
			->join(array("a" => "KF_UnitMaster"), "a.UnitId=f.UnitId", array(), $select::JOIN_INNER)
			->join(array("b" => "Crm_UnitBooking"), "f.UnitId=b.UnitId", array(), $select::JOIN_INNER)
			->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('ExtraItemDoneNo','ExtratItemDoneDate','ExtraItemDoneRegId','data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
			->where(array('f.ExtraItemDoneRegId'=>$extraItemDoneRegId)); 
	    $statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
	
		$select = $sql->select();
		$select->from(array("a" => "Crm_ExtraItemDoneTrans"))
			->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array(), $select::JOIN_INNER)
			->columns(array('ExtraItemId'=>'ExtraItemId','ItemDescription' => new Expression("b.ItemDescription"),'Amount'=>'Amount','Rate'=>'Rate','Quantity'=>'Quantity'))
			->where(array('a.ExtraItemDoneRegId'=>$extraItemDoneRegId)); 
	    $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $result="";
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent($result);
					return $response;
			}
		} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			//try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
					//Print_r($postData);die;
					$arrTransVNo = CommonHelper::getVoucherNo(818, date('m-d-Y', strtotime($postData['extraitem_date'])), 0, 0, $dbAdapter, "I");
					if($arrTransVNo['genType']== true){
						$extraItemDoneNo = $arrTransVNo['voucherNo'];
					} else {
						$extraItemDoneNo = $postData['extraitemNo'];
					}
					
				  $delete = $sql->delete();
                  $delete->from('Crm_ExtraItemDoneTrans')
							->where(array('ExtraItemDoneRegId' => $extraItemDoneRegId));
				  $DelStatement = $sql->getSqlStringForSqlObject($delete);
				  $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Crm_ExtraItemDoneRegister');
                    $update->set(array(
					   'ExtraItemDoneNo'=>$extraItemDoneNo,
					   'ExtratItemDoneDate'=>date('m-d-y',strtotime($postData['extraitem_date'])),
                    ));
                    $update->where(array('ExtraItemDoneRegId'=>$extraItemDoneRegId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach($postData as $key => $data) {
                        if(preg_match('/^extraItemId_[\d]+$/', $key)) {

                            preg_match_all('/^extraItemId_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $id], 'number');
                            if($extraItemId <= 0) {
                                continue;
                            }

                            $extraItemDoneTrans = array(
                                'ExtraItemDoneRegId' => $extraItemDoneRegId,
                                'ExtraItemId' => $extraItemId,
                                'Rate' => $this->bsf->isNullCheck($postData['transRate_' . $id], 'number'),
                                'Quantity' => $this->bsf->isNullCheck($postData['transQuantity_' . $id], 'number'),
                                'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_ExtraItemDoneTrans');
                            $insert->values($extraItemDoneTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert); 
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
					$connection->commit();
				//Print_r($extraItemDoneNo);die;
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-WorkDone-Modify','E','Extra-Bill-WorkDone',$extraItemDoneRegId,0, 0, 'CRM', $extraItemDoneNo,$userId, 0 ,0);

            //	$this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'done-register'));

				}
				

				
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	
	public function doneRegisterAction(){
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

        //total Payment//
        $select = $sql->select();
        $select->from(array('a' =>'Crm_ExtraItemDoneRegister'))
            ->columns(array('ExtraItemDoneRegisterId' => new expression('count(*)')))
            ->where(array('DeleteFlag'=>'0'));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->paymentreg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a" => "Crm_ExtraItemDoneRegister"))
            ->columns(array(new Expression("a.ExtraItemDoneRegId,a.ExtraItemDoneNo,FORMAT(a.ExtratItemDoneDate, 'dd-MM-yyyy') ExtraItemDoneDate,COUNT(b.ExtraItemId) as Totalservice,sum(b.Amount) as TotalAmount")))
            ->join(array('b' => 'Crm_ExtraItemDoneTrans'), 'a.ExtraItemDoneRegId=b.ExtraItemDoneRegId', array(), $select::JOIN_LEFT)
            ->join(array('c' => 'KF_UnitMaster'), 'a.UnitId=c.UnitId', array('UnitNo'), $select::JOIN_LEFT)
            ->join(array("d" => "Proj_ProjectMaster"), "c.ProjectId=d.ProjectId", array('ProjectName'), $select::JOIN_INNER)
            ->where(array('a.DeleteFlag' => 0))
            ->group(new expression('a.ExtraItemDoneRegId,a.ExtratItemDoneDate,a.UnitId,c.UnitNo,d.ProjectName,a.ExtraItemDoneNo'))
			->order('a.ExtraItemDoneRegId desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->extraItemReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        return $this->_view;
	}
    public function extraitemdoneDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('ExtraItemDoneRegId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('Crm_ExtraItemDoneRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('ExtraItemDoneRegId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Extra-Bill-WorkDone-Delete','D','Extra-Bill-WorkDone',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);


                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    public function checkExtraItemDoneAction(){
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

                $postParams = $request->getPost();
                $ExtraItemDoneRegId= $postParams['ExtraItemDoneRegId'];
                $UnitId= $postParams['UnitId'];

                $subQuery = $sql->select();
                $subQuery->from(array('a' => 'Crm_ExtraItemDoneRegister'))
                    ->join(array('b'=>'Crm_ExtraItemDoneTrans'),'a.ExtraItemDoneRegId = b.ExtraItemDoneRegId',array('ExtraItemId'),$subQuery::JOIN_INNER)
                    ->columns(array())
                    ->where(array("a.ExtraItemDoneRegId"=>$ExtraItemDoneRegId));
//                echo $statement2 = $sql->getSqlStringForSqlObject($subQuery);

                $select = $sql->select();
                $select->from(array('a' => 'Crm_ExtraBillTrans'))
                    ->join(array('b' => 'Crm_ExtraBillRegister'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId',array(), $select::JOIN_INNER)
                    ->columns(array('ExtraItemId'))
                    ->where(array("b.UnitId"=>$UnitId))
                    ->where->expression('a.ExtraItemId IN ?', array($subQuery));
                 $statement = $sql->getSqlStringForSqlObject($select);

                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ='N';
                if (!empty($results)) $ans ='Y';

                $response = $this->getResponse();
                $response->setContent($ans);
                return $response;
            }
        }
    }

	public function extraItemQuotationAction(){
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
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();

                    $rfqRowNum = $this->bsf->isNullCheck($postData['item'], 'number');
                    $rfqId = $this->bsf->isNullCheck($postData['rfq_Id_'.$rfqRowNum], 'number');

                    for($j=1;$j<=intval($postData['item_row_id_'.$rfqRowNum]);$j++) {
                        $extraItemId= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_extraItemId_'.$j],'number');
                        $transId= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_transId_'.$j],'number');
                        $unitName= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_unit_'.$j],'string');
                        $feasibility= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_feasability_'.$j],'number');
                        $rate= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_rate_'.$j],'number');
                        $qty= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_qty_'.$j],'number');
                        $qValue= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_qValue_'.$j],'number');
                        $mUnitId= $this->bsf->isNullCheck($postData['rfq_'.$rfqRowNum.'_mUnitId_'.$j],'number');

                        $update = $sql->update();
                        $update->table('BP_RFQTrans')
                            ->set(array('Unit'=>$unitName,'MunitId'=>$mUnitId,'Rate'=>$rate,'Qty'=>$qty,'QuotedValue'=>$qValue,'Feasibility'=>$feasibility))
                            ->where(array('TransId' => $transId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $update = $sql->update();
                    $update->table('BP_RFQRegister')
                        ->set(array('Status'=>'quote'))
                        ->where(array('RFQId' => $rfqId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('WF_AppNotification');
                    $update->set(array(
                        'DeleteFlag'  => 1,
                    ));
                    $update->where(array('ExtraItemRFQId'=>$rfqId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $this->redirect()->toRoute('crm/default', array('controller' => 'extraitem', 'action' => 'extra-item-quotation'));

                } catch(PDOException $e){
                    $connection->rollback();
                }
                //begin trans try block example ends


            } else {
                $select = $sql->select();
                $select->from(array("a" => "BP_RFQRegister"))
                    ->columns(array('RFQDate'=>new Expression("CONVERT(varchar(10),a.RFQDate,105)"),'RFQId','Status'))
                    ->join(array('b' => 'Crm_UnitBooking'), new Expression("a.UnitId = b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Crm_Leads'), 'b.LeadId = c.LeadId', array('LeadName','LeadId'), $select::JOIN_INNER)
                    ->join(array('d' => 'KF_UnitMaster'), 'b.UnitId = d.UnitId', array('UnitNo','UnitId'), $select::JOIN_INNER)
                    ->join(array('e' => 'Proj_ProjectMaster'), 'd.ProjectId = e.ProjectId', array('ProjectId','ProjectName'), $select::JOIN_INNER)
                    ->where(array("a.Status" => "wait","a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rfqDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $subQuery = $sql->select()
                    ->from("BP_RFQRegister")
                    ->columns(array('RFQId'))
                    ->where(array("Status" => "wait","DeleteFlag"=>0));

                $select = $sql->select();
                $select->from(array('a'=>'BP_RFQTrans'))
                    ->columns(array('*'))
                    ->where->expression('a.RFQId IN ?', array($subQuery));
                $select->order(new Expression("a.RFQId, a.TransId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->itemDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a" => "Proj_UOM"))
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("UnitName")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->itemUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            }

			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

}