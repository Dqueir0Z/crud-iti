<?php

declare(strict_types=1);

namespace Application;

use Laminas\Mvc\MvcEvent;

class Module
{
    public function getConfig(): array
    {
        /** @var array $config */
        $config = include __DIR__ . '/../config/module.config.php';
        return $config;
    }

    public function onBootstrap(MvcEvent $evento): void
    {
        // O php.ini do Ubuntu vem em UTC; as datas gravadas/exibidas usam o fuso configurado.
        /** @var array{app?: array{timezone?: string}} $config */
        $config = $evento->getApplication()->getServiceManager()->get('config');
        date_default_timezone_set($config['app']['timezone'] ?? 'America/Sao_Paulo');

        // Expõe o nome da rota atual ao layout, para destacar o item do menu.
        $evento->getApplication()->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            static function (MvcEvent $e): void {
                $rota = $e->getRouteMatch();
                $e->getViewModel()->setVariable('rotaAtual', $rota?->getMatchedRouteName() ?? '');
            },
            -50
        );
    }
}
