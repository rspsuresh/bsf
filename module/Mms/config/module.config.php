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
                        'controller' => 'Mms\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /mms/:controller/:action
            'mms' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/mms',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Mms\Controller',
                        'controller'    => 'Master',
                        'action'        => 'opening-stock',
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
					'feeds-edit' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller][/:action][/:regid]',
								'constraints' => array(
									'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
									'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								),
								'defaults' => array(
								),
							),
					),

					'feeds-entry-edit' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller][/:action][/:regid]',
								'constraints' => array(
									'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
									'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								),
								'defaults' => array(
								),
							),
					),	

					'entry-edit' => array(
						 'type' => 'segment',
						 'options' => array(
							 'route'    => '/[:controller[/:action][/:mid]]',
							 'constraints' => array(
								 'action'     => 'entry-edit',
								 'controller'     => 'min',
								 'mid' => '\d+',
							 ),
							 'defaults' => array(
							 )
						),
					),


					'entry-edit' => array(
						 'type' => 'segment',
						 'options' => array(
							 'route'    => '/[:controller[/:action][/:IssueRegisterId]]',
							 'constraints' => array(
								 'action'     => 'entry-edit',
								 'controller'     => 'issue',
								 'IssueRegisterId' => '\d+',
							 ),
							 'defaults' => array(
							 )
						),
					),
                     'issue-detailed' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:rid]]',
                                'constraints' => array(
                                    'action'     => 'issue-detailed',
                                    'controller'     => 'issue',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                     ),
                        'issueedit' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:rid]]',
                                'constraints' => array(
                                    'action'     => 'issueedit',
                                    'controller'     => 'issue',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'issueDelete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:id]]',
                                'constraints' => array(
                                    'action'     => 'issueDelete',
                                    'controller'     => 'issue',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
					'opening-stock' => array(
						 'type' => 'segment',
						 'options' => array(
							 'route'    => '/[:controller[/:action][/:projectId]]',
							 'constraints' => array(
								 'action'     => 'opening-stock',
								 'controller'     => 'master',
								 'projectId' => '\d+',
							 ),
							 'defaults' => array(
							 )
						 ),
					),	
					'priority' => array(
						'type' => 'segment',
						'options' => array(
							 'route'    => '/[:controller[/:action][/:projectId]]',
							 'constraints' => array(
								 'action'     => 'priority',
								 'controller'     => 'master',
								 'projectId' => '\d+',
							 ),
							 'defaults' => array(
							 )
						),
					),	
					'resource-register' => array(
						'type' => 'segment',
						'options' => array(
							'route'    => '/[:controller[/:action][/:projectId]]',
							'constraints' => array(
								 'action'     => 'resource-register',
								 'controller'     => 'master',
								 'projectId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),	
                  'resource-item-register' => array(
						'type' => 'segment',
						'options' => array(
							'route'    => '/[:controller[/:action]]',
							'constraints' => array(
								 'action'     => 'resource-item-register',
								 'controller'     => 'master',
								 //'projectId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),						
				   'gate-list' => array(
						 'type' => 'segment',
						 'options' => array(
							 'route'    => '/[:controller[/:action][/:projectId]]',
							 'constraints' => array(
								 'action'     => 'gate-list',
								 'controller'     => 'master',
								 'projectId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),
					'purchase-type' => array(
						'type' => 'segment',
						'options' => array(
							'route'    => '/[:controller[/:action][/:companyId]]',
							'constraints' => array(
								'action'     => 'purchase-type',
								'controller'     => 'purchase',
								'companyId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),
					'resource-item' => array(
						'type' => 'segment',
						'options' => array(
							'route'    => '/[:controller[/:action][/:brandId]]',
							'constraints' => array(
								'action'     => 'resource-item',
								'controller'     => 'master',
								'brandId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),
                        'Pbill' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action]]',
                                'constraints' => array(
                                    'action'     => 'pbill',
                                    'controller'     => 'purchasebill',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'displaypurchasebill' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'displaypurchasebill',
                                    'controller'     => 'purchasebill',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'billentry' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:flag][/:id]]]',
                                'constraints' => array(
                                    'action'     => 'billentry',
                                    'controller'     => 'purchasebill',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),

                        'pbill-delete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:regId]]]',
                                'constraints' => array(
                                    'action'     => 'pbill-delete',
                                    'controller'     => 'purchasebill',
                                    'regId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),

					'register' => array(
						'type' => 'segment',
						'options' => array(
							'route'    => '/[:controller[/:action]]',
							'constraints' => array(
								'action'     => 'register',
								'controller'     => 'issue',
								//'brandId' => '\d+',
							),
							'defaults' => array(
							)
						),
					),

                    'order-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:flag][/:poRegId]]]',
                            'constraints' => array(
                                'controller' => 'purchase',
                                'action'     => 'order-entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'detailed' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:rid]]',
                                'constraints' => array(
                                    'action'     => 'detailed',
                                    'controller'     => 'purchase',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'request' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:RcId]]',
                                'constraints' => array(
                                    'action'     => 'request',
                                    'controller'     => 'purchase',
                                    'RcId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'transfer-entry' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:flag][/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'tventry',
                                    'controller'     => 'transfer',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'transfer-detailed' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:rid]]',
                                'constraints' => array(
                                    'action'     => 'detailed',
                                    'controller'     => 'transfer',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'transfer-shortclose' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:tid]]',
                                'constraints' => array(
                                    'action'     => 'transfer-shortclose',
                                    'controller'     => 'transfer',
                                    'tid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'minentry' => array(
                            'type'    => 'Segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:flag][/:dcId]]]',
                                'constraints' => array(
                                    'controller' => 'min',
                                    'action'     => 'minentry',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
                        'delete-min' => array(
                            'type'    => 'Segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'controller' => 'min',
                                    'action'     => 'delete-min',

                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
                        'mindetailed' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    =>'/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'detailed',
                                    'controller' => 'min',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
                        'min-shortclose' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    =>'/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'min-shortclose',
                                    'controller' => 'min',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
						'termsmaster' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action]]',
                                'constraints' => array(
                                    'action'     => 'termsmaster',
                                    'controller'     => 'termsmaster',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
						
						'displayregister' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:GateRegId]]]',
                                'constraints' => array(
                                    'action'     => 'displayregister',
                                    'controller'     => 'master',
                                    'GateRegId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
						
						'gateentry-edit' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:GateRegId]]]',
                                'constraints' => array(
                                    'action'     => 'gateentry-edit',
                                    'controller'     => 'master',
                                    'GateRegId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
						
						'deletegate' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:GateRegId]]]',
                                'constraints' => array(
                                    'action'     => 'deletegate',
                                    'controller'     => 'master',
                                    'GateRegId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
						
						'reportlist' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action]]',
                                'constraints' => array(
                                    'action'     => 'reportlist',
                                    'controller'     => 'report',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'detailedconversion' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'detailedconversion',
                                    'controller'     => 'minconversion',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'deleteconversion' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'conversion-delete',
                                    'controller'     => 'minconversion',
                                    'rid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'returnbill-detailed' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:prId]]]',
                                'constraints' => array(
                                    'action'     => 'returnbill-detailed',
                                    'controller'     => 'billreturn',
                                    'prId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'return-entry' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action][/:prId]]',
                                'constraints' => array(
                                    'action'     => 'return-entry',
                                    'controller'     => 'billreturn',
                                    'prId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'returnbill-delete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:prId]]]',
                                'constraints' => array(
                                    'action'     => 'returnbill-delete',
                                    'controller'     => 'billreturn',
                                    'prId' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'conversionentry' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:id]]]',
                                'constraints' => array(
                                    'action'     => 'conversionentry',
                                    'controller'     => 'minconversion',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'tvreceipt-entry' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:tid]]]',
                                'constraints' => array(
                                    'action'     => 'tvreceipt-entry',
                                    'controller' => 'transfer',
                                    'tid' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'tvreceipt-details' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:id]]]',
                                'constraints' => array(
                                    'action'     => 'tvreceipt-details',
                                    'controller' => 'transfer',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'tvreceipt-delete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:did]]]',
                                'constraints' => array(
                                    'action'     => 'tvreceipt-delete',
                                    'controller' => 'transfer',
                                    'did' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'warehousetransfer-edit' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:id]]]',
                                'constraints' => array(
                                    'action'     => 'warehousetransfer-entry',
                                    'controller' => 'transfer',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'warehousetransfer-details' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:id]]]',
                                'constraints' => array(
                                    'action'     => 'warehouse-transfer-detailed',
                                    'controller' => 'transfer',
                                    'id' => '\d+',
                                ),
                                'defaults' => array(
                                )
                            ),
                        ),
                        'warehousetransfer-delete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    =>'/[:controller[/:action[/:id]]]',
                                'constraints' => array(
                                    'action'     => 'warehouse-transfer-delete',
                                    'controller' => 'transfer',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
                        'fast-moving' => array(
                            'type'    => 'Segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:CostCentre][/:WareHouse]]]',
                                'constraints' => array(
                                    'controller' => 'general',
                                    'action'     => 'fast-moving',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),

                        'monthly-purchase-details' => array(
                            'type'    => 'Segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:CostCentre]]]',
                                'constraints' => array(
                                    'controller' => 'purchasebillreport',
                                    'action'     => 'monthly-purchase-details',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
						'purchaseshort-close' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    =>'/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'purchaseshort-close',
                                    'controller' => 'purchase',
                                ),
                                'defaults' => array(
                                ),
                            ),
                        ),
						'purchaseshortclose-delete' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    =>'/[:controller[/:action[/:rid]]]',
                                'constraints' => array(
                                    'action'     => 'purchaseshortclose-delete',
                                    'controller' => 'purchase',
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
						'report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'purchase',
									'action'     => 'report',
								),
								'defaults' => array(
								),
							),
						),
						'minheader' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'minheader',
								),
								'defaults' => array(
								),
							),
						),
						'minfooter' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'minfooter',
								),
								'defaults' => array(
								),
							),
						),
						'minreport' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'min',
									'action'     => 'minreport',
								),
								'defaults' => array(
								),
							),
						),
						'minconversion-header' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'minconversion-header',
								),
								'defaults' => array(
								),
							),
						),
						'minconversion-footer' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'minconversion-footer',
								),
								'defaults' => array(
								),
							),
						),
						'minconversion-report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'minconversion',
									'action'     => 'minconversion-report',
								),
								'defaults' => array(
								),
							),
						),
						'purchasebill-header' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'purchasebill-header',
								),
								'defaults' => array(
								),
							),
						),
						'purchasebill-footer' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'purchasebill-footer',
								),
								'defaults' => array(
								),
							),
						),
						'purchasebill-report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'purchasebill',
									'action'     => 'purchasebill-report',
								),
								'defaults' => array(
								),
							),
						),
						'issue-header' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'issue-header',
								),
								'defaults' => array(
								),
							),
						),
						'issue-footer' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'issue-footer',
								),
								'defaults' => array(
								),
							),
						),
						'issue-report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'issue',
									'action'     => 'issue-report',
								),
								'defaults' => array(
								),
							),
						),
						'transdispatch-header' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'transdispatch-header',
								),
								'defaults' => array(
								),
							),
						),
						'transdispatch-footer' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'transdispatch-footer',
								),
								'defaults' => array(
								),
							),
						),
						'transdispatch-report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'transfer',
									'action'     => 'transdispatch-report',
								),
								'defaults' => array(
								),
							),
						),
						'billreturn-header' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'billreturn-header',
								),
								'defaults' => array(
								),
							),
						),
						'billreturn-footer' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:type]]]',
								'constraints' => array(
									'controller' => 'report',
									'action'     => 'billreturn-footer',
								),
								'defaults' => array(
								),
							),
						),
						'billreturn-report' => array(
							'type'    => 'Segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:id]]]',
								'constraints' => array(
									'controller' => 'billreturn',
									'action'     => 'billreturn-report',
								),
								'defaults' => array(
								),
							),
						),
						'uploadfile' => array(
							'type' => 'segment',
							'options' => array(
								'route'    => '/[:controller[/:action[/:rfqId]]]',
								'constraints' => array(
									'action'     => 'uploadfile',
									'controller'     => 'min',
									'rfqId' => '\d+',
								),
								'defaults' => array(
								)
							),
						),
                        'quality-test-upload' => array(
                            'type' => 'segment',
                            'options' => array(
                                'route'    => '/[:controller[/:action[/:qtId]]]',
                                'constraints' => array(
                                    'action'     => 'quality-test-upload',
                                    'controller'     => 'min',
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
            'Mms\Controller\Index' => 'Mms\Controller\IndexController',
			'Mms\Controller\Purchase' => 'Mms\Controller\PurchaseController',
			'Mms\Controller\Min' => 'Mms\Controller\MinController',
			'Mms\Controller\Minconversion' => 'Mms\Controller\MinconversionController',

			'Mms\Controller\Master' => 'Mms\Controller\MasterController',
			'Mms\Controller\Issue' => 'Mms\Controller\IssueController',
			'Mms\Controller\Purchasebill' => 'Mms\Controller\PurchasebillController',
			'Mms\Controller\Termsmaster' => 'Mms\Controller\TermsmasterController',
            'Mms\Controller\Transfer' => 'Mms\Controller\TransferController',
			'Mms\Controller\Report' => 'Mms\Controller\ReportController',
			'Mms\Controller\Billreturn' => 'Mms\Controller\BillreturnController',
			'Mms\Controller\Minreport' => 'Mms\Controller\MinreportController',
			'Mms\Controller\Purchasebillreport' => 'Mms\Controller\PurchasebillreportController',
			'Mms\Controller\Issuereport' => 'Mms\Controller\IssuereportController',
			'Mms\Controller\General' => 'Mms\Controller\GeneralController',
			'Mms\Controller\Transferreport' => 'Mms\Controller\TransferreportController',
            'Mms\Controller\Stock' => 'Mms\Controller\StockController',
            'Mms\Controller\Stockreport' => 'Mms\Controller\StockreportController'


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
            'mms/index/index' => __DIR__ . '/../view/mms/index/index.phtml',
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