import { Button } from '@/components/ui/button';
import { Mic, MicOff, Pause, Play, PhoneOff } from 'lucide-react';

export interface CallControlsProps {
  isMuted: boolean;
  isOnHold: boolean;
  onMute: () => void;
  onHold: () => void;
  onEndCall: () => void;
  callState?: string;
  disabled?: boolean;
  /**
   * Whether to offer hold at all. Holding the remote party is not
   * something every telephony provider this product can be installed
   * against is able to do, and where it cannot, the button is left out
   * rather than rendered disabled: this is a white-label product, so the
   * only honest explanation for a greyed-out control would name the
   * provider brand the buyer chose and the customer was never told
   * about. A control that is simply not there asks no question.
   *
   * Defaults to true, so every caller that does not know about the
   * provider lock — the embeddable widget build, which mounts outside
   * Inertia and so cannot read the shared prop — keeps the exact
   * behaviour it has always had.
   */
  canHold?: boolean;
}

export function CallControls({
  isMuted,
  isOnHold,
  onMute,
  onHold,
  onEndCall,
  callState,
  disabled = false,
  canHold = true,
}: CallControlsProps) {
  const isConnected = callState === 'connected';

  return (
    <div className="flex items-center justify-center gap-4">
      {/* Mute/Unmute */}
      <Button
        type="button"
        variant={isMuted ? 'default' : 'outline'}
        size="lg"
        disabled={disabled || !isConnected}
        onClick={onMute}
        className="h-14 w-14 rounded-full"
        title={isMuted ? 'Unmute' : 'Mute'}
      >
        {isMuted ? (
          <MicOff className="h-5 w-5" />
        ) : (
          <Mic className="h-5 w-5" />
        )}
      </Button>

      {/* Hold/Resume */}
      {canHold && (
        <Button
          type="button"
          variant={isOnHold ? 'default' : 'outline'}
          size="lg"
          disabled={disabled || !isConnected}
          onClick={onHold}
          className="h-14 w-14 rounded-full"
          title={isOnHold ? 'Resume' : 'Hold'}
        >
          {isOnHold ? (
            <Play className="h-5 w-5" />
          ) : (
            <Pause className="h-5 w-5" />
          )}
        </Button>
      )}

      {/* End Call */}
      <Button
        type="button"
        variant="destructive"
        size="lg"
        disabled={disabled}
        onClick={onEndCall}
        className="h-14 w-14 rounded-full"
        title="End Call"
      >
        <PhoneOff className="h-5 w-5" />
      </Button>
    </div>
  );
}
