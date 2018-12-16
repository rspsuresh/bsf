<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

return array(
    'general' => array(
        'mandrilEmail'         => 'sathish@micromen.in',
        'socketIpCall' =>   'http://192.168.1.8:2001',
        'socketIpChat' =>   'http://192.168.1.8:2002'
    ),
);

