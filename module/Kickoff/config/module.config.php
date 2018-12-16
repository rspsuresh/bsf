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
                        'controller' => 'Pcm\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /pcm/:controller/:action
            'kickoff' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/kickoff',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Kickoff\Controller',
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
                    'newproject' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'newproject'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'newproject-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'newproject-edit'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'project-kickoff' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:msg]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'project-kickoff',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'conception' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'conception'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'conception-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId][/:conceptionId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'conception-detail'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'wbs' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'wbs'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'turnaround' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'turnaround'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'team' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'team'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'critical-area' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'critical-area'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'make-brand' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'make-brand'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'documents' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'documents'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'setup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:kickoffId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'setup'
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
            'Kickoff\Controller\Index' => 'Kickoff\Controller\IndexController'
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
            'kickoff/index/index' => __DIR__ . '/../view/kickoff/index/index.phtml',
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
