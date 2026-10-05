<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Icp\Controller\AcController;
use Icp\Controller\Factory\AcControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;

return [
    'doctrine' => [
        'driver' => [
            'icp_entities' => [
                'class' => AttributeDriver::class,
                'cache' => 'array',
                'paths' => [
                    __DIR__ . '/../src/Entity',
                ],
            ],

            'orm_default' => [
                'drivers' => [
                    'Icp\Entity' => 'icp_entities',
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            AcController::class => AcControllerFactory::class,
        ],
    ],

    'router' => [
        'routes' => [
            'ac' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/ac',
                    'defaults' => [
                        'controller' => AcController::class,
                        'action' => 'index',
                    ],
                ],

                'may_terminate' => true,

                'child_routes' => [
                    'create' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/create',
                            'defaults' => [
                                'action' => 'create',
                            ],
                        ],
                    ],

                    'edit' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/edit/:id',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults' => [
                                'action' => 'edit',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            'icp' => __DIR__ . '/../view',
        ],
    ],
];
