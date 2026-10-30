<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AuthCodeUtility
{
    /**
     * Additional secret for HashService::hmac(). The actual key material is the TYPO3 encryptionKey,
     * which HashService mixes in itself. The value must not change, otherwise the auth codes
     * in already sent mails would become invalid.
     */
    protected const HMAC_ADDITIONAL_SECRET = 'changeMe';

    /**
     * check if the supplied auth code is identical with the counted authCode
     *
     * @param string $submittedAuthCode
     * @param array $recipientRecord
     * @param string $authcodeFieldList
     * @return bool
     */
    public static function validateAuthCode(
        string $submittedAuthCode,
        array $recipientRecord,
        string $authcodeFieldList = 'uid'): bool
    {
        if (!empty($submittedAuthCode)) {
            $hmac = self::getHmac($recipientRecord, $authcodeFieldList);
            if (hash_equals($hmac, $submittedAuthCode)) {
                return true;
            }
        }
        return false;
    }

    public static function getHmac(array $recipientRecord, string $authcodeFieldList): string
    {
        $recCopy_temp = [];
        if ($authcodeFieldList) {
            $fieldArr = GeneralUtility::trimExplode(',', $authcodeFieldList, true);
            foreach ($fieldArr as $k => $v) {
                $recCopy_temp[$k] = $recipientRecord[$v] ?? '';
            }
        } else {
            $recCopy_temp = $recipientRecord;
        }
        $preKey = implode('|', $recCopy_temp);

        return GeneralUtility::makeInstance(HashService::class)->hmac($preKey, self::HMAC_ADDITIONAL_SECRET);
    }
}
