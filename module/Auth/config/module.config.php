<?php

declare(strict_types=1);

use Auth\Controller\AuthController;
use Auth\Controller\Factory\AuthControllerFactory;
use Auth\Factory\AuthenticationServiceFactory;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Router\Http\Literal;
use Laminas\Session\Storage\SessionArrayStorage;

return [
    'doctrine' => [
        'driver' => [
            'auth_entities' => [
                'class' => AttributeDriver::class,
                'cache' => 'array',
                'paths' => [__DIR__ . '/../src/Entity'],
            ],
            'orm_default' => [
                'drivers' => [
                    'Auth\Entity' => 'auth_entities',
                ],
            ],
        ],
    ],

    'service_manager' => [
        'factories' => [
            AuthenticationService::class => AuthenticationServiceFactory::class,
        ],
        'aliases' => [
            AuthenticationServiceInterface::class => AuthenticationService::class,
        ],
    ],

    'session_config' => [
        'name'            => 'CRUDITISESSID',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime'  => 7200,
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],

    'controllers' => [
        'factories' => [
            AuthController::class => AuthControllerFactory::class,
        ],
    ],

    'router' => [
        'routes' => [
            'login' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/login',
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action'     => 'login',
                    ],
                ],
            ],
            'logout' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/logout',
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action'     => 'logout',
                    ],
                ],
            ],
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            'auth' => __DIR__ . '/../view',
        ],
    ],
];
