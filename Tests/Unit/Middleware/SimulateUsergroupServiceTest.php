<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Tests\Unit\Middleware;

use DirectMailTeam\DirectMail\Middleware\SimulateUsergroup;
use DirectMailTeam\DirectMail\Utility\DmRegistryUtility;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Context\Context;

final class SimulateUsergroupServiceTest extends TestCase
{
    public function testMiddlewareCanBeRetrievedWithAutowiredDependencies(): void
    {
        $configuration = Yaml::parseFile(__DIR__ . '/../../../Configuration/Services.yaml')['services'];
        $defaults = $configuration['_defaults'];
        $middleware = $configuration[SimulateUsergroup::class] ?? [];
        $container = new ContainerBuilder();
        $container->register(Context::class);
        $container->register(DmRegistryUtility::class);
        $container->register(SimulateUsergroup::class)
            ->setAutowired($middleware['autowire'] ?? $defaults['autowire'])
            ->setPublic($middleware['public'] ?? $defaults['public']);

        $container->compile();

        self::assertTrue(
            $container->has(SimulateUsergroup::class),
            'TYPO3 must be able to retrieve the middleware rather than construct it without dependencies.'
        );
        self::assertInstanceOf(SimulateUsergroup::class, $container->get(SimulateUsergroup::class));
    }
}
