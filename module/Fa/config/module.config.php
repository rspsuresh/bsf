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
                        'controller' => 'Fa\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /fa/:controller/:action
            'fa' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/fa',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Fa\Controller',
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
                    'cashbankdetailentry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:cashbankId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'cashbankdetailentry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'fiscalyearentry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:FYearId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'fiscalyearentry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'accountentry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'accountentry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                   /* 'companyaccountdet' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fYearId][/:companyId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'companyaccountdet',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),*/
                    'ifrsdetail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:typeId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'ifrsdetail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'chequedetail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'chequedetail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'cashmanagement' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EntryId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'cashmanagement',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'specialjournal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:JournalEntryId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'specialjournal',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'transferregister' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId][/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'transferregister',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'groupcompanytransfer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:entryId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'groupcompanytransfer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'multicompanytransfer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mtransferId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'multicompanytransfer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'chequenodetail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId][/:chequeId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'chequenodetail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'recurringentry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:recurringId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'recurringentry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'ccbalance' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accId][/:g_lCNYearId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'ccbalance',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'slbalance' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'slbalance',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'paymentadvice' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:paymentAdvRegId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'paymentadvice',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'paymentjournalregister' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId][/:fromDate][/:toDate][/:bookType]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'paymentjournalregister',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'paymentjournal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:entryId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'paymentjournal',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'depositentry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:entryId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'depositentry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'depositregister' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId][/:fromDate][/:toDate][/:bookType]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'depositregister',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'journalbook' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accountId][/:fromDate][/:toDate][/:bookType]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'journalbook',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'trialbalancerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'trialbalancerpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'trialbalancetrans' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accIds][/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'trialbalancetrans',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'generalledgerrpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'generalledgerrpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'summarytbrpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'summarytbrpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'summarytbtrans' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:accIds][/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'summarytbtrans',
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
            'Fa\Controller\Index' => 'Fa\Controller\IndexController',
            'Fa\Controller\Report' => 'Fa\Controller\ReportController'
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
            'fa/index/index' => __DIR__ . '/../view/fa/index/index.phtml',
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
