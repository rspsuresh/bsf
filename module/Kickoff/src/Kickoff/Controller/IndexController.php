<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Kickoff\Controller;

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
        $this->bsf = new \BuildsuperfastClass();
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

    public function indexAction()
	{
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
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // project Types
        $select = $sql->select();
        $select->from('Proj_ProjectTypeMaster');
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->arrProjectTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                $projectId = $postParams['projectId'];
                $this->redirect()->toRoute('kickoff/newproject', array('controller' => 'index', 'action' => 'newproject', 'projectId' => $projectId));
            }
        }
        //Common function
        $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    // AJAX Request
    public function addProjectAction()
	{
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

        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $postData = $request->getPost();
                    $projectName = $this->bsf->isNullCheck($postData['ProjectName'], 'string');
                    $projectType = $this->bsf->isNullCheck($postData['ProjectType'], 'number');
                    $description = $this->bsf->isNullCheck($postData['ProjectName'], 'string');
                    if($projectName == '') {
                        throw new \Exception('Project Name is required!');
                    }
                    if($projectType == 0) {
                        throw new \Exception('Project Type is required!');
                    }
                    if($description == '') {
                        throw new \Exception('Description is required!');
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectMaster')
                        ->values(array(
                            'ProjectName' => $projectName,
                            'ProjectTypeId' => $projectType,
                            'ProjectDescription' => $description
                         ));
                    $stmt = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $projectId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    if($projectId <= 0) {
                        throw new \Exception('Project not created!');
                    }

                    $result =  json_encode(array('ProjectId' => $projectId, 'ProjectName' => $projectName));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $connection->rollback();
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

    public function projectEditAction()
	{
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

        $subQuery = $sql->select();
        $subQuery->from('KF_UnitMaster')
            ->columns(array('ProjectId'));

        $select = $sql->select();
        $select->from(array('a' => 'Proj_ProjectMaster'))
            ->where->expression('a.ProjectId IN ?', array($subQuery));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                $projectId = $postParams['projectId'];
                $this->redirect()->toRoute('kickoff/newproject-edit', array('controller' => 'index', 'action' => 'newproject-edit', 'projectId' => $projectId));
            }
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

	public function projectAction()
	{
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

	public function newProjectAction()
	{
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/newproject', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

        //Getting BusinessType
        $this->_view->unitGenType = 0;
        $select = $sql->select();
        $select->from('KF_Conception')
            ->columns(array('BusinessTypeId'))
            ->where('KickoffId = ' . $kickoffId);
        $statement = $sql->getSqlStringForSqlObject($select);
        $kfConceptionRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(isset($kfConceptionRes['BusinessTypeId']) && $kfConceptionRes['BusinessTypeId'] != '') {
            $this->_view->unitGenType = $kfConceptionRes['BusinessTypeId'];
        }
		
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

                try {
                    $postData = $request->getPost();
                    //echo '<pre>'; print_r($postData); die;
					$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
                    /*$ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number' );
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }*/

                    $select = $sql->select();
                    $select->from('KF_UnitMaster')
                        ->where(array('KickoffId' => $iKickoffId));
                    $stmt = $sql->getSqlStringForSqlObject( $select );
                    $arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if(!empty($arrUnits)) {
                        $this->redirect()->toRoute('kickoff/newproject', array('controller' => 'index', 'action' => 'newproject-edit', 'kickoffId' => $iKickoffId));
                        return;
                    }

                    $arrPhases = array();
                    $phasePattern = '/^([a-z]+)?(phase_[\d])+/';
                    foreach($postData as $key => $data) {
                        if(preg_match($phasePattern, $key) == FALSE) {
                            continue;
                        }

                        preg_match_all($phasePattern, $key, $arrMatches);
                        if(isset($arrMatches[0][0])) {
                            $phase = $arrMatches[2][0];
                        }

                        if(preg_match('/^([a-z]+)?phase_[\d]+$/', $key)) {
                            // phase
                            preg_match_all('/^([a-z]+)?(phase_[\d])+$/', $key, $arrBlockMatches);
                            $column = $arrBlockMatches[1][0];
                            if($column == '') {
                                $column = 'name';
                            }
                            $arrPhases[$phase][$column] = $data;
                        } elseif(preg_match('/^phase_[\d]+_[a-z]+block_[\d]+$/', $key)) {
                            // block

                            preg_match_all('/[\w]([a-z]+)(block_[\d]+)$/', $key, $arrBlockMatches);
                            $arrPhases[$phase]['blocks'][$arrBlockMatches[2][0]][$arrBlockMatches[1][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+_[a-z]+floor_[\d]+$/', $key)) {
                            // floor

                            preg_match_all('/^phase_[\d]+_(block_[\d]+)_([a-z]+)(floor_[\d]+)$/', $key, $arrFloorMatches);
                            $arrPhases[$phase]['blocks'][$arrFloorMatches[1][0]]['floors'][$arrFloorMatches[3][0]][$arrFloorMatches[2][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+_floor_[\d]+_[a-z]+unittype_[\d]+$/', $key)) {
                            // unit type

                            preg_match_all('/^phase_[\d]+_(block_[\d]+)_(floor_[\d]+)_([a-z]+)(unittype_[\d]+)$/', $key, $arrUtMs);
                            $arrPhases[$phase]['blocks'][$arrUtMs[1][0]]['floors'][$arrUtMs[2][0]]['unittypes'][$arrUtMs[4][0]][$arrUtMs[3][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+_floor_[\d]+_unittype_[\d]+_[a-z]+unit_[\d]+$/', $key)) {
                            // unit

                            preg_match_all('/^phase_[\d]+_(block_[\d]+)_(floor_[\d]+)_(unittype_[\d]+)_([a-z]+)(unit_[\d]+)$/', $key, $arrUMs);
                            $arrPhases[$phase]['blocks'][$arrUMs[1][0]]['floors'][$arrUMs[2][0]]['unittypes'][$arrUMs[3][0]]['units'][$arrUMs[5][0]][$arrUMs[4][0]] = $data;
                        }
                    }
					//echo '<pre>'; print_r($arrPhases); die;
                    // create phases
                    foreach($arrPhases as $phase) {
                        $now = date('Y-m-d H:i:s');

                        $insert = $sql->insert();
                        $insert->into('KF_PhaseMaster')
                            ->values(array(
                                         'PhaseName' => $phase['name'],
                                         'Title' => $phase['title'],
                                         'KickoffId' => $iKickoffId,
                                         'CreatedDate' => $now
                                     ));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $phaseId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        // create blocks
                        foreach($phase['blocks'] as $block) {

                            $insert = $sql->insert();
                            $insert->into('KF_BlockMaster')
                                ->values(array(
                                    'BlockName' => $block['name'],
                                    'Title' => $block['title'],
                                    'KickoffId' => $iKickoffId,
                                    'PhaseId' => $phaseId,
                                    'CreatedDate' => $now
                                ));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $blockId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            // create floor
                            $floorSortId = 1;
                            foreach($block['floors'] as $floor) {

                                $insert = $sql->insert();
                                $insert->into('KF_FloorMaster')
                                    ->values(array(
                                        'FloorName' => $floor['name'],
                                        'Title' => $floor['title'],
                                        'KickoffId' => $iKickoffId,
                                        'BlockId' => $blockId,
                                        'SortId' => $floorSortId,
                                        'CreatedDate' => $now
                                    ));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $floorId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                // create unit type
                                foreach($floor['unittypes'] as $key => $unittype) {

                                    $unitDetailValues = array(
                                        'Rate' => 0,
                                        'BaseAmt' => 0,
                                        'AdvPercent' => 0,
                                        'AdvAmount' => 0,
                                        'GuideLineValue' => 0,
                                        'FacingId' => 0,
                                        'CreditDays' => 0,
                                        'LandAmount' => 0,
                                        'ConstructionAmount' => 0,
                                        'GrossAmount' => 0,
                                        'MarketLandValue' => 0,
                                        'UDSLandArea' => 0,
                                        'CarpetArea' => 0,
                                        'IntPercent' => 0
										//'NetAmt' => 0 @todo do not take from unit type
                                    );
									
									$unittypeId = 0;
									if(!empty($unittype['name'])) {
										$select = $sql->select();
										$select->from('KF_UnitTypeMaster')
											->where(array('UnitTypeName' => $unittype['name'], 'TypeName' => $unittype['type'], 'Area' => $unittype['area'], 'KickoffId' => $iKickoffId));
										$stmt = $sql->getSqlStringForSqlObject($select);
										$existUnitType = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
										if(!empty($existUnitType)) {
											// unit type already exists
											$unittypeId = $existUnitType['UnitTypeId'];

											$select = $sql->select();
											$select->from('Crm_UnitType')
												->columns(array('Rate', 'BaseAmt', 'AdvPercent', 'AdvAmount', 'GuideLineValue',
														  'FacingId', 'CreditDays', 'LandAmount', 'ConstructionAmount',
														  'GrossAmount' => 'GrossAmt', 'MarketLandValue',
														  'UDSLandArea', 'CarpetArea', 'IntPercent'
															//, 'NetAmt' @todo do not take from unit type
														  ))
												->where(array('UnitTypeId' => $unittypeId));
											$stmt = $sql->getSqlStringForSqlObject($select);
											$unitDetails = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
											if(!empty($unitDetails)) {
												$unitDetailValues = $unitDetails[ 0 ];
											}
										} else {
											// new unit type
											$insert = $sql->insert();
											$insert->into('KF_UnitTypeMaster')
												->values(array(
															 'UnitTypeName' => $unittype['name'],
															 'TypeName' => $unittype['type'],
															 'Area' => $unittype['area'],
															 'Title' => $unittype['title'],
															 'KickoffId' => $iKickoffId,
															 'CreatedDate' => $now
														 ));
											$stmt = $sql->getSqlStringForSqlObject($insert);
											$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
											$unittypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

											$insert = $sql->insert();
											$insert->into(('Crm_UnitType'))
												->values(array('UnitTypeId' => $unittypeId));
											$stmt = $sql->getSqlStringForSqlObject($insert);
											$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
										}
									}

                                    // create unit
                                    foreach($unittype['units'] as $unit) {
                                        $insert = $sql->insert();
                                        $insert->into('KF_UnitMaster')
                                            ->values(array(
                                                'UnitNo' => $unit['name'],
                                                'UnitArea' => $unit['area'],
                                                'KickoffId' => $iKickoffId,
                                                'BlockId' => $blockId,
                                                'FloorId' => $floorId,
                                                'UnitTypeId' => $unittypeId,
                                                'CreatedDate' => $now
                                            ));
                                        $stmt = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $unitId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $unitDetailValues['UnitId'] = $unitId;
                                        $unitDetailValues['NetAmt'] = $unitDetailValues['GrossAmount'];

                                        $insert = $sql->insert();
                                        $insert->into('Crm_UnitDetails')
                                            ->values($unitDetailValues);
                                        $stmt = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                $floorSortId++;
                            }
                        }
                    }

                    $connection->commit();
					$this->redirect()->toRoute("kickoff/newproject", array("controller" => "index", "action" => "wbs", 'kickoffId' => $iKickoffId));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                // GET request
                try {
                    //$ProjectId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'projectId' ), 'number' );
					
                    // Projects
                    /*$select = $sql->select();
                    $select->from( 'Proj_ProjectMaster' )
                        ->where( array( 'ProjectId' => $ProjectId, 'DeleteFlag' => 0 ) );
                    $stmt = $sql->getSqlStringForSqlObject( $select );
                    $project = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
					$this->_view->project = $project;*/
					
                    $select = $sql->select();
                    $select->from('KF_UnitMaster')
                        ->where(array('KickoffId' => $kickoffId));
                    $stmt = $sql->getSqlStringForSqlObject( $select );
                    $arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if(!empty($arrUnits)) {
						$this->redirect()->toRoute('kickoff/newproject', array('controller' => 'index', 'action' => 'newproject-edit', 'kickoffId' => $kickoffId));
                        return;
                    }
					
					// Kickoff Register      
					$select = $sql->select();
					$select->from(array('a' => 'KF_KickoffRegister'))
						->where('a.KickoffId = ' . $kickoffId);
					$statement = $sql->getSqlStringForSqlObject($select);
					$this->_view->kickoffRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
                $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
	}

    public function newProjectEditAction()
	{
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
                $this->_view->setTerminal(true);
                try {
                    $units = $this->params()->fromPost('units');
                    $duplications = array();
                    foreach($units as $unit) {
                        $whereCond = "UnitNo like '".$unit['value']."' ";
                        if(isset($unit['id'])) {
                            $id = $this->bsf->isNullCheck($unit['id'], 'number');
                            $whereCond .= " AND UnitId <> " . $unit['id'];
                        }

                        $select = $sql->select();
                        $select->from('KF_UnitMaster')
                            ->columns(array('UnitId'));
                        $select->where($whereCond);
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        if($result != FALSE){
                            $duplications[] = $unit;
                        }
                    }
                    if(sizeof($duplications) > 0) {
                        $response->setStatusCode(200);
                        $response->setContent(json_encode($duplications));
                    } else {
                        $response->setStatusCode(201);
                        $response->setContent('No data.');
                    }
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            $kickoffId = $this->params()->fromRoute('kickoffId');
            if($kickoffId == '') {
                $this->redirect()->toRoute('kickoff/newproject-edit', array('controller' => 'index', 'action' => 'project-kickoff'));
            }
            $this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

            $this->_view->unitGenType = 0;
            $select = $sql->select();
            $select->from('KF_Conception')
                ->columns(array('BusinessTypeId'))
                ->where('KickoffId = ' . $kickoffId);
            $statement = $sql->getSqlStringForSqlObject($select);
            $kfConceptionRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if(isset($kfConceptionRes['BusinessTypeId']) && $kfConceptionRes['BusinessTypeId'] != '') {
                $this->_view->unitGenType = $kfConceptionRes['BusinessTypeId'];
            }

            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
				
                try {
                    $postData = $request->getPost();
					$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');

                    /*$ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number' );
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }*/

                    $arrPhases = array();
                    $phasePattern = '/phase_/';
                    foreach($postData as $key => $data) {

                        if(preg_match($phasePattern, $key) == FALSE) {
                            continue;
                        }

                        preg_match_all('/phase_([\d]+)/', $key, $arrMatches);
                        $phase = $arrMatches[1][0];

                        if(preg_match('/^[a-z]+phase_[\d]+$/', $key)) {
                            // phase
                            preg_match_all('/^([a-z]+)phase_[\d]+/', $key, $arrPhaseMatches);
                            $arrPhases[$phase][$arrPhaseMatches[1][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_[a-z]+block_[\d]+(_new)?$/', $key)) {
                            // block

                            preg_match_all('/^phase_[\d]+_([a-z]+)(block_[\d]+(_new)?)$/', $key, $arrBlockMatches);
                            $arrPhases[$phase]['blocks'][$arrBlockMatches[2][0]][$arrBlockMatches[1][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+(_new)?_[a-z]+floor_[\d]+(_new)?$/', $key)) {
                            // floor

                            preg_match_all('/^phase_[\d]+_(block_[\d]+(_new)?)_([a-z]+)(floor_[\d]+(_new)?)$/', $key, $arrFloorMatches);
                            $arrPhases[$phase]['blocks'][$arrFloorMatches[1][0]]['floors'][$arrFloorMatches[4][0]][$arrFloorMatches[3][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+(_new)?_floor_[\d]+(_new)?_[a-z]+unittype_[\d]+(_new)?$/', $key)) {
                            // unit type

                            preg_match_all('/^phase_[\d]+_(block_[\d]+(_new)?)_(floor_[\d]+(_new)?)_([a-z]+)(unittype_[\d]+(_new)?)/', $key, $arrUtMs);
                            $arrPhases[$phase]['blocks'][$arrUtMs[1][0]]['floors'][$arrUtMs[3][0]]['unittypes'][$arrUtMs[6][0]][$arrUtMs[5][0]] = $data;
                        } elseif(preg_match('/^phase_[\d]+_block_[\d]+(_new)?_floor_[\d]+(_new)?_unittype_[\d]+(_new)?_[a-z]+unit_[\d]+/', $key)) {
                            // unit

                            preg_match_all('/^phase_[\d]+_(block_[\d]+(_new)?)_(floor_[\d]+(_new)?)_(unittype_[\d]+(_new)?)_([a-z]+)(unit_[\d]+)/', $key, $arrUMs);
                            $arrPhases[$phase]['blocks'][$arrUMs[1][0]]['floors'][$arrUMs[3][0]]['unittypes'][$arrUMs[5][0]]['units'][$arrUMs[8][0]][$arrUMs[7][0]] = $data;
                        }
                    }

                    // create phases
                    foreach($arrPhases as $phase) {
                        $now = date('Y-m-d H:i:s');

                        $phaseId = $phase['id'];
                        $projectId = $phase['project'];


                        if(is_numeric($phaseId)) {
                            $update = $sql->update();
                            $update->table( 'KF_PhaseMaster' )
                                ->set( array( 'PhaseName' => $phase[ 'name' ],'ProjectId'=> $projectId ) )
                                ->where( array( 'PhaseId' => $phaseId) );
                            $stmt = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );
                        } else {
                            $insert = $sql->insert();
                            $insert->into('KF_PhaseMaster')
                                ->values(array('PhaseName' => $phase['name'], 'KickoffId' => $iKickoffId,'ProjectId'=> $projectId , 'CreatedDate' => $now));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $phaseId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // create blocks
                        foreach($phase['blocks'] as $block) {

                            $blockId = $block['id'];
                            $projId = $block['proj'];
                            //print_r($projId);

                            if(is_numeric($blockId)) {
                                $update = $sql->update();
                                $update->table( 'KF_BlockMaster' )
                                    ->set( array( 'BlockName' => $block[ 'name' ] ,'ProjectId' => $projectId) )
                                    ->where( array( 'BlockId' => $blockId) );
                                $stmt = $sql->getSqlStringForSqlObject( $update );
                                $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );
                            } else {
                                $insert = $sql->insert();
                                $insert->into('KF_BlockMaster')
                                    ->values(array(
                                                 'BlockName' => $block['name'],
                                                 'KickoffId' => $iKickoffId,
                                                 'PhaseId' => $phaseId,
                                                 'ProjectId' => $projectId,
                                                 'CreatedDate' => $now
                                             ));
                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                $blockId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            }

                            // create floor
                            $floorSortId = 1;
                            foreach($block['floors'] as $floor) {

                                $floorId = $floor['id'];

                                if(is_numeric($floorId)) {
                                    $update = $sql->update();
                                    $update->table( 'KF_FloorMaster' )
                                        ->set( array( 'FloorName' => $floor[ 'name' ] ,'ProjectId' => $projectId) )
                                        ->where( array( 'FloorId' => $floorId) );
                                    $stmt = $sql->getSqlStringForSqlObject( $update );
                                    $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('KF_FloorMaster')
                                        ->values(array(
                                                     'FloorName' => $floor['name'],
                                                     'KickoffId' => $iKickoffId,
                                                     'BlockId' => $blockId,
                                                     'ProjectId' => $projectId,
                                                     'SortId' => $floorSortId,
                                                     'CreatedDate' => $now
                                                 ));
                                    $stmt = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $floorId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }

                                // create unit type
                                foreach($floor['unittypes'] as $key => $unittype) {

                                    $unitTypeValues = array(
                                        'Rate' => 0,
                                        'BaseAmt' => 0,
                                        'AdvPercent' => 0,
                                        'AdvAmount' => 0,
                                        'GuideLineValue' => 0,
                                        'FacingId' => 0,
                                        'CreditDays' => 0,
                                        'LandAmount' => 0,
                                        'ConstructionAmount' => 0,
                                        'GrossAmount' => 0,
                                        'MarketLandValue' => 0,
                                        'UDSLandArea' => 0,
                                        'CarpetArea' => 0,
                                        'IntPercent' => 0,
										//'NetAmt' => 0 @todo do not take unit type
                                    );
                                    $unittypeId = 0;
                                    if ($key != 'unittype_0') {
                                        $unittypeId = $unittype['id'];

                                        if(is_numeric($unittypeId)) {
                                            $update = $sql->update();
                                            $update->table( 'KF_UnitTypeMaster' )
                                                ->set( array( 'UnitTypeName' => $unittype['name'], 'Area' => $unittype['area'] ) )
                                                ->where( array( 'UnitTypeId' => $unittypeId) );
                                            $stmt = $sql->getSqlStringForSqlObject( $update );
                                            $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );

                                            $select = $sql->select();
                                            $select->from('Crm_UnitType')
                                                ->columns(array('Rate', 'BaseAmt', 'AdvPercent', 'AdvAmount', 'GuideLineValue',
                                                              'FacingId', 'CreditDays', 'LandAmount', 'ConstructionAmount',
                                                              'GrossAmount' => 'GrossAmt', 'MarketLandValue',
                                                              'UDSLandArea', 'CarpetArea', 'IntPercent'
															//'NetAmt' @todo do not take from unit type
                                                          ))
                                                ->where(array('UnitTypeId' => $unittypeId));
                                            $stmt = $sql->getSqlStringForSqlObject($select);
                                            $unitTypeDetails = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                            $unitTypeValues = $unitTypeDetails[0];

                                        } else {

                                            $select = $sql->select();
                                            $select->from('KF_UnitTypeMaster')
                                                ->where(array('UnitTypeName' => $unittype['name'], 'KickoffId' => $iKickoffId));
                                            $stmt = $sql->getSqlStringForSqlObject($select);
                                            $existUnitType = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                            if(!empty($existUnitType)) {
                                                // unit type already exists
                                                $unittypeId = $existUnitType['UnitTypeId'];
                                            } else {
                                                $uttype = '';
                                                if(isset($unittype['type'])){
                                                    $uttype = $unittype['type'];
                                                }

                                                $insert = $sql->insert();
                                                $insert->into('KF_UnitTypeMaster')
                                                    ->values(array(
                                                                 'UnitTypeName' => $unittype['name'],
																 'TypeName' => $uttype,
                                                                 'Area' => $unittype['area'],
                                                                 'KickoffId' => $iKickoffId,
                                                                 'CreatedDate' => $now
                                                             ));
                                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $unittypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                $insert = $sql->insert();
                                                $insert->into(('Crm_UnitType'))
                                                    ->values(array('UnitTypeId' => $unittypeId));
                                                $stmt = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }

                                    if(!isset($unittype['units'])) {
                                        continue;
                                    }

                                    foreach($unittype['units'] as $unit) {

                                        if(is_numeric($unit['id'])) {
                                            if(isset($unit['delete'])) {
                                                $delete = $sql->delete();
                                                $delete->from('KF_UnitMaster')
                                                    ->where(array('UnitId' => $unit['id']));
                                                $stmt = $sql->getSqlStringForSqlObject($delete);
                                                $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );

                                                $delete = $sql->delete();
                                                $delete->from('Crm_UnitDetails')
                                                    ->where(array('UnitId' => $unit['id']));
                                                $stmt = $sql->getSqlStringForSqlObject($delete);
                                                $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );

                                            } else {
                                                $update = $sql->update();
                                                $update->table( 'KF_UnitMaster' )
                                                    ->set( array( 'UnitNo' => $unit['name'], 'UnitArea' => $unit['area'], 'ProjectId' => $projectId, 'UnitTypeId' => $unittypeId ) )
                                                    ->where( array( 'UnitId' => $unit[ 'id' ] ) );
                                                $stmt = $sql->getSqlStringForSqlObject( $update );
                                                $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE );
                                            }
                                        } else {
                                            // create unit
                                            $insert = $sql->insert();
                                            $insert->into('KF_UnitMaster')
                                                ->values(array(
                                                             'UnitNo' => $unit['name'],
                                                             'UnitArea' => $unit['area'],
                                                             'KickoffId' => $iKickoffId,
                                                             'BlockId' => $blockId,
                                                             'FloorId' => $floorId,
                                                              'ProjectId' => $projectId,
                                                             'UnitTypeId' => $unittypeId,
                                                             'CreatedDate' => $now
                                                         ));
                                            $stmt = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $unitId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $unitTypeValues['UnitId'] = $unitId;
                                            $unitTypeValues['NetAmt'] = $unitTypeValues['GrossAmount'];

                                            $insert = $sql->insert();
                                            $insert->into('Crm_UnitDetails')
                                                ->values($unitTypeValues);
                                            $stmt = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                                $floorSortId++;
                            }
                        }
                    }


                    $connection->commit();
					
					$this->redirect()->toRoute("kickoff/newproject-edit", array("controller" => "index", "action" => "wbs", 'kickoffId' => $iKickoffId));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request
                try {
                    /*$ProjectId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'projectId' ), 'number' );
                    if ( $ProjectId == 0 ) {
                        $this->redirect()->toRoute("kickoff/default", array("controller" => "index", "action" => "project-edit"));
                    }*/

                    // Projects
                    /*$select = $sql->select();
                    $select->from( 'Proj_ProjectMaster' )
                        ->where( array( 'ProjectId' => $ProjectId, 'DeleteFlag' => 0 ) );
                    $stmt = $sql->getSqlStringForSqlObject( $select );
                    $project = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if(empty($project)) {
                        $this->redirect()->toRoute("kickoff/default", array("controller" => "index", "action" => "project-edit"));
                    }
                    $this->_view->project = $project;*/

                    // Phases in projects
                    $select = $sql->select();
                    $select->from('KF_PhaseMaster')
                        ->where(array('KickoffId' => $kickoffId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPhases = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // UnitType in project
                    $select = $sql->select();
                    $select->from('KF_UnitTypeMaster')
                        //->columns(array('data' => 'UnitTypeId', 'value' => new Expression("CONCAT(UnitTypeName,'-',TypeName,'-',Area)"), 'Type' => 'TypeName', 'Area' => 'Area'))
						->columns(array('data' => 'UnitTypeId', 'value' => 'UnitTypeName', 'Type' => 'TypeName', 'Area' => 'Area'))
                        ->where(array('KickoffId' => $kickoffId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnitTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonUnitTypes = json_encode($arrUnitTypes);

                    foreach($arrPhases as &$phase) {
                        // Blocks
                        $select = $sql->select();
                        $select->from('KF_BlockMaster')
                            ->where(array('PhaseId' => $phase['PhaseId'], 'DeleteFlag' => 0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrBlocks = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($arrBlocks as &$block) {
                            // Floor
                            $select = $sql->select();
                            $select->from( 'KF_FloorMaster' )
                                ->where(array('BlockId' => $block['BlockId'], 'DeleteFlag' => 0));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrFloors = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($arrFloors as &$floor) {
                                // Unit
                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->join(array('b' => 'Crm_UnitBooking'), 'b.UnitId=a.UnitId', array('isBooked' => new Expression("CAST ( CASE WHEN BookingId IS NOT NULL THEN 1 ELSE 0 END AS bit)")), $select::JOIN_LEFT)
                                    ->join(array('c' => 'KF_UnitTypeMaster'), 'c.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName', 'UnitTypeType' => 'TypeName', 'UnitTypeArea' => 'Area', 'UnitTypeTitle' => 'Title'), $select::JOIN_LEFT)
                                    ->where(array('a.FloorId' => $floor['FloorId'], 'a.DeleteFlag' => 0));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

								//$arrUnitTypeIds = array();
								//foreach($arrUnits as $unit) {
								//if(in_array($unit['UnitTypeId'], $arrUnitTypeIds) == FALSE) {
								//$arrUnitTypeIds[] = $unit['UnitTypeId'];
								//}
								//}

                                $floor['units'] = $arrUnits;
                            }
                            $block['floors'] = $arrFloors;
                        }

                        $phase['blocks'] = $arrBlocks;
                    }

                    $this->_view->jsonDatas = json_encode($arrPhases);

                    /*$subQuery = $sql->select();
                    $subQuery->from('KF_UnitMaster')
                        ->columns(array('ProjectId'));

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectMaster'))
                        ->where->expression('a.ProjectId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/
					
					// Kickoff Register      
					$select = $sql->select();
					$select->from(array('a' => 'KF_KickoffRegister'))
						->where('a.KickoffId = ' . $kickoffId);
					$statement = $sql->getSqlStringForSqlObject($select);
					$this->_view->kickoffRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
	
	public function projectKickoffAction()
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
		$userId = $this->auth->getIdentity()->UserId;
		
		$iEnquiryId = $this->params()->fromRoute('enquiryId');

        $msg = $this->params()->fromRoute('msg');
        $this->_view->successMsg = '';
        if($msg != '') {
            if($msg == '1') {
                $this->_view->successMsg = 'Project has been created successfully';
            } else if($msg == '2') {
                $this->_view->successMsg = 'Project has been updated successfully';
            }
        }
		
		$select = $sql->select();
		$select->from(array('a' => 'Proj_LandEnquiry'))
				->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->propertyNames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
        $select->from('KF_KickoffRegister')
            ->columns(array('data' => 'KickoffId', 'value' => 'ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectNames = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Cost Centre
		$select = $sql->select();
		$select->from( 'WF_CostCentre' )
				->columns(array("CostCentreId", "CostCentreName"));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->costCentre = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Company Master
		$select = $sql->select();
		$select->from( 'WF_CompanyMaster' )
				->columns(array("CompanyId", "CompanyName"));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->companyMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'Proj_LandEnquiry'))
            ->columns(array('data' => 'EnquiryId', 'value' => 'PropertyName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $property= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->propertyName = $property['data'];
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			try {
				$postData = $request->getPost();
				//echo '<pre>'; print_r($postData); die;
				
				$enquiryId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');
                $projectId = $this->bsf->isNullCheck($postData['projectId'], 'number');

				$costCentreId = $this->bsf->isNullCheck($postData['costCentreId'], 'number');
                $projectName = $this->bsf->isNullCheck($postData['newProjectName'], 'string');
				$neConception = $this->bsf->isNullCheck($postData['conceptionid'], 'number');
				$projectKfId = $this->bsf->isNullCheck($postData['projectKfId'], 'number');
                if  ($projectKfId ==0 && $projectId !=0) $projectKfId= $projectId;

				$refDate = NULL;
				
				if ($projectKfId != 0) {
					$this->redirect()->toRoute('kickoff/conception-detail', array('controller' => 'index', 'action' => 'conception-detail', 'kickoffId' => $projectKfId, 'conceptionId' => 0));
				} else {
					if ($enquiryId != 0 || $projectName != "") {
						$iKickId = 0;
                        $connection = $dbAdapter->getDriver()->getConnection();
                        $connection->beginTransaction();

                        $insert = $sql->insert();
                        $insert->into('KF_KickoffRegister');
                        $insert->Values(array('EnquiryId' => $enquiryId
                            , 'RefNo' => $this->bsf->isNullCheck('','string')
                            , 'RefDate' => $refDate
                            , 'ProjectName' => $projectName
                            , 'CostCentreId' => $costCentreId));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $kickoffId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if($enquiryId != 0) {
                            $update = $sql->update();
                            $update->table('Proj_LandEnquiry');
                            $update->set(array('KickoffDone' => $this->bsf->isNullCheck(1,'number')));
                            $update->where(array('EnquiryId' => $enquiryId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        if($postData['newCC'] == '1') {
                            $update = $sql->update();
                            $update->table('WF_CostCentre');
                            $update->set(array('KickoffId' => $this->bsf->isNullCheck($kickoffId,'number')));
                            $update->where(array('CostCentreId' => $costCentreId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'Project-Kickoff-Add', 'N', 'Project-Kickoff', $kickoffId, $projectId, 0, 'KICKOFF', '', $userId, 0, 0);

                        $FeedId = $this->params()->fromQuery('FeedId');
                        $AskId = $this->params()->fromQuery('AskId');
                        if((isset($FeedId) && $FeedId!="")) {
                            $this->redirect()->toRoute('kickoff/conception-detail', array('controller' => 'index', 'action' => 'conception-detail', 'kickoffId' => $kickoffId, 'conceptionId' => $neConception), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {
                            $this->redirect()->toRoute('kickoff/conception-detail', array('controller' => 'index', 'action' => 'conception-detail', 'kickoffId' => $kickoffId, 'conceptionId' => $neConception));
                        }
					}
				}
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}

        $this->_view->EnquiryId = '';
		$this->_view->landName = '';
		if($iEnquiryId != '') {
			$select = $sql->select();
			$select->from('Proj_LandEnquiry')
				->columns(array('EnquiryId','PropertyName'))
				->where('EnquiryId = '.$iEnquiryId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$landEnquiry = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->landName = $landEnquiry['PropertyName'];
			$this->_view->EnquiryId = $landEnquiry['EnquiryId'];
		}
		$this->_view->enquiryId = (isset($iEnquiryId) && $iEnquiryId != 0) ? $iEnquiryId : 0;
		
		return $this->_view;
	}
	
	public function conceptionAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
            $this->redirect()->toRoute('kickoff/conception', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
				->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->kickoffRes = $arrKick;
		
        $iEnquiryId = 0;
        if (!empty($arrKick)) {
            $iEnquiryId = $this->bsf->isNullCheck($arrKick['EnquiryId'], 'number');
        }
		
		$this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		// Landbank Conception
		$select = $sql->select();
		$select->from(array('a' => 'Proj_LandConceptionRegister'))
				->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId = b.EnquiryId', array('PropertyName'))
				->where('a.EnquiryId = ' . $iEnquiryId);
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->landbankConception = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
        if (empty($this->_view->landbankConception)) {
            $this->redirect()->toRoute('kickoff/conception-detail', array('controller' => 'index', 'action' => 'conception-detail', 'kickoffId' => $kickoffId, 'conceptionId' => 0));
        }
		
		// Done Conception
		$select = $sql->select();
		$select->from('KF_Conception')
				->columns(array("LandConceptionId"))
				->where('EnquiryId = ' . $iEnquiryId);
		$statement = $sql->getSqlStringForSqlObject($select);
		$doneConcepts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$arrDoneConcept = array();
		foreach($doneConcepts as $doneC) {
			$arrDoneConcept[] = $doneC['LandConceptionId'];
		}
		
		$this->_view->doneConceptions = $arrDoneConcept;
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;
		
		return $this->_view;
	}
	
	public function conceptionDetailAction()
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
		
		$userId = $this->auth->getIdentity()->UserId;
		$kickoffId = $this->bsf->isNullCheck($this->params()->fromRoute('kickoffId'),'number');
		$conceptionId = $this->bsf->isNullCheck($this->params()->fromRoute('conceptionId'),'number');
        //echo $kickoffId;echo $conceptionId;exit;
		
		if($kickoffId == 0 && $conceptionId == 0) {

			$this->redirect()->toRoute('kickoff/conception-detail', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				$files = $request->getFiles();
				//echo '<pre>'; print_r($postData); die;
				//echo '<pre>'; print_r($files); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				$iKfConceptionId = $this->bsf->isNullCheck($postData['kfConceptionId'], 'number');
				$landConceptionId = $this->bsf->isNullCheck($postData['landConceptionId'], 'number');
				$enquiryId = $this->bsf->isNullCheck($postData['enquiryId'], 'number');
				if ($iKfConceptionId == 0) {
					$insert = $sql->insert();
					$insert->into('KF_Conception');
					$insert->Values(array('EnquiryId' => $enquiryId
						, 'LandConceptionId' => $landConceptionId
						, 'KickoffId' => $iKickoffId
						, 'OptionName' => $this->bsf->isNullCheck($postData['OptionName'],'string')
						, 'PresentedBy' => $this->bsf->isNullCheck($postData['PresentedBy'],'string')
						, 'BusinessTypeId' => $this->bsf->isNullCheck($postData['BusinessTypeId'],'number')
						, 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')
						, 'NoOfPhases' => $this->bsf->isNullCheck($postData['NoOfPhases'],'number')
						, 'NoOfBlocks' => $this->bsf->isNullCheck($postData['NoOfBlocks'],'number')
						, 'NoOfFloors' => $this->bsf->isNullCheck($postData['NoOfFloors'],'number')
						, 'NoOfFlats' => $this->bsf->isNullCheck($postData['NoOfFlats'],'number')
						, 'CommonArea' => $this->bsf->isNullCheck($postData['CommonArea'],'number')
						, 'CommonAreaUnitId' => $this->bsf->isNullCheck($postData['CommonAreaUnitId'],'number')
						, 'SaleableArea' => $this->bsf->isNullCheck($postData['SaleableArea'],'number')
						, 'SaleableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'],'number')
						, 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                     $statement = $sql->getSqlStringForSqlObject($insert);

					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$conceptionId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert();
					$insert->into('KF_ConceptionGeneral');
					$insert->Values(array('ConceptionId' => $conceptionId
						, 'ProjectAddress' => $this->bsf->isNullCheck($postData['ProjectAddress'],'string')
						, 'City' => $this->bsf->isNullCheck($postData['City'],'string')
						, 'PinCode' => $this->bsf->isNullCheck($postData['PinCode'],'string')
						, 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'],'string')
						, 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'],'string')
						, 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'],'string')
						, 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'],'number')
						, 'Electricity' => $this->bsf->isNullCheck($postData['Electricity'],'number')
						, 'FSI' => $this->bsf->isNullCheck($postData['FSI'],'number')
						, 'PremiumFSI' => $this->bsf->isNullCheck($postData['PremiumFSI'],'number')
						, 'Guideline' => $this->bsf->isNullCheck($postData['Guideline'],'number')
						, 'Floors' => $this->bsf->isNullCheck($postData['Floors'],'number')
						, 'ExpandableArea' => $this->bsf->isNullCheck($postData['ExpandableArea'],'number')));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$specrowid = $this->bsf->isNullCheck($postData['specrowid'],'number');
					for ($i = 1; $i <= $specrowid; $i++) {
						$specName = $this->bsf->isNullCheck($postData['spec_' . $i],'string');
						if ($specName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionSpecTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'SpecificationName' => $specName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$splfeaturerowid = $this->bsf->isNullCheck($postData['splfeaturerowid'],'number');
					for ($i = 1; $i <= $splfeaturerowid; $i++) {
						$splfeatureName = $this->bsf->isNullCheck($postData['splfeature_' . $i],'string');
						if ($splfeatureName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionFeatureTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'FeatureName' => $splfeatureName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$amenitiesrowid = $this->bsf->isNullCheck($postData['amenitiesrowid'],'number');
					for ($i = 1; $i <= $amenitiesrowid; $i++) {
						$amenityName = $this->bsf->isNullCheck($postData['amenities_' . $i],'string');
						if ($amenityName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionAmenityTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'AmenityName' => $amenityName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$drawingrowid = $this->bsf->isNullCheck($postData['drawingrowid'],'number');
					for ($i = 1; $i <= $drawingrowid; $i++) {
						$drawingName = $this->bsf->isNullCheck($postData['drawingname_' . $i],'string');
						$drawingUrl = $this->bsf->isNullCheck($postData['drawingUrl_' . $i],'string');
						if ($drawingName != "") {
							if ($files['drawFile_' . $i]['name']) {
								$drawingUrl = '';
								$dir = 'public/uploads/kickoff/conception/drawing/' . $conceptionId . '/';
								$filename = $this->bsf->uploadFile($dir, $files['drawFile_' . $i]);
								if ($filename) {
									// update valid files only
									$drawingUrl = '/uploads/kickoff/conception/drawing/' . $conceptionId . '/' . $filename;
								}
							}
							$insert = $sql->insert();
							$insert->into('KF_ConceptionDrawingTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'Title' => $drawingName, 'URL' => $drawingUrl));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$documentrowid = $this->bsf->isNullCheck($postData['documentrowid'],'number');
					for ($i = 1; $i <= $documentrowid; $i++) {
						$type = $this->bsf->isNullCheck($postData['documentname_' . $i],'string');
						$description = $this->bsf->isNullCheck($postData['documentdescription_' . $i],'string');
						$docUrl = $this->bsf->isNullCheck($postData['documentUrl_' . $i],'string');
						if ($type != "") {
							if ($files['docFile_' . $i]['name']) {
								$docUrl = '';
								$dir = 'public/uploads/kickoff/conception/documents/' . $conceptionId . '/';
								$filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);
								if ($filename) {
									// update valid files only
									$docUrl = '/uploads/kickoff/conception/documents/' . $conceptionId . '/' . $filename;
								}
							}
							$insert = $sql->insert();
							$insert->into('KF_ConceptionDocumentTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'Type' => $type, 'Description' => $description, 'URL' => $docUrl));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$consultantrowid = $this->bsf->isNullCheck($postData['consultantrowid'],'number');
					for ($i = 1; $i <= $consultantrowid; $i++) {
						$conName = $this->bsf->isNullCheck($postData['consultantname_' . $i],'string');
						$conType = $this->bsf->isNullCheck($postData['consultanttype_' . $i],'string');
						$fee = $this->bsf->isNullCheck($postData['fees_' . $i],'number');
						$feeAmount = $this->bsf->isNullCheck($postData['feesamount_' . $i],'number');
						if ($conName != "" && $conType != "" && $fee != "" && $feeAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionConsultantTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'Name' => $conName, 'Type' => $conType, 'Fee' => $fee, 'FeeAmount' => $feeAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$approvalrowid = $this->bsf->isNullCheck($postData['approvalrowid'],'number');
					for ($i = 1; $i <= $approvalrowid; $i++) {
						$authName = $this->bsf->isNullCheck($postData['approvalauthority_' . $i],'string');
						$approveDate = $this->bsf->isNullCheck($postData['approvaldate_' . $i],'string');
						$approveNo = $this->bsf->isNullCheck($postData['approvalno_' . $i],'string');
						if ($authName != "" && $approveDate != "" && $approveNo != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionApprovalTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'AuthorityName' => $authName, 'ApproveDate' => date('Y-m-d', strtotime($approveDate)), 'ApproveNo' => $approveNo));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$pcostrowid = $this->bsf->isNullCheck($postData['pcostrowid'],'number');
					for ($i = 1; $i <= $pcostrowid; $i++) {
						$exParticular = $this->bsf->isNullCheck($postData['pcostparticular_' . $i],'string');
						$exAmount = $this->bsf->isNullCheck($postData['pcostamount_' . $i],'number');
						if ($exParticular != "" && $exAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionExpenseTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'Particular' => $exParticular, 'Amount' => $exAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$exincomerowid = $this->bsf->isNullCheck($postData['incomerowid'],'number');
                    //print_r($exincomerowid);exit;
					for ($i = 1; $i <= $exincomerowid; $i++) {
						$inParticular = $this->bsf->isNullCheck($postData['incomeparticular_' . $i],'string');
						$inAmount = $this->bsf->isNullCheck($postData['incomeamount_' . $i],'number');
						if ($inParticular != "" && $inAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionIncomeTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'Particular' => $inParticular, 'Amount' => $inAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$constructionrowid = $this->bsf->isNullCheck($postData['constructionrowid'],'number');
					for ($i = 1; $i <= $constructionrowid; $i++) {
						$schYear = $this->bsf->isNullCheck($postData['constructionyear_' . $i],'number');
						$schAmount = $this->bsf->isNullCheck($postData['constructionamount_' . $i],'number');
						if ($schYear != "" && $schAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionScheduleTrans');
							$insert->Values(array('ConceptionId' => $conceptionId, 'ShYear' => $schYear, 'Amount' => $schAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
				} else {
					$update = $sql->update();
                    $update->table('KF_Conception');
                    $update->set(array('OptionName' => $this->bsf->isNullCheck($postData['OptionName'],'string')
						, 'PresentedBy' => $this->bsf->isNullCheck($postData['PresentedBy'],'string')
						, 'BusinessTypeId' => $this->bsf->isNullCheck($postData['BusinessTypeId'],'number')
						, 'ProjectTypeId' => $this->bsf->isNullCheck($postData['ProjectTypeId'],'number')
						, 'NoOfPhases' => $this->bsf->isNullCheck($postData['NoOfPhases'],'number')
						, 'NoOfBlocks' => $this->bsf->isNullCheck($postData['NoOfBlocks'],'number')
						, 'NoOfFloors' => $this->bsf->isNullCheck($postData['NoOfFloors'],'number')
						, 'NoOfFlats' => $this->bsf->isNullCheck($postData['NoOfFlats'],'number')
						, 'CommonArea' => $this->bsf->isNullCheck($postData['CommonArea'],'number')
						, 'CommonAreaUnitId' => $this->bsf->isNullCheck($postData['CommonAreaUnitId'],'number')
						, 'SaleableArea' => $this->bsf->isNullCheck($postData['SaleableArea'],'number')
						, 'SaleableAreaUnitId' => $this->bsf->isNullCheck($postData['SaleableAreaUnitId'],'number')));
                    $update->where(array('ConceptionId'=>$iKfConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$update = $sql->update();
                    $update->table('KF_ConceptionGeneral');
                    $update->set(array('ProjectAddress' => $this->bsf->isNullCheck($postData['ProjectAddress'],'string')
						, 'City' => $this->bsf->isNullCheck($postData['City'],'string')
						, 'PinCode' => $this->bsf->isNullCheck($postData['PinCode'],'string')
						, 'LandMark' => $this->bsf->isNullCheck($postData['LandMark'],'string')
						, 'SoilType' => $this->bsf->isNullCheck($postData['SoilType'],'string')
						, 'GroundWaterLevel' => $this->bsf->isNullCheck($postData['GroundWaterLevel'],'string')
						, 'GovtWaterSupply' => $this->bsf->isNullCheck($postData['GovtWaterSupply'],'number')
						, 'Electricity' => $this->bsf->isNullCheck($postData['Electricity'],'number')
						, 'FSI' => $this->bsf->isNullCheck($postData['FSI'],'number')
						, 'PremiumFSI' => $this->bsf->isNullCheck($postData['PremiumFSI'],'number')
						, 'Guideline' => $this->bsf->isNullCheck($postData['Guideline'],'number')
						, 'Floors' => $this->bsf->isNullCheck($postData['Floors'],'number')
						, 'ExpandableArea' => $this->bsf->isNullCheck($postData['ExpandableArea'],'number')));
                    $update->where(array('ConceptionId'=>$iKfConceptionId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionSpecTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$specrowid = $this->bsf->isNullCheck($postData['specrowid'],'number');
					for ($i = 1; $i <= $specrowid; $i++) {
						$specName = $this->bsf->isNullCheck($postData['spec_' . $i],'string');
						if ($specName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionSpecTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'SpecificationName' => $specName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionFeatureTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$splfeaturerowid = $this->bsf->isNullCheck($postData['splfeaturerowid'],'number');
					for ($i = 1; $i <= $splfeaturerowid; $i++) {
						$splfeatureName = $this->bsf->isNullCheck($postData['splfeature_' . $i],'string');
						if ($splfeatureName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionFeatureTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'FeatureName' => $splfeatureName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionAmenityTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$amenitiesrowid = $this->bsf->isNullCheck($postData['amenitiesrowid'],'number');
					for ($i = 1; $i <= $amenitiesrowid; $i++) {
						$amenityName = $this->bsf->isNullCheck($postData['amenities_' . $i],'string');
						if ($amenityName != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionAmenityTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'AmenityName' => $amenityName));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionDrawingTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$drawingrowid = $this->bsf->isNullCheck($postData['drawingrowid'],'number');
					for ($i = 1; $i <= $drawingrowid; $i++) {
						$drawingName = $this->bsf->isNullCheck($postData['drawingname_' . $i],'string');
						$drawingUrl = $this->bsf->isNullCheck($postData['drawingUrl_' . $i],'string');
						if ($drawingName != "") {
							if ($files['drawFile_' . $i]['name']) {
								$drawingUrl = '';
								$dir = 'public/uploads/kickoff/conception/drawing/' . $iKfConceptionId . '/';
								$filename = $this->bsf->uploadFile($dir, $files['drawFile_' . $i]);
								if ($filename) {
									// update valid files only
									$drawingUrl = '/uploads/kickoff/conception/drawing/' . $iKfConceptionId . '/' . $filename;
								}
							}
							$insert = $sql->insert();
							$insert->into('KF_ConceptionDrawingTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'Title' => $drawingName, 'URL' => $drawingUrl));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionDocumentTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$documentrowid = $this->bsf->isNullCheck($postData['documentrowid'],'number');
					for ($i = 1; $i <= $documentrowid; $i++) {
						$type = $this->bsf->isNullCheck($postData['documentname_' . $i],'string');
						$description = $this->bsf->isNullCheck($postData['documentdescription_' . $i],'string');
						$docUrl = $this->bsf->isNullCheck($postData['documentUrl_' . $i],'string');
						if ($type != "") {
							if ($files['docFile_' . $i]['name']) {
								$docUrl = '';
								$dir = 'public/uploads/kickoff/conception/documents/' . $iKfConceptionId . '/';
								$filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);
								if ($filename) {
									// update valid files only
									$docUrl = '/uploads/kickoff/conception/documents/' . $iKfConceptionId . '/' . $filename;
								}
							}
							$insert = $sql->insert();
							$insert->into('KF_ConceptionDocumentTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'Type' => $type, 'Description' => $description, 'URL' => $docUrl));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionConsultantTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$consultantrowid = $this->bsf->isNullCheck($postData['consultantrowid'],'number');
					for ($i = 1; $i <= $consultantrowid; $i++) {
						$conName = $this->bsf->isNullCheck($postData['consultantname_' . $i],'string');
						$conType = $this->bsf->isNullCheck($postData['consultanttype_' . $i],'string');
						$fee = $this->bsf->isNullCheck($postData['fees_' . $i],'number');
						$feeAmount = $this->bsf->isNullCheck($postData['feesamount_' . $i],'number');
						if ($conName != "" && $conType != "" && $fee != "" && $feeAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionConsultantTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'Name' => $conName, 'Type' => $conType, 'Fee' => $fee, 'FeeAmount' => $feeAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionApprovalTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$approvalrowid = $this->bsf->isNullCheck($postData['approvalrowid'],'number');
					for ($i = 1; $i <= $approvalrowid; $i++) {
						$authName = $this->bsf->isNullCheck($postData['approvalauthority_' . $i],'string');
						$approveDate = $this->bsf->isNullCheck($postData['approvaldate_' . $i],'string');
						$approveNo = $this->bsf->isNullCheck($postData['approvalno_' . $i],'string');
						if ($authName != "" && $approveDate != "" && $approveNo != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionApprovalTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'AuthorityName' => $authName, 'ApproveDate' => date('Y-m-d', strtotime($approveDate)), 'ApproveNo' => $approveNo));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionExpenseTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$pcostrowid = $this->bsf->isNullCheck($postData['pcostrowid'],'number');
					for ($i = 1; $i <= $pcostrowid; $i++) {
						$exParticular = $this->bsf->isNullCheck($postData['pcostparticular_' . $i],'string');
						$exAmount = $this->bsf->isNullCheck($postData['pcostamount_' . $i],'number');
						if ($exParticular != "" && $exAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionExpenseTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'Particular' => $exParticular, 'Amount' => $exAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionIncomeTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$exincomerowid = $this->bsf->isNullCheck($postData['incomerowid'],'number');
					for ($i = 1; $i <= $exincomerowid; $i++) {
						$inParticular = $this->bsf->isNullCheck($postData['incomeparticular_' . $i],'string');
						$inAmount = $this->bsf->isNullCheck($postData['incomeamount_' . $i],'number');
						if ($inParticular != "" && $inAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionIncomeTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'Particular' => $inParticular, 'Amount' => $inAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
					
					$delete = $sql->delete();
                    $delete->from('KF_ConceptionScheduleTrans')
                        ->where("ConceptionId=$iKfConceptionId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$constructionrowid = $this->bsf->isNullCheck($postData['constructionrowid'],'number');
					for ($i = 1; $i <= $constructionrowid; $i++) {
						$schYear = $this->bsf->isNullCheck($postData['constructionyear_' . $i],'number');
						$schAmount = $this->bsf->isNullCheck($postData['constructionamount_' . $i],'number');
						if ($schYear != "" && $schAmount != "") {
							$insert = $sql->insert();
							$insert->into('KF_ConceptionScheduleTrans');
							$insert->Values(array('ConceptionId' => $iKfConceptionId, 'ShYear' => $schYear, 'Amount' => $schAmount));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
				}
                $aVNo = CommonHelper::getVoucherNo(110, date('Y-m-d', strtotime($postData['refDate'])), 0, 0, $dbAdapter, "I");
                if ($aVNo["genType"] == true) $sVno = $aVNo["voucherNo"];


                $update = $sql->update();
				$update->table('KF_KickoffRegister');
				$update->set(array('RefDate' => date('Y-m-d', strtotime($postData['refDate']))
					, 'RefNo' => $sVno ));
				$update->where(array('KickoffId'=>$iKickoffId));
				$statement = $sql->getSqlStringForSqlObject($update);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$voucherNo = $this->bsf->isNullCheck($postData['refNo'],'string');

				$connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'), 'Project-Kickoff-Modify', 'E', 'Project-Kickoff', $iKickoffId, 0, 0, 'KICKOFF', $voucherNo, $userId, 0 ,0);

                $select = $sql->select();
				$select->from('KF_UnitMaster')
					->where(array('KickoffId' => $iKickoffId));
				$stmt = $sql->getSqlStringForSqlObject( $select );
				$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
				if(!empty($arrUnits)) {
					$this->redirect()->toRoute('kickoff/newproject-edit', array('controller' => 'index', 'action' => 'newproject-edit', 'kickoffId' => $iKickoffId));
					return;
				} else {
					$this->redirect()->toRoute('kickoff/newproject', array('controller' => 'index', 'action' => 'newproject', 'kickoffId' => $iKickoffId));
				}
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}

        // Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
            ->join(array('b' => 'Proj_landEnquiry'), 'a.EnquiryId= b.EnquiryId', array('PropertyName'=>new Expression("isnull(b.PropertyName,'')")), $select::JOIN_LEFT)
            ->columns(array('KickOffId','EnquiryId','RefDate','RefNo','ProjectName','CostCentreId'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->kickoffRes = $arrKick;
		$this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();

		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		// Kickoff Conception Register
		$select = $sql->select();
		$select->from('KF_Conception')
			->where('KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
		$kfConceptionRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		//print_r($kfConceptionRes);exit;
		$kfConceptionId = 0;
		if($kfConceptionRes['ConceptionId'] != '') {
			$kfConceptionId = $kfConceptionRes['ConceptionId'];
		}

        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->genType = $aVNo["genType"];
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		if($kfConceptionId != 0) {
			// Kickoff Conception Register
			$select = $sql->select();
			$select->from(array('a' => 'KF_Conception'))
				->join(array('d' => 'KF_ConceptionGeneral'), 'a.ConceptionId = d.ConceptionId', array('ProjectAddress', 'City', 'PinCode', 'LandMark', 'SoilType', 'GroundWaterLevel', 'GovtWaterSupply', 'Electricity', 'FSI', 'PremiumFSI', 'Guideline', 'Floors', 'ExpandableArea'))
				->where(array("a.ConceptionId" => $kfConceptionId));
			$statement = $sql->getSqlStringForSqlObject($select);
            //echo $statement;exit;
			$this->_view->conceptionRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            //print_r($this->_view->conceptionRes);exit;
			// Kickoff Conception Specification
			$select = $sql->select();
			$select->from('KF_ConceptionSpecTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionSpec = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Special Features
			$select = $sql->select();
			$select->from('KF_ConceptionFeatureTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionSpecFeatures = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Amenity
			$select = $sql->select();
			$select->from('KF_ConceptionAmenityTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionAmenities = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Drawing
			$select = $sql->select();
			$select->from('KF_ConceptionDrawingTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionDrawing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Documents
			$select = $sql->select();
			$select->from('KF_ConceptionDocumentTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionDocuments = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Consultant
			$select = $sql->select();
			$select->from('KF_ConceptionConsultantTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionConsultants = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Approval
			$select = $sql->select();
			$select->from('KF_ConceptionApprovalTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionApproval = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Kickoff Conception Expense
			$select = $sql->select();
			$select->from('KF_ConceptionExpenseTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionExpense = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //print_r($this->_view->conceptionExpense);exit;
			// Kickoff Conception Income
			$select = $sql->select();
			$select->from('KF_ConceptionIncomeTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionIncome = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			//print_r($this->_view->conceptionIncome);exit;
			// Kickoff Conception Schedule
			$select = $sql->select();
			$select->from('KF_ConceptionScheduleTrans')
				->where('ConceptionId=' . $kfConceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		} else {
			// Land Conception Register
			$select = $sql->select();
			$select->from(array('a' => 'Proj_LandConceptionRegister'))
				->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId = b.EnquiryId', array('PropertyName'))
				->join(array('c' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId = c.ProjectTypeId', array('ProjectTypeName'))
				->join(array('d' => 'Proj_LandBusinessFeasibility'), 'a.FeasibilityId = d.FeasibilityId', array('OptionName', 'PresentedBy'))
				->join(array('e' => 'Proj_LandConceptionGeneral'), 'a.ConceptionId = e.ConceptionId', array('ProjectAddress', 'LandMark', 'SoilType', 'GroundWaterLevel', 'GovtWaterSupply', 'Electricity', 'FSI', 'PremiumFSI', 'Guideline', 'Floors', 'ExpandableArea'))
				->where(array("a.ConceptionId" => $conceptionId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
			// Land Conception Special Features
			$select = $sql->select();
			$select->from('Proj_LandConceptionFeatureTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionSpecFeatures = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Amenity
			$select = $sql->select();
			$select->from('Proj_LandConceptionAmenityTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionAmenities = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Drawing
			$select = $sql->select();
			$select->from('Proj_LandConceptionDrawingTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionDrawing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Documents
			$select = $sql->select();
			$select->from('Proj_LandConceptionDocumentTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionDocuments = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Consultant
			$select = $sql->select();
			$select->from('Proj_LandConceptionConsultantTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionConsultants = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Approval
			$select = $sql->select();
			$select->from('Proj_LandConceptionApprovalTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionApproval = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Expense
			$select = $sql->select();
			$select->from('Proj_LandConceptionExpenseTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionExpense = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Income
			$select = $sql->select();
			$select->from('Proj_LandConceptionIncomeTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionIncome = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			// Land Conception Schedule
			$select = $sql->select();
			$select->from('Proj_LandConceptionScheduleTrans')
				->where('ConceptionId=' . $conceptionId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->conceptionSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		// Area Unit Types
		$select = $sql->select();
		$select->from('Proj_UOM')
			->where('TypeId=2');
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
        $select = $sql->select();
        $select->from( 'WF_BusinessTypeMaster' )
            ->columns(array("BusinessTypeId", "BusinessTypeName"))
            ->where("BType='B'");
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->businessTypeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		$select = $sql->select();
        $select->from('Proj_ProjectTypeMaster');
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
		$select->from(array('a' => 'KF_PhaseMaster'))
			->columns(array('PCount' => new Expression("count(distinct a.PhaseId)")))
			->join(array('b' => 'KF_BlockMaster'), 'a.PhaseId = b.PhaseId', array('BCount' => new Expression("count(distinct b.BlockId)")), $select::JOIN_LEFT)
			->join(array('c' => 'KF_FloorMaster'), 'b.BlockId = c.BlockId', array('FCount' => new Expression("count(distinct c.FloorId)")), $select::JOIN_LEFT)
			->join(array('d' => 'KF_UnitMaster'), 'c.FloorId = d.FloorId', array('UCount' => new Expression("count(distinct d.UnitId)")), $select::JOIN_LEFT)
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->kfCounts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//Specifications
		$select = $sql->select();
		$select->from( 'KF_ConceptionSpecTrans' )
				->columns(array("value" => new Expression ('DISTINCT(SpecificationName)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfSpecifications = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Features
		$select = $sql->select();
		$select->from( 'KF_ConceptionFeatureTrans' )
				->columns(array("value" => new Expression ('DISTINCT(FeatureName)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfFeatures = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Amenity
		$select = $sql->select();
		$select->from( 'KF_ConceptionAmenityTrans' )
				->columns(array("value" => new Expression ('DISTINCT(AmenityName)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfAmenity = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Drawing
		$select = $sql->select();
		$select->from( 'KF_ConceptionDrawingTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Title)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfDrawing = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Document
		$select = $sql->select();
		$select->from( 'KF_ConceptionDocumentTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Type)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfDocument = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Consultant
		$select = $sql->select();
		$select->from( 'KF_ConceptionConsultantTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Name)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfConsultantName = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		$select = $sql->select();
		$select->from( 'KF_ConceptionConsultantTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Type)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfConsultantType = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Approval
		$select = $sql->select();
		$select->from( 'KF_ConceptionApprovalTrans' )
				->columns(array("value" => new Expression ('DISTINCT(AuthorityName)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfApproval = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Expense
		$select = $sql->select();
		$select->from( 'KF_ConceptionExpenseTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Particular)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfExpense = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Income
		$select = $sql->select();
		$select->from( 'KF_ConceptionIncomeTrans' )
				->columns(array("value" => new Expression ('DISTINCT(Particular)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfIncome = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;
		$this->_view->kfConceptionId = (isset($kfConceptionId) && $kfConceptionId != 0) ? $kfConceptionId : 0;
		$this->_view->landConceptionId = (isset($conceptionId) && $conceptionId != 0) ? $conceptionId : 0;
		$this->_view->enquiryId = (isset($arrKick) && $arrKick['EnquiryId'] != 0) ? $arrKick['EnquiryId'] : 0;
		
		return $this->_view;
	}
	
	public function wbsAction()
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
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$kfId = $this->params()->fromRoute('kickoffId');
			
			$select = $sql->select();
			$select->from('KF_WBS')
				->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => 'WBSName'))
				->where(array("KickoffId = $kfId"));
			$statement = $sql->getSqlStringForSqlObject($select);
			
			$response = $this->getResponse();
			$response->setContent(json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray()));
			return $response;
		} else {
			$kickoffId = $this->params()->fromRoute('kickoffId');
			if($kickoffId == '') {
				$this->redirect()->toRoute('kickoff/wbs', array('controller' => 'index', 'action' => 'project-kickoff'));
			}
			
			if(isset($kickoffId) && $kickoffId != 0) {
				$select = $sql->select();
				$select->from('KF_WBS')
					->columns(array('WBSId'))
					->where(array("KickoffId = '".$kickoffId."'"));
				$statement = $sql->getSqlStringForSqlObject($select);
				$wbsRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				// Kickoff Register      
				$select = $sql->select();
				$select->from(array('a' => 'KF_KickoffRegister'))
					->where('a.KickoffId = ' . $kickoffId);
				$statement = $sql->getSqlStringForSqlObject($select);
				$arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				$this->_view->kickoffRes = $arrKick;
				
				$wbsId = 0;
                if (!empty($wbsRes)) $wbsId = $this->bsf->isNullCheck($wbsRes['WBSId'],'number');
				
				if($wbsId == 0) {
					$insert = $sql->insert();
					$insert->into('KF_WBS');
					$insert->Values(array('KickoffId' => $kickoffId, 'ParentId' => 0, 'WBSName' => $arrKick['ProjectName'], 'SortOrder' => 0));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$this->_view->unitUrl = '#';
				$select = $sql->select();
				$select->from('KF_UnitMaster')
					->where(array('KickoffId' => $kickoffId));
				$stmt = $sql->getSqlStringForSqlObject( $select );
				$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
				
				if(!empty($arrUnits)) {
					$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
				} else {
					$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
				}
			}
			
			$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;
            $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
            if ($aVNo["genType"] == false)
                $this->_view->svNo = "";
            else
                $this->_view->svNo = $aVNo["voucherNo"];
			
			return $this->_view;
		}
	}
	
	public function wbsInsertAction()
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
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			$postData = $request->getPost();
			
			$treeData = json_decode($postData['result'], true);
			//echo '<pre>'; print_r($treeData); die;
			$status = 'process';
			
			foreach($treeData as $key => $tData) {
				if($tData['action']=='create') {
					$select = $sql->select();
					$select->from('KF_WBS')
						->columns(array('WBSId'))
						->where(array("WBSName = '".$tData['newval']."'"));
					$statement = $sql->getSqlStringForSqlObject($select);
					$wbsRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					
					$wbsId = $wbsRes['WBSId'];
					if($wbsId != '') {
						$insert = $sql->insert();
						$insert->into('KF_WBS');
						$insert->Values(array('KickoffId' => $tData['kfId'], 'ParentId' => $wbsId, 'WBSName' => $tData['name'], 'SortOrder' => 0));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				} else if($tData['action']=='update') {
					$select = $sql->select();
					$select->from('KF_WBS')
						->columns(array('WBSId'))
						->where(array("WBSName = '".$tData['newval']."'"));
					$statement = $sql->getSqlStringForSqlObject($select);
					$wbsRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					
					$wbsId = $wbsRes['WBSId'];
					if($wbsId != '') {						
						$update = $sql->update();
						$update->table('KF_WBS');
						$update->set(array('WBSName' => $tData['name']));
						$update->where(array('WBSId'=>$wbsId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
			}
			$status = 'success';
			
			$response = $this->getResponse();
			$response->setContent($status);
			return $response;
		}
	}
	
	public function turnaroundAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/turnaround', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				//echo '<pre>'; print_r($postData); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				
				if ($iKickoffId != 0) {
					$delete = $sql->delete();
                    $delete->from('KF_TurnaroundSchedule')
                        ->where("KickoffId = $iKickoffId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$taScheduleRows = $this->bsf->isNullCheck($postData['taScheduleRowId'],'number');
				for($i=1;$i<=$taScheduleRows;$i++) {
					$description = $this->bsf->isNullCheck($postData['description_' . $i],'string');
					$icostTypeId = $this->bsf->isNullCheck($postData['costTypeId_' . $i],'number');
					$amount = $this->bsf->isNullCheck($postData['amount_' . $i],'number');
					$duration = $this->bsf->isNullCheck($postData['duration_' . $i],'string');
					$sDate = $this->bsf->isNullCheck($postData['startDate_' . $i],'string');
					$eDate = $this->bsf->isNullCheck($postData['endDate_' . $i],'string');
					
					if ($description == "" || $amount == 0 || $duration == "" || $sDate == "" || $eDate == "")
						continue;
					
					$insert = $sql->insert();
					$insert->into('KF_TurnaroundSchedule');
					$insert->Values(array('KickoffId' => $iKickoffId, 'Description' => $description, 'CostTypeId' => $icostTypeId, 'Amount' => $amount, 'Duration' => $duration, 'StartDate' => date('Y-m-d', strtotime($sDate)), 'EndDate' => date('Y-m-d', strtotime($eDate))));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$connection->commit();
				$this->redirect()->toRoute('kickoff/turnaround', array('controller' => 'index', 'action' => 'team', 'kickoffId' => $iKickoffId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
		$arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->kickoffRes = $arrKick;

        $this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		//Cost Type Master
		$select = $sql->select();
        $select->from('Proj_CostTypeMaster')
            ->columns(array('data' => 'CostTypeId', 'value' => 'CostTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->costTypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Description
		$select = $sql->select();
		$select->from( 'KF_TurnaroundSchedule' )
				->columns(array("value" => new Expression ('DISTINCT(Description)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfTurnaround = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		if (isset($kickoffId) && $kickoffId != 0) {
			$select = $sql->select();
			$select->from(array('a' => 'KF_TurnaroundSchedule'))
				->join(array('b' => 'Proj_CostTypeMaster'), 'a.CostTypeId = b.CostTypeId', array('CostTypeName'))
				->where('KickoffId=' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfTaSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		return $this->_view;
	}
	
	public function teamAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/team', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				//echo '<pre>'; print_r($postData); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				
				if ($iKickoffId != 0) {
					$delete = $sql->delete();
                    $delete->from('KF_Team')
                        ->where("KickoffId = $iKickoffId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				 $teamRowId = $this->bsf->isNullCheck($postData['teamRowId'],'number');
				for($i=1;$i<=$teamRowId;$i++) {
                    $positionId = $this->bsf->isNullCheck($postData['PositionId_' . $i],'number');
					$quantity = $this->bsf->isNullCheck($postData['Quantity_' . $i],'number');
					
					if ($positionId == 0 || $quantity == 0)
						continue;
					
					$insert = $sql->insert();
					$insert->into('KF_Team');
					$insert->Values(array('KickoffId' => $iKickoffId, 'PositionId' => $positionId, 'Quantity' => $quantity));
					 $statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				$connection->commit();
				$this->redirect()->toRoute('kickoff/team', array('controller' => 'index', 'action' => 'make-brand', 'kickoffId' => $iKickoffId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->kickoffRes = $arrKick;

        $this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		// Position Master
		$select = $sql->select();
		$select->from( 'WF_PositionMaster' )
				->columns(array("data"=>"PositionId", "value"=>"PositionName"))
				->where( array( 'DeleteFlag' => 0 ) );
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->positionMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		if (isset($kickoffId) && $kickoffId != 0) {
			$select = $sql->select();
			$select->from(array('a' => 'KF_Team'))
				->join(array('b' => 'WF_PositionMaster'), 'a.PositionId = b.PositionId', array('PositionName'))
				->where('KickoffId=' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfTeam = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		return $this->_view;
	}
	
	public function makeBrandAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/make-brand', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				//echo '<pre>'; print_r($postData); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				
				if ($iKickoffId != 0) {
					$delete = $sql->delete();
                    $delete->from('KF_MakeBrand')
                        ->where("KickoffId = $iKickoffId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$makebrandRowId = $this->bsf->isNullCheck($postData['makebrandRowId'],'number');
				for($i=1;$i<=$makebrandRowId;$i++) {
					$materialName = $this->bsf->isNullCheck($postData['MaterialName_' . $i],'string');
					$materialId = $this->bsf->isNullCheck($postData['MaterialId_' . $i],'number');
					$brandName = $this->bsf->isNullCheck($postData['BrandName_' . $i],'string');
					$brandId = $this->bsf->isNullCheck($postData['BrandId_' . $i],'number');
					
					if($brandName != '' && $brandId == 0) {
						$insert = $sql->insert();
						$insert->into('Proj_BrandMaster');
						$insert->Values(array('BrandName' => $brandName));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$brandId = $dbAdapter->getDriver()->getLastGeneratedValue();
					}
					
					if ($materialId == 0 || $brandId == 0)
						continue;
					
					$insert = $sql->insert();
					$insert->into('KF_MakeBrand');
					$insert->Values(array('KickoffId' => $iKickoffId, 'ResourceId' => $materialId, 'BrandId' => $brandId));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$connection->commit();
				$this->redirect()->toRoute('kickoff/make-brand', array('controller' => 'index', 'action' => 'documents', 'kickoffId' => $iKickoffId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->kickoffRes = $arrKick;
		
		$this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		// Resource Master
		$select = $sql->select();
		$select->from( 'Proj_Resource' )
				->columns(array("data"=>"ResourceId", "value"=>"ResourceName"))
				->where( array( 'TypeId' => 2 ) );
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->resourceMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		// Brand Master
		$select = $sql->select();
		$select->from( 'Proj_BrandMaster' )
				->columns(array("data"=>"BrandId", "value"=>"BrandName"));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->brandMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		if (isset($kickoffId) && $kickoffId != 0) {
			$select = $sql->select();
			$select->from(array('a' => 'KF_MakeBrand'))
				->join(array('b' => 'Proj_Resource'), 'a.ResourceId = b.ResourceId', array('ResourceName'))
				->join(array('c' => 'Proj_BrandMaster'), 'a.BrandId = c.BrandId', array('BrandName'))
				->where('KickoffId=' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfMakeBrand = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;
        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		return $this->_view;
	}
	
	public function documentsAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/documents', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				$files = $request->getFiles();
				//echo '<pre>'; print_r($files);
				//echo '<pre>'; print_r($postData); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				
				if ($iKickoffId != 0) {
					$delete = $sql->delete();
                    $delete->from('KF_Documents')
                        ->where("KickoffId = $iKickoffId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$delete = $sql->delete();
                    $delete->from('KF_Notes')
                        ->where("KickoffId = $iKickoffId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$documentRowId = $this->bsf->isNullCheck($postData['documentRowId'],'number');
				for($i=1;$i<=$documentRowId;$i++) {
					$docName = $this->bsf->isNullCheck($postData['documentName_' . $i],'string');
					$docType = $this->bsf->isNullCheck($postData['documentType_' . $i],'string');
					
					if ($docName == '' || $docType == '')
						continue;
					
					$url = '';
					if($postData['documentUrl_' . $i] == '') {
						if($files['docFile_' . $i]['name']) {
							$dir = 'public/uploads/kickoff/documents/'.$iKickoffId.'/';
							$filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);
							
							if($filename) {
								// update valid files only
								$url = '/uploads/kickoff/documents/'.$iKickoffId.'/' . $filename;
							}
						}
					} else {
						$url = $this->bsf->isNullCheck($postData['documentUrl_' . $i],'string');
					}
					
					$insert = $sql->insert();
					$insert->into('KF_Documents');
					$insert->Values(array('KickoffId' => $iKickoffId, 'DocumentName' => $docName, 'DocumentType' => $docType, 'URL' => $url));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$noteRowId = $this->bsf->isNullCheck($postData['noteRowId'],'number');
				for($i=1;$i<=$noteRowId;$i++) {
					$title = $this->bsf->isNullCheck($postData['title_' . $i],'string');
					$notes = $this->bsf->isNullCheck($postData['notes_' . $i],'string');
					
					if ($title == '' || $notes == '')
						continue;
					
					$url = '';
					$insert = $sql->insert();
					$insert->into('KF_Notes');
					$insert->Values(array('KickoffId' => $iKickoffId, 'Title' => $title, 'Notes' => $notes, 'URL' => $url));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				$connection->commit();
				$this->redirect()->toRoute('kickoff/documents', array('controller' => 'index', 'action' => 'setup', 'kickoffId' => $iKickoffId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->kickoffRes = $arrKick;
		
        $this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		if (isset($kickoffId) && $kickoffId != 0) {
			$select = $sql->select();
			$select->from('KF_Documents')
				->where('KickoffId = ' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfDocuments = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			$select = $sql->select();
			$select->from('KF_Notes')
				->where('KickoffId = ' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfNotes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		//Document Name
		$select = $sql->select();
		$select->from( 'KF_Documents' )
				->columns(array("value" => new Expression ('DISTINCT(DocumentName)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfDocName = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Document Type
		$select = $sql->select();
		$select->from( 'KF_Documents' )
				->columns(array("value" => new Expression ('DISTINCT(DocumentType)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfDocType = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Note Title
		$select = $sql->select();
		$select->from( 'KF_Notes' )
				->columns(array("value" => new Expression ('DISTINCT(Title)')));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfNoteTitle = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		return $this->_view;
	}
	
	public function setupAction()
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
		
		$kickoffId = $this->params()->fromRoute('kickoffId');
        $page = $this->params()->fromRoute('page');
		if($kickoffId == '') {
			$this->redirect()->toRoute('kickoff/setup', array('controller' => 'index', 'action' => 'project-kickoff'));
		}
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			
			try {
				$postData = $request->getPost();
				//echo '<pre>'; print_r($postData); die;
				
				$iKickoffId = $this->bsf->isNullCheck($postData['kickOffId'],'number');
				$iSetupId = $this->bsf->isNullCheck($postData['editCount'],'number');
				
				if($iSetupId == 0) {

                    $iMsg = 1;
					$costCentreId = $this->bsf->isNullCheck($postData['costCentreId'],'number');
					
					//insert into Proj_ProjectMaster with kickoffid
					if(isset($postData['isMultiple']) && $postData['isMultiple'] == 1) {
						$phRows = $this->bsf->isNullCheck($postData['phCount'],'number');
						for($i=1;$i<=$phRows;$i++) {
							$projectName = $this->bsf->isNullCheck($postData['projectName_' . $i],'string');
							$kfWbsId = $this->bsf->isNullCheck($postData['wbsId_' . $i],'string');
							$phaseId = $this->bsf->isNullCheck($postData['phaseId_' . $i],'string');
							
							$insert = $sql->insert();
							$insert->into('Proj_ProjectMaster');
							$insert->Values(array('ProjectName' => $projectName
								, 'KickoffId' => $iKickoffId));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$projectId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $select = $sql->select();
                            $select->from( 'WF_CostCentre' )
                                ->columns(array("CompanyId"))
                                ->where('CostCentreId = ' . $costCentreId);
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $blocks = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $CompanyId=$blocks['CompanyId'];

							$insert = $sql->insert();
							$insert->into('WF_OperationalCostCentre');
							$insert->Values(array('CostCentreName' => $projectName
											, 'FACostCentreId' => $costCentreId
											, 'ProjectId' => $projectId
											, 'KickoffId' => $iKickoffId
											, 'CompanyId' => $CompanyId
											, 'KfWbsId' => $kfWbsId
											, 'PhaseId' => $phaseId
											, 'SEZProject' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
											, 'WBSReqMMS' => $this->bsf->isNullCheck($postData['materialStock'],'number')
											, 'WBSReqWPM' => $this->bsf->isNullCheck($postData['workProgress'],'number')
											, 'WBSReqClientBill' => 0
											, 'WBSReqLS' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
											, 'WBSReqMMSStockOut' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
											, 'WBSReqAsset' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
											, 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
											, 'ItemWiseIssue' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
											, 'IssueRate' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
											, 'IssueBasedOn' => $this->bsf->isNullCheck($postData['issueBased'],'string')
											, 'TransferBasedOn' => $this->bsf->isNullCheck($postData['transferBased'],'string')
											, 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
											, 'OHBudget' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')
											, 'CRMActual' => $this->bsf->isNullCheck($postData['crmActualBased'],'string')
											, 'CRMReceivable' => $this->bsf->isNullCheck($postData['crmReceivableBased'],'string')));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							//update projectid into KF_PhaseMaster
							$update = $sql->update();
							$update->table('KF_PhaseMaster');
							$update->set(array('ProjectId' => $projectId));
							$update->where(array('PhaseId' => $phaseId));
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							//update projectid into KF_BlockMaster
							$update = $sql->update();
							$update->table('KF_BlockMaster');
							$update->set(array('ProjectId' => $projectId));
							$update->where(array('PhaseId' => $phaseId));
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							//Block Master
                                $select = $sql->select();
                                $select->from( 'KF_BlockMaster' )
                                        ->columns(array("BlockId"))
                                        ->where('PhaseId = ' . $phaseId);
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $blockMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							
							foreach($blockMaster as $bm) {
								//update projectid into KF_FloorMaster
								$update = $sql->update();
								$update->table('KF_FloorMaster');
								$update->set(array('ProjectId' => $projectId));
								$update->where(array('BlockId' => $bm['BlockId']));
								$statement = $sql->getSqlStringForSqlObject($update);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								//update projectid into KF_UnitMaster
								$update = $sql->update();
								$update->table('KF_UnitMaster');
								$update->set(array('ProjectId' => $projectId));
								$update->where(array('BlockId' => $bm['BlockId']));
								$statement = $sql->getSqlStringForSqlObject($update);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								//Unit Master
								$select = $sql->select();
								$select->from( 'KF_UnitMaster' )
										->columns(array("UnitTypeId"))
										->where('BlockId = ' . $bm['BlockId']);
								$statement = $sql->getSqlStringForSqlObject( $select );
								$unitMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
								
								foreach($unitMaster as $um) {
									//update projectid into KF_UnitTypeMaster
									$update = $sql->update();
									$update->table('KF_UnitTypeMaster');
									$update->set(array('ProjectId' => $projectId));
									$update->where(array('UnitTypeId' => $bm['UnitTypeId']));
									$statement = $sql->getSqlStringForSqlObject($update);
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}
							}
						}
					} else {
						$projectName = $this->bsf->isNullCheck($postData['projectName'],'string');
						$insert = $sql->insert();
						$insert->into('Proj_ProjectMaster');
						$insert->Values(array('ProjectName' => $projectName
							, 'KickoffId' => $iKickoffId));
					    $statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$projectId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select->from( 'WF_CostCentre' )
                            ->columns(array("CompanyId"))
                            ->where('CostCentreId = ' . $costCentreId);
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $blocks = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $CompanyId=$blocks['CompanyId'];

						$insert = $sql->insert();
						$insert->into('WF_OperationalCostCentre');
						$insert->Values(array('CostCentreName' => $projectName
										, 'FACostCentreId' => $costCentreId
										, 'ProjectId' => $projectId
										, 'KickoffId' => $iKickoffId
										, 'CompanyId' => $CompanyId
										, 'SEZProject' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
										, 'WBSReqMMS' => $this->bsf->isNullCheck($postData['materialStock'],'number')
										, 'WBSReqWPM' => $this->bsf->isNullCheck($postData['workProgress'],'number')
										, 'WBSReqClientBill' => 0
										, 'WBSReqLS' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
										, 'WBSReqMMSStockOut' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
										, 'WBSReqAsset' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
										, 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
										, 'ItemWiseIssue' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
										, 'IssueRate' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
										, 'IssueBasedOn' => $this->bsf->isNullCheck($postData['issueBased'],'string')
										, 'TransferBasedOn' => $this->bsf->isNullCheck($postData['transferBased'],'string')
										, 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
										, 'OHBudget' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')
										, 'CRMActual' => $this->bsf->isNullCheck($postData['crmActualBased'],'string')
										, 'CRMReceivable' => $this->bsf->isNullCheck($postData['crmReceivableBased'],'string')));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//update projectid into KF_PhaseMaster
						$update = $sql->update();
						$update->table('KF_PhaseMaster');
						$update->set(array('ProjectId' => $projectId));
						$update->where(array('KickoffId' => $iKickoffId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//update projectid into KF_BlockMaster
						$update = $sql->update();
						$update->table('KF_BlockMaster');
						$update->set(array('ProjectId' => $projectId));
						$update->where(array('KickoffId' => $iKickoffId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//update projectid into KF_FloorMaster
						$update = $sql->update();
						$update->table('KF_FloorMaster');
						$update->set(array('ProjectId' => $projectId));
						$update->where(array('KickoffId' => $iKickoffId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//update projectid into KF_UnitTypeMaster
						$update = $sql->update();
						$update->table('KF_UnitTypeMaster');
						$update->set(array('ProjectId' => $projectId));
						$update->where(array('KickoffId' => $iKickoffId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						//update projectid into KF_UnitMaster
						$update = $sql->update();
						$update->table('KF_UnitMaster');
						$update->set(array('ProjectId' => $projectId));
						$update->where(array('KickoffId' => $iKickoffId));
						$statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				} else {

                    $iMsg = 2;
					$costCentreId = $this->bsf->isNullCheck($postData['costCentreId'],'number');
                    //echo $costCentreId; die;
					//update into Proj_ProjectMaster with kickoffid
					if(isset($postData['isMultiple']) && $postData['isMultiple'] == 1) {
						$phRows = $this->bsf->isNullCheck($postData['phCount'],'number');
						for($i=1;$i<=$phRows;$i++) {
							$projectName = $this->bsf->isNullCheck($postData['projectName_' . $i],'string');
							$kfWbsId = $this->bsf->isNullCheck($postData['wbsId_' . $i],'number');
							$phaseId = $this->bsf->isNullCheck($postData['phaseId_' . $i],'number');
							$projectId = $this->bsf->isNullCheck($postData['projectId_' . $i],'number');
							
							$update = $sql->update();
							$update->table('Proj_ProjectMaster');
							$update->set(array('ProjectName' => $projectName));
							$update->where(array('ProjectId'=>$projectId));
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->select();
                            $select->from( 'WF_CostCentre' )
                                ->columns(array("CompanyId"))
                                ->where('CostCentreId = ' . $costCentreId);
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $blocks = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                            $CompanyId=$blocks['CompanyId'];
							
							$update = $sql->update();
							$update->table('WF_OperationalCostCentre');
							$update->set(array('CostCentreName' => $projectName
											, 'FACostCentreId' => $costCentreId
											, 'KfWbsId' => $kfWbsId
											, 'CompanyId' => $CompanyId
											, 'SEZProject' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
											, 'WBSReqMMS' => $this->bsf->isNullCheck($postData['materialStock'],'number')
											, 'WBSReqWPM' => $this->bsf->isNullCheck($postData['workProgress'],'number')
											, 'WBSReqClientBill' => 0
											, 'WBSReqLS' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
											, 'WBSReqMMSStockOut' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
											, 'WBSReqAsset' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
											, 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
											, 'ItemWiseIssue' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
											, 'IssueRate' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
											, 'IssueBasedOn' => $this->bsf->isNullCheck($postData['issueBased'],'string')
											, 'TransferBasedOn' => $this->bsf->isNullCheck($postData['transferBased'],'string')
											, 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
											, 'OHBudget' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')
											, 'CRMActual' => $this->bsf->isNullCheck($postData['crmActualBased'],'string')
											, 'CRMReceivable' => $this->bsf->isNullCheck($postData['crmReceivableBased'],'string')));
							$update->where(array('ProjectId' => $projectId));
							$statement = $sql->getSqlStringForSqlObject($update);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //update projectid into KF_PhaseMaster
                            $update = $sql->update();
                            $update->table('KF_PhaseMaster');
                            $update->set(array('ProjectId' => $projectId));
                            $update->where(array('PhaseId' => $phaseId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //update projectid into KF_BlockMaster
                            $update = $sql->update();
                            $update->table('KF_BlockMaster');
                            $update->set(array('ProjectId' => $projectId));
                            $update->where(array('PhaseId' => $phaseId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Block Master
                            $select = $sql->select();
                            $select->from( 'KF_BlockMaster' )
                                ->columns(array("BlockId"))
                                ->where('PhaseId = ' . $phaseId);
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $blockMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            foreach($blockMaster as $bm) {
                                //update projectid into KF_FloorMaster
                                $update = $sql->update();
                                $update->table('KF_FloorMaster');
                                $update->set(array('ProjectId' => $projectId));
                                $update->where(array('BlockId' => $bm['BlockId']));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //update projectid into KF_UnitMaster
                                $update = $sql->update();
                                $update->table('KF_UnitMaster');
                                $update->set(array('ProjectId' => $projectId));
                                $update->where(array('BlockId' => $bm['BlockId']));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //Unit Master
                                $select = $sql->select();
                                $select->from( 'KF_UnitMaster' )
                                    ->columns(array("UnitTypeId"))
                                    ->where('BlockId = ' . $bm['BlockId']);
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $unitMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                foreach($unitMaster as $um) {
                                    //update projectid into KF_UnitTypeMaster
                                    $update = $sql->update();
                                    $update->table('KF_UnitTypeMaster');
                                    $update->set(array('ProjectId' => $projectId));
                                    $update->where(array('UnitTypeId' => $bm['UnitTypeId']));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
						}
					} else {
                        $projectName = $this->bsf->isNullCheck($postData['projectName'],'string');
                        $projectId = $this->bsf->isNullCheck($postData['projectId'],'number');

                        $update = $sql->update();
                        $update->table('Proj_ProjectMaster');
                        $update->set(array('ProjectName' => $projectName));
                        $update->where(array('ProjectId' => $projectId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select->from( 'WF_CostCentre' )
                            ->columns(array("CompanyId"))
                            ->where('CostCentreId = ' . $costCentreId);
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $blocks = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $CompanyId=$blocks['CompanyId'];

                        $update = $sql->update();
                        $update->table('WF_OperationalCostCentre');
                        $update->set(array('CostCentreName' => $projectName
                        , 'FACostCentreId' => $costCentreId
                        , 'CompanyId' => $CompanyId
                        , 'SEZProject' => $this->bsf->isNullCheck($postData['isSezProject'],'number')
                        , 'WBSReqMMS' => $this->bsf->isNullCheck($postData['materialStock'],'number')
                        , 'WBSReqWPM' => $this->bsf->isNullCheck($postData['workProgress'],'number')
                        , 'WBSReqClientBill' => 0
                        , 'WBSReqLS' => $this->bsf->isNullCheck($postData['labourStrength'],'number')
                        , 'WBSReqMMSStockOut' => $this->bsf->isNullCheck($postData['materialConsumption'],'number')
                        , 'WBSReqAsset' => $this->bsf->isNullCheck($postData['plantMachinery'],'number')
                        , 'MaterialConsumptionBased' => $this->bsf->isNullCheck($postData['materialConsumptionBased'],'string')
                        , 'ItemWiseIssue' => $this->bsf->isNullCheck($postData['issueRequire'],'number')
                        , 'IssueRate' => $this->bsf->isNullCheck($postData['issueRateBased'],'string')
                        , 'IssueBasedOn' => $this->bsf->isNullCheck($postData['issueBased'],'string')
                        , 'TransferBasedOn' => $this->bsf->isNullCheck($postData['transferBased'],'string')
                        , 'CostControlBased' => $this->bsf->isNullCheck($postData['costControlBased'],'string')
                        , 'OHBudget' => $this->bsf->isNullCheck($postData['ohBudgetFrom'],'string')
                        , 'CRMActual' => $this->bsf->isNullCheck($postData['crmActualBased'],'string')
                        , 'CRMReceivable' => $this->bsf->isNullCheck($postData['crmReceivableBased'],'string')));
                        $update->where(array('ProjectId' => $projectId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update projectid into KF_PhaseMaster
                        $update = $sql->update();
                        $update->table('KF_PhaseMaster');
                        $update->set(array('ProjectId' => $projectId));
                        $update->where(array('KickoffId' => $iKickoffId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update projectid into KF_BlockMaster
                        $update = $sql->update();
                        $update->table('KF_BlockMaster');
                        $update->set(array('ProjectId' => $projectId));
                        $update->where(array('KickoffId' => $iKickoffId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update projectid into KF_FloorMaster
                        $update = $sql->update();
                        $update->table('KF_FloorMaster');
                        $update->set(array('ProjectId' => $projectId));
                        $update->where(array('KickoffId' => $iKickoffId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update projectid into KF_UnitTypeMaster
                        $update = $sql->update();
                        $update->table('KF_UnitTypeMaster');
                        $update->set(array('ProjectId' => $projectId));
                        $update->where(array('KickoffId' => $iKickoffId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update projectid into KF_UnitMaster
                        $update = $sql->update();
                        $update->table('KF_UnitMaster');
                        $update->set(array('ProjectId' => $projectId));
                        $update->where(array('KickoffId' => $iKickoffId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
				}

				$connection->commit();
				$this->redirect()->toRoute('kickoff/project-kickoff', array('controller' => 'index', 'action' => 'project-kickoff', 'enquiryId' => 0, 'msg' => $iMsg));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
		}
		
		// Kickoff Register
		$select = $sql->select();
		$select->from(array('a' => 'KF_KickoffRegister'))
			->where('a.KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject($select);
        $arrKick = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->kickoffRes = $arrKick;
		
        $this->_view->unitUrl = '#';
		$select = $sql->select();
		$select->from('KF_UnitMaster')
			->where(array('KickoffId' => $kickoffId));
		$stmt = $sql->getSqlStringForSqlObject( $select );
		$arrUnits = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->current();
		if(!empty($arrUnits)) {
			$this->_view->unitUrl = '/kickoff/index/newproject-edit/'.$kickoffId;
		} else {
			$this->_view->unitUrl = '/kickoff/index/newproject/'.$kickoffId;
		}
		
		//Cost Centre
		$select = $sql->select();
		$select->from( 'WF_CostCentre' )
				->columns(array("CostCentreId", "CostCentreName"));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->costCentre = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//Phase Master
		$select = $sql->select();
		$select->from( 'KF_PhaseMaster' )
				->columns(array("PhaseId", "PhaseName"))
				->where('KickoffId = ' . $kickoffId);
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->phaseMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		//WBS Master
		$select = $sql->select();
		$select->from( 'KF_WBS' )
				->columns(array("WBSId", "WBSName"))
				->where(array('KickoffId' => $kickoffId, 'ParentId' => 0));
		$statement = $sql->getSqlStringForSqlObject( $select );
		$this->_view->kfWbs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		
		if (isset($kickoffId) && $kickoffId != 0) {			
			$select = $sql->select();
			$select->from(array('a' => 'WF_OperationalCostCentre'))
				->where('KickoffId = ' . $kickoffId);
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->kfSetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		}
		
		$this->_view->kickoffId = (isset($kickoffId) && $kickoffId != 0) ? $kickoffId : 0;

        $aVNo = CommonHelper::getVoucherNo(110, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];
		
		return $this->_view;
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
	
	public function getKickoffAction()
	{
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
				$enquiryId = $postParams['enquiryId'];
				
				$sql = new Sql($dbAdapter);
				$select = $sql->select();
				$select->from('KF_KickoffRegister')
					->columns(array('KickoffId', 'ProjectName'))
					->where(array('EnquiryId' => $enquiryId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			}
			$this->_view->setTerminal(true);
			$response = $this->getResponse()->setContent(json_encode($result));
			return $response;
		}
    }

    public function addCostCentreAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                 //print_r($postData);exit;
                $sql = new Sql($dbAdapter);
                if($postData['costCentre'] != '') {
                    $cityId = 0;
                    $cityName = $this->bsf->isNullCheck($postData['city'], 'string');
                    //checking in city master
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId'))
                        ->where("CityName='$cityName'")
                        ->limit(1);
                    $city_stmt = $sql->getSqlStringForSqlObject($select);
                    $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if (!empty($city)) {
                        $cityId = $city['CityId'];
                    }

                    $insert = $sql->insert();
                    $insert->into('WF_CostCentre');
                    $insert->Values(array('CostCentreName' => $this->bsf->isNullCheck($postData['costCentre'], 'string')
                    , 'CompanyId' => $this->bsf->isNullCheck($postData['companyId'], 'number')
                    , 'BranchId' => $this->bsf->isNullCheck($postData['branchId'], 'number')
                    , 'Address' => $this->bsf->isNullCheck($postData['address'], 'string')
                    , 'CityId' => $cityId
                    , 'Pincode' => $this->bsf->isNullCheck($postData['pinCode'], 'number')
                    , 'Phone' => $this->bsf->isNullCheck($postData['phone'], 'number')
                    , 'Fax' => $this->bsf->isNullCheck($postData['fax'], 'number')
                    , 'Mobile' => $this->bsf->isNullCheck($postData['mobile'], 'number')
                    , 'Email' => $this->bsf->isNullCheck($postData['email'], 'string')
                    , 'Website' => $this->bsf->isNullCheck($postData['web'], 'string')
                    , 'ContactPerson' => $this->bsf->isNullCheck($postData['contactPerson'], 'string')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //Cost Centre
                $select = $sql->select();
                $select->from( 'WF_CostCentre' )
                    ->columns(array("CostCentreId", "CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getlandprojectListAction(){
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
                $ienquiryId = $this->bsf->isNullCheck($this->params()->fromPost('enquiryId'), 'number');

                $select = $sql->select();
                $select->from('KF_KickoffRegister')
                    ->columns(array('data' => 'KickoffId', 'value' => 'ProjectName'))
                    ->where(array('EnquiryId'=>$ienquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    public function getlandconceptionListAction(){
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
                $ienquiryId = $this->bsf->isNullCheck($this->params()->fromPost('enquiryId'), 'number');

                $select = $sql->select();
                $select->from('Proj_LandConceptionRegister')
                    ->columns(array('data' => 'ConceptionId', 'value' => 'OptionName'))
                    ->where(array('EnquiryId'=>$ienquiryId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
}