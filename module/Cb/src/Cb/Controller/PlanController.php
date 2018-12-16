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

class PlanController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
        $this->_view->messages = $this->flashMessenger()->getMessages();
	}

	public function upgradeAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Plan Upgrade");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $connection = $dbAdapter->getDriver()->getConnection();

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $userId = $this->auth->getIdentity()->CbUserId;

        $request = $this->getRequest();

        $select = $sql->select();
        $select->from('CB_SubscriberMaster')
            ->columns(array('UserPlan', 'SubcriptionDuration', 'PaymentId', 'UpgradePaymentId', 'ExpiryDate', 'EMail', 'Phone'))
            ->where(array("SubscriberId"=>$subscriberId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $subscriber = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('CB_Users')
            ->columns(array('FirstName', 'LastName', 'Mobile', 'Email'))
            ->where(array("CbUserId"=>$userId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $user = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($request->isPost()) {
            $postData = $request->getPost();
            try {
                $curPlan = strtoupper($this->bsf->isNullCheck( $this->params()->fromPost( 'curPlan' ), 'string' ));
                $upgradePlan = strtoupper($this->bsf->isNullCheck( $this->params()->fromPost( 'upgradePlan' ), 'string' ));
                $SubcriptionDuration = $this->bsf->isNullCheck($postData['subcriptionDuration'],'number');
                $reduceAmount = $this->bsf->isNullCheck($postData['reduceAmount'],'number');

                if ( $curPlan === $upgradePlan ) {
                    $this->flashMessenger()->addMessage(array('error' => 'Same plan is selected for upgrade, Please select a different plan!'));
                    $this->redirect()->toRoute('cb/index', array('controller' => 'plan', 'action' => 'upgrade'));
                }

                $discount = 0;
                if($SubcriptionDuration == '12')
                    $discount = 10;

                switch ( $upgradePlan ) {
                    case 'A':
                        $plan_amount = ((2500) * $SubcriptionDuration) - (((2500) * $SubcriptionDuration) * ($discount / 100));
                        Break;
                    case 'B':
                        $plan_amount = ((5000) * $SubcriptionDuration) - (((5000) * $SubcriptionDuration) * ($discount / 100));
                        Break;
                    case 'C':
                        $plan_amount = ((10000) * $SubcriptionDuration) - (((10000) * $SubcriptionDuration) * ($discount / 100));
                        Break;
                }

                // minus prev plan amount
                $plan_amount = $plan_amount - $reduceAmount;

                $connection->beginTransaction();

                // payment gateway
                $payment_ref = 'RABILLS-UPGRADE-' . substr( md5( date( 'Y-m-d H:i:s' ) ), 0, 10 );
                $api = new \Instamojo( $this->bsf->api_key, $this->bsf->auth_token);
                $arr_payment_request = array(
                    "purpose" => $payment_ref,
                    "amount" => $plan_amount,
                    "send_email" => false,
                    "email" => $subscriber[ 'EMail' ],
                    'buyer_name' => $user['FirstName'].$user['LastName'],
                    'phone' => $user['Mobile'],
                    "allow_repeated_payments" => false,
                    "redirect_url" => "http://".$_SERVER['SERVER_NAME'].$viewRenderer->basePath()."/cb/plan/upgrade-payment-response"
                );
                $response = $api->paymentRequestCreate($arr_payment_request);

                // create a payment log
                $insert = $sql->insert();
                $insert->into('CB_PaymentMaster');
                $insert->Values(array('PaymentRef' => $payment_ref
                                , 'PaymentRequestId' => $response['id']
                                , 'PaymentInitDate' => date('Y-m-d H:i:s')
                                , 'PaymentAmount' => $plan_amount));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $UpgradePaymentId = $dbAdapter->getDriver()->getLastGeneratedValue();

                // insert log
                $insert = $sql->insert();
                $insert->into( 'CB_UserPlanLog' );
                $insert->Values( array( 'CbUserId' => $userId, 'SubscriberId' => $subscriberId, 'LogTime' => date('Y-m-d')
                                 , 'CurPlan' => $curPlan, 'UpgradePlan' => $upgradePlan, 'SubcriptionDuration' => $SubcriptionDuration) );
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                $LogId = $dbAdapter->getDriver()->getLastGeneratedValue();

                // update upgrade payment id to subscriber
                $updateSubscriber = $sql->update();
                $updateSubscriber->table('CB_SubscriberMaster');
                $updateSubscriber->set(array('UpgradePaymentId' => $UpgradePaymentId, 'UpgradePlanLogId' => $LogId, 'SubcriptionDuration' => $SubcriptionDuration))
                    ->where(array('SubscriberId'=>$subscriberId));
                $updateStatement = $sql->getSqlStringForSqlObject($updateSubscriber);
                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();

                // payment gateway redirect
                header('Location:'.$response['longurl']);
                exit();
            } catch ( PDOException $e ) {
                $connection->rollback();
            }
        }

        $session_pref = new Container('subscriber_pref');
        $this->_view->UserPlan = strtolower($session_pref->UserPlan);
        $this->_view->SubcriptionDuration = strtolower($session_pref->SubcriptionDuration);
        if($session_pref->UserPlan != '') {
            // calculcate plan amout per day
            $payment_id = $subscriber['PaymentId'];
            if ($subscriber['UpgradePaymentId'] != '') {
                $payment_id = $subscriber['UpgradePaymentId'];
            }
            $select = $sql->select();
            $select->from('CB_PaymentMaster')
                ->columns(array("PaymentAmount", "PaymentPaidDate"))
                ->where(array("PaymentId"=>$payment_id));
            $statement = $sql->getSqlStringForSqlObject($select);
            $payment = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // find date interval
            $datetime1 = date_create(date('Y-m-d', strtotime($payment['PaymentPaidDate'])));
            $datetime2 = date_create(date('Y-m-d', strtotime($subscriber['ExpiryDate'])));
            $datetime3 = date_create(date('Y-m-d'));
            $interval = date_diff($datetime1, $datetime2);
            $actual_days = $interval->days;
            $interval = date_diff($datetime3, $datetime2);
            $days_remining = $interval->days;

            $SubcriptionDuration = $subscriber['SubcriptionDuration'];
            $discount = 0;
            if($SubcriptionDuration == '12')
                $discount = 10;

            switch ( strtoupper($subscriber['UserPlan']) ) {
                case 'A':
                    $plan_amount = ((2500) * $SubcriptionDuration) - (((2500) * $SubcriptionDuration) * ($discount / 100));
                    Break;
                case 'B':
                    $plan_amount = ((5000) * $SubcriptionDuration) - (((5000) * $SubcriptionDuration) * ($discount / 100));
                    Break;
                case 'C':
                    $plan_amount = ((10000) * $SubcriptionDuration) - (((10000) * $SubcriptionDuration) * ($discount / 100));
                    Break;
                default:
                    $plan_amount = 0;
                    break;
            }

            $amt_per_day = $plan_amount / $actual_days;
            $this->_view->reduc_amt = round($amt_per_day * $days_remining,2);
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}

	public function upgradePaymentResponseAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}

		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Upgrade");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $connection = $dbAdapter->getDriver()->getConnection();

        $subscriberId = $this->auth->getIdentity()->SubscriberId;
        $userId = $this->auth->getIdentity()->CbUserId;

        $this->_view->response = '400';

        $request = $this->getRequest();
        $payment_request_id = $this->bsf->isNullCheck($request->getQuery('payment_request_id'),'string');
        $payment_id = $this->bsf->isNullCheck($request->getQuery('payment_id'),'string');

        if($this->getRequest()->isXmlHttpRequest())	{

		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
            } else {
                if($payment_request_id != "" && $payment_id != "") {
                    $select = $sql->select();
                    $select->from(array('a' => 'CB_PaymentMaster'))
                        ->where("PaymentRequestId='$payment_request_id'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $payment = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($payment != FALSE) {
                        $api = new \Instamojo( $this->bsf->api_key, $this->bsf->auth_token);
                        $response = $api->paymentRequestStatus( $payment_request_id );

                        if ( $response[ 'status' ] == 'Completed' ) {
                            $connection->beginTransaction();

                            // update payment respose id
                            $update = $sql->update();
                            $update->table('CB_PaymentMaster')
                                ->set(array('PaymentResponseId' => $payment_id, 'PaymentPaidDate' => date('Y-m-d H:i:s')))
                                ->where(array('PaymentRequestId' => $payment_request_id));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $select = $sql->select();
                            $select->from('CB_SubscriberMaster')
                                ->columns(array('UpgradePlanLogId','EMail','ExpiryDate'))
                                ->where(array('SubscriberId'=>$subscriberId));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $subscriber  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from('CB_UserPlanLog')
                                ->where(array('LogId'=>$subscriber['UpgradePlanLogId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $PlanLog = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $startDate = strtotime($subscriber['ExpiryDate']);
                            $ExpiryDate = date( 'Y-m-d', strtotime( "+".$PlanLog['SubcriptionDuration']." months" , $startDate) );
                            $UpgradePlan = $PlanLog['UpgradePlan'];
                            switch ( $UpgradePlan ) {
                                case 'A':
                                    $NoOfUserCount = 1;
                                    $NoOfClientCount = 1;
                                    $NoOfBillCount = 2;
                                    $PlanName = 'Basic';
                                    Break;
                                case 'B':
                                    $NoOfUserCount = 1;
                                    $NoOfClientCount = 3;
                                    $NoOfBillCount = 5;
                                    $PlanName = 'Value';
                                    Break;
                                case 'C':
                                    $NoOfUserCount = 1;
                                    $NoOfClientCount = 5;
                                    $NoOfBillCount = 10;
                                    $PlanName = 'Premium';
                                    Break;
                            }

                            // update plan
                            $update = $sql->update();
                            $update->table( 'CB_SubscriberMaster' );
                            $update->set( array( 'UserPlan' => $UpgradePlan
                                          , 'NoOfUserCount' => $NoOfUserCount
                                          , 'NoOfClientCount' => $NoOfClientCount
                                          , 'NoOfBillCount' => $NoOfBillCount
                                          , 'ExpiryDate' => $ExpiryDate ) )
                                ->where( array( 'SubscriberId' => $subscriberId ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                            $connection->commit();

                            // set subscriber preferences
                            $session_pref = new Container('subscriber_pref');
                            $session_pref->UserPlan = $UpgradePlan;
                            $session_pref->NoOfUserCount = $NoOfUserCount;
                            $session_pref->NoOfClientCount = $NoOfClientCount;
                            $session_pref->NoOfBillCount = $NoOfBillCount;

                            // plan upgrade mail
                            $mailData = array(
                                array(
                                    'name' => 'PLAN',
                                    'content' => $PlanName
                                )
                            );
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo( $subscriber[ 'EMail' ], $config['general']['mandrilEmail'], 'Your account plan upgraded', 'cb_upgradesuccessful', $mailData );
                        }

                        $this->_view->response = '200';
                    } else {
                        $this->_view->response = '404';
                    }
                }
			}

			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}
}