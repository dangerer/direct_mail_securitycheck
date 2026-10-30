<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use GuzzleHttp\Psr7\Response;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * https://docs.guzzlephp.org/en/latest/request-options.html#verify
 */
class FetchUtility
{
    /**
     * Hosts can be restricted via $GLOBALS['TYPO3_CONF_VARS']['HTTP']['allowed_hosts']['direct_mail'] (list of host names)
     */
    protected const HTTP_CONTEXT = 'direct_mail';
    protected const TIMEOUT = 10;
    protected const CONNECT_TIMEOUT = 5;

    /**
     * @return resource|null
     */
    public function getStreamContext()
    {
        $context = null;
        $applicationContext = Environment::getContext();
        if ($applicationContext->isDevelopment()) {
            $context = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer' => Typo3ConfVarsUtility::getDMConfigSSLVerifyPeer(),
                        'verify_peer_name' => Typo3ConfVarsUtility::getDMConfigSSLVerifyPeerName(),
                    ],
                ]
            );
        }

        return $context;
    }

    public function getResponse(string $url): Response
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only http and https URLs can be fetched.', 1730300001);
        }

        // never wait forever for a remote host and do not follow redirects to other protocols
        $context = [
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']],
        ];
        $applicationContext = Environment::getContext();
        if ($applicationContext->isDevelopment()) {
            $context['verify'] = Typo3ConfVarsUtility::getDMConfigSSLVerify();
        }

        return GeneralUtility::makeInstance(RequestFactory::class)->request($url, 'GET', $context, self::HTTP_CONTEXT);
    }

    public function getContents(string $url): string
    {
        try  {
            $respose = $this->getResponse($url);
            return (string)$respose->getBody()->getContents();
        } catch(\Exception $e) {
        }
        return '';
    }
}
