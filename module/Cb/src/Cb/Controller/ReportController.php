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

use PHPExcel;
use PHPExcel_IOFactory;
use Application\View\Helper\CommonHelper;
use DOMPDF;

class ReportController extends AbstractActionController
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
        
	}
	
	public function receiptAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
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

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
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
			//$canvas = $dompdf->get_canvas();
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$receiptId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
			//$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if($receiptId == 0)
				$this->redirect()->toRoute( 'cb/receipt', array( 'controller' => 'receipt', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
													
			$select = $sql->select();
			$select->from(array("a"=>"CB_ReceiptRegister"))
				->join(array("b"=>"CB_WORegister"), "a.WORegisterId=b.WorkOrderId", array("ProjectId","WONo",'WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select::JOIN_INNER)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array("ProjectName"), $select::JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)
				->columns(array("WORegisterId", "ReceiptNo", "ReceiptDate" => new Expression( "FORMAT(a.ReceiptDate, 'dd-MM-yyyy')" ) 
				,"ReceiptAgainst" => new Expression("Case When a.ReceiptAgainst='B' then 'Bill' When a.ReceiptAgainst='M' then 'Mobilization Advance' When a.ReceiptAgainst='A' then 'Adhoc Advance' When a.ReceiptAgainst='R' then 'Retention' When a.ReceiptAgainst='W' then 'With held' else 'Others' End"), "ReceiptMode", "TransactionNo", 
					"TransactionDate" => new Expression( "FORMAT(a.TransactionDate, 'dd-MM-yyyy')" ),"TransactionRemarks", "Amount" )
					, array("ProjectId", "WONo",'WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")),  array("ProjectName")
					, array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
					, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
					, array('ClientCountry'=> new Expression("j.CountryName")));
			$select->where(array( 'a.DeleteFlag' => '0', 'a.ReceiptId' => $receiptId));
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$receiptregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			$rworegid =  $receiptregister->WORegisterId;
			$this->_view->receiptregister = $receiptregister;
			
			//trans
			$select1 = $sql->select();
			$select1->from(array('a' => 'CB_BillMaster'))
					->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount', 'CertifyAmount') )
					->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'PrevAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select1::JOIN_LEFT)
					->where(array( 'a.WoRegisterId' => $rworegid));
			$select1->group(new Expression('a.BillId,a.BillNo,a.BillDate,a.SubmitAmount,a.CertifyAmount'));
			
			$select2 = $sql->select(); 
			$select2->from(array("a"=>"CB_BillMaster"))
					->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount' => new Expression("CAST(0 As Decimal(18,2))")
					, 'CertifyAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))")) )
					->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'PrevAmt' => new Expression("Sum(b.Amount)")), $select2::JOIN_INNER);
			$select2->where("b.ReceiptId<>$receiptId and a.WoRegisterId=$rworegid");
			$select2->combine($select1,'Union ALL');											
			$select2->group(new Expression('a.BillId,a.BillNo,a.BillDate'));

			$select2Edit = $sql->select(); 
			$select2Edit->from(array("a"=>"CB_BillMaster"))
					->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount' => new Expression("CAST(0 As Decimal(18,2))")
					, 'CertifyAmount' => new Expression("CAST(0 As Decimal(18,2))")) )
					->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression("Sum(b.Amount)"),'PrevAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select2Edit::JOIN_INNER);
			$select2Edit->where("b.ReceiptId=$receiptId");
			$select2Edit->combine($select2,'Union ALL');											
			$select2Edit->group(new Expression('a.BillId,a.BillNo,a.BillDate'));
			
			$select3 = $sql->select();
			$select3->from(array("g"=>$select2Edit))
					->columns(array('BillId', 'BillDate', 'BillNo',"SubmitAmount"=>new Expression("Sum(g.SubmitAmount)")
					,"CertifyAmount"=>new Expression("Sum(g.CertifyAmount)"),"CurAmount"=>new Expression("Sum(g.AdjAmount)"),"AdjAmount"=>new Expression("Sum(g.PrevAmt)") ));
			$select3->group(new Expression('g.BillId,g.BillNo,g.BillDate'));
			$statement = $sql->getSqlStringForSqlObject($select3);
			$billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

			foreach($billformats as &$bill) {
				$billId = $bill['BillId'];
				$select = $sql->select();
				$select->from( array('a' => 'CB_BillAbstract' ))
					->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
					->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( ), $select::JOIN_LEFT)
					->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT)
					->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
					array( 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select:: JOIN_INNER);
					//->where("a.BillId=$billId")
				$select->where(array( 'a.BillId' => $billId, 'c.ReceiptId' => $receiptId));
				$select->where( "a.BillFormatId<>0");
				$select->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
				
				$select2 = $sql->select(); 
				$select2->from( array('a' => 'CB_BillAbstract' ))
					->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
					->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( ), $select2::JOIN_LEFT)
					->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select2::JOIN_LEFT)
					->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
					array( 'AdjAmount' => new Expression("Sum(c.Amount)"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select2:: JOIN_INNER);
					//->where("a.BillId=$billId")
				$select2->where("a.BillId=$billId AND a.BillFormatId<>0 AND c.ReceiptId<>$receiptId");
				$select2->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
				$select2->combine($select,'Union ALL');
				
				$select21 = $sql->select(); 
				$select21->from( array('a' => 'CB_BillAbstract' ))
					->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
					->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array(), $select21::JOIN_LEFT)
					->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', 
					array('AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select21:: JOIN_INNER);
					//->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
					//array( 'AdjAmount' => new Expression("Sum(c.Amount)"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT);
				$select21->where("a.BillId=$billId AND a.CurAmount<>0 AND a.BillFormatId<>0");
				$select21->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
				$select21->combine($select2,'Union ALL');			
				
				$select3 = $sql->select();
				$select3->from(array("g"=>$select21))
						->columns(array('BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName','BillId',"AdjAmount"=>new Expression("Sum(g.AdjAmount)")
						,"CurrentAmount"=>new Expression("Sum(g.CurrentAmount)") ));
				$select3->group(new Expression('g.BillAbsId,g.BillFormatId,g.CurAmount,g.TypeName,g.BillId'));
				
				$statement = $sql->getSqlStringForSqlObject($select3);
				$billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$bill['BillAbs'] = $billabs;
			}
			$this->_view->billformats = $billformats;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function workorderdetAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
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

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
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
			//$canvas = $dompdf->get_canvas();
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$workorderId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
			//$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if($workorderId == 0)
				$this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
			
			$select = $sql->select();
			$select->from(array("a" => "CB_WORegister"))
				->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array("ProjectTypeId", "ProjectDescription", "ProjectName"), $select::JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'b.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'a.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)
				->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
					, "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
					, 'PeriodType' => new Expression("Case When a.PeriodType='D' then 'Day' When a.PeriodType='M' then 'Month' else 'Year' End"), "Duration"
					, "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')"), "AuthorityName", "AuthorityAddress"
					, 'AgreementType' => new Expression("Case When a.AgreementType='R' then 'Item Wise Rate' When a.AgreementType='I' then 'Item Wise %' When a.AgreementType='O' then 'Overall %' else 'Turn Key' End")
					, "Duration", "OrderAmount", "OrderPercent", "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')"))
					, array("ProjectTypeId", "ProjectDescription", "ProjectName")
					, array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
					, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
					, array('ClientCountry'=> new Expression("j.CountryName")));
			$select->where(array('a.DeleteFlag' => '0', 'a.WorkOrderId' => $workorderId));
			$statement = $sql->getSqlStringForSqlObject($select);
			$woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->woregister = $woregister;

			// boq
			$select = $sql->select();
			$select->from(array('a' => "CB_WOBOQ"))
					->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
					->columns(array('WOBOQId', 'WOBOQTransId', 'TransType', 'SortId', 'WorkGroupId', 'AgtNo', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'ClientRate', 'ClientAmount', 'Rate', 'Amount'
					, 'RateVariance', 'Header','HeaderType' => new Expression("Case When a.HeaderType='W' then 'WBS' When a.HeaderType='G' then 'WorkGroup' else 'Parent' End")))
					->where("a.WORegisterId=$workorderId and a.TransType='I'")
					->order('a.SortId');
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}

	public function materialadvdetAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
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

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
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
			//$canvas = $dompdf->get_canvas();
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billHeader = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billHeader = $billHeader;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			
			$this->_view->billHeader = $billHeader;
			$this->_view->typeEntry = $typeEntry;
			
			 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);
				
				//For Material Advance Report
				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno','TypeName'=> new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"),'Description', 'Sign', 'Header'))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId and a.BillFormatId=3 ")
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
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '2': // Non-Agreement
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_NonAgtItemMaster' ), 'a.NonBOQId=b.NonBOQId', array( 'SlNo','Specification','NonBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement','CellName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '3': //Material Advance
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillMaterialAdvance' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
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
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId");
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
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("isnull(b.Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.ReceiptId<>0");
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId AND b.ReceiptId<>0");
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
							
							//Advance Recovery BillAbstract FormatTypeId=3
							$select = $sql->select();
							$select->from( array('a' => 'CB_BillAbstract' ))
								->columns(array( 'BillAbsId', 'BillId', 'BillFormatId' => new Expression("6"), 'Amount' => new Expression("a.CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
								->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
								->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
							$select->where("a.CurAmount<>0 ");
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.Amount) ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select21->combine($select,'Union ALL');
							
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.BillId<>$billId");
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '7': // Bill Deduction
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillVendorBill' ) )
								->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
								->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '19': // Free Supply Material
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
			$this->_view->billinfo = $billinfo;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function priceesclationdetAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
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

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
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
			//$canvas = $dompdf->get_canvas();
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billHeader = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billHeader = $billHeader;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			
			$this->_view->billHeader = $billHeader;
			$this->_view->typeEntry = $typeEntry;
			
			 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);
				
				//For Price Escalation Report
				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header'))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId and a.BillFormatId=18 ")
					->order('a.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				foreach($BillFormat as &$Format) {
					$billFormatId= $Format['BillFormatId'];
					$billAbsId= $Format['BillAbsId'];
					switch($billFormatId) {						
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('PBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
			$this->_view->billinfo = $billinfo;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function reportabstractcurrentAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 '<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.
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

			'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.
			'</head>'.
			'<body>'. $content. '</body>';
			'</html>';

			$dompdf = new DOMPDF();		
			//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
			$dompdf->load_html($pdfhtml);
			//$dompdf->set_paper('letter', 'landscape');
			$dompdf->set_paper("A4");
			//$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
			$canvas = $dompdf->get_canvas();
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/

			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billinfo = $billinfo;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			$this->_view->typeEntry = $typeEntry;
			
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function reportabstractsubvscerAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 '<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.
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

			'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.
			'</head>'.
			'<body>'. $content. '</body>';
			'</html>';

			$dompdf = new DOMPDF();		
			//$dompdf->load_html($pdfhtml, 'html', 'UTF-8');
			$dompdf->load_html($pdfhtml);
			//$dompdf->set_paper('letter', 'landscape');
			$dompdf->set_paper("A4");
			//$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
			$canvas = $dompdf->get_canvas();
			$canvas->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
						
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billinfo = $billinfo;
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillAbstract"))
				->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
				->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
				, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
				->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount','CerCurAmount',
				'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
				, array(), array(), array('Header','Bold','Italic','Underline') );
			
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function sampleAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
		
		$request = $this->getRequest();
		if($type=="workorder"){
			$dir = 'public/reports/workorder/'. $subscriberId;
			$filePath = $dir.'/wo_template.phtml';		
		} else if($type=="rabill"){
			$dir = 'public/reports/rabill/'. $subscriberId;
			$filePath = $dir.'/rabill_template.phtml';
		} else if($type=="receipt"){
			$dir = 'public/reports/receipt/'. $subscriberId;
			$filePath = $dir.'/receipt_template.phtml';
		}
		
		if ($request->isPost()) {		
			$content=$request->getPost('htmlcontent');
			
			mkdir($dir);
			file_put_contents($filePath, $content);
			
			if($type=="workorder"){
				$this->redirect()->toRoute("cb/workorder", array("controller" => "workorder","action" => "register"));
			} else if($type=="rabill"){
				$this->redirect()->toRoute("cb/clientbilling", array("controller" => "clientbilling","action" => "register"));
			} else if($type=="receipt"){
				$this->redirect()->toRoute("cb/receipt", array("controller" => "receipt","action" => "register"));
			}
		} else {
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			if($type != 'workorder' && $type != 'rabill' && $type != 'receipt')
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
								
			if (!file_exists($filePath)) {
				if($type=="workorder"){
					$filePath = 'public/reports/workorder/template.phtml';
				} else if($type=="rabill"){
					$filePath = 'public/reports/rabill/template.phtml';
				} else if($type=="receipt"){
					$filePath = 'public/reports/receipt/template.phtml';
				}	
			}
			$this->_view->type = $type;
			$template = file_get_contents($filePath);
			$this->_view->template = $template;

			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function test1Action(){
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
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			/*demo link 
			http://ibmphp.blogspot.in/2012/11/dompdf-every-page-with-header-and-footer.html*/
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			//'<html>'.
			//'<head>'.
			  '<style>'.
				'@page { margin: 180px 50px; }'.
				'#header { position: fixed; left: 0px; top: -180px; right: 0px; height: 150px; background-color: orange; text-align: center; }'.
				'#footer { position: fixed; left: 0px; bottom: -180px; right: 0px; height: 150px; background-color: lightblue; }'.
				'#footer .page:after { content: counter(page, upper-roman); }'.
			  '</style>'.
			  '</head>'.
			'<body>'.
			  '<div id="header">'.
				'<h1>Header Title</h1>'.
			  '</div>'.
			  '<div id="footer">'.
				'<p class="page"><a href="ibmphp.blogspot.com">Footer Title</a></p>'.
			  '</div>'.
			  '<div id="content">'.
				'<p><a href="ibmphp.blogspot.com">ibmphp.blogspot.com242323</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com1</a></p>'.
				'<p><a href="ibmphp.blogspot.com">ibmphp.blogspot.com2</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com3</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com4</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com5</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com6</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com7</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com8</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com9</a></p>'.
				'<p style="page-break-before: always;"><a href="ibmphp.blogspot.com">ibmphp.blogspot.com10</a></p>'.
			  '</div>'.
			'</body>'.
			'</html>';

			$dompdf = new DOMPDF();
			$dompdf->load_html($pdfhtml);
			$dompdf->set_paper("A4");
			$dompdf->render();
			$dompdf->stream("Report.pdf");

		} else {
			
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}

	public function reportabstractmeasureAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
			
			$content=$request->getPost('htmlcontent');
			$clientId= $this->bsf->isNullCheck( $request->getPost('clientId'), 'number' );
			$select = $sql->select();
			$select->from(array('a' => "CB_ClientMaster"))	
				->columns( array('ClientName'=> new Expression("LEFT(a.ClientName, 4)")) )
				->where("a.ClientId=$clientId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);			
			$clientName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			//$ClientPass = substr($clientName['ClientName'], 4);
			$ClientPass = $clientName['ClientName'];
			//if ( !DOMPDF_ENABLE_REMOTE){$path = DOMPDF_LIB_DIR;}
			//$path=$path.'/res/';
			//$content = str_replace('/bsf_v1.0/public/images/', $path, $content);
			$content = str_replace('<button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button>', '', $content);
							
			$pdfhtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'.
			'<head>'.
			 '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />'.
			 '<title>Div Drag/Resize Demo</title>'.
			 //'<script type="text/javascript" src="library/gridstack/dragresize.js"></script>'.
			 /*'<script type="text/javascript">'.
			 ' if(typeof addEvent!="function"){var addEvent=function(o,t,f,l){var d="addEventListener",n="on"+t,rO=o,rT=t,rF=f,rL=l;if(o[d]&&!l)return o[d](t,f,false);if(!o._evts)o._evts={};if(!o._evts[t]){o._evts[t]=o[n]?{b:o[n]}:{};o[n]=new Function("e","var r=true,o=this,a=o._evts[""+t+""],i;for(i in a){o._f=a[i];r=o._f(e||window.event)!=false&&r;o._f=null}return r");if(t!="unload")addEvent(window,"unload",function(){removeEvent(rO,rT,rF,rL)})}if(!f._i)f._i=addEvent._i++;o._evts[t][f._i]=f};addEvent._i=1;var removeEvent=function(o,t,f,l){var d="removeEventListener";if(o[d]&&!l)return o[d](t,f,false);if(o._evts&&o._evts[t]&&f._i)delete o._evts[t][f._i]}}function cancelEvent(e,c){e.returnValue=false;if(e.preventDefault)e.preventDefault();if(c){e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation()}};function DragResize(myName,config){var props={myName:myName,enabled:true,handles:["tl","tm","tr","ml","mr","bl","bm","br"],isElement:null,isHandle:null,element:null,handle:null,minWidth:10,minHeight:10,minLeft:0,maxLeft:9999,minTop:0,maxTop:9999,zIndex:1,mouseX:0,mouseY:0,lastMouseX:0,lastMouseY:0,mOffX:0,mOffY:0,elmX:0,elmY:0,elmW:0,elmH:0,allowBlur:true,ondragfocus:null,ondragstart:null,ondragmove:null,ondragend:null,ondragblur:null};for(var p in props)this[p]=(typeof config[p]=="undefined")?props[p]:config[p]};DragResize.prototype.apply=function(node){var obj=this;addEvent(node,"mousedown",function(e){obj.mouseDown(e)});addEvent(node,"mousemove",function(e){obj.mouseMove(e)});addEvent(node,"mouseup",function(e){obj.mouseUp(e)})};DragResize.prototype.select=function(newElement){with(this){if(!document.getElementById||!enabled)return;if(newElement&&(newElement!=element)&&enabled){element=newElement;element.style.zIndex=++zIndex;if(this.resizeHandleSet)this.resizeHandleSet(element,true);elmX=parseInt(element.style.left);elmY=parseInt(element.style.top);elmW=element.offsetWidth;elmH=element.offsetHeight;if(ondragfocus)this.ondragfocus()}}};DragResize.prototype.deselect=function(delHandles){with(this){if(!document.getElementById||!enabled)return;if(delHandles){if(ondragblur)this.ondragblur();if(this.resizeHandleSet)this.resizeHandleSet(element,false);element=null}handle=null;mOffX=0;mOffY=0}};DragResize.prototype.mouseDown=function(e){with(this){if(!document.getElementById||!enabled)return true;var elm=e.target||e.srcElement,newElement=null,newHandle=null,hRE=new RegExp(myName+"-([trmbl]{2})","");while(elm){if(elm.className){if(!newHandle&&(hRE.test(elm.className)||isHandle(elm)))newHandle=elm;if(isElement(elm)){newElement=elm;break}}elm=elm.parentNode}if(element&&(element!=newElement)&&allowBlur)deselect(true);if(newElement&&(!element||(newElement==element))){if(newHandle)cancelEvent(e);select(newElement,newHandle);handle=newHandle;if(handle&&ondragstart)this.ondragstart(hRE.test(handle.className))}}};DragResize.prototype.mouseMove=function(e){with(this){if(!document.getElementById||!enabled)return true;mouseX=e.pageX||e.clientX+document.documentElement.scrollLeft;mouseY=e.pageY||e.clientY+document.documentElement.scrollTop;var diffX=mouseX-lastMouseX+mOffX;var diffY=mouseY-lastMouseY+mOffY;mOffX=mOffY=0;lastMouseX=mouseX;lastMouseY=mouseY;if(!handle)return true;var isResize=false;if(this.resizeHandleDrag&&this.resizeHandleDrag(diffX,diffY)){isResize=true}else{var dX=diffX,dY=diffY;if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmX+=diffX;elmY+=diffY}with(element.style){left=elmX+"px";width=elmW+"px";top=elmY+"px";height=elmH+"px"}if(window.opera&&document.documentElement){var oDF=document.getElementById("op-drag-fix");if(!oDF){var oDF=document.createElement("input");oDF.id="op-drag-fix";oDF.style.display="none";document.body.appendChild(oDF)}oDF.focus()}if(ondragmove)this.ondragmove(isResize);cancelEvent(e)}};DragResize.prototype.mouseUp=function(e){with(this){if(!document.getElementById||!enabled)return;var hRE=new RegExp(myName+"-([trmbl]{2})","");if(handle&&ondragend)this.ondragend(hRE.test(handle.className));deselect(false)}};DragResize.prototype.resizeHandleSet=function(elm,show){with(this){if(!elm._handle_tr){for(var h=0;h<handles.length;h++){var hDiv=document.createElement("div");hDiv.className=myName+" "+myName+"-"+handles[h];elm["_handle_"+handles[h]]=elm.appendChild(hDiv)}}for(var h=0;h<handles.length;h++){elm["_handle_"+handles[h]].style.visibility=show?"inherit":"hidden"}}};DragResize.prototype.resizeHandleDrag=function(diffX,diffY){with(this){var hClass=handle&&handle.className&&handle.className.match(new RegExp(myName+"-([tmblr]{2})"))?RegExp.$1:"";var dY=diffY,dX=diffX,processed=false;if(hClass.indexOf("t")>=0){rs=1;if(elmH-dY<minHeight)mOffY=(dY-(diffY=elmH-minHeight));else if(elmY+dY<minTop)mOffY=(dY-(diffY=minTop-elmY));elmY+=diffY;elmH-=diffY;processed=true}if(hClass.indexOf("b")>=0){rs=1;if(elmH+dY<minHeight)mOffY=(dY-(diffY=minHeight-elmH));else if(elmY+elmH+dY>maxTop)mOffY=(dY-(diffY=maxTop-elmY-elmH));elmH+=diffY;processed=true}if(hClass.indexOf("l")>=0){rs=1;if(elmW-dX<minWidth)mOffX=(dX-(diffX=elmW-minWidth));else if(elmX+dX<minLeft)mOffX=(dX-(diffX=minLeft-elmX));elmX+=diffX;elmW-=diffX;processed=true}if(hClass.indexOf("r")>=0){rs=1;if(elmW+dX<minWidth)mOffX=(dX-(diffX=minWidth-elmW));else if(elmX+elmW+dX>maxLeft)mOffX=(dX-(diffX=maxLeft-elmX-elmW));elmW+=diffX;processed=true}return processed}};'.
			 '</script>'.*/
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

			/*'<script type="text/javascript">'.
			'var dragresize = new DragResize("dragresize",'.
			 '{ minWidth: 50, minHeight: 50, minLeft: 20, minTop: 20, maxLeft: 600, maxTop: 600 });'.
			'dragresize.isElement = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsElement") > -1) return true;'.
			'};'.
			'dragresize.isHandle = function(elm)'.
			'{'.
			 'if (elm.className && elm.className.indexOf("drsMoveHandle") > -1) return true;'.
			'};'.
			'dragresize.ondragfocus = function() { };'.
			'dragresize.ondragstart = function(isResize) { };'.
			'dragresize.ondragmove = function(isResize) { };'.
			'dragresize.ondragend = function(isResize) { };'.
			'dragresize.ondragblur = function() { };'.
			'dragresize.apply(document);'.
			'</script>'.*/
			'</head>'.
			'<body>'. $content. '</body>';
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
			//$canvas = $dompdf->get_canvas();
			//$dompdf-> page_text ($ w - 60, $ h - 10, "{page_num} - {PAGE_COUNT}", $ font, 10, array (0.5,0.5,0.5));
			//$font = Font_Metrics::get_font("helvetica", "bold");
			//$dompdf->page_text(16, 800, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

			$dompdf->stream("Report.pdf");

		} else {
			$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
			$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
			
			
			if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
			// check for bill id and subscriber id
			/*$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
				->columns(array('BillId'))
				->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
				$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
			
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select:: JOIN_LEFT)
				->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectName'), $select:: JOIN_LEFT)
				->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
				->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array('CityName'), $select:: JOIN_LEFT)
				->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array('StateName'), $select:: JOIN_LEFT)
				->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array('CountryName'), $select:: JOIN_LEFT)
				->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array('ClientCity'=> new Expression("h.CityName")), $select:: JOIN_LEFT)
				->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array('ClientState'=> new Expression("i.StateName")), $select:: JOIN_LEFT)
				->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array('ClientCountry'=> new Expression("j.CountryName")), $select:: JOIN_LEFT)	
				->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
				,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")),array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectName'),array('ClientId','ClientName','ClientAddress'=> new Expression("c1.Address")),array('BusinessName','Address','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array('CityName'), array('StateName'), array('CountryName'), array('ClientCity'=> new Expression("h.CityName")), array('ClientState'=> new Expression("i.StateName"))
				, array('ClientCountry'=> new Expression("j.CountryName")) )
				->where("a.BillId=$billId");
			$statement = $statement = $sql->getSqlStringForSqlObject($select);
			$billHeader = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->billHeader = $billHeader;
			$typeEntry = "Certify";
			if($type=='S'){
				$typeEntry = "Submit";
			}
			
			$this->_view->billHeader = $billHeader;
			$this->_view->typeEntry = $typeEntry;
			
			 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);
				
				//start
				$select = $sql->select();
				if($type == "S"){
					$select->from(array('a' => "CB_BillAbstract"))
						->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
						->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
						->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
						, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
						->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount','PrevAmount','CurAmount',
						'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
						, array(), array(), array('Header','Bold','Italic','Underline') );
				} else {
					$select->from(array('a' => "CB_BillAbstract"))
						->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
						->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
						->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
						, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
						->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount'=> new Expression("a.CerCumAmount")
						,'PrevAmount'=> new Expression("a.CerPrevAmount"),'CurAmount'=> new Expression("a.CerCurAmount"),
						'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
						, array(), array(), array('Header','Bold','Italic','Underline') );
				}
				$select->where("a.BillId=$billId");
				$select->order('d.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$this->_view->billsAbsts = $billsAbsts;
				
				$select3 = $sql->select();
				$select1 = $sql->select();
				if($type == "S"){
					$select1->from(array('a' => "CB_BillBOQ"))
						->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
						->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
						->columns( array('BillBOQId','BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
						,array('AgtNo','Specification'),array('BillId') );
					$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
					
					$select2 = $sql->select();
					$select2->from(array('a' => "CB_BillBOQ"))
						->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
						->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
						->columns( array('BillBOQId','BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
						,array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
					$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
					
					$select2->combine($select1,'Union ALL');
								
					$select3 = $sql->select();
					$select3->from(array("g"=>$select2))
							->join(array('b' => 'CB_BillMeasurement'), 'g.BillBOQId=b.BillBOQId', array('Measurement'), $select3:: JOIN_INNER)
							->columns(array("BillBOQId","BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));							
					
				} else {
					$select1->from(array('a' => "CB_BillBOQ"))
						->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
						->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
						->columns( array('BillBOQId','BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
						'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
						,array('AgtNo','Specification'),array('BillId') );
					$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
					
					$select2 = $sql->select();
					$select2->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
						->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
						->columns( array('BillBOQId','BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
						'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
						, array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
					$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
					
					$select2->combine($select1,'Union ALL');
								
					$select3 = $sql->select();
					$select3->from(array("g"=>$select2))
							->join(array('b' => 'CB_BillMeasurement'), 'g.BillBOQId=b.BillBOQId', array('Measurement'), $select3:: JOIN_INNER)
							->columns(array("BillBOQId","BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));
				}
				$statement = $sql->getSqlStringForSqlObject( $select3 );
				$billsAbstIOws = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$this->_view->billsAbstIOws = $billsAbstIOws;
				
				//measurement
				/*$select1 = $sql->select();
				$select1->from(array('a' => "CB_BillMeasurement"))
						->join(array('b' => 'CB_BillBOQ'), 'a.BillBOQId=b.BillBOQId', array(), $select1:: JOIN_INNER)
						->join(array('c' => 'CB_BillAbstract'), 'b.BillAbsId=c.BillAbsId And b.BillFormatId=c.BillFormatId', array(), $select1:: JOIN_INNER)	
						->columns( array('BillBOQId','Measurement') );
				$select1->where("c.BillId=$billId");
				$statement = $sql->getSqlStringForSqlObject( $select1 );
				$billsMeasures = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				$this->_view->billsMeasures = $billsMeasures;*/
			}
			$this->_view->billinfo = $billinfo;
			// csrf Key
			$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
		}
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function workorderdetreportAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		$request = $this->getRequest();
		
		if ($request->isPost()) {
			$this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );
		}
					
		$dir = 'public/reports/workorder/'. $subscriberId;
		$filePath = $dir.'/wo_template.phtml';
		$workorderId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
		if($workorderId == 0)
			$this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );
		
		if (!file_exists($filePath)) {
			$filePath = 'public/reports/workorder/template.phtml';
		}
		
		$template = file_get_contents($filePath);
		$this->_view->template = $template;

		// check for bill id and subscriber id
		/*$select = $sql->select();
		$select->from(array('a' => "CB_BillMaster"))
			->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
			->columns(array('BillId'))
			->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
		$statement = $sql->getSqlStringForSqlObject($select);
		if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
			$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/
		
		$select = $sql->select();
		$select->from(array("a" => "CB_WORegister"))
			->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array("ProjectTypeId", "ProjectDescription", "ProjectName"), $select::JOIN_LEFT)
			->join(array('c1' => 'CB_ClientMaster'), 'b.ClientId=c1.ClientId', array('ClientId','ClientName'), $select:: JOIN_LEFT)
			->join(array('d' => 'CB_SubscriberMaster'), 'a.SubscriberId = d.SubscriberId', array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
			->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array(), $select:: JOIN_LEFT)
			->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array(), $select:: JOIN_LEFT)
			->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array(), $select:: JOIN_LEFT)
			->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array(), $select:: JOIN_LEFT)
			->columns(array("WorkOrderId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
				, "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
				, 'PeriodType' => new Expression("Case When a.PeriodType='D' then 'Day' When a.PeriodType='M' then 'Month' else 'Year' End"), "Duration"
				, "OrderAmount", "AgreementNo", "AgreementDate" => new Expression("FORMAT(a.AgreementDate, 'dd-MM-yyyy')"), "AuthorityName", "AuthorityAddress"
				, 'AgreementType' => new Expression("Case When a.AgreementType='R' then 'Item Wise Rate' When a.AgreementType='I' then 'Item Wise %' When a.AgreementType='O' then 'Overall %' else 'Turn Key' End")
				, "Duration", "OrderAmount", "OrderPercent", "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
				
				, "ClientAddress"=> new Expression("(ISNULL(c1.Address,'')+' '+ISNULL(h.CityName,'')+' '+ISNULL(i.StateName,'')+' '+ISNULL(j.CountryName,''))")
				, "Address" => new Expression("(ISNULL(d.Address,'')+' '+ISNULL(e.CityName,'')+' '+ISNULL(f.StateName,'')+' '+ISNULL(g.CountryName,''))") )
				, array("ProjectTypeId", "ProjectDescription", "ProjectName")
				, array('ClientId','ClientName'), array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
				, array(), array(), array(), array(), array()
				, array());
		$select->where(array('a.DeleteFlag' => '0', 'a.WorkOrderId' => $workorderId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
	
		$this->_view->woregister = $woregister;
		$woAmtinwords = $this->convertAmountToWords($woregister['OrderAmount']);
		$this->_view->woAmtinwords = $woAmtinwords;

		// boq
		$select = $sql->select();
		$select->from(array('a' => "CB_WOBOQ"))
				->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
				->columns(array('WOBOQId', 'WOBOQTransId', 'TransType', 'SortId', 'WorkGroupId', 'AgtNo', 'Specification', 'ShortSpec', 'UnitId', 'Qty', 'ClientRate', 'ClientAmount', 'Rate', 'Amount'
				, 'RateVariance', 'Header','HeaderType' => new Expression("Case When a.HeaderType='W' then 'WBS' When a.HeaderType='G' then 'WorkGroup' else 'Parent' End")))
				->where("a.WORegisterId=$workorderId and a.TransType='I'")
				->order('a.SortId');
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	public function rabilldetreportAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
		$request = $this->getRequest();
		if ($request->isPost()) {
			$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
		}
		$dir = 'public/reports/rabill/'. $subscriberId;
		$filePath = $dir.'/rabill_template.phtml';
	
		$billId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'billid' ), 'number' );
		$type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
		$entryfrom = $this->bsf->isNullCheck( $this->params()->fromRoute( 'entryfrom' ), 'string' );
		
		if($billId == 0)
			$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );
			
		if(strlen($type) != 1 || ($type != 'S' && $type != 'C'))
			$this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );

		if (!file_exists($filePath)) {
			$filePath = 'public/reports/rabill/template.phtml';
		}
		
		$template = file_get_contents($filePath);
		$template = str_replace('contenteditable="true"', '', $template);
		$this->_view->template = $template;
		
		$typeEntry = "Certify";
		$sCer = "Cer";
		if($type=='S'){
			$typeEntry = "Submit";
			$sCer = "";
		}
		
		$select = $sql->select();
		$select->from(array('a' => "CB_BillMaster"))
			->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'OrderAmount'), $select:: JOIN_LEFT)
			->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectTypeId', 'ProjectDescription','ProjectName'), $select:: JOIN_LEFT)
			->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName'), $select:: JOIN_LEFT)
			->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
			->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array(), $select:: JOIN_LEFT)
			->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array(), $select:: JOIN_LEFT)
			->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array(), $select:: JOIN_LEFT)
			->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array(), $select:: JOIN_LEFT)	
			->columns( array('BillNo','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')")
			,'BillType'=> new Expression("Case When a.BillType='S' then 'First Bill' When a.BillType='R' then 'Running Bill' else 'Final Bill' End")
			,'Amount'=> $typeEntry."Amount"
			, "ClientAddress"=> new Expression("(ISNULL(c1.Address,'')+' '+ISNULL(h.CityName,'')+' '+ISNULL(i.StateName,'')+' '+ISNULL(j.CountryName,''))")
				, "Address" => new Expression("(ISNULL(d.Address,'')+' '+ISNULL(e.CityName,'')+' '+ISNULL(f.StateName,'')+' '+ISNULL(g.CountryName,''))") )
			, array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'OrderAmount')
			, array('ProjectTypeId', 'ProjectDescription', 'ProjectName')
			, array('ClientId','ClientName'), array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
			, array(), array(), array(), array(), array(), array() );
			
		$select->where(array('a.DeleteFlag' => '0', 'a.BillId' => $billId));
		$statement = $sql->getSqlStringForSqlObject($select);
		$billregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$woAmtinwords = $this->convertAmountToWords($billregister['OrderAmount']);
		$billAmtinwords = $this->convertAmountToWords($billregister['Amount']);
		$this->_view->billregister = $billregister;
		$this->_view->woAmtinwords = $woAmtinwords;
		$this->_view->billAmtinwords = $billAmtinwords;
		$this->_view->typeEntry = $typeEntry;
		$this->_view->entryfrom = $entryfrom;
		
		if($entryfrom == "abstractCum"){
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount','PrevAmount','CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount'=> new Expression("a.CerCumAmount")
					,'PrevAmount'=> new Expression("a.CerPrevAmount"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
		} else if($entryfrom == "abstractwithIOW"){
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount','PrevAmount','CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CumAmount'=> new Expression("a.CerCumAmount")
					,'PrevAmount'=> new Expression("a.CerPrevAmount"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
			
			$select3 = $sql->select();
			$select1 = $sql->select();
			if($type == "S"){
				$select1->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo','Specification'),array('BillId') );
				$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
				
				$select2 = $sql->select();
				$select2->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount','PrevAmount','CurAmount', 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
				$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
				
				$select2->combine($select1,'Union ALL');
							
				$select3 = $sql->select();
				$select3->from(array("g"=>$select2))
						->columns(array("BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));							
				
			} else {
				$select1->from(array('a' => "CB_BillBOQ"))
					->join(array('b' => 'CB_WOBOQ'), 'a.WOBOQId=b.WOBOQId', array('AgtNo','Specification'), $select1:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select1:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
					'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					,array('AgtNo','Specification'),array('BillId') );
				$select1->where("c.BillId=$billId AND a.WOBOQId<>0");
				
				$select2 = $sql->select();
				$select2->from(array('a' => "CB_BillBOQ"))
				->join(array('b' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=b.NonBOQId', array('AgtNo'=>new Expression("b.SlNo"),'Specification'), $select2:: JOIN_INNER)
					->join(array('c' => 'CB_BillAbstract'), 'a.BillAbsId=c.BillAbsId And a.BillFormatId=c.BillFormatId', array('BillId'), $select2:: JOIN_INNER)	
					->columns( array('BillAbsId','BillFormatId','CumAmount'=> new Expression("a.CerCumAmount"),'PrevAmount'=> new Expression("a.CerPrevAmount"),
					'CurAmount'=> new Expression("a.CerCurAmount"), 'SNo'=> new Expression("row_number() over (order by a.BillBOQId)"))
					, array('AgtNo'=>new Expression("b.SlNo"),'Specification'),array('BillId') );
				$select2->where("c.BillId=$billId AND a.NonBOQId<>0");
				
				$select2->combine($select1,'Union ALL');
							
				$select3 = $sql->select();
				$select3->from(array("g"=>$select2))
						->columns(array("BillAbsId","BillFormatId","CumAmount", "PrevAmount", "CurAmount", "SNo","AgtNo","Specification","BillId" ));
			}
			$statement = $sql->getSqlStringForSqlObject( $select3 );
			$billsAbstIOws = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbstIOws = $billsAbstIOws;
		} else if($entryfrom == "abstractOverall"){
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);

				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header','Bold' ,'Italic' ,'Underline'))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId")
					->order('a.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				foreach($BillFormat as &$Format) {
					$billFormatId= $Format['BillFormatId'];
					$billAbsId= $Format['BillAbsId'];
					switch($billFormatId) {
						case '1': // Agreement
							/*$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");*/


							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.WBSId=b1.WOBOQId', array( 'Header','HeaderType'), $select::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='W' AND b.WBSId<>0");

							$select2 = $sql->select();
							$select2->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select2::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.ParentId=b1.WOBOQId', array( 'Header','HeaderType'), $select2::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='P' AND b.ParentId<>0");
							$select2->combine($select,'Union ALL');
							
							$select1 = $sql->select();
							$select1->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId','Header','HeaderType'), $select1::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b.HeaderType='' AND b.ParentId=0 AND b.WBSId=0");
							$select1->combine($select2,'Union ALL');

							$select3 = $sql->select();
							$select3->from(array("g"=>$select1))
								->columns(array('*'))
								->join( array( 'c' => 'Proj_UOM' ), 'g.unit=c.UnitId', array( 'UnitId','UnitName'), $select3::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'g.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select3::JOIN_LEFT )
								->order('g.SortId');
							$statement = $sql->getSqlStringForSqlObject( $select3 );
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
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
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId");
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
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.ReceiptId<>0");
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId AND b.ReceiptId<>0");
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
									->where(array('b.BillId' => $billId, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select21->combine($selectsub,'Union ALL');
							
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '21', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.BillId<>$billId");
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '7': // Bill Deduction
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillVendorBill' ) )
								->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
								->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '19': // Free Supply Material
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
		} else if($entryfrom == "materialadvdet"){
			 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);
				
				//For Material Advance Report
				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End"), 'Description', 'Sign', 'Header'))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId and a.BillFormatId=3 ")
					->order('a.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				foreach($BillFormat as &$Format) {
					$billFormatId= $Format['BillFormatId'];
					$billAbsId= $Format['BillAbsId'];
					switch($billFormatId) {
						case '1': // Agreement
							/*$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");*/
								
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.WBSId=b1.WOBOQId', array( 'Header','HeaderType'), $select::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='W' AND b.WBSId<>0");

							$select2 = $sql->select();
							$select2->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select2::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.ParentId=b1.WOBOQId', array( 'Header','HeaderType'), $select2::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='P' AND b.ParentId<>0");
							$select2->combine($select,'Union ALL');
							
							$select1 = $sql->select();
							$select1->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId','Header','HeaderType'), $select1::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b.HeaderType='' AND b.ParentId=0 AND b.WBSId=0");
							$select1->combine($select2,'Union ALL');

							$select3 = $sql->select();
							$select3->from(array("g"=>$select1))
								->columns(array('*'))
								->join( array( 'c' => 'Proj_UOM' ), 'g.unit=c.UnitId', array( 'UnitId','UnitName'), $select3::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'g.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select3::JOIN_LEFT )
								->order('g.SortId');
							$statement = $sql->getSqlStringForSqlObject( $select3 );
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
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
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId");
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
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.ReceiptId<>0");
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId AND b.ReceiptId<>0");
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
						case '21': // Material Advance Recovery
							//Advance Recovery BillAbstract FormatTypeId=3
							$select = $sql->select();
							$select->from( array('a' => 'CB_BillAbstract' ))
								->columns(array( 'BillAbsId', 'BillId', 'BillFormatId' => new Expression("6"), 'Amount' => new Expression("a.".$sCer."CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
								->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
								->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
							$select->where("a.CurAmount<>0 ");
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select21->combine($select,'Union ALL');
							
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.BillId<>$billId");
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '7': // Bill Deduction
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillVendorBill' ) )
								->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
								->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '19': // Free Supply Material
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
			$this->_view->billinfo = $billinfo;
		
		} else if($entryfrom == "priceesclationdet"){
				 // Bill Info
			$select = $sql->select();
			$select->from(array('a' => "CB_BillMaster"))
				->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('WONo','WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"),'WorkOrderId'), $select:: JOIN_LEFT)
				->columns(array('BillNo','BillType','BillDate'=> new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'IsSubmittedBill',
							  'IsCertifiedBill', 'SubmittedDate', 'CertifiedDate', 'SubmittedRemarks', 'CertifiedRemarks')
					,array('WONo','WODate','WorkOrderId'))
				->where("a.BillId=$billId AND b.SubscriberId=$subscriberId");
			$statement = $sql->getSqlStringForSqlObject($select);
			$billinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

			if ($billinfo) {
				$billinfo['TransType'] = $type;
				$WOId = $billinfo['WorkOrderId'];
				$billType = $billinfo['BillType'];
				if($billType=="R" || $billType=="F" || $billType=="S" )
					$billType = array('R', 'S', 'F');
				else
					$billType = array($billinfo['BillType']);
				
				//For Price Escalation Report
				$select = $sql->select();
				$select->from( array( 'a' => 'CB_BillFormatTrans' ) )
					->join( array( 'b' => 'CB_BillFormatMaster' ), 'a.BillFormatId=b.BillFormatId', array( 'RowName','FormatTypeId','Sign','BillFormatId'), $select::JOIN_LEFT )
					->join( array( 'c' => 'CB_BillAbstract' ), 'a.BillFormatId=c.BillFormatId and a.BillFormatTransId=c.BillFormatTransId', array( 'CumAmount','PrevAmount','CurAmount','BillAbsId', 'Formula' ), $select::JOIN_LEFT )
					->columns(array('Slno', 'Description', 'Sign', 'Header','TypeName' => new Expression("Case When a.Description<>'' then a.Description else b.TypeName End")))
					->where( "a.WorkOrderId=$WOId AND c.BillId=$billId and a.BillFormatId=18 ")
					->order('a.SortId');
				$statement = $sql->getSqlStringForSqlObject( $select );
				$BillFormat = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

				foreach($BillFormat as &$Format) {
					$billFormatId= $Format['BillFormatId'];
					$billAbsId= $Format['BillAbsId'];
					switch($billFormatId) {
						case '1': // Agreement
							/*$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification','WOBOQId'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'a.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId");*/

							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.WBSId=b1.WOBOQId', array( 'Header','HeaderType'), $select::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='W' AND b.WBSId<>0");

							$select2 = $sql->select();
							$select2->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId'), $select2::JOIN_LEFT )
								->join( array( 'b1' => 'CB_WOBOQ' ), 'b.ParentId=b1.WOBOQId', array( 'Header','HeaderType'), $select2::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b1.HeaderType='P' AND b.ParentId<>0");
							$select2->combine($select,'Union ALL');
							
							$select1 = $sql->select();
							$select1->from( array( 'a' => 'CB_BillBOQ' ) )
								->join( array( 'b' => 'CB_WOBOQ' ), 'a.WOBOQId=b.WOBOQId', array( 'AgtNo','Specification', 'unit' => 'UnitId', 'SortId','Header','HeaderType'), $select1::JOIN_LEFT )
								->columns(array('BillBOQId', 'WOBOQId', 'Rate', 'CurQty' => $sCer.'CurQty', 'CurAmount' => $sCer.'CurAmount'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId AND b.HeaderType='' AND b.ParentId=0 AND b.WBSId=0");
							$select1->combine($select2,'Union ALL');

							$select3 = $sql->select();
							$select3->from(array("g"=>$select1))
								->columns(array('*'))
								->join( array( 'c' => 'Proj_UOM' ), 'g.unit=c.UnitId', array( 'UnitId','UnitName'), $select3::JOIN_LEFT )
								->join( array( 'd' => 'CB_BillMeasurement' ), 'g.BillBOQId=d.BillBOQId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select3::JOIN_LEFT )
								->order('g.SortId');
							$statement = $sql->getSqlStringForSqlObject( $select3 );
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillMaterialBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
									->columns(array('MBillTransId','BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Qty','Rate', 'Amount','URL'), array( 'VendorName','VendorId'))
									->where( "a.MTransId=$MTransId");
								$statement = $sql->getSqlStringForSqlObject( $select );
								$advance['BillTrans'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							}
							break;
						case '18': // Price Escalation
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillPriceEscalation' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

							foreach($Format['AddRow'] as &$advance) {
								$MTransId = $advance['MTransId'];
								$select = $sql->select();
								$select->from( array( 'a' => 'CB_BillPriceEscalationBillTrans' ) )
									->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorName','VendorId'), $select::JOIN_LEFT )
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
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '5' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId");
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
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("isnull(b.".$sCer."Amount ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.ReceiptId<>0");
							$select2->combine($select,'Union ALL');
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('ReceiptId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(b.".$sCer."Amount ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillFormatId' => '6' , 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->where("b.BillId<>$billId AND b.ReceiptId<>0");
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
						case '21': // Material Advance Recovery
							//Advance Recovery BillAbstract FormatTypeId=3
							$select = $sql->select();
							$select->from( array('a' => 'CB_BillAbstract' ))
								->columns(array( 'BillAbsId', 'BillId', 'BillFormatId' => new Expression("6"), 'Amount' => new Expression("a.".$sCer."CurAmount"), 'PrevAmount' => new Expression("1-1"), 'CurAmount' => new Expression("1-1") ))
								->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_INNER)
								->join(array('c' => 'CB_BillMaster'), 'a.BillId=c.BillId', array(), $select::JOIN_INNER)
								->where(array('c.DeleteFlag' => '0' ,'c.WORegisterId' => $WOId, 'c.BillType' => $billType ,'b.FormatTypeId' => '3'));
							$select->where("a.CurAmount<>0 ");
							
							$select21 = $sql->select(); 
							$select21->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select21::JOIN_INNER)
									->where(array('b.BillId' => $billId, 'b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select21->group(new Expression('b.BillAbsId,b.BillId,b.BillFormatId'));
							$select21->combine($select,'Union ALL');
							
							$select2 = $sql->select(); 
							$select2->from(array("b"=>"CB_BillAdvanceRecovery"))
									->columns(array('BillAbsId', 'BillId', 'BillFormatId', 'Amount' => new Expression("1-1"), 'PreAmount' => new Expression("isnull(Sum(b.".$sCer."Amount) ,0)"), 'CurAmount' => new Expression("1-1") ))
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select2::JOIN_INNER)
									->where(array('b.ReceiptId' => '0' ,'b.BillFormatId' => '6', 'c.WORegisterId' => $WOId, 'c.DeleteFlag' =>'0', 'c.BillType' => $billType));
							$select2->where("b.BillId<>$billId");
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
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '7': // Bill Deduction
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillVendorBill' ) )
								->join( array( 'b' => 'CB_VendorMaster' ), 'a.VendorId=b.VendorId', array( 'VendorId','VendorName'), $select::JOIN_LEFT )
								->columns(array('BillDate' =>new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo', 'Amount','URL','TransId'), array( 'VendorId','VendorName'))
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
						case '19': // Free Supply Material
							$select = $sql->select();
							$select->from( array( 'a' => 'CB_BillFreeSupplyMaterial' ) )
								->join( array( 'b' => 'CB_MaterialMaster' ), 'a.MaterialId=b.MaterialId', array( 'MaterialId','MaterialName'), $select::JOIN_LEFT )
								->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId','UnitName'), $select::JOIN_LEFT )
								->where( "a.BillAbsId=$billAbsId AND a.BillFormatId=$billFormatId and a.TransType='$type'");
							$statement = $sql->getSqlStringForSqlObject( $select );
							$Format['AddRow'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
							break;
					}
				}
				$this->_view->BillFormat = $BillFormat;
			}
			$this->_view->billinfo = $billinfo;
		} else if($entryfrom == "abstractCurrent"){
			$select = $sql->select();
			if($type == "S"){
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount',
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			} else {
				$select->from(array('a' => "CB_BillAbstract"))
					->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
					->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
					->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
					, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
					->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount'=> new Expression("a.CerCurAmount"),
					'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
					, array(), array(), array('Header','Bold','Italic','Underline') );
			}
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
		} else if($entryfrom == "abstractSubvsCer"){
			$select = $sql->select();
			$select->from(array('a' => "CB_BillAbstract"))
				->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_INNER)
				->join(array('c' => 'CB_BillFormatMaster'), 'a.BillFormatId=c.BillFormatId', array(), $select:: JOIN_LEFT)
				->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and b.WORegisterId=d.WorkOrderId and a.BillFormatTransId=d.BillFormatTransId'
				, array('Header','Bold','Italic','Underline'), $select:: JOIN_INNER)	
				->columns( array('BillAbsId', 'BillFormatId'=> new Expression("a.BillFormatId"),'CurAmount','CerCurAmount',
				'TypeName'=> new Expression("Case When d.Description<>'' then d.Description else c.TypeName End"),'RowName'=> new Expression("c.RowName"),'SNO'=> new Expression("ROW_NUMBER() OVER(ORDER BY d.SortId,a.BillFormatId)"))
				, array(), array(), array('Header','Bold','Italic','Underline') );
			
			$select->where("a.BillId=$billId");
			$select->order('d.SortId');
			$statement = $sql->getSqlStringForSqlObject( $select );
			$billsAbsts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$this->_view->billsAbsts = $billsAbsts;
		}
		// csrf Key
		//$this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function receiptdetreportAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
		$request = $this->getRequest();		
		if ($request->isPost()) {
			$this->redirect()->toRoute( 'cb/receipt', array( 'controller' => 'receipt', 'action' => 'register' ) );
		}
		
		$dir = 'public/reports/receipt/'. $subscriberId;
		$filePath = $dir.'/receipt_template.phtml';
				
		$receiptId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );			
		if($receiptId == 0)
			$this->redirect()->toRoute( 'cb/receipt', array( 'controller' => 'receipt', 'action' => 'register' ) );
					
		if (!file_exists($filePath)) {
			$filePath = 'public/reports/receipt/template.phtml';
		}
		
		$template = file_get_contents($filePath);
		$this->_view->template = $template;
		
		//new	
		$select = $sql->select();
		$select->from(array("a"=>"CB_ReceiptRegister"))
			->join(array("b"=>"CB_WORegister"), "a.WORegisterId=b.WorkOrderId", array("ProjectId","WONo","OrderAmount",'WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')")), $select::JOIN_INNER)
			->join(array('c' => 'CB_ProjectMaster'), 'b.ProjectId=c.ProjectId', array('ProjectTypeId', 'ProjectDescription',"ProjectName"), $select::JOIN_LEFT)
			->join(array('c1' => 'CB_ClientMaster'), 'c.ClientId=c1.ClientId', array('ClientId','ClientName'), $select:: JOIN_LEFT)
			->join(array('d' => 'CB_SubscriberMaster'), 'b.SubscriberId = d.SubscriberId', array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo'), $select:: JOIN_LEFT)
			->join(array('e' => 'WF_CityMaster'), 'e.CityId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('f' => 'WF_StateMaster'), 'f.StateID = d.StateID', array(), $select:: JOIN_LEFT)
			->join(array('g' => 'WF_CountryMaster'), 'g.CountryId = d.CityId', array(), $select:: JOIN_LEFT)
			->join(array('h' => 'WF_CityMaster'), 'h.CityId = c1.CityId', array(), $select:: JOIN_LEFT)
			->join(array('i' => 'WF_StateMaster'), 'i.StateID = h.StateID', array(), $select:: JOIN_LEFT)
			->join(array('j' => 'WF_CountryMaster'), 'j.CountryId = c1.CityId', array(), $select:: JOIN_LEFT)
			->columns(array("WORegisterId", "ReceiptNo", "ReceiptDate" => new Expression( "FORMAT(a.ReceiptDate, 'dd-MM-yyyy')" ) 
			,"ReceiptAgainst" => new Expression("Case When a.ReceiptAgainst='B' then 'Bill' When a.ReceiptAgainst='M' then 'Mobilization Advance' When a.ReceiptAgainst='A' then 'Adhoc Advance' When a.ReceiptAgainst='R' then 'Retention' When a.ReceiptAgainst='W' then 'With held' else 'Others' End"), "ReceiptMode", "TransactionNo", 
				"TransactionDate" => new Expression( "FORMAT(a.TransactionDate, 'dd-MM-yyyy')" ),"TransactionRemarks", "Amount"
				, "ClientAddress"=> new Expression("(ISNULL(c1.Address,'')+' '+ISNULL(h.CityName,'')+' '+ISNULL(i.StateName,'')+' '+ISNULL(j.CountryName,''))")
				, "Address" => new Expression("(ISNULL(d.Address,'')+' '+ISNULL(e.CityName,'')+' '+ISNULL(f.StateName,'')+' '+ISNULL(g.CountryName,''))") )
				, array("ProjectId", "WONo","OrderAmount",'WODate'=> new Expression("FORMAT(b.WODate, 'dd-MM-yyyy')"))
				, array('ProjectTypeId', 'ProjectDescription', 'ProjectName')
			, array('ClientId','ClientName'), array('BusinessName','PinCode','Tin','Tan','Pan','SubscriberId','Logo')
			, array(), array(), array(), array(), array(), array() );
		$select->where(array( 'a.DeleteFlag' => '0', 'a.ReceiptId' => $receiptId));
		$statement = $statement = $sql->getSqlStringForSqlObject($select);
		$receiptregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$rworegid =  $receiptregister->WORegisterId;
		$this->_view->receiptregister = $receiptregister;
		$woAmtinwords = $this->convertAmountToWords($receiptregister['OrderAmount']);
		$this->_view->woAmtinwords = $woAmtinwords;
		$receiptAmtinwords = $this->convertAmountToWords($receiptregister['Amount']);
		$this->_view->receiptAmtinwords = $receiptAmtinwords;
		
		//trans
		$select1 = $sql->select();
		$select1->from(array('a' => 'CB_BillMaster'))
				->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount', 'CertifyAmount') )
				->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'PrevAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select1::JOIN_LEFT)
				->where(array( 'a.WoRegisterId' => $rworegid));
		$select1->group(new Expression('a.BillId,a.BillNo,a.BillDate,a.SubmitAmount,a.CertifyAmount'));
		
		$select2 = $sql->select(); 
		$select2->from(array("a"=>"CB_BillMaster"))
				->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount' => new Expression("CAST(0 As Decimal(18,2))")
				, 'CertifyAmount' => new Expression("CAST(0 As Decimal(18,2))"), 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))")) )
				->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'PrevAmt' => new Expression("Sum(b.Amount)")), $select2::JOIN_INNER);
		$select2->where("b.ReceiptId<>$receiptId and a.WoRegisterId=$rworegid");
		$select2->combine($select1,'Union ALL');											
		$select2->group(new Expression('a.BillId,a.BillNo,a.BillDate'));

		$select2Edit = $sql->select(); 
		$select2Edit->from(array("a"=>"CB_BillMaster"))
				->columns( array('BillId', 'BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'BillNo', 'SubmitAmount' => new Expression("CAST(0 As Decimal(18,2))")
				, 'CertifyAmount' => new Expression("CAST(0 As Decimal(18,2))")) )
				->join(array('b' => 'CB_ReceiptAdustment'), 'a.BillId=b.BillId', array( 'AdjAmount' => new Expression("Sum(b.Amount)"),'PrevAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select2Edit::JOIN_INNER);
		$select2Edit->where("b.ReceiptId=$receiptId");
		$select2Edit->combine($select2,'Union ALL');											
		$select2Edit->group(new Expression('a.BillId,a.BillNo,a.BillDate'));
		
		$select3 = $sql->select();
		$select3->from(array("g"=>$select2Edit))
				->columns(array('BillId', 'BillDate', 'BillNo',"SubmitAmount"=>new Expression("Sum(g.SubmitAmount)")
				,"CertifyAmount"=>new Expression("Sum(g.CertifyAmount)"),"CurAmount"=>new Expression("Sum(g.AdjAmount)"),"AdjAmount"=>new Expression("Sum(g.PrevAmt)") ));
		$select3->group(new Expression('g.BillId,g.BillNo,g.BillDate'));
		$statement = $sql->getSqlStringForSqlObject($select3);
		$billformats = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

		foreach($billformats as &$bill) {
			$billId = $bill['BillId'];
			$select = $sql->select();
			$select->from( array('a' => 'CB_BillAbstract' ))
				->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
				->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( ), $select::JOIN_LEFT)
				->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT)
				->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
				array( 'AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("Sum(c.Amount)")), $select::JOIN_LEFT)
				->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select:: JOIN_INNER);
				//->where("a.BillId=$billId")
			$select->where(array( 'a.BillId' => $billId, 'c.ReceiptId' => $receiptId));
			$select->where( "a.BillFormatId<>0");
			$select->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
			
			$select2 = $sql->select(); 
			$select2->from( array('a' => 'CB_BillAbstract' ))
				->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
				->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( ), $select2::JOIN_LEFT)
				->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select2::JOIN_LEFT)
				->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
				array( 'AdjAmount' => new Expression("Sum(c.Amount)"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select2::JOIN_LEFT)
				->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select2:: JOIN_INNER);
				//->where("a.BillId=$billId")
			$select2->where("a.BillId=$billId AND a.BillFormatId<>0 AND c.ReceiptId<>$receiptId");
			$select2->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
			$select2->combine($select,'Union ALL');
			
			$select21 = $sql->select(); 
			$select21->from( array('a' => 'CB_BillAbstract' ))
				->columns( array('BillId', 'BillAbsId', 'BillFormatId', 'CurAmount','TypeName'=> new Expression("Case When d.Description<>'' then d.Description else b.TypeName End")) )
				->join(array('a1' => 'CB_ReceiptAdustment'), 'a.BillId=a1.BillId', array( ), $select21::JOIN_LEFT)
				->join(array('b' => 'CB_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', 
				array('AdjAmount' => new Expression("CAST(0 As Decimal(18,2))"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT)
				->join(array('d' => 'CB_BillFormatTrans'), 'a.BillFormatId=d.BillFormatId and a.BillFormatTransId=d.BillFormatTransId', array(), $select21:: JOIN_INNER);
				//->join(array('c' => 'CB_ReceiptAdustmentTrans'), 'a1.ReceiptId=c.ReceiptId and a.BillFormatId=c.BillFormatId and a.BillId=c.BillId', 
				//array( 'AdjAmount' => new Expression("Sum(c.Amount)"),'CurrentAmount' => new Expression("CAST(0 As Decimal(18,2))")), $select21::JOIN_LEFT);
			$select21->where("a.BillId=$billId AND a.CurAmount<>0 AND a.BillFormatId<>0");
			$select21->group(new Expression('a.BillId,a.BillAbsId,a.BillFormatId,a.CurAmount,d.Description,b.TypeName'));
			$select21->combine($select2,'Union ALL');			
			
			$select3 = $sql->select();
			$select3->from(array("g"=>$select21))
					->columns(array('BillAbsId', 'BillFormatId', 'CurAmount', 'TypeName','BillId',"AdjAmount"=>new Expression("Sum(g.AdjAmount)")
					,"CurrentAmount"=>new Expression("Sum(g.CurrentAmount)") ));
			$select3->group(new Expression('g.BillAbsId,g.BillFormatId,g.CurAmount,g.TypeName,g.BillId'));
			
			$statement = $sql->getSqlStringForSqlObject($select3);
			$billabs = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			$bill['BillAbs'] = $billabs;
		}
		$this->_view->billformats = $billformats;

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	private function convertAmountToWords($number) {
		$no = floor($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array('0' => '', '1' => 'One', '2' => 'Two',
            '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
            '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
            '13' => 'Thirteen', '14' => 'Fourteen',
            '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
            '18' => 'Eighteen', '19' =>'Nineteen', '20' => 'Twenty',
            '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
            '60' => 'Sixty', '70' => 'Seventy',
            '80' => 'Eighty', '90' => 'Ninety');
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');

        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }
        $str = array_reverse($str);
        $result = explodeimplode('', $str);
        $points = ($point) ? " and " . $words[((int)($point /10)) . '0'] . " " . $words[$point = $point % 10] . " Paise": '';

		if($result==""){ 
			$result = ""; 
		} else {
			$result = $result . "Rupees  " . $points . " Only.";
		}
        return $result;
    }
	
	public function shortcertificationAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );

                $select = $sql->select();
                switch($RType) {
					case 'getLoad':
						//start Update//
						$select = $sql->delete();
						$select->from('CB_WOSubmitCertifyReport');				
						$DelStatement = $sql->getSqlStringForSqlObject($select);			
						$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
										
						$select = $sql->select();
						$select->from(array("a"=>"CB_WOBOQ"))
							->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array(), $select:: JOIN_LEFT)
							->columns(array('WOBOQId','AgtNo','Specification','WOQty' => 'Qty','Rate','WOAmount' =>'Amount','Unit' => new Expression("b.UnitName"),'Type' => new Expression("'A'")));
						$select->where(array( 'a.WORegisterId' => $PostDataStr));
						$select->where( "a.Specification<>'' and a.TransType<>'H' " );
						
						$select2 = $sql->select();
						$select2->from(array("a"=>"CB_NonAgtItemMaster"))
							->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array(), $select2:: JOIN_LEFT)
							->columns(array('WOBOQId' => new Expression("a.NonBOQId"),'AgtNo' => new Expression("a.SlNo"),'Specification','WOQty' => new Expression("1-1"),'Rate','WOAmount' =>new Expression("1-1"),'Unit' => new Expression("b.UnitName"),'Type' => new Expression("'N'")));
						$select2->where(array( 'a.WORegisterId' => $PostDataStr ,'a.DeleteFlag' => '0'));
						$select->combine($select2,'Union ALL');
							
						$statement = $statement = $sql->getSqlStringForSqlObject($select);
						$woBoqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

						$i=0;
						foreach($woBoqList as $woBoqListTrans) {
							$WOType = $woBoqListTrans['Type'];
							$WOBOQId = $woBoqListTrans['WOBOQId'];

							$select = $sql->select();
							if($WOType=="A"){
								$select->from(array('a' => "CB_BillBOQ"))
									->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select:: JOIN_INNER)
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select:: JOIN_INNER)
									->columns(array('WOBOQId', 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
									, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)") ))
									->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0', "a.WOBOQId" => $WOBOQId, "a.NonBOQId" => '0' ))
									->group(new Expression('a.WOBOQId,a.Rate'));
							} else {
							
								$select->from(array('a' => "CB_BillBOQ"))
									->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select:: JOIN_INNER)
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select:: JOIN_INNER)
									->columns(array('WOBOQId' => new Expression("a.NonBOQId"), 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
									, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)") ))
									->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0', "a.NonBOQId" => $WOBOQId, "a.WOBOQId" => '0' ))
									->group(new Expression('a.NonBOQId,a.Rate'));
							}
							
							$statement = $statement = $sql->getSqlStringForSqlObject($select);
							$subvscerBoqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							$j=0;
							foreach($subvscerBoqList as $subvscerBoqListTrans) {
								$i = $i + 1;
								$j = $j + 1;
								$insert = $sql->insert();
								$insert->into( 'CB_WOSubmitCertifyReport' );
								$insert->Values( array( 
									'WOBOQId' => $WOBOQId,
									'AgtNo' => $woBoqListTrans['AgtNo'],
									'Specification' => $woBoqListTrans['Specification'],
									'UnitName' => $woBoqListTrans['Unit'],
									'Rate' => $woBoqListTrans['Rate'],
									'WOQty' => $woBoqListTrans['WOQty'],
									'WOAmount' => $woBoqListTrans['WOAmount'],
									'SubmitQty' => $subvscerBoqListTrans['SubmitQty'],
									'SubmitAmount' => $subvscerBoqListTrans['SubmitAmount'],
									'CertifyQty' => $subvscerBoqListTrans['CertifyQty'],
									'CertifyAmount' => $subvscerBoqListTrans['CertifyAmount'],
									'DiffQty' => $subvscerBoqListTrans['SubmitQty'] - $subvscerBoqListTrans['CertifyQty'],
									'DiffAmount' => $subvscerBoqListTrans['SubmitQty'] - $subvscerBoqListTrans['CertifyQty'],
									'Type' => $WOType
								));
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							
							}
							if($j == 0){
								$insert = $sql->insert();
								$insert->into( 'CB_WOSubmitCertifyReport' );
								$insert->Values( array( 'WOBOQId' => $WOBOQId, 'AgtNo' => $woBoqListTrans['AgtNo'], 'Specification' => $woBoqListTrans['Specification']
								,'UnitName' => $woBoqListTrans['Unit'], 'Rate' => $woBoqListTrans['Rate'], 'WOQty' => $woBoqListTrans['WOQty']
								,'WOAmount' => $woBoqListTrans['WOAmount'], 'SubmitQty' => 0, 'SubmitAmount' => 0
								,'CertifyQty' => 0, 'CertifyAmount' => 0, 'Type' => $WOType
								) );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							}
						}
						//End Update//
						$select = $sql->select();
						$select->from( 'CB_WOSubmitCertifyReport' )
							->columns( array( 'WOBOQId','AgtNo','Specification','UnitName','Rate','WOQty','WOAmount','SubmitQty','SubmitAmount','CertifyQty','CertifyAmount','DiffQty','DiffAmount','Type' =>new Expression("Case When Type ='A' then 'Agreement Item' else 'NonAgreement Item' end")) );
						$select->order(new Expression("Type,DiffAmount desc"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						
						
						$data = json_encode($results);
                        break;                  
					}

                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		}
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
	
		$select = $sql->select();
		$select->from(array("a"=>"CB_WORegister"))
			->columns(array('data' => 'WorkOrderId', 'value' => 'WONo'));
		//$select->where(array( 'a.DeleteFlag' => '0', 'a.SubscriberId'=$subscriberId));
		$select->where( "a.DeleteFlag='0' AND a.SubscriberId=$subscriberId AND a.WONo<>'' " );
		$statement = $statement = $sql->getSqlStringForSqlObject($select);
		$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->woList = $woList;
		
		/*$select = $sql->select();
		$select->from( 'CB_WOSubmitCertifyReport' )
			->columns( array( 'WOBOQId','AgtNo','Specification','UnitName','Rate','WOQty','WOAmount','SubmitQty','SubmitAmount','CertifyQty','CertifyAmount','DiffQty','DiffAmount','Type' =>new Expression("Case When Type ='A' then 'Agreement Item' else 'NonAgreement Item' end")) );
		$select->order(new Expression("Type,DiffAmount desc"));
		echo $statement = $statement = $sql->getSqlStringForSqlObject($select);die;
		$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		$this->_view->results = $results;*/

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function wovssubmitvscertifyrptAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );

                $select = $sql->select();
                switch($RType) {
					case 'getLoad':
						//start Update//
						$select = $sql->delete();
						$select->from('CB_WOSubmitCertifyReport');				
						$DelStatement = $sql->getSqlStringForSqlObject($select);			
						$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
										
						$select = $sql->select();
						$select->from(array("a"=>"CB_WOBOQ"))
							->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array(), $select:: JOIN_LEFT)
							->columns(array('WOBOQId','AgtNo','Specification','WOQty' => 'Qty','Rate','WOAmount' =>'Amount','Unit' => new Expression("b.UnitName"),'Type' => new Expression("'A'")));
						$select->where(array( 'a.WORegisterId' => $PostDataStr));
						$select->where( "a.Specification<>'' and a.TransType<>'H' " );
						
						$select2 = $sql->select();
						$select2->from(array("a"=>"CB_NonAgtItemMaster"))
							->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array(), $select2:: JOIN_LEFT)
							->columns(array('WOBOQId' => new Expression("a.NonBOQId"),'AgtNo' => new Expression("a.SlNo"),'Specification','WOQty' => new Expression("1-1"),'Rate','WOAmount' =>new Expression("1-1"),'Unit' => new Expression("b.UnitName"),'Type' => new Expression("'N'")));
						$select2->where(array( 'a.WORegisterId' => $PostDataStr ,'a.DeleteFlag' => '0'));
						$select->combine($select2,'Union ALL');
							
						$statement = $statement = $sql->getSqlStringForSqlObject($select);
						$woBoqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

						$i=0;
						foreach($woBoqList as $woBoqListTrans) {
							$WOType = $woBoqListTrans['Type'];
							$WOBOQId = $woBoqListTrans['WOBOQId'];

							$select = $sql->select();
							if($WOType=="A"){
								$select->from(array('a' => "CB_BillBOQ"))
									->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select:: JOIN_INNER)
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select:: JOIN_INNER)
									->columns(array('WOBOQId', 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
									, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)") ))
									->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0', "a.WOBOQId" => $WOBOQId, "a.NonBOQId" => '0' ))
									->group(new Expression('a.WOBOQId,a.Rate'));
							} else {
							
								$select->from(array('a' => "CB_BillBOQ"))
									->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select:: JOIN_INNER)
									->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select:: JOIN_INNER)
									->columns(array('WOBOQId' => new Expression("a.NonBOQId"), 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
									, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)") ))
									->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0', "a.NonBOQId" => $WOBOQId, "a.WOBOQId" => '0' ))
									->group(new Expression('a.NonBOQId,a.Rate'));
							}
							
							$statement = $statement = $sql->getSqlStringForSqlObject($select);
							$subvscerBoqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							$j=0;
							foreach($subvscerBoqList as $subvscerBoqListTrans) {
								$i = $i + 1;
								$j = $j + 1;
								$insert = $sql->insert();
								$insert->into( 'CB_WOSubmitCertifyReport' );
								$insert->Values( array( 
									'WOBOQId' => $WOBOQId,
									'AgtNo' => $woBoqListTrans['AgtNo'],
									'Specification' => $woBoqListTrans['Specification'],
									'UnitName' => $woBoqListTrans['Unit'],
									'Rate' => $woBoqListTrans['Rate'],
									'WOQty' => $woBoqListTrans['WOQty'],
									'WOAmount' => $woBoqListTrans['WOAmount'],
									'SubmitQty' => $subvscerBoqListTrans['SubmitQty'],
									'SubmitAmount' => $subvscerBoqListTrans['SubmitAmount'],
									'CertifyQty' => $subvscerBoqListTrans['CertifyQty'],
									'CertifyAmount' => $subvscerBoqListTrans['CertifyAmount'],
									'DiffQty' => $subvscerBoqListTrans['SubmitQty'] - $subvscerBoqListTrans['CertifyQty'],
									'DiffAmount' => $subvscerBoqListTrans['SubmitQty'] - $subvscerBoqListTrans['CertifyQty'],
									'Type' => $WOType
								));
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							
							}
							if($j == 0){
								$insert = $sql->insert();
								$insert->into( 'CB_WOSubmitCertifyReport' );
								$insert->Values( array( 'WOBOQId' => $WOBOQId, 'AgtNo' => $woBoqListTrans['AgtNo'], 'Specification' => $woBoqListTrans['Specification']
								,'UnitName' => $woBoqListTrans['Unit'], 'Rate' => $woBoqListTrans['Rate'], 'WOQty' => $woBoqListTrans['WOQty']
								,'WOAmount' => $woBoqListTrans['WOAmount'], 'SubmitQty' => 0, 'SubmitAmount' => 0
								,'CertifyQty' => 0, 'CertifyAmount' => 0, 'Type' => $WOType
								) );
								$statement = $sql->getSqlStringForSqlObject( $insert );
								$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
							}
						}
						//End Update//
						$select = $sql->select();
						$select->from( 'CB_WOSubmitCertifyReport' )
							->columns( array( 'WOBOQId','AgtNo','Specification','UnitName','Rate','WOQty','WOAmount','SubmitQty','SubmitAmount','CertifyQty','CertifyAmount','DiffQty','DiffAmount','Type' =>new Expression("Case When Type ='A' then 'Agreement Item' else 'NonAgreement Item' end")) );
						//$select->order(new Expression("Type,DiffAmount desc"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						
						
						$data = json_encode($results);
                        break;                  
					}

                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		}
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
	
		$select = $sql->select();
		$select->from(array("a"=>"CB_WORegister"))
			->columns(array('data' => 'WorkOrderId', 'value' => 'WONo'));
		//$select->where(array( 'a.DeleteFlag' => '0', 'a.SubscriberId'=$subscriberId));
		$select->where( "a.DeleteFlag='0' AND a.SubscriberId=$subscriberId AND a.WONo<>'' " );
		$statement = $statement = $sql->getSqlStringForSqlObject($select);
		$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->woList = $woList;
		
		/*$select = $sql->select();
		$select->from( 'CB_WOSubmitCertifyReport' )
			->columns( array( 'WOBOQId','AgtNo','Specification','UnitName','Rate','WOQty','WOAmount','SubmitQty','SubmitAmount','CertifyQty','CertifyAmount','DiffQty','DiffAmount','Type' =>new Expression("Case When Type ='A' then 'Agreement Item' else 'NonAgreement Item' end")) );
		$select->order(new Expression("Type,DiffAmount desc"));
		echo $statement = $statement = $sql->getSqlStringForSqlObject($select);die;
		$results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
		$this->_view->results = $results;*/

		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function submitvscertifyrptAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
                $RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );

                $select = $sql->select();
                switch($RType) {
					case 'getBill':
						$select = $sql->select();
						 $select = $sql->select();
                        $select->from( 'CB_BillMaster' )
                            ->columns( array( 'data' => 'BillId', 'value' => 'BillNo' ) )
                            ->where( "WORegisterId='$PostDataStr' AND SubscriberId='$subscriberId' AND DeleteFlag=0" );
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
						$data = json_encode($results);
                        break;
					case 'getLoad':
						//start Update//
						$billId = $this->bsf->isNullCheck( $postData[ 'billId' ], 'string' );
						
						$select = $sql->delete();
						$select->from('CB_WOSubmitCertifyReport');				
						$DelStatement = $sql->getSqlStringForSqlObject($select);			
						$dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
										
						$select = $sql->select();
						$select->from(array('a' => "CB_BillBOQ"))
							->join(array('a1' => 'CB_WOBOQ'), 'a.WOBOQId=a1.WOBOQId', array(), $select:: JOIN_INNER)
							->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select:: JOIN_INNER)
							->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select:: JOIN_INNER)
							->join(array('d' => 'Proj_UOM'), 'a1.UnitId=d.UnitId', array(), $select:: JOIN_LEFT)			
							->columns(array('WOBOQId','AgtNo'=> new Expression("a1.AgtNo"),'Specification' => new Expression("a1.Specification"),'Unit' => new Expression("d.UnitName")
								, 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
								, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)"),'Type' => new Expression("'A'") ))
							->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0' ))
							->where("a.WOBOQId<>0 AND a1.Specification<>'' AND a1.TransType<>'H'");
						if($billId!=0){
							$select->where("c.BillId=$billId");
						}
						$select->group(new Expression('a.WOBOQId,a1.AgtNo,a1.Specification,d.UnitName,a.Rate'));
								
						$select1 = $sql->select();
						$select1->from(array('a' => "CB_BillBOQ"))
							->join(array('a1' => 'CB_NonAgtItemMaster'), 'a.NonBOQId=a1.NonBOQId', array(), $select1:: JOIN_INNER)
							->join(array('b' => 'CB_BillAbstract'), 'a.BillAbsId=b.BillAbsId', array(), $select1:: JOIN_INNER)
							->join(array('c' => 'CB_BillMaster'), 'b.BillId=c.BillId', array(), $select1:: JOIN_INNER)
							->join(array('d' => 'Proj_UOM'), 'a1.UnitId=d.UnitId', array(), $select1:: JOIN_LEFT)			
							->columns(array('WOBOQId'=> new Expression("a.NonBOQId"),'AgtNo'=> new Expression("a1.SlNo"),'Specification' => new Expression("a1.Specification"),'Unit' => new Expression("d.UnitName")
								, 'Rate', 'SubmitQty' => new Expression("Sum(a.CurQty)") ,'SubmitAmount' => new Expression("Sum(a.CurAmount)")
								, 'CertifyQty' => new Expression("Sum(a.CerCurQty)") ,'CertifyAmount' => new Expression("Sum(a.CerCurAmount)"),'Type' => new Expression("'N'") ))
							->where(array("c.WORegisterId" => $PostDataStr , "c.DeleteFlag" => '0' ))
							->where("a.NonBOQId<>0 AND a1.Specification<>''");
						if($billId!=0){
							$select1->where("c.BillId=$billId");
						}
						$select1->group(new Expression('a.NonBOQId,a1.SlNo,a1.Specification,d.UnitName,a.Rate'));
						$select->combine($select1,'Union ALL');
						$statement = $statement = $sql->getSqlStringForSqlObject($select);
						$subvscerBoqList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
						foreach($subvscerBoqList as $subvscerBoqListTrans) {
							$insert = $sql->insert();
							$insert->into( 'CB_WOSubmitCertifyReport' );
							$insert->Values( array( 
								'WOBOQId' => $subvscerBoqListTrans['WOBOQId'],
								'AgtNo' => $subvscerBoqListTrans['AgtNo'],
								'Specification' => $subvscerBoqListTrans['Specification'],
								'UnitName' => $subvscerBoqListTrans['Unit'],
								'Rate' => $subvscerBoqListTrans['Rate'],
								'SubmitQty' => $subvscerBoqListTrans['SubmitQty'],
								'SubmitAmount' => $subvscerBoqListTrans['SubmitAmount'],
								'CertifyQty' => $subvscerBoqListTrans['CertifyQty'],
								'CertifyAmount' => $subvscerBoqListTrans['CertifyAmount'],
								'Type' => $subvscerBoqListTrans['Type']
							));
							$statement = $sql->getSqlStringForSqlObject( $insert );
							$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );		
						}
						//End Update//
						$select = $sql->select();
						$select->from( 'CB_WOSubmitCertifyReport' )
							->columns( array( 'WOBOQId','AgtNo','Specification','UnitName','Rate','SubmitQty','SubmitAmount','CertifyQty','CertifyAmount','Type' =>new Expression("Case When Type ='A' then 'Agreement Item' else 'NonAgreement Item' end")) );
						$select->order(new Expression("Type,WOBOQId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
												
						$data = json_encode($results);
                        break;                  
					}

                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		}
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
		$select = $sql->select();
		$select->from(array("a"=>"CB_WORegister"))
			->columns(array('data' => 'WorkOrderId', 'value' => 'WONo'));
		$select->where( "a.DeleteFlag='0' AND a.SubscriberId=$subscriberId AND a.WONo<>'' " );
		$statement = $statement = $sql->getSqlStringForSqlObject($select);
		$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->woList = $woList;
		
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
	
	public function receivablestatementAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
				$RType = $this->bsf->isNullCheck( $postData[ 'rtype' ], 'string' );
                $PostDataStr = $this->bsf->isNullCheck( $postData[ 'data' ], 'string' );
				$asonDate= date('Y-m-d', strtotime($PostDataStr));

                $select = $sql->select();
                switch($RType) {
					case 'getLoad':
					
					$select->from(array('a'=>'CB_WORegister'))
					->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array(), $select:: JOIN_INNER)
					->columns(array('ClientId', 'ClientName' => new Expression ("b.ClientName"), 'WOAmount' => new Expression ("Sum(a.OrderAmount)")));
					$select->where( "a.DeleteFlag='0' and a.WODate<= '$asonDate' " );
					$select->group(new Expression('a.ClientId,b.ClientName'));
					$statement = $sql->getSqlStringForSqlObject($select);
					$clientList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					$arrWoLists= array();
					$i=0;
					$ParId=0;
					foreach($clientList as &$clientLists) {
						$i=$i+1;
						$ParId=0;
						
						$ClientId=$clientLists['ClientId'];
						$j=$i;
						$select = $sql->select();
						$select->from(array('a'=>'CB_WORegister'))
							->columns(array('WorkOrderId', 'WONo', 'WOAmount' => new Expression ("a.OrderAmount")));
						$select->where("a.ClientId= $ClientId AND a.DeleteFlag='0' and a.WODate<= '$asonDate' ");
						$statement = $sql->getSqlStringForSqlObject($select);
						$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$totSubmitAmountList=0;
						$totCertifyAmountList=0;
						$totReceiptAmountList=0;
								
						foreach($woList as &$woLists) {
							$WorkOrderId=$woLists['WorkOrderId'];
							$i=$i+1;
							$k=$i;
							
							$select = $sql->select();
							$select->from(array('a'=>'CB_BillMaster'))
								->columns(array('BillId', 'BillNo', 'SubmitAmount', 'CertifyAmount'));
							$select->where("a.WORegisterId= $WorkOrderId AND a.DeleteFlag='0' and a.BillDate<= '$asonDate' ");
							$statement = $sql->getSqlStringForSqlObject($select);
							$billList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							$totSubmitAmount = 0;
							$totCertifyAmount = 0;
							$totReceiptAmount = 0;
							foreach($billList as &$billLists) {
								$i=$i+1;
								$l=$i;
								$BillId=$billLists['BillId'];
								$select = $sql->select();
								$select->from(array('a'=>'CB_ReceiptAdustment'))
									->join(array('b' => 'CB_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select:: JOIN_INNER)
									->columns(array('ReceiptAmount' => new Expression ("isnull(Sum(a.Amount),0)")));
								$select->where("a.BillId= $BillId AND b.DeleteFlag='0' and b.ReceiptDate<= '$asonDate' ");
								$statement = $sql->getSqlStringForSqlObject($select);
								$receiptList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
								$billLists['receiptAmount'] = $receiptList['ReceiptAmount'];
								$dumArr=array();
								$dumArr = array(
									'Id' => $l,
									'ParentId' => $k,
									'Description' => $billLists['BillNo'],
									'WOAmount' => 0,
									'SubmitAmount' => $billLists['SubmitAmount'],
									'CertifyAmount' =>$billLists['CertifyAmount'],
									'ReceiptAmount' => $receiptList['ReceiptAmount'],
									'BalanceAmount' => $billLists['CertifyAmount'] - $receiptList['ReceiptAmount']
								);
								$arrWoLists[] =$dumArr;

								$totSubmitAmount += $billLists['SubmitAmount'];
								$totCertifyAmount += $billLists['CertifyAmount'];
								$totReceiptAmount += $receiptList['ReceiptAmount'];
							}
							$dumArr=array();
							$dumArr = array(
								'Id' => $k,
								'ParentId' => $j,
								'Description' => $woLists['WONo'],
								'WOAmount' => $woLists['WOAmount'],
								'SubmitAmount' => $totSubmitAmount,
								'CertifyAmount' => $totCertifyAmount,
								'ReceiptAmount' => $totReceiptAmount,
								'BalanceAmount' => $totCertifyAmount - $totReceiptAmount,
								'expanded' => 'true'
							);
							$arrWoLists[] =$dumArr;
							
							$totSubmitAmountList += $totSubmitAmount;
							$totCertifyAmountList += $totCertifyAmount;
							$totReceiptAmountList += $totReceiptAmount;
						}

						$dumArr=array();
						$dumArr = array(
							'Id' => $j,
							'ParentId' => $ParId,
							'Description' => $clientLists['ClientName'],
							'WOAmount' => $clientLists['WOAmount'],
							'SubmitAmount' => $totSubmitAmountList,
							'CertifyAmount' => $totCertifyAmountList,
							'ReceiptAmount' => $totReceiptAmountList,
							'BalanceAmount' => $totCertifyAmountList - $totReceiptAmountList,
							'expanded' => 'true'
						);
						$arrWoLists[] =$dumArr;
					}
			
						
					$data = json_encode($arrWoLists);
					break;
				}
                $response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		}
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
		$asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
		
		$select = $sql->select();
		$select->from(array('a'=>'CB_WORegister'))
		->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array(), $select:: JOIN_INNER)
			->columns(array('ClientId', 'ClientName' => new Expression ("b.ClientName"), 'WOAmount' => new Expression ("Sum(a.OrderAmount)")));
		$select->where( "a.DeleteFlag='0' and a.WODate<= '$asonDate' " );
		$select->group(new Expression('a.ClientId,b.ClientName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$clientList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$arrWoLists= array();
		$i=0;
		$ParId=0;
		foreach($clientList as &$clientLists) {
			$i=$i+1;
			$ParId=0;
			
			$ClientId=$clientLists['ClientId'];
			$j=$i;
			$select = $sql->select();
			$select->from(array('a'=>'CB_WORegister'))
				->columns(array('WorkOrderId', 'WONo', 'WOAmount' => new Expression ("a.OrderAmount")));
			$select->where("a.ClientId= $ClientId AND a.DeleteFlag='0' and a.WODate<= '$asonDate' ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$totSubmitAmountList=0;
			$totCertifyAmountList=0;
			$totReceiptAmountList=0;
					
			foreach($woList as &$woLists) {
				$WorkOrderId=$woLists['WorkOrderId'];
				$i=$i+1;
				$k=$i;
				
				$select = $sql->select();
				$select->from(array('a'=>'CB_BillMaster'))
					->columns(array('BillId', 'BillNo', 'SubmitAmount', 'CertifyAmount'));
				$select->where("a.WORegisterId= $WorkOrderId AND a.DeleteFlag='0' and a.BillDate<= '$asonDate' ");
				$statement = $sql->getSqlStringForSqlObject($select);
				$billList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$totSubmitAmount = 0;
				$totCertifyAmount = 0;
				$totReceiptAmount = 0;
				foreach($billList as &$billLists) {
					$i=$i+1;
					$l=$i;
					$BillId=$billLists['BillId'];
					$select = $sql->select();
					$select->from(array('a'=>'CB_ReceiptAdustment'))
						->join(array('b' => 'CB_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select:: JOIN_INNER)
						->columns(array('ReceiptAmount' => new Expression ("isnull(Sum(a.Amount),0)")));
					$select->where("a.BillId= $BillId AND b.DeleteFlag='0' and b.ReceiptDate<= '$asonDate' ");
					$statement = $sql->getSqlStringForSqlObject($select);
					$receiptList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$billLists['receiptAmount'] = $receiptList['ReceiptAmount'];
					$dumArr=array();
					$dumArr = array(
						'Id' => $l,
						'ParentId' => $k,
						'Description' => $billLists['BillNo'],
						'WOAmount' => 0,
						'SubmitAmount' => $billLists['SubmitAmount'],
						'CertifyAmount' =>$billLists['CertifyAmount'],
						'ReceiptAmount' => $receiptList['ReceiptAmount'],
						'BalanceAmount' => $billLists['CertifyAmount'] - $receiptList['ReceiptAmount']
					);
					$arrWoLists[] =$dumArr;

					$totSubmitAmount += $billLists['SubmitAmount'];
					$totCertifyAmount += $billLists['CertifyAmount'];
					$totReceiptAmount += $receiptList['ReceiptAmount'];
				}
				$dumArr=array();
				$dumArr = array(
					'Id' => $k,
					'ParentId' => $j,
					'Description' => $woLists['WONo'],
					'WOAmount' => $woLists['WOAmount'],
					'SubmitAmount' => $totSubmitAmount,
					'CertifyAmount' => $totCertifyAmount,
					'ReceiptAmount' => $totReceiptAmount,
					'BalanceAmount' => $totCertifyAmount - $totReceiptAmount,
					'expanded' => 'true'
				);
				$arrWoLists[] =$dumArr;
				
				$totSubmitAmountList += $totSubmitAmount;
				$totCertifyAmountList += $totCertifyAmount;
				$totReceiptAmountList += $totReceiptAmount;
			}

			$dumArr=array();
			$dumArr = array(
				'Id' => $j,
				'ParentId' => $ParId,
				'Description' => $clientLists['ClientName'],
				'WOAmount' => $clientLists['WOAmount'],
				'SubmitAmount' => $totSubmitAmountList,
				'CertifyAmount' => $totCertifyAmountList,
				'ReceiptAmount' => $totReceiptAmountList,
				'BalanceAmount' => $totCertifyAmountList - $totReceiptAmountList,
				'expanded' => 'true'
			);
			$arrWoLists[] =$dumArr;
		}

		/*echo '<pre>';
		print_r($arrWoLists);
		echo '</pre>';
		die;*/
		$this->_view->arrWoLists = $arrWoLists;
		
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}

	public function aginganalysisrptAction(){
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
        $subscriberId = $this->auth->getIdentity()->SubscriberId;
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
                $postData = $request->getPost();
				
				$response = $this->getResponse();
                $response->setContent($data);
                return $response;
			}
		}
		
		if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
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
		
		$asonDate= date('Y-m-d', strtotime(Date('d-m-Y')));
		
		$select = $sql->select();
		$select->from(array('a'=>'CB_WORegister'))
		->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array(), $select:: JOIN_INNER)
			->columns(array('ClientId', 'ClientName' => new Expression ("b.ClientName"), 'WOAmount' => new Expression ("Sum(a.OrderAmount)")));
		$select->where( "a.DeleteFlag='0' and a.WODate<= '$asonDate' " );
		$select->group(new Expression('a.ClientId,b.ClientName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$clientList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$arrWoLists= array();
		$i=0;
		$ParId=0;
		foreach($clientList as &$clientLists) {
			$i=$i+1;
			$ParId=0;
			
			$ClientId=$clientLists['ClientId'];
			$j=$i;
			$select = $sql->select();
			$select->from(array('a'=>'CB_WORegister'))
				->columns(array('WorkOrderId', 'WONo', 'WOAmount' => new Expression ("a.OrderAmount")));
			$select->where("a.ClientId= $ClientId AND a.DeleteFlag='0' and a.WODate<= '$asonDate' ");
			$statement = $sql->getSqlStringForSqlObject($select);
			$woList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$totSubmitAmountList=0;
			$totCertifyAmountList=0;
			$totReceiptAmountList=0;
					
			foreach($woList as &$woLists) {
				$WorkOrderId=$woLists['WorkOrderId'];
				$i=$i+1;
				$k=$i;
				
				$select = $sql->select();
				$select->from(array('a'=>'CB_BillMaster'))
					->columns(array('BillId', 'BillNo', 'SubmitAmount', 'CertifyAmount'));
				$select->where("a.WORegisterId= $WorkOrderId AND a.DeleteFlag='0' and a.BillDate<= '$asonDate' ");
				$statement = $sql->getSqlStringForSqlObject($select);
				$billList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$totSubmitAmount = 0;
				$totCertifyAmount = 0;
				$totReceiptAmount = 0;
				foreach($billList as &$billLists) {
					$i=$i+1;
					$l=$i;
					$BillId=$billLists['BillId'];
					$select = $sql->select();
					$select->from(array('a'=>'CB_ReceiptAdustment'))
						->join(array('b' => 'CB_ReceiptRegister'), 'a.ReceiptId=b.ReceiptId', array(), $select:: JOIN_INNER)
						->columns(array('ReceiptAmount' => new Expression ("isnull(Sum(a.Amount),0)")));
					$select->where("a.BillId= $BillId AND b.DeleteFlag='0' and b.ReceiptDate<= '$asonDate' ");
					$statement = $sql->getSqlStringForSqlObject($select);
					$receiptList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
					$billLists['receiptAmount'] = $receiptList['ReceiptAmount'];
					$dumArr=array();
					$dumArr = array(
						'Id' => $l,
						'ParentId' => $k,
						'Description' => $billLists['BillNo'],
						'WOAmount' => 0,
						'SubmitAmount' => $billLists['SubmitAmount'],
						'CertifyAmount' =>$billLists['CertifyAmount'],
						'ReceiptAmount' => $receiptList['ReceiptAmount'],
						'BalanceAmount' => $billLists['CertifyAmount'] - $receiptList['ReceiptAmount']
					);
					$arrWoLists[] =$dumArr;

					$totSubmitAmount += $billLists['SubmitAmount'];
					$totCertifyAmount += $billLists['CertifyAmount'];
					$totReceiptAmount += $receiptList['ReceiptAmount'];
				}
				$dumArr=array();
				$dumArr = array(
					'Id' => $k,
					'ParentId' => $j,
					'Description' => $woLists['WONo'],
					'WOAmount' => $woLists['WOAmount'],
					'SubmitAmount' => $totSubmitAmount,
					'CertifyAmount' => $totCertifyAmount,
					'ReceiptAmount' => $totReceiptAmount,
					'BalanceAmount' => $totCertifyAmount - $totReceiptAmount,
					'expanded' => 'true'
				);
				$arrWoLists[] =$dumArr;
				
				$totSubmitAmountList += $totSubmitAmount;
				$totCertifyAmountList += $totCertifyAmount;
				$totReceiptAmountList += $totReceiptAmount;
			}

			$dumArr=array();
			$dumArr = array(
				'Id' => $j,
				'ParentId' => $ParId,
				'Description' => $clientLists['ClientName'],
				'WOAmount' => $clientLists['WOAmount'],
				'SubmitAmount' => $totSubmitAmountList,
				'CertifyAmount' => $totCertifyAmountList,
				'ReceiptAmount' => $totReceiptAmountList,
				'BalanceAmount' => $totCertifyAmountList - $totReceiptAmountList,
				'expanded' => 'true'
			);
			$arrWoLists[] =$dumArr;
		}

		/*echo '<pre>';
		print_r($arrWoLists);
		echo '</pre>';
		die;*/
		$this->_view->arrWoLists = $arrWoLists;
		
		$viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
		return $this->_view;
	}
}