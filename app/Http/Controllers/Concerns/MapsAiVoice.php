<?php

namespace App\Http\Controllers\Concerns;

trait MapsAiVoice
{
    /**
     * Map a short voice name to the full Twilio Polly/Google voice string.
     *
     * Default = Neural voices (not Generative). Neural voices are 2-3x
     * faster to synthesize (~300-600ms) vs Generative (~1.5-3s). Still
     * sound natural enough for phone calls. Generative variants remain
     * available via an explicit "-generative" suffix.
     */
    protected function mapVoiceForTwilio(string $voice): string
    {
        $voiceMap = [
            // Fast Neural voices (default) — ~300-600ms synthesis
            'joanna'   => 'Polly.Joanna-Neural',   // Female, US English, warm & natural
            'matthew'  => 'Polly.Matthew-Neural',  // Male, US English, confident & clear
            'ruth'     => 'Polly.Ruth-Neural',     // Female, US English, professional
            'stephen'  => 'Polly.Stephen-Neural',  // Male, US English, authoritative
            'kevin'    => 'Polly.Kevin-Neural',    // Male, US English, youthful
            'salli'    => 'Polly.Salli-Neural',    // Female, US English, friendly

            // Generative voices — higher quality but slower. Opt-in via "<name>-generative".
            'joanna-generative'  => 'Polly.Joanna-Generative',
            'matthew-generative' => 'Polly.Matthew-Generative',
            'ruth-generative'    => 'Polly.Ruth-Generative',
            'stephen-generative' => 'Polly.Stephen-Generative',

            // Google Chirp3-HD voices — mid-latency (~500-900ms), very expressive
            'aoede'    => 'Google.en-US-Chirp3-HD-Aoede',
            'charon'   => 'Google.en-US-Chirp3-HD-Charon',
            'kore'     => 'Google.en-US-Chirp3-HD-Kore',
            'fenrir'   => 'Google.en-US-Chirp3-HD-Fenrir',
            'puck'     => 'Google.en-US-Chirp3-HD-Puck',
            'leda'     => 'Google.en-US-Chirp3-HD-Leda',
        ];

        return $voiceMap[$voice] ?? 'Polly.Joanna-Neural';
    }
}
