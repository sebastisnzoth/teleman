import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { AlertCircle, AlertTriangle, Info } from 'lucide-react';

interface Issue {
    field: string;
    issue: string;
    severity: 'low' | 'medium' | 'high';
}

interface IssuesListProps {
    issues: Issue[];
}

export function IssuesList({ issues }: IssuesListProps) {
    if (issues.length === 0) {
        return (
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>No issues found. Contact data looks good!</AlertDescription>
            </Alert>
        );
    }

    const getSeverityIcon = (severity: string) => {
        switch (severity) {
            case 'high':
                return <AlertCircle className="h-4 w-4 text-red-500" />;
            case 'medium':
                return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
            default:
                return <Info className="h-4 w-4 text-blue-500" />;
        }
    };

    const getSeverityBadge = (severity: string) => {
        const configs = {
            high: 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
            medium: 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-500/20',
            low: 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20',
        };

        return configs[severity as keyof typeof configs] || configs.low;
    };

    return (
        <div className="space-y-2">
            <h4 className="text-sm font-semibold">Issues Found ({issues.length})</h4>
            <div className="space-y-2">
                {issues.map((issue, index) => (
                    <div
                        key={index}
                        className="flex items-start gap-3 p-3 rounded-md border bg-card"
                    >
                        {getSeverityIcon(issue.severity)}
                        <div className="flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-sm capitalize">{issue.field}</span>
                                <Badge variant="outline" className={getSeverityBadge(issue.severity)}>
                                    {issue.severity}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">{issue.issue}</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
