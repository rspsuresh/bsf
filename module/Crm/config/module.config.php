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
                        'controller' => 'Crm\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),*/
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /crm/:controller/:action
            'crm' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/crm',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Crm\Controller',
                        'controller'    => 'Index',
                        'action'        => 'dashboard',
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
						'car-park-management' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CarId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'car-park-management',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'campaign-index' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'campaign',
                                'action'     => 'index',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'car-park-detail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CarId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'car-park-detail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'block-history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:blockType]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'block-history',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'prebook-history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:preBookType]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'prebook-history',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'statement-account-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:UnitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'statement-account-print',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'plan-based-discount' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:PlanId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'plan-based-discount',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'units-generation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:UnitId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'units-generation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'other-cost' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:costCentreId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'other-cost',
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
                                'controller' => 'buyer',
                                'action'     => 'register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'maintenance-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:MaintainId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'maintenance-print',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'details',

                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'broker-register-page' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'register',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'block-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'block-register',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-block-cancellation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:unitId][/:cancellationId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'unit-block-cancellation',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'report-project-wise' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'report-project-wise',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-followup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:BrokerId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'followup',
                                'BrokerId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-followup-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'followup-register',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-full-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    =>'/[:controller[/:action[/:BrokerId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'full-details',
                                //'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:CallerSid]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'entry',
								'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'personal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'personal',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'address' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'address',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'bank' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'bank',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'coa' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:coAppId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'coa',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'poa' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:poaId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'poa',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-voucher' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:voucherId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'payment-voucher',
                                'leadId' => "\d+",
                            ),
                            'voucherId' => array(
                            ),
                        ),
                    ),
                    'payment-voucher-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:voucherId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'payment-voucher-print',
                                'leadId' => "\d+",
                            ),
                            'voucherId' => array(
                            ),
                        ),
                    ),
                    'financial' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'financial',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'requirement' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'requirement',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'lead-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'register',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'edit',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-due' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'payment',
                                'unitId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'statement-of-account' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'statement-of-account',
                                'unitId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'progress-bp' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'progress',
                                'unitId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'ticket-entry-bp' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'ticket-entry',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'customisation-order' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'customisation-order',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'customisation-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'Bp',
                                'action'     => 'customisation-register',
                                'unitId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'details',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'lead-full-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'full-details',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'executive-edit' => array(
                        'type'  => 'Segment',
                        'options' => array(
                            'route'  => '/[:controller[/:action[/:TargetId]]]',
                            'constraints' => array(
                                'controller' => 'executive',
                                'action' => 'edit',
                                'TargetId' => '\d+'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'pm-rental-edit' => array(
                        'type'  => 'Segment',
                        'options' => array(
                            'route'  => '/[:controller[/:action[/:RegisterId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action' => 'rental-edit',
                                'TargetId' => '\d+'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rental-print' => array(
                        'type'  => 'Segment',
                        'options' => array(
                            'route'  => '/[:controller[/:action[/:RegisterId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action' => 'rental-print',
                                'TargetId' => '\d+'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'ticket-register' => array(
                        'type'  => 'Segment',
                        'options' => array(
                            'route'  => '/[:controller[/:action[/:status]]]',
                            'constraints' => array(
                                'controller' => 'Support',
                                'action' => 'ticket-register',
                                'TargetId' => '\d+'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'ticket-entry' => array(
                        'type'  => 'Segment',
                        'options' => array(
                            'route'  => '/[:controller[/:action[/:TicketId]]]',
                            'constraints' => array(
                                'controller' => 'Support',
                                'action' => 'ticket-entry',
                                'TargetId' => '\d+'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'buyer-full-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'full-details',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup-page' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:projectId][/:callSid]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'followup',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'entry-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'entry-edit',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'history',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'buyer-followup-history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'followup-history',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-followup-history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:BrokerId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'history',
                                'BrokerId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup-entry-page' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'followup-entry',
                                //'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup-history' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'followup-history',
                                'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    =>'/[:controller[/:action[/:BrokerId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'broker-followup-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:FollowupId]]]',
                            'constraints' => array(
                                'controller' => 'broker',
                                'action'     => 'followup-details',

                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'lead-followup-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EntryId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'followup-details',

                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'allocation-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'allocation-register',
                                //'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'allocation-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'allocation-details',
                                //'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projects' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'projects',
                                'projectId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'general' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller'     => 'project',
                                'action'     => 'general',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'land-area' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'land-area',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'land-cost' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'land-cost',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'other-cost' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'other-cost',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'payment',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-schedule-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'payment-schedule-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-schedule' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'payment-schedule',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-generation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-generation',
                                'projectId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'agreement-generation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:BuyerId][/:AgreementId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'agreement-generation'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'allotment-letter' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:BuyerId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'allotment-letter'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-type-generation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-type-generation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unittypegeneration' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'multi-select',
                                'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'utg' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitTypeId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'utg',
                                'unitTypeId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'other-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:vendorId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'other-details',
                                'vendorId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'facility' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'facility',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'car-park' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:carId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'car-park',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'other-facility' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'other-facility',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'checklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'checklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'penality-interestrate' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'penality-interestrate',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:followupId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'followup-details',

                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:LeadId][/:ProjectId][/:CallSid]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'followup'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'followup-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:followupId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'followup-register',
                                'followupId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-fulldetails' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:UnitDetailId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-fulldetails',
                                'UnitDetailId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'costsheet' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:UnitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'costsheet',
                                'UnitId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unitgrid-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:UnitDetailId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unitgrid-details',
                                'UnitDetailId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'unit-details' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-details',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'receipt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:id][/:aAmount]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'receipt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'receipt-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:receiptId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'receipt-print',
                                'receiptId' => "\d+"
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'extra-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:extraId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'extra-print',
                                'extraId' => "\d+"
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'progress' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:stgCId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'progress',
                                'stgCId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'stagecompletion-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:stageCompletionId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'stagecompletion-edit',
                                'stageCompletionId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'stagecompletion' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:ProjectId][/:UnitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'stagecompletion',
                                'stageCompletionId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'unit-grid' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:projectId][/:soldId]]]',
							'constraints' => array(
								'controller' => 'project',
								'action'     => 'unit-grid',
								'projectId' => "\d+",
							),
							'defaults' => array(
							),
						),
					),
					'progress-edit' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:pbId]]]',
							'constraints' => array(
								'controller' => 'bill',
								'action'     => 'progress-edit',
								'pbId' => "\d+",
							),
							'defaults' => array(
							),
						),
					),
					'progress-print' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:pbId]]]',
							'constraints' => array(
								'controller' => 'bill',
								'action'     => 'progress-print',
								'pbId' => "\d+",
							),
							'defaults' => array(
							),
						),
					),
					'finalisation' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:mode[/:leadId][/:CallTypeId][/:Date][/:Call][/:ProjectId][/:UnitId]]]]',
							'constraints' => array(
								'controller' => 'lead',
								'action'     => 'finalisation',
							),
							'defaults' => array(
							),
						),
					),

                    'finalisation-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:bookingId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'finalisation-edit',
                                'bookingId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'post-sale-discount' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:bookingId][/:postsaleId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'post-sale-discount',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'block' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:CallTypeId][/:Date][/:Call][/:ProjectId][/:UnitId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'block',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'block-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:bookingId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'block-edit',
                                'bookingId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'extra-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:extraBillRegId]]]',
                            'constraints' => array(
                                'controller' => 'bill',
                                'action'     => 'extra-edit',
                                'bookingId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'target' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'executive',
                                'action'     => 'target',

                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-schedule-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:paySchId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'payment-schedule-edit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-schedule-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:paySchPrintId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'payment-schedule-print',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-type' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-type',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-type-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-type-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'incentive-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'incentive-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-type-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:unitTypeId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-type-edit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'unit-edit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'dashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:userId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'dashboard',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'crmdashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'index',
                                'action'     => 'crmdashboard',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'buyerwisereceivablerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:stageCompletionId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'buyerwisereceivablerpt',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'stagewisereceivablerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:paySchId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'stagewisereceivablerpt',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'loanduerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'loanduerpt',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'ageingrpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'ageingrpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unittransferhistory' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'unittransferhistory',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unitcancelhistory' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'unitcancelhistory',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:regId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'entry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'deposit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId][/:regId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'deposit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),


                    'payment-entry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:RegisterId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'payment',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:RegisterId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'payment-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'payment-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:RegisterId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'payment-edit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),


                    'services' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId][/:regId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'services',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'rent' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId][/:regId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'rent',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'property-management' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'property-management',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

					'salesteamperformancerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:year][/:mode][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'salesteamperformancerpt',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'executiveanalysisrpt' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
							'constraints' => array(
								'controller' => 'report',
								'action'     => 'executiveanalysisrpt',
							),
							'defaults' => array(
							),
						),
					),
                    'executive-next-followup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'executive-next-followup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'reserve' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:reserveId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'reserve',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'maintenance-bill' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:registerId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'maintenance-bill',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'inventory-bill' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:registerId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'inventory-bill',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-transfer' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId][/:transferId][/:callTypeId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'unit-transfer',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-cancellation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:unitId][/:cancellationId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'unit-cancellation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-pre-booking' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:CallTypeId][/:Date][/:Call][/:PreBookingId]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'unit-pre-booking',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-proposal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:leadId][/:CallTypeId][/:Date][/:Call]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'unit-proposal',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'property-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'register',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'property-dashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'dashboard',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'servicedone-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:ServiceDoneRegId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'servicedone-edit',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'servicedone-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'servicedone-register',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'dailycampaignanalysis' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:Date]]]',
							'constraints' => array(
								'controller' => 'report',
								'action'     => 'dailycampaignanalysis',
							),
							'defaults' => array(
							),
						),
					),
                    'rentalagreement' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:rentalId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'rental-agreement',
                                'rentalId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					
                    'commercialagreement' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:rentalId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'commercial-agreement',
                                'rentalId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'agreementrenewal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:rentalId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'agreement-renewal',
                                'rentalId' => "\d+"
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'agreementcancellation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:mode][/:rentalId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'agreement-cancellation',
                                'rentalId' => "\d+"
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'marketinganalysisrpt' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
							'constraints' => array(
								'controller' => 'report',
								'action'     => 'marketinganalysisrpt',
							),
							'defaults' => array(
							),
						),
					),
					'register' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:projectId]]]',
							'constraints' => array(
								'controller' => 'extraitem',
								'action'     => 'register',
							),
							'defaults' => array(
							),
						),
					),
					'request-register' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:projectId]]]',
							'constraints' => array(
								'controller' => 'extraitem',
								'action'     => 'request-register',
							),
							'defaults' => array(
							),
						),
					),
					'maintenancereceivablerpt' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
							'constraints' => array(
								'controller' => 'report',
								'action'     => 'maintenancereceivablerpt',
							),
							'defaults' => array(
							),
						),
					),
					'request-edit' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:regId]]]',
							'constraints' => array(
								'controller' => 'extraitem',
								'action'     => 'request-edit',
							),
							'defaults' => array(
							),
						),
					),
					'done-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'marketinganalysisrpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'extraitem',
                                'action'     => 'register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'request-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'extraitem',
                                'action'     => 'request-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'maintenancereceivablerpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'maintenancereceivablerpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'request-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:regId]]]',
                            'constraints' => array(
                                'controller' => 'extraitem',
                                'action'     => 'request-edit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'done-edit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:extraItemDoneRegId]]]',
                            'constraints' => array(
                                'controller' => 'extraitem',
                                'action'     => 'done-edit',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'master' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'extraitem',
                                'action'     => 'master',
                                //'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'email-template' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:emailTempId]]]',
                            'constraints' => array(
                                'controller' => 'email',
                                'action'     => 'email-template'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'agreement-template' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:TempId]]]',
                            'constraints' => array(
                                'controller' => 'property',
                                'action'     => 'agreement-template'
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'campaignbudgetvsexpenserpt' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'campaignbudgetvsexpenserpt',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tree-view' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'executive',
                                'action'     => 'tree-view',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

                    'handing-over' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:callTypeId]]]',
                            'constraints' => array(
                                'controller' => 'buyer',
                                'action'     => 'handingover',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),

					'receivablestatementrpt' => array(
						'type'    => 'Segment',
						'options' => array(
							'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
							'constraints' => array(
								'controller' => 'report',
								'action'     => 'receivablestatementrpt',
							),
							'defaults' => array(
							),
						),
					),
					'projectstatus' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'receivablestatementrpt',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectstatus' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'projectstatus',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
					'buyerreceivableabstract' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitId]]]',
                            'constraints' => array(
                                'controller' => 'project',
                                'action'     => 'buyerreceivableabstract',
								//'projectId' => '\d+',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'bankinfo' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:branchId]]]',
                            'constraints' => array(
                                'controller' => 'bank',
                                'action'     => 'bankinfo',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'unit-discount' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'unit-discount',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'lead' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId]]]',
                            'constraints' => array(
                                'controller' => 'telecaller',
                                'action'     => 'lead',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tele-lead-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:fromDate][/:toDate][/:filter]]]',
                            'constraints' => array(
                                'controller' => 'telecaller',
                                'action'     => 'lead-register',
                                'leadId' => "\d+",
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'bulk-lead-mail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:leadId][/:fromDate][/:toDate]]]',
                            'constraints' => array(
                                'controller' => 'lead',
                                'action'     => 'bulk-lead-mail',
                                'leadId' => "\d+",
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
            'Crm\Controller\Index' => 'Crm\Controller\IndexController',
            'Crm\Controller\Lead' => 'Crm\Controller\LeadController',
            'Crm\Controller\Buyer' => 'Crm\Controller\BuyerController',
            'Crm\Controller\Broker' => 'Crm\Controller\BrokerController',
            'Crm\Controller\Project' => 'Crm\Controller\ProjectController',
			'Crm\Controller\Executive' => 'Crm\Controller\ExecutiveController',
			'Crm\Controller\Bill' => 'Crm\Controller\BillController',
			'Crm\Controller\Campaign' => 'Crm\Controller\CampaignController',
			'Crm\Controller\pm' => 'Crm\Controller\pmController',
			'Crm\Controller\Property' => 'Crm\Controller\PropertyController',
			'Crm\Controller\Report' => 'Crm\Controller\ReportController',
			'Crm\Controller\Extraitem' => 'Crm\Controller\ExtraitemController',
			'Crm\Controller\Support' => 'Crm\Controller\SupportController',
			'Crm\Controller\Email' => 'Crm\Controller\EmailController',
            'Crm\Controller\Bank' => 'Crm\Controller\BankController',
            'Crm\Controller\Telecaller' => 'Crm\Controller\TelecallerController'

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
            'crm/index/index' => __DIR__ . '/../view/crm/index/index.phtml',
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
