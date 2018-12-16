<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schedule\Controller;

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

class IndexController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
    public function indexAction()	{
		//if(!$this->auth->hasIdentity()) {
		//	$this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
		//}

        $icount = 0;
        $stdate="";
        $eddate="";
        $strText="";
        $ParentId=0;
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$config = $this->getServiceLocator()->get('config');

        $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('v' => 'gantt_tasks'))
            ->columns(array('id','text','start_date','end_date','duration','progress','Predecessor','parent'));//select id,text,start_date,end_date,duration,progress,Predecessor,parent from gantt_tasks Where parent=0
        $select->where(array('parent' => $ParentId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        foreach($typeResult as $row)
        {
            $ParentId=$row['id'];
            if ($icount ==0)
            {
                $strText.= '{ ';
                //$SQLSELECTDate = "select DATEADD(DAY, -3, min(start_date)) stdate,DATEADD(DAY, 30, max(end_Date)) todate from gantt_tasks";
                //$resultD_set = sqlsrv_query($conn,$SQLSELECTDate);
               // while($rowd = sqlsrv_fetch_array($resultD_set))
               // {
                //    $stdate= date('m/d/Y',strtotime($rowd['stdate']));
                //    $eddate=date('m/d/Y',strtotime($rowd['todate']));
               // }

                $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('v' => 'gantt_tasks'))
                    ->columns(array("end_date"))
                        ->order("end_date DESC")
                        ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDay)
                {
                    $eddate=date('m/d/Y',strtotime($rowDay['end_date']));
                }

                $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('v' => 'gantt_tasks'))
                    ->columns(array("start_date"))
                    ->order("start_date ASC")
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDayed)
                {
                    $stdate=date('m/d/Y',strtotime($rowDayed['start_date']));
                }
                   //->array('start_date' => new Zend_Db_Expr('MIN(start_date)'), 'end_date' => new Zend_Db_Expr('MAX(end_date)'),);
                   //$select->from(array('p' => 'gantt_tasks'), array('id', 'start_date' => new Zend_Db_Expr('MIN(p.start_date)')) );

            }
            else
            {
                $strText.= ', { ';
            }
            $strText.= '"TaskID" : ' . $row['id'] . ' ,';
            $strText.= '"TaskName" : "' . $row['text'] . '" ,';
            $strText.= '"StartDate" : "' . date('m/d/Y',strtotime($row['start_date'])) . '" ,';
            $strText.= '"EndDate" : "' . date('m/d/Y',strtotime($row['end_date'])) . '" ,';
            $strText.= '"Duration" : ' . $row['duration'] . ' ,';
            $strText.= '"Progress" : "' . $row['progress'] . '", ';
            $strText.= '"parent" : "' . $row['parent'] . '", ';
            $strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';

            $icount1 = 0;

            //$SQLSELECT1 = "select id,text,start_date,end_date,duration,progress,Predecessor,parent from gantt_tasks Where parent=$ParentId";
            //$result_set1 = sqlsrv_query($conn,$SQLSELECT1);
            $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('v' => 'gantt_tasks'))
                ->columns(array('id','text','start_date','end_date','duration','progress','Predecessor','parent'));//select id,text,start_date,end_date,duration,progress,Predecessor,parent from gantt_tasks Where parent=0
            $select->where(array('parent' => $ParentId));

            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            foreach($typeResult as $row1)
            {
                $SubParentId=$row1['id'];
                if ($icount1 ==0)
                {
                    $strText.= '"Children": [ ';
                    $strText.= "{";
                }
                else
                {
                    $strText.= ", {";
                }
                $strText.= '"TaskID" : ' . $row1['id'] . ' ,';
                $strText.= '"TaskName" : "' . $row1['text'] . '" ,';
                $strText.= '"StartDate" : "' . date('m/d/Y',strtotime($row1['start_date'])) . '" ,';
                $strText.= '"EndDate" : "' . date('m/d/Y',strtotime($row1['end_date'])) . '" ,';
                $strText.= '"Duration" : ' . $row1['duration'] . ' ,';
                $strText.= '"Progress" : "' . $row1['progress'] . '" ,';
                $strText.= '"parent" : "' . $row1['parent'] . '", ';
                $strText.= '"Predecessor" : "' . $row1['Predecessor'] . '" , ';

                //start
                $icount2 = 0;
                //$SQLSELECT2 = "select id,text,start_date,end_date,duration,progress,Predecessor,parent from gantt_tasks Where parent=$SubParentId";
                //$result_set2 = sqlsrv_query($conn,$SQLSELECT2);
                $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('v' => 'gantt_tasks'))
                    ->columns(array('id','text','start_date','end_date','duration','progress','Predecessor','parent'));
                $select->where(array('parent' => $SubParentId));

                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $row2)
                {
                    if ($icount2 ==0)
                    {
                        $strText.= '"Children": [ ';
                        $strText.= "{";
                    }
                    else
                    {
                        $strText.= ", {";
                    }
                    $strText.= '"TaskID" : ' . $row2['id'] . ' ,';
                    $strText.= '"TaskName" : "' . $row2['text'] . '" ,';
                    $strText.= '"StartDate" : "' . date('m/d/Y',strtotime($row2['start_date'])) . '" ,';
                    $strText.= '"EndDate" : "' . date('m/d/Y',strtotime($row2['end_date'])) . '" ,';
                    $strText.= '"Duration" : ' . $row2['duration'] . ' ,';
                    $strText.= '"Progress" : "' . $row2['progress'] . '" ,';
                    $strText.= '"parent" : "' . $row2['parent'] . '", ';
                    $strText.= '"Predecessor" : "' . $row2['Predecessor'] . '" , } ';

                    $icount2=$icount2+1;
                }
                if ($icount2 >=1)
                {
                    $strText.= ' ] ';
                }
                //End
                $strText.= '} ';
                $icount1=$icount1+1;
            }
            if ($icount1 >=1)
            {
                $strText.= ' ] ';
            }

            $icount=$icount+1;
            $strText.= '} ';
        }

        $this->_view->strText = $strText;
        $this->_view->stdate = $stdate;
        $this->_view->eddate = $eddate;
		return $this->_view;
    }
    public function updateAction(){
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postParams = $request->getPost();
            //echo '<pre>'; print_r($postParams); echo '</pre>'; die;
            //echo $postParams['tskName']; die;
            $dbAdapter = $viewRenderer->openDatabase($config['db_details']['gantt']);
            $sql = new Sql($dbAdapter);
            $select = $sql->update();
            $select->table('gantt_tasks');
            $select->set(array(
                'text' => $postParams['tskName'], 'start_date'=>$postParams['stdate'], 'end_date' => $postParams['endate'], 'duration' => $postParams['dration'], 'progress' => $postParams['prgress'], 'Predecessor' => $postParams['prdecessor']
            ));
            $select->where(array('id'=>$postParams['tskId']));

            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
        }
        return $this->_view;
        //$update_query = "UPDATE gantt_tasks SET text = '".$_POST['tskName']."', start_date = '".$_POST['stdate']."', end_date = '".$_POST['endate']."', duration = '".$_POST['dration']."', progress = '".$_POST['prgress']."', Predecessor = '".$_POST['prdecessor']."' WHERE id='".$_POST['tskId']."'";
    }
}
