<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class StallionRates
{
    public function __construct(?string $base = null, ?string $token = null, int $ttlSeconds = 1800)
    {
        // assign ONCE (no readonly reassign issue)
        $this->base       = rtrim(($base ?? (string) config('services.stallion.base')), '/');
        $this->token      = (string) ($token ?? config('services.stallion.token'));
        $this->ttlSeconds = $ttlSeconds;
    }


    /**
     * Get shipping rates for a destination and package.
     *
     * @param array $to   ['city','province_code','postal_code','country_code','name?','address1?','email?','phone?','is_residential?']
     * @param array $pkg  ['weight','weight_unit','length?','width?','height?','size_unit?','package_type?']
     * @param array $opts ['postage_types?'=>[], 'signature_confirmation?'=>bool, 'insured?'=>bool, 'region?'=>string, 'no_cache?'=>bool]
     * @return array      Array of rates as returned by Stallion (you can map/format in your UI)
     * @throws \RuntimeException on failure
     */
    public function quote(array $to, array $pkg, array $opts = []): array
    {
        $payload = $this->buildPayload($to, $pkg, $opts);
        $cacheKey = $this->cacheKey($payload);

        if (empty($opts['no_cache'])) {
            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }
        }

        try {
            $resp = Http::withToken($this->token)
                ->acceptJson()
                ->post($this->base . '/rates', $payload);

            $resp->throw();

            $rates = $resp->json('rates', []);

            if (empty($opts['no_cache'])) {
                Cache::put($cacheKey, $rates, $this->ttlSeconds);
            }

            return $rates;
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? $e->response?->body();
            throw new \RuntimeException('Stallion rates error: ' . json_encode($body, JSON_UNESCAPED_SLASHES), previous: $e);
        }
    }

private function buildPayload(array $to, array $pkg, array $opts): array
{
    foreach (['city','province_code','postal_code','country_code'] as $k) {
        if (empty($to[$k])) throw new \InvalidArgumentException("Missing to_address.$k");
    }
    foreach (['weight','weight_unit'] as $k) {
        if (!isset($pkg[$k])) throw new \InvalidArgumentException("Missing package $k");
    }

    $payload = [
        'to_address' => [
            'name'           => $to['name'] ?? 'Recipient',
            'address1'       => $to['address1'] ?? 'N/A',
            'city'           => $to['city'],
            'province_code'  => $to['province_code'],
            'postal_code'    => $to['postal_code'],
            'country_code'   => $to['country_code'], // 'US' or 'CA'
            'is_residential' => $to['is_residential'] ?? true,
            'email'          => $to['email'] ?? null,
            'phone'          => $to['phone'] ?? null,
        ],
        'weight_unit'  => $pkg['weight_unit'],             // 'kg','g','oz','lbs'
        'weight'       => (float) $pkg['weight'],
        'length'       => (float) ($pkg['length'] ?? 0),
        'width'        => (float) ($pkg['width']  ?? 0),
        'height'       => (float) ($pkg['height'] ?? 0),
        'size_unit'    => $pkg['size_unit']    ?? 'cm',    // 'cm' or 'in'
        'package_type' => $pkg['package_type'] ?? 'Parcel',
        'postage_types'          => $opts['postage_types']          ?? [],
        'signature_confirmation' => (bool) ($opts['signature_confirmation'] ?? false),
        'insured'                => (bool) ($opts['insured'] ?? false),
        'region'                 => $opts['region'] ?? null,        // e.g. 'ON','BC','QC'
    ];

    // EITHER send items...
    if (!empty($opts['items'])) {
        // normalize minimal item shape that Stallion accepts
        $payload['items'] = array_map(function ($i) {
            return [
                'description' => (string) ($i['description'] ?? 'Item'),
                'sku'         => (string) ($i['sku'] ?? ''),
                'quantity'    => (int)    ($i['quantity'] ?? 1),
                'value'       => (float)  ($i['value'] ?? 0),
                'currency'    => (string) ($i['currency'] ?? 'CAD'),
                // optional customs fields:
                'country_of_origin' => $i['country_of_origin'] ?? null,
                'hs_code'           => $i['hs_code'] ?? null,
                'manufacturer_name' => $i['manufacturer_name'] ?? null,
                'manufacturer_address1' => $i['manufacturer_address1'] ?? null,
                'manufacturer_city'     => $i['manufacturer_city'] ?? null,
                'manufacturer_province_code' => $i['manufacturer_province_code'] ?? null,
                'manufacturer_postal_code'   => $i['manufacturer_postal_code'] ?? null,
                'manufacturer_country_code'  => $i['manufacturer_country_code'] ?? null,
            ];
        }, $opts['items']);
    } else {
        // ...OR send package_contents + value (REQUIRED when no items/customs lines)
        $payload['package_contents'] = $opts['package_contents'] ?? 'Merchandise';
        // value is REQUIRED; pick from opts, or throw a friendly error
        if (!isset($opts['value'])) {
            throw new \InvalidArgumentException(
                'Rates request needs either items[] or a declared value (set $opts["value"]) when no items are provided.'
            );
        }
        $payload['value']    = (float) $opts['value'];
        $payload['currency'] = $opts['currency']
            ?? ($to['country_code'] === 'US' ? 'USD' : 'CAD');
    }

    // Optional: pass tax identifiers if you have them (IOSS, etc.)
    if (!empty($opts['tax_identifier'])) {
        $payload['tax_identifier'] = $opts['tax_identifier'];
    }

    // Optional: include return_address if you want to be explicit
    if (!empty($opts['return_address'])) {
        $payload['return_address'] = $opts['return_address'];
    }

    return $payload;
}


    private function cacheKey(array $payload): string
    {
        // Only the parts that affect pricing; strip noisy fields
        $keyParts = [
            $payload['to_address']['country_code'],
            $payload['to_address']['postal_code'],
            $payload['to_address']['city'],
            $payload['to_address']['province_code'],
            $payload['weight_unit'],
            $payload['weight'],
            $payload['length'],
            $payload['width'],
            $payload['height'],
            $payload['size_unit'],
            $payload['package_type'],
            implode(',', $payload['postage_types'] ?? []),
            (int)($payload['signature_confirmation'] ?? 0),
            (int)($payload['insured'] ?? 0),
            $payload['region'] ?? '',
        ];

        return 'stallion:rates:' . md5(implode('|', $keyParts));
    }
}
