import React from 'react';
import { Phone, ChevronDown, CheckCircle2, RefreshCw, Globe, Server } from 'lucide-react';
import { useUserNumbers } from '@/hooks/useUserNumbers';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

function SourceBadge({ source }: { source?: string }) {
  if (source === 'sip_trunk') {
    return (
      <Badge variant="outline" className="text-[10px] px-1 py-0 h-4 border-blue-300 text-blue-600">
        <Server className="h-2.5 w-2.5 mr-0.5" />
        SIP
      </Badge>
    );
  }
  return (
    <Badge variant="outline" className="text-[10px] px-1 py-0 h-4 border-green-300 text-green-600">
      <Globe className="h-2.5 w-2.5 mr-0.5" />
      Phone
    </Badge>
  );
}

export function NumberSwitcher() {
  const { numbers, selectedNumber, selectNumber, loading, error, refresh } = useUserNumbers();

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <RefreshCw className="h-4 w-4 animate-spin" />
        <span>Loading numbers...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center gap-2">
        <Badge variant="destructive" className="text-xs">
          {error}
        </Badge>
        <Button
          variant="ghost"
          size="sm"
          onClick={refresh}
          className="h-8 w-8 p-0"
        >
          <RefreshCw className="h-4 w-4" />
        </Button>
      </div>
    );
  }

  if (numbers.length === 0) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Phone className="h-4 w-4" />
        <span>No phone numbers assigned</span>
      </div>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="flex items-center gap-2 min-w-[200px]">
          <Phone className="h-4 w-4" />
          <div className="flex-1 text-left">
            {selectedNumber ? (
              <div className="flex flex-col">
                <div className="flex items-center gap-1.5">
                  <span className="text-sm font-medium">
                    {selectedNumber.formatted_number}
                  </span>
                  <SourceBadge source={selectedNumber.source} />
                </div>
                {selectedNumber.friendly_name && (
                  <span className="text-xs text-muted-foreground">
                    {selectedNumber.friendly_name}
                  </span>
                )}
              </div>
            ) : (
              <span className="text-sm">Select Number</span>
            )}
          </div>
          <ChevronDown className="h-4 w-4 opacity-50" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-[250px]">
        <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
          Your Phone Numbers ({numbers.length})
        </div>
        <DropdownMenuSeparator />
        {numbers.map((number) => (
          <DropdownMenuItem
            key={number.id}
            onClick={() => selectNumber(number)}
            className="flex items-start gap-2 cursor-pointer"
          >
            <div className="flex items-center justify-center w-4 h-4 mt-0.5">
              {selectedNumber?.id === number.id && (
                <CheckCircle2 className="h-4 w-4 text-primary" />
              )}
            </div>
            <div className="flex-1">
              <div className="flex items-center gap-1.5">
                <span className="font-medium text-sm">
                  {number.formatted_number}
                </span>
                <SourceBadge source={number.source} />
              </div>
              {number.friendly_name && (
                <div className="text-xs text-muted-foreground">
                  {number.friendly_name}
                </div>
              )}
            </div>
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={refresh}
          className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer"
        >
          <RefreshCw className="h-3 w-3" />
          <span>Refresh Numbers</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
