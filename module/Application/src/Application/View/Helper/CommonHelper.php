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
use Project\View\Helper\ProjectHelper;
use Wpm\View\Helper\MMSHelper;
use Wpm\View\Helper\WpmHelper;
use Wpm\View\Helper\CrmHelper;

class CommonHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    public $UserList=array();
    protected $connection = null;
	protected $sRowId ="";
	
	public $sSuperiors = "";
	public $m_iMaxLevel=0;
	public $iMaxLevelCount=0;
	public $lUserId;
    protected $lAUserId="";
    public $sUserName="";
	public $sUserId="";
	public $bPowerUser;
    public $bLockUser;
	public $iPasswordReset=0;
    protected $sRoleName;
	public $bAutoApproval;
	public $sFAUpdate = "";
    public $sNextupdateRole = "";
	protected $bApprovalFound = false;
    protected $bTopApprovalFound = false;
    public $bApprovalEdit = false;
	public $bAlterLog = false;
    /** 
     * Set the service locator. 
     * 
     * @param ServiceLocatorInterface $serviceLocator 
     * @return CustomHelper 
     */  
	public function __construct()
	{
		$this->auth = new AuthenticationService();
	}
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

	public function getVoucherNo($argTypeId,$argDate,$argCompanyId,$argCCId,$dbAdapter,$argfrom)
	{
		//$sm = $this->getServiceLocator()->getServiceLocator();
        //$config = $sm->get('application')->getConfig();
		$oVtype = array("genType" => false, "voucherNo" => "", "periodWise" => false, "periodId" => 0, "monthWise" => false, "month" => 0, "year" => 0);
		try {
			$iWidth = 0;
            $iStartNo = 0;
            $iMaxNo = 0;
            $iVNo = 0;
            $iLen = 0;
            $sPre = "";
            $sPrefix = "";
            $sSuffix = "";
			
			$sql     = new Sql($dbAdapter);
			$select = $sql->select();
			$select->from('WF_VoucherTypeTrans')
				   ->columns(array('GenType','PeriodWise'))
				   ->where(array("TypeId"=>$argTypeId,"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$voucherTypeResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			if(sizeof($voucherTypeResult) > 0) {
				$oVtype['genType'] = $voucherTypeResult[0]['GenType'];
				$oVtype['periodWise'] = $voucherTypeResult[0]['PeriodWise'];
			}
			if($oVtype['genType'] == true) {
				if($oVtype['periodWise'] == true) {
					$sql     = new Sql($dbAdapter);
					$select = $sql->select();
					$select->from('WF_VoucherPeriodMaster')
						   ->columns(array('PeriodId'))
						   ->where(array("FromDate"=>$argDate,"ToDate"=>$argDate));
					$statement = $sql->getSqlStringForSqlObject($select);
					$voucherMasterResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(sizeof($voucherMasterResult) > 0) {
						$oVtype['periodId'] = $voucherMasterResult[0]['PeriodId'];
					}
					if($oVtype['periodId'] != 0) {
						$sql     = new Sql($dbAdapter);
						$select = $sql->select();
						$select->from('WF_VoucherTypePeriod')
							   ->columns(array('Monthwise'))
							   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
						$statement = $sql->getSqlStringForSqlObject($select);
						$voucherTypePeriodResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						if(sizeof($voucherTypePeriodResult) > 0) {
							$oVtype['monthWise'] = $voucherTypePeriodResult[0]['Monthwise'];
						}
						
						if($oVtype['monthWise'] == true) {
							$oVtype['month'] = date('m',strtotime($argDate));
							$oVtype['year'] = date('Y',strtotime($argDate));
							$sql     = new Sql($dbAdapter);
							$select = $sql->select();
							$select->from('WF_VoucherTypePeriodTrans')
								   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
								   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId,"Month"=>$oVtype['month'],"Year"=>$oVtype['year']));
							$statement = $sql->getSqlStringForSqlObject($select);
							$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(sizeof($voucherTypeTransResult) > 0) {
								$iWidth = $voucherTypeTransResult[0]['Width'];
								$iStartNo = $voucherTypeTransResult[0]['StartNo'];
								$iMaxNo = $voucherTypeTransResult[0]['MaxNo'];
								$sPrefix = $voucherTypeTransResult[0]['Prefix'];
								$sSuffix = $voucherTypeTransResult[0]['Suffix'];

								if($iStartNo > $iMaxNo) {
									$iVNo = $iStartNo;
								} else {
									$iVNo = $iMaxNo + 1;
								}
								$iLen = $iWidth - strlen($iVNo);
								$sPre = "";
								for($i = 1; $i < $iLen; $i++) {
									$sPre = $sPre."0";
								}
								$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
							}
						} else {
							$sql     = new Sql($dbAdapter);
							$select = $sql->select();
							$select->from('WF_VoucherTypePeriod')
								   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
								   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
							$statement = $sql->getSqlStringForSqlObject($select);
							$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							if(sizeof($voucherTypeTransResult) > 0) {
								$iWidth = $voucherTypeTransResult['Width'];
								$iStartNo = $voucherTypeTransResult['StartNo'];
								$iMaxNo = $voucherTypeTransResult['MaxNo'];
								$sPrefix = $voucherTypeTransResult['Prefix'];
								$sSuffix = $voucherTypeTransResult['Suffix'];
								
								if($iStartNo > $iMaxNo) {
									$iVNo = $iStartNo;
								} else {
									$iVNo = $iMaxNo + 1;
								}
								$iLen = $iWidth - strlen($iVNo);
								$sPre = "";
								for($i = 1; $i < $iLen; $i++) {
									$sPre = $sPre."0";
								}
								$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
							}
						}
					}
				} else {
					$sql     = new Sql($dbAdapter);
					$select = $sql->select();
					$select->from('WF_VoucherTypeTrans')
						   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
						   ->where(array("TypeId"=>$argTypeId,"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
					$statement = $sql->getSqlStringForSqlObject($select);

					$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					if(sizeof($voucherTypeTransResult) > 0) {
						$iWidth = $voucherTypeTransResult['Width'];
						$iStartNo = $voucherTypeTransResult['StartNo'];
						$iMaxNo = $voucherTypeTransResult['MaxNo'];
						$sPrefix = $voucherTypeTransResult['Prefix'];
						$sSuffix = $voucherTypeTransResult['Suffix'];
						
						if($iStartNo > $iMaxNo) {
							$iVNo = $iStartNo;
						} else {
							$iVNo = $iMaxNo + 1;
						}
						$iLen = $iWidth - strlen($iVNo);
						$sPre = "";
						for($i = 1; $i < $iLen; $i++) {
							$sPre = $sPre."0";
						}
						$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
					}
				}
                if ($argfrom == "I")
                {
                    CommonHelper::UpdateMaxNo($argTypeId, $oVtype, $argCompanyId, $argCCId, $dbAdapter,$iVNo);
                }

            }
		} catch (Zend_Exception $e) {
			echo "Error: " . $e->getMessage() . "</br>";
		}

		return $oVtype;
	}

    public function updateMaxNo($argTypeId,$argvType,$argCompanyId,$argCCId,$dbAdapter,$argvNo)
    {
        if ($argvType['genType'] == true)
        {
            $sql = new Sql($dbAdapter);
            $update = $sql->update();
            $iNo=1;

            if ($argvType['periodWise'] == true)
            {
                if ($argvType['monthWise'] == true)
                {
                    $update->table('WF_VoucherTypePeriodTrans');
                    $update->set(array(
                        'MaxNo' => $argvNo,
                    ));
                    $update->where(array("TypeId"=>$argTypeId,"PeriodId"=>$argvType['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId,"Month"=>$argvType['month'],"Year"=>$argvType['year']));
                }
                else
                {
                    $update->table('WF_VoucherTypePeriodTrans');
                    $update->set(array(
                        'MaxNo' => $argvNo,
                    ));
                    $update->where(array("TypeId"=>$argTypeId,"PeriodId"=>$argvType['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
                }
            }
            else
            {
                $update->table('WF_VoucherTypeTrans');
                $update->set(array(
                    'MaxNo' => $argvNo,
                ));
                $update->where(array("TypeId"=>$argTypeId,"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
            }

            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
    }
	
	public function insertLog($argLogTime,$argRoleName,$argType,$argLogDescription,$argRegId,$argCCId,$argCompanyId,$argDBName,$argRefNo,$argUserId,$argValue ,$argPrevLogId)
	{
        $this->argUserId=$argUserId;
		//$argValue = 0;
		//$argPrevLogId = 0;
		$bApprovalEdit = false;
		$argFaCCId = 0;
		$lAUserId = 0;
		$this->sFAUpdate = "N";
		$bLogUdpate = false;
		$this->sNextupdateRole = "";
		$this->bAlterLog =false;
		$sUserName="";
		$sCName = "";
        $sTaskName = "";
		$sipaddress = "";
		$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		$sipaddress =  CommonHelper::get_client_ip();
		$sType = "";
        $sTaskName = "";
		/*
		$config = $this->getServiceLocator()->get('config');
				$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
				$connection = $dbAdapter->getDriver()->getConnection();
				try {
					$connection->beginTransaction();
		*/
		$dbAdapter = $this->getServiceLocator()->get("Zend\Db\Adapter\Adapter");
		$connection = $dbAdapter->getDriver()->getConnection();
		$connection->beginTransaction();
		try {

		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from('WF_TaskTrans')
			   ->columns(array('TaskType','TaskName'))
			   ->where("RoleName='$argRoleName'");
            $statement = $sql->getSqlStringForSqlObject($select);
		$tasktransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		if(count($tasktransResult) > 0) {
			$sType = $tasktransResult[0]['TaskType'];
			$sTaskName = $tasktransResult[0]['TaskName'];
		}
		if($lAUserId != 0){
		//Alternate User
			if ($sType == "C" || $argType == "E"){				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_UserRoleTrans"))
					->columns(array("RoleId"))
					->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
					->where("b.RoleName='$argRoleName' And a.UserId=$argUserId ");
				 $statement = $sql->getSqlStringForSqlObject($select);
				$userRoleResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if ( count( $userRoleResult ) == 0 ) {
					$this->bAlterLog =true;
				}
				
				$bSpecial=false;
				if($this->bAlterLog == true){				
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserspecialRoleTrans"))
						->columns(array("RoleId"))
						->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
						->where("b.RoleName='$argRoleName' And a.UserId=$argUserId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$userspecialRoleResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if ( count( $userspecialRoleResult ) > 0 ) {
						$this->bAlterLog = false;
						$bSpecial = true;
					} else {
						$this->bAlterLog = true;
					}				
				}
				
				if ($this->bAlterLog == false && $bSpecial == false){
					if ($argCCId != 0)
					{
						$select = $sql->select();
						$select->from('WF_UserCostCentreTrans')
							   ->columns(array('CostCentreId'))
							   ->where("CostCentreId=$argCCId and UserId = $argUserId ");
						$statement = $sql->getSqlStringForSqlObject($select);
						$userCCtransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();						
						if ( count( $userCCtransResult ) > 0 ) { $this->bAlterLog = true; }
					}
					else if ($argCompanyId != 0)
					{
						$select = $sql->select();
						$select->from('WF_UserCompanyTrans')
							   ->columns(array('CompanyId'))
							   ->where("CompanyId=$argCompanyId and UserId = $argUserId ");
						$statement = $sql->getSqlStringForSqlObject($select);
						$userCompanytransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if ( count( $userCompanytransResult ) > 0 ) { $this->bAlterLog = true; }
					}
				 
				}
			}	
		}

		$iNewUserId = $argUserId;

		if ($this->bAlterLog == true) {
			$iNewUserId = $lAUserId;
			$this->m_iMaxLevel = 0;
			CommonHelper::GetSuperiorUsers($iNewUserId, $dbAdapter);
			if($this->sSuperiors != ""){
				$this->sSuperiors = rtrim($this->sSuperiors,',');
			}
		}
		
		$insert = $sql->insert('WF_LogMaster');
		$insert->values(array(
			'UserId'  => $iNewUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => $argType,
			'LogDescription'  => $argLogDescription,'ComputerName'  => $sCName,'AUserId'  => $argUserId,'IpAddress' => $sipaddress	
		));
            $statement = $sql->getSqlStringForSqlObject($insert);
		$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
		
		$insert = $sql->insert('WF_LogTrans');
		$insert->values(array(
			'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
			'DBName' => $argDBName,'RefNo' => $argRefNo,'Priority'  => "",'PriorityRemarks'  => ""	
		));
		$statement = $sql->getSqlStringForSqlObject($insert);
		$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
		
		if($argRoleName == "Login"){
			$select = $sql->select();
			$select->from('WF_LogStatus')
				   ->columns(array('TransId'))
				   ->where(array("UserId"=>$argUserId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$logStatusResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$bAns = false;
			if(count($logStatusResult) > 0) {
				$bAns = true;
			}
			
			if ($bAns == false) {
				$insert = $sql->insert('WF_LogStatus');
				$insert->values(array(
					'UserId'  => $argUserId,'ComputerName'  => $sCName,'SessionName'  => "",'LogTime'  => date( 'Y/m/d H:i:s' ),
					'LogStatus'  => date( 'Y/m/d H:i:s' ), 'IpAddress'=>$sipaddress
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
		}
		
		if($argRoleName == "Logout"){
			$delete = $sql->delete();
			$delete->from('WF_LogStatus')
				->where("UserId=$argUserId and ComputerName ='$sCName' and SessionName ='' and IpAddress='$sipaddress' ");	
			$DelStatement = $sql->getSqlStringForSqlObject($delete);			
			$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
		}
		

		if ($argType == "R") {
			$update = $sql->update();
			$update->table( 'WF_PendingWorks' )
				->set( array( 'Status' => '1' ))
				->where("PendingRole='$argRoleName' and LogId =$argPrevLogId ");
			$statement = $sql->getSqlStringForSqlObject( $update ); 
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			//Multiple Feed Delete Start
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_PendingWorks"))
				->columns(array('TransId','UserId'))
				->where("a.PendingRole ='$argRoleName' and a.Status= 1 and LogId =$argPrevLogId");
			$statementfeed = $sql->getSqlStringForSqlObject($select); 
			$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($feedUserResult as &$feedUserResults) {
				$iPendingWorkId = $feedUserResults['TransId'];
				CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
			}
			//Multiple Feed Delete End
					
			$subQuery = $sql->select();
			$subQuery->from("WF_LogTrans")
				->columns(array('LogId'))
				->where("RegisterId=$argRegId and DBName= '$argDBName'");
						
			$update = $sql->update();
			$update->table( 'WF_ApprovalTrans' )
				->set( array( 'Status' => '0' ))
				->where("RoleName='$argRoleName' and RegId =$argRegId ");
			$update->where->expression('LogId IN ?', array($subQuery));
			$statement = $sql->getSqlStringForSqlObject( $update ); 
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			$select = $sql->select();
			$select->from('WF_TaskTrans')
				   ->columns(array('TaskName'))
				   ->where("RoleName='$argRoleName' ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskResult) > 0) {
				$sTaskName = $taskResult[0]['TaskName'];
			}
			
			$sPrevRole = "";
			$select = $sql->select();
			$select->from('WF_TaskTrans')
				   ->columns(array('RoleName'))
				   ->where("TaskName='$sTaskName' and RoleType='N' and TaskType='C' ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$taskPrevResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskPrevResult) > 0) {
				$sPrevRole = $taskPrevResult[0]['RoleName'];
			}
			
			$iUserId = 0;
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_LogMaster"))
				->columns(array("UserId"))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
				->where("a.RoleName='$sPrevRole' And b.RegisterId=$argRegId and b.DBName = '$argDBName' ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$logResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($logResult) > 0) {
				$iUserId = $logResult[0]['UserId'];
			}
			
			$insert = $sql->insert('WF_PendingWorks');
			$insert->values(array(
				'PendingRole'  => $sPrevRole,'RoleType'  => 'N','NonTask'  => 0,'LogId'  => $identity,
				'UserId'  => $iUserId	
			));
			$statement = $sql->getSqlStringForSqlObject($insert);
			$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			$pendingWorkTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
			CommonHelper::UpdateApprovalFeedDetail($pendingWorkTransId, $iUserId, $dbAdapter ,'I');
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_PendingWorks"))
				->columns(array("TransId","UserId"), array("RefNo"))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
				->where("a.PendingRole='$sPrevRole' And a.LogId=$identity ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$iId = 0;
			$sREfNo = "";
			$iPUserId = 0;
			$iRemId = 0;
			foreach($logPendingResult as &$logPendingResults) {
				$iId = $logPendingResults['TransId'];
				$sREfNo = $logPendingResults['RefNo'];
				if ($sREfNo != "") { 
				$sREfNo = $sPrevRole . " (" . $sREfNo . ")"; 
				} else { $sREfNo = $sPrevRole; 
				}
				$iPUserId = $logPendingResults['UserId'];
				
				$insert = $sql->insert('WF_ReminderMaster');
				$insert->values(array(
					'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId,
					'UserId'  => $iUserId	
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$insert = $sql->insert('WF_ReminderTrans');
				$insert->values(array(
					'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
			
			$sMsg = $sTaskName . " (" . $argRefNo . ")" . " Rejected by " . $sUserName . " Kindly Check";
			
			$insert = $sql->insert('WF_ReminderMaster');
			$insert->values(array(
				'ReminderDescription'  => $sMsg,'ReminderDate'  => date( 'Y/m/d H:i:s' )
			));
			$statement = $sql->getSqlStringForSqlObject($insert);
			$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
			
			$subQuery = $sql->select();
			$subQuery->from("WF_TaskTrans")
				->columns(array('RoleName'))
				->where("TaskName='$sTaskName'");
				
			$select = $sql->select();
			$select->from( array('a' => 'WF_LogMaster' ))
				->columns(array( 'UserId', 'ReminderId'=>new Expression("'$iRemId'"), 'Live'=>new Expression("1") ))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER);
			$select->where("b.RegisterId=$argRegId and b.DBName='$argDBName' ");
			$select->where->expression('a.RoleName IN ?', array($subQuery));
			
			$insert = $sql->insert();
			$insert->into( 'WF_ReminderTrans' );
			$insert->columns(array('UserId', 'ReminderId', 'Live'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			$sTableName = "";
			$sFieldName = "";
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskMaster"))
				->columns(array("TableName","FieldName"))
				->where("a.TaskName='$sTaskName' ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskResult) > 0) {
				$sTableName = $taskResult[0]['TableName'];
				$sFieldName = $taskResult[0]['FieldName'];
			}
			
			if($sTableName != "" && $sFieldName != "") {
				$update = $sql->update();
				$update->table( "$sTableName" )
					->set( array( 'Approve' => 'N' ))
					->where("$sFieldName=$argRegId ");
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			}
		} else if ($argType == "U") {
			if($argRoleName != "Approve-Posting-Period-Lock") {
				$iLevelId = 0;
				$iPositionId = 0;
				$sApprove = "";
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskTrans"))
					->columns(array("MultiApproval","ApprovalBased"))
					->where("a.RoleName='$argRoleName' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($taskResult) > 0) {
					if ($taskResult[0]['MultiApproval'] == 1) {
						if ($taskResult[0]["ApprovalBased"] == "") { 
							$sApprove = "L"; 
						} else { 
							$sApprove = $taskResult[0]["ApprovalBased"]; 
						}
					}
				}
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_Users"))
					->columns(array("LevelId","PositionId"))
					->where("a.UserId=$iNewUserId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$userResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($userResult) > 0) {
					$iLevelId = $userResult[0]["LevelId"];
					$iPositionId = $userResult[0]["PositionId"];					
				}
				$bSpecial = false;
				
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId=$argRegId and DBName='$argDBName' ");
					
				$select = $sql->select();
				$select->from( array('a' => 'WF_ApprovalTrans' ))
					->columns(array( 'Special'));
				$select->where("a.RoleName='$argRoleName' and a.RegId=$argRegId and a.UserId=$iNewUserId ");
				$select->where->expression('a.LogId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject($select); 
				$ApprovalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($ApprovalResult) > 0) {
					$bSpecial = $ApprovalResult[0]["Special"];
				}
				
				if ($bSpecial == true)  {
					if ($sApprove == "L") {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iLevelId ");
									
						$update = $sql->update();
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '0' ))
							->where("RoleName='$argRoleName' and RegId =$argRegId ");
						$update->where->expression('UserId IN ?', array($subQuery1));
						$update->where->expression('LogId IN ?', array($subQuery));					
					} else if ($sApprove == "P") {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iLevelId and PositionId=$iPositionId ");
									
						$update = $sql->update();
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '0' ))
							->where("RoleName='$argRoleName' and RegId =$argRegId ");
						$update->where->expression('UserId IN ?', array($subQuery1));
						$update->where->expression('LogId IN ?', array($subQuery));	
					} else if ($sApprove == "A") {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$update = $sql->update();
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '0' ))
							->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$iNewUserId ");
						$update->where->expression('LogId IN ?', array($subQuery));						
					} else {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$update = $sql->update();
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '0' ))
							->where("RoleName='$argRoleName' and RegId =$argRegId ");
						$update->where->expression('LogId IN ?', array($subQuery));	
					}					
				} else {
					$subQuery = $sql->select();
					$subQuery->from("WF_LogTrans")
						->columns(array('LogId'))
						->where("RegisterId=$argRegId and DBName= '$argDBName'");
						
					$update = $sql->update();
					$update->table( 'WF_ApprovalTrans' )
						->set( array( 'Status' => '0' ))
						->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$iNewUserId ");
					$update->where->expression('LogId IN ?', array($subQuery));	
				}
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Delete WF_ReminderTrans
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("a.RoleName='$argRoleName' and RegisterId=$argRegId and DBName='$argDBName' ");
				
				$subQueryPendingWorks = $sql->select();
				$subQueryPendingWorks->from("WF_PendingWorks")
					->columns(array('TransId'))
					->where("Status='0'");
				$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
								
				$subQuery = $sql->select();
				$subQuery->from("WF_ReminderMaster")
					->columns(array('ReminderId'));
				$subQuery->where->expression('PId IN ?', array($subQueryPendingWorks));
					
				$delete = $sql->delete();
				$delete->from('WF_ReminderTrans');
				$delete->where->expression('ReminderId IN ?', array($subQuery));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				//delete WF_ReminderMaster
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("a.RoleName='$argRoleName' and RegisterId=$argRegId and DBName='$argDBName' ");
				
				$subQueryPendingWorks = $sql->select();
				$subQueryPendingWorks->from("WF_PendingWorks")
					->columns(array('TransId'))
					->where("Status='0'");
				$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
				
				$delete = $sql->delete();
				$delete->from('WF_ReminderMaster');
				$delete->where->expression('PId IN ?', array($subQueryPendingWorks));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				//Delete PendingWorks				
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("a.RoleName='$argRoleName' and RegisterId=$argRegId and DBName='$argDBName' ");
				
				$delete = $sql->delete();
				$delete->from('WF_PendingWorks')
					->where("Status='0'");
				$delete->where->expression('LogId IN ?', array($subQueryLog));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				//Insert PendingWorks
				
				$subQueryApp = $sql->select();
				$subQueryApp->from("WF_ApprovalTrans")
					->columns(array('UserId'))
					->where("RoleName='$argRoleName' and RegId='$argRegId'");
				if ($bSpecial == true){
					if ($sApprove == "L") {
						$subQueryUser = $sql->select();
						$subQueryUser->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId='$iLevelId'");
												
						$subQueryApp->where->expression('UserId IN ?', array($subQueryUser));
					} else if ($sApprove == "P") {
						$subQueryUser = $sql->select();
						$subQueryUser->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId='$iLevelId' and PositionId='$iPositionId'");
						
						$subQueryApp->where->expression('UserId IN ?', array($subQueryUser));
					} else if ($sApprove == "A") {
						$subQueryApp->where("UserId='$iNewUserId'");
					} else {
					
					}
				} else {
					$subQueryApp->where("UserId='$iNewUserId'");
				}
				
				$select = $sql->select();
				$select->from( array('a' => 'WF_Users' ))
					->columns(array( 'PendingRole'=>new Expression("'$argRoleName'"), 'RoleType'=>new Expression("'A'"), 'NonTask'=>new Expression("1-1"), 'LogId'=>new Expression("$identity"), 'UserId' ));
				$select->where->expression('a.UserId IN ?', array($subQueryApp)); 
				$insert = $sql->insert();
				$insert->into( 'WF_PendingWorks' );
				$insert->columns(array('PendingRole', 'RoleType', 'NonTask', 'LogId', 'UserId'));
				$insert->Values( $select );								
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Multiple Feed Insert Start
				$statementfeed = $sql->getSqlStringForSqlObject($select); 
				$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($feedUserResult as &$feedUserResults) {
					$ifeedUserId = $feedUserResults['UserId'];
					$ifeedLogId = $feedUserResults['LogId'];
					$ifeedPendingRole = $feedUserResults['PendingRole'];
					
					$iPendingWorkId =0;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId"))
						->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='A' and a.NonTask=0 and a.UserId=$ifeedUserId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($penResult) > 0) {
						$iPendingWorkId = $penResult[0]['TransId'];
						CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
					}
				}
				//Multiple Feed Insert End
				
				$sTaskName = "";				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskTrans"))
					->columns(array("TaskName"))
					->where("a.RoleName='$argRoleName' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($taskResult) > 0) {
					$sTaskName = $taskResult[0]['TaskName'];
				}
				
				$subQuery = $sql->select();
				$subQuery->from("WF_TaskTrans")
					->columns(array('RoleName'))
					->where("TaskName='$sTaskName' ");
				
				$select = $sql->select();
				$select->from( array('a' => 'WF_LogMaster' ))
					->columns(array( 'UserId' => new Expression("Distinct a.UserId") ))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER);
				$select->where("b.RegisterId='$argRegId' and b.DBName = '$argDBName'");
				$select->where->expression('a.RoleName IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject($select);
				$logResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$iRemId = 0;
				$sREfNo="";
				$iPUserId=0;
				foreach($logResult as &$logResults) {
					$iPUserId = $logResults['UserId'];					
					$sREfNo = $sTaskName . " - UnApproved" . " (" . $argRefNo . ") by " . $sUserName;
					
					$insert = $sql->insert('WF_ReminderMaster');
					$insert->values(array(
						'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' )
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_ReminderTrans');
					$insert->values(array(
						'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
				
				//FaUpdate-Remove-Check
				$iOrderId = 0;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_Users"))
					->columns(array('OrderId' => new Expression("b.OrderId")))
					->join(array("b"=> "WF_LevelMaster"), "a.LevelId=b.LevelId", array(), $select::JOIN_INNER);
				$select->where("UserId='$iNewUserId' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($taskResult) > 0) {
					$iOrderId = $taskResult[0]['OrderId'];
				}
				$select = $sql->select();
				$select->from(array("a"=>"WF_ApprovalTrans"))
					->columns(array('TransId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
					->where("a.RoleName='$argRoleName' and a.OrderID<$iOrderId and a.RegId =$argRegId and b.DBName='$argDBName' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$AppResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($AppResult) == 0) {
					$this->sFAUpdate = "Remove";
				}
				$sApp = "N";
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ApprovalTrans"))
					->columns(array('TransId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
					->where("a.RoleName='$argRoleName' and a.Status=1 and a.RegId =$argRegId and b.DBName='$argDBName' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$AppResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($AppResult) > 0) {
					$sApp = "P";
				}
				
				$sTableName = "";
                $sFieldName = "";
				if ($sApp == "N") { $this->sFAUpdate = "Remove"; }
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskMaster"))
					->columns(array("TableName","FieldName"))
					->where("a.TaskName='$sTaskName' ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($taskResult) > 0) {
					$sTableName = $taskResult[0]['TableName'];
					$sFieldName = $taskResult[0]['FieldName'];
				}
				if($sTableName != "" && $sFieldName != "") {
					$update = $sql->update();
					$update->table( "$sTableName" )
						->set( array( 'Approve' => $sApp ))
						->where("$sFieldName=$argRegId ");
					$statement = $sql->getSqlStringForSqlObject( $update ); 
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				}		
			}
		} else if ($argType == "D") {
			$sTaskName = "";
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskTrans"))
				->columns(array("TaskName"))
				->where("a.RoleName='$argRoleName' ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$taskNameResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskNameResult) > 0) {
				$sTaskName = $taskNameResult[0]['TaskName'];
			}
			
			if ($sTaskName != "") {
				//delete WF_ReminderTrans
				$subQuerytask = $sql->select();
				$subQuerytask->from("WF_TaskTrans")
					->columns(array('RoleName'));
					$subQuerytask->where("TaskName='$sTaskName'");
				
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("RegisterId=$argRegId and DBName='$argDBName' ");
				$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
				
				$subQueryPendingWorks = $sql->select();
				$subQueryPendingWorks->from("WF_PendingWorks")
					->columns(array('TransId'));
				$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
								
				$subQuery = $sql->select();
				$subQuery->from("WF_ReminderMaster")
					->columns(array('ReminderId'));
				$subQuery->where->expression('PId IN ?', array($subQueryPendingWorks));
					
				$delete = $sql->delete();
				$delete->from('WF_ReminderTrans');
				$delete->where->expression('ReminderId IN ?', array($subQuery));
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				//delete WF_ReminderMaster
				$subQuerytask = $sql->select();
				$subQuerytask->from("WF_TaskTrans")
					->columns(array('RoleName'));
					$subQuerytask->where("TaskName='$sTaskName'");
				
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where(" b.RegisterId=$argRegId and b.DBName= '$argDBName' ");
				$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
				
				$subQueryPendingWorks = $sql->select();
				$subQueryPendingWorks->from("WF_PendingWorks")
					->columns(array('TransId'));
				$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
				
				$delete = $sql->delete();
				$delete->from('WF_ReminderMaster');
				$delete->where->expression('PId IN ?', array($subQueryPendingWorks));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

				//Delete PendingWorks
				$subQuerytask = $sql->select();
				$subQuerytask->from("WF_TaskTrans")
					->columns(array('RoleName'));
					$subQuerytask->where("TaskName='$sTaskName'");
					
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("b.RegisterId=$argRegId and b.DBName='$argDBName' ");
				$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
				
				$delete = $sql->delete();
				$delete->from('WF_PendingWorks');
				$delete->where->expression('LogId IN ?', array($subQueryLog));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				//Delete ApprovalTrans
				$subQuerytask = $sql->select();
				$subQuerytask->from("WF_TaskTrans")
					->columns(array('RoleName'));
					$subQuerytask->where("TaskName='$sTaskName'");
					
				$subQueryLog = $sql->select();
				$subQueryLog->from( array('a' => 'WF_LogMaster' ))
					->columns(array('LogId'))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
				$subQueryLog->where("b.RegisterId=$argRegId and b.DBName='$argDBName' ");
				$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
				
				$delete = $sql->delete();
				$delete->from('WF_ApprovalTrans');
				$delete->where->expression('LogId IN ?', array($subQueryLog));	
				$DelStatement = $sql->getSqlStringForSqlObject($delete);			
				$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
			}	
		} else {
			$sType = "";
			$sTaskName = "";
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskTrans"))
				->columns(array("TaskType", "TaskName"))
				->where("a.RoleName='$argRoleName' ");
			 $statement = $sql->getSqlStringForSqlObject($select);
			$taskNameResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskNameResult) > 0) {
				$sType = $taskNameResult[0]['TaskType'];
				$sTaskName = $taskNameResult[0]['TaskName'];
			}
			if ($argType == "C") {
                $sType = "";
            }
			if ($argType == "E") {
				if($bApprovalEdit == false) {
					$iAppRoleId = 0;
					$sAddRole = "";
					$sAppRole = "";
					$bMulti = false;
					$bValueApproval = false;
					$sApprovalBase = "";
					$iMaxLevel = 0;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_TaskTrans"))
						->columns(array("RoleId", "RoleName","MultiApproval","ValueApproval","ApprovalBased","MaxLevel"))
						->where("a.TaskName='$sTaskName' and a.RoleType='A' ");
					$statement = $sql->getSqlStringForSqlObject($select);
					$taskNameRResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($taskNameRResult) > 0) {
						$iAppRoleId = $taskNameRResult[0]['RoleId'];
						$sAppRole = $taskNameRResult[0]['RoleName'];
						
						$bMulti = $taskNameRResult[0]['MultiApproval'];
						$bValueApproval = $taskNameRResult[0]['ValueApproval'];
						$sApprovalBase = $taskNameRResult[0]['ApprovalBased'];
						$iMaxLevel = $taskNameRResult[0]['MaxLevel'];
					}
					if ($bMulti == true && $sApprovalBase == "") { $sApprovalBase = "L"; }
					$iAddRoleId = 0;
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_TaskTrans"))
						->columns(array("RoleId", "RoleName"))
						->where("a.TaskName='$sTaskName' and a.RoleType='N' and TaskType='C' ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$taskNameResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($taskNameResult) > 0) {
						$iAddRoleId = $taskNameResult[0]['RoleId'];
						$sAddRole = $taskNameResult[0]['RoleName'];
					}
					
					$iLogId = 0;
					if ($iAddRoleId != 0) {
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_LogMaster"))
							->columns(array("LogId"))
							->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
							->where("a.RoleName='$sAddRole' and b.DBName='$argDBName' and b.RegisterId=$argRegId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$logResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($logResult) > 0) {
							$iLogId = $logResult[0]['LogId'];
						}
						$bEditApproval = false;
						
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_ApprovalTrans"))
							->columns(array("UserId"))
							->where("a.RoleName='$argRoleName' and UserId=$iNewUserId and RegId=$argRegId ");
						$select->where->expression('LogId IN ?', array($subQuery));
						$statement = $sql->getSqlStringForSqlObject($select); 
						$ApprovalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($ApprovalResult) > 0) {
							$bEditApproval = true;
						}
						
						if ($bEditApproval == false){
							$subQuery = $sql->select();
							$subQuery->from("WF_LogTrans")
								->columns(array('LogId'))
								->where("RegisterId=$argRegId and DBName= '$argDBName'");
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_ApprovalTrans"))
								->columns(array("UserId"))
								->where("a.RoleName='$argRoleName' and RegId=$argRegId ");
							$select->where->expression('LogId IN ?', array($subQuery));
							$statement = $sql->getSqlStringForSqlObject($select); 
							$ApprovalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($ApprovalResult) == 0) {
								$bEditApproval = true;
							}
							if ($iLogId == 0) { $iLogId = $identity; }
						}
						
						
						if ($bEditApproval == true) {
							if ($sTaskName != "") {
								//Delete WF_ReminderTrans
								$subQueryTask = $sql->select();
								$subQueryTask->from( array('a' => 'WF_TaskTrans' ))
									->columns(array('RoleName'));
								$subQueryTask->where("a.TaskName='$sTaskName' ");
								
								$subQueryLog = $sql->select();
								$subQueryLog->from( array('a' => 'WF_LogMaster' ))
									->columns(array('LogId'))
									->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
								$subQueryLog->where("RegisterId=$argRegId and DBName='$argDBName' ");
								$subQueryLog->where->expression('RoleName IN ?', array($subQueryTask));
								
								$subQueryPendingWorks = $sql->select();
								$subQueryPendingWorks->from("WF_PendingWorks")
									->columns(array('TransId'));
								$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
												
								$subQuery = $sql->select();
								$subQuery->from("WF_ReminderMaster")
									->columns(array('ReminderId'));
								$subQuery->where->expression('PId IN ?', array($subQueryPendingWorks));
									
								$delete = $sql->delete();
								$delete->from('WF_ReminderTrans');
								$delete->where->expression('ReminderId IN ?', array($subQuery));	
								$DelStatement = $sql->getSqlStringForSqlObject($delete);			
								$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
								//delete WF_ReminderMaster
								$subQuerytask = $sql->select();
								$subQuerytask->from("WF_TaskTrans")
									->columns(array('RoleName'));
									$subQuerytask->where("TaskName='$sTaskName'");
								
								$subQueryLog = $sql->select();
								$subQueryLog->from( array('a' => 'WF_LogMaster' ))
									->columns(array('LogId'))
									->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
								$subQueryLog->where(" b.RegisterId=$argRegId and b.DBName= '$argDBName' ");
								$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
								
								$subQueryPendingWorks = $sql->select();
								$subQueryPendingWorks->from("WF_PendingWorks")
									->columns(array('TransId'));
								$subQueryPendingWorks->where->expression('LogId IN ?', array($subQueryLog));
								
								$delete = $sql->delete();
								$delete->from('WF_ReminderMaster');
								$delete->where->expression('PId IN ?', array($subQueryPendingWorks));	
								$DelStatement = $sql->getSqlStringForSqlObject($delete);			
								$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								//Delete PendingWorks
								$subQuerytask = $sql->select();
								$subQuerytask->from("WF_TaskTrans")
									->columns(array('RoleName'));
									$subQuerytask->where("TaskName='$sTaskName'");
									
								$subQueryLog = $sql->select();
								$subQueryLog->from( array('a' => 'WF_LogMaster' ))
									->columns(array('LogId'))
									->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
								$subQueryLog->where("b.RegisterId=$argRegId and b.DBName='$argDBName' ");
								$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
								
								$delete = $sql->delete();
								$delete->from('WF_PendingWorks');
								$delete->where->expression('LogId IN ?', array($subQueryLog));	
								$DelStatement = $sql->getSqlStringForSqlObject($delete);			
								$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								//Delete ApprovalTrans
								$subQuerytask = $sql->select();
								$subQuerytask->from("WF_TaskTrans")
									->columns(array('RoleName'));
									$subQuerytask->where("TaskName='$sTaskName'");
									
								$subQueryLog = $sql->select();
								$subQueryLog->from( array('a' => 'WF_LogMaster' ))
									->columns(array('LogId'))
									->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQueryLog::JOIN_INNER);
								$subQueryLog->where("b.RegisterId=$argRegId and b.DBName='$argDBName' ");
								$subQueryLog->where->expression('a.RoleName IN ?', array($subQuerytask));
								
								$delete = $sql->delete();
								$delete->from('WF_ApprovalTrans');
								$delete->where->expression('LogId IN ?', array($subQueryLog));	
								$DelStatement = $sql->getSqlStringForSqlObject($delete);			
								$register2 = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							}

							if ($iAppRoleId != 0) {
								CommonHelper::InsertEditApproval($sAppRole, $argRegId, $iAppRoleId, $bMulti, $iLogId, $argDBName, $dbAdapter, $iNewUserId, $argCCId, $argValue, $argCompanyId, $argRefNo, $bValueApproval,$iMaxLevel,$argUserId);
							}
						}	
					} else {
						if ($sAppRole != "") {
							$subQuery = $sql->select();
							$subQuery->from(array("a"=>"WF_LogMaster"))
								->columns(array('LogId'))
								->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
								->where("b.RegisterId=$argRegId and DBName= '$argDBName'");
							
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_PendingWorks"))
								->columns(array("TransId"))
								->where("PendingRole='$sAppRole' and Status=0 ");
							$select->where->expression('LogId IN ?', array($subQuery));
							$statement = $sql->getSqlStringForSqlObject($select); 
							$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($pendingResult) == 0) {
								$sType = "C";
							}						
						}
						if ($sType == "C") {
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_LogMaster"))
								->columns(array("LogId"))
								->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
								->where("a.RoleName='$sAppRole' and b.DBName='$argDBName' and b.RegisterId=$argRegId ")
								->order('a.LogId Desc');
							$statement = $sql->getSqlStringForSqlObject($select); 
							$logResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($logResult) > 0) {
								$iLogId = $logResult[0]['LogId'];
							}
							
							$subQueryPendingWorks = $sql->select();
							$subQueryPendingWorks->from("WF_PendingWorks")
								->columns(array('TransId'))
								->where("LogId='$iLogId'");
											
							$subQuery = $sql->select();
							$subQuery->from("WF_ReminderMaster")
								->columns(array('ReminderId'));
							$subQuery->where->expression('PId IN ?', array($subQueryPendingWorks));
								
							$delete = $sql->delete();
							$delete->from('WF_ReminderTrans');
							$delete->where->expression('ReminderId IN ?', array($subQuery));	
							$DelStatement = $sql->getSqlStringForSqlObject($delete);			
							$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$subQuery = $sql->select();
							$subQuery->from("WF_PendingWorks")
								->columns(array('TransId'))
								->where("LogId='$iLogId'");
							
							$delete = $sql->delete();
							$delete->from('WF_ReminderMaster');
							$delete->where->expression('PId IN ?', array($subQuery));	
							$DelStatement = $sql->getSqlStringForSqlObject($delete);			
							$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$delete = $sql->delete();
							$delete->from('WF_ApprovalTrans')
									->where("LogId='$iLogId'");	
							$DelStatement = $sql->getSqlStringForSqlObject($delete);			
							$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$delete = $sql->delete();
							$delete->from('WF_PendingWorks')
									->where("LogId='$iLogId'");	
							$DelStatement = $sql->getSqlStringForSqlObject($delete);			
							$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$argRoleName = $sAddRole;
							$argType = "N";
						}					
					}					
				}			
			}
			
			//echo $sType;
			
			if ($sType == "C") {
                CommonHelper::InsertPendingWork($argLogTime, $argRoleName, $argRegId, $identity, $argDBName, $dbAdapter, $iNewUserId, $argCCId, $argValue, $argCompanyId, $argRefNo, $argUserId);
			} else if ($argType == "Z") {
                CommonHelper::InsertPendingWorkNonTask($argLogTime, $argRoleName, $argRegId, $identity, $argDBName, $dbAdapter, $iNewUserId, $argCCId, $argValue);
			}
            //echo "1287,";
			//die;
			
		}

		if ($this->sNextupdateRole != "") { $argRoleName = $this->sNextupdateRole; }

		if ($this->sFAUpdate == "Add" || $this->sFAUpdate == "Remove") {
			if (CommonHelper::UpdateFA($identity, $argLogTime, $argCCId, $argRoleName, $argRegId, $dbAdapter, $argDBName) == true) {
			//Commit
			}
		}
		
		$connection->commit();			
		} catch(PDOException $e){
			$connection->rollback();
			print "Error!: " . $e->getMessage() . "</br>";				
		}
	}
	
	public function InsertEditApproval($argRoleName, $argRegId, $argRoleId, $argMulti, $argLogId, $argDBName, $dbAdapter, $argUserId, $argCCId, $argValue, $argCompanyId, $argRefNo, $argValueBase,$argMaxLevel,$lUserId) {
        $this->sNextupdateRole = "";
		$sUserId = "";
		$sipaddress =  CommonHelper::get_client_ip();
		$iProjId=0;
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from('Proj_ProjectMaster')
			   ->columns(array('ProjectId'))
			   ->where("ProjectName='$argDBName'");
		$statement = $sql->getSqlStringForSqlObject($select);
		$projListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		if(count($projListResult) > 0) {
			$iProjId = $projListResult[0]['ProjectId'];
		}
		//Get start Auto Approval
		$bAutoApproval = true;
		$select = $sql->select();
		$select->from('WF_GeneralSetting')
			   ->columns(array('AutoApproval'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$autoApprovalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($autoApprovalResult) > 0) {
			$bAutoApproval = $autoApprovalResult[0]['AutoApproval'];
		}
		//Get end Auto Approval
		
		$this->m_iMaxLevel = $argMaxLevel;
        CommonHelper::GetSuperiorUsers($argUserId, $dbAdapter);
		
		if($this->sSuperiors != "") {
			
			$subQuery = $sql->select();
			$subQuery->from("WF_TaskTrans")
				->columns(array('RoleId'))
				->where("RoleName='$argRoleName'");
				
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_UserRoleTrans"))
				->columns(array("UserId"));
			$select->where->expression('RoleId IN ?', array($subQuery));
			//$select->where->expression('UserId IN ?', $this->sSuperiors);
			$select->where("UserId IN ($this->sSuperiors)");
			
			if($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from("WF_UserCostCentreTrans")
					->columns(array('UserId'))
					->where("CostCentreId='$argCCId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserCC));
			}
			if($iProjId != 0) {
				$subQueryUserProj = $sql->select();
				$subQueryUserProj->from("WF_UserProjectTrans")
					->columns(array('UserId'))
					->where("ProjectId='$iProjId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserProj));
			}
			if ($argMulti == false){
				if ($argValue != 0 && $argValueBase == true) {			
					$selectBetween = $sql->select(); 
					$selectBetween->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId");
					$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
					
					$selectLesser = $sql->select(); 
					$selectLesser->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
					$selectLesser->combine($selectBetween,'Union ALL');
					
					$selectUser = $sql->select(); 
					$selectUser->from(array("a"=>"WF_Users"))
						->columns(array("UserId"));
					$selectUser->where->expression('LevelId IN ?', array($selectLesser));
					
					$select->where->expression('UserId IN ?', array($selectUser));				
				}
			} else {
				if ($argValue != 0 && $argValueBase == true) {
				
					$selectBetween = $sql->select(); 
					$selectBetween->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId");
					$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
					
					$selectGreaterTovalue = $sql->select(); 
					$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
					$selectGreaterTovalue->combine($selectBetween,'Union ALL');
					
					$selectGreaterFromTovalue = $sql->select(); 
					$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
					$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
					
					$selectGreaterFromvalue = $sql->select(); 
					$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
					$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
					
					$selectUser = $sql->select(); 
					$selectUser->from(array("a"=>"WF_Users"))
						->columns(array("UserId"));
					$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
					
					$select->where->expression('UserId IN ?', array($selectUser));
				}
			}			
			$statement = $sql->getSqlStringForSqlObject($select); 
			$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($userSuperiortransResult as &$userSuperiortransResults) {					
				$sUserId = $sUserId . $userSuperiortransResults["UserId"] . ",";
			
			}
			if($sUserId!="") {
				$sUserId = rtrim($sUserId,',');
			} else {
				$selectQuery = $sql->select(); 
				$selectQuery->from(array("a"=>"WF_TaskTrans"))
					->columns(array("RoleId"))				
					->where("a.RoleName='$argRoleName' ");
						
				$select = $sql->select();
				$select->from('WF_UserRoleTrans')
					   ->columns(array('UserId'))
					   ->where("UserId=$argUserId");	   
				$select->where->expression('RoleId IN ?', array($selectQuery));
				$statement = $sql->getSqlStringForSqlObject($select);
				$userResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($userResult) > 0) {
					$sUserId = $userResult[0]['UserId'];
				}
			}
		}
		$bApprovalNotRequired = false;
		$select = $sql->select();
		$select->from('WF_TaskTrans')
			   ->columns(array('RoleId'))
			   ->where("RoleId=$argRoleId and NotRequired=1");
		$statement = $sql->getSqlStringForSqlObject($select);
		$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($taskResult) > 0) {
			$bApprovalNotRequired = true;
		}
		
		$bSpecialApprovalRequired = false;
		$selectQuery = $sql->select(); 
		$selectQuery->from(array("a"=>"WF_UserspecialRoleTrans"))
			->columns(array("UserId"))				
			->where("a.RoleId='$argRoleId' and a.Limit <= $argValue ");
				
		$select = $sql->select();
		$select->from('WF_Users')
			   ->columns(array('UserId'));	   
		$select->where->expression('UserId IN ?', array($selectQuery));
		$statement = $sql->getSqlStringForSqlObject($select);
		$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($taskResult) > 0) {
			$bSpecialApprovalRequired = true;
		}
	
		if ($bAutoApproval == false && $sUserId == ""){
			$subQuery = $sql->select();
			$subQuery->from("WF_TaskTrans")
				->columns(array('RoleId'))
				->where("RoleName='$argRoleName'");
				
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_UserRoleTrans"))
				->columns(array("UserId"))
				->where("UserId='$argUserId'");
			$select->where->expression('RoleId IN ?', array($subQuery));
			if($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from("WF_UserCostCentreTrans")
					->columns(array('UserId'))
					->where("CostCentreId='$argCCId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserCC));
			}
			if($iProjId != 0) {
				$subQueryUserProj = $sql->select();
				$subQueryUserProj->from("WF_UserProjectTrans")
					->columns(array('UserId'))
					->where("ProjectId='$iProjId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserProj));
			}
			if ($argMulti == false){
				if ($argValue != 0 && $argValueBase == true) {
					$selectBetween = $sql->select(); 
					$selectBetween->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId");
					$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
					
					$selectLesser = $sql->select(); 
					$selectLesser->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
					$selectLesser->combine($selectBetween,'Union ALL');
					
					$selectUser = $sql->select(); 
					$selectUser->from(array("a"=>"WF_Users"))
						->columns(array("UserId"));
					$selectUser->where->expression('LevelId IN ?', array($selectLesser));
					
					$select->where->expression('UserId IN ?', array($selectUser));				
				}
			} else {
				if ($argValue != 0 && $argValueBase == true) {
					$selectBetween = $sql->select(); 
					$selectBetween->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId");
					$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
					
					$selectGreaterTovalue = $sql->select(); 
					$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
					$selectGreaterTovalue->combine($selectBetween,'Union ALL');
					
					$selectGreaterFromTovalue = $sql->select(); 
					$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
					$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
					
					$selectGreaterFromvalue = $sql->select(); 
					$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
						->columns(array("LevelId"))				
						->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
					$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
					
					$selectUser = $sql->select(); 
					$selectUser->from(array("a"=>"WF_Users"))
						->columns(array("UserId"));
					$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
					
					$select->where->expression('UserId IN ?', array($selectUser));
				}
			}			
			$statement = $sql->getSqlStringForSqlObject($select); 
			$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($userSuperiortransResult) > 0) {
				$sUserId = $userSuperiortransResult[0]['UserId'];
			}
		}

		if (($sUserId == "" && $this->sSuperiors == "" && $bAutoApproval == true && $bSpecialApprovalRequired == false) || $bApprovalNotRequired == true){
			$iLevelId = 0;
            $iOrderId = 0;
			
			$selectQuery = $sql->select(); 
			$selectQuery->from(array("a"=>"WF_Users"))
				->columns(array("LevelId"))				
				->where("a.UserId='$argUserId'");
					
			$select = $sql->select();
			$select->from('WF_LevelMaster')
				   ->columns(array('LevelId', 'OrderId'));	   
			$select->where->expression('LevelId IN ?', array($selectQuery));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($levelMasterResult) > 0) {
				$iLevelId = $levelMasterResult[0]['LevelId'];
				$iOrderId = $levelMasterResult[0]['OrderId'];
			}
			
			$identity = 0;
            $sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
			if ($this->bAlterLog == true) {
				$insert = $sql->insert('WF_LogMaster');
				$insert->values(array(
					'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
					'LogDescription'  => $argRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId	,'IpAddress' => $sipaddress
				));		
			} else {
				$insert = $sql->insert('WF_LogMaster');
				$insert->values(array(
					'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
					'LogDescription'  => $argRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress	
				));
			}
			$statement = $sql->getSqlStringForSqlObject($insert);
			$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
			
			$insert = $sql->insert('WF_LogTrans');
			$insert->values(array(
				'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
				'DBName'  => $argDBName,'RefNo'  => $argRefNo	
			));
			$statement = $sql->getSqlStringForSqlObject($insert);
			$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			//Delete from WF_ApprovalTrans
			$subQuery = $sql->select();
			$subQuery->from( array('a' => 'WF_LogMaster' ))
				->columns(array('LogId'))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
				->where("a.RoleName='$argRoleName' and b.DBName='$argDBName' and b.RegisterId = $argRegId ");
				
			$delete = $sql->delete();
			$delete->from('WF_ApprovalTrans')
				->where("RoleName='$argRoleName' and RegId = $argRegId ");
			$delete->where->expression('LogId IN ?', array($subQuery));	
			$DelStatement = $sql->getSqlStringForSqlObject($delete);			
			$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
			
			$insert = $sql->insert('WF_ApprovalTrans');
			$insert->values(array(
				'LogId'  => $identity,'RoleName'  => $argRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
				'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
			));
			$statement = $sql->getSqlStringForSqlObject($insert);
			$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

			$sTableName = "";
			$sFieldName = "";
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskMaster"))
				->columns(array("TableName","FieldName"))
				->join(array("b"=>"WF_TaskTrans"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
				->where("b.RoleName='$argRoleName' ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($taskResult) > 0) {
				$sTableName = $taskResult[0]['TableName'];
				$sFieldName = $taskResult[0]['FieldName'];
			}
			
			if($sTableName != "" && $sFieldName != "") {
				$update = $sql->update();
				$update->table( "$sTableName" )
					->set( array( 'Approve' => 'Y' ))
					->where("$sFieldName=$argRegId ");
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			}
            $this->sNextupdateRole = $argRoleName;
            $this->sFAUpdate = "Add";
		} else {
			if ($argMulti == false) { $bUserApproval = false;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_UserRoleTrans"))
					->columns(array("RoleId"))
					->where("a.UserId=$argUserId and a.RoleId=$argRoleId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$userRoleResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($userRoleResult) > 0) {
					$bUserApproval = true;
				}
				
				if ($bUserApproval == true && $bAutoApproval == true) {
					$iLevelId = 0;
                    $iOrderId = 0;
					
					$selectQuery = $sql->select(); 
					$selectQuery->from(array("a"=>"WF_Users"))
						->columns(array("LevelId"))				
						->where("a.UserId='$argUserId'");
							
					$select = $sql->select();
					$select->from('WF_LevelMaster')
						   ->columns(array('LevelId', 'OrderId'));	   
					$select->where->expression('LevelId IN ?', array($selectQuery));
					$statement = $sql->getSqlStringForSqlObject($select); 
					$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($levelMasterResult) > 0) {
						$iLevelId = $levelMasterResult[0]['LevelId'];
						$iOrderId = $levelMasterResult[0]['OrderId'];
					}
					
					$identity = 0;
					$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
					if ($this->bAlterLog == true) {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
							'LogDescription'  => $argRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId	,'IpAddress' => $sipaddress
						));		
					} else {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
							'LogDescription'  => $argRoleName,'ComputerName'  => $sCName ,'IpAddress' => $sipaddress	
						));
					}
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_LogTrans');
					$insert->values(array(
						'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
						'DBName'  => $argDBName,'RefNo'  => $argRefNo	
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					//Delete from WF_ApprovalTrans
					$subQuery = $sql->select();
					$subQuery->from( array('a' => 'WF_LogMaster' ))
						->columns(array('LogId'))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
						->where("a.RoleName='$argRoleName' and b.DBName='$argDBName' and b.RegisterId = $argRegId ");
						
					$delete = $sql->delete();
					$delete->from('WF_ApprovalTrans')
						->where("RoleName='$argRoleName' and RegId = $argRegId ");
					$delete->where->expression('LogId IN ?', array($subQuery));	
					$DelStatement = $sql->getSqlStringForSqlObject($delete);			
					$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

					$insert = $sql->insert('WF_ApprovalTrans');
					$insert->values(array(
						'LogId'  => $identity,'RoleName'  => $argRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
						'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

					$sTableName = "";
					$sFieldName = "";
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_TaskMaster"))
						->columns(array("TableName","FieldName"))
						->join(array("b"=>"WF_TaskTrans"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
						->where("b.RoleName='$argRoleName' ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($taskResult) > 0) {
						$sTableName = $taskResult[0]['TableName'];
						$sFieldName = $taskResult[0]['FieldName'];
					}
					
					if($sTableName != "" && $sFieldName != "") {
						$update = $sql->update();
						$update->table( "$sTableName" )
							->set( array( 'Approve' => 'Y' ))
							->where("$sFieldName=$argRegId ");
						$statement = $sql->getSqlStringForSqlObject( $update ); 
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}
                    $this->sNextupdateRole = $argRoleName;
                    $this->sFAUpdate = "Add";
				} else {
					$subQuery = $sql->select();
					$subQuery->from( array('a' => 'WF_LogMaster' ))
						->columns(array('LogId'))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
						->where("a.RoleName='$argRoleName' and b.DBName='$argDBName' and b.RegisterId = $argRegId ");
						
					$delete = $sql->delete();
					$delete->from('WF_ApprovalTrans')
						->where("RoleName='$argRoleName' and RegId = $argRegId ");
					$delete->where->expression('LogId IN ?', array($subQuery));	
					$DelStatement = $sql->getSqlStringForSqlObject($delete);			
					$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//Insert WF_ApprovalTrans
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$argRoleName'"), 'UserId', 'RegId' => new Expression("$argRegId")
						, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId")  ))
						->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
						->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
						->where("RoleId='$argRoleId'");
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					if ($sUserId != "") { 
						//$select->where->expression('a.UserId IN ?', array($sUserId));
						$select->where("a.UserId IN ($sUserId)");
					} else { 
						$select->where("a.UserId= 0");
					}
					
					if ($argValue != 0 && $argValueBase == true) {
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectLesser = $sql->select(); 
						$selectLesser->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
						$selectLesser->combine($selectBetween,'Union ALL');
						
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectLesser));
						
						$select->where->expression('a.UserId IN ?', array($selectUser));
					}
					$select->order('c.OrderId Desc');
					
					$insert = $sql->insert();
					$insert->into( 'WF_ApprovalTrans' );
					$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Insert WF_PendingWorks
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array( 'RoleName' => new Expression("'$argRoleName'"), 'Type' => new Expression("'A'")
						, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId'  ))
						->where("a.RoleId='$argRoleId'");
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					if ($sUserId != "") { 
						//$select->where->expression('a.UserId IN ?', array($sUserId));
						$select->where("a.UserId IN ($sUserId)");	
					} else { 
						$select->where("a.UserId= 0");
					}
					if ($argValue != 0 && $argValueBase == true) {
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectLesser = $sql->select(); 
						$selectLesser->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
						$selectLesser->combine($selectBetween,'Union ALL');
						
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectLesser));
						
						$select->where->expression('a.UserId IN ?', array($selectUser));		
					}
					
					$insert = $sql->insert();
					$insert->into( 'WF_PendingWorks' );
					$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Multiple Feed Insert Start
					$statementfeed = $sql->getSqlStringForSqlObject($select); 
					$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($feedUserResult as &$feedUserResults) {
						$ifeedUserId = $feedUserResults['UserId'];
						$ifeedLogId = $feedUserResults['LogId'];
						$ifeedPendingRole = $feedUserResults['RoleName'];
					
						$iPendingWorkId =0;
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array("TransId"))
							->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='A' and a.NonTask=0 and a.UserId=$ifeedUserId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($penResult) > 0) {
							$iPendingWorkId = $penResult[0]['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
						}
					}
					//Multiple Feed Insert End
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
						->where("a.PendingRole='$argRoleName' and a.LogId=$argLogId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$iId = 0;
                    $sREfNo = "";
                    $iPUserId = 0;
                    $iRemId = 0;
					foreach($pendingResult as $pendingResults){
						$iId = $pendingResults['TransId'];
						$sREfNo = $pendingResults['RefNo'];
						if ($sREfNo != "") { 
							$sREfNo = $argRoleName . " (" . $sREfNo . ")"; 
						} else { 
							$sREfNo = $argRoleName;
						}							
						$iPUserId = $pendingResults['UserId'];
						
						$insert = $sql->insert('WF_ReminderMaster');
						$insert->values(array(
							'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
							'RType'  => 'P' ,'PId'  => $iId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
						
						$insert = $sql->insert('WF_ReminderTrans');
						$insert->values(array(
							'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
							'Live'  => 1 ));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
				}
            } else {
				if ($sUserId == "" && $this->sSuperiors == "" && $bAutoApproval == true) {
					$iLevelId = 0;
                    $iOrderId = 0;
					
					$selectQuery = $sql->select(); 
					$selectQuery->from(array("a"=>"WF_Users"))
						->columns(array("LevelId"))				
						->where("a.UserId='$argUserId'");
							
					$select = $sql->select();
					$select->from('WF_LevelMaster')
						   ->columns(array('LevelId', 'OrderId'));	   
					$select->where->expression('LevelId IN ?', array($selectQuery));
					$statement = $sql->getSqlStringForSqlObject($select); 
					$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($levelMasterResult) > 0) {
						$iLevelId = $levelMasterResult[0]['LevelId'];
						$iOrderId = $levelMasterResult[0]['OrderId'];
					}
					
					$identity = 0;
					$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
					if ($this->bAlterLog == true) {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
							'LogDescription'  => $argRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId	,'IpAddress' => $sipaddress
						));		
					} else {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $argRoleName,'LogType'  => 'A',
							'LogDescription'  => $argRoleName,'ComputerName'  => $sCName ,'IpAddress' => $sipaddress
						));
					}
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_LogTrans');
					$insert->values(array(
						'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
						'DBName'  => $argDBName,'RefNo'  => $argRefNo	
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//Delete from WF_ApprovalTrans
					$subQuery = $sql->select();
					$subQuery->from( array('a' => 'WF_LogMaster' ))
						->columns(array('LogId'))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
						->where("a.RoleName='$argRoleName' and b.DBName='$argDBName' and b.RegisterId = $argRegId ");
						
					$delete = $sql->delete();
					$delete->from('WF_ApprovalTrans')
						->where("RoleName='$argRoleName' and RegId = $argRegId ");
					$delete->where->expression('LogId IN ?', array($subQuery));	
					$DelStatement = $sql->getSqlStringForSqlObject($delete);			
					$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

					$insert = $sql->insert('WF_ApprovalTrans');
					$insert->values(array(
						'LogId'  => $identity,'RoleName'  => $argRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
						'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
				} else {
					//Delete from WF_ApprovalTrans
					$subQuery = $sql->select();
					$subQuery->from( array('a' => 'WF_LogMaster' ))
						->columns(array('LogId'))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $subQuery::JOIN_INNER)
						->where("a.RoleName='$argRoleName' and b.DBName='$argDBName' and b.RegisterId = $argRegId ");
						
					$delete = $sql->delete();
					$delete->from('WF_ApprovalTrans')
						->where("RoleName='$argRoleName' and RegId = $argRegId ");
					$delete->where->expression('LogId IN ?', array($subQuery));	
					$DelStatement = $sql->getSqlStringForSqlObject($delete);			
					$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//Insert WF_ApprovalTrans					
					$subQueryRole = $sql->select();
					$subQueryRole->from("WF_UserRoleTrans")
						->columns(array('UserId'))
						->where("RoleId='$argRoleId'");

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$argRoleName'"), 'UserId' => new Expression("b.UserId"), 'RegId' => new Expression("$argRegId")
						, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId")  ))
						->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
						->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
						->where("RoleId='$argRoleId'");
//					$select->where->expression('b.UserId IN ?', array($subQueryRole));
	
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('b.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('b.UserId IN ?', array($subQueryUserProj));
					}
					if ($sUserId != "") { 
						//$select->where->expression('b.UserId IN ?', array($sUserId));
						$select->where("b.UserId IN ($sUserId)");
					} else { 
						$select->where("b.UserId= 0");
					}
					
					if ($argValue != 0 && $argValueBase == true) {
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectGreaterTovalue = $sql->select(); 
						$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
						$selectGreaterTovalue->combine($selectBetween,'Union ALL');
						
						$selectGreaterFromTovalue = $sql->select(); 
						$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
						$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
						
						$selectGreaterFromvalue = $sql->select(); 
						$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$argRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
						$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
						
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
						
						$select->where->expression('UserId IN ?', array($selectUser));
					}
					$select->order('c.OrderId Desc');
					
					$insert = $sql->insert();
					$insert->into( 'WF_ApprovalTrans' );
					$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );				
				}
				//Insert WF_ApprovalTrans
				$subQuery = $sql->select();
				$subQuery->from("WF_UserspecialRoleTrans")
					->columns(array('UserId'))
					->where("RoleId='$argRoleId' and Limit <=$argValue ");
					
				$select = $sql->select();
				$select->from( array('a' => 'WF_Users' ))
					->columns(array( 'LogId'=>new Expression("'$argLogId'"),'RoleName'=>new Expression("'$argRoleName'"),'UserId', 'RegId'=>new Expression("$argRegId")
					, 'Status'=>new Expression("1-1"), 'LevelId'=>new Expression("1-1"), 'OrderId'=>new Expression("1-1"), 'Special'=>new Expression("1") ));
				$select->where->expression('a.UserId IN ?', array($subQuery));
				
				$insert = $sql->insert();
				$insert->into( 'WF_ApprovalTrans' );
				$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','Special'));
				$insert->Values( $select );
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				$bSpecial = false;
                $iUserLevelId = 0;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ApprovalTrans"))
					->columns(array("LevelId","Special"))
					->where("a.RoleName = '$argRoleName' and RegId = $argRegId and LogId = $argLogId and Status = 0  ");
				$select->order('OrderId Desc');
				$statement = $sql->getSqlStringForSqlObject($select); 
				$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($taskResult) > 0) {
					$iUserLevelId = $taskResult[0]['LevelId'];
					$bSpecial = $taskResult[0]['Special'];
				}
				
				//Insert WF_PendingWorks
                $selQuryFound=0;
				$select = $sql->select();
				if ($iUserLevelId != 0) {
                    $selQuryFound=1;
					$subQueryRole = $sql->select();
					$subQueryRole->from("WF_Users")
						->columns(array('UserId'))
						->where("LevelId='$iUserLevelId'");

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array( 'PendingRole' => new Expression("'$argRoleName'"), 'RoleType' => new Expression("'A'")
						, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId' ))
						->where("RoleId='$argRoleId'");
					$select->where->expression('a.UserId IN ?', array($subQueryRole));

					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					if ($sUserId != "") { 
						//$select->where->expression('a.UserId IN ?', array($sUserId));
						$select->where("a.UserId IN ($sUserId)");						
					} else { 
						$select->where("a.UserId= 0");
					}	
				} else if ($bSpecial == true) {
                    $selQuryFound=1;
					$subQueryRole = $sql->select();
					$subQueryRole->from("WF_UserspecialRoleTrans")
						->columns(array('UserId'))
						->where("RoleId='$argRoleId' and Limit <= $argValue ");

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_Users"))
						->columns(array( 'PendingRole' => new Expression("'$argRoleName'"), 'RoleType' => new Expression("'A'")
						, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId' ));
					$select->where->expression('a.UserId IN ?', array($subQueryRole));
				}

                if($selQuryFound == 1){
                    $insert = $sql->insert();
                    $insert->into( 'WF_PendingWorks' );
                    $insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId'));
                    $insert->Values( $select );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    //Multiple Feed Insert Start
                    $statementfeed = $sql->getSqlStringForSqlObject($select);
                    $feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($feedUserResult as &$feedUserResults) {
                        $ifeedUserId = $feedUserResults['UserId'];
                        $ifeedLogId = $feedUserResults['LogId'];
                        $ifeedPendingRole = $feedUserResults['PendingRole'];

                        $iPendingWorkId =0;
                        $select = $sql->select();
                        $select->from(array("a"=>"WF_PendingWorks"))
                            ->columns(array("TransId"))
                            ->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='A' and a.NonTask=0 and a.UserId=$ifeedUserId ");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($penResult) > 0) {
                            $iPendingWorkId = $penResult[0]['TransId'];
                            CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
                        }
                    }
                }
				//Multiple Feed Insert End

				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
					->where("a.PendingRole='$argRoleName' and a.LogId=$argLogId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$iId = 0;
				$sREfNo = "";
				$iPUserId = 0;
				$iRemId = 0;
				foreach($pendingResult as $pendingResults){
					$iId = $pendingResults['TransId'];
					$sREfNo = $pendingResults['RefNo'];
					if ($sREfNo != "") { 
						$sREfNo = $argRoleName . " (" . $sREfNo . ")"; 
					} else { 
						$sREfNo = $argRoleName;
					}							
					$iPUserId = $pendingResults['UserId'];
	
					$insert = $sql->insert('WF_ReminderMaster');
					$insert->values(array(
						'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
						'RType'  => 'P' ,'PId'  => $iId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_ReminderTrans');
					$insert->values(array(
						'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
						'Live'  => 1 ));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}			
			}			
		}
	}							
							
	public function UpdateFA($argLogId, $argLogTime, $argCCId, $argRole, $argRegId, $dbAdapter, $argDBName) {
        $bAns = false;
		if ($this->sFAUpdate == "N") { $bAns = true; return $bAns; }
		$bAns = true;
		if ($this->sFAUpdate == "Add") {
			if ($argRole == "Lead-Approval") {
				//if ($bAns == true) { CommonHelper::Update_CRM_Leads($argRegId, $dbAdapter); }
			} else if ($argRole == "Request-For-Creation-Approve") {
                if ($bAns == true) { ProjectHelper::approveFromRFC($argRegId, $dbAdapter); }
            } else if ($argRole == "Tender-Quotation-Approve") {
                if ($bAns == true) { ProjectHelper::_updateTenderQuotation($argRegId, $dbAdapter); }
            } else if ($argRole == "Tender-Workorder-Approve") {
                if ($bAns == true) { ProjectHelper::_updateWorkOrderToProjects($argRegId, $dbAdapter); }
            } else if ($argRole == "WPM-Labour-Master-Approve") {
                if ($bAns == true) { WpmHelper::Approve_Labours($argRegId, $dbAdapter); }
            } else if ($argRole == "PO-Approval") {
                if ($bAns == true) { MMSHelper::Update_PO_Advance($argLogId, $argLogTime, $argCCId, $argRegId, $dbAdapter); }
            }  else if ($argRole == "Bill-Approval" || $argRole == "Bill-Direct-Approval") {
                if ($bAns == true) { MMSHelper::Update_PurchaseBill($argLogId, $argLogTime, $argCCId, $argRegId, $dbAdapter,$argRole); }
            } else if ($argRole == "Bill-Return-Approval") {
                if ($bAns == true) { MMSHelper::Update_Purchase_Return($argRegId, $dbAdapter); }
            } else if ($argRole == "CRM-Receipt-Approval") {
                if ($bAns == true) { CrmHelper::Update_BuyerReceipt($argRegId, $dbAdapter); }
            }
		} else if ($this->sFAUpdate == "Remove") {
			if ($argRole == "Lead-Approval") {
				//if ($bAns == true) { CommonHelper::Remove_CRM_Leads($argRegId, $dbAdapter);}
			} 
		}
		return $bAns;
	}
	
	public function GetSuperiorUsers($argId,$dbAdapter) {
		$this->sSuperiors = "";
		CommonHelper::GetSuperiors($argId,$dbAdapter);
		if ($this->sSuperiors != "") { 
			$this->sSuperiors = rtrim($this->sSuperiors,',');
		}
	}
	
	public function GetSuperiors($argId,$dbAdapter) {
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from('WF_UserSuperiorTrans')
			   ->columns(array('SUserId'))
			   ->where("UserId=$argId");
		$statement = $sql->getSqlStringForSqlObject($select);
		$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		foreach($userSuperiortransResult as &$userSuperiortransResults) {					
			$this->sSuperiors = $this->sSuperiors . $userSuperiortransResults["SUserId"] . ",";
			/*$this->iMaxLevelCount = $this->iMaxLevelCount + 1;
			if ($this->m_iMaxLevel != 0)
			{
				if ($this->iMaxLevelCount < $this->m_iMaxLevel)
				{
					$this->GetSuperiors($userSuperiortransResults["SUserId"], $dbAdapter);
				}
			}
			else
			{
				$this->GetSuperiors($userSuperiortransResults["SUserId"], $dbAdapter);
			}*/
			CommonHelper::GetSuperiors($userSuperiortransResults["SUserId"], $dbAdapter);
		}
	}
	
	/* Start CRM */
//	public function Update_CRM_Leads($argRegId,$dbAdapter) {
//		$sql = new Sql($dbAdapter);
//		$update = $sql->update();
//		$update->table( 'Crm_Leads' )
//			->set( array( 'Approve' => 'Y' ))
//			->where("LeadId=$argRegId ");
//		$statement = $sql->getSqlStringForSqlObject( $update );
//		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//		return true;
//	}
//
//	public function Remove_CRM_Leads($argRegId,$dbAdapter) {
//		$bAns = false;
//		$sql = new Sql($dbAdapter);
//		$update = $sql->update();
//		$update->table( 'Crm_Leads' )
//			->set( array( 'Approve' => 'N' ))
//			->where("LeadId=$argRegId ");
//		$statement = $sql->getSqlStringForSqlObject( $update );
//		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
//		$bAns = true;
//		return $bAns;
//	}
	/* End CRM */

    public function GetVoucherType($argTypeId, $dbAdapter) {
        $sType = "";
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"WF_VoucherTypeMaster"))
            ->columns(array('BaseType'))
            ->where("a.TypeId=$argTypeId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $vouResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($vouResult) > 0) {
            $sType = $vouResult[0]['BaseType'];
        }
        return $sType;
    }

    public function Get_Account_From_Type($arg_iTypeId, $dbAdapter) {
        $AccountId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_AccountMaster"))
            ->columns(array('AccountId'))
            ->where("a.TypeId=$arg_iTypeId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accResult) > 0) {
            $AccountId = $accResult[0]['AccountId'];
        }
        return $AccountId;
    }

    public function Get_Vendor_State($arg_iVendorId, $dbAdapter) {
        $iStateId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"WF_CityMaster"))
            ->columns(array('StateId'))
            ->join(array("b"=>"Vendor_Master"), "a.CityId=b.CityId", array(), $select::JOIN_INNER)
            ->where("b.VendorId=$arg_iVendorId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accResult) > 0) {
            $iStateId = $accResult[0]['StateId'];
        }
        return $iStateId;
    }

    public function Get_Vendor_Branch_State($arg_iBranchId, $arg_iVendorId, $dbAdapter) {
        $iStateId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"WF_CityMaster"))
            ->columns(array('StateId'))
            ->join(array("b"=>"Vendor_Branch"), "a.CityId=b.CityId", array(), $select::JOIN_INNER)
            ->where("b.BranchId=$arg_iBranchId and b.VendorId=$arg_iVendorId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accResult) > 0) {
            $iStateId = $accResult[0]['StateId'];
        }
        return $iStateId;
    }

    public function Get_VAT_Input_Type($arg_iStateId, $dbAdapter) {
        $iVATInputId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"Proj_CommodityStateInputTrans"))
            ->columns(array('InputCredit'))
            ->where("a.StateId=$arg_iStateId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accResult) > 0) {
            $iVATInputId = $accResult[0]['InputCredit'];
        }
        return $iVATInputId;
    }

    public function Check_Entries_Exists_FA($arg_iRefId, $arg_sRefType, $arg_sDBName, $arg_iCompanyId, $dbAdapter) {
        $bFound = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "FA_EntryTrans"))
            ->columns(array('RefId'=> new Expression("TOP 1 RefId")))
            ->where("RefType='$arg_sRefType' AND RefId=$arg_iRefId AND CompanyId=$arg_iCompanyId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $entryResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (count($entryResult) > 0) {
            $bFound = true;
        }
        return $bFound;
    }

    public function Check_Receipt_Exists_FA($arg_iRefId, $arg_sRefType, $arg_sDBName, $dbAdapter) {
        $bFound = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "FA_ReceiptRegister"))
            ->columns(array('RefId'=> new Expression("TOP 1 ReceiptId")))
            ->where("RefType='$arg_sRefType' AND ReferenceId=$arg_iRefId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $entryResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (count($entryResult) > 0) {
            $bFound = true;
        }
        //echo $bFound."dfdf";die;
        return $bFound;
    }

    public function Check_Posting_Lock_FA($arg_iCompanyId, $arg_iFYearId, $arg_dBillDate, $dbAdapter) {
        $bLock = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_FiscalYearTrans"))
            ->columns(array('FYearId'))
            ->where("Freeze=1 AND CompanyId=$arg_iCompanyId AND FYearId=$arg_iFYearId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $accResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($accResult) > 0) {
            $bLock=true;
        }


        if ($bLock == false){

            $select = $sql->select();
            if (CommonHelper::FAFindPermission("Allow-Entries-Posting-Period-After-Lock",$dbAdapter) == true){
                $select->from(array("a"=>"FA_FiscalYearTrans"))
                    ->columns(array('FYearId'))
                    ->where("CompanyId=$arg_iCompanyId AND FYearId=$arg_iFYearId AND LockDate>='$arg_dBillDate'");
            } else {
                $select->from(array("a"=>"FA_FiscalYearTrans"))
                    ->columns(array('FYearId'))
                    ->where("CompanyId=$arg_iCompanyId AND FYearId=$arg_iFYearId AND PostingDate>='$arg_dBillDate'");
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $rowResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($rowResult) > 0) {
                $bLock=true;
            }
        }
        return $bLock;
    }

    public function FAFindPermission($argRoleName,$dbAdapter) {
        $bAns = false;
        $bHRM = false;
        //$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'WF_TaskTrans'))
            ->columns(array('RoleName','ModuleId' => new Expression("b.ModuleId")))
            ->join(array("b"=>"WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
            ->where(array('a.RoleName'=>$argRoleName));
        $selectTaskstmt = $sql->getSqlStringForSqlObject($select);
        $resTaskDet = $dbAdapter->query($selectTaskstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($resTaskDet) > 0) {
            if($resTaskDet[0]['ModuleId'] == 9){ $bHRM = true; }
        }

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'WF_Users'))
            ->columns(array('PowerUser','Lock'))
            ->where(array('a.UserId'=>$this->argUserId));
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $resUserDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($resUserDet) > 0) {
            if($resUserDet[0]['PowerUser'] == 1) { $g_bPowerUser = true;}
        }

        if ($g_bPowerUser == true && $bHRM == false){
            $bAns = true;
        } else {
            if($this->sUserId!='') {
                $select = $sql->select();
                $select->from(array('a' => 'WF_UserRoleTrans'))
                    ->columns(array('RoleName' => new Expression("b.RoleName")))
                    ->join(array("b" => "WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "WF_TaskMaster"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
                    ->where(array('b.RoleName' => $argRoleName));
                $select->where("a.UserId in ('$this->sUserId')");
                $selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
                $resTaskRoleDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (count($resTaskRoleDet) > 0) {
                    $bAns = true;
                }
            }
        }
        /*
         * Select B.RoleName,A.Variant From UserRoleTrans A WITH(READPAST) " +
                   "Inner Join TaskTrans B WITH(READPAST) on A.RoleId=B.RoleId " +
                   "Inner Join TaskMaster C WITH(READPAST) on B.TaskName=C.TaskName " +
                   "Where UserId in  (" + sUserId + ")";
            sSql = sSql + " Union all Select RoleName,0 Variant from TaskTrans WITH(READPAST) Where RoleType='A' and (NotRequired=1 or RoleId not in (Select RoleId from ActivityRoleTrans WITH(READPAST)))

       */
        return $bAns;
    }

    public function GetFAYearId($argCompId, $argDate, $dbAdapter) {
        /*
         * int iFYearId;
        string sSql = String.Format("SELECT FY.FYearId,FY.PFYearId,FY.NFYearId FROM [{0}].dbo.FiscalYearTrans FYT " +
                                    "INNER JOIN [{0}].dbo.FiscalYear FY ON FY.FYearId=FYT.FYearId  " +
                                    "WHERE FYT.CompanyId= {1} AND '{2:dd-MMM-yyyy}' BETWEEN FYT.FromDate AND FYT.ToDate",
                                    BsfGlobal.g_sFaDBName, argCompId, argDate
                                    );

         */
        $iFYearId = 0;
        //FYearGlobal.YearEndTransfer.CFYearId = iFYearId;
        //FYearGlobal.YearEndTransfer.NFYearId = 0;
        //FYearGlobal.YearEndTransfer.PFYearId = 0;
        //FYearGlobal.YearEndTransfer.CompanyId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_FiscalYearTrans"))
            ->columns(array('FYearId' => new Expression("b.FYearId"),'PFYearId' => new Expression("b.PFYearId"),'NFYearId' => new Expression("b.NFYearId")))
            ->join(array("b"=>"FA_FiscalYear"), "a.FYearId=b.FYearId", array(), $select::JOIN_INNER)
            ->where("a.CompanyId=$argCompId AND '$argDate' BETWEEN a.FromDate AND a.ToDate");
        $statement = $sql->getSqlStringForSqlObject($select);
        $fiscalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($fiscalResult) > 0) {
            $iFYearId = $fiscalResult[0]['FYearId'];
            //FYearGlobal.YearEndTransfer.CFYearId = iFYearId;
            //FYearGlobal.YearEndTransfer.NFYearId = Convert.ToInt32(dt.Rows[0]["NFYearId"]);
            //FYearGlobal.YearEndTransfer.PFYearId = Convert.ToInt32(dt.Rows[0]["PFYearId"]);
            //FYearGlobal.YearEndTransfer.CompanyId = argCompId;
        }
        return $iFYearId;
    }

    public function GetSubLedgerId($argId, $argTypeId, $dbAdapter) {
        $iSubLedgerId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_SubLedgerMaster"))
            ->columns(array('SubLedgerId'))
            ->where("a.SubLedgerTypeId=$argTypeId AND a.RefId=$argId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $subLegResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($subLegResult) > 0) {
            $iSubLedgerId = $subLegResult[0]['SubLedgerId'];
        }

        if ($iSubLedgerId == 0){
            CommonHelper::InsertSubLedger($argTypeId, $dbAdapter);
            $select = $sql->select();
            $select->from(array("a"=>"FA_SubLedgerMaster"))
                ->columns(array('SubLedgerId'))
                ->where("a.SubLedgerTypeId=$argTypeId AND a.RefId=$argId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $subLegFinResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($subLegFinResult) > 0) {
                $iSubLedgerId = $subLegFinResult[0]['SubLedgerId'];
            }
        }

        return $iSubLedgerId;
    }

    public function Get_Common_SubLedgerId($argTypeId, $dbAdapter) {
        $iSubLedgerId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_SubLedgerMaster"))
            ->columns(array('SubLedgerId'))
            ->where("a.SubLedgerTypeId=$argTypeId AND a.RefId=0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $subLegResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($subLegResult) > 0) {
            $iSubLedgerId = $subLegResult[0]['SubLedgerId'];
        }
        return $iSubLedgerId;
    }

    public function GetQualId($argId, $dbAdapter) {
        $iQualId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"Proj_Qualifier_Temp"))
            ->columns(array('QualMId'))
            ->where("a.QualifierId=$argId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $qualdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($qualdetResult) > 0) {
            $iQualId = $qualdetResult[0]['QualMId'];
        }
        return $iQualId;
    }

    public function Get_TermsTypeId($sType, $dbAdapter) {
        $iTermsTypeId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"WF_TermsType"))
            ->columns(array('TermsTypeId'))
            ->where("a.TermsName='$sType'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $termdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($termdetResult) > 0) {
            $iTermsTypeId = $termdetResult[0]['TermsTypeId'];
        }
        return $iTermsTypeId;
    }

    public function FindHOCC($argCCId, $dbAdapter) {
        $bFound = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("A"=>"WF_OperationalCostCentre"))
            ->columns(array('HO'=>new Expression("B.HO")))
            ->join(array("B" => "WF_CostCentre"), "A.FACostCentreId=B.CostCentreId", array(), $select::JOIN_INNER)
            ->where("A.CostCentreId=$argCCId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $HOdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($HOdetResult) > 0) {
            if($HOdetResult[0]['HO']==1){$bFound = true;}
        }
        return $bFound;
    }

    public function GetTaxSubLedger($argQualId, $argiStateId, $argPercent, $argServiceTypeId, $dbAdapter) {
        $sql = new Sql($dbAdapter);
        $iSLId = 0;
        $sSLName = "";
        $sQualName = "";
        $iQualTypeId = 0;
        $iStateId = 0;
        $dPercent = 0;
        $iServiceTypeId = 0;
        $iSectionId = 0;

        $select = $sql->select();
        $select->from(array("a"=>"Proj_QualifierMaster"))
            ->columns(array('QualifierName','QualTypeId'=>new Expression("a.QualifierTypeId")))
            ->where("a.QualifierId=$argQualId");//a.QualMId=$argQualId
        $statement = $sql->getSqlStringForSqlObject($select);
        $projQualdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($projQualdetResult) > 0) {
            $sQualName = $projQualdetResult[0]['QualifierName'];
            $iQualTypeId = $projQualdetResult[0]['QualTypeId'];
        }

        if ($iQualTypeId == 1) {
            $iServiceTypeId = $argServiceTypeId;
            $iStateId = 0;
            $dPercent = $argPercent;
        } else if ($iQualTypeId == 2 || $iQualTypeId == 8 || $iQualTypeId == 9 || $iQualTypeId == 12 || $iQualTypeId == 18 || $iQualTypeId == 19 || $iQualTypeId == 21 || $iQualTypeId == 22) {
            $iServiceTypeId = 0;
            $iStateId = 0;
            $dPercent = $argPercent;
        } else if ($iQualTypeId == 3 || $iQualTypeId == 4 || $iQualTypeId == 5) {
            $iServiceTypeId = 0;
            $iStateId = $argiStateId;
            $dPercent = $argPercent;
        } else {
            $iServiceTypeId = 0;
            $iStateId = 0;
            $dPercent = 0;
        }

        $select = $sql->select();
        $select->from(array("a"=>"FA_SubLedgerMaster"))
            ->columns(array('SubLedgerId','SubLedgerName'))
            ->where("a.RefId=$argQualId AND SubLedgerTypeId=8");
        if ($iQualTypeId != 17){
            $select->where("SLRatio=$dPercent AND StateId=$iStateId AND ServiceTypeId=$iServiceTypeId");
        }
        $statement = $sql->getSqlStringForSqlObject($select);
        $subLedgerdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($subLedgerdetResult) > 0) {
            $iSLId = $subLedgerdetResult[0]['SubLedgerId'];
            $sSLName = $subLedgerdetResult[0]['SubLedgerName'];
        }
        $sLedgerName = $sQualName;
        $iSectionId = 0;
        if ($iQualTypeId == 1){
            $select = $sql->select();
            $select->from(array("a"=>"Vendor_ServiceType"))
                ->columns(array('ServiceType','SectionId'))
                ->where("a.ServiceTypeId=$iServiceTypeId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $serTypedetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($serTypedetResult) > 0) {
                $sLedgerName = $sLedgerName . " - " . $serTypedetResult[0]['ServiceType'];
                $iSectionId = $serTypedetResult[0]['SectionId'];
            }

            $select = $sql->select();
            $select->from(array("a"=>"FA_TDSSection"))
                ->columns(array('Section'))
                ->where("a.SectionId=$iSectionId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $TDSSecdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($TDSSecdetResult) > 0) {
                $sLedgerName = $sLedgerName . " [" . $TDSSecdetResult[0]['Section'] . "]";
            }
            $sLedgerName =$sLedgerName . " [" . $dPercent. "%]";
        } else if ($iQualTypeId == 2 || $iQualTypeId == 8 || $iQualTypeId == 9 || $iQualTypeId == 12 || $iQualTypeId == 18 || $iQualTypeId == 19 || $iQualTypeId == 21 || $iQualTypeId == 22){
            $sLedgerName =$sLedgerName . " [" . $dPercent. "%]";
        } else if ($iQualTypeId == 3 || $iQualTypeId == 4 || $iQualTypeId == 5){
            $select = $sql->select();
            $select->from(array("a"=>"WF_Statemaster"))
                ->columns(array('StateName'))
                ->where("a.StateId=$iStateId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $statedetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($statedetResult) > 0) {
                $sLedgerName = $sLedgerName . " - " . $statedetResult[0]['StateName'];
            }
            $sLedgerName =$sLedgerName . " [" . $dPercent. "%]";
        }
        if ($iSLId == 0){
            $iIdentity = 0;
            $insert = $sql->insert();
            $insert->into('FA_SubLedgerMaster');
            $insert->Values(array('SubLedgerName' => $sLedgerName
            , 'SLRatio' => $dPercent
            , 'SubLedgerTypeId' => 8
            , 'RefId' => $argQualId
            , 'StateId' => $iStateId
            , 'ServiceTypeId' => $iServiceTypeId));
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $iIdentity = $dbAdapter->getDriver()->getLastGeneratedValue();
            $iSLId = $iIdentity;
        } else if ($sSLName!=$sLedgerName && $sLedgerName!='') {
            $update = $sql->update();
            $update->table('FA_SubLedgerMaster')
                ->set(array('SubLedgerName' => $sLedgerName));
            $update->where(array('SubLedgerId' => $iSLId));
            $statement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        return $iSLId;
    }

    public function InsertSubLedger($argTypeId, $dbAdapter) {
        $iTypeId = 0;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_SubLedgerType"))
            ->columns(array('SubLedgerTypeId','SubledgerTypeName'))
            ->where("a.SubLedgerTypeId=$argTypeId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $subLegResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($subLegResult as &$subLegResults) {
            $iTypeId = $subLegResults['SubLedgerTypeId'];
            $sSubLedTypeName = $subLegResults['SubledgerTypeName'];
            //#region 1.Vendor
            if(strtoupper($sSubLedTypeName)=="VENDOR"){
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("VendorName"),
                        'AliasName' => new Expression ("ChequeNo from Vendor_Master b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.VendorId ");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Vendor_Master"))
                    ->columns(array( 'VendorName','ChequeNo','SubLedgerTypeId' => new Expression("$iTypeId"), 'VendorId' ));
                $select->where->expression('a.VendorId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'AliasName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            //#endregion
            #region 2.Client
            if(strtoupper($sSubLedTypeName)=="CLIENT") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("ClientName from Proj_ClientMaster b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.ClientId ");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_ClientMaster"))
                    ->columns(array( 'ClientName','SubLedgerTypeId' => new Expression("$iTypeId"),'ClientId' ));
                $select->where->expression('a.ClientId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }
            //#endregion
            #region 3.Buyer
            if(strtoupper($sSubLedTypeName)=="BUYER") {
                $subQueryRole = $sql->select();
                $subQueryRole->from("CRM_UnitBooking")
                    ->columns(array('LeadId' => new Expression("DISTINCT LeadId")));

                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("LeadName from CRM_Leads b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.LeadId");
                $update->where->expression('b.LeadId IN ?', array($subQueryRole));
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryUnit = $sql->select();
                $subQueryUnit->from("CRM_UnitBooking")
                    ->columns(array('LeadId' => new Expression("DISTINCT LeadId")));

                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"CRM_Leads"))
                    ->columns(array( 'LeadName','SubLedgerTypeId' => new Expression("$iTypeId"),'LeadId' ));
                $select->where->expression('a.LeadId NOT IN ?', array($subQueryRole));
                $select->where->expression('a.LeadId IN ?', array($subQueryUnit));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            //#endregion
            #region 4.Employee
            if(strtoupper($sSubLedTypeName)=="EMPLOYEE") {
                /* HRM Need to add
                 * sSql = String.Format("UPDATE [{2}].dbo.SubLedgerMaster SET SubLedgerName = EmployeeName+' ('+EmpCode +' )'  " +
                                                             "FROM [{0}].dbo.EmployeeMaster EM WHERE [{2}].dbo.SubLedgerMaster.SubLedgerTypeId={1} AND [{2}].dbo.SubLedgerMaster.RefId=EM.EmployeeId " +
                                                             "AND EM.PositionId IN (SELECT PositionId FROM [{3}].dbo.Position WHERE PositionType<>'D')",
                                                             BsfGlobal.g_sHRMDBName, iTypeId, BsfGlobal.g_sFaDBName,BsfGlobal.g_sWorkFlowDBName);

                 */

            }
            //#endregion
            #region 5.Asset
            if(strtoupper($sSubLedTypeName)=="ASSET") {
                // Asset Need to add
            }
            //#endregion
            #region 6.Material
            if(strtoupper($sSubLedTypeName)=="MATERIAL" || strtoupper($sSubLedTypeName)=="RESOURCE") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerGroup" )
                    ->set( array( 'SLGroupName' => new Expression ("ResourceGroupName from Proj_ResourceGroup b ")));
                $update->where("FA_SubLedgerGroup.SLGroupTypeId=$iTypeId AND FA_SubLedgerGroup.SLGroupRefId=b.ResourceGroupId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerGroup")
                    ->columns(array('SLGroupRefId'))
                    ->where("SLGroupTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_ResourceGroup"))
                    ->columns(array( 'ResourceGroupName','ResourceGroupId' ,'SLGroupTypeId' => new Expression("$iTypeId")));
                $select->where->expression('a.ResourceGroupId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerGroup');
                $insert->columns(array('SLGroupName', 'SLGroupRefId', 'SLGroupTypeId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("SUBSTRING(ResourceName,1,255) from Proj_Resource b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.ResourceId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_Resource"))
                    ->columns(array( 'ResourceName' => new Expression("SUBSTRING(ResourceName,1,255)") ,'SubLedgerTypeId' => new Expression("$iTypeId"),'ResourceId'));
                $select->where->expression('a.ResourceId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerGroupId' => new Expression ("SLGroupId from FA_SubLedgerMaster SLM JOIN (SELECT SLGroupId, SLGroupRefId,SLGroupTypeId, RR.ResourceId FROM FA_SubLedgerGroup SLG
                        INNER JOIN Proj_Resource RR ON RR.ResourceGroupId=SLG.SLGroupRefId) RRA ON SLM.RefId =RRA.ResourceId
                        AND RRA.SLGroupTypeId=SLM.SubLedgerTypeId AND RRA.SLGroupTypeId=$iTypeId ")));
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            }
            //#endregion
            #region 7.Works
            if(strtoupper($sSubLedTypeName)=="WORKS") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("WorkGroupName from Proj_WorkGroupMaster b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.WorkGroupId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_WorkGroupMaster"))
                    ->columns(array( 'WorkGroupName' ,'SubLedgerTypeId' => new Expression("$iTypeId"),'WorkGroupId'));
                $select->where->expression('a.WorkGroupId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            //#endregion
            #region 8.Tax
            if(strtoupper($sSubLedTypeName)=="TAX") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("c.QualifierName + ' - '+b.StateName + ' ['+CAST (a.SLRatio AS VARCHAR) +'%]' from FA_SubLedgerMaster a JOIN (SELECT StateID, StateName FROM WF_StateMaster) b on a.StateId=b.StateID
                        INNER JOIN Proj_QualifierMaster c ON c.QualifierId=a.RefId AND a.SubLedgerTypeId=8")));
                $update->where("a.SubLedgerTypeId=8 AND a.SLRatio<>0");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
            }
            //#endregion
            #region 9.Miscellaneous
            if(strtoupper($sSubLedTypeName)=="MISCELLANEOUS") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("TermsName from WF_TermsType b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.TermsTypeId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $subQueryIn = $sql->select();
                $subQueryIn->from("WF_TermsMaster")
                    ->columns(array('TermsTypeId'))
                    ->where("AccountUpdate=1");

                $select = $sql->select();
                $select->from(array("a"=>"WF_TermsType"))
                    ->columns(array( 'TermsName' ,'SubLedgerTypeId' => new Expression("$iTypeId"),'TermsTypeId'));
                $select->where->expression('a.TermsTypeId IN ?', array($subQueryIn));
                $select->where->expression('a.TermsTypeId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            //#endregion

            #region 10.Service
            if(strtoupper($sSubLedTypeName)=="SERVICE") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("ServiceName from Proj_ServiceMaster b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.ServiceId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_ServiceMaster"))
                    ->columns(array( 'ServiceName' ,'SubLedgerTypeId' => new Expression("$iTypeId"),'ServiceId'));
                $select->where->expression('a.ServiceId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }
            //#endregion
            #region 11.Group Companies
            if(strtoupper($sSubLedTypeName)=="GROUP COMPANIES") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("CompanyName from WF_CompanyMaster b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.CompanyId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $subQueryIn = $sql->select();
                $subQueryIn->from("FA_FiscalYearTrans")
                    ->columns(array('CompanyId'));

                $select = $sql->select();
                $select->from(array("a"=>"WF_CompanyMaster"))
                    ->columns(array( 'CompanyName' ,'SubLedgerTypeId' => new Expression("$iTypeId"),'CompanyId'));
                $select->where->expression('a.CompanyId IN ?', array($subQueryIn));
                $select->where->expression('a.CompanyId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }
            //#endregion
            #region 15.Project Type
            if(strtoupper($sSubLedTypeName)=="PROJECT TYPE") {
                $update = $sql->update();
                $update->table( "FA_SubLedgerMaster" )
                    ->set( array( 'SubLedgerName' => new Expression ("ProjectTypeName from Proj_ProjectTypeMaster b ")));
                $update->where("FA_SubLedgerMaster.SubLedgerTypeId=$iTypeId AND FA_SubLedgerMaster.RefId=b.ProjectTypeId");
                $statement = $sql->getSqlStringForSqlObject( $update );
                $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                //Insert
                $subQueryRole = $sql->select();
                $subQueryRole->from("FA_SubLedgerMaster")
                    ->columns(array('RefId'))
                    ->where("SubLedgerTypeId=$iTypeId");

                $select = $sql->select();
                $select->from(array("a"=>"Proj_ProjectTypeMaster"))
                    ->columns(array( 'ProjectTypeName' ,'SubLedgerTypeId' => new Expression("$iTypeId"),'ProjectTypeId'));
                $select->where->expression('a.ProjectTypeId NOT IN ?', array($subQueryRole));

                $insert = $sql->insert();
                $insert->into('FA_SubLedgerMaster');
                $insert->columns(array('SubLedgerName', 'SubLedgerTypeId', 'RefId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            //#endregion
        }
    }

    public function Check_Bill_Exists_FA($arg_iRefId, $arg_sRefType, $dbAdapter) {
        $bFound = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"FA_BillRegister"))
            ->columns(array('BillRegisterId' => new Expression("TOP 1 a.BillRegisterId")))
            ->where("a.FromOB=0 AND RefType='$arg_sRefType' AND a.ReferenceId=$arg_iRefId");
         $statement = $sql->getSqlStringForSqlObject($select);
        $subLegResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($subLegResult) > 0) {
            $bFound = true;
        }
        return $bFound;
    }

    public function InsertPaymentAdvice($argLogId, $argLogTime, $argCCId, $dbAdapter) {
        $sNextRoleName = "Payment-Advice-Add";
        $sNextRoleType = "N";
        $iNextRoleId = 0;
        //Select RoleId from TaskTrans WITH(READPAST)  Where RoleName='" + sNextRoleName + "'
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a"=>"WF_TaskTrans"))
            ->columns(array('RoleId'))
            ->where("a.RoleName='$sNextRoleName'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $tasktransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($tasktransResult) > 0) {
            $iNextRoleId = $tasktransResult[0]['RoleId'];
        }

        if ($iNextRoleId != 0) {
            $sIntType = "";
            $iIntPeriod = 0;
            $sProcessType = "";
            $iProcessPeriod = 0;
            $dProcessDate="";

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array("a"=>"WF_RoleTrans"))
                ->columns(array('ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
                ->where("a.RoleId=$iNextRoleId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $roletransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(count($roletransResult) > 0) {
                $sIntType = $roletransResult[0]['IntervalType'];
                $iIntPeriod = $roletransResult[0]['IntervalPeriod'];
                $sProcessType = $roletransResult[0]['ProcessType'];
                $iProcessPeriod = $roletransResult[0]['ProcessPeriod'];
            }

            if ($sIntType == "None") { $iIntPeriod = 0; }
            if ($sProcessType == "None") { $iProcessPeriod = 0; }
            //dProcessDate = DateTime.MinValue;
            if ($iProcessPeriod != 0) {
                $dProcessDate = $argLogTime;
                if ($sProcessType == "Minutes") {
                    $lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));
                } else if ($sProcessType == "Hour") {
                    $lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
                } else if ($sProcessType == "Day") {
                    $lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
                } else if ($sProcessType == "Week") {
                    $lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
                } else if ($sProcessType == "Month") {
                    $lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
                } else if ($sProcessType == "Year") {
                    $lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
                }
            }
            if ($iIntPeriod != 0) {
                if ($sIntType == "Minutes") {
                    $lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
                } else if ($sIntType == "Hour") {
                    $lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
                } else if ($sIntType == "Day") {
                    $lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
                } else if ($sIntType == "Week") {
                    $lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
                } else if ($sIntType == "Month") {
                    $lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
                } else if ($sIntType == "Year") {
                    $lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
                    $dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
                    //$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
                }
            }

            //Insert WF_PendingWorks
            $select = $sql->select();
            $select->from(array("a"=>"WF_UserRoleTrans"))
                ->columns(array( 'PendingRole' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
                , 'NonTask' => new Expression("0"), 'LogId' => new Expression("$argLogId"), 'UserId'
                , 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"),'ActivityId' ))
                ->join(array("b"=>"WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array(), $select::JOIN_INNER)
                ->where("a.RoleId='$iNextRoleId'");
            if ($argCCId != 0) {
                $subQueryUserCC = $sql->select();
                $subQueryUserCC->from("WF_UserCostCentreTrans")
                    ->columns(array('UserId'))
                    ->where("CostCentreId='$argCCId'");

                $select->where->expression('UserId IN ?', array($subQueryUserCC));
            }
            $insert = $sql->insert();
            $insert->into( 'WF_PendingWorks' );
            $insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime','ActivityId'));
            $insert->Values( $select );
            $statement = $sql->getSqlStringForSqlObject( $insert );
            $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

            $select = $sql->select();
            $select->from(array("a"=>"WF_PendingWorks"))
                ->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
                ->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
                ->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
            $statement = $sql->getSqlStringForSqlObject($select);
            $pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $iId = 0;
            $sREfNo = "";
            $iPUserId = 0;
            $iRemId = 0;
            foreach($pendingResult as $pendingResults){
                $iId = $pendingResults['TransId'];
                $sREfNo = $pendingResults['RefNo'];
                if ($sREfNo != "") {
                    $sREfNo = $sNextRoleName . " (" . $sREfNo . ")";
                } else {
                    $sREfNo = $sNextRoleName;
                }
                $iPUserId = $pendingResults['UserId'];

                $insert = $sql->insert('WF_ReminderMaster');
                $insert->values(array(
                    'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
                    'RType'  => 'P' ,'PId'  => $iId
                ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $insert = $sql->insert('WF_ReminderTrans');
                $insert->values(array(
                    'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
                    'Live'  => 1 ));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
        }
    }

	public function InsertPendingWork($argLogTime,$argRoleName,$argRegId,$argLogId,$argDBName,$dbAdapter,$argUserId,$argCCId,$argValue,$argCompanyId,$argRefNo, $lUserId) {
        $iRoleId = 0;
        $iActivityId = 0;
        $iNextActivityId = 0;
        $sRoleType = "";
        $sTaskName = "";
        $sTableName = "";
        $sFieldName = "";
        $this->sNextupdateRole = "";
        $bMultiApproval = false;
        $bAppComplete = false;
        $bApprovalNotRequired = false;
        $sApprove = "";
        $sIntType = "";
        $sProcessType = "";
        $iIntPeriod = 0;
        $iProcessPeriod = 0;
		$lastest_timestamp = strtotime('+0 days');
		$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
        $sNextRoleApprove = "";
        $iUserLevelId = 0;
        $iProjId = 0;
        $iPendRoleId = 0;
        $sPendRoleName = "";
        $sPendRoleType = "";
        $iMaxLevel = 0;
		$sipaddress =  CommonHelper::get_client_ip();
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from('Proj_ProjectMaster')
			   ->columns(array('ProjectId'))
			   ->where("ProjectName='$argDBName'");
		 $statement = $sql->getSqlStringForSqlObject($select);
		$projListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($projListResult) > 0) {
			$iProjId = $projListResult[0]['ProjectId'];
		}

		//Get start Auto Approval
		$bAutoApproval = true;
		$select = $sql->select();
		$select->from('WF_GeneralSetting')
			   ->columns(array('AutoApproval'));
		 $statement = $sql->getSqlStringForSqlObject($select);
		$autoApprovalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($autoApprovalResult) > 0) {
			$bAutoApproval = $autoApprovalResult[0]['AutoApproval'];
		}
		
		//Get end Auto Approval
		
		$select = $sql->select();
		$select->from('WF_TaskTrans')
			   ->columns(array('RoleId','RoleType','TaskName','MultiApproval','ApprovalBased','MaxLevel'))
			   ->where("RoleName='$argRoleName'");
		 $statement = $sql->getSqlStringForSqlObject($select);
		$tsaktransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($tsaktransResult) > 0) {
			$iRoleId = $tsaktransResult[0]['RoleId'];
            $sRoleType = $tsaktransResult[0]['RoleType'];
            $sTaskName = $tsaktransResult[0]['TaskName'];
            $bMultiApproval = $tsaktransResult[0]['MultiApproval'];
            $sApprove = $tsaktransResult[0]['ApprovalBased'];
            $iMaxLevel = $tsaktransResult[0]['MaxLevel'];
		}
		if ($bMultiApproval == true && $sApprove == "") { $sApprove = "L"; }
		
		$select = $sql->select(); 
		$select->from(array("a"=>"WF_TaskMaster"))
			->columns(array("TableName","FieldName"))
			->where("a.TaskName='$sTaskName' ");
		$statement = $sql->getSqlStringForSqlObject($select);
		$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($taskResult) > 0) {
			$sTableName = $taskResult[0]['TableName'];
			$sFieldName = $taskResult[0]['FieldName'];
		}

		$select = $sql->select();
		$select->from(array("a"=>"WF_ActivityTaskTrans"))
            ->join(array("b"=>"WF_TaskMaster"), "a.TaskId=b.TaskId", array(), $select::JOIN_INNER)
            ->join(array("c"=>"WF_TaskTrans"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
			->columns(array("ActivityId"))
			->where("c.RoleId='$iRoleId' ");
		$statement = $sql->getSqlStringForSqlObject($select);
		$activityResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($activityResult) > 0) {
			$iActivityId = $activityResult[0]['ActivityId'];
		}

		$iNextRoleId = 0;
        $sNextRoleName = "";
        $sNextRoleType = "";
        $bNextMulti = false;
        $bValueApproval = false;
        $bSpecialApprovalRequired = false;
		
		if ($sRoleType != "A") {
			$iNextRoleId = 0;
			
			$subQuery = $sql->select();
			$subQuery->from("WF_ActivityCriticalTrans")
				->columns(array('OrderId' => new Expression("OrderId+1")))
				->where("ActivityId=$iActivityId and RoleId= $iRoleId");
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_ActivityCriticalTrans"))
				->columns(array("RoleId"))
				->where("ActivityId=$iActivityId");
			$select->where->expression('OrderId IN ?', array($subQuery));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$criticaltranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($criticaltranResult) > 0) {
				$iNextRoleId = $criticaltranResult[0]['RoleId'];
			}

			if ($iNextRoleId == 0 && $iActivityId == 0 && $sRoleType == "N") {
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskTrans"))
					->columns(array("RoleId"))
					->where("RoleType='A' and TaskName = '$sTaskName' ");
				//$select->where->expression('OrderId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject($select);
				$tranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($tranResult) > 0) {
					$iNextRoleId = $tranResult[0]['RoleId'];
				}				
			}
			//echo $iNextRoleId;
			//echo $iActivityId;

			if ($iNextRoleId == 0) {
				if ($iActivityId != 0){				
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_ActivityMaster"))
						->columns(array("ActivityId"))
						->where("PrevActivityId=$iActivityId ");
					//$select->where->expression('SortOrder IN ?', array($subQuery));
					$statement = $sql->getSqlStringForSqlObject($select);
					$actmasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($actmasterResult) > 0) {
						$iNextActivityId = $actmasterResult[0]['ActivityId'];
					}
				}
				//echo $iNextActivityId;
				if ($iNextActivityId != 0) {
					$sNextRoleApprove = "";
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_ActivityCriticalTrans"))
						->columns(array('RoleId'), array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'))
						->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'), $select::JOIN_INNER)
						->where("a.ActivityId='$iNextActivityId' and a.OrderId=1 ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$criticalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($criticalResult) > 0) {
						$iNextRoleId = $criticalResult[0]['RoleId'];
						$sNextRoleName = $criticalResult[0]['RoleName'];
						$bNextMulti = $criticalResult[0]['MultiApproval'];
						$sNextRoleType = $criticalResult[0]['RoleType'];
						$bValueApproval = $criticalResult[0]['ValueApproval'];
						$sNextRoleApprove = $criticalResult[0]['ApprovalBased'];
						$iMaxLevel = $criticalResult[0]['MaxLevel'];
					}
					if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }
				}
			} else {
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskTrans"))
					->columns(array('RoleId','RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'))
					->where("a.RoleId='$iNextRoleId'");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$tsakdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($tsakdetResult) > 0) {
					$iNextRoleId = $tsakdetResult[0]['RoleId'];
					$sNextRoleName = $tsakdetResult[0]['RoleName'];
					$bNextMulti = $tsakdetResult[0]['MultiApproval'];
					$sNextRoleType = $tsakdetResult[0]['RoleType'];
					$bValueApproval = $tsakdetResult[0]['ValueApproval'];
					$sNextRoleApprove = $tsakdetResult[0]['ApprovalBased'];
					$iMaxLevel = $tsakdetResult[0]['MaxLevel'];
				}
				
				if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }
			}

			if ($iNextRoleId != 0) { 
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_RoleTrans"))
					->columns(array('ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
					->where("a.RoleId='$iNextRoleId'");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($roledetResult) > 0) {
					$sIntType = $roledetResult[0]['IntervalType'];
					$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
					$sProcessType = $roledetResult[0]['ProcessType'];
					$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];
				}

				if ($sIntType == "None") { $iIntPeriod = 0; }
                if ($sProcessType == "None") { $iProcessPeriod = 0; }
				//var_dump($dProcessDate);//$dProcessDate = "2000-01-01 01:00:00";
				if ($iProcessPeriod != 0) {
					$dProcessDate = $argLogTime;
					if ($sProcessType == "Minutes") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
					} else if ($sProcessType == "Hour") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
					} else if ($sProcessType == "Day") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
					} else if ($sProcessType == "Week") {
						$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
					} else if ($sProcessType == "Month") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
					} else if ($sProcessType == "Year") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
					}
				}
				if ($iIntPeriod != 0) {
					if ($sIntType == "Minutes") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
					} else if ($sIntType == "Hour") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
					} else if ($sIntType == "Day") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
					} else if ($sIntType == "Week") {
						$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
					} else if ($sIntType == "Month") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
					} else if ($sIntType == "Year") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
					}					
				}
				if ($sNextRoleType == "A") {
					$bApprovalNotRequired = false;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_TaskTrans"))
						->columns(array('RoleId'))
						->where("a.RoleId='$iNextRoleId' and NotRequired=1");
					$statement = $sql->getSqlStringForSqlObject($select);
					$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($roledetResult) > 0) {
						$bApprovalNotRequired = true;
					}

					$sUserId = "";
                    $this->m_iMaxLevel = $iMaxLevel;
                    CommonHelper::GetSuperiorUsers($argUserId, $dbAdapter);

					if ($this->sSuperiors != "") {
						$subQueryRole = $sql->select();
						$subQueryRole->from("WF_TaskTrans")
							->columns(array('RoleId'))
							->where("RoleName='$sNextRoleName'");

						$select = $sql->select(); 
						$select->from(array("a"=>"WF_UserRoleTrans"))
							->columns(array('UserId' ));
						$select->where->expression('a.RoleId IN ?', array($subQueryRole));
						//$select->where->expression('a.UserId IN ?', array($this->sSuperiors));
						$select->where("a.UserId IN ($this->sSuperiors)");
		
						if($argCCId != 0) {
							$subQueryUserCC = $sql->select();
							$subQueryUserCC->from("WF_UserCostCentreTrans")
								->columns(array('UserId'))
								->where("CostCentreId='$argCCId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
						}
						if($iProjId != 0) {
							$subQueryUserProj = $sql->select();
							$subQueryUserProj->from("WF_UserProjectTrans")
								->columns(array('UserId'))
								->where("ProjectId='$iProjId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
						}

						if ($bNextMulti == false) {
                            if ($argValue != 0 && $bValueApproval == true) {
								$selectBetween = $sql->select(); 
								$selectBetween->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId");
								$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
								
								$selectLesser = $sql->select(); 
								$selectLesser->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
								$selectLesser->combine($selectBetween,'Union ALL');
								
								$selectUser = $sql->select(); 
								$selectUser->from(array("a"=>"WF_Users"))
									->columns(array("UserId"));
								$selectUser->where->expression('LevelId IN ?', array($selectLesser));
								
								$select->where->expression('UserId IN ?', array($selectUser));
							}							
						} else {
							if ($argValue != 0 && $bValueApproval == true) {
							
								$selectBetween = $sql->select(); 
								$selectBetween->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId");
								$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
								$selectGreaterTovalue = $sql->select(); 
								$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
								$selectGreaterTovalue->combine($selectBetween,'Union ALL');
								
								$selectGreaterFromTovalue = $sql->select(); 
								$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
								$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
								
								$selectGreaterFromvalue = $sql->select(); 
								$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
								$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
								
								$selectUser = $sql->select(); 
								$selectUser->from(array("a"=>"WF_Users"))
									->columns(array("UserId"));
								$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
								
								$select->where->expression('UserId IN ?', array($selectUser));
							}
						}
						$statement = $sql->getSqlStringForSqlObject($select);
						$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($userSuperiortransResult as &$userSuperiortransResults) {					
							$sUserId = $sUserId . $userSuperiortransResults["UserId"] . ",";						
						}
						if($sUserId!="") {
							$sUserId = rtrim($sUserId,',');
						} else {
							$selectQuery = $sql->select();
							$selectQuery->from(array("a"=>"WF_TaskTrans"))
								->columns(array("RoleId"))				
								->where("a.RoleName='$sNextRoleName' ");
									
							$select = $sql->select();
							$select->from('WF_UserRoleTrans')
								   ->columns(array('UserId'))
								   ->where("UserId=$argUserId");	   
							$select->where->expression('RoleId IN ?', array($selectQuery));
							$statement = $sql->getSqlStringForSqlObject($select);
							$userResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($userResult) > 0) {
								$sUserId = $userResult[0]['UserId'];
							}
						}					
					}
					if ($bAutoApproval == false && $sUserId == "") {
						$subQueryRole = $sql->select();
						$subQueryRole->from("WF_TaskTrans")
							->columns(array('RoleId'))
							->where("RoleName='$sNextRoleName'");

						$select = $sql->select(); 
						$select->from(array("a"=>"WF_UserRoleTrans"))
							->columns(array('UserId' ))
							->where("UserId='$argUserId'");
						$select->where->expression('a.RoleId IN ?', array($subQueryRole));
		
						if($argCCId != 0) {
							$subQueryUserCC = $sql->select();
							$subQueryUserCC->from("WF_UserCostCentreTrans")
								->columns(array('UserId'))
								->where("CostCentreId='$argCCId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
						}
						if($iProjId != 0) {
							$subQueryUserProj = $sql->select();
							$subQueryUserProj->from("WF_UserProjectTrans")
								->columns(array('UserId'))
								->where("ProjectId='$iProjId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
						}
						if ($bNextMulti == false){
							if ($argValue != 0 && $bValueApproval == true) {
								$selectBetween = $sql->select(); 
								$selectBetween->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId");
								$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
								
								$selectLesser = $sql->select(); 
								$selectLesser->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
								$selectLesser->combine($selectBetween,'Union ALL');
								
								$selectUser = $sql->select(); 
								$selectUser->from(array("a"=>"WF_Users"))
									->columns(array("UserId"));
								$selectUser->where->expression('LevelId IN ?', array($selectLesser));
								
								$select->where->expression('UserId IN ?', array($selectUser));
							}
						} else {
							if ($argValue != 0 && $bValueApproval == true) {
							
								$selectBetween = $sql->select(); 
								$selectBetween->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId");
								$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
								
								$selectGreaterTovalue = $sql->select(); 
								$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
								$selectGreaterTovalue->combine($selectBetween,'Union ALL');
						
								$selectGreaterFromTovalue = $sql->select(); 
								$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
								$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
								
								$selectGreaterFromvalue = $sql->select(); 
								$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
								$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
								
								$selectUser = $sql->select(); 
								$selectUser->from(array("a"=>"WF_Users"))
									->columns(array("UserId"));
								$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
								
								$select->where->expression('UserId IN ?', array($selectUser));
							}
						}			
						$statement = $sql->getSqlStringForSqlObject($select);
						$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($userSuperiortransResult) > 0) {
							$sUserId = $userSuperiortransResult[0]['UserId'];
						}
	
					}
					$bSpecialApprovalRequired = false;

					$selectQuery = $sql->select(); 
					$selectQuery->from(array("a"=>"WF_UserspecialRoleTrans"))
						->columns(array("UserId"))				
						->where("a.RoleId='$iNextRoleId' and a.Limit <= $argValue ");

					$select = $sql->select();
					$select->from('WF_Users')
						   ->columns(array('UserId'));	   
					$select->where->expression('UserId IN ?', array($selectQuery));
					$statement = $sql->getSqlStringForSqlObject($select);
					$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($taskResult) > 0) {
						$bSpecialApprovalRequired = true;
					}

                    if (($sUserId != "" || $this->sSuperiors != "" || $bAutoApproval == false || $bSpecialApprovalRequired == true) && $bApprovalNotRequired == false) {
						if ($bNextMulti == false){ $bUserApproval = false;
							$select = $sql->select();
							$select->from('WF_UserRoleTrans')
								   ->columns(array('RoleId'))
								   ->where("UserId='$argUserId' and RoleId = $iNextRoleId ");
							$statement = $sql->getSqlStringForSqlObject($select);
							$userRoleResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($userRoleResult) > 0) {
								$bUserApproval = true;
							}
							
							if ($bUserApproval == true && $bAutoApproval == true) {
                                $iLevelId = 0;
                                $iOrderId = 0;
								$selectQuery = $sql->select(); 
								$selectQuery->from(array("a"=>"WF_Users"))
									->columns(array("LevelId"))				
									->where("a.UserId='$argUserId'");
										
								$select = $sql->select();
								$select->from('WF_LevelMaster')
									   ->columns(array('LevelId','OrderId'));	   
								$select->where->expression('LevelId IN ?', array($selectQuery));
								$statement = $sql->getSqlStringForSqlObject($select);
								$levelResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								if(count($levelResult) > 0) {
									$iLevelId = $levelResult[0]['LevelId'];
                                    $iOrderId = $levelResult[0]['OrderId'];
								}
								
								$identity = 0;
								$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
								if ($this->bAlterLog == true) {
									$insert = $sql->insert('WF_LogMaster');
									$insert->values(array(
										'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sNextRoleName,'LogType'  => 'A',
										'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId ,'IpAddress' => $sipaddress
									));		
								} else {
									$insert = $sql->insert('WF_LogMaster');
									$insert->values(array(
										'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sNextRoleName,'LogType'  => 'A',
										'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress
									));
								}
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
							
								$insert = $sql->insert('WF_LogTrans');
								$insert->values(array(
									'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
									'DBName'  => $argDBName,'RefNo'  => $argRefNo	
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								$insert = $sql->insert('WF_ApprovalTrans');
								$insert->values(array(
									'LogId'  => $identity,'RoleName'  => $sNextRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
									'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

								if($sTableName != "" && $sFieldName != "") {
									$update = $sql->update();
									$update->table( "$sTableName" )
										->set( array( 'Approve' => 'Y' ))
										->where("$sFieldName=$argRegId ");
									$statement = $sql->getSqlStringForSqlObject( $update ); 
									$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
								}
                                $this->sNextupdateRole = $sNextRoleName;
                                $this->sFAUpdate = "Add";
								$iRoleId = $iNextRoleId;								
								goto CheckNextActivity;
								//echo "goto CheckNextActivity- 2949,";
							} else {
								//Insert WF_ApprovalTrans
								
								$select = $sql->select(); 
								$select->from(array("a"=>"WF_UserRoleTrans"))
									->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sNextRoleName'"), 'UserId', 'RegId' => new Expression("$argRegId")
									, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId"), 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
									->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
									->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
									->where("RoleId='$iNextRoleId'");

								if($argCCId != 0) {
									$subQueryUserCC = $sql->select();
									$subQueryUserCC->from("WF_UserCostCentreTrans")
										->columns(array('UserId'))
										->where("CostCentreId='$argCCId'");
								
									$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
								}
								if($iProjId != 0) {
									$subQueryUserProj = $sql->select();
									$subQueryUserProj->from("WF_UserProjectTrans")
										->columns(array('UserId'))
										->where("ProjectId='$iProjId'");
								
									$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
								}
								if ($sUserId != "") { 
									//$select->where->expression('a.UserId IN ?', array($sUserId));
									$select->where("a.UserId IN ($sUserId)");
								} else { 
									$select->where("a.UserId= 0");
								}
								
								if ($argValue != 0 && $bValueApproval == true) {
									$selectBetween = $sql->select(); 
									$selectBetween->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId");
									$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
	
									$selectLesser = $sql->select(); 
									$selectLesser->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
									$selectLesser->combine($selectBetween,'Union ALL');
									
									$selectUser = $sql->select(); 
									$selectUser->from(array("a"=>"WF_Users"))
										->columns(array("UserId"));
									$selectUser->where->expression('LevelId IN ?', array($selectLesser));
									
									$select->where->expression('a.UserId IN ?', array($selectUser));
								}
								$select->order('c.OrderId Desc');
								
								$insert = $sql->insert();
								$insert->into( 'WF_ApprovalTrans' );
								$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
								$insert->Values( $select );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
								
								//Insert WF_PendingWorks
								$select = $sql->select(); 
								$select->from(array("a"=>"WF_UserRoleTrans"))
									->columns(array( 'RoleName' => new Expression("'$sNextRoleName'"), 'Type' => new Expression("'$sNextRoleType'")
									, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId',  'StartTime' => new Expression("'$argLogTime'"),  'DueTime' => new Expression("'$dProcessDate'")  ))
									->where("a.RoleId='$iNextRoleId'");
																		
								if($argCCId != 0) {
									$subQueryUserCC = $sql->select();
									$subQueryUserCC->from("WF_UserCostCentreTrans")
										->columns(array('UserId'))
										->where("CostCentreId='$argCCId'");
									$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
								}
								if($iProjId != 0) {
									$subQueryUserProj = $sql->select();
									$subQueryUserProj->from("WF_UserProjectTrans")
										->columns(array('UserId'))
										->where("ProjectId='$iProjId'");
									$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
								}
								if ($sUserId != "") { 
									//$select->where->expression('a.UserId IN ?', array($sUserId));
									$select->where("a.UserId IN ($sUserId)");
								} else { 
									$select->where("a.UserId= 0");
								}
								if ($argValue != 0 && $bValueApproval == true) {
									$selectBetween = $sql->select(); 
									$selectBetween->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId");
									$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
									
									$selectLesser = $sql->select(); 
									$selectLesser->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
									$selectLesser->combine($selectBetween,'Union ALL');
									
									$selectUser = $sql->select(); 
									$selectUser->from(array("a"=>"WF_Users"))
										->columns(array("UserId"));
									$selectUser->where->expression('LevelId IN ?', array($selectLesser));
									
									$select->where->expression('a.UserId IN ?', array($selectUser));		
								}
								
								$insert = $sql->insert();
								$insert->into( 'WF_PendingWorks' );
								$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
								$insert->Values( $select );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
																
								//Multiple Feed Insert Start
								$statementfeed = $sql->getSqlStringForSqlObject($select); 
								$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								foreach($feedUserResult as &$feedUserResults) {
									$ifeedUserId = $feedUserResults['UserId'];
									$ifeedLogId = $feedUserResults['LogId'];
									$ifeedPendingRole = $feedUserResults['RoleName'];
									$ifeedType = $feedUserResults['Type'];
									
									$iPendingWorkId =0;
									$select = $sql->select(); 
									$select->from(array("a"=>"WF_PendingWorks"))
										->columns(array("TransId"))
										->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
									$statement = $sql->getSqlStringForSqlObject($select); 
									$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
									if(count($penResult) > 0) {
										$iPendingWorkId = $penResult[0]['TransId'];
										CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
									}
								}
								//Multiple Feed Insert End
								
								$select = $sql->select(); 
								$select->from(array("a"=>"WF_PendingWorks"))
									->columns(array("TransId","UserId"), array("RefNo"))
									->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
									->where("a.PendingRole='$sNextRoleName' And a.LogId=$argLogId ");
								$statement = $sql->getSqlStringForSqlObject($select); 
								$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								$iId = 0;
								$sREfNo = "";
								$iPUserId = 0;
								$iRemId = 0;
								foreach($logPendingResult as &$logPendingResults) {
									$iId = $logPendingResults['TransId'];
									$sREfNo = $logPendingResults['RefNo'];
									if ($sREfNo != "") { 
									$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
									} else { $sREfNo = $sNextRoleName; 
									}
									$iPUserId = $logPendingResults['UserId'];
									$insert = $sql->insert('WF_ReminderMaster');
									$insert->values(array(
										'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId
									));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
									$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
									$insert = $sql->insert('WF_ReminderTrans');
									$insert->values(array(
										'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
									));
									$statement = $sql->getSqlStringForSqlObject($insert);
									$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);									
								}
							}						
						} else {
							if ($sUserId == "" && $this->sSuperiors == "" && $bAutoApproval == true) {
								$iLevelId = 0;
                                $iOrderId = 0;
								
								$selectQuery = $sql->select(); 
								$selectQuery->from(array("a"=>"WF_Users"))
									->columns(array("LevelId"))				
									->where("a.UserId='$argUserId'");
										
								$select = $sql->select();
								$select->from('WF_LevelMaster')
									   ->columns(array('LevelId', 'OrderId'));	   
								$select->where->expression('LevelId IN ?', array($selectQuery));
								$statement = $sql->getSqlStringForSqlObject($select);
								$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								if(count($levelMasterResult) > 0) {
									$iLevelId = $levelMasterResult[0]['LevelId'];
									$iOrderId = $levelMasterResult[0]['OrderId'];
								}

								$identity = 0;
								$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
								//argLogTime insert
								$insert = $sql->insert('WF_LogMaster');
								if ($this->bAlterLog == true) {									
									$insert->values(array(
										'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sNextRoleName,'LogType'  => 'A',
										'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId,'IpAddress' => $sipaddress
									));		
								} else {
									$insert->values(array(
										'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sNextRoleName,'LogType'  => 'A',
										'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress
									));
								}
								$statement = $sql->getSqlStringForSqlObject($insert);
								$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
								
								$insert = $sql->insert('WF_LogTrans');
								$insert->values(array(
									'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
									'DBName'  => $argDBName,'RefNo'  => $argRefNo	
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								
								$insert = $sql->insert('WF_ApprovalTrans');
								$insert->values(array(
									'LogId'  => $identity,'RoleName'  => $sNextRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
									'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							} else{
								//Insert WF_ApprovalTrans
//								$subQueryUser = $sql->select();
//								$subQueryUser->from("WF_UserRoleTrans")
//									->columns(array('UserId'))
//										->where("RoleId='$iNextRoleId'");
										
								$select = $sql->select(); 
								$select->from(array("a"=>"WF_UserRoleTrans"))
									->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sNextRoleName'"), 'UserId' => new Expression("b.UserId"), 'RegId' => new Expression("$argRegId")
									, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId"), 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
									->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
									->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
									->where("RoleId='$iNextRoleId'");

								//$select->where->expression('b.UserId IN ?', array($subQueryUser));
								
								if($argCCId != 0) {
									$subQueryUserCC = $sql->select();
									$subQueryUserCC->from("WF_UserCostCentreTrans")
										->columns(array('UserId'))
										->where("CostCentreId='$argCCId'");							
									$select->where->expression('b.UserId IN ?', array($subQueryUserCC));
								}
								if($iProjId != 0) {
									$subQueryUserProj = $sql->select();
									$subQueryUserProj->from("WF_UserProjectTrans")
										->columns(array('UserId'))
										->where("ProjectId='$iProjId'");
									$select->where->expression('b.UserId IN ?', array($subQueryUserProj));
								}
								if ($sUserId != "") { 
									//$select->where->expression('b.UserId IN ?', array($sUserId));
									$select->where("b.UserId IN ($sUserId)");
								} else { 
									$select->where("b.UserId= 0");
								}


								if ($argValue != 0 && $bValueApproval == true) {
									$selectBetween = $sql->select(); 
									$selectBetween->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId");
									$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
									
									$selectGreaterTovalue = $sql->select(); 
									$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
									$selectGreaterTovalue->combine($selectBetween,'Union ALL');
									
									$selectGreaterFromTovalue = $sql->select(); 
									$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
									$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
									
									$selectGreaterFromvalue = $sql->select(); 
									$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
										->columns(array("LevelId"))				
										->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
									$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
									
									$selectUser = $sql->select(); 
									$selectUser->from(array("a"=>"WF_Users"))
										->columns(array("UserId"));
									$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
									
									$select->where->expression('b.UserId IN ?', array($selectUser));
								}
								$select->order('c.OrderId Desc');

								$insert = $sql->insert();
								$insert->into( 'WF_ApprovalTrans' );
								$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
								$insert->Values( $select );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							}
							//Insert WF_ApprovalTrans
							$subQueryUser = $sql->select();
							$subQueryUser->from("WF_UserspecialRoleTrans")
								->columns(array('UserId'))
								->where("RoleId='$iNextRoleId' and Limit <= $argValue ");
									
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_Users"))
								->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sNextRoleName'"), 'UserId' , 'RegId' => new Expression("$argRegId")
								, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("1-1"), 'OrderId' => new Expression("1-1")
								, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"), 'Special' => new Expression("1")  ));
							$select->where->expression('UserId IN ?', array($subQueryUser));
								
							$insert = $sql->insert();
							$insert->into( 'WF_ApprovalTrans' );
							$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime','Special'));
							$insert->Values( $select );
							$statement = $sql->getSqlStringForSqlObject( $insert );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							
							$bSpecial = false;
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_ApprovalTrans"))
								->columns(array("LevelId","Special"))
								->where("a.RoleName = '$sNextRoleName' and RegId = $argRegId and LogId = $argLogId and Status = 0");
							$select->order('OrderId Desc');
							$statement = $sql->getSqlStringForSqlObject($select); 
							$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($taskResult) > 0) {
								$iUserLevelId = $taskResult[0]['LevelId'];
								$bSpecial = $taskResult[0]['Special'];
							}

							//Insert WF_PendingWorks
							$select = $sql->select();
							if ($iUserLevelId != 0){
								$subQueryUser = $sql->select();
								$subQueryUser->from("WF_Users")
									->columns(array('UserId'))
									->where("LevelId='$iUserLevelId'");
										
								$select->from(array("a"=>"WF_UserRoleTrans"))
									->columns(array( 'RoleName' => new Expression("'$sNextRoleName'"), 'Type' => new Expression("'$sNextRoleType'")
									, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId',  'StartTime' => new Expression("'$argLogTime'"),  'DueTime' => new Expression("'$dProcessDate'")  ))
									->where("a.RoleId='$iNextRoleId'");
								$select->where->expression('a.UserId IN ?', array($subQueryUser));
																		
								if($argCCId != 0) {
									$subQueryUserCC = $sql->select();
									$subQueryUserCC->from("WF_UserCostCentreTrans")
										->columns(array('UserId'))
										->where("CostCentreId='$argCCId'");
								
									$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
								}				
								if($iProjId != 0) {
									$subQueryUserProj = $sql->select();
									$subQueryUserProj->from("WF_UserProjectTrans")
										->columns(array('UserId'))
										->where("ProjectId='$iProjId'");
								
									$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
								}
								if ($sUserId != "") { 
									//$select->where->expression('a.UserId IN ?', array($sUserId));
									$select->where("a.UserId IN ($sUserId)");									
								} else { 
									$select->where("a.UserId= 0");
								}
								$insert = $sql->insert();
								$insert->into( 'WF_PendingWorks' );
								$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
								$insert->Values( $select );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
								
								
								//Multiple Feed Insert Start
								$statementfeed = $sql->getSqlStringForSqlObject($select); 
								$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								foreach($feedUserResult as &$feedUserResults) {
									$ifeedUserId = $feedUserResults['UserId'];
									$ifeedLogId = $feedUserResults['LogId'];
									$ifeedPendingRole = $feedUserResults['RoleName'];
									$ifeedType = $feedUserResults['Type'];
										
									$iPendingWorkId =0;
									$select = $sql->select(); 
									$select->from(array("a"=>"WF_PendingWorks"))
										->columns(array("TransId"))
										->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
									$statement = $sql->getSqlStringForSqlObject($select); 
									$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
									if(count($penResult) > 0) {
										$iPendingWorkId = $penResult[0]['TransId'];
										CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
									}
								}
								//Multiple Feed Insert End
							} else if ($bSpecial == true) {
								$subQueryUser = $sql->select();
								$subQueryUser->from("WF_UserspecialRoleTrans")
									->columns(array('UserId'))
									->where("RoleId='$iNextRoleId' and Limit <= $argValue");
										
								$select->from(array("a"=>"WF_Users"))
									->columns(array( 'RoleName' => new Expression("'$sNextRoleName'"), 'Type' => new Expression("'$sNextRoleType'")
									, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId',  'StartTime' => new Expression("'$argLogTime'")
									,  'DueTime' => new Expression("'$dProcessDate'") ));
								$select->where->expression('a.UserId IN ?', array($subQueryUser));
								
								$insert = $sql->insert();
								$insert->into( 'WF_PendingWorks' );
								$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
								$insert->Values( $select );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
								
								
								//Multiple Feed Insert Start
								$statementfeed = $sql->getSqlStringForSqlObject($select); 
								$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
								foreach($feedUserResult as &$feedUserResults) {
									$ifeedUserId = $feedUserResults['UserId'];
									$ifeedLogId = $feedUserResults['LogId'];
									$ifeedPendingRole = $feedUserResults['RoleName'];
									$ifeedType = $feedUserResults['Type'];
										
									$iPendingWorkId =0;
									$select = $sql->select(); 
									$select->from(array("a"=>"WF_PendingWorks"))
										->columns(array("TransId"))
										->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
									$statement = $sql->getSqlStringForSqlObject($select); 
									$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
									if(count($penResult) > 0) {
										$iPendingWorkId = $penResult[0]['TransId'];
										CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
									}
								}
								//Multiple Feed Insert End
							}
							
							
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_PendingWorks"))
								->columns(array("TransId","UserId"), array("RefNo"))
								->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
								->where("a.PendingRole='$sNextRoleName' And a.LogId=$argLogId ");
							$statement = $sql->getSqlStringForSqlObject($select); 
							$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							$iId = 0;
							$sREfNo = "";
							$iPUserId = 0;
							$iRemId = 0;
							foreach($logPendingResult as &$logPendingResults) {
								$iId = $logPendingResults['TransId'];
								$sREfNo = $logPendingResults['RefNo'];
								if ($sREfNo != "") { 
								$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
								} else { $sREfNo = $sNextRoleName; 
								}
								$iPUserId = $logPendingResults['UserId'];
								
								$insert = $sql->insert('WF_ReminderMaster');
								$insert->values(array(
									'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId,
									'UserId'  => $iPUserId	
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
								
								$insert = $sql->insert('WF_ReminderTrans');
								$insert->values(array(
									'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
								));
								$statement = $sql->getSqlStringForSqlObject($insert);
								$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							}
							return;
						}						
					} else {
						//Direct Approval
						$iLevelId = 0;
                        $iOrderId = 0;
						
						$selectQuery = $sql->select(); 
						$selectQuery->from(array("a"=>"WF_Users"))
							->columns(array("LevelId"))				
							->where("a.UserId='$argUserId'");
							
						$select = $sql->select();
						$select->from('WF_LevelMaster')
							   ->columns(array('LevelId', 'OrderId'));	   
						$select->where->expression('LevelId IN ?', array($selectQuery));
						$statement = $sql->getSqlStringForSqlObject($select); 
						$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($levelMasterResult) > 0) {
							$iLevelId = $levelMasterResult[0]['LevelId'];
							$iOrderId = $levelMasterResult[0]['OrderId'];
						}
						
						$identity = 0;
						$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
					
						if ($this->bAlterLog == true) {
							$insert = $sql->insert('WF_LogMaster');
							$insert->values(array(
								'UserId'  => $argUserId,'LogTime'  => $argLogTime,'RoleName'  => $sNextRoleName,'LogType'  => 'A',
								'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId,'IpAddress' => $sipaddress
							));		
						} else {
							$insert = $sql->insert('WF_LogMaster');
							$insert->values(array(
								'UserId'  => $argUserId,'LogTime'  => $argLogTime,'RoleName'  => $sNextRoleName,'LogType'  => 'A',
								'LogDescription'  => $sNextRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress
							));
						}
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
						
						$insert = $sql->insert('WF_LogTrans');
						$insert->values(array(
							'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
							'DBName'  => $argDBName,'RefNo'  => $argRefNo	
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						
						$insert = $sql->insert('WF_ApprovalTrans');
						$insert->values(array(
							'LogId'  => $identity,'RoleName'  => $sNextRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
							'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						//echo "Test-2  ";
						if($sTableName != "" && $sFieldName != "") {
							$update = $sql->update();
							$update->table( "$sTableName" )
								->set( array( 'Approve' => 'Y' ))
								->where("$sFieldName=$argRegId ");
							$statement = $sql->getSqlStringForSqlObject( $update ); 
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						}
                        $this->sNextupdateRole = $sNextRoleName;
                        $this->sFAUpdate = "Add";
                        $iRoleId = $iNextRoleId;
                        goto CheckNextActivity;
						//echo "goto CheckNextActivity- 3510,";
					}                    
				} else {
					//Insert WF_PendingWorks
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array( 'RoleName' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
						, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId', 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
						->where("a.RoleId='$iNextRoleId'");
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}		
					
					$insert = $sql->insert();
					$insert->into( 'WF_PendingWorks' );
					$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
								
					//Multiple Feed Insert Start
					$statementfeed = $sql->getSqlStringForSqlObject($select); 
					$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($feedUserResult as &$feedUserResults) {
						$ifeedUserId = $feedUserResults['UserId'];
						$ifeedLogId = $feedUserResults['LogId'];
						$ifeedPendingRole = $feedUserResults['RoleName'];
						$ifeedType = $feedUserResults['RoleType'];
						
						$iPendingWorkId =0;
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array("TransId"))
							->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($penResult) > 0) {
							$iPendingWorkId = $penResult[0]['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
						}
					}
					//Multiple Feed Insert End
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
						->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$iId = 0;
                    $sREfNo = "";
                    $iPUserId = 0;
                    $iRemId = 0;
					foreach($pendingResult as $pendingResults){
						$iId = $pendingResults['TransId'];
						$sREfNo = $pendingResults['RefNo'];
						if ($sREfNo != "") { 
							$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
						} else { 
							$sREfNo = $sNextRoleName;
						}							
						$iPUserId = $pendingResults['UserId'];
						
						$insert = $sql->insert('WF_ReminderMaster');
						$insert->values(array(
							'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
							'RType'  => 'P' ,'PId'  => $iId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
						$insert = $sql->insert('WF_ReminderTrans');
						$insert->values(array(
							'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
							'Live'  => 1 ));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}	
				}
			} else if ($iNextActivityId != 0) {
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ActivityMaster"))
					->columns(array('ActivityName','ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
					->where("a.ActivityId='$iNextActivityId'");
				$statement = $sql->getSqlStringForSqlObject($select);
				$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($roledetResult) > 0) {
					$sNextRoleName = $roledetResult[0]['ActivityName'];
					$sIntType = $roledetResult[0]['IntervalType'];
					$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
					$sProcessType = $roledetResult[0]['ProcessType'];
					$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];					
				}
				
				if ($sIntType == "None") { $iIntPeriod = 0; }
                if ($sProcessType == "None") { $iProcessPeriod = 0; }
				
				/*if ($iProcessPeriod != 0) {
					$dProcessDate = $argLogTime;
					if ($sProcessType == "Minutes") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
					} else if ($sProcessType == "Hour") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
					} else if ($sProcessType == "Day") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
					} else if ($sProcessType == "Week") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
					} else if ($sProcessType == "Month") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
					} else if ($sProcessType == "Year") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
					}
				}
				if ($iIntPeriod != 0) {
					if ($sIntType == "Minutes") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
					} else if ($sIntType == "Hour") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
					} else if ($sIntType == "Day") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
					} else if ($sIntType == "Week") { 
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
					} else if ($sIntType == "Month") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
					} else if ($sIntType == "Year") {
						$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
					}
				}*/
				
				if ($iProcessPeriod != 0) {
					$dProcessDate = $argLogTime;
					if ($sProcessType == "Minutes") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
					} else if ($sProcessType == "Hour") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
					} else if ($sProcessType == "Day") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
					} else if ($sProcessType == "Week") {
						$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
					} else if ($sProcessType == "Month") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
					} else if ($sProcessType == "Year") {
						$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
					}
				}
				if ($iIntPeriod != 0) {
					if ($sIntType == "Minutes") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
					} else if ($sIntType == "Hour") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
					} else if ($sIntType == "Day") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
					} else if ($sIntType == "Week") {
						$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
					} else if ($sIntType == "Month") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
					} else if ($sIntType == "Year") {
						$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
						$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
						//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
					}					
				}

				//Insert WF_PendingWorks
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_UserActivityTrans"))
					->columns(array( 'PendingRole' => new Expression("b.ActivityName"), 'RoleType' => new Expression("''")
					, 'NonTask' => new Expression("1"), 'LogId' => new Expression("$argLogId"), 'UserId'
					, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"),'ActivityId' ))
					->join(array("b"=>"WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array(), $select::JOIN_INNER)
					->where("a.ActivityId='$iNextActivityId'");
				if($argCCId != 0) {
					$subQueryUserCC = $sql->select();
					$subQueryUserCC->from("WF_UserCostCentreTrans")
						->columns(array('UserId'))
						->where("CostCentreId='$argCCId'");
				
					$select->where->expression('UserId IN ?', array($subQueryUserCC));
				}
				if($iProjId != 0) {
					$subQueryUserProj = $sql->select();
					$subQueryUserProj->from("WF_UserProjectTrans")
						->columns(array('UserId'))
						->where("ProjectId='$iProjId'");
				
					$select->where->expression('UserId IN ?', array($subQueryUserProj));
				}		
				
				$insert = $sql->insert();
				$insert->into( 'WF_PendingWorks' );
				$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime','ActivityId'));
				$insert->Values( $select );
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Multiple Feed Insert Start
				$statementfeed = $sql->getSqlStringForSqlObject($select); 
				$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($feedUserResult as &$feedUserResults) {
					$ifeedUserId = $feedUserResults['UserId'];
					$ifeedLogId = $feedUserResults['LogId'];
					$ifeedPendingRole = $feedUserResults['PendingRole'];
						
					$iPendingWorkId =0;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId"))
						->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='' and a.NonTask=1 and a.UserId=$ifeedUserId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($penResult) > 0) {
						$iPendingWorkId = $penResult[0]['TransId'];
						CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
					}
				}
				//Multiple Feed Insert End
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
					->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$iId = 0;
				$sREfNo = "";
				$iPUserId = 0;
				$iRemId = 0;
				foreach($pendingResult as $pendingResults){
					$iId = $pendingResults['TransId'];
					$sREfNo = $pendingResults['RefNo'];
					if ($sREfNo != "") { 
						$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
					} else { 
						$sREfNo = $sNextRoleName;
					}							
					$iPUserId = $pendingResults['UserId'];
					
					$insert = $sql->insert('WF_ReminderMaster');
					$insert->values(array(
						'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
						'RType'  => 'P' ,'PId'  => $iId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_ReminderTrans');
					$insert->values(array(
						'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
						'Live'  => 1 ));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
			}
			/*
			else if ($sRoleType == "N") { //This condition temparory added
				if ($sTableName != "" && $sFieldName != "") {
					$update = $sql->update();
					$update->table( "$sTableName" )
						->set( array( 'Approve' => 'Y' ))
						->where("$sFieldName=$argRegId ");
					$statement = $sql->getSqlStringForSqlObject( $update ); 
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					$this->sFAUpdate = "Add";
				}
			}*/;
			return;			
		} else {
			if ($bMultiApproval == true) {
				$iPositionId = 0;
                $bSpecial = false;
				
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId='$argRegId' and DBName = '$argDBName'");
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ApprovalTrans"))
					->columns(array('Special'))
					->where("a.RoleName='$argRoleName' and RegId=$argRegId and UserId=$argUserId ");
				$select->where->expression('a.LogId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject($select); 
				$specialAppdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($specialAppdetResult) > 0) {
					$bSpecial = $specialAppdetResult[0]['ActivityName'];					
				}
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_Users"))
					->columns(array('LevelId','PositionId'))
					->where("a.UserId='$argUserId'");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$userdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($userdetResult) > 0) {
					$iUserLevelId = $userdetResult[0]['LevelId'];
					$iPositionId = $userdetResult[0]['PositionId'];					
				}
				
				$update = $sql->update();
				if ($bSpecial == false) {
					if ($sApprove == "A") {					
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
									
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$argUserId ")
							->where->expression('LogId IN ?', array($subQuery));
					} else if ($sApprove == "P") {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iUserLevelId and PositionId=$iPositionId ");			
						
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$argUserId ")
							->where->expression('LogId IN ?', array($subQuery))
							->where->expression('UserId IN ?', array($subQuery1));
					} else {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iUserLevelId ");			
						
						$update->table( 'WF_ApprovalTrans' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$argUserId ")
							->where->expression('LogId IN ?', array($subQuery))
							->where->expression('UserId IN ?', array($subQuery1));
					}
				} else {
					$subQuery = $sql->select();
					$subQuery->from("WF_LogTrans")
						->columns(array('LogId'))
						->where("RegisterId=$argRegId and DBName= '$argDBName'");
								
					$update->table( 'WF_ApprovalTrans' )
						->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
						->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$argUserId ")
						->where->expression('LogId IN ?', array($subQuery));
				}
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				$update = $sql->update();
				if ($bSpecial == false) {
					if ($sApprove == "A"){
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
			
						$update->table( 'WF_PendingWorks' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("PendingRole='$argRoleName' and UserId =$argUserId ");
						$update->where->expression('LogId IN ?', array($subQuery));
						$statement = $sql->getSqlStringForSqlObject( $update );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Delete Start
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array("TransId"))
							->where("a.PendingRole ='$argRoleName' and a.UserId='$argUserId' and a.Status= 1");
						$select->where->expression('a.LogId IN ?', array($subQuery));
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$iPendingWorkId = $feedUserResults['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $argUserId, $dbAdapter ,'D');
						}
						//Multiple Feed Delete End
						
					} else if ($sApprove == "P") {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iUserLevelId and PositionId= $iPositionId");
					
						$update->table( 'WF_PendingWorks' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("PendingRole='$argRoleName'");
						$update->where->expression('LogId IN ?', array($subQuery));
						$update->where->expression('UserId IN ?', array($subQuery1));
						$statement = $sql->getSqlStringForSqlObject( $update );
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Delete Start
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array('TransId','UserId'))
							->where("a.PendingRole ='$argRoleName' and a.Status= 1");
						$select->where->expression('a.LogId IN ?', array($subQuery));
						$select->where->expression('a.UserId IN ?', array($subQuery1));
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$iPendingWorkId = $feedUserResults['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
						}
						//Multiple Feed Delete End
						
					} else {
						$subQuery = $sql->select();
						$subQuery->from("WF_LogTrans")
							->columns(array('LogId'))
							->where("RegisterId=$argRegId and DBName= '$argDBName'");
							
						$subQuery1 = $sql->select();
						$subQuery1->from("WF_Users")
							->columns(array('UserId'))
							->where("LevelId=$iUserLevelId");
					
						$update->table( 'WF_PendingWorks' )
							->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
							->where("PendingRole='$argRoleName'");
						$update->where->expression('LogId IN ?', array($subQuery));
						$update->where->expression('UserId IN ?', array($subQuery1));
						$statement = $sql->getSqlStringForSqlObject( $update );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Delete Start
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array('TransId','UserId'))
							->where("a.PendingRole ='$argRoleName' and a.Status= 1");
						$select->where->expression('a.LogId IN ?', array($subQuery));
						$select->where->expression('a.UserId IN ?', array($subQuery1));
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$iPendingWorkId = $feedUserResults['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
						}
						//Multiple Feed Delete End
					}					
				} else {
					$subQuery = $sql->select();
					$subQuery->from("WF_LogTrans")
						->columns(array('LogId'))
						->where("RegisterId=$argRegId and DBName= '$argDBName'");
		
					$update->table( 'WF_PendingWorks' )
						->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
						->where("PendingRole='$argRoleName' and UserId =$argUserId ");
					$update->where->expression('LogId IN ?', array($subQuery));
					$statement = $sql->getSqlStringForSqlObject( $update );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Multiple Feed Delete Start
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array('TransId','UserId'))
						->where("a.PendingRole ='$argRoleName' and a.Status= 1");
					$select->where->expression('a.LogId IN ?', array($subQuery));
					$statementfeed = $sql->getSqlStringForSqlObject($select); 
					$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($feedUserResult as &$feedUserResults) {
						$iPendingWorkId = $feedUserResults['TransId'];
						CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
					}
					//Multiple Feed Delete End
				}
				
				
				$bLevelStatus = true;
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId='$argRegId' and DBName = '$argDBName'");
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ApprovalTrans"))
					->columns(array('TransId'))
					->where("a.RoleName='$argRoleName' and RegId=$argRegId ");
				$select->where->expression('a.LogId IN ?', array($subQuery));
				if ($bSpecial == false) {
					$select->where("status='0' and LevelId=$iUserLevelId ");
				} else {
					$select->where("status='0' and Special='1' ");
				}
				$statement = $sql->getSqlStringForSqlObject($select);
				$specialAppdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($specialAppdetResult) > 0) {
					$bLevelStatus = false;					
				}
				//echo "Test-3  ";
				if ($bLevelStatus == false) {
					if($sTableName != "" && $sFieldName != "") {
						$update = $sql->update();
						$update->table( "$sTableName" )
							->set( array( 'Approve' => 'P' ))
							->where("$sFieldName=$argRegId ");
						$statement = $sql->getSqlStringForSqlObject( $update ); 
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}
					return;
				}
				
				if ($bLevelStatus == true) {
					$subQuery = $sql->select();
					$subQuery->from("WF_LogTrans")
						->columns(array('LogId'))
						->where("RegisterId='$argRegId' and DBName = '$argDBName'");
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_ApprovalTrans"))
						->columns(array('LevelId','DueTime','Special'))
						->where("a.RoleName='$argRoleName' and RegId=$argRegId and Status = 0 ");
					$select->where->expression('a.LogId IN ?', array($subQuery));
					$select->order('a.OrderId Desc');
					$bAppComplete = true;
                    $bSpecial = false;
					$statement = $sql->getSqlStringForSqlObject($select);
					$AppdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($AppdetResult) > 0) {
						$iUserLevelId = $AppdetResult[0]['LevelId'];
                        if ($AppdetResult[0]['DueTime'] != null) { $dDueTime = $AppdetResult[0]['DueTime']; }
                        $bAppComplete = false;
                        $bSpecial = $AppdetResult[0]['Special'];					
					}
				
					if ($bAppComplete == false){
						$select = $sql->select(); 
						if ($bSpecial == false) {				
							$subQueryUser = $sql->select();
							$subQueryUser->from("WF_Users")
								->columns(array('UserId'))
								->where("LevelId='$iUserLevelId'");
									
							$subQueryLog = $sql->select();
							$subQueryLog->from("WF_LogTrans")
								->columns(array('LogId'))
								->where("RegisterId='$argRegId' and DBName='$argDBName'");
								
							$subQueryApp = $sql->select();
							$subQueryApp->from("WF_ApprovalTrans")
								->columns(array('UserId'))
								->where("RoleName='$argRoleName' and RegId= $argRegId");
							$subQueryApp->where->expression('LogId IN ?', array($subQueryLog));
								
							$select->from(array("a"=>"WF_UserRoleTrans"))
								->columns(array( 'RoleName' => new Expression("'$argRoleName'"), 'Type' => new Expression("'$sRoleType'")
								, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId',  'StartTime' => new Expression("'$argLogTime'"),  'DueTime' => new Expression("'$dDueTime'")  ))
								->where("a.RoleId='$iRoleId'");			
							$select->where->expression('a.UserId IN ?', array($subQueryUser));
							$select->where->expression('a.UserId IN ?', array($subQueryApp));									
						} else{
							$subQueryUser = $sql->select();
							$subQueryUser->from("WF_UserspecialRoleTrans")
								->columns(array('UserId'))
								->where("RoleId='$iRoleId' and Limit <=$argValue");
	 
							$select->from(array("a"=>"WF_UserspecialRoleTrans"))
								->columns(array( 'RoleName' => new Expression("'$argRoleName'"), 'Type' => new Expression("'$sRoleType'")
								, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId',  'StartTime' => new Expression("'$argLogTime'"),  'DueTime' => new Expression("'$dDueTime'")  ))
								->where("a.RoleId='$iRoleId'");			
							$select->where->expression('a.UserId IN ?', array($subQueryUser));
						}
						$insert = $sql->insert();
						$insert->into( 'WF_PendingWorks' );
						$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
						$insert->Values( $select );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Insert Start
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$ifeedUserId = $feedUserResults['UserId'];
							$ifeedLogId = $feedUserResults['LogId'];
							$ifeedPendingRole = $feedUserResults['RoleName'];
							$ifeedType = $feedUserResults['Type'];
							
							$iPendingWorkId =0;
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_PendingWorks"))
								->columns(array("TransId"))
								->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
							$statement = $sql->getSqlStringForSqlObject($select); 
							$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($penResult) > 0) {
								$iPendingWorkId = $penResult[0]['TransId'];
								CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
							}
						}
						//Multiple Feed Insert End
				
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
							->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
							->where("a.PendingRole='$argRoleName' and a.LogId=$argLogId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$iId = 0;
						$sREfNo = "";
						$iPUserId = 0;
						$iRemId = 0;
						foreach($pendingResult as $pendingResults){
							$iId = $pendingResults['TransId'];
							$sREfNo = $pendingResults['RefNo'];
							if ($sREfNo != "") { 
								$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
							} else { 
								$sREfNo = $sNextRoleName;
							}							
							$iPUserId = $pendingResults['UserId'];
						
							$insert = $sql->insert('WF_ReminderMaster');
							$insert->values(array(
								'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
								'RType'  => 'P' ,'PId'  => $iId
							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
						
							$insert = $sql->insert('WF_ReminderTrans');
							$insert->values(array(
								'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
								'Live'  => 1 ));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
						//echo "Test-4  ";
						if ($sTableName != "" && $sFieldName != "") {
							$update = $sql->update();
							$update->table( "$sTableName" )
								->set( array( 'Approve' => 'P' ))
								->where("$sFieldName=$argRegId ");
							$statement = $sql->getSqlStringForSqlObject( $update );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						}
						return;
					} else { //echo "Test-5  ";
						if ($sTableName != "" && $sFieldName != "") {
							$update = $sql->update();
							$update->table( "$sTableName" )
								->set( array( 'Approve' => 'Y' ))
								->where("$sFieldName=$argRegId ");
							$statement = $sql->getSqlStringForSqlObject( $update );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							$this->sFAUpdate = "Add";
						}
					}
				}
			} else {
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId=$argRegId");
					
				$update = $sql->update();		
				$update->table( 'WF_PendingWorks' )
					->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
					->where("PendingRole='$argRoleName' ")
					->where->expression('LogId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Multiple Feed Delete Start
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array('TransId','UserId'))
					->where("a.PendingRole ='$argRoleName' and a.Status= 1");
				$select->where->expression('a.LogId IN ?', array($subQuery));
				$statementfeed = $sql->getSqlStringForSqlObject($select); 
				$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($feedUserResult as &$feedUserResults) {
					$iPendingWorkId = $feedUserResults['TransId'];
					CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
				}
				//Multiple Feed Delete End
					
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId=$argRegId and DBName= '$argDBName'");
				$update = $sql->update();	
				$update->table( 'WF_ApprovalTrans' )
					->set( array( 'Status' => '1', 'FinishTime' => "$argLogTime" ))
					->where("RoleName='$argRoleName' and RegId =$argRegId and UserId=$argUserId ")
					->where->expression('LogId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				$subQuery = $sql->select();
				$subQuery->from("WF_LogTrans")
					->columns(array('LogId'))
					->where("RegisterId=$argRegId");
				
				$subQueryPendingWorks = $sql->select();
				$subQueryPendingWorks->from("WF_PendingWorks")
					->columns(array('TransId'))
					->where("PendingRole='$argRoleName'")
					->where->expression('LogId IN ?', array($subQuery));
					
				$subQueryReminderMaster = $sql->select();
				$subQueryReminderMaster->from("WF_ReminderMaster")
					->columns(array('ReminderId'))
					->where("RType='P'")
					->where->expression('PId IN ?', array($subQueryPendingWorks));
				
				$update = $sql->update();	
				$update->table( 'WF_ReminderTrans' )
					->set( array( 'Live' => '0' ))
					->where->expression('ReminderId IN ?', array($subQueryReminderMaster));
				$statement = $sql->getSqlStringForSqlObject( $update );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				//echo "Test-6  ";
				if ($sTableName != "" && $sFieldName != "") {
					$update = $sql->update();
					$update->table( "$sTableName" )
						->set( array( 'Approve' => 'Y' ))
						->where("$sFieldName=$argRegId ");
					$statement = $sql->getSqlStringForSqlObject( $update );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					$this->sFAUpdate = "Add";
				}
			}
			goto CheckNextActivity;
		} //echo "3891,";
		CheckNextActivity:
		//echo "present CheckNextActivity- 4291,";
		$iPendRoleId = 0;
		$subQuery = $sql->select();		
		$subQuery->from("WF_ActivityCriticalTrans")
			->columns(array('OrderId' => new Expression("OrderId+1")))
			->where("ActivityId=$iActivityId and RoleId= $iRoleId");
		$select = $sql->select(); 
		$select->from(array("a"=>"WF_ActivityCriticalTrans"))
			->columns(array("RoleId"))
			->where("ActivityId=$iActivityId");
		$select->where->expression('OrderId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$criticaltranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($criticaltranResult) > 0) {
			$iPendRoleId = $criticaltranResult[0]['RoleId'];
		}

		if ($iPendRoleId == 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_ActivityMaster"))
				->columns(array("ActivityId","ActivityName"))
				->where("PrevActivityId=$iActivityId ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$tranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($tranResult) > 0) {
				$iNextActivityId = $tranResult[0]['ActivityId'];
				$sNextRoleName = $tranResult[0]['ActivityName'];
			}

			if ($iNextActivityId != 0){
				$sNextRoleApprove = "";
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ActivityCriticalTrans"))
					->columns(array('RoleId'), array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'))
					->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'), $subQuery::JOIN_INNER)
					->where("a.ActivityId='$iNextActivityId' and a.OrderId=1 ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$criticalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($criticalResult) > 0) {
					$iPendRoleId = $criticalResult[0]['RoleId'];
					$sPendRoleName = $criticalResult[0]['RoleName'];
					$bNextMulti = $criticalResult[0]['MultiApproval'];
					$sPendRoleType = $criticalResult[0]['RoleType'];
					$bValueApproval = $criticalResult[0]['ValueApproval'];
					$sNextRoleApprove = $criticalResult[0]['ApprovalBased'];
					$iMaxLevel = $criticalResult[0]['MaxLevel'];
				}
				if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }
			}			
		} else {
			$sNextRoleApprove = "";
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskTrans"))
				->columns(array('RoleId','RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased','MaxLevel'))
				->where("a.RoleId='$iPendRoleId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$tsakdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($tsakdetResult) > 0) {
				$iPendRoleId = $tsakdetResult[0]['RoleId'];
				$sPendRoleName = $tsakdetResult[0]['RoleName'];
				$bNextMulti = $tsakdetResult[0]['MultiApproval'];
				$sPendRoleType = $tsakdetResult[0]['RoleType'];
				$bValueApproval = $tsakdetResult[0]['ValueApproval'];
				$sNextRoleApprove = $tsakdetResult[0]['ApprovalBased'];
				$iMaxLevel = $tsakdetResult[0]['MaxLevel'];
			}
			if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }			
		}
		
		if ($iPendRoleId != 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_RoleTrans"))
				->columns(array('ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
				->where("a.RoleId='$iPendRoleId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($roledetResult) > 0) {
				$sIntType = $roledetResult[0]['IntervalType'];
				$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
				$sProcessType = $roledetResult[0]['ProcessType'];
				$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];
			}
			
			if ($sIntType == "None") { $iIntPeriod = 0; }
			if ($sProcessType == "None") { $iProcessPeriod = 0; }
			
			/*if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				
				if ($sProcessType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}
			}*/
			if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") {
					$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") {
					$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}					
			}

			if ($sPendRoleType == "A") {
				$bApprovalNotRequired = false;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_TaskTrans"))
					->columns(array('RoleId'))
					->where("a.RoleId='$iPendRoleId' and NotRequired=1");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($roledetResult) > 0) {
					$bApprovalNotRequired = true;
				}
				
				$bSpecialApprovalRequired = false;
				$subQuery = $sql->select();		
				$subQuery->from("WF_UserspecialRoleTrans")
					->columns(array('UserId'))
					->where("RoleId=$iPendRoleId and Limit<= $argValue");
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_Users"))
					->columns(array("UserId"));
				$select->where->expression('UserId IN ?', array($subQuery));
				$statement = $sql->getSqlStringForSqlObject($select); 
				$userdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($userdetResult) > 0) {
					$bSpecialApprovalRequired = true;
				}
				
				$this->m_iMaxLevel = $iMaxLevel;
				CommonHelper::GetSuperiorUsers($argUserId, $dbAdapter);
				
				$m_sUserId = "";
				if ($this->sSuperiors != "") {
					$subQueryRole = $sql->select();
					$subQueryRole->from("WF_TaskTrans")
						->columns(array('RoleId'))
						->where("RoleName='$sPendRoleName'");

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('UserId' ));
					$select->where->expression('a.RoleId IN ?', array($subQueryRole));
					//$select->where->expression('a.UserId IN ?', array($this->sSuperiors));
					$select->where("a.UserId IN ($this->sSuperiors)");
	
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					
					if ($bNextMulti == false) {
						if ($argValue != 0 && $bValueApproval == true) {
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
							$selectLesser = $sql->select(); 
							$selectLesser->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
							$selectLesser->combine($selectBetween,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectLesser));
							
							$select->where->expression('UserId IN ?', array($selectUser));
						}							
					} else {
						if ($argValue != 0 && $bValueApproval == true) {
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
							$selectGreaterTovalue = $sql->select(); 
							$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
							$selectGreaterTovalue->combine($selectBetween,'Union ALL');
							
							$selectGreaterFromTovalue = $sql->select(); 
							$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
							$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
							
							$selectGreaterFromvalue = $sql->select(); 
							$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
							$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
							
							$select->where->expression('UserId IN ?', array($selectUser));
						}
					}
					$statement = $sql->getSqlStringForSqlObject($select); 
					$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($userSuperiortransResult as &$userSuperiortransResults) {					
						$sUserId = $sUserId . $userSuperiortransResults["UserId"] . ",";						
					}
					if($sUserId!="") {
						$sUserId = rtrim($sUserId,',');
					} else {					
						$selectQuery = $sql->select(); 
						$selectQuery->from(array("a"=>"WF_TaskTrans"))
							->columns(array("RoleId"))				
							->where("a.RoleName='$sPendRoleName' ");
								
						$select = $sql->select();
						$select->from('WF_UserRoleTrans')
							   ->columns(array('UserId'))
							   ->where("UserId=$argUserId");	   
						$select->where->expression('RoleId IN ?', array($selectQuery));
						$statement = $sql->getSqlStringForSqlObject($select);
						$userResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($userResult) > 0) {
							$sUserId = $userResult[0]['UserId'];
						}
					}
				}
				if ($bAutoApproval == false && $sUserId == ""){
					$subQueryRole = $sql->select();
					$subQueryRole->from("WF_TaskTrans")
						->columns(array('RoleId'))
						->where("RoleName='$sPendRoleName'");

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('UserId' ))
						->where("UserId='$argUserId'");
					$select->where->expression('a.RoleId IN ?', array($subQueryRole));
	
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					if ($bNextMulti == false){
						if ($argValue != 0 && $bValueApproval == true) {
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
							$selectLesser = $sql->select(); 
							$selectLesser->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
							$selectLesser->combine($selectBetween,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectLesser));
							
							$select->where->expression('UserId IN ?', array($selectUser));
						}
					} else {
						if ($argValue != 0 && $bValueApproval == true) {
						
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
							$selectGreaterTovalue = $sql->select(); 
							$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
							$selectGreaterTovalue->combine($selectBetween,'Union ALL');
					
							$selectGreaterFromTovalue = $sql->select(); 
							$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
							$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
							
							$selectGreaterFromvalue = $sql->select(); 
							$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
							$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
							
							$select->where->expression('UserId IN ?', array($selectUser));
						}
					}			
					$statement = $sql->getSqlStringForSqlObject($select); 
					$userSuperiortransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($userSuperiortransResult) > 0) {
						$sUserId = $userSuperiortransResult[0]['UserId'];
					}
				}
				if (($sUserId == "" && $this->sSuperiors == "" && $bAutoApproval == true && $bSpecialApprovalRequired == false) || $bApprovalNotRequired == true) {
					$iLevelId = 0;
                    $iOrderId = 0;
					
					$selectQuery = $sql->select(); 
					$selectQuery->from(array("a"=>"WF_Users"))
						->columns(array("LevelId"))				
						->where("a.UserId='$argUserId'");
					
					$select = $sql->select();
					$select->from('WF_LevelMaster')
						   ->columns(array('LevelId', 'OrderId'));	   
					$select->where->expression('LevelId IN ?', array($selectQuery));
					$statement = $sql->getSqlStringForSqlObject($select); 
					$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($levelMasterResult) > 0) {
						$iLevelId = $levelMasterResult[0]['LevelId'];
						$iOrderId = $levelMasterResult[0]['OrderId'];
					}
					
					$identity = 0;
					$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
					if ($this->bAlterLog == true) {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => $argLogTime,'RoleName'  => $sPendRoleName,'LogType'  => 'A',
							'LogDescription'  => $sPendRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId,'IpAddress' => $sipaddress	
						));		
					} else {
						$insert = $sql->insert('WF_LogMaster');
						$insert->values(array(
							'UserId'  => $argUserId,'LogTime'  => $argLogTime,'RoleName'  => $sPendRoleName,'LogType'  => 'A',
							'LogDescription'  => $sPendRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress
						));
					}
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_LogTrans');
					$insert->values(array(
						'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
						'DBName'  => $argDBName,'RefNo'  => $argRefNo	
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$insert = $sql->insert('WF_ApprovalTrans');
					$insert->values(array(
						'LogId'  => $identity,'RoleName'  => $sPendRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
						'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					//echo "Test-7  ";
					if($sTableName != "" && $sFieldName != "") {
						$update = $sql->update();
						$update->table( "$sTableName" )
							->set( array( 'Approve' => 'Y' ))
							->where("$sFieldName=$argRegId ");
						$statement = $sql->getSqlStringForSqlObject( $update );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					}
                    $this->sNextupdateRole = $sPendRoleName;
					$this->sFAUpdate = "Add";
				} else {
					if ($bNextMulti == false) {
						//Insert WF_ApprovalTrans
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_UserRoleTrans"))
							->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sPendRoleName'"), 'UserId', 'RegId' => new Expression("$argRegId")
							, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId"), 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
							->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
							->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
							->where("RoleId='$iPendRoleId'");
						if($argCCId != 0) {
							$subQueryUserCC = $sql->select();
							$subQueryUserCC->from("WF_UserCostCentreTrans")
								->columns(array('UserId'))
								->where("CostCentreId='$argCCId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
						}
						if($iProjId != 0) {
							$subQueryUserProj = $sql->select();
							$subQueryUserProj->from("WF_UserProjectTrans")
								->columns(array('UserId'))
								->where("ProjectId='$iProjId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
						}
						if ($sUserId != "") { 
							//$select->where->expression('a.UserId IN ?', array($sUserId));
							$select->where("a.UserId IN ($sUserId)");
						} else { 
							$select->where("a.UserId= 0");
						}
						
						if ($argValue != 0 && $bValueApproval == true) {
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
							$selectLesser = $sql->select(); 
							$selectLesser->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
							$selectLesser->combine($selectBetween,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectLesser));
							
							$select->where->expression('a.UserId IN ?', array($selectUser));
						}
						$select->order('c.OrderId Desc');
						
						$insert = $sql->insert();
						$insert->into( 'WF_ApprovalTrans' );
						$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
						$insert->Values( $select );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
						//Insert WF_PendingWorks
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_UserRoleTrans"))
							->columns(array( 'RoleName' => new Expression("'$sPendRoleName'"), 'RoleType' => new Expression("'$sPendRoleType'")
							, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId', 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
							->where("a.RoleId='$iPendRoleId'");
						if($argCCId != 0) {
							$subQueryUserCC = $sql->select();
							$subQueryUserCC->from("WF_UserCostCentreTrans")
								->columns(array('UserId'))
								->where("CostCentreId='$argCCId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
						}
						if($iProjId != 0) {
							$subQueryUserProj = $sql->select();
							$subQueryUserProj->from("WF_UserProjectTrans")
								->columns(array('UserId'))
								->where("ProjectId='$iProjId'");
						
							$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
						}
						if ($sUserId != "") { 
							//$select->where->expression('a.UserId IN ?', array($sUserId));
							$select->where("a.UserId IN ($sUserId)");
						} else { 
							$select->where("a.UserId= 0");
						}
						if ($argValue != 0 && $bValueApproval == true) {
							$selectBetween = $sql->select(); 
							$selectBetween->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId");
							$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
							
							$selectLesser = $sql->select(); 
							$selectLesser->from(array("a"=>"WF_LevelTrans"))
								->columns(array("LevelId"))				
								->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
							$selectLesser->combine($selectBetween,'Union ALL');
							
							$selectUser = $sql->select(); 
							$selectUser->from(array("a"=>"WF_Users"))
								->columns(array("UserId"));
							$selectUser->where->expression('LevelId IN ?', array($selectLesser));
							
							$select->where->expression('a.UserId IN ?', array($selectUser));		
						}
						
						$insert = $sql->insert();
						$insert->into( 'WF_PendingWorks' );
						$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
						$insert->Values( $select );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Insert Start
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$ifeedUserId = $feedUserResults['UserId'];
							$ifeedLogId = $feedUserResults['LogId'];
							$ifeedPendingRole = $feedUserResults['RoleName'];
							$ifeedType = $feedUserResults['RoleType'];
							
							$iPendingWorkId =0;
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_PendingWorks"))
								->columns(array("TransId"))
								->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
							$statement = $sql->getSqlStringForSqlObject($select); 
							$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($penResult) > 0) {
								$iPendingWorkId = $penResult[0]['TransId'];
								CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
							}
						}
						//Multiple Feed Insert End

						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
							->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
							->where("a.PendingRole='$sPendRoleName' and a.LogId=$argLogId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$iId = 0;
						$sREfNo = "";
						$iPUserId = 0;
						$iRemId = 0;
						foreach($pendingResult as $pendingResults){
							$iId = $pendingResults['TransId'];
							$sREfNo = $pendingResults['RefNo'];
							if ($sREfNo != "") { 
								$sREfNo = $sPendRoleName . " (" . $sREfNo . ")"; 
							} else { 
								$sREfNo = $sPendRoleName;
							}							
							$iPUserId = $pendingResults['UserId'];
							
							$insert = $sql->insert('WF_ReminderMaster');
							$insert->values(array(
								'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
								'RType'  => 'P' ,'PId'  => $iId
							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$insert = $sql->insert('WF_ReminderTrans');
							$insert->values(array(
								'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
								'Live'  => 1 ));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					} else {
						if ($sUserId == "" && $this->sSuperiors == "" && $bAutoApproval == true) {
							$iLevelId = 0;
                            $iOrderId = 0;
							
							$selectQuery = $sql->select(); 
							$selectQuery->from(array("a"=>"WF_Users"))
								->columns(array("LevelId"))				
								->where("a.UserId='$argUserId'");
									
							$select = $sql->select();
							$select->from('WF_LevelMaster')
								   ->columns(array('LevelId', 'OrderId'));	   
							$select->where->expression('LevelId IN ?', array($selectQuery));
							$statement = $sql->getSqlStringForSqlObject($select); 
							$levelMasterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($levelMasterResult) > 0) {
								$iLevelId = $levelMasterResult[0]['LevelId'];
								$iOrderId = $levelMasterResult[0]['OrderId'];
							}
							
							$identity = 0;
							$sCName = gethostbyaddr($_SERVER['REMOTE_ADDR']);//Machine Name
							if ($this->bAlterLog == true) {
								$insert = $sql->insert('WF_LogMaster');
								$insert->values(array(
									'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sPendRoleName,'LogType'  => 'A',
									'LogDescription'  => $sPendRoleName,'ComputerName'  => $sCName,'AUserId'  => $lUserId,'IpAddress' => $sipaddress
								));		
							} else {
								$insert = $sql->insert('WF_LogMaster');
								$insert->values(array(
									'UserId'  => $argUserId,'LogTime'  => date( 'Y/m/d H:i:s' ),'RoleName'  => $sPendRoleName,'LogType'  => 'A',
									'LogDescription'  => $sPendRoleName,'ComputerName'  => $sCName,'IpAddress' => $sipaddress
								));
							}
							$statement = $sql->getSqlStringForSqlObject($insert);
							$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$identity = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$insert = $sql->insert('WF_LogTrans');
							$insert->values(array(
								'LogId'  => $identity,'RegisterId'  => $argRegId,'CostCentreId'  => $argCCId,'CompanyId'  => $argCompanyId,
								'DBName'  => $argDBName,'RefNo'  => $argRefNo	
							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
							$insert = $sql->insert('WF_ApprovalTrans');
							$insert->values(array(
								'LogId'  => $identity,'RoleName'  => $sPendRoleName,'UserId'  => $argUserId,'RegId'  => $argRegId,
								'Status'  => '1','LevelId'  => $iLevelId	,'OrderId'  => $iOrderId
							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
						} else {
							//Insert WF_ApprovalTrans					
//							$subQueryRole = $sql->select();
//							$subQueryRole->from("WF_UserRoleTrans")
//								->columns(array('UserId'))
//								->where("RoleId='$iPendRoleId'");
							
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_UserRoleTrans"))
								->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sPendRoleName'"), 'UserId' => new Expression("b.UserId"), 'RegId' => new Expression("$argRegId")
								, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId"), 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
								->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
								->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
								->where("RoleId='$iPendRoleId'");
//							$select->where->expression('b.UserId IN ?', array($subQueryRole));
			
							if($argCCId != 0) {
								$subQueryUserCC = $sql->select();
								$subQueryUserCC->from("WF_UserCostCentreTrans")
									->columns(array('UserId'))
									->where("CostCentreId='$argCCId'");
							
								$select->where->expression('b.UserId IN ?', array($subQueryUserCC));
							}
							if($iProjId != 0) {
								$subQueryUserProj = $sql->select();
								$subQueryUserProj->from("WF_UserProjectTrans")
									->columns(array('UserId'))
									->where("ProjectId='$iProjId'");
							
								$select->where->expression('b.UserId IN ?', array($subQueryUserProj));
							}
							if ($sUserId != "") { 
								//$select->where->expression('b.UserId IN ?', array($sUserId));
								$select->where("b.UserId IN ($sUserId)");
							} else { 
								$select->where("b.UserId= 0");
							}
							
							if ($argValue != 0 && $bValueApproval == true) {
								$selectBetween = $sql->select(); 
								$selectBetween->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iPendRoleId");
								$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
								
								$selectGreaterTovalue = $sql->select(); 
								$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iPendRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
								$selectGreaterTovalue->combine($selectBetween,'Union ALL');
								
								$selectGreaterFromTovalue = $sql->select(); 
								$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
								$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
								
								$selectGreaterFromvalue = $sql->select(); 
								$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
									->columns(array("LevelId"))				
									->where("a.RoleId=$iPendRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
								$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
								
								$selectUser = $sql->select(); 
								$selectUser->from(array("a"=>"WF_Users"))
									->columns(array("UserId"));
								$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
								
								$select->where->expression('UserId IN ?', array($selectUser));
							}
							$select->order('c.OrderId Desc');
							
							$insert = $sql->insert();
							$insert->into( 'WF_ApprovalTrans' );
							$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
							$insert->Values( $select );
							$statement = $sql->getSqlStringForSqlObject( $insert );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );				
						}
						
						//Insert WF_ApprovalTrans					
						$subQueryRole = $sql->select();
						$subQueryRole->from("WF_UserspecialRoleTrans")
							->columns(array('UserId'))
							->where("RoleId='$iPendRoleId' and Limit <= $argValue");
						
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_Users"))
							->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sNextRoleName'"), 'UserId', 'RegId' => new Expression("$argRegId")
							, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("1-1"), 'OrderId' => new Expression("1-1")
							, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"), 'Special' => new Expression("1")  ))
							->where("RoleId='$iPendRoleId'");
						$select->where->expression('a.UserId IN ?', array($subQueryRole));
						
						$insert = $sql->insert();
						$insert->into( 'WF_ApprovalTrans' );
						$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime','Special'));
						$insert->Values( $select );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							
						$bSpecial = false;
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_ApprovalTrans"))
							->columns(array("LevelId","Special"))
							->where("a.RoleName = '$sPendRoleName' and RegId = $argRegId and LogId = $argLogId and Status = 0  ");
						$select->order('OrderId Desc');
						$statement = $sql->getSqlStringForSqlObject($select); 
						$taskResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($taskResult) > 0) {
							$iUserLevelId = $taskResult[0]['LevelId'];
							$bSpecial = $taskResult[0]['Special'];
						}
						
						//Insert WF_PendingWorks
						$select = $sql->select();
						if ($iUserLevelId != 0) {				
							$subQueryRole = $sql->select();
							$subQueryRole->from("WF_Users")
								->columns(array('UserId'))
								->where("LevelId='$iUserLevelId'");
						
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_UserRoleTrans"))
								->columns(array( 'PendingRole' => new Expression("'$sPendRoleName'"), 'RoleType' => new Expression("'$sPendRoleType'")
								, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId'
								, 'StartTime' => new Expression("'$argLogTime'"),'DueTime' => new Expression("'$dProcessDate'")))
								->where("RoleId='$iPendRoleId'");
							$select->where->expression('a.UserId IN ?', array($subQueryRole));

							if($argCCId != 0) {
								$subQueryUserCC = $sql->select();
								$subQueryUserCC->from("WF_UserCostCentreTrans")
									->columns(array('UserId'))
									->where("CostCentreId='$argCCId'");
							
								$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
							}
							if($iProjId != 0) {
								$subQueryUserProj = $sql->select();
								$subQueryUserProj->from("WF_UserProjectTrans")
									->columns(array('UserId'))
									->where("ProjectId='$iProjId'");
							
								$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
							}
							if ($sUserId != "") { 
								//$select->where->expression('a.UserId IN ?', array($sUserId));
								$select->where("a.UserId IN ($sUserId)");
							} else { 
								$select->where("a.UserId= 0");
							}	
						} else if ($bSpecial == true) {
							$subQueryRole = $sql->select();
							$subQueryRole->from("WF_UserspecialRoleTrans")
								->columns(array('UserId'))
								->where("RoleId='$iPendRoleId' and Limit <= $argValue ");

							$select = $sql->select(); 
							$select->from(array("a"=>"WF_Users"))
								->columns(array( 'PendingRole' => new Expression("'$sPendRoleName'"), 'RoleType' => new Expression("'$sPendRoleType'")
								, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId'
								, 'StartTime' => new Expression("'$argLogTime'"),'DueTime' => new Expression("'$dProcessDate'") ));
							$select->where->expression('a.UserId IN ?', array($subQueryRole));
						}				
						$insert = $sql->insert();
						$insert->into( 'WF_PendingWorks' );
						$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
						$insert->Values( $select );
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						
						//Multiple Feed Insert Start
						$statementfeed = $sql->getSqlStringForSqlObject($select); 
						$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($feedUserResult as &$feedUserResults) {
							$ifeedUserId = $feedUserResults['UserId'];
							$ifeedLogId = $feedUserResults['LogId'];
							$ifeedPendingRole = $feedUserResults['PendingRole'];
							$ifeedType = $feedUserResults['RoleType'];
							
							$iPendingWorkId =0;
							$select = $sql->select(); 
							$select->from(array("a"=>"WF_PendingWorks"))
								->columns(array("TransId"))
								->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
							$statement = $sql->getSqlStringForSqlObject($select); 
							$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(count($penResult) > 0) {
								$iPendingWorkId = $penResult[0]['TransId'];
								CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
							}
						}
						//Multiple Feed Insert End
	
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
							->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
							->where("a.PendingRole='$sPendRoleName' and a.LogId=$argLogId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$iId = 0;
						$sREfNo = "";
						$iPUserId = 0;
						$iRemId = 0;
						foreach($pendingResult as $pendingResults){
							$iId = $pendingResults['TransId'];
							$sREfNo = $pendingResults['RefNo'];
							if ($sREfNo != "") { 
								$sREfNo = $sPendRoleName . " (" . $sREfNo . ")"; 
							} else { 
								$sREfNo = $sPendRoleName;
							}							
							$iPUserId = $pendingResults['UserId'];
							
							$insert = $sql->insert('WF_ReminderMaster');
							$insert->values(array(
								'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
								'RType'  => 'P' ,'PId'  => $iId
							));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
							
							$insert = $sql->insert('WF_ReminderTrans');
							$insert->values(array(
								'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
								'Live'  => 1 ));
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						}
					}
				}
				
			} else {
				//Insert WF_PendingWorks
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_UserRoleTrans"))
					->columns(array( 'RoleName' => new Expression("'$sPendRoleName'"), 'RoleType' => new Expression("'$sPendRoleType'")
					, 'Field' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId', 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
					->where("a.RoleId='$iPendRoleId'");
				if($argCCId != 0) {
					$subQueryUserCC = $sql->select();
					$subQueryUserCC->from("WF_UserCostCentreTrans")
						->columns(array('UserId'))
						->where("CostCentreId='$argCCId'");
				
					$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
				}
				if($iProjId != 0) {
					$subQueryUserProj = $sql->select();
					$subQueryUserProj->from("WF_UserProjectTrans")
						->columns(array('UserId'))
						->where("ProjectId='$iProjId'");
				
					$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
				}
				
				$insert = $sql->insert();
				$insert->into( 'WF_PendingWorks' );
				$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
				$insert->Values( $select );
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Multiple Feed Insert Start
				$statementfeed = $sql->getSqlStringForSqlObject($select); 
				$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($feedUserResult as &$feedUserResults) {
					$ifeedUserId = $feedUserResults['UserId'];
					$ifeedLogId = $feedUserResults['LogId'];
					$ifeedPendingRole = $feedUserResults['RoleName'];
					$ifeedType = $feedUserResults['RoleType'];
					
					$iPendingWorkId =0;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId"))
						->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($penResult) > 0) {
						$iPendingWorkId = $penResult[0]['TransId'];
						CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
					}
				}
				//Multiple Feed Insert End

				$select = $sql->select();
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
					->where("a.PendingRole='$sPendRoleName' and a.LogId=$argLogId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$iId = 0;
				$sREfNo = "";
				$iPUserId = 0;
				$iRemId = 0;
				foreach($pendingResult as $pendingResults){
					$iId = $pendingResults['TransId'];
					$sREfNo = $pendingResults['RefNo'];
					if ($sREfNo != "") { 
						$sREfNo = $sPendRoleName . " (" . $sREfNo . ")"; 
					} else { 
						$sREfNo = $sPendRoleName;
					}							
					$iPUserId = $pendingResults['UserId'];
					
					$insert = $sql->insert('WF_ReminderMaster');
					$insert->values(array(
						'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
						'RType'  => 'P' ,'PId'  => $iId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_ReminderTrans');
					$insert->values(array(
						'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
						'Live'  => 1 ));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
			
			}
		
		} else if ($iNextActivityId != 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_ActivityMaster"))
				->columns(array('ActivityName','ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
				->where("a.ActivityId='$iNextActivityId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($roledetResult) > 0) {
				$sNextRoleName = $roledetResult[0]['ActivityName'];
				$sIntType = $roledetResult[0]['IntervalType'];
				$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
				$sProcessType = $roledetResult[0]['ProcessType'];
				$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];					
			}
			
			if ($sIntType == "None") { $iIntPeriod = 0; }
			if ($sProcessType == "None") { $iProcessPeriod = 0; }
			
			/*if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}
			}*/
			
			if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") {
					$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") {
					$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}					
			}
			//Insert WF_PendingWorks
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_UserActivityTrans"))
				->columns(array( 'PendingRole' => new Expression("b.ActivityName"), 'RoleType' => new Expression("''")
				, 'NonTask' => new Expression("1"), 'LogId' => new Expression("$argLogId"), 'UserId'
				, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"),'ActivityId' ))
				->join(array("b"=>"WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array(), $select::JOIN_INNER)
				->where("a.ActivityId='$iNextActivityId'");
			if($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from("WF_UserCostCentreTrans")
					->columns(array('UserId'))
					->where("CostCentreId='$argCCId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserCC));
			}
			if($iProjId != 0) {
				$subQueryUserProj = $sql->select();
				$subQueryUserProj->from("WF_UserProjectTrans")
					->columns(array('UserId'))
					->where("ProjectId='$iProjId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserProj));
			}		
			
			$insert = $sql->insert();
			$insert->into( 'WF_PendingWorks' );
			$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime','ActivityId'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			//Multiple Feed Insert Start
			$statementfeed = $sql->getSqlStringForSqlObject($select); 
			$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($feedUserResult as &$feedUserResults) {
				$ifeedUserId = $feedUserResults['UserId'];
				$ifeedLogId = $feedUserResults['LogId'];
				$ifeedPendingRole = $feedUserResults['PendingRole'];
					
				$iPendingWorkId =0;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array("TransId"))
					->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='' and a.NonTask=1 and a.UserId=$ifeedUserId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($penResult) > 0) {
					$iPendingWorkId = $penResult[0]['TransId'];
					CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
				}
			}
			//Multiple Feed Insert End
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_PendingWorks"))
				->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
				->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$iId = 0;
			$sREfNo = "";
			$iPUserId = 0;
			$iRemId = 0;
			foreach($pendingResult as $pendingResults){
				$iId = $pendingResults['TransId'];
				$sREfNo = $pendingResults['RefNo'];
				if ($sREfNo != "") { 
					$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
				} else { 
					$sREfNo = $sNextRoleName;
				}							
				$iPUserId = $pendingResults['UserId'];
				
				$insert = $sql->insert('WF_ReminderMaster');
				$insert->values(array(
					'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
					'RType'  => 'P' ,'PId'  => $iId
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$insert = $sql->insert('WF_ReminderTrans');
				$insert->values(array(
					'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
					'Live'  => 1 ));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
		}	
	}
	
	public function InsertPendingWorkNonTask($argLogTime,$argRoleName,$argRegId,$argLogId,$argDBName,$dbAdapter,$argUserId,$argCCId,$argValue) {
		$iRoleId = 0;
        $iActivityId = 0;
        $iNextActivityId = 0;
        $sIntType = "";
        $sProcessType = "";
		$iIntPeriod = 0;
        $iProcessPeriod = 0;
        $lastest_timestamp = strtotime('+0 days');
		$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
		$sNextRoleApprove = "";
        $iUserLevelId = 0;
        $sql = new Sql( $dbAdapter );
		$select = $sql->select();
        $select = $sql->select();
        $select->from(array("a"=>"WF_ActivityTaskTrans"))
            ->join(array("b"=>"WF_TaskMaster"), "a.TaskId=b.TaskId", array(), $select::JOIN_INNER)
            ->join(array("c"=>"WF_TaskTrans"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
            ->columns(array("ActivityId"))
            ->where("c.RoleId='$iRoleId' ");
		$statement = $sql->getSqlStringForSqlObject($select);
		$activityResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($activityResult) > 0) {
			$iActivityId = $activityResult[0]['ActivityId'];
		}
		
		$select = $sql->select();
		$select->from('Proj_ProjectMaster')
			   ->columns(array('ProjectId'))
			   ->where("ProjectName='$argDBName'");
		$statement = $sql->getSqlStringForSqlObject($select);
		$projListResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($projListResult) > 0) {
			$iProjId = $projListResult[0]['ProjectId'];
		}
		
		$subQuery = $sql->select();
		$subQuery->from("WF_LogTrans")
			->columns(array('LogId'))
			->where("RegisterId=$argRegId");

		$sql = new Sql($dbAdapter);
		$update = $sql->update();
		$update->table( 'WF_PendingWorks' )
			->set( array( 'Status' => '1','FinishTime' => "$argLogTime"))
			->where("PendingRole='$argRoleName' and NonTask=1 ");
		$update->where->expression('LogId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject( $update ); 
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		//Multiple Feed Delete Start
		$select = $sql->select(); 
		$select->from(array("a"=>"WF_PendingWorks"))
			->columns(array('TransId','UserId'))
			->where("a.PendingRole ='$argRoleName' and NonTask=1 and a.Status= 1");
		$select->where->expression('a.LogId IN ?', array($subQuery));
		$statementfeed = $sql->getSqlStringForSqlObject($select); 
		$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		foreach($feedUserResult as &$feedUserResults) {
			$iPendingWorkId = $feedUserResults['TransId'];
			CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $feedUserResults['UserId'], $dbAdapter ,'D');
		}
		//Multiple Feed Delete End
		
		$subQuery = $sql->select();
		$subQuery->from("WF_LogTrans")
			->columns(array('LogId'))
			->where("RegisterId=$argRegId");
		
		$subQueryPendingWorks = $sql->select();
		$subQueryPendingWorks->from("WF_PendingWorks")
			->columns(array('TransId'))
			->where("PendingRole='$argRoleName' and NonTask=1")
			->where->expression('LogId IN ?', array($subQuery));
			
		$subQueryReminderMaster = $sql->select();
		$subQueryReminderMaster->from("WF_ReminderMaster")
			->columns(array('ReminderId'))
			->where("RType='P'")
			->where->expression('PId IN ?', array($subQueryPendingWorks));
			
		$update = $sql->update();	
		$update->table( 'WF_ReminderTrans' )
			->set( array( 'Live' => '0' ))
			->where->expression('ReminderId IN ?', array($subQueryReminderMaster));
		$statement = $sql->getSqlStringForSqlObject( $update ); 
		$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		
		$iNextRoleId = 0;
        $sNextRoleName = "";
        $sNextRoleType = "";
        $bNextMulti = false;
        $bValueApproval = false;
	
		$subQuery = $sql->select();		
		$subQuery->from("WF_ActivityCriticalTrans")
			->columns(array('OrderId' => new Expression("OrderId+1")))
			->where("ActivityId=$iActivityId and RoleId= $iRoleId");
		$select = $sql->select(); 
		$select->from(array("a"=>"WF_ActivityCriticalTrans"))
			->columns(array("RoleId"))
			->where("ActivityId=$iActivityId");
		$select->where->expression('OrderId IN ?', array($subQuery));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$criticaltranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($criticaltranResult) > 0) {
			$iNextRoleId = $criticaltranResult[0]['RoleId'];
		}
		if ($iNextRoleId == 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_ActivityMaster"))
				->columns(array("ActivityId"))
				->where("PrevActivityId=$iActivityId ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$tranResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($tranResult) > 0) {
				$iNextActivityId = $tranResult[0]['ActivityId'];
			}

			if ($iNextActivityId != 0){
				$sNextRoleApprove = "";
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_ActivityCriticalTrans"))
					->columns(array('RoleId'), array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased'))
					->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array('RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased'), $subQuery::JOIN_INNER)
					->where("a.ActivityId='$iNextActivityId' and a.OrderId=1 ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$criticalResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($criticalResult) > 0) {
					$iNextRoleId = $criticalResult[0]['RoleId'];
					$sNextRoleName = $criticalResult[0]['RoleName'];
					$bNextMulti = $criticalResult[0]['MultiApproval'];
					$sNextRoleType = $criticalResult[0]['RoleType'];
					$bValueApproval = $criticalResult[0]['ValueApproval'];
					$sNextRoleApprove = $criticalResult[0]['ApprovalBased'];
				}
				if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }
			}			
		} else {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_TaskTrans"))
				->columns(array('RoleId','RoleName','RoleType','MultiApproval','ValueApproval','ApprovalBased'))
				->where("a.RoleId='$iNextRoleId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$tsakdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($tsakdetResult) > 0) {
				$iNextRoleId = $tsakdetResult[0]['RoleId'];
				$sNextRoleName = $tsakdetResult[0]['RoleName'];
				$bNextMulti = $tsakdetResult[0]['MultiApproval'];
				$sNextRoleType = $tsakdetResult[0]['RoleType'];
				$bValueApproval = $tsakdetResult[0]['ValueApproval'];
				$sNextRoleApprove = $tsakdetResult[0]['ApprovalBased'];
			}
			if ($bNextMulti == true && $sNextRoleApprove == "") { $sNextRoleApprove = "L"; }
		}
		
		if ($iNextRoleId != 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_RoleTrans"))
				->columns(array('ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
				->where("a.ActivityId='$iNextActivityId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($roledetResult) > 0) {
				$sIntType = $roledetResult[0]['IntervalType'];
				$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
				$sProcessType = $roledetResult[0]['ProcessType'];
				$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];				
			}
			
			if ($sIntType == "None") { $iIntPeriod = 0; }
			if ($sProcessType == "None") { $iProcessPeriod = 0; }
			
			/*if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}
			}*/
			if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") {
					$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") {
					$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}					
			}
			
			if ($sNextRoleType == "A") {
                if ($bNextMulti == false) {
					//Insert WF_ApprovalTrans
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('LogId' => new Expression("$argLogId"), 'RoleName' => new Expression("'$sNextRoleName'"), 'UserId' => new Expression("a.UserId"), 'RegId' => new Expression("$argRegId")
						, 'Field' => new Expression("1-1"), 'LevelId' => new Expression("b.LevelId"), 'OrderId' => new Expression("c.OrderId"), 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'")  ))
						->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
						->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
						->where("RoleId='$iNextRoleId'");
					
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");							
						$select->where->expression('a.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
						$select->where->expression('a.UserId IN ?', array($subQueryUserProj));
					}
					
					if ($argValue != 0 && $bValueApproval == true) {
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectGreaterTovalue = $sql->select(); 
						$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId and  ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
						$selectGreaterTovalue->combine($selectBetween,'Union ALL');
					
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectGreaterTovalue));
						
						$select->where->expression('a.UserId IN ?', array($selectUser));
					}
					
					$insert = $sql->insert();
					$insert->into( 'WF_ApprovalTrans' );
					$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Insert WF_PendingWorks
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array( 'PendingRole' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
						, 'NonTask' => new Expression("1"), 'LogId' => new Expression("$argLogId"), 'UserId'
						, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'") ))
						->where("a.RoleId='$iNextRoleId'");
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('UserId IN ?', array($subQueryUserProj));
					}
					if ($argValue != 0 && $bValueApproval == true) {
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectLesser = $sql->select(); 
						$selectLesser->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0 ");
						$selectLesser->combine($selectBetween,'Union ALL');
						
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectLesser));
						
						$select->where->expression('UserId IN ?', array($selectUser));
					}
					
					$insert = $sql->insert();
					$insert->into( 'WF_PendingWorks' );
					$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Multiple Feed Insert Start
					$statementfeed = $sql->getSqlStringForSqlObject($select); 
					$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($feedUserResult as &$feedUserResults) {
						$ifeedUserId = $feedUserResults['UserId'];
						$ifeedLogId = $feedUserResults['LogId'];
						$ifeedPendingRole = $feedUserResults['PendingRole'];
						$ifeedType = $feedUserResults['RoleType'];
						
						$iPendingWorkId =0;
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array("TransId"))
							->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=1 and a.UserId=$ifeedUserId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($penResult) > 0) {
							$iPendingWorkId = $penResult[0]['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
						}
					}
					//Multiple Feed Insert End

					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId","UserId"), array("RefNo"))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
						->where("a.PendingRole='$sNextRoleName' And a.LogId=$argLogId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$iId = 0;
					$sREfNo = "";
					$iPUserId = 0;
					$iRemId = 0;
					foreach($logPendingResult as &$logPendingResults) {
						$iId = $logPendingResults['TransId'];
						$sREfNo = $logPendingResults['RefNo'];
						if ($sREfNo != "") { 
						$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
						} else { $sREfNo = $sNextRoleName; 
						}
						$iPUserId = $logPendingResults['UserId'];
						
						$insert = $sql->insert('WF_ReminderMaster');
						$insert->values(array(
							'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId
							//,'UserId'  => $iUserId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
						
						$insert = $sql->insert('WF_ReminderTrans');
						$insert->values(array(
							'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}			
				} else {
					//Insert WF_ApprovalTrans
//					$subQueryUser = $sql->select();
//					$subQueryUser->from("WF_UserRoleTrans")
//						->columns(array('UserId'))
//							->where("RoleId='$iNextRoleId'");
							
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array('LogId' => new Expression("$argLogId"),'RoleName' => new Expression("'$sNextRoleName'"), 'UserId' => new Expression("b.UserId")
						, 'RegId' => new Expression("'$argRegId'"), 'Field' => new Expression("1"), 'LevelId'=>new Expression("b.LevelId"), 'OrderId' => new Expression("C.OrderId")
						, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'") ))
						->join(array("b"=>"WF_Users"), "a.UserId=b.UserId", array(), $select::JOIN_INNER)
						->join(array("c"=>"WF_LevelMaster"), "b.LevelId=c.LevelId", array(), $select::JOIN_INNER)
						->where("a.RoleId='$iNextRoleId'");
//					$select->where->expression('b.UserId IN ?', array($subQueryUser));
					
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");							
						$select->where->expression('b.UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
						$select->where->expression('b.UserId IN ?', array($subQueryUserProj));
					}
					if ($argValue != 0 && $bValueApproval == true) {	
						$selectBetween = $sql->select(); 
						$selectBetween->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId");
						$selectBetween->where(new Expression(" $argValue Between ValueFrom and ValueTo"));
						
						$selectGreaterTovalue = $sql->select(); 
						$selectGreaterTovalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId and ValueFrom = 0 and ValueTo <= $argValue and ValueTo <> 0 ");
						$selectGreaterTovalue->combine($selectBetween,'Union ALL');
						
						$selectGreaterFromTovalue = $sql->select(); 
						$selectGreaterFromTovalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo <= $argValue and ValueFrom<>0 and ValueTo<>0  ");
						$selectGreaterFromTovalue->combine($selectGreaterTovalue,'Union ALL');
						
						$selectGreaterFromvalue = $sql->select(); 
						$selectGreaterFromvalue->from(array("a"=>"WF_LevelTrans"))
							->columns(array("LevelId"))				
							->where("a.RoleId=$iNextRoleId and ValueFrom <= $argValue and ValueTo =0 and ValueFrom<>0  ");
						$selectGreaterFromvalue->combine($selectGreaterFromTovalue,'Union ALL');
						
						$selectUser = $sql->select(); 
						$selectUser->from(array("a"=>"WF_Users"))
							->columns(array("UserId"));
						$selectUser->where->expression('LevelId IN ?', array($selectGreaterFromvalue));
						
						$select->where->expression('b.UserId IN ?', array($selectUser));
					}
					
					$insert = $sql->insert();
					$insert->into( 'WF_ApprovalTrans' );
					$insert->columns(array('LogId', 'RoleName', 'UserId','RegId','Status','LevelId','OrderId','StartTime','DueTime'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_ApprovalTrans"))
						->columns(array('LevelId'))
						->where("RoleName='$sNextRoleName' and RegId=$argRegId and LogId=$argLogId and Status = 0")
						->order('OrderId Desc');
					$statement = $sql->getSqlStringForSqlObject($select); 
					$appTransdetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($appTransdetResult) > 0) {
						$iUserLevelId = $appTransdetResult[0]['LevelId'];			
					}
					
					//Insert WF_PendingWorks
					$subQueryUser = $sql->select();
					$subQueryUser->from("WF_Users")
						->columns(array('UserId'))
							->where("LevelId='$iUserLevelId'");
							
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_UserRoleTrans"))
						->columns(array( 'PendingRole' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
						, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId'
						, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'") ))
						->where("a.RoleId='$iNextRoleId'");
					$select->where->expression('UserId IN ?', array($subQueryUser));
					if($argCCId != 0) {
						$subQueryUserCC = $sql->select();
						$subQueryUserCC->from("WF_UserCostCentreTrans")
							->columns(array('UserId'))
							->where("CostCentreId='$argCCId'");
					
						$select->where->expression('UserId IN ?', array($subQueryUserCC));
					}
					if($iProjId != 0) {
						$subQueryUserProj = $sql->select();
						$subQueryUserProj->from("WF_UserProjectTrans")
							->columns(array('UserId'))
							->where("ProjectId='$iProjId'");
					
						$select->where->expression('UserId IN ?', array($subQueryUserProj));
					}
					
					$insert = $sql->insert();
					$insert->into( 'WF_PendingWorks' );
					$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
					$insert->Values( $select );
					$statement = $sql->getSqlStringForSqlObject( $insert );
					$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
					
					//Multiple Feed Insert Start
					$statementfeed = $sql->getSqlStringForSqlObject($select); 
					$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					foreach($feedUserResult as &$feedUserResults) {
						$ifeedUserId = $feedUserResults['UserId'];
						$ifeedLogId = $feedUserResults['LogId'];
						$ifeedPendingRole = $feedUserResults['PendingRole'];
						$ifeedType = $feedUserResults['RoleType'];
						
						$iPendingWorkId =0;
						$select = $sql->select(); 
						$select->from(array("a"=>"WF_PendingWorks"))
							->columns(array("TransId"))
							->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
						$statement = $sql->getSqlStringForSqlObject($select); 
						$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(count($penResult) > 0) {
							$iPendingWorkId = $penResult[0]['TransId'];
							CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
						}
					}
					//Multiple Feed Insert End
					
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId","UserId"), array("RefNo"))
						->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
						->where("a.PendingRole='$sNextRoleName' And a.LogId=$argLogId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$iId = 0;
					$sREfNo = "";
					$iPUserId = 0;
					$iRemId = 0;
					foreach($logPendingResult as &$logPendingResults) {
						$iId = $logPendingResults['TransId'];
						$sREfNo = $logPendingResults['RefNo'];
						if ($sREfNo != "") { 
							$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
						} else { 
							$sREfNo = $sNextRoleName; 
						}
						$iPUserId = $logPendingResults['UserId'];
						
						$insert = $sql->insert('WF_ReminderMaster');
						$insert->values(array(
							'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId
							//,'UserId'  => $iUserId
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
						
						$insert = $sql->insert('WF_ReminderTrans');
						$insert->values(array(
							'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
						));
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					return;
					
				}
			} else {
				//Insert WF_PendingWorks	
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_UserRoleTrans"))
					->columns(array( 'PendingRole' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
					, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("$argLogId"), 'UserId'
					, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'") ))
					->where("a.RoleId='$iNextRoleId'");
				if($argCCId != 0) {
					$subQueryUserCC = $sql->select();
					$subQueryUserCC->from("WF_UserCostCentreTrans")
						->columns(array('UserId'))
						->where("CostCentreId='$argCCId'");
				
					$select->where->expression('UserId IN ?', array($subQueryUserCC));
				}
				
				if($iProjId != 0) {
					$subQueryUserProj = $sql->select();
					$subQueryUserProj->from("WF_UserProjectTrans")
						->columns(array('UserId'))
						->where("ProjectId='$iProjId'");
				
					$select->where->expression('UserId IN ?', array($subQueryUserProj));
				}
				
				$insert = $sql->insert();
				$insert->into( 'WF_PendingWorks' );
				$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
				$insert->Values( $select );
				$statement = $sql->getSqlStringForSqlObject( $insert );
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
				
				//Multiple Feed Insert Start
				$statementfeed = $sql->getSqlStringForSqlObject($select); 
				$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				foreach($feedUserResult as &$feedUserResults) {
					$ifeedUserId = $feedUserResults['UserId'];
					$ifeedLogId = $feedUserResults['LogId'];
					$ifeedPendingRole = $feedUserResults['PendingRole'];
					$ifeedType = $feedUserResults['RoleType'];
					
					$iPendingWorkId =0;
					$select = $sql->select(); 
					$select->from(array("a"=>"WF_PendingWorks"))
						->columns(array("TransId"))
						->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
					$statement = $sql->getSqlStringForSqlObject($select); 
					$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(count($penResult) > 0) {
						$iPendingWorkId = $penResult[0]['TransId'];
						CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
					}
				}
				//Multiple Feed Insert End
				
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array("TransId","UserId"), array("RefNo"))
					->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array("RefNo"), $select::JOIN_INNER)
					->where("a.PendingRole='$sNextRoleName' And a.LogId=$argLogId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$logPendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$iId = 0;
				$sREfNo = "";
				$iPUserId = 0;
				$iRemId = 0;
				foreach($logPendingResult as &$logPendingResults) {
					$iId = $logPendingResults['TransId'];
					$sREfNo = $logPendingResults['RefNo'];
					if ($sREfNo != "") { 
						$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
					} else { 
						$sREfNo = $sNextRoleName; 
					}
					$iPUserId = $logPendingResults['UserId'];
					
					$insert = $sql->insert('WF_ReminderMaster');
					$insert->values(array(
						'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),'RType'  => 'P','PId'  => $iId
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
					
					$insert = $sql->insert('WF_ReminderTrans');
					$insert->values(array(
						'UserId'  => $iPUserId,'ReminderId'  => $iRemId,'Live'  => '1'	
					));
					$statement = $sql->getSqlStringForSqlObject($insert);
					$results1 =$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
			}	
		} else if ($iNextActivityId != 0) {
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_ActivityMaster"))
				->columns(array('ActivityName','ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
				->where("a.ActivityId='$iNextActivityId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($roledetResult) > 0) {
				$sNextRoleName = $roledetResult[0]['ActivityName'];
				$sIntType = $roledetResult[0]['IntervalType'];
				$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
				$sProcessType = $roledetResult[0]['ProcessType'];
				$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];			
			}
			
			if ($sIntType == "None") { $iIntPeriod = 0; }
			if ($sProcessType == "None") { $iProcessPeriod = 0; }
			
			/*if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}
			}*/
			if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") {
					$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") {
					$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}					
			}
			
			//Insert WF_PendingWorks
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_UserActivityTrans"))
				->columns(array( 'PendingRole' => new Expression("b.ActivityName"), 'RoleType' => new Expression("''")
				, 'NonTask' => new Expression("1"), 'LogId' => new Expression("$argLogId"), 'UserId'
				, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'"),'ActivityId' ))
				->join(array("b"=>"WF_ActivityMaster"), "a.ActivityId=b.ActivityId", array(), $select::JOIN_INNER)
				->where("a.ActivityId='$iNextActivityId'");
			if($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from("WF_UserCostCentreTrans")
					->columns(array('UserId'))
					->where("CostCentreId='$argCCId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserCC));
			}
			if($iProjId != 0) {
				$subQueryUserProj = $sql->select();
				$subQueryUserProj->from("WF_UserProjectTrans")
					->columns(array('UserId'))
					->where("ProjectId='$iProjId'");
			
				$select->where->expression('UserId IN ?', array($subQueryUserProj));
			}		
			
			$insert = $sql->insert();
			$insert->into( 'WF_PendingWorks' );
			$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime','ActivityId'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			//Multiple Feed Insert Start
			$statementfeed = $sql->getSqlStringForSqlObject($select); 
			$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($feedUserResult as &$feedUserResults) {
				$ifeedUserId = $feedUserResults['UserId'];
				$ifeedLogId = $feedUserResults['LogId'];
				$ifeedPendingRole = $feedUserResults['PendingRole'];
				$ifeedType = $feedUserResults['RoleType'];
				
				$iPendingWorkId =0;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array("TransId"))
					->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=1 and a.UserId=$ifeedUserId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($penResult) > 0) {
					$iPendingWorkId = $penResult[0]['TransId'];
					CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
				}
			}
			//Multiple Feed Insert End
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_PendingWorks"))
				->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
				->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$iId = 0;
			$sREfNo = "";
			$iPUserId = 0;
			$iRemId = 0;
			foreach($pendingResult as $pendingResults){
				$iId = $pendingResults['TransId'];
				$sREfNo = $pendingResults['RefNo'];
				if ($sREfNo != "") { 
					$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
				} else { 
					$sREfNo = $sNextRoleName;
				}							
				$iPUserId = $pendingResults['UserId'];
				
				$insert = $sql->insert('WF_ReminderMaster');
				$insert->values(array(
					'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
					'RType'  => 'P' ,'PId'  => $iId
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$insert = $sql->insert('WF_ReminderTrans');
				$insert->values(array(
					'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
					'Live'  => 1 ));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
		}
	}
	
	public function InsertAlert($argAlertName, $argAlertDescription, $argCCId, $argDBName, $dbAdapter) {
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_AlertMaster'))
			->columns(array('AlertId','Screen','Email','SMS'))
			->where(array('a.AlertName'=>$argAlertName));
		$select_stmt = $sql->getSqlStringForSqlObject($select);
		$resalertDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$iAlertId = 0;
		$bScreen = false;
        $bEmail = false;
        $bSMS = false;		
		if(count($resalertDet) > 0) {
			$iAlertId = $resalertDet[0]['AlertId'];
			if($resalertDet[0]['Screen'] == 1) { $bScreen = true; }
			if($resalertDet[0]['Email'] == 1) { $bEmail = true; }
			if($resalertDet[0]['SMS'] == 1) { $bSMS = true; }
		}	
		//var_dump($bScreen);
		if ($iAlertId != 0) {
			$iScreen = 0;
            $iEmail = 0;
            $iSMS = 0;

			if ($bScreen == true) { $iScreen = 1; }
			if ($bEmail == true) { $iEmail = 1; }
			if ($bSMS == true) { $iSMS = 1; }
			
			$subQuery = $sql->select();
			$subQuery->from(array("a"=>"WF_UserAlertTrans"))
				->columns(array('UserId'))
				->where("a.AlertId=$iAlertId ");
			if ($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from(array("a"=>"WF_UserCostCentreTrans"))
					->columns(array('UserId'))
					->where("a.CostCentreId=$argCCId ");
				
				$subQuery->where->expression('UserId IN ?', array($subQueryUserCC));
			} else {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from(array("a"=>"Proj_ProjectMaster"))
					->columns(array('ProjectId'))
					->where("a.ProjectName='$argDBName'");
					
				$subQueryUserProj = $sql->select();
				$subQueryUserProj->from(array("a"=>"WF_UserProjectTrans"))
					->columns(array('UserId'));
				$subQueryUserProj->where->expression('ProjectId IN ?', array($subQueryUserCC));
				
				$subQuery->where->expression('UserId IN ?', array($subQueryUserProj));
			}
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_Users"))
				->columns(array('UserId','Email' => new Expression("isnull(Email,'')"),'Mobile' => new Expression("isnull(Mobile,'')")));
			$select->where->expression('UserId IN ?', array($subQuery));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$alerttransResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($alerttransResult as $alerttransResults){
				$insert = $sql->insert('WF_AlertTrans');
				$insert->values(array(
					'AlertDescription' => $argAlertDescription,'ScreenReq' => $iScreen,'EMailReq' => $iEmail 
					,'SMSReq' => $iSMS,'EmailId' => $alerttransResults['Email'],'PhoneNo' => $alerttransResults['Mobile']
					,'UserId' => $alerttransResults['UserId']
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);			
			}			
		}
	}
	
	public function GetPasswordPeriod($dbAdapter) {
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_GeneralSetting'))
			->columns(array('PasswordReset'));
		$select_stmt = $sql->getSqlStringForSqlObject($select);
		$resSettingDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resSettingDet) > 0) {
			$this->iPasswordReset = $resSettingDet[0]['PasswordReset'];
		}
	}
	
	public function CheckPowerUser($argUserId, $dbAdapter) {
		$viewRenderer = $this->getServiceLocator()->get('Zend\View\Renderer\RendererInterface');
		$viewRenderer->bPowerUser = false;
        $viewRenderer->bLockUser = false;
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_Users'))
			->columns(array('PowerUser','Lock'))
			->where(array('a.UserId'=>$argUserId));
		$select_stmt = $sql->getSqlStringForSqlObject($select);
		$resUserDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resUserDet) > 0) {
			if($resUserDet[0]['PowerUser'] == 1) { $viewRenderer->bPowerUser = true;}
			if($resUserDet[0]['Lock'] == 1) { $viewRenderer->bLockUser = true;}
		}

		if ($viewRenderer->iPasswordReset != 0) {
			$currentdDate = date('Y-M-d H:i:s');
			$todDate = date('Y-M-d H:i:s', strtotime('+'.$viewRenderer->iPasswordReset.' days'));

			$select = $sql->select(); 
			$select->from(array("a"=>"WF_Users"))
				->columns(array('UserId', 'SetDate' => new Expression("'$currentdDate'"), 'NextDate' => new Expression("'$todDate'") ))
				->where("UserId='$argUserId'");

			$subQueryUserAlert = $sql->select();
			$subQueryUserAlert->from(array("a"=>"WF_UserAlertTrans"))
				->columns(array('UserId' ))
				->join(array("b"=>"WF_AlertMaster"), "a.AlertId=b.AlertId", array(), $subQueryUserAlert::JOIN_INNER)
				->where("AlertName='Password-Reset'");
				
			$subQueryPass = $sql->select();
			$subQueryPass->from(array("a"=>"WF_PasswordTrans"))
				->columns(array('UserId' ));
		
			$select->where->expression('UserId IN ?', array($subQueryUserAlert));
			$select->where->expression('UserId NOT IN ?', array($subQueryPass));
				
			$insert = $sql->insert();
			$insert->into( 'WF_ApprovalTrans' );
			$insert->columns(array('UserId', 'SetDate', 'NextDate'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );	
		}
	}

	public function InsertNextRole($argNextRole, $argLogId, $argLogTime, $argCCId, $dbAdapter) {
		$sNextRoleName = $argNextRole;
        $sNextRoleType = "N";
		
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_TaskTrans'))
			->columns(array('RoleId'))
			->where("a.RoleName='$sNextRoleName'");
		$select_stmt = $sql->getSqlStringForSqlObject($select);
		$resTasktransDet = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$iNextRoleId = 0;	
		if(count($resTasktransDet) > 0) {
			$iNextRoleId = $resTasktransDet[0]['RoleId'];
		}
		
		if ($iNextRoleId != 0) {
			$sIntType = "";
            $iIntPeriod = 0;
            $sProcessType = "";
            $iProcessPeriod = 0;
            $dProcessDate = "";
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_RoleTrans"))
				->columns(array('ProcessType','ProcessPeriod','IntervalType','IntervalPeriod'))
				->where("a.RoleId='$iNextRoleId'");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$roledetResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($roledetResult) > 0) {
				$sIntType = $roledetResult[0]['IntervalType'];
				$iIntPeriod = $roledetResult[0]['IntervalPeriod'];
				$sProcessType = $roledetResult[0]['ProcessType'];
				$iProcessPeriod = $roledetResult[0]['ProcessPeriod'];
			}
		
			if ($sIntType == "None") { $iIntPeriod = 0; }
			if ($sProcessType == "None") { $iProcessPeriod = 0; }
			$dProcessDate = "";
			$lastest_timestamp = strtotime('+0 days');
			$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
			/*if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				
				if ($sProcessType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") { 
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}
			}*/
			if ($iProcessPeriod != 0) {
				$dProcessDate = $argLogTime;
				if ($sProcessType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' minutes', $dProcessDate));						
				} else if ($sProcessType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' hours', $dProcessDate));
				} else if ($sProcessType == "Day") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' days', $dProcessDate));
				} else if ($sProcessType == "Week") {
					$lastest_timestamp = strtotime('+'.($iProcessPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iProcessPeriod * 7).' days', $dProcessDate));
				} else if ($sProcessType == "Month") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' months', $dProcessDate));
				} else if ($sProcessType == "Year") {
					$lastest_timestamp = strtotime('+'.$iProcessPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iProcessPeriod.' years', $dProcessDate));
				}
			}
			if ($iIntPeriod != 0) {
				if ($sIntType == "Minutes") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' minutes');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' minutes', $dProcessDate));
				} else if ($sIntType == "Hour") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' hours');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' hours', $dProcessDate));
				} else if ($sIntType == "Day") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' days', $dProcessDate));
				} else if ($sIntType == "Week") {
					$lastest_timestamp = strtotime('+'.($iIntPeriod * 7).' days');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.($iIntPeriod * 7).' days', $dProcessDate));
				} else if ($sIntType == "Month") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' months');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);		
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' months', $dProcessDate));
				} else if ($sIntType == "Year") {
					$lastest_timestamp = strtotime('+'.$iIntPeriod.' years');
					$dProcessDate= date('Y-m-d H:i:s', $lastest_timestamp);
					//$dProcessDate= date('Y-m-d H:i:s', strtotime('+'.$iIntPeriod.' years', $dProcessDate));
				}					
			}
	
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_UserRoleTrans"))
				->columns(array('PendingRole' => new Expression("'$sNextRoleName'"), 'RoleType' => new Expression("'$sNextRoleType'")
				, 'NonTask' => new Expression("1-1"), 'LogId' => new Expression("'$argLogId'"), 'UserId'
				, 'StartTime' => new Expression("'$argLogTime'"), 'DueTime' => new Expression("'$dProcessDate'") ))
				->where("a.RoleId='$iNextRoleId'");
			
			if($argCCId != 0) {
				$subQueryUserCC = $sql->select();
				$subQueryUserCC->from("WF_UserCostCentreTrans")
					->columns(array('UserId'))
					->where("CostCentreId='$argCCId'");							
				$select->where->expression('b.UserId IN ?', array($subQueryUserCC));
			}
			$insert = $sql->insert();
			$insert->into( 'WF_PendingWorks' );
			$insert->columns(array('PendingRole', 'RoleType', 'NonTask','LogId','UserId','StartTime','DueTime'));
			$insert->Values( $select );
			$statement = $sql->getSqlStringForSqlObject( $insert );
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			
			//Multiple Feed Insert Start
			$statementfeed = $sql->getSqlStringForSqlObject($select); 
			$feedUserResult = $dbAdapter->query($statementfeed, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			foreach($feedUserResult as &$feedUserResults) {
				$ifeedUserId = $feedUserResults['UserId'];
				$ifeedLogId = $feedUserResults['LogId'];
				$ifeedPendingRole = $feedUserResults['PendingRole'];
				$ifeedType = $feedUserResults['RoleType'];
				
				$iPendingWorkId =0;
				$select = $sql->select(); 
				$select->from(array("a"=>"WF_PendingWorks"))
					->columns(array("TransId"))
					->where("a.LogId =$ifeedLogId and a.PendingRole='$ifeedPendingRole' and a.RoleType='$ifeedType' and a.NonTask=0 and a.UserId=$ifeedUserId ");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$penResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				if(count($penResult) > 0) {
					$iPendingWorkId = $penResult[0]['TransId'];
					CommonHelper::UpdateApprovalFeedDetail($iPendingWorkId, $ifeedUserId, $dbAdapter ,'I');
				}
			}
			//Multiple Feed Insert End
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_PendingWorks"))
				->columns(array('TransId','UserId','RefNo' => new Expression("b.RefNo")))
				->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
				->where("a.PendingRole='$sNextRoleName' and a.LogId=$argLogId ");
			$statement = $sql->getSqlStringForSqlObject($select); 
			$pendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$iId = 0;
			$sREfNo = "";
			$iPUserId = 0;
			$iRemId = 0;
			foreach($pendingResult as $pendingResults){
				$iId = $pendingResults['TransId'];
				$sREfNo = $pendingResults['RefNo'];
				if ($sREfNo != "") { 
					$sREfNo = $sNextRoleName . " (" . $sREfNo . ")"; 
				} else { 
					$sREfNo = $sNextRoleName;
				}							
				$iPUserId = $pendingResults['UserId'];

				$insert = $sql->insert('WF_ReminderMaster');
				$insert->values(array(
					'ReminderDescription'  => $sREfNo,'ReminderDate'  => date( 'Y/m/d H:i:s' ),
					'RType'  => 'P' ,'PId'  => $iId
				));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				$iRemId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$insert = $sql->insert('WF_ReminderTrans');
				$insert->values(array(
					'UserId'  => $iPUserId,'ReminderId'  => $iRemId,
					'Live'  => 1 ));
				$statement = $sql->getSqlStringForSqlObject($insert);
				$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			}
		}
	}
	
	public function FindTaskPermission($argUserId,$argTaskName,$dbAdapter)
    {
		$bAns = false;
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_TaskMaster'))
			->columns(array('TaskName','ModuleId'))
			->where(array('a.TaskName'=>$argTaskName));
		$selectTaskstmt = $sql->getSqlStringForSqlObject($select);
		$resTaskDet = $dbAdapter->query($selectTaskstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$select = $sql->select();
		$select->from(array('a' => 'WF_UserRoleTrans'))
			->columns(array('TaskName' => new Expression("Distinct C.TaskName")))
			->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
			->join(array("c"=>"WF_TaskMaster"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
			->where(array('b.TaskName'=>$argTaskName));
		$select->where("a.UserId in ('$argUserId')");
		$selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
		$resTaskRoleDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		if(count($resTaskRoleDet) > 0) {
			$bAns = true;
		}
		return $bAns;		
	}
	
	public function FindPermission($argUserId,$argRoleName,$dbAdapter)
    {
		$bAns = false;
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array('a' => 'WF_TaskTrans'))
			->columns(array('RoleName','ModuleId' => new Expression("b.ModuleId")))
			->join(array("b"=>"WF_TaskMaster"), "a.TaskName=b.TaskName", array(), $select::JOIN_INNER)
			->where(array('a.RoleName'=>$argRoleName));
		$selectTaskstmt = $sql->getSqlStringForSqlObject($select);
		$resTaskDet = $dbAdapter->query($selectTaskstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*
		 sSql = "Select A.RoleName,B.ModuleId from TaskTrans  A " +
                  "Inner Join TaskMaster B on A.TaskName=B.TaskName";
				  
		 sSql = "Select B.RoleName,A.Variant From UserRoleTrans A " +
                   "Inner Join TaskTrans B on A.RoleId=B.RoleId " +
                   "Inner Join TaskMaster C on B.TaskName=C.TaskName " +
                   "Where UserId in  (" + sUserId + ")";
            sSql = sSql + " Union all Select RoleName,0 Variant from TaskTrans Where RoleType='A' and (NotRequired=1 or RoleId not in (Select RoleId from ActivityRoleTrans))";
		*/
		$select = $sql->select();
		$select->from(array('a' => 'WF_UserRoleTrans'))
			->columns(array('RoleName' => new Expression("b.RoleName")))
			->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
			->join(array("c"=>"WF_TaskMaster"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
			->where(array('b.RoleName'=>$argRoleName));
		$select->where("a.UserId in ('$argUserId')");
		$selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
		$resTaskRoleDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resTaskRoleDet) > 0) {
			$bAns = true;
		}
		return $bAns;		
	}

    public function FindPermissionVariant($argUserId,$argRoleName,&$argVariant,$dbAdapter) {
        $bAns = false;
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'WF_UserRoleTrans'))
            ->columns(array('RoleName' => new Expression("b.RoleName"),'Variant'=>new Expression("a.Variant")))
            ->join(array("b"=>"WF_TaskTrans"), "a.RoleId=b.RoleId", array(), $select::JOIN_INNER)
            ->join(array("c"=>"WF_TaskMaster"), "b.TaskName=c.TaskName", array(), $select::JOIN_INNER)
            ->where(array('b.RoleName'=>$argRoleName));
        $select->where("a.UserId in ('$argUserId')");
        $selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
        $resTaskRoleDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(count($resTaskRoleDet) > 0) {
            $bAns = true;
            $argVariant = $this->bsf->isNullCheck($resTaskRoleDet['Variant'],'number');
        }
        return $bAns;
    }

	public function GetApprovalStatus($argRegId,$argRoleName,$argDBName,$argUserId,$dbAdapter)
    {
		$bAns = false;
		$sRoleName = "";
		
		$sql = new Sql($dbAdapter);
		$subQuery = $sql->select();
		$subQuery->from(array("a"=>"WF_TaskTrans"))
			->columns(array('TaskName'))
			->where("a.RoleName='$argRoleName' ");
		
		$select = $sql->select();
		$select->from(array('a' => 'WF_TaskTrans'))
			->columns(array('RoleName'))
			->where("a.RoleType='A' ");
		$select->where->expression('TaskName IN ?', array($subQuery));
		$selectTaskstmt = $sql->getSqlStringForSqlObject($select);
		$resTaskDet = $dbAdapter->query($selectTaskstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resTaskDet) > 0) {
			$sRoleName = $resTaskDet[0]['RoleName'];
		}
		/*
		sSql = "Select A.Status From ApprovalTrans  A " +
                    "Where A.UserId= " + argUserId + " and A.RegId=" + argRegId + " and A.RoleName='" + sRoleName + "' and  " +
                    "A.LogId in (Select LogId from LogTrans Where DBName='" + argDBName + "') Order by OrderId Desc,TransId Desc";
		*/
		$subQuery = $sql->select();
		$subQuery->from(array("a"=>"WF_LogTrans"))
			->columns(array('LogId'))
			->where("a.DBName='$argDBName' ");
			
		$select = $sql->select();
		$select->from(array('a' => 'WF_ApprovalTrans'))
			->columns(array('Status'))
			->where("a.UserId =$argUserId and a.RegId=$argRegId and a.RoleName='$sRoleName' ");
		$select->where->expression('LogId IN ?', array($subQuery));
		$select->order(new Expression("OrderId Desc,TransId Desc"));
		$selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
		$resTaskRoleDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		if(count($resTaskRoleDet) > 0) {
			if($resTaskRoleDet[0]['Status'] == 1) { $bAns = true; }
		}
		return $bAns;		
	}

	public function GetTopApprovalFound($argUserId,$argLogId,$argRoleName,$dbAdapter)
    {
		$bAns = false;
		$sDBName = "";
        $iOrderId = 0;
        $iRegId = 0;
		
		$sql = new Sql($dbAdapter);
		$select = $sql->select();
		$select->from(array("a"=>"WF_LogTrans"))
			->columns(array('RegisterId','DBName'))
			->where("a.LogId='$argLogId' ");
		$selectLogstmt = $sql->getSqlStringForSqlObject($select);
		$resLogDet = $dbAdapter->query($selectLogstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resLogDet) > 0) {
			$iRegId = $resLogDet[0]['RegisterId'];
			$sDBName = $resLogDet[0]['DBName'];
		}
		
		$bSpecial = false;
		
		$subQuery = $sql->select();
		$subQuery->from(array("a"=>"WF_LogTrans"))
			->columns(array('LogId'))
			->where("a.RegisterId = $iRegId and a.DBName='$sDBName' ");
			
		$select = $sql->select();
		$select->from(array('a' => 'WF_ApprovalTrans'))
			->columns(array('Special'))
			->where("a.UserId =$argUserId and a.RoleName='$argRoleName' and a.RegId=$iRegId ");
		$select->where->expression('a.LogId IN ?', array($subQuery));
		$select->order(new Expression("OrderId Desc,TransId Desc"));
		$selectTaskRolestmt = $sql->getSqlStringForSqlObject($select);
		$resApprDet = $dbAdapter->query($selectTaskRolestmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		if(count($resApprDet) > 0) {
			if($resApprDet[0]['Special'] == 1) { $bSpecial = true; }
		}
		
		if ($bSpecial == true) { return $bAns; }

		$select = $sql->select();
		$select->from(array('a' => 'WF_Users'))
			->columns(array('OrderId' => new Expression("b.OrderId")))
			->join(array("b"=>"WF_LevelMaster"), "a.LevelId=b.LevelId", array(), $select::JOIN_INNER)
			->where("a.UserId =$argUserId");
		$selectUserstmt = $sql->getSqlStringForSqlObject($select);
		$resUsersDet = $dbAdapter->query($selectUserstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		if(count($resUsersDet) > 0) {
			$iOrderId = $resUsersDet[0]['OrderId'];
		}
	
		$select = $sql->select();
		$select->from(array('a' => 'WF_ApprovalTrans'))
			->columns(array('TransId'))
			->join(array("b"=>"WF_LogTrans"), "a.LogId=b.LogId", array(), $select::JOIN_INNER)
			->where("a.RoleName ='$argRoleName' and A.OrderID < $iOrderId and A.RegId =$iRegId and B.DBName='$sDBName' and Status=1 and UserID <> $argUserId");
		$selectUserstmt = $sql->getSqlStringForSqlObject($select);
		$resApprovalListDet = $dbAdapter->query($selectUserstmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();			
		if(count($resApprovalListDet) > 0) {
			$bAns = true;
		}
		
		return $bAns;		
	}
	
	public function commonFunctionality($log,$share,$request,$reminder,$ask,$feed,$activityStream,$geoLocation,$approve)
    {
	
	}

	public function CalculatePercentageRequest($RequestTypeId,$dbAdapter) {
		$PercentageTypecal=0;
		$sql = new Sql($dbAdapter);
		$selectMaterialPer1 = $sql->select(); 
		$selectMaterialPer1->from(array("a"=>"VM_RequestRegister"))
			->columns(array("ReqFound"=>new Expression("Count(*) "), "DecFound"=>new Expression("1-1")))
			->where(array("a.RequestType"=>$RequestTypeId));
				
		$Subselect2= $sql->select();
		$Subselect2->from("VM_RequestRegister")
			 ->columns(array("RequestId"))
			 ->where(array("RequestType"=>$RequestTypeId));

		$selectMaterialPer2 = $sql->select(); 
		$selectMaterialPer2->from(array("a"=>"VM_ReqDecTrans"))
			->columns(array("ReqFound"=>new Expression("1-1"),"DecFound"=>new Expression("Count(Distinct(a.RequestId)) ") ))			
			->where->In('a.RequestId',$Subselect2);		
		$selectMaterialPer2->combine($selectMaterialPer1,'Union ALL');	
				
		$selectMainMaterialPer1 = $sql->select();
		$selectMainMaterialPer1->from(array("g"=>$selectMaterialPer2))
			->columns(array("ReqFound"=>new Expression("Sum(G.ReqFound)"), "DecFound"=>new Expression("Sum(G.DecFound)"), "Per"=>new Expression("isnull(((isnull(Sum(G.DecFound),0)*100)/nullif(Sum(G.ReqFound),0)),0)")));
		
		 $statement = $sql->getSqlStringForSqlObject($selectMainMaterialPer1);
		 $resultsList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		 
		$PercentageTypecal=100- $resultsList[0]['Per'];
		 
		return $PercentageTypecal;	
	}

	public function getVendorContact($VendorId) {
		$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$VendorContact = $sql->select(); 
		$VendorContact->from(array("a"=>"Vendor_Contact"))
					->columns(array("CAddress", "Phone1","CPerson1","ContactNo1","Email1"))
					->where(array("a.VendorId"=>$VendorId));
		$statement = $sql->getSqlStringForSqlObject($VendorContact);
		$Vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		return $Vendor;
	}
	
	public function GetWareHouseParentId($wareHouseId, $rowId, $dbAdapter) {
		$sRowId="";
		$sRowId= $rowId.",";
		$sql = new Sql($dbAdapter);
		
		$rowSelect = $sql->select();		
		$rowSelect->from('MMS_WareHouseDetails')
			->columns(array('Id'))
			->where(array("warehouseId"=>$wareHouseId,"ParentId"=>$rowId ));
		$rowSelect->where->notEqualTo('ParentId', 0);
		//$selectResCount->where('a.Rate <> 0')
		$rowStatement = $sql->getSqlStringForSqlObject($rowSelect);
		$rowResult = $dbAdapter->query($rowStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($rowResult)==0)
		{
			return $sRowId;
		} else {
			foreach($rowResult as $row1){
				$sRowId=$sRowId.$row1['Id'].",";
			}

			$sRowId=CommonHelper::GetSubWareHouseParentId($wareHouseId, array_values(array_column($rowResult, 'Id')), $dbAdapter, $sRowId);
		}
		return $sRowId;			
	}
	
	public function GetSubWareHouseParentId($wareHouseId, $rowId, $dbAdapter, $sRowId) {
		$sql = new Sql($dbAdapter);
		$rowSelectParent = $sql->select();		
		$rowSelectParent->from('MMS_WareHouseDetails')
			->columns(array('Id'))
			->where(array("warehouseId"=>$wareHouseId,"ParentId"=>$rowId ));
		$rowStatement = $sql->getSqlStringForSqlObject($rowSelectParent);
		$rowResult1 = $dbAdapter->query($rowStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($rowResult1)==0)
		{
			return $sRowId;
		} else {
			foreach($rowResult1 as $row1){
				$sRowId=$sRowId.$row1['Id'].",";
			}
			
			$sRowId=CommonHelper::GetSubWareHouseParentId($wareHouseId, array_values(array_column($rowResult1, 'Id')),$dbAdapter, $sRowId);
		}
		return $sRowId;
	}

	public function UpdateApprovalFeedDetail($pendingWorkTransId, $userId, $dbAdapter ,$Type) {
		$userGeoDetails = new Container('userGeoDetails');
		$geoLocation = $userGeoDetails->city .", ".$userGeoDetails->region."-".$userGeoDetails->countryCode;
		
		$sql = new Sql($dbAdapter);
		if($Type == "I") {
			$parentFeedId=0;
			$day = date("d");
			$month = date("n");
			$year = date("Y");
			$hour = date("H");
			
			$select = $sql->select(); 
			$select->from(array("a"=>"WF_Feeds"))
				->columns(array('FeedId'))
				->where("a.UserId=$userId and a.FeedType='approval' and DATEPART(dd,a.CreatedDate) ='$day' and DATEPART(mm,a.CreatedDate) ='$month' and DATEPART(yyyy,a.CreatedDate) ='$year' and DATEPART(hh,a.CreatedDate)='$hour' ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$feedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			if(count($feedResult) > 0) {
				$parentFeedId = $feedResult[0]['FeedId'];				
			}
			
			$insert = $sql->insert('WF_Feeds');
			$insert->values(array(
				'UserId'  => $userId, 
				'FeedType'  => 'approval', 
				'ParentId' => $parentFeedId, 
				'PendingWorkId'  => $pendingWorkTransId,
				'Location'=>$geoLocation,
				'CreatedDate'  => date( 'Y/m/d H:i:s' )
			));
			$statement = $sql->getSqlStringForSqlObject($insert);
			$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
			$iFeedId = $dbAdapter->getDriver()->getLastGeneratedValue();
			
			if($parentFeedId == 0){
				$update = $sql->update();
				$update->table( 'WF_Feeds' )
					->set( array( 'ParentId' => $iFeedId ))
					->where("FeedId='$iFeedId'");
				$statement = $sql->getSqlStringForSqlObject( $update ); 
				$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
			}			
		} else {
			$update = $sql->update();
			$update->table( 'WF_Feeds' )
				->set( array( 'DeleteFlag' => 1 ))
				->where("PendingWorkId=$pendingWorkTransId");
			$statement = $sql->getSqlStringForSqlObject( $update ); 
			$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
		}
		
	}
    // Sanitize Number
    public function sanitizeNumber($obj, $digit=2, $currenyType=false, $emptyStr=false) {
        $obj = number_format($obj, $digit,'.', '');
        if($obj == 0 && (!$currenyType || $emptyStr))
            $obj = '';
        elseif($obj == 0 && $currenyType)
            $obj = 0;

        // convert number to indian currency form
        if($currenyType && $obj !== '' && $obj !== 0) {
            $num = $obj;
            $sign = '';
            if ( substr( $num, 0, 1 ) == '-' ) {
                $num = substr( $num, 1 );
                $sign = '-';
            }

            $explrestunits = "";
            $num = preg_replace( '/,+/', '', $num );
            $words = explode( ".", $num );
            $des = "00";
            if ( count( $words ) <= 2 ) {
                $num = $words[ 0 ];
                if ( count( $words ) >= 2 ) {
                    $des = $words[ 1 ];
                }
                if ( strlen( $des ) < 2 ) {
                    $des = "$des";
                }
                else {
                    $des = substr( $des, 0, 2 );
                }
            }
            if ( strlen( $num ) > 3 ) {
                $lastthree = substr( $num, strlen( $num ) - 3, strlen( $num ) );
                $restunits = substr( $num, 0, strlen( $num ) - 3 ); // extracts the last three digits
                $restunits = ( strlen( $restunits ) % 2 == 1 ) ? "0" . $restunits : $restunits; // explodes the remaining digits in 2's formats, adds a zero in the beginning to maintain the 2's grouping.
                $expunit = str_split( $restunits, 2 );
                for ( $i = 0; $i < sizeof( $expunit ); $i++ ) {
                    // creates each of the 2's group and adds a comma to the end
                    if ( $i == 0 ) {
                        $explrestunits .= (int) $expunit[ $i ] . ","; // if is first value , convert into integer
                    }
                    else {
                        $explrestunits .= $expunit[ $i ] . ",";
                    }
                }
                $thecash = $explrestunits . $lastthree;
            }
            else {
                $thecash = $num;
            }

            return "$sign$thecash.$des"; // writes the final format where $currency is the currency symbol.
        }

        return $obj;
    }

    public function numberFormat($obj, $type, $digit=2) {
        if (strtoupper($type) == "C" ) { $digit=2; }
        else if (strtoupper($type) == "Q" ) { $digit=3; }

        $obj = number_format($obj, $digit,'.', '');
        if($obj == 0)
            $obj = '';
        return $obj;
    }

    public function getCityId($cityName, $stateName, $countryName, $dbAdapter) {
        $sql = new Sql($dbAdapter);
        $cityId = null;
        $stateId = null;
        $countryId = null;

        // check for city
        $select = $sql->select();
        $select->from('WF_CityMaster')
            ->columns(array('CityId'))
            ->where("CityName='$cityName'")
            ->limit(1);
        $city_stmt = $sql->getSqlStringForSqlObject($select);
        $city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($city)
            return $city['CityId'];

        // check for state
        $select = $sql->select();
        $select->from('WF_StateMaster')
            ->columns(array('StateId', 'CountryId'))
            ->where("StateName='$stateName'")
            ->limit(1);
        $state_stmt = $sql->getSqlStringForSqlObject($select);
        $state = $dbAdapter->query($state_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if ($state) {
            $stateId = $state['StateId'];
            $countryId = $state['CountryId'];
        } else {
            // state not found
            // check for country

            // get country id
            $select = $sql->select();
            $select->from('WF_CountryMaster')
                ->columns(array('CountryId'))
                ->where("CountryName='$countryName'")
                ->limit(1);
            $cntry_stmt = $sql->getSqlStringForSqlObject($select);
            $country = $dbAdapter->query($cntry_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if($country) {
                // country found
                $countryId = $country['CountryId'];
            } else {
                // country not found have to insert
                $insert = $sql->insert();
                $insert->into('WF_CountryMaster');
                $insert->Values(array('CountryName'=>$countryName));
                $stmt = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                $countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
            }

            // add state
            $insert = $sql->insert();
            $insert->into('WF_StateMaster');
            $insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
            $stmt = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
            $stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
        }

        // add city
        $insert = $sql->insert();
        $insert->into('WF_CityMaster');
        $insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
        $stmt = $sql->getSqlStringForSqlObject($insert);
        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
        $cityId = $dbAdapter->getDriver()->getLastGeneratedValue();

        return $cityId;
    }
    
    public function getCsrfKey() {
        
        // get csrf
        $csrf = new Element\Csrf('csrf');
        $csrfKey = md5($csrf->getValue() . base64_encode($_SERVER['SERVER_NAME']) );
        
        $csrfSession = new Container('csrf');
        $csrfSession->csrfKey = $csrfKey;
        
        return $csrfKey;
    }
    
    public function verifyCsrf($csrfKey) {
        
        $csrfSession = new Container('csrf');
        if ($csrfKey == $csrfSession->csrfKey) {
            return TRUE;
        } else {
            return FALSE;
        }
        
    }

    public function get_client_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    public function insertCBLog($rolename, $refId,$logdescription,$dbAdapter) {
        $userid = $this->auth->getIdentity()->UserId;
        $sipaddress =  CommonHelper::get_client_ip();

        if  (strlen ($logdescription) >255) $slogDescription = substr($logdescription,0,255);
        else $slogDescription = $logdescription;

        $sql = new Sql($dbAdapter);
        $insert = $sql->insert();
        $insert->into('CB_LogMaster');
        $insert->Values( array('CbUserId' => $userid,'RoleName'=>$rolename,'RefId'=>$refId,'IpAddress'=>$sipaddress,'LogDescription'=>$slogDescription));
        $statement = $sql->getSqlStringForSqlObject( $insert );
        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
    }

    public function encodeString($string)
    {
        $key = "This is the encryption .";
        $cipher_alg = MCRYPT_RIJNDAEL_256;
        $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher_alg,MCRYPT_MODE_ECB), MCRYPT_RAND);
        $string = mcrypt_encrypt($cipher_alg, $key, $string, MCRYPT_MODE_ECB, $iv);
        $encrypted_string = base64_encode($string);
        return trim($encrypted_string);
    }
	
    public function decodeString($string)
    {
        $key = "This is the encryption .";
        $cipher_alg = MCRYPT_RIJNDAEL_256;
        $string = base64_decode($string);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher_alg,MCRYPT_MODE_ECB), MCRYPT_RAND);
        $decrypted_string = mcrypt_decrypt($cipher_alg, $key, $string, MCRYPT_MODE_ECB, $iv);
        return trim($decrypted_string);
    }
	
//	public function clientRequest() {
//		$this->auth = new AuthenticationService();
//		$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
//		$subscriberId = $this->auth->getIdentity()->SubscriberId;
//		$sql = new Sql($dbAdapter);
//		$select = $sql->select();
//		$select->from(array('tr' => 'CB_MClientTrans'))
//		->join( array('ma' => 'CB_MClientMaster'), 'tr.MClientId = ma.MClientId', array('ClientName'), $select::JOIN_LEFT)
//			->columns(array('MClientId'),array('ClientName'))
//			->where(array('tr.SubscriberId'=>$subscriberId,'tr.Request'=>1,'tr.Accepted'=>0));
//			//->limit(1);
//		$select_stmt = $sql->getSqlStringForSqlObject($select);
//		$subscribers = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//		return $subscribers;
//	}
//
//	public function clientAdminRequests(){
//		$this->auth = new AuthenticationService();
//		$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
//		$subscriberId = $this->auth->getIdentity()->SubscriberId;
//		$sql = new Sql($dbAdapter);
//		$select = $sql->select();
//		$select->from(array('ma' => 'CB_MClientMaster'))
//			->columns(array('MClientId','ClientName','RequestOn'))
//			->where(array('ma.Request'=>1,'ma.Accepted'=>0));
//			//->limit(1);
//		$select_stmt = $sql->getSqlStringForSqlObject($select);
//		$subscribers = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//		return $subscribers;
//	}
//
//	public function countRequest() {/*
//		$this->auth = new AuthenticationService();
//		$dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
//		$subscriberId = $this->auth->getIdentity()->SubscriberId;
//		$sql = new Sql($dbAdapter);
//		$select = $sql->select();
//		$select->from(array('tr' => 'CB_MClientTrans'))
//		->join( array('ma' => 'CB_MClientMaster'), 'tr.MClientId = ma.MClientId', array('ClientName'), $select::JOIN_LEFT)
//			->columns(array('MClientId'),array('ClientName'))
//			->where(array('tr.SubscriberId'=>$subscriberId,'tr.Request'=>1,'tr.Accepted'=>0));
//			//->limit(1);
//		$select_stmt = $sql->getSqlStringForSqlObject($select);
//		$subscribers = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//		return count($subscribers);*/
//	}

    public function checkTeleCaller() {
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $TeleCalling = $this->auth->getIdentity()->TeleCalling;
        $cUserId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);
        $PositionTypeId=array(5,2,3);
        $sub = $sql->select();
        $sub->from(array('a'=>'WF_PositionMaster'))
            ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
            ->columns(array('PositionId'))
            ->where(array("b.PositionTypeId"=>$PositionTypeId));

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('EmployeeName'))
            ->where->expression("PositionId IN ?",array($sub));
        $select->where(array('UserId' => $this->auth->getIdentity()->UserId,'DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsExe= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $sm = $this->getServiceLocator()->getServiceLocator();
        $config = $sm->get('application')->getConfig();

       $res=array($TeleCalling,0,$cUserId, $config['general']['socketIpCall'],$config['general']['socketIpChat']);
        if($resultsExe!="") {
            $res=array($TeleCalling,1,$cUserId,$config['general']['socketIpCall'],$config['general']['socketIpChat']);

        }
        return $res;
    }

//    public function isUserActive() {
//        $subscriberId = $this->auth->getIdentity()->SubscriberId;
//        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
//        $sql = new Sql($dbAdapter);
//        $select = $sql->select();
//        $select->from('CB_SubscriberMaster')
//            ->columns(array("IsActive"))
//            ->where(array("SubscriberId"=>$subscriberId, "IsActive" => '1'));
//        $statement = $sql->getSqlStringForSqlObject($select);
//        $subscriber = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//        if($subscriber == FALSE)
//            return FALSE;
//
//        return TRUE;
//    }
// Voucher for trans
public function getVoucherTransNo($argTypeId,$argDate,$argCompanyId,$argCCId,$dbAdapter,$argfrom,$cnt)
	{
		$oVtype = array("genType" => false, "voucherNo" => "", "periodWise" => false, "periodId" => 0, "monthWise" => false, "month" => 0, "year" => 0);
		try {
			$iWidth = 0;
            $iStartNo = 0;
            $iMaxNo = 0;
            $iVNo = 0;
            $iLen = 0;
            $sPre = "";
            $sPrefix = "";
            $sSuffix = "";
			
			$sql     = new Sql($dbAdapter);
			$select = $sql->select();
			$select->from('WF_VoucherTypeTrans')
				   ->columns(array('GenType','PeriodWise'))
				   ->where(array("TypeId"=>$argTypeId,"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$voucherTypeResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			if(sizeof($voucherTypeResult) > 0) {
				$oVtype['genType'] = $voucherTypeResult[0]['GenType'];
				$oVtype['periodWise'] = $voucherTypeResult[0]['PeriodWise'];
			}
			if($oVtype['genType'] == true) {
				if($oVtype['periodWise'] == true) {
					$sql     = new Sql($dbAdapter);
					$select = $sql->select();
					$select->from('WF_VoucherPeriodMaster')
						   ->columns(array('PeriodId'))
						   ->where(array("FromDate"=>$argDate,"ToDate"=>$argDate));
					$statement = $sql->getSqlStringForSqlObject($select);
					$voucherMasterResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					if(sizeof($voucherMasterResult) > 0) {
						$oVtype['periodId'] = $voucherMasterResult[0]['PeriodId'];
					}
					if($oVtype['periodId'] != 0) {
						$sql     = new Sql($dbAdapter);
						$select = $sql->select();
						$select->from('WF_VoucherTypePeriod')
							   ->columns(array('Monthwise'))
							   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
						$statement = $sql->getSqlStringForSqlObject($select);
						$voucherTypePeriodResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						if(sizeof($voucherTypePeriodResult) > 0) {
							$oVtype['monthWise'] = $voucherTypePeriodResult[0]['Monthwise'];
						}
						if($oVtype['monthWise'] == true) {
							$oVtype['month'] = date('m',strtotime($argDate));
							$oVtype['year'] = date('Y',strtotime($argDate));
							$sql     = new Sql($dbAdapter);
							$select = $sql->select();
							$select->from('WF_VoucherTypePeriodTrans')
								   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
								   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId,"Month"=>$oVtype['month'],"Year"=>$oVtype['year']));
							$statement = $sql->getSqlStringForSqlObject($select);
							$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							if(sizeof($voucherTypeTransResult) > 0) {
								$iWidth = $voucherTypeTransResult[0]['Width'];
								$iStartNo = $voucherTypeTransResult[0]['StartNo'];
								$iMaxNo = $voucherTypeTransResult[0]['MaxNo'];
								$sPrefix = $voucherTypeTransResult[0]['Prefix'];
								$sSuffix = $voucherTypeTransResult[0]['Suffix'];
								if($iStartNo > $iMaxNo) {
									$iVNo = $iStartNo;
								} else {
									$iVNo = $iMaxNo + 1+ $cnt;
								}
								$iLen = $iWidth - strlen($iVNo);
								$sPre = "";
								for($i = 1; $i < $iLen; $i++) {
									$sPre = $sPre."0";
								}
								$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
							}
						} else {
							$sql     = new Sql($dbAdapter);
							$select = $sql->select();
							$select->from('WF_VoucherTypePeriod')
								   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
								   ->where(array("TypeId"=>$argTypeId,"PeriodId"=>$oVtype['periodId'],"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
							$statement = $sql->getSqlStringForSqlObject($select);
							$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
							if(sizeof($voucherTypeTransResult) > 0) {
								$iWidth = $voucherTypeTransResult['Width'];
								$iStartNo = $voucherTypeTransResult['StartNo'];
								$iMaxNo = $voucherTypeTransResult['MaxNo'];
								$sPrefix = $voucherTypeTransResult['Prefix'];
								$sSuffix = $voucherTypeTransResult['Suffix'];
								if($iStartNo > $iMaxNo) {
									$iVNo = $iStartNo;
								} else {
									$iVNo = $iMaxNo + 1 + $cnt;
								}
								$iLen = $iWidth - strlen($iVNo);
								$sPre = "";
								for($i = 1; $i < $iLen; $i++) {
									$sPre = $sPre."0";
								}
								$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
							}
						}
					}
				} else {
					$sql     = new Sql($dbAdapter);
					$select = $sql->select();
					$select->from('WF_VoucherTypeTrans')
						   ->columns(array('MaxNo','Prefix','StartNo','Width','Suffix'))
						   ->where(array("TypeId"=>$argTypeId,"CCId"=>$argCCId,"CompanyId"=>$argCompanyId));
					$statement = $sql->getSqlStringForSqlObject($select);
					$voucherTypeTransResult   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					if(sizeof($voucherTypeTransResult) > 0) {
						$iWidth = $voucherTypeTransResult['Width'];
						$iStartNo = $voucherTypeTransResult['StartNo'];
						$iMaxNo = $voucherTypeTransResult['MaxNo'];
						$sPrefix = $voucherTypeTransResult['Prefix'];
						$sSuffix = $voucherTypeTransResult['Suffix'];
						if($iStartNo > $iMaxNo) {
							$iVNo = $iStartNo;
						} else {
							$iVNo = $iMaxNo + 1+ $cnt;
						}
						$iLen = $iWidth - strlen($iVNo);
						$sPre = "";
						for($i = 1; $i < $iLen; $i++) {
							$sPre = $sPre."0";
						}
						$oVtype['voucherNo'] = $sPrefix.$sPre.trim($iVNo).$sSuffix;
					}
				}
            }
		} catch (Zend_Exception $e) {
			echo "Error: " . $e->getMessage() . "</br>";
		}

		return $oVtype;
	}
    public function masterSuperior($curUserId,$dbAdapter) {
        $this->UserList=array();
        $this->UserList[]=$curUserId;
        CommonHelper::superiorUsersList($curUserId, $dbAdapter);
        return $this->UserList;
    }
    public function superiorUsersList($curUserId,$dbAdapter) {

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('WF_UserSuperiorTrans')
            ->columns(array('UserId'))
            ->where(array('SUserId' => $curUserId));
        $select_stmt = $sql->getSqlStringForSqlObject($select);
        $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(count($result)>0) {
            foreach($result as $result1) {
                if(!in_array($result1['UserId'],$this->UserList)) {
                    $this->UserList[] = $result1['UserId'];
                }

                CommonHelper::superiorUsersList($result1["UserId"], $dbAdapter);
            }
        }
    }

    public function getTDSSetting($tdsTypeId,$date,$dbAdapter) {

        $oTDS = array("TaxablePer" => 0, "TaxPer" => 0, "SurCharge" => 0, "EDCess" => 0, "HEDCess" => 0, "NetTax" => 0);

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from ('FA_QualPeriod')
               ->columns(array('PeriodId'));
        $select->where("QualType='T' and ((TDate is not null and FDate <= '$date' and TDate >= '$date') or
                        (TDate is null and FDate <= '$date'))");
       $statement = $sql->getSqlStringForSqlObject($select);
        $period = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iPeriodId=0;
        if (!empty($period)) $iPeriodId = $period['PeriodId'];

        $select = $sql->select();
        $select->from ('FA_TDSSetting')
            ->columns(array('TaxablePer','TaxPer','SurCharge','EDCess','HEDCess','NetTax'));
        $select->where("TDSTypeId=$tdsTypeId and PeriodId = $iPeriodId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $tds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($tds)) {
            $oTDS['TaxablePer'] = $tds['TaxablePer'];
            $oTDS['TaxPer'] = $tds['TaxPer'];
            $oTDS['SurCharge'] = $tds['SurCharge'];
            $oTDS['EDCess'] = $tds['EDCess'];
            $oTDS['HEDCess'] = $tds['HEDCess'];
            $oTDS['NetTax'] = $tds['NetTax'];
        }

        return $oTDS;
    }

    public function getSTSetting($worktype,$date,$dbAdapter) {

        $oST = array("TaxablePer" => 0, "TaxPer" => 0, "SurCharge" => 0, "EDCess" => 0, "HEDCess" => 0, "NetTax" => 0,'ReversePer' =>0,'SBCess' =>0,'KKCess'=>0);

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from ('FA_QualPeriod')
            ->columns(array('PeriodId'));
        $select->where("QualType='S' and ((TDate is not null and FDate <= '$date' and TDate >= '$date') or
                        (TDate is null and FDate <= '$date'))");
        $statement = $sql->getSqlStringForSqlObject($select);
        $period = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $iPeriodId=0;
        if (!empty($period)) $iPeriodId = $period['PeriodId'];

        $select = $sql->select();
        $select->from ('FA_ServiceTaxSetting')
            ->columns(array('TaxablePer','TaxPer','SurCharge','KKCess','SBCess','NetTax','ReversePer'));
        $select->where("WorkType='$worktype' and PeriodId = $iPeriodId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $sT = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (!empty($sT)) {
            $oST['TaxablePer'] = $sT['TaxablePer'];
            $oST['TaxPer'] = $sT['TaxPer'];
            $oST['SurCharge'] = $sT['SurCharge'];
            $oST['KKCess'] = $sT['KKCess'];
            $oST['SBCess'] = $sT['SBCess'];
            $oST['NetTax'] = $sT['NetTax'];
            $oST['ReversePer'] = $sT['ReversePer'];
        }
        return $oST;
    }

    public function targetTreeView($in_parent,$store_all_id) {
        $returnString = '';
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);

        if(in_array($in_parent, $store_all_id)) {
            $select = $sql->select();
            $select->from(array("a"=>'WF_UserSuperiorTrans'))
                //->join(array("b"=>"WF_Users"),"a.SUserId=b.UserId",array("UserName"),$select::JOIN_INNER)
                ->where(array('a.SUserId' => $in_parent));
            $select_stmt = $sql->getSqlStringForSqlObject($select);
            $result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $returnString .= $in_parent == 1 ? "<ul class='tree'>" : "<ul>";
            foreach($result as $results) {
                $returnString .= "<li";
                //if ($results['Hide']) {
                    //$returnString .= " class='thide'";
                //}
                $returnString .= "><div id=" . $results['UserId'] . " class='tree_div'>
				<span class='activityName'>" . $results['UserId'] . "</span></div>";
                $returnString .= $this->targetTreeView($results['UserId'], $store_all_id);
                $returnString .= "</li>";
            }
            $returnString .= "</ul>";
        }
        return $returnString;
    }
	public function getCityDetails($cityName,$stateName,$countryName) {
        $returnString = '';
        $this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
		$select->from('WF_CityMaster')
			->columns(array('CityId','StateId','CountryId'))
			->where("CityName='$cityName'")
			->limit(1);
		$city_stmt = $sql->getSqlStringForSqlObject($select);
		$city = $dbAdapter->query($city_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$cityId = null;
		$stateId = null;
		$countryId= null;	
		if ($city) {
			// city found
		$cityId = $city['CityId'];
		$stateId = $city['StateId'];
		$countryId= $city['CountryId'];	
		} else {
			
			// check for state
			$select = $sql->select();
			$select->from('WF_StateMaster')
				->columns(array('StateId', 'CountryId'))
				->where("StateName='$stateName'")
				->limit(1);
			$state_stmt = $sql->getSqlStringForSqlObject($select);
			$state = $dbAdapter->query($state_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
			$stateId = null;
			$countryId = null;
			if ($state) {
				$stateId = $state['StateId'];
				$countryId = $state['CountryId'];
			} else {
				// state not found
				// check for country
				
				// get country id
				$select = $sql->select();
				$select->from('WF_CountryMaster')
					->columns(array('CountryId'))
					->where("CountryName='$countryName'")
					->limit(1);
				$cntry_stmt = $sql->getSqlStringForSqlObject($select);
				$country = $dbAdapter->query($cntry_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				if($country) {
					// country found
					$countryId = $country['CountryId'];
				} else {
					// country not found have to insert
					$insert = $sql->insert();
					$insert->into('WF_CountryMaster');
					$insert->Values(array('CountryName'=>$countryName));
					$stmt = $sql->getSqlStringForSqlObject($insert);
					$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
					$countryId = $dbAdapter->getDriver()->getLastGeneratedValue();
				}
				
				// add state
				$insert = $sql->insert();
				$insert->into('WF_StateMaster');
				$insert->Values(array('StateName'=>$stateName, 'CountryId' => $countryId));
				$stmt = $sql->getSqlStringForSqlObject($insert); 
				$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
				$stateId = $dbAdapter->getDriver()->getLastGeneratedValue();
			}
			
			// add city
			$insert = $sql->insert();
			$insert->into('WF_CityMaster');
			$insert->Values(array('CityName'=>$cityName, 'StateId' => $stateId, 'CountryId' => $countryId));
			$stmt = $sql->getSqlStringForSqlObject($insert);
			$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
			$cityId = $dbAdapter->getDriver()->getLastGeneratedValue();
		}
		$cityDetails=array("CityId"=>$cityId,"StateId"=>$stateId,"CountryId"=>$countryId);
        return $cityDetails;
    }

    public function getFA_FiscalYear() {
        $filledfiscalId=0;
        $companySession = new Container('faCompany');
        $filledfiscalId = $companySession->fiscalId;

        $returnString = '';
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "FA_FiscalYear"))
            ->columns(array("FYearId", "FName"));
        $select->where("a.DeleteFlag=0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $fiscalYearList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //$returnString = "<ul>";
        foreach($fiscalYearList as $fiscalYearLists) {

            if($filledfiscalId==$fiscalYearLists['FYearId']) {
                $returnString .= "<option value='" . $fiscalYearLists['FYearId'] . "' selected>" . $fiscalYearLists['FName'] . "</option>";
            } else {
                $returnString .= "<option value='" . $fiscalYearLists['FYearId'] . "'>" . $fiscalYearLists['FName'] . "</option>";
            }
            //$returnString .= "</li>";
        }
       // $returnString .= "</ul>";
        /*
         *  $transFisSelect = $sql->select();
             $transFisSelect->from('FA_FiscalyearTrans')
                 ->columns(array('CompanyId'));
             $transFisSelect->where("FYearId = $fYearId");

             $select = $sql->select();
             $select->from(array("a" => "WF_CompanyMaster"))
                 ->columns(array("CompanyId", "CompanyName"));
             $select->where("DeleteFlag=0");
             $select->where->In('a.CompanyId', $transFisSelect);

             $statement = $statement = $sql->getSqlStringForSqlObject($select);
             $accCompMasterList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             $this->_view->accCompMasterList = $accCompMasterList;
         */
        return $returnString;
    }

    public function getFA_Companydet() {
        $filledfiscalId=0;
        $filledCompId=0;
        $companySession = new Container('faCompany');
        if($companySession->fiscalId!=""){
            $filledfiscalId = $companySession->fiscalId;
        }
        if($companySession->companyId!="") {
            $filledCompId = $companySession->companyId;
        }

        $returnString = '';
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);

        $transFisSelect = $sql->select();
        $transFisSelect->from('FA_FiscalyearTrans')
            ->columns(array('CompanyId'));
        $transFisSelect->where("FYearId = $filledfiscalId");

        $select = $sql->select();
        $select->from(array("a" => "WF_CompanyMaster"))
            ->columns(array("CompanyId", "CompanyName"));
        $select->where("DeleteFlag=0");
        $select->where->In('a.CompanyId', $transFisSelect);
        $statement = $statement = $sql->getSqlStringForSqlObject($select);
        $companyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //$returnString = "<ul>";
        foreach ($companyList as $companyLists) {
            if ($filledCompId == $companyLists['CompanyId']) {
                $returnString .= "<option value='" . $companyLists['CompanyId'] . "' selected>" . $companyLists['CompanyName'] . "</option>";
            } else {
                $returnString .= "<option value='" . $companyLists['CompanyId'] . "'>" . $companyLists['CompanyName'] . "</option>";
            }
            //$returnString .= "</li>";
        }
        return $returnString;
    }

    public function getFA_SessionfiscalId() {
        $filledfiscalId=0;
        $companySession = new Container('faCompany');
        if($companySession->fiscalId!="") {
            $filledfiscalId = $companySession->fiscalId;
        }
        return $filledfiscalId;
    }

    public function getFA_SessioncompanyId() {
        $filledCompId=0;
        $companySession = new Container('faCompany');
        if($companySession->companyId!="") {
            $filledCompId = $companySession->companyId;
        }
        return $filledCompId;
    }

    public function getFA_SessionFYEndDate() {
        $g_dEndDate="";
        $companySession = new Container('faCompany');
        if($companySession->g_dEndDate!="") {
            $g_dEndDate = $companySession->g_dEndDate;
        }
        return $g_dEndDate;
    }

    public function getFA_SessionFYStartDate() {
        $g_dStartDate="";
        $companySession = new Container('faCompany');
        if($companySession->g_dStartDate!="") {
            $g_dStartDate = $companySession->g_dStartDate;
        }
        return $g_dStartDate;
    }

    public function getFA_SessionFYCurrencyId() {
        $g_iFYCurrencyId=0;
        $companySession = new Container('faCompany');
        if($companySession->g_iFYCurrencyId!="") {
            $g_iFYCurrencyId = $companySession->g_iFYCurrencyId;
        }
        return $g_iFYCurrencyId;
    }
    public function calculateCurrency($fromCurrency, $toCurrency, $amount) {
        $amount = urlencode($amount);
        $fromCurrency = urlencode($fromCurrency);
        $toCurrency = urlencode($toCurrency);
        $rawdata = file_get_contents("https://www.google.com/finance/converter?a=$amount&from=$fromCurrency&to=$toCurrency");
        $data = explode('bld>', $rawdata);
        $data = explode($toCurrency, $data[1]);
        return round($data[0], 2);
    }
}
?>