import { useWidgetSoftphone } from '@/contexts/WidgetSoftphoneContext';
import { PhoneInput } from '@/components/calls/phone-input';
import { DialPad } from '@/components/calls/dial-pad';
import { CallControls } from '@/components/calls/call-controls';
import { CallTimer } from '@/components/calls/call-timer';
import { CallStatus } from '@/components/calls/call-status';
import { WidgetNumberSwitcher } from '@/components/softphone/WidgetNumberSwitcher';
import { WidgetIncomingCallAlert } from '@/components/softphone/WidgetIncomingCallAlert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Phone, AlertCircle, Loader2 } from 'lucide-react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { isHidden } from '@/lib/telephony';

export function WidgetExpandedWidget() {
  const {
    phoneNumber,
    setPhoneNumber,
    enableRecording,
    setEnableRecording,
    callState,
    callMetadata,
    duration,
    formattedDuration,
    handleCall,
    handleEndCall,
    handleDigitClick,
    toggleMute,
    toggleHold,
    deviceState,
    deviceError,
    callError,
    isConfigured,
    hasAssignedNumbers,
    incomingCall,
    hiddenFeatures,
  } = useWidgetSoftphone();

  const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);
  const isDialerEnabled = isConfigured && hasAssignedNumbers && deviceState === 'registered';
  const canMakeCall = isDialerEnabled && callState === 'idle' && phoneNumber && phoneNumber.toString().replace(/\D/g, '').length >= 10;

  // Determine what message to show
  let disabledMessage = '';
  if (!isConfigured) {
    disabledMessage = 'Voice calling service is not configured. Please contact support.';
  } else if (!hasAssignedNumbers) {
    disabledMessage = 'No phone numbers assigned to your account. Please request a phone number first.';
  }

  return (
    <ScrollArea className="h-[550px]">
      <div className="p-4 space-y-3">
        {/* Incoming Call Alert - Shows at the top when there's an incoming call */}
        {incomingCall && <WidgetIncomingCallAlert />}

        {/* Configuration/Assignment Required Alert */}
        {!isDialerEnabled && disabledMessage && (
          <Alert variant="destructive" className="text-xs">
            <AlertCircle className="h-3 w-3" />
            <AlertDescription className="text-xs">{disabledMessage}</AlertDescription>
          </Alert>
        )}

        {/* Device Error */}
        {deviceError && (
          <Alert variant="destructive" className="text-xs">
            <AlertCircle className="h-3 w-3" />
            <AlertDescription className="text-xs">{deviceError}</AlertDescription>
          </Alert>
        )}

        {/* Call Error */}
        {callError && (
          <Alert variant="destructive" className="text-xs">
            <AlertCircle className="h-3 w-3" />
            <AlertDescription className="text-xs">{callError}</AlertDescription>
          </Alert>
        )}

        {/* Device Status */}
        {deviceState === 'registering' && (
          <Alert className="text-xs">
            <Loader2 className="h-3 w-3 animate-spin" />
            <AlertDescription className="text-xs">Connecting to voice service...</AlertDescription>
          </Alert>
        )}

        {/* Active Call Status */}
        {isCallActive && (
          <div className="space-y-2">
            <CallStatus state={callState} />
            {callState === 'connected' && (
              <CallTimer duration={duration} formattedDuration={formattedDuration} />
            )}
            {callMetadata.toNumber && (
              <div className="text-center">
                <div className="text-sm text-muted-foreground">Calling</div>
                <div className="text-lg font-semibold">{callMetadata.toNumber}</div>
              </div>
            )}
          </div>
        )}

        {/* Dialer Interface (when not in call) */}
        {!isCallActive && (
          <>
            {/* Number Switcher */}
            <div>
              <Label className="text-xs text-muted-foreground mb-1.5 block">Calling From</Label>
              <WidgetNumberSwitcher />
            </div>

            {/* Phone Input */}
            <PhoneInput
              value={phoneNumber || ''}
              onChange={(value) => setPhoneNumber(value || '')}
              disabled={!isDialerEnabled}
              label="Phone Number"
            />

            {/* Dial Pad */}
            <DialPad
              onDigitClick={handleDigitClick}
              disabled={!isDialerEnabled}
            />

            {/* Recording Option */}
            <div className="flex items-center space-x-2 px-1">
              <Checkbox
                id="widget-recording"
                checked={enableRecording}
                onCheckedChange={(checked) => setEnableRecording(checked as boolean)}
                disabled={!isDialerEnabled}
              />
              <Label
                htmlFor="widget-recording"
                className="text-xs text-muted-foreground cursor-pointer"
              >
                Enable call recording
              </Label>
            </div>

            {/* Call Button */}
            <Button
              onClick={handleCall}
              disabled={!canMakeCall}
              className="w-full"
              size="lg"
            >
              <Phone className="mr-2 h-5 w-5" />
              Call
            </Button>
          </>
        )}

        {/* Call Controls (when in call) */}
        {isCallActive && (
          <CallControls
            onMute={toggleMute}
            onHold={toggleHold}
            onEndCall={handleEndCall}
            callState={callState}
            isMuted={callMetadata.isMuted || false}
            isOnHold={callMetadata.isOnHold || false}
            // Not the component's default: hold redirects the remote leg
            // through a provider-specific document, and where that has no
            // equivalent the endpoint behind this button answers 404 —
            // leaving a control that flips to "on hold" and reverts a
            // moment later. This build has no Inertia page, so the list
            // comes from the context rather than the shared prop; see
            // ExpandedWidget.tsx for the same line on the pages that do.
            canHold={!isHidden('call_hold', hiddenFeatures)}
          />
        )}
      </div>
    </ScrollArea>
  );
}
