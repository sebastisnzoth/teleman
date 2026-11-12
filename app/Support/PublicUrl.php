<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether a URL somebody typed may be fetched by the server.
 *
 * "Import from URL" fetches whatever it is given and hands back the text.
 * Left open, any signed-in user could read the server's own network through
 * it: the cloud metadata endpoint (which holds the machine's credentials),
 * an admin panel bound to localhost, the router. Only http(s) URLs whose
 * host resolves to public addresses pass.
 *
 * resolve() returns the address it checked so the caller can pin the request
 * to it — otherwise a DNS answer that changes between the check and the fetch
 * (rebinding) would walk straight past.
 */
class PublicUrl
{
    /**
     * @return array{host: string, port: int, ip: string}|null null when the URL may not be fetched
     */
    public static function resolve(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::lookUp($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return null;
            }
        }

        return [
            'host' => $host,
            'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'ip' => $addresses[0],
        ];
    }

    public static function isPublic(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16) {
            // An IPv4 address carried inside IPv6 is judged as that IPv4 address.
            $embedded = self::embeddedIpv4($packed);

            if ($embedded !== null) {
                return $embedded !== false && self::isPublic($embedded);
            }

            // Otherwise only global unicast (2000::/3) is the internet.
            if ((ord($packed[0]) & 0xE0) !== 0x20) {
                return false;
            }
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Carrier-grade NAT (100.64.0.0/10) is shared address space, not the internet.
        $long = ip2long($address);

        return $long === false || ($long & 0xFFC00000) !== (ip2long('100.64.0.0') & 0xFFC00000);
    }

    /**
     * The IPv4 address an IPv6 address stands for: null when it carries none;
     * false for a form refused outright (Teredo, local-use NAT64), because
     * what it reaches cannot be read from the address.
     */
    private static function embeddedIpv4(string $packed): string|false|null
    {
        $hex = bin2hex($packed);

        return match (true) {
            str_starts_with($hex, '00000000000000000000ffff'), // ::ffff:0:0/96, IPv4-mapped
            str_starts_with($hex, '000000000000000000000000'), // ::/96, IPv4-compatible (and :: and ::1)
            str_starts_with($hex, '0064ff9b0000000000000000') => inet_ntop(substr($packed, 12, 4)), // 64:ff9b::/96, NAT64
            str_starts_with($hex, '2002') => inet_ntop(substr($packed, 2, 4)), // 2002::/16, 6to4
            str_starts_with($hex, '0064ff9b0001'), // 64:ff9b:1::/48, local-use NAT64
            str_starts_with($hex, '20010000') => false, // 2001::/32, Teredo
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private static function lookUp(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        // A host with a private IPv4 address is refused whatever else it has,
        // so there is no need to ask for its IPv6 ones.
        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return $addresses;
            }
        }

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }
}
