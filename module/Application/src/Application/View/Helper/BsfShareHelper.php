<?php
namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Zend\Db\Adapter\Adapter;
use Zend\Authentication\Result;

use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Session\Container;
use Zend\Form\Element;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class BsfShareHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    protected $connection = null;

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return CustomHelper
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
        return $this;
    }

    public function getConnection($dbName)
    {
        $sm = $this->getServiceLocator()->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        $this->connection = new Adapter(array(
            'driver' => 'pdo_sqlsrv',
            'hostname' => $config['db_details']['hostname'],
            'username' => $config['db_details']['username'],
            'password' => $config['db_details']['password'],
            'database' => $dbName,
        ));
        return $this->connection;
    }


    /**
     * @param $shareId
     * @param $type
     */
    public function bsfDataShare($shareId,$type,$askId) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $viewRenderer = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');

        $sql = new Sql($dbAdapter);
        $Ename  = $this->auth->getIdentity()->EmployeeName;
        $userId  = $this->auth->getIdentity()->UserId;

        if($type=='ask') {
            $select = $sql->select();
            $select->from('WF_DataShare')
                ->columns(array('InputValue'))
                ->where(array('AskId' => $shareId));
            $select_stmt = $sql->getSqlStringForSqlObject($select);
            $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            return $result;
        }
        else if($type=='feed') {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $update = $sql->update();
                $update->table('WF_Feeds');
                $update->set(array(
                    'DeleteFlag'  => 1,
                ));
                $update->where(array('FeedId'=>$shareId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from('WF_ReminderInfo')
                    ->columns(array('ReminderId'))
                    ->where(array('FeedId' => $shareId));
                $select_stmt = $sql->getSqlStringForSqlObject($select);
                $resultremind = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $reminId=$resultremind['ReminderId'];

                $update = $sql->update();
                $update->table('WF_Feeds');
                $update->set(array(
                    'DeleteFlag'  => 1,
                ));
                $update->where(array('ReminderId'=>$reminId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('WF_ReminderInfo');
                $update->set(array(
                    'DeleteFlag'  => 1,
                ));
                $update->where(array('ReminderId'=>$reminId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $select = $sql->select();
                $select->from(array('a' => 'WF_AskInfo'))
                    ->columns(array('UserId'))
                    ->join(array( 'b' => 'WF_Feeds'), 'a.AskId=b.AskId', array("FeedId"), $select::JOIN_LEFT)
                    ->where(array('b.FeedId' => $shareId));
                $select_stmt = $sql->getSqlStringForSqlObject($select);
                $resultask = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $resuser=$resultask['UserId'];

                //echo $resuser;
                $insert = $sql->insert();
                $insert  = $sql->insert('WF_RespondInfo');
                $newData = array(
                    'UserId'   =>$userId,
                    'AskId'   =>$askId,
                    'FeedId'  =>$shareId,
                    'Remarks'=> '',
                    'CreatedDate' =>date('Y-m-d H:i:s'),
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $respondId = $dbAdapter->getDriver()->getLastGeneratedValue();

                //feed Entry//
                $FeedType='res';
                $feedDet=$respondId;
                $this->feedInsert($resuser,$FeedType,$feedDet);

                $select = $sql->select();
                $select ->from('WF_Feeds')
                    ->where(array("FeedId"=>$shareId));
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

                $connection->commit();
            }
            catch(PDOException $e) {
                $connection->rollback();

            }
            $sm = $this->getServiceLocator()->getServiceLocator();
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
            }
        }
    }
    public function bsfDataView($shareId,$type,$feedId) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        //$subscriberId = $this->auth->getIdentity()->SubscriberId;
        $sql = new Sql($dbAdapter);
        $connection = $dbAdapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $select = $sql->select();
            $select->from('WF_ViewShare')
                ->columns(array('InputValue'))
                ->where(array('ShareId' => $shareId));
            $select_stmt = $sql->getSqlStringForSqlObject($select);
            $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $update = $sql->update();
            $update->table('WF_Feeds');
            $update->set(array(
                'DeleteFlag'  => 1,
            ));
            $update->where(array('FeedId'=>$feedId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('WF_ShareInfo');
            $update->set(array(
                'DeleteFlag'  => 1,
                'ModifiedDate'=>date('Y-m-d H:i:s'),
            ));
            $update->where(array('ShareId'=>$shareId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $connection->commit();
            return $result;
        }
        catch(PDOException $e) {
            $connection->rollback();
        }
    }
    public function currenttask($module,$action,$controller) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $viewRenderer = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');

        $sql = new Sql($dbAdapter);
        $userId  = $this->auth->getIdentity()->UserId;

        $update = $sql->update();
        $update->table('WF_LogStatus');
        $update->set(array(
            'Module'  => $module,
            'Action'  => $action,
            'Controller'  => $controller,
        ));
        $update->where(array('UserId'=>$userId));
        $statement = $sql->getSqlStringForSqlObject($update);
        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
    }
    public function feedInsert($UserId,$FeedType,$FeedDetailId) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $viewRenderer = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $sql = new Sql($dbAdapter);
        $userGeoDetails = new Container('userGeoDetails');
        $geoLocation = $userGeoDetails->city .", ".$userGeoDetails->region."-".$userGeoDetails->countryCode;
        if($FeedType=='birthday'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'BirthdayId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='ask'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'AskId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='reminder'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'ReminderId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='status'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType' =>$FeedType,
                'Message'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='response'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'ResponseId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='share'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'ShareId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        else if($FeedType=='photo'){
            $insert = $sql->insert();
            $insert  = $sql->insert('WF_Feeds');
            $newData = array(
                'UserId'   =>$UserId,
                'FeedType'  =>$FeedType,
                'PhotoShareId'=>$FeedDetailId,
                'Location'=>$geoLocation,
                'CreatedDate' =>date('Y-m-d H:i:s'),
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }

    }
    public function getMultiImage($PhotoShareId) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');

        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('WF_PhotoShareTrans')
            ->columns(array('ImageUrl'))
            ->where(array('PhotoShareId' => $PhotoShareId));
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $result;
    }
    public function getPendingWorks($feedId) {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $viewRenderer = $this->getServiceLocator()->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
        $sql = new Sql($dbAdapter);
        $returnString = '';
        $select = $sql->select();
        $select->from(array('a' =>'WF_PendingWorks'))
            ->columns(array('*'))
            ->join(array('b' => 'WF_LogMaster'), 'a.LogId=b.LogId', array('RoleName'), $select::JOIN_INNER)
            ->join(array('c' => 'WF_LogTrans'), 'a.LogId=c.LogId', array('RegisterId','DBName','RefNo'), $select::JOIN_INNER)
            ->where("a.TransId IN (select PendingWorkId from WF_Feeds where ParentId = '".$feedId."')")
            ->where("a.Status=0 and a.RoleType = 'A'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $pending = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($pending as $pendingApprovalList){
            $returnString .= '<li>
					<div class="strm_appro_icon">
						<span></span>
					</div>
					<div class="strm_appro_cnt">
						<p>Please Approve Request ID '.$pendingApprovalList['RefNo'].'</p>
						<span><span><i class="fa fa-adjust"></i></span> 1 minutes ago</span>
					</div>
					<div class="strm_appro_btn">
						<p>Approve ?</p>
						<label>
							<input type="checkbox" name="checkbox" tagname="cid" cid="'.$pendingApprovalList['LogId'].'" class="ios_checkbox"/>
							<div class="ios_switch"><span></span></div>
						</label>
					</div>
				</li>';
        }
        return $returnString;
    }
}


?>