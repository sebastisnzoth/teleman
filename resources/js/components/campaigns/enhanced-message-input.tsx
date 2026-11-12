import { useState, useRef, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle2, Sparkles, User, Building2, MessageSquare, Wand2, Loader2 } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import axios from 'axios';

interface EnhancedMessageInputProps {
  value: string;
  onChange: (value: string) => void;
  contactVariables: string[];
  campaignVariables: Record<string, string>;
  error?: string;
}

export default function EnhancedMessageInput({
  value,
  onChange,
  contactVariables,
  campaignVariables,
  error,
}: EnhancedMessageInputProps) {
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [cursorPosition, setCursorPosition] = useState(0);
  const [showAiDialog, setShowAiDialog] = useState(false);
  const [aiMode, setAiMode] = useState<'generate' | 'enhance'>('generate');
  const [aiPrompt, setAiPrompt] = useState('');
  const [aiLoading, setAiLoading] = useState(false);
  const [aiError, setAiError] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const MAX_CHARS = 250;

  // All available variables
  const standardVars = ['first_name', 'last_name', 'email', 'company', 'phone_number'];
  const allVariables = [...standardVars, ...contactVariables, ...Object.keys(campaignVariables)];

  // Extract variables used in message with better regex
  const usedVariables = useMemo(() => {
    const regex = /\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g;
    const matches = [...(value || '').matchAll(regex)];
    return matches.map(match => match[1]).filter((v, i, arr) => arr.indexOf(v) === i);
  }, [value]);

  // Find undefined variables
  const undefinedVariables = usedVariables.filter(v => !allVariables.includes(v));

  // Find variables with empty values (campaign variables)
  const emptyValueVariables = usedVariables.filter(
    v => v in campaignVariables && !campaignVariables[v].trim()
  );

  const handleTextChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newValue = e.target.value;
    onChange(newValue);
    setCursorPosition(e.target.selectionStart);

    // Check if user typed {{ to show suggestions
    const beforeCursor = newValue.slice(0, e.target.selectionStart);
    if (beforeCursor.endsWith('{{')) {
      setShowSuggestions(true);
    } else {
      setShowSuggestions(false);
    }
  };

  const insertVariable = (varName: string) => {
    if (!textareaRef.current) return;

    const before = value.slice(0, cursorPosition);
    const after = value.slice(cursorPosition);

    // If user already typed {{, replace it
    const newBefore = before.endsWith('{{') ? before.slice(0, -2) : before;
    const newValue = `${newBefore}{{${varName}}}${after}`;

    onChange(newValue);
    setShowSuggestions(false);

    // Focus back to textarea
    setTimeout(() => {
      if (textareaRef.current) {
        textareaRef.current.focus();
        const newPosition = newBefore.length + varName.length + 4; // {{ + varName + }}
        textareaRef.current.setSelectionRange(newPosition, newPosition);
      }
    }, 0);
  };

  // Get variable info with icon
  const getVariableIcon = (varName: string) => {
    if (standardVars.includes(varName) || contactVariables.includes(varName)) {
      return { Icon: User, color: 'text-emerald-600' };
    } else if (varName in campaignVariables) {
      return { Icon: Building2, color: 'text-purple-600' };
    }
    return { Icon: AlertCircle, color: 'text-red-600' };
  };

  // Handle AI message generation/enhancement
  const handleAiAssist = async () => {
    setAiError('');
    setAiLoading(true);

    try {
      const endpoint = aiMode === 'generate' 
        ? '/api/ai/message/generate' 
        : '/api/ai/message/enhance';

      const payload = {
        [aiMode === 'generate' ? 'prompt' : 'message']: aiMode === 'generate' ? aiPrompt : value,
        contact_variables: [...standardVars, ...contactVariables],
        campaign_variables: campaignVariables,
      };

      const response = await axios.post(endpoint, payload);

      if (response.data.success) {
        onChange(response.data.message);
        setShowAiDialog(false);
        setAiPrompt('');
      } else {
        setAiError(response.data.error || 'Failed to process AI request');
      }
    } catch (err) {
      const error = err as { response?: { data?: { error?: string } }; message?: string };
      setAiError(error.response?.data?.error || error.message || 'An error occurred');
    } finally {
      setAiLoading(false);
    }
  };

  // Open AI dialog
  const openAiDialog = (mode: 'generate' | 'enhance') => {
    setAiMode(mode);
    setAiPrompt('');
    setAiError('');
    setShowAiDialog(true);
  };

  // Character count color
  const getCharCountColor = () => {
    if (value.length >= MAX_CHARS) return 'text-red-600 font-semibold';
    if (value.length >= 240) return 'text-yellow-600 font-medium';
    if (value.length >= 200) return 'text-amber-600';
    return 'text-slate-500';
  };

  return (
    <>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <MessageSquare className="h-5 w-5 text-slate-700" />
                Campaign Message
              </CardTitle>
              <CardDescription>
                Write your personalized message. Type <code className="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">{'{{'}</code> to insert variables.
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => openAiDialog('generate')}
                className="text-xs"
              >
                <Wand2 className="h-3.5 w-3.5 mr-1.5 text-purple-600" />
                Generate with AI
              </Button>
              {value && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => openAiDialog('enhance')}
                  className="text-xs"
                >
                  <Sparkles className="h-3.5 w-3.5 mr-1.5 text-amber-500" />
                  Enhance with AI
                </Button>
              )}
            </div>
          </div>
        </CardHeader>
      <CardContent className="space-y-4">
        {/* Message Input Area */}
        <div className="space-y-3">
          <div className="relative">
            <Textarea
              ref={textareaRef}
              value={value}
              onChange={handleTextChange}
              onFocus={() => {
                const text = textareaRef.current?.value || '';
                const pos = textareaRef.current?.selectionStart || 0;
                if (text.slice(Math.max(0, pos - 2), pos) === '{{') {
                  setShowSuggestions(true);
                }
              }}
              placeholder="Example: Hello {{first_name}}, visit {{store_name}} for {{discount}}% off!"
              rows={6}
              className="font-mono text-sm resize-none"
            />

            {/* Variable Suggestions Dropdown */}
            {showSuggestions && (
              <div className="absolute z-10 mt-1 w-full max-h-60 overflow-y-auto bg-white border rounded-lg shadow-lg">
                <div className="p-3">
                  <div className="flex items-center justify-between mb-3 pb-2 border-b">
                    <p className="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                      <Sparkles className="h-3.5 w-3.5 text-amber-500" />
                      Available Variables ({allVariables.length})
                    </p>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setShowSuggestions(false)}
                      className="h-6 text-xs"
                    >
                      Close
                    </Button>
                  </div>
                  <div className="space-y-1 max-h-48 overflow-y-auto">
                    {allVariables.map(varName => {
                      const { Icon, color } = getVariableIcon(varName);
                      return (
                        <button
                          key={varName}
                          type="button"
                          onClick={() => insertVariable(varName)}
                          className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 rounded-md text-left transition-colors"
                        >
                          <Icon className={`h-4 w-4 ${color} shrink-0`} />
                          <span className="font-mono font-medium text-slate-700">{`{{${varName}}}`}</span>
                          {varName in campaignVariables && campaignVariables[varName] && (
                            <span className="text-xs text-slate-500 ml-auto truncate">
                              = "{campaignVariables[varName]}"
                            </span>
                          )}
                        </button>
                      );
                    })}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Message Preview with Variable Tags */}
          {value && (
            <div className="border rounded-lg p-3 bg-slate-50">
              <div className="text-xs font-medium text-slate-600 mb-2">Preview with variables highlighted:</div>
              <div className="text-sm leading-relaxed">
                {value.split(/(\{\{[a-zA-Z_][a-zA-Z0-9_]*\}\})/).map((part, index) => {
                  const match = part.match(/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/);
                  if (match) {
                    const varName = match[1];
                    const { Icon, color } = getVariableIcon(varName);
                    const isEmpty = varName in campaignVariables && !campaignVariables[varName].trim();
                    const isUndefined = !allVariables.includes(varName);
                    
                    return (
                      <span
                        key={index}
                        className={`inline-flex items-center gap-1 px-2 py-0.5 rounded font-mono text-xs font-medium border ${
                          isUndefined
                            ? 'bg-red-50 border-red-200 text-red-700'
                            : isEmpty
                            ? 'bg-yellow-50 border-yellow-200 text-yellow-700'
                            : 'bg-white border-slate-200 text-slate-700'
                        }`}
                      >
                        <Icon className={`h-3 w-3 ${isUndefined ? 'text-red-500' : isEmpty ? 'text-yellow-500' : color}`} />
                        {part}
                        {varName in campaignVariables && campaignVariables[varName] && (
                          <span className="text-[10px] text-slate-500">
                            = {campaignVariables[varName]}
                          </span>
                        )}
                      </span>
                    );
                  }
                  return <span key={index} className="text-slate-700">{part}</span>;
                })}
              </div>
            </div>
          )}
        </div>

        {error && <InputError message={error} />}

        {/* Quick Insert Buttons */}
        {allVariables.length > 0 && (
          <div className="space-y-2">
            <div className="text-xs font-medium text-slate-600 flex items-center gap-1.5">
              <Sparkles className="h-3.5 w-3.5 text-amber-500" />
              Quick Insert:
            </div>
            <div className="flex flex-wrap gap-2">
              {allVariables.slice(0, 8).map((varName) => {
                const { Icon, color } = getVariableIcon(varName);
                return (
                  <Button
                    key={varName}
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => insertVariable(varName)}
                    className="text-xs font-mono h-7"
                  >
                    <Icon className={`h-3 w-3 mr-1 ${color}`} />
                    {`{{${varName}}}`}
                  </Button>
                );
              })}
            </div>
          </div>
        )}

        {/* Validation Messages */}
        {undefinedVariables.length > 0 && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>Undefined Variables</AlertTitle>
            <AlertDescription className="mt-2">
              The following variables are not defined: <strong>{undefinedVariables.map(v => `{{${v}}}`).join(', ')}</strong>
              <div className="mt-1 text-sm">
                → Add them in the Dynamic Variables section above.
              </div>
            </AlertDescription>
          </Alert>
        )}

        {emptyValueVariables.length > 0 && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>Missing Campaign Variable Values</AlertTitle>
            <AlertDescription className="mt-2">
              These campaign variables need values: <strong>{emptyValueVariables.map(v => `{{${v}}}`).join(', ')}</strong>
              <div className="mt-1 text-sm">
                → Fill them in the Campaign Variables tab above.
              </div>
            </AlertDescription>
          </Alert>
        )}

        {/* Success State */}
        {usedVariables.length > 0 && undefinedVariables.length === 0 && emptyValueVariables.length === 0 && (
          <Alert className="bg-emerald-50 border-emerald-200">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            <AlertTitle className="text-emerald-900">All Variables Valid</AlertTitle>
            <AlertDescription className="mt-2 text-emerald-800 text-sm">
              Your message uses {usedVariables.length} variable{usedVariables.length !== 1 ? 's' : ''} and all are properly defined.
            </AlertDescription>
          </Alert>
        )}

        {/* Empty State Tip */}
        {usedVariables.length === 0 && value && (
          <Alert>
            <Sparkles className="h-4 w-4 text-amber-500" />
            <AlertTitle>Tip: Personalize Your Message</AlertTitle>
            <AlertDescription className="mt-2 text-sm">
              Your message doesn't use any variables yet. Add variables to personalize it for each contact.
              <div className="mt-1">
                Try: <code className="bg-slate-100 px-1 rounded text-xs">Hello {'{{first_name}}'}</code>
              </div>
            </AlertDescription>
          </Alert>
        )}

        {/* Character count */}
        <div className="flex items-center justify-between pt-2 border-t">
          <div className="flex items-center gap-3 text-xs">
            <span className={getCharCountColor()}>
              {value.length} / {MAX_CHARS} characters
            </span>
            {usedVariables.length > 0 && (
              <>
                <span className="text-slate-400">•</span>
                <span className="text-slate-500">{usedVariables.length} variable{usedVariables.length !== 1 ? 's' : ''}</span>
              </>
            )}
          </div>
          <span className="flex items-center gap-1 text-xs text-slate-500">
            Type <code className="bg-slate-100 px-1 rounded font-mono">{'{{'}</code> for suggestions
          </span>
        </div>
      </CardContent>
    </Card>

    {/* AI Assistant Dialog */}
    <Dialog open={showAiDialog} onOpenChange={setShowAiDialog}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {aiMode === 'generate' ? (
              <>
                <Wand2 className="h-5 w-5 text-purple-600" />
                Generate Message with AI
              </>
            ) : (
              <>
                <Sparkles className="h-5 w-5 text-amber-500" />
                Enhance Message with AI
              </>
            )}
          </DialogTitle>
          <DialogDescription>
            {aiMode === 'generate' 
              ? 'Describe what you want your message to say, and AI will create it for you.'
              : 'AI will improve your message while keeping your variables and staying under 250 characters.'
            }
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {aiMode === 'generate' ? (
            <div className="space-y-2">
              <Label htmlFor="ai-prompt">What should the message say?</Label>
              <Textarea
                id="ai-prompt"
                value={aiPrompt}
                onChange={(e) => setAiPrompt(e.target.value)}
                placeholder="Example: Invite customers to visit our store for a special discount on their birthday"
                rows={4}
                className="resize-none"
              />
            </div>
          ) : (
            <div className="space-y-2">
              <Label>Current Message:</Label>
              <div className="p-3 bg-slate-50 border rounded-lg text-sm font-mono">
                {value}
              </div>
            </div>
          )}

          {aiError && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{aiError}</AlertDescription>
            </Alert>
          )}
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => setShowAiDialog(false)}
            disabled={aiLoading}
          >
            Cancel
          </Button>
          <Button
            type="button"
            onClick={handleAiAssist}
            disabled={aiLoading || (aiMode === 'generate' && !aiPrompt.trim())}
          >
            {aiLoading ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Processing...
              </>
            ) : (
              <>
                {aiMode === 'generate' ? (
                  <>
                    <Wand2 className="h-4 w-4 mr-2" />
                    Generate
                  </>
                ) : (
                  <>
                    <Sparkles className="h-4 w-4 mr-2" />
                    Enhance
                  </>
                )}
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </>
  );
}
