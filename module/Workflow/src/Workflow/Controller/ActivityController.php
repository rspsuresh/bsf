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
use Zend\Session\Container;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class ActivityController extends AbstractActionController
{
	public function __construct()	{
		$this->bsf = new \BuildsuperfastClass();
		$this->auth = new AuthenticationService();
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
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter); 
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$returnString =  "";
				//Write your Ajax post code here
				$postParams = $request->getPost();
				$action = $this->bsf->isNullCheck($postParams['action'], 'string');
				if($action == 'edit') {
					$showhideval = isset($postParams['showhideval']) ? 1 : 0;
                    $id=$postParams['id'];
                    $subQuery = $sql->select();
                    $subQuery->from("WF_ActivityMaster")
                        ->columns(array('ParentId'))
                        ->where("ActivityId=$id");

                    $select = $sql->select();
                    $select->from('WF_ActivityMaster')
                        ->columns(array('ActivityName'))
                        ->where(array("ActivityName" =>$postParams['activityName']));
                    $select->where->expression('ParentId IN ?', array($subQuery));
                    $select->where("ActivityId<>$id");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $checkExist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($checkExist)==0) {
                        $update = $sql->update();
                        $update->table('WF_ActivityMaster')
                            ->set(array(
                                'ActivityName' => $postParams['activityName'],
                                'Hide' => $showhideval
                            ))
                            ->where(array('ActivityId' => $postParams['id']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $returnString="old";
                    }
				} else if($action == 'delete') {
					if($postParams['id'] != 1) {
						
						$delete = $sql->delete();
						$delete->from('WF_ActivityMaster')
							->where('ActivityId IN ('.$postParams['id'].')');
						$statement = $sql->getSqlStringForSqlObject($delete);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				} else if($action == 'add') {
					$showhideval = isset($postParams['showhideval']) ? 1 : 0;
                    $select = $sql->select();
                    $select->from('WF_ActivityMaster')
                        ->columns(array('ActivityName'))
                        ->where(array("ActivityName" =>$postParams['activityName'],'ParentId'=>$postParams['parentid']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $checkExist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($checkExist)==0) {
                        $insert = $sql->insert('WF_ActivityMaster')
                            ->values(array(
                                'ActivityName' => $postParams['activityName'],
                                'ParentId' => $postParams['parentid'],
                                'Hide' => $showhideval,
                                'CreatedDate' => date('Y-m-d H:i:s'),
                                'ModifiedDate' => date('Y-m-d H:i:s')
                            ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $returnString = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $returnString='old';
                    }
				} else if($action == 'drag') {
					$update = $sql->update();
					$update->table('WF_ActivityMaster')
						->set(array(
							'ParentId'  => $postParams['parentid']
						))
						->where(array('ActivityId'=>$postParams['id']));
					$statement = $sql->getSqlStringForSqlObject($update);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				} else if($action == 'addform') {
                    $imgPath = $viewRenderer->basePath().'/images/post-loader.gif';
					$returnString = '<form class="add_data" method="post" action="">
							<span class="close"><i class="fa fa-times"></i></span>
							<h5>Add Activity</h5>
							<input type="text" class="activityName" name="activityName" placeholder="">
							<!--input type="checkbox" name="showhideval" id="hide" />
							<label for="hide">Hide Child Nodes</label-->
                            <div id="remind_post_loader" class="post_loader activity_post_loader brad_50"><img src="'.$imgPath.'" alt="" title=""/></div>
							<input id="sub_button" type="submit" class="submit ripple" name="submit" value="Submit">
						</form>';
				} else if($action == 'editform') {
                    $imgPath = $viewRenderer->basePath().'/images/post-loader.gif';

                    $select = $sql->select();
					$select->from('WF_ActivityMaster')
						->where(array("ActivityId"=>$postParams['edit_ele_id']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$activityById = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$hide = $activityById['Hide'];
					$checked = $hide == 1 ? "checked" : ""; 
					
					$returnString = '<form class="edit_data" method="post" action="">
							<span class="close"><i class="fa fa-times"></i></span>
							<h5>Edit Activity</h5>
							<input type="text" class="activityName" name="activityName" value="'.$activityById['ActivityName'].'" placeholder="">
							<!--input type="checkbox" '.$checked.' value="'.$hide.'" name="showhideval" id="hide" />
							<label for="hide">Hide Child Nodes</label-->
                            <div class="post_loader activity_post_loader brad_50"><img src="'.$imgPath.'" alt="" title=""/></div>
							<input type="submit" class="edit ripple" name="submit" value="submit">
						</form>';
				} else if($action == 'addTask') {	
					$select = $sql->select();		
					$select->from('WF_TaskMaster');
					$statement = $sql->getSqlStringForSqlObject($select);
					$taskList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					$select = $sql->select();		
					$select->from('WF_ActivityTaskTrans')
					->where(array("ActivityId"=>$postParams['activityId']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$activityTaskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$activityTask = array();
					foreach($activityTaskResult as $activityTaskList) {
						$activityTask[] = $activityTaskList['TaskId'];
					}
					$returnString = '<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
									<h1 id="myModalLabel">Add Task</h1>
								</div>
								<div class="modal-body modal_body_min_h200">
									<div class="col-lg-10 col-lg-offset-1 col-md-10 col-md-offset-1 col-sm-10 col-sm-offset-1 padtop20">
										<form class="form-horizontal" id="modalAddForm">
											<div class="row m_btm30">
												<div class="form-group col-lg-12">
													<select class="multiple_dropdown lbl_move" name="addTask[]" id="select_task" multiple="multiple" style="width:100%;" label="Select Task">';
														foreach($taskList as $task) {
															$returnString .= '<option value="'.$task['TaskId'].'" ';
															if(in_array($task['TaskId'], $activityTask)) { $returnString .= 'selected'; }
															$returnString .= '>'.$task['TaskName'].'</option>';	
														}
								$returnString .= '</select>
													<div class="pull-right">
														<div class="radio_check">
															<p>
															   <input type="checkbox" value="1" id="select_all_task"/>
															   <label for="select_all_task" class="ripple">Select all</label>
														   </p>
														</div>
													</div>
												<input type="hidden" name="hidActivityId" value="'.$postParams['activityId'].'" />
												<input type="hidden" name="action" value="taskAdding" />
												</div>    
											</div>      
										</form>
									</div>
								</div>
								<div class="modal-footer clear">
									<div class="col-lg-12 savebtn_area no_border">
										<ul>
											<li class="save_btn float_r"><a href="javascript:void(0);" onclick="return submitModal();" class="ripple">Save</a></li>
											<li class="cancel_btn float_r"><a href="javascript:void(0);" data-dismiss="modal" class="ripple">Close</a></li>
										</ul>
									</div>
								</div>';
				} else if($action == 'taskAdding') {
					$connection = $dbAdapter->getDriver()->getConnection();
					$connection->beginTransaction();
					try {
						$delete = $sql->delete();		
						$delete->from('WF_ActivityTaskTrans')
							->where(array("ActivityId"=>$postParams['hidActivityId']));
						$statement = $sql->getSqlStringForSqlObject($delete);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						//irfan Added
						$delete = $sql->delete();		
						$delete->from('WF_ActivityCriticalTrans')
							->where(array("ActivityId"=>$postParams['hidActivityId']));
						$statement = $sql->getSqlStringForSqlObject($delete);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						//
						
						foreach($postParams['addTask'] as $addTask) {
							$insert = $sql->insert('WF_ActivityTaskTrans')
								->values(array(
									'ActivityId' => $postParams['hidActivityId'],
									'TaskId' => $addTask
								));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							//irfan Added
							$taskId=$addTask;
							$activityId=$postParams['hidActivityId'];
							$subQuery = $sql->select();
							$subQuery->from("WF_TaskMaster")
								->columns(array('TaskName'))
								->where("TaskId='$taskId'");
														
							$select = $sql->select();
							$select->from(array("a"=>"WF_TaskTrans"))
								->columns( array( 'ActivityId' => new Expression("'$activityId'"), 'RoleId','OrderId'  => new Expression("ROW_NUMBER() OVER (ORDER BY RoleId)") ) )
								->where("TaskType='C'");
							$select->where->expression('a.TaskName IN ?', array($subQuery));
							
							$insert = $sql->insert();
							$insert->into( 'WF_ActivityCriticalTrans' );
							$insert->columns(array('ActivityId', 'RoleId', 'OrderId'));
							$insert->Values( $select );
							$statement = $sql->getSqlStringForSqlObject( $insert );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							//
						}
						$connection->commit();
						$returnString = 'success';
					} catch(PDOException $e){
						$connection->rollback();
						$returnString = 'Internal Server Error';
					}
				} else if($action == 'addRole') {	
					$select = $sql->select();		
					$select->from('WF_TaskTrans')
						->where("TaskName IN (select TaskName from WF_TaskMaster where TaskId IN (select TaskId from WF_ActivityTaskTrans where ActivityId = '".$postParams['activityId']."'))");
					$statement = $sql->getSqlStringForSqlObject($select);
					$roleList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					$select = $sql->select();		
					$select->from('WF_ActivityRoleTrans')
					->where(array("ActivityId"=>$postParams['activityId']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$activityRoleResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$activityRole = array();
					foreach($activityRoleResult as $activityRoleList) {
						$activityRole[] = $activityRoleList['RoleId'];
					}
					$returnString = '<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
									<h1 id="myModalLabel">Add Role</h1>
								</div>
								<div class="modal-body modal_body_min_h200">
									<div class="col-lg-10 col-lg-offset-1 col-md-10 col-md-offset-1 col-sm-10 col-sm-offset-1 padtop20">
										<form class="form-horizontal" id="modalAddForm">
											<div class="row m_btm30">
												<div class="form-group col-lg-12">
													<select class="multiple_dropdown lbl_move" name="addRole[]" id="select_role" multiple="multiple" style="width:100%;" label="Select Role">';
														foreach($roleList as $role) {
															$returnString .= '<option value="'.$role['RoleId'].'" ';
															if(in_array($role['RoleId'], $activityRole)) { $returnString .= 'selected'; }
															$returnString .= '>'.$role['RoleName'].'</option>';	
														}
								$returnString .= '</select>
													<div class="pull-right">
														<div class="radio_check">
															<p>
															   <input type="checkbox" value="1" id="select_all_role"/>
															   <label for="select_all_role" class="ripple">Select all</label>
														   </p>
														</div>
													</div>
												<input type="hidden" name="hidActivityId" value="'.$postParams['activityId'].'" />
												<input type="hidden" name="action" value="roleAdding" />
												</div>    
											</div>      
										</form>
									</div>
								</div>
								<div class="modal-footer clear">
									<div class="col-lg-12 savebtn_area no_border">
										<ul>
											<li class="save_btn float_r"><a href="javascript:void(0);" onclick="return submitModal();" class="ripple">Save</a></li>
											<li class="cancel_btn float_r"><a href="javascript:void(0);" data-dismiss="modal" class="ripple">Close</a></li>
										</ul>
									</div>
								</div>';
				} else if($action == 'roleAdding') {
					$connection = $dbAdapter->getDriver()->getConnection();
					$connection->beginTransaction();
					try {
						$delete = $sql->delete();		
						$delete->from('WF_ActivityRoleTrans')
							->where(array("ActivityId"=>$postParams['hidActivityId']));
						$statement = $sql->getSqlStringForSqlObject($delete);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						foreach($postParams['addRole'] as $addRole) {
							$insert = $sql->insert('WF_ActivityRoleTrans')
								->values(array(
									'ActivityId' => $postParams['hidActivityId'],
									'RoleId' => $addRole
								));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
						$connection->commit();
						$returnString = 'success';
					} catch(PDOException $e){
						$connection->rollback();
						$returnString = 'Internal Server Error';
					}
				} else if($action == 'assignUser') {	
					$select = $sql->select();		
					$select->from('WF_Users');
					$statement = $sql->getSqlStringForSqlObject($select);
					$userList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					$select = $sql->select();		
					$select->from('WF_UserActivityTrans')
					->where(array("ActivityId"=>$postParams['activityId']));
					$statement = $sql->getSqlStringForSqlObject($select);
					$activityUserResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$activityUser = array();
					foreach($activityUserResult as $activityUserList) {
						$activityUser[] = $activityUserList['UserId'];
					}
					
					$returnString = '<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
									<h1 id="myModalLabel">Assign User</h1>
								</div>
								<div class="modal-body modal_body_min_h200">
									<div class="col-lg-10 col-lg-offset-1 col-md-10 col-md-offset-1 col-sm-10 col-sm-offset-1 padtop20">
										<form class="form-horizontal" id="modalAddForm">
											<div class="row m_btm30">
												<div class="form-group col-lg-12">
													<select class="multiple_dropdown lbl_move" multiple="multiple" id="select_user" name="assignUser[]" style="width:100%;" label="Select Users">';
														foreach($userList as $user) {
															$returnString .= '<option value="'.$user['UserId'].'" ';
															if(in_array($user['UserId'], $activityUser)) { $returnString .= 'selected'; }
															$returnString .= '>'.$user['EmployeeName'].'</option>';	
														}
								                $returnString .= '</select>
													<div class="pull-right">
														<div class="radio_check">
															<p>
															   <input type="checkbox" value="1" id="select_all_user"/>
															   <label for="select_all_user" class="ripple">Select all</label>
														   </p>
														</div>
													</div>
												<input type="hidden" name="hidActivityId" value="'.$postParams['activityId'].'" />
												<input type="hidden" name="action" value="userAdding" />
												</div>    
											</div>      
										</form>
									</div>
								</div>
								<div class="modal-footer clear">
									<div class="col-lg-12 savebtn_area no_border">
										<ul>
											<li class="save_btn float_r"><a href="javascript:void(0);" onclick="return submitModal();" class="ripple">Save</a></li>
											<li class="cancel_btn float_r"><a href="javascript:void(0);" data-dismiss="modal" class="ripple">Close</a></li>
										</ul>
									</div>
								</div>';
				} else if($action == 'userAdding') {
					$connection = $dbAdapter->getDriver()->getConnection();
					$connection->beginTransaction();
					try {
						foreach($postParams['assignUser'] as $assignUser) {
							$delete = $sql->delete();		
							$delete->from('WF_UserActivityTrans')
								->where(array("ActivityId"=>$postParams['hidActivityId']))
								->where(array("UserId"=>$assignUser));
							$statement = $sql->getSqlStringForSqlObject($delete);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$insert = $sql->insert('WF_UserActivityTrans')
								->values(array(
									'ActivityId' => $postParams['hidActivityId'],
									'UserId' => $assignUser
								));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
						$connection->commit();
						$returnString = 'success';
					} catch(PDOException $e){
						$connection->rollback();
						$returnString = 'Internal Server Error';
					}
				} 
				
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($returnString);
				return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}
			
			$select = $sql->select();
			$select->from('WF_ActivityMaster');
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->actityMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$store_all_id = array();
			foreach($this->_view->actityMaster as $actityMaster) {
				array_push($store_all_id, $actityMaster['ParentId']);
			}
			$this->_view->store_all_id = $store_all_id;
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}