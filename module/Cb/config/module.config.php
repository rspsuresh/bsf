<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(
    'router' => array(
        'routes' => array(
            /*'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Cb\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /cb/:controller/:action
            'cb' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/cb',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Cb\Controller',
                        'controller'    => 'Index',
                        'action'        => 'dashboard',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id[/:mode[/:type]]]]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'index' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:SearchStr]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'index',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workorder' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:SearchStr]]]',
                            'constraints' => array(
                                'controller' => 'workorder',
                                'action'     => 'index',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'clientbilling' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:SearchStr]]]',
                            'constraints' => array(
                                'controller' => 'clientbilling',
                                'action'     => 'index',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'receipt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:SearchStr]]]',
                            'constraints' => array(
                                'controller' => 'receipt',
                                'action'     => 'index',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reset-password' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id][/:code]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'reset-password',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'user-signup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:subId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'user-signup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstract' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'clientbilling',
                                'action'     => 'reportabstract',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstractiow' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'clientbilling',
                                'action'     => 'reportabstractiow',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstractoverall' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'clientbilling',
                                'action'     => 'reportabstractoverall',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-receipt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'receipt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-workorderdet' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'workorderdet',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-materialadvdet' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'materialadvdet',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-priceesclationdet' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'priceesclationdet',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'billreportlist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'clientbilling',
                                'action'     => 'billreportlist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstractcurrent' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'reportabstractcurrent',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstractsubvscer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'reportabstractsubvscer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'reportabstractmeasure' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'reportabstractmeasure',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'sample' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'sample',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-workorderdetreport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'workorderdetreport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-rabilldetreport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type[/:entryfrom]]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'rabilldetreport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'report-receiptdetreport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'receiptdetreport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'aliases' => array(
            'translator' => 'MvcTranslator',
        ),
    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Cb\Controller\Index' => 'Cb\Controller\IndexController',
            'Cb\Controller\Workorder' => 'Cb\Controller\WorkorderController',
            'Cb\Controller\Clientbilling' => 'Cb\Controller\ClientbillingController',
            'Cb\Controller\Receipt' => 'Cb\Controller\ReceiptController',
			'Cb\Controller\Report' => 'Cb\Controller\ReportController',
			'Cb\Controller\Master' => 'Cb\Controller\MasterController',
			'Cb\Controller\Bsf' => 'Cb\Controller\BsfController',
			'Cb\Controller\Expense' => 'Cb\Controller\ExpenseController',
			'Cb\Controller\Plan' => 'Cb\Controller\PlanController'
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../../Application/view/layout/layout.phtml',
            'layout/clientbillinglayout'           => __DIR__ . '/../../Cb/view/layout/clientbillinglayout.phtml',
            'cb/index/index' => __DIR__ . '/../view/cb/index/index.phtml',
            'error/404'               => __DIR__ . '/../../Application/view/error/404.phtml',
            'error/index'             => __DIR__ . '/../../Application/view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
            ),
        ),
    ),
);
