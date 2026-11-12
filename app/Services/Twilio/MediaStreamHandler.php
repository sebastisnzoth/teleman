<?php

namespace App\Services\Twilio;

use App\Models\AiAgent;
use App\Models\AiAgentCall;
use App\Services\AiAgent\CallStateManager;
use App\Services\AiAgent\DeepgramService;
use Exception;
use Illuminate\Support\Facades\Log;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class MediaStreamHandler implements MessageComponentInterface
{
    private array $connections = [];
    private array $callStates = [];
    private CallStateManager $stateManager;
    private DeepgramService $deepgram;

    public function __construct(
        CallStateManager $stateManager,
        DeepgramService $deepgram
    ) {
        $this->stateManager = $stateManager;
        $this->deepgram = $deepgram;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->connections[$conn->resourceId] = $conn;
        Log::info('New WebSocket connection', ['id' => $conn->resourceId]);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data) {
                Log::warning('Invalid JSON received', ['msg' => $msg]);
                return;
            }

            $event = $data['event'] ?? null;
            
            switch ($event) {
                case 'start':
                    $this->handleStart($from, $data);
                    break;
                    
                case 'media':
                    $this->handleMedia($from, $data);
                    break;
                    
                case 'stop':
                    $this->handleStop($from, $data);
                    break;
                    
                default:
                    Log::debug('Unknown event', ['event' => $event]);
            }
        } catch (Exception $e) {
            Log::error('Error processing message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleStart(ConnectionInterface $conn, array $data)
    {
        $streamSid = $data['streamSid'] ?? null;
        $callSid = $data['start']['callSid'] ?? null;
        
        if (!$callSid) {
            Log::error('No call SID in start event');
            return;
        }

        Log::info('Media stream started', [
            'call_sid' => $callSid,
            'stream_sid' => $streamSid,
        ]);

        // Initialize call state
        $this->callStates[$conn->resourceId] = [
            'call_sid' => $callSid,
            'stream_sid' => $streamSid,
            'audio_buffer' => '',
            'transcript_buffer' => '',
            'is_speaking' => false,
        ];

        // Find or create AI agent call
        $customParameters = $data['start']['customParameters'] ?? [];
        $agentId = $customParameters['ai_agent_id'] ?? null;

        if ($agentId) {
            $agent = AiAgent::find($agentId);
            if ($agent) {
                $call = $this->stateManager->initializeCall(
                    $agent,
                    $callSid,
                    'inbound',
                    $data['start']['from'] ?? '',
                    $data['start']['to'] ?? ''
                );

                $this->callStates[$conn->resourceId]['call'] = $call;
                $this->callStates[$conn->resourceId]['agent'] = $agent;

                // Handle greeting (text-only, no TTS audio in WebSocket path)
                $greeting = $this->stateManager->handleCallAnswered($call);
                if ($greeting && $greeting['text']) {
                    Log::info('AI agent greeting ready', [
                        'call_id' => $call->id,
                        'text' => substr($greeting['text'], 0, 50),
                    ]);
                }
            }
        }
    }

    private function handleMedia(ConnectionInterface $conn, array $data)
    {
        $state = &$this->callStates[$conn->resourceId] ?? null;
        
        if (!$state || !isset($state['call'])) {
            return;
        }

        // Get audio payload
        $payload = $data['media']['payload'] ?? null;
        if (!$payload) {
            return;
        }

        // Decode audio (μ-law base64)
        $audioData = base64_decode($payload);
        
        // Buffer audio for transcription
        $state['audio_buffer'] .= $audioData;

        // When we have enough audio (~1 second), transcribe it
        if (strlen($state['audio_buffer']) > 8000) { // ~1 second of μ-law audio
            $this->transcribeAndRespond($conn, $state);
            $state['audio_buffer'] = '';
        }
    }

    private function handleStop(ConnectionInterface $conn, array $data)
    {
        $state = $this->callStates[$conn->resourceId] ?? null;
        
        if ($state && isset($state['call'])) {
            $this->stateManager->handleCallCompleted(
                $state['call'],
                'completed',
                null
            );
        }

        Log::info('Media stream stopped', [
            'call_sid' => $state['call_sid'] ?? 'unknown',
        ]);

        unset($this->callStates[$conn->resourceId]);
    }

    private function transcribeAndRespond(ConnectionInterface $conn, array &$state)
    {
        try {
            $audioBuffer = $state['audio_buffer'];
            $call = $state['call'];
            $agent = $state['agent'];
            $streamSid = $state['stream_sid'];

            Log::debug('Processing audio buffer', [
                'size' => strlen($audioBuffer),
                'call_id' => $call->id,
            ]);

            // Convert audio to WAV format for Deepgram (μ-law to PCM)
            $wavAudio = $this->convertMulawToWav($audioBuffer);

            // Create temporary file for Deepgram processing
            $tempFile = tempnam(sys_get_temp_dir(), 'audio_');
            file_put_contents($tempFile, $wavAudio);

            try {
                // Transcribe audio using Deepgram
                $transcription = $this->deepgram->transcribeFile("file://{$tempFile}");

                if ($transcription && !empty($transcription['transcript'])) {
                    $transcript = trim($transcription['transcript']);
                    $confidence = $transcription['confidence'] ?? 1.0;

                    Log::info('Transcription complete', [
                        'call_id' => $call->id,
                        'transcript' => $transcript,
                        'confidence' => $confidence,
                    ]);

                    // Process user input and get AI response
                    $response = $this->stateManager->processUserInput($call, $transcript, $confidence);

                    if ($response) {
                        // Handle different actions
                        switch ($response['action']) {
                            case 'end':
                                $this->sendMark($conn, $streamSid, 'end_call');
                                $this->stateManager->handleCallCompleted($call, 'user_ended');
                                break;

                            case 'transfer':
                                $this->sendTransfer($conn, $streamSid, $response['transfer_to']);
                                break;

                            case 'continue':
                            default:
                                Log::debug('AI response generated', [
                                    'call_id' => $call->id,
                                    'text' => substr($response['text'] ?? '', 0, 80),
                                ]);
                                break;
                        }

                        // Check if call should end due to limits
                        if ($this->stateManager->shouldEndCall($call)) {
                            $this->sendMark($conn, $streamSid, 'end_call');
                            $this->stateManager->handleCallCompleted($call, 'time_limit');
                        }
                    }
                }
            } finally {
                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (Exception $e) {
            Log::error('Error in transcribeAndRespond', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Convert μ-law audio to WAV format for Deepgram
     */
    private function convertMulawToWav(string $mulawData): string
    {
        // μ-law decoding lookup table
        $mulawToLinear = function ($ulaw) {
            $ulaw = ~$ulaw;
            $sign = ($ulaw & 0x80);
            $exponent = ($ulaw >> 4) & 0x07;
            $mantissa = $ulaw & 0x0F;
            $sample = (33 + (2 * $mantissa)) << ($exponent + 2);
            if ($sign != 0) $sample = -$sample;
            return $sample;
        };

        // Decode μ-law to PCM
        $pcmData = '';
        for ($i = 0; $i < strlen($mulawData); $i++) {
            $sample = $mulawToLinear(ord($mulawData[$i]));
            $pcmData .= pack('s', $sample); // 16-bit signed PCM
        }

        // Build WAV header
        $numSamples = strlen($pcmData) / 2;
        $sampleRate = 8000;
        $numChannels = 1;
        $bitsPerSample = 16;

        $header = pack('a4', 'RIFF');
        $header .= pack('V', 36 + strlen($pcmData));
        $header .= pack('a4', 'WAVE');
        $header .= pack('a4', 'fmt ');
        $header .= pack('V', 16); // Subchunk size
        $header .= pack('v', 1); // Audio format (PCM)
        $header .= pack('v', $numChannels);
        $header .= pack('V', $sampleRate);
        $header .= pack('V', $sampleRate * $numChannels * $bitsPerSample / 8);
        $header .= pack('v', $numChannels * $bitsPerSample / 8);
        $header .= pack('v', $bitsPerSample);
        $header .= pack('a4', 'data');
        $header .= pack('V', strlen($pcmData));

        return $header . $pcmData;
    }

    /**
     * Send mark event to Twilio
     */
    private function sendMark(ConnectionInterface $conn, string $streamSid, string $name): void
    {
        $message = json_encode([
            'event' => 'mark',
            'streamSid' => $streamSid,
            'mark' => [
                'name' => $name,
            ],
        ]);

        $conn->send($message);
    }

    /**
     * Send transfer command
     */
    private function sendTransfer(ConnectionInterface $conn, string $streamSid, string $transferTo): void
    {
        Log::info('Transfer requested', [
            'stream_sid' => $streamSid,
            'transfer_to' => $transferTo,
        ]);

        // Twilio media streams don't directly support transfer
        // You'd need to use TwiML redirect or Twilio API
        $this->sendMark($conn, $streamSid, 'transfer_initiated');
    }

    private function sendAudio(ConnectionInterface $conn, string $audioBase64, string $streamSid)
    {
        // Send audio to Twilio
        $message = json_encode([
            'event' => 'media',
            'streamSid' => $streamSid,
            'media' => [
                'payload' => $audioBase64,
            ],
        ]);

        $conn->send($message);
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->connections[$conn->resourceId]);
        
        if (isset($this->callStates[$conn->resourceId])) {
            $state = $this->callStates[$conn->resourceId];
            
            if (isset($state['call'])) {
                $this->stateManager->handleCallCompleted(
                    $state['call'],
                    'disconnected'
                );
            }
            
            unset($this->callStates[$conn->resourceId]);
        }

        Log::info('WebSocket connection closed', ['id' => $conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        Log::error('WebSocket error', [
            'id' => $conn->resourceId,
            'error' => $e->getMessage(),
        ]);

        $conn->close();
    }
}
