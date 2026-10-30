<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Tests\Unit\Module;

use DirectMailTeam\DirectMail\Module\DmailController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DmailControllerTest extends TestCase
{
    public function testHandleRequestStoresTheCurrentRequestBeforeInitializingTheModule(): void
    {
        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = $this->createStub(BackendUserAuthentication::class);
        $controller = (new \ReflectionClass(DmailController::class))->newInstanceWithoutConstructor();
        $requestProperty = new \ReflectionProperty(DmailController::class, 'request');

        try {
            foreach ([1, 2] as $requestNumber) {
                $request = $this->createStub(ServerRequestInterface::class);
                $stopInitialization = new \RuntimeException('Stop before loading backend services');
                $factory = $this->createStub(LanguageServiceFactory::class);
                $factory->method('createFromUserPreferences')->willThrowException($stopInitialization);
                GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);

                try {
                    $controller->handleRequest($request);
                    self::fail('Module initialization should have been interrupted.');
                } catch (\RuntimeException $exception) {
                    self::assertSame($stopInitialization, $exception);
                }

                self::assertSame(
                    $request,
                    $requestProperty->getValue($controller),
                    'Request ' . $requestNumber . ' must be available to recipient-query helpers.'
                );
            }
        } finally {
            if ($previousBackendUser === null) {
                unset($GLOBALS['BE_USER']);
            } else {
                $GLOBALS['BE_USER'] = $previousBackendUser;
            }
        }
    }
}
