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
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            return self::isPublicIpv4($ip, $packed);
        }

        // IPv4 encapsulée dans de l'IPv6 (préfixe ::ffff:0:0/96, ex. ::ffff:127.0.0.1) :
        // on classe l'IPv4 embarquée plutôt que l'adresse IPv6 elle-même.
        if (substr($packed, 0, 10) === str_repeat("\x00", 10) && substr($packed, 10, 2) === "\xff\xff") {
            $embedded = inet_ntop(substr($packed, 12, 4));
            return $embedded !== false && self::isPublicIp($embedded);
        }

        // Toute autre adresse IPv6 : seule la plage d'unicast global (2000::/3)
        // est autorisée (exclut notamment ULA fc00::/7, lien-local fe80::/10,
        // multicast ff00::/8, le NAT64 64:ff9b::/96 et les adresses « fantaisistes »
        // comme ::7f00:1 qui ne sont pas des adresses mappées IPv4 standard).
        if ((ord($packed[0]) & 0xe0) !== 0x20) {
            return false;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }

    private static function isPublicIpv4(string $ip, string $packed): bool
    {
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) === false) {
            return false;
        }
        // filter_var() laisse passer 224.0.0.0/4 (multicast) et 240.0.0.0/4
        // (réservé) : on les exclut explicitement.
        return ord($packed[0]) < 224;
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
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        // curl punycode-convertit les hôtes IDN (ex. bär.example.com → xn--br-via...)
        // et ferait alors sa propre résolution DNS sur ce nom converti, contournant
        // le pin sur l'IP vérifiée : on refuse tout hôte non-IP qui ne soit pas de
        // l'ASCII pur (lettres/chiffres/points/tirets).
        if (!$isIp && preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            throw new InvalidArgumentException('Nom de domaine invalide.');
        }
        $ips = $isIp ? [$host] : ($this->resolver)($host);
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
