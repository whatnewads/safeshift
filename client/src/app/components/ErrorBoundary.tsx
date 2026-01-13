import React, { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle } from 'lucide-react';
import { Button } from './ui/button';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
    errorInfo: null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error, errorInfo: null };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Uncaught error:', error, errorInfo);
    this.setState({
      error,
      errorInfo,
    });
  }

  private handleReset = () => {
    this.setState({ hasError: false, error: null, errorInfo: null });
    window.location.href = '/dashboard';
  };

  public render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900 p-4">
          <div className="max-w-2xl w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg p-8">
            <div className="flex items-center gap-3 mb-6">
              <AlertTriangle className="h-8 w-8 text-red-600" />
              <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                Something went wrong
              </h1>
            </div>
            
            <p className="text-slate-600 dark:text-slate-400 mb-6">
              The application encountered an unexpected error. Please try refreshing the page or contact support if the problem persists.
            </p>

            {this.state.error && (
              <div className="bg-slate-100 dark:bg-slate-900 rounded-lg p-4 mb-6 overflow-auto">
                <p className="text-sm font-mono text-red-600 dark:text-red-400 mb-2">
                  {this.state.error.toString()}
                </p>
                {this.state.errorInfo && (
                  <details className="text-xs font-mono text-slate-600 dark:text-slate-400">
                    <summary className="cursor-pointer hover:text-slate-900 dark:hover:text-white">
                      Stack trace
                    </summary>
                    <pre className="mt-2 whitespace-pre-wrap">
                      {this.state.errorInfo.componentStack}
                    </pre>
                  </details>
                )}
              </div>
            )}

            <div className="flex gap-3">
              <Button onClick={this.handleReset}>
                Go to Dashboard
              </Button>
              <Button
                variant="outline"
                onClick={() => window.location.reload()}
              >
                Reload Page
              </Button>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
