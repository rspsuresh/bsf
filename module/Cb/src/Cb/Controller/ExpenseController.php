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
    class ExpenseController extends AbstractActionController
    {
        public function __construct()	{
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
            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Expense");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $subscriberId = $this->auth->getIdentity()->SubscriberId;
            $sql = new Sql( $dbAdapter );
            $connection = $dbAdapter->getDriver()->getConnection();

            if($this->getRequest()->isXmlHttpRequest())	{
            } else {
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $postData = $request->getPost();
                    $editExpenseId = $this->bsf->isNullCheck($postData['ExpenseId'],'number');
                    try {
                        if($editExpenseId == 0) {
                            $WorkOrderId = $this->bsf->isNullCheck($postData['WorkOrderId'],'number');
                            $ExpenseDate = $this->bsf->isNullCheck($postData['ExpenseDate'],'string');
                            $ExpenseNo = $this->bsf->isNullCheck($postData['ExpenseNo'],'string');
                            $AmountTotal = $this->bsf->isNullCheck($postData['AmountTotal'],'number');

                            $connection->beginTransaction();
                            $insert = $sql->insert();
                            $insert->into('CB_ExpenseRegister');
                            $insert->Values(array('WorkOrderId' => $WorkOrderId, 'ExpenseNo' => $ExpenseNo, 'ExpenseDate' => date('Y-m-d', strtotime($ExpenseDate))
                                            ,'SubscriberId' => $subscriberId, 'Amount' => $AmountTotal));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $ExpenseId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $rowid = $this->bsf->isNullCheck($postData['rowid'],'number');
                            for ( $i = 1; $i <= $rowid; $i++ ) {
                                $tags = $postData[ 'tags_' . $i ];
                                $paidToCategory = $this->bsf->isNullCheck( $postData[ 'paidToCategory_' . $i ], 'string' );
                                $paidToId = $this->bsf->isNullCheck( $postData[ 'paidToId_' . $i ], 'number' );
                                $Description = $this->bsf->isNullCheck( $postData[ 'desc_' . $i ], 'string' );
                                $Amount = $this->bsf->isNullCheck( $postData[ 'Amount_' . $i ], 'number' );

                                if (count($tags) == 0 || $paidToCategory == '' || $paidToId == 0 || $Amount == 0)
                                    continue;

                                $AccountIds = '';
                                foreach($tags as $tagName) {
                                    $select = $sql->select();
                                    $select->from( 'CB_AccountMaster' )
                                        ->columns( array( 'Id') )
                                        ->where("Name='$tagName' AND SubscriberId=$subscriberId AND DeleteFlag=0" );
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $tag = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                    if($tag) {
                                        $AccountIds .= $tag['Id'] . ',';
                                         continue;
                                    }

                                    $insert = $sql->insert();
                                    $insert->into( 'CB_AccountMaster' );
                                    $insert->Values( array( 'Name' => $tagName, 'SubscriberId' => $subscriberId) );
                                    $statement = $sql->getSqlStringForSqlObject( $insert );
                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                    $AccountIds .= $dbAdapter->getDriver()->getLastGeneratedValue() . ',';
                                }
                                $AccountIds = trim($AccountIds, ',');

                                if($paidToCategory == 'Client') {
                                    $paidToCol = 'ClientId';
                                } else if ($paidToCategory == 'Vendor') {
                                    $paidToCol = 'VendorId';
                                }

                                $insert = $sql->insert();
                                $insert->into( 'CB_ExpenseTrans' );
                                $insert->Values( array( 'ExpenseId' => $ExpenseId, 'AccountIds' => $AccountIds, $paidToCol => $paidToId, 'Amount' => $Amount, 'Description' => $Description) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }

                            CommonHelper::insertCBLog('Client-Expense-Add',$ExpenseId,$ExpenseNo,$dbAdapter);
                            CommonHelper::getVoucherNo(106, date('Y/m/d'), 0, 0, $dbAdapter, "I");

                            $connection->commit();
                            $this->redirect()->toRoute('cb/default', array('controller' => 'expense', 'action' => 'index'));
                        } else {
                            $WorkOrderId = $this->bsf->isNullCheck($postData['WorkOrderId'],'number');
                            $ExpenseDate = $this->bsf->isNullCheck($postData['ExpenseDate'],'string');
                            $ExpenseNo = $this->bsf->isNullCheck($postData['ExpenseNo'],'string');
                            $AmountTotal = $this->bsf->isNullCheck($postData['AmountTotal'],'number');

                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table('CB_ExpenseRegister');
                            $update->set(array('WorkOrderId' => $WorkOrderId, 'ExpenseNo' => $ExpenseNo, 'ExpenseDate' => date('Y-m-d', strtotime($ExpenseDate))
                                         ,'SubscriberId' => $subscriberId, 'Amount' => $AmountTotal));
                            $update->where(array('ExpenseId' => $editExpenseId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            // delete expense trans
                            $rowdeleteids = rtrim($this->bsf->isNullCheck($postData['rowdeleteids'],'string'), ",");
                            if($rowdeleteids !== '') {
                                $delete = $sql->delete();
                                $delete->from('CB_ExpenseTrans')
                                    ->where("TransId IN ($rowdeleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $rowid = $this->bsf->isNullCheck($postData['rowid'],'number');
                            for ( $i = 1; $i <= $rowid; $i++ ) {
                                $tags = $postData[ 'tags_' . $i ];
                                $paidToCategory = $this->bsf->isNullCheck( $postData[ 'paidToCategory_' . $i ], 'string' );
                                $paidToId = $this->bsf->isNullCheck( $postData[ 'paidToId_' . $i ], 'number' );
                                $Description = $this->bsf->isNullCheck( $postData[ 'desc_' . $i ], 'string' );
                                $Amount = $this->bsf->isNullCheck( $postData[ 'Amount_' . $i ], 'number' );
                                $TransId = $this->bsf->isNullCheck( $postData[ 'TransId_' . $i ], 'number' );
                                $UpdateRow = $this->bsf->isNullCheck( $postData[ 'UpdateRow_' . $i ], 'number' );

                                if (count($tags) == 0 || $paidToCategory == '' || $paidToId == 0 || $Amount == 0)
                                    continue;

                                // skip existing row not modified
                                if($TransId != 0 && $UpdateRow == 0)
                                    continue;

                                $AccountIds = '';
                                foreach($tags as $tagName) {
                                    $select = $sql->select();
                                    $select->from( 'CB_AccountMaster' )
                                        ->columns( array( 'Id') )
                                        ->where("Name='$tagName' AND SubscriberId=$subscriberId AND DeleteFlag=0" );
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $tag = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                                    if($tag) {
                                        $AccountIds .= $tag['Id'] . ',';
                                        continue;
                                    }

                                    $insert = $sql->insert();
                                    $insert->into( 'CB_AccountMaster' );
                                    $insert->Values( array( 'Name' => $tagName, 'SubscriberId' => $subscriberId) );
                                    $statement = $sql->getSqlStringForSqlObject( $insert );
                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                    $AccountIds .= $dbAdapter->getDriver()->getLastGeneratedValue() . ',';
                                }
                                $AccountIds = trim($AccountIds, ',');

                                if($paidToCategory == 'Client') {
                                    $paidToClientId = $paidToId;
                                    $paidToVendorId = 0;
                                } else if ($paidToCategory == 'Vendor') {
                                    $paidToClientId = 0;
                                    $paidToVendorId = $paidToId;
                                }

                                if($TransId == 0) {
                                    $insert = $sql->insert();
                                    $insert->into( 'CB_ExpenseTrans' );
                                    $insert->Values( array( 'ExpenseId' => $editExpenseId, 'AccountIds' => $AccountIds, 'ClientId' => $paidToClientId, 'VendorId' => $paidToVendorId, 'Amount' => $Amount, 'Description' => $Description ) );
                                    $statement = $sql->getSqlStringForSqlObject( $insert );
                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                } else if($TransId != 0 && $UpdateRow == 1) {
                                    $update = $sql->update();
                                    $update->table( 'CB_ExpenseTrans' )
                                        ->set( array( 'ExpenseId' => $editExpenseId, 'AccountIds' => $AccountIds,  'ClientId' => $paidToClientId, 'VendorId' => $paidToVendorId, 'Amount' => $Amount, 'Description' => $Description ) )
                                        ->where(array('TransId' => $TransId));
                                    $statement = $sql->getSqlStringForSqlObject( $update );
                                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                }
                            }

                            CommonHelper::insertCBLog('Client-Expense-Update',$editExpenseId,$ExpenseNo,$dbAdapter);

                            $connection->commit();
                            $this->redirect()->toRoute('cb/default', array('controller' => 'expense', 'action' => 'index'));
                        }
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                } else {
                    $editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
                    $mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );

                    if ($editid != 0) {
                        // check for expense id and subscriber id
                        $select = $sql->select();
                        $select->from(array('a' => "CB_ExpenseRegister"))
                            ->join(array('b' => 'CB_WORegister'), 'a.WorkOrderId=b.WorkOrderId', array('WONo'), $select:: JOIN_LEFT)
                            ->where("a.ExpenseId=$editid AND a.SubscriberId=$subscriberId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $expense = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(!$expense)
                            $this->redirect()->toRoute( 'cb/default', array( 'controller' => 'expense', 'action' => 'register' ) );

                        $this->_view->expense  = $expense;

                        $select = $sql->select();
                        $select->from( array( 'a' => 'CB_ExpenseTrans' ) )
                            ->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName', 'ClientId'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'CB_VendorMaster'), 'a.VendorId=c.VendorId', array('VendorName', 'VendorId'), $select:: JOIN_LEFT)
                            ->where( "a.ExpenseId=$editid")
                            ->order('a.TransId');
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $expenseTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if(count($expenseTrans))
                            $this->_view->expenseTrans = $expenseTrans;
                    }

                    // Clients
                    $select = $sql->select();
                    $select->from( 'CB_ClientMaster' )
                        ->columns( array( 'id' => 'ClientId', 'value' => 'ClientName') )
                        ->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->clients = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    // vendors
                    $select = $sql->select();
                    $select->from('CB_VendorMaster' )
                        ->columns(array("id"=>'VendorId',"value"=> "VendorName"))
                        ->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->vendors = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    // workorders
                    $select = $sql->select();
                    $select->from(array("a"=>"CB_WORegister"))
                        ->columns( array( 'data' => 'WorkOrderId', 'value' => 'WONo') )
                        ->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->workorders = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    // accounts
                    $select = $sql->select();
                    $select->from("CB_AccountMaster")
                        ->columns( array('Id','Name') )
                        ->where(array('DeleteFlag' => '0', 'SubscriberId' => $subscriberId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->accounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $aVNo = CommonHelper::getVoucherNo(106, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->genType = $aVNo["genType"];
                    if (!$aVNo["genType"])
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];

                    $this->_view->expenseid = $editid;
                    $this->_view->mode = $mode;

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                }

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

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Expense Register");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );

            $subscriberId = $this->auth->getIdentity()->SubscriberId;

            $select = $sql->select();
            $select->from(array('a' => 'CB_ExpenseRegister'))
                ->join(array('b' => 'CB_WORegister'), 'a.WorkOrderId=b.WorkOrderId', array('WONo'),$select:: JOIN_LEFT)
                ->columns(array( 'WorkOrderId','ExpenseId','ExpenseNo','ExpenseDate' => new Expression("FORMAT(ExpenseDate, 'dd-MM-yyyy')"),'Amount'))
                ->where("a.DeleteFlag='0' and a.SubscriberId=$subscriberId");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->expenses = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

        public function deleteexpenseAction(){
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
                        $ExpenseId = $this->bsf->isNullCheck($this->params()->fromPost('ExpenseId'), 'number');
                        $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                        $sql = new Sql($dbAdapter);
                        $response = $this->getResponse();
                        $connection->beginTransaction();

                        $select = $sql->select();
                        $select->from('CB_ExpenseRegister')
                            ->columns(array('ExpenseNo'))
                            ->where(array("ExpenseId" => $ExpenseId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $expense = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $ExpenseNo="";
                        if (!empty($expense)) { $ExpenseNo =$expense->ExpenseNo; }

                        $update = $sql->update();
                        $update->table('CB_ExpenseRegister')
                            ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                            ->where(array('ExpenseId' => $ExpenseId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        CommonHelper::insertCBLog('Client-Expense-Delete',$ExpenseId,$ExpenseNo,$dbAdapter);

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
    }