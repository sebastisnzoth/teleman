import { useState, useEffect, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus, X, User, Building2, AlertCircle, CheckCircle2, Sparkles } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface VariableManagerProps {
  message: string;
  onVariablesChange: (contactVars: string[], campaignVars: Record<string, string>) => void;
  initialContactVars?: string[];
  initialCampaignVars?: Record<string, string>;
  error?: string;
}

export default function VariableManager({
  message,
  onVariablesChange,
  initialContactVars = [],
  initialCampaignVars = {},
  error,
}: VariableManagerProps) {
  const [newVariable, setNewVariable] = useState('');
  const [variableType, setVariableType] = useState<'contact' | 'campaign'>('campaign');
  const [inputError, setInputError] = useState('');
  
  const [contactVariables, setContactVariables] = useState<string[]>(initialContactVars);
  const [campaignVariables, setCampaignVariables] = useState<Record<string, string>>(initialCampaignVars);

  // Extract variables used in message with better regex
  const usedVariables = useMemo(() => {
    const regex = /\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g;
    const matches = [...(message || '').matchAll(regex)];
    return matches.map(match => match[1]).filter((v, i, arr) => arr.indexOf(v) === i);
  }, [message]);

  useEffect(() => {
    onVariablesChange(contactVariables, campaignVariables);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [contactVariables, campaignVariables]);

  const handleAddVariable = () => {
    const trimmed = newVariable.trim().toLowerCase();
    
    if (!trimmed) {
      setInputError('Please enter a variable name');
      return;
    }

    if (!/^[a-z_][a-z0-9_]*$/.test(trimmed)) {
      setInputError('Use only letters, numbers, and underscores (e.g., store_name)');
      return;
    }

    if (contactVariables.includes(trimmed) || trimmed in campaignVariables) {
      setInputError('This variable already exists');
      return;
    }

    if (variableType === 'contact') {
      setContactVariables([...contactVariables, trimmed]);
    } else {
      setCampaignVariables({ ...campaignVariables, [trimmed]: '' });
    }

    setNewVariable('');
    setInputError('');
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddVariable();
    }
  };

  const handleRemoveContactVariable = (variable: string) => {
    setContactVariables(contactVariables.filter(v => v !== variable));
  };

  const handleRemoveCampaignVariable = (variable: string) => {
    const updated = { ...campaignVariables };
    delete updated[variable];
    setCampaignVariables(updated);
  };

  const handleCampaignVariableValueChange = (variable: string, value: string) => {
    setCampaignVariables({ ...campaignVariables, [variable]: value });
  };

  // Get missing variables (used but not defined)
  const missingVariables = usedVariables.filter(
    v => !contactVariables.includes(v) && !(v in campaignVariables) && !['first_name', 'last_name', 'email', 'company', 'phone_number'].includes(v)
  );

  const unusedContactVars = contactVariables.filter(v => !usedVariables.includes(v));
  const unusedCampaignVars = Object.keys(campaignVariables).filter(v => !usedVariables.includes(v));

  const emptyCampaignVars = Object.entries(campaignVariables)
    .filter(([, value]) => !value.trim())
    .map(([name]) => name);

  const standardContactVars = ['first_name', 'last_name', 'email', 'company', 'phone_number'];

  const contactSuggestions = ['first_name', 'last_name', 'company', 'email', 'city', 'country']
    .filter(s => !contactVariables.includes(s) && !(s in campaignVariables));
  
  const campaignSuggestions = ['store_name', 'brand_name', 'discount', 'offer_date', 'website', 'phone']
    .filter(s => !contactVariables.includes(s) && !(s in campaignVariables));

  return (
    <Card>
      <CardHeader>
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <CardTitle className="flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-amber-500" />
              Dynamic Variables
            </CardTitle>
            <CardDescription className="mt-1.5">
              Define variables to personalize your campaign. Type <code className="bg-slate-100 px-1 rounded text-xs">{'{{'}</code> in your message to use them.
            </CardDescription>
          </div>
          <Badge variant="outline">
            {contactVariables.length + Object.keys(campaignVariables).length} Variables
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Validation Alerts */}
        {missingVariables.length > 0 && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>Missing Variables</AlertTitle>
            <AlertDescription className="mt-2">
              Your message uses <strong>{missingVariables.map(v => `{{${v}}}`).join(', ')}</strong> but {missingVariables.length === 1 ? "it's" : "they're"} not defined.
            </AlertDescription>
          </Alert>
        )}

        {emptyCampaignVars.length > 0 && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>Missing Values</AlertTitle>
            <AlertDescription className="mt-2">
              Campaign variables need values: <strong>{emptyCampaignVars.map(v => `{{${v}}}`).join(', ')}</strong>
            </AlertDescription>
          </Alert>
        )}

        <Tabs value={variableType} onValueChange={(v) => setVariableType(v as 'contact' | 'campaign')} className="w-full">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="campaign" className="gap-2">
              <Building2 className="h-4 w-4 text-purple-600" />
              <span>Campaign Variables</span>
            </TabsTrigger>
            <TabsTrigger value="contact" className="gap-2">
              <User className="h-4 w-4 text-emerald-600" />
              <span>Contact Variables</span>
            </TabsTrigger>
          </TabsList>

          {/* Campaign Variables Tab */}
          <TabsContent value="campaign" className="space-y-4 mt-4">
            <Alert>
              <Building2 className="h-4 w-4 text-purple-600" />
              <AlertTitle>Campaign Variables</AlertTitle>
              <AlertDescription className="mt-2 text-sm">
                Use these for information that's the same for all contacts. Examples: store name, discount, event date.
              </AlertDescription>
            </Alert>

            <div className="space-y-3">
              <Label htmlFor="new-campaign-variable" className="text-sm font-medium">
                Add Campaign Variable
              </Label>
              <div className="flex gap-2">
                <Input
                  id="new-campaign-variable"
                  placeholder="e.g., store_name, discount, brand"
                  value={newVariable}
                  onChange={(e) => {
                    setNewVariable(e.target.value);
                    setInputError('');
                  }}
                  onKeyPress={handleKeyPress}
                  className={`flex-1 ${inputError ? 'border-red-500' : ''}`}
                />
                <Button type="button" onClick={handleAddVariable}>
                  <Plus className="h-4 w-4 mr-1" />
                  Add
                </Button>
              </div>
              {inputError && <p className="text-sm text-red-600 flex items-center gap-1"><AlertCircle className="h-3 w-3" />{inputError}</p>}
            </div>

            {campaignSuggestions.length > 0 && (
              <div className="space-y-2">
                <Label className="text-xs text-slate-600 flex items-center gap-1">
                  <Sparkles className="h-3 w-3 text-amber-500" />
                  Quick Add:
                </Label>
                <div className="flex flex-wrap gap-2">
                  {campaignSuggestions.slice(0, 6).map((suggestion) => (
                    <Button
                      key={suggestion}
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setCampaignVariables({ ...campaignVariables, [suggestion]: '' });
                      }}
                      className="h-7 text-xs"
                    >
                      <Plus className="h-3 w-3 mr-1" />
                      {suggestion}
                    </Button>
                  ))}
                </div>
              </div>
            )}

            {Object.keys(campaignVariables).length > 0 && (
              <div className="space-y-3">
                <Label className="text-sm font-medium">
                  Campaign Variables ({Object.keys(campaignVariables).length})
                </Label>
                <div className="space-y-3">
                  {Object.entries(campaignVariables).map(([varName, value]) => (
                    <div key={varName} className="flex items-start gap-3 p-3 border rounded-lg">
                      <div className="flex-1 space-y-2">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="font-mono text-xs">
                            <Building2 className="h-3 w-3 mr-1 text-purple-600" />
                            {`{{${varName}}}`}
                          </Badge>
                          {value.trim() ? (
                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                          ) : usedVariables.includes(varName) ? (
                            <AlertCircle className="h-4 w-4 text-red-600" />
                          ) : null}
                          {!usedVariables.includes(varName) && (
                            <span className="text-xs text-slate-500">Not used in message</span>
                          )}
                        </div>
                        <Input
                          placeholder={`Enter value for {{${varName}}}`}
                          value={value}
                          onChange={(e) => handleCampaignVariableValueChange(varName, e.target.value)}
                          className={`text-sm ${!value.trim() && usedVariables.includes(varName) ? 'border-red-500' : ''}`}
                        />
                        {!value.trim() && usedVariables.includes(varName) && (
                          <p className="text-xs text-red-600">This variable is used but has no value</p>
                        )}
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => handleRemoveCampaignVariable(varName)}
                        className="shrink-0"
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {Object.keys(campaignVariables).length === 0 && (
              <div className="text-center py-6 text-slate-500">
                <Building2 className="h-10 w-10 mx-auto mb-2 opacity-20" />
                <p className="text-sm">No campaign variables yet</p>
              </div>
            )}
          </TabsContent>

          {/* Contact Variables Tab */}
          <TabsContent value="contact" className="space-y-4 mt-4">
            <Alert>
              <User className="h-4 w-4 text-emerald-600" />
              <AlertTitle>Contact Variables</AlertTitle>
              <AlertDescription className="mt-2 text-sm">
                Use these for information that's different for each contact. Values come from CSV or contact data.
              </AlertDescription>
            </Alert>

            <div className="space-y-3">
              <Label htmlFor="new-contact-variable" className="text-sm font-medium">
                Add Contact Variable
              </Label>
              <div className="flex gap-2">
                <Input
                  id="new-contact-variable"
                  placeholder="e.g., first_name, company, city"
                  value={newVariable}
                  onChange={(e) => {
                    setNewVariable(e.target.value);
                    setInputError('');
                  }}
                  onKeyPress={handleKeyPress}
                  className={`flex-1 ${inputError ? 'border-red-500' : ''}`}
                />
                <Button type="button" onClick={handleAddVariable}>
                  <Plus className="h-4 w-4 mr-1" />
                  Add
                </Button>
              </div>
              {inputError && <p className="text-sm text-red-600 flex items-center gap-1"><AlertCircle className="h-3 w-3" />{inputError}</p>}
            </div>

            {contactSuggestions.length > 0 && (
              <div className="space-y-2">
                <Label className="text-xs text-slate-600 flex items-center gap-1">
                  <Sparkles className="h-3 w-3 text-amber-500" />
                  Quick Add:
                </Label>
                <div className="flex flex-wrap gap-2">
                  {contactSuggestions.slice(0, 6).map((suggestion) => (
                    <Button
                      key={suggestion}
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setContactVariables([...contactVariables, suggestion]);
                      }}
                      className="h-7 text-xs"
                    >
                      <Plus className="h-3 w-3 mr-1" />
                      {suggestion}
                    </Button>
                  ))}
                </div>
              </div>
            )}

            {contactVariables.length > 0 && (
              <div className="space-y-3">
                <Label className="text-sm font-medium">
                  Contact Variables ({contactVariables.length})
                </Label>
                <div className="flex flex-wrap gap-2">
                  {contactVariables.map((variable) => (
                    <Badge
                      key={variable}
                      variant="outline"
                      className="text-sm py-1.5 px-3 font-mono"
                    >
                      <User className="h-3 w-3 mr-1.5 text-emerald-600" />
                      {`{{${variable}}}`}
                      {usedVariables.includes(variable) && (
                        <CheckCircle2 className="h-3 w-3 ml-1.5 text-emerald-600" />
                      )}
                      <button
                        type="button"
                        onClick={() => handleRemoveContactVariable(variable)}
                        className="ml-2 hover:text-red-600"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </Badge>
                  ))}
                </div>
              </div>
            )}

            {contactVariables.length === 0 && (
              <div className="text-center py-6 text-slate-500">
                <User className="h-10 w-10 mx-auto mb-2 opacity-20" />
                <p className="text-sm">No contact variables yet</p>
              </div>
            )}

            {/* Standard Variables Info */}
            <Alert>
              <CheckCircle2 className="h-4 w-4 text-slate-600" />
              <AlertTitle>Standard Contact Variables</AlertTitle>
              <AlertDescription className="mt-2 text-sm">
                These are always available from contact data:
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {standardContactVars.map(v => (
                    <code key={v} className="bg-slate-100 px-2 py-0.5 rounded text-xs font-mono">
                      {`{{${v}}}`}
                    </code>
                  ))}
                </div>
              </AlertDescription>
            </Alert>
          </TabsContent>
        </Tabs>

        {/* Unused variables warning */}
        {(unusedContactVars.length > 0 || unusedCampaignVars.length > 0) && (
          <Alert>
            <AlertCircle className="h-4 w-4 text-amber-600" />
            <AlertTitle>Unused Variables</AlertTitle>
            <AlertDescription className="mt-2 text-sm">
              These variables aren't used in your message:
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {[...unusedContactVars, ...unusedCampaignVars].map(v => (
                  <code key={v} className="bg-slate-100 px-2 py-0.5 rounded text-xs font-mono">
                    {`{{${v}}}`}
                  </code>
                ))}
              </div>
            </AlertDescription>
          </Alert>
        )}

        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}
