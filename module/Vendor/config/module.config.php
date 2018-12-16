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
                        'controller' => 'Vendor\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /vendor/:controller/:action
            'vendor' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/vendor',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Vendor\Controller',
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
                    'basic-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid][/:mode]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'basic-detail'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'contact-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                           'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'contact-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'statutory-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid][/:mode]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'statutory-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'bankfinance-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                           'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'bankfinance-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'experience-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'experience-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					
					'vendor-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action][/:vendorid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-detail',
								'vendorid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
				
					'vendor-terms' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-terms',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'branch' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'branch',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'resource' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'resource',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'financial' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'financial',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'supply' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'supply',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'works' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'works',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'service' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'service',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'servicemaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'servicemaster-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'others' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'others',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'manufacture-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'manufacture-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'dealer-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'dealer-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),	
					'distributor-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'distributor-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'assessment-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'assessment-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'assessmentmaster-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'assessmentmaster-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'vendor-registration' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-registration',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unapprove' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'unapprove-vendor',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'grade' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'grade',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'vendor-renewal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid[/:regid]]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-renewal',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'vendor-profile' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vendor-profile',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'license' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action][/:vendorid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'license',
								'vendorid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'ohse' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action][/:vendorid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'ohse',
								'vendorid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'vehicle' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vehicle',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'vehiclemaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid][/:vehicleid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vehiclemaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'vehicleregister' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'vehicleregister',
                                //'vendorid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'unregistervendor-profile' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action][/:vendorid]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'unregistervendor-profile',
								'vendorid' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'uploadfile' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorid][/:expid]]]',
                            'constraints' => array(
                                'action'     => 'upload-file',
                                'controller'  => 'index',
                                'rfqId' => '\d+',
                            ),
                            'defaults' => array(
                            )
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
            'Vendor\Controller\Index' => 'Vendor\Controller\IndexController',
            'Vendor\Controller\Decision' => 'Vendor\Controller\DecisionController',
			'Vendor\Controller\Rfq' => 'Vendor\Controller\RfqController',
			'Vendor\Controller\Response' => 'Vendor\Controller\ResponseController',
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
            'vendor/index/index' => __DIR__ . '/../view/vendor/index/index.phtml',
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
