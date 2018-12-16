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
                        'controller' => 'Warehouse\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /Warehouse/:controller/:action
            'warehouse' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/warehouse',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Warehouse\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'warehouse-plan' => array(
                        'type'    => 'Segment',
                        'options' => array(
                           'route'    => '/[:controller[/:action][/:warehouseid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'warehouse-plan',
								'warehouseid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'warehouse-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'warehouse-register'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'warehouse-planedit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                           'route'    => '/[:controller[/:action][/:warehouseid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'warehouse-planedit',
								'warehouseid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'warehouse-create' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:warehouseid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'warehouse-create',
                                'warehouseid' => '\d+',
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
            'Warehouse\Controller\Index' => 'Warehouse\Controller\IndexController'
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
            'warehouse/index/index' => __DIR__ . '/../view/warehouse/index/index.phtml',
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
