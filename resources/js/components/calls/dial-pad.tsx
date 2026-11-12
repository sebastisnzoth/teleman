import { Button } from '@/components/ui/button';

export interface DialPadProps {
  onDigitClick: (digit: string) => void;
  disabled?: boolean;
}

const digits = [
  { digit: '1', letters: '' },
  { digit: '2', letters: 'ABC' },
  { digit: '3', letters: 'DEF' },
  { digit: '4', letters: 'GHI' },
  { digit: '5', letters: 'JKL' },
  { digit: '6', letters: 'MNO' },
  { digit: '7', letters: 'PQRS' },
  { digit: '8', letters: 'TUV' },
  { digit: '9', letters: 'WXYZ' },
  { digit: '*', letters: '' },
  { digit: '0', letters: '+' },
  { digit: '#', letters: '' },
];

export function DialPad({ onDigitClick, disabled = false }: DialPadProps) {
  return (
    <div className="grid grid-cols-3 gap-2 w-full max-w-xs mx-auto">
      {digits.map(({ digit, letters }) => (
        <Button
          key={digit}
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled}
          onClick={() => onDigitClick(digit)}
          className="h-12 flex flex-col items-center justify-center hover:bg-slate-100 active:bg-slate-200"
        >
          <span className="text-xl font-medium">{digit}</span>
          {letters && (
            <span className="text-[10px] text-slate-500 mt-0.5">{letters}</span>
          )}
        </Button>
      ))}
    </div>
  );
}
