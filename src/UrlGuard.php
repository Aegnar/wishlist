<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/** Protège le téléchargement d'images contre les requêtes vers le réseau interne (SSRF). */
final class UrlGuard
{
    private const ALLOWED_PORTS = [80, 443];

    /** @var callable(string): list<string> */
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            return $ips;
        };
    }

    public static function isPublicIp(string $ip): bool
    {
        // IPv4 encapsulée dans de l'IPv6 (::ffff:10.0.0.1) : on vérifie l'IPv4.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m) === 1) {
            $ip = $m[1];
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }

    /** @return array{url: string, scheme: string, host: string, port: int, ip: string} */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException("URL d'image invalide (http ou https uniquement).");
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL avec identifiants refusée.');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, self::ALLOWED_PORTS, true)) {
            throw new InvalidArgumentException('Port non autorisé (80 ou 443 uniquement).');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        if ($ips === []) {
            throw new InvalidArgumentException('Nom de domaine introuvable.');
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                throw new InvalidArgumentException('Adresse non autorisée (réseau privé ou local).');
            }
        }
        return ['url' => $url, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }
}
