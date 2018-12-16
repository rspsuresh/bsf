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
                        'controller' => 'Clientbilling\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /clientbilling/:controller/:action
            'clientbilling' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/clientbilling',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Clientbilling\Controller',
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
                    'billreportlist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:billid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'billreportlist',
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
                    ),'reportabstractcurrent' => array(
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
            'Clientbilling\Controller\Index' => 'Clientbilling\Controller\IndexController',
            'Clientbilling\Controller\Billformatmaster' => 'Clientbilling\Controller\BillformatmasterController',
            'Clientbilling\Controller\Receipt' => 'Clientbilling\Controller\ReceiptController',
            'Clientbilling\Controller\Report' => 'Clientbilling\Controller\ReportController',
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
            'layout/clientbillinglayout'           => __DIR__ . '/../../Clientbilling/view/layout/clientbillinglayout.phtml',
            'clientbilling/index/index' => __DIR__ . '/../view/clientbilling/index/index.phtml',
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
