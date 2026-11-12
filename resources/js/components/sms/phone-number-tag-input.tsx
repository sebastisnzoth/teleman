import { useState, KeyboardEvent, ClipboardEvent } from 'react';
import { X, AlertCircle, CheckCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { validatePhoneNumber, parsePhoneNumbers } from '@/lib/phone-validation';
import { cn } from '@/lib/utils';

interface PhoneTag {
  phone: string;
  isValid: boolean;
  error?: string;
}

interface PhoneNumberTagInputProps {
  value: PhoneTag[];
  onChange: (tags: PhoneTag[]) => void;
  placeholder?: string;
  className?: string;
}

export default function PhoneNumberTagInput({
  value = [],
  onChange,
  placeholder = 'Type phone number and press Enter (e.g., +12345678900)',
  className,
}: PhoneNumberTagInputProps) {
  const [inputValue, setInputValue] = useState('');

  const addPhoneNumber = (phone: string) => {
    const trimmed = phone.trim();
    if (!trimmed) return;

    // Check for duplicates
    if (value.some((tag) => tag.phone === trimmed)) {
      return;
    }

    const validation = validatePhoneNumber(trimmed);
    const newTag: PhoneTag = {
      phone: trimmed,
      isValid: validation.isValid,
      error: validation.error,
    };

    onChange([...value, newTag]);
  };

  const removePhoneNumber = (phone: string) => {
    onChange(value.filter((tag) => tag.phone !== phone));
  };

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && inputValue) {
      e.preventDefault();
      addPhoneNumber(inputValue);
      setInputValue('');
    } else if (e.key === 'Backspace' && !inputValue && value.length > 0) {
      // Remove last tag when backspace is pressed on empty input
      onChange(value.slice(0, -1));
    }
  };

  const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault();
    const pastedText = e.clipboardData.getData('text');
    const phoneNumbers = parsePhoneNumbers(pastedText);

    const newTags: PhoneTag[] = phoneNumbers
      .filter((phone) => !value.some((tag) => tag.phone === phone)) // Remove duplicates
      .map((phone) => {
        const validation = validatePhoneNumber(phone);
        return {
          phone,
          isValid: validation.isValid,
          error: validation.error,
        };
      });

    onChange([...value, ...newTags]);
    setInputValue('');
  };

  const validCount = value.filter((tag) => tag.isValid).length;
  const invalidCount = value.filter((tag) => !tag.isValid).length;

  return (
    <div className={cn('space-y-2', className)}>
      <div className="min-h-[120px] max-h-[300px] overflow-y-auto rounded-md border border-input bg-background p-3 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
        <div className="flex flex-wrap gap-2 mb-2">
          {value.map((tag) => (
            <Badge
              key={tag.phone}
              variant={tag.isValid ? 'default' : 'destructive'}
              className={cn(
                'px-3 py-1.5 flex items-center gap-2 text-sm group relative',
                tag.isValid
                  ? 'bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800'
                  : 'bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800'
              )}
              title={tag.error}
            >
              {tag.isValid ? (
                <CheckCircle className="h-3 w-3" />
              ) : (
                <AlertCircle className="h-3 w-3" />
              )}
              <span>{tag.phone}</span>
              <button
                type="button"
                onClick={() => removePhoneNumber(tag.phone)}
                className="ml-1 rounded-full hover:bg-white/20 p-0.5 transition-colors"
              >
                <X className="h-3 w-3" />
              </button>

              {/* Tooltip for error */}
              {!tag.isValid && tag.error && (
                <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                  {tag.error}
                </span>
              )}
            </Badge>
          ))}
        </div>

        <Input
          type="text"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          onPaste={handlePaste}
          placeholder={value.length === 0 ? placeholder : 'Add more...'}
          className="border-0 focus-visible:ring-0 focus-visible:ring-offset-0 p-0 h-8"
        />
      </div>

      {/* Summary */}
      <div className="flex items-center justify-between text-sm">
        <div className="flex items-center gap-4">
          {validCount > 0 && (
            <span className="text-green-600 dark:text-green-400 flex items-center gap-1">
              <CheckCircle className="h-4 w-4" />
              {validCount} valid
            </span>
          )}
          {invalidCount > 0 && (
            <span className="text-red-600 dark:text-red-400 flex items-center gap-1">
              <AlertCircle className="h-4 w-4" />
              {invalidCount} invalid
            </span>
          )}
          {value.length === 0 && (
            <span className="text-muted-foreground">No recipients added</span>
          )}
        </div>
        <span className="text-muted-foreground">{value.length} total</span>
      </div>

      <p className="text-xs text-muted-foreground">
        💡 Press <kbd className="px-1.5 py-0.5 bg-muted rounded text-xs">Enter</kbd> to add • Paste
        multiple numbers (comma, newline, or space separated) • Invalid numbers will be highlighted
        in red
      </p>
    </div>
  );
}
