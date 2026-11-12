import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface QualityBadgeProps {
    score: number;
    showScore?: boolean;
    className?: string;
}

export function QualityBadge({ score, showScore = true, className }: QualityBadgeProps) {
    const getQualityConfig = (score: number) => {
        if (score >= 80) {
            return {
                label: 'High Quality',
                color: 'bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20',
            };
        } else if (score >= 60) {
            return {
                label: 'Medium Quality',
                color: 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-500/20',
            };
        } else {
            return {
                label: 'Low Quality',
                color: 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
            };
        }
    };

    const config = getQualityConfig(score);

    return (
        <Badge variant="outline" className={cn(config.color, className)}>
            {config.label}
            {showScore && ` (${score}/100)`}
        </Badge>
    );
}
