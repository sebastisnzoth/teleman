<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\PricingProviderInterface;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Plivo\RestClient;

/**
 * Plivo pricing, normalised into the rows the Twilio path has always
 * written to pricing_rules.
 *
 * Read against the installed plivo/plivo-php SDK (v4.71), whose Pricing
 * resource fixes both halves of the exchange:
 *
 *  - The request. PricingInterface::get() is a single GET of
 *    Account/{auth_id}/Pricing/?country_iso=XX. Nothing lists countries or
 *    prices more than one at a time, so where Twilio pages through its own
 *    country list, this pages through ISO 3166-1 alpha-2 codes (COUNTRIES),
 *    one request per country.
 *  - The answer. One country's answer prices voice, messages and phone
 *    numbers together, so each country is asked about once per run however
 *    many service types are fetched: see $answers. Its `country_code` is
 *    the dialling code (1 for the United States); the rule's country_code
 *    is the ISO code, which is what was asked for.
 *
 * Which of a country's prices becomes its rule:
 *
 *  - call: voice.outbound.local.rate, the per-minute rate for an outbound
 *    call to the country's ordinary numbers. The answer also lists rates
 *    per dialled prefix, but nothing in the SDK says what order they come
 *    in; `local` is the rate it names. It plays the part the first
 *    outbound prefix price plays on the Twilio path.
 *  - sms: message.outbound.rate.
 *  - phone_number: phone_numbers.local.rate, a local number's monthly
 *    rental (the Twilio path keeps its first local or mobile price).
 *
 * The answer is read here rather than through the SDK's Pricing resource.
 * That resource indexes every section unconditionally — so an answer
 * missing one is a PHP warning, an ErrorException under Laravel — and
 * PricingInterface::get() hands it error responses without looking at the
 * status. Making get()'s own request through the SDK's BaseClient keeps
 * the status in view, and reading the keys the resource reads keeps a
 * country's call and SMS prices even when its number prices are missing.
 *
 * Normalising into pricing_rules as the table already is:
 *
 *  - base_cost is DECIMAL(10,4) and Plivo quotes five places, so a rate is
 *    rounded to four here — half away from zero, as MySQL would round it
 *    anyway — and what is stored no longer depends on the database.
 *  - A rate that is absent, not a number, or not above zero once rounded
 *    produces no row. A zero rule would bill that service at nothing
 *    (PricingService charges max(charge, minimum_charge), and both would
 *    be zero); with no rule, billing falls back the way it does for any
 *    unpriced country. The Twilio path stores a zero, as it always has.
 *
 * When a country cannot be fetched, nothing already fetched is thrown
 * away: a page carries the prices it did collect, and a run that has to
 * end early ends the paging the way a failed Twilio page ends its own.
 * Two failures end a run — an account Plivo refuses, and an API that
 * cannot be reached at all for UNREACHABLE_LIMIT_SECONDS — and both are
 * logged as errors, because a scheduled run's output goes nowhere and the
 * log is the only place an admin will find out why prices stopped moving.
 *
 * Not final, and the SDK client is reached through client() rather than
 * built inline, so a test can replace the transport underneath it while
 * everything here still runs for real.
 */
class PlivoPricingProvider implements PricingProviderInterface
{
    /**
     * Countries per page: Twilio's page size, so the command's progress
     * lines read the same on either provider.
     */
    private const COUNTRIES_PER_PAGE = 50;

    /**
     * How long requests may go on failing to reach Plivo at all — no answer
     * of any kind, not even an error one — before the run gives up.
     *
     * The SDK allows each request five seconds in all
     * (Plivo\BaseClient::DEFAULT_REQUEST_TIMEOUT, which cURL applies over
     * its own sixty-second connect timeout), so an unreachable Plivo costs
     * up to five seconds a country: around twenty minutes for the whole
     * list, and again for every service type that asks after it. A minute
     * is about a dozen timed-out requests — long enough to ride out a
     * hiccup, short enough that an outage ends a run instead of holding it
     * for an hour.
     *
     * Time, not a count of failures: failures that cost no time (a refused
     * connection, a DNS lookup that fails outright) are a blip, not an
     * outage, and must not end a run that is otherwise collecting prices.
     */
    private const UNREACHABLE_LIMIT_SECONDS = 60;

    /**
     * Every officially assigned ISO 3166-1 alpha-2 code — the 249 in the
     * IANA time zone database's iso3166.tab. A code Plivo does not price
     * is simply left off its page.
     *
     * @var list<string>
     */
    private const COUNTRIES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
        'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
        'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
        'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
        'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
        'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
        'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
        'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
        'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    private string $authId = '';

    private string $authToken = '';

    private ?RestClient $client = null;

    /**
     * What Plivo answered for each country — or null where it answered that
     * it has no pricing — keyed by ISO code, so fetching calls, then SMS,
     * then numbers asks about each country once rather than three times.
     *
     * A country Plivo did not answer for at all is deliberately absent
     * rather than null: one dropped connection during the call pass must
     * not cost that country its SMS and number prices too, so the next
     * service type asks again.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $answers = [];

    /**
     * When the run last had an answer of any kind from Plivo — null while
     * it is getting them. See UNREACHABLE_LIMIT_SECONDS.
     */
    private ?CarbonInterface $unreachableSince = null;

    /**
     * Whether Plivo has priced any country at all for this account during
     * the run — which is what tells a refused account apart from a refusal
     * about one country.
     */
    private bool $priced = false;

    public function initialize(array $credentials): static
    {
        $this->authId = (string) ($credentials['auth_id'] ?? '');
        $this->authToken = (string) ($credentials['auth_token'] ?? '');

        // Credentials just changed: neither the client built from the
        // previous set nor anything it fetched may be reused.
        $this->client = null;
        $this->answers = [];
        $this->unreachableSince = null;
        $this->priced = false;

        return $this;
    }

    public function fetchPricing(string $serviceType, ?string $page = null): array
    {
        [$rate, $unit] = match ($serviceType) {
            'call' => ['voice.outbound.local.rate', 'per_minute'],
            'sms' => ['message.outbound.rate', 'per_sms'],
            'phone_number' => ['phone_numbers.local.rate', 'per_month'],
        };

        $offset = (int) $page;
        $pricing = [];

        foreach (array_slice(self::COUNTRIES, $offset, self::COUNTRIES_PER_PAGE) as $iso) {
            if (! array_key_exists($iso, $this->answers) && ! $this->ask($iso, $serviceType)) {
                // Plivo cannot be asked anything more this run. What this
                // page collected before that is kept, and the paging ends
                // here — the way a failed page ends the Twilio path's,
                // without discarding the pages before it.
                return ['pricing' => $pricing, 'next_page' => null];
            }

            $answer = $this->answers[$iso] ?? null;

            if ($answer === null) {
                continue;
            }

            $quoted = data_get($answer, $rate);
            $baseCost = $this->baseCost($quoted);

            if ($baseCost === null) {
                // Worth a line: this country will be billed at whatever
                // rule billing falls back to, and the owner is the only one
                // who can decide whether that is acceptable.
                Log::warning('Plivo quoted no usable rate; no pricing rule written', [
                    'service_type' => $serviceType,
                    'country' => $iso,
                    'rate' => $quoted,
                ]);

                continue;
            }

            $pricing[] = [
                'country_code' => $iso,
                'country_name' => is_string($answer['country'] ?? null) ? $answer['country'] : null,
                'base_cost' => $baseCost,
                'base_cost_unit' => $unit,
            ];
        }

        $next = $offset + self::COUNTRIES_PER_PAGE;

        return [
            'pricing' => $pricing,
            'next_page' => $next < count(self::COUNTRIES) ? (string) $next : null,
        ];
    }

    public function getName(): string
    {
        return 'plivo';
    }

    // ─── Private Helpers ─────────────────────────

    /**
     * Ask Plivo for one country's prices, keeping its answer. False when
     * the run must end here.
     *
     * An answer of any kind — including one that says this country has no
     * pricing — is kept, and the country is not asked about again this
     * run. A request that never reached Plivo is not an answer: that
     * country is simply left off this page, and the next service type asks
     * again, unless nothing has reached Plivo for UNREACHABLE_LIMIT_SECONDS.
     *
     * An account Plivo refuses ends the run too: every other country would
     * be refused in exactly the same way.
     */
    private function ask(string $iso, string $serviceType): bool
    {
        $client = $this->client();
        $askedAt = now();

        try {
            // The request Plivo\Resources\Pricing\PricingInterface::get() makes.
            $response = $client->client->fetch(
                'Account/'.$client->client->getAuthId().'/Pricing/',
                ['country_iso' => $iso]
            );
        } catch (Exception $e) {
            $this->unreachableSince ??= $askedAt;
            $unreachableFor = $this->unreachableSince->diffInSeconds(now());

            if ($unreachableFor >= self::UNREACHABLE_LIMIT_SECONDS) {
                Log::error('Plivo pricing API unreachable; pricing sync stopped early', [
                    'service_type' => $serviceType,
                    'country' => $iso,
                    'unreachable_for_seconds' => (int) $unreachableFor,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            Log::warning('Failed to fetch Plivo pricing for country', [
                'country' => $iso,
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        $this->unreachableSince = null;

        $status = (int) $response->getStatusCode();
        $answer = $response->getContent();

        if ($status === 401 || $status === 403) {
            $error = is_array($answer) ? ($answer['error'] ?? null) : null;

            // Once Plivo has priced a country for this account, the account
            // plainly works, so a refusal about another country is about
            // that country — a market this account may not price — and
            // costs only that one.
            if ($this->priced) {
                Log::error('Plivo refused pricing for country', [
                    'country' => $iso,
                    'status' => $status,
                    'error' => $error,
                ]);
                $this->answers[$iso] = null;

                return true;
            }

            Log::error('Plivo rejected the account credentials; pricing sync stopped', [
                'service_type' => $serviceType,
                'country' => $iso,
                'status' => $status,
                'error' => $error,
            ]);

            return false;
        }

        if (! $response->ok() || ! is_array($answer)) {
            Log::info('No Plivo pricing for country', [
                'country' => $iso,
                'status' => $status,
            ]);
            $this->answers[$iso] = null;

            return true;
        }

        $this->answers[$iso] = $answer;
        $this->priced = true;

        return true;
    }

    /**
     * A quoted rate as a base cost pricing_rules can hold, or null when it
     * amounts to no price at all.
     */
    private function baseCost(mixed $rate): ?float
    {
        if (! is_numeric($rate)) {
            return null;
        }

        $baseCost = round((float) $rate, 4);

        return $baseCost > 0 ? $baseCost : null;
    }

    /**
     * Refuses to build a client without credentials of its own. The Plivo
     * SDK does not: handed an empty auth id or token, its BasicAuth quietly
     * falls back to PLIVO_AUTH_ID and PLIVO_AUTH_TOKEN in the process
     * environment, so an unconfigured caller would act on whatever account
     * that names. PricingProviderFactory already refuses to hand out a
     * provider without an account; this keeps every other way in shut too.
     */
    protected function client(): RestClient
    {
        if ($this->authId === '' || $this->authToken === '') {
            throw new Exception('Plivo credentials not configured. Please configure the Plivo account in Settings → Telephony.');
        }

        return $this->client ??= new RestClient($this->authId, $this->authToken);
    }
}
