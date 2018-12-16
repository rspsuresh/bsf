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
                        'controller' => 'Workflow\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /workflow/:controller/:action
            'workflow' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/workflow',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Workflow\Controller',
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
					'company-view' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:companyId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'company-view',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'user-view' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:userId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'user-view',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'costcenter-view' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CostCentreId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'costcenter-view',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'new-company' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:companyId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'new-company',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'cost-center' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CostCentreId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'cost-center',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'user-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:userId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'user-entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'approval-settings' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:type]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'approval-settings',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'currency-settings' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:type]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'currency-settings',
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
            'Workflow\Controller\Index' => 'Workflow\Controller\IndexController',
			'Workflow\Controller\Activity' => 'Workflow\Controller\ActivityController'
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
            'workflow/index/index' => __DIR__ . '/../view/workflow/index/index.phtml',
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
