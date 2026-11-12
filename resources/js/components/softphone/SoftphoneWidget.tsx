import { useSoftphone } from '@/contexts/SoftphoneContext';
import { Phone, X, Maximize2, Minimize2, PhoneOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { MinimizedWidget } from './MinimizedWidget';
import { ExpandedWidget } from './ExpandedWidget';
import { PostCallModal } from './PostCallModal';
import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

export function SoftphoneWidget() {
  const {
    isWidgetExpanded,
    setIsWidgetExpanded,
    isWidgetVisible,
    callState,
    incomingCall,
    deviceState,
    activeContactName,
  } = useSoftphone();

  const [position, setPosition] = useState({ x: 20, y: 20 }); // Position from bottom-right
  const [isDragging, setIsDragging] = useState(false);
  const [isOnSoftphonePage, setIsOnSoftphonePage] = useState(false);

  // Check if we're on the Softphone page
  useEffect(() => {
    const checkPage = () => {
      const currentPath = window.location.pathname;
      setIsOnSoftphonePage(currentPath === '/softphone' || currentPath.startsWith('/softphone/'));
    };

    checkPage();

    // Listen to Inertia navigation
    const unsubscribe = router.on('navigate', checkPage);

    return () => unsubscribe();
  }, []);

  // Check if there's an active call (dialing, ringing, or connected)
  const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);

  // Don't show widget if explicitly hidden (unless there's an active call)
  if (!isWidgetVisible && !isCallActive && !incomingCall) {
    return null;
  }

  // Widget shows on ALL pages including softphone page
  // Both are synced via SoftphoneContext - same state, same Twilio device

  return (
    <>
      {/* Minimized floating button */}
      {!isWidgetExpanded && (
        <MinimizedWidget
          position={position}
          onExpand={() => setIsWidgetExpanded(true)}
        />
      )}

      {/* Expanded widget panel */}
      {isWidgetExpanded && (
        <Card
          className={cn(
            'fixed z-[9999] shadow-2xl border-2 transition-all duration-300',
            'w-[380px] max-h-[600px] overflow-hidden',
            'animate-in fade-in-0 slide-in-from-bottom-4'
          )}
          style={{
            bottom: `${position.y}px`,
            right: `${position.x}px`,
          }}
        >
          {/* Header */}
          <div className="flex items-center justify-between p-3 border-b bg-muted/30">
            <div className="flex items-center gap-2">
              <div className="relative">
                <Phone className="h-5 w-5 text-primary" />
                {deviceState === 'registered' && (
                  <div className="absolute -top-1 -right-1 w-2 h-2 bg-green-500 rounded-full" />
                )}
              </div>
              <div>
                <span className="font-semibold text-sm">Softphone</span>
                {activeContactName && isCallActive && (
                  <span className="ml-2 text-xs text-muted-foreground">- {activeContactName}</span>
                )}
              </div>
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                size="icon"
                className="h-7 w-7"
                onClick={() => setIsWidgetExpanded(false)}
              >
                <Minimize2 className="h-4 w-4" />
              </Button>
            </div>
          </div>

          {/* Content */}
          <ExpandedWidget />
        </Card>
      )}

      {/* Post-call disposition modal */}
      <PostCallModal />
    </>
  );
}
