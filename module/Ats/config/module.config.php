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
                        'controller' => 'Ats\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /ats/:controller/:action
             'ats' => array(
                 'type'    => 'segment',
                 'options' => array(
                     'route'    => '/ats',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Ats\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                 ),
				 
             // Defines that "/news" can be matched on its own without a child route being matched
             'may_terminate' => true,
             'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'controller'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),				 
			 
                 'edit-resource' => array(
                     'type' => 'segment',
                     'options' => array(
                         'route'    => '/[:controller[/:action][/:rid]]',
                         'constraints' => array(
                             'action'     => 'edit-resource',
							 'controller'     => 'index',
							 'rid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
                 'request-detailed' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rid]]',
                         'constraints' => array(
                             'action'     => 'request-detailed',
							 'controller'     => 'index',
							 'rid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
                 'editrequest-decision' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:decisionid]]',
                         'constraints' => array(
                             'action'     => 'editrequest-decision',
							 'controller'     => 'decision',
							 'decisionid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),	
				 'request-decision' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action[/:flag][/:decisionid]]]',
                         'constraints' => array(
                             'action'     => 'request-decision',
							 'controller'     => 'decision',
							 'decisionid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 
                 'detailed' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:decisionid]]',
                         'constraints' => array(
                             'action'     => 'detailed',
							 'controller'     => 'decision',
							 'decisionid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),		
				'new-rfq' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:quottypeid]]',
                         'constraints' => array(
                             'action'     => 'new-rfq',
							 'controller'     => 'rfq',
							 'quottypeid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
                 'rfqedit' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'rfqedit',
							 'controller'     => 'rfq',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),						 
				'rfq-detailed' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'rfq-detailed',
							 'controller'     => 'rfq',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				'uploadfile' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqId]]',
                         'constraints' => array(
                             'action'     => 'uploadfile',
							 'controller'     => 'rfq',
							 'rfqId' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),	
				 'rfqresponse-track' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'rfqresponse-track',
							 'controller'     => 'rfq',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),					 
				'response-entry' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid][/:vendorid]]',
                         'constraints' => array(
                             'action'     => 'response-entry',
							 'controller'     => 'response',
							 'rfqid' => '\d+',
							 'vendorid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				'edit-response' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'edit-response',
							 'controller'     => 'response',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),	
				 'techinfo-entry' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid][/:vendorid]]',
                         'constraints' => array(
                             'action'     => 'techinfo-entry',
							 'controller'     => 'response',
							 'rfqid' => '\d+',
							 'vendorid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'edit-techinfo' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'edit-techinfo',
							 'controller'     => 'response',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'response-details' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'response-details',
							 'controller'     => 'response',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),	
				 'accept-material' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'accept-material',
							 'controller'     => 'response',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),					 
				 'validating-techinfo' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'validating-techinfo',
							 'controller'     => 'rfq',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'request-analysis' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'request-analysis',
							 'controller'     => 'rfq',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'request-termanalysis' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'request-termanalysis',
							 'controller'     => 'rfq',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'response-detailview' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'response-detailview',
							 'controller'     => 'response',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
				 'rfq-vendors' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:rfqid]]',
                         'constraints' => array(
                             'action'     => 'rfq-vendors',
							 'controller'     => 'response',
							 'rfqid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                ),
				 'analysis-detailed' => array(
                     'type' => 'segment',
                     'options' => array(
                        'route'    => '/[:controller[/:action][/:regid]]',
                         'constraints' => array(
                             'action'     => 'analysis-detailed',
							 'controller'     => 'rfq',
							 'regid' => '\d+',
                         ),
                         'defaults' => array(
                         )
                     ),
                 ),
                 'request-delete' => array(
                     'type'    => 'Segment',
                     'options' => array(
                         'route'    => '/[:controller[/:action[/:requestId]]]',
                         'constraints' => array(
                             'controller' => 'index',
                             'action'     => 'request-delete',
                             'requestId' => '\d+',
                         ),
                         'defaults' => array(
                         ),
                     ),
                 ),
                 'entry-sample' => array(
                     'type'    => 'Segment',
                     'options' => array(
                         'route'    => '/[:controller[/:action[/:requestId]]]',
                         'constraints' => array(
                             'controller' => 'index',
                             'action'     => 'entry-sample',
                         ),
                         'defaults' => array(
                         ),
                     ),
                 ),
				 
				'design' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'    => '/[:controller[/:action[/:type]]]',
						'constraints' => array(
							'controller' => 'report',
							'action'     => 'design',
						),
						'defaults' => array(
						),
					),
                ),
				'designfooter' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'    => '/[:controller[/:action[/:type]]]',
						'constraints' => array(
							'controller' => 'report',
							'action'     => 'designfooter',
						),
						'defaults' => array(
						),
					),
                ),
				'request' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'    => '/[:controller[/:action[/:id]]]',
						'constraints' => array(
							'controller' => 'index',
							'action'     => 'request',
						),
						'defaults' => array(
						),
					),
				),
                 'delete' => array(
                     'type'    => 'Segment',
                     'options' => array(
                         'route'    => '/[:controller[/:action[/:rid]]]',
                         'constraints' => array(
                             'controller' => 'decision',
                             'action'     => 'delete',
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
            'Ats\Controller\Index' => 'Ats\Controller\IndexController',
			'Ats\Controller\Decision' => 'Ats\Controller\DecisionController',
			'Ats\Controller\Rfq' => 'Ats\Controller\RfqController',
			'Ats\Controller\Response' => 'Ats\Controller\ResponseController',
			'Ats\Controller\Report' => 'Ats\Controller\ReportController'
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
            'ats/index/index' => __DIR__ . '/../view/ats/index/index.phtml',
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
