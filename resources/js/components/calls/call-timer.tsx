export interface CallTimerProps {
  duration: string;
}

export function CallTimer({ duration }: CallTimerProps) {
  return (
    <div className="text-center">
      <div className="text-4xl font-mono font-semibold text-slate-900 tabular-nums">
        {duration}
      </div>
      <div className="text-sm text-slate-500 mt-1">Call Duration</div>
    </div>
  );
}
