<?php
    /**
     * Zend Framework (http://framework.zend.com/)
     *
     * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
     * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     */

    namespace Clientbilling\Controller;

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

        public function __construct()	{
            $this->auth = new AuthenticationService();
            $this->bsf = new \BuildsuperfastClass();
            if ($this->auth->hasIdentity()) {
                $this->identity = $this->auth->getIdentity();
            }
            $this->_view = new ViewModel();
        }

        public function dashboardAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Dashboard");
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
                    $select->from(array('a' => 'Proj_WORegister'))
                        ->columns(array('Mon' => new Expression("month(WODate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),10) + '-' + ltrim(str(Year(WODate)))"),'OrderAmount' =>new Expression("sum(OrderAmount)"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                        ->where("a.DeleteFlag='0' and a.LiveWO ='0' and WODate >= '$fromDate' and WODate <= '$toDate'")
                        ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),10),Year(WODate)'));


                    $select1 = $sql->select();
                    $select1->from(array('a' => 'CB_BillMaster'))
                        ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId = a.WORegisterId', array(), $select::JOIN_LEFT)
                        ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),10) + '-' + ltrim(str(Year(BillDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount' =>new Expression("sum(Case When CertifyAmount <>0 then CertifyAmount else SubmitAmount end)"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                        ->where("a.DeleteFlag='0' and BillDate >= '$fromDate' and BillDate <= '$toDate'")
                        ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),10),Year(BillDate)'));
                    $select1->combine($select,'Union ALL');

                    $select2 = $sql->select();
                    $select2->from(array('a' => 'CB_ReceiptRegister'))
                        ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId = a.WORegisterId', array(), $select::JOIN_LEFT)
                        ->columns(array('Mon' => new Expression("month(ReceiptDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),10) + '-' + ltrim(str(Year(ReceiptDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount' =>new Expression("sum(Amount)")))
                        ->where("a.DeleteFlag='0' and ReceiptDate >= '$fromDate' and ReceiptDate <= '$toDate'")
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
                $select->from('Proj_WORegister')
                    ->columns(array('totOrders' =>new Expression("Count(WORegisterId)"), 'totOrderAmt' =>new Expression("Sum(OrderAmount)")))
                    ->where("LiveWO ='0'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $order = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAbstract'))
                    ->join(array('b' => 'CB_BillMaster'), 'b.BillId = a.BillId', array(), $select::JOIN_LEFT)
                    ->columns(array('curAmount' =>new Expression("Sum(CerCurAmount)")))
                    ->where("a.BillFormatId='1' AND b.DeleteFlag='0'");
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
                    ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('totProjects' =>new Expression("Count(Distinct(b.CostCentreId))")))
                    ->where("a.DeleteFlag='0' AND b.LiveWO ='0'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $project = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillMaster'))
                    ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('submitAmount' =>new Expression("Sum(SubmitAmount)"), 'certifyAmount' =>new Expression("Sum(CertifyAmount)")))
                    ->where("a.DeleteFlag='0'");
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
                    ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId=a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('totReceived' =>new Expression("Sum(a.Amount)")))
                    ->where("a.DeleteFlag='0' AND a.ReceiptAgainst='B'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receipt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();


                $select = $sql->select();
                $select->from(array('a' => 'CB_ReceiptRegister'))
                    ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array(),$select:: JOIN_LEFT)
                    ->columns(array('Amount' =>new Expression("Sum(Amount)")))
                    ->where("a.DeleteFlag='0' and a.ReceiptAgainst='M'");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receiptAdvanceRec = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAdvanceRecovery'))
                    ->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(),$select:: JOIN_LEFT)
                    ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(),$select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_WORegister'), 'c.WORegisterId=d.WORegisterId', array(),$select:: JOIN_LEFT)
                    ->columns(array('amount' =>new Expression("Sum(Amount)")))
                    ->where("a.ReceiptId<>0");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $receiptAdvance = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array('a' => 'CB_BillAbstract'))
                    ->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(),$select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_WORegister'), 'b.WORegisterId=c.WORegisterId', array(),$select:: JOIN_LEFT)
                    ->columns(array('amount' =>new Expression("Sum(CerCurAmount)")))
                    ->where("(a.BillFormatId='9' or a.BillFormatId='20') and b.IsCertifiedBill = 1");
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

                $toDate = date('Y-m-d');
                $fromDate = date('Y-01-01');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WORegister'))
                    ->columns(array('Mon' => new Expression("month(WODate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),10) + '-' + ltrim(str(Year(WODate)))"),'OrderAmount' =>new Expression("sum(OrderAmount)"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                    ->where("a.LiveWO ='0' AND WODate >= '$fromDate' and WODate <= '$toDate'")
                    ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),10),Year(WODate)'));


                $select1 = $sql->select();
                $select1->from(array('a' => 'CB_BillMaster'))
                    ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),10) + '-' + ltrim(str(Year(BillDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount' =>new Expression("sum(Case When CertifyAmount <>0 then CertifyAmount else SubmitAmount end)"),'ReceiptAmount'=>new Expression("CAST(0 As Decimal(18,5))")))
                    ->where("a.DeleteFlag='0' and BillDate >= '$fromDate' and BillDate <= '$toDate'")
                    ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),10),Year(BillDate)'));
                $select1->combine($select,'Union ALL');

                $select2 = $sql->select();
                $select2->from(array('a' => 'CB_ReceiptRegister'))
                    ->join(array('b' => 'Proj_WORegister'), 'b.WORegisterId = a.WORegisterId', array(), $select::JOIN_LEFT)
                    ->columns(array('Mon' => new Expression("month(ReceiptDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,ReceiptDate),10) + '-' + ltrim(str(Year(ReceiptDate)))"),'OrderAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'BillAmount'=>new Expression("CAST(0 As Decimal(18,5))"),'ReceiptAmount' =>new Expression("sum(Amount)")))
                    ->where("a.DeleteFlag='0' and ReceiptDate >= '$fromDate' and ReceiptDate <= '$toDate'")
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

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Billing Register");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillMaster' ))
                ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select::JOIN_LEFT)
                ->join(array('e' => 'Proj_ClientMaster'), 'b.ClientId=e.ClientId', array('ClientName'), $select::JOIN_LEFT)
                ->join(array('d' => 'WF_OperationalCostCentre'), 'b.CostCentreId=d.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                ->join(array('c' => 'CB_ReceiptAdustment'), 'a.BillId=c.BillId', array( 'PaymentReceived' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
                ->columns( array( 'BillId','BillNo','BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')")
                , 'WORegisterId', 'SubmitAmount', 'CertifyAmount','IsSubmittedBill', 'IsCertifiedBill','Submitted', 'Certified') )
                ->where(array('a.DeleteFlag' => '0'))
                ->group(new Expression('a.BillId,a.BillNo,a.BillDate,e.ClientName,a.WORegisterId,a.SubmitAmount,a.CertifyAmount,a.IsSubmittedBill,a.IsCertifiedBill,a.Submitted,a.Certified,d.CostCentreName'))
                ->order('a.BillId ASC');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillMaster' ))
                ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select::JOIN_LEFT)
                ->columns(array('costcentres' =>new Expression("Count(Distinct(b.CostCentreId))")))
                ->where("a.DeleteFlag='0' AND b.LiveWO='1'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->costcentrecount = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from('Proj_WORegister')
                ->columns(array('OrderAmt' =>new Expression("Sum(OrderAmount)")))
                ->where("LiveWO ='0'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->ordervalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from('CB_BillMaster')
                ->columns(array('submitAmount' =>new Expression("Sum(SubmitAmount)")))
                ->where("DeleteFlag='0'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->submitvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from('CB_BillMaster')
                ->columns(array('certifyAmount' =>new Expression("Sum(CertifyAmount)")))
                ->where("DeleteFlag='0'");
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->certifyvalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillMaster'))
                ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array('ClientId'),$select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId',array('ClientName'), $select:: JOIN_LEFT)
                ->columns(array('Amount' =>new Expression("sum(a.SubmitAmount)")),array('ClientName'))
                ->where("a.DeleteFlag='0'")
                ->group(array('c.ClientName','b.ClientId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->submitamt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillMaster'))
                ->columns(array('Mon' => new Expression("month(BillDate)"),'Mondata' => new Expression("LEFT(DATENAME(MONTH,BillDate),3) + '-' + ltrim(str(Year(BillDate)))"),'SubmitAmount' =>new Expression("sum(SubmitAmount)"),'CertifyAmount' =>new Expression("sum(CertifyAmount)")))
                ->where("a.DeleteFlag='0'")
                ->group(new Expression('month(BillDate), LEFT(DATENAME(MONTH,BillDate),3),Year(BillDate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->monsubVscer = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

            $select = $sql->select();
            $select->from(array('a' => 'CB_BillMaster'))
                ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array('ClientId'),$select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId',array('ClientName'), $select:: JOIN_LEFT)
                ->columns(array('SubmitAmount' =>new Expression("sum(SubmitAmount)"),'CertifyAmount' =>new Expression("sum(CertifyAmount)")))
                ->where("a.DeleteFlag='0'")
                ->group(array('c.ClientName','b.ClientId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->clientsubVscer = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

        public function billselectionAction(){

            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Client Billing");
            $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
            $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
            $sql = new Sql( $dbAdapter );

            if($this->getRequest()->isXmlHttpRequest()) {
                $this->_view->setTerminal(true);
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $postData = $request->getPost();
                    $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                    $data = 'N';
                    $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'number' );
                    $response = $this->getResponse();
                    switch($RType) {
                        case 'billno':
                            $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                            $select = $sql->select();
                            $select->from( 'CB_BillMaster' )
                                ->columns( array( 'BillId' ) )
                                ->where( "BillNo='$PostDataStr' AND DeleteFlag=0" );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if (sizeof($results) !=0 )
                                $data ='Y';
                            break;
                        case 'billAddchk':
                            $Bill_Id = $this->bsf->isNullCheck( $postData[ 'Bill_Id' ], 'number' );
                            $WorkOrderId = $this->bsf->isNullCheck( $postData[ 'WorkOrderId' ], 'number' );
                            $CostCenterId = $this->bsf->isNullCheck( $postData[ 'CostCenterId' ], 'number' );
                            $BillType = $this->bsf->isNullCheck( $postData[ 'BillType' ], 'string' );$select = $sql->select();

                            // check for bill format in workorder
                            $select = $sql->select();
                            $select->from( 'CB_BillFormatTrans' )
                                ->columns( array( 'BillFormatTransId' ) )
                                ->where( "CostCenterId='$CostCenterId'" );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if (sizeof($billformats) < 1 ) {
                                $response->setStatusCode(403);
                                $response->setContent("No bill format(s) found in selected workorder!");
                                return $response;
                            }

                            $select = $sql->select();
                            $select->from( 'CB_BillMaster' )
                                ->columns( array( 'BillId' ) )
                                ->where( "WORegisterId='$WorkOrderId' AND DeleteFlag=0" );

                            if($BillType=="S"){
                                $select->where( " IsSubmittedBill<>1 " );
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if (sizeof($results) !=0 )
                                    $data ='Y';
                            } else {
                                $select->where( "BillId='$Bill_Id' AND IsSubmittedBill<>1" );
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if (sizeof($results) !=0 ){
                                    $data ='S';//submit bill not approve
                                } else {
                                    $select = $sql->select();
                                    $select->from( 'CB_BillMaster' )
                                        ->columns( array( 'BillId' ) )
                                        ->where( "WORegisterId='$WorkOrderId' AND DeleteFlag=0" );
                                    $select->where( "BillId<'$Bill_Id' AND IsCertifiedBill<>1" );
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    if (sizeof($results) !=0 )
                                        $data ='C';//Prev bill Certify not approve
                                }
                            }
                            break;
                        case 'getWONo':
                            $select = $sql->select();
                            $select->from( 'Proj_WORegister' )
                                ->columns( array( 'data' => 'WORegisterId', 'value' => 'WONo', 'WODate' => new Expression("FORMAT(WODate, 'dd-MM-yyyy')"), 'StartDate' => new Expression("FORMAT(StartDate, 'dd-MM-yyyy')") ))
                                ->where( "CostCentreId='$PostDataStr' AND LiveWO=1" );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $data = json_encode($results);
                            break;
                        case 'getBillNo':
                            $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
                            $select = $sql->select();
                            $select->from( 'CB_BillMaster' )
                                ->columns( array( 'data' => 'BillId', 'value' => 'BillNo', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')") ) )
                                ->where( "WORegisterId='$PostDataStr' AND DeleteFlag=0" );
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $data = json_encode($results);
                            break;
                    }
                    $response->setContent($data);
                    return $response;
                } else {
                    $response = $this->getResponse();
                    return $response->setStatusCode(400)
                        ->setContent('Bad Request');
                }
            } else {
                $request = $this->getRequest();
                if ( $request->isPost() ) {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    $postData = $request->getPost();
                    try {
                        $BillId = $this->bsf->isNullCheck($postData['BillId'],'number');
                        $BillType = $this->bsf->isNullCheck($postData['BillType'],'string');
                        $PWorkOrderId = $this->bsf->isNullCheck($postData['PWorkOrderId'],'string');
                        $CostCentreId = $this->bsf->isNullCheck($postData['CostCentreId'],'string');

                        // New Bill
                        $MBillNo = $this->bsf->isNullCheck($postData['MBillNo'],'string');
                        $MBillDate = $this->bsf->isNullCheck($postData['MBillDate'],'date');
                        $BillEntryType = $this->bsf->isNullCheck($postData['MBillEntryType'],'string');
                        $MFromDate = $this->bsf->isNullCheck($postData['MFromDate'],'date');
                        $MToDate = $this->bsf->isNullCheck($postData['MToDate'],'date');
                        $Date = date('Y-m-d');

                        // check for bill trans type
                        if($BillType == 'S') {
                            $IsSubmittedBill = 1;

                            $SubmittedDate = date('Y-m-d', strtotime($Date));
                            $SubmittedRemarks = '';

                            $IsCertifiedBill = 0;
                            $CertifiedDate = null;
                            $CertifiedRemarks = '';
                        } else {
                            $IsSubmittedBill = 0;

                            $SubmittedDate = null;
                            $SubmittedRemarks = '';

                            $IsCertifiedBill = 1;

                            $CertifiedDate = date('Y-m-d', strtotime($Date));
                            $CertifiedRemarks = '';
                        }

                        if($BillId==0){
                            $insert = $sql->insert();
                            $insert->into( 'CB_BillMaster' );
                            $insert->Values( array( 'BillNo' => $MBillNo, 'BillDate' => date('Y-m-d', strtotime($MBillDate))
                            , 'WORegisterId' => $PWorkOrderId, 'FromDate' => date('Y-m-d', strtotime($MFromDate)), 'ToDate' => date('Y-m-d', strtotime($MToDate))
                            , 'BillType' => $BillEntryType
                            , 'Submitted' => $IsSubmittedBill, 'Certified' => $IsCertifiedBill,'SubmittedRemarks' => $SubmittedRemarks
                            , 'SubmittedDate' => $SubmittedDate, 'CertifiedDate' => $CertifiedDate, 'CertifiedRemarks' => $CertifiedRemarks) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $BillId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            //Submit Adjustment
                            if($IsSubmittedBill == 1){
                                $this->UpdateBillCumulativedet($BillId, $PWorkOrderId, $BillEntryType, $dbAdapter);
                                $this->GetBilldet($BillId, $PWorkOrderId, $CostCentreId, $BillEntryType, $dbAdapter);
                                $this->LoadSubmit_Certify_Billdet($BillId, $PWorkOrderId, $dbAdapter);
                            }
                            CommonHelper::insertCBLog('Client-Bill-Add', $BillId, $MBillNo, $dbAdapter);
                        } else {
                            $update = $sql->update();
                            $update->table('CB_BillMaster');
                            $update->set( array( 'Certified' => $IsCertifiedBill ) );
                            $update->where(array('BillId' => $BillId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //billformat refresh Start
                            $subselect = $sql->select();
                            $subselect->from( array( 'a' => 'CB_BillAbstract' ) )
                                ->columns(array('BillFormatId'))
                                ->where( "a.BillId=$BillId");

                            $select2 = $sql->select();
                            $select2->from( array( 'a' => 'CB_BillFormatTrans' ) )
                                ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array('BillFormatId'), $select2::JOIN_LEFT )
                                ->columns(array('Formula'))
                                ->where( "a.CostCentreId=$CostCentreId")
                                ->where->notIn('a.BillFormatId',$subselect);

                            $statement = $sql->getSqlStringForSqlObject( $select2 );
                            $billsNonAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            foreach($billsNonAbstracts as $billsNonAbstract) {
                                if(is_null($billsNonAbstract[ 'BillFormatId' ]) || $billsNonAbstract[ 'BillFormatId' ] == '')
                                    $billsNonAbstract[ 'BillFormatId' ] = 0;

                                $insert = $sql->insert();
                                $insert->into( 'CB_BillAbstract' );
                                $insert->Values( array( 'BillId' => $BillId, 'BillFormatId' => $billsNonAbstract[ 'BillFormatId' ],
                                    'Formula' => $billsNonAbstract[ 'Formula' ] ) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }
                            //billformat refresh End

                            //Certify Adjustment
                            if($IsCertifiedBill == 1){
                                $this->UpdateBillCumulativedet($BillId, $PWorkOrderId, $BillEntryType, $dbAdapter);
                                $this->GetSubmittedtoCertifyBilldet($BillId ,$PWorkOrderId, $BillEntryType, $dbAdapter);
                            }
                            CommonHelper::insertCBLog('Client-Bill-Edit', $BillId, $MBillNo, $dbAdapter);
                        }

                        $connection->commit();
                        if($BillId != 0){
                            $this->redirect()->toRoute('clientbilling/default', array('controller' => 'index', 'action' => 'bill', 'id' => $BillId, 'mode' => 'edit', 'type' => $BillType));
                        }
                    } catch ( PDOException $e ) {
                        $connection->rollback();
                    }
                } else {
                    $editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
                    $mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );
                    $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

                    if ( $editid != 0 ) {
                        // Bill Info
                        $select = $sql->select();
                        $select->from( array( 'a' => "CB_BillMaster" ) )
                            ->join( array( 'b' => 'Proj_WORegister' ), 'a.WORegisterId=b.WORegisterId', array( 'WONo', 'WODate' => new Expression( "FORMAT(b.WODate, 'dd-MM-yyyy')" ), 'WorkOrderId' => 'WORegisterId' ), $select:: JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_ProjectMaster' ), 'b.ProjectId=c.ProjectId', array( 'ProjectId', 'ProjectName' ), $select:: JOIN_LEFT )
                            ->columns( array( 'BillNo', 'BillType', 'BillDate' => new Expression( "FORMAT(a.BillDate, 'dd-MM-yyyy')" ), 'Submitted', 'Certified' ), array( 'WONo', 'WODate', 'WorkOrderId' => 'WORegisterId'), array( 'ProjectId', 'ProjectName' ) )
                            ->where( "a.BillId=$editid" );
                        $statement = $statement = $sql->getSqlStringForSqlObject( $select );
                        $billinfo = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        if ( $type == "C" && $billinfo[ 'Certified' ] == 1 ) {

                        }
                        else if ( $type == "S" && $billinfo[ 'Submitted' ] == 1 ) {

                        }
                        else {
                            $this->redirect()->toRoute( 'clientbilling/default', array( 'controller' => 'index', 'action' => 'register' ) );
                        }

                        $this->_view->billinfo = $billinfo;
                    }

                    // Projects
                    $select = $sql->select();
                    $select->from( 'WF_OperationalCostCentre' )
                        ->columns(array( 'data' => 'CostCentreId', 'value' => 'CostCentreName' ))
                        ->where(array('WORegisterId <>0','Deactivate'=>0));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->projects = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $this->_view->billid = $editid;
                    $this->_view->mode = $mode;
                    $this->_view->type = $type;
                }

                $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
                return $this->_view;
            }
        }

        public function billAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
            $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
            $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
            $sql = new Sql( $dbAdapter );

            $request = $this->getRequest();
            if ( $request->isPost() ) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                $postData = $request->getPost();
                $files = $request->getFiles();
                try {
                    $BillId = $this->bsf->isNullCheck($postData['BillId'],'number');
                    $BillType = $this->bsf->isNullCheck($postData['BillType'],'string');
                    if($this->bsf->isNullCheck($postData['mode'],'string') == 'edit') { // Edit fns
                        // update Bill Master
                        $isSubCer = $this->bsf->isNullCheck($postData['isSubCer'],'number');
                        $Remarks = $this->bsf->isNullCheck($postData['remarks'],'string');
                        $TotalCurAmount = $this->bsf->isNullCheck($postData['TotalCurAmount'],'number');

                        $Date = NULL;
                        if($isSubCer == '1') {
                            $Date = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date'],'string')));
                        }

                        // check for db CRUD fns
                        $strCer = "";
                        if($BillType == 'C') {
                            $strCer = "Cer";
                        }

                        $select = $sql->select();
                        $select->from("CB_BillMaster")
                            ->where(array("BillId" => $BillId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $oldBillData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        // check for bill trans type
                        $update = $sql->update();
                        if($BillType == 'S') {
                            $update->table('CB_BillMaster')
                                ->set( array('SubmittedRemarks' => $Remarks , 'SubmittedDate' => $Date
                                , 'IsSubmittedBill' => $isSubCer,'SubmitAmount' => $TotalCurAmount));
                        } else {
                            $update->table('CB_BillMaster')
                                ->set( array('CertifiedRemarks' => $Remarks , 'CertifiedDate' => $Date
                                , 'IsCertifiedBill' => $isSubCer,'CertifyAmount' => $TotalCurAmount ));
                        }
                        $update->where(array('BillId' => $BillId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // get bill details
                        $select = $sql->select();
                        $select->from("CB_BillMaster")
                            ->columns(array('WORegisterId'))
                            ->where(array("BillId" => $BillId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                        // insert new NonAgtItem(s)
                        $NewNonAgtRowId = $this->bsf->isNullCheck($postData['newnonagtrowid'],'number');
                        $NewNonAgtIds = array();
                        for ($v = 1; $v <= $NewNonAgtRowId; $v++) {
                            $nonagtId = $this->bsf->isNullCheck($postData['newnonagtid_' . $v], 'string');
                            $nonslno = $this->bsf->isNullCheck($postData['newnonagtslno_' . $v], 'string');
                            $nonspec = $this->bsf->isNullCheck($postData['newnonagtspec_' . $v], 'string');
//                            $nonwgid = $this->bsf->isNullCheck($postData['newnonagtwgid_' . $v], 'number');
                            $nonunitid= $this->bsf->isNullCheck($postData['newnonagtunitid_' . $v], 'number');
                            $nonrate= $this->bsf->isNullCheck($postData['newnonagtrate_' . $v], 'number');

                            $insert = $sql->insert();
                            $insert->into('CB_NonAgtItemMaster')
                                ->Values(array('SlNo' => $nonslno, 'Specification' => $nonspec, 'UnitId' => $nonunitid
                                , 'WorkGroupId' => '0', 'Rate' => $nonrate,'WORegisterId'=>$billinfo['WORegisterId']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $generatedNonAgtId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            $NewNonAgtIds[$nonagtId] = $generatedNonAgtId;
                        }

                        $entryrowid = $this->bsf->isNullCheck($postData['entryrowid'],'number');
                        for ($n = 1; $n <= $entryrowid; $n++) {
                            $BillFormatId = $this->bsf->isNullCheck( $postData[ 'BillFormatId_' . $n ], 'number' );
                            $CumAmount = $this->bsf->isNullCheck( $postData[ 'CumAmount_' . $n ], 'number' );
                            $PrevAmount = $this->bsf->isNullCheck( $postData[ 'PrevAmount_' . $n ], 'number' );
                            $CurAmount = $this->bsf->isNullCheck( $postData[ 'CurAmount_' . $n ], 'number' );
                            $BillAbsId = $this->bsf->isNullCheck( $postData[ 'BillAbsId_' . $n ], 'number' );
                            $Formula = $this->bsf->isNullCheck($postData['Formula_' . $n],'string');

                            $update = $sql->update();
                            $update->table('CB_BillAbstract')
                                ->set( array( 'BillId' => $BillId, 'BillFormatId' => $BillFormatId, $strCer.'CumAmount' => $CumAmount
                                , $strCer.'PrevAmount' => $PrevAmount, $strCer.'CurAmount' => $CurAmount, 'Formula' => $Formula ) )
                                ->where(array('BillAbsId' => $BillAbsId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            switch($BillFormatId) {
                                case '1': // Agreement
                                    $boqrowid = $this->bsf->isNullCheck($postData['boqrowid'],'number');

                                    // delete boqs
                                    $boqrowdeleteids = rtrim($this->bsf->isNullCheck($postData['boqrowdeleteids'],'string'), ",");
                                    if($boqrowdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillBOQ')
                                            ->where("BillBOQId IN ($boqrowdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($i = 1; $i <= $boqrowid; $i++) {
                                        $BillBOQId = $this->bsf->isNullCheck($postData['BillBOQId_'. $i],'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['UpdateBOQRow_'. $i],'number');

                                        $WOBOQId = $this->bsf->isNullCheck( $postData[ 'WOBOQId_' . $i ], 'number' );
                                        $UnitId = $this->bsf->isNullCheck( $postData[ 'UnitId_' . $i ], 'number' );
                                        $qty = $this->bsf->isNullCheck( $postData[ 'Qty_' . $i ], 'number' );
                                        $rate = $this->bsf->isNullCheck( $postData[ 'Rate_' . $i ], 'number' );
                                        $amt = $this->bsf->isNullCheck( $postData[ 'Amount_' . $i ], 'number' );
                                        $measurement = $this->bsf->isNullCheck( $postData[ 'Measurement_' . $i ], 'string' );
                                        $cellname = $this->bsf->isNullCheck( $postData[ 'CellName_' . $i ], 'string' );
                                        $SelectedColumns = $this->bsf->isNullCheck( $postData[ 'SelectedColumns_' . $i ], 'string' );

                                        if ( $WOBOQId == 0 || ($qty == 0 && $measurement == '') || ($UpdateBOQRow != 1 && $BillBOQId != 0))
                                            continue;

                                        if($UpdateBOQRow == 0 && $BillBOQId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into( 'CB_BillBOQ' );
                                            $insert->Values( array( 'BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'WOBOQId' => $WOBOQId,
                                                'UnitId' => $UnitId, $strCer.'Rate' => $rate, $strCer.'CurQty' => $qty, $strCer.'CurAmount' => $amt,
                                                $strCer.'CumQty' => $qty, $strCer.'CumAmount' => $amt ) );
                                            $statement = $sql->getSqlStringForSqlObject( $insert );
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            $BillBOQId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateBOQRow == 1 && $BillBOQId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('CB_BillBOQ')
                                                ->set( array( 'BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'WOBOQId' => $WOBOQId, $strCer.'Rate' => $rate,
                                                    'UnitId' => $UnitId, $strCer.'CurQty' => $qty, $strCer.'CurAmount' => $amt, $strCer.'CumQty' => new Expression($strCer.'PrevQty +'.$qty),
                                                    $strCer.'CumAmount' => new Expression($strCer.'PrevAmount +'.$amt) ) )
                                                ->where(array('BillBOQId' => $BillBOQId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        if($measurement != '') {
                                            $delete = $sql->delete();
                                            $delete->from('CB_BillMeasurement')
                                                ->where("BillBOQId=$BillBOQId");
                                            $statement = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $insert = $sql->insert();
                                            $insert->into( 'CB_BillMeasurement' );
                                            $insert->Values( array( 'BillBOQId' => $BillBOQId, $strCer.'Measurement' => $measurement, $strCer.'CellName' => $cellname, $strCer.'SelectedColumns' => $SelectedColumns) );
                                            $statement = $sql->getSqlStringForSqlObject( $insert );
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                        }
                                    }
                                    break;
                                case '2': // Non-Agreement
                                    $naboqrowid = $this->bsf->isNullCheck($postData['naboqrowid'],'number');
                                    // delete boqs
                                    $naboqrowdeleteids = rtrim($this->bsf->isNullCheck($postData['naboqrowdeleteids'],'string'), ",");
                                    if($naboqrowdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillBOQ')
                                            ->where("BillBOQId IN ($naboqrowdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($i = 1; $i <= $naboqrowid; $i++) {
                                        $BillBOQId = $this->bsf->isNullCheck($postData['NABillBOQId_'. $i],'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['NAUpdateBOQRow_'. $i],'number');

                                        $qty = $this->bsf->isNullCheck($postData['NAQty_' . $i],'number');
                                        $rate = $this->bsf->isNullCheck($postData['NARate_' . $i],'number');
                                        $amt = $this->bsf->isNullCheck($postData['NAAmount_' . $i],'number');
                                        $measurement = $this->bsf->isNullCheck( $postData[ 'NAMeasurement_' . $i ], 'string' );
                                        $cellname = $this->bsf->isNullCheck( $postData[ 'NACellName_' . $i ], 'string' );
                                        $SelectedColumns = $this->bsf->isNullCheck( $postData[ 'NASelectedColumns_' . $i ], 'string' );
                                        $NonAgtId = $postData['NABOQId_'.$i];

                                        if ($NonAgtId == '' || ($qty == 0 && $measurement == '') || $rate==0 || ($UpdateBOQRow != 1 && $BillBOQId != 0))
                                            continue;

                                        if(substr($NonAgtId, 0, 3) == 'New')
                                            $nonboqid = $NewNonAgtIds[ $NonAgtId ];
                                        else
                                            $nonboqid  = $this->bsf->isNullCheck($NonAgtId,'number');

                                        if($UpdateBOQRow == 0 && $BillBOQId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('CB_BillBOQ');
                                            $insert->Values(array('BillAbsId' => $BillAbsId,'NonBOQId'=> $nonboqid, 'BillFormatId' => $BillFormatId
                                            ,$strCer.'Rate' => $rate,$strCer.'CurQty'=> $qty,$strCer.'CurAmount'=> $amt, $strCer.'CumQty' => $qty
                                            , $strCer.'CumAmount' => $amt));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            $BillBOQId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateBOQRow == 1 && $BillBOQId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('CB_BillBOQ')
                                                ->set(array('BillAbsId' => $BillAbsId,'NonBOQId'=> $nonboqid,'BillFormatId' => $BillFormatId
                                                , $strCer.'Rate' => $rate, $strCer.'CurQty'=> $qty, $strCer.'CurAmount'=> $amt, $strCer.'CumQty' => new Expression($strCer.'PrevQty +'.$qty)
                                                , $strCer.'CumAmount' => new Expression($strCer.'PrevAmount +'.$amt)))
                                                ->where(array('BillBOQId' => $BillBOQId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                        }

                                        if($measurement != '') {
                                            $delete = $sql->delete();
                                            $delete->from('CB_BillMeasurement')
                                                ->where("BillBOQId=$BillBOQId");
                                            $statement = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $insert = $sql->insert();
                                            $insert->into( 'CB_BillMeasurement' );
                                            $insert->Values( array( 'BillBOQId' => $BillBOQId, $strCer.'Measurement' => $measurement, $strCer.'CellName' => $cellname, $strCer.'SelectedColumns' => $SelectedColumns) );
                                            $statement = $sql->getSqlStringForSqlObject( $insert );
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                        }
                                    }
                                    break;
                                case '3': //Material Advance
                                    $materialadvdeleteids = rtrim($this->bsf->isNullCheck($postData['materialadvdeleteids'],'string'), ",");
                                    if($materialadvdeleteids !== '') {
                                        $subQuery = $sql->select();
                                        $subQuery->from("CB_BillMaterialAdvance")
                                            ->columns(array('MTransId'))
                                            ->where("MTransId IN ($materialadvdeleteids)");

                                        // select urls
                                        $select = $sql->select();
                                        $select->from("CB_BillMaterialBillTrans")
                                            ->columns(array('URL'))
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        // delete all bill trans
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillMaterialBillTrans')
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        // unbind files
                                        foreach($urls as $url) {
                                            if($url['URL'] != '' || !is_null($url['URL'])) {
                                                unlink('public' . $url['URL']);
                                            }
                                        }

                                        // delete all material advance
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillMaterialAdvance')
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    $materialadvbilldeleteids = rtrim($this->bsf->isNullCheck($postData['materialadvbilldeleteids'],'string'), ",");
                                    if($materialadvbilldeleteids !== '') {
                                        // select urls
                                        $select = $sql->select();
                                        $select->from("CB_BillMaterialBillTrans")
                                            ->columns(array('URL'))
                                            ->where("MBillTransId IN ($materialadvbilldeleteids)");
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $delete = $sql->delete();
                                        $delete->from('CB_BillMaterialBillTrans')
                                            ->where("MBillTransId IN ($materialadvbilldeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        // unbind files
                                        foreach($urls as $url) {
                                            if($url['URL'] != '' || !is_null($url['URL'])) {
                                                unlink('public' . $url['URL']);
                                            }
                                        }
                                    }

                                    $materialadvrowid = $this->bsf->isNullCheck($postData['materialadvrowid'],'number');
                                    for ($i = 1; $i <= $materialadvrowid; $i++) {
                                        $MaterialId = $this->bsf->isNullCheck($postData['MaterialId_'. $i],'number');
                                        $qty = $this->bsf->isNullCheck($postData['MQty_' . $i],'number');
                                        $rate = $this->bsf->isNullCheck($postData['MRate_' . $i],'number');
                                        $amt = $this->bsf->isNullCheck($postData['MAmount_' . $i],'number');
                                        $AdvPeramt = $this->bsf->isNullCheck($postData['MAdvancePer_' . $i],'number');
                                        $Advamt = $this->bsf->isNullCheck($postData['MAdvAmount_' . $i],'number');
                                        $PurQty = $this->bsf->isNullCheck($postData['MaterialTotalPurQty_' . $i],'number');
                                        $ConQty = $this->bsf->isNullCheck($postData['MaterialTotalConQty_' . $i],'number');
                                        $MAdvUpdateRow = $this->bsf->isNullCheck($postData['MAdvUpdateRow_'.$i],'number');
                                        $MTransId = $this->bsf->isNullCheck($postData['MAdvTransId_'.$i],'number');

                                        if ($MaterialId == 0 || $qty==0) continue;

                                        if($MAdvUpdateRow == 0 && $MTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('CB_BillMaterialAdvance');
                                            $insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt
                                            ,'AdvPercent'=> $AdvPeramt,'AdvAmount'=> $Advamt,'PurchaseQty'=> $PurQty,'ConsumeQty'=> $ConQty, 'TransType' => $BillType));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $MTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($MAdvUpdateRow == 1 && $MTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('CB_BillMaterialAdvance')
                                                ->set(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt
                                                ,'AdvPercent'=> $AdvPeramt,'AdvAmount'=> $Advamt,'PurchaseQty'=> $PurQty,'ConsumeQty'=> $ConQty))
                                                ->where(array('MTransId' => $MTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        $materialadvbillrowid = $this->bsf->isNullCheck($postData['materialadvbillrowid_'.$i],'number');
                                        for ($j = 1; $j <= $materialadvbillrowid; $j++) {
                                            $BillDate = $this->bsf->isNullCheck($postData['MBill_'.$i.'_BillDate_' . $j],'date');
                                            $BillNo = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillNo_' . $j],'string');
                                            $qty = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillQty_' . $j],'number');
                                            $rate = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillRate_' . $j],'number');
                                            $amt = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MBillAmount_' . $j],'number');
                                            $UpdateBillDeducRow = $this->bsf->isNullCheck($postData['MBill_'.$i.'_UpdateMBillRow_' . $j],'number');
                                            $PBillTransId = $this->bsf->isNullCheck($postData['MBill_'.$i.'_PBillTransId_' . $j],'number');
                                            $url = $this->bsf->isNullCheck($postData['MBill_'.$i.'_DocFile_' . $j], 'string');
                                            $VendorId = $this->bsf->isNullCheck($postData['MBill_'.$i.'_MVendorId_' . $j],'number');

                                            if ($BillDate == null || $BillNo == '' || $VendorId == 0 || $qty == 0 || $rate == 0 || $amt == 0) continue;

                                            if($files['MBill_'.$i.'_DocFile_' . $j]['name']){
                                                $dir = 'public/uploads/clientbilling/default/'.$BillId.'/';
                                                $filename = $this->bsf->uploadFile($dir, $files['MBill_'.$i.'_DocFile_' . $j]);

                                                if($filename) {
                                                    // update valid files only
                                                    $url = '/uploads/clientbilling/bill/'.$BillId.'/' . $filename;
                                                }
                                            }

                                            if($UpdateBillDeducRow == 0 && $PBillTransId == 0) { // New Row
                                                $insert = $sql->insert();
                                                $insert->into('CB_BillMaterialBillTrans');
                                                $insert->Values(array('MTransId' => $MTransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
                                                ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url));
                                                $statement = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            } else if ($UpdateBillDeducRow == 1 && $PBillTransId != 0) { // Update Row
                                                $update = $sql->update();
                                                $update->table('CB_BillMaterialBillTrans')
                                                    ->set(array('MTransId' => $MTransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
                                                    ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url))
                                                    ->where(array('MBillTransId' => $PBillTransId));
                                                $statement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            }
                                        }
                                    }
                                    break;
                                case '18': // Price Escalation
                                    $priceescalationdeleteids = rtrim($this->bsf->isNullCheck($postData['priceescalationdeleteids'],'string'), ",");
                                    if($priceescalationdeleteids !== '') {
                                        $subQuery = $sql->select();
                                        $subQuery->from("CB_BillPriceEscalation")
                                            ->columns(array('MTransId'))
                                            ->where("MTransId IN ($priceescalationdeleteids)");

                                        // select urls
                                        $select = $sql->select();
                                        $select->from("CB_BillPriceEscalationBillTrans")
                                            ->columns(array('URL'))
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        // delete all bill trans
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillPriceEscalationBillTrans')
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        // unbind files
                                        foreach($urls as $url) {
                                            if($url['URL'] != '' || !is_null($url['URL'])) {
                                                unlink('public' . $url['URL']);
                                            }
                                        }

                                        // delete all material advance
                                        $delete = $sql->delete();
                                        $delete->from('CB_BillPriceEscalation')
                                            ->where->expression('MTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    $priceescalationbilldeleteids = rtrim($this->bsf->isNullCheck($postData['priceescalationbilldeleteids'],'string'), ",");
                                    if($priceescalationbilldeleteids !== '') {
                                        // select urls
                                        $select = $sql->select();
                                        $select->from("CB_BillPriceEscalationBillTrans")
                                            ->columns(array('URL'))
                                            ->where("PBillTransId IN ($priceescalationbilldeleteids)");
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $delete = $sql->delete();
                                        $delete->from('CB_BillPriceEscalationBillTrans')
                                            ->where("PBillTransId IN ($priceescalationbilldeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        // unbind files
                                        foreach($urls as $url) {
                                            if($url['URL'] != '' || !is_null($url['URL'])) {
                                                unlink('public' . $url['URL']);
                                            }
                                        }
                                    }

                                    $priceescalationrowid = $this->bsf->isNullCheck($postData['priceescalationrowid'],'number');
                                    for ($i = 1; $i <= $priceescalationrowid; $i++) {
                                        $MaterialId = $this->bsf->isNullCheck($postData['EMaterialId_'. $i],'number');
                                        $qty = $this->bsf->isNullCheck($postData['EMQty_' . $i],'number');
                                        $brate = $this->bsf->isNullCheck($postData['EMRate_' . $i],'number');
                                        $escPer = $this->bsf->isNullCheck($postData['EMEscPercent_' . $i],'number');
                                        $advPer = $this->bsf->isNullCheck($postData['EMAdvancePer_' . $i],'number');
                                        $amt = $this->bsf->isNullCheck($postData['EMAmount_' . $i],'number');
                                        $rateCondition = $this->bsf->isNullCheck($postData['ERateCondition_' . $i],'string');
                                        $EUpdateRow = $this->bsf->isNullCheck($postData['EUpdateRow_'.$i],'number');
                                        $ETransId = $this->bsf->isNullCheck($postData['ETransId_'.$i],'number');

                                        if ($MaterialId == 0 || $qty==0) continue;

                                        if($EUpdateRow == 0 && $ETransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('CB_BillPriceEscalation');
                                            $insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Qty'=> $qty
                                            ,'BaseRate'=> $brate,'EscalationPer'=> $escPer,'ActualRate'=> $advPer,'Amount'=> $amt, 'RateCondition' => $rateCondition, 'TransType' => $BillType));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $ETransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($EUpdateRow == 1 && $ETransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('CB_BillPriceEscalation')
                                                ->set(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $MaterialId,'Qty'=> $qty
                                                ,'BaseRate'=> $brate,'EscalationPer'=> $escPer,'ActualRate'=> $advPer,'Amount'=> $amt, 'RateCondition' => $rateCondition))
                                                ->where(array('MTransId' => $ETransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        $embillrowid = $this->bsf->isNullCheck($postData['embillrowid_'.$i],'number');
                                        for ($j = 1; $j <= $embillrowid; $j++) {
                                            $BillDate = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillDate_' . $j],'date');
                                            $BillNo = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillNo_' . $j],'string');
                                            $qty = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillQty_' . $j],'number');
                                            $rate = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillRate_' . $j],'number');
                                            $amt = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillAmount_' . $j],'number');
                                            $UpdateBillDeducRow = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_UpdateMBillRow_' . $j],'number');
                                            $PBillTransId = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PBillTransId_' . $j],'number');
                                            $url = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_DocFile_' . $j], 'string');
                                            $VendorId = $this->bsf->isNullCheck($postData['EMBill_'.$i.'_PVendorId_' . $j],'number');

                                            if ($BillDate == null || $BillNo == '' || $VendorId == 0 || $qty == 0 || $rate == 0 || $amt == 0) continue;

                                            if($files['EMBill_'.$i.'_DocFile_' . $j]['name']){
                                                $dir = 'public/uploads/clientbilling/bill/'.$BillId.'/';
                                                $filename = $this->bsf->uploadFile($dir, $files['EMBill_'.$i.'_DocFile_' . $j]);

                                                if($filename) {
                                                    // update valid files only
                                                    $url = '/uploads/clientbilling/bill/'.$BillId.'/' . $filename;
                                                }
                                            }

                                            if($UpdateBillDeducRow == 0 && $PBillTransId == 0) { // New Row
                                                $insert = $sql->insert();
                                                $insert->into('CB_BillPriceEscalationBillTrans');
                                                $insert->Values(array('MTransId' => $ETransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
                                                ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url));
                                                $statement = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            } else if ($UpdateBillDeducRow == 1 && $PBillTransId != 0) { // Update Row
                                                $update = $sql->update();
                                                $update->table('CB_BillPriceEscalationBillTrans')
                                                    ->set(array('MTransId' => $ETransId,'BillDate' => date('Y-m-d', strtotime($BillDate)),'BillNo'=> $BillNo
                                                    ,'VendorId' => $VendorId,'Qty' => $qty,'Rate' => $rate,'Amount' => $amt, 'URL' => $url))
                                                    ->where(array('PBillTransId' => $PBillTransId));
                                                $statement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                            }

                                        }
                                    }
                                    break;
                                case '5': // MobAdvance Recovery
                                    $delete = $sql->delete();
                                    $delete->from('CB_BillAdvanceRecovery')
                                        ->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>5));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $mobadvrecoveryrowid = $this->bsf->isNullCheck($postData['mobadvrecoveryrowid'],'number');
                                    for ($i = 1; $i <= $mobadvrecoveryrowid; $i++) {
                                        $AdvRecoveryReceiptId = $this->bsf->isNullCheck($postData['mobAdvRecoveryReceiptId_'. $i],'number');
                                        $curAmt = $this->bsf->isNullCheck($postData['mobAdvRecoveryCurrent_' . $i],'number');

                                        if ($curAmt == 0 || ($AdvRecoveryReceiptId == 0)) continue;

                                        $insert = $sql->insert();
                                        $insert->into('CB_BillAdvanceRecovery');
                                        $insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId, 'ReceiptId' => $AdvRecoveryReceiptId
                                        ,'BillFormatId' => 5, $strCer.'Amount'=> $curAmt ));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '6': // Advance Recovery
                                    $delete = $sql->delete();
                                    $delete->from('CB_BillAdvanceRecovery')
                                        ->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>6));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $advrecoveryrowid = $this->bsf->isNullCheck($postData['advrecoveryrowid'],'number');
                                    for ($i = 1; $i <= $advrecoveryrowid; $i++) {
                                        $AdvRecoveryReceiptId = $this->bsf->isNullCheck($postData['AdvRecoveryReceiptId_'. $i],'number');
                                        $curAmt = $this->bsf->isNullCheck($postData['AdvRecoveryCurrent_' . $i],'number');

                                        if ($curAmt == 0) continue;

                                        $insert = $sql->insert();
                                        $insert->into('CB_BillAdvanceRecovery');
                                        $insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId, 'ReceiptId' => $AdvRecoveryReceiptId
                                        ,'BillFormatId' => 6, $strCer.'Amount'=> $curAmt ));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '21': // Material Advance Recovery
                                    $delete = $sql->delete();
                                    $delete->from('CB_BillAdvanceRecovery')
                                        ->where(array("BillAbsId" => $BillAbsId, "BillFormatId" =>21));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $madvrecoveryrowid = $this->bsf->isNullCheck($postData['madvrecoveryrowid'],'number');
                                    for ($i = 1; $i <= $madvrecoveryrowid; $i++) {
                                        //$AdvRecoveryBillId = $this->bsf->isNullCheck($postData['MAdvRecoveryBillId_'. $i],'number');
                                        $curAmt = $this->bsf->isNullCheck($postData['MAdvRecoveryCurrent_' . $i],'number');

                                        if ($curAmt == 0) continue;

                                        $insert = $sql->insert();
                                        $insert->into('CB_BillAdvanceRecovery');
                                        $insert->Values(array('BillAbsId' => $BillAbsId, 'BillId' => $BillId,'BillFormatId' => 21, $strCer.'Amount'=> $curAmt ));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '8': // Material Recovery
                                    $delete = $sql->delete();
                                    $delete->from('CB_BillMaterialRecovery')
                                        ->where(array("BillAbsId" => $BillAbsId));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $mrecoveryrowid = $this->bsf->isNullCheck($postData['mrecoveryrowid'],'number');
                                    for ($i = 1; $i <= $mrecoveryrowid; $i++) {
                                        $RMaterialId = $this->bsf->isNullCheck($postData['RMaterialId_'. $i],'number');
                                        $qty = $this->bsf->isNullCheck($postData['RMQty_' . $i],'number');
                                        $rate = $this->bsf->isNullCheck($postData['RMRate_' . $i],'number');
                                        $amt = $this->bsf->isNullCheck($postData['RMAmount_' . $i],'number');

                                        if ($RMaterialId == 0 || $qty==0) continue;

                                        $insert = $sql->insert();
                                        $insert->into('CB_BillMaterialRecovery');
                                        $insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $RMaterialId
                                        ,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt, 'TransType' => $BillType ));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '7': // Bill Deduction
                                    // delete vendor bills
                                    $billdeductiondeleteids = rtrim($this->bsf->isNullCheck($postData['billdeductiondeleteids'],'string'), ",");
                                    if($billdeductiondeleteids !== '') {
                                        // select urls
                                        $select = $sql->select();
                                        $select->from("CB_BillVendorBill")
                                            ->columns(array('URL'))
                                            ->where("TransId IN ($billdeductiondeleteids)");
                                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                                        $urls = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $delete = $sql->delete();
                                        $delete->from('CB_BillVendorBill')
                                            ->where("TransId IN ($billdeductiondeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        // unbind files
                                        foreach($urls as $url) {
                                            if($url['URL'] != '' || !is_null($url['URL'])) {
                                                unlink('public' . $url['URL']);
                                            }
                                        }
                                    }

                                    $billdeductionrowid = $this->bsf->isNullCheck($postData['billdeductionrowid'],'number');
                                    for ($i = 1; $i <= $billdeductionrowid; $i++) {
                                        $DBillDate = $this->bsf->isNullCheck($postData['DBillDate_'. $i],'date');
                                        $DBillNo = $this->bsf->isNullCheck($postData['DBillNo_'. $i],'string');
                                        $amt = $this->bsf->isNullCheck($postData['DAmount_' . $i],'number');
                                        $UpdateBillDeducRow = $this->bsf->isNullCheck($postData['UpdateDBillRow_' . $i],'number');
                                        $TransId = $this->bsf->isNullCheck($postData['DBillTransId_' . $i],'number');
                                        $url = $this->bsf->isNullCheck($postData['DDocFile_' . $i], 'string');
                                        $DVendorId = $this->bsf->isNullCheck($postData['DVendorId_'. $i],'number');

                                        if ($DVendorId == 0 || $DBillDate== null || $DBillNo == '' || $amt == 0) continue;

                                        if($files['DDocFile_' . $i]['name']){
                                            $dir = 'public/uploads/clientbilling/bill/'.$BillId.'/';
                                            $filename = $this->bsf->uploadFile($dir, $files['DDocFile_' . $i]);

                                            if($filename) {
                                                // update valid files only
                                                $url = '/uploads/clientbilling/bill/'.$BillId.'/' . $filename;
                                            }
                                        }

                                        if($UpdateBillDeducRow == 0 && $TransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('CB_BillVendorBill');
                                            $insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'VendorId' => $DVendorId
                                            , 'BillDate' => date('Y-m-d', strtotime($DBillDate)) ,'BillNo' => $DBillNo,'Amount'=> $amt, 'URL' => $url, 'TransType' => $BillType ));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                                        } else if ($UpdateBillDeducRow == 1 && $TransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('CB_BillVendorBill')
                                                ->set( array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'VendorId' => $DVendorId
                                                , 'BillDate' => date('Y-m-d', strtotime($DBillDate)) ,'BillNo' => $DBillNo,'Amount'=> $amt, 'URL' => $url ))
                                                ->where(array('TransId' => $TransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '19': // Free Supply Material
                                    $delete = $sql->delete();
                                    $delete->from('CB_BillFreeSupplyMaterial')
                                        ->where(array("BillAbsId" => $BillAbsId));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $fsmaterialrowid = $this->bsf->isNullCheck($postData['fsmaterialrowid'],'number');
                                    for ($i = 1; $i <= $fsmaterialrowid; $i++) {
                                        $FSMaterialId = $this->bsf->isNullCheck($postData['FSMaterialId_'. $i],'number');
                                        $qty = $this->bsf->isNullCheck($postData['FSMQty_' . $i],'number');
                                        $rate = $this->bsf->isNullCheck($postData['FSMRate_' . $i],'number');
                                        $amt = $this->bsf->isNullCheck($postData['FSMAmount_' . $i],'number');

                                        if ($FSMaterialId == 0 || $qty==0) continue;

                                        $insert = $sql->insert();
                                        $insert->into('CB_BillFreeSupplyMaterial');
                                        $insert->Values(array('BillAbsId' => $BillAbsId, 'BillFormatId' => $BillFormatId, 'MaterialId' => $FSMaterialId
                                        ,'Rate' => $rate,'Qty'=> $qty,'Amount'=> $amt, 'TransType' => $BillType ));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                            }
                        }

                        //Save Role
                        $MBillNo = $this->bsf->isNullCheck($postData['MBillNo'],'string');
                        if($BillType == 'S' ) {
                            if(isset($postData['isSubCer'])){
                                CommonHelper::insertCBLog('Client-Bill-Submit-Approve', $BillId, $MBillNo, $dbAdapter);
                            } else{
                                CommonHelper::insertCBLog('Client-Bill-Submit-Edit', $BillId, $MBillNo, $dbAdapter);
                            }
                        } elseif($BillType == 'C') {
                            if(isset($postData['isSubCer'])){
                                CommonHelper::insertCBLog('Client-Bill-Certify-Approve', $BillId, $MBillNo, $dbAdapter);
                            } else {
                                CommonHelper::insertCBLog('Client-Bill-Certify-Edit', $BillId, $MBillNo, $dbAdapter);
                            }
                        }

                        // trigger mail
                        $userId = $this->auth->getIdentity()->UserId;
                        $select = $sql->select();
                        $select->from("WF_Users")
                            ->columns(array('Email'))
                            ->where("UserId=$userId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $user = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => "CB_BillMaster"))
                            ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array('WONo', 'WODate'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'WF_OperationalCostCentre'), 'b.CostCentreId=d.CostCentreId', array('CostCentreName'), $select:: JOIN_LEFT)
                            ->where(array("a.BillId" => $BillId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $bill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($BillType == 'S' && $oldBillData['IsSubmittedBill'] != $bill['IsSubmittedBill']){
                            $mailData = array(
                                array(
                                    'name' => 'ORDERID',
                                    'content' => $bill['WONo']
                                ),
                                array(
                                    'name' => 'DATE',
                                    'content' => date('d-m-Y', strtotime($bill['WODate']))
                                ),
                                array(
                                    'name' => 'PROJECTNAME',
                                    'content' => $bill['CostCentreName']
                                ),
                                array(
                                    'name' => 'CLIENTNAME',
                                    'content' => $bill['ClientName']
                                ),
                                array(
                                    'name' => 'BILLNUMBER',
                                    'content' => $bill['BillNo']
                                ),
                                array(
                                    'name' => 'BILLDATE',
                                    'content' => date('d-m-Y', strtotime($bill['BillDate']))
                                ),
                                array(
                                    'name' => 'AMOUNT',
                                    'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['SubmitAmount'],2,true)
                                )
                            );

                            if($user && $user['Email'] != '') {
                                $sm = $this->getServiceLocator();
                                $config = $sm->get('application')->getConfig();
                                $viewRenderer->MandrilSendMail()->sendMailTo( $user[ 'Email' ], $config['general']['mandrilEmail'], 'Bill Submitted', 'cb_billsubmitted', $mailData );
                            }
                        } elseif($BillType == 'C' && $oldBillData['IsCertifiedBill'] != $bill['IsCertifiedBill']){
                            $mailData = array(
                                array(
                                    'name' => 'ORDERID',
                                    'content' => $bill['WONo']
                                ),
                                array(
                                    'name' => 'DATE',
                                    'content' => date('d-m-Y', strtotime($bill['WODate']))
                                ),
                                array(
                                    'name' => 'PROJECTNAME',
                                    'content' => $bill['ProjectName']
                                ),
                                array(
                                    'name' => 'CLIENTNAME',
                                    'content' => $bill['ClientName']
                                ),
                                array(
                                    'name' => 'BILLNUMBER',
                                    'content' => $bill['BillNo']
                                ),
                                array(
                                    'name' => 'BILLDATE',
                                    'content' => date('d-m-Y', strtotime($bill['BillDate']))
                                ),
                                array(
                                    'name' => 'SUBMITTEDAMOUNT',
                                    'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['SubmitAmount'],2,true)
                                ),
                                array(
                                    'name' => 'CERTIFIEDAMOUNT',
                                    'content' => $viewRenderer->commonHelper()->sanitizeNumber($bill['CertifyAmount'],2,true)
                                )
                            );

                            if($user && $user['Email'] != '') {
                                $sm = $this->getServiceLocator();
                                $config = $sm->get('application')->getConfig();
                                $viewRenderer->MandrilSendMail()->sendMailTo($user['Email'], $config['general']['mandrilEmail'], 'Bill Certified', 'cb_billcertified', $mailData );
                            }
                        }
                    }

                    $connection->commit();
                    $this->redirect()->toRoute('clientbilling/default', array('controller' => 'index', 'action' => 'billselection'));
                } catch ( PDOException $e ) {
                    $connection->rollback();
                }
            }
            else {
                $editid = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
                $mode = $this->bsf->isNullCheck( $this->params()->fromRoute( 'mode' ), 'string' );
                $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

                // check for bill type
                if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
                    $this->redirect()->toRoute( 'clientbilling/default', array( 'controller' => 'index', 'action' => 'billselection' ) );

                // check for bill id
                $select = $sql->select();
                $select->from(array('a' => "CB_BillMaster"))
                    ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select:: JOIN_LEFT)
                    ->columns(array('BillId'))
                    ->where("a.BillId=$editid");
                $statement = $sql->getSqlStringForSqlObject($select);
                if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
                    $this->redirect()->toRoute( 'clientbilling/default', array( 'controller' => 'index', 'action' => 'billselection' ) );

                if ($editid != 0) {
                    // Bill Info
                    $select = $sql->select();
                    $select->from(array('a' => "CB_BillMaster"))
                        ->join(array('b' => 'Proj_WORegister'), 'a.WORegisterId=b.WORegisterId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WORegisterId', 'CostCentreId'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ClientMaster'), 'b.ClientId=c.ClientId', array('ClientName'), $select:: JOIN_LEFT)
                        ->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
                                'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks'))
                        ->where("a.BillId=$editid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if ($billinfo) {
                        $billinfo['TransType'] = $type;
                        $WOId = $billinfo['WORegisterId'];
                        $CostCentreId = $billinfo['CostCentreId'];
                        $billType = $billinfo['BillType'];
                        if($billType=="R" || $billType=="F" || $billType=="S" )
                            $billType = array('R', 'S', 'F');
                        else
                            $billType = array($billinfo['BillType']);

                        /* BillFormatTransId Update */
                        $select = $sql->select();
                        $select->from( array( 'a' => 'CB_BillAbstract' ) )
                            ->columns(array('BillFormatId','BillAbsId','Formula'))
                            ->where( "a.BillId=$editid AND a.BillFormatTransId=0 AND a.BillFormatId<>0");
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $BillAbsupdate = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($BillAbsupdate as &$bilAbsFTupdate) {
                            $billFormatId= $bilAbsFTupdate['BillFormatId'];
                            $billAbsId= $bilAbsFTupdate['BillAbsId'];
                            $billFormula= $bilAbsFTupdate['Formula'];

                            $select = $sql->select();
                            $select->from( array( 'a' => 'CB_BillFormatTrans' ) )
                                ->columns(array('BillFormatTransId'))
                                    ->where( "a.CostCentreId=$CostCentreId AND a.BillFormatId=$billFormatId");
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $BillAbsupdateList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                            foreach($BillAbsupdateList as &$billAbsupdateList) {
                                $update = $sql->update();
                                $update->table('CB_BillAbstract')
                                    ->set( array('BillFormatTransId' => $billAbsupdateList['BillFormatTransId'] ))
                                    ->where(array('billAbsId' => $billAbsId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        /* BillFormatTransId Update */
                        $sCer = "";
                        if($type == 'C') {
                            $sCer = "Cer";
                        }

                        $select = $sql->select();
                        $select->from( array( 'a' => 'CB_BillFormatTrans' ) )
                            ->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount' =>$sCer.'CumAmount','PrevAmount' =>$sCer.'PrevAmount','CurAmount' =>$sCer.'CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
                            ->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header','Bold', 'Italic', 'Underline'))
                            ->where( "a.CostCentreId=$CostCentreId AND c.BillId=$editid")
                            ->order('a.SortId');
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach($BillFormat as &$Format) {
                            $billFormatId= $Format['BillFormatId'];
                            $billAbsId= $Format['BillAbsId'];
                            switch($billFormatId) {
                                case '1': // Agreement
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillBOQ' ) )
                                        ->join( array( 'b' => 'Proj_TenderWOTrans' ), 'a.WOBOQId=b.PrevTenderWOTransId', array( 'SerialNo','Specification', 'unit' => 'UnitId'), $select::JOIN_LEFT )
                                        ->join( array( 'b1' => 'Proj_TenderWOTrans' ), 'b.PrevTenderWOTransId=b1.PrevTenderWOTransId', array( 'TotalQty' => 'Qty'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'a.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                                        ->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount', 'CumQty' => $sCer. 'CumQty', 'BalQty' => new Expression("b.Qty-a.CumQty")))
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '2': // Non-Agreement
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillBOQ' ) )
                                        ->join( array( 'b' => 'CB_NonAgtItemMaster' ), 'a.NonBOQId=b.NonBOQId', array( 'SlNo','Specification','NonBOQId'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement','CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                                        ->columns(array('BillBOQId', 'NonBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '3': //Material Advance
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillMaterialAdvance' ) )
                                        ->join( array( 'b' => 'Proj_Resource' ), 'a.MaterialId=b.ResourceId', array( 'MaterialName' => 'ResourceName'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                    foreach($Format['AddRow'] as &$advance) {
                                        $MTransId = $advance['MTransId'];
                                        $select = $sql->select();
                                        $select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
                                            ->join( array( 'b' => 'Vendor_Master' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
                                            ->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
                                            ->where( "a.MTransId=$MTransId");
                                        $statement = $sql->getSqlStringForSqlObject( $select );
                                        $advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    }
                                    break;
                                case '18': // Price Escalation
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
                                        ->join( array( 'b' => 'Proj_Resource' ), 'a.MaterialId=b.ResourceId', array( 'MaterialName' => 'ResourceName'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                    foreach($Format['AddRow'] as &$advance) {
                                        $MTransId = $advance['MTransId'];
                                        $select = $sql->select();
                                        $select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
                                            ->join( array( 'b' => 'Vendor_Master' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
                                            ->columns(array('PBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
                                            ->where( "a.MTransId=$MTransId");
                                        $statement = $sql->getSqlStringForSqlObject( $select );
                                        $advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    }
                                    break;
                                case '5': // MobAdvRecovery
                                    // Advance Recovery (Receipt & Material Advance)
                                    $select = $sql->select();
                                    $select->from( array('a' => 'CB_ReceiptRegister' ))
                                        ->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
                                        ->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'M'));

                                    $select2 = $sql->select();
                                    $select2->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
                                        ->where(array('b.BillId' => $editid, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select2->combine($select,'Union ALL');

                                    $select21 = $sql->select();
                                    $select21->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
                                        ->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select21->where("b.BillId<>$editid");
                                    $select21->combine($select2,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$select21))
                                        ->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
                                            array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
                                        ->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
                                    $select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
                                    $select3->order('g.ReceiptId');
                                    $statement = $sql->getSqlStringForSqlObject($select3);
                                    $Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '6': // Advance Recovery
                                    //Advance Recovery Receipt
                                    $select = $sql->select();
                                    $select->from( array('a' => 'CB_ReceiptRegister' ))
                                        ->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
                                        ->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => $WOId ,'a.ReceiptAgainst' => 'A'));

                                    $select2 = $sql->select();
                                    $select2->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
                                        ->where(array('b.BillId' => $editid, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select2->where("b.ReceiptId<>0");
                                    $select2->combine($select,'Union ALL');

                                    $select21 = $sql->select();
                                    $select21->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
                                        ->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select21->where("b.BillId<>$editid AND b.ReceiptId<>0");
                                    $select21->combine($select2,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$select21))
                                        ->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
                                            array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
                                        ->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
                                    $select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
                                    $select3->order('g.ReceiptId');
                                    $statement = $sql->getSqlStringForSqlObject($select3);
                                    $Format['Receipt'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '21': // Material Advance Recovery
                                    //Advance Recovery BillAbstract FormatTypeId=3
                                    /**/
                                    $select = $sql->select();
                                    $select->from( array('a' => 'CB_BillAbstract' ))
                                        ->columns(array( 'BillId', 'BillFormatId' => new Expression("21"), 'Amount' => new Expression("a.".$sCer."CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
                                        ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
                                        ->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
                                        ->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
                                    $select->where("a.CurAmount<>0 ");

                                    $selectsub = $sql->select();
                                    $selectsub->from(array("g1"=>$select))
                                        ->columns(array('BillAbsId' => new Expression("h.BillAbsId"), '*'))
                                        ->join(array('h' => 'CB_BillAbstract'), 'g1.BillId=h.BillId and g1.BillFormatId=h.BillFormatId', array(), $selectsub::JOIN_INNER);
                                    /**/
                                    $select21 = $sql->select();
                                    $select21->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
                                        ->where(array('b.BillId' => $editid, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
                                    $select21->combine($selectsub,'Union ALL');

                                    $select2 = $sql->select();
                                    $select2->from(array("b"=>"CB_BillAdvanceRecovery"))
                                        ->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
                                        ->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
                                    $select2->where("b.BillId<>$editid");
                                    $select2->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
                                    $select2->combine($select21,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g"=>$select2))
                                        ->columns(array("BillAbsId","BillId","BillFormatId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount+g.CurAmount)"), "CurAmount"=>new Expression("Sum(g.CurAmount)") ),
                                            array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ))
                                        ->join(array('c' => 'CB_BillMaster'), 'g.BillId=c.BillId', array( "BillNo", "BillDate" => new Expression("FORMAT(c.BillDate, 'dd-MM-yyyy')") ), $select3::JOIN_INNER);
                                    $select3->group(new Expression('g.BillAbsId,g.BillId,g.BillFormatId,c.BillNo,c.BillDate'));
                                    $select3->order('g.BillId');
                                    $statement = $sql->getSqlStringForSqlObject($select3);
                                    $Format['BillAbstract'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '8': // Material Recovery
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillMaterialRecovery' ) )
                                        ->join( array( 'b' => 'Proj_Resource' ), 'a.MaterialId=b.ResourceId', array( 'MaterialId' => 'ResourceId','MaterialName' => 'ResourceName'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '7': // Bill Deduction
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillVendorBill' ) )
                                        ->join( array( 'b' => 'Vendor_Master' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
                                        ->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                                case '19': // Free Supply Material
                                    $select = $sql->select();
                                    $select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
                                        ->join( array( 'b' => 'Proj_Resource' ), 'a.MaterialId=b.ResourceId', array( 'MaterialId' => 'ResourceId','MaterialName' => 'ResourceName'), $select::JOIN_LEFT )
                                        ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
                                        ->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    break;
                            }
                        }
                        $this->_view->BillFormat = $BillFormat;

                        $select = $sql->select();
                        $select->from('CB_ReceiptRegister')
                            ->columns( array('ReceiptNo', "ReceiptDate" =>  new Expression("FORMAT(ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst", "ReceiptMode", "Amount" ))
                            ->where( "WORegisterId='$WOId' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->ReceiptDetails = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        $select = $sql->select();
                        $select->from( array( 'a' => 'Proj_TenderWOTrans' ) )
                            ->join( array( 'b' => 'Proj_UOM' ), 'a.UnitId=b.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
                            ->columns( array("value" => new Expression( "a.SerialNo + ' ' + Specification" ), "UnitId", "Rate" , 'Qty',
                                'data'=> 'PrevTenderWOTransId', 'ParentId'))
                            ->where( "a.WORegisterId='$WOId'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->boq_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        $select = $sql->select();
                        $select->from( array( 'a' => 'CB_NonAgtItemMaster' ) )
                            ->join( array( 'b' => 'Proj_UOM' ), 'a.UnitId=b.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
                            ->columns( array( "data" => 'NonBOQId', "value" => new Expression( "SlNo + ' ' + Specification" ), "UnitId", "Rate"),array('UnitName'))
                            ->where( "a.WORegisterId='$WOId'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->nonboq_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        // Material Advance - materials list
                        $select = $sql->select();
                        $select->from( array( 'a' => 'Proj_WOMaterialAdvance' ) )
                            ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'value' => 'ResourceName', 'UnitId' ), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
                            ->columns( array( "data" => 'ResourceId',"AdvPercent"))
                            ->where( "a.WORegisterId='$WOId'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->materialadv_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        // Material Recovery - materials list
                        $select = $sql->select();
                        $select->from( array( 'a' => 'Proj_WOClientSupplyMaterial' ) )
                            ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'value' => 'ResourceName', 'UnitId' ), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT )
                            ->columns( array( "data" => 'ResourceId', 'Rate') )
                            ->where( "a.WORegisterId='$WOId' AND SType ='C'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->recoverymaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        // Price Escalation - materials list
                        $select = $sql->select();
                        $select->from( array( 'a' => 'Proj_WOMaterialPriceEscalation' ) )
                            ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'value' => 'ResourceName', 'UnitId' ), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
                            ->columns( array( "data" => 'ResourceId', "Rate", "EscalationPer","RateCondition","ActualRate"))
                            ->where( "a.WORegisterId='$WOId'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->priceescmaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        // Free Supply Material - materials list
                        $select = $sql->select();
                        $select->from( array( 'a' => 'Proj_WOClientSupplyMaterial' ) )
                            ->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'value' => 'ResourceName', 'UnitId' ), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitName' ), $select::JOIN_LEFT)
                            ->columns( array( "data" => 'ResourceId', 'Rate') )
                            ->where( "a.WORegisterId='$WOId' AND SType ='F'" );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->freesupplymaterial_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        $select = $sql->select();
                        $select->from( array( 'a' => 'CB_WorkGroupMaster' ) )
                            ->columns( array( "data" => 'WorkGroupId', "value" => 'WorkGroupName'));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->workgroup_lists = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    }
                    $this->_view->billinfo = $billinfo;
                }

                // vendors
                $select = $sql->select();
                $select->from('Vendor_Master' )
                    ->columns(array("data"=>'VendorId',"value"=> "VendorName"))
                    ->where(array('DeleteFlag' => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->vendors = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                //Units
                $select = $sql->select();
                $select->from('Proj_UOM')
                    ->columns(array("data"=>'UnitId', "value"=>'UnitName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // Excel Templates
                $select = $sql->select();
                $select->from('Proj_MeasurementTemplate')
                    ->columns(array('TemplateId','TemplateName','Description'))
                    ->where(array('DeleteFlag' => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->exceltemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->billid = $editid;
                $this->_view->mode = $mode;
            }

            $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
            return $this->_view;
        }

        public function deletebillAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            if($this->getRequest()->isXmlHttpRequest())	{
                $this->_view->setTerminal(true);
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $status = "failed";
                    $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $sql = new Sql($dbAdapter);
                    try {
                        $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                        $BillId = $this->bsf->isNullCheck($this->params()->fromPost('BillId'), 'number');
                        $BillType = $this->bsf->isNullCheck($this->params()->fromPost('BillType'), 'string');

                        $WORegisterId = $this->params()->fromPost('WORegisterId');
                        $response = $this->getResponse();
                        $connection->beginTransaction();

                        $select = $sql->select();
                        $select->from( 'CB_BillMaster' )
                            ->columns( array( 'BillNo', 'IsSubmittedBill', 'IsCertifiedBill' ) )
                            ->where( array( "BillId" => $BillId ) );
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $Bill = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                        //check for bill certificated and submitted
                        if ( !$Bill ) {
                            $response->setStatusCode( '403' );
                            $response->setContent( 'Not able to delete this bill!' );
                            return $response;
                        }

                        if ( $BillType == 'C' && $Bill[ 'IsCertifiedBill' ] == '1' ) {
                            $response->setStatusCode( '403' );
                            $response->setContent( 'Not able to delete this bill, since certify bill is approved!' );
                            return $response;
                        }
                        elseif ( $BillType == 'S' && $Bill[ 'IsSubmittedBill' ] == '1' ) {
                            $response->setStatusCode( '403' );
                            $response->setContent( 'Not able to delete this bill, since submit bill is approved!' );
                            return $response;
                        }

                        switch($Type) {
                            case 'check':
                                // check for receipt
                                $select = $sql->select();
                                $select->from( array( 'a' => 'CB_ReceiptAdustment' ) )
                                    ->join( array( 'b' => 'CB_ReceiptRegister' ), 'a.ReceiptId=b.ReceiptId', array(), $select::JOIN_INNER )
                                    ->columns( array( 'ReceiptId' ) )
                                    ->where( "a.BillId=$BillId AND a.Amount>0 AND b.DeleteFlag=0 AND b.WORegisterId=$WORegisterId" );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $receipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                if ( count( $receipts ) ) {
                                    $response->setStatusCode( '403' );
                                    $response->setContent( 'Not able to delete this bill, since there were receipts entries!' );
                                    return $response;
                                }

                                $response->setStatusCode('200');
                                $status = 'Not used';
                                break;
                            case 'update':
                                $Remarks =  $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');

                                $select = $sql->select();
                                $select->from('CB_BillMaster')
                                    ->columns(array('BillNo'))
                                    ->where(array("BillId" => $BillId));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                                $billNo =$bills->BillNo;

                                if($Bill['IsSubmittedBill'] == '0' && $Bill['IsCertifiedBill'] == '0') {
                                    $update = $sql->update();
                                    $update->table('CB_BillMaster')
                                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                        ->where(array('BillId' => $BillId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } elseif ($BillType == 'C' && $Bill['IsCertifiedBill'] == '0') {
                                    $update = $sql->update();
                                    $update->table('CB_BillMaster')
                                        ->set(array('Certified' => '0', 'CertifyAmount' => '0'))
                                        ->where(array('BillId' => $BillId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                CommonHelper::insertCBLog('Client-Bill-Delete',$BillId,$billNo,$dbAdapter);
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

        public function checknonagtitemAction(){
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

                    $Spec = $this->bsf->isNullCheck($this->params()->fromPost('Spec'), 'string');
                    $select = $sql->select();
                    $select->from('CB_NonAgtItemMaster')
                        ->columns( array( 'NonBOQId'))
                        ->where( "Specification='$Spec' AND DeleteFlag=0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $ans ='N';
                    if (sizeof($results) !=0 )
                        $ans ='Y';

                    return $this->getResponse()->setContent($ans);
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

            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                if ($request->isPost()) {
                    $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                    $sql = new Sql($dbAdapter);

                    $TemplateId = $this->bsf->isNullCheck($this->params()->fromPost('TemplateId'), 'number');
                    $select = $sql->select();
                    $select->from('Proj_MeasurementTemplate')
                        ->columns( array('Description','CellName', 'SelectedColumns'))
                        ->where( "TemplateId='$TemplateId'" );
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($results)
                        return $this->getResponse()->setContent(json_encode($results));

                    return $this->getResponse()->setStatus(201)->setContent('Not Found');
                }
            }
        }

        public function checkboqfoundAction(){
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

                    $woboqid = $this->bsf->isNullCheck($this->params()->fromPost('woboqid'), 'string');
                    $billid = $this->bsf->isNullCheck($this->params()->fromPost('billid'), 'string');
                    $woid = $this->bsf->isNullCheck($this->params()->fromPost('woid'), 'string');

                    $select = $sql->select();
                    $select->from(array('a' =>'CB_BillAbstract'))
                        ->join(array('b' => 'CB_BillBOQ'), 'a.BillAbsId = b.BillAbsId', array('WOBOQId'), $select::JOIN_INNER)
                        ->join(array('c' => 'CB_BillMaster'), 'a.BillId = c.BillId', array(), $select::JOIN_INNER)
                        ->columns( array())
                        ->where( "b.WOBOQId = $woboqid AND a.BillId < $billid AND c.WORegisterId = $woid AND (b.CurQty <> 0 or b.CerCurQty <> 0)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $ans ='N';
                    if (sizeof($results) !=0 )
                        $ans ='Y';

                    return $this->getResponse()->setContent($ans);
                }
            }
        }

        public function uploadboqdataAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            if($this->getRequest()->isXmlHttpRequest())	{
                $request = $this->getRequest();
                $response = $this->getResponse();
                if ($request->isPost()) {
                    $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                    $sql = new Sql($dbAdapter);
                    $uploadedFile = $request->getFiles();

                    if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                        $response->setContent('Invalid File Format');
                        $response->setStatusCode(400);
                        return $response;
                    }

                    $file_csv = "public/uploads/clientbilling/tmp/" . md5(time()) .".csv";
                    $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                    $data = array();
                    $file = fopen($file_csv, "r");

                    $icount = 0;
                    $bValid =true;

                    while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {
                        if ($icount == 0) {
                            foreach ($xlData as $j => $value) {
                                if (trim($value) == "Specification")
                                    $col_1 = $j;
                                if (trim($value) == "Unit")
                                    $col_2 = $j;
                                if (trim($value) == "Qty")
                                    $col_3 = $j;
                                if (trim($value) == "Rate")
                                    $col_4 = $j;
                            }
                        } else {
                            if (!isset($col_1) || !isset($col_2) || !isset($col_3) || !isset($col_4)) { $bValid =false; break;}

                            $select = $sql->select();

                            $select->from('Proj_UOM')
                                ->columns(array('UnitId', 'UnitName'))
                                ->where(array("UnitName='$xlData[$col_2]'"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $row = $results->current();

                            $data[] = array('Valid'=>$bValid,'Spec'=>$xlData[$col_1],
                                'UnitId' => $row['UnitId'], 'Unit' => $row['UnitName'], 'Qty' => $xlData[$col_3],
                                'Rate' => $xlData[$col_4]);
                        }
                        $icount = $icount + 1;
                    }

                    if ($bValid==false){$data[] = array('Valid'=>$bValid);}

                    // delete csv file
                    fclose($file);
                    unlink($file_csv);

                    $response->setContent(json_encode($data));
                    return $response;
                }
            }
        }

        function _convertXLStoCSV($infile, $outfile) {
            $fileType = PHPExcel_IOFactory::identify($infile);
            $objReader = PHPExcel_IOFactory::createReader($fileType);

            $objReader->setReadDataOnly(true);
            $objPHPExcel = $objReader->load($infile);

            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
            $objWriter->save($outfile);
        }

        function _validateUploadFile($file) {
            $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
            $mime_types = array('application/octet-stream','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain','application/csv', 'text/comma-separated-values', 'application/excel');
            $exts = array('csv', 'xls', 'xlsx');

            if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
                return false;

            return true;
        }

        // Rebuild Func Start
        function LoadprevbillAbstactdet($BillId, $WORegisterId, $submitType, $dbAdapter) {
            $sql = new Sql($dbAdapter);

            /*Select BillId,BillTransId,OrderNo,BillType,Mobilization,Material from BillTrans
            Where CostCentreId = " + lCostCentreId + " and BillId=" + argBillId + " Order by BillId*/
            $select = $sql->select();
            $select->from('CB_BillMaster')
                ->columns( array( 'BillId','OrderNo','BillType') )
                ->where(array('DeleteFlag'=>'0', "BillId" => $BillId, "WORegisterId" => $WORegisterId));
            $select->order('BillId');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            foreach($bills as $bill) {
                /*Select BillAbsId,BillId,BillFormatId From CB_BillAbstract " +
                      "Where WORegisterId in ( " + argCostID + " ) and " +
                      "BillId=" + argBillId + " Order by BillId";*/
                $billType= $bill['BillType'];
                if($billType=="R" || $billType=="F" || $billType=="S" ){
                    $billType = array('R', 'S', 'F');
                } else {
                    $billType = array($bill['BillType']);
                }

                $select = $sql->select();
                $select->from('CB_BillAbstract')
                    ->columns( array( 'BillAbsId','BillId','BillFormatId') )
                    ->where(array("BillId" => $BillId));
                $select->order('BillId');
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                if($submitType=="S") //Submit
                {
                    foreach($billAbstracts as $billAbstract) {
                        /*Update BillAbstract Set CumulativeValue=0 " +
                                        "Where BillTransId = " + lTransId + "*/
                        $billAbsId= $billAbstract['BillAbsId'];
                        $billFormatId= $billAbstract['BillFormatId'];

                        $update = $sql->update();
                        $update->table('CB_BillAbstract')
                            ->set(array('CumAmount' => '0','PrevAmount' => '0'))
                            ->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*sSql = "Select BillTransId,PrevBillTransId,BillType from BillCumulativeTrans
                        Where billTransid in (select billTransid from Billtrans where BillId=" + lBillId + "
                        and CostcentreId=" + lCostCentreId + " and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")";*/
                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillCumulativeTrans')
                            ->columns( array( 'BillId','PrevBillId','BillType') )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach($billCums as $billCum) {
                            $prevBillId= $billCum['PrevBillId'];
                            $cumType= $billCum['BillType'];

                            $subQuery = $sql->select();
                            $subQuery->from("CB_BillMaster")
                                ->columns(array('BillId'))
                                ->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                            $select = $sql->select();
                            $select->from('CB_BillAbstract')
                                ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
                                ->where->expression('BillId IN ?', array($subQuery));
                            $select->where(array( "BillFormatId" => $billFormatId));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $billAmount=0;
                            $billCerAmount=0;
                            foreach($billCumAMounts as $billCumAMount) {
                                $billAmount=$billCumAMount['Amount'];
                                $billCerAmount=$billCumAMount['CerAmount'];
                            }

                            $update = $sql->update();
                            if($cumType=="C"){
                                /*sSql = "Update BillAbstract Set CumulativeValue=CumulativeValue+(Select isnull(Sum(CurrentValue),0) from BillAbstract " +
                                     "Where TypeID = " + lTypeId + " and CostCentreId = " + lCostCentreId + " and BillID in " +
                                     "(Select BillID from BillTrans where BillTransId = " + iPrevBillTraId + " and CostCentreId= " + lCostCentreId + " and BillType='" + sBillType + "' and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")) " +
                                     "Where BillTransId = " + lTransId + " ";*/
                                $update->table('CB_BillAbstract')
                                    ->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)  ));
                            } else {
                                $update->table('CB_BillAbstract')
                                    ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount) ));
                            }
                            $update->where(array('BillAbsId' => $billAbsId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        /*"Update BillAbstract Set CumulativeValue=CumulativeValue+
                        (Select isnull(Sum(CurrentValue),0) from BillAbstract " +
                       "Where TypeID = " + lTypeId + " and CostCentreId = " + lCostCentreId + " and BillID in " +
                       "(Select BillID from BillTrans where BillTransId = " + iCurBillTraId + " and CostCentreId= " + lCostCentreId + " and
                       BillType='" + sBillType + "' and Mobilization=" + lMobadv + " and Material=" + lMatadv + ")) " +
                       "Where BillTransId = " + lTransId + " ";*/

                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillAbstract')
                            ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)")) )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $select->where(array("BillFormatId" => $billFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billAmount=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billAmount=$billCumAMount['Amount'];
                        }

                        $update = $sql->update();
                        $update->table('CB_BillAbstract')
                            ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount) ));
                        $update->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else { //Certify
                    foreach($billAbstracts as $billAbstract) {
                        $billAbsId= $billAbstract['BillAbsId'];
                        $billFormatId= $billAbstract['BillFormatId'];

                        $update = $sql->update();
                        $update->table('CB_BillAbstract')
                            ->set(array('CerCumAmount' => '0','CerPrevAmount' => '0'))
                            ->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //
                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
                        $subQuery->where(array("BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillAbstract')
                            ->columns( array( 'Amount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $select->where(array("BillFormatId" => $billFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billAmount=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billAmount=$billCumAMount['Amount'];
                        }

                        $update = $sql->update();
                        $update->table('CB_BillAbstract')
                            ->set(array('CerCumAmount' => $billAmount ));
                        $update->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('CB_BillAbstract')
                            ->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount") ));
                        $update->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        }

        function Loadprevbilldet($BillId, $WORegisterId, $submitType, $dbAdapter) {
            //$BillId =2;
            //$WORegisterId = 1;
            //$submitType="S";
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from('CB_BillMaster')
                ->columns( array( 'BillId','OrderNo','BillType') )
                ->where(array('DeleteFlag'=>'0', "BillId" => $BillId, "WORegisterId" => $WORegisterId));
            $select->order('BillId');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            foreach($bills as $bill) {

                $billType= $bill['BillType'];
                if($billType=="R" || $billType=="F" || $billType=="S" ){
                    $billType = array('R', 'S', 'F');
                } else {
                    $billType = array($bill['BillType']);
                }

                $subQuery = $sql->select();
                $subQuery->from("CB_BillMaster")
                    ->columns(array('BillId'))
                    ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                $subQuery1 = $sql->select();
                $subQuery1->from("CB_BillAbstract")
                    ->columns(array('BillAbsId'))
                    ->where->expression('BillId IN ?', array($subQuery));

                $select = $sql->select();
                $select->from('CB_BillBOQ')
                    ->columns( array( 'BillBOQId','BillAbsId','BillFormatId','WOBOQId','NonBOQId','Rate','CerRate','PartRate','NonBOQId') )
                    ->where->expression('BillAbsId IN ?', array($subQuery1));
                $select->order('BillAbsId');
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                if($submitType=="S") //Submit
                {
                    foreach($billIOWs as $billIOW) {
                        $billBOQId = $billIOW['BillBOQId'];
                        $billAbsId = $billIOW['BillAbsId'];
                        $billFormatId = $billIOW['BillFormatId'];
                        $wOBOQId = $billIOW['WOBOQId'];
                        $nonBOQId = $billIOW['NonBOQId'];
                        $rate = $billIOW['Rate'];
                        $cerrate = $billIOW['CerRate'];

                        $update = $sql->update();
                        $update->table('CB_BillBOQ')
                            ->set(array('CumAmount' => '0','CumQty' => '0', 'PrevAmount' => '0','PrevQty' => '0'))
                            ->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
                        $select = $sql->select();
                        $select->from('CB_BillCumulativeTrans')
                            ->columns( array( 'BillId','PrevBillId','BillType') )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        foreach($billCums as $billCum) {
                            $prevBillId= $billCum['PrevBillId'];
                            $cumType= $billCum['BillType'];

                            $subQuery = $sql->select();
                            $subQuery->from("CB_BillMaster")
                                ->columns(array('BillId'))
                                ->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                            $select = $sql->select();
                            $select->from('CB_BillAbstract')
                                ->columns( array( 'BillAbsId' ) )
                                ->where->expression('BillId IN ?', array($subQuery));
                            $select->where(array( "BillFormatId" => $billFormatId));


                            $select1 = $sql->select();
                            $select1->from('CB_BillBOQ')
                                ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'Qty' => new Expression("isnull(Sum(CurQty),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
                                ->where->expression('BillAbsId IN ?', array($select));
                            $select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $billAmount=0;
                            $billCerAmount=0;
                            $billQty=0;
                            $billCerQty=0;
                            foreach($billCumAMounts as $billCumAMount) {
                                $billAmount=$billCumAMount['Amount'];
                                $billCerAmount=$billCumAMount['CerAmount'];
                                $billQty=$billCumAMount['Qty'];
                                $billCerQty=$billCumAMount['CerQty'];
                            }

                            $update = $sql->update();
                            if($cumType=="C"){
                                $update->table('CB_BillBOQ')
                                    ->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)
                                    ,'CumQty' => new Expression('CumQty +'.$billCerQty), 'PrevQty' => new Expression('PrevQty +'.$billCerQty) ));
                            } else {
                                $update->table('CB_BillBOQ')
                                    ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount)
                                    ,'CumQty' => new Expression('CumQty +'.$billQty), 'PrevQty' => new Expression('PrevQty +'.$billQty) ));
                            }
                            $update->where(array('BillBOQId' => $billBOQId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        /*$subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
                        */
                        $select = $sql->select();
                        $select->from('CB_BillBOQ')
                            ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"), 'Qty' => new Expression("isnull(Sum(CurQty),0)")) );
                        $select->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillAbsId" => $billAbsId , "BillFormatId" => $billFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billAmount=0;
                        $billQty=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billAmount=$billCumAMount['Amount'];
                            $billQty=$billCumAMount['Qty'];
                        }

                        $update = $sql->update();
                        $update->table('CB_BillBOQ')
                            ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'CumQty' => new Expression('CumQty +'.$billQty) ));
                        $update->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else { //Certify
                    foreach($billIOWs as $billIOW) {

                        $billBOQId = $billIOW['BillBOQId'];
                        $billAbsId = $billIOW['BillAbsId'];
                        $billFormatId = $billIOW['BillFormatId'];
                        $wOBOQId = $billIOW['WOBOQId'];
                        $nonBOQId = $billIOW['NonBOQId'];
                        $rate = $billIOW['Rate'];
                        $cerrate = $billIOW['CerRate'];

                        $update = $sql->update();
                        $update->table('CB_BillBOQ')
                            ->set(array('CerCumAmount' => '0','CerCumQty' => '0', 'CerPrevAmount' => '0','CerPrevQty' => '0'))
                            ->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //
                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
                        $subQuery->where(array("BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillAbstract')
                            ->columns( array( 'BillAbsId' ) )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $select->where(array( "BillFormatId" => $billFormatId));


                        $select1 = $sql->select();
                        $select1->from('CB_BillBOQ')
                            ->columns( array( 'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
                            ->where->expression('BillAbsId IN ?', array($select));
                        $select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
                        $statement = $sql->getSqlStringForSqlObject( $select1 );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billCerAmount=0;
                        $billCerQty=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billCerAmount=$billCumAMount['CerAmount'];
                            $billCerQty=$billCumAMount['CerQty'];
                        }

                        $update = $sql->update();
                        $update->table('CB_BillBOQ')
                            ->set(array('CerCumAmount' => $billCerAmount, 'CerCumQty' => $billCerQty ));
                        $update->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('CB_BillBOQ')
                            ->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount"), 'CerPrevQty' => new Expression("CerCumQty-CerCurQty") ));
                        $update->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
        }

        function LoadSubmit_Certify_Billdet($curBillId, $WORegisterId, $dbAdapter) {
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from('CB_BillMaster')
                ->columns( array( 'BillId','OrderNo','BillType') );
            if($curBillId <> 0){
                $select->where(array('DeleteFlag'=>'0','BillId'=> $curBillId , "WORegisterId" => $WORegisterId));
            } else {
                $select->where(array('DeleteFlag'=>'0', "WORegisterId" => $WORegisterId));
            }
            $select->order('BillId');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            foreach($bills as $bill) {
                $BillId = $bill['BillId'];
                $billType = $bill['BillType'];
                if($billType=="R" || $billType=="F" || $billType=="S" ){
                    $billType = array('R', 'S', 'F');
                } else {
                    $billType = array($bill['BillType']);
                }
                //BillAbstract
                $select = $sql->select();
                $select->from('CB_BillAbstract')
                    ->columns( array( 'BillAbsId','BillId','BillFormatId') )
                    ->where(array("BillId" => $BillId));
                $select->order('BillId');
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                foreach($billAbstracts as $billAbstract) {
                    $billAbsId= $billAbstract['BillAbsId'];
                    $billFormatId= $billAbstract['BillFormatId'];

                    $update = $sql->update();
                    $update->table('CB_BillAbstract')
                        ->set(array('CumAmount' => '0','PrevAmount' => '0', 'CerCumAmount' => '0','CerPrevAmount' => '0'))
                        ->where(array('BillAbsId' => $billAbsId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //Submit
                    $subQuery = $sql->select();
                    $subQuery->from("CB_BillMaster")
                        ->columns(array('BillId'))
                        ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                    $select = $sql->select();
                    $select->from('CB_BillCumulativeTrans')
                        ->columns( array( 'BillId','PrevBillId','BillType') )
                        ->where->expression('BillId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    foreach($billCums as $billCum) {
                        $prevBillId= $billCum['PrevBillId'];
                        $cumType= $billCum['BillType'];

                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillAbstract')
                            ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $select->where(array( "BillFormatId" => $billFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billAmount=0;
                        $billCerAmount=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billAmount=$billCumAMount['Amount'];
                            $billCerAmount=$billCumAMount['CerAmount'];
                        }

                        $update = $sql->update();
                        if($cumType=="C"){
                            $update->table('CB_BillAbstract')
                                ->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)  ));
                        } else {
                            $update->table('CB_BillAbstract')
                                ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount) ));
                        }
                        $update->where(array('BillAbsId' => $billAbsId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $subQuery = $sql->select();
                    $subQuery->from("CB_BillMaster")
                        ->columns(array('BillId'))
                        ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                    $select = $sql->select();
                    $select->from('CB_BillAbstract')
                        ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)")) )
                        ->where->expression('BillId IN ?', array($subQuery));
                    $select->where(array("BillFormatId" => $billFormatId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $billAmount=0;
                    foreach($billCumAMounts as $billCumAMount) {
                        $billAmount=$billCumAMount['Amount'];
                    }

                    $update = $sql->update();
                    $update->table('CB_BillAbstract')
                        ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount) ));
                    $update->where(array('BillAbsId' => $billAbsId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //Certify
                    $subQuery = $sql->select();
                    $subQuery->from("CB_BillMaster")
                        ->columns(array('BillId'))
                        ->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
                    $subQuery->where(array("BillType" => $billType));

                    $select = $sql->select();
                    $select->from('CB_BillAbstract')
                        ->columns( array( 'Amount' => new Expression("isnull(Sum(CerCurAmount),0)")) )
                        ->where->expression('BillId IN ?', array($subQuery));
                    $select->where(array("BillFormatId" => $billFormatId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $billAmount=0;
                    foreach($billCumAMounts as $billCumAMount) {
                        $billAmount=$billCumAMount['Amount'];
                    }

                    $update = $sql->update();
                    $update->table('CB_BillAbstract')
                        ->set(array('CerCumAmount' => $billAmount ));
                    $update->where(array('BillAbsId' => $billAbsId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('CB_BillAbstract')
                        ->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount") ));
                    $update->where(array('BillAbsId' => $billAbsId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //BillIOW
                $subQuery = $sql->select();
                $subQuery->from("CB_BillMaster")
                    ->columns(array('BillId'))
                    ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                $subQuery1 = $sql->select();
                $subQuery1->from("CB_BillAbstract")
                    ->columns(array('BillAbsId'))
                    ->where->expression('BillId IN ?', array($subQuery));

                $select = $sql->select();
                $select->from('CB_BillBOQ')
                    ->columns( array( 'BillBOQId','BillAbsId','BillFormatId','WOBOQId','NonBOQId','Rate','CerRate','PartRate') )
                    ->where->expression('BillAbsId IN ?', array($subQuery1));
                $select->order('BillAbsId');
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                foreach($billIOWs as $billIOW) {
                    $billBOQId = $billIOW['BillBOQId'];
                    $billAbsId = $billIOW['BillAbsId'];
                    $billFormatId = $billIOW['BillFormatId'];
                    $wOBOQId = $billIOW['WOBOQId'];
                    $nonBOQId = $billIOW['NonBOQId'];
                    $rate = $billIOW['Rate'];
                    $cerrate = $billIOW['CerRate'];

                    $update = $sql->update();
                    $update->table('CB_BillBOQ')
                        ->set(array('CumAmount' => '0','CumQty' => '0', 'PrevAmount' => '0','PrevQty' => '0'
                        ,'CerCumAmount' => '0','CerCumQty' => '0', 'CerPrevAmount' => '0','CerPrevQty' => '0'))
                        ->where(array('BillBOQId' => $billBOQId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    //Submit
                    $subQuery = $sql->select();
                    $subQuery->from("CB_BillMaster")
                        ->columns(array('BillId'))
                        ->where(array( "BillId" => $BillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));
                    $select = $sql->select();
                    $select->from('CB_BillCumulativeTrans')
                        ->columns( array( 'BillId','PrevBillId','BillType') )
                        ->where->expression('BillId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    foreach($billCums as $billCum) {
                        $prevBillId= $billCum['PrevBillId'];
                        $cumType= $billCum['BillType'];

                        $subQuery = $sql->select();
                        $subQuery->from("CB_BillMaster")
                            ->columns(array('BillId'))
                            ->where(array( "BillId" => $prevBillId, "WORegisterId" => $WORegisterId, "BillType" => $billType));

                        $select = $sql->select();
                        $select->from('CB_BillAbstract')
                            ->columns( array( 'BillAbsId' ) )
                            ->where->expression('BillId IN ?', array($subQuery));
                        $select->where(array( "BillFormatId" => $billFormatId));


                        $select1 = $sql->select();
                        $select1->from('CB_BillBOQ')
                            ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"),'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'Qty' => new Expression("isnull(Sum(CurQty),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
                            ->where->expression('BillAbsId IN ?', array($select));
                        $select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
                        $statement = $sql->getSqlStringForSqlObject( $select1 );
                        $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        $billAmount=0;
                        $billCerAmount=0;
                        $billQty=0;
                        $billCerQty=0;
                        foreach($billCumAMounts as $billCumAMount) {
                            $billAmount=$billCumAMount['Amount'];
                            $billCerAmount=$billCumAMount['CerAmount'];
                            $billQty=$billCumAMount['Qty'];
                            $billCerQty=$billCumAMount['CerQty'];
                        }

                        $update = $sql->update();
                        if($cumType=="C"){
                            $update->table('CB_BillBOQ')
                                ->set(array('CumAmount' => new Expression('CumAmount +'.$billCerAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billCerAmount)
                                ,'CumQty' => new Expression('CumQty +'.$billCerQty), 'PrevQty' => new Expression('PrevQty +'.$billCerQty) ));
                        } else {
                            $update->table('CB_BillBOQ')
                                ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'PrevAmount' => new Expression('PrevAmount +'.$billAmount)
                                ,'CumQty' => new Expression('CumQty +'.$billQty), 'PrevQty' => new Expression('PrevQty +'.$billQty) ));
                        }
                        $update->where(array('BillBOQId' => $billBOQId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from('CB_BillBOQ')
                        ->columns( array( 'Amount' => new Expression("isnull(Sum(CurAmount),0)"), 'Qty' => new Expression("isnull(Sum(CurQty),0)")) );
                    $select->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillAbsId" => $billAbsId , "BillFormatId" => $billFormatId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $billAmount=0;
                    $billQty=0;
                    foreach($billCumAMounts as $billCumAMount) {
                        $billAmount=$billCumAMount['Amount'];
                        $billQty=$billCumAMount['Qty'];
                    }

                    $update = $sql->update();
                    $update->table('CB_BillBOQ')
                        ->set(array('CumAmount' => new Expression('CumAmount +'.$billAmount), 'CumQty' => new Expression('CumQty +'.$billQty) ));
                    $update->where(array('BillBOQId' => $billBOQId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //Certify
                    $subQuery = $sql->select();
                    $subQuery->from("CB_BillMaster")
                        ->columns(array('BillId'))
                        ->where("BillId<='$BillId' AND WORegisterId='$WORegisterId'");
                    $subQuery->where(array("BillType" => $billType));

                    $select = $sql->select();
                    $select->from('CB_BillAbstract')
                        ->columns( array( 'BillAbsId' ) )
                        ->where->expression('BillId IN ?', array($subQuery));
                    $select->where(array( "BillFormatId" => $billFormatId));


                    $select1 = $sql->select();
                    $select1->from('CB_BillBOQ')
                        ->columns( array( 'CerAmount' => new Expression("isnull(Sum(CerCurAmount),0)"),'CerQty' => new Expression("isnull(Sum(CerCurQty),0)")) )
                        ->where->expression('BillAbsId IN ?', array($select));
                    $select1->where(array("WOBOQId" => $wOBOQId, "NonBOQId" => $nonBOQId, "BillFormatId" => $billFormatId, "Rate" => $rate ));
                    $statement = $sql->getSqlStringForSqlObject( $select1 );
                    $billCumAMounts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $billCerAmount=0;
                    $billCerQty=0;
                    foreach($billCumAMounts as $billCumAMount) {
                        $billCerAmount=$billCumAMount['CerAmount'];
                        $billCerQty=$billCumAMount['CerQty'];
                    }

                    $update = $sql->update();
                    $update->table('CB_BillBOQ')
                        ->set(array('CerCumAmount' => $billCerAmount, 'CerCumQty' => $billCerQty ));
                    $update->where(array('BillBOQId' => $billBOQId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('CB_BillBOQ')
                        ->set(array('CerPrevAmount' => new Expression("CerCumAmount-CerCurAmount"), 'CerPrevQty' => new Expression("CerCumQty-CerCurQty") ));
                    $update->where(array('BillBOQId' => $billBOQId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

            }


        }

        public function loadprevbilldetAction() {
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing Register");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $BillId =1012;
            $WORegisterId = 1;
            $submitType="C";
            $sql = new Sql($dbAdapter);

            $subQuery = $sql->select();
            $subQuery->from("CB_BillMaster")
                ->columns(array('BillId' => new Expression("isnull(max(BillId),0)") ))
                ->where(array("WORegisterId" => $WORegisterId));

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillCumulativeTrans' ))
                ->join(array('b' => 'CB_BillMaster'), 'a.PrevBillId=B.BillId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'CB_BillAbstract'), 'b.BillId=c.BillId', array("BillFormatId"), $select::JOIN_LEFT)
                ->columns(array( 'Amount' => new Expression("isnull(Case When A.BillType='C' then c.CerCurAmount else c.CurAmount End ,0)") ))
                ->where(array('b.DeleteFlag' => '0'));
            $select->where->expression('a.BillId IN ?', array($subQuery));

            $select2 = $sql->select();
            $select2->from(array("b"=>"CB_BillMaster"))
                ->columns(array( 'Amount' => new Expression("isnull(Case When b.IsCertifiedBill=1 then c.CerCurAmount else c.CurAmount End ,0)") ))
                ->join(array('c' => 'CB_BillAbstract'), 'b.BillId=c.BillId', array("BillFormatId"), $select2::JOIN_INNER);
            $select2->where(array('b.DeleteFlag' => '0'));
            $select2->where->expression('b.BillId IN ?', array($subQuery));
            $select2->combine($select,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select2))
                ->columns(array("Amount"=>new Expression("Sum(g.Amount)") ), array('*'))
                ->join(array('a' => 'CB_BillFormatTrans'), 'g.BillFormatId=a.BillFormatId', array('*'), $select3::JOIN_INNER)
                ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array("FormatTypeId"), $select3::JOIN_LEFT);
            $select3->where(array('a.WorkOrderId' => $WORegisterId));
            $select3->group(new Expression('a.BillFormatId,a.RowName,a.Slno,a.TypeName,a.Description,a.Sign
		,a.Header,a.WorkOrderId,a.Formula,a.Bold,a.Italic,a.Underline,a.SortId,b.FormatTypeId'));
            $select3->order('a.BillFormatId');

            $billType=array('R', 'S', 'F');
            //Mobilization Adv Recovery (5)
            $select = $sql->select();
            $select->from( array('a' => 'CB_ReceiptRegister' ))
                ->columns(array( 'ReceiptId', 'Amount', 'PreAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
                ->where(array('a.DeleteFlag' => '0' ,'a.WORegisterId' => '1' ,'a.ReceiptAgainst' => 'M'));

            $select2 = $sql->select();
            $select2->from(array("b"=>"CB_BillAdvanceRecovery"))
                ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
                ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
                ->where(array('b.BillId' => '1', 'b.BillFormatId' => '5' , 'c.WORegisterId' => '1', 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
            $select2->combine($select,'Union ALL');

            $select21 = $sql->select();
            $select21->from(array("b"=>"CB_BillAdvanceRecovery"))
                ->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
                ->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
                ->where(array('b.BillId' => '0', 'b.BillFormatId' => '5' , 'c.WORegisterId' => '1', 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
            $select21->where("b.BillId<>1");
            $select21->combine($select2,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select21))
                ->columns(array("ReceiptId","Amount"=>new Expression("Sum(g.Amount)"), "PreAmount"=>new Expression("Sum(g.PreAmount)"), "Balance"=>new Expression("Sum(g.Amount-g.PreAmount)"), "Balance"=>new Expression("Sum(CurAmount)") ),
                    array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"))
                ->join(array('a' => 'CB_ReceiptRegister'), 'g.ReceiptId=a.ReceiptId', array( "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"), "ReceiptAgainst"), $select3::JOIN_INNER);
            $select3->group(new Expression('g.ReceiptId,a.ReceiptNo,a.ReceiptDate,a.ReceiptAgainst'));
            $select3->order('g.ReceiptId');
            //$statement = $sql->getSqlStringForSqlObject($select3);
            //$mobilizationAdv = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Adv Recovery (6)




            $select = $sql->select();
            $select->from( array('a' => 'Crm_DescriptionMaster' ))
                ->columns(array( 'Id' => new Expression("a.DescriptionId"), 'Name'  => new Expression("a.DescriptionName"), 'Type' => new Expression("'D'") ));

            $select21 = $sql->select();
            $select21->from(array("a"=>"KF_StageMaster"))
                ->columns(array( 'Id' => new Expression("a.StageId"), 'Name'  => new Expression("a.StageName"), 'Type' => new Expression("'S'") ));
            $select21->combine($select,'Union ALL');

            $select22 = $sql->select();
            $select22->from(array("a"=>"Crm_OtherCostMaster"))
                ->columns(array( 'Id' => new Expression("a.OtherCostId"), 'Name'  => new Expression("a.OtherCostName"), 'Type' => new Expression("'O'") ));
            $select22->combine($select21,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select22))
                ->columns(array("Id","Name","Type" ));
            $select3->order('g.Name');

            /*$subQuery = $sql->select();
            $subQuery->from("CB_BillAbstract")
                ->columns(array('BillAbsId'))
                ->where(array("BillId" => $BillId));

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillMaterialAdvance' ))
                        ->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' => new Expression("'C'") ));
            $select->where(array("a.BillFormatId" => 3, "a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillMaterialAdvance' );
            $insert->columns(array('BillAbsId', 'BillFormatId','MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
            $insert->Values( $select );
            $curvBillAbsId=1012;
            $prevBillAbsId=1006;
            $BillFormatId=18;*/

            $statement = $sql->getSqlStringForSqlObject( $select3 );
            $billCums = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            //$this->LoadprevbillAbstactdet($BillId ,$WORegisterId, $submitType, $dbAdapter);
            //$this->Loadprevbilldet($BillId ,$WORegisterId, $submitType, $dbAdapter);
            //$this->LoadSubmit_Certify_Billdet(2, $WORegisterId, $dbAdapter);
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }

        function UpdateBillCumulativedet($BillId, $WORegisterId, $submitType, $dbAdapter) {
            $sql = new Sql($dbAdapter);
            //Start BillCumulativeTrans
            $delete = $sql->delete();
            $delete->from('CB_BillCumulativeTrans')
                ->where("BillId =$BillId");
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if($submitType=="R" || $submitType=="F" || $submitType=="S" || $submitType==""){
                $billTypechk = array('R', 'S', 'F');
            } else {
                $billTypechk = array($submitType);
            }

            $select = $sql->select();
            $select->from( array( 'a' => 'CB_BillMaster' ) )
                ->columns(array('BillId','IsSubmittedBill','IsCertifiedBill'))
                ->where( "a.DeleteFlag=0 AND a.WORegisterId=$WORegisterId AND a.BillID < $BillId ");
            $select->where(array("BillType" => $billTypechk));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $billFlows = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            foreach($billFlows as $billFlow) {
                $prevbillType="S";
                $prevbillId= $billFlow['BillId'];
                //$prevSub_billflag= $billFlow['IsSubmittedBill'];
                $prevCer_billflag= $billFlow['IsCertifiedBill'];
                if($prevCer_billflag==1){
                    $prevbillType="C";
                }

                $insert = $sql->insert();
                $insert->into( 'CB_BillCumulativeTrans' );
                $insert->Values( array( 'BillId' => $BillId, 'PrevBillId' => $prevbillId, 'BillType' => $prevbillType ) );
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
            }
            //End BillCumulativeTrans
        }

        function GetBilldet($BillId, $WORegisterId, $CostCentreId, $submitType, $dbAdapter) {
            $sql = new Sql($dbAdapter);

            if($submitType=="R" || $submitType=="F" || $submitType=="S" ){
                $billType = array('R', 'S', 'F');
            } else {
                $billType = array($submitType);
            }

            //BillAbstract - Prev Bill
            $subQuery = $sql->select();
            $subQuery->from("CB_BillMaster")
                ->columns(array('BillId' => new Expression("isnull(max(BillId),0)") ))
                ->where(array("DeleteFlag" => '0', "WORegisterId" => $WORegisterId, "BillType" => $billType));
            $subQuery->where("BillId<>$BillId");
            $statement = $sql->getSqlStringForSqlObject($subQuery);
            $prevbillinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $PrevBillId=$prevbillinfo['BillId'];

            if($PrevBillId!=0){

                $select = $sql->select();
                $select->from( array('a' => 'CB_BillAbstract' ))
                    ->columns(array( 'BillAbsId', 'BillFormatId' ,'BillFormatTransId', 'Formula' ));
                $select->where(array("a.BillId" => $PrevBillId));
                $statement = $sql->getSqlStringForSqlObject( $select );
                $billsAbstracts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                foreach($billsAbstracts as $billsAbstract) {
                    $prevBillAbsId= $billsAbstract['BillAbsId'];
                    $BillFormatId= $billsAbstract['BillFormatId'];
                    $BillFormatTraId= $billsAbstract['BillFormatTransId'];
//				$Formula= $billsAbstract['Formula'];

                    // To get formula from workorder
                    $select = $sql->select();
                    $select->from( array('a' => 'CB_BillFormatTrans' ))
                        ->columns(array( 'Formula' ));
                    $select->where(array("a.CostCentreId" => $CostCentreId, "a.BillFormatId" => $BillFormatId, "a.BillFormatTransId" => $BillFormatTraId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billFormatTrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $insert = $sql->insert();
                    $insert->into( 'CB_BillAbstract' );
                    $insert->Values( array( 'BillId' => $BillId, 'BillFormatId' => $BillFormatId, 'BillFormatTransId' => $BillFormatTraId, 'Formula' => $billFormatTrans['Formula']) );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    $curvBillAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    if($BillFormatId==1 || $BillFormatId==2){ //BillIOW
                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillBOQ' ))
                            ->columns(array( 'BillFormatId', 'WOBOQId', 'NonBOQId', 'SlNo', 'Spec', 'UnitId', 'Rate', 'PartRate', 'PartPercent'
                            , 'FullRate', 'CerRate', 'CerPartPercent', 'CerFullRate' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsIOWs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsIOWs as $billsIOW) {
                            $IOWBillFormatId= $billsIOW['BillFormatId'];
                            $WOBOQId= $billsIOW['WOBOQId'];
                            $NonBOQId= $billsIOW['NonBOQId'];
                            $SlNo= $billsIOW['SlNo'];
                            $Spec= $billsIOW['Spec'];
                            $UnitId= $billsIOW['UnitId'];
                            $Rate= $billsIOW['Rate'];
                            $PartRate= $billsIOW['PartRate'];
                            $PartPercent= $billsIOW['PartPercent'];
                            $FullRate= $billsIOW['FullRate'];
                            $CerRate= $billsIOW['CerRate'];
                            $CerPartPercent= $billsIOW['CerPartPercent'];
                            $CerFullRate= $billsIOW['CerFullRate'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillBOQ' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $IOWBillFormatId, 'WOBOQId' => $WOBOQId, 'NonBOQId' => $NonBOQId
                            , 'SlNo' => $SlNo, 'Spec' => $Spec, 'UnitId' => $UnitId, 'Rate' => $Rate, 'PartRate' => $PartRate, 'PartPercent' => $PartPercent, 'FullRate' => $FullRate
                            , 'CerRate' => $CerRate, 'CerPartPercent' => $CerPartPercent, 'CerFullRate' => $CerFullRate) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                    } else if($BillFormatId==3){ //MaterialAdvance
                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillMaterialAdvance' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"),'MTransId','BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId, "a.TransType" => 'S'));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsMatAdvs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsMatAdvs as $billsMatAdv) {
                            $MatadvBillFormatId= $billsMatAdv['BillFormatId'];
                            $MTransPrevId= $billsMatAdv['MTransId'];
                            $MaterialId= $billsMatAdv['MaterialId'];
                            $Qty= $billsMatAdv['Qty'];
                            $Rate= $billsMatAdv['Rate'];
                            $Amount= $billsMatAdv['Amount'];
                            $AdvPercent= $billsMatAdv['AdvPercent'];
                            $AdvAmount= $billsMatAdv['AdvAmount'];
                            $PurchaseQty= $billsMatAdv['PurchaseQty'];
                            $ConsumeQty= $billsMatAdv['ConsumeQty'];
                            $TransType= $billsMatAdv['TransType'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillMaterialAdvance' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $MatadvBillFormatId, 'MaterialId' => $MaterialId, 'AdvPercent' => $AdvPercent,
                                //'Qty' => $Qty , 'Rate' => $Rate, 'Amount' => $Amount, 'AdvAmount' => $AdvAmount, 'PurchaseQty' => $PurchaseQty, 'ConsumeQty' => $ConsumeQty,
                                'TransType' => $TransType) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            $mTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $select = $sql->select();
                            $select->from( array('a' => 'CB_BillMaterialBillTrans' ))
                                ->columns(array( 'BillDate','BillNo','VendorId','Rate'));
                            $select->where(array("a.MTransId" => $MTransPrevId));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $billsMatAdvstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            foreach($billsMatAdvstrans as $billsMatAdvstran) {
                                $insert = $sql->insert();
                                $insert->into( 'CB_BillMaterialBillTrans' );
                                $insert->Values( array( 'MTransId' => $mTransId, 'BillDate' => $billsMatAdvstran['BillDate'], 'BillNo' => $billsMatAdvstran['BillNo']
                                , 'VendorId' => $billsMatAdvstran['VendorId'], 'Rate' => $billsMatAdvstran['Rate'] ) );
                                $statement = $sql->getSqlStringForSqlObject( $insert );
                                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                            }

                        }

                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillMaterialAdvance' ))
                                    ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillMaterialAdvance' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );*/

                    } else if($BillFormatId==18){ //Price Escalation
                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillPriceEscalation' ))
                            ->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType', 'RateCondition', 'ORate'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsPriceEscs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsPriceEscs as $billsPriceEsc) {
                            $PrEscBillFormatId= $billsPriceEsc['BillFormatId'];
                            $MaterialId= $billsPriceEsc['MaterialId'];
                            $Qty= $billsPriceEsc['Qty'];
                            $BaseRate= $billsPriceEsc['BaseRate'];
                            $EscalationPer= $billsPriceEsc['EscalationPer'];
                            $ActualRate= $billsPriceEsc['ActualRate'];
                            $Amount= $billsPriceEsc['Amount'];
                            $TransType= $billsPriceEsc['TransType'];
                            $RateCondition= $billsPriceEsc['RateCondition'];
                            $ORate= $billsPriceEsc['ORate'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillPriceEscalation' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $PrEscBillFormatId, 'MaterialId' => $MaterialId, 'Qty' => $Qty
                            , 'BaseRate' => $BaseRate, 'EscalationPer' => $EscalationPer, 'ActualRate' => $ActualRate, 'Amount' => $Amount, 'TransType' => $TransType,
                            'RateCondition' => $RateCondition, 'ORate' => $ORate) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }*/

                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillPriceEscalation' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'BaseRate', 'EscalationPer', 'ActualRate', 'TransType', 'RateCondition', 'ORate' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillPriceEscalation' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'BaseRate', 'EscalationPer', 'ActualRate', 'TransType', 'RateCondition', 'ORate'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    } else if($BillFormatId==5 || $BillFormatId==6){ //MobAdvance Recovery or Advance Recovery
                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillAdvanceRecovery' ))
                            ->columns(array( 'BillId', 'ReceiptId', 'BillFormatId', 'Amount', 'CerAmount'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsMobRecs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsMobRecs as $billsMobRec) {
                            $MobRecBillId= $billsMobRec['BillId'];
                            $MobRecReceiptId= $billsMobRec['ReceiptId'];
                            $MobRecBillFormatId= $billsMobRec['BillFormatId'];
                            $Amount= $billsMobRec['Amount'];
                            $CerAmount= $billsMobRec['CerAmount'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillAdvanceRecovery' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillId' => $MobRecBillId, 'ReceiptId' => $MobRecReceiptId, 'BillFormatId' => $MobRecBillFormatId
                            , 'Amount' => $Amount, 'CerAmount' => $CerAmount) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }*/

                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillAdvanceRecovery' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'BillId' =>new Expression("'$BillId'"), 'ReceiptId' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillAdvanceRecovery' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'BillId', 'ReceiptId'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    } else if($BillFormatId==8){ //Material Recovery
                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillMaterialRecovery' ))
                            ->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsMatRecs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsMatRecs as $billsMatRec) {
                            $MatRecBillFormatId= $billsMatRec['BillFormatId'];
                            $MaterialId= $billsMatRec['MaterialId'];
                            $Qty= $billsMatRec['Qty'];
                            $Rate= $billsMatRec['Rate'];
                            $Amount= $billsMatRec['Amount'];
                            $TransType= $billsMatRec['TransType'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillMaterialRecovery' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $MatRecBillFormatId, 'MaterialId' => $MaterialId, 'Qty' => $Qty
                            , 'Rate' => $Rate, 'Amount' => $Amount, 'TransType' => $TransType) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }*/

                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillMaterialRecovery' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'TransType' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillMaterialRecovery' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'TransType'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    } else if($BillFormatId==7){ //Bill Deduction
                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillVendorBill' ))
                            ->columns(array( 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsDedus = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsDedus as $billsDedu) {
                            $DeductionBillFormatId= $billsDedu['BillFormatId'];
                            $BillDate= date('Y-m-d', strtotime($billsDedu['BillDate']));
                            $BillNo= $billsDedu['BillNo'];
                            $VendorId= $billsDedu['VendorId'];
                            $Amount= $billsDedu['Amount'];
                            $TransType= $billsDedu['TransType'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillVendorBill' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $DeductionBillFormatId, 'BillDate' => $BillDate, 'BillNo' => $BillNo
                            , 'VendorId' => $VendorId, 'Amount' => $Amount, 'TransType' => $TransType) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }
                        */
                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillVendorBill' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillVendorBill' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    } else if($BillFormatId==19){ //Free Supply Material
                        /*$select = $sql->select();
                        $select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
                            ->columns(array( 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $billsFreeSups = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        foreach($billsFreeSups as $billsFreeSup) {
                            $FreesupplyBillFormatId= $billsFreeSup['BillFormatId'];
                            $MaterialId= $billsFreeSup['MaterialId'];
                            $Qty= $billsFreeSup['Qty'];
                            $Rate= $billsFreeSup['Rate'];
                            $Amount= $billsFreeSup['Amount'];
                            $TransType= $billsFreeSup['TransType'];

                            $insert = $sql->insert();
                            $insert->into( 'CB_BillFreeSupplyMaterial' );
                            $insert->Values( array( 'BillAbsId' => $curvBillAbsId, 'BillFormatId' => $FreesupplyBillFormatId, 'MaterialId' => $MaterialId
                            , 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount, 'TransType' => $TransType) );
                            $statement = $sql->getSqlStringForSqlObject( $insert );
                            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                        }*/

                        $select = $sql->select();
                        $select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
                            ->columns(array( 'BillAbsId' =>new Expression("'$curvBillAbsId'"), 'BillFormatId', 'MaterialId', 'Rate', 'TransType' ));
                        $select->where(array("a.BillAbsId" => $prevBillAbsId, "a.BillFormatId" => $BillFormatId));

                        $insert = $sql->insert();
                        $insert->into( 'CB_BillFreeSupplyMaterial' );
                        $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Rate', 'TransType'));
                        $insert->Values( $select );
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    }
                }
            } else {
                //Insert New BillAbstract
                $select = $sql->select();
                $select->from( array('a' => 'CB_BillFormatTrans' ))
                    ->columns(array( 'BillId' =>new Expression("'$BillId'"), 'BillFormatId'=>new Expression("isnull(a.BillFormatId,0)"), 'BillFormatTransId', 'Formula'))
                    ->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT);
                $select->where(array("a.CostCentreId" => $CostCentreId));
                //$select->order('a.SortId');

                $insert = $sql->insert();
                $insert->into( 'CB_BillAbstract' );
                $insert->columns(array('BillId', 'BillFormatId', 'BillFormatTransId', 'Formula'));
                $insert->Values( $select );
                $statement = $sql->getSqlStringForSqlObject( $insert );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
            }
        }

        function GetSubmittedtoCertifyBilldet($BillId, $WORegisterId, $submitType, $dbAdapter) {
            $sql = new Sql($dbAdapter);

            //BillMaster
            $update = $sql->update();
            $update->table('CB_BillMaster')
                ->set(array('CertifyAmount' => new Expression('SubmitAmount') ));
            $update->where(array('BillId' => $BillId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //BillAbstract
            $update = $sql->update();
            $update->table('CB_BillAbstract')
                ->set(array('CerCurAmount' => new Expression('CurAmount') ));
            $update->where(array('BillId' => $BillId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //Bill IOW
            $subQuery = $sql->select();
            $subQuery->from("CB_BillAbstract")
                ->columns(array('BillAbsId'))
                ->where(array("BillId" => $BillId));

            $update = $sql->update();
            $update->table('CB_BillBOQ')
                ->set(array('CerCurQty' => new Expression('CurQty'), 'CerCurAmount' => new Expression('CurAmount')
                , 'CerRate' => new Expression('Rate'), 'CerPartPercent' => new Expression('PartPercent'), 'CerFullRate' => new Expression('FullRate') ));
            $update->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //MaterialAdvance Bulk Insert
            $delete = $sql->delete();
            $delete->from('CB_BillMaterialAdvance')
                ->where(array("BillFormatId" => 3, "TransType" =>'C'));
            $delete->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillMaterialAdvance' ))
                ->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType' => new Expression("'C'") ));
            $select->where(array("a.BillFormatId" => 3, "a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillMaterialAdvance' );
            $insert->columns(array('BillAbsId', 'BillFormatId','MaterialId', 'Qty', 'Rate', 'Amount', 'AdvPercent', 'AdvAmount', 'PurchaseQty', 'ConsumeQty', 'TransType'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //Price Esclation Bulk Insert
            $delete = $sql->delete();
            $delete->from('CB_BillPriceEscalation')
                ->where(array("TransType" =>'C'));
            $delete->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillPriceEscalation' ))
                ->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType' => new Expression("'C'"), 'RateCondition', 'ORate' ));
            $select->where(array("a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillPriceEscalation' );
            $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'BaseRate', 'EscalationPer', 'ActualRate', 'Amount', 'TransType', 'RateCondition', 'ORate'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //MobAdvance Recovery or Advance Recovery
            $update = $sql->update();
            $update->table('CB_BillAdvanceRecovery')
                ->set(array('CerAmount' => new Expression('Amount') ));
            $update->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject( $update );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //Material Recovery Bulk Insert
            $delete = $sql->delete();
            $delete->from('CB_BillMaterialRecovery')
                ->where(array("TransType" =>'C'));
            $delete->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillMaterialRecovery' ))
                ->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType' => new Expression("'C'") ));
            $select->where(array("a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillMaterialRecovery' );
            $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //Bill Deduction Bulk Insert
            $delete = $sql->delete();
            $delete->from('CB_BillVendorBill')
                ->where(array("TransType" =>'C'));
            $delete->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillVendorBill' ))
                ->columns(array( 'BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType' => new Expression("'C'") ));
            $select->where(array("a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillVendorBill' );
            $insert->columns(array('BillAbsId', 'BillFormatId', 'BillDate', 'BillNo', 'VendorId', 'Amount', 'TransType'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            //Free Supply Material Bulk Insert
            $delete = $sql->delete();
            $delete->from('CB_BillFreeSupplyMaterial')
                ->where(array("TransType" =>'C'));
            $delete->where->expression('BillAbsId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $select = $sql->select();
            $select->from( array('a' => 'CB_BillFreeSupplyMaterial' ))
                ->columns(array( 'BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType' => new Expression("'C'") ));
            $select->where(array("a.TransType" =>'S'));
            $select->where->expression('a.BillAbsId IN ?', array($subQuery));

            $insert = $sql->insert();
            $insert->into( 'CB_BillFreeSupplyMaterial' );
            $insert->columns(array('BillAbsId', 'BillFormatId', 'MaterialId', 'Qty', 'Rate', 'Amount', 'TransType'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            $this->LoadprevbillAbstactdet($BillId ,$WORegisterId, "C", $dbAdapter);
            $this->Loadprevbilldet($BillId ,$WORegisterId, "C", $dbAdapter);
        }
        // Rebuild Func End

        public function billreportlistAction(){
            if(!$this->auth->hasIdentity()) {
                if($this->getRequest()->isXmlHttpRequest())	{
                    echo "session-expired"; exit();
                } else {
                    $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
                }
            }

            $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Billing Register");
            $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql( $dbAdapter );
            $request = $this->getRequest();
            if ($request->isPost()) {

            } else {
                $billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
                $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

                if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
                    $this->redirect()->toRoute( 'clientbilling/default', array( 'controller' => 'index', 'action' => 'register' ) );

                $this->_view->billId = $billId;
                $this->_view->type = $type;
                // csrf Key
                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }


        public function sampleAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
        
        $request = $this->getRequest();
        if($type=="workorder"){
            $dir = 'public/reports/clientbilling/workorder/'. $userId;
            $filePath = $dir.'/wo_template.phtml';      
        } else if($type=="bill"){
            $dir = 'public/reports/clientbilling/bill/'. $userId;
            $filePath = $dir.'/rabill_template.phtml';
        } else if($type=="receipt"){
            $dir = 'public/reports/clientbilling/receipt/'. $userId;
            $filePath = $dir.'/receipt_template.phtml';
           // echo $filePath;exit;
        }
        
        if ($request->isPost()) {       
            $content=$request->getPost('htmlcontent');

            mkdir($dir);
            file_put_contents($filePath, $content);
            
            if($type=="workorder"){
                $this->redirect()->toRoute("cb/workorder", array("controller" => "workorder","action" => "register"));
            } else if($type=="bill"){
                $this->redirect()->toRoute("cb/clientbilling", array("controller" => "clientbilling","action" => "register"));
            } else if($type=="receipt"){
                $this->redirect()->toRoute("cb/receipt", array("controller" => "receipt","action" => "register"));
            }
        } else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
            if($type != 'workorder' && $type != 'bill' && $type != 'receipt')
                $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
                                
            if (!file_exists($filePath)) {
                if($type=="workorder"){
                    $filePath = 'public/reports/clientbilling/workorder/template.phtml';
                } else if($type=="rabill"){
                    $filePath = 'public/reports/clientbilling/bill/template.phtml';
                } else if($type=="receipt"){
                    $filePath = 'public/reports/clentbilling/receipt/template.phtml';
                }   
            }

            $this->_view->type = $type;
            //print_r($filePath);exit;
            $template = file_get_contents($filePath);

            $this->_view->template = $template;

            // csrf Key
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    }