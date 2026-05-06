<?php

/**
 * Проверка даты истечения регистрации домена через WHOIS с кэшем для снижения rate limit.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\Json;
use DateTimeImmutable;

/**
 * Метрика срока действия домена по данным WHOIS.
 */
final class DomainExpire extends AbstractChecker
{
    private const CACHE_OPTION = 'whois_domain_cache_json';

    private const CACHE_TTL_SEC = 86400;

    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'DOMAIN_EXPIRE';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Истечение регистрации домена (WHOIS)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultInterval(): int
    {
        return 86400;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsOptions(): array
    {
        return [
            ['domain', 'Домен (без протокола)', '', ['text', 60]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $domain = isset($settings['domain']) ? trim((string)$settings['domain']) : '';
        if ($domain === '') {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не задан домен в настройках метрики.',
            ]);
        }

        $domain = strtolower($domain);

        $cached = $this->readCache($domain);
        if ($cached !== null) {
            return $this->normalizeResult($cached);
        }

        try {
            $response = $this->queryWhois($domain);
        } catch (\Throwable $e) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => 'WHOIS ошибка: ' . $e->getMessage(),
            ]);
        }

        $expiryTs = $this->parseExpiry($domain, $response);
        if ($expiryTs === null) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['raw' => mb_substr($response, 0, 2000)], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не удалось определить дату истечения домена по ответу WHOIS.',
            ]);
        }

        $now = time();
        $daysLeft = (int)floor(($expiryTs - $now) / 86400);

        $payload = [
            'domain' => $domain,
            'expires_ts' => $expiryTs,
            'expires_iso' => gmdate('c', $expiryTs),
            'days_left' => $daysLeft,
        ];

        $this->writeCache($domain, $expiryTs);

        $status = 'OK';
        $message = 'Домен действителен. Осталось дней: ' . $daysLeft;
        if ($daysLeft < 0) {
            $status = 'ERROR';
            $message = 'Домен просрочен.';
        } elseif ($daysLeft <= 14) {
            $status = 'WARNING';
            $message = 'Скоро истечение домена. Осталось дней: ' . $daysLeft;
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }

    /**
     * @return array{VALUE: string, STATUS: string, MESSAGE: string}|null
     */
    private function readCache(string $domain): ?array
    {
        $raw = Option::get($this->getModuleId(), self::CACHE_OPTION, '{}');
        try {
            $map = Json::decode($raw);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($map) || !isset($map[$domain]) || !is_array($map[$domain])) {
            return null;
        }

        $expiryTs = (int)($map[$domain]['expires_ts'] ?? 0);
        $checkedAt = (int)($map[$domain]['checked_at'] ?? 0);
        if ($expiryTs <= 0 || $checkedAt <= 0) {
            return null;
        }

        if ((time() - $checkedAt) > self::CACHE_TTL_SEC) {
            return null;
        }

        $now = time();
        $daysLeft = (int)floor(($expiryTs - $now) / 86400);

        $payload = [
            'domain' => $domain,
            'expires_ts' => $expiryTs,
            'expires_iso' => gmdate('c', $expiryTs),
            'days_left' => $daysLeft,
            'cached' => true,
        ];

        $status = 'OK';
        $message = 'Домен действителен (кэш WHOIS). Осталось дней: ' . $daysLeft;
        if ($daysLeft < 0) {
            $status = 'ERROR';
            $message = 'Домен просрочен (кэш WHOIS).';
        } elseif ($daysLeft <= 14) {
            $status = 'WARNING';
            $message = 'Скоро истечение домена (кэш WHOIS). Осталось дней: ' . $daysLeft;
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }

    private function writeCache(string $domain, int $expiryTs): void
    {
        $raw = Option::get($this->getModuleId(), self::CACHE_OPTION, '{}');
        try {
            $map = Json::decode($raw);
        } catch (\Throwable) {
            $map = [];
        }

        if (!is_array($map)) {
            $map = [];
        }

        $map[$domain] = [
            'expires_ts' => $expiryTs,
            'checked_at' => time(),
        ];

        Option::set($this->getModuleId(), self::CACHE_OPTION, Json::encode($map, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws \RuntimeException
     */
    private function queryWhois(string $domain): string
    {
        $zone = $this->extractZone($domain);
        $server = $this->resolveWhoisServer($zone);

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($server, 43, $errno, $errstr, 3.0);
        if (!is_resource($fp)) {
            throw new \RuntimeException($errstr !== '' ? $errstr : 'FSOCKOPEN_FAILED_' . (string)$errno);
        }

        stream_set_timeout($fp, 3, 0);
        fwrite($fp, $domain . "\r\n");

        $data = '';
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === false) {
                break;
            }
            $data .= $chunk;

            $meta = stream_get_meta_data($fp);
            if (!empty($meta['timed_out'])) {
                break;
            }
        }

        fclose($fp);

        return $data;
    }

    private function extractZone(string $domain): string
    {
        $parts = explode('.', $domain);
        return strtolower((string)array_pop($parts));
    }

    private function resolveWhoisServer(string $zone): string
    {
        $map = [
            'ru' => 'whois.tcinet.ru',
            'рф' => 'whois.tcinet.ru',
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.publicinterestregistry.net',
            'su' => 'whois.tcinet.ru',
        ];

        return $map[$zone] ?? 'whois.iana.org';
    }

    private function parseExpiry(string $domain, string $text): ?int
    {
        $patterns = [
            '~paid-till:\s*(\d{4}-\d{2}-\d{2})~iu',
            '~Registry Expiry Date:\s*([^\r\n]+)~i',
            '~Expiry Date:\s*([^\r\n]+)~i',
            '~Expiration Date:\s*([^\r\n]+)~i',
            '~free-date:\s*(\d{4}-\d{2}-\d{2})~iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $raw = trim((string)$m[1]);
                if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $raw) === 1) {
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
                    if ($dt instanceof DateTimeImmutable) {
                        return $dt->getTimestamp() + 86399;
                    }
                }

                $ts = strtotime($raw);
                if ($ts !== false) {
                    return $ts;
                }
            }
        }

        return null;
    }
}
