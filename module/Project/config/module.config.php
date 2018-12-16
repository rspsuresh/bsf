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
            'project' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/project',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Project\Controller',
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
                    'getrfciowpicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfciowpicklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getrfcresourcepicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcresourcepicklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfciowdelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfciowdelete',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcresgroupdelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcresgroupdelete',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'rfcprojectiowdelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:id][/:projecttype]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcprojectiowdelete',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'rfcwbsdelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcwbsdelete',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'getrfcresourcegrouppicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcresourcegrouppicklist',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'getrfcresgrouppicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcresgrouppicklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),


                    'getresourceunit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:resId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getresourceunit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getiowunit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:resId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getiowunit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tdssetting' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:periodid]]]',
                            'constraints' => array(
                                'controller' => 'qualifier',
                                'action'     => 'tdssetting',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'servicetaxsetting' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:periodid]]]',
                            'constraints' => array(
                                'controller' => 'qualifier',
                                'action'     => 'servicetaxsetting',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'checkresparentnamefound' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:groupname]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'checkresparentnamefound',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'checkworktypenamefound' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:typename]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'checkworktypenamefound',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'checkrestypefound' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:typename]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'checkrestypefound',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'checkunitfound' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:unitname]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'checkunitfound',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcworkgroupdelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId[/:id]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcworkgroupdelete',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getrfcworkgrouppicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId[/:editId]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcworkgrouppicklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getrfcworktypepicklist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId[/:editId]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcworktypepicklist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcresource' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcresource',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcworktype' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcworktype',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcworkgroup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcworkgroup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcresourcegroup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcresourcegroup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'resgroupmaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'resgroupmaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workgroupmaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'workgroupmaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfciow' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfciow',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcresourcerate' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcresourcerate',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcboqqty' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcboqqty',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbs' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'wbs',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcwbsedit' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:wbsid][/:projectid[/:projecttype]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcwbsedit',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcprojectiow' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcprojectiow',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),'rfciowplan' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfciowplan',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcresourcedelete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId][/:id]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcresourcedelete',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcregister' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcregister',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getrfcwbslist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:rfcid]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getrfcwbslist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getworktypelist' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:ids]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getworktypelist',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getprojectboqmaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getprojectboqmaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'getprojectwbsmaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'getprojectwbsmaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectwbs' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId[/:projectType]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'projectwbs',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'landbankenquiry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'enquiry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'initialfeasibility' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:feasibilityId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'initialfeasibility',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'duediligence' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:dueDiligenceId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'duediligence',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'businessfeasibility' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:feasibilityId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'businessfeasibility',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'businessfeasibility-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'businessfeasibility-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectconception-register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'projectconception-register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'financialfeasibility' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'financialfeasibility',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'financialfeasibilitydetail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EnquiryId][/:FeasibilityId][/:FinancialFeasibilityId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'financialfeasibilitydetail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'enquiry' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:TenderEnquiryId]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'enquiry',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'qualifiersetting' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:Type]]]',
                            'constraints' => array(
                                'controller' => 'qualifier',
                                'action'     => 'qualifiersetting',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'register' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EnquiryId]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'register',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'finalization' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:finalizationId][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'finalization',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectconception' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EnquiryId]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'projectconception',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'feasibilityoption' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EnquiryId][/:OptionType][/:page]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'feasibilityoption',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectconceptiondetail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:EnquiryId[/:FeasibilityId][/:page]]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'projectconceptiondetail',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'workorder' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:mode][/:id]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'workorder',
                            ),
                            'defaults' => array(
                            )
                        ),
                    ),
                    'landbankfollowup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:followupid]]]',
                            'constraints' => array(
                                'controller' => 'landbank',
                                'action'     => 'followup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'followup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:followupid]]]',
                            'constraints' => array(
                                'controller' => 'followup',
                                'action'     => 'followup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'document-purchase' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:EnquiryCallTypeId][/:EnquiryFollowupId]]]',
                            'constraints' => array(
                                'controller' => 'followup',
                                'action'     => 'document-purchase',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'site-investigation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:EnquirySiteInvestigationId]]]]',
                            'constraints' => array(
                                'controller' => 'followup',
                                'action'     => 'site-investigation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'technical-specification' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:TechnicalSpecificationId]]]]',
                            'constraints' => array(
                                'controller' => 'followup',
                                'action'     => 'technical-specification',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcschedule' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'schedule',
                                'action'     => 'rfcschedule',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbs-schedule' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId]]]',
                            'constraints' => array(
                                'controller' => 'schedule',
                                'action'     => 'wbs-schedule',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'document' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'document',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'bidstatus' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:EnquiryFollowupId]]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'bidstatus',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'quotation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'quotation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'pre-qualstatus' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:CallTypeId[/:EnquiryFollowupId]]]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'pre-qualstatus',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'meeting' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:CallTypeId[/:EnquiryFollowupId]]]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'meeting',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projboq' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:type][/:page]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'projboq',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projboqplan' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'projboqplan',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'iowmasterview' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:wgid][/:page]]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'iowmasterview',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'iowmasterprint' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:wgid][/:page]]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'iowmasterprint',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'resourcemaster' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'resourcemaster',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projboq-print' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:type]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'projboq-print',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projwbs' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:type][/:page]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'projwbs',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectmain' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => 'dashboard',
                                'action'     => 'projectmain',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'holidaysetting' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'holidaysetting',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectdashboard' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'dashboard',
                                'action'     => 'projectdashboard',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'schedulevscompletion' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'schedulevscompletion',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'resourcerequirement' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:typeid]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'resourcerequirement',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectprogress' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'projectprogress',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectedcashflow' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'projectedcashflow',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'revisionreport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'revisionreport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'planquantity-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'planquantity-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbsalloted-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'wbsalloted-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbsitemabstract' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'wbsitemabstract',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbsrevision' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'wbsrevision',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'itemwisewbs-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid[/:type]]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'itemwisewbs-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'quotationcomparison' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'quotationcomparison',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'quotationproposal' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:quotationid]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'quotationproposal',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'slippagereport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'slippagereport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'schedulereport' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'schedulereport',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'criticalactivity' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'criticalactivity',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'resourcedetail-report' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:type]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'resourcedetail-report',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectworkgroup' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:projecttype]]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'projectworkgroup',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'wbsbudget' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId][/:type]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'wbsbudget',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectsummary' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectId]]]',
                            'constraints' => array(
                                'controller' => 'report',
                                'action'     => 'projectsummary',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'projectiowsortorder' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:projectid][/:projecttype]]]',
                            'constraints' => array(
                                'controller' => 'main',
                                'action'     => 'projectiowsortorder',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'rfcothercost' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:rfcId[/:id[/:projectid[/:projecttype]]]]]]',
                            'constraints' => array(
                                'controller' => 'rfc',
                                'action'     => 'rfcothercost',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tender-quotation' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId][/:quotationId][/:mode]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'tender-quotation',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tendersubmitform' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'tendersubmitform',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'tenderquantityrevision' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId[/:RegisterId]]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'quantity-revision',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                    'quotationsortorder' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action[/:enquiryId]]]',
                            'constraints' => array(
                                'controller' => 'tender',
                                'action'     => 'quotationsortorder',
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
            'Project\Controller\Index' => 'Project\Controller\IndexController',
            'Project\Controller\Main' => 'Project\Controller\MainController',
            'Project\Controller\Rfc' => 'Project\Controller\RfcController',
            'Project\Controller\Schedule' => 'Project\Controller\ScheduleController',
            'Project\Controller\Landbank' => 'Project\Controller\LandbankController',
            'Project\Controller\Tender' => 'Project\Controller\TenderController',
            'Project\Controller\Followup' => 'Project\Controller\FollowupController',
            'Project\Controller\Qualifier' => 'Project\Controller\QualifierController',
            'Project\Controller\Report' => 'Project\Controller\ReportController',
            'Project\Controller\Dashboard' => 'Project\Controller\DashboardController',
            'Project\Controller\Template' => 'Project\Controller\TemplateController',
            'Project\Controller\Communication' => 'Project\Controller\CommunicationController'
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
            'project/index/index' => __DIR__ . '/../view/project/index/index.phtml',
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