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

class CronjobController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function checksubscriptionAction(){
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $select = $sql->select();
        $select->from( 'CB_SubscriberMaster' )
            ->columns( array( 'CreatedDate', 'ExpiryDate', 'EMail', 'BusinessName', 'ContactPerson' ) )
            ->where( "IsActive='1' AND IsDelete='0'" );
        $statement = $sql->getSqlStringForSqlObject($select);
        $subscribers = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        $sm = $this->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        foreach ($subscribers as $subscriber) {
            $startDate = new DateTime(date('Y-m-d'));
            $expiryDate = new DateTime($subscriber['ExpiryDate']);
            $difference = $startDate->diff($expiryDate);
            if($difference->days == 10) {
                // send mail before 10 days of expiry
                $mailData = array(
                    array(
                        'name' => 'EXPIRYDATE',
                        'content' => $subscriber['ExpiryDate']
                    ),
                    array(
                        'name' => 'BUSINESSNAME',
                        'content' => $subscriber['BusinessName']
                    ),
                    array(
                        'name' => 'CONTACTPERSON',
                        'content' => $subscriber['ContactPerson']
                    ),
                    array(
                        'name' => 'CREATEDDATE',
                        'content' => $subscriber['CreatedDate']
                    )
                );

                $viewRenderer->MandrilSendMail()->sendMailTo( $subscriber[ 'Email' ],$config['general']['mandrilEmail'], 'Subscription Expiry Alert Notification', 'cb_subscription_alert', $mailData );
            }
        }
    }

    public function subscriberDeactivationAction(){
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $select = $sql->select();
        $select->from( 'CB_SubscriberMaster' )
            ->columns( array( 'SubscriberId', 'CreatedDate', 'ExpiryDate', 'EMail', 'BusinessName', 'ContactPerson' ) )
            ->where( "IsActive='1' AND IsDelete='0'" );
        $statement = $sql->getSqlStringForSqlObject($select);
        $subscribers = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        $sm = $this->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        foreach ($subscribers as $subscriber) {
            $startDate = new DateTime(date('Y-m-d'));
            $expiryDate = new DateTime($subscriber['ExpiryDate']);
            $difference = $startDate->diff($expiryDate);
            if($difference->days == 0) {
                // update delete flag if expired
                $update = $sql->update();
                $update->table('CB_SubscriberMaster')
                    ->set( array('IsDelete' => '1'));
                $update->where(array('SubscriberId' => $subscriber['SubscriberId']));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $mailData = array(
                    array(
                        'name' => 'EXPIRYDATE',
                        'content' => $subscriber['ExpiryDate']
                    ),
                    array(
                        'name' => 'BUSINESSNAME',
                        'content' => $subscriber['BusinessName']
                    ),
                    array(
                        'name' => 'CONTACTPERSON',
                        'content' => $subscriber['ContactPerson']
                    ),
                    array(
                        'name' => 'CREATEDDATE',
                        'content' => $subscriber['CreatedDate']
                    )
                );

                $viewRenderer->MandrilSendMail()->sendMailTo( $subscriber[ 'Email' ], $config['general']['mandrilEmail'], 'Subscription Expired Alert Notification', 'cb_subscription_expired', $mailData );
            }
        }
    }


    public function autoBirthdayAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $today = date('Y-m-d');
        $tDay = date('d', strtotime($today));
        $tMonth = date('m', strtotime($today));
        $response = $this->getResponse();

        //Birthday Reminders
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('EmployeeName', 'UserId', 'UserDob', 'UserLogo', 'Email'))
            ->where->expression('MONTH(UserDob) = ?', $tMonth)
            ->where->expression('DAY(UserDob)=?', $tDay);
        $statement = $sql->getSqlStringForSqlObject($select);
        $birthdayAll = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sm = $this->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        if(isset($birthdayAll)) {
            foreach ($birthdayAll as $birthWish) {
                //birthday wish mail send//
                $mailData = array(

                    array(
                        'name' => 'EmployeeName',
                        'content' => $birthWish['EmployeeName']
                    ),
                    array(
                        'name' => 'Description',
                        'content' => 'Wishing You a Happy Birthday.........'
                    )

                );

                $viewRenderer->MandrilSendMail()->sendMailTo($birthWish['Email'], $config['general']['mandrilEmail'], 'Greetings!!!', 'BirthdayNote', $mailData);
            }
        }
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function billPaymentDueAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $curDate = date('Y-m-d');
        $select = $sql->select();
        $select->from(array("a"=>"Crm_ProgressBill"));
        $select->columns(array(new Expression("b.UnitId,e.UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,Convert(varchar(10),DATEADD(day,h.CreditDays,a.BillDate),105) as DueDate,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType, b.ProgressBillTransId,b.Amount,b.NetAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage, Convert(varchar(10),i.DOB,105) as DOB,b.PaidAmount,(b.NetAmount-b.PaidAmount) as BalanceAmount")))
            ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array(), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $select::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array(), $select::JOIN_LEFT)
            ->join(array("f"=>"Crm_UnitBooking"), "f.UnitId=b.UnitId", array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email'), $select::JOIN_LEFT)
            ->join(array("h"=>"Crm_UnitType"), "h.UnitTypeId=e.UnitTypeId", array('CreditDays', 'IntPercent'), $select::JOIN_LEFT)
            ->join(array("i"=>"Crm_LeadPersonalInfo"), "i.LeadId=f.LeadId", array(), $select::JOIN_LEFT)
            ->where("a.DeleteFlag=0 and h.CreditDays IS NOT NULL and DATEADD(day,h.CreditDays,a.BillDate) > '$curDate' and (b.NetAmount-b.PaidAmount) > 0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $paymentInfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('WF_GeneralSetting')
            ->columns(array('PaymentReminderDays'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $balPayDays = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $sm = $this->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        if(isset($paymentInfo)) {
            foreach ($paymentInfo as $payIn) {
                //birthday wish mail send//
                $dueDate = strtotime(date('Y-m-d',strtotime($payIn['DueDate'])));
                $cur_time = strtotime($curDate);
                $time_elapsed = $dueDate - $cur_time;
                $days = round($time_elapsed / 86400);
                if ($days == $balPayDays['PaymentReminderDays']) {
                    $mailInfo = array(
                        array(
                            'name' => 'PBNo',
                            'content' => $payIn['PBNo']
                        ),
                        array(
                            'name' => 'BillDate',
                            'content' => $payIn['BillDate']
                        ),
                        array(
                            'name' => 'BuyerName',
                            'content' => $payIn['BuyerName']
                        ),
                        array(
                            'name' => 'DueDate',
                            'content' => $payIn['DueDate']
                        ),
                        array(
                            'name' => 'NetAmount',
                            'content' => $payIn['NetAmount']
                        ),
                        array(
                            'name' => 'PaidAmount',
                            'content' => $payIn['PaidAmount']
                        ),
                        array(
                            'name' => 'BalanceAmount',
                            'content' => $payIn['BalanceAmount']
                        ),
                        array(
                            'name' => 'DaysBal',
                            'content' => $days." days remaining to pay your Balance Bill Amount"
                        )
                    );

                    $viewRenderer->MandrilSendMail()->sendMailTo($payIn['Email'], $config['general']['mandrilEmail'], 'Reminds!!!', 'PaymentReminder', $mailInfo);
                } else if($days == 0) {
                    $mailInfo = array(
                        array(
                            'name' => 'PBNo',
                            'content' => $payIn['PBNo']
                        ),
                        array(
                            'name' => 'BillDate',
                            'content' => $payIn['BillDate']
                        ),
                        array(
                            'name' => 'BuyerName',
                            'content' => $payIn['BuyerName']
                        ),
                        array(
                            'name' => 'DueDate',
                            'content' => $payIn['DueDate']
                        ),
                        array(
                            'name' => 'NetAmount',
                            'content' => $payIn['NetAmount']
                        ),
                        array(
                            'name' => 'PaidAmount',
                            'content' => $payIn['PaidAmount']
                        ),
                        array(
                            'name' => 'BalanceAmount',
                            'content' => $payIn['BalanceAmount']
                        ),
                        array(
                            'name' => 'DaysBal',
                            'content' => " Today is your last day to pay Balance Bill Amount"
                        )
                    );

                    $viewRenderer->MandrilSendMail()->sendMailTo($payIn['Email'], $config['general']['mandrilEmail'], 'Reminds!!!', 'PaymentReminder', $mailInfo);
                }
            }
        }
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function lateInterestAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $curDate = date('Y-m-d');
        $select = $sql->select();
        $select->from(array("a"=>"Crm_ProgressBill"));
        $select->columns(array(new Expression("b.UnitId,e.UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,Convert(varchar(10),DATEADD(day,h.CreditDays,a.BillDate),105) as DueDate, a.ProgressBillId,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType, b.ProgressBillTransId,b.Amount,b.NetAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                        When a.StageType='D' then 'DescriptionName' end as Stage, Convert(varchar(10),i.DOB,105) as DOB,b.PaidAmount,(b.NetAmount-b.PaidAmount) as BalanceAmount")))
            ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array(), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $select::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array('ProjectId'), $select::JOIN_LEFT)
            ->join(array("f"=>"Crm_UnitBooking"), "f.UnitId=b.UnitId", array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email'), $select::JOIN_LEFT)
            ->join(array("k"=>"Crm_LeadAddress"), "g.LeadId=k.LeadId", array('Address1'), $select::JOIN_LEFT)
            ->join(array("h"=>"Crm_UnitType"), "h.UnitTypeId=e.UnitTypeId", array('CreditDays', 'IntPercent'), $select::JOIN_LEFT)
            ->join(array("i"=>"Crm_LeadPersonalInfo"), "i.LeadId=f.LeadId", array(), $select::JOIN_LEFT)
            ->where("a.DeleteFlag=0 and h.CreditDays  !=0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $paymentInfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



        if(isset($paymentInfo)) {
            foreach ($paymentInfo as $payIn) {
              //  if($payIn['BalanceAmount']!=0 || $payIn['BalanceAmount']!='' || $payIn['BalanceAmount']!=NULL) {
                    $select = $sql->select();
                    $select->from(array("a"=>"Crm_ProjectDetail"))
                    ->where(array('a.ProjectId'=>$payIn['ProjectId']));
                  $statement = $sql->getSqlStringForSqlObject($select);
                    $intercalc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                   $tm=0;

                    if($intercalc['IntCalculationOn']=='S'){
                        $select->from(array("a"=>"KF_StageCompletion"))
                            ->columns(array('CompletionDate'))
                            ->join(array("e"=>"KF_StageCompletionTrans"), "a.StageCompletionId=e.StageCompletionId", array(), $select::JOIN_LEFT)
                            ->where(array('a.ProjectId'=>$payIn['ProjectId'],'e.UnitId'=>$payIn['UnitId'],'a.StageCompletionId'=>$payIn['StageCompletionId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $stagecompletedate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dueDate = date('Y-m-d',strtotime($stagecompletedate['CompletionDate']));
                        $datecalc1=date('Y-m-d',strtotime($dueDate .'+'.$payIn['CreditDays'].' days'));
                        $datecalc= strtotime(date('Y-m-d',strtotime($dueDate .'+'.$payIn['CreditDays'].' days')));
                        $cur_time = strtotime($curDate);
                        if($datecalc1 >= date('Y-m-d')){
                            $time_elapsed=0;
                        }
                        else {
                            $time_elapsed = $cur_time - $datecalc;
                        }
                        $tm=round($time_elapsed / 86400);


                        //calculation from buyer amount//
                        if($intercalc['IntCalculationFrom']=='B'){
                            $latefee= ($payIn['PaidAmount'])*($payIn['IntPercent'])*($tm)/36500;
                        }
                        //calculation from Scheduleamount//
                        if($intercalc['IntCalculationFrom']=='S'){
                            $latefee=($payIn['BalanceAmount'])*($payIn['IntPercent'])*($tm)/36500;
                        }
                    }

                    //Interest Calculation based on ProgressBill //
                    if($intercalc['IntCalculationOn']=='P'){
                        $dueDate = date('Y-m-d',strtotime($payIn['BillDate']));
                        $datecalc1=date('Y-m-d',strtotime($dueDate .'+'.$payIn['CreditDays'].' days'));
                        $datecalc= strtotime(date('Y-m-d',strtotime($dueDate .'+'.$payIn['CreditDays'].' days')));
                        $cur_time = strtotime($curDate);

                        if($datecalc1 >= date('Y-m-d')){
                            $time_elapsed=0;
                        }
                        else{
                        $time_elapsed = $cur_time - $datecalc;
                        }
                        $tm=round($time_elapsed / 86400);

                         //calculation from buyer amount//
                        if($intercalc['IntCalculationFrom']=='B'){
                            $latefee= ($payIn['PaidAmount'])*($payIn['IntPercent'])*($tm)/36500;
                        }
                        //calculation from Scheduleamount//
                        if($intercalc['IntCalculationFrom']=='S'){
                            $latefee=($payIn['BalanceAmount'])*($payIn['IntPercent'])*($tm)/36500;
                        }
               }

                     //update//
                    $update = $sql->update();
                        $update->table('Crm_ProgressBillTrans');
                        $update->set(array(
                            'LateFee' => $latefee,
                           // 'NextDueDate' => $newdate,
                        ));
                        $update->where(array('ProgressBillTransId' => $payIn['ProgressBillTransId'], 'ProgressBillId' => $payIn['ProgressBillId']));
                       $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    if ($tm > 0) {
                        $mailInfo = array(
                            array(
                                'name' => 'PROGRESSBILLNO',
                                'content' => $payIn['PBNo']
                            ),
                            array(
                                'name' => 'PROGRESSBILLDATE',
                                'content' => $payIn['BillDate']
                            ),
                            array(
                                'name' => 'BUYERNAME',
                                'content' => $payIn['BuyerName']
                            ),
//                            array(
//                                'name' => 'DUEDATE',
//                                'content' => $newdate
//                            ),
                            array(
                                'name' => 'NETAMOUNT',
                                'content' => $payIn['NetAmount']
                            ),
                            array(
                                'name' => 'PAIDAMOUNT',
                                'content' => $payIn['PaidAmount']
                            ),
                            array(
                                'name' => 'BalanceAmount',
                                'content' => $payIn['BalanceAmount']
                            ),

                            array(
                                'name' => 'BUYERADDRESS1',
                                'content' => $payIn['Address1']
                            ),
                            array(
                                'name' => 'BUYERMAIL',
                                'content' => $payIn['Email']
                            ),

                            array(
                                'name' => 'NETAMOUNT',
                                'content' => $payIn['NetAmount']
                            ),
                            array(
                                'name' => 'STAGENAME',
                                'content' => $payIn['StageName']
                            ),
                            array(
                                'name' => 'LateFee',
                                'content' => $latefee
                            )
                        );
                        $viewRenderer->MandrilSendMail()->sendMailTo($payIn['Email'], 'admin@ra-bills.com', 'Reminds!!!', 'PaymentReminder', $mailInfo);
                    }

        //    }
 }

        }

        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function reminderAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $endDate= date('Y-m-d')." 00:00:00";

        $select = $sql->select();
        $select->from('WF_Reminder')
            ->columns(array(new Expression("ReminderId,CONVERT(varchar(10),RDate,105) as RDate,RDescription ,Type,'' EmployeeName,RepeatEvery")))
            ->where(array('DeleteFlag' => 0,'Type'=>1));
        $select->where->greaterThanOrEqualTo('RDate', $endDate);
        $select->order('ReminderId desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $reminders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($reminders as $rem):

            if($rem['RepeatEvery']==3) {
                $insert  = $sql->insert('WF_AppNotification');
                $newData = array(
                    'ReminderId' => $rem['ReminderId'],
                    'NotificationType' =>'reminder',
                    'CreatedDate'=>date('Y-m-d H:i:s')
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            } else if($rem['RepeatEvery']==2) {
                if(date('l')=="Friday") {
                    $insert  = $sql->insert('WF_AppNotification');
                    $newData = array(
                        'ReminderId' => $rem['ReminderId'],
                        'NotificationType' =>'reminder',
                        'CreatedDate'=>date('Y-m-d H:i:s')
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            } else {
                if(date('d')==1) {
                    $insert  = $sql->insert('WF_AppNotification');
                    $newData = array(
                        'ReminderId' => $rem['ReminderId'],
                        'NotificationType' =>'reminder',
                        'CreatedDate'=>date('Y-m-d H:i:s')
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }

        endforeach;
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function newsAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $endDate= date('Y-m-d')." 23:59:59";
        $startDate= date('Y-m-d')." 00:00:00";

        $select = $sql->select();
        $select->from('WF_News')
            ->columns(array('NewsId'))
            ->where(array('DeleteFlag' => 0));
        $select->where->greaterThanOrEqualTo('ToDate', $startDate)
            ->lessThanOrEqualTo('FromDate', $endDate);
        $select->order('NewsId desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $news = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($news as $new):

            $insert  = $sql->insert('WF_AppNotification');
            $newData = array(
                'NewsId' => $new['NewsId'],
                'NotificationType' =>'News',
                'CreatedDate'=>date('Y-m-d H:i:s')
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        endforeach;
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function unBlockAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $endDate= date('Y-m-d');

        $select = $sql->select();
        $select->from(array('a'=>'Crm_UnitBlock'))
            ->join(array("b"=>"Crm_Leads"), "a.LeadId=b.LeadId", array('Email','LeadName'), $select::JOIN_LEFT)
            ->join(array("c"=>"KF_UnitMaster"), "a.UnitId=c.UnitId", array('UnitNo','UnitId'), $select::JOIN_LEFT)
            ->join(array("d"=>"Proj_ProjectMaster"), "c.ProjectId=d.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
            ->columns(array('BlockId','ValidUpto'))
            ->where(array('a.DeleteFlag' => 0));
        $select->where->lessThan('ValidUpto', $endDate);
        $statement = $sql->getSqlStringForSqlObject($select);
        $blockDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sm = $this->getServiceLocator();
        $config = $sm->get('application')->getConfig();

        foreach($blockDetails as $block):

            $update = $sql->update();
            $update->table('Crm_UnitBlock');
            $update->set(array(
                'DeleteFlag'  => '1',
            ));
            $update->where(array('BlockId' =>$block['BlockId']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('KF_UnitMaster');
            $update->set(array(
                'Status'  => 'U',
            ));
            $update->where(array('UnitId' =>$block['UnitId']));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            if($block['Email']!='') {

                $mailData = array(
                    array(
                        'name' => 'LEADNAME',
                        'content' => $block['LeadName']
                    ),
                    array(
                        'name' => 'UNITNO',
                        'content' => $block['UnitNo']
                    ),
                    array(
                        'name' => 'PROJECTNAME',
                        'content' => $block['ProjectName']
                    ),
                    array(
                        'name' => 'VALIDDATE',
                        'content' => $block['ValidUpto']
                    )
                );

                $viewRenderer->MandrilSendMail()->sendMailTo($block['Email'], $config['general']['mandrilEmail'], 'Ublock Alert Notification', 'Crm_Unblock', $mailData);

            }
        endforeach;
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

    public function todayFollowupAction() {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        $PositionTypeId=array(5,2);
        $sub = $sql->select();
        $sub->from(array('a'=>'WF_PositionMaster'))
            ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
            ->columns(array('PositionId'))
            ->where(array("b.PositionTypeId"=>$PositionTypeId));

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','EmployeeName','UserName','Email'))
            ->where->expression("PositionId IN ?",array($sub));
        $select->where(array("DeleteFlag"=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsExe= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $today = date('Y-m-d');
        if(isset($resultsExe)) {

            foreach($resultsExe as $resExe) {

                $arrExecutiveIds = $viewRenderer->commonHelper()->masterSuperior($resExe['UserId'],$dbAdapter);

                if(count($arrExecutiveIds)>0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_LeadFollowup'))
                        ->columns(array('FollowUpDate'))
                        ->join(array('b' => 'Crm_Leads'), 'b.LeadId=a.LeadId', array('LeadId', 'LeadName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_CallTypeMaster'), 'c.CallTypeId=a.NextFollowUpTypeId', array('CallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Crm_CallTypeMaster'), 'd.CallTypeId=a.CallTypeId', array('PrevCallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('g' => 'Crm_NatureMaster'), 'g.NatureId=a.NatureId', array('PrevCallNatureDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_Users'), 'b.ExecutiveId=e.UserId', array('ExecuName' => 'EmployeeName', 'Email'), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0, 'a.NextCallDate' => $today));
                    $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                    $select->order('Completed ASC');
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrTodayLeadFollowups = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $data='<table style="font-family:arial, sans-serif;border-collapse:collapse;width:100%;">
                                     <tr style="background-color:#4CAF50;color:#fff;">
                                        <th style="border:1px solid #dddddd;text-align:left;padding:8px;">Lead Name</th>
                                        <th style="border:1px solid #fff;text-align:left;padding:8px;">Previous Followup Date</th>
                                        <th style="border:1px solid #fff;text-align:left;padding:8px;">Previous Followup Type</th>
                                        <th style="border:1px solid #fff;text-align:left;padding:8px;">Nature</th>
                                        <th style="border:1px solid #fff;text-align:left;padding:8px;">Followup Type</th>
                                        <th style="border:1px solid #fff;text-align:left;padding:8px;">Executive Name</th>
                                    </tr>';
                if(isset($arrTodayLeadFollowups)) {
                    foreach ($arrTodayLeadFollowups as $arrToday):
                        $data .= '<tr>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . $arrToday['LeadName'] . '</td>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . date('d-m-Y', strtotime($arrToday['FollowUpDate'])) . '</td>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . $arrToday['PrevCallTypeDec'] . '</td>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . $arrToday['PrevCallNatureDec'] . '</td>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . $arrToday['CallTypeDec'] . '</td>
                                <td style="border:1px solid #dddddd;text-align:left;padding:8px;">' . $arrToday['ExecuName'] . '</td>
                            </tr>';
                    endforeach;
                }
                $data.='</table>';

                if(isset($arrTodayLeadFollowups) && count($arrTodayLeadFollowups)>0) {

                    if ($resExe['Email'] != '') {

                        $mailData = array(
                            array(
                                'name' => 'TODAYFOLLOWUP',
                                'content' => $data
                            ),
                            array(
                                'name' => 'Executive',
                                'content' => $resExe['EmployeeName']
                            )
                        );

                        $viewRenderer->MandrilSendMail()->sendMailTo($resExe['Email'], 'sairam@micromen.info', 'Today Followup', 'today_followup_list', $mailData);

                    }
                }

            }
        }
        $this->_view->setTerminal(true);
        $response->setContent("success");
        return $response;
    }

}