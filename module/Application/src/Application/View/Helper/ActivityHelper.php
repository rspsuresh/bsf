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

class ActivityHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    protected $connection = null;

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return CustomHelper
     */
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
 
    public function activityDisplay($in_parent,$store_all_id) {
		$returnString = '';
		$this->auth = new AuthenticationService();
        $dbAdapter = $this->getServiceLocator()->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
		
		if(in_array($in_parent, $store_all_id)) {
			$select = $sql->select();
			$select->from('WF_ActivityMaster')
				->where(array('ParentId' => $in_parent));
			$select_stmt = $sql->getSqlStringForSqlObject($select);
			$result = $dbAdapter->query($select_stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			$returnString .= $in_parent == 0 ? "<ul class='tree'>" : "<ul>";
			foreach($result as $results) {
				$returnString .= "<li";
				if ($results['Hide']) {
					$returnString .= " class='thide'";
				}
				$returnString .= "><div id=" . $results['ActivityId'] . " class='tree_div'>
				<span class='activityName'>" . $results['ActivityName'] . " <span class='delete_action'><i class='fa fa-trash-o'></i></span></span></div>";
				$returnString .= $this->activityDisplay($results['ActivityId'], $store_all_id);
				$returnString .= "</li>";
			}
			$returnString .= "</ul>";
		}
        return $returnString;
    }
}
?>