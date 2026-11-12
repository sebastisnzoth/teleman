import React, { useEffect, useState } from 'react';
import { Clock, Calendar, TrendingUp, MapPin, Sparkles, RefreshCw } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';

interface OptimalTimeRecommendation {
  optimal_time: string;
  local_time: string;
  day_of_week: string;
  confidence: number;
  timezone: string;
  reasoning: string[];
  alternative_times: Array<{
    time: string;
    local_time: string;
    day: string;
  }>;
}

interface BestTimeRecommendationProps {
  contactId?: number;
  campaignContactId?: number;
  phoneNumber?: string;
  onSchedule?: (time: string) => void;
  showAlternatives?: boolean;
}

export function BestTimeRecommendation({
  contactId,
  campaignContactId,
  phoneNumber,
  onSchedule,
  showAlternatives = true,
}: BestTimeRecommendationProps) {
  const [recommendation, setRecommendation] = useState<OptimalTimeRecommendation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRecommendation = async () => {
    if (!contactId && !campaignContactId) {
      setError('Contact ID or Campaign Contact ID is required');
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      
      const response = await axios.get(
        `/api/v1/contacts/${contactId || campaignContactId}/optimal-time`
      );

      if (response.data.success) {
        setRecommendation(response.data.data);
      } else {
        setError('Failed to get recommendation');
      }
    } catch (err: any) {
      console.error('Error fetching optimal time:', err);
      setError(err.response?.data?.message || 'Failed to get optimal call time');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRecommendation();
  }, [contactId, campaignContactId]);

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 80) return 'bg-green-500';
    if (confidence >= 60) return 'bg-yellow-500';
    return 'bg-orange-500';
  };

  const getConfidenceLabel = (confidence: number) => {
    if (confidence >= 80) return 'High Confidence';
    if (confidence >= 60) return 'Medium Confidence';
    return 'Low Confidence';
  };

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="w-5 h-5" />
            Best Time to Call
          </CardTitle>
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

  if (!recommendation) {
    return null;
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-yellow-500" />
            AI-Optimized Call Time
          </CardTitle>
          <Button
            variant="ghost"
            size="sm"
            onClick={fetchRecommendation}
            className="h-8 w-8 p-0"
          >
            <RefreshCw className="w-4 h-4" />
          </Button>
        </div>
        <CardDescription>
          AI-predicted best time to reach this contact
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Main Recommendation */}
        <div className="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
          <div className="flex items-start justify-between mb-3">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <Clock className="w-5 h-5 text-blue-600" />
                <span className="text-2xl font-bold text-gray-900">
                  {recommendation.local_time}
                </span>
              </div>
              <div className="flex items-center gap-2 text-sm text-gray-600">
                <Calendar className="w-4 h-4" />
                <span>{recommendation.day_of_week}</span>
              </div>
              <div className="flex items-center gap-2 text-sm text-gray-600 mt-1">
                <MapPin className="w-4 h-4" />
                <span className="capitalize">{recommendation.timezone} Time</span>
              </div>
            </div>
            <div className="flex flex-col items-end gap-2">
              <Badge className={`${getConfidenceColor(recommendation.confidence)} text-white`}>
                {recommendation.confidence}% {getConfidenceLabel(recommendation.confidence)}
              </Badge>
              {onSchedule && (
                <Button
                  size="sm"
                  onClick={() => onSchedule(recommendation.optimal_time)}
                  className="mt-2"
                >
                  Schedule Now
                </Button>
              )}
            </div>
          </div>

          {/* Reasoning */}
          <div className="mt-3 pt-3 border-t border-blue-200">
            <p className="text-xs font-semibold text-gray-700 mb-2">Why this time?</p>
            <ul className="space-y-1">
              {recommendation.reasoning.map((reason, index) => (
                <li key={index} className="text-xs text-gray-600 flex items-start gap-2">
                  <TrendingUp className="w-3 h-3 mt-0.5 text-blue-500 flex-shrink-0" />
                  <span>{reason}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* Alternative Times */}
        {showAlternatives && recommendation.alternative_times.length > 0 && (
          <div>
            <h4 className="text-sm font-semibold text-gray-700 mb-2">Alternative Times</h4>
            <div className="grid grid-cols-1 gap-2">
              {recommendation.alternative_times.map((alt, index) => (
                <div
                  key={index}
                  className="p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors cursor-pointer"
                  onClick={() => onSchedule?.(alt.time)}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Clock className="w-4 h-4 text-gray-400" />
                      <span className="font-medium text-gray-900">{alt.local_time}</span>
                      <span className="text-sm text-gray-500">{alt.day}</span>
                    </div>
                    {onSchedule && (
                      <Button variant="ghost" size="sm" className="h-7 text-xs">
                        Use This
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Time Until Call */}
        <div className="text-xs text-gray-500 text-center pt-2 border-t">
          Optimal time is{' '}
          {formatDistanceToNow(new Date(recommendation.optimal_time), { addSuffix: true })}
        </div>
      </CardContent>
    </Card>
  );
}
