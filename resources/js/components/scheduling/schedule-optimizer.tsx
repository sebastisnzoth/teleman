import React, { useState } from 'react';
import { Sparkles, Calendar, Clock, Users, TrendingUp, RefreshCw } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import axios from 'axios';

interface ScheduleOptimizerProps {
  campaignId: number;
  onOptimized?: () => void;
}

export function ScheduleOptimizer({ campaignId, onOptimized }: ScheduleOptimizerProps) {
  const [optimizing, setOptimizing] = useState(false);
  const [result, setResult] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [autoApply, setAutoApply] = useState(false);
  const [smartSchedulingEnabled, setSmartSchedulingEnabled] = useState(false);

  const handleOptimize = async () => {
    try {
      setOptimizing(true);
      setError(null);

      const response = await axios.post(
        `/api/v1/campaigns/${campaignId}/optimize-schedule`,
        {
          apply: autoApply,
          limit: 100,
        }
      );

      if (response.data.success) {
        setResult(response.data.data);
        onOptimized?.();
      } else {
        setError('Failed to optimize schedule');
      }
    } catch (err: any) {
      console.error('Error optimizing schedule:', err);
      setError(err.response?.data?.message || 'Failed to optimize schedule');
    } finally {
      setOptimizing(false);
    }
  };

  const handleToggleSmartScheduling = async () => {
    try {
      const endpoint = smartSchedulingEnabled
        ? `/api/v1/campaigns/${campaignId}/disable-smart-scheduling`
        : `/api/v1/campaigns/${campaignId}/enable-smart-scheduling`;

      const response = await axios.post(endpoint);

      if (response.data.success) {
        setSmartSchedulingEnabled(!smartSchedulingEnabled);
      }
    } catch (err: any) {
      console.error('Error toggling smart scheduling:', err);
      setError(err.response?.data?.message || 'Failed to update setting');
    }
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Sparkles className="w-5 h-5 text-purple-500" />
              AI Schedule Optimizer
            </CardTitle>
            <CardDescription>
              Optimize call times for maximum answer rates
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Switch
              id="smart-scheduling"
              checked={smartSchedulingEnabled}
              onCheckedChange={handleToggleSmartScheduling}
            />
            <Label htmlFor="smart-scheduling" className="text-sm cursor-pointer">
              Auto-optimize
            </Label>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Controls */}
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <Switch
              id="auto-apply"
              checked={autoApply}
              onCheckedChange={setAutoApply}
            />
            <Label htmlFor="auto-apply" className="text-sm cursor-pointer">
              Apply changes automatically
            </Label>
          </div>
          <Button
            onClick={handleOptimize}
            disabled={optimizing}
            className="ml-auto"
          >
            {optimizing ? (
              <>
                <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                Optimizing...
              </>
            ) : (
              <>
                <TrendingUp className="w-4 h-4 mr-2" />
                Optimize Schedule
              </>
            )}
          </Button>
        </div>

        {/* Error Message */}
        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Results */}
        {result && (
          <div className="space-y-4">
            <Alert className="bg-green-50 border-green-200">
              <TrendingUp className="w-4 h-4 text-green-600" />
              <AlertDescription className="text-green-800">
                {result.total_updated > 0
                  ? `Successfully optimized ${result.total_updated} contacts with AI-predicted best times!`
                  : `Generated ${result.total_analyzed} recommendations (preview mode)`}
              </AlertDescription>
            </Alert>

            {/* Statistics */}
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div className="p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div className="flex items-center gap-2 mb-1">
                  <Users className="w-4 h-4 text-blue-600" />
                  <span className="text-xs text-blue-700">Contacts Analyzed</span>
                </div>
                <div className="text-2xl font-bold text-blue-900">
                  {result.total_analyzed}
                </div>
              </div>

              {result.total_updated > 0 && (
                <div className="p-4 bg-green-50 rounded-lg border border-green-200">
                  <div className="flex items-center gap-2 mb-1">
                    <Calendar className="w-4 h-4 text-green-600" />
                    <span className="text-xs text-green-700">Schedules Updated</span>
                  </div>
                  <div className="text-2xl font-bold text-green-900">
                    {result.total_updated}
                  </div>
                </div>
              )}

              <div className="p-4 bg-purple-50 rounded-lg border border-purple-200">
                <div className="flex items-center gap-2 mb-1">
                  <TrendingUp className="w-4 h-4 text-purple-600" />
                  <span className="text-xs text-purple-700">Expected Lift</span>
                </div>
                <div className="text-2xl font-bold text-purple-900">
                  +30-50%
                </div>
              </div>
            </div>

            {/* Sample Optimizations (first 5) */}
            {result.optimizations && result.optimizations.length > 0 && (
              <div>
                <h4 className="text-sm font-semibold text-gray-700 mb-2">
                  Sample Recommendations
                </h4>
                <div className="space-y-2">
                  {result.optimizations.slice(0, 5).map((opt: any, index: number) => (
                    <div
                      key={index}
                      className="p-3 bg-gray-50 rounded-lg border border-gray-200"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="font-medium text-sm text-gray-900">
                            {opt.phone_number}
                          </div>
                          <div className="flex items-center gap-2 mt-1">
                            <Clock className="w-3 h-3 text-gray-400" />
                            <span className="text-xs text-gray-600">
                              {new Date(opt.optimal_time).toLocaleString('en-US', {
                                weekday: 'short',
                                hour: 'numeric',
                                minute: '2-digit',
                              })}
                            </span>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="text-sm font-semibold text-blue-600">
                            {opt.confidence}%
                          </div>
                          <div className="text-xs text-gray-500">confidence</div>
                        </div>
                      </div>
                      {opt.reasoning && opt.reasoning.length > 0 && (
                        <div className="mt-2 text-xs text-gray-600 italic">
                          {opt.reasoning[0]}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
                {result.optimizations.length > 5 && (
                  <p className="text-xs text-gray-500 mt-2 text-center">
                    ... and {result.optimizations.length - 5} more contacts
                  </p>
                )}
              </div>
            )}
          </div>
        )}

        {/* Info */}
        {!result && (
          <Alert>
            <Sparkles className="w-4 h-4" />
            <AlertDescription className="text-sm">
              AI will analyze historical call patterns, timezone data, and industry best practices
              to predict the optimal time to call each contact. Expected improvement: 30-50% higher
              answer rates.
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}
