<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Fa\Controller;

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
use Fa\View\Helper\FaHelper;
use Zend\Session\Container;
use Application\View\Helper\Qualifier;

class ReportController extends AbstractActionController
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction()
    {
        if (!$this->auth->hasIdentity()) {
            $this->redirect()->toRoute('application/default', array('controller' => 'index', 'action' => 'index'));
        }

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        return $this->_view;
    }


    public function trialbalancerptAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
            //$response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $this->strAccListId="";
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->columns(array("AccountId"));
            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accfilledList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($accfilledList as $accfilledLists) {
                $curAccId=$accfilledLists['AccountId'];
                //$this->AccountList[]=$curAccId;
                FaHelper::GetsuperiorAccList($curAccId, $dbAdapter);
                $this->strAccListId=$this->strAccListId. $curAccId .",";
            }

            if($this->strAccListId != ""){
                $this->strAccListId = rtrim($this->strAccListId,',');
            }
            $accIds=$this->strAccListId;
            if($accIds==""){
                $accIds="0";
            }

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $select = $sql->select();
            $select->columns(array(new expression("SELECT A.AccountId,A.AccountName,A.ParentAccountId,A.LastLevel,
                    CASE WHEN B.CB >0 THEN B.CB ELSE 0 END Debit,
                    CASE WHEN B.CB <0 THEN B.CB*(-1) ELSE 0 END Credit,A.TypeId,(1-1) as expanded
                    FROM dbo.FA_AccountList A LEFT JOIN (SELECT A.AccountId,CB=SUM(ISNULL(A.OB,0)+ISNULL(A.TB,0)) FROM
                     (SELECT AccountId, OB=0, TB= CASE WHEN TransType='D' THEN Amount ELSE -Amount END FROM FA_EntryTrans
                     WITH (READPAST) WHERE [PDC/Cancel]=0 AND CompanyId=$companyId AND Approve='Y' AND [PDC/Cancel]=0 AND
                      VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' ) A GROUP BY AccountId )
                      B ON A.AccountId = B.AccountId WHERE A.CompanyId = $companyId AND ((A.LastLevel='N' ) Or
                      (B.AccountId <>0 AND A.LastLevel='Y'))  ORDER BY A.LevelNo,A.SortId, A.AccountName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
            $accDirectoryList = $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists1= array();
            $irowACCId=0;
            foreach($accDirectoryList as $accDirectoryLists) {
                $irowACCId=$accDirectoryLists['AccountId'];
                //$this->strAccListId="";
                $this->strAccListId=$irowACCId .",";
                if($irowACCId!=0){
                    FaHelper::GetLowLevelAccList($irowACCId, $companyId, $dbAdapter);
                }

                if($this->strAccListId != ""){
                    $this->strAccListId = rtrim($this->strAccListId,',');
                }
                //var_dump($this->AccountList);
                $accIds=$this->strAccListId;
                if($accIds==""){ $accIds="0";}
                /* if($irowACCId==9){
                     echo $accIds;die;
                 }*/
                $selectFinal = $sql->select();
                $selectFinal->from(array("A" => "FA_EntryTrans"))
                    ->columns(array("debit"=> new Expression("CASE WHEN TransType='D' THEN Amount ELSE 0 END"),"credit"=> new Expression("CASE WHEN TransType='C' THEN Amount ELSE 0 END")));
                //->join(array("A" => "FA_Account"), new Expression("B.AccountId=A.AccountId And A.CompanyId=B.CompanyId"), array(), $select::JOIN_INNER);
                $selectFinal->where("[PDC/Cancel]=0 AND CompanyId=$companyId and AccountId in ($accIds) AND Approve='Y' AND [PDC/Cancel]=0 AND
                      VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'");

                $select = $sql->select();
                $select->from(array("g" => $selectFinal))
                    ->columns(array("debit"=> new Expression("isnull(sum(g.debit),0)"),"credit"=> new Expression("isnull(sum(g.credit),0)")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $obAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $diffAmt=$obAccList['debit']-$obAccList['credit'];
                $debit=0;
                $credit=0;
                if($diffAmt>0){
                    $debit=$diffAmt;
                } else {
                    $credit=(-1)*$diffAmt;
                }

                $dumArr1 = array();
                if($accDirectoryLists['LastLevel']=="Y"){
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'Debit' => $accDirectoryLists['Debit'],
                        'Credit' => $accDirectoryLists['Credit'],
                        'TypeId' => $accDirectoryLists['TypeId'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                } else {
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'Debit' => $debit,
                        'Credit' => $credit,
                        'TypeId' => $accDirectoryLists['TypeId'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                }
                $arrUnitLists1[] = $dumArr1;
            }
            $this->_view->accDirectoryList = $arrUnitLists1;

            $select = $sql->select();
            $select->from(array("a" => "WF_CompanyMaster"))
                ->columns(array("CompanyName"));
            $select->where("CompanyId=$companyId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $companyName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->companyName = $companyName['CompanyName'];

            $this->_view->companyId = $companyId;
            $this->_view->fYearId = $fYearId;
            $this->_view->g_lCNYearId = 0;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function trialbalancetransAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $acc_Ids= $this->bsf->isNullCheck($this->params()->fromRoute('accIds'),'string');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
            //$response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            //strart
            $arrAccNameLists = array();
            $select = $sql->select();
            $select->from("FA_AccountMaster")
                ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                ->where("ParentAccountId=0");//and LastLevel='N' AND AccountType NOT IN ('BA','CA')
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accFirstLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($accFirstLevelList as &$accFirstLevelLists) {
                $iLevelno = 1;
                $iAccId = $accFirstLevelLists['AccountId'];
                $sAccDesc =$accFirstLevelLists['AccountName'];

                $dumArr = array();
                $dumArr = array(
                    'Id' => $iAccId,
                    'ParentId' => $accFirstLevelLists['ParentAccountId'],
                    //'LevelNo' => $iLevelno,
                    'LevelNo' => $accFirstLevelLists['LevelNo'],
                    'LastLevel' => $accFirstLevelLists['LastLevel'],
                    'data' => $iAccId,
                    'value' => $sAccDesc
                );
                $arrAccNameLists[] = $dumArr;

                $select = $sql->select();
                $select->from("FA_AccountMaster")
                    ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                    ->where("ParentAccountId=$iAccId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $accSecondLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach ($accSecondLevelList as &$accSecondLevelLists) {
                    $iLevelno = 2;
                    $iAccId = $accSecondLevelLists['AccountId'];
                    $sAccDesc="-->" . $accSecondLevelLists['AccountName'];
                    $dumArr = array();
                    $dumArr = array(
                        'Id' => $iAccId,
                        'ParentId' => $accSecondLevelLists['ParentAccountId'],
                        //'LevelNo' => $iLevelno,
                        'LevelNo' => $accSecondLevelLists['LevelNo'],
                        'LastLevel' => $accSecondLevelLists['LastLevel'],
                        'data' => $iAccId,
                        'value' => $sAccDesc
                    );
                    $arrAccNameLists[] = $dumArr;

                    $select = $sql->select();
                    $select->from("FA_AccountMaster")
                        ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                        ->where("ParentAccountId=$iAccId ");
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $accThirdLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach ($accThirdLevelList as &$accThirdLevelLists) {
                        $iLevelno = 3;
                        $iAccId = $accThirdLevelLists['AccountId'];
                        $sAccDesc="-->-->" . $accThirdLevelLists['AccountName'];

                        $dumArr = array();
                        $dumArr = array(
                            'Id' => $iAccId,
                            'ParentId' => $accThirdLevelLists['ParentAccountId'],
                            //'LevelNo' => $iLevelno,
                            'LevelNo' => $accThirdLevelLists['LevelNo'],
                            'LastLevel' => $accThirdLevelLists['LastLevel'],
                            'data' => $iAccId,
                            'value' => $sAccDesc
                        );
                        $arrAccNameLists[] = $dumArr;

                        $select = $sql->select();
                        $select->from("FA_AccountMaster")
                            ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                            ->where("ParentAccountId=$iAccId ");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $accFourthLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($accFourthLevelList as &$accFourthLevelLists) {
                            $iLevelno = 4;
                            $iAccId = $accFourthLevelLists['AccountId'];
                            $sAccDesc="-->-->-->" . $accFourthLevelLists['AccountName'];

                            $dumArr = array();
                            $dumArr = array(
                                'Id' => $iAccId,
                                'ParentId' => $accFourthLevelLists['ParentAccountId'],
                                //'LevelNo' => $iLevelno,
                                'LevelNo' => $accFourthLevelLists['LevelNo'],
                                'LastLevel' => $accFourthLevelLists['LastLevel'],
                                'data' => $iAccId,
                                'value' => $sAccDesc
                            );
                            $arrAccNameLists[] = $dumArr;

                            $select = $sql->select();
                            $select->from("FA_AccountMaster")
                                ->columns(array('AccountId', 'AccountName', 'ParentAccountId', 'LevelNo', 'LastLevel'))
                                ->where("ParentAccountId=$iAccId");
                            $statement = $statement = $sql->getSqlStringForSqlObject($select);
                            $accFifthLevelList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($accFifthLevelList as &$accFifthLevelLists) {
                                $iLevelno = 5;
                                $iAccId = $accFifthLevelLists['AccountId'];
                                $sAccDesc="-->-->-->-->" . $accFifthLevelLists['AccountName'];

                                $dumArr = array();
                                $dumArr = array(
                                    'Id' => $iAccId,
                                    'ParentId' => $accFifthLevelLists['ParentAccountId'],
                                    //'LevelNo' => $iLevelno,
                                    'LevelNo' => $accFifthLevelLists['LevelNo'],
                                    'LastLevel' => $accFifthLevelLists['LastLevel'],
                                    'data' => $iAccId,
                                    'value' => $sAccDesc
                                );
                                $arrAccNameLists[] = $dumArr;

                            }
                        }

                    }

                }

            }
            //End
            $this->_view->strParentAccName = $arrAccNameLists;
            /*
            * SELECT ET.EntryTransId,ET.VoucherDate,ET.VoucherNo,ET.AccountId,ET.RelatedAccountId,
            ET.SubLedgerId,AM.AccountName,SubLedgerName=CASE WHEN AM.TypeId IN (-1) THEN ' '
            ELSE SLM.SubLedgerName END,RefSubLedgerName=CASE WHEN AM1.TypeId IN (197) THEN ' '
            ELSE SLM1.SubLedgerName END,RefType, CASE WHEN ET.TransType='D' THEN Abs(ET.Amount) ELSE 0
            END Debit, CASE WHEN ET.TransType='C' THEN Abs(ET.Amount) ELSE 0 END Credit, EM.ChequeNo,
            ChequeDate=Convert(varchar,ChequeDate,103), ET.Remarks Narration, ET.IRemarks,CC.CostCentreName
            FROM FA_EntryTrans ET
            LEFT JOIN FA_EntryMaster EM  ON EM.EntryId=ET.RefId AND EM.JournalType=ET.RefType
            INNER JOIN FA_AccountMaster AM  ON ET.RelatedAccountId=AM.AccountId
            INNER JOIN FA_AccountMaster AM1  ON ET.AccountId=AM1.AccountId
            LEFT JOIN WF_OperationalCostCentre CC   ON ET.CostCentreId=CC.CostCentreId
            LEFT JOIN FA_SubLedgerMaster SLM  ON ET.RelatedSLId=SLM.SubLedgerId
            LEFT JOIN FA_SubLedgerMaster SLM1  ON ET.SubLedgerId=SLM1.SubLedgerId
            WHERE [PDC/Cancel]=0 AND ET.VoucherDate<='31-May-2017' AND ET.Approve='Y' AND [PDC/Cancel]=0 AND
            ET.CompanyId=1
            AND ET.AccountId IN (197)
            ORDER By ET.VoucherDate, ET.EntryTransId
            */

            $this->strAccListId=$acc_Ids.",";
            $select = $sql->select();
            $select->from(array("a" => "FA_AccountMaster"))
                ->columns(array("AccountId"));
            $select->where("a.ParentAccountId In ($acc_Ids)");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accfilledList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($accfilledList as $accfilledLists) {
                $curAccId=$accfilledLists['AccountId'];
                //$this->AccountList[]=$curAccId;
                FaHelper::GetLowLevelAccList($curAccId, $companyId, $dbAdapter);//GetChildsuperiorAccList
                $this->strAccListId=$this->strAccListId. $curAccId .",";
            }

            if($this->strAccListId != ""){
                $this->strAccListId = rtrim($this->strAccListId,',');
            }
            $accIds=$this->strAccListId;
            if($accIds==""){
                $accIds="0";
            }
            /*$irowACCId= $acc_Ids;
            $this->strAccListId=$irowACCId .",";
            if($irowACCId!=0){
                $this->GetLowLevelAccList($irowACCId, $companyId, $dbAdapter);
            }

            if($this->strAccListId != ""){
                $this->strAccListId = rtrim($this->strAccListId,',');
            }
            //var_dump($this->AccountList);
            $accIds=$this->strAccListId;
            if($accIds==""){ $accIds="0";}*/
            //echo $accIds;die;

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $select = $sql->select();
            $select->from(array("ET" => "FA_EntryTrans"))
                ->join(array("EM" => "FA_EntryMaster"), "EM.EntryId=ET.RefId AND EM.JournalType=ET.RefType", array(), $select::JOIN_LEFT)
                ->join(array("AM" => "FA_AccountMaster"), "ET.RelatedAccountId=AM.AccountId", array(), $select::JOIN_INNER)
                ->join(array("AM1" => "FA_AccountMaster"), "ET.AccountId=AM1.AccountId", array(), $select::JOIN_INNER)
                ->join(array("CC" => "WF_OperationalCostCentre"), "ET.CostCentreId=CC.CostCentreId", array(), $select::JOIN_LEFT)
                ->join(array("SLM" => "FA_SubLedgerMaster"), "ET.RelatedSLId=SLM.SubLedgerId", array(), $select::JOIN_LEFT)
                ->join(array("SLM1" => "FA_SubLedgerMaster"), "ET.SubLedgerId=SLM1.SubLedgerId", array(), $select::JOIN_LEFT)
                ->columns(array('EntryTransId', 'VoucherDate' => new Expression("FORMAT(ET.VoucherDate, 'dd-MM-yyyy')"), 'VoucherNo'
                ,'AccountId','RelatedAccountId','SubLedgerId','AccountName'=> new Expression("AM.AccountName")
                ,'SubLedgerName'=> new Expression("CASE WHEN AM.TypeId IN (-1) THEN ' ' ELSE SLM.SubLedgerName END")
                ,'RefSubLedgerName'=> new Expression("CASE WHEN AM1.TypeId IN (197) THEN ' ' ELSE SLM1.SubLedgerName END")
                ,'RefType','Debit'=> new Expression("CASE WHEN ET.TransType='D' THEN Abs(ET.Amount) ELSE 0 END")
                ,'Credit'=> new Expression("CASE WHEN ET.TransType='C' THEN Abs(ET.Amount) ELSE 0 END")
                ,'ChequeNo'=> new Expression("EM.ChequeNo"), 'ChequeDate' => new Expression("FORMAT(EM.ChequeDate, 'dd-MM-yyyy')")
                ,'Narration'=> new Expression("ET.Remarks"),'IRemarks','CostCentreName'=> new Expression("CC.CostCentreName")
                ));
            $select->where(" [PDC/Cancel]=0 AND ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.CompanyId=$companyId AND ET.AccountId IN ($accIds)");
            //$select->order(array("ET.VoucherDate", "ET.EntryTransId"));

            /*Available for only Ason date
             * $selectFill = $sql->select();
            $selectFill->from(array("a"=>"FA_Account"))
                ->columns(array('EntryTransId' => new Expression("1-1"), 'VoucherDate' => new Expression("''"), 'VoucherNo' => new Expression("''")
                ,'AccountId' => new Expression("1-1"),'RelatedAccountId' => new Expression("1-1"),'SubLedgerId' => new Expression("1-1")
                ,'AccountName'=> new Expression("'Opening Balance'") ,'SubLedgerName'=> new Expression("''"),'RefSubLedgerName'=> new Expression("''")
                ,'RefType' => new Expression("''"),'Debit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN SUM(ISNULL(OpeningBalance,0)) ELSE 0 END")
                ,'Credit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN 0 ELSE Abs(SUM(ISNULL(OpeningBalance,0))) END")
                ,'ChequeNo'=> new Expression("''"), 'ChequeDate' => new Expression("''")
                ,'Narration'=> new Expression("''"),'IRemarks'=> new Expression("''"),'CostCentreName'=> new Expression("''")));
            $selectFill->where("AccountId IN ($accIds) and CompanyId=$companyId and FYearId=$fYearId");
            $selectFill->combine($select, 'Union ALL');*/

            $selectState = $sql->select();
            $selectState->from(array("g"=>$select))
                ->columns(array("*"));
            $selectState->order(array("g.VoucherDate", "g.EntryTransId"));
            $statement = $sql->getSqlStringForSqlObject($selectState);
            $regList= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->regList= $regList;

            $this->_view->companyId = $companyId;
            $this->_view->fYearId = $fYearId;
            $this->_view->g_lCNYearId = 0;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;

            $acc_Ids=explode(",",$acc_Ids);
            $this->_view->acc_Ids=$acc_Ids;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function generalledgerrptAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }
        $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
        $toDateFormatted=date('d/M/Y',strtotime($toDate));

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
//            $response->setContent(json_encode($resp));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }

            $select1 = $sql->select();
            $select1->from(array("ET"=>"FA_EntryTrans"))
                ->columns(array("AccountId"=>new Expression("ET.AccountId")
                ,"Debit"=>new Expression("sum(Case when ET.TransType='D' then ET.Amount ELSE -ET.Amount END)")
                ,"Credit"=>new Expression("sum(Case when ET.TransType='C' then -ET.Amount ELSE ET.Amount END)")));
            $select1->where(array("ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.CompanyId=$companyId"));
            $select1->group("ET.AccountId");

            $selectA= $sql->select();
            $selectA->from(array("A1"=>$select1))
                ->columns(array("AccountId"=>new Expression("A.AccountId"),"AccountName"=>new Expression("A.AccountName")
                ,"Debit"=>new Expression("Case when A1.Debit >0 then A1.Debit ELSE 0 END")
                ,"Credit"=>new Expression("Case when A1.Credit<0 then ABS(A1.Credit) ELSE 0 END")
                ,"TypeId"=>new Expression("A.TypeId")));
            $selectA->join(array("A"=>"FA_AccountMaster"),"a.AccountId=A1.AccountId",array(),$selectA::JOIN_INNER);
            $selectA->where(array("a.AccountType NOT IN ('BA','CA')"));

            $selectB = $sql->select();
            $selectB->from(array("A"=>"FA_AccountList"))
                ->columns(array("AccountId"=>new Expression("A.AccountId"),"AccountName"=>new Expression("A.AccountName")
                ,"Debit"=>new Expression("Case when B.OP >0 then B.OP ELSE 0 END")
                ,"Credit"=>new Expression("Case when B.OP <0 then B.OP*(-1) ELSE 0 END "),"TypeId"=>new Expression("A.TypeId")));
            $selectB->join(array("B"=>"FA_AccountListOP"),"A.AccountId = B.AccountId AND A.CompanyId = B.CompanyId",array(),$selectB::JOIN_INNER);
            $selectB->where(array("A.CompanyId =$companyId AND A.AccountType NOT IN ('BA','CA') AND A.LastLevel='Y'"));
            $selectB->combine($selectA,'Union ALL');

            $selectSub = $sql->select();
            $selectSub->from(array("A"=>$selectB))
                ->columns(array("AccountId"=>new Expression("A.AccountId"),"AccountName"=>new Expression("A.AccountName")
                ,"Debit"=>new Expression("SUM(Debit)"),"Credit"=>new Expression("Sum(Credit)"),"TypeId"=>new Expression("A.TypeId")));
            $selectSub->group(array('A.AccountId','A.AccountName','A.TypeId'));

            $selectFinal = $sql->select();
            $selectFinal->from(array("A"=>$selectSub))
                ->columns(array("AccountId"=>new Expression("A.AccountId"),"AccountName"=>new Expression("A.AccountName")
                ,"Debit"=>new Expression("Case when Debit-Credit>0 THEN Debit-Credit ELSE 0 END")
                ,"Credit"=>new Expression("Case when Credit-Debit>0 THEN Credit-Debit ELSE 0 END"),"TypeId"=>new Expression("A.TypeId")));
            $selectFinal->order("A.AccountName");
            $statement = $sql->getSqlStringForSqlObject($selectFinal);
            $generalLedgerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view-> generalLedgerList= $generalLedgerList;

            $select = $sql->select();
            $select->from(array("a" => "WF_CompanyMaster"))
                ->columns(array("CompanyName"));
            $select->where("CompanyId=$companyId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $companyName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->companyName = $companyName['CompanyName'];
            $this->_view->companyId = $companyId;

            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;
            return $this->_view;
        }
    }
    public function glaccountdetAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($FYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();
                $arg_iAccId = $this->bsf->isNullCheck($postData['accountId'], 'number');
                $arg_iAccTypeId = $this->bsf->isNullCheck($postData['typeId'], 'number');
                $accountName= $this->bsf->isNullCheck($postData['accountName'], 'string');
                $fromDate = $this->bsf->isNullCheck($postData['fromDate'], 'string');
                $toDate = $this->bsf->isNullCheck($postData['toDate'], 'string');

                $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
                $toDateFormatted=date('d/M/Y',strtotime($toDate));
                $sBetween='';
                $BranchId=0;

                $select = $sql->select();
                $select->from(array("a" => "FA_SLAccountType"))
                    ->columns(array("rowCount"=>new Expression("Count(SLTypeId)")))
                    ->where("TypeId =$arg_iAccTypeId");
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $slAccounTypeList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $iCount = $slAccounTypeList['rowCount'];

                $select = $sql->select();
                if ($iCount != 0) {
                    //first sai Converted
                    if ($arg_iAccTypeId == 3 || $arg_iAccTypeId == 16 || $arg_iAccTypeId == 27){
                        $select->columns(array(new expression("SELECTDESC A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) SLName, [O/B] as OB,
                                                Debit,Credit,[O/B]+Debit-Credit [CB],ISNULL(RG.ResourceGroupName,'') [Group],VType=''
                                                FROM (
                                                SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit  FROM (
                                                SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM (
                                                SELECT ET.SubLedgerId,ET.AccountId,
                                                SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit,
                                                SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit
                                                FROM FA_EntryTrans ET WHERE ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                                AND ET.CompanyId=$companyId AND ET.AccountId=$arg_iAccId
                                                GROUP BY ET.SubLedgerId,ET.AccountId) A1
                                                UNION ALL
                                                SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0
                                                FROM FA_CCAccount B WHERE B.OpeningBalance<>0 AND B.ParentAccountId=$arg_iAccId AND B.CompanyId = $companyId $sBetween) A GROUP BY A.AccountId,A.SLId ) A
                                                LEFT JOIN FA_SubLedgerMaster SL ON SL.SubLedgerId=A.SLId
                                                LEFT JOIN Proj_Resource RR ON RR.ResourceId=SL.RefId AND SL.SubLedgerTypeId=6
                                                LEFT JOIN Proj_ResourceGroup RG ON RG.ResourceGroupId=RR.ResourceGroupId
                                                INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId
                                                WHERE AM.AccountType NOT IN ('BA','CA')ORDER BY SLName")));
                        /*
                         *  sSql = String.Format("SELECT A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) SLName, [O/B]," +
                                                                        "Debit,Credit,[O/B]+Debit-Credit [C/B],ISNULL(RG.Resource_Group_Name,'') [Group],ISNULL(RS.Resource_SubGroup_Name,'') [SubGroup],VType='' FROM  " +
                                                                        "(SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit  FROM " +
                                                                        "(SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM  " +
                                                                        "(SELECT ET.SubLedgerId,ET.AccountId," +
                                                                        "SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit, " +
                                                                        "SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit " +
                                                                        "FROM EntryTrans ET WITH (READPAST) WHERE ET.VoucherDate<='03-Jun-2017' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                   AND ET.CompanyId={1} AND ET.AccountId={5} " +
                                                                        "GROUP BY ET.SubLedgerId,ET.AccountId) A1 " +
                                                                        "UNION ALL " +
                                                                        "SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0 " +
                                                                        "FROM SLAccount B WITH (READPAST) WHERE B.OpeningBalance<>0 AND B.ParentAccountId={5} AND B.CompanyId = {1} {4}) A GROUP BY A.AccountId,A.SLId ) A " +
                                                                        "LEFT JOIN [{2}].dbo.SubLedgerMaster SL ON SL.SubLedgerId=A.SLId  " +
                                                                        "LEFT JOIN [{3}].dbo.Resource RR ON RR.Resource_ID=SL.RefId AND SL.SubLedgerTypeId=6 " +
                                                                        "LEFT JOIN [{3}].dbo.Resource_SubGroup RS ON RS.Resource_SubGroup_ID=RR.Resource_SubGroup_ID " +
                                                                        "LEFT JOIN [{3}].dbo.Resource_Group RG ON RG.Resource_Group_ID=RR.Resource_Group_ID " +
                                                                        "INNER JOIN [{2}].dbo.AccountMaster AM ON A.AccountId=AM.AccountId " +
                                                                        "WHERE AM.AccountType NOT IN ('BA','CA')ORDER BY SLName",
                                                                        sVDate, BsfGlobal.g_lCompanyId, BsfGlobal.g_sFaDBName, BsfGlobal.g_sRateAnalDBName, sBetween, arg_iAccId
                                                                        );
                         */
                    } else if ($arg_iAccTypeId == 18){ // For Assert not working
                        $select->columns(array(new expression("SELECTDESC A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) SLName, [O/B] as OB,
                                                        Debit,Credit,[O/B]+Debit-Credit [CB],CASE WHEN SL.SubLedgerTypeId=5 THEN ISNULL(RG.ResourceGroupName,'') ELSE ISNULL(RG1.ResourceGroupName,'') END [Group],
                                                        CASE WHEN SL.SubLedgerTypeId=5 THEN ISNULL(RR.ResourceName,'') ELSE ISNULL(RS1.ResourceGroupName,'') END [SubGroup],VType=''  FROM
                                                        (SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit  FROM
                                                        (SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM
                                                        (SELECT ET.SubLedgerId,ET.AccountId,
                                                        SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit,
                                                        SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit
                                                        FROM FA_EntryTrans ET WHERE ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                                        AND  ET.CompanyId=$companyId AND ET.AccountId=$arg_iAccId
                                                        GROUP BY ET.SubLedgerId,ET.AccountId) A1
                                                        UNION ALL
                                                        SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0
                                                        FROM FA_CCAccount B  WHERE B.OpeningBalance<>0 AND B.ParentAccountId=$arg_iAccId AND B.CompanyId = $companyId $sBetween) A GROUP BY A.AccountId,A.SLId ) A
                                                        LEFT JOIN FA_SubLedgerMaster SL ON SL.SubLedgerId=A.SLId
                                                        LEFT JOIN Asset_SubAssetItem AI ON AI.SubAssetItemId=SL.RefId AND SL.SubLedgerTypeId=5
                                                        LEFT JOIN Proj_Resource RR ON RR.ResourceId=AI.ComponentId
                                                        LEFT JOIN Proj_ResourceGroup RG ON RG.ResourceGroupId=RR.ResourceGroupId
                                                        LEFT JOIN Proj_Resource RR1 ON RR1.ResourceId=SL.RefId AND SL.SubLedgerTypeId=6
                                                        LEFT JOIN Proj_ResourceGroup RG1 ON RG1.ResourceGroupId=RR1.ResourceGroupId
                                                        INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId
                                                        WHERE AM.AccountType NOT IN ('BA','CA') ORDER BY SLName")));
                        /*
                         * sSql = String.Format("SELECT A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) SLName, [O/B]," +
                                                                        "Debit,Credit,[O/B]+Debit-Credit [C/B],CASE WHEN SL.SubLedgerTypeId=5 THEN ISNULL(RG.Resource_Group_Name,'') ELSE ISNULL(RG1.Resource_Group_Name,'') END [Group]," +
                                                                        "CASE WHEN SL.SubLedgerTypeId=5 THEN ISNULL(RR.Resource_Name,'') ELSE ISNULL(RS1.Resource_SubGroup_Name,'') END [SubGroup],VType=''  FROM  " +
                                                                        "(SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit  FROM " +
                                                                        "(SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM  " +
                                                                        "(SELECT ET.SubLedgerId,ET.AccountId," +
                                                                        "SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit, " +
                                                                        "SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit " +
                                                                        "FROM EntryTrans ET WITH (READPAST) WHERE ET.VoucherDate<='03-Jun-2017' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                  AND  ET.CompanyId={1} AND ET.AccountId={5} " +
                                                                        "GROUP BY ET.SubLedgerId,ET.AccountId) A1 " +
                                                                        "UNION ALL " +
                                                                        "SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0 " +
                                                                        "FROM SLAccount B WITH (READPAST) WHERE B.OpeningBalance<>0 AND B.ParentAccountId={5} AND B.CompanyId = {1} {4}) A GROUP BY A.AccountId,A.SLId ) A " +
                                                                        "LEFT JOIN [{2}].dbo.SubLedgerMaster SL ON SL.SubLedgerId=A.SLId  " +
                                                                        "LEFT JOIN [{6}].dbo.SubAssetItem AI ON AI.SubAssetItemId=SL.RefId AND SL.SubLedgerTypeId=5 " +
                                                                        "LEFT JOIN [{3}].dbo.Resource RR ON RR.Resource_ID=AI.ComponentId " +
                                                                        "LEFT JOIN [{3}].dbo.Resource_Group RG ON RG.Resource_Group_ID=RR.Resource_Group_ID " +
                                                                        "LEFT JOIN [{3}].dbo.Resource RR1 ON RR1.Resource_ID=SL.RefId AND SL.SubLedgerTypeId=6 " +
                                                                        "LEFT JOIN [{3}].dbo.Resource_SubGroup RS1 ON RS1.Resource_SubGroup_ID=RR1.Resource_SubGroup_ID " +
                                                                        "LEFT JOIN [{3}].dbo.Resource_Group RG1 ON RG1.Resource_Group_ID=RR1.Resource_Group_ID " +
                                                                        "INNER JOIN [{2}].dbo.AccountMaster AM ON A.AccountId=AM.AccountId " +
                                                                        "WHERE AM.AccountType NOT IN ('BA','CA') ORDER BY SLName",
                                                                        sVDate, BsfGlobal.g_lCompanyId, BsfGlobal.g_sFaDBName, BsfGlobal.g_sRateAnalDBName, sBetween, arg_iAccId, BsfGlobal.g_sAssetDBName);
                         */
                    } else if ($arg_iAccTypeId == 40) {
                        $select->columns(array(new expression("SELECTDESC A.AccountId, AM.AccountName,A.SLId, LTRIM(SL.SubLedgerName)+ ' ' + ISNULL(CCSL.Remarks,'') SLName,[O/B] as OB,
                                                    Debit,Credit,[O/B]+Debit-Credit [CB],CASE WHEN SL.SubLedgerGroupId=0 THEN '(None)' ELSE SL.SubLedgerName END [Group],'' [SubGroup],VType='' FROM
                                                    (SELECT A.AccountId,A.SLId,SUM(OB) [O/B], Sum(Debit) Debit, Sum(Credit)Credit FROM
                                                    (SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM
                                                    (SELECT ET.SubLedgerId,ET.AccountId,SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit,
                                                    SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit FROM FA_EntryTrans ET WHERE ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                                    /*-- AND ET.CostCentreId IN (SELECT CCT.CostCentreId FROM WF_CostCentreTrans CCT
                                                    -- INNER JOIN WF_CostCentre CC ON CC.CostCentreId=CCT.CostCentreId
                                                    -- WHERE CC.BO=1 OR CC.BranchId=$BranchId)*/
                                                     AND ET.CompanyId=$companyId AND ET.AccountId=$arg_iAccId GROUP BY ET.SubLedgerId,ET.AccountId) A1
                                                    UNION ALL
                                                    SELECT B.ParentAccountId AccountId, B.SubLedgerId, B.OpeningBalance,0,0 FROM FA_CCAccount B WHERE B.OpeningBalance<>0 AND B.CompanyId =$companyId AND B.ParentAccountId=$arg_iAccId
                                                    /*--AND B.CostCentreId IN (SELECT CCT.CostCentreId FROM WF_CostCentreTrans CCT
                                                    --INNER JOIN WF_CostCentre CC ON CC.CostCentreId=CCT.CostCentreId
                                                    --WHERE CC.BO=1 OR CC.BranchId=$BranchId)*/
                                                    $sBetween) A GROUP BY A.AccountId,A.SLId ) A
                                                    INNER JOIN FA_SubLedgerMaster SL ON SL.SubLedgerId=A.SLId
                                                    INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId
                                                    LEFT JOIN FA_CMSLDet CCSL ON SL.SubLedgerId=CCSL.SLId AND CCSL.CompanyId=$companyId
                                                    --LEFT JOIN FA_FIGroupDet VM ON SL.SubLedgerGroupId=VM.FIGroupId AND SL.SubLedgerTypeId=13
                                                    WHERE AM.AccountType NOT IN ('BA','CA') AND SL.SubLedgerTypeId=13")));
                        /*
                         * sSql = String.Format("SELECT A.AccountId, AM.AccountName,A.SLId, LTRIM(SL.SubLedgerName)+ ' ' + ISNULL(CCSL.Remarks,'') SLName,[O/B], " +
                                                                        "Debit,Credit,[O/B]+Debit-Credit [C/B],CASE WHEN SL.SubLedgerGroupId=0 THEN '(None)' ELSE VM.FIGroupName END [Group],'' [SubGroup],VType='' FROM " +
                                                                        "(SELECT A.AccountId,A.SLId,SUM(OB) [O/B], Sum(Debit) Debit, Sum(Credit)Credit FROM " +
                                                                        "(SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM " +
                                                                        "(SELECT ET.SubLedgerId,ET.AccountId,SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit, " +
                                                                        "SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit FROM EntryTrans ET WITH (READPAST) WHERE ET.VoucherDate<='03-Jun-2017' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                  " +
                                                                        "AND ET.CostCentreId IN (SELECT CCT.CostCentreId FROM dbo.CostCentreTrans CCT " +
                                                                        "INNER JOIN [{1}].dbo.CostCentre CC ON CC.CostCentreId=CCT.CostCentreId " +
                                                                        "WHERE CC.BO=1 OR CC.BranchId={2}) AND ET.CompanyId={3} AND ET.AccountId={7} GROUP BY ET.SubLedgerId,ET.AccountId) A1 " +
                                                                        "UNION ALL " +
                                                                        "SELECT B.ParentAccountId AccountId, B.SubLedgerId, B.OpeningBalance,0,0 FROM CCAccount B WHERE B.OpeningBalance<>0 AND B.CompanyId ={3} AND B.ParentAccountId={7} " +
                                                                        "AND B.CostCentreId IN (SELECT CCT.CostCentreId FROM dbo.CostCentreTrans CCT " +
                                                                        "INNER JOIN [{1}].dbo.CostCentre CC ON CC.CostCentreId=CCT.CostCentreId " +
                                                                        "WHERE CC.BO=1 OR CC.BranchId={2}) {6}) A GROUP BY A.AccountId,A.SLId ) A " +
                                                                        "INNER JOIN [{5}].dbo.SubLedgerMaster SL ON SL.SubLedgerId=A.SLId " +
                                                                        "INNER JOIN [{5}].dbo.AccountMaster AM ON A.AccountId=AM.AccountId " +
                                                                        "LEFT JOIN [{5}].dbo.CMSLDet CCSL ON SL.SubLedgerId=CCSL.SLId AND CCSL.CompanyId={3} " +
                                                                        "LEFT JOIN [{5}].dbo.FIGroupDet VM ON SL.SubLedgerGroupId=VM.FIGroupId AND SL.SubLedgerTypeId=13 " +
                                                                        "WHERE AM.AccountType NOT IN ('BA','CA') ",
                                                                        sVDate, BsfGlobal.g_sWorkFlowDBName, LedgerPayRecBL.BranchId, BsfGlobal.g_lCompanyId, sCCName, BsfGlobal.g_sFaDBName, sBetween, arg_iAccId, BsfGlobal.g_sVendorDBName
                                                                        );
                         */

                    } else {
                        $select->columns(array(new expression("SELECTDESC A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) + ' ' + ISNULL(CCSL.Remarks,'') SLName, [O/B] as OB,
                                                    Debit, Credit, [O/B]+Debit-Credit [CB],'' [Group],'' [SubGroup],VType=CASE WHEN ISNULL(VM.SSI,0)=0 THEN '(All)' ELSE 'SSI' END FROM
                                                    (SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit FROM
                                                    (SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM
                                                    (SELECT ET.SubLedgerId,ET.AccountId,
                                                    SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit,
                                                    SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit
                                                    FROM FA_EntryTrans ET  WHERE ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                                                    AND  ET.CompanyId=$companyId AND ET.AccountId=$arg_iAccId
                                                    GROUP BY ET.SubLedgerId,ET.AccountId) A1
                                                    UNION ALL
                                                    SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0
                                                    FROM FA_CCAccount B  WHERE B.OpeningBalance<>0 AND B.ParentAccountId=$arg_iAccId AND B.CompanyId = $companyId $sBetween) A GROUP BY A.AccountId,A.SLId ) A
                                                    LEFT JOIN FA_SubLedgerMaster SL ON SL.SubLedgerId=A.SLId
                                                    INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId
                                                    LEFT JOIN FA_CMSLDet CCSL ON SL.SubLedgerId=CCSL.SLId AND CCSL.CompanyId=$companyId
                                                    LEFT JOIN Vendor_Master VM ON SL.RefId=VM.VendorId AND SL.SubLedgerTypeId=1
                                                    WHERE AM.AccountType NOT IN ('BA','CA') ORDER BY SLName")));

                        /*
                         *  sSql = String.Format("SELECT A.AccountId, AM.AccountName,A.SLId,LTRIM(SL.SubLedgerName) + ' ' + ISNULL(CCSL.Remarks,'') SLName, [O/B]," +
                                                        "Debit, Credit, [O/B]+Debit-Credit [C/B],'' [Group],'' [SubGroup],VType=CASE WHEN ISNULL(VM.SSI,0)=0 THEN '(All)' ELSE 'SSI' END FROM  " +
                                                        "(SELECT A.AccountId,A.SLId,SUM(OB) [O/B],Sum(Debit) Debit, Sum(Credit)Credit FROM " +
                                                        "(SELECT A1.AccountId,A1.SubLedgerId SLId,0 OB,Debit,Credit FROM  " +
                                                        "(SELECT ET.SubLedgerId,ET.AccountId," +
                                                        "SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit, " +
                                                        "SUM(CASE WHEN ET.TransType='C' THEN ET.Amount ELSE 0 END) Credit " +
                                                        "FROM FA_EntryTrans ET WITH (READPAST) WHERE ET.VoucherDate<='03-Jun-2017' AND ET.Approve='Y' AND ET.[PDC/Cancel]=0
                  AND  ET.CompanyId=$companyId AND ET.AccountId=$AccId " +
                                                        "GROUP BY ET.SubLedgerId,ET.AccountId) A1 " +
                                                        "UNION ALL " +
                                                        "SELECT B.ParentAccountId AccountId, B.SubLedgerId,B.OpeningBalance,0,0 " +
                                                        "FROM FA_CCAccount B WITH (READPAST) WHERE B.OpeningBalance<>0 AND B.ParentAccountId=$AccId AND B.CompanyId = {1} {3}) A GROUP BY A.AccountId,A.SLId ) A " +
                                                        "INNER JOIN FA_SubLedgerMaster SL ON SL.SubLedgerId=A.SLId " +
                                                        "INNER JOIN FA_AccountMaster AM ON A.AccountId=AM.AccountId " +
                                                        "LEFT JOIN FA_CMSLDet CCSL ON SL.SubLedgerId=CCSL.SLId AND CCSL.CompanyId=$companyId " +
                                                        "LEFT JOIN Vendor_Master VM ON SL.RefId=VM.VendorId AND SL.SubLedgerTypeId=1 " +
                                                        "WHERE AM.AccountType NOT IN ('BA','CA') ORDER BY SLName ",
                                                        sVDate, BsfGlobal.g_lCompanyId, BsfGlobal.g_sFaDBName, sBetween, arg_iAccId, BsfGlobal.g_sVendorDBName);

                         */

                    }
                } else {
                    /*
                     * SELECT [Ref.Info]=ET.VoucherNo + ' ('+ ET.RefType + ' )',ET.AccountId,ET.SubLedgerId, ET.VoucherDate,
    ET.VoucherNo, AM.AccountName, SubLedgerName=LTRIM(SLM.SubLedgerName) + '  ' + ISNULL(CCSL.Remarks,'') ,
    ET.RefType, Debit=(CASE WHEN ET.TransType='D' THEN  ET.Amount ELSE 0 END), Credit=(CASE WHEN ET.TransType='C'
     THEN ET.Amount ELSE 0 END),EM.ChequeNo, ChequeDate=Convert(varchar,ChequeDate,103),
     Narration=ET.Remarks,ET.IRemarks, CC.CostCentreName,Balance=CAST(0 AS Decimal(18,3)),
     RefDate=Convert(varchar,RefDate,103),ET.RefNo,ET.RefAmount FROM FA_EntryTrans ET WITH (READPAST)
     LEFT JOIN FA_EntryMaster EM WITH (READPAST) ON EM.EntryId=ET.RefId AND EM.JournalType=ET.RefType
     INNER JOIN FA_AccountMaster AM ON AM.AccountId=ET.RelatedAccountId
     LEFT JOIN FA_SubLedgerMaster SLM ON SLM.SubLedgerId=ET.RelatedSLId
     LEFT JOIN WF_OperationalCostCentre CC ON ET.CostCentreId=CC.CostCentreId
     LEFT JOIN FA_CMSLDet CCSL ON ET.RelatedSLId=CCSL.SLId AND CCSL.CompanyId=1
     WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.VoucherDate<='03-Jun-2017'
     AND ET.CompanyId=1 AND ET.AccountId=157 ORDER BY ET.VoucherDate,ET.VoucherNo

                    SELECT OpeningBalance FROM FA_Account WHERE AccountId=157 AND CompanyId= 1
                     */
                    $selectFina1 = $sql->select();
                    $selectFina1->columns(array(new expression("SELECTDESC  [Ref.Info]=ET.VoucherNo + ' ('+ ET.RefType + ' )',ET.AccountId,ET.SubLedgerId, Format(ET.VoucherDate,'dd-MM-yyyy') VoucherDate,
                            ET.VoucherNo, AM.AccountName, SubLedgerName=LTRIM(SLM.SubLedgerName) + '  ' + ISNULL(CCSL.Remarks,'') ,
                            ET.RefType, Debit=(CASE WHEN ET.TransType='D' THEN  ET.Amount ELSE 0 END), Credit=(CASE WHEN ET.TransType='C'
                             THEN ET.Amount ELSE 0 END),EM.ChequeNo, ChequeDate=Convert(varchar,ChequeDate,103),
                             Narration=ET.Remarks,ET.IRemarks, CC.CostCentreName,Balance=CAST(0 AS Decimal(18,3)),
                             RefDate=Convert(varchar,RefDate,103),ET.RefNo,ET.RefAmount FROM FA_EntryTrans ET WITH (READPAST)
                             LEFT JOIN FA_EntryMaster EM WITH (READPAST) ON EM.EntryId=ET.RefId AND EM.JournalType=ET.RefType
                             INNER JOIN FA_AccountMaster AM ON AM.AccountId=ET.RelatedAccountId
                             LEFT JOIN FA_SubLedgerMaster SLM ON SLM.SubLedgerId=ET.RelatedSLId
                             LEFT JOIN WF_OperationalCostCentre CC ON ET.CostCentreId=CC.CostCentreId
                             LEFT JOIN FA_CMSLDet CCSL ON ET.RelatedSLId=CCSL.SLId AND CCSL.CompanyId=$companyId
                             WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
                             AND ET.CompanyId=$companyId AND ET.AccountId=$arg_iAccId")));

                    $selectFill = $sql->select();
                    $selectFill->from(array("a"=>"FA_Account"))
                        ->columns(array('Ref.Info' => new Expression("''"), 'AccountId' => new Expression("1-1"), 'SubLedgerId' => new Expression("1-1")
                        ,'VoucherDate' => new Expression("''"),'VoucherNo' => new Expression("''")
                        ,'AccountName'=> new Expression("'Opening Balance'") ,'SubLedgerName'=> new Expression("''"),'RefType'=> new Expression("''")
                        ,'Debit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN SUM(ISNULL(OpeningBalance,0)) ELSE 0 END")
                        ,'Credit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN 0 ELSE Abs(SUM(ISNULL(OpeningBalance,0))) END")
                        ,'ChequeNo'=> new Expression("''"), 'ChequeDate' => new Expression("''")
                        ,'Narration'=> new Expression("''"),'IRemarks'=> new Expression("''"),'CostCentreName'=> new Expression("''")
                        ,'Balance'=> new Expression("CAST(0 AS Decimal(18,3))"),'RefDate'=> new Expression("''"),'RefNo'=> new Expression("''"),'RefAmount'=> new Expression("1-1")));
                    $selectFill->where("AccountId=$arg_iAccId AND CompanyId=$companyId");
                    $selectFill->combine($selectFina1, 'Union ALL');

                    $select->from(array("g"=>$selectFill))
                        ->columns(array("*"));
                    $select->order(array("g.VoucherDate", "g.VoucherNo"));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $statementFinal = preg_replace('/SELECTDESC/', '', $statement, 1);
                $accDetails= $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->accDetails=$accDetails;

                $this->_view->iCount=$iCount;
                $this->_view->accountName=$accountName;
            }
            $this->_view->setTerminal(true);
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
            //$response->setContent(json_encode($resp));
            //return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            return $this->_view;
        }
    }

    public function slanalysisrptAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate = date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate = date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if ($FYearId == 0 || $companyId == 0) {
            $this->redirect()->toRoute("fa/default", array("controller" => "index", "action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'), 'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'), 'string');

        if ($fromDate == "") {
            $fromDate = $fiscalfromDate;
        }
        if ($toDate == "") {
            $toDate = $fiscaltoDate;
        }

        if ($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postData = $request->getPost();

            }
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $select = $sql->select();

            $selectA= $sql->select();
            $selectA->from(array("ET"=>"FA_EntryTrans"))
                ->columns(array("SubLedgerTypeId"=>new Expression("ET.SubLedgerTypeId")
                ,"Debit"=>new Expression("SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END)")
                ,"Credit"=>new Expression("SUM(CASE WHEN ET.TransType='C' THEN ABS(ET.Amount) ELSE 0 END)")));
            $selectA->where("ET.Approve='Y' AND ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted'  AND ET.CompanyId=$companyId");
            $selectA->group("SubLedgerTypeId");

            $selectB = $sql->select();
            $selectB->from(array("SL"=>"FA_CCAccount"))
                ->columns(array("SubLedgerTypeId"=>new Expression("SLT.SLTypeId")
                ,"Debit"=>new Expression("CASE WHEN Sum(SL.OpeningBalance)>0 THEN Sum(SL.OpeningBalance) ELSE 0 END")
                ,"Credit"=>new Expression("CASE WHEN Sum(SL.OpeningBalance)<0 THEN ABS(Sum(SL.OpeningBalance)) ELSE 0 END")));
            $selectB->join(array("A"=>"FA_Account"),"SL.ParentAccountId=A.AccountId AND A.CompanyId=SL.CompanyId",array(),$selectB::JOIN_INNER)
                ->join(array("AM"=>"FA_AccountMaster"),"A.AccountId=AM.AccountId",array(),$selectB::JOIN_INNER)
                ->join(array("SLT"=>"FA_SLAccountType"),"SLT.TypeId=AM.TypeId",array(),$selectB::JOIN_INNER)
                ->join(array("SLM"=>"FA_SubLedgerMaster"),"SLM.SubLedgerTypeId=SLT.SLTypeId AND SL.SubLedgerId=SLM.SubLedgerId",array(),$selectB::JOIN_INNER);
            $selectB->where(array("LastLevel='Y' AND SLTypeId<>0 AND SL.CompanyId=$companyId "));
            $selectB->group("SLT.SLTypeId");
            $selectB->combine($selectA,'Union ALL');

            $selectSub = $sql->select();
            $selectSub->from(array("A"=>$selectB))
                ->columns(array("SLTypeId"=>new Expression("A.SubLedgerTypeId"),"Debit"=>new Expression("SUM(Debit)"),"Credit"=>new Expression("SUM(credit)")))
                ->group(array("A.SubLedgerTypeId"));

            $select = $sql->select();
            $select->from(array("A"=>$selectSub))
                ->join(array("B"=>"FA_SubLedgerType"),"B.SubLedgerTypeId=A.SLTypeId",array(),$select::JOIN_RIGHT)
                ->columns(array("SLTypeId"=>new Expression("B.SubLedgerTypeId"),'SLTypeName'=>new Expression("B.SubLedgerTypeName")
                ,"Debit"=>new Expression("CASE WHEN Debit-Credit>=0 THEN Debit-Credit ELSE 0 END ")
                ,"Credit"=>new Expression("CASE WHEN (Debit-Credit<0 ) THEN ABS(Debit-Credit) ELSE 0 END")))
                ->order(array("B.SubLedgerTypeName"));

            $statement = $sql->getSqlStringForSqlObject($select);
            $slanalysisList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->slanalysisList = $slanalysisList;

            $select = $sql->select();
            $select->from(array("a" => "WF_CompanyMaster"))
                ->columns(array("CompanyName"));
            $select->where("CompanyId=$companyId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $companyName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->companyName = $companyName['CompanyName'];
            $this->_view->companyId = $companyId;

            $this->_view->fromDate = $fromDate;
            $this->_view->toDate = $toDate;
            $this->_view->fiscalfromDate = $fiscalfromDate;
            $this->_view->fiscaltoDate = $fiscaltoDate;
            return $this->_view;


            /*subLedger Detail
             *
SELECT A.SLTypeId,A.SubLedgerId SLId,B.SubLedgerName + ' ' + ISNULL(CCSL.Remarks,'')  SLName,
 CASE WHEN Debit-Credit>=0  THEN Debit-Credit ELSE 0 END Debit,CASE WHEN (Debit-Credit<0 )
 THEN ABS(Debit-Credit) ELSE 0 END Credit,VType=CASE WHEN ISNULL(VM.SSI,0)=0 THEN '(All)' ELSE 'SSI' END,
 CASE WHEN B.SubLedgerGroupId=0 THEN '(None)' ELSE '' END [Group] FROM (
 SELECT A.SubLedgerTypeId SLTypeId ,A.SubLedgerId, SUM(Debit) Debit, SUM(credit) Credit FROM (
 SELECT ET.SubLedgerTypeId,ET.SubLedgerId,SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END) Debit,
  SUM(CASE WHEN ET.TransType='C' THEN ABS(ET.Amount) ELSE 0 END) Credit FROM FA_EntryTrans ET WITH (READPAST)
  INNER JOIN FA_AccountMaster AM ON AM.AccountId=ET.AccountId
  WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.SubLedgerTypeId=5 AND
  ET.VoucherDate<='05/Jun/2017' AND ET.CompanyId=1
  GROUP BY SubLedgerTypeId,SubLedgerId
  UNION ALL
  SELECT SLT.SLTypeId,SLM.SubLedgerId,CASE WHEN Sum(SL.OpeningBalance)>0 THEN Sum(SL.OpeningBalance) ELSE 0 END
    Debit,  CASE WHEN Sum(SL.OpeningBalance)<0 THEN ABS(Sum(SL.OpeningBalance)) ELSE 0 END  Credit
	FROM FA_CCAccount SL
	INNER JOIN FA_Account A ON SL.ParentAccountId=A.AccountId  AND A.CompanyId=SL.CompanyId
	INNER JOIN  FA_AccountMaster AM ON A.AccountId=AM.AccountId
	INNER JOIN  FA_SLAccountType SLT ON SLT.TypeId=AM.TypeId
	INNER JOIN  FA_SubLedgerMaster SLM ON SLM.SubLedgerTypeId=SLT.SLTypeId
	AND SLM.SubLedgerId=SL.SubLedgerId
	WHERE  LastLevel='Y' AND SLTypeId=5 AND SL.CompanyId=1
	GROUP BY SLT.SLTypeId, SLM.SubLedgerId) A GROUP BY A.SubLedgerTypeId,A.SubLedgerId) A
	INNER JOIN  FA_SubLedgerMaster B ON B.SubLedgerId=A.SubLedgerId
	LEFT JOIN Vendor_Master VM ON B.RefId=VM.VendorId AND B.SubLedgerTypeId=1
	LEFT JOIN CB_ClientMaster CM ON B.RefId=CM.ClientId AND B.SubLedgerTypeId=2
	LEFT JOIN FA_CMSLDet CCSL ON B.SubLedgerId=CCSL.SLId AND CCSL.CompanyId=1
	Where B.SubLedgerTypeId=13
	ORDER BY B.SubLedgerName
             */
            /* Bill Transaction detail
             * select *from (
	SELECT [Ref.Info]=ET.VoucherNo + ' ('+ ET.RefType + ' )',  ET.AccountId, ET.SubLedgerTypeId SLTypeId,
	ET.VoucherDate,ET.VoucherNo,ET.SubLedgerId SLId,ET.RelatedSLId,AM1.AccountName,AM.AccountName
	RelatedAccount,SubLedger=SLM.SubLedgerName,RelatedSubLedger=SLM1.SubLedgerName,
	 RefType, EM.ChequeNo,ChequeDate=Convert(varchar,ChequeDate,103),Narration=ET.Remarks,
	 ET.IRemarks,CC.CostCentreName CostCentre, CASE WHEN ET.TransType='D' THEN Abs(ET.Amount) ELSE 0 END Debit,
	  CASE WHEN ET.TransType='C' THEN Abs(ET.Amount) ELSE 0 END Credit, Balance=CAST(0 AS decimal(18,3)),
	   RefDate=Convert(varchar,ET.RefDate,103),ET.RefNo,ET.RefAmount FROM FA_EntryTrans ET WITH (READPAST)
	   LEFT JOIN FA_EntryMaster EM WITH (READPAST) ON ET.RefId=EM.EntryId AND RefType=JournalType
	   LEFT JOIN FA_AccountMaster AM WITH (READPAST) ON ET.AccountId=AM.AccountId
	   LEFT JOIN FA_AccountMaster AM1 WITH (READPAST) ON ET.RelatedAccountId=AM1.AccountId
	   LEFT JOIN FA_SubLedgerMaster SLM WITH (READPAST) ON ET.SubLedgerId=SLM.SubLedgerId
	   LEFT JOIN FA_SubLedgerMaster SLM1 WITH (READPAST) ON ET.RelatedSLId=SLM1.SubLedgerId
	   LEFT JOIN WF_OperationalCostCentre CC ON CC.CostCentreId=ET.CostCentreId
	   WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.VoucherDate<='05/Jun/2017' AND ET.CompanyId=1
	   AND ET.SubLedgerTypeId=6 AND ET.SubLedgerId=9645
	   --ORDER BY ET.VoucherDate,ET.RefId,ET.VoucherNo
	   union all

	   SELECT '' [Ref.Info],0 AccountId,0 SLTypeId, '' VoucherDate,
'' VoucherNo,0 SLId,0 RelatedSLId, 'Opening Balance' AccountName, '' RelatedAccount,
'' SubLedger,'' RelatedSubLedger,'' RefType,'' ChequeNo,'' ChequeDate,
 '' Narration,'' IRemarks,'' CostCentreName
, CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN SUM(ISNULL(OpeningBalance,0)) ELSE 0 END Debit
, CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN 0 ELSE Abs(SUM(ISNULL(OpeningBalance,0))) END Credit,
   Balance=CAST(0 AS Decimal(18,3)),
    '' RefDate,'' RefNo,0 RefAmount FROM FA_CCAccount
	   WHERE  SubLedgerId=9645 AND CompanyId= 1 AND ParentAccountId IN (
	   SELECT SL.ParentAccountId FROM FA_CCAccount SL
	   INNER JOIN FA_SubLedgerMaster SLM ON SL.SubLedgerId=SLM.SubLedgerId
	   INNER JOIN FA_AccountMaster AM ON AM.AccountId=SL.ParentAccountId
	   WHERE SLM.SubLedgerId=9645 AND SLM.SubledgerTypeId=6 AND SL.CompanyId=1 )
	   ) g  ORDER BY g.VoucherDate,g.VoucherNo
             */
        }
    }
    public function slanalysistransdetAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if ($FYearId == 0 || $companyId == 0) {
            $this->redirect()->toRoute("fa/default", array("controller" => "index", "action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        if ($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $arg_iSLTypeId= $this->bsf->isNullCheck($postData['SLTypeId'], 'number');
                $SLTypeName= $this->bsf->isNullCheck($postData['SLTypeName'], 'string');
                $fromDate = $this->bsf->isNullCheck($postData['fromDate'], 'string');
                $toDate = $this->bsf->isNullCheck($postData['toDate'], 'string');

                $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
                $toDateFormatted=date('d/M/Y',strtotime($toDate));

                $select1 = $sql->select();
                $select1->from(array("ET"=>"FA_EntryTrans"))
                    ->join(array("AM"=>"FA_AccountMaster"),"AM.AccountId=ET.AccountId",array(),$select1::JOIN_INNER)
                    ->columns(array("SLTypeId"=>new Expression("ET.SubLedgerTypeId"),'SubLedgerId'
                    ,"Debit"=>new Expression("SUM(CASE WHEN ET.TransType='D' THEN ET.Amount ELSE 0 END)")
                    ,"Credit"=>new Expression("SUM(CASE WHEN ET.TransType='C' THEN ABS(ET.Amount) ELSE 0 END)")))
                    ->where("ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.SubLedgerTypeId=$arg_iSLTypeId AND ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.CompanyId=$companyId")
                    ->group(array("SubLedgerTypeId","SubLedgerId"));

                $select2 = $sql->select();
                $select2->from(array("SL"=>"FA_CCAccount"))
                    ->join(array("A"=>"FA_Account"),"SL.ParentAccountId=A.AccountId AND A.CompanyId=SL.CompanyId",array(),$select2::JOIN_INNER)
                    ->join(array("AM"=>"FA_AccountMaster"),"A.AccountId=AM.AccountId",array(),$select2::JOIN_INNER)
                    ->join(array("SLT"=>"FA_SLAccountType"),"SLT.TypeId=AM.TypeId",array(),$select2::JOIN_INNER)
                    ->join(array("SLM"=>"FA_SubLedgerMaster"),"SLM.SubLedgerTypeId=SLT.SLTypeId AND SLM.SubLedgerId=SL.SubLedgerId",array(),$select2::JOIN_INNER)
                    ->columns(array("SLTypeId"=>new Expression("SLT.SLTypeId"),'SubLedgerId'=>new Expression("SLM.SubLedgerId")
                    ,"Debit"=>new Expression("CASE WHEN Sum(SL.OpeningBalance)>0 THEN Sum(SL.OpeningBalance) ELSE 0 END")
                    ,"Credit"=>new Expression("CASE WHEN Sum(SL.OpeningBalance)<0 THEN ABS(Sum(SL.OpeningBalance)) ELSE 0 END")))
                    ->where("LastLevel='Y' AND SLTypeId=$arg_iSLTypeId AND SL.CompanyId=$companyId")
                    ->group(array("SLT.SLTypeId","SLM.SubLedgerId"));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array("A"=>$select2))
                    ->columns(array("SLTypeId",'SubLedgerId'
                    ,"Debit"=>new Expression("SUM(Debit)")
                    ,"Credit"=>new Expression("SUM(credit)")))
                    ->group(array("A.SLTypeId","A.SubLedgerId"));

                $select4 = $sql->select();
                $select4->from(array("A"=>$select3))
                    ->join(array("B"=>"FA_SubLedgerMaster"),"B.SubLedgerId=A.SubLedgerId",array(),$select4::JOIN_INNER)
                    ->join(array("VM"=>"Vendor_Master"),new Expression("B.RefId=VM.VendorId AND B.SubLedgerTypeId=1"),array(),$select4::JOIN_LEFT)
                    ->join(array("CM"=>"CB_ClientMaster"),new Expression("B.RefId=CM.ClientId AND B.SubLedgerTypeId=2"),array(),$select4::JOIN_LEFT)
                    ->join(array("CCSL"=>"FA_CMSLDet"),new Expression("B.SubLedgerId=CCSL.SLId AND CCSL.CompanyId=$companyId"),array(),$select4::JOIN_LEFT)
                    ->columns(array("SLTypeId",'SLId'=>new Expression("A.SubLedgerId"),'SLName'=>new Expression("B.SubLedgerName + ' ' + ISNULL(CCSL.Remarks,'')")
                    ,"Debit"=>new Expression("CASE WHEN Debit-Credit>=0  THEN Debit-Credit ELSE 0 END")
                    ,"Credit"=>new Expression("CASE WHEN (Debit-Credit<0 ) THEN ABS(Debit-Credit) ELSE 0 END")
                    ,"VType"=>new Expression("CASE WHEN ISNULL(VM.SSI,0)=0 THEN '(All)' ELSE 'SSI' END")
                    ,"Group"=>new Expression("CASE WHEN B.SubLedgerGroupId=0 THEN '(None)' ELSE '' END")
                    ))
                    ->order(array("B.SubLedgerName"));

                $statement = $sql->getSqlStringForSqlObject($select4);
                $slTransDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->slTransDet = $slTransDet;

                $this->_view->SLTypeName=$SLTypeName;
            }
            $this->_view->setTerminal(true);
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
           return $this->_view;
        }
    }
    public function slanalysisbilldetAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $companySession = new Container('faCompany');
        $FYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;
        $FYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();

        if ($FYearId == 0 || $companyId == 0) {
            $this->redirect()->toRoute("fa/default", array("controller" => "index", "action" => "accountdirectory"));
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        if ($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $arg_iSLId= $this->bsf->isNullCheck($postData['SLId'], 'number');
                $arg_iSLTypeId= $this->bsf->isNullCheck($postData['SLTypeId'], 'number');
                $SLName= $this->bsf->isNullCheck($postData['SLName'], 'string');
                $fromDate = $this->bsf->isNullCheck($postData['fromDate'], 'string');
                $toDate = $this->bsf->isNullCheck($postData['toDate'], 'string');

                $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
                $toDateFormatted=date('d/M/Y',strtotime($toDate));

                $selectFina1 = $sql->select();
                $selectFina1->columns(array(new expression("SELECTDESC [Ref.Info]=ET.VoucherNo + ' ('+ ET.RefType + ' )',  ET.AccountId, ET.SubLedgerTypeId SLTypeId,
                                                            ET.VoucherDate,ET.VoucherNo,ET.SubLedgerId SLId,ET.RelatedSLId,AM1.AccountName,AM.AccountName
                                                            RelatedAccount,SubLedger=SLM.SubLedgerName,RelatedSubLedger=SLM1.SubLedgerName,
                                                            RefType, EM.ChequeNo,ChequeDate=Convert(varchar,ChequeDate,103),Narration=ET.Remarks,
                                                            ET.IRemarks,CC.CostCentreName CostCentre, CASE WHEN ET.TransType='D' THEN Abs(ET.Amount) ELSE 0 END Debit,
                                                            CASE WHEN ET.TransType='C' THEN Abs(ET.Amount) ELSE 0 END Credit, Balance=CAST(0 AS decimal(18,3)),
                                                            RefDate=Convert(varchar,ET.RefDate,103),ET.RefNo,ET.RefAmount FROM FA_EntryTrans ET
                                                            LEFT JOIN FA_EntryMaster EM ON ET.RefId=EM.EntryId AND RefType=JournalType
                                                            LEFT JOIN FA_AccountMaster AM ON ET.AccountId=AM.AccountId
                                                            LEFT JOIN FA_AccountMaster AM1 ON ET.RelatedAccountId=AM1.AccountId
                                                            LEFT JOIN FA_SubLedgerMaster SLM ON ET.SubLedgerId=SLM.SubLedgerId
                                                            LEFT JOIN FA_SubLedgerMaster SLM1 ON ET.RelatedSLId=SLM1.SubLedgerId
                                                            LEFT JOIN WF_OperationalCostCentre CC ON CC.CostCentreId=ET.CostCentreId
                                                            WHERE ET.Approve='Y' AND ET.[PDC/Cancel]=0 AND ET.VoucherDate BETWEEN '$fromDateFormatted' AND '$toDateFormatted' AND ET.CompanyId=$companyId
                                                            AND ET.SubLedgerTypeId=$arg_iSLTypeId AND ET.SubLedgerId=$arg_iSLId")));

                $selectFill = $sql->select();
                $selectFill->from(array("a"=>"FA_CCAccount"))
                    ->columns(array('Ref.Info' => new Expression("''"), 'AccountId' => new Expression("1-1")
                    , 'SLTypeId' => new Expression("1-1"),'VoucherDate' => new Expression("''")
                    ,'VoucherNo' => new Expression("''"), 'SLId' => new Expression("1-1")
                    ,'RelatedSLId' => new Expression("1-1"),'AccountName'=> new Expression("'Opening Balance'")
                    ,'RelatedAccount'=> new Expression("''"),'SubLedger'=> new Expression("''")
                    ,'RelatedSubLedger'=> new Expression("''"),'RefType'=> new Expression("''")
                    ,'ChequeNo'=> new Expression("''"), 'ChequeDate' => new Expression("''")
                    ,'Narration'=> new Expression("''"),'IRemarks'=> new Expression("''"),'CostCentreName'=> new Expression("''")
                    ,'Debit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN SUM(ISNULL(OpeningBalance,0)) ELSE 0 END")
                    ,'Credit'=> new Expression("CASE WHEN SUM(ISNULL(OpeningBalance,0))>0 THEN 0 ELSE Abs(SUM(ISNULL(OpeningBalance,0))) END")
                    ,'Balance'=> new Expression("CAST(0 AS Decimal(18,3))"),'RefDate'=> new Expression("''")
                    ,'RefNo'=> new Expression("''"),'RefAmount'=> new Expression("1-1")));
                $selectFill->where("SubLedgerId=$arg_iSLId AND CompanyId= $companyId AND ParentAccountId IN
                                            (SELECT SL.ParentAccountId FROM FA_CCAccount SL
                                            INNER JOIN FA_SubLedgerMaster SLM ON SL.SubLedgerId=SLM.SubLedgerId
                                            INNER JOIN FA_AccountMaster AM ON AM.AccountId=SL.ParentAccountId
                                            WHERE SLM.SubLedgerId=$arg_iSLId AND SLM.SubledgerTypeId=$arg_iSLTypeId AND SL.CompanyId=$companyId)");
                $selectFill->combine($selectFina1, 'Union ALL');

                $select = $sql->select();
                $select->from(array("g"=>$selectFill))
                    ->columns(array("*"));
                $select->order(array("g.VoucherDate", "g.VoucherNo"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $statementFinal = preg_replace('/SELECTDESC/', '', $statement, 1);
                $slBillDet= $dbAdapter->query($statementFinal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->slBillDet = $slBillDet;

                $this->_view->SLName=$SLName;
            }
            $this->_view->setTerminal(true);
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {
                    $connection->beginTransaction();

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
           return $this->_view;
        }
    }
    public function summarytbrptAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*$companySession = new Container('faCompany');
        $fYearId = $companySession->fiscalId;
        $companyId = $companySession->companyId;*/
        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
            //$response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
/*
 * SELECT A.AccountId,A.AccountName,A.ParentAccountId,A.LastLevel, OBDebit=ISNULL(B.OBDebit,0),OBCredit=ISNULL
 (B.OBCredit,0),TBDebit=ISNULL(B.TBDebit,0),TBCredit=ISNULL(B.TBCredit,0),CBDebit=
 CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)>0
 THEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0) ELSE 0 END ,
 CBCredit=CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)<0
 THEN ABS(ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)) ELSE 0 END
 ,A.CompanyId FROM FA_AccountList A LEFT JOIN (SELECT B.AccountId, OBDebit=SUM(B.OBDebit),OBCredit=
 SUM(B.OBCredit),TBDebit=SUM(B.TBDebit),TBCredit=SUM(B.TBCredit),B.CompanyId FROM (
 SELECT AccountId, OBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),OBCredit=SUM(CASE WHEN
  TransType='C' THEN Amount ELSE 0 END),0 TBDebit, 0 TBCredit,CompanyId FROM FA_EntryTrans WITH (READPAST)
  WHERE [PDC/Cancel]=0 AND CompanyId=1 AND VoucherDate<'01-Apr-2017' AND Approve='Y'
  AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId
 UNION ALL
 SELECT AccountId, OBDebit=CASE WHEN OpeningBalance>0 THEN OpeningBalance ELSE 0 END,OBCredit=CASE WHEN
 OpeningBalance<0 THEN ABS(OpeningBalance) ELSE 0 END,0 TBDebit, 0 TBCredit,CompanyId FROM FA_Account
 WITH (READPAST) WHERE CompanyId=1 AND OpeningBalance<>0
 UNION ALL
 SELECT AccountId,0 OBDebit, 0 OBCredit, TBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),
  TBCredit=SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END),CompanyId FROM FA_EntryTrans
  WHERE [PDC/Cancel]=0 AND CompanyId=1 AND VoucherDate<='06-Jun-2017' AND Approve='Y'
  AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId ) B
  GROUP BY B.AccountId,B.CompanyId) B ON A.AccountId=B.AccountId AND A.CompanyId=B.CompanyId
  WHERE A.CompanyId=1  ORDER BY A.LevelNo,A.SortId, A.AccountName
 */
            $this->strAccListId="";
            $select = $sql->select();
            $select->from(array("a" => "FA_Account"))
                ->columns(array("AccountId"));
            $select->where("a.CompanyId=$companyId and a.FYearId=$fYearId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $accfilledList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($accfilledList as $accfilledLists) {
                $curAccId=$accfilledLists['AccountId'];
                //$this->AccountList[]=$curAccId;
                FaHelper::GetsuperiorAccList($curAccId, $dbAdapter);
                $this->strAccListId=$this->strAccListId. $curAccId .",";
            }

            if($this->strAccListId != ""){
                $this->strAccListId = rtrim($this->strAccListId,',');
            }
            $accIds=$this->strAccListId;
            if($accIds==""){
                $accIds="0";
            }

            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $select = $sql->select();
            $select->columns(array(new expression("SELECT A.AccountId,A.AccountName,A.ParentAccountId,A.LastLevel, OBDebit=ISNULL(B.OBDebit,0),OBCredit=ISNULL
 (B.OBCredit,0),TBDebit=ISNULL(B.TBDebit,0),TBCredit=ISNULL(B.TBCredit,0),CBDebit=
 CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)>0
 THEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0) ELSE 0 END ,
 CBCredit=CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)<0
 THEN ABS(ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)) ELSE 0 END
 ,A.CompanyId,(1-1) as expanded FROM FA_AccountList A LEFT JOIN (SELECT B.AccountId, OBDebit=SUM(B.OBDebit),OBCredit=
 SUM(B.OBCredit),TBDebit=SUM(B.TBDebit),TBCredit=SUM(B.TBCredit),B.CompanyId FROM (
 SELECT AccountId, OBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),OBCredit=SUM(CASE WHEN
  TransType='C' THEN Amount ELSE 0 END),0 TBDebit, 0 TBCredit,CompanyId FROM FA_EntryTrans WITH (READPAST)
  WHERE [PDC/Cancel]=0 AND CompanyId=$companyId AND VoucherDate<'$fromDateFormatted' AND Approve='Y'
  AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId
 UNION ALL
 SELECT AccountId, OBDebit=CASE WHEN OpeningBalance>0 THEN OpeningBalance ELSE 0 END,OBCredit=CASE WHEN
 OpeningBalance<0 THEN ABS(OpeningBalance) ELSE 0 END,0 TBDebit, 0 TBCredit,CompanyId FROM FA_Account
 WITH (READPAST) WHERE CompanyId=$companyId AND OpeningBalance<>0
 UNION ALL
 SELECT AccountId,0 OBDebit, 0 OBCredit, TBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),
  TBCredit=SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END),CompanyId FROM FA_EntryTrans
  WHERE [PDC/Cancel]=0 AND CompanyId=$companyId AND VoucherDate<='$toDateFormatted' AND Approve='Y'
  AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId ) B
  GROUP BY B.AccountId,B.CompanyId) B ON A.AccountId=B.AccountId AND A.CompanyId=B.CompanyId
  WHERE A.CompanyId=1  ORDER BY A.LevelNo,A.SortId, A.AccountName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
            $statementFinal1 = preg_replace('/AS Expression1/', '', $statementFinal, 1);
            $accDirectoryList = $dbAdapter->query($statementFinal1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $arrUnitLists1= array();
            $irowACCId=0;
            foreach($accDirectoryList as $accDirectoryLists) {
                $irowACCId=$accDirectoryLists['AccountId'];
                //$this->strAccListId="";
                $this->strAccListId=$irowACCId .",";
                if($irowACCId!=0){
                    FaHelper::GetLowLevelAccList($irowACCId, $companyId, $dbAdapter);
                }

                if($this->strAccListId != ""){
                    $this->strAccListId = rtrim($this->strAccListId,',');
                }
                //var_dump($this->AccountList);
                $accIds=$this->strAccListId;
                if($accIds==""){ $accIds="0";}

                $select = $sql->select();
                $select->columns(array(new expression("select sum(g.OBDebit) as OBDebit,sum(g.OBCredit) as OBCredit,sum(g.TBDebit) as TBDebit
                         ,sum(g.TBCredit) as TBCredit,sum(g.CBDebit) as CBDebit ,sum(g.CBCredit)  as CBCredit,(1-1) as expanded from
                         ( SELECT A.AccountId,A.AccountName,A.ParentAccountId,A.LastLevel, OBDebit=ISNULL(B.OBDebit,0),OBCredit=ISNULL
                         (B.OBCredit,0),TBDebit=ISNULL(B.TBDebit,0),TBCredit=ISNULL(B.TBCredit,0),CBDebit=
                         CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)>0
                         THEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0) ELSE 0 END ,
                         CBCredit=CASE WHEN ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)<0
                         THEN ABS(ISNULL(B.OBDebit,0)+ISNULL(B.TBDebit,0)-ISNULL(B.OBCredit,0)-ISNULL(B.TBCredit,0)) ELSE 0 END
                         ,A.CompanyId FROM FA_AccountList A LEFT JOIN (SELECT B.AccountId, OBDebit=SUM(B.OBDebit),OBCredit=
                         SUM(B.OBCredit),TBDebit=SUM(B.TBDebit),TBCredit=SUM(B.TBCredit),B.CompanyId FROM (
                         SELECT AccountId, OBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),OBCredit=SUM(CASE WHEN
                          TransType='C' THEN Amount ELSE 0 END),0 TBDebit, 0 TBCredit,CompanyId FROM FA_EntryTrans WITH (READPAST)
                          WHERE [PDC/Cancel]=0 AND CompanyId=$companyId AND VoucherDate<'$fromDateFormatted' AND Approve='Y' and AccountId in ($accIds)
                          AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId
                         UNION ALL
                         SELECT AccountId, OBDebit=CASE WHEN OpeningBalance>0 THEN OpeningBalance ELSE 0 END,OBCredit=CASE WHEN
                         OpeningBalance<0 THEN ABS(OpeningBalance) ELSE 0 END,0 TBDebit, 0 TBCredit,CompanyId FROM FA_Account
                         WITH (READPAST) WHERE CompanyId=$companyId AND OpeningBalance<>0 and AccountId in ($accIds)
                         UNION ALL
                         SELECT AccountId,0 OBDebit, 0 OBCredit, TBDebit=SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END),
                          TBCredit=SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END),CompanyId FROM FA_EntryTrans
                          WHERE [PDC/Cancel]=0 AND CompanyId=$companyId AND VoucherDate<='$toDateFormatted' AND Approve='Y' and AccountId in ($accIds)
                          AND [PDC/Cancel]=0 GROUP BY AccountId, CompanyId ) B
                          GROUP BY B.AccountId,B.CompanyId) B ON A.AccountId=B.AccountId AND A.CompanyId=B.CompanyId
                          WHERE A.CompanyId=1 and A.AccountId in ($accIds) )g")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $statementFinal = preg_replace('/SELECT/', '', $statement, 1);
                $statementFinalAcc = preg_replace('/AS Expression1/', '', $statementFinal, 1);
                $obAccList = $dbAdapter->query($statementFinalAcc, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $dumArr1 = array();
                if($accDirectoryLists['LastLevel']=="Y"){
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'OBDebit' => $accDirectoryLists['OBDebit'],
                        'OBCredit' => $accDirectoryLists['OBCredit'],
                        'TBDebit' => $accDirectoryLists['TBDebit'],
                        'TBCredit' => $accDirectoryLists['TBCredit'],
                        'CBDebit' => $accDirectoryLists['CBDebit'],
                        'CBCredit' => $accDirectoryLists['CBCredit'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                } else {
                    $dumArr1 = array(
                        'AccountId' => $accDirectoryLists['AccountId'],
                        'AccountName' => $accDirectoryLists['AccountName'],
                        'ParentAccountId' => $accDirectoryLists['ParentAccountId'],
                        'LastLevel' => $accDirectoryLists['LastLevel'],
                        'OBDebit' => $obAccList['OBDebit'],
                        'OBCredit' => $obAccList['OBCredit'],
                        'TBDebit' => $obAccList['TBDebit'],
                        'TBCredit' => $obAccList['TBCredit'],
                        'CBDebit' => $obAccList['CBDebit'],
                        'CBCredit' => $obAccList['CBCredit'],
                        'expanded' => $accDirectoryLists['expanded']
                    );
                }
                $arrUnitLists1[] = $dumArr1;
            }
            $this->_view->accDirectoryList = $arrUnitLists1;

            $select = $sql->select();
            $select->from(array("a" => "WF_CompanyMaster"))
                ->columns(array("CompanyName"));
            $select->where("CompanyId=$companyId");
            $statement = $statement = $sql->getSqlStringForSqlObject($select);
            $companyName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->companyName = $companyName['CompanyName'];

            $this->_view->companyId = $companyId;
            $this->_view->fYearId = $fYearId;
            $this->_view->g_lCNYearId = 0;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
    public function summarytbtransAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $fYearId = CommonHelper::getFA_SessionfiscalId();
        $companyId = CommonHelper::getFA_SessioncompanyId();
        $fiscalfromDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYStartDate(), 'date')));
        $fiscaltoDate =date('d-m-Y', strtotime($this->bsf->isNullCheck(CommonHelper::getFA_SessionFYEndDate(), 'date')));

        if($fYearId==0 || $companyId==0){
            $this->redirect()->toRoute("fa/default", array("controller" => "index","action" => "accountdirectory"));
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Account Directory");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
        $userId = $this->auth->getIdentity()->UserId;
        $connection = $dbAdapter->getDriver()->getConnection();
        $request = $this->getRequest();
        $response = $this->getResponse();

        $acc_Ids= $this->bsf->isNullCheck($this->params()->fromRoute('accIds'),'string');
        $fromDate = $this->bsf->isNullCheck($this->params()->fromRoute('fromDate'),'string');
        $toDate = $this->bsf->isNullCheck($this->params()->fromRoute('toDate'),'string');

        if($fromDate==""){
            $fromDate =$fiscalfromDate;
        }
        if($toDate==""){
            $toDate =$fiscaltoDate;
        }

        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postData = $request->getPost();

            }
//            $this->_view->setTerminal(true);
            //$response->setContent(json_encode($result));
            return $response;
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                try {

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            $fromDateFormatted=date('d/M/Y',strtotime($fromDate));
            $toDateFormatted=date('d/M/Y',strtotime($toDate));

            $this->_view->companyId = $companyId;
            $this->_view->fYearId = $fYearId;
            $this->_view->fromDate=$fromDate;
            $this->_view->toDate=$toDate;
            $this->_view->fiscalfromDate=$fiscalfromDate;
            $this->_view->fiscaltoDate=$fiscaltoDate;

            $acc_Ids=explode(",",$acc_Ids);
            $this->_view->acc_Ids=$acc_Ids;

            $accountId=9;
            $sAccListIds="";
            $statement = "exec FA_Get_Account_Hierarchy_Child @AccountId= " .$accountId;
            $accListDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($accListDet as $accListDets) {
                $sAccListIds=$sAccListIds. $accListDets['AccountId'] .",";
            }
            if($sAccListIds != ""){
                $sAccListIds = rtrim($sAccListIds,',');
            }
            if($sAccListIds==""){ $sAccListIds="0";}


            for($i=0; $i<12; $i++) {
                if($i==0){
                    $select = $sql->select();
                    $select->from(array("A"=>"FA_Account"))
                        ->columns(array("OpeningBalance"=> new Expression("SUM(ISNULL(OpeningBalance,0))")));
                    $select->where(" AccountId IN ($sAccListIds) AND CompanyId=$companyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $obAccList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $dOB=$obAccList['OpeningBalance'];
                }
                $tDate = date('Y-m-d', strtotime("+". $i ." month", strtotime($fiscalfromDate)));
                $tMonth = date('M', strtotime($tDate));
                $iMonth = date('m', strtotime($tDate));
                $tYear = date('Y', strtotime($tDate));

                $monthText = $tMonth . ', '. $tYear;

                $select3 = $sql->select();
                $select3->from(array("ET"=>"FA_EntryTrans"))
                    ->columns(array('Year' => new Expression("DATENAME(YEAR,VoucherDate)")
                    , 'Month' => new Expression("REPLACE(CONVERT(VARCHAR(4),VoucherDate,100),'-','')")
                    , 'MonthName' => new Expression("REPLACE(CONVERT(VARCHAR(4),VoucherDate,100),'-','')+DATENAME(YEAR,VoucherDate)")
                    , 'Mnth' => new Expression("RIGHT('0000'+CAST(DATENAME(YEAR,Voucherdate)AS Varchar),4)+ RIGHT('00'+CAST(MONTH(VoucherDate) AS Varchar),2)")
                    , 'OpeningBalance' => new Expression("CAST (0 AS decimal(18,3))")
                    , 'MonthNo' => new Expression("DATEPART(MONTH,Voucherdate)")
                    , 'Debit' => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE 0 END )")
                    , 'Credit' => new Expression("SUM(CASE WHEN TransType='C' THEN Amount ELSE 0 END )")
                    , 'Net' => new Expression("SUM(CASE WHEN TransType='D' THEN Amount ELSE -Amount END )")
                    ));
                $select3->where("[PDC/Cancel]=0 AND ET.AccountId IN ($sAccListIds) AND ET.Approve='Y' And
                ET.CompanyId=$companyId and DATEPART(MONTH,Voucherdate)=$iMonth");
                $select3->group(new Expression("DATENAME(YEAR,Voucherdate),REPLACE(CONVERT(VARCHAR(4),Voucherdate,100),'-',''), DATEPART(MONTH,VoucherDate)"));
                $select3 ->order("Mnth");
                $statement = $statement = $sql->getSqlStringForSqlObject($select3);
                $accIndividet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $dumArr=array();
                if(count($accIndividet)>0){
                    $dCB= $dOB +$accIndividet['Net'];
                    $dumArr = array(
                        'Year' => $accIndividet['Year'],
                        'Month' => $accIndividet['Month'],
                        'MonthName' => $accIndividet['MonthName'],
                        'Mnth' => $accIndividet['Mnth'],
                        'MonthNo' => $accIndividet['MonthNo'],
                        'OpeningBalance' => $dOB,
                        'Debit' => $accIndividet['Debit'],
                        'Credit' => $accIndividet['Credit'],
                        'Net' => $dCB,
                    );
                    $dOB = 0;
                }
                $arrUnitLists[] =$dumArr;

            }

            /*echo '<pre>';
                print_r($arrUnitLists);
               echo '</pre>';
               die;*/

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

}
