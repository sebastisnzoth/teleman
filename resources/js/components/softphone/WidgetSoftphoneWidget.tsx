import { useWidgetSoftphone } from '@/contexts/WidgetSoftphoneContext';
import { WidgetExpandedWidget } from './WidgetExpandedWidget';
import { Phone, PhoneIncoming, PhoneCall } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function WidgetSoftphoneWidget() {
  const { 
    isWidgetExpanded, 
    setIsWidgetExpanded, 
    incomingCall, 
    callState, 
    duration, 
    deviceState 
  } = useWidgetSoftphone();

  const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);
  const hasIncomingCall = !!incomingCall && callState === 'idle';

  // Format duration for display
  const formatDuration = () => {
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  };

  return (
    <>
      {/* Widget Container */}
      <div
        className="fixed z-50"
        style={{
          bottom: '20px',
          right: '20px',
        }}
      >
        {isWidgetExpanded ? (
          <Card className="w-[380px] shadow-2xl">
            <WidgetExpandedWidget />
          </Card>
        ) : (
          <Button
            onClick={() => setIsWidgetExpanded(true)}
            className={cn(
              'rounded-full shadow-2xl transition-all duration-300',
              'hover:scale-110 active:scale-95',
              isCallActive && 'bg-green-600 hover:bg-green-700 animate-pulse',
              hasIncomingCall && 'bg-blue-600 hover:bg-blue-700 animate-bounce',
              !isCallActive && !hasIncomingCall && 'bg-primary hover:bg-primary/90'
            )}
            style={{
              width: isCallActive ? '120px' : '56px',
              height: '56px',
            }}
            size="icon"
          >
            <div className="flex items-center gap-2">
              {hasIncomingCall ? (
                <>
                  <PhoneIncoming className="h-6 w-6 animate-bounce" />
                  <span className="text-xs font-semibold">Incoming</span>
                </>
              ) : isCallActive ? (
                <>
                  <PhoneCall className="h-5 w-5" />
                  <span className="text-xs font-mono">{formatDuration()}</span>
                </>
              ) : (
                <Phone className="h-6 w-6" />
              )}
            </div>
            
            {/* Status indicator */}
            {deviceState === 'registered' && !isCallActive && !hasIncomingCall && (
              <div className="absolute bottom-1 right-1 h-3 w-3 rounded-full bg-green-400 ring-2 ring-white" />
            )}
          </Button>
        )}
      </div>
    </>
  );
}
