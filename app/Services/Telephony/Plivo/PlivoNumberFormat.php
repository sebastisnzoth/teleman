<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

/**
 * The one place a Plivo phone number is converted between this app's own
 * E.164-with-leading-plus convention and Plivo's own bare-digit one.
 *
 * Extracted from PhoneNumberService's private toE164()/toPlivoFormat()
 * (added when phone-number provisioning was routed through the provider
 * abstraction — see that class's own docblocks on those two methods),
 * which cannot be reused directly from here because they are private
 * instance methods on an unrelated service. Duplicating their one-line
 * bodies again for a second, independent consumer is exactly the
 * "fixture/logic written from the same wrong assumption twice" pattern
 * this codebase has already been bitten by more than once, so the second
 * consumer is a shared, standalone class instead of a second copy.
 *
 * Both directions matter and neither is safe to build from the other.
 * This is specifically about the phone-number resource (Account/{id}/
 * Number/{number}/, Account/{id}/PhoneNumber/{country}/): the number is
 * itself a path segment there, and Plivo hands it back and expects it
 * bare (no leading '+') — everything else in this application (the
 * `number` column, the admin's own validation, the webhook lookups keyed
 * against that column) assumes the '+', so an identifier read straight
 * off one of those endpoints and matched against a stored number without
 * going through toE164() first will never match.
 *
 * The **Call resource** (Account/{id}/Call/) needs toPlivoFormat() too, on
 * BOTH its numbers, even though neither is a path segment there. The
 * requirement is the API's own, stated in the installed SDK's
 * CallInterface::create() docblock: the caller id is given "with the country
 * code ... could be, 15677654321", and the destination's "Regular number
 * must be prefixed with country code but without the + sign".
 * PlivoVoiceProvider::makeCall() converts both — without that, no outbound
 * Plivo call placed by this app reaches the API in a form it accepts. Note
 * the destination may instead be a SIP endpoint
 * ("sip:john1234@phone.plivo.com"), which has no leading '+' and so passes
 * through untouched; that is pinned by a test rather than assumed.
 *
 * The call **XML** boundary is a different one again and does NOT go through
 * this: <Dial><Number> takes the '+' form in Plivo's own examples, and
 * PlivoXmlGenerator deliberately leaves it alone.
 *
 * The messaging resource (Account/{id}/Message/) is a different case and
 * does NOT need toPlivoFormat(): its URI is fixed, src/dst travel as body
 * fields rather than a path segment, and the installed SDK's own
 * MessageInterface::create() docblock documents no bare-digit
 * requirement for them — PlivoSMSProvider::send() passes this app's own
 * '+'-prefixed form straight through deliberately, unconverted. The
 * inbound-SMS *webhook* Plivo posts back to this app is the part of
 * messaging that does arrive bare (confirmed against a real payload,
 * unlike src/dst) — see PlivoWebhookNormalizer::normalizeIncomingSms(),
 * the other consumer of toE164() below.
 */
final class PlivoNumberFormat
{
    /**
     * Add the leading '+' Plivo's own API never sends — to a bare digit
     * string, and to nothing else.
     *
     * The webhook fields this is applied to do not always hold a phone
     * number: a call placed from a browser softphone arrives From the
     * Endpoint's SIP URI, and a withheld caller ID arrives as a word. Prefixing
     * those would manufacture "+sip:..." or "+anonymous". An already-prefixed
     * number and an empty string are likewise returned unchanged — the empty
     * string so a genuinely missing value stays recognisable as missing.
     */
    public static function toE164(string $number): string
    {
        if (! ctype_digit($number)) {
            return $number;
        }

        return '+'.$number;
    }

    /**
     * Strip the leading '+' before the number is used as a path segment
     * on Plivo's phone-number resource (buy/get/update/delete a number) —
     * see the class docblock for why the messaging resource's src/dst
     * fields do NOT go through this. A no-op on a value that never had
     * one.
     */
    public static function toPlivoFormat(string $number): string
    {
        return ltrim($number, '+');
    }
}
