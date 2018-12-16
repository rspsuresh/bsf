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
            'wpm' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/wpm',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Wpm\Controller',
                        'controller'    => 'workorder',
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
                    'wo-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:editId][/:amdId]]]',
                            'constraints' => array(
                                'controller' => 'workorder',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workprogress-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:editId]]]',
                            'constraints' => array(
                                'controller' => 'workprogress',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workbill-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:editId]]]',
                            'constraints' => array(
                                'controller' => 'workbill',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workbill-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:costcentreId]]]',
                            'constraints' => array(
                                'controller' => 'workbill',
                                'action'     => 'report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:labourId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'entry-form' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:labourId][/:type]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'entry-form',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'labour-master' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'labour-master',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'rate-approval-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:lraId][/:mode]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'rate-approval-entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'service-order' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:serviceOrderId][/:amdId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'service-order',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'service-done' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:serviceDoneId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'service-done',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'service-bill' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:serviceBillId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'service-bill',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'hire-order' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:hireOrderId][/:amdId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'hire-order',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'hire-bill' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:hireBillId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'hire-bill',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workcompletion-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:editId]]]',
                            'constraints' => array(
                                'controller' => 'workcompletion',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'retention-release' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:retRelId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'retention-release',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'advance' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:advRecId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'advance',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wpmdashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'wpmdashboard',
                                'action'     => 'wpmdashboard',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wpm-act-dashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'wpmdashboard',
                                'action'     => 'wpm-act-dashboard',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'security-deposit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:secDepId][/:type]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'security-deposit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'work-bill-type' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CostCentre][/:BillType][/:dateFrom][/:dateTo]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'work-bill-type',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'hire-order-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workorder',
                                'action'     => 'hire-order-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'service-order-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workorder',
                                'action'     => 'service-order-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'work-progress-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workprogress',
                                'action'     => 'work-progress-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'work-order-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id][/:type]]]',
                            'constraints' => array(
                                'controller' => 'workorder',
                                'action'     => 'work-order-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'work-bill-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workbill',
                                'action'     => 'work-bill-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'hire-bill-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workbill',
                                'action'     => 'hire-bill-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'service-bill-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:id]]]',
                            'constraints' => array(
                                'controller' => 'workbill',
                                'action'     => 'service-bill-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'voucher' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'voucher',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'footer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'footer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'labour-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:labourRegId][/:mode][/:labourId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'labour-entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'labourtransfer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:transId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'labourtransfer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'labourdeactivate' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:DeactivateId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'labour-deactivate',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'labouractivate' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:ActivateId]]]',
                            'constraints' => array(
                                'controller' => 'labourstrength',
                                'action'     => 'labour-activate',
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
            'Wpm\Controller\Workorder' => 'Wpm\Controller\WorkorderController',
			'Wpm\Controller\Labourstrength' => 'Wpm\Controller\LabourstrengthController',
			'Wpm\Controller\Workprogress' => 'Wpm\Controller\WorkprogressController',
			'Wpm\Controller\Billformatmaster' => 'Wpm\Controller\BillformatmasterController',
            'Wpm\Controller\Wpmdashboard' => 'Wpm\Controller\WpmdashboardController',
            'Wpm\Controller\Workbill' => 'Wpm\Controller\WorkbillController',
            'Wpm\Controller\Workcompletion' => 'Wpm\Controller\WorkcompletionController',
            'Wpm\Controller\report' => 'Wpm\Controller\ReportController',
            'Wpm\Controller\template' => 'Wpm\Controller\TemplateController'
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
            'wpm/index/index'		  => __DIR__ . '/../view/wpm/index/index.phtml',
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
