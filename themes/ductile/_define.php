<?php

/**
 * @file
 * @brief       The theme ductile definition
 * @ingroup     ductile
 *
 * @defgroup    ductile Theme ductile.
 *
 * ductile, a mediaqueries compliant elegant theme for Dotclear 2.
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
$this->registerModule(
    'Ductile',                              // Name
    'Mediaqueries compliant elegant theme', // Description
    'Dotclear Team',                        // Author
    '3.0',                                  // Version
    [                                  // Properties
        'standalone_config' => true,
        'type'              => 'theme',
        'overload'          => true,
    ]
);
