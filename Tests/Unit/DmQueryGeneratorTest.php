<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Tests\Unit;

use DirectMailTeam\DirectMail\DmQueryGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Lowlevel\Controller\QuerySearchController;

final class DmQueryGeneratorTest extends TestCase
{
    public function testQueryGeneratorUsesTheTypo3FourteenControllerAndAutowiredConstructor(): void
    {
        self::assertTrue(is_subclass_of(DmQueryGenerator::class, QuerySearchController::class));
        $configuration = Yaml::parseFile(__DIR__ . '/../../Configuration/Services.yaml')['services'];
        $defaults = $configuration['_defaults'];
        $service = $configuration[DmQueryGenerator::class] ?? [];
        $container = new ContainerBuilder();
        $constructor = new \ReflectionMethod(DmQueryGenerator::class, '__construct');
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $container->register($type)->setSynthetic(true);
        }
        $container->register(DmQueryGenerator::class)
            ->setAutowired($service['autowire'] ?? $defaults['autowire'])
            ->setPublic($service['public'] ?? $defaults['public'])
            ->setShared($service['shared'] ?? true);

        $container->compile();

        self::assertTrue($container->has(DmQueryGenerator::class));
        self::assertFalse($container->getDefinition(DmQueryGenerator::class)->isShared());
        $arguments = $container->getDefinition(DmQueryGenerator::class)->getArguments();
        foreach ($constructor->getParameters() as $index => $parameter) {
            self::assertInstanceOf(Reference::class, $arguments[$index]);
            self::assertSame($parameter->getType()->getName(), (string)$arguments[$index]);
        }
    }

    public function testSpecialQueryFormUsesPassedSettingsAndRequest(): void
    {
        $settings = ['queryTable' => '', 'queryConfig' => '', 'search_query_makeQuery' => ''];
        $request = $this->createStub(ServerRequestInterface::class);
        $generator = $this->getMockBuilder(DmQueryGenerator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['init', 'makeSelectorTable'])
            ->getMock();
        $generator->setSettings($settings);
        $generator->expects(self::once())->method('init')->with('queryConfig', '', '', $settings);
        $generator->expects(self::once())->method('makeSelectorTable')
            ->with($settings, $request)->willReturn('<select></select>');

        [$html, $query] = $generator->queryMakerDM($request);

        self::assertStringContainsString('<select></select>', $html);
        self::assertSame('', $query);
    }

    public function testRecipientQueryRespectsSavedSettingsAndLimitFlag(): void
    {
        $previousTca = $GLOBALS['TCA'] ?? null;
        $GLOBALS['TCA']['tt_address'] = [];
        try {
            foreach ([false, true] as $limitDisabled) {
                $settings = [
                    'queryTable' => 'tt_address',
                    'queryConfig' => serialize([]),
                    'queryLimit' => '25',
                    'search_query_makeQuery' => 'all',
                ];
                $request = $this->createStub(ServerRequestInterface::class);
                $generator = $this->getMockBuilder(DmQueryGenerator::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['init', 'makeSelectorTable', 'getQuery', 'getSelectQuery'])
                    ->getMock();
                $generator->setSettings($settings);
                $generator->expects(self::once())->method('init')
                    ->with('queryConfig', 'tt_address', '', $settings)
                    ->willReturnCallback(static function () use ($generator): void {
                        (new \ReflectionProperty(QuerySearchController::class, 'table'))->setValue($generator, 'tt_address');
                    });
                $generator->expects(self::once())->method('makeSelectorTable')->with($settings, $request)
                    ->willReturnCallback(static function () use ($generator): string {
                        (new \ReflectionProperty(QuerySearchController::class, 'extFieldLists'))
                            ->setValue($generator, ['queryLimit' => '25']);
                        return '';
                    });
                $generator->expects(self::once())->method('getQuery')->with([])->willReturn('filter');
                $generator->expects(self::once())->method('getSelectQuery')->with('filter')
                    ->willReturnCallback(static function () use ($generator, $limitDisabled): string {
                        $fields = (new \ReflectionProperty(QuerySearchController::class, 'extFieldLists'))->getValue($generator);
                        self::assertSame($limitDisabled ? '' : '25', $fields['queryLimit']);
                        return 'SELECT uid FROM tt_address';
                    });

                self::assertSame('SELECT uid FROM tt_address', $generator->getQueryDM($limitDisabled, $request));
            }
        } finally {
            if ($previousTca === null) {
                unset($GLOBALS['TCA']);
            } else {
                $GLOBALS['TCA'] = $previousTca;
            }
        }
    }
}
