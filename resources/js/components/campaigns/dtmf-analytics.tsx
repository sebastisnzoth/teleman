import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BarChart3, TrendingUp, Users, Phone } from 'lucide-react';

interface DtmfStat {
  digit: string;
  label: string;
  count: number;
  percentage: number;
}

interface DtmfAnalyticsProps {
  dtmfResponses: {
    total: number;
    by_digit: DtmfStat[];
    by_action: Array<{
      action: string;
      count: number;
      percentage: number;
    }>;
  };
  totalCalls: number;
}

export function DtmfAnalytics({ dtmfResponses, totalCalls }: DtmfAnalyticsProps) {
  if (!dtmfResponses || dtmfResponses.total === 0) {
    return null;
  }

  const responseRate = totalCalls > 0 ? ((dtmfResponses.total / totalCalls) * 100).toFixed(1) : '0';

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Phone className="h-5 w-5" />
              DTMF Response Analytics
            </CardTitle>
            <CardDescription>
              Interactive keypad responses from callers
            </CardDescription>
          </div>
          <Badge variant="secondary" className="text-lg px-4 py-2">
            {dtmfResponses.total} Responses
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Summary Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-blue-900">Total Responses</p>
                <p className="text-2xl font-bold text-blue-600">{dtmfResponses.total}</p>
              </div>
              <Users className="h-8 w-8 text-blue-400" />
            </div>
          </div>

          <div className="bg-green-50 border border-green-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-green-900">Response Rate</p>
                <p className="text-2xl font-bold text-green-600">{responseRate}%</p>
              </div>
              <TrendingUp className="h-8 w-8 text-green-400" />
            </div>
          </div>

          <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-purple-900">Unique Keys</p>
                <p className="text-2xl font-bold text-purple-600">{dtmfResponses.by_digit.length}</p>
              </div>
              <BarChart3 className="h-8 w-8 text-purple-400" />
            </div>
          </div>
        </div>

        {/* Responses by Digit */}
        {dtmfResponses.by_digit.length > 0 && (
          <div>
            <h4 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <BarChart3 className="h-4 w-4" />
              Responses by Key Pressed
            </h4>
            <div className="space-y-2">
              {dtmfResponses.by_digit.map((stat) => (
                <div key={stat.digit} className="flex items-center gap-3">
                  <div className="flex-shrink-0 w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center">
                    <span className="text-lg font-bold text-slate-700">{stat.digit}</span>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium">{stat.label || `Key ${stat.digit}`}</span>
                      <span className="text-sm text-muted-foreground">
                        {stat.count} ({stat.percentage.toFixed(1)}%)
                      </span>
                    </div>
                    <div className="w-full bg-slate-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full transition-all"
                        style={{ width: `${stat.percentage}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Responses by Action */}
        {dtmfResponses.by_action && dtmfResponses.by_action.length > 0 && (
          <div>
            <h4 className="text-sm font-semibold mb-3">Actions Taken</h4>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
              {dtmfResponses.by_action.map((action) => (
                <div key={action.action} className="border rounded-lg p-3">
                  <div className="text-xs text-muted-foreground uppercase mb-1">
                    {action.action.replace('_', ' ')}
                  </div>
                  <div className="text-xl font-bold">{action.count}</div>
                  <div className="text-xs text-muted-foreground">
                    {action.percentage.toFixed(1)}% of responses
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Insights */}
        {dtmfResponses.by_digit.length > 0 && (
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <h4 className="text-sm font-semibold text-amber-900 mb-2">💡 Insights</h4>
            <ul className="text-sm text-amber-800 space-y-1">
              {dtmfResponses.by_digit[0] && (
                <li>
                  • Most popular option: <strong>Key {dtmfResponses.by_digit[0].digit}</strong> ({dtmfResponses.by_digit[0].label}) with {dtmfResponses.by_digit[0].count} responses
                </li>
              )}
              <li>
                • <strong>{responseRate}%</strong> of callers interacted with your campaign
              </li>
              {parseFloat(responseRate) > 50 && (
                <li className="text-green-700">
                  • 🎉 Excellent engagement! Over half of your callers responded
                </li>
              )}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
