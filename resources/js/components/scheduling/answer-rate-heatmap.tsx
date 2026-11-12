import React, { useEffect, useState } from 'react';
import { TrendingUp, Clock, Calendar, RefreshCw, Lightbulb } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import axios from 'axios';

interface HourStat {
  hour: number;
  hour_12: string;
  answer_rate: number;
  total_calls: number;
  answered_calls: number;
}

interface DayStat {
  day: number;
  day_name: string;
  answer_rate: number;
  total_calls: number;
  answered_calls: number;
}

interface Recommendation {
  type: string;
  priority: string;
  message: string;
  data: any;
}

interface CampaignOptimalTimes {
  best_hours: HourStat[];
  best_days: DayStat[];
  worst_hours?: HourStat[];
  peak_answer_rate_hour?: HourStat;
  recommendations: Recommendation[];
}

interface AnswerRateHeatmapProps {
  campaignId: number;
  onOptimize?: () => void;
}

export function AnswerRateHeatmap({ campaignId, onOptimize }: AnswerRateHeatmapProps) {
  const [data, setData] = useState<CampaignOptimalTimes | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await axios.get(`/api/v1/campaigns/${campaignId}/optimal-times`);

      if (response.data.success) {
        setData(response.data.data);
      } else {
        setError('Failed to load answer rate patterns');
      }
    } catch (err: any) {
      console.error('Error fetching answer rate patterns:', err);
      setError(err.response?.data?.message || 'Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [campaignId]);

  const getHeatmapColor = (rate: number) => {
    if (rate >= 70) return 'bg-green-500';
    if (rate >= 50) return 'bg-green-400';
    if (rate >= 30) return 'bg-yellow-400';
    if (rate >= 10) return 'bg-orange-400';
    return 'bg-red-400';
  };

  const getHeatmapOpacity = (rate: number) => {
    if (rate >= 70) return 'opacity-100';
    if (rate >= 50) return 'opacity-80';
    if (rate >= 30) return 'opacity-60';
    if (rate >= 10) return 'opacity-40';
    return 'opacity-20';
  };

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Answer Rate Patterns</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-center py-8">
            <RefreshCw className="w-6 h-6 animate-spin text-blue-500" />
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  if (!data || data.best_hours.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Answer Rate Patterns</CardTitle>
          <CardDescription>No data available yet</CardDescription>
        </CardHeader>
        <CardContent>
          <Alert>
            <Lightbulb className="w-4 h-4" />
            <AlertDescription>
              Start making calls to see AI-powered answer rate patterns and optimize your schedule.
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Peak Performance Card */}
      {data.peak_answer_rate_hour && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <TrendingUp className="w-5 h-5 text-green-500" />
                Peak Performance Time
              </CardTitle>
              <Button variant="ghost" size="sm" onClick={fetchData}>
                <RefreshCw className="w-4 h-4" />
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg border border-green-200">
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-3xl font-bold text-gray-900">
                    {data.peak_answer_rate_hour.hour_12}
                  </div>
                  <div className="text-sm text-gray-600 mt-1">
                    {data.peak_answer_rate_hour.answer_rate}% answer rate
                  </div>
                  <div className="text-xs text-gray-500 mt-1">
                    {data.peak_answer_rate_hour.answered_calls} of{' '}
                    {data.peak_answer_rate_hour.total_calls} calls answered
                  </div>
                </div>
                {onOptimize && (
                  <Button onClick={onOptimize}>
                    Optimize Schedule
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Hourly Heatmap */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="w-5 h-5" />
            Answer Rate by Hour
          </CardTitle>
          <CardDescription>Performance throughout the day</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {data.best_hours.slice(0, 10).map((hour) => (
              <div key={hour.hour} className="flex items-center gap-3">
                <div className="w-16 text-sm font-medium text-gray-700">
                  {hour.hour_12}
                </div>
                <div className="flex-1 h-10 relative bg-gray-100 rounded-lg overflow-hidden">
                  <div
                    className={`h-full ${getHeatmapColor(hour.answer_rate)} ${getHeatmapOpacity(
                      hour.answer_rate
                    )} transition-all`}
                    style={{ width: `${hour.answer_rate}%` }}
                  />
                  <div className="absolute inset-0 flex items-center justify-between px-3">
                    <span className="text-xs font-semibold text-gray-900">
                      {hour.answer_rate}%
                    </span>
                    <span className="text-xs text-gray-600">
                      {hour.answered_calls}/{hour.total_calls} calls
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Legend */}
          <div className="mt-4 pt-4 border-t">
            <div className="flex items-center gap-4 text-xs">
              <span className="text-gray-600">Answer Rate:</span>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 bg-red-400 rounded"></div>
                <span>&lt;10%</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 bg-orange-400 rounded"></div>
                <span>10-30%</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 bg-yellow-400 rounded"></div>
                <span>30-50%</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 bg-green-400 rounded"></div>
                <span>50-70%</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 bg-green-500 rounded"></div>
                <span>70%+</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Day of Week Performance */}
      {data.best_days.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="w-5 h-5" />
              Answer Rate by Day
            </CardTitle>
            <CardDescription>Best days of the week to call</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              {data.best_days.map((day) => (
                <div
                  key={day.day}
                  className="p-4 bg-gray-50 rounded-lg border border-gray-200 hover:border-blue-300 transition-colors"
                >
                  <div className="text-lg font-semibold text-gray-900">{day.day_name}</div>
                  <div className="mt-2">
                    <div className="text-2xl font-bold text-blue-600">{day.answer_rate}%</div>
                    <div className="text-xs text-gray-500 mt-1">
                      {day.answered_calls} of {day.total_calls} calls
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* AI Recommendations */}
      {data.recommendations.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lightbulb className="w-5 h-5 text-yellow-500" />
              AI Recommendations
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {data.recommendations.map((rec, index) => (
                <Alert key={index} className={rec.priority === 'high' ? 'border-yellow-300 bg-yellow-50' : ''}>
                  <AlertDescription className="text-sm">
                    <span className="font-semibold capitalize">{rec.type}:</span> {rec.message}
                  </AlertDescription>
                </Alert>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
