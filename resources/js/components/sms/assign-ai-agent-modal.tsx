import { useState } from 'react';
import { Bot, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { router } from '@inertiajs/react';

interface PhoneNumber {
  id: number;
  number: string;
  friendly_name: string | null;
  ai_agent: {
    id: number;
    name: string;
  } | null;
}

interface AIAgent {
  id: number;
  name: string;
  description: string;
}

interface Props {
  open: boolean;
  onClose: () => void;
  phoneNumber: PhoneNumber;
  aiAgents: AIAgent[];
}

export default function AssignAIAgentModal({
  open,
  onClose,
  phoneNumber,
  aiAgents,
}: Props) {
  const [selectedAgentId, setSelectedAgentId] = useState<string>(
    phoneNumber.ai_agent?.id?.toString() || ''
  );
  const [processing, setProcessing] = useState(false);

  const handleSubmit = () => {
    setProcessing(true);

    router.post(
      `/sms/phone-numbers/${phoneNumber.id}/assign-agent`,
      {
        ai_agent_id: selectedAgentId || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          onClose();
        },
        onFinish: () => {
          setProcessing(false);
        },
      }
    );
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Bot className="h-5 w-5" />
            Assign AI Agent to SMS Number
          </DialogTitle>
          <DialogDescription>
            Choose an AI agent to handle conversations for {phoneNumber.number}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label>SMS Phone Number</Label>
            <div className="p-3 bg-muted rounded-md">
              <div className="font-medium">{phoneNumber.number}</div>
              {phoneNumber.friendly_name && (
                <div className="text-sm text-muted-foreground">
                  {phoneNumber.friendly_name}
                </div>
              )}
            </div>
          </div>

          <div className="space-y-3">
            <Label>Select AI Agent</Label>
            <RadioGroup
              value={selectedAgentId}
              onValueChange={setSelectedAgentId}
              className="space-y-3"
            >
              {/* Option to unassign */}
              <div className="flex items-start space-x-3 p-3 rounded-lg border hover:bg-accent cursor-pointer">
                <RadioGroupItem value="" id="no-agent" className="mt-1" />
                <Label htmlFor="no-agent" className="flex-1 cursor-pointer">
                  <div className="font-medium">No Agent (Manual Mode)</div>
                  <div className="text-sm text-muted-foreground">
                    Messages won't be answered automatically
                  </div>
                </Label>
              </div>

              {/* AI Agents */}
              {aiAgents.map((agent) => (
                <div
                  key={agent.id}
                  className="flex items-start space-x-3 p-3 rounded-lg border hover:bg-accent cursor-pointer"
                >
                  <RadioGroupItem
                    value={agent.id.toString()}
                    id={`agent-${agent.id}`}
                    className="mt-1"
                  />
                  <Label
                    htmlFor={`agent-${agent.id}`}
                    className="flex-1 cursor-pointer"
                  >
                    <div className="flex items-center gap-2">
                      <Bot className="h-4 w-4 text-primary" />
                      <span className="font-medium">{agent.name}</span>
                    </div>
                    {agent.description && (
                      <div className="text-sm text-muted-foreground mt-1">
                        {agent.description}
                      </div>
                    )}
                  </Label>
                  {phoneNumber.ai_agent?.id === agent.id && (
                    <Check className="h-4 w-4 text-green-600 dark:text-green-400" />
                  )}
                </div>
              ))}
            </RadioGroup>
          </div>

          {selectedAgentId && (
            <div className="p-3 bg-blue-50 dark:bg-blue-950 rounded-md border border-blue-200 dark:border-blue-800">
              <div className="text-sm text-blue-900 dark:text-blue-100">
                <strong>Auto-Response Enabled:</strong> The selected AI agent will
                automatically respond to incoming SMS messages within 2 seconds.
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={processing}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={processing}>
            {processing ? 'Assigning...' : 'Assign Agent'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
