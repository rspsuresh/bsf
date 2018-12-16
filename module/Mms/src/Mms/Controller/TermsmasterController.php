<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Mms\Controller;

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

class TermsmasterController extends AbstractActionController
{
    public function __construct()	{
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function termsmasterAction(){
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
        $response = $this->getResponse();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $data = $request->getPost();
                if($data['mode']=="select"){
                    $select = $sql->select();
                    $select->from(array("a"=>"WF_TermsMaster"))
                        ->columns(array("*"))
                        ->where(array("TermType"=>$data['TermType']));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $result=json_encode($result);
                }else if($data['mode']=="delete"){

                    $select1 = $sql->select();
                    $select1->from(array("a"=>"MMS_POPaymentTerms"))
                        ->columns(array("TermsId"));

                    $select2 = $sql->select();
                    $select2->from(array("a"=>"MMS_PvPaymentTerms"))
                        ->columns(array("TermsId"));
                    $select2->combine($select1,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("a"=>"WPM_HOGeneralTerms"))
                        ->columns(array("TermsId"));
                    $select3->combine($select2,'Union ALL');

                    $select4 = $sql->select();
                    $select4->from(array("a"=>"WPM_SOGeneralTerms"))
                        ->columns(array("TermsId"));
                    $select4->combine($select3,'Union ALL');

                    $select5 = $sql->select();
                    $select5->from(array("a"=>"WPM_WOGeneralTerms"))
                        ->columns(array("TermsId"));
                    $select5->combine($select4,'Union ALL');

                    $select6 = $sql->select();
                    $select6->from(array("a" =>$select5))
                        ->columns(array("TermsId"))
                        ->where(array("a.TermsId"=>$data['TermId']));
                    $statement = $sql->getSqlStringForSqlObject( $select6 );
                    $termsCh = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $result='';
                    if(count($termsCh) == 0){
                        $del = $sql->delete();
                        $del->from('WF_TermsMaster')
                            ->where(array("TermsId"=>$data['TermId']));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $result='success';
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    for($i=1;$i<$postData['blockrowid'];$i++) {

                        $termsid = $this->bsf->isNullCheck($postData['TermsId_'.$i], 'number');
                        $termstypeid = $this->bsf->isNullCheck($postData['titleId_'.$i], 'number');
                        $title = $this->bsf->isNullCheck($postData['title_'.$i], 'string');
                        $sno = $this->bsf->isNullCheck($postData['sno_'.$i], 'string');
                        $description = $this->bsf->isNullCheck($postData['description_'.$i], 'string');
                        $per = $this->bsf->isNullCheck($postData['per_'.$i], 'number');
                        $value = $this->bsf->isNullCheck($postData['value_'.$i], 'number');
                        $period = $this->bsf->isNullCheck($postData['period_'.$i], 'number');
                        $date = $this->bsf->isNullCheck($postData['date_'.$i], 'number');
                        $string = $this->bsf->isNullCheck($postData['string_'.$i], 'number');
                        $sys = $this->bsf->isNullCheck($postData['sys_'.$i], 'number');
                        $yn =$this->bsf->isNullCheck( $postData['yn_'.$i], 'number');
                        $includegross = $this->bsf->isNullCheck($postData['includegross_'.$i], 'number');
                        $sortorder = $this->bsf->isNullCheck($postData['sortorder_'.$i], 'number');
                        $accountupdate = $this->bsf->isNullCheck($postData['accountupdate_'.$i], 'number');
                        $account = $this->bsf->isNullCheck($postData['account_'.$i], 'number');
                        $termtype = $postData['type'];

//								if($termsid == 0) {
//									if($termstypeid== 0){
//										$insert = $sql->insert();
//										$insert->into('WF_TermsType');
//										$insert->Values(array(
//										'TermsName'=>$title,
//										'Per'=>$per,
//										'Value'=>$value,
//										'Period'=>$period,
//										'TDate'=>$date,
//										'TString'=>$string,
//										'IncludeGross'=>$includegross,
//										'SysDefault'=>$sys,
//										'AccountUpdate'=>$accountupdate));
//										$stmt = $sql->getSqlStringForSqlObject($insert);
//										$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
//										$termstypeid = $dbAdapter->getDriver()->getLastGeneratedValue();
//									}
//										$insert = $sql->insert('WF_TermsMaster');
//										$newData = array(
//											'SlNo'			=> $sno,
//											'Title' 		=>$title,
//											'Description' 	=> $description,
//											'Per' 			=> $per,
//											'Value' 		=> $value,
//											'Period' 		=> $period,
//											'TDate' 		=> $date,
//											'TString'		=> $string,
//											'SysDefault' 	=> $sys,
//											'TermType' 		=> $termtype,
//											'IncludeGross' 	=> $includegross,
//											'AccountId' 	=> $account,
//											'AccountUpdate' => $accountupdate,
//											'YesNo'  		=> $yn,
//											'TermsTypeId' 	=> $termstypeid);
//										$insert->values($newData);
//										$statement = $sql->getSqlStringForSqlObject($insert);
//										$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//								} else {
//									if($termstypeid== 0){
//										$insert = $sql->insert();
//										$insert->into('WF_TermsType');
//										$insert->Values(array(
//										'TermsName'=>$title,
//										'Per'=>$per,
//										'Value'=>$value,
//										'Period'=>$period,
//										'TDate'=>$date,
//										'TString'=>$string,
//										'IncludeGross'=>$includegross,
//										'SysDefault'=>$sys,
//										'AccountUpdate'=>$accountupdate));
//										$stmt = $sql->getSqlStringForSqlObject($insert);
//										$dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
//										$termstypeid = $dbAdapter->getDriver()->getLastGeneratedValue();
//									}
//
//									$update = $sql->update();
//									$update->table('WF_TermsMaster');
//									$update->set(array(
//										'SlNo'			=> $sno,
//										'Title' 		=> $title,
//										'Description' 	=> $description,
//										'Per' 			=> $per,
//										'Value' 		=> $value,
//										'Period' 		=> $period,
//										'TDate' 		=> $date,
//										'TString'		=> $string,
//										'SysDefault' 	=> $sys,
//										'TermType' 		=> $termtype,
//										'IncludeGross' 	=> $includegross,
//										'AccountId' 	=> $account,
//										'AccountUpdate' => $accountupdate,
//										'YesNo'  		=> $yn,
//										'TermsTypeId' 	=> $termstypeid,
//									));
//									$update->where(array('TermsId'=>$termsid));
//									$statement = $sql->getSqlStringForSqlObject($update);
//									$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//								}

                        if($termsid > 0){

                            $update = $sql->update();
                            $update->table('WF_TermsMaster');
                            $update->set(array(
                                'SlNo'			=> $sno,
                                'Title' 		=> $title,
                                'Description' 	=> $description,
                                'Per' 			=> $per,
                                'Value' 		=> $value,
                                'Period' 		=> $period,
                                'TDate' 		=> $date,
                                'TString'		=> $string,
                                'SysDefault' 	=> $sys,
                                'TermType' 		=> $termtype,
                                'IncludeGross' 	=> $includegross,
                                'AccountId' 	=> $account,
                                'AccountUpdate' => $accountupdate,
                                'YesNo'  		=> $yn,
                                'TermsTypeId' 	=> $termstypeid,
                            ));
                            $update->where(array('TermsId'=>$termsid));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            if($title != '') {
                                if ($termstypeid == 0) {
                                    //insert termstype
                                    $insert = $sql->insert();
                                    $insert->into('WF_TermsType');
                                    $insert->Values(array(
                                        'TermsName' => $title,
                                        'Per' => $per,
                                        'Value' => $value,
                                        'Period' => $period,
                                        'TDate' => $date,
                                        'TString' => $string,
                                        'IncludeGross' => $includegross,
                                        'SysDefault' => $sys,
                                        'AccountUpdate' => $accountupdate));
                                    $stmt = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $termstypeid = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }
                                $insert = $sql->insert('WF_TermsMaster');
                                $newData = array(
                                    'SlNo' => $sno,
                                    'Title' => $title,
                                    'Description' => $description,
                                    'Per' => $per,
                                    'Value' => $value,
                                    'Period' => $period,
                                    'TDate' => $date,
                                    'TString' => $string,
                                    'SysDefault' => $sys,
                                    'TermType' => $termtype,
                                    'IncludeGross' => $includegross,
                                    'AccountId' => $account,
                                    'AccountUpdate' => $accountupdate,
                                    'YesNo' => $yn,
                                    'TermsTypeId' => $termstypeid);
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $select = $sql->select();
            $select->from(array("a"=>"WF_TermsType"))
                ->columns(array('data'=>'TermsTypeId','value'=>'TermsName','Per','Value','Period','TDate','TString','IncludeGross','AccountUpdate','SysDefault'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->tid  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql -> select();
            $select ->from (array('a' => 'FA_AccountMaster'))
                ->columns(array('AccountId','AccountName '))
                ->where (array('LastLevel'=>"Y" ,'TypeId' =>22));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->position = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();



            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}