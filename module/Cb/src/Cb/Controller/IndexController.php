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

    use Zend\Db\Adapter\Adapter;

    use Zend\Authentication\Adapter\DbTable as AuthAdapter;

    use Zend\Db\Sql\Where;
    use Zend\Db\Sql\Sql;
    use Zend\Db\Sql\Expression;
    use Application\View\Helper\CommonHelper;
    use Zend\Session\Container;

    class IndexController extends AbstractActionController
    {
        public function __construct()
        {
            $this->auth = new AuthenticationService();
            $this->bsf = new \BuildsuperfastClass();

            if ($this->auth->hasIdentity()) {
                $this->identity = $this->auth->getIdentity();
            }
            $this->_view = new ViewModel();
            $this->_view->messages = $this->flashMessenger()->getMessages();
        }

        public function loginAction() {
            if($this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index","action" => "dashboard"));
                }
            }

            // csrf validation
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
                        $this->redirect()->toRoute("cb/default", array("controller" => "index","action" => "login"));
                        return;
                    }
                }
            } else {
                $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $this->_process($postParams);
            }
            return $this->_view;
        }

        public function activationAction() {
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Account Activation");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $subscriberId = $this->auth->getIdentity()->SubscriberId;

            if($this->_subActive($subscriberId) == 1) {
                $this->redirect()->toRoute("cb/index", array("controller" => "index","action" => "dashboard"));
            }

            // csrf validation
            if($this->getRequest()->isPost()) {
                $response = $this->getResponse();
                if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                    // CSRF attack
                    if($this->getRequest()->isXmlHttpRequest())	{
                        // AJAX
                        $response->setStatusCode(401)
                            ->setContent('CSRF attack');
                        return $response;
                    } else {
                        // Normal
                        $this->redirect()->toRoute("cb/default", array("controller" => "index","action" => "login"));
                        return;
                    }
                }
            } else {
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            $this->_view->message = 0;
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                //echo '<pre>'; print_r($postData); die;

                $ucode = strtoupper($postData['code']);
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                //fetching subscriber data
                $select = $sql->select();
                $select->from('CB_SubscriberMaster')
                    ->columns(array("*"))
                    ->where(array("SubscriberId"=>$subscriberId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $subscriberData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $sbName = $subscriberData->BusinessName;

                if($ucode==$subscriberData['ActivationCode']) {
                    $updateSubscriber = $sql->update();
                    $updateSubscriber->table('CB_SubscriberMaster');
                    $updateSubscriber->set(array('IsActive' => $this->bsf->isNullCheck('1','number')
                                           , 'ActivationCode' => $this->bsf->isNullCheck('','string')))
                        ->where(array('SubscriberId'=>$subscriberId));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateSubscriber);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Subscriber-Master-Edit',$subscriberId,$sbName,$dbAdapter);

                    //updating logincount
                    $updateCount = $sql->update();
                    $updateCount->table('CB_Users');
                    $updateCount->set(array('LoginCount' => $this->bsf->isNullCheck('1','number')))
                        ->where(array('CbUserId'=>$this->auth->getIdentity()->CbUserId));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateCount);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'setup'));
                } else {
                    $this->_view->message = 1;
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }

        //start login process
        protected function _process($values, $encript = false) {
            //Get our authentication adapter and check credentials
            $password = CommonHelper::encodeString($values['password']);
            $adapter = $this->_getAuthAdapter($encript);
            $adapter->setIdentity($values['username']);
            $adapter->setCredential($password);

            $this->auth = new AuthenticationService();
            $result = $this->auth->authenticate($adapter);
            switch ($result->getCode()) {
                case Result::FAILURE_IDENTITY_NOT_FOUND:
                    // do stuff for nonexistent identity
                    $this->flashMessenger()->addMessage(array('error' => 'User Name does not Exist'));
                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                    break;
                case Result::FAILURE_CREDENTIAL_INVALID:
                    // do stuff for invalid credential
                    $this->flashMessenger()->addMessage(array('error' => 'User Name / Password incorrect'));
                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
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
                    $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
                    $sql = new Sql($dbAdapter);

                    //fetching subscriber data
                    $select = $sql->select();
                    $select->from('CB_SubscriberMaster')
                        ->columns(array("*"))
                        ->where(array("SubscriberId"=>$userData['SubscriberId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $subscriberData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $session_pref = new Container('subscriber_pref');
                    if ($subscriberData['IsDelete']==1) {
                        $this->flashMessenger()->addMessage(array('error' => 'Your Account is Expired'));
                        $this->auth->clearIdentity();
                        //                    $session_pref->getManager()->getStorage()->clear();
                        $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                    } else if($subscriberData['IsActive']==1) {
                        //updating logincount
                        $userData['LoginCount'] += 1;
                        $updateCount = $sql->update();
                        $updateCount->table('CB_Users');
                        $updateCount->set(array('LoginCount' => $this->bsf->isNullCheck($userData['LoginCount'],'number')))
                            ->where(array('CbUserId'=>$userData['CbUserId']));
                        $updateStatement = $sql->getSqlStringForSqlObject($updateCount);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // set subscriber preferences
                        $session_pref->UserPlan = $subscriberData['UserPlan'];
                        $session_pref->SubcriptionDuration = $subscriberData['SubcriptionDuration'];
                        $session_pref->NoOfUserCount = $subscriberData['NoOfUserCount'];
                        $session_pref->NoOfClientCount = $subscriberData['NoOfClientCount'];
                        $session_pref->NoOfBillCount = $subscriberData['NoOfBillCount'];

                        $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'dashboard'));
                    } else {
                        if($userData['UType']==1) {
                            if($subscriberData['ActivationCode']!='') {
                                //$this->auth->clearIdentity();
                                $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'activation'));
                            } else {
                                $this->flashMessenger()->addMessage(array('error' => 'Your Account is not activated'));
                                $this->auth->clearIdentity();
                                $session_pref->getManager()->getStorage()->clear();
                                $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                            }
                        } else {
                            $this->flashMessenger()->addMessage(array('error' => 'Your Account is not activated'));
                            $this->auth->clearIdentity();
                            $session_pref->getManager()->getStorage()->clear();

                            $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                        }
                    }

                    break;
                default:
                    // do stuff for other failure
                    break;
            }
            return false;
        }
        //end login process

        //start auth process for auth session
        protected function _getAuthAdapter($encript)
        {
            $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
            $authAdapter = new AuthAdapter($dbAdapter,
                                           'CB_Users', // there is a method setTableName to do the same
                                           'Username', // there is a method setIdentityColumn to do the same
                                           'Password' // there is a method setCredentialColumn to do the same
            );
            return $authAdapter;
        }
        //end auth process for auth session

        //start auth process for auth session
        protected function _getAuthAdapterN($encript)
        {
            $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
            $authAdapter = new AuthAdapter($dbAdapter,
                                           'CB_Users', // there is a method setTableName to do the same
                                           'Email', // there is a method setIdentityColumn to do the same
                                           'Password' // there is a method setCredentialColumn to do the same
            );
            return $authAdapter;
        }
        //end auth process for auth session

        //start logout action
        public function logoutAction()	{
            $this->auth = new AuthenticationService();

            if ($this->auth->hasIdentity()) {
                $identity = $this->auth->getIdentity();
            }

            $this->auth->clearIdentity();
            $session_pref = new Container('subscriber_pref');
            $session_pref->getManager()->getStorage()->clear();

            return $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
        }
        //end logout action

        public function signupAction()
        {
            if($this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index", "action" => "dashboard"));
                }
            }
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Signup");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $connection = $dbAdapter->getDriver()->getConnection();
            $sql = new Sql($dbAdapter);

            $this->_view->success = 0;

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $select = $sql->select();
                $select->from('CB_Users')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("Username = '".$postData['username']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $uResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('CB_Users')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("Email = '".$postData['email']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $eResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($uResult['Count'] == 0 && $eResult['Count'] == 0) {
                    $activationCode = $this->bsf->activationCode();

                    $Plan = strtoupper($this->bsf->isNullCheck($postData['plan'],'string'));
                    $SubcriptionDuration = $this->bsf->isNullCheck($postData['subcriptionDuration'],'number');
                    $ExpiryDate = date( 'Y-m-d', strtotime( "+".$SubcriptionDuration." months" ) );

                    $discount = 0;
                    if($SubcriptionDuration == '6')
                        $discount = 5;
                    else if($SubcriptionDuration == '12')
                        $discount = 10;

                    switch ( $Plan ) {
                        case 'A':
                            $NoOfUserCount = 1;
                            $NoOfClientCount = 1;
                            $NoOfBillCount = 2;
                            $plan_amount = ((2500) * $SubcriptionDuration) - (((2500) * $SubcriptionDuration) * ($discount / 100));
                            Break;
                        case 'B':
                            $NoOfUserCount = 1;
                            $NoOfClientCount = 3;
                            $NoOfBillCount = 5;
                            $plan_amount = ((5000) * $SubcriptionDuration) - (((5000) * $SubcriptionDuration) * ($discount / 100));
                            Break;
                        case 'C':
                            $NoOfUserCount = 1;
                            $NoOfClientCount = 5;
                            $NoOfBillCount = 10;
                            $plan_amount = ((10000) * $SubcriptionDuration) - (((10000) * $SubcriptionDuration) * ($discount / 100));
                            Break;
                        default: // free trial
                            $NoOfUserCount = 1;
                            $NoOfClientCount = 1;
                            $NoOfBillCount = 2;
                            $plan_amount = '';
                            $ExpiryDate = date( 'Y-m-d', strtotime( "+15 days" ) );
                            break;
                    }

                    try {
                        $connection->beginTransaction();

                        $PaymentId = 0;
                        if($Plan != "") {
                            // payment gateway
                            $payment_ref = 'RABILLS-' . substr( md5( date( 'Y-m-d H:i:s' ) ), 0, 10 );
                            $api = new \Instamojo( $this->bsf->api_key, $this->bsf->auth_token);
                            $response = $api->paymentRequestCreate(
                                array(
                                    "purpose" => $payment_ref,
                                    "amount" => $plan_amount,
                                    "send_email" => false,
                                    "email" => $postData[ 'email' ],
                                    "allow_repeated_payments" => false,
                                    "redirect_url" => "http://".$_SERVER['SERVER_NAME'].$viewRenderer->basePath()."/cb/index/payment-response"
                                )
                            );

                            // create a payment log
                            $insert = $sql->insert();
                            $insert->into('CB_PaymentMaster');
                            $insert->Values(array('PaymentRef' => $payment_ref
                                            , 'PaymentRequestId' => $response['id']
                                            , 'PaymentInitDate' => date('Y-m-d H:i:s')
                                            , 'PaymentAmount' => $plan_amount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $PaymentId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        // create a subscriber
                        $insert = $sql->insert();
                        $insert->into('CB_SubscriberMaster');
                        $insert->Values(array('BusinessName' => $this->bsf->isNullCheck($postData['businessName'],'string')
                                        , 'BusinessType' => $this->bsf->isNullCheck($postData['businessType'],'number')
                                        , 'EMail' =>  $this->bsf->isNullCheck($postData['email'],'string')
                                        , 'Address' => $this->bsf->isNullCheck('','string')
                                        , 'CityId' => $this->bsf->isNullCheck($postData['cityId'],'number')
                                        , 'StateID' => $this->bsf->isNullCheck('','number')
                                        , 'CountryId' => $this->bsf->isNullCheck('','number')
                                        , 'PinCode' => $this->bsf->isNullCheck('','string')
                                        , 'Phone' => $this->bsf->isNullCheck('','string')
                                        , 'Fax' => $this->bsf->isNullCheck('','string')
                                        , 'Website' => $this->bsf->isNullCheck('','string')
                                        , 'ContactPerson' => $this->bsf->isNullCheck('','string')
                                        , 'Pan' => $this->bsf->isNullCheck('','string')
                                        , 'Tan' => $this->bsf->isNullCheck('','string')
                                        , 'Tin' => $this->bsf->isNullCheck('','string')
                                        , 'Logo' => $this->bsf->isNullCheck('','string')
                                        , 'IsDelete' => $this->bsf->isNullCheck('0','number')
                                        , 'IsActive' => $this->bsf->isNullCheck('0','number')
                                        , 'NumberOfUsers' => $this->bsf->isNullCheck('0','number')
                                        , 'ActivationCode' => $this->bsf->isNullCheck($activationCode,'string')
                                        , 'UserPlan' => $Plan
                                        , 'NoOfUserCount' => $NoOfUserCount
                                        , 'NoOfClientCount' => $NoOfClientCount
                                        , 'NoOfBillCount' => $NoOfBillCount
                                        , 'ExpiryDate' => $ExpiryDate
                                        , 'SubcriptionDuration' => $SubcriptionDuration
                                        , 'PaymentId' => $PaymentId
                                        , 'CreatedDate' => date('Y-m-d')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $subscriberId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        // create a user
                        $insert = $sql->insert();
                        $insert->into('CB_Users');
                        $insert->Values(array('SubscriberId' => $this->bsf->isNullCheck($subscriberId,'number')
                                        , 'Username' =>  $this->bsf->isNullCheck($postData['username'],'string')
                                        , 'Password' => $this->bsf->isNullCheck(CommonHelper::encodeString($postData['password']),'string')
                                        , 'FirstName' => $this->bsf->isNullCheck($postData['firstName'],'string')
                                        , 'LastName' => $this->bsf->isNullCheck($postData['lastName'],'string')
                                        , 'Email' =>  $this->bsf->isNullCheck($postData['email'],'string')
                                        , 'Mobile' => $this->bsf->isNullCheck($postData['mobile'],'string')
                                        , 'UType' => $this->bsf->isNullCheck('1','number')
                                        , 'LoginCount' => $this->bsf->isNullCheck('0','number')
                                        , 'IsDelete' => $this->bsf->isNullCheck('0','number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $connection->commit();
                        if($Plan != "") {
                            // payment gateway redirect
                            header('Location:'.$response['longurl']);
                            exit();
                        } else {
                            //welcome mail
                            $mailData = array();
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo($postData['email'],$config['general']['mandrilEmail'],'Welcome to RA Bills','cb_welcome',$mailData);

                            //activation mail
                            $mailData = array(
                                array(
                                    'name' => 'USERNAME',
                                    'content' => $postData[ 'username' ]
                                ),
                                array(
                                    'name' => 'ACTIVATIONCODE',
                                    'content' => $activationCode
                                ),
                            );
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo( $postData[ 'email' ], $config['general']['mandrilEmail'], 'Activate your account', 'cb_registeredsuccessfully', $mailData );
                        }

                        $this->_view->success = 1;

                    } catch (Exception $e) {
                        $connection->rollback();
                        $this->flashMessenger()->addMessage(array('error' => 'Signup failed. Try again later!'));
                    }
                }
            }

            //Fetching data from City Master
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId','CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->cityMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->businessType = $this->bsf->getBusinessType();

            $this->_view->plan = $this->params()->fromQuery('plan');
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

        public function userSignupAction()
        {
            if($this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index", "action" => "dashboard"));
                }
            }

            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Sign Up");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $this->_view->subscriberId = $this->params()->fromRoute('subId');

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $subscriberId = CommonHelper::decodeString($postData['subscriberId']);

                $select = $sql->select();
                $select->from('CB_Users')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("Username = '".$postData['username']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $uResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('CB_Users')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("Email = '".$postData['email']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $eResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($uResult['Count'] == 0 && $eResult['Count'] == 0) {
                    $insert = $sql->insert();
                    $insert->into('CB_Users');
                    $insert->Values(array('SubscriberId' => $this->bsf->isNullCheck($subscriberId,'number')
                                    , 'Username' =>  $this->bsf->isNullCheck($postData['username'],'string')
                                    , 'Password' => $this->bsf->isNullCheck(CommonHelper::encodeString($postData['password']),'string')
                                    , 'FirstName' => $this->bsf->isNullCheck($postData['firstName'],'string')
                                    , 'LastName' => $this->bsf->isNullCheck($postData['lastName'],'string')
                                    , 'Email' =>  $this->bsf->isNullCheck($postData['email'],'string')
                                    , 'Mobile' => $this->bsf->isNullCheck($postData['mobile'],'string')
                                    , 'UType' => $this->bsf->isNullCheck('2','number')
                                    , 'LoginCount' => $this->bsf->isNullCheck('0','number')
                                    , 'IsDelete' => $this->bsf->isNullCheck('0','number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('CB_SubscriberMaster')
                        ->columns(array("NumberOfUsers"))
                        ->where(array("SubscriberId"=>$subscriberId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $subscriberData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $subscriberData['NumberOfUsers'] += 1;
                    $updateSubscriber = $sql->update();
                    $updateSubscriber->table('CB_SubscriberMaster');
                    $updateSubscriber->set(array('NumberOfUsers' => $this->bsf->isNullCheck($subscriberData['NumberOfUsers'],'number')))
                        ->where(array('SubscriberId'=>$subscriberId));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateSubscriber);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                }
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

        public function forgotPasswordAction()
        {
            if($this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index","action" => "dashboard"));
                }
            }
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $this->_view->success = 0;
            $this->_view->message = 0;

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $passwordCode = $this->bsf->activationCode();
                $code = strtolower($passwordCode);

                $select = $sql->select();
                $select->from('CB_Users')
                    ->columns(array("*"))
                    ->where("Email = '".$postParams['email']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($result)==1) {
                    $updateCount = $sql->update();
                    $updateCount->table('CB_Users');
                    $updateCount->set(array('Password' => $this->bsf->isNullCheck(CommonHelper::encodeString($code),'string')))
                        ->where(array('CbUserId'=>$result[0]['CbUserId']));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateCount);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $cbuid = $this->bsf->encode($result[0]['CbUserId']);
                    $rcode = $this->bsf->encode($code);
                    $link = $_SERVER['SERVER_NAME'].$this->request->getBasePath().'/cb/index/reset-password/'.$cbuid.'/'.$rcode;

                    $mailData = array(
                        array(
                            'name' => 'CODE',
                            'content' => $code
                        ),
                        array(
                            'name' => 'LINK',
                            'content' => $link
                        ),
                    );
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $viewRenderer->MandrilSendMail()->sendMailTo($postParams['email'],$config['general']['mandrilEmail'],'Forgot Password Mail','cb_forgetpassword',$mailData);

                    $this->_view->success = 1;
                } else {
                    $this->_view->message = 1;
                }
            }
            return $this->_view;
        }

        public function resetPasswordAction()
        {
            if($this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index","action" => "dashboard"));
                }
            }
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $this->_view->cbuid = $this->params()->fromRoute('id');
            $this->_view->code = $this->params()->fromRoute('code');
            $this->_view->message = 0;

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $hcode = $this->bsf->decode($postParams['hcode']);
                $cbuid = $this->bsf->decode($postParams['cbuid']);

                if($postParams['code']==$hcode) {
                    $updateCount = $sql->update();
                    $updateCount->table('CB_Users');
                    $updateCount->set(array('Password' => $this->bsf->isNullCheck(CommonHelper::encodeString($postParams['password']),'string')))
                        ->where(array('CbUserId'=>$cbuid));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateCount);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'login'));
                } else {
                    $this->_view->message = 1;
                }
            }
            return $this->_view;
        }

        public function setupAction()
        {
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index", "action" => "login"));
                }
            }
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            $subscriberId = $this->auth->getIdentity()->SubscriberId;

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
                    $postData = $request->getPost();
                    $files = $request->getFiles();
                    $subscriberId = $postData['subscriberId'];

                    $docExt = '';
                    if($files['logo']['name']){
                        $dir = 'public/uploads/cb/company/';
                        if(!is_dir($dir))
                            mkdir($dir, 0755, true);

                        $docExt = pathinfo($files['logo']['name'], PATHINFO_EXTENSION);
                        $path = $dir.$subscriberId.'.'.$docExt;
                        move_uploaded_file($files['logo']['tmp_name'], $path);
                    }

                    $businessName = $this->bsf->isNullCheck($postData['businessName'],'string');
                    $website = $this->bsf->addHttp($postData['website']);

                    $updateSubscriber = $sql->update();
                    $updateSubscriber->table('CB_SubscriberMaster');
                    $updateSubscriber->set(array('BusinessName' => $this->bsf->isNullCheck($postData['businessName'],'string')
                                           , 'Address' => $this->bsf->isNullCheck($postData['address'],'string')
                                           , 'CityId' => $this->bsf->isNullCheck($postData['cityId'],'number')
                                           , 'StateID' => $this->bsf->isNullCheck($postData['stateId'],'number')
                                           , 'CountryId' => $this->bsf->isNullCheck($postData['countryId'],'number')
                                           , 'PinCode' => $this->bsf->isNullCheck($postData['pinCode'],'string')
                                           , 'Phone' => $this->bsf->isNullCheck($postData['phone'],'string')
                                           , 'Website' => $this->bsf->isNullCheck($website,'string')
                                           , 'ContactPerson' => $this->bsf->isNullCheck($postData['contactPerson'],'string')
                                           , 'Pan' => $this->bsf->isNullCheck($postData['pan'],'string')
                                           , 'Tan' => $this->bsf->isNullCheck($postData['tan'],'string')
                                           , 'Tin' => $this->bsf->isNullCheck($postData['tin'],'string')
                                           , 'Logo' => $this->bsf->isNullCheck($docExt,'string')))
                        ->where(array('SubscriberId'=>$subscriberId));
                    $updateStatement = $sql->getSqlStringForSqlObject($updateSubscriber);
                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    CommonHelper::insertCBLog('Subscriber-Master-Edit',$subscriberId,$businessName,$dbAdapter);

                    $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'dashboard'));
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

                //Fetching data from Subscriber Master
                $select = $sql->select();
                $select->from('CB_SubscriberMaster')
                    ->columns(array("*"))
                    ->where(array("SubscriberId"=>$subscriberId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->subscriber = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //Fetching data from City Master
                $select = $sql->select();
                $select->from('WF_CityMaster')
                    ->columns(array('CityId','CityName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->cityMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Fetching data from State Master
                $select = $sql->select();
                $select->from('WF_StateMaster')
                    ->columns(array('StateID','StateName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->stateMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Fetching data from Country Master
                $select = $sql->select();
                $select->from('WF_CountryMaster')
                    ->columns(array('CountryId','CountryName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->countryMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                return $this->_view;
            }
        }

        public function addUserAction()
        {
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("cb/index", array("controller" => "index", "action" => "login"));
                }
            }
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);

            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $subId = CommonHelper::encodeString($this->auth->getIdentity()->SubscriberId);
                $link = $this->request->getBasePath().'/cb/index/user-signup/'.$subId;

                $mailData = array(
                    array(
                        'name' => 'LINK',
                        'content' => $link
                    ),
                );
                $sm = $this->getServiceLocator();
                $config = $sm->get('application')->getConfig();
                $viewRenderer->MandrilSendMail()->sendMailTo($postParams['email'],$config['general']['mandrilEmail'],'Sign up your account','cb_welcome_signup',$mailData);
                $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'dashboard'));
            } else {
                $subscriberId = $this->auth->getIdentity()->SubscriberId;

                $select = $sql->select();
                $select->from( array('a' => 'CB_Users' ))
                    ->columns( array( 'CbUserId') )
                    ->where(array('a.IsDelete' => '0', 'a.SubscriberId' => $subscriberId));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $users = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $session_pref = new Container('subscriber_pref');
                if(count($users) >= $session_pref->NoOfUserCount) {
                    $this->_view->allowAddUser = false;
                    $this->_view->NoOfUserCount = $session_pref->NoOfUserCount;
                }
            }
            return $this->_view;
        }

        public function adminPanelAction()
        {
            /*if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }*/
            //$this->layout("layout/layout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );

            //Fetching data from City Master
            $select = $sql->select();
            $select->from('WF_CityMaster')
                ->columns(array('CityId','CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->cityMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from( array( 's' => 'CB_SubscriberMaster' ))
                ->join( array('su' => 'CB_Users'), 'su.SubscriberId = s.SubscriberId', array('Mobile'), $select::JOIN_LEFT)
                ->join( array('cm' => 'WF_CityMaster'), 'cm.CityId = s.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns( array( 'SubscriberId', 'BusinessName', 'EMail', 'Address', 'NumberOfUsers', 'IsActive'))
                ->where("su.UType = '1'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->subscribers = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }

        public function editSubscriberAction()
        {
            /*if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }*/

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
                        $subscriberId = $this->params()->fromPost('subscriberId');
                        if(null !== ($this->params()->fromPost('isActive'))) {
                            $isActive = $this->params()->fromPost('isActive');
                        } else {
                            $isActive = '1';
                        }

                        $sql = new Sql($dbAdapter);
                        $response = $this->getResponse();
                        $connection->beginTransaction();

                        $update = $sql->update();
                        $update->table('CB_SubscriberMaster')
                            ->set(array('IsActive' => $isActive))
                            ->where(array('SubscriberId' => $subscriberId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //CommonHelper::insertCBLog('Subscriber-Master-Edit',$subscriberId,$businessName,$dbAdapter);

                        $connection->commit();

                        $status = 'Edit';
                    } catch (PDOException $e) {
                        $connection->rollback();
                        print "Error!: " . $e->getMessage() . "</br>";
                    }

                    $response->setContent($status);
                    return $response;
                }
            }
        }

        public function checkUsernameEmailAction()
        {
            $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $postParams = $request->getPost();

                    $arrCount = array();
                    $sql     = new Sql($dbAdapter);
                    $select1 = $sql->select();
                    $select1->from('CB_Users')
                        ->columns(array("uCount" => new Expression("Count(*)")))
                        ->where("Username = '".$postParams['userName']."'");
                    $statement1 = $sql->getSqlStringForSqlObject($select1);
                    $result1 = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $arrCount['uCount'] = $result1['uCount'];

                    $select2 = $sql->select();
                    $select2->from('CB_Users')
                        ->columns(array("eCount" => new Expression("Count(*)")))
                        ->where("Email = '".$postParams['eMail']."'");
                    $statement2 = $sql->getSqlStringForSqlObject($select2);
                    $result2 = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $arrCount['eCount'] = $result2['eCount'];
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($arrCount));
                return $response;
            }
        }

        public function checkEmailAction()
        {
            $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $postParams = $request->getPost();
                    $sql = new Sql($dbAdapter);
                    $select = $sql->select();
                    $select->from('CB_Users')
                        ->columns(array("Count" => new Expression("Count(*)")))
                        ->where("Email = '".$postParams['eMail']."'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        }

        /*public function checkSubscriberAction()
        {
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
                        $subscriberId = $this->params()->fromPost('subscriberId');
                        $businessName = $this->params()->fromPost('businessName');

                        $sql = new Sql($dbAdapter);
                        $response = $this->getResponse();
                        $select = $sql->select();

                        $select->from( array( 's' => 'CB_SubscriberMaster' ))
                                ->columns( array( 'SubscriberId'))
                                ->where( "BusinessName = '$businessName' and IsActive = 1");

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        $status = json_encode(array('results' => $results));
                    } catch (PDOException $e) {
                        $connection->rollback();
                        print "Error!: " . $e->getMessage() . "</br>";
                    }
                    $response->setContent($status);
                    return $response;
                }
            }
        }*/

        public function dashboardAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }
            $subscriberId = $this->auth->getIdentity()->SubscriberId;
            if($this->_subActive($subscriberId) == 0) {
                $this->redirect()->toRoute('cb/index', array('controller' => 'index', 'action' => 'activation'));
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Dashboard");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );



            if($this->getRequest()->isXmlHttpRequest())	{

                $request = $this->getRequest();
                if ($request->isPost()) {

                    $toDate = date('Y-m-d', strtotime($this->params()->fromPost('date')));
                    if (strtotime($toDate) > strtotime(date('Y-m-d'))) {
                        $toDate = date('Y-m-d');
                    }
                    $fromDate = date('Y-01-01', strtotime($this->params()->fromPost('date')));

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_WORegister'))
                        ->columns(array('Mon' => new Expression("month(WODate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),10) + '-' + ltrim(str(Year(WODate)))"),'OrderAmount' =>new Expression("sum(OrderAmount)"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                        ->where("a.DeleteFlag='0' and a.LiveWO ='0' AND a.SubscriberId = '$subscriberId' and WODate >= '$fromDate' and WODate <= '$toDate' AND a.SubscriberId = '$subscriberId'")
                        ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),10),Year(WODate)'));


                    $select1 = $sql->select();
                    $select1->from(array('a' => 'CB_BillMaster'))
                        ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                        ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),10) + '-' + ltrim(str(Year(BillDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount' =>new Expression("sum(Case When CertifyAmount <>0 then CertifyAmount else SubmitAmount end)"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                        ->where("a.DeleteFlag='0' and BillDate >= '$fromDate' and BillDate <= '$toDate' AND b.SubscriberId = '$subscriberId'")
                        ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),10),Year(BillDate)'));
                    $select1->combine($select,'Union ALL');

                    $select2 = $sql->select();
                    $select2->from(array('a' => 'CB_ReceiptRegister'))
                        ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                        ->columns(array('Mon' => new Expression("month(ReceiptDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),10) + '-' + ltrim(str(Year(ReceiptDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount' =>new Expression("sum(Amount)")))
                        ->where("a.DeleteFlag='0' and ReceiptDate >= '$fromDate' and ReceiptDate <= '$toDate' AND b.SubscriberId = '$subscriberId'")
                        ->group(new Expression('month(ReceiptDate), LEFT(DATENAME(MONTH,ReceiptDate),10),Year(ReceiptDate)'));
                    $select2->combine($select1,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$select2))
                        ->columns(array('Mon', 'Mondata', 'OrderAmount'=>new Expression("sum(g.OrderAmount)") ,'BillAmount'=> new Expression("sum(g.BillAmount)"),'ReceiptAmount'=>new Expression("sum(g.ReceiptAmount)")))
                        ->group(array('g.Mon','g.Mondata'));
                    $statement = $sql->getSqlStringForSqlObject($select3);

                    $reportData = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $serialChartData = array(
                        'orders' => array(),
                        'bills' => array(),
                        'receipts' => array()
                    );

                    $totOrderAmt = 0;
                    $totBillAmt = 0;
                    $totReceiptAmt = 0;

                    for($i=11; $i>=0; $i--) {
                        $data['date'] = date('Y-m-t', strtotime("-$i month " . $toDate));
                        if(date('m', strtotime($data['date'])) == date('m')) {
                            $data['date'] = date('Y-m-d');
                        }

                        // order
                        $data['order'] = 0;
                        $isExists = false;
                        foreach($reportData as $report) {
                            if(isset($report['Mon']) && $report['Mon'] == date('m', strtotime($data['date']))) {
                                $data['order'] = $report['OrderAmount'];
                                $data['bill'] = $report['BillAmount'];
                                $data['receipt'] = $report['ReceiptAmount'];
                                $isExists = true;
                                break;
                            }
                        }

                        if($isExists) {

                            $serialChartData['orders'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'order' => $data['order']
                            );

                            $serialChartData['bills'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'bill' => $data['bill']
                            );

                            $serialChartData['receipts'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'receipt' => $data['receipt']
                            );

                            $totOrderAmt += $data['order'];
                            $totBillAmt += $data['bill'];
                            $totReceiptAmt += $data['receipt'];
                        } else {

                            $serialChartData['orders'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'order' => 0
                            );

                            $serialChartData['bills'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'bill' => 0
                            );

                            $serialChartData['receipts'][] = array(
                                'month' => date('M', strtotime($data['date'])),
                                'receipt' => 0
                            );
                        }
                    }

                    $result =  json_encode(array(
                                               'serialChartData' => $serialChartData,
                                               'avgOrderPercentage' => round(($totOrderAmt / 12)),
                                               'avgBillPercentage' => round(($totBillAmt / 12) ),
                                               'avgReceiptPercentage' => round(($totReceiptAmt / 12))
                                           ));
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent($result);
                    return $response;
                }

            } else {
                /* order detail */
                $select = $sql->select();
                $select->from('CB_WORegister')
                    ->columns(array('totOrders' =>new Expression("Count(WorkOrderId)"), 'totOrderAmt' =>new Expression("Sum(OrderAmount)")))
                    ->where("DeleteFlag='0' and LiveWO ='0' AND SubscriberId = '$subscriberId'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $order = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAbstract'))
                    ->join(array('b' => 'CB_BillMaster'), 'b.BillId = a.BillId', array(), $select::JOIN_LEFT)
                    ->columns(array('curAmount' =>new Expression("Sum(CerCurAmount)")))
                    ->where("a.BillFormatId='1' AND b.SubscriberId = '$subscriberId' AND b.DeleteFlag='0'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $workvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $order['orderCompleted'] = (double)$workvalue['curAmount'];
                $order['orderNotBilled'] = (double)($order['totOrderAmt'] - $workvalue['curAmount']);
                if($order['totOrderAmt']!=0){
                    $order['orderCompletedPercentage'] = round(($order['orderCompleted'] / $order['totOrderAmt']) * 100);
                } else {
                    $order['orderCompletedPercentage'] = 0;
                }
                $this->_view->order = $order;

                /* BILL detail */
                $select = $sql->select();
                $select->from( array('a' => 'CB_BillMaster' ))
                    ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(), $select::JOIN_LEFT)
                    ->columns(array('totProjects' =>new Expression("Count(Distinct(ProjectId))")))
                    ->where("a.DeleteFlag='0' AND b.LiveWO ='0' AND a.SubscriberId = '$subscriberId'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $project = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillMaster'))
                    ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('submitAmount' =>new Expression("Sum(SubmitAmount)"), 'certifyAmount' =>new Expression("Sum(CertifyAmount)")))
                    ->where("a.DeleteFlag='0' AND b.SubscriberId = '$subscriberId'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $billMaster['difference'] = (double)($billMaster['submitAmount'] - $billMaster['certifyAmount']);
                $billMaster['totProjects'] = $project['totProjects'];
                if($billMaster['certifyAmount'] == 0 || $billMaster['submitAmount'] == 0) {
                    $billMaster['billCompletedPercentage'] = 0;
                } else {
                    $billMaster['billCompletedPercentage'] = round(($billMaster['certifyAmount'] / $billMaster['submitAmount'] ) * 100);
                }
                $this->_view->bill = $billMaster;

                /* RECEIPT detail */
                $select = $sql->select();
                $select->from(array('a' =>'CB_ReceiptRegister'))
                    ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('totReceived' =>new Expression("Sum(a.Amount)")))
                    ->where("a.DeleteFlag='0' AND a.ReceiptAgainst='B' AND b.SubscriberId = '$subscriberId'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                $select = $sql->select();
                $select->from(array('a' => 'CB_ReceiptRegister'))
                    ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array(),$select:: JOIN_LEFT)
                    ->columns(array('Amount' =>new Expression("Sum(Amount)")))
                    ->where("a.DeleteFlag='0' and a.ReceiptAgainst='M' and b.SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receiptAdvanceRec = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAdvanceRecovery'))
                    ->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(),$select:: JOIN_LEFT)
                    ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(),$select:: JOIN_LEFT)
                    ->join(array('d' => 'CB_WORegister'), 'c.WORegisterId=d.WorkOrderId', array(),$select:: JOIN_LEFT)
                    ->columns(array('amount' =>new Expression("Sum(Amount)")))
                    ->where("a.ReceiptId<>0 and d.SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receiptAdvance = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAbstract'))
                    ->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(),$select:: JOIN_LEFT)
                    ->join(array('c' => 'CB_WORegister'), 'b.WORegisterId=c.WorkOrderId', array(),$select:: JOIN_LEFT)
                    ->columns(array('amount' =>new Expression("Sum(CerCurAmount)")))
                    ->where("(a.BillFormatId='9' or a.BillFormatId='20') and b.IsCertifiedBill = 1 and b.SubscriberId=$subscriberId");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billAbstract = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                $receipt['retValue'] = (double)$billAbstract['amount'];
                $receipt['receivable'] = ((double)($billMaster['certifyAmount'] + $billAbstract['amount'])) - (double) $receipt['totReceived'];

                $receipt['advancePayable'] = abs((double)$receiptAdvanceRec['Amount'] - (double)$receiptAdvance['amount']);
                if($receipt['totReceived'] == 0 || $billMaster['submitAmount'] == 0) {
                    $receipt['receiptCompletedPercentage'] = 0;
                } else {
                    $receipt['receiptCompletedPercentage'] = round(( $receipt['totReceived'] / $billMaster['submitAmount']) * 100);
                }
                $this->_view->receipt = $receipt;

                /* analytical graph completed

                $startingDate = date('Y-m-01', strtotime('-11 month'));
                /* ORDER
                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAbstract'))
                    ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId=a.BillId', array(), $select::JOIN_LEFT)
                    ->columns(array('amount' => new Expression('SUM(CurAmount)'), 'month' => new Expression('DATEPART(m, WODate)')))
                    ->where("WODate>=$startingDate AND BillFormatId='1'")
                    ->group(new Expression('datepart(m, WODate)'));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $ordersByMonth = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                /* BILL
                $select = $sql->select();
                $select->from('CB_BillMaster')
                    ->columns(array('amount' => new Expression('SUM(CertifyAmount)'), 'month' => new Expression('DATEPART(m, BillDate)')))
                    ->where("BillDate>=$startingDate AND DeleteFlag='0'")
                    ->group(new Expression('datepart(m, BillDate)'));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $billsByMonth = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                /* RECEIPT
                $select = $sql->select();
                $select->from(array('a' => 'CB_ReceiptRegister'))
                    ->columns(array('amount' =>new Expression("Sum(Amount)"), 'month' => new Expression('DATEPART(m, ReceiptDate)')))
                    ->where("DeleteFlag='0' and ReceiptAgainst='A'")
                    ->group(new Expression('datepart(m, ReceiptDate)'));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $receiptsByMonth = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray(); */

                $toDate = date('Y-m-d');
                $fromDate = date('Y-01-01');

                $select = $sql->select();
                $select->from(array('a' => 'CB_WORegister'))
                    ->columns(array('Mon' => new Expression("month(WODate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),10) + '-' + ltrim(str(Year(WODate)))"),'OrderAmount' =>new Expression("sum(OrderAmount)"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                    ->where("a.DeleteFlag='0' and a.LiveWO ='0' AND a.SubscriberId = '$subscriberId' and WODate >= '$fromDate' and WODate <= '$toDate'")
                    ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),10),Year(WODate)'));


                $select1 = $sql->select();
                $select1->from(array('a' => 'CB_BillMaster'))
                    ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),10) + '-' + ltrim(str(Year(BillDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount' =>new Expression("sum(Case When CertifyAmount <>0 then CertifyAmount else SubmitAmount end)"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                    ->where("a.DeleteFlag='0' and BillDate >= '$fromDate' and BillDate <= '$toDate' AND b.SubscriberId = '$subscriberId'")
                    ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),10),Year(BillDate)'));
                $select1->combine($select,'Union ALL');

                $select2 = $sql->select();
                $select2->from(array('a' => 'CB_ReceiptRegister'))
                    ->join(array('b' => 'CB_WORegister'), 'b.WorkOrderId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('Mon' => new Expression("month(ReceiptDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),10) + '-' + ltrim(str(Year(ReceiptDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount' =>new Expression("sum(Amount)")))
                    ->where("a.DeleteFlag='0' and ReceiptDate >= '$fromDate' and ReceiptDate <= '$toDate' AND b.SubscriberId = '$subscriberId'")
                    ->group(new Expression('month(ReceiptDate), LEFT(DATENAME(MONTH,ReceiptDate),10),Year(ReceiptDate)'));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("g"=>$select2))
                    ->columns(array('Mon', 'Mondata', 'OrderAmount'=>new Expression("sum(g.OrderAmount)") ,'BillAmount'=> new Expression("sum(g.BillAmount)"),'ReceiptAmount'=>new Expression("sum(g.ReceiptAmount)")))
                    ->group(array('g.Mon','g.Mondata'));
                $statement = $sql->getSqlStringForSqlObject($select3);

                $reportData = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                $chartData = [];
                $serialChartData = array(
                    'orders' => array(),
                    'bills' => array(),
                    'receipts' => array()
                );

                $totOrderAmt = 0;
                $totBillAmt = 0;
                $totReceiptAmt = 0;

                for($i=11; $i>=0; $i--) {
                    $data['date'] = date('Y-m-t', strtotime("-$i month"));
                    if(date('m', strtotime($data['date'])) == date('m')) {
                        $data['date'] = date('Y-m-d');
                    }

                    // order
                    $data['order'] = 0;
                    $isExists = false;
                    foreach($reportData as $report) {
                        if(isset($report['Mon']) && $report['Mon'] == date('m', strtotime($data['date']))) {
                            $data['order'] = (double)$report['OrderAmount'];
                            $data['bill'] = (double)$report['BillAmount'];
                            $data['receipt'] = (double)$report['ReceiptAmount'];
                            $isExists = true;
                            break;
                        }
                    }

                    if($isExists) {

                        $serialChartData['orders'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'order' => $data['order']
                        );

                        $serialChartData['bills'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'bill' => $data['bill']
                        );

                        $serialChartData['receipts'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'receipt' => $data['receipt']
                        );

                        $totOrderAmt += $data['order'];
                        $totBillAmt += $data['bill'];
                        $totReceiptAmt += $data['receipt'];
                    } else {

                        $serialChartData['orders'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'order' => 0
                        );

                        $serialChartData['bills'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'bill' => 0
                        );

                        $serialChartData['receipts'][] = array(
                            'month' => date('M', strtotime($data['date'])),
                            'receipt' => 0
                        );
                    }

                    $chartData[] = $data;
                }

                $this->_view->chartData = json_encode($chartData);
                $this->_view->serialChartData = $serialChartData;

                $this->_view->avgOrderPercentage = round(($totOrderAmt / 12));
                $this->_view->avgBillPercentage = round(($totBillAmt / 12) );
                $this->_view->avgReceiptPercentage = round(($totReceiptAmt / 12));

                // order monthly variation
                $curMonOrderAmt = 0;
                $curMonBillAmt = 0;
                $curMonReceiptAmt = 0;
                foreach($reportData as $report) {
                    if(isset($report['Mon']) && $report['Mon'] == date('m')) {
                        $curMonOrderAmt = $report['OrderAmount'];
                        $curMonBillAmt = $report['BillAmount'];
                        $curMonReceiptAmt = $report['ReceiptAmount'];
                        break;
                    }
                }

                $prevMonOrderAmt = 0;
                $prevMonBillAmt = 0;
                $prevMonReceiptAmt = 0;
                foreach($reportData as $report) {
                    if(isset($report['Mon']) && $report['Mon'] == date('m', strtotime('-1 month'))) {
                        $prevMonOrderAmt = $report['OrderAmount'];
                        $prevMonBillAmt = $report['BillAmount'];
                        $prevMonReceiptAmt = $report['ReceiptAmount'];
                        break;
                    }
                }

                if($prevMonOrderAmt == 0) {
                    $prevMonOrderAmt = $curMonOrderAmt;
                }

                if($prevMonBillAmt == 0) {
                    $prevMonBillAmt = $curMonBillAmt;
                }
                if($prevMonReceiptAmt == 0) {
                    $prevMonReceiptAmt = $curMonReceiptAmt;
                }

                if($curMonOrderAmt != 0) {
                    $orderMonthVariation = (($curMonOrderAmt - $prevMonOrderAmt) / $prevMonOrderAmt)*100;
                } else {
                    $orderMonthVariation = 0;
                }

                if ($curMonBillAmt != 0) {
                    $billMonthVariation = (($curMonBillAmt - $prevMonBillAmt) / $prevMonBillAmt)*100;
                } else {
                    $billMonthVariation = 0;
                }

                if ($curMonReceiptAmt != 0) {
                    $receiptMonthVariation = (($curMonReceiptAmt - $prevMonReceiptAmt) / $prevMonReceiptAmt)*100;
                } else {
                    $receiptMonthVariation = 0;
                }

                if($orderMonthVariation >= 0) {
                    $orderMonthVariation = round($orderMonthVariation, 1) . "% higher ";
                } else {
                    $orderMonthVariation = round(abs($orderMonthVariation), 1) . "% lesser ";
                }

                if($billMonthVariation >= 0) {
                    $billMonthVariation = round($billMonthVariation, 1) ."% higher ";
                } else {
                    $billMonthVariation = round(abs($billMonthVariation), 1) . "% lesser ";
                }

                if($receiptMonthVariation >= 0) {
                    $receiptMonthVariation = round($receiptMonthVariation, 1) ."% higher ";
                } else {
                    $receiptMonthVariation = round(abs($receiptMonthVariation),1) . "% lesser ";
                }
                $this->_view->orderMonthVariation = $orderMonthVariation;
                $this->_view->billMonthVariation = $billMonthVariation;
                $this->_view->receiptMonthVariation = $receiptMonthVariation;

                $this->_view->loginCount = $this->auth->getIdentity()->LoginCount;
                return $this->_view;
            }
        }

        public function allowAction()
        {
            $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $request = $this->getRequest();
            $response = $this->getResponse();
            $subscriberId = $this->auth->getIdentity()->SubscriberId;
            $sql = new Sql( $dbAdapter );
            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                if ($request->isPost()) {
                    //Write your Ajax post code here
                    $postData = $request->getPost();
                    $current_date = date("Y-m-d H:i:s");
                    $clientId = $postData['clientid'];
                    $allowClient = $sql->update();
                    $allowClient->table('CB_MClientTrans');
                    $allowClient->set(array('Accepted' => 1,'AcceptOn'=>$current_date))
                        ->where(array('MClientId'=>$clientId,'SubscriberId'=>$subscriberId));
                    $allowClientStatement = $sql->getSqlStringForSqlObject($allowClient);
                    $result = $dbAdapter->query($allowClientStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $sClientName="";
                    $sEmail ="";

                    $select = $sql->select();
                    $select->from(array('a' => 'CB_MClientMaster'))
                        ->columns(array('ClientName','EMail'))
                        ->where("MClientId = $clientId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if (!empty($projects)) {
                        $sClientName = $projects->ClientName;
                        $sEmail = $projects->EMail;
                    }

                    if ($sClientName != "")
                    {
                        $iClientId=0;

                        $select = $sql->select();
                        $select->from(array('a' => 'CB_ClientMaster'))
                            ->columns(array('ClientId'))
                            ->where("ClientName = '$sClientName' and SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if (!empty($projects)) {
                            $iClientId = $projects->ClientId;

                            $update = $sql->update();
                            $update->table('CB_ClientMaster');
                            $update->set(array('MClientId' => $clientId));
                            $update->where("ClientId = $iClientId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        else
                        {
                            $insert = $sql->insert();
                            $insert->into('CB_ClientMaster');
                            $insert->Values(array('ClientName' => $sClientName, 'EMail' => $sEmail,'SubscriberId' => $subscriberId,'MClientId' => $clientId));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }

                    //$dbAdapter->query($allowClientStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $this->_view->setTerminal(true);
                    if($result){
                        $response = $this->getResponse()->setContent(1);
                    } else {
                        $response = $this->getResponse()->setContent(0);
                    }
                    return $response;
                }
            }
        }
        public function allowClientAction()
        {
            $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $request = $this->getRequest();
            $response = $this->getResponse();
            $subscriberId = $this->auth->getIdentity()->SubscriberId;
            $sql = new Sql( $dbAdapter );
            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                if ($request->isPost()) {
                    //Write your Ajax post code here
                    $postData = $request->getPost();
                    $current_date = date("Y-m-d H:i:s");
                    $clientId = $postData['clientid'];
                    $allowClient = $sql->update();
                    $allowClient->table('CB_MClientMaster');
                    $allowClient->set(array('Accepted' => 1,'AcceptOn'=>$current_date))
                        ->where(array('MClientId'=>$clientId));
                    $allowClientStatement = $sql->getSqlStringForSqlObject($allowClient);
                    $result = $dbAdapter->query($allowClientStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //$dbAdapter->query($allowClientStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $this->_view->setTerminal(true);
                    if($result){
                        $response = $this->getResponse()->setContent(1);
                    } else {
                        $response = $this->getResponse()->setContent(0);
                    }
                    return $response;
                }
            }
        }

        protected function _subActive($subscriberId)
        {
            $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );
            $select = $sql->select();
            $select->from(array('a' => 'CB_SubscriberMaster'))
                ->where("SubscriberId=$subscriberId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $subscriberDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            return $subscriberDetail['IsActive'];
        }

        public function paymentResponseAction(){
            $this->layout("layout/clientbillinglayout");
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Payment Response");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            $connection = $dbAdapter->getDriver()->getConnection();

            $this->_view->response = '400';

            $request = $this->getRequest();
            $payment_request_id = $this->bsf->isNullCheck($request->getQuery('payment_request_id'),'string');
            $payment_id = $this->bsf->isNullCheck($request->getQuery('payment_id'),'string');

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
                        try {
                            $connection->beginTransaction();
                            // update payment respose id
                            $update = $sql->update();
                            $update->table( 'CB_PaymentMaster' )
                                ->set( array( 'PaymentResponseId' => $payment_id, 'PaymentPaidDate' => date('Y-m-d H:i:s')) )
                                ->where( array( 'PaymentRequestId' => $payment_request_id ) );
                            $statement = $sql->getSqlStringForSqlObject( $update );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                            $connection->commit();

                            // trigger mail
                            $select = $sql->select();
                            $select->from('CB_SubscriberMaster')
                                ->columns(array('EMail', 'SubscriberId','ActivationCode'))
                                ->where(array('PaymentId'=>$payment['PaymentId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $subscriber  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from('CB_Users')
                                ->columns(array('Username'))
                                ->where(array('SubscriberId'=>$subscriber['SubscriberId']));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $user  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //welcome mail
                            $mailData = array();
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo($subscriber['EMail'],$config['general']['mandrilEmail'],'Welcome to RA Bills','cb_welcome',$mailData);

                            //activation mail
                            $mailData = array(
                                array(
                                    'name' => 'USERNAME',
                                    'content' => $user[ 'Username' ]
                                ),
                                array(
                                    'name' => 'ACTIVATIONCODE',
                                    'content' => $subscriber['ActivationCode']
                                ),
                            );
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo( $subscriber[ 'EMail' ],$config['general']['mandrilEmail'], 'Activate your account', 'cb_registeredsuccessfully', $mailData );
                        }  catch ( PDOException $e ) {
                            $connection->rollback();
                        }
                    }

                    $this->_view->response = '200';
                } else {
                    $this->_view->response = '404';
                }
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }