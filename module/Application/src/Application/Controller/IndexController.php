<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

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

class IndexController extends AbstractActionController
{
    public function __construct()	{
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
        $this->_view->messages = $this->flashMessenger()->getMessages();
    }

    public function indexAction()	{

        /*echo $bsf->currentDateTime();
        echo $bsf::PERPAGE;
        echo '<pre>'; print_r($bsf->getCategories());*/
        if($this->auth->hasIdentity()) {
            $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
        }
        $this->layout('layout/login');

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            $this->_process($postParams);
        }
        return $this->_view;
    }

    public function homeAction() {
        if(!$this->auth->hasIdentity()) {
            //$this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
        }

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        //$dbAdapter = $viewRenderer->openDatabase($config['db_details']['workflow']);

        $sql     = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserName'))
            ->where->like('UserId', '%1%');
        $statement = $sql->getSqlStringForSqlObject($select);
        $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->results = $results;

        return $this->_view;
    }

    // Start Login Process
    protected function _process($values, $encript = false)	{
        //Get our authentication adapter and check credentials
        if($values['password']==" ") {
            $pass = $values['password'];
        } else {
            $pass = $this->bsf->isNullCheck(CommonHelper::encodeString($values['password']),'string');
        }
        $adapter = $this->_getAuthAdapter($encript);
        $adapter->setIdentity($values['userName']);
        $adapter->setCredential($pass);

        $this->auth = new AuthenticationService();
        $result = $this->auth->authenticate($adapter);
        switch ($result->getCode()) {
            case Result::FAILURE_IDENTITY_NOT_FOUND:
                // do stuff for nonexistent identity
                $this->flashMessenger()->addMessage(array('error' => 'User Name does not Exist'));
                $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
                break;

            case Result::FAILURE_CREDENTIAL_INVALID:
                // do stuff for invalid credential
                $this->flashMessenger()->addMessage(array('error' => 'User Name / Password incorrect'));
                $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
                break;

            case Result::SUCCESS:
                $storage = $this->auth->getStorage();
                $storage->write($adapter->getResultRowObject(null,'usr_password'));
                $time = 1209600; // 14 days 1209600/3600 = 336 hours => 336/24 = 14 days
                //if ($data['rememberme']) $storage->getSession()->getManager()->rememberMe($time); // no way to get the session
                if ($values['rememberme']) {
                    $sessionManager = new \Zend\Session\SessionManager();
                    $sessionManager->rememberMe($time);
                }

                $userData = (array)$adapter->getResultRowObject(null,'usr_password');
                $userId = $userData['UserId'];
                if($this->auth->getIdentity()->Lock==1) {
                    $this->flashMessenger()->addMessage(array('error' => 'Your Account was Deactivated ..!'));
                    $this->auth->clearIdentity();
                    $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));

                } else {
                    /*$config = $this->getServiceLocator()->get('config');
               $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
               $connection = $dbAdapter->getDriver()->getConnection();
               try {
                   $connection->beginTransaction();*/
                    CommonHelper::insertLog(date('Y-m-d H:i:s'), 'Login', 'S', 'Login', 0, 0, 0, 'WF', '', $userId, 0, 0);
                    //$dbAdapter = $viewRenderer->openDatabase($config['db_details']['workflow']);

                    //teleCaller
                    $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
                    $sql     = new Sql($dbAdapter);

                    $update = $sql->update();
                    $update->table( 'WF_Users' )
                        ->set( array( 'TeleCalling' => 1 ,'CallBusy'=>0))
                        ->where(array("UserId"=>$userId));
                    $statement = $sql->getSqlStringForSqlObject( $update );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    $this->auth->getIdentity()->TeleCalling=1;
                    /*$connection->commit();

                    } catch (PDOException $e) {
                        $connection->rollback();
                    }*/

                    $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
                }
                break;

            default:
                // do stuff for other failure
                break;
        }
        return false;
    }
    // End Login Process

    // Start Auth Process for auth session
    protected function _getAuthAdapter($encript)	{
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $authAdapter = new AuthAdapter($dbAdapter,
            'WF_Users', // there is a method setTableName to do the same
            'UserName', // there is a method setIdentityColumn to do the same
            'Password' // there is a method setCredentialColumn to do the same
        );
        return $authAdapter;
    }
    // End Auth Process for auth session

    //Start logout action//
    public function logoutAction()	{
        $this->auth = new AuthenticationService();

        if ($this->auth->hasIdentity()) {
            $identity = $this->auth->getIdentity();
            $userId = $this->bsf->isNullCheck($this->auth->getIdentity()->UserId,'number');

            //$config = $this->getServiceLocator()->get('config');
            //$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            //$connection = $dbAdapter->getDriver()->getConnection();

            //try {
            //$connection->beginTransaction();
            $companySession = new Container('faCompany');
            $companySession->fiscalId=0;
            $companySession->companyId=0;

            CommonHelper::insertLog(date('Y-m-d H:i:s'), 'Logout', 'S', 'Logout', 0, 0, 0, 'WF', '', $userId, 0, 0);

            //Tele-Caller
            $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
            $sql     = new Sql($dbAdapter);

            $update = $sql->update();
            $update->table( 'WF_Users' )
                ->set( array( 'TeleCalling' => 0 ))
                ->where(array("UserId"=>$userId));
            $statement = $sql->getSqlStringForSqlObject( $update );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //$connection->commit();
            //} catch (PDOException $e) {
            //	$connection->rollback();
            //}
        }

        $this->auth->clearIdentity();
        return $this->redirect()->toRoute('application/default', array('controller' => 'index', 'action' => 'index'));
    }
    //End logout action//

    // Start Auth Process for auth session
    protected function _encodeString($string)
    {
        $key = "This is the encryption .";
        $cipher_alg = MCRYPT_RIJNDAEL_256;
        $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher_alg,MCRYPT_MODE_ECB), MCRYPT_RAND);
        $string = mcrypt_encrypt($cipher_alg, $key, $string, MCRYPT_MODE_ECB, $iv);
        $encrypted_string = base64_encode($string);
        return trim($encrypted_string);
    }
    // End Auth Process for auth session

    // Start Auth Process for auth session
    protected function _decodeString($string)
    {
        $key = "This is the encryption .";
        $cipher_alg = MCRYPT_RIJNDAEL_256;
        $string = base64_decode($string);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher_alg,MCRYPT_MODE_ECB), MCRYPT_RAND);
        $decrypted_string = mcrypt_decrypt($cipher_alg, $key, $string, MCRYPT_MODE_ECB, $iv);
        return trim($decrypted_string);
    }
    // End Auth Process for auth session

    //Start activity stream action//
    public function activityStreamAction() {
        $this->layout('layout/activity');
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter 	  = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql          = new Sql($dbAdapter);
        $userId       = $this->auth->getIdentity()->UserId;
        $Ename        = $this->auth->getIdentity()->EmployeeName;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $mode = $this->bsf->isNullCheck($postParams['mode'], 'string');
                if($mode!=""){
                    $status = "failed";
                    $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $response = $this->getResponse();
                    try {
                        if($mode=='like') {
                            $likePos = $this->bsf->isNullCheck($postParams['LikePos'], 'number');
                            $likeFeedId = $this->bsf->isNullCheck($postParams['FeedId'], 'number');
                            $curUserId = $this->bsf->isNullCheck($postParams['UserId'], 'number');

                            if($likePos==0) {
                                $insert  = $sql->insert('WF_Likes');
                                $newData = array(
                                    'UserId'   =>$curUserId,
                                    'FeedId' =>$likeFeedId,
                                    'CreatedDate'=>date('Y-m-d H:i:s')
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $likeId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                $insert  = $sql->insert('WF_AppNotification');
                                $newData = array(
                                    'UserId'   => $curUserId,
                                    'FeedId' => $likeFeedId,
                                    'LikeId' => $likeId,
                                    'NotificationType' =>'like',
                                    'CreatedDate'=>date('Y-m-d H:i:s')
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $status="1";
                            } else {
                                $delete = $sql->delete();
                                $delete->from('WF_Likes')
                                    ->where(array('FeedId'=>$likeFeedId,'UserId'=>$userId));
                                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $status="0";
                            }
                        } else if($mode=='setUser') {

                            $FromId=$this->auth->getIdentity()->UserId;
                            $ToId=$postParams['id'];
                            $select = $sql->select();
                            $select->from(array('a'=>'WF_Chats'))
                                ->join(array("b" => "WF_Users"), "a.FromId=b.UserId", array("UserId","EmployeeName"=>new Expression("case when a.FromId=".$FromId." then 'Me' else b.EmployeeName end")), $select::JOIN_LEFT)
                                ->columns(array("Message","ChatId"));
                            $select->where("(a.ToId=$ToId AND a.FromId=$FromId) OR (a.ToId=$FromId AND a.FromId=$ToId)");
                            $select->order("a.ChatId Desc");
                            $select->limit(100);

                            $resSelect = $sql->select();
                            $resSelect->from(array('g'=>$select))
                                ->columns(array("UserId","EmployeeName","Message","ChatId"));
                            $resSelect->order("ChatId Asc");
                            $resStatement = $sql->getSqlStringForSqlObject($resSelect);
                            $res = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a'=>'WF_Users'))
                                ->columns(array("UserLogo","UserId","EmployeeName","LogFlag"=>new expression("(select distinct(b.UserId) from WF_LogStatus as b where a.UserId=b.UserId )")));
                            $select->where(array("a.UserId"=>$ToId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $rDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                            $result=array("msg"=>$res,"rDetail"=>$rDetail);
                            $status=json_encode($result);
                        } else if($mode=='insert') {

                            $insert = $sql->insert('WF_Chats');
                            $insert->values(array(
                                'FromId'  => $this->auth->getIdentity()->UserId,
                                'ToId'  =>$this->bsf->isNullCheck($postParams['id'],'number'),
                                'Message' => $this->bsf->isNullCheck($postParams['msg'],'string'),
                                'ModifiedDate' => date('Y-m-d H:i:s')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $status=$this->auth->getIdentity()->EmployeeName;
                        } else if($mode=='chatRefresh') {
                            $status=array();
                            $uId=$this->auth->getIdentity()->UserId;
                            $select = $sql->select();
                            $select->from(array('a' =>'WF_Users'))
                                ->columns(array("EmployeeName","UserLogo","UserId","LogFlag"=>new expression("(select distinct(b.UserId) from WF_LogStatus as b where a.UserId=b.UserId )")))
                                ->where(array("a.DeleteFlag"=>'0',"a.Lock"=>'0'))
                                ->where->notEqualTo('a.UserId', $uId);

                            $resSelect = $sql->select();
                            $resSelect->from(array('g'=>$select))
                                ->columns(array("UserId","EmployeeName","UserLogo","LogFlag"));
                            $resSelect->order("LogFlag desc");
                            $resStatement = $sql->getSqlStringForSqlObject($resSelect);
                            $result = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $status=json_encode($result);
                        } else if($postParams['mode']=='telecall') {

                            $update = $sql->update();
                            $update->table( 'WF_Users' )
                                ->set( array( 'TeleCalling' => $postParams['teleVal'],'CallBusy'=>0 ))
                                ->where(array("UserId"=>$this->auth->getIdentity()->UserId));
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $this->auth->getIdentity()->TeleCalling=$postParams['teleVal'];
                            $status=$postParams['teleVal'];
                        } else if($mode=='comment') {
                            $commentText = $this->bsf->isNullCheck($postParams['commentTxt'], 'string');
                            $commentFeedId = $this->bsf->isNullCheck($postParams['FeedId'], 'number');
                            $curUserId = $this->bsf->isNullCheck($postParams['UserId'], 'number');
                            $UserLogo = $this->auth->getIdentity()->UserLogo;

                            $insert  = $sql->insert('WF_Comments');
                            $newData = array(
                                'UserId'   =>$curUserId,
                                'FeedId' =>$commentFeedId,
                                'Comments' =>$commentText,
                                'CreatedDate'=>date('Y-m-d H:i:s')
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $commentId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $insert  = $sql->insert('WF_AppNotification');
                            $newData = array(
                                'UserId'   => $curUserId,
                                'FeedId' => $commentFeedId,
                                'CommentId' => $commentId,
                                'NotificationType' => 'comment',
                                'CreatedDate'=>date('Y-m-d H:i:s')
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $cmtSDate = $this->bsf->timeAgo(date('Y-m-d H:i:s'));

                            $status= '<li>
										<div class="cmt_usrpp">
											<div><img src="'.$viewRenderer->basePath().'/'.$UserLogo.'"></div>
										</div>
										<div class="cmt_news">
											<div>'.$Ename.'<span><i class="fa fa-clock-o"></i>'.$cmtSDate.'</span></div>
												<p>'.$commentText.'</p>
										</div>
									  </li>';
                        } else if($mode=='searchmodule') {
                            $mType = $this->bsf->isNullCheck($postParams['mType'], 'string');
                            $searchVal = $this->bsf->isNullCheck($postParams['searchVal'], 'string');

                            $select = $sql->select();
                            $select->from(array('a' => 'WF_LogMaster'))
                                ->columns(array('Count' => new Expression('COUNT(*)'), 'ModuleName' => new Expression("UPPER(b.DBName) + ':' + a.RoleName"), 'Url' => new Expression("'/' + c.Mpath + '/' + c.Controller + '/' + c.Action")))
                                ->join(array('b' => 'WF_LogTrans'), 'a.LogId=b.LogId', array(), $select::JOIN_INNER)
                                ->join(array('c' => 'WF_TaskTrans'), 'a.RoleName=c.RoleName', array(), $select::JOIN_INNER);
                            if ($searchVal != "") {
                                $select->where("b.DBName+a.RoleName LIKE '%" . $searchVal . "%'");
                            }
                            $select->where(array("c.RoleType" => $mType));
                            $select->group(new expression('a.RoleName,b.DBName ,c.MPath,c.Controller,c.Action'));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $status = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $status = json_encode($status);

                        }  else if($mode=='likeUserList') {

                            $feedId = $this->bsf->isNullCheck($postParams['feedId'], 'number');
                            $select = $sql->select();
                            $select->from(array('a' =>'WF_Likes'))
                                ->columns(array())
                                ->join(array('b' => 'WF_Users'), 'a.UserId=b.UserId', array('UserId','EmployeeName'), $select::JOIN_LEFT)
                                ->where("a.FeedId='$feedId'")
                                ->order('a.CreatedDate Desc');
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $status = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

                        } else if($mode=='AppNotiCount') {
                            $select = $sql->select();
                            $select->from(array('a' =>'WF_AppNotification'))
                                ->columns(array('*'))
                                ->join(array('b' => 'WF_Feeds'), 'a.FeedId=b.FeedId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array(), $select::JOIN_LEFT)
                                ->where("b.UserId=$userId and c.UserId!=$userId and a.DeleteFlag='0' and a.NotificationView='0'");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $status = count($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

                        } else if($mode=='appNotiDel') {
                            $notificationId = $this->bsf->isNullCheck($postParams['nId'], 'number');


                            $update = $sql->update();
                            $update->table('WF_AppNotification');
                            $update->set(array(
                                'DeleteFlag'  => 1,
                            ));
                            $update->where(array('NotificationId'=>$notificationId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $status="success";


                        } else if($mode=='CRM') {
                            $logId = $this->bsf->isNullCheck($postParams['LogId'], 'number');
                            $select = $sql->select();
                            $select->from(array('a' => 'WF_PendingWorks'))
                                ->columns(array('TransId', 'PendingRole', 'RoleType', 'LogId', 'NonTask', 'RefNo' => New Expression("c.RefNo"), 'RoleName' => New Expression("b.RoleName")
                                , 'Remarks' => New Expression("b.LogDescription"), 'DBName' => New Expression("c.DBName"), 'RegisterId' => New Expression("c.RegisterId")
                                , 'CostCentreId' => New Expression("c.CostCentreId"), 'CompanyId' => New Expression("c.CompanyId")))
                                ->join(array('b' => 'WF_LogMaster'), 'a.LogId=b.LogId', array(), $select::JOIN_INNER)
                                ->join(array('c' => 'WF_LogTrans'), 'b.LogId=c.LogId', array(), $select::JOIN_INNER);
                            $select->where("a.LogId=$logId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            if (count($resData) > 0) {

                                $sRoleName = $this->bsf->isNullCheck($resData['RoleName'], 'string');
                                $iRegId = $this->bsf->isNullCheck($resData['RegisterId'], 'number');

                            }


                            if ($sRoleName == 'Finalization-Add') {

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_UnitBooking'))
                                    ->join(array('b' => 'KF_UnitMaster'), 'a.unitId=b.UnitId', array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                                    ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                                    ->join(array('d' => 'KF_BlockMaster'), 'b.BlockId=d.BlockId', array('BlockName'), $select::JOIN_INNER)
                                    ->join(array('e' => 'KF_FloorMaster'), 'b.FloorId=e.FloorId', array('FloorName'), $select::JOIN_INNER)
                                    ->join(array('h' => 'Crm_Leads'), 'a.LeadId=h.LeadId', array('*'), $select::JOIN_INNER)
                                    ->join(array('g' => 'KF_UnitTypeMaster'), 'b.ProjectId=g.ProjectId', array('UnitTypeName'), $select::JOIN_INNER);
                                $select->where("a.BookingId=$iRegId");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array("totalunt"=>new Expression('Count(UnitId)')));
                                $select->where(array('Status'=>'U'));
                                $select->where(array('ProjectId'=>$status['ProjectId']));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status['totaltxt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                                    ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                                    ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                                    ->join(array("h" => "Crm_ProgressBillTrans"), NEW Expression("a.StageId=h.StageId and a.UnitId=h.UnitId and h.CancelId=0"), array('BillDate' => new Expression("FORMAT(h.BillDate, 'dd-MM-yyyy')"), 'QualAmount', 'PBNo', 'Amount', 'BillAmount' => new Expression("h.QualAmount + h.Amount"), 'PaidAmount', 'Balance' => new Expression('Case When (isnull(a.NetAmount,0) > isnull(h.PaidAmount,0) )   then (isnull(a.NetAmount,0) - isnull(h.PaidAmount,0)) else (isnull(h.PaidAmount,0)- isnull(a.NetAmount,0)) end')), $select::JOIN_LEFT)
                                    ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName end as StageName")
                                    , "PaymentScheduleUnitTransId", "StageId", 'NetAmount', "StageType"))
                                    ->where(array('a.UnitId' => $status['UnitId']));
                                  $select->where("a.StageType<>'A'");
                                $select->order("SortId asc");
                               $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['arrBuyerStmt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array('AdvAmount'), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), NEW Expression("a.UnitId=c.UnitId and c.ReceiptAgainst='A'and c.CancelId=0 and c.DeleteFlag= 0 "), array('sumAmount' => new expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                    ->columns(array('NetAmount'))
                                    ->where(array('a.UnitId' => $status['UnitId'], 'a.StageType' => 'A'))
                                    ->group(new expression('a.NetAmount,b.AdvAmount'));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['advamt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $status['type']='final';
                            }
                            else if($sRoleName =='Stage-Completion-Add'){

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_StageCompletion'))
                                    ->join(array("c" => "Proj_ProjectMaster"),"a.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                                    ->join(array("d" => "KF_BlockMaster"),"a.BlockId=d.BlockId", array('BlockName'), $select::JOIN_INNER)
                                    ->join(array("e" => "KF_FloorMaster"), "a.FloorId=e.FloorId", array('FloorName'), $select::JOIN_INNER)
                                    ->where(array('a.StageCompletionId' =>$iRegId));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_StageImageTrans'))
                                    ->where(array('a.StageCompletionId' =>$iRegId));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['image'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                $status['type']='stagecompl';
                            }
                            else  if($sRoleName =='Unit-Transfer-Add'){
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_UnitTransfer'))
                                    ->columns(array('OldUnitId','NewUnitId'))
                                    ->where(array("a.TransferId"=>$iRegId));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $transfer = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_UnitBooking'))
                                    ->join(array('b' => 'KF_UnitMaster'), 'a.unitId=b.UnitId', array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                                    ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                                    ->join(array('d' => 'KF_BlockMaster'), 'b.BlockId=d.BlockId', array('BlockName'), $select::JOIN_INNER)
                                    ->join(array('e' => 'KF_FloorMaster'), 'b.FloorId=e.FloorId', array('FloorName'), $select::JOIN_INNER)
                                    ->join(array('h' => 'Crm_Leads'), 'a.LeadId=h.LeadId', array('*'), $select::JOIN_INNER)
                                    ->join(array('g' => 'KF_UnitTypeMaster'), 'b.ProjectId=g.ProjectId', array('UnitTypeName'), $select::JOIN_INNER);
                                $select->where(array("a.UnitId"=>$transfer['OldUnitId']));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array("totalunt"=>new Expression('Count(UnitId)')));
                                $select->where(array('Status'=>'U'));
                                $select->where(array('ProjectId'=>$status['ProjectId']));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status['totaltxt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                                    ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                                    ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                                    ->join(array("h" => "Crm_ProgressBillTrans"), NEW Expression("a.StageId=h.StageId and a.UnitId=h.UnitId and h.CancelId=0"), array('BillDate' => new Expression("FORMAT(h.BillDate, 'dd-MM-yyyy')"), 'QualAmount', 'PBNo', 'Amount', 'BillAmount' => new Expression("h.QualAmount + h.Amount"), 'PaidAmount', 'Balance' => new Expression('Case When (isnull(a.NetAmount,0) > isnull(h.PaidAmount,0) )   then (isnull(a.NetAmount,0) - isnull(h.PaidAmount,0)) else (isnull(h.PaidAmount,0)- isnull(a.NetAmount,0)) end')), $select::JOIN_LEFT)
                                    ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName end as StageName")
                                    , "PaymentScheduleUnitTransId", "StageId", 'NetAmount', "StageType"))
                                    ->where(array('a.UnitId' => $transfer['OldUnitId']));
                                  $select->where("a.StageType<>'A'");
                                $select->order("SortId asc");
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['oldarrBuyerStmt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array('AdvAmount'), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), NEW Expression("a.UnitId=c.UnitId and c.ReceiptAgainst='A'and c.CancelId=0 and c.DeleteFlag= 0 "), array('sumAmount' => new expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                    ->columns(array('NetAmount'))
                                    ->where(array('a.UnitId' => $transfer['OldUnitId'], 'a.StageType' => 'A'))
                                    ->group(new expression('a.NetAmount,b.AdvAmount'));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['oldadvamt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_UnitBooking'))
                                    ->join(array('b' => 'KF_UnitMaster'), 'a.unitId=b.UnitId', array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                                    ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                                    ->join(array('d' => 'KF_BlockMaster'), 'b.BlockId=d.BlockId', array('BlockName'), $select::JOIN_INNER)
                                    ->join(array('e' => 'KF_FloorMaster'), 'b.FloorId=e.FloorId', array('FloorName'), $select::JOIN_INNER)
                                    ->join(array('h' => 'Crm_Leads'), 'a.LeadId=h.LeadId', array('*'), $select::JOIN_INNER)
                                    ->join(array('g' => 'KF_UnitTypeMaster'), 'b.ProjectId=g.ProjectId', array('UnitTypeName'), $select::JOIN_INNER);
                                $select->where(array("a.UnitId"=>$transfer['NewUnitId']));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status['new'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                                    ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                                    ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                                    ->join(array("h" => "Crm_ProgressBillTrans"), NEW Expression("a.StageId=h.StageId and a.UnitId=h.UnitId and h.CancelId=0"), array('BillDate' => new Expression("FORMAT(h.BillDate, 'dd-MM-yyyy')"), 'QualAmount', 'PBNo', 'Amount', 'BillAmount' => new Expression("h.QualAmount + h.Amount"), 'PaidAmount', 'Balance' => new Expression('Case When (isnull(a.NetAmount,0) > isnull(h.PaidAmount,0) )   then (isnull(a.NetAmount,0) - isnull(h.PaidAmount,0)) else (isnull(h.PaidAmount,0)- isnull(a.NetAmount,0)) end')), $select::JOIN_LEFT)
                                    ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName end as StageName")
                                    , "PaymentScheduleUnitTransId", "StageId", 'NetAmount', "StageType"))
                                    ->where(array('a.UnitId' => $transfer['NewUnitId']));
                                  $select->where("a.StageType<>'A'");
                                $select->order("SortId asc");
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['newarrBuyerStmt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array('AdvAmount'), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), NEW Expression("a.UnitId=c.UnitId and c.ReceiptAgainst='A'and c.CancelId=0 and c.DeleteFlag= 0 "), array('sumAmount' => new expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                    ->columns(array('NetAmount'))
                                    ->where(array('a.UnitId' => $transfer['NewUnitId'], 'a.StageType' => 'A'))
                                    ->group(new expression('a.NetAmount,b.AdvAmount'));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['newadvamt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                                $status['type']='transfer';


                            }
                            else if($sRoleName =='Unit-Cancellation-Add'||$sRoleName =='Unit-Cancellation-Edit'){

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_UnitCancellation'))
                                    ->columns(array('Type','UnitId','Remarks'))
                                    ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId',array('ProjectId'), $select::JOIN_LEFT)
                                    ->where(array("a.CancellationId"=>$iRegId));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                                // unit details//
                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array('UnitId','UnitArea','UnitNo'))
                                    ->join(array('b' => 'KF_UnitTypeMaster'), 'b.UnitTypeId=a.UnitTypeId',array('UnitTypeName'), $select::JOIN_LEFT)
                                    ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array("FloorName"), $select::JOIN_LEFT)
                                    ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT);
                                if($status['Type']=='S') {
                                    $select->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId"), array('Rate', 'BookingId', 'Discount'), $select::JOIN_LEFT);
                                    $select->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array( 'LeadName','Mobile','Email'), $select::JOIN_LEFT);
                                    $select->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId',  'Discount'=> 'PostDiscount','Rate'), $select::JOIN_LEFT);
                                    $select   ->order("o.PostSaleDiscountId desc");
                                }
                                if($status['Type']=='B') {
                                    $select  -> join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId"), array('BlockId','AdvAmnt','Rate'=>'BRate','Discount'), $select::JOIN_LEFT);
                                    $select ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('LeadName','Mobile','Email'), $select::JOIN_LEFT);
                                }
                                if($status['Type']=='P') {
                                    $select  -> join(array('x' => 'Crm_UnitPreBooking'), new Expression("a.UnitId=x.UnitId"), array('PreBookingId',  'AdvAmount', 'Rate' => 'PRate', 'Discount'), $select::JOIN_LEFT);
                                    $select ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('LeadName','Mobile','Email'), $select::JOIN_LEFT);
                                }
                                $select->join(array("l"=>"Proj_ProjectMaster"), "l.ProjectId=a.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                                    ->where(array("a.UnitId"=>$status['UnitId']));

                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $status['unitInfo'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array("totalunt"=>new Expression('Count(UnitId)')));
                                $select->where(array('Status'=>'U'));
                                $select->where(array('ProjectId'=>$status['ProjectId']));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $status['totaltxt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if($status['Type']=='S') {

                                    $select = $sql->select();
                                    $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                        ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                                        ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                                        ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                                        ->join(array("h" => "Crm_ProgressBillTrans"), NEW Expression("a.StageId=h.StageId and a.UnitId=h.UnitId and h.CancelId=0"), array('BillDate' => new Expression("FORMAT(h.BillDate, 'dd-MM-yyyy')"), 'QualAmount', 'PBNo', 'Amount', 'BillAmount' => new Expression("h.QualAmount + h.Amount"), 'PaidAmount', 'Balance' => new Expression('Case When (isnull(a.NetAmount,0) > isnull(h.PaidAmount,0) )   then (isnull(a.NetAmount,0) - isnull(h.PaidAmount,0)) else (isnull(h.PaidAmount,0)- isnull(a.NetAmount,0)) end')), $select::JOIN_LEFT)
                                        ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
							         	When a.StageType='D' then e.DescriptionName end as StageName")
                                        , "PaymentScheduleUnitTransId", "StageId", 'NetAmount', "StageType"))
                                        ->where(array('a.UnitId' => $status['UnitId']));
                                    $select->order("SortId asc");
                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $status['arrBuyerStmt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $select = $sql->select();
                                    $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                        ->join(array("b" => "Crm_UnitDetails"), "a.UnitId=b.UnitId", array('AdvAmount'), $select::JOIN_LEFT)
                                        ->join(array("c" => "Crm_ReceiptRegister"), NEW Expression("a.UnitId=c.UnitId and c.ReceiptAgainst='A'and c.CancelId=0 and c.DeleteFlag= 0 "), array('sumAmount' => new expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                        ->columns(array('NetAmount'))
                                        ->where(array('a.UnitId' => $status['UnitId'], 'a.StageType' => 'A'))
                                        ->group(new expression('a.NetAmount,b.AdvAmount'));
                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $status['advamt'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                }
                                else {
                                    $status['advamt']=[] ;
                                    $status['arrBuyerStmt']=[] ;
                                }
                                $status['type']='cancel';
                            }

                            $status = json_encode($status);
                        }
                        else if($mode=='costsheet') {
                            $status=array();
                            $postParams = $request->getPost();
                            $unitId = $postParams['unitId'];
                            $projectId = $postParams['projectId'];
                            $booking = $postParams['bookingId'];

                            $select = $sql->select();
                            $select->from(array('a' => 'KF_UnitMaster'))
                                ->columns(array('UnitTypeId'))
                                ->where(array('a.ProjectId' => $projectId,'a.UnitId'=>$unitId));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $unitType = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Crm_UnitTypeOtherCostTrans'), 'a.OtherCostId = c.OtherCostId', array('Amount'), $select::JOIN_LEFT)
                                ->where(array('a.ProjectId' => $projectId,'c.UnitTypeId'=>$unitType['UnitTypeId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $other = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $status['other']=$other;
                            //paymentschedule//
                            $select = $sql->select();
                            $select->from('Crm_PostSaleDiscountRegister')
                                ->columns(array('PostSaleDiscountId','BookingId'))
                                ->where(array('BookingId' =>$booking))
                                ->order("PostSaleDiscountId desc");
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $unitPostSale = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $postsalecount=count($unitPostSale['BookingId']);
                            //payment schedules
                            $select = $sql->select();
                            $select->from('Crm_PaymentSchedule')
                                ->where(array('ProjectId' => $projectId, 'DeleteFlag' => 0));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            // Receipt Type
                            $select = $sql->select();
                            $select->from('Crm_ReceiptTypeMaster');
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $arrReceiptTypes = $arrResults;
                            $arrAllReceiptTypes = array();
                            foreach($arrResults as $result) {
                                $arrAllReceiptTypes[$result['ReceiptTypeId']] = $result['Type'];
                            }
                            if( $postsalecount > 0 ){
                                $select1 = $sql->select();
                                $select1->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'S', 'BookingId' => $booking,'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));

                                $select2 = $sql->select();
                                $select2->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'D', 'BookingId' => $booking,'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                                $select2->combine($select1,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'O', 'BookingId' => $booking,'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                                $select3->combine($select2,'Union ALL');

                                $select4 = $sql->select();
                                $select4->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'A', 'BookingId' => $booking,'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                                $select4->combine($select3,'Union ALL');

                                $select5 = $sql->select();
                                $select5->from(array("g"=>$select4))
                                    ->columns(array('*'))
                                    ->where(array('BookingId' => $booking))
                                    ->order("g.SortId ASC");
                                $stmt = $sql->getSqlStringForSqlObject($select5);
                                $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(!empty($arrPaymentScheduleDetails)) {

                                    foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                        // receipt type
                                        $select1 = $sql->select();
                                        $select1->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                            ->where( array( 'b.ReceiptType' => 'S', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                                        $select2 = $sql->select();
                                        $select2->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                            ->where( array( 'a.ReceiptType' => 'D', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                                        $select2->combine( $select1, 'Union ALL' );

                                        $select3 = $sql->select();
                                        $select3->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                            ->where( array( 'a.ReceiptType' => 'O', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                                        $select3->combine( $select2, 'Union ALL' );

                                        $select4 = $sql->select();
                                        $select4->from( array( "g" => $select3 ) )
                                            ->columns( array( '*' ) )
                                            ->order("g.ReceiptTypeTransId ASC");

                                        $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                        $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                        if(!empty($arrReceiptTypes)) {

                                            $iQualCount = 0;
                                            foreach($arrReceiptTypes as &$receipt) {

                                                switch($receipt['ReceiptType']) {
                                                    case 'O':
                                                        $receipt['Type'] = 'O';
                                                        break;
                                                    case 'S':
                                                        $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                        break;
                                                }

                                                // qualifier
                                                $select = $sql->select();
                                                $select->from( array( 'a' => 'Crm_PSDPaymentScheduleQualifierTrans' ) )
                                                    ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                                        'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                                        'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                                    ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                                $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                                $statement = $sql->getSqlStringForSqlObject( $select );
                                                $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                                if ( !empty( $qualList ) ) {
                                                    foreach($qualList as &$qual) {
                                                        $qual['BaseAmount'] = $receipt['Amount'];
                                                    }

                                                    $sHtml = Qualifier::getQualifier( $qualList );
                                                    $iQualCount = $iQualCount + 1;
                                                    $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                                    $receipt[ 'qualHtmlTag' ] = $sHtml;

                                                }

                                            }

                                            $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                        }
                                    }
                                }
                            }
                            else{

                                // current payment schedule detail
                                $select1 = $sql->select();
                                $select1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'S', 'BookingId' => $booking));

                                $select2 = $sql->select();
                                $select2->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'D', 'BookingId' => $booking));
                                $select2->combine($select1,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'O', 'BookingId' => $booking));
                                $select3->combine($select2,'Union ALL');

                                $select4 = $sql->select();
                                $select4->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                    ->columns(array('*'))
                                    ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                                    ->where(array('a.StageType' => 'A', 'BookingId' =>$booking));
                                $select4->combine($select3,'Union ALL');

                                $select5 = $sql->select();
                                $select5->from(array("g"=>$select4))
                                    ->columns(array('*'))
                                    ->where(array('BookingId' =>$booking))
                                    ->order("g.SortId ASC");
                                $stmt = $sql->getSqlStringForSqlObject($select5);
                                $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(!empty($arrPaymentScheduleDetails)) {

                                    foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                        // receipt type
                                        $select1 = $sql->select();
                                        $select1->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                            ->where( array( 'b.ReceiptType' => 'S', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                                        $select2 = $sql->select();
                                        $select2->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                            ->where( array( 'a.ReceiptType' => 'D', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                                        $select2->combine( $select1, 'Union ALL' );

                                        $select3 = $sql->select();
                                        $select3->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                            ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                            ->where( array( 'a.ReceiptType' => 'O', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                                        $select3->combine( $select2, 'Union ALL' );

                                        $select4 = $sql->select();
                                        $select4->from( array( "g" => $select3 ) )
                                            ->columns( array( '*' ) )
                                            ->order("g.ReceiptTypeTransId ASC");

                                        $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                        $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                        if(!empty($arrReceiptTypes)) {

                                            $iQualCount = 0;
                                            foreach($arrReceiptTypes as &$receipt) {

                                                switch($receipt['ReceiptType']) {
                                                    case 'O':
                                                        $receipt['Type'] = 'O';
                                                        break;
                                                    case 'S':
                                                        $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                        break;
                                                }

                                                // qualifier
//                                                    $select = $sql->select();
//                                                    $select->from( array( 'a' => 'Crm_PaymentScheduleQualifierTrans' ) )
//                                                        ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
//                                                            'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
//                                                            'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
//                                                        ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
//                                                    $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
//                                                    $statement = $sql->getSqlStringForSqlObject( $select );
//                                                    $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//                                                    if ( !empty( $qualList ) ) {
//                                                        foreach($qualList as &$qual) {
//                                                            $qual['BaseAmount'] = $receipt['Amount'];
//                                                        }
//
//                                                        $sHtml = Qualifier::getQualifier( $qualList );
//                                                        $iQualCount = $iQualCount + 1;
//                                                        $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
//                                                        $receipt[ 'qualHtmlTag' ] = $sHtml;
//
//                                                    }

                                            }

                                            $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                        }
                                    }
                                }}

                            $status['arrPaymentSchedules']=$arrPaymentScheduleDetails;

                            //costsheet//


                            $select = $sql->select();
                            $select->from(array('a' => 'KF_UnitMaster'))
                                ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
                                ->join(array('z' => 'KF_UnitTypeMaster'), 'z.UnitTypeId=a.UnitTypeId', array('TypeName'), $select::JOIN_LEFT)
                                ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId', array('*'), $select::JOIN_LEFT)
                                ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                                ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId', array('UnitName'), $select::JOIN_LEFT)
                                ->join(array("d" => "Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac" => "Description"), $select::JOIN_LEFT)
                                ->join(array("e" => "Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status" => "Description"), $select::JOIN_LEFT)
                                ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
                                ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate' => 'Rate', 'BOther' => 'OtherCostAmount', 'BQual' => 'QualifierAmount', 'BDiscountType' => 'DiscountType', 'BNet' => 'NetAmount', 'BBase' => 'BaseAmount', 'BConst' => 'ConstructionAmount', "BOther" => 'OtherCostAmount', 'BLand' => 'LandAmount', 'BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"), 'BookingId', 'Approve', 'BDiscount' => new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
                                ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId', 'PrevRate' => 'Rate', 'base' => 'BaseAmount', 'const' => 'ConstructionAmount', 'land' => 'LandAmount', 'gross' => 'GrossAmount', 'PostDiscount', "other" => 'OtherCostAmount', "PostDiscountType", "qual" => 'QualifierAmount', "net" => 'NetAmount', 'PRate' => 'Rate'), $select::JOIN_LEFT)
                                ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId', 'ValidUpto', 'BlockBAdv' => 'AdvAmnt', 'Blockbase' => 'BaseAmount', 'BRate', 'BlockDiscount' => 'Discount', 'Blockconst' => 'ConstructionAmount', 'Blockland' => 'LandAmount', 'Blockgross' => 'GrossAmount', "Blockother" => 'OtherCost', "Blockqual" => 'QualAmount', "Blocknet" => 'NetAmount', 'BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                                ->join(array('x' => 'Crm_UnitPreBooking'), new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId', 'ValidUpto', 'PreBAdv' => 'AdvAmount', 'Prebase' => 'BaseAmount', 'PreRate' => 'PRate', 'PreDiscount' => 'Discount', 'Preconst' => 'ConstructionAmount', 'Preland' => 'LandAmount', 'Pregross' => 'GrossAmount', "Preother" => 'OtherCost', "Prequal" => 'QualAmount', "Prenet" => 'NetAmount', 'PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                                ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                                ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto' => 'Photo'), $select::JOIN_LEFT)
                                ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto' => 'Photo'), $select::JOIN_LEFT)
                                ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
                                ->join(array("c" => "WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                                ->join(array("m" => "WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                                ->join(array("s" => "WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                                ->where(array("a.UnitId" => $unitId))
                                ->order("o.PostSaleDiscountId desc");
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();






                            if (empty($unitInfo)) {
                                throw new \Exception('Unit info not found!');
                            }

                            if ($unitInfo['Status'] == 'B') {
                                $unitInfo['BuyerName'] = $unitInfo['BlockedName'];
                                $unitInfo['ExecutiveName'] = $unitInfo['BlockExecutiveName'];
                                $unitInfo['OtherCostAmt'] = 0;
                                $unitInfo['NetAmt'] = $unitInfo['Blocknet'];
                                $unitInfo['QualifierAmount'] = $unitInfo['Blockqual'];
                                $unitInfo['Discount'] = $unitInfo['BlockDiscount'];
                                $unitInfo['AdvAmount'] = $unitInfo['BlockBAdv'];
                                $unitInfo['BaseAmt'] = $unitInfo['Blockbase'];
                                $unitInfo['ConstructionAmount'] = $unitInfo['Blockconst'];
                                $unitInfo['GrossAmount'] = $unitInfo['Blockgross'];
                                $unitInfo['Rate'] = $unitInfo['BRate'];
                                $unitInfo['Rate'] = $unitInfo['BRate'];
                                $unitInfo['LandAmount'] = $unitInfo['Blockland'];
                            } else if ($unitInfo['Status'] == 'P') {
                                $unitInfo['BuyerName'] = $unitInfo['PreName'];
                                $unitInfo['ExecutiveName'] = $unitInfo['PreExecutiveName'];
                                $unitInfo['OtherCostAmt'] = 0;
                                $unitInfo['NetAmt'] = $unitInfo['Prenet'];
                                $unitInfo['QualifierAmount'] = $unitInfo['Prequal'];
                                $unitInfo['Discount'] = $unitInfo['PreDiscount'];
                                $unitInfo['AdvAmount'] = $unitInfo['PreBAdv'];
                                $unitInfo['BaseAmt'] = $unitInfo['Prebase'];
                                $unitInfo['ConstructionAmount'] = $unitInfo['Preconst'];
                                $unitInfo['GrossAmount'] = $unitInfo['Pregross'];
                                $unitInfo['Rate'] = $unitInfo['PreRate'];
                                $unitInfo['LandAmount'] = $unitInfo['Preland'];
                            } else if ($unitInfo['Status'] == 'U') {
                                $unitInfo['BuyerName'] = " ";
                                $unitInfo['OtherCostAmt'] = 0;
                            } else if ($unitInfo['Status'] == 'R') {
                                $unitInfo['OtherCostAmt'] = 0;
                            }
                            if ($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                                $unitInfo['OtherCostAmt'] = $unitInfo['other'];
                                $unitInfo['NetAmt'] = $unitInfo['net'];
                                $unitInfo['QualifierAmount'] = $unitInfo['qual'];
                                $unitInfo['Discount'] = $unitInfo['PostDiscount'];
                                $unitInfo['BaseAmt'] = $unitInfo['base'];
                                $unitInfo['ConstructionAmount'] = $unitInfo['const'];
                                $unitInfo['GrossAmount'] = $unitInfo['gross'];
                                $unitInfo['LandAmount'] = $unitInfo['land'];
                                $unitInfo['Rate'] = $unitInfo['PrevRate'];
                            } else if ($unitInfo['count'] == 1) {
                                $unitInfo['OtherCostAmt'] = $unitInfo['BOther'];
                                $unitInfo['NetAmt'] = $unitInfo['BNet'];
                                $unitInfo['QualifierAmount'] = $unitInfo['BQual'];
                                $unitInfo['Discount'] = $unitInfo['BDiscount'];
                                $unitInfo['BaseAmt'] = $unitInfo['BBase'];
                                $unitInfo['ConstructionAmount'] = $unitInfo['BConst'];
                                $unitInfo['GrossAmount'] = $unitInfo['BBase'] + $unitInfo['BOther'];
                                $unitInfo['LandAmount'] = $unitInfo['BLand'];
                                $unitInfo['Rate'] = $unitInfo['B00kRate'];

                            }
                            $status['unitInfo']=$unitInfo;


                            $select = $sql->select();
                            $select->from(array('a' => 'KF_UnitMaster'))
                                ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                                ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId', array('*'), $select::JOIN_LEFT)
                                ->where(array("a.UnitId" => $unitId));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                            $select = $sql->select();
                            $select->from('Crm_ReceiptRegister')
                                ->columns(array('count' => new Expression("count(ReceiptNo)")))
                                ->where(array('UnitId' => $unitInfo['UnitId'], 'ReceiptAgainst' => 'A', 'CancelId' => 0, 'DeleteFlag' => 0));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $this->_view->advAmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $planDiscountValid2 = false;
                            $planDiscountValid1 = false;

                            if ($unitInfo['Status'] == 'S') {
                                //cost sheet
                                if ($unitInfo['count'] == 1) {
                                    $planDiscountValid2 = true;
                                    $Bother = $unitInfo['BOther'];
                                    $BNet = $unitInfo['BNet'];
                                    $BQual = $unitInfo['BQual'];
                                    $BDis = $unitInfo['BDiscount'];
                                    $BDisType = $unitInfo['BDiscountType'];
                                    // Print_r($BDisType);die;
                                    $Bbase = $unitInfo['BBase'];
                                    $Bconst = $unitInfo['BConst'];
                                    $BGross = $unitInfo['BBase'] + $unitInfo['BOther'];
                                    $BLand = $unitInfo['BLand'];
                                    $BRate = $unitInfo['B00kRate'];

                                    if ($BDisType == 'R') {
                                        $BDisType = 'Rate/Sqft';
                                    } else if ($BDisType == 'L') {
                                        $BDisType = 'Lumpsum';
                                    } else if ($BDisType == 'P') {
                                        $BDisType = 'Percentage';
                                    } else {
                                        $BDisType = '-';
                                    }

                                }
                                if ($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                                    $planDiscountValid1 = true;
                                    $Pother = $unitInfo['other'];
                                    $PNet = $unitInfo['net'];
                                    $PQual = $unitInfo['qual'];
                                    $PDis = $unitInfo['PostDiscount'];
                                    $PDisType = $unitInfo['PostDiscountType'];
                                    // Print_r($PDisType);die;
                                    $Pbase = $unitInfo['base'];
                                    $Pconst = $unitInfo['const'];
                                    $PGross = $unitInfo['gross'];
                                    $PLand = $unitInfo['land'];
                                    $PRate = $unitInfo['PRate'];
                                    if ($PDisType == 'R') {
                                        $PDisType = 'Rate/Sqft';
                                    } else if ($PDisType == 'L') {
                                        $PDisType = 'Lumpsum';
                                    } else if ($PDisType == 'P') {
                                        $PDisType = 'Percentage';
                                    } else {
                                        $PDisType = '-';
                                    }
                                }
                                $discount = 0;
                                //  $area =$unitInfo['UnitArea'];
                                $rate = $unitIn['Rate'];
                                $baseAmt = $unitIn['BaseAmt'];
                                $grossAmt = $unitIn['GrossAmount'];
                                $netAmt = $unitIn['NetAmt'];
                                $otherCostAmt = $unitIn['OtherCostAmt'];
                                $constructionAmount = $unitIn['ConstructionAmount'];
                                $landAmount = $unitIn['LandAmount'];
                                $discountType = '-';


                                $costSheet = array();

                                $this->_view->planDiscountValid2 = $planDiscountValid2;
                                $this->_view->planDiscountValid1 = $planDiscountValid1;
                                if ($planDiscountValid1 == true && $planDiscountValid2 == true) {
                                    $costSheet['Discount Type'] = array($discountType, $BDisType, $PDisType);
                                    $costSheet['Discount'] = array($viewRenderer->commonHelper()->sanitizeNumber($discount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BDis, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($PDis, 2, true));
                                    $costSheet['other Cost Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bother, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Pother, 2, true));
                                    $costSheet['Rate'] = array($viewRenderer->commonHelper()->sanitizeNumber($rate, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BRate, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($PRate, 2, true));
                                    $costSheet['Base Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bbase, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Pbase, 2, true));
                                    $costSheet['Gross Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BGross, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($PGross, 2, true));
                                    $costSheet['Net Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($netAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BNet, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($PNet, 2, true));
                                    $costSheet['Construction Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bconst, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Pconst, 2, true));
                                    $costSheet['Land Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($landAmount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BLand, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($PLand, 2, true));
                                } else if ($planDiscountValid1 == false && $planDiscountValid2 == true) {
                                    $costSheet['Discount Type'] = array($discountType, $BDisType, '-');
                                    $costSheet['Discount'] = array($viewRenderer->commonHelper()->sanitizeNumber($discount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BDis, 2, true), '-');
                                    $costSheet['Rate'] = array($viewRenderer->commonHelper()->sanitizeNumber($rate, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BRate, 2, true), '-');
                                    $costSheet['Base Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bbase, 2, true), '-');
                                    $costSheet['Gross Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BGross, 2, true), '-');
                                    $costSheet['Net Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($netAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BNet, 2, true), '-');
                                    $costSheet['Construction Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bconst, 2, true), '-');
                                    $costSheet['Land Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($landAmount, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($BLand, 2, true), '-');
                                    $costSheet['otherCost Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt, 2, true), $viewRenderer->commonHelper()->sanitizeNumber($Bother, 2, true), '-');
                                } else if ($planDiscountValid1 == false && $planDiscountValid2 == false) {
                                    $costSheet['Discount Type'] = array($discountType, '-', '-');
                                    $costSheet['Discount'] = array($viewRenderer->commonHelper()->sanitizeNumber($discount, 2, true), '-', '-');
                                    $costSheet['Rate'] = array($viewRenderer->commonHelper()->sanitizeNumber($rate, 2, true), '-', '-');
                                    $costSheet['Base Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt, 2, true), '-', '-');
                                    $costSheet['Gross Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt, 2, true), '-', '-');
                                    $costSheet['Net Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($netAmt, 2, true), '-', '-');
                                    $costSheet['Construction Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount, 2, true), '-', '-');
                                    $costSheet['Land Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($landAmount, 2, true), '-', '-');
                                    $costSheet['otherCost Amount'] = array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt, 2, true), '-', '-');
                                }
                                $status['costSheet'] = $costSheet;
                            }


                            $status = json_encode($status);
                        }



                        else if($mode=='appnoti') {
                            $checkCloseAll = $this->bsf->isNullCheck($postParams['check'], 'string');
                            if($checkCloseAll=='clear') {

                                $select = $sql->select();
                                $select->from(array('a' =>'WF_AppNotification'))
                                    ->columns(array(new Expression ('DISTINCT(a.FeedId) as FeedId')))
                                    ->join(array('b' => 'WF_Feeds'), 'a.FeedId=b.FeedId', array(), $select::JOIN_LEFT)
                                    ->where("b.UserId=$userId and a.DeleteFlag='0'");

                                $update = $sql->update();
                                $update->table('WF_AppNotification');
                                $update->set(array(
                                    'DeleteFlag'  => 1,
                                ));
                                $update->where->expression('FeedId IN ?', array($select));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }

                            $select = $sql->select();
                            $select->from(array('a' =>'WF_AppNotification'))
                                ->columns(array('*'))
                                ->join(array('b' => 'WF_Feeds'), 'a.FeedId=b.FeedId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array('EmployeeName','UserLogo','UserId'), $select::JOIN_LEFT)
                                ->where("b.UserId=$userId and c.UserId!=$userId and a.DeleteFlag='0'")
                                ->order('a.createdDate DESC');
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $appNote = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $path = $viewRenderer->basePath() . '/' .$this->auth->getIdentity()->UserLogo;
                            $status="";
                            $noteArr=array();

                            if(isset($appNote)) {
                                foreach ($appNote as $app) {
                                    if($app['NotificationView']==0) {
                                        $noteArr[]=$app['NotificationId'];
                                    }
                                    if (isset($app['UserLogo']) && trim($app['UserLogo']) != '') {
                                        $path = $viewRenderer->basePath() . '/' . $app['UserLogo'];
                                    } else {
                                        $path = $viewRenderer->basePath() . '/images/avatar.jpg';
                                    }
                                    $appDate = $this->bsf->timeAgo($app['CreatedDate']);
                                    if($app['NotificationType']=="like") {
                                        $status .= '<li id="close_hide_'.$app["NotificationId"].'">
                                                        <a href="javascript:void(0);" >
                                                            <div class="noti_icon">
                                                                <img src="' . $path . '" alt="" title="">
                                                            </div>
                                                            <div class="noti_info">
                                                                <span onclick="clearMessage('.$app["NotificationId"].');" class="close"><i class="fa fa-times"></i></span></span>
                                                                <p><span class="fav_name fav_user_name">' . $app['EmployeeName'] . '</span> Likes your Post</p>
                                                            </div>
                                                            <div class="noti_time">
                                                                <span>' . $appDate . '</span>
                                                            </div>
                                                        </a>
                                                    </li>';
                                    } else if($app['NotificationType']=="comment") {
                                        $status .= '<li id="close_hide_'.$app["NotificationId"].'">
                                                        <a href="javascript:void(0);" >
                                                            <div class="noti_icon">
                                                                <img src="' . $path . '" alt="" title="">
                                                            </div>
                                                            <div class="noti_info">
                                                                <span onclick="clearMessage('.$app["NotificationId"].');" class="close"><i class="fa fa-times"></i></span></span>
                                                                <p><span class="fav_name fav_user_name">' . $app['EmployeeName'] . '</span> Commented on your Post</p>
                                                            </div>
                                                            <div class="noti_time">
                                                                <span>' . $appDate . '</span>
                                                            </div>
                                                        </a>
                                                    </li>';
                                    }
                                }
                            }

                            if(count($noteArr) > 0) {
                                $update = $sql->update();
                                $update->table('WF_AppNotification');
                                $update->set(array(
                                    'NotificationView' => 1,
                                ));
                                $update->where(array('NotificationId' => $noteArr));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        } else if($mode=='commentall') {
                            $commentFeedId = $this->bsf->isNullCheck($postParams['FeedId'], 'number');

                            $select = $sql->select();
                            $select->from(array('a' =>'WF_Comments'))
                                ->columns(array('*'))
                                ->join(array('b' => 'WF_Users'), 'a.UserId=b.UserId', array('UserLogo','EmployeeName'), $select::JOIN_INNER);
                            $select->where("a.FeedId=$commentFeedId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $status="";
                            if(isset($resData)) {
                                foreach ($resData as $commentData) {
                                    if (isset($commentData['UserLogo']) && trim($commentData['UserLogo']) != '') {
                                        $path = $viewRenderer->basePath() . '/' . $commentData['UserLogo'];
                                    } else {
                                        $path = $this->basePath() . '/images/avatar.jpg';
                                    }
                                    $cmtDate = $this->bsf->timeAgo($commentData['CreatedDate']);

                                    $status .= '<li>
                                                    <div class="cmt_usrpp">
                                                        <div><img src="' . $path . '"></div>
                                                    </div>
                                                    <div class="cmt_news">
                                                        <div>' . $commentData['EmployeeName'] . '<span><i class="fa fa-clock-o"></i>'.$cmtDate.'</span></div>
                                                        <p>' . $commentData['Comments'] . '</p>
                                                    </div>
                                                </li>';

                                }
                            }
                        } else {
                            $sType = $this->bsf->isNullCheck($postParams['Type'], 'string');
                            //$iRegId = $this->bsf->isNullCheck($postParams['RegId'], 'number');
                            //$sRoleName = $this->bsf->isNullCheck($postParams['RoleName'], 'string');
                            //$sDBName = $this->bsf->isNullCheck($postParams['DBName'], 'string');
                            $iLogId = $this->bsf->isNullCheck($postParams['LogId'], 'number');

                            /*
                            Select M.ModuleName,F.TaskName,A.TransId, B.LogTime,C.RefNo,B.RoleName PrevRole,A.PendingRole,A.RoleType,A.LogId,D.ProjectName
                            ,B.LogDescription Remarks,A.NonTask,C.Priority,C.PriorityRemarks from WF_Pendingworks A
                            Inner Join WF_LogMaster B  on A.LogId=B.LogId
                            Inner Join WF_LogTrans C  on B.LogId=C.LogId
                            Left Join Proj_ProjectMaster D  on C.CostCentreId=D.ProjectId
                            Left Join WF_TaskTrans E on B.RoleName=E.RoleName
                            Left Join WF_TaskMaster F  on E.TaskName=F.TaskName
                            Left Join WF_Module M  on F.ModuleId=M.ModuleId
                            Where A.UserId in (18) and A.Status=0 and A.RoleType = 'A'
                            */
                            $iRegId =0;
                            $iCCId =0;
                            $iCompanyId =0;
                            $sDBName= "";
                            $sRoleName ="";
                            $sRefNo="";
                            $sRemarks="";

                            $select = $sql->select();
                            $select->from(array('a' =>'WF_PendingWorks'))
                                ->columns(array('TransId','PendingRole','RoleType','LogId','NonTask','RefNo' => New Expression("c.RefNo"),'PrevRole' => New Expression("b.RoleName")
                                ,'Remarks' => New Expression("b.LogDescription"),'DBName' => New Expression("c.DBName"),'RegisterId' => New Expression("c.RegisterId")
                                ,'CostCentreId' => New Expression("c.CostCentreId"),'CompanyId' => New Expression("c.CompanyId")))
                                ->join(array('b' => 'WF_LogMaster'), 'a.LogId=b.LogId', array(), $select::JOIN_INNER)
                                ->join(array('c' => 'WF_LogTrans'), 'b.LogId=c.LogId', array(), $select::JOIN_INNER);
                            $select->where("a.LogId=$iLogId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $resData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if(count($resData) > 0) {
                                $sDBName = $this->bsf->isNullCheck($resData[0]['DBName'], 'string');
                                $sRoleName = $this->bsf->isNullCheck($resData[0]['PendingRole'], 'string');
                                $sRefNo = $this->bsf->isNullCheck($resData[0]['RefNo'], 'string');
                                $iRegId = $this->bsf->isNullCheck($resData[0]['RegisterId'], 'number');
                                $sRemarks = $this->bsf->isNullCheck($resData[0]['Remarks'], 'string');
                                $iCCId = $this->bsf->isNullCheck($resData[0]['CostCentreId'], 'number');
                                $iCompanyId = $this->bsf->isNullCheck($resData[0]['CompanyId'], 'number');
                            }

                            switch($mode) {
                                case 'check':
                                    if ($sType == "R") {
                                        $bAns = CommonHelper::GetApprovalStatus($iRegId, $sRoleName, $sDBName, $userId, $dbAdapter);
                                        if ($bAns == true) {
                                            // alert(Already Approved, Don't Reject) Return
                                            return $response->setStatusCode('201')->setContent('Already Approved, Don\'t Reject');
                                        }
                                    }

                                    if ($sType == "U") {
                                        $bAns = CommonHelper::GetApprovalStatus($iRegId, $sRoleName, $sDBName, $userId, $dbAdapter);
                                        if ($bAns == false) {
                                            //Already Unapproved, Don't UnApprove again Return
                                            return $response->setStatusCode('201')->setContent('Already Unapproved, Don\'t UnApprove again');
                                        }

                                        $bAns = CommonHelper::GetTopApprovalFound($userId, $iLogId, $sRoleName, $dbAdapter);
                                        if ($bAns == true) {
                                            //Higher Approval Found, Don't UnApprove Return
                                            return $response->setStatusCode('201')->setContent('Higher Approval Found, Don\'t UnApprove');
                                        }
                                    }
                                    if ($sType == "A") {
                                        $bAns = CommonHelper::GetApprovalStatus($iRegId, $sRoleName, $sDBName, $userId, $dbAdapter);
                                        if ($bAns == true) {
                                            //Already Approved, Don't Approve Again Return
                                            return $response->setStatusCode('201')->setContent('Already Approved, Don\'t Approve Again');
                                        }
                                    }
                                    return $response->setStatusCode('200')->setContent('Not used');

                                    break;
                                case 'update':
                                    $sType = $this->bsf->isNullCheck($postParams['Type'], 'string');
                                    //$iRegId = $this->bsf->isNullCheck($postParams['RegId'], 'number');
                                    //$sRoleName = $this->bsf->isNullCheck($postParams['RoleName'], 'string');
                                    //$sDBName = $this->bsf->isNullCheck($postParams['DBName'], 'string');
                                    //$userId = $this->bsf->isNullCheck($postParams['UserId'], 'number');
                                    $iLogId = $this->bsf->isNullCheck($postParams['LogId'], 'number');



                                    //$connection->beginTransaction();
                                    CommonHelper::insertLog(date('Y-m-d H:i:s'), $sRoleName, $sType, $sRemarks, $iRegId, $iCCId, $iCompanyId, $sDBName, $sRefNo, $userId, 0, $iLogId);
                                    //$connection->commit();

                                    $status = 'update';
                                    break;
                            }

                        }
                    } catch (PDOException $e) {
                        $connection->rollback();
                        $response->setStatusCode(400)->setContent($status);
                    }
                }

                $this->_view->setTerminal(true);
                $response->setContent($status);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
            } else {
                /*****LEFT RAIL*****/
                //Current Date,Month,Year
                $today = date('Y-m-d');
                $tDate = date('Y-m-d', strtotime($today));
                $tDa = date('d', strtotime($today));
                $tMonth = date('M', strtotime($tDate));
                $tMonthNo = date('n', strtotime($tDate));
                $tMontho = date('m', strtotime($tDate));
                $tYear = date('Y', strtotime($tDate));
                $cDate = $tYear."-".$tMontho."-01";

                //Birthday Reminders
                $select = $sql->select();
                $select->from(array('a' => 'WF_Users'))
                    ->join(array('b' => 'WF_PositionMaster'), 'a.PositionId=b.PositionId', array('PositionName'), $select::JOIN_LEFT)
                    ->columns(array('EmployeeName', 'UserId', 'UserDob', 'UserLogo', 'Email'))
                    ->where->expression('MONTH(UserDob) = ?', $tMontho)
                    ->where->expression('DAY(UserDob)=?', $tDa)
                    ->where->notEqualTo('UserId', $userId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->birthdayReminder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Ongoing Activities
                $arrExecutiveIds = $viewRenderer->commonHelper()->masterSuperior($userId,$dbAdapter);
                $select = $sql->select();
                $select->from(array('a' =>'WF_LogStatus'))
                    ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array('EmployeeName'), $select::JOIN_LEFT)
                    ->where('a.UserId IN (' . implode(',', $arrExecutiveIds) . ')')
                    ->where->notEqualTo('a.UserId', 1)
                    ->where->notEqualTo('a.UserId', $userId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ongoing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Recently Used
                $select = $sql->select();
                $select->from(array('a' =>'WF_LogMaster'))
                    ->columns(array('UserId' => new Expression("top 3 a.UserId"),'RoleName'))
                    ->join(array('b' => 'WF_LogTrans'), 'a.LogId=b.LogId', array('DBName'), $select::JOIN_INNER)
                    ->join(array('c' => 'WF_TaskTrans'), 'a.RoleName=c.RoleName', array('MPath','Controller','Action'), $select::JOIN_LEFT)
                    ->where("a.UserId = $userId and a.LogType !='S'and a.LogType !='A'and a.LogType !='U'")
                    ->order('a.LogTime desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->recentused = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Most Used
                $select = $sql->select();
                $select->from(array('a' =>'WF_LogMaster'))
                    ->columns(array( 'count' => new Expression('COUNT(*)'),'RoleName'))
                    ->join(array('b' => 'WF_LogTrans'), 'a.LogId=b.LogId', array('DBName'), $select::JOIN_INNER)
                    ->join(array('c' => 'WF_TaskTrans'), 'a.RoleName=c.RoleName', array('MPath','Controller','Action'), $select::JOIN_LEFT)
                    ->where("a.LogType !='S'and a.LogType !='A'and a.LogType !='U' ")
                    ->group(new expression('a.RoleName,b.DBName ,c.MPath,c.Controller,c.Action'))
                    ->order('count desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->mostused = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                /*****LEFT RAIL*****/

                /*****RIGHT RAIL 1*****/
                //check Executive
                $PositionTypeId=array(5,2);
                $sub = $sql->select();
                $sub->from(array('a'=>'WF_PositionMaster'))
                    ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                    ->columns(array('PositionId'))
                    ->where(array("b.PositionTypeId"=>$PositionTypeId));

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'))
                    ->where->expression("PositionId IN ?",array($sub));
                $select->where(array("UserId"=>$userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultsExe= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->checkExe=$resultsExe;

                if(count($resultsExe)>0) {
                    // User Target
                    // Target
                    $select = $sql->select()
                        ->from(array('a' => 'Crm_TargetTrans'))
                        ->columns(array('MonthTarget' => new Expression("SUM(CASE b.TargetPeriod  WHEN 1 THEN a.TValue/1
                                WHEN 2 THEN a.TValue/2
                                WHEN 3 THEN a.TValue/3
                                WHEN 4 THEN a.TValue/6
                                WHEN 5 THEN a.TValue/12
                                END)")))
                        ->join(array('b' => 'Crm_TargetRegister'), 'b.TargetId=a.TargetId', array(), $select::JOIN_LEFT)
                        ->where(array('a.ExecutiveId' =>$userId))
                        ->where(array('b.DeleteFlag' => 0))
                        ->where("'".$cDate."' >= a.FromDate AND '".$cDate."' <= a.ToDate");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->targetVal = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // User Acheived
                    $select = $sql->select();
                    $select->from(array('b' => 'Crm_UnitDetails'))
                        ->columns(array('AchAmount' => new Expression('SUM(GrossAmount)')))
                        ->join(array('a' => 'Crm_UnitBooking'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'kf_unitMaster'), 'b.UnitId=c.UnitId', array(), $select::JOIN_LEFT)
                        ->where(array("ExecutiveId" => $userId))
                        ->where(array('a.DeleteFlag' => 0))
                        ->where->expression('MONTH(BookingDate) = ?', array($tMontho));
                    $select->where->expression('YEAR(BookingDate) = ?', array($tYear));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->achvied = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //User Success Rate
                    $select = $sql->select();
                    $select->from('Crm_LeadFollowup')
                        ->columns(array('winCount' => new expression('count(*)')))
                        ->where(array("ExecutiveId" => $userId, "CallTypeId" => '4'))
                        ->where->expression('MONTH(FollowupDate) = ?', array($tMontho));
                    $select->where->expression('YEAR(FollowupDate) = ?', array($tYear));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->win = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->userId = $this->auth->getIdentity()->UserId;


                    //User Loss rate
                    $select = $sql->select();
                    $select->from('Crm_LeadFollowup')
                        ->columns(array('dropCount' => new expression('count(*)')))
                        ->where(array("ExecutiveId" => $userId, "CallTypeId" => '3'))
                        ->where->expression('MONTH(FollowupDate) = ?', array($tMonthNo));
                    $select->where->expression('YEAR(FollowupDate) = ?', array($tYear));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->drop = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    /*****RIGHT RAIL 1*****/
                }
                /*****FEED SECTION*****/
                //Current user have birthday
                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('EmployeeName', 'UserId', 'UserDob', 'UserLogo', 'Email'))
                    ->where->expression('MONTH(UserDob) = ?', $tMontho);
                $select ->where->expression('DAY(UserDob)=?', $tDa);
                $select ->where->EqualTo('UserId', $userId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userBday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //Pending Approval
                $select = $sql->select();
                $select->from(array('a' =>'WF_PendingWorks'))
                    ->columns(array('StartDate'=>new Expression("Distinct(CONVERT(varchar(10),StartTime,103))")))
                    ->where(array("a.UserId" => $userId))
                    ->where("a.Status=0 and a.RoleType = 'A'")
                    ->where->notEqualTo('a.Starttime', $today);
                $statement = $sql->getSqlStringForSqlObject($select);
                $distinctdate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' =>'WF_PendingWorks'))
                    ->columns(array('*'))
                    ->join(array('b' => 'WF_LogMaster'), 'a.LogId=b.LogId', array('RoleName'), $select::JOIN_INNER)
                    ->join(array('c' => 'WF_LogTrans'), 'a.LogId=c.LogId', array('RegisterId','DBName','RefNo'), $select::JOIN_INNER)
                    ->where(array("a.UserId" => $userId))
                    ->where("a.Status=0 and a.RoleType = 'A'")
                    ->where->notEqualTo('a.Starttime', $today);
                $statement = $sql->getSqlStringForSqlObject($select);
              $pending = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach($pending as &$pen){
                    $pen['name']='';
                    if($pen['RoleName'] =='Finalization-Add'){
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitBooking'))
                            ->join(array('b' => 'KF_UnitMaster'), 'a.unitId=b.UnitId', array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                            ->join(array('d' => 'KF_BlockMaster'), 'b.BlockId=d.BlockId', array('BlockName'), $select::JOIN_INNER)
                        ->where(array("a.BookingId"=>$pen['RegisterId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $pen['final'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pen['name']='Finalisation';
                    }
                    else  if($pen['RoleName'] =='Unit-Cancellation-Add'){


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_UnitCancellation'))
                                ->columns(array('Type','UnitId','Remarks'))
                                ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId',array('ProjectId','UnitNo'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                                ->where(array("a.CancellationId"=>$pen['RegisterId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                           $pen['final'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         $pen['name']='cancellation';
                    }
                    else  if($pen['RoleName'] =='Block-Add'){


                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitBlock'))
                            ->columns(array('UnitId','Remarks'))
                            ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId',array('ProjectId','UnitNo'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                            ->where(array("a.BlockId"=>$pen['RegisterId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $pen['final'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pen['name']='block';
                    }
                    else  if($pen['RoleName'] =='PreBook-Add'){


                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitPreBooking'))
                            ->columns(array('UnitId','Remarks'))
                            ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId',array('ProjectId','UnitNo'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                            ->where(array("a.CancellationId"=>$pen['RegisterId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $pen['final'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pen['name']='prebook';
                    }
                    else  if($pen['RoleName'] =='Stage-Completion-Add'){



                            $select = $sql->select();
                            $select->from(array('a' => 'KF_StageCompletion'))
                                ->join(array("c" => "Proj_ProjectMaster"),"a.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                                ->join(array("d" => "KF_BlockMaster"),"a.BlockId=d.BlockId", array('BlockName'), $select::JOIN_INNER)
                                ->join(array("e" => "KF_FloorMaster"), "a.FloorId=e.FloorId", array('FloorName'), $select::JOIN_INNER)
                                ->where(array('a.StageCompletionId' =>$pen['RegisterId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                        $pen['final'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pen['name']='StageCompletion';
              }
                    else  if($pen['RoleName'] =='Unit-Transfer-Add'){


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_UnitTransfer'))
                                ->columns(array('OldUnitId','NewUnitId'))
                                ->where(array("a.TransferId"=>$pen['RegisterId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $transfer = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_UnitBooking'))
                                ->join(array('b' => 'KF_UnitMaster'), 'a.unitId=b.UnitId', array('UnitId', 'UnitNo', 'ProjectId'), $select::JOIN_INNER)
                                ->join(array('c' => 'Proj_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select::JOIN_INNER)
                                ->join(array('d' => 'KF_BlockMaster'), 'b.BlockId=d.BlockId', array('BlockName'), $select::JOIN_INNER)
                                ->join(array('e' => 'KF_FloorMaster'), 'b.FloorId=e.FloorId', array('FloorName'), $select::JOIN_INNER)
                                ->join(array('h' => 'Crm_Leads'), 'a.LeadId=h.LeadId', array('*'), $select::JOIN_INNER)
                                ->join(array('g' => 'KF_UnitTypeMaster'), 'b.ProjectId=g.ProjectId', array('UnitTypeName'), $select::JOIN_INNER);
                            $select->where(array("a.UnitId"=>$transfer['NewUnitId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                        $pen['final'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $pen['name']='unit transfer';

                    }

                }
                $this->_view->pending=$pending;
                $this->_view->distinctdate=$distinctdate;

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
                    ->join(array('d' => 'WF_RespondInfo'), 'a.ResponseId=d.RespondId', array('RespondMessage'=>'Remarks'), $select::JOIN_LEFT)
                    ->join(array('f' => 'WF_ReminderInfo'), 'a.ReminderId=f.ReminderId', array('RemindMessage'=>'Remarks'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_AskInfo'), 'a.AskId=e.AskId OR d.AskId=e.AskId OR f.AskId=e.AskId', array('AskMessage'=>'Remarks','AskedBy'=>'UserId','AskUrl'=>'Url','AskFor'=>'AskFor','AskTitle'=>'Title'), $select::JOIN_LEFT)
                    ->join(array('g' => 'WF_ShareInfo'), 'a.ShareId=g.ShareId', array('ShareMessage'=>'Remarks','SharedBy'=>'UserId','ShareUrl'=>'Url','ShareTitle'=>'Title'), $select::JOIN_LEFT)
                    ->join(array('h' => 'WF_users'), 'a.UserId=h.UserId', array('UserName'=>'EmployeeName','UserAvatar'=>'Userlogo'), $select::JOIN_LEFT)
                    ->join(array('i' => 'WF_users'), 'e.UserId=i.UserId', array('AskToName'=>'EmployeeName'), $select::JOIN_LEFT)
                    ->where("((a.FeedType = 'birthday' AND a.DeleteFlag = '0' AND a.BirthdayId IN (Select BirthdyId from WF_BirthDyWishes where WisherId = '".$this->auth->getIdentity()->UserId."'))
						OR (a.FeedType = 'status' AND a.DeleteFlag = '0') 
						OR (a.FeedType = 'photo' AND a.DeleteFlag = '0')
						OR (a.FeedType = 'ask' AND a.DeleteFlag = '0' AND a.AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."')) 
						OR (a.FeedType = 'response' AND a.DeleteFlag = '0' AND a.ResponseId IN (Select RespondId from WF_RespondInfo where UserId = '".$this->auth->getIdentity()->UserId."' OR AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
						OR (a.FeedType = 'reminder' AND a.DeleteFlag = '0' AND a.ReminderId IN (Select ReminderId from WF_ReminderInfo where AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
						OR (a.FeedType = 'share' AND a.DeleteFlag = '0' AND a.ShareId IN (Select ShareId from WF_ViewTo where ViewUser = '".$this->auth->getIdentity()->UserId."'))
						OR (a.FeedType = 'approval' AND a.FeedId = a.ParentId AND a.UserId = '".$this->auth->getIdentity()->UserId."'))")
                    ->order('a.createdDate DESC')
                    ->limit(10)
                    ->offset(0);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->feedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Total Feeds Count
                $select = $sql->select();
                $select->from(array('a' =>'WF_Feeds'))
                    ->columns(array('TotalFeeds'=>new Expression("count(*)"),))
                    ->where("((a.FeedType = 'birthday' AND a.DeleteFlag = '0' AND a.BirthdayId IN (Select BirthdyId from WF_BirthDyWishes where WisherId = '".$this->auth->getIdentity()->UserId."'))
						OR (a.FeedType = 'status' AND a.DeleteFlag = '0') 
						OR (a.FeedType = 'photo' AND a.DeleteFlag = '0')
						OR (a.FeedType = 'ask' AND a.DeleteFlag = '0' AND a.AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."')) 
						OR (a.FeedType = 'response' AND a.DeleteFlag = '0' AND a.ResponseId IN (Select RespondId from WF_RespondInfo where UserId = '".$this->auth->getIdentity()->UserId."' OR AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
						OR (a.FeedType = 'reminder' AND a.DeleteFlag = '0' AND a.ReminderId IN (Select ReminderId from WF_ReminderInfo where AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
						OR (a.FeedType = 'share' AND a.DeleteFlag = '0' AND a.ShareId IN (Select ShareId from WF_ViewTo where ViewUser = '".$this->auth->getIdentity()->UserId."'))
						OR (a.FeedType = 'approval' AND a.FeedId = a.ParentId AND a.UserId = '".$this->auth->getIdentity()->UserId."'))");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalFeeds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                /*****FEED SECTION*****/

                /*****RIGHT RAIL 2*****/
                //users completed Activities
                $select = $sql->select();
                $select->from(array('a' => 'WF_UserSuperiorTrans'))
                    ->columns(array('Details' => new Expression("Case When d.RoleType='A' then 'Approved ' When d.RoleType='N' then 'Completed the '  When d.RoleType='D' then 'Removed '
					 When d.RoleType='E' then 'Modified the ' else ' ' End")))
                    ->join(array('b' => 'WF_LogMaster'), 'a.UserId=b.UserId', array('LogTime', 'UserId', 'RoleName', 'LogDescription','LogId'), $select::JOIN_INNER)
                    ->join(array('c' => 'WF_Users'), 'b.UserId=c.UserId', array('EmployeeName', 'UserLogo'), $select::JOIN_INNER)
                    ->join(array('d' => 'WF_TaskTrans'), 'b.RoleName=d.RoleName', array('RoleType'), $select::JOIN_INNER)
                    ->where(array("a.SUserId" => $userId))
                    ->order('b.LogTime desc');
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->taskcomplete = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Friends Chat

                $select = $sql->select();
                $select->from(array('a' =>'WF_Users'))
                    ->columns(array("EmployeeName","UserLogo","UserId","LogFlag"=>new expression("(select distinct(b.UserId) from WF_LogStatus as b where a.UserId=b.UserId )")))
                    ->where(array("a.DeleteFlag"=>'0',"a.Lock"=>'0'))
                    ->where->notEqualTo('a.UserId', $userId);

                $resSelect = $sql->select();
                $resSelect->from(array('g'=>$select))
                    ->columns(array("UserId","EmployeeName","UserLogo","LogFlag"));
                $resSelect->order("LogFlag desc");
                $resStatement = $sql->getSqlStringForSqlObject($resSelect);
                $this->_view->chatUser = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->sender=$this->auth->getIdentity()->UserId;
                /*****RIGHT RAIL 2*****/

            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function dashboardAction()	{
        $this->layout('layout/dashboard');
    }

    //End dashboard action//

    public function shareAction(){
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
        $userId  = $this->auth->getIdentity()->UserId;
        $Ename  = $this->auth->getIdentity()->EmployeeName;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    if($postParams['mode']=='askdetail'){

                        $insert  = $sql->insert('WF_AskInfo');

                        $newData = array(
                            'UserId'   =>$userId,
                            'url'=>$this->bsf->isNullCheck($postParams['url'],'string'),
                            'Title'=>$this->bsf->isNullCheck($postParams['dataHead'],'string'),
                            'Remarks' =>$this->bsf->isNullCheck($postParams['remarks'],'string'),
                            'AskFor' =>$this->bsf->isNullCheck($postParams['ask_for'],'string'),
                            'CreatedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                       $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $askId = $dbAdapter->getDriver()->getLastGeneratedValue();


                        $FeedType='ask';
                        $feedDet=$askId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastfeedId = $dbAdapter->getDriver()->getLastGeneratedValue();


                        if($postParams['ask_for'] != 'Approval'){
                            foreach ($postParams['inputdata'] as $value){

                                $select = $sql->insert('WF_DataShare');
                                $newData = array(
                                    'AskId' => $askId,
                                    'InputValue'=> $value,
                                );
                                $select->values($newData);
                               $statement = $sql->getSqlStringForSqlObject($select);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }}

                        foreach ($postParams['allsuperioruser'] as $value){
                            $select = $sql->insert('WF_AskTo');
                            $newData = array(
                                'AskId' => $askId,
                                'AskUser'=> $value,
                            );
                            $select->values($newData);
                          $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Users'))
                            ->columns(array('Email','UserId'))
                            ->where(array("a.UserId"=>$postParams['allsuperioruser']));
                       $stmt = $sql->getSqlStringForSqlObject($select);
                        $emailId = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();

                        foreach ($emailId as $email) {

                            $mailData = array(

                                array(
                                    'name' => 'EmployeeName',
                                    'content' => $Ename
                                ),
                                array(
                                    'name' => 'Title',
                                    'content' =>$postParams['dataHead']
                                ),
                                array(
                                    'name' => 'LINK',
                                    'content' =>$postParams['url'].'?AskId='.$askId.'&FeedId='.$lastfeedId.'&type=ask'
                                )

                            );
                            $viewRenderer->MandrilSendMail()->sendMailTo($email['Email'],$config['general']['mandrilEmail'],'Request for info','AskInfo',$mailData);
                        }

                    }
                    else if($postParams['mode']=='sharedetail'){

                        $insert  = $sql->insert('WF_ShareInfo');

                        $newData = array(
                            'UserId'   =>$userId,
                            'url'=>$this->bsf->isNullCheck($postParams['url'],'string'),
                            'Title'=>$this->bsf->isNullCheck($postParams['dataHead'],'string'),
                            'Remarks' =>$this->bsf->isNullCheck($postParams['remarks'],'string'),
                            'CreatedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $shareId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $FeedType='share';
                        $feedDet=$shareId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastfeedId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach ($postParams['inputdata'] as $value){
                            $select = $sql->insert('WF_ViewShare');
                            $newData = array(
                                'ShareId' => $shareId,
                                'InputValue'=> $value,
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        foreach ($postParams['allsuperioruser'] as $value){
                            $select = $sql->insert('WF_ViewTo');
                            $newData = array(
                                'ShareId' => $shareId,
                                'ViewUser'=> $value,
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Users'))
                            ->columns(array('Email','UserId'))
                            ->where(array("a.UserId"=>$postParams['allsuperioruser']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $emailId = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();

                        foreach ($emailId as $email) {

                            $mailData = array(

                                array(
                                    'name' => 'EmployeeName',
                                    'content' => $Ename
                                ),
                                array(
                                    'name' => 'Title',
                                    'content' =>$postParams['dataHead']
                                ),
                                array(
                                    'name' => 'LINK',
                                    'content' =>$postParams['url'].'?ShareId='.$shareId.'&FeedId='.$lastfeedId.'&type=share'
                                )

                            );
                            $viewRenderer->MandrilSendMail()->sendMailTo($email['Email'],$config['general']['mandrilEmail'],'share for info','AskInfo',$mailData);
                        }}
                    else if($postParams['mode']=='Birthday'){
                        $name=$this->bsf->isNullCheck($postParams['name'],'string');
                        $email=$this->bsf->isNullCheck($postParams['email'],'string');
                        $insert  = $sql->insert('WF_BirthDyWishes');
                        $newData = array(
                            'UserId'   =>$userId,
                            'Description'  => $this->bsf->isNullCheck($postParams['wishp'],'string'),
                            'WisherId'=>$this->bsf->isNullCheck($postParams['UserId'],'number'),
                            'CreatedDate' =>date('Y-m-d H:i:s'),
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $wishId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //feed insert//

                        $FeedType='birthday';
                        $feedDet=$wishId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);

                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();

                        //birthday wish mail send//
                        $mailData = array(

                            array(
                                'name' => 'EmployeeName',
                                'content' => $Ename
                            ),
                            array(
                                'name' => 'Description',
                                'content' =>$postParams['wishp']
                            )

                        );
                        $viewRenderer->MandrilSendMail()->sendMailTo($email,$config['general']['mandrilEmail'],'Greetings!!!','Birthday Wishes',$mailData);
                    } else if($postParams['mode']=='Remindcount'){

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->columns(array('*'))
                            ->where(array("a.UserId"=> $userId, "a.FeedType"=>"ask","a.DeleteFlag"=>'0'));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $remind = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $remind=count($remind);
                        $response->setContent($remind);
                        return $response;

                    } else if($postParams['mode']=='status'){
                        $status=$this->bsf->isNullCheck($postParams['status'],'string');
                        $mode=$this->bsf->isNullCheck($postParams['mode'],'string');

                        //feed Entry//
                        $FeedType='status';
                        $feedDet=$status;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastfeedId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select ->from('WF_Users')
                            ->columns(array('EmployeeName','UserLogo'))
                            ->where(array( "UserId"=>$userId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->uDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->status=$status;
                        $this->_view->mode=$mode;
                        $this->_view->lastFeedId=$lastfeedId;
                    }
                    else if($postParams['mode']=='image') {
                        $photoStatus=$this->bsf->isNullCheck($postParams['photoStatus'],'string');
                        $files = $request->getFiles();

                        $insert = $sql->insert();
                        $insert  = $sql->insert('WF_PhotoShare');
                        $newData = array(
                            'Message'=>$photoStatus,
                            'CreatedDate' =>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $photoShareId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //feed Entry//
                        $FeedType='photo';
                        $feedDet=$photoShareId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastFeedId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $displayImg=array();

                        foreach($files as $index => $file) {
                            $fileTempName = $file['tmp_name'];
                            if(!empty($file['error'][$index])) {
                                return false;
                            }

                            if(!empty($fileTempName) && is_uploaded_file($fileTempName)) {
                                $url = "public/uploads/application/uploadimage/";
                                $filename = $this->bsf->uploadFile($url, $file);
                                if ($filename) {
                                    // update valid files only
                                    $url = 'uploads/application/uploadimage/' . $filename;
                                    $insert = $sql->insert();
                                    $insert  = $sql->insert('WF_PhotoShareTrans');
                                    $newData = array(
                                        'PhotoShareId'=>$photoShareId,
                                        'ImageUrl' =>$url
                                    );
                                    $insert->values($newData);
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $displayImg[]=$url;

                                }
                            }
                        }
                        $this->_view->displayImg=$displayImg;
                        $this->_view->mode=$postParams['mode'];
                        $this->_view->lastFeedId=$lastFeedId;
                        $this->_view->photoStatus=$photoStatus;

                    }
                    else if($postParams['mode']=='Respondcount') {

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->join(array( 'c' => 'WF_AskTo'),'a.AskId=c.AskId', array('AskUser'), $select::JOIN_INNER)
                            ->columns(array('*'))
                            ->where(array("a.FeedType"=>'ask', "a.DeleteFlag"=>'0',"c.AskUser"=>$userId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $respondcount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $resCount=count($respondcount);
                        $response->setContent($resCount);
                        return $response;
                    }
                    else if($postParams['mode']=='Reminder'){
                        $arrUnitLists= array();
                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->columns(array('FeedId','AskId'=> new Expression("b.AskId"),'Remarks'=> new Expression("b.Title")))
                            ->join(array( 'b' => 'WF_AskInfo'),'a.AskId=b.AskId', array(), $select::JOIN_INNER)
                            ->where(array( "a.FeedType"=>'ask',"a.DeleteFlag"=>'0',"a.UserId"=>$userId));
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $askList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($askList as &$askLists) {
                            $select = $sql->select();
                            $select ->from(array('a' => 'WF_AskTo'))
                                ->columns(array('UserId'=> new Expression("b.UserId"),'EmployeeName'=> new Expression("b.EmployeeName"),'UserLogo'=> new Expression("b.UserLogo")))
                                ->join(array( 'b' => 'WF_Users'),'a.AskUser=b.UserId', array(), $select::JOIN_INNER)
                                ->where(array( "a.AskId"=>$askLists['AskId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $superiorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($superiorList as &$superiorLists) {
                                $dumArr1=array();
                                $dumArr1 = array(
                                    'Id' => $superiorLists['UserId'],
                                    'FeedId' => 0,
                                    'RefAskId' => $askLists['AskId'],
                                    'Description' => $superiorLists['EmployeeName'],
                                    'Type' => 'S',
                                    'LogoPath' => $superiorLists['UserLogo']
                                );
                                $arrUnitLists[] =$dumArr1;
                            }

                            $select = $sql->select();
                            $select ->from('WF_ReminderInfo')
                                ->columns(array('ReminderId','Remarks','CreatedDate','AskId'))
                                ->where(array( "AskId"=>$askLists['AskId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $historyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($historyList as &$hisList) {
                                $dumArr2=array();
                                $dumArr2 = array(
                                    'Id' => $hisList['ReminderId'],
                                    'FeedId' => 0,
                                    'RefAskId' => $hisList['AskId'],
                                    'Description' => $hisList['Remarks'],
                                    'Type' => 'H',
                                    'LogoPath' =>$hisList['CreatedDate']
                                );
                                $arrUnitLists[] =$dumArr2;
                            }

                            $dumArr=array();
                            $dumArr = array(
                                'Id' => $askLists['AskId'],
                                'FeedId' => $askLists['FeedId'],
                                'RefAskId' => 0,
                                'Description' => $askLists['Remarks'],
                                'Type' => 'A',
                                'LogoPath' => count($historyList)
                            );
                            $arrUnitLists[] =$dumArr;


                        }
                        $this->_view->arrUnitLists=$arrUnitLists;
                        $this->_view->mode=$postParams['mode'];
                    } else if($postParams['mode']=='Respondinfo') {
                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->join(array( 'b' => 'WF_AskInfo'),'a.AskId=b.AskId', array('Url','Title','Remarks','AskFor','AskId'), $select::JOIN_INNER)
                            ->join(array( 'c' => 'WF_AskTo'),'b.AskId=c.AskId', array('AskUser'), $select::JOIN_INNER)
                            ->join(array( 'd' => 'WF_Users'),'b.UserId=d.UserId', array('UserLogo','EmployeeName'), $select::JOIN_INNER)
                            ->columns(array('*'))
                            ->where(array("a.FeedType"=>'Ask', "a.DeleteFlag"=>'0',"c.AskUser"=>$userId))
                            ->order('a.FeedId desc');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $respondinfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($respondinfo as &$data){
                            $askId=$data['AskId'];
                            $feedId=$data['FeedId'];

                            $select = $sql->select();
                            $select ->from(array('a' => 'WF_DataShare'))
                                ->columns(array('*'))
                                ->where(array("a.AskId"=>$askId));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $data['dataShare'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select ->from(array('a' => 'WF_ReminderInfo'))
                                ->columns(array('*'))
                                ->where(array("a.FeedId"=>$feedId));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $data['History'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $data['Count']=count($data['History']);
                        }
                        $this->_view->respondinfo=$respondinfo;
                        $this->_view->mode=$postParams['mode'];

                    } else if($postParams['mode']=='remindresponse'){
                        $insert  = $sql->insert('WF_ReminderInfo');
                        $newData = array(
                            'UserId'   =>$userId,
                            'AskId'=>$postParams['askId'],
                            'FeedId'  =>$postParams['feedId'],
                            'Remarks'=>$postParams['remarks'],
                            'CreatedDate' =>date('Y-m-d H:i:s'),
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $reminderId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //feed Entry//
                        $FeedType='reminder';
                        $feedDet=$reminderId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastfeedId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->where(array("a.FeedId"=>$postParams['feedId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $ask = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $askId=$ask['AskId'];

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_AskTo'))
                            ->join(array( 'b' => 'WF_Users'),'b.UserId=a.AskUser', array('Email'), $select::JOIN_INNER)
                            ->join(array( 'c' => 'WF_AskInfo'),'a.AskId=c.AskId', array('Title','AskFor'), $select::JOIN_INNER)
                            ->where(array("a.AskId"=>$askId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $emailId = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();

                        foreach ($emailId as $email) {
                            $mailData = array(
                                array(
                                    'name' => 'EmployeeName',
                                    'content' => $Ename
                                ),
                                array(
                                    'name' => 'Title',
                                    'content' =>$email['Title']
                                )
                            );
                            $viewRenderer->MandrilSendMail()->sendMailTo($email['Email'],$config['general']['mandrilEmail'],'remember you for info','AskInfo',$mailData);
                        }


                    }
                    else if($postParams['mode']=='askresponse'){
                        $insert  = $sql->insert('WF_RespondInfo');
                        $newData = array(
                            'UserId'   =>$userId,
                            'FeedId'  =>$postParams['feedId'],
                            'AskId'=>$postParams['askId'],
                            'Remarks'=>$postParams['remarks'],
                            'CreatedDate' =>date('Y-m-d H:i:s'),
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $respondId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        //feed Entry//
                        $FeedType='response';
                        $feedDet=$respondId;
                        $viewRenderer->BsfShareHelper()->feedInsert($userId,$FeedType,$feedDet);
                        $lastfeedId = $dbAdapter->getDriver()->getLastGeneratedValue();


                        $update = $sql->update();
                        $update->table('WF_ReminderInfo');
                        $update->set(array(
                            'DeleteFlag'  => 1,
                        ));
                        $update->where(array('FeedId'=>$postParams['feedId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('WF_Feeds');
                        $update->set(array(
                            'DeleteFlag'  => 1,
                        ));
                        $update->where(array('FeedId'=>$postParams['feedId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_Feeds'))
                            ->where(array("a.FeedId"=>$postParams['feedId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $ask = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $askId=$ask['AskId'];

                        $select = $sql->select();
                        $select ->from(array('a' => 'WF_AskTo'))
                            ->join(array( 'b' => 'WF_Users'),'b.UserId=a.AskUser', array('Email'), $select::JOIN_INNER)
                            ->join(array( 'c' => 'WF_AskInfo'),'a.AskId=c.AskId', array('Title','AskFor'), $select::JOIN_INNER)
                            ->where(array("a.AskId"=>$askId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $emailId = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        foreach ($emailId as $email) {

                            $mailData = array(

                                array(
                                    'name' => 'EmployeeName',
                                    'content' => $Ename
                                ),
                                array(
                                    'name' => 'Title',
                                    'content' =>$email['Title']
                                ),
                            );
                            $viewRenderer->MandrilSendMail()->sendMailTo($email['Email'],$config['general']['mandrilEmail'],'respond you for info','AskInfo',$mailData);
                        }}
                    else if($postParams['mode']=='Ask' || $postParams['mode']=='share' ){
                        $select = $sql->select();
                        $select->from('WF_Users')
                            ->columns(array('LevelId'))
                            ->where(array('UserId'=>$userId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $level = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('WF_LevelMaster')
                            ->columns(array('OrderId'))
                            ->where(array('LevelId'=>$level['LevelId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $levelSelectIdDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $subSelect1 = $sql->select();
                        $subSelect1->from('WF_LevelMaster')
                            ->columns(array('LevelId'))
                            ->where->lessThan('OrderId', $levelSelectIdDetails['OrderId']);

                        $select = $sql->select();
                        $select->from('WF_Users')
                            ->columns(array('UserId', 'UserName'=>'EmployeeName'))
                            ->where->expression('LevelId IN ?', array($subSelect1));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $datashare=$postParams['dataShare'];
                        $url=$postParams['url'];
                        $dataHead=$postParams['dataHead'];
                        $this->_view->results=$results;
                        $this->_view->datashare=$datashare;
                        $this->_view->url=$url;
                        $this->_view->dataHead=$dataHead;
                        $this->_view->mode=$postParams['mode'];

                    }

                    $connection->commit();
                    $this->_view->setTerminal(true);
                    return $this->_view;
                } catch(PDOException $e) {
                    $connection->rollback();
                    $response = $this->getResponse()->setStatusCode(400);
                    return $response;
                }


            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                // Print_r($postParams); die;

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

//	public function getCity() {
//
//		if(!$this->auth->hasIdentity()) {
//				if($this->getRequest()->isXmlHttpRequest())	{
//					echo "session-expired"; exit();
//				} else {
//					$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
//				}
//			}
//			//$this->layout("layout/layout");
//			$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
//			$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
//			$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
//			$sql = new Sql($dbAdapter);
//
//		//check, if the provided ip is valid
//		if( !filter_var( $ip, FILTER_VALIDATE_IP ) )
//		{
//			throw new InvalidArgumentException("IP is not valid");
//		}
//
//		//contact ip-server
//		$response=@file_get_contents( 'http://www.netip.de/search?query='.$ip );
//
//		if( empty( $response ) )
//		{
//			throw new InvalidArgumentException( "Error contacting Geo-IP-Server" );
//		}
//		$ip='192.168.1.111';
//		//Array containing all regex-patterns necessary to extract ip-geoinfo from page
//		$patterns=array();
//		$patterns["domain"] = '#Domain: (.*?) #i';
//		$patterns["country"] = '#Country: (.*?) #i';
//		$patterns["state"] = '#State/Region: (.*?)<br#i';
//		$patterns["town"] = '#City: (.*?)<br#i';
//
//		//Array where results will be stored
//		$ipInfo=array();
//
//		//check response from ipserver for above patterns
//		foreach( $patterns as $key => $pattern )
//		{
//			//store the result in array
//
//		  //  $ipInfo[$key] = preg_match( $pattern, $response, $value ) &amp; & !empty( $value[1] ) ? $value[1] : 'not found';
//		}
//
//		/*I've included the substr function for Country to exclude the abbreviation (UK, US, etc..)
//		To use the country abbreviation, simply modify the substr statement to:
//		substr($ipInfo["country"], 0, 3)
//		*/
//	   echo $ipdata = $ipInfo["town"]. ", ".$ipInfo["state"].", ".substr($ipInfo["country"], 4);
//	die;
//		return $ipdata;
//	}


    public function setUserGeoAction(){
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
                $userGeoDetails = new Container('userGeoDetails');
                $userGeoDetails->ip = $postParams['ip'];
                $userGeoDetails->country = $postParams['country'];
                $userGeoDetails->countryCode = $postParams['countryCode'];
                $userGeoDetails->city = $postParams['city'];
                $userGeoDetails->region = $postParams['region'];
                $userGeoDetails->latitude = $postParams['latitude'];
                $userGeoDetails->longtitude = $postParams['longtitude'];
                $userGeoDetails->timezone = $postParams['timezone'];
                $result =  "success";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function addMoreFeedsAction(){
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
                //Feed Query
                $offset = 10 * $postParams['PageNo'];
                $userId = $postParams['UserId'];

                $select = $sql->select();
                $select->from(array('a' =>'WF_users'))
                    ->where(array("UserId" => $userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($postParams['FeedType'] == 'home') {
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
                        ->join(array('d' => 'WF_RespondInfo'), 'a.ResponseId=d.RespondId', array('RespondMessage'=>'Remarks'), $select::JOIN_LEFT)
                        ->join(array('f' => 'WF_ReminderInfo'), 'a.ReminderId=f.ReminderId', array('RemindMessage'=>'Remarks'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_AskInfo'), 'a.AskId=e.AskId OR d.AskId=e.AskId OR f.AskId=e.AskId', array('AskMessage'=>'Remarks','AskedBy'=>'UserId','AskUrl'=>'Url','AskFor'=>'AskFor','AskTitle'=>'Title'), $select::JOIN_LEFT)
                        ->join(array('g' => 'WF_ShareInfo'), 'a.ShareId=g.ShareId', array('ShareMessage'=>'Remarks','SharedBy'=>'UserId','ShareUrl'=>'Url','ShareTitle'=>'Title'), $select::JOIN_LEFT)
                        ->join(array('h' => 'WF_users'), 'a.UserId=h.UserId', array('UserName'=>'EmployeeName','UserAvatar'=>'Userlogo'), $select::JOIN_LEFT)
                        ->join(array('i' => 'WF_users'), 'e.UserId=i.UserId', array('AskToName'=>'EmployeeName'), $select::JOIN_LEFT)
                        ->where("((a.FeedType = 'birthday' AND a.DeleteFlag = '0' AND a.BirthdayId IN (Select BirthdyId from WF_BirthDyWishes where WisherId = '".$userId."'))
							OR (a.FeedType = 'status' AND a.DeleteFlag = '0') 
							OR (a.FeedType = 'photo' AND a.DeleteFlag = '0')
							OR (a.FeedType = 'ask' AND a.DeleteFlag = '0' AND a.AskId IN (Select AskId from WF_AskTo where AskUser = '".$userId."')) 
							OR (a.FeedType = 'response' AND a.DeleteFlag = '0' AND a.ResponseId IN (Select RespondId from WF_RespondInfo where UserId = '".$this->auth->getIdentity()->UserId."' OR AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
							OR (a.FeedType = 'reminder' AND a.DeleteFlag = '0' AND a.ReminderId IN (Select ReminderId from WF_ReminderInfo where AskId IN (Select AskId from WF_AskTo where AskUser = '".$this->auth->getIdentity()->UserId."'))) 
							OR (a.FeedType = 'share' AND a.DeleteFlag = '0' AND a.ShareId IN (Select ShareId from WF_ViewTo where ViewUser = '".$this->auth->getIdentity()->UserId."'))
							OR (a.FeedType = 'approval' AND a.FeedId = a.ParentId AND a.UserId = '".$this->auth->getIdentity()->UserId."'))")
                        ->order('a.createdDate DESC')
                        ->limit(10)
                        ->offset($offset);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->feedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParams['FeedType'] == 'user') {
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
                        ->offset($offset);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->feedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function updateModuleAction(){
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
                $moduleClickDetails = new Container('moduleClickDetails');
                $moduleClickDetails->module = $postParams['module'];
                $result = 'success';
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function changepasswordAction()	{
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
        $request= $this->getRequest();
        $response = $this->getResponse();
        $userId  = $this->auth->getIdentity()->UserId;
        $this->_view->userId = $userId;

        if($request->isXmlHttpRequest()){
            $result="failed";
            if($request->isPost()){
                $postParams = $request->getPost();
                $uId = $postParams['userid'];
                /* password available checking*/
                if($postParams['mode'] == 'Password'){

                    if($postParams['password']==" ") {
                        $encryptPassword = $postParams['password'];
                    } else {
                        $encryptPassword = $this->bsf->isNullCheck(CommonHelper::encodeString($postParams['password']),'string');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_Users'))
                        ->columns(array('Password'))
                        ->where(array('a.UserId'=>$uId,'a.Password'=>$encryptPassword,'a.DeleteFlag'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(count($resp)>0){
                        $result = "success";
                    }
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if($request->isPost()){
                $postParams = $request->getPost();

                $encryptPassword = $this->bsf->isNullCheck(CommonHelper::encodeString($postParams['new_password']),'string');

                $update = $sql->update();
                $update->table('WF_Users')
                    ->set(array('Password' => $encryptPassword));
                $update->where(array('UserId' => $userId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $this->redirect()->toRoute("workflow/default", array("controller" => "index","action" => "userprofile"));
            }
        }
        return $this->_view;
    }

    public function forgetpasswordAction(){
        if($this->auth->hasIdentity()) {
            $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
        }
        $this->layout('layout/login');
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "failed";
                $this->_view->setTerminal(true);
                $postParams = $request->getPost();
                $email = $this->bsf->isNullCheck($postParams['email'],'string');

                $select = $sql->select();
                $select->from(array('a' => 'WF_Users'))
                    ->columns(array('UserId','Email'))
                    ->where(array('a.Email'=>$email,'a.DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($resp) > 0) {
                    $userId= $resp[0]['UserId'];
                    $randomKey=$this->bsf->generateRandomString(5);
                    $link = $_SERVER['SERVER_NAME'].$this->request->getBasePath().'/application/index/reset-password/'.$this->bsf->encode($userId).'/'.$this->bsf->encode($randomKey);
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $mailData = array(
                        array(
                            'name' => 'LINK',
                            'content' => $link
                        ),
                    );
                    $viewRenderer->MandrilSendMail()->sendMailTo($email,$config['general']['mandrilEmail'],'Forgot Password Mail','forget_password',$mailData);

                    $update = $sql->update();
                    $update->table('WF_Users');
                    $update->set(array(
                        'ForgotKey'  => $randomKey,
                    ));
                    $update->where(array('UserId'=>$userId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $result =  "success";
                }

                $response = $this->getResponse()->setStatusCode('200')->setContent($result);
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
            //$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function resetPasswordAction(){
        if($this->auth->hasIdentity()) {
            $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
        }
        $this->layout('layout/login');
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
                $response = $this->getResponse()->setStatusCode('200')->setContent($result);
                return $response;
            }
        } else {

            $request = $this->getRequest();
            if ($request->isPost()) {

                //Write your Normal form post code here
                $postParams = $request->getPost();
                $userId = $this->bsf->isNullCheck($postParams['userId'],'number');

                $newPass = $this->bsf->isNullCheck(CommonHelper::encodeString(trim($postParams['new_pass'])),'string');

                $update = $sql->update();
                $update->table('WF_Users');
                $update->set(array(
                    'Password'  => $newPass,
                    'ForgotKey' => ""

                ));
                $update->where(array('UserId'=>$userId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));

            } else {
                try {
                    $userId = $this->bsf->isNullCheck($this->bsf->decode(trim($this->params()->fromRoute('userId'))), 'number');
                    $fKey = $this->bsf->isNullCheck($this->bsf->decode(trim($this->params()->fromRoute('fKey'))), 'string');

                    if(!is_numeric($userId) || trim($fKey)=="" || $userId==0) {
                        throw new \Exception('Invalid Link..!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_Users'))
                        ->columns(array('ForgotKey'))
                        ->where(array('a.UserId'=>$userId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($resp['ForgotKey']!=$fKey) {
                        throw new \Exception('Invalid Link..!');
                    }
                    $this->_view->userId = $userId;

                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }

            }

            //Common function
            //$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}