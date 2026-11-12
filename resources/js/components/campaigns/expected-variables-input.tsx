import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus, X, Sparkles, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface ExpectedVariablesInputProps {
  value: string[];
  onChange: (variables: string[]) => void;
  error?: string;
}

export default function ExpectedVariablesInput({ value, onChange, error }: ExpectedVariablesInputProps) {
  const [newVariable, setNewVariable] = useState('');
  const [inputError, setInputError] = useState('');

  const handleAddVariable = () => {
    const trimmed = newVariable.trim().toLowerCase();
    
    // Validation
    if (!trimmed) {
      setInputError('Variable name cannot be empty');
      return;
    }

    if (!/^[a-z_][a-z0-9_]*$/.test(trimmed)) {
      setInputError('Variable name must start with a letter or underscore, and contain only letters, numbers, and underscores');
      return;
    }

    if (value.includes(trimmed)) {
      setInputError('This variable already exists');
      return;
    }

    // Add the variable
    onChange([...value, trimmed]);
    setNewVariable('');
    setInputError('');
  };

  const handleRemoveVariable = (variableToRemove: string) => {
    onChange(value.filter(v => v !== variableToRemove));
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddVariable();
    }
  };

  // Common variable suggestions
  const suggestions = [
    'first_name',
    'last_name',
    'company',
    'email',
    'product_name',
    'appointment_date',
    'amount',
    'account_number',
  ].filter(s => !value.includes(s));

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Sparkles className="h-5 w-5 text-purple-600" />
          Dynamic Variables
        </CardTitle>
        <CardDescription>
          Define custom variables that you'll use in your message. These can be filled when adding contacts.
          Use them in your message with double curly braces, like {'{{first_name}}'}.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Input for new variable */}
        <div className="space-y-2">
          <Label htmlFor="new-variable">Add Variable</Label>
          <div className="flex gap-2">
            <div className="flex-1">
              <Input
                id="new-variable"
                placeholder="e.g., first_name, company, product_name"
                value={newVariable}
                onChange={(e) => {
                  setNewVariable(e.target.value);
                  setInputError('');
                }}
                onKeyPress={handleKeyPress}
                className={inputError ? 'border-red-500' : ''}
              />
              {inputError && (
                <p className="text-sm text-red-500 mt-1">{inputError}</p>
              )}
            </div>
            <Button type="button" onClick={handleAddVariable}>
              <Plus className="h-4 w-4 mr-1" />
              Add
            </Button>
          </div>
        </div>

        {/* Quick suggestions */}
        {suggestions.length > 0 && (
          <div className="space-y-2">
            <Label className="text-sm text-muted-foreground">Quick Add:</Label>
            <div className="flex flex-wrap gap-2">
              {suggestions.slice(0, 6).map((suggestion) => (
                <Button
                  key={suggestion}
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    onChange([...value, suggestion]);
                  }}
                >
                  <Plus className="h-3 w-3 mr-1" />
                  {suggestion}
                </Button>
              ))}
            </div>
          </div>
        )}

        {/* Current variables */}
        {value.length > 0 && (
          <div className="space-y-2">
            <Label>Defined Variables ({value.length})</Label>
            <div className="flex flex-wrap gap-2">
              {value.map((variable) => (
                <Badge key={variable} variant="secondary" className="text-sm py-1 px-3">
                  <span className="font-mono">{`{{${variable}}}`}</span>
                  <button
                    type="button"
                    onClick={() => handleRemoveVariable(variable)}
                    className="ml-2 hover:text-red-600"
                  >
                    <X className="h-3 w-3" />
                  </button>
                </Badge>
              ))}
            </div>
          </div>
        )}

        {/* Info alert */}
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            <strong>Tip:</strong> Add all the variables you plan to use in your message here. 
            When you add contacts later, you'll be able to provide values for these variables.
            {value.length === 0 && (
              <span className="block mt-1">
                Standard fields like <code className="text-xs">phone_number</code>, <code className="text-xs">first_name</code>, 
                <code className="text-xs">last_name</code>, and <code className="text-xs">email</code> are always available.
              </span>
            )}
          </AlertDescription>
        </Alert>

        {error && (
          <p className="text-sm text-red-500">{error}</p>
        )}
      </CardContent>
    </Card>
  );
}
