<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Icp\Controller\AcController;
use Icp\Controller\AcN2Controller;
use Icp\Controller\ArController;
use Icp\Controller\EstruturaController;
use Icp\Controller\Factory\EntityManagerControllerFactory;
use Icp\Controller\Factory\ImportacaoControllerFactory;
use Icp\Controller\Factory\QrCodeControllerFactory;
use Icp\Controller\ImportacaoController;
use Icp\Controller\QrCodeController;
use Icp\Factory\BotaoQrCodeFactory;
use Icp\Factory\ImportadorEstruturaFactory;
use Icp\Factory\LinkQrCodeFactory;
use Icp\Service\GeradorQrCode;
use Icp\Service\ImportadorEstrutura;
use Icp\Service\LinkQrCode;
use Icp\View\Helper\BotaoQrCode;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Rotas de CRUD: /{base}, /{base}/create, /{base}/view/:id, /{base}/edit/:id
 * e /{base}/delete/:id (somente POST com token CSRF).
 */
$rotasCrud = static function (string $base, string $controller): array {
    $comId = static fn (string $acao): array => [
        'type'    => Segment::class,
        'options' => [
            'route'       => '/' . $acao . '/:id',
            'constraints' => ['id' => '[0-9]+'],
            'defaults'    => ['action' => $acao],
        ],
    ];

    return [
        'type'          => Literal::class,
        'options'       => [
            'route'    => $base,
            'defaults' => ['controller' => $controller, 'action' => 'index'],
        ],
        'may_terminate' => true,
        'child_routes'  => [
            'create' => [
                'type'    => Literal::class,
                'options' => ['route' => '/create', 'defaults' => ['action' => 'create']],
            ],
            'view'   => $comId('view'),
            'edit'   => $comId('edit'),
            'delete' => $comId('delete'),
        ],
    ];
};

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

    // Sobrescreva em config/autoload/local.php para que o QR Code aponte para um
    // endereço acessível de outro dispositivo (ex.: 'http://192.168.0.10:8080').
    'icp' => [
        'qrcode' => [
            'base_url' => null,
        ],
    ],

    'service_manager' => [
        'factories' => [
            GeradorQrCode::class       => InvokableFactory::class,
            LinkQrCode::class          => LinkQrCodeFactory::class,
            ImportadorEstrutura::class => ImportadorEstruturaFactory::class,
        ],
    ],

    'controllers' => [
        'factories' => [
            AcController::class         => EntityManagerControllerFactory::class,
            AcN2Controller::class       => EntityManagerControllerFactory::class,
            ArController::class         => EntityManagerControllerFactory::class,
            EstruturaController::class  => EntityManagerControllerFactory::class,
            ImportacaoController::class => ImportacaoControllerFactory::class,
            QrCodeController::class     => QrCodeControllerFactory::class,
        ],
    ],

    'view_helpers' => [
        'factories' => [
            BotaoQrCode::class => BotaoQrCodeFactory::class,
        ],
        'aliases'   => [
            'botaoQrCode' => BotaoQrCode::class,
        ],
    ],

    'router' => [
        'routes' => [
            'ac'    => $rotasCrud('/ac', AcController::class),
            'ac-n2' => $rotasCrud('/ac-n2', AcN2Controller::class),
            'ar'    => $rotasCrud('/ar', ArController::class),

            'estrutura' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/estrutura',
                    'defaults' => ['controller' => EstruturaController::class, 'action' => 'index'],
                ],
            ],
            'importacao' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/importar',
                    'defaults' => ['controller' => ImportacaoController::class, 'action' => 'index'],
                ],
            ],
            'qrcode' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/qrcode/:tipo/:id',
                    'constraints' => ['tipo' => 'ac|ac-n2|ar', 'id' => '[0-9]+'],
                    'defaults'    => ['controller' => QrCodeController::class, 'action' => 'svg'],
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
