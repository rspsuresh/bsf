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

use Application\View\Helper\CommonHelper;
use DOMPDF;

class PrintController extends AbstractActionController
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
		
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Print");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql( $dbAdapter );
		
		$request = $this->getRequest();		
		if (!$request->isPost()) {
			$this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
		}
		
		// check if client id
		$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
		if($clientId==0){
			$this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
		}
		
		// check if client exists
		$select = $sql->select();
		$select->from(array('a' => "CB_ClientMaster"))	
			->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
			->where("a.ClientId=$clientId");
		$statement = $statement = $sql->getSqlStringForSqlObject($select);			
		$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$clientName){
			$this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'activity-stream'));
		}
		
		$this->_view->setTerminal(true);
		require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
		
		$content=$request->getPost('htmlcontent');		
		$ClientPass = $clientName['ClientName'];
					
		$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
		'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
		'<head>'.
		 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
		 '<title>Print Preview</title>'.		
		'<style type="text/css">'.
		'.drsElement { position: absolute; border: 1px solid #333; }'.
		'.drsMoveHandle { height: 20px; background-color: #CCC; border-bottom: 1px solid #666; cursor: move;}'.
		'.dragresize { position: absolute; width: 5px; height: 5px; font-size: 1px; background: #EEE; border: 1px solid #333; }'.
		'.dragresize-tl {top: -8px; left: -8px; cursor: nw-resize; }'.
		'.dragresize-tm { top: -8px; left: 50%; margin-left: -4px; cursor: n-resize;}'.
		'.dragresize-tr { top: -8px; right: -8px; cursor: ne-resize;}'.
		'.dragresize-ml {top: 50%;margin-top: -4px;left: -8px;cursor: w-resize;}'.
		'.dragresize-mr {top: 50%;margin-top: -4px;right: -8px;cursor: e-resize;}'.
		'.dragresize-bl {bottom: -8px;left: -8px;cursor: sw-resize;}'.
		'.dragresize-bm {bottom: -8px;left: 50%;margin-left: -4px;cursor: s-resize;}'.
		'.dragresize-br {bottom: -8px;right: -8px;cursor: se-resize;}'.
		'.text-bold, .text-bold *{ font-weight: bold !important; }'.
		'.text-italic, .text-italic *{ font-style: italic !important;}'.
		'.text-underline, .text-underline *{ text-decoration: underline !important;}'.
		'.style-left, .style-left * {text-align: left;}'.
		'.style-center, .style-center * {text-align: center;}'.
		'.style-right, .style-right * {text-align: right;}'.
		'.style-justify, .style-justify * {text-align: justify;}'.
		'#styleOptions{ display:  none; }'.
		'</style>'.
		'</head>'.
		'<body>'. $content. '</body>'.
		'</html>';

		$dompdf = new DOMPDF();		
		//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
		$dompdf->load_html($pdfhtml);
		//$dompdf->set_paper('letter', 'landscape');
		$dompdf->set_paper("A4");
		$dompdf->render();
		$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
		$canvas = $dompdf->get_canvas();
		$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));
		$dompdf->stream("Report.pdf",array('Attachment'=>0));
		
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
		
	}
}