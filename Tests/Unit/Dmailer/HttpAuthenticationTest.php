<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Tests\Unit\Dmailer;

use DirectMailTeam\DirectMail\Dmailer;
use PHPUnit\Framework\TestCase;

final class HttpAuthenticationTest extends TestCase
{
    public function testRecipientLinksAndTrackingTargetsDoNotContainFetchCredentials(): void
    {
        foreach (['', 'https://example.test/track?url='] as $prefix) {
            foreach ([false, true] as $useId) {
                foreach (['', ' no_jumpurl="1"'] as $attributes) {
                    $dmailer = $this->createDmailer(
                        '<a href="downloads/news.pdf"' . $attributes . '>Read</a>'
                    );
                    $dmailer->setJumperURLPrefix($prefix);
                    $dmailer->setJumperURLUseId($useId);
                    $dmailer->rewriteHyperLinks();

                    self::assertSame(
                        'https://example.test:8443/newsletter/downloads/news.pdf',
                        $dmailer->getPartHtmlConfig('hrefs')[0]['absRef']
                    );
                    $expectedUrl = 'https://example.test:8443/newsletter/downloads/news.pdf';
                    if ($prefix !== '' && $attributes === '') {
                        $expectedUrl = $prefix . ($useId ? '0' : str_replace('%2F', '/', rawurlencode($expectedUrl)));
                    }
                    self::assertSame(
                        '<a href="' . $expectedUrl . '"' . $attributes . '>Read</a>',
                        $dmailer->getPartHtmlConfig('content')
                    );
                }
            }
        }
    }

    public function testAbsoluteLinksKeepTheirPortPathQueryAndFragment(): void
    {
        $dmailer = $this->createDmailer(
            '<a href="https://fetch-user:p%40ss@example.test:8443/read?email=a@b.test#part">Read</a>'
        );
        $dmailer->rewriteHyperLinks();

        self::assertSame(
            '<a href="https://example.test:8443/read?email=a@b.test#part">Read</a>',
            $dmailer->getPartHtmlConfig('content')
        );
    }

    public function testFormActionsDoNotContainFetchCredentials(): void
    {
        $dmailer = $this->createDmailer('<form action="respond"></form>');
        $dmailer->rewriteHyperLinks();

        self::assertSame(
            '<form action="https://example.test:8443/newsletter/respond"></form>',
            $dmailer->getPartHtmlConfig('content')
        );
    }

    public function testOpenPicLinksDoNotContainFetchCredentials(): void
    {
        $dmailer = $this->createDmailer('<a onClick="openPic(\'preview.png\')">Preview</a>');
        $dmailer->rewriteHyperLinks();

        self::assertSame(
            '<a onClick="openPic(\'https://example.test:8443/newsletter/preview.png\')">Preview</a>',
            $dmailer->getPartHtmlConfig('content')
        );
    }

    public function testTrackingMediaDoesNotExposeCredentialsWhileEmbeddingRetainsAuthentication(): void
    {
        $dmailer = $this->createDmailer('<img src="pixel.png" dmailerping="1">');
        $dmailer->setJumperURLPrefix('https://example.test/track?url=');
        $dmailer->rewriteHyperLinks();

        $publicUrl = 'https://example.test:8443/newsletter/pixel.png';
        self::assertSame($publicUrl, $dmailer->getPartHtmlConfig('hrefs')['pixel.png']['absRef']);
        self::assertSame(
            '<img src="https://example.test/track?url=' . str_replace('%2F', '/', rawurlencode($publicUrl)) . '" dmailerping="1">',
            $dmailer->getPartHtmlConfig('content')
        );
        self::assertSame(
            'https://fetch-user:fetch-password@example.test:8443/newsletter/pixel.png',
            $dmailer->getPartHtmlConfig('media')[0]['absRef']
        );

        $dmailer = $this->createDmailer('<img src="protected.png">');
        $dmailer->extractMediaLinks();
        self::assertSame(
            'https://fetch-user:fetch-password@example.test:8443/newsletter/protected.png',
            $dmailer->getPartHtmlConfig('media')[0]['absRef']
        );
    }

    public function testUncredentialedAndMailtoLinksAreUnchanged(): void
    {
        $content = '<a href="https://example.test/read?email=a@b.test#part">Read</a>'
            . '<a href="mailto:reader@example.test">Contact</a>';
        $dmailer = $this->createDmailer($content);
        $dmailer->rewriteHyperLinks();

        self::assertSame($content, $dmailer->getPartHtmlConfig('content'));
    }

    private function createDmailer(string $content): HttpAuthenticationDmailer
    {
        $dmailer = new HttpAuthenticationDmailer();
        $dmailer->setPartHtmlConfig('path', 'https://fetch-user:fetch-password@example.test:8443/newsletter/');
        $dmailer->setPartHtmlConfig('content', $content);
        $dmailer->setPartHtmlConfig('hrefs', []);
        return $dmailer;
    }
}

final class HttpAuthenticationDmailer extends Dmailer
{
    public function rewriteHyperLinks(): void
    {
        $this->extractHyperLinks();
        $this->substHREFsInHTML();
    }
}
