import React, { Component, type ErrorInfo, type ReactNode } from 'react';
import * as Sentry from '@sentry/react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

/**
 * ErrorBoundary catches JavaScript errors in child components and renders a fallback UI
 * instead of crashing the entire page.
 */
export class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        console.error('ErrorBoundary caught an error:', error, errorInfo);
        // Report to Sentry if initialized
        try {
            Sentry.captureException(error, { extra: { componentStack: errorInfo.componentStack } });
        } catch {
            // Sentry may not be initialized in all environments
        }
    }

    render(): ReactNode {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div role="alert" className="flex min-h-[200px] items-center justify-center rounded-lg border border-destructive/20 bg-destructive/5 p-6">
                    <div className="text-center">
                        <h3 className="text-lg font-semibold text-destructive">Something went wrong</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            An unexpected error occurred. Please try refreshing the page.
                        </p>
                        <button
                            type="button"
                            onClick={() => this.setState({ hasError: false, error: null })}
                            className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            aria-label="Try again"
                        >
                            Try Again
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
